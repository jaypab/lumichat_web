{{-- resources/views/admin/appointments/index.blade.php --}}
@extends('layouts.admin')
@section('title','Admin · Appointments')

@php
  use Carbon\Carbon;
  use Illuminate\Support\Str;

  $status = $status ?? request('status', 'all');
  $period = $period ?? request('period', 'all');
  $q      = $q      ?? request('q', '');

  $statusOptions = [
    'all'       => 'All Statuses',
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'canceled'  => 'Canceled',
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
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Page Header ========= --}}
  @php $totalAppointments = $appointments->total(); @endphp
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between animate-fadeup">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Appointments</h2>
      <p class="text-sm text-slate-600">
        View and manage booked counseling sessions.
        <span class="ml-2 text-slate-400">•</span>
        <span class="ml-2 text-slate-500">
          {{ $appointments->total() }} {{ Str::plural('appointment', $appointments->total()) }}
        </span>
      </p>
    </div>

    {{-- Header action --}}
    <a href="{{ route('admin.appointments.export.pdf', request()->only('status','period','q')) }}"
       class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
      </svg>
      Download PDF
    </a>
  </div>

  {{-- ========= Filters ========= --}}
  <form id="apptSearchForm" method="GET" action="{{ route('admin.appointments.index') }}" class="mb-6">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end animate-fadeup">

      {{-- Status --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
        <select name="status"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          @foreach ($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      {{-- Date Range --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Date Range</label>
        <select name="period"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          @foreach ($periodOptions as $value => $label)
            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      {{-- Search --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
        <div class="relative">
          <input id="appt-q" type="text" name="q" value="{{ $q }}" placeholder="Search counselor or student"
                 class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <div class="sm:ml-auto"></div>

      {{-- Reset / Apply --}}
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.appointments.index') }}"
           class="h-11 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200
                  shadow-sm hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h7M4 10h16M4 16h10"/>
          </svg>
          Reset
        </a>
        <button class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm">
          Apply
        </button>
      </div>

    </div>
  </form>

  {{-- ========= Mobile Print ========= --}}
  <div class="mt-3 md:hidden">
    <button type="button" onclick="printAppointments()"
            class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition w-full sm:w-auto">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V4h12v5M6 18h12a2 2 0 002-2v-5H4v5a 2 2 0 002 2z"/>
      </svg>
      Print
    </button>
  </div>

  {{-- ========= Table ========= --}}
  <div id="appt-print-root" class="space-y-2">
    <h1 class="appt-print-title hidden">Appointments</h1>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="relative overflow-x-auto">
        <table class="min-w-full text-sm leading-6 table-auto">
          <colgroup>
            <col style="width:6%">
            <col style="width:18%">
            <col style="width:18%">
            <col style="width:19%">
            <col style="width:19%">
            <col style="width:12%">
            <col class="col-action" style="width:8%">
          </colgroup>

          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
            <tr class="align-middle">
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">ID</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Student</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Counselor</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Date &amp; Time</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Booked On</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Status</th>
              <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap col-action">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            @forelse ($appointments as $row)
              @php
                $dt       = Carbon::parse($row->scheduled_at);
                $bookedAt = $row->booked_at ?? $row->created_at ?? null;

                $statusMap = [
                  'pending'   => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200','dot'=>'bg-amber-500'],
                  'confirmed' => ['bg'=>'bg-blue-50','text'=>'text-blue-700','ring'=>'ring-blue-200','dot'=>'bg-blue-500'],
                  'completed' => ['bg'=>'bg-emerald-50','text'=>'text-emerald-700','ring'=>'ring-emerald-200','dot'=>'bg-emerald-500'],
                  'canceled'  => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200','dot'=>'bg-rose-500'],
                ];
                $s   = $statusMap[$row->status] ?? ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200','dot'=>'bg-slate-400'];
                $cls = $s['bg'].' '.$s['text'].' ring-1 '.$s['ring'];
                $dot = $s['dot'];
              @endphp

              <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
                <td class="px-6 py-4 font-semibold text-slate-900">{{ $row->id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $row->student_name }} 
                </td>
                
                {{-- Counselor --}}
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($row->status === 'canceled')
                    <span class="inline-flex items-center gap-2 rounded-lg bg-rose-50 px-2.5 py-1 text-[13px] text-rose-700 ring-1 ring-rose-200">
                      <span class="inline-block size-1.5 rounded-full bg-rose-500"></span>
                      Appointment Canceled
                    </span>

                  @else
                    @php $cname = trim((string) $row->counselor_name); @endphp

                    {{-- Unassigned --}}
                    @if ($cname === '' || $cname === '—')
                      @if ($row->status === 'pending')
                        {{-- Make it clickable only when pending --}}
                        <a href="{{ route('admin.appointments.assign.form', $row->id) }}"
                          class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-2.5 py-1 text-[13px] text-indigo-700 ring-1 ring-slate-200
                                  hover:bg-indigo-50 hover:ring-indigo-200 transition cursor-pointer"
                          title="Assign counselor">
                          <span class="inline-block size-1.5 rounded-full bg-indigo-500"></span>
                          Assign counselor
                        </a>
                      @else
                        {{-- Not pending: just a neutral chip (not clickable) --}}
                        <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-2.5 py-1 text-[13px] text-slate-700 ring-1 ring-slate-200">
                          <span class="inline-block size-1.5 rounded-full bg-slate-400"></span>
                          To be assigned
                        </span>
                      @endif

                    {{-- Already assigned --}}
                    @else
                      <span class="text-slate-700">{{ $cname }}</span>
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

                {{-- Status chip --}}
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="relative inline-flex items-center h-7 w-[112px] rounded-full text-xs font-medium leading-none {{ $cls }}">
                    <span class="absolute left-3 inline-block size-2 rounded-full {{ $dot }}"></span>
                    <span class="mx-auto">{{ ucfirst($row->status) }}</span>
                  </span>
                </td>

                {{-- Actions --}}
                <td class="px-6 py-4 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                    <a href="{{ route('admin.appointments.show', $row->id) }}"
                       class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">
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
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 not-print">
          {{ $appointments->withQueryString()->links() }}
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ========= Helpers ========= --}}
<script>
  const aq = document.getElementById('appt-q');
  const af = document.getElementById('apptSearchForm');
  let at = null;
  if (aq && af) {
    aq.addEventListener('input', function () {
      if (at) clearTimeout(at);
      at = setTimeout(function () { af.submit(); }, 300);
    });
  }
  function printAppointments(){ window.print(); }
</script>
@endsection
