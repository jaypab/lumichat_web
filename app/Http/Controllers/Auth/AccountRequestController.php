<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreAccountRequestRequest;
use App\Models\AccountRequest;
use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountRequestController extends Controller
{
    public function create(): View
    {
        return view('auth.account-request');
    }

    public function store(StoreAccountRequestRequest $request): RedirectResponse
    {
        $attachmentPath = $request->file('attachment')?->store('account-requests', 'public');

        $accountRequest = AccountRequest::create([
            'sis' => $request->string('sis')->toString(),
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'contact_number' => $request->string('contact_number')->toString(),
            'course' => $request->string('course')->toString(),
            'year_level' => $request->string('year_level')->toString(),
            'attachment_path' => $attachmentPath,
            'request_ip' => $request->ip(),
            'device_key' => $request->string('device_key')->toString(),
            'status' => AccountRequest::STATUS_PENDING,
        ]);

        $url = route('admin.account-requests.show', $accountRequest->id);

        User::query()
            ->where('role', User::ROLE_ADMIN)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new SimpleDatabaseNotification(
                'New account request',
                $accountRequest->name . ' submitted a student account request.',
                $url
            )));

        return redirect()
            ->route('login')
            ->with('status', 'Your request has been submitted. Please wait for admin approval.');
    }
}
