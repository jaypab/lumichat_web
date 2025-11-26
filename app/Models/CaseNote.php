<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Artisan;  
class CaseNote extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_case_notes';

    protected $fillable = [
        'appointment_id','counselor_id','student_id',
        'student_name','note_date','program_year','address',
        'presenting_problem','observations','interventions','response','plan_followup',
        'emergency_contact_person','emergency_relationship','emergency_contact_no','emergency_address',
        'created_by','updated_by',
    ];

    protected $casts = [
        'note_date' => 'date',
    ];

    public function appointment() { return $this->belongsTo(\App\Models\Appointment::class, 'appointment_id'); }

    protected static function booted()
    {
        // Whenever a case note is created or updated
        static::saved(function (CaseNote $note) {
            // Rebuild course analytics from case notes
            Artisan::call('analytics:rebuild-courses');
        });

        // Whenever a case note is soft-deleted / force-deleted
        static::deleted(function (CaseNote $note) {
            Artisan::call('analytics:rebuild-courses');
        });
    }
    
    /**
     * Create or update the case note for an appointment (including walk-ins).
     */
    public function storeCaseNote(Request $request, $id)
    {
        // 1) Load the appointment – works for booked + walk-in
        $appointment = Appointment::findOrFail($id);
        $userId      = Auth::id();

        // 2) Validate case note fields
        $data = $request->validate([
            'note_date'                => ['nullable', 'date'],
            'program_year'             => ['nullable', 'string', 'max:255'],
            'address'                  => ['nullable', 'string'],
            'presenting_problem'       => ['nullable', 'string'],
            'observations'             => ['nullable', 'string'],
            'interventions'            => ['nullable', 'string'],
            'response'                 => ['nullable', 'string'],
            'plan_followup'            => ['nullable', 'string'],
            'emergency_contact_person' => ['nullable', 'string', 'max:255'],
            'emergency_relationship'   => ['nullable', 'string', 'max:255'],
            'emergency_contact_no'     => ['nullable', 'string', 'max:255'],
            'emergency_address'        => ['nullable', 'string'],
        ]);

        // 3) Either fetch existing case note or create a new one
        $note = CaseNote::firstOrNew([
            'appointment_id' => $appointment->id,
        ]);

        // 4) Core identity links (works even if student has no account = walk-in)
        $note->counselor_id = $appointment->counselor_id ?? $note->counselor_id;
        $note->student_id   = $appointment->student_id   ?? $note->student_id;

        // Prefer appointment.student_name (from walk-in form), fallback to existing
        $note->student_name = $appointment->student_name ?? $note->student_name;

        // Note date: prefer input, else appointment date, else today
        $note->note_date = !empty($data['note_date'])
            ? Carbon::parse($data['note_date'])->toDateString()
            : ($appointment->scheduled_at
                ? Carbon::parse($appointment->scheduled_at)->toDateString()
                : now()->toDateString());

        // Program year: prefer input, else appointment year_level (walk-in field)
        $note->program_year = $data['program_year']
            ?? $appointment->year_level
            ?? $note->program_year;

        // 5) Copy the rest of the text fields
        $note->address                  = $data['address']                  ?? $note->address;
        $note->presenting_problem       = $data['presenting_problem']       ?? $note->presenting_problem;
        $note->observations             = $data['observations']             ?? $note->observations;
        $note->interventions            = $data['interventions']            ?? $note->interventions;
        $note->response                 = $data['response']                 ?? $note->response;
        $note->plan_followup            = $data['plan_followup']            ?? $note->plan_followup;
        $note->emergency_contact_person = $data['emergency_contact_person'] ?? $note->emergency_contact_person;
        $note->emergency_relationship   = $data['emergency_relationship']   ?? $note->emergency_relationship;
        $note->emergency_contact_no     = $data['emergency_contact_no']     ?? $note->emergency_contact_no;
        $note->emergency_address        = $data['emergency_address']        ?? $note->emergency_address;

        // 6) Audit fields
        if (!$note->exists) {
            $note->created_by = $userId;
        }
        $note->updated_by = $userId;

        $note->save();   // analytics hook in CaseNote::booted() will run automatically

        return redirect()
            ->route('counselor.appointments.show', $appointment->id)
            ->with('success', 'Case note saved for this session.');
    }
}

