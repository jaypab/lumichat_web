@extends('layouts.admin')
@section('title','Counselor Logs · '.$counselor->full_name)

@section('content')
@php
  $label = \Carbon\Carbon::create($year,$month,1)->format('F Y');
@endphp

<div class="max-w-6xl mx-auto space-y-6">

  {{-- Header (screen only) --}}
  <div class="flex items-start justify-between gap-3 screen-only">
    <div>
      <h2 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $counselor->full_name }}</h2>
      <p class="text-sm text-slate-500">
        Logs for <span class="font-medium text-slate-700">{{ $label }}</span>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <a href="{{ route('admin.counselor-logs.show.export', ['counselor'=>$counselor->id, 'month'=>$month, 'year'=>$year]) }}"
         class="inline-flex items-center h-10 px-4 rounded-xl text-sm font-medium bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
        {{-- download icon --}}
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Download PDF
      </a>

      <a href="{{ route('admin.counselor-logs.index') }}"
         class="inline-flex items-center h-10 px-4 rounded-xl text-sm font-medium bg-white border border-slate-200 text-slate-700 shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
        ← Back
      </a>
    </div>
  </div>

  {{-- ===== PRINT SCOPE ===== --}}
  <div id="print-counselor-show" class="space-y-5">

    {{-- Print title --}}
    <h1 class="hidden print:block text-xl font-semibold">Counselor Logs — {{ $counselor->full_name }} ({{ $label }})</h1>

    {{-- Diagnosis summary (chips) --}}
    @if($dxCounts->count())
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden relative">
        <span class="pointer-events-none absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>
        <div class="px-5 py-4">
          <div class="text-sm font-semibold text-slate-800 mb-2">Diagnosis Summary</div>
          <div class="flex flex-wrap gap-2">
            @foreach($dxCounts as $dx)
              <span class="px-3 py-1.5 rounded-full border border-sky-200 bg-sky-50 text-sky-700 text-xs">
                {{ $dx->diagnosis_result }} • {{ $dx->cnt }}
              </span>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Non-Technical table --}}
    <div class="relative rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-200 text-sm font-semibold text-slate-800">
        Student Diagnosis Summary
      </div>

      <div class="relative overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr class="text-left">
              <th class="px-5 py-3 font-medium">Student</th>
              <th class="px-5 py-3 font-medium">Scheduled</th>
              <th class="px-5 py-3 font-medium">Diagnosis / Result</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse($students as $row)
              <tr class="hover:bg-slate-50/60">
                <td class="px-5 py-3">{{ $row->student_name ?? '—' }}</td>
                <td class="px-5 py-3">{{ $row->scheduled_at_fmt }}</td>
                <td class="px-5 py-3">{{ $row->diagnosis_result }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="px-5 py-10 text-center text-slate-500">No appointments this month.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>{{-- /print scope --}}
</div>

{{-- Print rules: isolate this report --}}
<style>
@media print{
  body *{ visibility:hidden !important; }
  #print-counselor-show, #print-counselor-show *{ visibility:visible !important; }
  #print-counselor-show{ position:fixed; inset:0; margin:12mm !important; background:#fff; }
  #print-counselor-show .overflow-x-auto{ overflow:visible !important; }
  #print-counselor-show .shadow-sm{ box-shadow:none !important; }
  #print-counselor-show .border{ border:0 !important; }
  .screen-only{ display:none !important; }
  @page{ size:A4; margin:12mm 14mm; }
}
</style>
@endsection
