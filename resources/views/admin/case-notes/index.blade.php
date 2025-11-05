{{-- resources/views/admin/case-notes/index.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Case Form Summary')
@section('page_title', 'Case Form Summary')

@section('content')
@php
  use Illuminate\Support\Str;

  $dateKey = request('date','all');   // all|today|last7|last30 (matches controller)
  $q       = request('q','');

  $total = ($notes instanceof \Illuminate\Pagination\LengthAwarePaginator)
          ? $notes->total()
          : $notes->count();

  // Neutral chip style for short text snippets
  $chip = function (?string $text) {
      $t = trim((string)($text ?? '—'));
      if ($t === '') $t = '—';
      $t = Str::limit($t, 42);
      return '<span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium bg-slate-50 text-slate-700 ring-1 ring-slate-200">'
           . e($t) . '</span>';
  };
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Page Header ========= --}}
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between animate-fadeup screen-only">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Case Form Summary</h2>
      <p class="text-sm text-slate-600">
        Review counselor case notes recorded per student session.
        <span class="ml-2 text-slate-400">•</span>
        <span class="ml-2 text-slate-500">{{ $total }} {{ Str::plural('record', $total) }}</span>
      </p>
    </div>

    {{-- ✅ Download PDF (keeps current filters) --}}
    <a href="{{ route('admin.case-notes.export.pdf', request()->only('date','q','from','to')) }}"
       target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
      </svg>
      Download PDF
    </a>
  </div>

  {{-- ========= Filter Bar ========= --}}
  <form method="GET" action="{{ route('admin.case-notes.index') }}" class="mb-6 screen-only">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end animate-fadeup">

      {{-- Date Range --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Date Range</label>
        <select name="date"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="all"    @selected($dateKey==='all')>All Dates</option>
          <option value="today"  @selected($dateKey==='today')>Today</option>
          <option value="last7"  @selected($dateKey==='last7')>Last 7 days</option>
          <option value="last30" @selected($dateKey==='last30')>Last 30 days</option>
        </select>
      </div>

      {{-- Search --}}
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

      {{-- Right side: Reset / Apply --}}
      <div class="md:col-span-6 md:col-start-7 flex items-center justify-end gap-2">
        <a href="{{ route('admin.case-notes.index') }}"
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
  <div id="case-print-root" class="space-y-2">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="relative overflow-x-auto">
        <table class="min-w-full text-sm leading-6 table-auto">
          <colgroup>
            <col style="width:16%">
            <col style="width:24%">
            <col style="width:22%">
            <col style="width:24%">
            <col style="width:10%">
            <col class="col-action" style="width:4%">
          </colgroup>

          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
            <tr class="align-middle">
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">ID</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Student Name</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Counselor Name</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Presenting Problem (snippet)</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Date</th>
              <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap col-action">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            @forelse ($notes as $n)
              @php
                $code        = 'CN-' . now()->format('Y') . '-' . str_pad($n->id, 4, '0', STR_PAD_LEFT);
                $studentName = $n->student_name_display ?? $n->student_name ?? '—';
                $counselor   = $n->counselor_name ?? ('Counselor #' . ($n->counselor_id ?? '—'));
                $date        = $n->note_date ? \Carbon\Carbon::parse($n->note_date)->format('M d, Y') : '—';
              @endphp
              <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
                <td class="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">{{ $code }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $studentName }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $counselor }}</td>

                {{-- snippet chip from presenting_problem --}}
                <td class="px-6 py-4 whitespace-nowrap">{!! $chip($n->presenting_problem ?? '') !!}</td>

                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $date }}</td>
                <td class="px-6 py-4 text-right">
                  <a href="{{ route('admin.case-notes.show', $n->id) }}"
                     class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm">
                    View
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-6 py-10 text-center text-slate-500">No case notes found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if(($notes instanceof \Illuminate\Contracts\Pagination\Paginator) && $notes->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 screen-only">
          {{ $notes->withQueryString()->links() }}
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Print CSS --}}
<style media="print">
  @page { margin: 12mm; }
  body * { visibility: hidden !important; }
  #case-print-root, #case-print-root * { visibility: visible !important; }
  #case-print-root {
    position: fixed !important; inset: 0 !important; margin: 12mm !important;
    background:#fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  #case-print-root .rounded-2xl, #case-print-root .shadow-sm, #case-print-root .border { border:0 !important; box-shadow:none !important; }
  #case-print-root .overflow-x-auto { overflow: visible !important; }
  #case-print-root th.col-action,
  #case-print-root td.col-action,
  #case-print-root col.col-action,
  #case-print-root thead th:last-child,
  #case-print-root tbody td:last-child { display:none !important; visibility:hidden !important; }
</style>
@endsection
