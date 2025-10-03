<?php

namespace App\Repositories\Eloquent;

use App\Models\Counselor;
use App\Repositories\Contracts\CounselorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CounselorRepository implements CounselorRepositoryInterface
{
    public function all(): Collection
    {
        return Counselor::orderBy('last_name')->orderBy('first_name')->get();
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $q = Counselor::query();

        // include ordered availabilities if requested
        if (!empty($filters['with_availabilities_ordered'])) {
            $q->with(['availabilities' => function ($sub) {
                $sub->orderBy('weekday')->orderBy('start_time');
            }]);
        }

        // search
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function (Builder $b) use ($term) {
                $b->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('employee_no', 'like', "%{$term}%");
            });
        }

        // active flag
        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $q->where('is_active', (bool)$filters['active']);
        }

        return $q->latest()->paginate($perPage)->withQueryString();
    }

    public function findById(int $id, array $with = []): ?object
    {
        // Always eager-load availabilities unless overridden
        $with = !empty($with) ? $with : ['availabilities'];

        $c = Counselor::with($with)->find($id);

        // If availabilities are loaded, sort them by weekday then start_time
        if ($c && $c->relationLoaded('availabilities')) {
            $sorted = $c->availabilities
                ->sortBy(fn ($s) => sprintf('%02d-%s', (int)$s->weekday, (string)$s->start_time))
                ->values();
            $c->setRelation('availabilities', $sorted);
        }

        return $c;
    }

    public function create(array $data): object
    {
        return DB::transaction(function () use ($data) {
            // prevent mass-assignment of 'availability' array
            $payload = Arr::only($data, ['name', 'email', 'phone', 'is_active', 'employee_no']);

            /** @var \App\Models\Counselor $c */
            $c = Counselor::create($payload);

            if (!empty($data['availability']) && is_array($data['availability'])) {
                foreach ($data['availability'] as $slot) {
                    // expect: weekday(int 0..6), start_time(H:i), end_time(H:i)
                    $c->availabilities()->create(Arr::only($slot, ['weekday', 'start_time', 'end_time']));
                }
            }

            return $c->fresh(['availabilities']);
        });
    }

    public function update(int $id, array $data): bool
    {
        return (bool) DB::transaction(function () use ($id, $data) {
            /** @var \App\Models\Counselor $c */
            $c = Counselor::findOrFail($id);

            $payload = Arr::only($data, ['name', 'email', 'phone', 'is_active', 'employee_no']);
            $updated = $c->update($payload);

            if (array_key_exists('availability', $data)) {
                // Replace all slots if the key is present (even if empty array)
                $c->availabilities()->delete();
                if (is_array($data['availability'])) {
                    foreach ($data['availability'] as $slot) {
                        $c->availabilities()->create(Arr::only($slot, ['weekday', 'start_time', 'end_time']));
                    }
                }
            }

            return $updated;
        });
    }

    public function delete(int $id): bool
    {
        $c = Counselor::findOrFail($id);
        return (bool) $c->delete();
    }
}
