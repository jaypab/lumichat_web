<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Notifications\WelcomeToLumiChat;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    // ==== Constants (dedupe magic strings) ====
    private const VIEW_REGISTER   = 'auth.register';
    private const FLASH_SUCCESS   = 'success';
    private const MSG_CREATED     = 'Your account has been created! Please sign in.';

    public function __construct()
    {
        // Prevent brute force attacks on registration
        $this->middleware('throttle:6,1')->only('store');
    }

    /**
     * Show the registration form.
     */
    public function create(): View
    {
        // If your file is at resources/views/auth/register.blade.php
        return view(self::VIEW_REGISTER);
    }

    /**
     * Handle the registration request.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Force lowercase email
        $data['email'] = Str::lower($data['email']);

        // Capture the created user so we can notify after commit
        $user = null;

        DB::transaction(function () use ($data, &$user) {
            // Persist to tbl_registration (keeps original hashing behavior)
            Registration::create([
                'full_name'      => $data['full_name'],
                'email'          => $data['email'],
                'contact_number' => $data['contact_number'],
                'course'         => $data['course'],
                'year_level'     => $data['year_level'],
                'password'       => Hash::make($data['password']),
            ]);

            // Save to tbl_users and keep the instance
            $user = User::create([
                'name'                 => $data['full_name'],
                'email'                => $data['email'],
                'course'               => $data['course'],
                'year_level'           => $data['year_level'],
                'contact_number'       => $data['contact_number'],
                'password'             => Hash::make($data['password']),
                'role'                 => User::ROLE_STUDENT,  // default role
                'appointments_enabled' => false,               // default setting
            ]);
        });

        // Send Welcome email AFTER the transaction succeeded
        if ($user) {
            try {
                $user->notify(new WelcomeToLumiChat(
                    name: $user->name,
                    loginUrl: route('login')
                ));
            } catch (\Throwable $e) {
                // Don't break registration flow if mail fails
                Log::warning('Welcome email failed', [
                    'user_id' => $user->id ?? null,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('login')
            ->with(self::FLASH_SUCCESS, self::MSG_CREATED);
    }
}
