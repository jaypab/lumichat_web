@extends('layouts.admin')

@section('title', 'Admin - Dashboard')
@section('page_title', 'Dashboard')  

@section('content')
  <div class="max-w-7xl mx-auto space-y-8">

    {{-- Dashboard Header with Quick Actions --}}
    <section class="rounded-2xl text-white shadow-lg screen-only animate-fadeup relative overflow-hidden" style="background: linear-gradient(to right, rgb(79, 70, 229), rgb(147, 51, 234), rgb(124, 58, 237)) !important;">
      {{-- Decorative background pattern --}}
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl transform translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-white rounded-full blur-3xl transform -translate-x-1/2 translate-y-1/2"></div>
      </div>
      
      <div class="relative p-5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Welcome back, {{ auth()->user()->name ?? 'Admin' }}</h2>
            <p class="text-white/90 text-sm mt-0.5">Here's what's happening with your students today.</p>
          </div>

          <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.appointments.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white/95 backdrop-blur-sm text-indigo-700 px-4 py-2 text-sm font-medium shadow-md hover:bg-white hover:shadow-lg active:scale-[.98] transition-all duration-200">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 9V4h12v5M6 18h12a2 2 0 002-2v-5H4v5a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Appointments
            </a>
            <a href="{{ route('admin.chatbot-sessions.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white/95 backdrop-blur-sm text-indigo-700 px-4 py-2 text-sm font-medium shadow-md hover:bg-white hover:shadow-lg active:scale-[.98] transition-all duration-200">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Chat Sessions
            </a>
          </div>
        </div>
      </div>
    </section>

    {{-- Enhanced KPI / Stat cards with gradients and animations --}}
    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5 animate-fadeup" style="--stagger:60ms">

      {{-- Total Appointments --}}
      <div class="stat-card-enhanced group relative min-h-[140px] rounded-2xl border border-slate-200/50 bg-gradient-to-br from-white via-white to-sky-50/30 dark:from-gray-800 dark:via-gray-800 dark:to-sky-900/20 p-6 shadow-md hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 animate-fadeup overflow-hidden">
        {{-- Gradient top bar --}}
        <div class="absolute inset-x-0 -top-px h-1 rounded-t-2xl bg-gradient-to-r from-sky-400 via-blue-500 to-sky-400"></div>
        
        {{-- Background decoration --}}
        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-sky-100/40 to-transparent dark:from-sky-500/10 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

        {{-- Info tooltip --}}
        <details data-kpi class="group/tooltip absolute right-2.5 top-2.5 z-50 select-none">
          <summary class="list-none inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/95 dark:bg-gray-700 ring-1 ring-sky-200 dark:ring-sky-500/30 text-sky-700 dark:text-sky-400 hover:bg-white dark:hover:bg-gray-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300" aria-label="About this metric">
            <svg viewBox="0 0 24 24" class="w-[14px] h-[14px]" fill="currentColor" aria-hidden="true">
              <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm.75 15h-1.5v-6h1.5Zm0-7.5h-1.5v-1.5h1.5Z"/>
            </svg>
          </summary>
          <div class="absolute right-0 top-full mt-2 w-72 rounded-md bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm text-slate-800 dark:text-gray-200 text-xs px-3 py-2 shadow-xl ring-1 ring-slate-200 dark:ring-gray-700 opacity-0 translate-y-1 scale-95 transition duration-200 group-open/tooltip:opacity-100 group-open/tooltip:translate-y-0 group-open/tooltip:scale-100">
            <span class="absolute -top-1 right-3 h-3 w-3 rotate-45 bg-white dark:bg-gray-800 ring-1 ring-slate-200 dark:ring-gray-700"></span>
            <div class="font-semibold text-slate-700 dark:text-gray-300">Total Appointments</div>
            <p class="leading-snug text-slate-600 dark:text-gray-400 mt-0.5">All appointments recorded. Trend compares this week (Mon–Sun) vs last week.</p>
          </div>
        </details>

        <div class="relative z-10 flex items-start gap-4">
          <span class="shrink-0 inline-flex w-14 h-14 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-500 shadow-lg shadow-sky-500/30 group-hover:shadow-sky-500/50 group-hover:scale-110 transition-all duration-300">
            <img src="{{ asset('images/icons/appointment.png') }}" class="w-7 h-7 brightness-0 invert" alt="Appointments icon">
          </span>
          <div class="min-w-0 flex-1">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-gray-400 truncate">Total Appointments</div>
            <div class="mt-1 text-3xl font-bold text-slate-900 dark:text-white counter-animate" data-target="{{ $appointmentsTotal ?? 0 }}">{{ number_format($appointmentsTotal ?? 0) }}</div>
            <div class="mt-1 text-xs text-slate-500 dark:text-gray-400 whitespace-nowrap" id="kpi-appointments-change">
              {{ $appointmentsTrend ?? '= Same as last week' }}
            </div>
          </div>
        </div>
      </div>

      {{-- Critical Cases --}}
      <div class="stat-card-enhanced group relative min-h-[140px] rounded-2xl border border-slate-200/50 bg-gradient-to-br from-white via-white to-rose-50/30 dark:from-gray-800 dark:via-gray-800 dark:to-rose-900/20 p-6 shadow-md hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 animate-fadeup overflow-hidden">
        <div class="absolute inset-x-0 -top-px h-1 rounded-t-2xl bg-gradient-to-r from-rose-400 via-pink-500 to-rose-400"></div>
        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-rose-100/40 to-transparent dark:from-rose-500/10 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

        <details data-kpi class="group/tooltip absolute right-2.5 top-2.5 z-50 select-none">
          <summary class="list-none inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/95 dark:bg-gray-700 ring-1 ring-rose-200 dark:ring-rose-500/30 text-rose-700 dark:text-rose-400 hover:bg-white dark:hover:bg-gray-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300" aria-label="About this metric">
            <svg viewBox="0 0 24 24" class="w-[14px] h-[14px]" fill="currentColor" aria-hidden="true">
              <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm.75 15h-1.5v-6h1.5Zm0-7.5h-1.5v-1.5h1.5Z"/>
            </svg>
          </summary>
          <div class="absolute right-0 top-full mt-2 w-72 rounded-md bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm text-slate-800 dark:text-gray-200 text-xs px-3 py-2 shadow-xl ring-1 ring-slate-200 dark:ring-gray-700 opacity-0 translate-y-1 scale-95 transition duration-200 group-open/tooltip:opacity-100 group-open/tooltip:translate-y-0 group-open/tooltip:scale-100">
            <span class="absolute -top-1 right-3 h-3 w-3 rotate-45 bg-white dark:bg-gray-800 ring-1 ring-slate-200 dark:ring-gray-700"></span>
            <div class="font-semibold text-slate-700 dark:text-gray-300">Critical Cases</div>
            <p class="leading-snug text-slate-600 dark:text-gray-400 mt-0.5">Students with at least one <b>High-risk</b> chatbot session (distinct users). Requires attention.</p>
          </div>
        </details>

        <div class="relative z-10 flex items-start gap-4">
          <span class="shrink-0 inline-flex w-14 h-14 items-center justify-center rounded-xl bg-gradient-to-br from-rose-400 to-pink-500 shadow-lg shadow-rose-500/30 group-hover:shadow-rose-500/50 group-hover:scale-110 transition-all duration-300 {{ ($criticalCasesTotal ?? 0) > 0 ? 'animate-pulse-glow' : '' }}">
            <img src="{{ asset('images/icons/diagnosis.png') }}" class="w-7 h-7 brightness-0 invert" alt="Critical cases icon">
          </span>
          <div class="min-w-0 flex-1">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-gray-400 truncate">Critical Cases</div>
            <div class="mt-1 text-3xl font-bold text-slate-900 dark:text-white counter-animate" data-target="{{ $criticalCasesTotal ?? 0 }}">{{ number_format($criticalCasesTotal ?? 0) }}</div>
            <div class="mt-1 text-xs">
              @if(($criticalCasesTotal ?? 0) > 0)
                <a href="{{ route('admin.chatbot-sessions.index', ['only' => 'high']) }}" class="text-rose-600 dark:text-rose-400 font-semibold underline decoration-rose-300 hover:decoration-rose-600 transition-colors">
                  Requires attention
                </a>
              @else
                <span class="text-slate-500 dark:text-gray-400">All clear</span>
              @endif
            </div>
          </div>
        </div>
      </div>

      {{-- Active Counselor --}}
      <div class="stat-card-enhanced group relative min-h-[140px] rounded-2xl border border-slate-200/50 bg-gradient-to-br from-white via-white to-amber-50/30 dark:from-gray-800 dark:via-gray-800 dark:to-amber-900/20 p-6 shadow-md hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 animate-fadeup overflow-hidden">
        <div class="absolute inset-x-0 -top-px h-1 rounded-t-2xl bg-gradient-to-r from-amber-400 via-orange-500 to-amber-400"></div>
        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-amber-100/40 to-transparent dark:from-amber-500/10 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

        <details data-kpi class="group/tooltip absolute right-2.5 top-2.5 z-50 select-none">
          <summary class="list-none inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/95 dark:bg-gray-700 ring-1 ring-amber-200 dark:ring-amber-500/30 text-amber-700 dark:text-amber-400 hover:bg-white dark:hover:bg-gray-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="About this metric">
            <svg viewBox="0 0 24 24" class="w-[14px] h-[14px]" fill="currentColor" aria-hidden="true">
              <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm.75 15h-1.5v-6h1.5Zm0-7.5h-1.5v-1.5h1.5Z"/>
            </svg>
          </summary>
          <div class="absolute right-0 top-full mt-2 w-64 rounded-md bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm text-slate-800 dark:text-gray-200 text-xs px-3 py-2 shadow-xl ring-1 ring-slate-200 dark:ring-gray-700 opacity-0 translate-y-1 scale-95 transition duration-200 group-open/tooltip:opacity-100 group-open/tooltip:translate-y-0 group-open/tooltip:scale-100">
            <span class="absolute -top-1 right-3 h-3 w-3 rotate-45 bg-white dark:bg-gray-800 ring-1 ring-slate-200 dark:ring-gray-700"></span>
            <div class="font-semibold text-slate-700 dark:text-gray-300">Active Counselor</div>
            <p class="leading-snug text-slate-600 dark:text-gray-400 mt-0.5">Number of counselors currently available to accept students.</p>
          </div>
        </details>

        <div class="relative z-10 flex items-start gap-4">
          <span class="shrink-0 inline-flex w-14 h-14 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-lg shadow-amber-500/30 group-hover:shadow-amber-500/50 group-hover:scale-110 transition-all duration-300">
            <img src="{{ asset('images/icons/counselor.png') }}" class="w-7 h-7 brightness-0 invert" alt="Counselor icon">
          </span>
          <div class="min-w-0 flex-1">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-gray-400 truncate">Active Counselors</div>
            <div class="mt-1 text-3xl font-bold text-slate-900 dark:text-white counter-animate" data-target="{{ $activeCounselors ?? 0 }}">{{ number_format($activeCounselors ?? 0) }}</div>
            <div class="mt-1 text-xs text-slate-500 dark:text-gray-400">Available now</div>
          </div>
        </div>
      </div>

      {{-- Chat Sessions --}}
      <div class="stat-card-enhanced group relative min-h-[140px] rounded-2xl border border-slate-200/50 bg-gradient-to-br from-white via-white to-indigo-50/30 dark:from-gray-800 dark:via-gray-800 dark:to-indigo-900/20 p-6 shadow-md hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 animate-fadeup overflow-hidden">
        <div class="absolute inset-x-0 -top-px h-1 rounded-t-2xl bg-gradient-to-r from-indigo-400 via-purple-500 to-indigo-400"></div>
        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-indigo-100/40 to-transparent dark:from-indigo-500/10 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

        <details data-kpi class="group/tooltip absolute right-2.5 top-2.5 z-50 select-none">
          <summary class="list-none inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/95 dark:bg-gray-700 ring-1 ring-indigo-200 dark:ring-indigo-500/30 text-indigo-700 dark:text-indigo-400 hover:bg-white dark:hover:bg-gray-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300" aria-label="About this metric">
            <svg viewBox="0 0 24 24" class="w-[14px] h-[14px]" fill="currentColor" aria-hidden="true">
              <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm.75 15h-1.5v-6h1.5Zm0-7.5h-1.5v-1.5h1.5Z"/>
            </svg>
          </summary>
          <div class="absolute right-0 top-full mt-2 w-72 rounded-md bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm text-slate-800 dark:text-gray-200 text-xs px-3 py-2 shadow-xl ring-1 ring-slate-200 dark:ring-gray-700 opacity-0 translate-y-1 scale-95 transition duration-200 group-open/tooltip:opacity-100 group-open/tooltip:translate-y-0 group-open/tooltip:scale-100">
            <span class="absolute -top-1 right-3 h-3 w-3 rotate-45 bg-white dark:bg-gray-800 ring-1 ring-slate-200 dark:ring-gray-700"></span>
            <div class="font-semibold text-slate-700 dark:text-gray-300">Chat Sessions</div>
            <p class="leading-snug text-slate-600 dark:text-gray-400 mt-0.5">Total number of chatbot conversations so far. Trend compares this week vs. last week.</p>
          </div>
        </details>

        <div class="relative z-10 flex items-start gap-4">
          <span class="shrink-0 inline-flex w-14 h-14 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-400 to-purple-500 shadow-lg shadow-indigo-500/30 group-hover:shadow-indigo-500/50 group-hover:scale-110 transition-all duration-300">
            <img src="{{ asset('images/icons/chatbot-session.png') }}" class="w-7 h-7 brightness-0 invert" alt="Chat sessions icon">
          </span>
          <div class="min-w-0 flex-1">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-gray-400 truncate">Chat Sessions</div>
            <div class="mt-1 text-3xl font-bold text-slate-900 dark:text-white counter-animate" data-target="{{ $chatSessionsTotal ?? $chatSessionsThisWeek ?? 0 }}">{{ number_format($chatSessionsTotal ?? $chatSessionsThisWeek ?? 0) }}</div>
            <div class="mt-1 text-xs text-slate-500 dark:text-gray-400 whitespace-nowrap" id="kpi-sessions-change">
              {{ $sessionsTrend ?? '= Same as last week' }}
            </div>
          </div>
        </div>
      </div>
    </section>

    {{-- Charts Section --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fadeup" style="--stagger:120ms">
      
      {{-- Appointments Trend Chart --}}
      <div class="rounded-2xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-md hover:shadow-lg transition-all duration-200">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Appointments Trend</h3>
          <span class="text-xs text-slate-500 dark:text-gray-400">Last 7 days</span>
        </div>
        <div class="h-64">
          <canvas id="appointmentsChart"></canvas>
        </div>
      </div>

      {{-- Session Distribution Chart --}}
      <div class="rounded-2xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-md hover:shadow-lg transition-all duration-200">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Sessions by Risk Level</h3>
          <span class="text-xs text-slate-500 dark:text-gray-400">Current week</span>
        </div>
        <div class="h-64">
          <canvas id="sessionsChart"></canvas>
        </div>
      </div>
    </section>

    {{-- Two-up content --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fadeup" style="--stagger:180ms">

      {{-- Recent appointments --}}
      <div class="rounded-2xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-md hover:shadow-lg transition-all duration-200">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent Appointments</h3>
          <a class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 rounded transition-colors" href="{{ route('admin.appointments.index') }}">View all →</a>
        </div>

        @if(($recentAppointments ?? collect())->isEmpty())
          <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-gray-700 mb-3">
              <svg class="w-8 h-8 text-slate-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-gray-400">No appointments yet</p>
          </div>
        @else
          <ul class="divide-y divide-slate-100 dark:divide-gray-700 text-sm" id="list-recent-appointments">
            @foreach($recentAppointments as $appt)
              @php
                $status = strtolower($appt->status ?? 'scheduled');
                $dotClass = (str_contains($status, 'cancel') ? 'bg-rose-500' : (str_contains($status, 'confirm') ? 'bg-emerald-500' : (str_contains($status, 'complete') ? 'bg-indigo-500' : (str_contains($status, 'pending') ? 'bg-amber-500' : ((str_contains($status,'critical') || str_contains($status,'urgent')) ? 'bg-rose-500' : 'bg-sky-500')))));
              @endphp
              <li class="py-3 px-2 flex items-center justify-between rounded-md transition hover:bg-slate-50/60 dark:hover:bg-gray-700/50 cursor-pointer group">
                <div class="flex items-center gap-3">
                  <span class="w-2 h-2 rounded-full {{ $dotClass }} group-hover:scale-125 transition-transform"></span>
                  <div>
                    <div class="font-medium text-slate-900 dark:text-white">{{ $appt->status ? ucfirst($appt->status) : 'Scheduled' }}</div>
                    @if(!empty($appt->notes))
                      <div class="text-xs text-slate-500 dark:text-gray-400 line-clamp-1">{{ $appt->notes }}</div>
                    @endif
                  </div>
                </div>
                <span class="text-slate-400 dark:text-gray-500 text-xs">{{ optional($appt->when)->diffForHumans() }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- System Activity --}}
      <div class="rounded-2xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-md hover:shadow-lg transition-all duration-200">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">System Activity</h3>
        </div>

        @if(($activities ?? collect())->isEmpty())
          <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-gray-700 mb-3">
              <svg class="w-8 h-8 text-slate-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-gray-400">No activity yet</p>
          </div>
        @else
          <ul class="space-y-3 text-sm" id="list-activities">
            @foreach($activities as $a)
              @php
                $dot = str_starts_with($a->event, 'chat_session') ? 'bg-indigo-500' : (str_starts_with($a->event, 'user.registered') ? 'bg-emerald-500' : 'bg-slate-400');
                $text = str_starts_with($a->event, 'chat_session') ? 'Chat session started: ' . ($a->meta ?? 'Starting conversation…') : (str_starts_with($a->event, 'user.registered') ? 'New user registered' : 'Activity');
              @endphp
              <li class="flex items-center justify-between group hover:bg-slate-50/60 dark:hover:bg-gray-700/50 -mx-2 px-2 py-2 rounded-lg transition">
                <div class="flex items-center gap-3">
                  <span class="w-2 h-2 rounded-full {{ $dot }} group-hover:scale-125 transition-transform"></span>
                  <span class="text-slate-700 dark:text-gray-300">
                    <span class="font-medium">{{ $text }}</span>
                    @if(!empty($a->actor))
                      <span class="text-slate-400 dark:text-gray-500 ml-2 text-xs">{{ $a->actor }}</span>
                    @endif
                  </span>
                </div>
                <span class="text-slate-400 dark:text-gray-500 text-xs">{{ optional($a->created_at)->diffForHumans() }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </section>

    {{-- Recent chats --}}
    <section class="rounded-2xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-md hover:shadow-lg transition-all duration-200 animate-fadeup" style="--stagger:240ms">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent Chat Sessions</h3>
        <a class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 rounded transition-colors" href="{{ route('admin.chatbot-sessions.index') }}">Open history →</a>
      </div>

      @if(($recentChatSessions ?? collect())->isEmpty())
        <div class="text-center py-12">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-gray-700 mb-3">
            <svg class="w-8 h-8 text-slate-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
          </div>
          <p class="text-sm text-slate-500 dark:text-gray-400">No chat sessions yet</p>
        </div>
      @else
        <ul class="divide-y divide-slate-100 dark:divide-gray-700" id="list-recent-chats">
          @foreach($recentChatSessions as $s)
            @php
              $risk = strtolower($s->risk_level ?? 'low');
              $dotClass = ($risk === 'high' ? 'bg-rose-500' : ($risk === 'moderate' ? 'bg-amber-500' : 'bg-indigo-500'));
            @endphp
            <li class="py-3 px-2 flex items-center justify-between rounded-md transition hover:bg-slate-50/60 dark:hover:bg-gray-700/50 cursor-pointer group">
              <div class="flex items-center gap-3">
                <span class="w-2 h-2 rounded-full {{ $dotClass }} group-hover:scale-125 transition-transform"></span>
                <div>
                  <span class="font-medium text-slate-900 dark:text-white">{{ $s->topic_summary ?: 'Starting conversation…' }}</span>
                  @if(!empty($s->actor))
                    <span class="text-xs text-slate-400 dark:text-gray-500 ml-2">{{ $s->actor }}</span>
                  @endif
                </div>
              </div>
              <span class="text-slate-400 dark:text-gray-500 text-sm">{{ optional($s->created_at)->diffForHumans() }}</span>
            </li>
          @endforeach
        </ul>
      @endif
    </section>

  </div>
@endsection

@push('styles')
<style>
/* Enhanced entrance animations */
@media (prefers-reduced-motion: no-preference) {
  .animate-fadeup {
    opacity: 0;
    transform: translateY(16px) scale(.98);
    animation: fadeUp .7s cubic-bezier(.22, 1, .36, 1) forwards;
    animation-delay: var(--stagger, 0ms);
  }
  @keyframes fadeUp {
    to { opacity: 1; transform: translateY(0) scale(1); }
  }
  
  /* Pulsing glow for critical alerts */
  .animate-pulse-glow {
    animation: pulseGlow 2s ease-in-out infinite;
  }
  @keyframes pulseGlow {
    0%, 100% { box-shadow: 0 0 20px rgba(244, 63, 94, 0.3); }
    50% { box-shadow: 0 0 30px rgba(244, 63, 94, 0.6), 0 0 40px rgba(244, 63, 94, 0.4); }
  }
}

/* Tooltip details reset */
details > summary::-webkit-details-marker { display: none; }
:where([data-kpi]) summary:focus-visible { outline: none; }

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  .animate-fadeup, .animate-pulse-glow {
    animation: none !important;
    opacity: 1 !important;
    transform: none !important;
  }
}
</style>
@endpush

@push('scripts')
{{-- Chart.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
(function(){
  const endpoint = "{{ route('admin.dashboard.stats') }}";
  const nf = new Intl.NumberFormat();
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

  // Counter animation
  function animateCounter(element) {
    const target = parseInt(element.dataset.target) || 0;
    const duration = 1500;
    const startTime = performance.now();
    
    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Easing function (ease-out-cubic)
      const easeOut = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(easeOut * target);
      
      element.textContent = nf.format(current);
      
      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        element.textContent = nf.format(target);
      }
    }
    
    requestAnimationFrame(update);
  }

  // Initialize counters on page load
  document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
      document.querySelectorAll('.counter-animate').forEach(animateCounter);
    }, 300);
  });

  function timeAgo(iso) {
    if (!iso) return '';
    const thenMs = new Date(iso).getTime();
    const diffSec = (thenMs - Date.now()) / 1000;
    const roundTZ = (n) => (n < 0 ? Math.ceil(n) : Math.floor(n));
    const units = [
      ['year', 31536000], ['month', 2592000], ['week', 604800],
      ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1],
    ];
    for (const [u, s] of units) {
      const v = diffSec / s;
      if (Math.abs(v) >= 1 || u === 'second') return rtf.format(roundTZ(v), u);
    }
  }

  const esc = (s) => (s ?? '').toString().replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));

  function statusDot(status) {
    const s = (status || '').toLowerCase();
    if (s.includes('cancel')) return 'bg-rose-500';
    if (s.includes('confirm')) return 'bg-emerald-500';
    if (s.includes('complete')) return 'bg-indigo-500';
    if (s.includes('pending')) return 'bg-amber-500';
    if (s.includes('critical') || s.includes('urgent')) return 'bg-rose-500';
    return 'bg-sky-500';
  }

  function liRecentAppointment(item) {
    const status = (item.status || 'scheduled').toLowerCase();
    const dot = statusDot(status);
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Scheduled';
    return `
      <li class="py-3 px-2 flex items-center justify-between rounded-md transition hover:bg-slate-50/60 dark:hover:bg-gray-700/50 cursor-pointer group">
        <div class="flex items-center gap-3">
          <span class="w-2 h-2 rounded-full ${dot} group-hover:scale-125 transition-transform"></span>
          <div>
            <div class="font-medium text-slate-900 dark:text-white">${esc(label)}</div>
            ${item.notes ? `<div class="text-xs text-slate-500 dark:text-gray-400 line-clamp-1">${esc(item.notes)}</div>` : ''}
          </div>
        </div>
        <span class="text-slate-400 dark:text-gray-500 text-xs">${esc(timeAgo(item.when) || '')}</span>
      </li>`;
  }

  function liActivity(a) {
    const isChat = (a.event || '').startsWith('chat_session');
    const dot = isChat ? 'bg-indigo-500' : ((a.event || '').startsWith('user.registered') ? 'bg-emerald-500' : 'bg-slate-400');
    const text = isChat ? `Chat session started: ${esc(a.meta || 'Starting conversation…')}` : ((a.event || '').startsWith('user.registered') ? 'New user registered' : 'Activity');
    const actor = a.actor ? `<span class="text-slate-400 dark:text-gray-500 ml-2 text-xs">${esc(a.actor)}</span>` : '';
    return `
      <li class="flex items-center justify-between group hover:bg-slate-50/60 dark:hover:bg-gray-700/50 -mx-2 px-2 py-2 rounded-lg transition">
        <div class="flex items-center gap-3">
          <span class="w-2 h-2 rounded-full ${dot} group-hover:scale-125 transition-transform"></span>
          <span class="text-slate-700 dark:text-gray-300 font-medium">${text}</span>
          ${actor}
        </div>
        <span class="text-slate-400 dark:text-gray-500 text-xs">${esc(timeAgo(a.created_at) || '')}</span>
      </li>`;
  }

  function liRecentChat(s) {
    const risk = (s.risk_level || 'low').toLowerCase();
    let dot = 'bg-indigo-500';
    if (risk === 'high') dot = 'bg-rose-500';
    else if (risk === 'moderate') dot = 'bg-amber-500';

    return `
      <li class="py-3 px-2 flex items-center justify-between rounded-md transition hover:bg-slate-50/60 dark:hover:bg-gray-700/50 cursor-pointer group">
        <div class="flex items-center gap-3">
          <span class="w-2 h-2 rounded-full ${dot} group-hover:scale-125 transition-transform"></span>
          <div>
            <span class="font-medium text-slate-900 dark:text-white">${esc(s.topic_summary || 'Starting conversation…')}</span>
            ${s.actor ? `<span class="text-xs text-slate-400 dark:text-gray-500 ml-2">${esc(s.actor)}</span>` : ''}
          </div>
        </div>
        <span class="text-slate-400 dark:text-gray-500 text-sm">${esc(timeAgo(s.created_at) || '')}</span>
      </li>`;
  }

  // Charts
  let appointmentsChart, sessionsChart;
  
  function initCharts() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#e5e7eb' : '#1f2937';
    const gridColor = isDark ? 'rgba(75, 85, 99, 0.3)' : 'rgba(0, 0, 0, 0.1)';
    
    // Appointments Line Chart
    const apptCtx = document.getElementById('appointmentsChart');
    if (apptCtx) {
      appointmentsChart = new Chart(apptCtx, {
        type: 'line',
        data: {
          labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
          datasets: [{
            label: 'Appointments',
            data: [12, 19, 15, 25, 22, 18, 24],
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: 'rgb(99, 102, 241)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: isDark ? 'rgba(31, 41, 55, 0.95)' : 'rgba(255, 255, 255, 0.95)',
              titleColor: textColor,
              bodyColor: textColor,
              borderColor: gridColor,
              borderWidth: 1,
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { color: textColor },
              grid: { color: gridColor }
            },
            x: {
              ticks: { color: textColor },
              grid: { display: false }
            }
          }
        }
      });
    }

    // Sessions Doughnut Chart
    const sessCtx = document.getElementById('sessionsChart');
    if (sessCtx) {
      sessionsChart = new Chart(sessCtx, {
        type: 'doughnut',
        data: {
          labels: ['Low Risk', 'Moderate Risk', 'High Risk'],
          datasets: [{
            data: [65, 25, 10],
            backgroundColor: [
              'rgba(99, 102, 241, 0.8)',
              'rgba(251, 191, 36, 0.8)',
              'rgba(244, 63, 94, 0.8)'
            ],
            borderColor: [
              'rgb(99, 102, 241)',
              'rgb(251, 191, 36)',
              'rgb(244, 63, 94)'
            ],
            borderWidth: 2,
            hoverOffset: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: textColor,
                padding: 15,
                font: { size: 12 }
              }
            },
            tooltip: {
              backgroundColor: isDark ? 'rgba(31, 41, 55, 0.95)' : 'rgba(255, 255, 255, 0.95)',
              titleColor: textColor,
              bodyColor: textColor,
              borderColor: gridColor,
              borderWidth: 1,
            }
          }
        }
      });
    }
  }

  let inflight;
  async function refresh() {
    try {
      if (inflight) inflight.abort();
      inflight = new AbortController();

      const res = await fetch(`${endpoint}?t=${Date.now()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
        signal: inflight.signal
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      const k = data.kpis || {};
      
      // Helper to update specific counter elements
      const updateCounter = (selector, value) => {
        const el = document.querySelector(selector);
        if (el) {
          el.textContent = nf.format(value ?? 0);
          el.dataset.target = value ?? 0;
        }
      };
      
      // Update each KPI counter with its specific value
      updateCounter('[data-target]:nth-of-type(1) .counter-animate', k.appointmentsTotal);
      updateCounter('[data-target]:nth-of-type(2) .counter-animate', k.criticalCasesTotal);
      updateCounter('[data-target]:nth-of-type(3) .counter-animate', k.activeCounselors);
      updateCounter('[data-target]:nth-of-type(4) .counter-animate', k.chatSessionsTotal || k.chatSessionsThisWeek);

      const setText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text || '= Same as last week';
      };
      setText('kpi-appointments-change', k.appointmentsTrend);
      setText('kpi-sessions-change', k.sessionsTrend);

      const put = (id, html) => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
      };

      const appts = (data.recentAppointments || []);
      put('list-recent-appointments',
        appts.length ? appts.map(liRecentAppointment).join('') : `<li class="py-3 text-sm text-slate-500 dark:text-gray-400 text-center">No appointments yet.</li>`
      );

      const acts = (data.activities || []);
      put('list-activities',
        acts.length ? acts.map(liActivity).join('') : `<li class="py-3 text-sm text-slate-500 dark:text-gray-400 text-center">No activity yet.</li>`
      );

      const chats = (data.recentChatSessions || []);
      put('list-recent-chats',
        chats.length ? chats.map(liRecentChat).join('') : `<li class="py-3 text-sm text-slate-500 dark:text-gray-400 text-center">No chat sessions yet.</li>`
      );
    } catch (e) {
      if (e.name !== 'AbortError') console.error('Dashboard refresh failed:', e);
    }
  }

  // Initialize on load
  document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    setTimeout(refresh, 600);
    setInterval(refresh, 5000);
  });
})();
</script>
@endpush
