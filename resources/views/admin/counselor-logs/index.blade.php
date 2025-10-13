@extends('layouts.admin')
@section('title','Admin - Counselors Logs')
@section('page_title', 'Counselors Logs') 

@section('content')
@php
  // Labels for subtitle + header counter
  $cName = $cid ? optional($counselors->firstWhere('id',$cid))->full_name : 'All';
  $mName = $month ? \Carbon\Carbon::create(null,$month,1)->format('F') : 'All';
  $yName = $year ?: 'All';

  $totalLogs = method_exists($rows,'total') ? $rows->total() : $rows->count();
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Page Header (consistent with Appointments) ========= --}}
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between animate-fadeup screen-only">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Counselor Logs</h2>
      <p class="text-sm text-slate-600">
        Per counselor, grouped by Month/Year with students handled and most common diagnosis.
        <span class="ml-2 text-slate-400">•</span>
        <span class="ml-2 text-slate-500">{{ $totalLogs }} {{ Str::plural('record', $totalLogs) }}</span>
      </p>
    </div>
    <a href="{{ route('admin.counselor-logs.export.pdf', request()->only('counselor_id','month','year')) }}"
       class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
      </svg>
      Download PDF
    </a>
  </div>

  {{-- ========= Filter Bar (match Appointments exactly) ========= --}}
  <form method="GET" class="mb-6 screen-only">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end animate-fadeup">

      {{-- Counselor --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Counselor</label>
        <select name="counselor_id"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All counselors</option>
          @foreach($counselors as $co)
            <option value="{{ $co->id }}" @selected($cid==$co->id)>{{ $co->full_name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Month --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
        <select name="month"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All</option>
          @for($m=1;$m<=12;$m++)
            <option value="{{ $m }}" @selected($month==$m)>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>
          @endfor
        </select>
      </div>

      {{-- Year --}}
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
        <select name="year"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All</option>
          @foreach($years as $y)
            <option value="{{ $y }}" @selected($year==$y)>{{ $y }}</option>
          @endforeach
        </select>
      </div>

      {{-- spacer pushes buttons to the right --}}
      <div class="sm:ml-auto"></div>

      {{-- right side: Reset / Apply --}}
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.counselor-logs.index') }}"
          class="h-11 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200
                  shadow-sm hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h7M4 10h16M4 16h10"/>
          </svg>
          Reset
        </a>

        <button
          class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm">
          Apply
        </button>
      </div>

    </div>
  </form>

  {{-- RESULTS (screen + print scope) --}}
  <div id="print-counselor-index" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="relative overflow-x-auto">
      <table class="min-w-full text-sm leading-6 table-auto">
        <colgroup>
          <col style="width:26%">
          <col style="width:18%">
          <col style="width:36%">
          <col style="width:12%">
          <col class="col-action" style="width:8%">
        </colgroup>

        {{-- Table header (matches Appointments) --}}
        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
          <tr class="align-middle">
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Counselor</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Month / Year</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Students handled</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Common diagnosis</th>
            <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap col-action">Action</th>
          </tr>
        </thead>

        {{-- ✅ BODY --}}
        <tbody class="divide-y divide-slate-100">
          @forelse($rows as $r)
            <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-700 grid place-items-center text-xs font-semibold">
                    {{ \Illuminate\Support\Str::of($r->counselor_name)->explode(' ')->map(fn($p)=>mb_substr($p,0,1))->take(2)->join('') }}
                  </div>
                  <div class="font-medium text-slate-900">{{ $r->counselor_name }}</div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center h-7 px-3 rounded-full text-xs font-medium ring-1
                            bg-violet-50 text-violet-700 ring-violet-200">
                  {{ $r->month_year }}
                </span>
              </td>

              {{-- Students handled (limit to first 10 + “+N others”) --}}
              <td class="px-6 py-4">
                @php
                  $names = $r->students_list ? explode(' | ', $r->students_list) : [];
                  $limit = 10;
                  $shown = array_slice($names, 0, $limit);
                  $extra = max(count($names) - $limit, 0);
                @endphp

                @if(count($names))
                  <div class="text-slate-700">
                    {{ implode(', ', $shown) }}
                    @if($extra > 0)
                      <span class="text-slate-500"> +{{ $extra }} others</span>
                    @endif
                  </div>
                  <span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium mt-1
                              bg-slate-50 text-slate-600 ring-1 ring-slate-200">
                    {{ $r->students_count }} unique
                  </span>
                @else
                  <span class="text-slate-400">—</span>
                @endif
              </td>

              {{-- Common diagnosis: support multiple (dx_list) with chips; fallback to single common_dx --}}
              <td class="px-6 py-4">
                @php
                  // prefer dx_list ('||' separated). If absent, fallback to common_dx.
                  $dx = [];
                  if (isset($r->dx_list) && $r->dx_list !== null && $r->dx_list !== '') {
                      $dx = array_filter(array_map('trim', explode('||', $r->dx_list)));
                  } elseif (!empty($r->common_dx)) {
                      $dx = [trim($r->common_dx)];
                  }
                @endphp

                @if(count($dx))
                  <div class="flex flex-wrap gap-1.5">
                    @foreach($dx as $label)
                      <span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium
                                   bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                        {{ $label }}
                      </span>
                    @endforeach
                  </div>
                @else
                  <span class="text-slate-400">—</span>
                @endif
              </td>

              <td class="px-6 py-4 text-right col-action">
                <a href="{{ route('admin.counselor-logs.show', ['counselor'=>$r->counselor_id, 'month'=>$r->month_num, 'year'=>$r->year_num]) }}"
                  class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 active:scale-[.98] transition">
                  View
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-6 py-10 text-center text-slate-500">No records found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($rows,'hasPages') && $rows->hasPages())
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 screen-only">
        {{ $rows->withQueryString()->links() }}
      </div>
    @endif
  </div>
</div>
</div>

@endsection
