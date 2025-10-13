<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const VIEW_DASHBOARD = 'admin.dashboard';

    public function __construct(
        protected DashboardRepositoryInterface $dashboard
    ) {}

    /** Render dashboard (Blade). */
    public function index(): View
    {
        $data = $this->dashboard->stats();

        // Cast arrays -> objects for Blade & parse times (kept from your code)
        $recentAppointments = collect($data['recentAppointments'])->map(function ($r) {
            $o = (object) $r;
            $o->when = !empty($o->when) ? Carbon::parse($o->when) : null;
            return $o;
        });

        $activities = collect($data['activities'])->map(function ($r) {
            $o = is_array($r) ? (object) $r : $r;
            $o->created_at = !empty($o->created_at) ? Carbon::parse($o->created_at) : null;
            return $o;
        });

        $recentChatSessions = collect($data['recentChatSessions'])->map(function ($r) {
            $o = (object) $r;
            $o->created_at = !empty($o->created_at) ? Carbon::parse($o->created_at) : null;
            return $o;
        });

        return view(self::VIEW_DASHBOARD, [
            // KPI numbers
            'appointmentsTotal'     => $data['kpis']['appointmentsTotal'],
            'criticalCasesTotal'    => $data['kpis']['criticalCasesTotal'],
            'activeCounselors'      => $data['kpis']['activeCounselors'],
            'chatSessionsThisWeek'  => $data['kpis']['chatSessionsThisWeek'],
            'chatSessionsTotal'     => $data['kpis']['chatSessionsTotal'], // 👈 add

            // KPI trend labels
            'appointmentsTrend'     => $data['kpis']['appointmentsTrend'],
            'sessionsTrend'         => $data['kpis']['sessionsTrend'],

            // Lists
            'recentAppointments'    => $recentAppointments,
            'activities'            => $activities,
            'recentChatSessions'    => $recentChatSessions,
        ]);
    }

    /** JSON for live refresh (poller). */
    public function stats(Request $request): JsonResponse
    {
        return response()
            ->json($this->dashboard->stats())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
