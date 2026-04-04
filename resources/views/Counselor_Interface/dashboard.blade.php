@extends('layouts.counselor')
@section('title','Counselor - Dashboard')
@section('page_title','Counselor Dashboard')

@section('content')
<div class="max-w-7xl mx-auto p-6 lg:p-8 space-y-7 select-none">

  {{-- Dashboard Welcome Header --}}
  <section class="group rounded-3xl bg-indigo-50/50 dark:bg-slate-800/50 border border-indigo-100/80 dark:border-slate-700/50 shadow-2xl shadow-indigo-500/5 dark:shadow-none animate-fadeup relative overflow-hidden">
    {{-- Premium mesh background effect --}}
    <div class="absolute inset-0 opacity-40 dark:opacity-20 transition-opacity duration-1000 group-hover:opacity-60 dark:group-hover:opacity-30">
      <div class="absolute -top-[20%] -right-[10%] w-[50%] h-[80%] bg-indigo-200/50 dark:bg-indigo-500/20 rounded-full blur-[110px] animate-pulse"></div>
      <div class="absolute -bottom-[20%] -left-[10%] w-[50%] h-[80%] bg-purple-200/50 dark:bg-purple-500/20 rounded-full blur-[110px]"></div>
    </div>
    
    <div class="relative p-7 sm:p-9">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="max-w-2xl">
          <nav class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.25em] text-indigo-600/60 dark:text-indigo-400/60 mb-3 px-0.5">
            <span>Counselor Control Center</span>
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-200 dark:bg-indigo-800"></span>
            <span>Dashboard</span>
          </nav>
          <h2 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight text-slate-900 dark:text-white">Welcome back, {{ auth()->user()->name ?? 'Counselor' }}</h2>
          <p class="text-slate-500 dark:text-slate-400 text-sm font-medium mt-3 leading-relaxed">Manage your availability, review sessions, and stay updated with today’s student engagement metrics.</p>
          
          {{-- QUICK ACTIONS --}}
          <div class="mt-6 flex items-center gap-3 flex-wrap">
            <a href="{{ route('counselor.availability.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-indigo-100 dark:border-slate-700 text-indigo-700 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.15em] transition-all shadow-lg shadow-indigo-500/5 dark:shadow-none hover:shadow-indigo-500/10 active:scale-[.95]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              Manage Availability
            </a>
            <a href="{{ route('counselor.appointments.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.15em] transition-all shadow-xl shadow-indigo-500/20 active:scale-[.95]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              View Appointments
            </a>
          </div>
        </div>

        {{-- Live clock --}}
        <div class="shrink-0">
          <div id="c-dash-clockbox" class="rounded-3xl bg-white/40 dark:bg-slate-800/40 border border-white/20 dark:border-slate-700/50 p-6 backdrop-blur-xl min-w-[220px] text-center shadow-2xl shadow-indigo-500/5 dark:shadow-none">
            <div id="dashClock" class="text-4xl font-black tabular-nums tracking-tighter text-indigo-600 dark:text-indigo-400">--:--</div>
            <div id="dashDate" class="mt-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">—</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- KPI CARDS --}}
  @php
    $todaysCount   = $todaysCount   ?? 0;
    $todaysDelta   = $todaysDelta   ?? 0;
    $pendingCount  = $pendingCount  ?? 0;
    $pendingDelta  = $pendingDelta  ?? 0;
    $queueCount    = $queueCount    ?? 0;  // use queue, not risk
    $queueDelta    = $queueDelta    ?? 0;
    $openHours     = $openHours     ?? 0;
  @endphp

  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    {{-- Today’s Appointments --}}
    <div id="c-kpi-today" class="group relative overflow-hidden rounded-[32px] bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/50 shadow-xl shadow-slate-200/40 dark:shadow-none hover:shadow-2xl hover:shadow-indigo-500/20 transition-all duration-500 hover:-translate-y-1.5 active:scale-95">
      <div class="p-6 relative z-10">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500 mb-1">Today's</span>
            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-900 dark:text-white">Appointments</span>
          </div>
          <span class="inline-flex items-center justify-center h-12 w-12 rounded-2xl bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-900/40 dark:to-slate-800 text-indigo-600 dark:text-indigo-400 shadow-inner rotate-3 group-hover:rotate-0 transition-transform duration-500 border border-indigo-100/50 dark:border-indigo-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/></svg>
          </span>
        </div>
        
        <div class="mt-4 flex items-end justify-between">
          <div class="text-4xl font-black text-slate-900 dark:text-white tabular-nums tracking-tighter">{{ $todaysCount }}</div>
          <div class="mb-1.5">
            <span @class(['flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-full border tracking-tight uppercase', 
                ($todaysDelta >= 0) ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-500/20' : 'bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 border-rose-100 dark:border-rose-500/20'])>
              <span class="text-[8px]">{{ ($todaysDelta >= 0) ? '▲' : '▼' }}</span> {{ abs($todaysDelta) }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Pending --}}
    <div id="c-kpi-pending" class="group relative overflow-hidden rounded-[32px] bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/50 shadow-xl shadow-slate-200/40 dark:shadow-none hover:shadow-2xl hover:shadow-amber-500/20 transition-all duration-500 hover:-translate-y-1.5 active:scale-95">
      <div class="p-6 relative z-10">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500 mb-1">Status</span>
            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-900 dark:text-white">Pending Review</span>
          </div>
          <span class="inline-flex items-center justify-center h-12 w-12 rounded-2xl bg-gradient-to-br from-amber-50 to-white dark:from-amber-900/40 dark:to-slate-800 text-amber-600 dark:text-amber-400 shadow-inner -rotate-3 group-hover:rotate-0 transition-transform duration-500 border border-amber-100/50 dark:border-amber-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/></svg>
          </span>
        </div>
        
        <div class="mt-4 flex items-end justify-between">
          <div class="text-4xl font-black text-slate-900 dark:text-white tabular-nums tracking-tighter">{{ $pendingCount }}</div>
          <div class="mb-1.5">
            <span class="flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20 tracking-tight uppercase">
                ACTION REQUIRED
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Queue --}}
    <div id="c-kpi-queue" class="group relative overflow-hidden rounded-[32px] bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/50 shadow-xl shadow-slate-200/40 dark:shadow-none hover:shadow-2xl hover:shadow-rose-500/20 transition-all duration-500 hover:-translate-y-1.5 active:scale-95">
      <div class="p-6 relative z-10">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500 mb-1">Queue</span>
            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-900 dark:text-white">Active Sessions</span>
          </div>
          <span class="inline-flex items-center justify-center h-12 w-12 rounded-2xl bg-gradient-to-br from-rose-50 to-white dark:from-rose-900/40 dark:to-slate-800 text-rose-600 dark:text-rose-400 shadow-inner rotate-6 group-hover:rotate-0 transition-transform duration-500 border border-rose-100/50 dark:border-rose-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10h5L11 22l1-8H7l6-12-1 8z"/></svg>
          </span>
        </div>
        
        <div class="mt-4 flex items-end justify-between">
          <div class="text-4xl font-black text-slate-900 dark:text-white tabular-nums tracking-tighter">{{ $queueCount }}</div>
          <div class="mb-1.5">
             <span class="flex items-center gap-1.5 text-[10px] font-black px-2.5 py-1 rounded-full bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-500/20 tracking-tight uppercase">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                </span>
                LIVE LOAD
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Capacity --}}
    <div id="c-kpi-openhours" class="group relative overflow-hidden rounded-[32px] bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/50 shadow-xl shadow-slate-200/40 dark:shadow-none hover:shadow-2xl hover:shadow-emerald-500/20 transition-all duration-500 hover:-translate-y-1.5 active:scale-95">
      <div class="p-6 relative z-10">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500 mb-1">Capacity</span>
            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-900 dark:text-white">Weekly Slots</span>
          </div>
          <span class="inline-flex items-center justify-center h-12 w-12 rounded-2xl bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-900/40 dark:to-slate-800 text-emerald-600 dark:text-emerald-400 shadow-inner -rotate-6 group-hover:rotate-0 transition-transform duration-500 border border-emerald-100/50 dark:border-emerald-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </span>
        </div>
        
        <div class="mt-4 flex items-end justify-between">
          <div class="text-4xl font-black text-slate-900 dark:text-white tabular-nums tracking-tighter">{{ $openHours }}</div>
          <div class="mb-1.5">
            <span class="flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20 tracking-tight uppercase">
                ON TRACK
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- TWO COLUMNS: Upcoming + Notes --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fadeup" style="--stagger:120ms">
    {{-- UPCOMING --}}
    <div class="lg:col-span-2 rounded-3xl bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/50 shadow-xl shadow-slate-200/50 dark:shadow-none overflow-hidden hover:shadow-2xl hover:shadow-indigo-500/5 transition-all duration-500">
      <div id="c-upcoming-head" class="flex items-center justify-between px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/30 dark:bg-slate-900/20">
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center justify-center h-8 w-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 5h14M5 19h14"/></svg>
          </span>
          <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-900 dark:text-white">Upcoming Appointments</h3>
        </div>
        <a id="c-upcoming-viewall" href="{{ route('counselor.appointments.index') }}" class="text-[10px] font-black uppercase tracking-[0.1em] text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors">View all →</a>
      </div>

      <div id="c-upcoming-list" class="divide-y divide-slate-50 dark:divide-slate-700/50">
        @forelse(($upcoming ?? []) as $row)
          @php
            $t12 = \Carbon\Carbon::parse($row['when'])->format('M d, Y • g:i A');
            $status = strtolower($row['status'] ?? 'pending');
            $badge = [
              'pending'   => 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-900/30',
              'confirmed' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-900/30',
              'ongoing'   => 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 border-indigo-100 dark:border-indigo-900/30',
              'canceled'  => 'bg-slate-50 dark:bg-slate-900/20 text-slate-500 dark:text-slate-400 border-slate-100 dark:border-slate-800',
            ][$status] ?? 'bg-slate-50 dark:bg-slate-900/20 text-slate-500 dark:text-slate-400 border-slate-100 dark:border-slate-800';
          @endphp
          <a href="{{ route('counselor.appointments.show', $row['id']) }}"
             class="group block px-6 py-4 hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-all duration-300">
            <div class="flex items-center justify-between gap-4">
              <div class="flex-1 min-w-0 transition-transform group-hover:translate-x-1 duration-300">
                <div class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $row['student'] }}</div>
                <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-1">{{ $t12 }}</div>
              </div>
              <span class="inline-flex items-center gap-1.5 text-[10px] font-black tracking-tight px-2.5 py-1 rounded-xl border {{ $badge }} uppercase">
                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span> {{ $status }}
              </span>
            </div>
          </a>
        @empty
          <div class="px-6 py-12 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-50 dark:bg-slate-900/40 text-slate-400 mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">No upcoming appointments</div>
            <div class="text-[11px] text-slate-400 mt-2">New bookings will appear here automatically.</div>
          </div>
        @endforelse
      </div>
    </div>

    {{-- Notes --}}
    <div id="c-dash-notes" class="rounded-3xl bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/50 shadow-xl shadow-slate-200/50 dark:shadow-none overflow-hidden flex flex-col hover:shadow-2xl hover:shadow-indigo-500/5 transition-all duration-500">
      <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/30 dark:bg-slate-900/20">
        <span class="inline-flex items-center justify-center h-8 w-8 rounded-xl bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </span>
        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-900 dark:text-white">Professional Notes</h3>
      </div>
      <div class="p-6 text-[13px] text-slate-500 dark:text-slate-400 leading-relaxed italic">
        Draft private session notes or reminders for students here. This section is visible only to you.
      </div>
      <div class="mt-auto p-6 pt-0">
        <button class="w-full py-3 rounded-2xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
          Create New Note
        </button>
      </div>
    </div>
  </div>

  {{-- TIP --}}
  <div id="c-dash-tip" class="rounded-3xl bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/50 shadow-xl shadow-slate-200/50 dark:shadow-none p-6 animate-fadeup" style="--stagger:180ms">
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
      <span class="inline-flex items-center justify-center h-10 w-10 rounded-2xl bg-slate-50 dark:bg-slate-900/40 text-slate-600 dark:text-slate-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-4 12.87V18a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-3.13A7 7 0 0 0 12 2zm1 17h-2v-2h2v2zm1.07-7.75-.9.92A1.5 1.5 0 0 0 12 13v1h-2v-1a3.5 3.5 0 0 1 1.02-2.49l1.24-1.26A1.5 1.5 0 1 0 9.5 7H8a3 3 0 0 1 6 0c0 .8-.32 1.56-.93 2.25z"/></svg>
      </span>
      <div class="text-[13px] text-slate-500 dark:text-slate-500 leading-relaxed">
        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-900 dark:text-white block mb-1">Redesign Tip:</span>
        Use <span class="font-bold text-indigo-600 dark:text-indigo-400">My Availability → Weekday Quick Editor</span> to mass-update your schedule. Individual date overrides can be managed directly via the Calendar Modal.
      </div>
    </div>
  </div>
</div>

{{-- Premium Live Clock Engine --}}
<script>
(function(){
  const clockEl = document.getElementById('dashClock');
  const dateEl  = document.getElementById('dashDate');
  
  function update(){
    const now = new Date();
    
    // Time Components
    let hh = now.getHours();
    const ap = hh >= 12 ? 'PM' : 'AM';
    hh = hh % 12 || 12;
    const mm = String(now.getMinutes()).padStart(2, '0');
    const ss = String(now.getSeconds()).padStart(2, '0');
    
    // Date Components (Uppercase to match premium style)
    const options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
    const dateStr = new Intl.DateTimeFormat('en-US', options).format(now).toUpperCase();

    if (clockEl) {
      clockEl.innerHTML = `
        <span class="tabular-nums">${hh}:${mm}</span><span class="text-[0.6em] opacity-70 ml-1.5 align-middle tracking-normal select-none">:${ss}</span>
        <span class="text-[0.45em] ml-2 font-black tracking-widest text-indigo-400/80 dark:text-indigo-500/80 select-none">${ap}</span>
      `;
    }
    if (dateEl) dateEl.textContent = dateStr;
  }

  // --- KPI Count-up Engine ---
  function animateCounter(el) {
      if (!el) return;
      const target = parseInt(el.textContent) || 0;
      if (target === 0) return;
      let count = 0;
      const speed = 1500 / target; // Max 1.5s total duration
      
      el.textContent = '0';
      const timer = setInterval(() => {
          count += Math.ceil(target / 40); // Increment chunk
          if (count >= target) {
              el.textContent = target;
              clearInterval(timer);
          } else {
              el.textContent = count;
          }
      }, 30);
  }

  // Select all KPI number divs
  document.querySelectorAll('.tabular-nums:not(#dashClock *)').forEach(animateCounter);

  update(); 
  setInterval(update, 1000);
})();
</script>
@endsection
