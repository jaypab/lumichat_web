{{-- resources/views/admin/diagnosis-reports/index.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Diagnosis Reports')
@section('page_title', 'Diagnosis Reports') 

@section('content')
@php
  use Illuminate\Support\Str;

  $dateKey = request('date','all');
  $q       = request('q','');

  $total = ($reports instanceof \Illuminate\Pagination\LengthAwarePaginator)
          ? $reports->total()
          : $reports->count();

  // —— pill palette (kept consistent with other pages) ——
  $palette = [
    'Stress'              => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200'],
    'Depression'          => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200'],
    'Anxiety'             => ['bg'=>'bg-sky-50','text'=>'text-sky-700','ring'=>'ring-sky-200'],
    'Family Problems'     => ['bg'=>'bg-yellow-50','text'=>'text-yellow-800','ring'=>'ring-yellow-200'],
    'Relationship Issues' => ['bg'=>'bg-orange-50','text'=>'text-orange-700','ring'=>'ring-orange-200'],
    'Low Self-Esteem'     => ['bg'=>'bg-fuchsia-50','text'=>'text-fuchsia-700','ring'=>'ring-fuchsia-200'],
    'Sleep Problems'      => ['bg'=>'bg-indigo-50','text'=>'text-indigo-700','ring'=>'ring-indigo-200'],
    'Time Management'     => ['bg'=>'bg-violet-50','text'=>'text-violet-700','ring'=>'ring-violet-200'],
    'Academic Pressure'   => ['bg'=>'bg-blue-50','text'=>'text-blue-700','ring'=>'ring-blue-200'],
    'Financial Stress'    => ['bg'=>'bg-teal-50','text'=>'text-teal-700','ring'=>'ring-teal-200'],
    'Bullying'            => ['bg'=>'bg-lime-50','text'=>'text-lime-700','ring'=>'ring-lime-200'],
    'Burnout'             => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200'],
    'Grief / Loss'        => ['bg'=>'bg-stone-50','text'=>'text-stone-700','ring'=>'ring-stone-200'],
    'Loneliness'          => ['bg'=>'bg-cyan-50','text'=>'text-cyan-700','ring'=>'ring-cyan-200'],
    'Substance Abuse'     => ['bg'=>'bg-red-50','text'=>'text-red-700','ring'=>'ring-red-200'],
  ];
  $fallback = ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200'];

  // helper to render a pill safely
  $pill = function (?string $label) use ($palette, $fallback) {
      $label = trim((string)($label ?? '—'));
      if ($label === '') $label = '—';
      $sty = $palette[$label] ?? $fallback;
      return '<span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium '
           . $sty['bg'].' '.$sty['text'].' ring-1 '.$sty['ring'].'">'.e($label).'</span>';
  };
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Page Header ========= --}}
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between animate-fadeup screen-only">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Diagnosis Reports</h2>
      <p class="text-sm text-slate-600">
        Review summary reports based on student responses and emotional patterns.
        <span class="ml-2 text-slate-400">•</span>
        <span class="ml-2 text-slate-500">{{ $total }} {{ Str::plural('report', $total) }}</span>
      </p>
    </div>

    {{-- ✅ Download PDF (keeps current filters) --}}
    <a href="{{ route('admin.diagnosis-reports.export.pdf', request()->only('date','q')) }}"
   target="_blank" rel="noopener"
   class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
  </svg>
  Download PDF
</a>
  </div>

  {{-- ========= Filter Bar ========= --}}
  <form method="GET" action="{{ route('admin.diagnosis-reports.index') }}" class="mb-6 screen-only">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end animate-fadeup">

      {{-- Date Range (left - 3 cols) --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Date Range</label>
        <select name="date"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="all"   @selected($dateKey==='all')>All Dates</option>
          <option value="7d"    @selected($dateKey==='7d')>Last 7 days</option>
          <option value="30d"   @selected($dateKey==='30d')>Last 30 days</option>
          <option value="month" @selected($dateKey==='month')>This month</option>
        </select>
      </div>

      {{-- Search (middle - 3 cols) --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
        <div class="relative">
          <input id="qInput" type="text" name="q" value="{{ $q }}" autocomplete="off"
                placeholder="Search student or counselor name"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      {{-- Right side: Reset / Apply (6 cols; pushed to far right) --}}
      <div class="md:col-span-6 md:col-start-7 flex items-center justify-end gap-2">
        <a href="{{ route('admin.diagnosis-reports.index') }}"
          class="h-11 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200
                  shadow-sm hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h7M4 10h16M4 16h10"/>
          </svg>
          Reset
        </a>

        <button type="submit"
                class="h-11 inline-flex items-center justify-center px-5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm">
          Apply
        </button>
      </div>

    </div>
  </form>


  {{-- ========= TABLE ========= --}}
  <div id="diag-print-root" class="space-y-2">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="relative overflow-x-auto">
        <table class="min-w-full text-sm leading-6 table-auto">
          <colgroup>
            <col style="width:16%">
            <col style="width:22%">
            <col style="width:22%">
            <col style="width:22%">
            <col style="width:14%">
            <col class="col-action" style="width:4%">
          </colgroup>

        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
          <tr class="align-middle">
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">ID</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Student Name</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Counselor Name</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Diagnosis Result</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Date</th>
            <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap col-action">Action</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse ($reports as $r)
            @php
              $code          = 'DRP-' . now()->format('Y') . '-' . str_pad($r->id, 4, '0', STR_PAD_LEFT);
              $studentName   = $r->student->name ?? '—';
              $counselorName = $r->counselor->name ?? ('Counselor #' . ($r->counselor_id ?? '—'));
              $date          = $r->created_at?->format('M d, Y') ?? '—';
              $diagLabel     = (string) ($r->diagnosis_result ?? '—');
            @endphp
            <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
              <td class="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">{{ $code }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $studentName }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $counselorName }}</td>

              {{-- 🔹 colored pill --}}
              <td class="px-6 py-4 whitespace-nowrap">
                {!! $pill($diagLabel) !!}
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $date }}</td>
              <td class="px-6 py-4 text-right">
                <a href="{{ route('admin.diagnosis-reports.show', $r->id) }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm">
                  View
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="px-6 py-10 text-center text-slate-500">No diagnosis reports found.</td>
            </tr>
          @endforelse
        </tbody>
        </table>
      </div>

      @if(($reports instanceof \Illuminate\Contracts\Pagination\Paginator) && $reports->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 screen-only">
          {{ $reports->withQueryString()->links() }}
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Print CSS can stay if you still support browser print elsewhere --}}
<style media="print">
  @page { margin: 12mm; }
  body * { visibility: hidden !important; }
  #diag-print-root, #diag-print-root * { visibility: visible !important; }
  #diag-print-root {
    position: fixed !important; inset: 0 !important; margin: 12mm !important;
    background:#fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  #diag-print-root .rounded-2xl, #diag-print-root .shadow-sm, #diag-print-root .border { border:0 !important; box-shadow:none !important; }
  #diag-print-root .overflow-x-auto { overflow: visible !important; }
  .diag-print-title { display:block !important; margin:0 0 8mm !important; font-size:20pt !important; font-weight:700 !important; color:#000 !important; }
  #diag-print-root th.col-action,
  #diag-print-root td.col-action,
  #diag-print-root col.col-action,
  #diag-print-root thead th:last-child,
  #diag-print-root tbody td:last-child { display:none !important; visibility:hidden !important; }
</style>
@endsection
