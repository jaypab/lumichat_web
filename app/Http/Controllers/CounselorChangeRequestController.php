<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Notifications\CounselorReassignmentRequested;

class CounselorChangeRequestController extends Controller
{
    private const CUTOFF_HOURS = 24;      // lock window before start
    private const MAX_PER_APPT = 1;       // student can ask once (admin can override later)

    public function store(Request $request, int $id)
    {
        // 1) Validate
        $request->validate([
            'reason_code'             => ['required','in:uncomfortable,language,schedule,conflict,other'],
            'reason_text'             => ['required','string','min:8','max:300'],
            'preference_counselor_id' => ['nullable','integer','min:1'],
        ]);

        $studentId   = Auth::id();
        $reasonCode  = (string) $request->input('reason_code');
        $reasonText  = $this->cleanReason((string) $request->input('reason_text'));
        $prefCounsel = $request->filled('preference_counselor_id')
            ? (int) $request->input('preference_counselor_id')
            : null;

        // 2) Load appointment owned by student
        $ap = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->select('a.*', 'c.id as curr_counselor_id')
            ->where('a.id', $id)
            ->where('a.student_id', $studentId)
            ->first();

        if (!$ap) {
            return back()->withErrors(['error' => 'Appointment not found.']);
        }
        if (empty($ap->curr_counselor_id)) {
            return back()->withErrors(['error' => 'A counselor has not been assigned yet.']);
        }
        if (in_array(strtolower((string)$ap->status), ['canceled','no_show','completed'], true)) {
            return back()->withErrors(['error' => 'This appointment can no longer be changed.']);
        }

        // 3) Time cutoff
        $start = Carbon::parse($ap->scheduled_at);
        if ($start->lte(now())) {
            return back()->withErrors(['error' => 'This appointment has started or passed.']);
        }
        if ($start->diffInHours(now()) < self::CUTOFF_HOURS) {
            return back()->withErrors([
                'error' => "Requests are locked within ".self::CUTOFF_HOURS." hours of the session. Please contact the admin.",
            ]);
        }

        // 4) Only one request per appointment
        $already = DB::table('counselor_change_requests')
            ->where('appointment_id', $ap->id)
            ->whereIn('status', ['requested','approved','declined'])
            ->count();
        if ($already >= self::MAX_PER_APPT) {
            return back()->withErrors(['error' => 'You already submitted a counselor change for this appointment.']);
        }

        // 5) Transaction
        DB::transaction(function () use ($ap, $studentId, $reasonCode, $reasonText, $prefCounsel) {

            // Insert request (omit columns your table doesn’t have)
            $payload = [
                'appointment_id'          => $ap->id,
                'requested_by_student_id' => $studentId,
                'current_counselor_id'    => $ap->curr_counselor_id,
                'reason_code'             => $reasonCode,
                'reason_text'             => $reasonText,
                'status'                  => 'requested',
                'created_at'              => now(),
                'updated_at'              => now(),
            ];
            if ($prefCounsel) {
                $payload['preference_counselor_id'] = $prefCounsel;
            }
            // only set preference_traits if column exists
            if (\Schema::hasColumn('counselor_change_requests', 'preference_traits')) {
                $payload['preference_traits'] = null;
            }
            DB::table('counselor_change_requests')->insert($payload);

            // Tag appointment if columns exist
            $apptUpdate = ['updated_at' => now()];
            if (\Schema::hasColumn('tbl_appointments', 'cr_status')) {
                $apptUpdate['cr_status'] = 'requested';
            }
            if (\Schema::hasColumn('tbl_appointments', 'cr_created_at')) {
                $apptUpdate['cr_created_at'] = now();
            }
            if (count($apptUpdate) > 0) {
                DB::table('tbl_appointments')->where('id', $ap->id)->update($apptUpdate);
            }
        });

        // 6) Notify current counselor (best effort)
        try {
            $cUser = null;
            $cUserId = DB::table('tbl_counselors')
                ->where('id', $ap->curr_counselor_id)
                ->value('user_id');

            if ($cUserId) {
                $cUser = \App\Models\User::find($cUserId);
            } elseif (\Schema::hasColumn('tbl_counselors', 'email')) {
                $cEmail = DB::table('tbl_counselors')
                    ->where('id', $ap->curr_counselor_id)
                    ->value('email');
                if ($cEmail) {
                    $cUser = \App\Models\User::where('email', $cEmail)->first();
                }
            }

            if ($cUser) {
                // Use a simple array payload if your Notification class differs
                $payload = [
                    'type'           => 'cr.request',
                    'title'          => 'Reassignment requested',
                    'body'           => 'Student requested counselor reassignment • '.(Auth::user()->name ?? 'Student'),
                    'reason'         => $reasonText,
                    'appointment_id' => $ap->id,
                    'url'            => route('counselor.appointments.show', $ap->id),
                ];
                // If you already created a Notification class, call that instead.
                $cUser->notify(new \App\Notifications\GenericArrayNotification($payload));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // 7) Notify student (optional)
        try {
            app(\App\Support\Notify::class)::student(
                $studentId,
                'Change request submitted',
                'Your counselor change request is under admin review. We’ll notify you once a decision is made.'
            );
        } catch (\Throwable $e) {}

        // 8) Safe redirect: fall back to a known route if appointment.view is missing
        $redirectRoute = \Route::has('appointment.view')
            ? route('appointment.view', $ap->id)
            : url('/appointments/'.$ap->id);

        return redirect($redirectRoute)
            ->with('success', 'Request submitted. The admin will review it and notify you of the outcome.');
    }
    
    private function cleanReason(string $raw): string
    {
        // strip tags and collapse whitespace
        $s = strip_tags($raw);
        $s = preg_replace('/\s+/u', ' ', $s);
        // optionally block urls/emails
        if (preg_match('/https?:\/\/|www\.|@[a-z0-9._-]+/i', $s)) {
            // you can throw a validation exception instead
            $s = preg_replace('/https?:\/\/\S+|www\.\S+|[^\s]+@[^\s]+/i', '[redacted]', $s);
        }
        // allow letters, numbers, common punctuation
        $s = preg_replace('/[^\p{L}\p{N}\s\.,;:!?\-\(\)\'"]/u', '', $s);
        return trim(mb_substr($s, 0, 300));
    }
}
