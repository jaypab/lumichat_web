<?php

namespace App\Repositories\Eloquent;

use App\Models\DiagnosisReport;
use App\Repositories\Contracts\DiagnosisReportRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DiagnosisReportRepository implements DiagnosisReportRepositoryInterface
{
    /* ===================== CRUD ===================== */

    public function all(): Collection
    {
        return DiagnosisReport::orderByDesc('created_at')->get();
    }

    public function findById(int $id, array $with = []): ?object
    {
        return DiagnosisReport::with($with)->find($id);
    }

    public function create(array $data): object
    {
        return DiagnosisReport::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $m = DiagnosisReport::findOrFail($id);
        return (bool) $m->update($data);
    }

    public function delete(int $id): bool
    {
        $m = DiagnosisReport::findOrFail($id);
        return (bool) $m->delete();
    }

    /* ============= Admin index listing ============= */

    public function paginateWithFilters(string $dateKey = 'all', string $q = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = DiagnosisReport::query()
            ->with([
                'student:id,name,email',
                'counselor',          // no column list to avoid unknown columns
            ]);

        // date filter
        if ($dateKey !== 'all') {
            $query = match ($dateKey) {
                '7d'    => $query->where('created_at', '>=', now()->subDays(7)),
                '30d'   => $query->where('created_at', '>=', now()->subDays(30)),
                'month' => $query->whereYear('created_at', now()->year)
                                 ->whereMonth('created_at', now()->month),
                default => $query,
            };
        }

        // free-text search
        $q = trim($q);
        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function (Builder $sub) use ($q, $like) {
                if (ctype_digit($q)) {
                    $sub->orWhere('id', (int) $q);
                }
                $sub->orWhere('diagnosis_result', 'like', $like)
                    ->orWhereHas('student', function (Builder $qq) use ($like) {
                        $qq->where('name', 'like', $like)
                           ->orWhere('email', 'like', $like);
                    })
                    // counselors table has 'name' (not 'full_name')
                    ->orWhereHas('counselor', function (Builder $qq) use ($like) {
                        $qq->where('name', 'like', $like);
                    });
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /* ============= Admin show page ============= */

    public function findWithRelations(int $id, array $with): ?object
    {
        return DiagnosisReport::with($with)->find($id);
    }
}
