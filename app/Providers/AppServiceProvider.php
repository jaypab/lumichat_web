<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\ChatSession;
use App\Models\Appointment;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;

// ✅ Bind the dashboard repo interface to the eloquent implementation
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Eloquent\DashboardRepository;

use App\Repositories\Contracts\CounselorLogRepositoryInterface;
use App\Repositories\Eloquent\CounselorLogRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(CounselorLogRepositoryInterface::class, CounselorLogRepository::class);
    }

    public function boot(): void
    {
        // Force HTTPS in production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // ======================  GLOBAL VIEW FLAGS  ======================
        // - $hasAppointments: does current user have ANY appointment rows?
        // - $appointmentEnabled: feature is unlocked (via signed link/session),
        //   OR persisted in users.appointment_enabled (if column exists),
        //   OR implied by having any appointment rows already.
        View::composer('*', function ($view) {
            $user = Auth::user();

            $hasAppointments = false;
            $appointmentEnabled = false;

            if ($user) {
                // Only query if table exists (prevents errors during fresh installs / early migrations)
                if (Schema::hasTable('tbl_appointments')) {
                    $hasAppointments = DB::table('tbl_appointments')
                        ->where('student_id', $user->id)
                        ->exists();
                }

                // Session unlock (set by signed link)
                $appointmentEnabled = (bool) session('appointment_enabled', false);

                // Persisted column (optional): users.appointment_enabled
                try {
                    if (Schema::hasTable('users') && Schema::hasColumn('users', 'appointment_enabled')) {
                        $appointmentEnabled = $appointmentEnabled || (bool) (
                            DB::table('users')->where('id', $user->id)->value('appointment_enabled') ?? false
                        );
                    }
                } catch (\Throwable $e) {
                    // ignore schema errors silently
                }

                // Having any appointment implies feature should be visible thereafter
                if ($hasAppointments) {
                    $appointmentEnabled = true;
                }
            }

            $view->with('hasAppointments', $hasAppointments);
            $view->with('appointmentEnabled', $appointmentEnabled);
        });
        // =================================================================

        // --- existing model events ---

        User::created(function (User $user) {
            ActivityLog::create([
                'event'        => 'user.registered',
                'description'  => "New user registered: {$user->name}",
                'actor_id'     => $user->id,
                'subject_type' => User::class,
                'subject_id'   => $user->id,
                'meta'         => ['email' => $user->email, 'role' => $user->role ?? null],
            ]);
        });

        RateLimiter::for('chat-send', function (Request $request) {
            $by = optional($request->user())->id ?? $request->ip();
            return [ Limit::perMinute(20)->by("chat:pm:{$by}") ];
        });

        ChatSession::created(function (ChatSession $session) {
            ActivityLog::create([
                'event'        => 'chat_session.started',
                'description'  => 'Chat session started: ' . Str::limit($session->topic_summary ?: 'New chat session', 80),
                'actor_id'     => $session->user_id,
                'subject_type' => ChatSession::class,
                'subject_id'   => $session->id,
                'meta'         => ['user_id' => $session->user_id],
            ]);
        });

        Appointment::created(function (Appointment $appt) {
            ActivityLog::create([
                'event'        => 'appointment.created',
                'description'  => 'Appointment created',
                'actor_id'     => $appt->student_id,
                'subject_type' => Appointment::class,
                'subject_id'   => $appt->id,
                'meta'         => [
                    'student_id'   => $appt->student_id,
                    'counselor_id' => $appt->counselor_id,
                    'scheduled_at' => optional($appt->scheduled_at)->toIso8601String(),
                ],
            ]);
        });
    }
}
