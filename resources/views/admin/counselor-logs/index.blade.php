@extends('layouts.admin')
@section('title', 'Admin - Counselors Logs')
@section('page_title', 'Counselors Logs')

@section('content')
@php
  $cName = $cid ? optional($counselors->firstWhere('id',$cid))->full_name : 'All';
  $mName = $month ? \Carbon\Carbon::create(null,$month,1)->format('F') : 'All';
  $yName = $year ?: 'All';
  $totalLogs = method_exists($rows,'total') ? $rows->total() : $rows->count();
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Counselor Logs</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20">
          {{ number_format($totalLogs) }} Records
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">Grouped performance data by Month/Year with student load and diagnosis mapping.</p>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ route('admin.counselor-logs.export.pdf', request()->only('counselor_id','month','year')) }}"
         target="_blank" rel="noopener"
         class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Export PDF
      </a>
    </div>
  </header>

  {{-- ========= Filters (Modern Inline Bar) ========= --}}
  <form id="clogsFilterForm" method="GET" class="screen-only">
    <div class="bg-white rounded-2xl border border-slate-200/60 p-2 shadow-sm flex flex-wrap items-center gap-2">
      <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-2">
        <div class="relative group">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </div>
          <select name="counselor_id"
                  class="w-full h-12 bg-white border-none rounded-xl pl-10 pr-4 text-sm font-medium text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all appearance-none cursor-pointer">
            <option value="">All Counselors</option>
            @foreach($counselors as $co)
              <option value="{{ $co->id }}" @selected((string)$cid===(string)$co->id)>{{ $co->full_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="relative group">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <select name="month"
                  class="w-full h-12 bg-white border-none rounded-xl pl-10 pr-4 text-sm font-medium text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all appearance-none cursor-pointer">
            <option value="">All Months</option>
            @for($m=1;$m<=12;$m++)
              <option value="{{ $m }}" @selected((string)$month===(string)$m)>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>
            @endfor
          </select>
        </div>

        <div class="relative group">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <select name="year"
                  class="w-full h-12 bg-white border-none rounded-xl pl-10 pr-4 text-sm font-medium text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all appearance-none cursor-pointer">
            <option value="">All Years</option>
            @foreach($years as $y)
              <option value="{{ $y }}" @selected((string)$year===(string)$y)>{{ $y }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <a href="{{ route('admin.counselor-logs.index') }}"
         class="h-12 inline-flex items-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 transition-all duration-200">
        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        Reset Filter
      </a>
    </div>
  </form>

  {{-- ========= LOGS TABLE (Matching Students Design) ========= --}}
  <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 overflow-hidden transition-all duration-300">
    <div class="relative overflow-visible">
      <table class="min-w-full text-sm leading-relaxed table-auto">
        <thead x-data="{ scrolled: false }" 
               x-init="const s = document.querySelector('.panel-scroll'); if(s){ s.addEventListener('scroll', () => scrolled = s.scrollTop > 10) }"
               class="text-slate-700 sticky top-0 z-20">
          <tr>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">Counselor</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Month / Year</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Load Summary</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Core Concern</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 col-action transition-all duration-300"
                :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Action</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse($rows as $r)
            <tr class="group hover:bg-indigo-50/30 transition-colors duration-200 align-middle">
              <td class="px-6 py-5 whitespace-nowrap">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 rounded-2xl overflow-hidden bg-indigo-100 flex items-center justify-center font-black text-indigo-600 text-xs shadow-sm ring-2 ring-white">
                    {{ \Illuminate\Support\Str::of($r->counselor_name)->explode(' ')->map(fn($p)=>mb_substr($p,0,1))->take(2)->join('') }}
                  </div>
                  <div class="flex flex-col">
                    <span class="font-bold text-slate-900 group-hover:text-indigo-600 transition-colors duration-200">{{ $r->counselor_name }}</span>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Faculty Counselor</span>
                  </div>
                </div>
              </td>

              <td class="px-6 py-5 whitespace-nowrap">
                @php
                  $labelMonthYear = \Carbon\Carbon::create($r->year_num, $r->month_num, 1)->format('F Y');
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-violet-50 text-violet-700 border border-violet-100">
                  {{ $labelMonthYear }}
                </span>
              </td>

              {{-- Load Summary --}}
              <td class="px-6 py-5">
                @php
                  $names = $r->students_list ? explode(' | ', $r->students_list) : [];
                  $limit = 10;
                  $shown = array_slice($names, 0, $limit);
                  $extra = max(count($names) - $limit, 0);
                @endphp
                @if($names)
                   <div class="text-[13px] text-slate-600 font-medium">
                      {{ implode(', ', $shown) }}
                      @if($extra > 0)
                        <span class="text-indigo-500 font-black"> +{{ $extra }} others</span>
                      @endif
                   </div>
                   <div class="mt-1 flex items-center gap-2">
                       <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-wider border border-emerald-100">
                         {{ $r->students_count }} Cases
                       </span>
                   </div>
                @else
                  <span class="text-slate-300 font-medium">—</span>
                @endif
              </td>

              {{-- Core Concern (Presenting Problems) --}}
              <td class="px-6 py-5">
                @php
                  $dx = [];
                  $rawDxList = $r->dx_list ?? null;
                  $rawCommonDx = $r->common_dx ?? null;

                  $tryDecode = function($raw) {
                    if (!is_string($raw) || $raw === '') return null;
                    $trim = trim($raw);
                    if ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{')) {
                      $j = json_decode($trim, true);
                      if (json_last_error() === JSON_ERROR_NONE) return $j;
                    }
                    return null;
                  };

                  if (!empty($rawDxList)) {
                    $maybeJson = $tryDecode($rawDxList);
                    $dx = is_array($maybeJson) ? $maybeJson : array_values(array_filter(array_map('trim', explode('||', (string)$rawDxList))));
                  } elseif (!empty($rawCommonDx)) {
                    $decoded = $tryDecode($rawCommonDx);
                    if (is_array($decoded)) {
                      $dx = array_values(array_keys($decoded) !== range(0, count($decoded)-1) ? collect($decoded)->sortDesc()->keys()->all() : $decoded);
                    } else {
                      $raw = trim((string)$rawCommonDx, "[]");
                      $dx = array_map(fn($v)=>trim($v," \t\n\r\0\x0B\"'"), array_filter(array_map('trim', explode(',', $raw))));
                    }
                  }

                  $dx = collect($dx)->map(fn($v)=>trim((string)$v, " \t\n\r\0\x0B\"'"))->filter()->unique()->values()->all();
                  $MAX = 2;
                  $shown = array_slice($dx, 0, $MAX);
                  $more = array_slice($dx, $MAX);

                  $palette = [
                    'Stress' => 'amber', 'Depression' => 'rose', 'Anxiety' => 'sky', 'Relationship Issues' => 'orange',
                    'Family Problems' => 'yellow', 'Academic Pressure' => 'blue', 'Financial Stress' => 'teal',
                    'Low Self-Esteem' => 'fuchsia', 'Sleep Problems' => 'indigo', 'Time Management' => 'violet'
                  ];
                @endphp

                @if(count($shown))
                  <div class="flex flex-wrap gap-1.5 max-w-[200px]">
                    @foreach($shown as $label)
                      @php $c = $palette[$label] ?? 'slate'; @endphp
                      <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-{{$c}}-50 text-{{$c}}-700 border border-{{$c}}-200/60 shadow-sm leading-none">
                        {{ $label }}
                      </span>
                    @endforeach
                    @if(count($more))
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-slate-50 text-slate-400 border border-slate-200/60 leading-none">
                        +{{ count($more) }}
                      </span>
                    @endif
                  </div>
                @else
                  <span class="text-slate-300 font-medium">—</span>
                @endif
              </td>

              <td class="px-6 py-5 text-right col-action">
                <a href="{{ route('admin.counselor-logs.show', ['counselor'=>$r->counselor_id, 'month'=>$r->month_num, 'year'=>$r->year_num]) }}"
                   class="inline-flex items-center justify-center rounded-xl bg-indigo-600 text-white px-4 py-2.5 text-xs font-black uppercase tracking-widest shadow-sm shadow-indigo-500/20 hover:bg-indigo-700 active:scale-[.98] transition-all leading-none">
                  View
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-6 py-10 text-center text-slate-500 font-medium">No performance records found for this period.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($rows,'hasPages') && $rows->hasPages())
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70">
        {{ $rows->withQueryString()->links() }}
      </div>
    @endif
  </div>
</div>

{{-- Auto-submit filters on change --}}
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('clogsFilterForm');
    if (!form) return;

    form.querySelectorAll('select').forEach((el) => {
      el.addEventListener('change', () => {
        let pageInput = form.querySelector('input[name="page"]');
        if (!pageInput) {
          pageInput = document.createElement('input');
          pageInput.type = 'hidden';
          pageInput.name = 'page';
          form.appendChild(pageInput);
        }
        pageInput.value = ''; 
        form.submit();
      });
    });
  });
</script>
@endsection