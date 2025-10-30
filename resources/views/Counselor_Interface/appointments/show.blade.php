@extends('layouts.counselor')
@section('title', 'Appointment #'.$appointment->id)
@section('page_title', 'Appointment Details')

@php
  use Carbon\Carbon;

  $dt       = Carbon::parse($appointment->scheduled_at);
  $now      = Carbon::now();
  $bookedAt = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;

  $hasStarted = $now->gte($dt);
  $status     = strtolower((string)$appointment->status);
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
  $canFollowUp = ($status === 'completed');

  $doneTitle = !$hasStarted
      ? 'You can only end the session after the scheduled start time'
      : 'End this session';

  // chips (added ongoing)
  $badgeMap = [
    'pending'   => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'canceled'  => 'bg-rose-100 text-rose-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'no_show'   => 'bg-rose-100 text-rose-800',
    'ongoing'   => 'bg-indigo-100 text-indigo-800',
  ];
  $dotMap = [
    'pending'   => 'bg-amber-500',
    'confirmed' => 'bg-blue-500',
    'canceled'  => 'bg-rose-500',
    'completed' => 'bg-emerald-500',
    'no_show'   => 'bg-rose-500',
    'ongoing'   => 'bg-indigo-600',
  ];
  $cls = $badgeMap[$status] ?? 'bg-slate-200 text-slate-700';
  $dot = $dotMap[$status] ?? 'bg-slate-500';
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

            {{-- Live elapsed when ongoing --}}
            @if($isOngoing)
              <span class="inline-flex items-center gap-1 text-xs font-medium text-indigo-700 bg-indigo-50 ring-1 ring-indigo-200 px-2 py-1 rounded-full">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20zm0-2a8 8 0 100-16 8 8 0 010 16z"/></svg>
                <span id="js-elapsed">00:00</span>
              </span>
            @endif
          </div>
          <div class="mt-1 text-sm text-slate-500 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20zm0-2a8 8 0 100-16 8 8 0 010 16z"/>
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
        {{-- Confirm --}}
        <form method="POST"
              action="{{ route('counselor.appointments.status', $appointment->id) }}"
              onsubmit="return askAction(event, this, 'confirm')">
          @csrf
          @method('PATCH')
          <input type="hidden" name="action" value="confirm">
          <button type="submit"
                  class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  {{ $canConfirm ? '' : 'disabled' }}>
            Confirm
          </button>
        </form>

        {{-- Start Session (to Ongoing) --}}
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

        {{-- End Session (Done) --}}
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

        {{-- No-Show (pending/confirmed only; unchanged) --}}
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
        @if ($canFollowUp)
          <a href="{{ route('counselor.appointments.follow.form', $appointment->id) }}"
             class="inline-flex items-center rounded-lg bg-indigo-50 px-4 py-2 text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-100">
            Create Follow-up
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
        <div class="p-4">
          <div class="font-medium text-slate-900">{{ $appointment->student_name }}</div>
          @if(!empty($appointment->student_email))
            <div class="text-sm text-slate-600">{{ $appointment->student_email }}</div>
          @endif
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
     CASE NOTE FORM (NEW)
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
          @if($status === 'completed')
            <form method="POST"
                  action="{{ route('counselor.appointments.case_note.store', $appointment->id) }}"
                  class="space-y-5">
              @csrf   {{-- ✅ REQUIRED to avoid 419 --}}

              <input type="hidden" name="type" value="case_note">

              {{-- Header fields --}}
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Student Name</label>
                  <input type="text" name="case_note[student_name]"
                        value="{{ old('case_note.student_name', $caseNote->student_name ?? $appointment->student_name) }}"
                        class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" required>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                  <input type="date" name="case_note[date]"
                        value="{{ old('case_note.date', optional($caseNote->note_date ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                        class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" required>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Program &amp; Year</label>
                  <input type="text" name="case_note[program_year]"
                        value="{{ old('case_note.program_year', $caseNote->program_year ?? '') }}"
                        class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" placeholder="e.g., BSIT - 3rd Year">
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
                  <input type="text" name="case_note[address]"
                        value="{{ old('case_note.address', $caseNote->address ?? '') }}"
                        class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" placeholder="Home address">
                </div>
              </div>

              {{-- I. Presenting Problem --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">I. Presenting Problem</label>
                <div class="relative">
                  <textarea name="case_note[presenting_problem]" rows="4" maxlength="4000"
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000" placeholder="Describe the client's main concerns..." required>{{ old('case_note.presenting_problem', $caseNote->presenting_problem ?? '') }}</textarea>
                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400"><span class="js-count">0</span>/4000</div>
                </div>
              </div>

              {{-- II. Observations --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">II. Observations</label>
                <div class="relative">
                  <textarea name="case_note[observations]" rows="4" maxlength="4000"
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000" placeholder="Counselor's observations (appearance, behavior, affect)...">{{ old('case_note.observations', $caseNote->observations ?? '') }}</textarea>
                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400"><span class="js-count">0</span>/4000</div>
                </div>
              </div>

              {{-- III. Interventions / Counselor’s Actions --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">III. Interventions / Counselor’s Actions</label>
                <div class="relative">
                  <textarea name="case_note[interventions]" rows="4" maxlength="4000"
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000" placeholder="What was done during the session (e.g., CBT techniques, grounding)?">{{ old('case_note.interventions', $caseNote->interventions ?? '') }}</textarea>
                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400"><span class="js-count">0</span>/4000</div>
                </div>
              </div>

              {{-- IV. Student’s Response / Insight --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">IV. Student’s Response / Insight</label>
                <div class="relative">
                  <textarea name="case_note[response]" rows="4" maxlength="4000"
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000" placeholder="How did the student respond? Key insights or changes observed.">{{ old('case_note.response', $caseNote->response ?? '') }}</textarea>
                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400"><span class="js-count">0</span>/4000</div>
                </div>
              </div>

              {{-- V. Plan / Follow-Up --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">V. Plan / Follow-Up</label>
                <div class="relative">
                  <textarea name="case_note[plan_followup]" rows="4" maxlength="4000"
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000" placeholder="Next steps, referrals, frequency of sessions, etc.">{{ old('case_note.plan_followup', $caseNote->plan_followup ?? '') }}</textarea>
                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400"><span class="js-count">0</span>/4000</div>
                </div>
              </div>

              {{-- VI. Emergency Safety Plan (Optional) --}}
              <div class="rounded-xl border border-slate-200 p-4 bg-white">
                <div class="text-[13px] font-medium text-slate-700 mb-3">VI. Emergency Safety Plan (Optional)</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Contact Person</label>
                    <input type="text" name="case_note[emergency_contact_person]"
                          value="{{ old('case_note.emergency_contact_person', $caseNote->emergency_contact_person ?? '') }}"
                          class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" placeholder="Full name">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Relationship</label>
                    <input type="text" name="case_note[emergency_relationship]"
                          value="{{ old('case_note.emergency_relationship', $caseNote->emergency_relationship ?? '') }}"
                          class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" placeholder="e.g., Mother">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Contact No.</label>
                    <input type="text" name="case_note[emergency_contact_no]"
                          value="{{ old('case_note.emergency_contact_no', $caseNote->emergency_contact_no ?? '') }}"
                          class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" placeholder="+63 9xx xxx xxxx">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
                    <input type="text" name="case_note[emergency_address]"
                          value="{{ old('case_note.emergency_address', $caseNote->emergency_address ?? '') }}"
                          class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3" placeholder="Address">
                  </div>
                </div>
              </div>

              @error('case_note') <div class="text-sm text-rose-600 mt-1">• {{ $message }}</div> @enderror

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

    {{-- SHOW SAVED CASE NOTE (read-only) --}}
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function askAction(e, form, action){
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const disable = (v)=>{ if(btn){ btn.disabled=v; btn.classList.toggle('opacity-50',v);} };
    const cfg = {
      confirm:{ title:'Confirm Appointment?', text:'Are you sure?', icon:'question', confirmButtonColor:'#2563eb' },
      start:{   title:'Start Session?', text:'Status will change to Ongoing.', icon:'info', confirmButtonColor:'#4f46e5' },
      done:{    title:'End Session?', text:'This will mark the appointment as Completed.', icon:'success', confirmButtonColor:'#059669' },
      no_show:{ title:'Mark as No-Show?', text:'This marks the student as a no-show.', icon:'warning', confirmButtonColor:'#dc2626' }
    }[action] || { title:'Are you sure?', text:'', icon:'info', confirmButtonColor:'#2563eb' };

    Swal.fire({
      title: cfg.title, text: cfg.text, icon: cfg.icon,
      showCancelButton: true, confirmButtonText: 'Yes, proceed', cancelButtonText: 'No, keep it',
      confirmButtonColor: cfg.confirmButtonColor, cancelButtonColor: '#6b7280',
      reverseButtons: true, focusCancel: true
    }).then(res => { if (res.isConfirmed){ disable(true); form.submit(); } });
    return false;
  }

  // Character counters (reused for textareas)
  (function () {
    const fields = document.querySelectorAll('.js-counted');
    const clamp = (s, m) => s.length > m ? s.slice(0, m) : s;
    fields.forEach(el => {
      const max = parseInt(el.dataset.max || el.getAttribute('maxlength') || '4000', 10);
      const counter = el.parentElement.querySelector('.js-count');
      const paint = () => {
        if (el.value.length > max) el.value = clamp(el.value, max);
        if (counter) counter.textContent = el.value.length;
        const wrap = counter?.parentElement;
        if (!wrap) return;
        const ratio = el.value.length / max;
        wrap.style.color = ratio >= 1 ? '#dc2626' : (ratio >= .9 ? '#f59e0b' : '#94a3b8');
      };
      paint();
      el.addEventListener('input', paint);
      el.addEventListener('paste', () => requestAnimationFrame(paint));
    });
  })();

  // Live elapsed timer when ongoing
  @if ($isOngoing)
    (function(){
      const el = document.getElementById('js-elapsed');
      if(!el) return;
      const startTs = new Date(@json($dt->format('Y-m-d\TH:i:sP'))).getTime();
      function pad(n){ return String(n).padStart(2,'0'); }
      function tick(){
        const now = Date.now();
        let sec = Math.max(0, Math.floor((now - startTs) / 1000));
        const h = Math.floor(sec / 3600); sec %= 3600;
        const m = Math.floor(sec / 60);  sec %= 60;
        el.textContent = (h>0 ? `${pad(h)}:` : '') + `${pad(m)}:${pad(sec)}`;
      }
      tick();
      setInterval(tick, 1000);
    })();
  @endif

  @if (session('swal'))
    Swal.fire(@json(session('swal')));
  @endif
</script>
@endpush
@endsection
