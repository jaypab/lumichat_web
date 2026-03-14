<?php

namespace App\Http\Requests\Auth;

use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sis' => [
                'required',
                'digits_between:4,20',
                'regex:/^[0-9]+$/',
                'unique:tbl_users,sis',
            ],
            'name' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z\s\.\-]+$/',
            ],
            'email' => [
                'required',
                'email:filter',
                'max:255',
                'unique:tbl_users,email',
            ],
            'contact_number' => [
                'required',
                'regex:/^[0-9]{10,13}$/',
            ],
            'course' => [
                'required',
                Rule::in(['BSIT','EDUC','CAS','CRIM','BLIS','MIDWIFERY','BSHM','BSBA']),
            ],
            'year_level' => [
                'required',
                Rule::in(['1st Year','2nd Year','3rd Year','4th Year']),
            ],
            'attachment' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'device_key' => [
                'required',
                'string',
                'max:120',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $currentIp = (string) $this->ip();
            $deviceKey = trim((string) $this->input('device_key'));

            if ($currentIp !== '' || $deviceKey !== '') {
                $historyQuery = AccountRequest::query();
                $historyQuery->where(function ($q) use ($currentIp, $deviceKey) {
                    if ($currentIp !== '') {
                        $q->orWhere('request_ip', $currentIp);
                    }
                    if ($deviceKey !== '') {
                        $q->orWhere('device_key', $deviceKey);
                    }
                });

                $latestBySource = (clone $historyQuery)->latest('id')->first();

                $approvedBySource = (clone $historyQuery)
                    ->where('status', AccountRequest::STATUS_APPROVED)
                    ->whereNotNull('approved_user_id')
                    ->latest('id')
                    ->first();

                if ($approvedBySource) {
                    $approvedUserStillExists = User::query()->whereKey($approvedBySource->approved_user_id)->exists();

                    if ($approvedUserStillExists) {
                        $validator->errors()->add(
                            'request_access',
                            'This device already has a LumiChat account request that was approved. Only one account per device is allowed.'
                        );
                        return;
                    }
                }

                if ($latestBySource) {
                    $cooldownEndsAt = optional($latestBySource->created_at)?->copy()->addDays(2);
                    if ($cooldownEndsAt && now()->lt($cooldownEndsAt)) {
                        $validator->errors()->add(
                            'request_access',
                            'You cannot request right now because this device/network already submitted recently. Please try again in 2 days.'
                        );
                        return;
                    }
                }
            }

            $pending = AccountRequest::query()
                ->where(function ($q) {
                    $q->where('email', (string) $this->input('email'))
                        ->orWhere('sis', (string) $this->input('sis'));
                })
                ->where('status', AccountRequest::STATUS_PENDING)
                ->exists();

            if ($pending) {
                $validator->errors()->add('email', 'You already have a pending account request.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'sis.regex' => 'The SIS ID may only contain numbers.',
            'contact_number.regex' => 'The contact number may only contain digits.',
            'name.regex' => 'The full name may only contain letters and basic punctuation.',
            'attachment.max' => 'Attachment must not exceed 5MB.',
        ];
    }
}
