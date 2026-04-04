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
  $source  = request('source');       // e.g. 'walk-in' later if you add filter

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

  {{-- ========= Page Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Case Form Summary</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20">
          {{ number_format($total) }} Records
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">Review counselor case notes recorded per student session.</p>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ route('admin.case-notes.export.pdf', request()->only('date','q','from','to','source')) }}"
         target="_blank" rel="noopener"
         class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        <span>Export PDF</span>
      </a>
    </div>
  </header>

  {{-- ========= Modern Inline Filter Bar ========= --}}
  <form id="filterForm" method="GET" action="{{ route('admin.case-notes.index') }}" class="screen-only group/form">
    <div class="flex flex-col lg:flex-row gap-4">
      {{-- Search --}}
      <div class="relative flex-1 group">
        <input
          id="qInput"
          type="text"
          name="q"
          value="{{ $q }}"
          placeholder="Search student, counselor, or note text..."
          autocomplete="off"
          class="h-12 w-full rounded-2xl border-none bg-white px-11 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 group-hover:ring-slate-300"
        />
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400 group-hover:text-indigo-500 transition-colors duration-200"
             viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="11" cy="11" r="7" stroke-width="2.5"></circle>
          <path d="M21 21l-4.3-4.3" stroke-width="2.5" stroke-linecap="round"></path>
        </svg>

        @if($q !== '')
          <a href="{{ route('admin.case-notes.index', array_filter(['date'=>$dateKey,'from'=>$from,'to'=>$to,'source'=>$source])) }}"
             class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all"
             aria-label="Clear search">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </a>
        @endif
      </div>

      {{-- Date Presets --}}
      <div class="flex items-center gap-1.5 p-1 bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm shrink-0 overflow-x-auto custom-scrollbar">
        @foreach($datePresets as $key => $label)
          @php $active = $dateKey === $key; @endphp
          <button type="submit" name="date" value="{{ $key }}"
                  class="h-10 px-4 rounded-xl text-[11px] font-black uppercase tracking-wider whitespace-nowrap transition-all duration-200
                         {{ $active ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
            {{ $label }}
          </button>
        @endforeach
      </div>

      {{-- Custom Range (conditional) --}}
      @if($dateKey === 'range')
        <div class="flex items-center gap-2 p-1 bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm shrink-0">
          <input type="date" name="from" value="{{ $from }}" onchange="this.form.submit()"
                 class="h-10 rounded-xl border-none text-[11px] font-bold text-slate-700 focus:ring-0 bg-transparent">
          <span class="text-slate-300 font-bold text-xs">—</span>
          <input type="date" name="to" value="{{ $to }}" onchange="this.form.submit()"
                 class="h-10 rounded-xl border-none text-[11px] font-bold text-slate-700 focus:ring-0 bg-transparent">
        </div>
      @endif

      {{-- Actions --}}
      <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('admin.case-notes.index') }}"
           class="h-12 px-5 inline-flex items-center justify-center gap-2 rounded-2xl bg-white text-slate-600 border border-slate-200 text-xs font-black uppercase tracking-widest shadow-sm hover:bg-slate-50 active:scale-[.98] transition-all">
          <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Reset</span>
        </a>
      </div>
    </div>
  </form>

  {{-- ========= Modern Sticky Results Table ========= --}}
  <section id="case-print-root" class="relative group/table" x-data="{ scrolled: false }" x-init="$el.querySelector('.panel-scroll').addEventListener('scroll', e => { scrolled = e.target.scrollTop > 10 })">
    <div class="rounded-3xl bg-white border border-slate-200/60 shadow-xl shadow-slate-200/50 overflow-hidden transition-all duration-300 group-hover/table:shadow-slate-300/50">
      <div class="panel-scroll relative overflow-x-auto max-h-[calc(100vh-320px)] custom-scrollbar">
        <table class="w-full text-sm text-left border-separate border-spacing-0">
          <thead class="sticky top-0 z-20 transition-all duration-300" :class="scrolled ? 'bg-indigo-50/95 backdrop-blur-md shadow-sm' : 'bg-indigo-50'">
            <tr>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100 transition-all duration-300" :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">ID</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Source</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Student Name</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Counselor Name</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Presenting Problem</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Date</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100 text-right pr-6 transition-all duration-300" :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Actions</th>
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
                $rawSource   = $n->note_source ?? null;
              @endphp
              <tr class="group/row hover:bg-slate-50/80 transition-all duration-150">
                <td class="px-6 py-5 whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-[11px] font-bold ring-1 ring-inset ring-slate-200/60 group-hover/row:bg-white transition-colors">
                    {{ $code }}
                  </span>
                </td>

                <td class="px-6 py-5 whitespace-nowrap">
                  @if($rawSource === 'Walk-in')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-[11px] font-bold border border-amber-100/50">
                      <span class="size-1.5 rounded-full bg-amber-400"></span>
                      Walk-in
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-[11px] font-bold border border-indigo-100/50">
                      <span class="size-1.5 rounded-full bg-indigo-400"></span>
                      Scheduled
                    </span>
                  @endif
                </td>

                <td class="px-6 py-5 whitespace-nowrap">
                  <div class="text-sm font-bold text-slate-700 group-hover/row:text-indigo-600 transition-colors">{{ $studentName }}</div>
                </td>

                <td class="px-6 py-5 whitespace-nowrap">
                   <div class="text-[12px] font-semibold text-slate-600">{{ $counselor }}</div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap" title="{{ $n->presenting_problem ?? '' }}">
                   <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-50 text-slate-600 text-[11px] font-medium border border-slate-200/60 max-w-[200px] truncate group-hover/row:bg-white transition-colors">
                      {{ Str::limit($n->presenting_problem ?? '—', 32) }}
                   </span>
                </td>

                <td class="px-6 py-5 whitespace-nowrap">
                  <div class="text-[12px] font-bold text-slate-500">{{ $date }}</div>
                </td>

                <td class="px-6 py-4 text-right pr-6">
                  <a href="{{ route('admin.case-notes.show', $n->id) }}"
                     class="inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 hover:text-indigo-600 hover:border-indigo-200 shadow-sm transition-all duration-200 active:scale-95 group/btn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="px-6 py-20">
                  <div class="flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 rounded-3xl bg-slate-50 flex items-center justify-center text-slate-300 mb-4 border border-slate-100 shadow-inner">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-slate-800 font-black uppercase tracking-widest text-[11px]">No Case Records Found</p>
                    <p class="text-slate-400 text-xs mt-1 font-medium">Try adjusting your filters or search keywords.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if(($notes instanceof \Illuminate\Contracts\Pagination\Paginator) && $notes->hasPages())
        <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">
            Showing <span class="text-slate-700">{{ $notes->firstItem() }}</span>–<span class="text-slate-700">{{ $notes->lastItem() }}</span> 
            of <span class="text-slate-700">{{ $notes->total() }}</span> Entries
          </p>
          <div class="modern-pagination">
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
  #case-print-root .rounded-2xl, #case-print-root .shadow-sm, #case-print-root .border {
    border:0 !important; box-shadow:none !important;
  }
  #case-print-root .overflow-x-auto { overflow: visible !important; }
  #case-print-root th.col-action,
  #case-print-root td.col-action,
  #case-print-root col.col-action,
  #case-print-root thead th:last-child,
  #case-print-root tbody td:last-child {
    display:none !important; visibility:hidden !important;
  }
</style>
@endsection

@push('scripts')
<script>
  (function () {
    const form      = document.querySelector('form[action="{{ route('admin.case-notes.index') }}"]');
    if (!form) return;

    const qInput    = document.getElementById('qInput');
    const fromInput = form.querySelector('input[name="from"]');
    const toInput   = form.querySelector('input[name="to"]');

    // ensure we send date=range for custom range auto-submit
    const ensureRangeDateField = () => {
      let hidden = form.querySelector('input[name="date"][type="hidden"]');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = 'date';
        form.appendChild(hidden);
      }
      hidden.value = 'range';
    };

    // keep focus on search + caret at the end after reload
    window.addEventListener('load', () => {
      if (!qInput) return;
      qInput.focus();
      const val = qInput.value || '';
      if (typeof qInput.setSelectionRange === 'function') {
        qInput.setSelectionRange(val.length, val.length);
      }
    });

    // search: submit only when pressing Enter
    if (qInput) {
      qInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          form.submit();
        }
      });
    }

    // custom range: auto-submit on change (and force date=range)
    const handleRangeChange = () => {
      ensureRangeDateField();
      form.submit();
    };

    if (fromInput) fromInput.addEventListener('change', handleRangeChange);
    if (toInput)   toInput.addEventListener('change', handleRangeChange);
  })();
</script>
@endpush
