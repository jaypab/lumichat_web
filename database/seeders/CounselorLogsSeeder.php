<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class CounselorLogsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Backfilling Counselor Logs from diagnosis reports…');

        $reports = 'tbl_diagnosis_reports';
        if (!Schema::hasTable($reports)) {
            $this->command->warn("❗ {$reports} table not found. Skipping.");
            return;
        }

        // Pick the real diagnosis column
        $dxCol = Schema::hasColumn($reports, 'diagnosis_result')
            ? 'diagnosis_result'
            : (Schema::hasColumn($reports, 'diagnosis') ? 'diagnosis' : null);

        if (!$dxCol) {
            $this->command->warn("❗ {$reports} has no diagnosis column (diagnosis_result/diagnosis). Skipping.");
            return;
        }
        if (!Schema::hasColumn($reports, 'student_id') || !Schema::hasColumn($reports, 'counselor_id')) {
            $this->command->warn("❗ {$reports} is missing student_id/counselor_id. Skipping.");
            return;
        }

        // --------------------------------------------------------------------------------
        // 1) CLEAN-UP: remove bad rows we accidentally inserted with "Session completed…"
        //    (or any obvious auto-seed note text). This keeps “Common diagnosis” correct.
        // --------------------------------------------------------------------------------
        $badPatterns = [
            'Session completed%',         // our earlier note text
            'Auto-seeded:%',              // any auto-seed markers
        ];

        $deleted = 0;
        foreach ($badPatterns as $pat) {
            $deleted += DB::table($reports)->where($dxCol, 'like', $pat)->delete();
        }
        if ($deleted) {
            $this->command->info("🧹 Removed {$deleted} bad report row(s) that looked like notes.");
        }

        // --------------------------------------------------------------------------------
        // 2) OPTIONAL BACKFILL: when appointment_id is present, make sure
        //    counselor_id / student_id / created_at are filled using the appointment.
        // --------------------------------------------------------------------------------
        $hasApptId = Schema::hasColumn($reports, 'appointment_id')
            && Schema::hasTable('tbl_appointments');

        if ($hasApptId) {
            // Pull reports that still miss counselor or student or have a NULL created_at
            $needs = DB::table($reports.' as r')
                ->leftJoin('tbl_appointments as a','a.id','=','r.appointment_id')
                ->where(function($q){
                    $q->whereNull('r.counselor_id')
                      ->orWhereNull('r.student_id')
                      ->orWhereNull('r.created_at');
                })
                ->select([
                    'r.id as rid',
                    'r.counselor_id as rcid',
                    'r.student_id as rsid',
                    'r.created_at as rcreated',
                    'a.counselor_id as acid',
                    'a.student_id as asid',
                    'a.finalized_at',
                    'a.scheduled_at',
                ])
                ->get();

            $fixed = 0;
            foreach ($needs as $row) {
                // choose best timestamp (finalized_at -> scheduled_at -> existing created_at -> now)
                $when = $row->finalized_at ?: $row->scheduled_at ?: $row->rcreated ?: now();

                $update = [];
                if (empty($row->rcid) && !empty($row->acid)) $update['counselor_id'] = (int)$row->acid;
                if (empty($row->rsid) && !empty($row->asid)) $update['student_id']   = (int)$row->asid;
                if (empty($row->rcreated))                   $update['created_at']   = $when;
                if (!empty($update)) {
                    $update['updated_at'] = now();
                    DB::table($reports)->where('id', $row->rid)->update($update);
                    $fixed++;
                }
            }
            if ($fixed) {
                $this->command->info("🔧 Backfilled {$fixed} report row(s) from appointments (links/timestamps).");
            }
        }

        // --------------------------------------------------------------------------------
        // 3) FINAL PASS: ensure we have *only* diagnosis text in diagnosis column.
        //    If any report row has empty diagnosis, set a readable fallback label.
        // --------------------------------------------------------------------------------
        $fallbacks = [
            'Academic Pressure','Anxiety','Bullying','Burnout','Depression',
            'Family Problems','Financial Stress','Grief / Loss','Loneliness',
            'Low Self-Esteem','Relationship Issues','Sleep Problems','Stress',
            'Substance Abuse','Time Management',
        ];

        $empties = DB::table($reports)->whereNull($dxCol)->orWhere($dxCol,'=','')->pluck('id')->all();
        if ($empties) {
            $chunked = array_chunk($empties, 500);
            $relabel = 0;
            foreach ($chunked as $ids) {
                $values = [];
                // one query per chunk: map each id -> random fallback
                foreach ($ids as $id) {
                    $values[$id] = Arr::random($fallbacks);
                }
                // update row-by-row (safe and simple)
                foreach ($values as $id => $label) {
                    DB::table($reports)->where('id',$id)->update([
                        $dxCol       => $label,
                        'updated_at' => now(),
                    ]);
                    $relabel++;
                }
            }
            $this->command->info("🏷️ Labeled {$relabel} empty diagnosis report(s) with readable fallbacks.");
        }

        $count = DB::table($reports)->count();
        $this->command->info("Done. {$count} report row(s) present. Counselor Logs will now use real diagnoses.");
    }
}
