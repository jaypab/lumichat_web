<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentRepository implements StudentRepositoryInterface
{
    public function all(): Collection
    {
        return User::query()
            ->where('role', 'student')
            ->orderBy('name')
            ->get();
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $q = User::query()->where('role', 'student');

        if (!empty($filters['year']) || $filters['year'] === '0') {
            $q->where('year_level', $filters['year']);
        }

        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $like = "%{$term}%";
            $q->where(function (Builder $sub) use ($like) {
                $sub->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('contact_number', 'like', $like)
                    ->orWhere('course', 'like', $like)
                    ->orWhere('year_level', 'like', $like);
            });
        }

        return $q->orderBy('name')
                 ->paginate($perPage)
                 ->withQueryString();
    }

    public function findById(int $id, array $with = []): ?object
    {
        return User::with($with)->where('role', 'student')->find($id);
    }

    public function create(array $data): object
    {
        $data['role'] = $data['role'] ?? 'student';
        return User::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $m = User::where('role', 'student')->findOrFail($id);
        return $m->update($data);
    }

    public function delete(int $id): bool
    {
        $m = User::where('role', 'student')->findOrFail($id);
        return (bool) $m->delete();
    }

    public function distinctYearLevels(): array
    {
        return User::query()
            ->where('role', 'student')
            ->whereNotNull('year_level')
            ->distinct()
            ->orderBy('year_level')
            ->pluck('year_level')
            ->toArray();
    }
}
