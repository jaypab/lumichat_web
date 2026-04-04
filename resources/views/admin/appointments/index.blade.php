{{-- resources/views/admin/appointments/index.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Appointments')
@section('page_title', 'Manage Appointments')

@php
  use Carbon\Carbon;
  use Illuminate\Support\Str;

  $status = $status ?? request('status', 'all');
  $period = $period ?? request('period', 'all');
  $q      = $q      ?? request('q', '');

  $statusOptions = [
    'all'        => 'All Statuses',
    'pending'    => 'Pending',
    'confirmed'  => 'Confirmed',
    'completed'  => 'Completed',
    'canceled'   => 'Canceled',
    'no_show'    => 'No Show',
    'reassigned' => 'Re-assigned only',
  ];

  $periodOptions = [
    'all'        => 'All Dates',
    'upcoming'   => 'Upcoming',
    'today'      => 'Today',
    'this_week'  => 'This Week',
    'this_month' => 'This Month',
    'past'       => 'Past',
  ];
@endphp

@section('content')
<div id="appointments-root"
     class="max-w-7xl mx-auto p-6 space-y-6"
     data-last-updated="{{ $lastUpdatedAt ?? '' }}">

  {{-- ========= Header band (Clean SaaS Layout) ========= --}}
  @php $totalAppointments = $appointments->total(); @endphp
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Appointments</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20">
          {{ number_format($totalAppointments) }} Total
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">View and manage booked counseling sessions.</p>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ route('admin.appointments.export.pdf', request()->only('status','period','q')) }}"
         target="_blank" rel="noopener"
         class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Export PDF
      </a>
    </div>
  </header>

  {{-- ========= Filters (Modern Search) ========= --}}
  <form id="apptSearchForm" method="GET" action="{{ route('admin.appointments.index') }}" class="screen-only">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="relative flex-1 group min-w-[200px]">
        <input
          id="appt-q"
          type="text"
          name="q"
          value="{{ $q }}"
          placeholder="Search counselor or student..."
          autocomplete="off"
          class="h-12 w-full rounded-2xl border-none bg-white px-11 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 group-hover:ring-slate-300"
          @if($q !== '') autofocus @endif
        />
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400 group-hover:text-indigo-500 transition-colors duration-200"
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
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        @endif
      </div>

      <div class="flex items-center gap-3">
        <select name="status" class="h-12 rounded-2xl border-none bg-white px-4 py-0 pl-4 pr-10 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 hover:ring-slate-300">
          @foreach ($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
          @endforeach
        </select>
        
        <select name="period" class="h-12 rounded-2xl border-none bg-white px-4 py-0 pl-4 pr-10 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 hover:ring-slate-300">
          @foreach ($periodOptions as $value => $label)
            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
          @endforeach
        </select>

        <a href="{{ route('admin.appointments.index') }}"
           class="h-12 inline-flex items-center gap-2 rounded-2xl bg-white px-5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 group transition-all duration-200">
          <svg class="h-4 w-4 text-slate-400 group-hover:rotate-180 transition-transform duration-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <path d="M12 8v8M8 12h8" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
          Reset
        </a>
      </div>
    </div>
    
    {{-- Small legend under filters (only when "Re-assigned only" is selected) --}}
    @if ($status === 'reassigned')
      <div class="mt-3 flex items-center gap-2 text-[11px] text-slate-500 ml-2">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-2 py-0.5 ring-1 ring-inset ring-violet-500/20">
          <svg class="w-3.5 h-3.5 text-violet-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 7h10l-3-3m3 3l-3 3M17 17H7l3 3m-3-3l3-3" />
          </svg>
          <span class="font-bold text-violet-700">Re-assigned from …</span>
        </span>
        <span class="font-medium">means this appointment was moved from a previous counselor.</span>
      </div>
    @endif
  </form>

  {{-- ========= Mobile Print ========= --}}
  <div class="mt-3 md:hidden screen-only">
    <button type="button" onclick="printAppointments()"
            class="inline-flex items-center justify-center gap-2 bg-slate-800 text-white px-4 py-2.5 rounded-xl shadow-sm hover:bg-slate-700 active:scale-[.99] transition w-full font-bold text-sm">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 9V4h12v5M6 18h12a2 2 0 002-2v-5H4v5a2 2 0 002 2z"/>
      </svg>
      Print
    </button>
  </div>

  {{-- ========= Table ========= --}}
  <div id="appt-print-root" class="space-y-4">
    <h1 class="appt-print-title hidden">Appointments</h1>

    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 transition-all duration-300 flex flex-col">
      <div class="relative overflow-visible rounded-t-3xl border-b border-transparent">
        <table class="min-w-full text-sm leading-relaxed table-auto">
          <colgroup>
            <col style="width:6%">
            <col style="width:18%">
            <col style="width:18%">
            <col style="width:19%">
            <col style="width:19%">
            <col style="width:12%">
            <col class="col-action text-right w-px">
          </colgroup>

          <thead x-data="{ scrolled: false }" 
                 x-init="const s = document.querySelector('.panel-scroll'); if(s){ s.addEventListener('scroll', () => scrolled = s.scrollTop > 10) }"
                 class="text-slate-700 sticky top-0 z-20">
            <tr>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300 whitespace-nowrap"
                  :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">ID</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 whitespace-nowrap">Student</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 whitespace-nowrap">Counselor</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 whitespace-nowrap">Date &amp; Time</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 whitespace-nowrap">Booked On</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 whitespace-nowrap">Status</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300 col-action whitespace-nowrap"
                  :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            @forelse ($appointments as $row)
              @php
                $dt       = Carbon::parse($row->scheduled_at);
                $bookedAt = $row->created_at ?? null;

                // display label + colors, including No Show
                $statusNice = [
                  'pending'   => 'Pending',
                  'confirmed' => 'Confirmed',
                  'completed' => 'Completed',
                  'canceled'  => 'Canceled',
                  'no_show'   => 'No Show',
                ][$row->status] ?? ucfirst($row->status ?? '—');

                $statusMap = [
                  'pending'   => ['bg'=>'bg-amber-50','text'=>'text-amber-800','ring'=>'ring-amber-500/30','dot'=>'bg-amber-500'],
                  'confirmed' => ['bg'=>'bg-blue-50','text'=>'text-blue-800','ring'=>'ring-blue-500/30','dot'=>'bg-blue-500'],
                  'completed' => ['bg'=>'bg-emerald-50','text'=>'text-emerald-800','ring'=>'ring-emerald-500/30','dot'=>'bg-emerald-500'],
                  'canceled'  => ['bg'=>'bg-slate-100','text'=>'text-slate-800','ring'=>'ring-slate-500/30','dot'=>'bg-slate-500'],
                  'no_show'   => ['bg'=>'bg-rose-50','text'=>'text-rose-800','ring'=>'ring-rose-500/30','dot'=>'bg-rose-500'],
                ];
                $s   = $statusMap[$row->status] ?? ['bg'=>'bg-slate-100','text'=>'text-slate-800','ring'=>'ring-slate-500/30','dot'=>'bg-slate-400'];
                $cls = $s['bg'].' '.$s['text'].' ring-1 ring-inset '.$s['ring'];
                $dot = $s['dot'];

                $crPending = isset($row->cr_status) && $row->cr_status === 'requested';
                $crTime    = !empty($row->cr_created_at)
                            ? \Carbon\Carbon::parse($row->cr_created_at)->diffForHumans()
                            : null;

                $prevCounselorName = trim((string) ($row->prev_counselor_name ?? ''));
                $isWalkIn = ($row->appointment_source ?? null) === 'walk_in';
              @endphp

              <tr class="align-middle group hover:bg-indigo-50/30 transition-colors duration-200">
                <td class="px-6 py-4 font-bold text-slate-800">{{ $row->id }}</td>

                {{-- Student --}}
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                  <div class="flex flex-col">
                    <span>{{ optional($row->student)->name ?? '—' }}</span>

                    {{-- Walk-in chip --}}
                    @if($isWalkIn)
                  <span class="mt-1 inline-flex w-max items-center px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide
                               bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-500/20 shadow-sm leading-none">
                      <span class="inline-block size-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                      WALK-IN
                  </span>
                    @endif
                  </div>
                </td>


                {{-- Counselor --}}
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($row->status === 'canceled')
                    <span class="inline-flex items-center gap-2 rounded-lg bg-rose-50 px-2.5 py-1 text-[13px] text-rose-700 ring-1 ring-rose-200">
                      <span class="inline-block size-1.5 rounded-full bg-rose-500"></span>
                      Appointment Canceled
                    </span>
                  @else
                    @php
                      $cname = trim((string) (optional($row->counselor)->name ?? ''));

                      // counselor re-assignment history for this appointment
                      $history        = $historyByAppt[$row->id] ?? [];
                      $historyCount   = is_countable($history) ? count($history) : 0;
                      $reassignLabel  = null;
                      $reassignTooltip = null;

                      if ($historyCount > 0) {
                          $names = [];
                          foreach ($history as $h) {
                              $label = trim((string) ($h->counselor_name ?? ''));
                              if ($label === '') {
                                  $label = 'Counselor #'.$h->counselor_id;
                              }
                              if (!in_array($label, $names, true)) {
                                  $names[] = $label;
                              }
                          }
                          if (!empty($names)) {
                              $reassignLabel = implode(' → ', $names);
                          }

                          $tooltipLines = [];
                          foreach ($history as $h) {
                              $ts = $h->changed_at
                                  ? Carbon::parse($h->changed_at)->format('M d, Y g:i A')
                                  : '—';
                              $nm = trim((string) ($h->counselor_name ?? ('Counselor #'.$h->counselor_id)));
                              $tooltipLines[] = $ts.' — '.$nm;
                          }
                          if (!empty($tooltipLines)) {
                              $reassignTooltip = implode('&#10;', $tooltipLines); // newline in title
                          }
                      }
                    @endphp

                    @if (($cname === '' || $cname === '—') && $row->status === 'pending')
                      <a href="{{ route('admin.appointments.assign.form', $row->id) }}"
                         class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-2.5 py-1 text-[13px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-500/20 hover:bg-indigo-600 hover:text-white transition-colors"
                         title="Assign counselor">
                        <span class="inline-block size-1.5 rounded-full bg-indigo-500"></span>
                        Assign counselor
                      </a>
                    @elseif ($cname === '' || $cname === '—')
                      <span class="inline-flex items-center gap-2 rounded-lg bg-slate-50 px-2.5 py-1 text-[13px] text-slate-600 ring-1 ring-inset ring-slate-500/20 font-medium">
                        <span class="inline-block size-1.5 rounded-full bg-slate-400"></span>
                        To be assigned
                      </span>
                    @else
                      <div class="flex flex-col">
                        {{-- Current counselor --}}
                        <span class="text-slate-800 font-bold">{{ $cname }}</span>

                        {{-- Change-request flag --}}
                        @if($crPending)
                          <span class="mt-1 inline-flex items-center gap-1.5 rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-200"
                                title="Student requested a counselor change for this appointment.">
                            <span class="inline-block size-1.5 rounded-full bg-violet-500"></span>
                            Change requested
                            @if($crTime) <span class="text-violet-500/70">• {{ $crTime }}</span> @endif
                          </span>
                        @endif

                        {{-- Re-assigned history chip --}}
                        @if($reassignLabel)
                          <span
                            class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-2.5 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-200"
                            title="{{ $reassignTooltip }}">
                            <svg class="w-3.5 h-3.5 text-violet-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 7h10l-3-3m3 3l-3 3M17 17H7l3 3m-3-3l3-3" />
                            </svg>
                            <span>Re-assigned from&nbsp;<span class="font-semibold">{{ $reassignLabel }}</span></span>
                            @if($historyCount > 1)
                              <span class="text-[10px] text-violet-500/80">&bull; {{ $historyCount }}x</span>
                            @endif
                          </span>
                        @endif
                      </div>
                    @endif
                  @endif
                </td>

                {{-- Date & Time --}}
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="leading-tight">
                    <div class="font-medium text-slate-900">{{ $dt->format('M d, Y') }}</div>
                    <div class="text-slate-500 text-xs">{{ $dt->format('g:i A') }}</div>
                  </div>
                </td>

                {{-- Booked On --}}
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($bookedAt)
                    @php $b = Carbon::parse($bookedAt); @endphp
                    <div class="leading-tight">
                      <div class="font-medium text-slate-900">{{ $b->format('M d, Y') }}</div>
                      <div class="text-slate-500 text-xs">{{ $b->format('g:i A') }}</div>
                    </div>
                  @else
                    <span class="text-slate-400">—</span>
                  @endif
                </td>

                {{-- Status pill --}}
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="relative inline-flex items-center h-6 px-3 rounded-full text-[10px] font-bold tracking-wide leading-none {{ $cls }}">
                    <span class="absolute left-2 inline-block size-1.5 rounded-full {{ $dot }}"></span>
                    <span class="ml-2.5">{{ strtoupper($statusNice) }}</span>
                  </span>
                </td>

                {{-- Actions --}}
                <td class="px-6 py-4 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('admin.appointments.show', $row->id) }}"
                       class="inline-flex items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 px-4 py-2 text-xs font-bold hover:bg-indigo-600 hover:text-white transition-all duration-200">
                      View
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="px-6 py-10 text-center text-slate-500">No appointments found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($appointments->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 not-print rounded-b-3xl">
          {{ $appointments->withQueryString()->onEachSide(1)->links() }}
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ========= Helpers ========= --}}
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form         = document.getElementById('apptSearchForm');
    const qInput       = document.getElementById('appt-q');
    const statusSelect = document.querySelector('select[name="status"]');
    const periodSelect = document.querySelector('select[name="period"]');

    if (form) {
      // Helper: clear paginator page then submit
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

      // SEARCH: submit only when user presses Enter
      if (qInput) {
        qInput.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            submitWithResetPage();
          }
        });
      }

      // STATUS: auto-submit on change
      if (statusSelect) {
        statusSelect.addEventListener('change', () => {
          submitWithResetPage();
        });
      }

      // DATE RANGE: auto-submit on change
      if (periodSelect) {
        periodSelect.addEventListener('change', () => {
          submitWithResetPage();
        });
      }
    }

    // ========== AUTO UPDATE (POLLING) ==========
    const root = document.getElementById('appointments-root');
    if (!root) return;

    let lastUpdated = root.getAttribute('data-last-updated') || '';

    async function checkForNewAppointments() {
      try {
        const url = "{{ route('admin.appointments.poll') }}" + '?last=' + encodeURIComponent(lastUpdated);
        const res = await fetch(url, {
          headers: {
            'Accept': 'application/json'
          }
        });

        if (!res.ok) return; // silent fail

        const data = await res.json();
        if (!data.ok) return;

        if (data.last_updated) {
          // update our known timestamp
          lastUpdated = data.last_updated;
        }

        if (data.has_changes) {
          // simplest: reload the page so filters + pagination stay consistent
          window.location.reload();
        }
      } catch (err) {
        console.error('Appointment poll failed:', err);
      }
    }

    // Check every 15 seconds (adjust if you want)
    setInterval(checkForNewAppointments, 15000);
  });

  function printAppointments() {
    window.print();
  }
</script>
@endsection

