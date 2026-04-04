@extends('layouts.counselor')
@section('title', 'Counselor - Appointments')
@section('page_title', 'Counselor - Appointments')

@php
  use Carbon\Carbon;

  // Preserve current filters (fallback to request values)
  $status = $status ?? request('status', 'all');
  $period = $period ?? request('period', 'all');
  $q = $q ?? request('q', '');

  $statusOptions = [
    'all' => 'All',
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'ongoing' => 'Ongoing',
    'completed' => 'Completed',
    'canceled' => 'Canceled',
    'no_show' => 'No Show',
  ];
  $periodOptions = [
    'all' => 'All Dates',
    'upcoming' => 'Upcoming',
    'today' => 'Today',
    'this_week' => 'This Week',
    'this_month' => 'This Month',
    'past' => 'Past',
  ];

  // Status chip tokens (matches your system palette)
  $statusMap = [
    'pending' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-200', 'dot' => 'bg-amber-500', 'label' => 'Pending'],
    'confirmed' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'ring' => 'ring-blue-200', 'dot' => 'bg-blue-500', 'label' => 'Confirmed'],
    'ongoing' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'ring' => 'ring-indigo-200', 'dot' => 'bg-indigo-600', 'label' => 'Ongoing'],
    'completed' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Completed'],
    'canceled' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'ring' => 'ring-rose-200', 'dot' => 'bg-rose-500', 'label' => 'Canceled'],
    'no_show' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'ring' => 'ring-rose-200', 'dot' => 'bg-rose-500', 'label' => 'No Show'],
  ];

  // current time reused for “ongoing walk-in” detection
  $now = Carbon::now();
@endphp

@section('content')
  <div class="w-full space-y-6">

    {{-- ========= Header band (Clean SaaS Layout) ========= --}}
    @php $totalAppointments = $appointments->total(); @endphp
    <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
      <div class="flex flex-col gap-1.5">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Appointments</h1>
          <div
            class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20">
            {{ number_format($totalAppointments) }} Total
          </div>
        </div>
        <p class="text-slate-500 text-sm font-medium">View and manage booked counseling sessions.</p>
      </div>

      <div class="flex items-center gap-3">
        <a href="{{ route('counselor.appointments.export.index.pdf', ['status' => $status, 'period' => $period, 'q' => $q]) }}"
          target="_blank"
          class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
          <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-colors" viewBox="0 0 24 24" fill="none"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
              d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z" />
          </svg>
          Export PDF
        </a>
      </div>
    </header>

    {{-- ========= Filters (Modern Search like Admin) ========= --}}
    <form id="apptSearchForm" method="GET" action="{{ route('counselor.appointments.index') }}">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center mb-6">
        <div class="relative flex-1 group min-w-[200px]">
          <input id="appt-q" type="text" name="q" value="{{ $q }}" placeholder="Search student..." autocomplete="off"
            class="h-12 w-full rounded-2xl border-none bg-white px-11 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 group-hover:ring-slate-300"
            @if($q !== '') autofocus @endif />
          <svg
            class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400 group-hover:text-indigo-500 transition-colors duration-200"
            viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2.5"></circle>
            <path d="M21 21l-4.3-4.3" stroke-width="2.5" stroke-linecap="round"></path>
          </svg>

          @if($q)
            <button type="button"
              onclick="let p = document.createElement('input'); p.type='hidden'; p.name='page'; p.value=''; this.form.appendChild(p); document.getElementById('appt-q').value=''; this.form.submit();"
              class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all"
              aria-label="Clear search">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          @endif
        </div>

        <div class="flex items-center gap-3">
          <select name="status"
            class="h-12 rounded-2xl border-none bg-white px-4 py-0 pl-4 pr-10 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 hover:ring-slate-300">
            @foreach ($statusOptions as $value => $label)
              <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
          </select>

          <select name="period"
            class="h-12 rounded-2xl border-none bg-white px-4 py-0 pl-4 pr-10 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 hover:ring-slate-300">
            @foreach ($periodOptions as $value => $label)
              <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
            @endforeach
          </select>

          <a href="{{ route('counselor.appointments.index') }}"
            class="h-12 inline-flex items-center gap-2 rounded-2xl bg-white px-5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 group transition-all duration-200">
            <svg class="h-4 w-4 text-slate-400 group-hover:rotate-180 transition-transform duration-500"
              viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <path d="M12 8v8M8 12h8" stroke-width="2.5" stroke-linecap="round" />
            </svg>
            Reset
          </a>
        </div>
      </div>
    </form>

    {{-- ========= Table ========= --}}
    <div
      class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 transition-all duration-300 flex flex-col">
      <div class="relative overflow-visible rounded-t-3xl border-b border-transparent">
        <table class="min-w-full text-sm leading-relaxed table-auto">
          <thead x-data="{ scrolled: false }"
            x-init="const s = document.querySelector('.panel-scroll') || window; s.addEventListener('scroll', () => scrolled = (s.scrollTop || window.scrollY) > 10)"
            class="text-slate-700 sticky top-0 z-20">
            <tr>
              <th :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'"
                class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300">
                APPOINTMENT #</th>
              <th
                class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                Student</th>
              <th
                class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                Date &amp; Time</th>
              <th
                class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                Booked On</th>
              <th
                class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                Status</th>
              <th :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'"
                class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300">
                Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100/60 dark:divide-slate-700/50">
            @forelse($appointments as $row)
              @php
                $dt = Carbon::parse($row->scheduled_at);
                $bookedAt = $row->booked_at ? Carbon::parse($row->booked_at) : null;

                // WALK-IN based on appointment_source
                $sourceRaw = strtolower((string) ($row->appointment_source ?? ''));
                $isWalkIn = in_array($sourceRaw, ['walk_in', 'walk-in', 'walk in'], true);

                // 👉 Always use the real DB status.
                //    No more “virtual ongoing” for completed walk-ins.
                $effectiveStatus = strtolower((string) $row->status);

                // status chip
                $s = $statusMap[$effectiveStatus] ?? [
                  'bg' => 'bg-slate-50',
                  'text' => 'text-slate-700',
                  'ring' => 'ring-slate-200',
                  'dot' => 'bg-slate-400',
                  'label' => ucfirst($effectiveStatus),
                ];
                $chipCl = "{$s['bg']} {$s['text']} ring-1 {$s['ring']}";
                $dotCl = $s['dot'];
                $label = $s['label'];

                $crPending = isset($row->cr_status) && $row->cr_status === 'requested';
                $crTime = !empty($row->cr_created_at)
                  ? Carbon::parse($row->cr_created_at)->diffForHumans()
                  : null;
              @endphp

              <tr class="align-middle group hover:bg-indigo-50/30 transition-colors duration-200">
                <td class="px-6 py-4 font-bold text-slate-800">APN-{{ str_pad($row->id, 3, '0', STR_PAD_LEFT) }}</td>

                <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                  @php
                    $avatarUrl = null;
                    if (isset($row->profile_picture) && $row->profile_picture) {
                      $avatarUrl = asset('storage/' . $row->profile_picture);
                    } elseif (isset($row->student_id)) {
                      $avatarUrl = optional(\App\Models\User::find($row->student_id))->avatar_url;
                    }
                    $init = mb_strtoupper(collect(explode(' ', $row->student_name))->map(fn($n) => $n[0] ?? '')->take(2)->join(''));
                  @endphp
                  <div class="flex items-center gap-3">
                    @if($avatarUrl)
                      <img src="{{ $avatarUrl }}" alt="{{ $row->student_name }}"
                        class="w-8 h-8 rounded-full object-cover shadow-sm ring-1 ring-slate-200">
                    @else
                      <div
                        class="w-8 h-8 flex-shrink-0 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-[10px] ring-1 ring-indigo-100/50 uppercase shadow-sm">
                        {{ $init }}
                      </div>
                    @endif
                    <div class="flex flex-col">
                      <span class="font-bold text-slate-800">{{ $row->student_name }}</span>

                      {{-- Walk-in chip --}}
                      @if($isWalkIn)
                        <span
                          class="mt-1 inline-flex w-max items-center px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-500/20 shadow-sm leading-none">
                          <span class="inline-block size-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                          WALK-IN
                        </span>
                      @endif
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="leading-tight">
                    <div class="font-medium text-slate-900">{{ $dt->format('M d, Y') }}</div>
                    <div class="text-slate-500 text-xs">{{ $dt->format('g:i A') }}</div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  @if($bookedAt)
                    <div class="leading-tight">
                      <div class="font-medium text-slate-900">{{ $bookedAt->format('M d, Y') }}</div>
                      <div class="text-slate-500 text-xs">{{ $bookedAt->format('g:i A') }}</div>
                    </div>
                  @else
                    <span class="text-slate-400">—</span>
                  @endif
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="relative inline-flex items-center h-6 px-3 rounded-full text-[10px] font-bold tracking-wide leading-none {{ $chipCl }}">
                    <span class="absolute left-2 inline-block size-1.5 rounded-full {{ $dotCl }}"></span>
                    <span class="ml-2.5">{{ strtoupper($label) }}</span>
                  </span>

                  @if($crPending)
                    <div class="mt-1">
                      <span
                        class="inline-flex items-center gap-1.5 rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-200"
                        title="Student requested counselor reassignment for this appointment.">
                        <span class="inline-block size-1.5 rounded-full bg-violet-500"></span>
                        Change requested
                        @if($crTime) <span class="text-violet-500/70">• {{ $crTime }}</span> @endif
                      </span>
                    </div>
                  @endif
                </td>

                <td class="px-6 py-4 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('counselor.appointments.show', $row->id) }}"
                      class="inline-flex items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 px-4 py-2 text-xs font-bold hover:bg-indigo-600 hover:text-white transition-all duration-200">
                      View
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-6 py-10 text-center text-slate-500">No appointments found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($appointments->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 rounded-b-3xl">
          {{ $appointments->withQueryString()->links() }}
        </div>
      @endif
    </div>
  </div>

  {{-- =========================
  Re-Assigned Appointments
  ========================= --}}
  @if(isset($reassignedAppointments) && $reassignedAppointments->count() > 0)
    <div class="max-w-7xl mx-auto space-y-4 mt-6">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">
            Re-Assigned Appointments
          </h3>
          <p class="text-xs text-slate-500">
            Appointments that were previously assigned to you and later reassigned to another counselor.
          </p>
        </div>
        <div class="text-xs text-slate-500">
          Total: <span class="font-semibold">{{ $reassignedAppointments->count() }}</span>
        </div>
      </div>

      <div
        class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 transition-all duration-300 flex flex-col">
        <div class="relative overflow-visible rounded-t-3xl border-b border-transparent">
          <table class="min-w-full text-sm leading-relaxed table-auto">
            <thead x-data="{ scrolled: false }"
              x-init="const s = document.querySelector('.panel-scroll') || window; s.addEventListener('scroll', () => scrolled = (s.scrollTop || window.scrollY) > 10)"
              class="text-slate-700 sticky top-0 z-20">
              <tr>
                <th :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'"
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300">
                  APPOINTMENT #</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Student</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Date &amp; Time</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Booked On</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Status</th>
                <th :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'"
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300">
                  Actions</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-700/50">
              @foreach($reassignedAppointments as $row)
                @php
                  $dt = Carbon::parse($row->scheduled_at);
                  $bookedAt = $row->booked_at ? Carbon::parse($row->booked_at) : null;
                  $changedLabel = $row->changed_at
                    ? Carbon::parse($row->changed_at)->diffForHumans()
                    : null;

                  // Walk-in detection for history rows (if "type" is selected in controller)
                  $hasType = property_exists($row, 'type');
                  $isWalkIn = $hasType && $row->type === 'walk-in';
                @endphp

                <tr class="align-middle hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                  <td class="px-6 py-4 font-semibold text-slate-900">APN-{{ str_pad($row->id, 3, '0', STR_PAD_LEFT) }}</td>

                  <td class="px-6 py-4">
                    @php
                      $avatarUrl = null;
                      if (isset($row->profile_picture) && $row->profile_picture) {
                        $avatarUrl = asset('storage/' . $row->profile_picture);
                      } elseif (isset($row->student_id)) {
                        $avatarUrl = optional(\App\Models\User::find($row->student_id))->avatar_url;
                      }
                      $init = mb_strtoupper(collect(explode(' ', $row->student_name))->map(fn($n) => $n[0] ?? '')->take(2)->join(''));
                    @endphp
                    <div class="flex items-center gap-3">
                      @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $row->student_name }}"
                          class="w-8 h-8 rounded-full object-cover shadow-sm ring-1 ring-slate-200">
                      @else
                        <div
                          class="w-8 h-8 flex-shrink-0 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-[10px] ring-1 ring-indigo-100/50 uppercase shadow-sm">
                          {{ $init }}
                        </div>
                      @endif
                      <div class="flex flex-col">
                        <span class="font-medium text-slate-900">{{ $row->student_name }}</span>
                        @if(!empty($row->student_email))
                          <span class="text-slate-500 text-xs">{{ $row->student_email }}</span>
                        @endif

                        @if($isWalkIn)
                          <div class="mt-1">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium 
                                              bg-amber-50 text-slate-800 ring-1 ring-amber-200">
                              <span class="inline-block size-1.5 rounded-full bg-amber-400 mr-1.5"></span>
                              Walk-in
                            </span>
                          </div>
                        @endif
                      </div>
                    </div>
                  </td>

                  <td class="px-6 py-4">
                    <div class="leading-tight">
                      <div class="font-medium text-slate-900">{{ $dt->format('M d, Y') }}</div>
                      <div class="text-slate-500 text-xs">{{ $dt->format('g:i A') }}</div>
                    </div>
                  </td>

                  <td class="px-6 py-4">
                    @if($bookedAt)
                      <div class="leading-tight">
                        <div class="font-medium text-slate-900">{{ $bookedAt->format('M d, Y') }}</div>
                        <div class="text-slate-500 text-xs">{{ $bookedAt->format('g:i A') }}</div>
                      </div>
                    @else
                      <span class="text-slate-400">—</span>
                    @endif
                  </td>

                  <td class="px-6 py-4">
                    <span class="inline-flex items-center h-7 rounded-full bg-slate-900 text-white text-xs font-medium px-3">
                      <span class="inline-block size-1.5 rounded-full bg-yellow-300 mr-1.5"></span>
                      Re-Assigned
                    </span>
                    @if($changedLabel)
                      <div class="mt-1 text-[11px] text-slate-500">
                        Reassigned {{ $changedLabel }}
                      </div>
                    @endif
                  </td>

                  <td class="px-6 py-4 text-right">
                    <a href="{{ route('counselor.appointments.show', $row->id) }}"
                      class="inline-flex items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 px-4 py-2 text-xs font-bold hover:bg-indigo-600 hover:text-white transition-all duration-200">
                      View
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- =========================
  Rescheduled Appointments
  ========================= --}}
  @if(isset($rescheduledAppointments) && $rescheduledAppointments->count() > 0)
    <div class="max-w-7xl mx-auto space-y-4 mt-6">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">
            Rescheduled Appointments
          </h3>
          <p class="text-xs text-slate-500">
            Appointments where the schedule or counselor was changed.
          </p>
        </div>
        <div class="text-xs text-slate-500">
          Total: <span class="font-semibold">{{ $rescheduledAppointments->count() }}</span>
        </div>
      </div>

      <div
        class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 transition-all duration-300 flex flex-col">
        <div class="relative overflow-visible rounded-t-3xl border-b border-transparent">
          <table class="min-w-full text-sm leading-relaxed table-auto">
            <thead x-data="{ scrolled: false }"
              x-init="const s = document.querySelector('.panel-scroll') || window; s.addEventListener('scroll', () => scrolled = (s.scrollTop || window.scrollY) > 10)"
              class="text-slate-700 sticky top-0 z-20">
              <tr>
                <th :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'"
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300">
                  APPOINTMENT #</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Student</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Old Schedule</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  New Schedule</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Counselor Change</th>
                <th
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">
                  Changed On</th>
                <th :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'"
                  class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300">
                  Actions</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-700/50">
              @foreach($rescheduledAppointments as $row)
                @php
                  $old = $row->old_scheduled_at ? Carbon::parse($row->old_scheduled_at) : null;
                  $new = $row->new_scheduled_at ? Carbon::parse($row->new_scheduled_at) : null;
                  $changedLabel = $row->changed_at
                    ? Carbon::parse($row->changed_at)->diffForHumans()
                    : null;

                  $oldCoun = $row->old_counselor_name ?? null;
                  $newCoun = $row->new_counselor_name ?? null;
                  $counselorChange = trim(($oldCoun ?? '') . ' → ' . ($newCoun ?? ''));
                @endphp

                <tr class="align-middle hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                  <td class="px-6 py-4 font-semibold text-slate-900">
                    APN-{{ str_pad($row->appointment_id, 3, '0', STR_PAD_LEFT) }}
                  </td>

                  <td class="px-6 py-4">
                    @php
                      $avatarUrl = null;
                      if (isset($row->profile_picture) && $row->profile_picture) {
                        $avatarUrl = asset('storage/' . $row->profile_picture);
                      } elseif (isset($row->student_id)) {
                        $avatarUrl = optional(\App\Models\User::find($row->student_id))->avatar_url;
                      }
                      $init = mb_strtoupper(collect(explode(' ', $row->student_name))->map(fn($n) => $n[0] ?? '')->take(2)->join(''));
                    @endphp
                    <div class="flex items-center gap-3">
                      @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $row->student_name }}"
                          class="w-8 h-8 rounded-full object-cover shadow-sm ring-1 ring-slate-200">
                      @else
                        <div
                          class="w-8 h-8 flex-shrink-0 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-[10px] ring-1 ring-indigo-100/50 uppercase shadow-sm">
                          {{ $init }}
                        </div>
                      @endif
                      <div class="flex flex-col">
                        <span class="font-medium text-slate-900">{{ $row->student_name }}</span>
                        @if(!empty($row->student_email))
                          <span class="text-slate-500 text-xs">{{ $row->student_email }}</span>
                        @endif
                      </div>
                    </div>
                  </td>

                  <td class="px-6 py-4">
                    @if($old)
                      <div class="leading-tight">
                        <div class="font-medium text-slate-900">{{ $old->format('M d, Y') }}</div>
                        <div class="text-slate-500 text-xs">{{ $old->format('g:i A') }}</div>
                      </div>
                    @else
                      <span class="text-slate-400">—</span>
                    @endif
                  </td>

                  <td class="px-6 py-4">
                    @if($new)
                      <div class="leading-tight">
                        <div class="font-medium text-slate-900">{{ $new->format('M d, Y') }}</div>
                        <div class="text-slate-500 text-xs">{{ $new->format('g:i A') }}</div>
                      </div>
                    @else
                      <span class="text-slate-400">—</span>
                    @endif
                  </td>

                  <td class="px-6 py-4">
                    @if($oldCoun || $newCoun)
                      <span class="inline-flex items-center h-7 rounded-full bg-slate-900 text-white text-xs font-medium px-3">
                        <span class="inline-block size-1.5 rounded-full bg-amber-300 mr-1.5"></span>
                        {{ $oldCoun ?? '—' }} → {{ $newCoun ?? '—' }}
                      </span>
                    @else
                      <span class="text-xs text-slate-500">Same counselor</span>
                    @endif

                    @if(!empty($row->reason))
                      <div class="mt-1 text-[11px] text-slate-500">
                        Reason: {{ $row->reason }}
                      </div>
                    @endif
                  </td>

                  <td class="px-6 py-4">
                    @if($changedLabel)
                      <div class="leading-tight">
                        <div class="font-medium text-slate-900">
                          {{ Carbon::parse($row->changed_at)->format('M d, Y') }}
                        </div>
                        <div class="text-slate-500 text-xs">{{ $changedLabel }}</div>
                      </div>
                    @else
                      <span class="text-slate-400">—</span>
                    @endif
                  </td>

                  <td class="px-6 py-4 text-right">
                    <a href="{{ route('counselor.appointments.show', $row->appointment_id) }}"
                      class="inline-flex items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 px-4 py-2 text-xs font-bold hover:bg-indigo-600 hover:text-white transition-all duration-200">
                      View
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

@endsection

{{-- ========= Helpers (Admin Form Auto-Submit) ========= --}}
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('apptSearchForm');
    const qInput = document.getElementById('appt-q');
    const statusSelect = document.querySelector('select[name="status"]');
    const periodSelect = document.querySelector('select[name="period"]');

    if (form) {
      function submitWithResetPage() {
        let pageInput = form.querySelector('input[name="page"]');
        if (!pageInput) {
          pageInput = document.createElement('input');
          pageInput.type = 'hidden';
          pageInput.name = 'page';
          form.appendChild(pageInput);
        }
        pageInput.value = ''; // back to first page
        form.submit();
      }

      if (qInput) {
        qInput.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            submitWithResetPage();
          }
        });
      }

      if (statusSelect) {
        statusSelect.addEventListener('change', () => submitWithResetPage());
      }
      if (periodSelect) {
        periodSelect.addEventListener('change', () => submitWithResetPage());
      }
    }
  });
</script>