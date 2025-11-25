<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\ChatSession;
use App\Models\Appointment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;

// ✅ Repo bindings
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Eloquent\DashboardRepository;

use App\Repositories\Contracts\CounselorLogRepositoryInterface;
use App\Repositories\Eloquent\CounselorLogRepository;

use App\Repositories\Contracts\CourseAnalyticsRepositoryInterface;
use App\Repositories\Eloquent\CourseAnalyticsRepository;

// ✅ NEW: observer for high-risk alerts
use App\Observers\ChatSessionObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(CounselorLogRepositoryInterface::class, CounselorLogRepository::class);
        $this->app->bind(CourseAnalyticsRepositoryInterface::class, CourseAnalyticsRepository::class);
    }

    public function boot(): void
    {
        // Force HTTPS in production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        /* ======================  GLOBAL VIEW FLAGS  ====================== */
        // - $hasAppointments: does current user have ANY appointment rows?
        // - $appointmentEnabled: unlocked by session flag, persisted column (if exists), or implied by having any appointment.
        View::composer('*', function ($view) {
            $user = Auth::user();

            $hasAppointments    = false;
            $appointmentEnabled = false;

            if ($user) {
                if (Schema::hasTable('tbl_appointments')) {
                    $hasAppointments = DB::table('tbl_appointments')
                        ->where('student_id', $user->id)
                        ->exists();
                }

                // Session unlock (set by signed link)
                $appointmentEnabled = (bool) session('appointment_enabled', false);

                // Persisted column (optional)
                try {
                    if (Schema::hasTable('tbl_users') && Schema::hasColumn('tbl_users', 'appointment_enabled')) {
                        $appointmentEnabled = $appointmentEnabled || (bool) (
                            DB::table('tbl_users')->where('id', $user->id)->value('appointment_enabled') ?? false
                        );
                    }
                } catch (\Throwable $e) {
                    // ignore schema errors silently
                }

                // Any appointment implies it should be visible thereafter
                if ($hasAppointments) {
                    $appointmentEnabled = true;
                }
            }

            $view->with('hasAppointments', $hasAppointments);
            $view->with('appointmentEnabled', $appointmentEnabled);
        });

        // One-time product tour flag
        View::composer('*', function ($view) {
            $view->with('shouldRunTour', Auth::check() && !optional(Auth::user())->has_seen_tutorial);
        });

        // Counselor layout: pending high-risk reviews badge
        View::composer('layouts.counselor', function ($view) {
            $pending = 0;
            try {
                if (Schema::hasTable('tbl_highrisk_reviews')) {
                    $pending = (int) DB::table('tbl_highrisk_reviews')
                        ->where('review_status', 'pending')
                        ->count();
                }
            } catch (\Throwable $e) {
                $pending = 0; // safe default
            }

            $view->with('cslPendingCount', $pending);
        });

        // Admin layout: high-risk chatbot sessions badge in sidebar
        View::composer('layouts.admin', function ($view) {
            $count = 0;

            try {
                // Resolve the actual table used by the ChatSession model
                if (class_exists(ChatSession::class)) {
                    $table = app(ChatSession::class)->getTable(); // e.g. chat_sessions or tbl_chat_sessions

                    if (Schema::hasTable($table)) {
                        $q = DB::table($table);

                        if (Schema::hasColumn($table, 'risk_level')) {
                            $q->whereIn('risk_level', ['high', 'high-risk', 'high_risk']);
                        } elseif (Schema::hasColumn($table, 'risk')) {
                            $q->whereIn('risk', ['high', 'high-risk', 'high_risk']);
                        } else {
                            // no risk column, nothing to count
                            $q = null;
                        }

                        if ($q) {
                            $count = (int) $q->count();
                        }
                    }
                }
            } catch (\Throwable $e) {
                $count = 0; // safe default on any error
            }

            $view->with('adminHighRiskCount', $count);
        });

        /* ================================================================= */

        /* --------------------- Activity log hooks ------------------------ */
        User::created(function (User $user) {
            if (!Schema::hasTable('tbl_activity_log')) return;
            ActivityLog::create([
                'event'        => 'user.registered',
                'description'  => "New user registered: {$user->name}",
                'actor_id'     => $user->id,
                'subject_type' => User::class,
                'subject_id'   => $user->id,
                'meta'         => ['email' => $user->email, 'role' => $user->role ?? null],
            ]);
        });

        ChatSession::created(function (ChatSession $session) {
            if (!Schema::hasTable('tbl_activity_log')) return;
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
            if (!Schema::hasTable('tbl_activity_log')) return;
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
        /* ----------------------------------------------------------------- */

        // ✅ Register ChatSession observer (handles high-risk admin alerts + deep links + review rows)
        if (class_exists(ChatSession::class) && class_exists(ChatSessionObserver::class)) {
            ChatSession::observe(ChatSessionObserver::class);
        }
    }
}
