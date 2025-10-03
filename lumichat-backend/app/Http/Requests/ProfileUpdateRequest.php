<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    public const COURSES = ['BSIT','EDUC','CAS','CRIM','BLIS','MIDWIFERY','BSHM','BSBA'];
    public const YEARS   = ['1st year','2nd year','3rd year','4th year'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Sanitize inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        // Name: strip tags + collapse whitespace
        $name = strip_tags((string) $this->input('name', ''));
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        // Email: strip tags, trim, lowercase
        $email = strip_tags((string) $this->input('email', ''));
        $email = mb_strtolower(trim($email));

        // Course: already stored as short code; whitelist
        $course = strtoupper(trim((string) $this->input('course', '')));
        $course = in_array($course, self::COURSES, true) ? $course : null;

        // Year: whitelist
        $year = trim((string) $this->input('year_level', ''));
        $year = in_array($year, self::YEARS, true) ? $year : null;

        // Phone: digits only; normalize common PH formats
        $digits = preg_replace('/\D+/', '', (string) $this->input('contact_number', ''));

        if ($digits !== '') {
            if (Str::startsWith($digits, '09') && strlen($digits) === 11) {
                // 09xxxxxxxxx  -> 63xxxxxxxxx
                $digits = '63' . substr($digits, 1);
            } elseif (!Str::startsWith($digits, '63') && Str::startsWith($digits, '9')) {
                // 9xxxxxxxxx   -> 639xxxxxxxxx
                $digits = '63' . $digits;
            }
            // hard length clamp for safety
            $digits = substr($digits, 0, 15);
        }

        $this->merge([
            'name'           => $name,
            'email'          => $email,
            'course'         => $course,
            'year_level'     => $year,
            'contact_number' => $digits,
        ]);
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => [
                'bail','required','string','min:2','max:100',
                'regex:/^[\p{L}\p{M}][\p{L}\p{M}\s\.\'\-]*$/u',
            ],
            'email' => [
                'bail','required','string','max:255','lowercase',
                'email:rfc,dns',
                Rule::unique('tbl_users','email')->ignore($userId, 'id'),
            ],
            'course'         => ['nullable','in:'.implode(',', self::COURSES)],
            'year_level'     => ['nullable','in:'.implode(',', self::YEARS)],

            // REQUIRED so it never becomes NULL in DB
            // Accept: 09XXXXXXXXX, 639XXXXXXXXX, or straight 10–15 digits
            'contact_number' => [
                'bail','required','string','min:10','max:15',
                'regex:/^(09\d{9}|639\d{9}|\d{10,15})$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'              => 'The name may contain letters, spaces, dots, apostrophes, and hyphens only, and must start with a letter.',
            'email.unique'            => 'This email is already in use.',
            'contact_number.required' => 'Please enter your contact number.',
            'contact_number.regex'    => 'Use PH format 09XXXXXXXXX or 639XXXXXXXXX (digits only).',
            'contact_number.min'      => 'Contact number is too short.',
            'contact_number.max'      => 'Contact number is too long.',
        ];
    }

    public function attributes(): array
    {
        return [
            'year_level'     => 'year level',
            'contact_number' => 'contact number',
        ];
    }
}
