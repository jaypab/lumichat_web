@extends('layouts.counselor')
@section('title','Walk-in Case #'.$walkin->id)
@section('page_title', 'Walk-in Case Note')

@section('content')
@php
  use Carbon\Carbon;

  $year = $walkin->note_date
          ? Carbon::parse($walkin->note_date)->format('Y')
          : (Carbon::parse($walkin->created_at)->format('Y') ?? now()->format('Y'));

  // WN = Walk-in Note
  $code = 'WN-'.$year.'-'.str_pad($walkin->id, 4, '0', STR_PAD_LEFT);

  $date = $walkin->note_date
          ? Carbon::parse($walkin->note_date)->format('F d, Y')
          : '—';

  $start = $walkin->start_time
          ? Carbon::parse($walkin->start_time)->format('M d, Y · g:i A')
          : null;

  $end   = $walkin->end_time
          ? Carbon::parse($walkin->end_time)->format('M d, Y · g:i A')
          : null;

  $duration = null;
  if ($walkin->start_time && $walkin->end_time) {
      $s = Carbon::parse($walkin->start_time);
      $e = Carbon::parse($walkin->end_time);
      $diffMins = $e->diffInMinutes($s);
      $h = intdiv($diffMins, 60);
      $m = $diffMins % 60;
      $duration = ($h ? $h.' hr'.($h>1?'s':'') : '').($h && $m ? ' ' : '').($m ? $m.' min' : '');
  }

  $courseYear = trim(
      ($walkin->course ?? '').($walkin->year_level ? ' - '.$walkin->year_level : '')
  ) ?: '—';

  $toBr = fn($v) => nl2br(e($v ?? '—'));
@endphp

<div class="max-w-5xl mx-auto p-6 space-y-6">

  {{-- Header --}}
  <div class="rounded-2xl bg-white border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-5">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900">
          Walk-in Case Note — <span class="font-semibold">{{ $code }}</span>
        </h2>
        <div class="text-sm text-slate-600">
          Saved {{ Carbon::parse($walkin->updated_at)->format('M d, Y g:i A') }}
        </div>
      </div>

      {{-- Actions (Back + Download PDF) --}}
      <div class="flex items-center gap-2">
        <a href="{{ route('counselor.walkins.index') }}"
           class="inline-flex items-center h-10 px-3.5 rounded-xl bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">
          Back
        </a>

        <a href="{{ route('counselor.walkins.show.export.pdf', $walkin->id) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 text-sm font-medium shadow-sm hover:bg-indigo-50 active:scale-[.99] transition">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
          </svg>
          Download PDF
        </a>
      </div>
    </div>

    {{-- Key facts --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-5 pt-0 text-sm">
      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60">
        <div class="text-slate-500">Student</div>
        <div class="font-medium text-slate-900">
          {{ $walkin->student_name ?? '—' }}
        </div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60">
        <div class="text-slate-500">Date</div>
        <div class="font-medium text-slate-900">{{ $date }}</div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60">
        <div class="text-slate-500">Program &amp; Year</div>
        <div class="font-medium text-slate-900">{{ $courseYear }}</div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60">
        <div class="text-slate-500">Counselor</div>
        <div class="font-medium text-slate-900">{{ $walkin->counselor_name ?? auth()->user()->name ?? '—' }}</div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60 md:col-span-2">
        <div class="text-slate-500">Session Time</div>
        <div class="font-medium text-slate-900">
          @if($start && $end)
            {{ $start }} – {{ $end }}
            @if($duration)
              <span class="text-slate-500"> • {{ $duration }}</span>
            @endif
          @elseif($start)
            Started {{ $start }}
          @else
            —
          @endif
        </div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60 md:col-span-2">
        <div class="text-slate-500">Brief Reason for Visit</div>
        <div class="font-medium text-slate-900 whitespace-pre-line">
          {!! $toBr($walkin->reason) !!}
        </div>
      </div>
    </div>
  </div>

  {{-- Sections --}}
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 space-y-5">
    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">I. Presenting Problem</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($walkin->presenting_problem) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">II. Observations</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($walkin->observations) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">III. Interventions / Counselor’s Actions</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($walkin->interventions) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">IV. Student’s Response / Insight</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($walkin->response) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">V. Plan / Follow-Up</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($walkin->plan_followup) !!}
      </div>
    </div>

    {{-- VI. Emergency Safety Plan --}}
    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">VI. Emergency Safety Plan</div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
          <div class="text-slate-500 text-xs mb-0.5">Contact Person</div>
          <div class="font-medium text-slate-900">{{ $walkin->emergency_contact_person ?? '—' }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
          <div class="text-slate-500 text-xs mb-0.5">Relationship</div>
          <div class="font-medium text-slate-900">{{ $walkin->emergency_relationship ?? '—' }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
          <div class="text-slate-500 text-xs mb-0.5">Contact No.</div>
          <div class="font-medium text-slate-900">{{ $walkin->emergency_contact_no ?? '—' }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
          <div class="text-slate-500 text-xs mb-0.5">Address</div>
          <div class="font-medium text-slate-900 whitespace-pre-line">
            {!! $toBr($walkin->emergency_address) !!}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
