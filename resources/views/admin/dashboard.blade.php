@extends('layouts.admin')

@section('title', 'Admin - Dashboard')
@section('page_title', 'Dashboard')  

@section('content')
  <div class="max-w-7xl mx-auto space-y-8">

    {{-- Dashboard Welcome Header --}}
    <section class="group rounded-3xl bg-indigo-50/50 dark:bg-slate-800/50 border border-indigo-100/80 dark:border-slate-700/50 shadow-2xl shadow-indigo-500/5 dark:shadow-none screen-only animate-fadeup relative overflow-hidden">
      {{-- Premium mesh background effect --}}
      <div class="absolute inset-0 opacity-40 dark:opacity-20 transition-opacity duration-1000 group-hover:opacity-60 dark:group-hover:opacity-30">
        <div class="absolute -top-[20%] -right-[10%] w-[50%] h-[80%] bg-indigo-200/50 dark:bg-indigo-500/20 rounded-full blur-[110px] animate-pulse"></div>
        <div class="absolute -bottom-[20%] -left-[10%] w-[50%] h-[80%] bg-purple-200/50 dark:bg-purple-500/20 rounded-full blur-[110px]"></div>
      </div>
      
      <div class="relative p-7 sm:p-9">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
          <div class="max-w-2xl">
            <nav class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.25em] text-indigo-600/60 dark:text-indigo-400/60 mb-3 px-0.5">
              <span>Admin Control Center</span>
              <span class="w-1.5 h-1.5 rounded-full bg-indigo-200 dark:bg-indigo-800"></span>
              <span>Overview</span>
            </nav>
            <h2 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight text-slate-900 dark:text-white">Welcome back, {{ auth()->user()->name ?? 'Admin' }}</h2>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium mt-3 leading-relaxed">Here's a live snapshot of your institution's health and engagement metrics for today.</p>
          </div>

          <div class="flex items-center gap-3 flex-wrap lg:flex-nowrap">
            <a href="{{ route('admin.appointments.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-indigo-100 dark:border-slate-700 text-indigo-700 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.15em] transition-all shadow-lg shadow-indigo-500/5 dark:shadow-none hover:shadow-indigo-500/10 active:scale-[.95]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              Quick Appointments
            </a>
            <a href="{{ route('admin.chatbot-sessions.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-[10px] font-bold uppercase tracking-[0.15em] transition-all shadow-xl shadow-indigo-500/20 active:scale-[.95]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
              Review Chats
            </a>
          </div>
        </div>
      </div>
    </section>

    {{-- Enhanced KPI / Stat cards --}}
    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 animate-fadeup" style="--stagger:60ms">

      {{-- Total Appointments --}}
      <div class="group relative rounded-3xl border border-slate-200/60 bg-white dark:bg-slate-800/80 p-5 shadow-xl shadow-slate-200/50 dark:shadow-none hover:shadow-2xl hover:shadow-indigo-500/10 transition-all duration-500 hover:-translate-y-1 overflow-hidden">
        {{-- Decorative background --}}
        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 dark:bg-indigo-900/20 rounded-full blur-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>

        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 transition-transform group-hover:scale-110 duration-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <div class="text-right flex-1 min-w-0">
              <div class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 whitespace-nowrap overflow-hidden text-ellipsis">Total Appointments</div>
              <div class="mt-1 text-2xl font-black text-slate-900 dark:text-white counter-animate" data-target="{{ $appointmentsTotal ?? 0 }}">{{ number_format($appointmentsTotal ?? 0) }}</div>
            </div>
          </div>
          <div class="flex items-center justify-between pt-4 border-t border-slate-50 dark:border-slate-700/50">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500">Past 7 Days</span>
            <span class="text-[10px] font-black px-2.5 py-1 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 tracking-tight" id="kpi-appointments-change">
              {{ $appointmentsTrend ?? 'Calculated automatically' }}
            </span>
          </div>
        </div>
      </div>

      {{-- Critical Cases --}}
      <div class="group relative rounded-3xl border border-slate-200/60 bg-white dark:bg-slate-800/80 p-5 shadow-xl shadow-slate-200/50 dark:shadow-none hover:shadow-2xl hover:shadow-rose-500/10 transition-all duration-500 hover:-translate-y-1 overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-rose-50 dark:bg-rose-900/20 rounded-full blur-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>

        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 transition-transform group-hover:scale-110 duration-500 {{ ($criticalCasesTotal ?? 0) > 0 ? 'animate-pulse' : '' }}">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </span>
            <div class="text-right flex-1 min-w-0">
              <div class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 whitespace-nowrap overflow-hidden text-ellipsis">Critical Cases</div>
              <div class="mt-1 text-2xl font-black text-slate-900 dark:text-white counter-animate" data-target="{{ $criticalCasesTotal ?? 0 }}">{{ number_format($criticalCasesTotal ?? 0) }}</div>
            </div>
          </div>
          <div class="flex items-center justify-between pt-4 border-t border-slate-50 dark:border-slate-700/50">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500">Requires Attention</span>
            @if(($criticalCasesTotal ?? 0) > 0)
              <a href="{{ route('admin.chatbot-sessions.index', ['only' => 'high']) }}" class="text-[10px] font-black px-2.5 py-1 rounded-xl bg-rose-50 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 tracking-tight hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors">
                View Risk Profile →
              </a>
            @else
              <span class="text-[10px] font-black px-2.5 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 tracking-tight">All Clear</span>
            @endif
          </div>
        </div>
      </div>

      {{-- Active Counselor --}}
      <div class="group relative rounded-3xl border border-slate-200/60 bg-white dark:bg-slate-800/80 p-5 shadow-xl shadow-slate-200/50 dark:shadow-none hover:shadow-2xl hover:shadow-amber-500/10 transition-all duration-500 hover:-translate-y-1 overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-amber-50 dark:bg-amber-900/20 rounded-full blur-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>

        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 transition-transform group-hover:scale-110 duration-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </span>
            <div class="text-right flex-1 min-w-0">
              <div class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 whitespace-nowrap overflow-hidden text-ellipsis">Available Staff</div>
              <div class="mt-1 text-2xl font-black text-slate-900 dark:text-white counter-animate" data-target="{{ $activeCounselors ?? 0 }}">{{ number_format($activeCounselors ?? 0) }}</div>
            </div>
          </div>
          <div class="flex items-center justify-between pt-4 border-t border-slate-50 dark:border-slate-700/50">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500">Active Now</span>
            <span class="text-[10px] font-black px-2.5 py-1 rounded-xl bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 tracking-tight">Online & Ready</span>
          </div>
        </div>
      </div>

      {{-- Chat Sessions --}}
      <div class="group relative rounded-3xl border border-slate-200/60 bg-white dark:bg-slate-800/80 p-5 shadow-xl shadow-slate-200/50 dark:shadow-none hover:shadow-2xl hover:shadow-indigo-500/10 transition-all duration-500 hover:-translate-y-1 overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 dark:bg-indigo-900/20 rounded-full blur-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>

        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 transition-transform group-hover:scale-110 duration-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </span>
            <div class="text-right flex-1 min-w-0">
              <div class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 whitespace-nowrap overflow-hidden text-ellipsis">Student Chats</div>
              <div class="mt-1 text-2xl font-black text-slate-900 dark:text-white counter-animate" data-target="{{ $chatSessionsTotal ?? $chatSessionsThisWeek ?? 0 }}">{{ number_format($chatSessionsTotal ?? $chatSessionsThisWeek ?? 0) }}</div>
            </div>
          </div>
          <div class="flex items-center justify-between pt-4 border-t border-slate-50 dark:border-slate-700/50">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500">Volume Trend</span>
            <span class="text-[10px] font-black px-2.5 py-1 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 tracking-tight" id="kpi-sessions-change">
              {{ $sessionsTrend ?? 'Active monitoring' }}
            </span>
          </div>
        </div>
      </div>
    </section>

    {{-- Charts Section --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fadeup" style="--stagger:120ms">
      
      {{-- Appointments Trend Chart --}}
      <div class="rounded-3xl border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-500/5">
        <div class="flex items-center justify-between mb-6">
          <div>
            <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">
              <span>Performance</span>
            </nav>
            <h3 class="text-lg font-black text-slate-900 leading-none">Appointments Trend</h3>
          </div>
          <div class="px-2 py-1 rounded-lg bg-slate-50 border border-slate-100">
            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Last 7 Days</span>
          </div>
        </div>
        <div class="h-64">
          <canvas id="appointmentsChart"></canvas>
        </div>
      </div>

      {{-- Session Distribution Chart --}}
      <div class="rounded-3xl border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-500/5">
        <div class="flex items-center justify-between mb-6">
          <div>
            <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">
              <span>Risk Analysis</span>
            </nav>
            <h3 class="text-lg font-black text-slate-900 leading-none">Risk Distribution</h3>
          </div>
          <div class="px-2 py-1 rounded-lg bg-slate-50 border border-slate-100">
            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Current Week</span>
          </div>
        </div>
        <div class="h-64">
          <canvas id="sessionsChart"></canvas>
        </div>
      </div>
    </section>

    {{-- Two-up content --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fadeup" style="--stagger:180ms">

      {{-- Recent appointments --}}
      <div class="rounded-3xl border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 transition-all duration-300 hover:shadow-2xl">
        <div class="flex items-center justify-between mb-6">
          <div>
            <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">
              <span>Scheduling</span>
            </nav>
            <h3 class="text-lg font-black text-slate-900 leading-none">Recent Appointments</h3>
          </div>
          <a class="text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:text-indigo-700 transition-colors italic" href="{{ route('admin.appointments.index') }}">View all →</a>
        </div>

        @if(($recentAppointments ?? collect())->isEmpty())
          <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-50 mb-3">
              <svg class="w-6 h-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">No appointments yet</p>
          </div>
        @else
          <ul class="divide-y divide-slate-50 text-sm" id="list-recent-appointments">
            @foreach($recentAppointments as $appt)
              @php
                $status = strtolower($appt->status ?? 'scheduled');
                $dotClass = (str_contains($status, 'cancel') ? 'bg-rose-500 shadow-rose-500/50' : (str_contains($status, 'confirm') ? 'bg-emerald-500 shadow-emerald-500/50' : (str_contains($status, 'complete') ? 'bg-indigo-500 shadow-indigo-500/50' : (str_contains($status, 'pending') ? 'bg-amber-500 shadow-amber-500/50' : ((str_contains($status,'critical') || str_contains($status,'urgent')) ? 'bg-rose-500 shadow-rose-500/50' : 'bg-sky-500 shadow-sky-500/50')))));
              @endphp
              <li class="py-3 px-1 flex items-center justify-between group cursor-pointer border-b border-transparent hover:border-slate-100 transition-all translate-x-0 hover:translate-x-1">
                <div class="flex items-center gap-4">
                  <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }} shadow-lg transition-transform group-hover:scale-150"></span>
                  <div>
                    <div class="font-black text-slate-900 text-xs tracking-tight">{{ $appt->status ? ucfirst($appt->status) : 'Scheduled' }}</div>
                    @if(!empty($appt->notes))
                      <div class="text-[11px] font-medium text-slate-400 line-clamp-1 mt-0.5 italic">"{{ $appt->notes }}"</div>
                    @endif
                  </div>
                </div>
                <span class="text-[10px] font-black uppercase text-slate-300 tracking-widest group-hover:text-indigo-400 transition-colors">{{ optional($appt->when)->diffForHumans() }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- System Activity --}}
      <div class="rounded-3xl border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 transition-all duration-300 hover:shadow-2xl">
        <div class="flex items-center justify-between mb-6">
          <div>
            <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">
              <span>Live Feed</span>
            </nav>
            <h3 class="text-lg font-black text-slate-900 leading-none">System Activity</h3>
          </div>
          <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-emerald-50 border border-emerald-100/50 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span class="text-[9px] font-black text-emerald-700 uppercase tracking-widest">Live Now</span>
          </div>
        </div>

        @if(($activities ?? collect())->isEmpty())
          <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-50 mb-3">
              <svg class="w-6 h-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">No activity yet</p>
          </div>
        @else
          <ul class="space-y-4 text-xs" id="list-activities">
            @foreach($activities as $a)
              @php
                $dot = str_starts_with($a->event, 'chat_session') ? 'bg-indigo-500 shadow-indigo-500/50' : (str_starts_with($a->event, 'user.registered') ? 'bg-emerald-500 shadow-emerald-500/50' : 'bg-slate-300 shadow-slate-300/50');
                $text = str_starts_with($a->event, 'chat_session') ? 'Chat session started' : (str_starts_with($a->event, 'user.registered') ? 'New user registered' : 'Activity');
                $subText = str_starts_with($a->event, 'chat_session') ? ($a->meta ?? 'Starting conversation…') : '';
              @endphp
              <li class="flex items-start justify-between group translate-x-0 hover:translate-x-1 transition-all">
                <div class="flex items-start gap-4">
                  <div class="mt-1.5 w-1.5 h-1.5 rounded-full {{ $dot }} shadow-lg transition-transform group-hover:scale-150"></div>
                  <div>
                    <div class="font-black text-slate-900 uppercase tracking-tight text-[11px]">{{ $text }}</div>
                    @if($subText) <div class="text-[10px] font-medium text-slate-400 mt-0.5 line-clamp-1 italic">"{{ $subText }}"</div> @endif
                    @if(!empty($a->actor)) <div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mt-1">{{ $a->actor }}</div> @endif
                  </div>
                </div>
                <span class="text-[10px] font-black uppercase text-slate-300 tracking-widest">{{ optional($a->created_at)->diffForHumans() }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </section>

    {{-- Recent chats --}}
    <section class="rounded-3xl border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 transition-all duration-300 hover:shadow-2xl animate-fadeup" style="--stagger:240ms">
      <div class="flex items-center justify-between mb-6">
        <div>
          <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">
            <span>Conversations</span>
          </nav>
          <h3 class="text-lg font-black text-slate-900 leading-none">History & Logs</h3>
        </div>
        <a class="text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:text-indigo-700 transition-colors italic" href="{{ route('admin.chatbot-sessions.index') }}">Full History →</a>
      </div>

      @if(($recentChatSessions ?? collect())->isEmpty())
        <div class="text-center py-12">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-50 mb-3">
            <svg class="w-6 h-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
          </div>
          <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">No chat sessions yet</p>
        </div>
      @else
        <ul class="divide-y divide-slate-50" id="list-recent-chats">
          @foreach($recentChatSessions as $s)
            @php
              $risk = strtolower($s->risk_level ?? 'low');
              $dotClass = ($risk === 'high' ? 'bg-rose-500 shadow-rose-500/50' : ($risk === 'moderate' ? 'bg-amber-500 shadow-amber-500/50' : 'bg-indigo-500 shadow-indigo-500/50'));
            @endphp
            <li class="py-3 px-1 flex items-center justify-between group cursor-pointer border-b border-transparent hover:border-slate-100 transition-all translate-x-0 hover:translate-x-1">
              <div class="flex items-center gap-4">
                <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }} shadow-lg transition-transform group-hover:scale-150"></span>
                <div>
                  <span class="font-black text-slate-900 text-sm tracking-tight">{{ $s->topic_summary ?: 'Starting conversation…' }}</span>
                  @if(!empty($s->actor))
                    <span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest ml-2">{{ $s->actor }}</span>
                  @endif
                </div>
              </div>
              <span class="text-[10px] font-black uppercase text-slate-300 tracking-widest group-hover:text-indigo-400 transition-colors">{{ optional($s->created_at)->diffForHumans() }}</span>
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
    const dotShadow = (status.includes('cancel') || status.includes('critical')) ? 'shadow-rose-500/50' : (status.includes('confirm') ? 'shadow-emerald-500/50' : (status.includes('complete') ? 'shadow-indigo-500/50' : (status.includes('pending') ? 'shadow-amber-500/50' : 'shadow-sky-500/50')));
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Scheduled';
    return `
      <li class="py-3 px-1 flex items-center justify-between group cursor-pointer border-b border-transparent hover:border-slate-100 dark:hover:border-slate-700 transition-all translate-x-0 hover:translate-x-1">
        <div class="flex items-center gap-4">
          <span class="w-1.5 h-1.5 rounded-full ${dot} ${dotShadow} shadow-lg transition-transform group-hover:scale-150"></span>
          <div>
            <div class="font-black text-slate-900 dark:text-white text-xs tracking-tight">${esc(label)}</div>
            ${item.notes ? `<div class="text-[11px] font-medium text-slate-400 dark:text-slate-500 mt-0.5 line-clamp-1">"${esc(item.notes)}"</div>` : ''}
          </div>
        </div>
        <span class="text-[10px] font-black uppercase text-slate-300 dark:text-slate-600 tracking-widest group-hover:text-indigo-400 transition-colors">${esc(timeAgo(item.when) || '')}</span>
      </li>`;
  }

  function liActivity(a) {
    const isChat = (a.event || '').startsWith('chat_session');
    const isUser = (a.event || '').startsWith('user.registered');
    const dot = isChat ? 'bg-indigo-500 shadow-indigo-500/50' : (isUser ? 'bg-emerald-500 shadow-emerald-500/50' : 'bg-slate-300 shadow-slate-300/50');
    const text = isChat ? 'Chat session started' : (isUser ? 'New user registered' : 'Activity');
    const subText = isChat ? (a.meta || 'Starting conversation…') : '';
    const actor = a.actor ? `<div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mt-1">${esc(a.actor)}</div>` : '';
    return `
      <li class="flex items-start justify-between group translate-x-0 hover:translate-x-1 transition-all">
        <div class="flex items-start gap-4">
          <div class="mt-1.5 w-1.5 h-1.5 rounded-full ${dot} shadow-lg transition-transform group-hover:scale-150"></div>
          <div>
            <div class="font-black text-slate-900 dark:text-white uppercase tracking-tight text-[11px]">${esc(text)}</div>
            ${subText ? `<div class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-0.5 line-clamp-1">"${esc(subText)}"</div>` : ''}
            ${actor}
          </div>
        </div>
        <span class="text-[10px] font-black uppercase text-slate-300 dark:text-slate-600 tracking-widest">${esc(timeAgo(a.created_at) || '')}</span>
      </li>`;
  }

  function liRecentChat(s) {
    const risk = (s.risk_level || 'low').toLowerCase();
    const dotClass = (risk === 'high' ? 'bg-rose-500 shadow-rose-500/50' : (risk === 'moderate' ? 'bg-amber-500 shadow-amber-500/50' : 'bg-indigo-500 shadow-indigo-500/50'));
    const actor = s.actor ? `<span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest ml-2">${esc(s.actor)}</span>` : '';

    return `
      <li class="py-3 px-1 flex items-center justify-between group cursor-pointer border-b border-transparent hover:border-slate-100 dark:hover:border-slate-700 transition-all translate-x-0 hover:translate-x-1">
        <div class="flex items-center gap-4">
          <span class="w-1.5 h-1.5 rounded-full ${dotClass} shadow-lg transition-transform group-hover:scale-150"></span>
          <div>
            <span class="font-black text-slate-900 dark:text-white text-sm tracking-tight">${esc(s.topic_summary || 'Starting conversation…')}</span>
            ${actor}
          </div>
        </div>
        <span class="text-[10px] font-black uppercase text-slate-300 dark:text-slate-600 tracking-widest group-hover:text-indigo-400 transition-colors">${esc(timeAgo(s.created_at) || '')}</span>
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
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        cache: 'no-store',
        signal: inflight.signal
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      // Check for theme change and refresh charts if needed
      const isDarkNow = document.documentElement.classList.contains('dark');
      if (typeof initCharts === 'function' && window.currentThemeIsDark !== isDarkNow) {
        if (appointmentsChart) appointmentsChart.destroy();
        if (sessionsChart) sessionsChart.destroy();
        initCharts();
        window.currentThemeIsDark = isDarkNow;
      }

      const k = data.kpis || {};
      
      // Helper to update specific counter elements
      const counters = document.querySelectorAll('.counter-animate');
      if (counters.length >= 4) {
        const updateByIndex = (idx, value) => {
          if (counters[idx]) {
            counters[idx].textContent = nf.format(value ?? 0);
            counters[idx].dataset.target = value ?? 0;
          }
        };
        updateByIndex(0, k.appointmentsTotal);
        updateByIndex(1, k.criticalCasesTotal);
        updateByIndex(2, k.activeCounselors);
        updateByIndex(3, k.chatSessionsTotal || k.chatSessionsThisWeek);
      }

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
    window.currentThemeIsDark = document.documentElement.classList.contains('dark');
    initCharts();
    setTimeout(refresh, 600);
    setInterval(refresh, 5000);
    
    // Listen for theme toggle button clicks to refresh charts immediately
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
      setTimeout(() => {
        const isDarkNow = document.documentElement.classList.contains('dark');
        if (window.currentThemeIsDark !== isDarkNow) {
          if (appointmentsChart) appointmentsChart.destroy();
          if (sessionsChart) sessionsChart.destroy();
          initCharts();
          window.currentThemeIsDark = isDarkNow;
        }
      }, 50); // small delay to wait for class toggle
    });
  });
})();
</script>
@endpush
