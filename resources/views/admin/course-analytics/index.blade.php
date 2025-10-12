@extends('layouts.admin')
@section('title','Admin - Course Summary')
@section('page_title', 'Course Summary')

@section('content')
@php
  $yearKey   = request('year','all');
  $courseOptions = $courseOptions ?? collect();
  $courseKey     = $courseKey     ?? 'all';
  $total     = is_countable($courses) ? count($courses) : ($courses?->count() ?? 0);
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Header ========= --}}
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between animate-fadeup screen-only">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Course Summary</h2>
      <p class="text-sm text-slate-600">
        Visual breakdown of mental wellness patterns across student programs.
        <span class="mx-2 text-slate-400">•</span>
        <span class="text-slate-500">{{ $total }} {{ \Illuminate\Support\Str::plural('record', $total) }}</span>
      </p>
    </div>

    <a href="{{ route('admin.course-analytics.export.pdf', request()->only('year','course')) }}"
       class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
      </svg>
      Download PDF
    </a>
  </div>

  {{-- ========= Filters ========= --}}
  <form method="GET" action="{{ route('admin.course-analytics.index') }}" class="mb-6 screen-only">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end animate-fadeup">

      {{-- Year Level --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Year Level</label>
        <select name="year"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="all" {{ $yearKey==='all' ? 'selected' : '' }}>All</option>
          <option value="1"   {{ $yearKey==='1' ? 'selected' : '' }}>1st year</option>
          <option value="2"   {{ $yearKey==='2' ? 'selected' : '' }}>2nd year</option>
          <option value="3"   {{ $yearKey==='3' ? 'selected' : '' }}>3rd year</option>
          <option value="4"   {{ $yearKey==='4' ? 'selected' : '' }}>4th year</option>
        </select>
      </div>

      {{-- Course (dropdown) --}}
      <div class="md:col-span-5 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Course</label>
        <div class="relative">
          <select name="course"
                  class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-3 pr-8 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
            <option value="all" {{ $courseKey==='all' ? 'selected' : '' }}>All courses</option>
            @foreach($courseOptions as $opt)
              @php
                // $opt can be ['code'=>'BSIT','name'=>'College of Information Technology']
                $code = is_array($opt) ? ($opt['code'] ?? $opt['value'] ?? $opt[0] ?? '') : ($opt->code ?? (string)$opt);
                $name = is_array($opt) ? ($opt['name'] ?? $opt['label'] ?? $code) : ($opt->name ?? $code);
              @endphp
              <option value="{{ $code }}" {{ $courseKey===$code ? 'selected' : '' }}>
                {{ $code }} — {{ $name }}
              </option>
            @endforeach
          </select>
          <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M6 9l6 6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>

      {{-- Right side: Reset / Apply --}}
      <div class="md:col-span-4 flex items-center justify-end gap-2">
        <a href="{{ route('admin.course-analytics.index') }}"
           class="h-11 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200
                  shadow-sm hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h7M4 10h16M4 16h10"/>
          </svg>
          Reset
        </a>

        <button type="submit"
                class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm">
          Apply
        </button>
      </div>

    </div>
  </form>

  {{-- ========= Table ========= --}}
  <div id="print-analytics-index" class="space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-[980px] w-full text-sm text-left">
          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
            <tr class="text-[12px] uppercase tracking-wide">
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Course</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Year Level</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">No. of Students</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Common Diagnoses</th>
              <th class="px-6 py-3 text-right font-semibold whitespace-nowrap screen-only">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100 text-slate-800">
            @forelse ($courses as $c)
              @php
                $id        = $c->id;
                $course    = $c->course ?? '—';
                $year      = $c->year_level ?? '—';
                $count     = $c->student_count ?? 0;

                // Expect array like ['Stress','Depression','Financial Stress', ...]
                $dxList    = is_array($c->common_diagnoses ?? null) ? $c->common_diagnoses : [];
                // Limit to 3 chips, then show “+N more”
                $chips     = array_slice($dxList, 0, 3);
                $moreN     = max(0, count($dxList) - count($chips));
              @endphp

              <tr class="hover:bg-slate-50 transition align-top">
                <td class="px-6 py-4 font-medium text-slate-900 whitespace-nowrap">{{ $course }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $year }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $count }}</td>
                <td class="px-6 py-4">
                  <div class="flex flex-wrap gap-1.5">
                    @forelse($chips as $dx)
                      <span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                        {{ $dx }}
                      </span>
                    @empty
                      <span class="text-slate-400">—</span>
                    @endforelse
                    @if($moreN > 0)
                      <span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium bg-slate-50 text-slate-600 ring-1 ring-slate-200">
                        +{{ $moreN }} more
                      </span>
                    @endif
                  </div>
                </td>
                <td class="px-6 py-4 text-right screen-only">
                  <a href="{{ route('admin.course-analytics.show', $id) }}"
                     class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
                    View
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-6 pt-14 pb-10 text-center">
                  <div class="mx-auto w-full max-w-sm">
                    <div class="mx-auto w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
                      <img src="{{ asset('images/icons/nodata.png') }}" alt="" class="w-6 h-6 opacity-60">
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700">No course summary found</p>
                    <p class="text-xs text-slate-500 mb-6">Data will appear here once available.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- ========= Print rules ========= --}}
<style>
@media print{
  body *{ visibility:hidden !important; }
  #print-analytics-index, #print-analytics-index *{ visibility:visible !important; }
  #print-analytics-index{ position:fixed; inset:0; margin:12mm !important; background:#fff; }
  #print-analytics-index .overflow-x-auto{ overflow:visible !important; }
  #print-analytics-index .shadow-sm{ box-shadow:none !important; }
  #print-analytics-index .border{ border:0 !important; }
  .screen-only{ display:none !important; }
  @page{ size:A4; margin:12mm 14mm; }
}
</style>
@endsection
