<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixCounselorLogsCommonDxSeeder extends Seeder
{
    public function run(): void
    {
        $rows = DB::table('tbl_counselor_logs')->select('id','common_dx')->get();
        $fixed = 0;

        foreach ($rows as $r) {
            $val = $r->common_dx;
            if ($val === null || $val === '') continue;

            // Already a proper JSON array?
            $decoded = is_string($val) ? json_decode($val, true) : $val;
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // ensure it's a plain array of strings (take keys if assoc)
                $arr = array_values(array_keys($decoded) !== range(0, count($decoded)-1)
                    ? array_keys($decoded)
                    : $decoded);
            } else {
                // try to rescue bracketed string
                $raw = trim((string)$val);
                if (preg_match('/^\s*\[.*\]\s*$/', $raw)) {
                    $try = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($try)) {
                        $arr = $try;
                    } else {
                        $raw = trim($raw, "[]");
                        $arr = array_map(
                            fn($v)=>trim($v, " \t\n\r\0\x0B\"'"),
                            array_filter(array_map('trim', explode(',', $raw)))
                        );
                    }
                } else {
                    $arr = [trim($raw)];
                }
            }

            // Clean + dedupe
            $arr = array_values(array_unique(array_filter(array_map(
                fn($v)=>trim((string)$v, " \t\n\r\0\x0B\"'"),
                $arr
            ))));

            DB::table('tbl_counselor_logs')
                ->where('id', $r->id)
                ->update(['common_dx' => json_encode($arr, JSON_UNESCAPED_UNICODE)]);
            $fixed++;
        }

        $this->command->info("Normalized {$fixed} counselor log row(s).");
    }
}