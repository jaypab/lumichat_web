@extends('layouts.admin')
@section('title','Admin - Course Analytics')
@section('page_title', 'Course Analytics') 

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
  {{-- ========= Filter bar (consistent sizing) ========= --}}
  @php
  $yearKey = request('year','all');
  $q       = request('q','');
  $total   = is_countable($courses) ? count($courses) : ($courses?->count() ?? 0);
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- Header (match Appointments) --}}
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between animate-fadeup screen-only">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Course Analytics</h2>
      <p class="text-sm text-slate-600">
        Visual breakdown of mental wellness patterns across different student programs.
        <span class="mx-2 text-slate-400">•</span>
        <span class="text-slate-500">{{ $total }} {{ \Illuminate\Support\Str::plural('record', $total) }}</span>
      </p>
    </div>

     <a href="{{ route('admin.course-analytics.export.pdf', request()->only('year','q')) }}"
       class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
      </svg>
      Download PDF
    </a>
  </div>

  {{-- Filters (12-col grid: 5 / 5 / 2) --}}
  <form method="GET" action="{{ route('admin.course-analytics.index') }}" class="mb-6 screen-only">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end animate-fadeup">

      {{-- Year Level (3) --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Year Level</label>
        <select name="year"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="all" {{ ($yearKey ?? 'all')==='all' ? 'selected' : '' }}>All</option>
          <option value="1"   {{ ($yearKey ?? '')==='1' ? 'selected' : '' }}>1st year</option>
          <option value="2"   {{ ($yearKey ?? '')==='2' ? 'selected' : '' }}>2nd year</option>
          <option value="3"   {{ ($yearKey ?? '')==='3' ? 'selected' : '' }}>3rd year</option>
          <option value="4"   {{ ($yearKey ?? '')==='4' ? 'selected' : '' }}>4th year</option>
        </select>
      </div>

      {{-- Search (3) --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
        <div class="relative">
          <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Search course…"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      {{-- Right side: Reset / Apply (push to far right) --}}
      <div class="md:col-span-6 md:col-start-7 flex items-center justify-end gap-2">
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


  {{-- ========= PRINT SCOPE ========= --}}
  <div id="print-analytics-index" class="space-y-6">

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-[920px] w-full text-sm text-left">
          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
            <tr class="text-[12px] uppercase tracking-wide">
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Course</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Year Level</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">No. of Students</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Common Diagnosis</th>
              <th class="px-6 py-3 text-right font-semibold whitespace-nowrap screen-only">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100 text-slate-800">
            @forelse ($courses as $c)
              @php
                $id        = $c->id;
                $course    = $c->course ?? '—';
                $year      = $c->year_level ?? '—';
                $count     = $c->student_count ?? '—';
                $list      = is_array($c->common_diagnoses ?? null) ? $c->common_diagnoses : [];
                $diagnoses = count($list) ? implode(', ', $list) : '—';
              @endphp
              <tr class="hover:bg-slate-50 transition align-top">
                <td class="px-6 py-4 font-medium text-slate-900 whitespace-nowrap">{{ $course }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $year }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $count }}</td>
                <td class="px-6 py-4">
                  <div class="max-w-[420px] leading-relaxed">{{ $diagnoses }}</div>
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
                    <p class="mt-3 text-sm font-medium text-slate-700">No course analytics found</p>
                    <p class="text-xs text-slate-500 mb-6">Analytics will appear here once data becomes available.</p>
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

{{-- ========= Print rules (hide UI chrome; keep table) ========= --}}
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
