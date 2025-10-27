{{-- resources/views/Counselor_Interface/dashboard.blade.php --}}
@extends('layouts.counselor')
@section('title','Counselor - Dashboard')
@section('page_title','Counselor Dashboard')

@section('content')
<div class="max-w-7xl mx-auto p-6 lg:p-8 space-y-7 select-none">

  {{-- HERO / QUICK ACTIONS --}}
  <div class="relative overflow-hidden rounded-3xl ring-1 ring-slate-200/80 bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 text-white">
    <div class="absolute inset-0 opacity-20 pointer-events-none" aria-hidden="true">
      <svg class="w-full h-full" viewBox="0 0 1200 600" preserveAspectRatio="none">
        <defs>
          <linearGradient id="dash_g" x1="0" x2="1" y1="0" y2="1">
            <stop stop-color="white" stop-opacity=".6"/>
            <stop offset=".6" stop-color="white" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <circle cx="180" cy="120" r="180" fill="url(#dash_g)"/>
        <circle cx="900" cy="420" r="260" fill="url(#dash_g)"/>
      </svg>
    </div>

    <div class="relative z-10 p-6 sm:p-8 lg:p-10">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <p class="text-xs/5 font-semibold uppercase tracking-widest text-white/85">Welcome back</p>
          <h2 class="mt-1 text-2xl sm:text-3xl font-black drop-shadow-sm">
            {{ Auth::user()->name ?? 'Counselor' }}
          </h2>
          <p class="mt-1 text-sm text-white/90">
            Manage your availability, review items, and keep an eye on today’s schedule.
          </p>

          {{-- QUICK ACTIONS (unified style + PNG icons) --}}
        <div class="mt-4 inline-flex flex-wrap gap-3">
          {{-- Manage Availability --}}
          <a href="{{ route('counselor.availability.index') }}"
            class="inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/15 backdrop-blur px-4 py-2.5 font-semibold ring-1 ring-white/20 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
            <span class="inline-grid place-items-center h-7 w-7 rounded-lg bg-white/10 ring-1 ring-white/20">
              <img src="{{ asset('images/icons/calendar.png') }}"
              class="h-4 w-4 object-contain filter invert brightness-200"
              alt="">
            </span>
            Manage Availability
          </a>

          {{-- View Appointments --}}
          <a href="{{ route('counselor.appointments.index') }}"
            class="inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/15 backdrop-blur px-4 py-2.5 font-semibold ring-1 ring-white/20 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
            <span class="inline-grid place-items-center h-7 w-7 rounded-lg bg-white/10 ring-1 ring-white/20">
              <img src="{{ asset('images/icons/appointment.png') }}" class="h-4 w-4 object-contain filter invert brightness-200"
              alt="">
            </span>
            View Appointments
          </a>
        </div>
        </div>

        {{-- Live clock --}}
        <div class="shrink-0">
          <div class="rounded-2xl bg-white/10 ring-1 ring-white/30 p-5 backdrop-blur min-w-[220px] text-center">
            <div id="dashClock" class="text-3xl font-black tabular-nums tracking-tight drop-shadow-sm">--:--</div>
            <div id="dashDate" class="mt-1 text-xs font-medium text-white/85">—</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- KPI CARDS --}}
  @php
    $todaysCount   = $todaysCount   ?? 0;
    $todaysDelta   = $todaysDelta   ?? 0;
    $pendingCount  = $pendingCount  ?? 0;
    $pendingDelta  = $pendingDelta  ?? 0;

    // ⬇️ use queue, not risk
    $queueCount    = $queueCount    ?? 0;
    $queueDelta    = $queueDelta    ?? 0;

    $openHours     = $openHours     ?? 0;
  @endphp

  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    {{-- Today’s Appointments --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm hover:shadow-md transition">
      <div class="absolute right-0 -top-6 opacity-10 group-hover:opacity-20 transition">
        <div class="h-24 w-24 rounded-full bg-indigo-500 blur-2xl"></div>
      </div>
      <div class="p-5">
        <div class="flex items-center gap-3">
          <span class="inline-grid place-items-center h-10 w-10 rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/>
            </svg>
          </span>
          <div class="text-sm font-semibold text-slate-600">Today’s Appointments</div>
        </div>
        <div class="mt-3 flex items-baseline gap-2">
          <div class="text-3xl font-black tabular-nums text-slate-900">{{ $todaysCount }}</div>
          <div class="text-xs font-semibold {{ ($todaysDelta ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
            {{ ($todaysDelta ?? 0) >= 0 ? '▲' : '▼' }} {{ abs($todaysDelta ?? 0) }}%
          </div>
        </div>
        <div class="text-[11px] text-slate-500 mt-1">All statuses today</div>
      </div>
      
      <div class="h-0.5 w-0 group-hover:w-full bg-indigo-500/70 transition-all duration-500"></div>
    </div>

    {{-- Pending --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm hover:shadow-md transition">
      <div class="absolute right-0 -top-6 opacity-10 group-hover:opacity-20 transition">
        <div class="h-24 w-24 rounded-full bg-amber-500 blur-2xl"></div>
      </div>
      <div class="p-5">
        <div class="flex items-center gap-3">
          <span class="inline-grid place-items-center h-10 w-10 rounded-xl bg-amber-50 text-amber-600 ring-1 ring-amber-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-width="2" d="M12 6v6l4 2"/>
            </svg>
          </span>
          <div class="text-sm font-semibold text-slate-600">Pending</div>
        </div>
        <div class="mt-3 flex items-baseline gap-2">
          <div class="text-3xl font-black tabular-nums text-slate-900">{{ $pendingCount }}</div>
          <div class="text-xs font-semibold {{ ($pendingDelta ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
            {{ ($pendingDelta ?? 0) >= 0 ? '▲' : '▼' }} {{ abs($pendingDelta ?? 0) }}%
          </div>
        </div>
      </div>
      <div class="h-0.5 w-0 group-hover:w-full bg-amber-500/70 transition-all duration-500"></div>
    </div>

    {{-- Placeholder: Queue (keep or remove) --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm hover:shadow-md transition">
    <div class="absolute right-0 -top-6 opacity-10 group-hover:opacity-20 transition">
      <div class="h-24 w-24 rounded-full bg-rose-500 blur-2xl"></div>
    </div>
    <div class="p-5">
      <div class="flex items-center gap-3">
        <span class="inline-grid place-items-center h-10 w-10 rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-200">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          </svg>
        </span>
        <div class="text-sm font-semibold text-slate-600">Queue</div>
      </div>
      <div class="mt-3 flex items-baseline gap-2">
        <div class="text-3xl font-black tabular-nums text-slate-900">{{ $queueCount }}</div>
        <div class="text-xs font-semibold {{ ($queueDelta ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
          {{ ($queueDelta ?? 0) >= 0 ? '▲' : '▼' }} {{ abs($queueDelta ?? 0) }}%
        </div>
      </div>
      <div class="text-[11px] text-slate-500 mt-1">Pending • Confirmed • Ongoing</div>
    </div>
    <div class="h-0.5 w-0 group-hover:w-full bg-rose-500/70 transition-all duration-500"></div>
  </div>

    {{-- Open Hours (This Week) --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm hover:shadow-md transition">
      <div class="absolute right-0 -top-6 opacity-10 group-hover:opacity-20 transition">
        <div class="h-24 w-24 rounded-full bg-emerald-500 blur-2xl"></div>
      </div>
      <div class="p-5">
        <div class="flex items-center gap-3">
          <span class="inline-grid place-items-center h-10 w-10 rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-width="2" d="M13 10h5L11 22l1-8H7l6-12-1 8z"/>
            </svg>
          </span>
          <div class="text-sm font-semibold text-slate-600">Open Hours (This Week)</div>
        </div>
        <div class="mt-3">
          <div class="text-3xl font-black tabular-nums text-slate-900">{{ $openHours }}</div>
          <div class="text-[11px] text-slate-500 mt-1">Hour slots available Mon–Fri</div>
        </div>
      </div>
      <div class="h-0.5 w-0 group-hover:w-full bg-emerald-500/70 transition-all duration-500"></div>
    </div>
  </div>

  {{-- TWO COLUMNS: Upcoming + (spacer) --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- UPCOMING --}}
    <div class="lg:col-span-2 rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/70">
        <div class="flex items-center gap-2">
          <span class="inline-grid place-items-center h-8 w-8 rounded-lg bg-indigo-50 text-indigo-600 ring-1 ring-indigo-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 5h14M5 19h14"/></svg>
          </span>
          <h3 class="font-bold text-slate-900">Upcoming Appointments</h3>
        </div>
        <a href="{{ route('counselor.appointments.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">View all →</a>
      </div>

      <div class="divide-y divide-slate-100">
        @forelse(($upcoming ?? []) as $row)
          @php
            $t12 = \Carbon\Carbon::parse($row['when'])->format('M d, Y • g:i A');
            $status = strtolower($row['status'] ?? 'pending');
            $badge = [
              'pending'   => 'bg-amber-50 text-amber-700 ring-amber-200',
              'confirmed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
              'ongoing'   => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
              'canceled'  => 'bg-slate-50 text-slate-600 ring-slate-200',
            ][$status] ?? 'bg-slate-50 text-slate-600 ring-slate-200';
          @endphp
          <a href="{{ route('counselor.appointments.show', $row['id']) }}"
             class="block px-5 py-4 hover:bg-slate-50/60 transition">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-semibold text-slate-900">{{ $row['student'] }}</div>
                <div class="text-sm text-slate-600 mt-0.5">{{ $t12 }}</div>
              </div>
              <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-lg ring-1 {{ $badge }}">
                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span> {{ ucfirst($status) }}
              </span>
            </div>
          </a>
        @empty
          <div class="px-5 py-10 text-center text-slate-500">
            <div class="font-semibold">No upcoming appointments</div>
            <div class="text-sm mt-1">Once students book, they’ll appear here.</div>
          </div>
        @endforelse
      </div>
    </div>

    {{-- Spacer / you can place another box here if needed --}}
    <div class="rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200/70">
        <h3 class="font-bold text-slate-900">Notes</h3>
      </div>
      <div class="px-5 py-6 text-sm text-slate-600">
        Nothing here yet.
      </div>
    </div>
  </div>

  {{-- TIP --}}
  <div class="rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm p-5">
    <div class="flex flex-wrap items-center gap-3 text-slate-600">
      <span class="inline-grid place-items-center h-8 w-8 rounded-lg bg-slate-100 ring-1 ring-slate-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-4 12.87V18a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-3.13A7 7 0 0 0 12 2zm1 17h-2v-2h2v2zm1.07-7.75-.9.92A1.5 1.5 0 0 0 12 13v1h-2v-1a3.5 3.5 0 0 1 1.02-2.49l1.24-1.26A1.5 1.5 0 1 0 9.5 7H8a3 3 0 0 1 6 0c0 .8-.32 1.56-.93 2.25z"/></svg>
      </span>
      <div class="text-sm">
        <b class="text-slate-800">Tip:</b> Use <span class="font-semibold">My Availability → Weekday Quick Editor</span>
        to quickly block common hours. The calendar modal lets you override a specific date.
      </div>
    </div>
  </div>
</div>

{{-- Tiny live clock --}}
<script>
(function(){
  const clock = document.getElementById('dashClock');
  const date  = document.getElementById('dashDate');
  function tick(){
    const now = new Date();
    const hh = now.getHours() % 12 || 12;
    const mm = String(now.getMinutes()).padStart(2,'0');
    const ap = now.getHours() >= 12 ? 'PM' : 'AM';
    clock.textContent = `${hh}:${mm} ${ap}`;
    date.textContent = new Intl.DateTimeFormat(undefined,{weekday:'long', month:'long', day:'numeric', year:'numeric'}).format(now);
  }
  tick(); setInterval(tick, 1000);
})();
</script>
@endsection
