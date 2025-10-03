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

        DB::transaction(function () use ($data) {
            // Persist to tbl_registration (keeps original hashing behavior)
            Registration::create([
                'full_name'      => $data['full_name'],
                'email'          => $data['email'],
                'contact_number' => $data['contact_number'],
                'course'         => $data['course'],
                'year_level'     => $data['year_level'],
                'password'       => Hash::make($data['password']),
            ]);

            // Save to tbl_users
            User::create([
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

        return redirect()
            ->route('login')
            ->with(self::FLASH_SUCCESS, self::MSG_CREATED);
    }
}
