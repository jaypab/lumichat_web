{{-- resources/views/admin/case-notes/index.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Case Form Summary')
@section('page_title', 'Case Form Summary')

@section('content')
@php
  use Illuminate\Support\Str;

  $dateKey = request('date','all');   // all|today|last7|last30|month|range
  $q       = trim((string) request('q',''));
  $from    = request('from');
  $to      = request('to');

  $total = ($notes instanceof \Illuminate\Pagination\LengthAwarePaginator)
          ? $notes->total()
          : $notes->count();

  // Tiny helper for a neutral “chip”
  $chip = function (?string $text) {
      $t = trim((string)($text ?? '—'));
      if ($t === '') $t = '—';
      $t = Str::limit($t, 56);
      return '<span class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-medium bg-slate-50 text-slate-700 ring-1 ring-slate-200">'
           . e($t) . '</span>';
  };

  // Date quick filters (matches controller)
  $datePresets = [
    'all'   => 'All',
    'today' => 'Today',
    'last7' => 'Last 7 days',
    'last30'=> 'Last 30 days',
    'month' => 'This Month',
    'range' => 'Custom',
  ];
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

{{-- ======= Page Header (gradient band + KPI) ======= --}}
<section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm screen-only">
    <div class="p-5 sm:p-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Case Form Summary</h2>
          <p class="text-white/80 text-sm mt-0.5">Review counselor case notes recorded per student session.</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-1.5 text-sm ring-1 ring-white/20">
            <svg class="h-4 w-4 opacity-90" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a7 7 0 0 0-7 7v3.126a4 4 0 0 1-.832 2.4L2.6 16.6A1 1 0 0 0 3.4 18h17.2a1 1 0 0 0 .8-1.6l-1.568-3.074A4 4 0 0 1 19 11.126V8a7 7 0 0 0-7-7Zm0 22a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z"/></svg>
            <strong class="font-semibold">{{ $total }}</strong><span class="opacity-90">records</span>
          </span>
          <a href="{{ route('admin.case-notes.export.pdf', request()->only('date','q','from','to')) }}"
             target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
            </svg>
            Download PDF
          </a>
        </div>
      </div>
    </div>
  </section>

  {{-- ======= Filters ======= --}}
  <form method="GET" action="{{ route('admin.case-notes.index') }}" class="screen-only">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">

      {{-- Quick date chips --}}
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-slate-500 mr-1.5">Date:</span>
        @foreach($datePresets as $key => $label)
          @php $active = $dateKey === $key; @endphp
          <button name="date" value="{{ $key }}" class="inline-flex items-center h-8 px-3 rounded-lg text-xs font-medium
                 {{ $active ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-50 text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100' }}">
            {{ $label }}
          </button>
        @endforeach
        {{-- keep other params when switching date --}}
        <input type="hidden" name="q" value="{{ $q }}">
        @if($from)<input type="hidden" name="from" value="{{ $from }}">@endif
        @if($to)<input type="hidden" name="to" value="{{ $to }}">@endif
      </div>

      {{-- Custom range & search row --}}
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        {{-- Custom range (only show when active) --}}
        <div class="md:col-span-5 {{ $dateKey==='range' ? '' : 'opacity-60 pointer-events-none' }}">
          <label class="block text-xs font-medium text-slate-600 mb-1">Custom range</label>
          <div class="grid grid-cols-2 gap-2">
            <input type="date" name="from" value="{{ $from }}"
                   class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <input type="date" name="to" value="{{ $to }}"
                   class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
        </div>

        {{-- Search --}}
        <div class="md:col-span-5">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
          <div class="relative">
            <input id="qInput" type="text" name="q" value="{{ $q }}" autocomplete="off"
                   placeholder="Search student, counselor, or note text"
                   class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-9 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="11" cy="11" r="7" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
            </svg>
            @if($q!=='')
              <a href="{{ route('admin.case-notes.index', array_filter(['date'=>$dateKey,'from'=>$from,'to'=>$to])) }}"
                 class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" title="Clear">
                ✕
              </a>
            @endif
          </div>
        </div>

        {{-- Actions --}}
        <div class="md:col-span-2 flex items-end gap-2">
          <a href="{{ route('admin.case-notes.index') }}"
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

  {{-- ======= Results table ======= --}}
  <section id="case-print-root" class="space-y-2">
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

          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700 sticky top-0 z-10">
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
                $year       = $n->note_date
                              ? \Carbon\Carbon::parse($n->note_date)->format('Y')
                              : (\Carbon\Carbon::parse($n->created_at)->format('Y') ?? now()->format('Y'));
                $code        = 'CN-' . $year . '-' . str_pad($n->id, 4, '0', STR_PAD_LEFT);
                $studentName = $n->student_name_display ?? $n->student_name ?? '—';
                $counselor   = $n->counselor_name ?? ('Counselor #' . ($n->counselor_id ?? '—'));
                $date        = $n->note_date ? \Carbon\Carbon::parse($n->note_date)->format('M d, Y') : '—';
              @endphp
              <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
                <td class="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">{{ $code }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $studentName }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $counselor }}</td>
                <td class="px-6 py-4 whitespace-nowrap" title="{{ $n->presenting_problem ?? '' }}">{!! $chip($n->presenting_problem ?? '') !!}</td>
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
                <td colspan="6" class="px-6 py-14">
                  <div class="text-center">
                    <div class="mx-auto mb-2 h-9 w-9 rounded-full bg-slate-100 ring-1 ring-slate-200 flex items-center justify-center text-slate-400">☰</div>
                    <p class="text-slate-700 font-medium">No case notes found</p>
                    <p class="text-slate-500 text-sm">Try adjusting the date filter or clearing the search.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if(($notes instanceof \Illuminate\Contracts\Pagination\Paginator) && $notes->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 screen-only">
          <div class="flex items-center justify-between text-sm text-slate-600">
            <span>Showing {{ $notes->firstItem() }}–{{ $notes->lastItem() }} of {{ $notes->total() }}</span>
            {{ $notes->withQueryString()->links() }}
          </div>
        </div>
      @endif
    </div>
  </section>
</div>

{{-- Print CSS (clean table print) --}}
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
