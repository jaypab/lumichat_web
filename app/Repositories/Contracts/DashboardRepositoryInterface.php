<?php

namespace App\Repositories\Contracts;

interface DashboardRepositoryInterface
{
    /**
     * Returns:
     * [
     *   'kpis' => [
     *     'appointmentsTotal'    => int,
     *     'criticalCasesTotal'   => int,   // DISTINCT users who still have at least one UNHANDLED high-risk chat session
     *     'activeCounselors'     => int,
     *     'chatSessionsThisWeek' => int,
     *     'appointmentsTrend'    => string,
     *     'sessionsTrend'        => string,
     *   ],
     *   'recentAppointments'   => array<array>,  // [{ status, notes, when, ... }]
     *   'activities'           => array<array>,  // [{ event, meta, actor, created_at }]
     *   'recentChatSessions'   => array<array>,  // [{ topic_summary, actor, risk_level, created_at }]
     *   'generatedAt'          => string (ISO8601),
     * ]
     */
    public function stats(): array;
}
