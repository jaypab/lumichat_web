@extends('layouts.admin')
@section('title','Admin - Case Note #'.$note->id)
@section('page_title', 'Case Note')

@section('content')
@php
  $year = $note->note_date
          ? \Carbon\Carbon::parse($note->note_date)->format('Y')
          : (\Carbon\Carbon::parse($note->created_at)->format('Y') ?? now()->format('Y'));
  $code = 'CN-'.$year.'-'.str_pad($note->id, 4, '0', STR_PAD_LEFT);
  $date = $note->note_date ? \Carbon\Carbon::parse($note->note_date)->format('F d, Y') : '—';
  $toBr = fn($v) => nl2br(e($v ?? '—'));

  // be defensive: some old notes might not have this column
  $noteSource = $note->note_source ?? null;
@endphp

<div class="max-w-5xl mx-auto p-6 space-y-6">

  {{-- Header --}}
  <div class="rounded-2xl bg-white border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-5">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 flex flex-wrap items-center gap-2">
          <span>Case Note — <span class="font-semibold">{{ $code }}</span></span>

          {{-- Walk-in tag beside title --}}
          @if($noteSource === 'Walk-in')
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 ring-1 ring-amber-200">
              Walk-in
            </span>
          @endif
        </h2>
        <div class="text-sm text-slate-600">
          Saved {{ \Carbon\Carbon::parse($note->updated_at)->format('M d, Y g:i A') }}
        </div>
      </div>

      {{-- Actions (Back + Download PDF) --}}
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.case-notes.index') }}"
           class="inline-flex items-center h-10 px-3.5 rounded-xl bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">
          Back
        </a>
        <a href="{{ route('admin.case-notes.show.export.pdf', $note->id) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 text-sm font-medium shadow-sm hover:bg-blue-50 active:scale-[.99] transition">
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
        <div class="font-medium text-slate-900">{{ $note->student_display_name ?? $note->student_name ?? '—' }}</div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60">
        <div class="text-slate-500">Date</div>
        <div class="font-medium text-slate-900">{{ $date }}</div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60">
        <div class="text-slate-500">Counselor</div>
        <div class="font-medium text-slate-900">{{ $note->counselor_name ?? '—' }}</div>
      </div>

      <div class="rounded-xl ring-1 ring-slate-200 p-3.5 bg-slate-50/60">
        <div class="text-slate-500">Appointment</div>
        <div class="font-medium text-slate-900">
          @if($note->scheduled_at)
            {{ \Carbon\Carbon::parse($note->scheduled_at)->format('F d, Y g:i A') }}
            <span class="text-slate-500">• {{ ucfirst($note->appt_status ?? '—') }}</span>
          @else
            —
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Sections --}}
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 space-y-5">
    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">I. Presenting Problem</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($note->presenting_problem) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">II. Observations</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($note->observations) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">III. Interventions / Counselor’s Actions</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($note->interventions) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">IV. Student’s Response / Insight</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($note->response) !!}
      </div>
    </div>

    <div>
      <div class="text-[13px] font-semibold text-slate-700 mb-1.5">V. Plan / Follow-Up</div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 whitespace-pre-line">
        {!! $toBr($note->plan_followup) !!}
      </div>
    </div>
  </div>
</div>
@endsection
