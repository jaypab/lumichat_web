<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = AccountRequest::query()->with(['reviewer:id,name', 'approvedUser:id,name,email,last_seen_appt_at']);

        if (in_array($status, [AccountRequest::STATUS_PENDING, AccountRequest::STATUS_APPROVED, AccountRequest::STATUS_REJECTED], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('sis', 'like', "%{$q}%");
            });
        }

        $accountRequests = $query->latest()->paginate(15)->withQueryString();

        return view('admin.account-requests.index', [
            'accountRequests' => $accountRequests,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function show(AccountRequest $accountRequest): View
    {
        $accountRequest->load(['reviewer:id,name', 'approvedUser:id,name,email']);

        return view('admin.account-requests.show', [
            'accountRequest' => $accountRequest,
        ]);
    }

    public function liveStatus(Request $request): JsonResponse
    {
        $ids = collect((array) $request->query('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        $items = AccountRequest::query()
            ->whereIn('id', $ids->all())
            ->with(['approvedUser:id,last_seen_appt_at'])
            ->get()
            ->map(function (AccountRequest $item) {
                $accountStatus = 'N/A';
                $lastOnline = 'N/A';

                if ($item->status === AccountRequest::STATUS_APPROVED && $item->approvedUser) {
                    $isOnline = Cache::has('user-online-' . $item->approvedUser->id);
                    $accountStatus = $isOnline ? 'Online' : 'Offline';
                    $lastOnline = $item->approvedUser->last_seen_appt_at
                        ? $item->approvedUser->last_seen_appt_at->diffForHumans()
                        : 'Not yet active';
                } elseif ($item->status === AccountRequest::STATUS_APPROVED) {
                    $accountStatus = 'Provisioning';
                    $lastOnline = 'N/A';
                }

                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'account_status' => $accountStatus,
                    'last_online' => $lastOnline,
                ];
            })
            ->values();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function approve(Request $request, AccountRequest $accountRequest): RedirectResponse
    {
        if ($accountRequest->status !== AccountRequest::STATUS_PENDING) {
            return back()->with('error', 'Only pending requests can be approved.');
        }

        $conflictExists = User::query()
            ->where('email', $accountRequest->email)
            ->orWhere('sis', $accountRequest->sis)
            ->exists();

        if ($conflictExists) {
            return back()->with('error', 'A user with the same email or SIS already exists.');
        }

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $generatedPassword = Str::password(24);
        $newUser = null;

        DB::transaction(function () use ($accountRequest, $validated, $generatedPassword, &$newUser) {
            $newUser = User::create([
                'sis' => $accountRequest->sis,
                'name' => $accountRequest->name,
                'email' => $accountRequest->email,
                'course' => $accountRequest->course,
                'year_level' => $accountRequest->year_level,
                'contact_number' => $accountRequest->contact_number,
                'role' => User::ROLE_STUDENT,
                'appointments_enabled' => false,
                'password' => Hash::make($generatedPassword),
            ]);

            $accountRequest->update([
                'status' => AccountRequest::STATUS_APPROVED,
                'review_notes' => $validated['review_notes'] ?? null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'approved_user_id' => $newUser->id,
            ]);
        });

        $sendStatus = Password::broker()->sendResetLink(['email' => $newUser->email]);

        $message = $sendStatus === Password::RESET_LINK_SENT
            ? 'Request approved. A password setup email was sent to the student.'
            : 'Request approved. Student account created, but password setup email could not be sent.';

        return redirect()
            ->route('admin.account-requests.show', $accountRequest)
            ->with($sendStatus === Password::RESET_LINK_SENT ? 'success' : 'error', $message);
    }

    public function reject(Request $request, AccountRequest $accountRequest): RedirectResponse
    {
        if ($accountRequest->status !== AccountRequest::STATUS_PENDING) {
            return back()->with('error', 'Only pending requests can be rejected.');
        }

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ]);

        $accountRequest->update([
            'status' => AccountRequest::STATUS_REJECTED,
            'review_notes' => $validated['review_notes'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('admin.account-requests.show', $accountRequest)
            ->with('success', 'Request rejected.');
    }
}
