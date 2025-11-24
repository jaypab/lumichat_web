@extends('layouts.counselor')
@section('title', 'Appointment #'.$appointment->id)
@section('page_title', 'Appointment Details')

@php
  use Carbon\Carbon;
  use Illuminate\Support\Facades\DB;

  $dt       = Carbon::parse($appointment->scheduled_at);
  $now      = Carbon::now();
  $bookedAt = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;

  $hasStarted = $now->gte($dt);
  $rawStatus  = strtolower((string)$appointment->status);
  $status     = !empty($isHistory) ? 'reassigned' : $rawStatus;
  $isOngoing  = ($status === 'ongoing');

  // human countdown / since
  $when = $now->isBefore($dt)
    ? 'Starts in '.$dt->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
    : ($isOngoing
        ? 'Started '.$dt->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
        : 'Started '.$dt->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
      );

  // Actions
  $canConfirm  = ($status === 'pending');
  $canStart    = ($status === 'confirmed') && $now->gte($dt->copy()->subMinutes(10)); // grace start
  $canDone     = (in_array($status, ['confirmed','ongoing'], true) && $hasStarted);
  $canFollowUp = in_array($status, ['completed', 'no_show'], true);

  $doneTitle = !$hasStarted
      ? 'You can only end the session after the scheduled start time'
      : 'End this session';

  // chips
   $badgeMap = [
    'pending'    => 'bg-amber-100 text-amber-800',
    'confirmed'  => 'bg-blue-100 text-blue-800',
    'canceled'   => 'bg-rose-100 text-rose-800',
    'completed'  => 'bg-emerald-100 text-emerald-800',
    'no_show'    => 'bg-rose-100 text-rose-800',
    'ongoing'    => 'bg-indigo-100 text-indigo-800',
    'reassigned' => 'bg-slate-100 text-slate-800',
  ];
  $dotMap = [
    'pending'    => 'bg-amber-500',
    'confirmed'  => 'bg-blue-500',
    'canceled'   => 'bg-rose-500',
    'completed'  => 'bg-emerald-500',
    'no_show'    => 'bg-rose-500',
    'ongoing'    => 'bg-indigo-600',
    'reassigned' => 'bg-slate-500',  
  ];         
  $cls = $badgeMap[$status] ?? 'bg-slate-200 text-slate-700';
  $dot = $dotMap[$status] ?? 'bg-slate-500';

  /* ─────────────────────────────────────────────────────────────
     FOLLOW-UP STATE
     Find the newest child appointment where parent_id = current id.
     If present, we show “Follow-up placed” instead of Create button.
     ───────────────────────────────────────────────────────────── */
  $followUp = DB::table('tbl_appointments')
      ->where('parent_id', $appointment->id)
      ->orderByDesc('scheduled_at')
      ->first();

  $hasFollowUp = $followUp !== null;
@endphp

@section('content')
<div class="max-w-6xl mx-auto p-6">
  <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <span class="pointer-events-none absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>

    {{-- Header --}}
    <div class="px-6 pt-5">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <h2 class="text-[20px] font-semibold text-slate-900">Appointment #{{ $appointment->id }}</h2>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $cls }}">
              <span class="inline-block w-1.5 h-1.5 rounded-full {{ $dot }} mr-1.5"></span>
              {{ $status === 'no_show' ? 'No Show' : ucfirst($status) }}
            </span>

            @php
              // When ongoing, use updated_at (set at 'start'); otherwise use scheduled_at
              $elapsedStartIso = \Carbon\Carbon::parse(
                  $isOngoing ? ($appointment->updated_at ?? $appointment->scheduled_at) : $appointment->scheduled_at
              )->toIso8601String();
            @endphp

            @if($isOngoing)
              <span
                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-700 bg-indigo-50 ring-1 ring-indigo-200 px-2 py-1 rounded-full"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20zm0-2a8 8 0 100-16 8 8 0 010 16z"/>
                </svg>
                <span id="js-elapsed" data-start="{{ $elapsedStartIso }}">00:00</span>
              </span>
            @endif

          </div>
          <div class="mt-1 text-sm text-slate-500 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20z"/>
            </svg>
            {{ $when }}
          </div>
        </div>

        <div class="flex items-center gap-2">
          <a href="{{ route('counselor.appointments.index') }}"
             class="inline-flex items-center gap-2 h-10 px-3.5 rounded-xl bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">
            Back
          </a>

          {{-- counselor-side single PDF --}}
          <a href="{{ route('counselor.appointments.export.show.pdf', $appointment->id) }}"
             target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
            </svg>
            Download PDF
          </a>
        </div>
      </div>

      {{-- Actions --}}
      <div class="mt-4 flex flex-wrap items-center gap-2">

        {{-- Start Session --}}
        <form method="POST"
              action="{{ route('counselor.appointments.status', $appointment->id) }}"
              @if(!$canStart || $isOngoing) onsubmit="return false" @else onsubmit="return askAction(event, this, 'start')" @endif
              class="{{ ($canStart && !$isOngoing) ? '' : 'pointer-events-none' }}">
          @csrf
          @method('PATCH')
          <input type="hidden" name="action" value="start">
          <button type="submit"
                  title="{{ $canStart ? 'Set status to Ongoing' : 'You can start near/after the scheduled time' }}"
                  class="px-4 py-2 rounded-lg text-white {{ ($canStart && !$isOngoing) ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-indigo-600 opacity-50 cursor-not-allowed' }}">
            Start Session
          </button>
        </form>

        {{-- End Session --}}
        <form method="POST"
              action="{{ route('counselor.appointments.status', $appointment->id) }}"
              @if(!$canDone) onsubmit="return false" @else onsubmit="return askAction(event, this, 'done')" @endif
              class="{{ $canDone ? '' : 'pointer-events-none' }}">
          @csrf
          @method('PATCH')
          <input type="hidden" name="action" value="done">
          <button type="submit"
                  title="{{ $doneTitle }}"
                  class="px-4 py-2 rounded-lg text-white {{ $canDone ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-emerald-600 opacity-50 cursor-not-allowed' }}">
            {{ $isOngoing ? 'End Session' : 'Done' }}
          </button>
        </form>

        {{-- No-Show --}}
        @if(in_array($status, ['pending','confirmed']))
          <form method="POST" action="{{ route('counselor.appointments.no_show', $appointment->id) }}"
                onsubmit="return askAction(event, this, 'no_show')">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white hover:bg-rose-700">
              Mark as No-Show
            </button>
          </form>
        @endif

        {{-- Follow-up --}}
        @if ($canFollowUp && !$hasFollowUp)
          <a href="{{ route('counselor.appointments.follow.form', $appointment->id) }}"
             class="inline-flex items-center rounded-lg bg-indigo-50 px-4 py-2 text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-100">
            Create Follow-up
          </a>
        @elseif ($hasFollowUp)
          <div class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3.5 py-2 text-emerald-700 ring-1 ring-emerald-200">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            Follow-up placed — {{ \Carbon\Carbon::parse($followUp->scheduled_at)->format('M d, Y g:i A') }}
          </div>
          <a href="{{ route('counselor.appointments.show', $followUp->id) }}"
             class="inline-flex items-center rounded-lg bg-white px-3.5 py-2 text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">
            View
          </a>
        @endif
      </div>
    </div>

    <div class="mt-4 h-px bg-slate-200/70"></div>

    {{-- Two columns --}}
    <div class="px-6 py-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
           {{-- Student --}}
      <section class="rounded-2xl ring-1 ring-slate-200 bg-white">
        <header class="px-4 py-2.5 bg-slate-50/60 rounded-t-2xl">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Student</h3>
        </header>
        <div class="p-4 space-y-3">
          <div>
            <div class="text-[13px] uppercase tracking-wide text-slate-500">Student Name</div>
            <div class="font-medium text-slate-900">
              {{ $appointment->student_name ?: '—' }}
            </div>
          </div>

          <div>
            <div class="text-[13px] uppercase tracking-wide text-slate-500">Course / Year</div>
            <div class="text-sm text-slate-700">
              {{ $appointment->student_program_year ?: '—' }}
            </div>
          </div>

          <div>
            <div class="text-[13px] uppercase tracking-wide text-slate-500">Email</div>
            <div class="text-sm text-slate-700">
              {{ $appointment->student_email ?: '—' }}
            </div>
          </div>
        </div>
      </section>


      {{-- Timing --}}
      <section class="rounded-2xl ring-1 ring-slate-200 bg-white">
        <header class="px-4 py-2.5 bg-slate-50/60 rounded-t-2xl">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Appointment Timing</h3>
        </header>
        <div class="p-4 space-y-6">
          <div class="flex gap-3">
            <div class="shrink-0 mt-0.5">
              <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7 2a1 1 0 0 0-1 1v1H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1z"/><path d="M21 10H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z"/>
              </svg>
            </div>
            <div>
              <div class="text-[13px] uppercase tracking-wide text-slate-500">Booked On</div>
              <div class="font-medium text-slate-900">{{ $bookedAt ? $bookedAt->format('F d, Y') : '—' }}</div>
              @if($bookedAt)
                <div class="text-sm text-slate-600">{{ $bookedAt->format('g:i A') }}</div>
              @endif
            </div>
          </div>

          <div class="flex gap-3">
            <div class="shrink-0 mt-0.5">
              <svg class="w-5 h-5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 10.414V7h-2v6h5v-2h-3z"/>
              </svg>
            </div>
            <div>
              <div class="text-[13px] uppercase tracking-wide text-slate-500">Scheduled For</div>
              <div class="font-medium text-slate-900">{{ $dt->format('F d, Y') }}</div>
              <div class="text-sm text-slate-600">{{ $dt->format('g:i A') }}</div>
            </div>
          </div>
        </div>
      </section>
    </div>

    {{-- =========================
         CASE NOTE FORM
       ========================= --}}
    <div class="px-6 pb-6">
      <div class="rounded-2xl bg-indigo-50/40 ring-1 ring-indigo-200/70 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3">
          <div class="text-xs font-semibold tracking-wide uppercase text-slate-700">Counselor Case Note Form</div>
          @isset($caseNote)
            <div class="text-xs text-slate-500">
              Last saved {{ \Carbon\Carbon::parse($caseNote->updated_at)->format('M d, Y g:i A') }}
            </div>
          @endisset
        </div>

        <div class="px-4 pb-4">
          @if ($status === 'completed')
            @php
              $dateValue = old(
                'case_note.date',
                isset($caseNote->note_date) ? \Carbon\Carbon::parse($caseNote->note_date)->format('Y-m-d') : now()->format('Y-m-d')
              );
              $ppInit   = mb_strlen(old('case_note.presenting_problem', $caseNote->presenting_problem ?? ''));
              $obsInit  = mb_strlen(old('case_note.observations',       $caseNote->observations ?? ''));
              $intInit  = mb_strlen(old('case_note.interventions',      $caseNote->interventions ?? ''));
              $respInit = mb_strlen(old('case_note.response',           $caseNote->response ?? ''));
              $planInit = mb_strlen(old('case_note.plan_followup',      $caseNote->plan_followup ?? ''));
            @endphp

            <form id="case-note-form" method="POST"
                  action="{{ route('counselor.appointments.case_note.store', $appointment->id) }}"
                  class="space-y-5" novalidate>
              @csrf
              <input type="hidden" name="type" value="case_note">

              {{-- Header fields --}}
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Student Name</label>
                  <input type="text" name="case_note[student_name]" required
                         value="{{ old('case_note.student_name', $caseNote->student_name ?? $appointment->student_name) }}"
                         class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3">
                  @error('case_note.student_name')
                    <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.student_name">{{ $message }}</p>
                  @enderror
                  <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.student_name"></p>
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                  <input type="date" name="case_note[date]" required
                         value="{{ $dateValue }}"
                         class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3">
                  @error('case_note.date')
                    <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.date">{{ $message }}</p>
                  @enderror
                  <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.date"></p>
                </div>

               <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Program &amp; Year</label>
              <input type="text" name="case_note[program_year]"
                    value="{{ old('case_note.program_year', $caseNote->program_year ?? $appointment->student_program_year) }}"
                    class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                    placeholder="e.g., BSIT - 3rd Year">
                </div>


                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
                  <input type="text" name="case_note[address]"
                         value="{{ old('case_note.address', $caseNote->address ?? '') }}"
                         class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                         placeholder="Home address">
                </div>
              </div>

              {{-- I. Presenting Problem --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">I. Presenting Problem</label>
                <div class="relative pb-6">
                  <textarea id="presenting_problem"
                            name="case_note[presenting_problem]"
                            rows="4" maxlength="4000" required
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000"
                            placeholder="Describe the client's main concerns...">{{ old('case_note.presenting_problem', $caseNote->presenting_problem ?? '') }}</textarea>

                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400">
                    <span class="js-count">{{ $ppInit }}</span>/4000
                  </div>

                  @error('case_note.presenting_problem')
                    <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.presenting_problem">{{ $message }}</p>
                  @enderror

                  {{-- client error fallback id --}}
                  <p id="pp_err"
                     class="mt-1 text-xs text-rose-600 hidden client-error"
                     data-client-error-for="case_note.presenting_problem"></p>
                </div>
              </div>

              {{-- II. Observations --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">II. Observations</label>
                <div class="relative pb-6">
                  <textarea id="observations" name="case_note[observations]" rows="4" maxlength="4000" required
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000"
                            placeholder="Counselor's observations (appearance, behavior, affect)...">{{ old('case_note.observations', $caseNote->observations ?? '') }}</textarea>

                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400">
                    <span class="js-count">{{ $obsInit }}</span>/4000
                  </div>

                  @error('case_note.observations')
                    <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.observations">{{ $message }}</p>
                  @enderror
                  <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.observations"></p>
                </div>
              </div>

              {{-- III. Interventions --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">III. Interventions / Counselor’s Actions</label>
                <div class="relative pb-6">
                  <textarea id="interventions" name="case_note[interventions]" rows="4" maxlength="4000" required
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000"
                            placeholder="What was done during the session (e.g., CBT techniques, grounding)?">{{ old('case_note.interventions', $caseNote->interventions ?? '') }}</textarea>

                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400">
                    <span class="js-count">{{ $intInit }}</span>/4000
                  </div>

                  @error('case_note.interventions')
                    <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.interventions">{{ $message }}</p>
                  @enderror
                  <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.interventions"></p>
                </div>
              </div>

              {{-- IV. Response --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">IV. Student’s Response / Insight</label>
                <div class="relative pb-6">
                  <textarea id="response" name="case_note[response]" rows="4" maxlength="4000" required
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000"
                            placeholder="How did the student respond? Key insights or changes observed.">{{ old('case_note.response', $caseNote->response ?? '') }}</textarea>

                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400">
                    <span class="js-count">{{ $respInit }}</span>/4000
                  </div>

                  @error('case_note.response')
                    <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.response">{{ $message }}</p>
                  @enderror
                  <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.response"></p>
                </div>
              </div>

              {{-- V. Plan / Follow-Up --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">V. Plan / Follow-Up</label>
                <div class="relative pb-6">
                  <textarea id="plan_followup" name="case_note[plan_followup]" rows="4" maxlength="4000" required
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000"
                            placeholder="Next steps, referrals, frequency of sessions, etc.">{{ old('case_note.plan_followup', $caseNote->plan_followup ?? '') }}</textarea>

                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400">
                    <span class="js-count">{{ $planInit }}</span>/4000
                  </div>

                  @error('case_note.plan_followup')
                    <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.plan_followup">{{ $message }}</p>
                  @enderror
                  <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.plan_followup"></p>
                </div>
              </div>

              {{-- VI. Emergency Safety Plan --}}
              <div class="rounded-xl border border-slate-200 p-4 bg-white">
                <div class="text-[13px] font-medium text-slate-700 mb-3">VI. Emergency Safety Plan</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Contact Person</label>
                    <input id="emergency_contact_person" type="text" name="case_note[emergency_contact_person]" required
                           value="{{ old('case_note.emergency_contact_person', $caseNote->emergency_contact_person ?? '') }}"
                           class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                           placeholder="Full name">
                    @error('case_note.emergency_contact_person')
                      <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.emergency_contact_person">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.emergency_contact_person"></p>
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Relationship</label>
                    <input id="emergency_relationship" type="text" name="case_note[emergency_relationship]" required
                           value="{{ old('case_note.emergency_relationship', $caseNote->emergency_relationship ?? '') }}"
                           class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                           placeholder="e.g., Mother">
                    @error('case_note.emergency_relationship')
                      <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.emergency_relationship">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.emergency_relationship"></p>
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Contact No.</label>
                    <input id="emergency_contact_no" type="text" name="case_note[emergency_contact_no]" required
                           value="{{ old('case_note.emergency_contact_no', $caseNote->emergency_contact_no ?? '') }}"
                           class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                           placeholder="+63 9xx xxx xxxx">
                    @error('case_note.emergency_contact_no')
                      <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.emergency_contact_no">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.emergency_contact_no"></p>
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
                    <input id="emergency_address" type="text" name="case_note[emergency_address]" required
                           value="{{ old('case_note.emergency_address', $caseNote->emergency_address ?? '') }}"
                           class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                           placeholder="Address">
                    @error('case_note.emergency_address')
                      <p class="mt-1 text-xs text-rose-600 server-error" data-error-for="case_note.emergency_address">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-rose-600 hidden client-error" data-client-error-for="case_note.emergency_address"></p>
                  </div>
                </div>
              </div>

              @error('case_note')
                <div class="text-sm text-rose-600 mt-1 server-error" data-error-for="case_note">• {{ $message }}</div>
              @enderror

              <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                  Save Case Note
                </button>
              </div>
            </form>
          @else
            <div class="bg-white rounded-lg p-3">
              <textarea rows="3" class="w-full rounded-md border-0 ring-0 bg-transparent" disabled
                        placeholder="Available after the appointment is marked Completed."></textarea>
              <div class="text-xs text-slate-500 mt-2">
                You can fill the Case Note once this appointment is <b>Completed</b>.
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- READ-ONLY SAVED NOTE --}}
    @if($caseNote)
      <div class="px-6 pb-6">
        <div class="rounded-2xl border border-slate-200 bg-white">
          <div class="flex items-center justify-between px-4 py-3">
            <div class="text-xs font-semibold tracking-wide uppercase text-slate-700">Saved Case Note</div>
            <a href="{{ route('counselor.appointments.case_note.pdf', $appointment->id) }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 bg-emerald-600 text-white px-3 py-1.5 h-9 rounded-lg hover:bg-emerald-700">
              Print / PDF
            </a>
          </div>

          <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <div class="text-slate-500">Student Name</div>
              <div class="font-medium text-slate-900">{{ $caseNote->student_name ?: '—' }}</div>
            </div>
            <div>
              <div class="text-slate-500">Date</div>
              <div class="font-medium text-slate-900">{{ $caseNote->note_date ? \Carbon\Carbon::parse($caseNote->note_date)->format('F d, Y') : '—' }}</div>
            </div>
            <div>
              <div class="text-slate-500">Program & Year</div>
              <div class="font-medium text-slate-900">{{ $caseNote->program_year ?: '—' }}</div>
            </div>
            <div>
              <div class="text-slate-500">Address</div>
              <div class="font-medium text-slate-900">{{ $caseNote->address ?: '—' }}</div>
            </div>
          </div>

          @php
            $toBr = fn($v) => nl2br(e($v ?? '—'));
          @endphp

          <div class="px-4 pb-4 space-y-4 text-sm">
            <div>
              <div class="text-slate-500 mb-1">I. Presenting Problem</div>
              <div class="whitespace-pre-line">{!! $toBr($caseNote->presenting_problem) !!}</div>
            </div>
            <div>
              <div class="text-slate-500 mb-1">II. Observations</div>
              <div class="whitespace-pre-line">{!! $toBr($caseNote->observations) !!}</div>
            </div>
            <div>
              <div class="text-slate-500 mb-1">III. Interventions / Counselor’s Actions</div>
              <div class="whitespace-pre-line">{!! $toBr($caseNote->interventions) !!}</div>
            </div>
            <div>
              <div class="text-slate-500 mb-1">IV. Student’s Response / Insight</div>
              <div class="whitespace-pre-line">{!! $toBr($caseNote->response) !!}</div>
            </div>
            <div>
              <div class="text-slate-500 mb-1">V. Plan / Follow-Up</div>
              <div class="whitespace-pre-line">{!! $toBr($caseNote->plan_followup) !!}</div>
            </div>

            <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
              <div class="text-[13px] font-medium text-slate-700 mb-2">VI. Emergency Safety Plan</div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><span class="text-slate-500">Contact Person:</span> <span class="font-medium">{{ $caseNote->emergency_contact_person ?: '—' }}</span></div>
                <div><span class="text-slate-500">Relationship:</span> <span class="font-medium">{{ $caseNote->emergency_relationship ?: '—' }}</span></div>
                <div><span class="text-slate-500">Contact No.:</span> <span class="font-medium">{{ $caseNote->emergency_contact_no ?: '—' }}</span></div>
                <div><span class="text-slate-500">Address:</span> <span class="font-medium">{{ $caseNote->emergency_address ?: '—' }}</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endif

    <div class="px-6 pb-6 border-t border-slate-200 flex items-center justify-end">
      <div class="text-xs text-slate-500">
        Status: <span class="font-medium">{{ $status === 'no_show' ? 'No Show' : ucfirst($status) }}</span>
      </div>
    </div>
  </div>
</div>
<style>
  .client-error:not(.hidden){ display:block !important; }
</style>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* (all your existing JS below stays the same) */
(function(){
  const el = document.getElementById('js-elapsed');
  if (!el) return;
  const iso = el.getAttribute('data-start');
  if (!iso) return;

  const startMs = Date.parse(iso);
  if (isNaN(startMs)) return;

  const pad = n => String(n).padStart(2, '0');

  function tick(){
    const secs = Math.max(0, Math.floor((Date.now() - startMs) / 1000));
    const h = Math.floor(secs / 3600);
    const m = Math.floor((secs % 3600) / 60);
    const s = secs % 60;
    el.textContent = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
  }

  tick();
  window.__lumiElapsedTimer && clearInterval(window.__lumiElapsedTimer);
  window.__lumiElapsedTimer = setInterval(tick, 1000);
})();
/* =========================================================
   2) ACTION CONFIRMS
========================================================= */
function askAction(e, form, action){
  e.preventDefault();
  const btn = form.querySelector('button[type="submit"]');
  const disable = v => { if(btn){ btn.disabled=v; btn.classList.toggle('opacity-50',v);} };
  const cfg = {
    confirm:{ title:'Confirm Appointment?', text:'Are you sure?', icon:'question', confirmButtonColor:'#2563eb' },
    start:{   title:'Start Session?', text:'Status will change to Ongoing.', icon:'info',    confirmButtonColor:'#4f46e5' },
    done:{    title:'End Session?',   text:'This will mark the appointment as Completed.', icon:'success', confirmButtonColor:'#059669' },
    no_show:{ title:'Mark as No-Show?', text:'This marks the student as a no-show.',       icon:'warning', confirmButtonColor:'#dc2626' }
  }[action] || { title:'Are you sure?', text:'', icon:'info', confirmButtonColor:'#2563eb' };
  Swal.fire({ ...cfg, showCancelButton:true, confirmButtonText:'Yes, proceed', cancelButtonText:'No, keep it',
              cancelButtonColor:'#6b7280', reverseButtons:true, focusCancel:true })
      .then(res => { if (res.isConfirmed){ disable(true); form.submit(); } });
  return false;
}
</script>
@endpush
@endsection
