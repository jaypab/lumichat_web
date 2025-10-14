
@extends('layouts.app')

@section('title', 'Lumi - Appointment History')
@section('page_title', 'Manage History')  

@php
  use Illuminate\Support\Str;
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- Page header --}}
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Appointment History</h2>
      @php $total = $appointments->total(); @endphp
      <p class="text-sm text-slate-600">
        View and manage your counseling bookings.
        <span class="mx-2 text-slate-300">•</span>
        <span class="text-slate-500">{{ $total }} {{ Str::plural('appointment', $total) }}</span>
      </p>
    </div>
 <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <a href="{{ route('appointment.create') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-indigo-700 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      Book New
    </a>
{{-- History list -> PDF --}}
<a href="{{ route('appointment.history.export.pdf', request()->only('status','period','q')) }}"
   target="_blank" rel="noopener"
   class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-green-700 active:scale-[.99] transition">
  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
  </svg>
  Download PDF
</a>


  </div>
  </div>

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

  <form id="studentApptFilters" method="GET" action="{{ route('appointment.history') }}">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
        <select name="status"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500">
          @foreach($statusOptions as $val => $label)
            <option value="{{ $val }}" @selected($status===$val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Date Range</label>
        <select name="period"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500">
          @foreach($periodOptions as $val => $label)
            <option value="{{ $val }}" @selected($period===$val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
        <div class="relative">
          <input id="student-appt-q" type="text" name="q" value="{{ $q }}" placeholder="Search counselor"
                 class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-3 text-sm focus:ring-2 focus:ring-indigo-500"/>
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <div class="md:col-span-3 flex items-center justify-end gap-2">
        <a href="{{ route('appointment.history') }}"
           class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-sm">
          Reset
        </a>
        <button class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm">
          Apply
        </button>
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
                'pending'   => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200','dot'=>'bg-amber-500','label'=>'Pending'],
                'confirmed' => ['bg'=>'bg-blue-50','text'=>'text-blue-700','ring'=>'ring-blue-200','dot'=>'bg-blue-500','label'=>'Confirmed'],
                'completed' => ['bg'=>'bg-emerald-50','text'=>'text-emerald-700','ring'=>'ring-emerald-200','dot'=>'bg-emerald-500','label'=>'Completed'],
                'canceled'  => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200','dot'=>'bg-rose-500','label'=>'Canceled'],
              ];
              $s   = $statusMap[$row->status] ?? ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200','dot'=>'bg-slate-400','label'=>ucfirst($row->status ?? '—')];
              $cls = $s['bg'].' '.$s['text'].' ring-1 '.$s['ring'];
              $dot = $s['dot'];

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
                  <span class="mx-auto">{{ $s['label'] }}</span>
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
