
@extends('layouts.app')

@section('title', 'Lumi - Appointment History')
@section('page_title', 'Manage History')  

@php
  use Illuminate\Support\Str;
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

 {{-- ======= Page Header (gradient band + KPI) ======= --}}
@php $total = $appointments->total(); @endphp
<section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm screen-only">
  <div class="p-5 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Appointment History</h2>
        <p class="text-white/80 text-sm mt-0.5">
          View and manage your counseling bookings.
        </p>
      </div>

      <div class="flex items-center gap-2">
        {{-- KPI chip: total records --}}
        <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-1.5 text-sm ring-1 ring-white/20">
          <svg class="h-4 w-4 opacity-90" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 1a7 7 0 0 0-7 7v3.126a4 4 0 0 1-.832 2.4L2.6 16.6A1 1 0 0 0 3.4 18h17.2a1 1 0 0 0 .8-1.6l-1.568-3.074A4 4 0 0 1 19 11.126V8a7 7 0 0 0-7-7Zm0 22a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z"/>
          </svg>
          <strong class="font-semibold">{{ $total }}</strong>
          <span class="opacity-90">{{ \Illuminate\Support\Str::plural('record', $total) }}</span>
        </span>

        {{-- Book New (white button, dark text) --}}
        <a href="{{ route('appointment.create') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-white text-slate-900 px-4 py-2 h-10 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Book New
        </a>

        {{-- Download PDF (white button, indigo text) --}}
        <a href="{{ route('appointment.history.export.pdf', request()->only('status','period','q')) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 h-10 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
          </svg>
          Download PDF
        </a>
      </div>
    </div>
  </div>
</section>

  {{-- Filters --}}
  @php
    $status = $status ?? request('status','all');
    $period = $period ?? request('period','all');
    $q      = $q      ?? request('q','');

    $statusOptions = [
      'all'       => 'All Appointment',
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

  {{-- ======= Filters (enhanced) ======= --}}
<form method="GET" action="{{ route('appointment.history') }}" class="screen-only">
  <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">

    {{-- Quick period chips (Today / This Week / etc.) --}}
    <div class="flex flex-wrap items-center gap-2">
      <span class="text-xs font-medium text-slate-500 mr-1.5">Date:</span>
      @foreach($periodOptions as $key => $label)
        @php $active = $period === $key; @endphp
        <button name="period" value="{{ $key }}"
          class="inline-flex items-center h-8 px-3 rounded-lg text-xs font-medium
                 {{ $active ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-50 text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100' }}">
          {{ $label }}
        </button>
      @endforeach
      {{-- keep other params when switching period --}}
      <input type="hidden" name="status" value="{{ $status }}">
      <input type="hidden" name="q" value="{{ $q }}">
    </div>

    {{-- Controls row --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
      {{-- Status (== width with Search) --}}
      <div class="md:col-span-5 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
        <select name="status"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          @foreach($statusOptions as $val => $label)
            <option value="{{ $val }}" @selected($status===$val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      {{-- Search (== width with Status) --}}
      <div class="md:col-span-5 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
        <div class="relative">
          <input id="qInput" type="text" name="q" value="{{ $q }}" autocomplete="off"
                placeholder="Search counselor"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-9 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
          </svg>
          @if($q!=='')
            <a href="{{ route('appointment.history', array_filter(['status'=>$status,'period'=>$period])) }}"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" title="Clear">✕</a>
          @endif
        </div>
      </div>

      {{-- Actions --}}
      <div class="md:col-span-2 flex items-end justify-end gap-2">
        <a href="{{ route('appointment.history') }}"
          class="h-10 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 active:scale-[.99]">
          Reset
        </a>
        <button type="submit"
                class="h-10 inline-flex items-center justify-center px-5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm">
          Apply
        </button>
      </div>
    </div>
  </div>
</form>

  {{-- Table --}}
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
    <div class="relative overflow-x-auto">
      <table class="min-w-full text-sm leading-6 table-auto">
        <colgroup>
          <col style="width:8%">
          <col style="width:28%">
          <col style="width:26%">
          <col style="width:20%">
          <col style="width:18%">
        </colgroup>

        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
          <tr class="align-middle">
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">ID</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Counselor</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Date &amp; Time</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Status</th>
            <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse ($appointments as $row)
            @php
              $start = \Carbon\Carbon::parse($row->scheduled_at);
              $now   = now();
              $mins  = $now->diffInMinutes($start, false);
              $abs   = abs($mins);
              $d=intdiv($abs,1440); $r=$abs%1440; $h=intdiv($r,60); $m=$r%60;
              $parts = []; if($d) $parts[]="$d"."d"; if($h) $parts[]="$h"."h"; if(!$d && $m) $parts[]="$m"."m";
              $countdown = $mins===0 ? 'Starting now' : ($mins>0 ? ('Starts in '.implode(' ', $parts)) : (implode(' ', $parts).' ago'));

              $statusMap = [
                'pending'   => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200','dot'=>'bg-amber-500'],
                'confirmed' => ['bg'=>'bg-blue-50','text'=>'text-blue-700','ring'=>'ring-blue-200','dot'=>'bg-blue-500'],
                'completed' => ['bg'=>'bg-emerald-50','text'=>'text-emerald-700','ring'=>'ring-emerald-200','dot'=>'bg-emerald-500'],
                'canceled'  => ['bg'=>'bg-slate-200','text'=>'text-slate-800','ring'=>'ring-slate-300','dot'=>'bg-slate-500'],
                'no_show'   => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200','dot'=>'bg-rose-500'],
              ];

              $s      = $statusMap[$row->status] ?? ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200','dot'=>'bg-slate-400'];
              $cls    = $s['bg'].' '.$s['text'].' ring-1 '.$s['ring'];
              $dot    = $s['dot'];
              $label  = $s['label'] ?? Str::headline($row->status ?? '—');

              // If controller uses LEFT JOIN, $row->counselor_name may be null.
              $noCounselor = empty($row->counselor_name);
            @endphp

            <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
              <td class="px-6 py-4 font-semibold text-slate-900">{{ $row->id }}</td>

              <td class="px-6 py-4 whitespace-nowrap">
                @if ($row->status === 'canceled')
                  <span class="inline-flex items-center gap-2 rounded-lg bg-rose-50 px-2.5 py-1 text-[13px] text-rose-700 ring-1 ring-rose-200">
                    <span class="inline-block size-1.5 rounded-full bg-rose-500"></span>
                    Appointment Canceled
                  </span>
                @else
                  @php $cname = trim((string) ($row->counselor_name ?? '')); @endphp
                  @if ($cname === '')
                    <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-2.5 py-1 text-[13px] text-slate-700 ring-1 ring-slate-200">
                      <span class="inline-block size-1.5 rounded-full bg-slate-400"></span>
                      Awaiting admin assignment
                    </span>
                  @else
                    <span class="text-slate-700">{{ $cname }}</span>
                  @endif
                @endif
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="leading-tight">
                  <div class="font-medium text-slate-900">{{ $start->format('M d, Y') }}</div>
                  <div class="text-slate-500 text-xs">{{ $start->format('g:i A') }} • {{ $countdown }}</div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span class="relative inline-flex items-center h-7 w-[128px] rounded-full text-xs font-medium leading-none {{ $cls }}">
                  <span class="absolute left-3 inline-block size-2 rounded-full {{ $dot }}"></span>
                  <span class="mx-auto">{{ $label }}</span>
                </span>
              </td>

              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                  <a href="{{ route('appointment.view', $row->id) }}"
                     class="inline-flex items-center justify-center h-9 px-3 rounded-lg bg-indigo-600 text-white hover:-translate-y-0.5 active:scale-[.98] transition"
                     title="View" aria-label="View appointment">
                    View
                  </a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-6 py-10 text-center text-slate-500">No appointments found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($appointments->hasPages())
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70">
        {{ $appointments->withQueryString()->links() }}
      </div>
    @endif
  </div>
</div>

{{-- SweetAlert + search debounce --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const successMsg = @json(session('success') ?? session('status'));
  if (successMsg) {
    Swal.fire({ icon:'success', title:'Success', text: successMsg, timer:2200, showConfirmButton:false });
  }

  const errs = @json($errors->all());
  if (Array.isArray(errs) && errs.length) {
    const html = '<ul style="text-align:left;margin:0;padding-left:1rem">' +
                 errs.map(i => `<li>• ${i}</li>`).join('') + '</ul>';
    Swal.fire({ icon:'error', title:'Unable to proceed', html });
  }

  // debounce search
  const q = document.getElementById('student-appt-q');
  const f = document.getElementById('studentApptFilters');
  let t = null;
  if (q && f) {
    q.addEventListener('input', function () {
      if (t) clearTimeout(t);
      t = setTimeout(() => f.submit(), 300);
    });
  }
});
</script>
@endpush
@endsection
