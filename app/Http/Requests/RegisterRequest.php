<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name'      => is_string($this->full_name) ? trim($this->full_name) : $this->full_name,
            'email'          => is_string($this->email) ? trim($this->email) : $this->email,
            'contact_number' => is_string($this->contact_number) ? trim($this->contact_number) : $this->contact_number,
            'course'         => is_string($this->course) ? trim($this->course) : $this->course,
            'year_level'     => is_string($this->year_level) ? trim($this->year_level) : $this->year_level,
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => [
                'required','string','between:2,80',
                // letters, marks, spaces, apostrophes, periods, hyphens
                'regex:/^[\pL\pM\'\.\-\s]+$/u',
            ],

            'email' => [
                'required','string','lowercase',
                'email:rfc,dns','max:255',
                // ✅ unique across BOTH tables you write to (use your actual table names)
                Rule::unique('tbl_registration', 'email'),
                Rule::unique('tbl_users', 'email'),   // <-- changed from 'users' to 'tbl_users'
            ],

            'contact_number' => [
                'required','string','between:7,20',
                // allow + - spaces digits parentheses
                'regex:/^[0-9\+\-\s\(\)]+$/',
            ],

            'course'     => ['required','string','between:2,100'],
            'year_level' => ['required','string','between:1,50'],

            'password' => [
                'required','string','min:12','confirmed',
                // at least 1 lower, 1 upper, 1 digit, 1 symbol
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).+$/',
            ],
            // Explicitly require the confirmation field since you use "confirmed"
            'password_confirmation' => ['required','string','min:12'],

            // Require successful OTP verification
            'verified_token' => ['required', function($attr, $value, $fail){
                $ok = \App\Http\Controllers\Auth\EmailOtpController::validateToken(
                    strtolower((string)$this->input('email')),
                    $value
                );
                if (!$ok) $fail('Please verify your email first.');
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'    => 'Please enter your full name.',
            'full_name.regex'       => 'Use letters, spaces, apostrophes, periods, or hyphens only.',

            'email.email'           => 'Enter a valid email address.',
            'email.unique'          => 'This email is already registered.',

            'contact_number.regex'  => 'Contact number may include digits, spaces, +, -, and parentheses.',

            'password.min'          => 'Password must be at least :min characters.',
            'password.regex'        => 'Password must include upper & lower case letters, a number, and a symbol.',
            'password.confirmed'    => 'Password confirmation does not match.',

            'password_confirmation.required' => 'Please confirm your password.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name'             => 'full name',
            'email'                 => 'email',
            'contact_number'        => 'contact number',
            'course'                => 'course',
            'year_level'            => 'year level',
            'password'              => 'password',
            'password_confirmation' => 'password confirmation',
        ];
    }

    protected function passedValidation(): void
    {
       
    }
}
