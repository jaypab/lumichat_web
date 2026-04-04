@extends('layouts.counselor')
@section('title', 'Appointment APN-'.str_pad($appointment->id,3,'0',STR_PAD_LEFT))
@section('page_title', 'Appointment Details')

@php
  use Carbon\Carbon;
  use Illuminate\Support\Facades\DB;

  $dt       = Carbon::parse($appointment->scheduled_at);
  $now      = Carbon::now();
  $bookedAt = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;

  $sourceRaw = strtolower((string)($appointment->appointment_source ?? ''));
  $isWalkIn  = in_array($sourceRaw, ['walk_in', 'walk-in', 'walk in'], true);

  $hasStarted = $now->gte($dt);
  $rawStatus  = strtolower((string)$appointment->status);
  $status     = !empty($isHistory) ? 'reassigned' : $rawStatus;
  $isOngoing  = ($status === 'ongoing');

  $when = $now->isBefore($dt)
    ? 'Starts in '.$dt->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
    : 'Started '.$dt->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW]);

  $canConfirm  = ($status === 'pending');
  $canStart    = ($status === 'confirmed') && $now->gte($dt->copy()->subMinutes(10));
  $canDone     = (in_array($status, ['confirmed','ongoing'], true) && $hasStarted);
  $canFollowUp = in_array($status, ['completed', 'no_show'], true);

  $doneTitle = !$hasStarted
      ? 'You can only end the session after the scheduled start time'
      : 'End this session';

  $badgeMap = [
    'pending'    => ['bg'=>'bg-amber-50',   'text'=>'text-amber-700',   'ring'=>'ring-amber-200',   'dot'=>'bg-amber-500'],
    'confirmed'  => ['bg'=>'bg-blue-50',    'text'=>'text-blue-700',    'ring'=>'ring-blue-200',    'dot'=>'bg-blue-500'],
    'canceled'   => ['bg'=>'bg-rose-50',    'text'=>'text-rose-700',    'ring'=>'ring-rose-200',    'dot'=>'bg-rose-500'],
    'completed'  => ['bg'=>'bg-emerald-50', 'text'=>'text-emerald-700', 'ring'=>'ring-emerald-200', 'dot'=>'bg-emerald-500'],
    'no_show'    => ['bg'=>'bg-rose-50',    'text'=>'text-rose-700',    'ring'=>'ring-rose-200',    'dot'=>'bg-rose-500'],
    'ongoing'    => ['bg'=>'bg-indigo-50',  'text'=>'text-indigo-700',  'ring'=>'ring-indigo-200',  'dot'=>'bg-indigo-600'],
    'reassigned' => ['bg'=>'bg-slate-100',  'text'=>'text-slate-700',   'ring'=>'ring-slate-200',   'dot'=>'bg-slate-400'],
  ];
  $badge = $badgeMap[$status] ?? ['bg'=>'bg-slate-100','text'=>'text-slate-700','ring'=>'ring-slate-200','dot'=>'bg-slate-400'];
  $statusLabel = $status === 'no_show' ? 'No Show' : ucfirst($status);

  $followUp = DB::table('tbl_appointments')
      ->where('parent_id', $appointment->id)
      ->orderByDesc('scheduled_at')
      ->first();
  $hasFollowUp = $followUp !== null;

  // Student avatar
  $studentAvatarUrl = null;
  if (!empty($appointment->profile_picture)) {
      $studentAvatarUrl = asset('storage/' . $appointment->profile_picture);
  } elseif (!empty($appointment->student_id)) {
      $studentAvatarUrl = optional(\App\Models\User::find($appointment->student_id))->avatar_url;
  }
  $studentName = $appointment->student_name ?: '—';
  $initParts = collect(explode(' ', $studentName))->map(fn($n) => $n[0] ?? '')->take(2)->join('');
  $studentInit = mb_strtoupper($initParts);

  $elapsedStartIso = Carbon::parse(
      $isOngoing ? ($appointment->updated_at ?? $appointment->scheduled_at) : $appointment->scheduled_at
  )->toIso8601String();
@endphp

@section('content')
<div class="w-full space-y-5">

  {{-- ─── Page Header ─── --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
          APN-{{ str_pad($appointment->id, 3, '0', STR_PAD_LEFT) }}
        </h1>
        {{-- Status pill --}}
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold ring-1 ring-inset {{ $badge['bg'] }} {{ $badge['text'] }} {{ $badge['ring'] }}">
          <span class="inline-block w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>
          {{ $statusLabel }}
        </span>
        @if($isWalkIn)
          <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-200">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            Walk-in
          </span>
        @endif
        @if($isOngoing)
          <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-indigo-50 ring-1 ring-indigo-200 text-indigo-700">
            <svg class="w-3.5 h-3.5 animate-pulse" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20zm0-2a8 8 0 100-16 8 8 0 010 16z"/>
            </svg>
            <span id="js-elapsed" data-start="{{ $elapsedStartIso }}">00:00</span>
          </span>
        @endif
      </div>
      <p class="text-slate-500 text-sm font-medium flex items-center gap-1.5">
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20z"/>
        </svg>
        {{ $when }}
      </p>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <a href="{{ route('counselor.appointments.index') }}"
         class="inline-flex items-center gap-2 h-10 px-4 rounded-xl bg-white text-slate-700 border border-slate-200 text-sm font-bold shadow-sm hover:bg-slate-50 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
        </svg>
        Back
      </a>
      <a href="{{ route('counselor.appointments.export.show.pdf', $appointment->id) }}"
         target="_blank" rel="noopener"
         class="group inline-flex items-center gap-2 h-10 px-4 rounded-xl bg-emerald-600 text-white text-sm font-bold shadow-sm hover:bg-emerald-700 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Download PDF
      </a>
    </div>
  </header>

  {{-- ─── Action Buttons ─── --}}
  @if(!$isWalkIn)

  {{-- Instruction Panel --}}
  <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 dark:bg-indigo-900/20 dark:border-indigo-800/50 px-5 py-4 transition-colors duration-300">
    <div class="flex items-start gap-3">
      <div class="mt-0.5 w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-800/50 flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2a10 10 0 110 20A10 10 0 0112 2zm0 9a1 1 0 00-1 1v4a1 1 0 002 0v-4a1 1 0 00-1-1zm0-4a1.25 1.25 0 110 2.5A1.25 1.25 0 0112 7z"/>
        </svg>
      </div>
      <div>
        <p class="text-sm font-bold text-indigo-800 dark:text-indigo-200 mb-1">How Quick Actions Work</p>
        <ul class="space-y-1 text-xs text-indigo-700/80 dark:text-indigo-300/80 leading-relaxed">
          <li class="flex items-start gap-2">
            <svg class="w-3.5 h-3.5 mt-0.5 text-indigo-400 dark:text-indigo-500 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            <span><span class="font-semibold text-indigo-900 dark:text-indigo-100">Start Session</span> — Available 10 minutes before or after the scheduled time. Changes status to <em>Ongoing</em>.</span>
          </li>
          <li class="flex items-start gap-2">
            <svg class="w-3.5 h-3.5 mt-0.5 text-indigo-400 dark:text-indigo-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span><span class="font-semibold text-indigo-900 dark:text-indigo-100">Done / End Session</span> — Only available after the appointment's scheduled start time. Marks it as <em>Completed</em>.</span>
          </li>
          <li class="flex items-start gap-2">
            <svg class="w-3.5 h-3.5 mt-0.5 text-indigo-400 dark:text-indigo-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 5.636l-12.728 12.728M5.636 5.636l12.728 12.728"/></svg>
            <span><span class="font-semibold text-indigo-900 dark:text-indigo-100">Mark as No-Show</span> — Use this if the student failed to attend a <em>Pending</em> or <em>Confirmed</em> appointment.</span>
          </li>
          <li class="flex items-start gap-2">
            <svg class="w-3.5 h-3.5 mt-0.5 text-indigo-400 dark:text-indigo-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
            <span><span class="font-semibold text-indigo-900 dark:text-indigo-100">Create Follow-up</span> — Appears after the appointment is <em>Completed</em> or marked <em>No-Show</em>. Books a new appointment linked to this one.</span>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-2 p-4 bg-white dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-300">
    <span class="text-xs font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500 mr-2">Quick Actions</span>

    {{-- Start Session --}}
    @php
      $startDisabledReason = $isOngoing
          ? 'Session is already ongoing.'
          : ($status !== 'confirmed' ? 'Appointment must be Confirmed first.' : 'You can start within 10 minutes of the scheduled time.');
    @endphp
    <div class="lumi-tooltip-wrap relative {{ ($canStart && !$isOngoing) ? '' : 'cursor-not-allowed' }}">
      <form method="POST" action="{{ route('counselor.appointments.status', $appointment->id) }}"
            @if(!$canStart || $isOngoing) onsubmit="return false" @else onsubmit="return askAction(event, this, 'start')" @endif
            class="{{ ($canStart && !$isOngoing) ? '' : 'pointer-events-none' }}">
        @csrf @method('PATCH')
        <input type="hidden" name="action" value="start">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all duration-200 active:scale-[.98]
                       {{ ($canStart && !$isOngoing) ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm' : 'bg-indigo-100 text-indigo-400 cursor-not-allowed' }}">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
          Start Session
        </button>
      </form>
      @if(!$canStart || $isOngoing)
        <div class="lumi-tooltip">{{ $startDisabledReason }}</div>
      @endif
    </div>

    {{-- End Session --}}
    @php
      $doneDisabledReason = !$hasStarted
          ? 'Available only after the scheduled start time has passed.'
          : (!in_array($status, ['confirmed','ongoing']) ? 'Session must be Confirmed or Ongoing to end.' : $doneTitle);
    @endphp
    <div class="lumi-tooltip-wrap relative {{ $canDone ? '' : 'cursor-not-allowed' }}">
      <form method="POST" action="{{ route('counselor.appointments.status', $appointment->id) }}"
            @if(!$canDone) onsubmit="return false" @else onsubmit="return askAction(event, this, 'done')" @endif
            class="{{ $canDone ? '' : 'pointer-events-none' }}">
        @csrf @method('PATCH')
        <input type="hidden" name="action" value="done">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all duration-200 active:scale-[.98]
                       {{ $canDone ? 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm' : 'bg-emerald-100 text-emerald-400 cursor-not-allowed' }}">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          {{ $isOngoing ? 'End Session' : 'Done' }}
        </button>
      </form>
      @if(!$canDone)
        <div class="lumi-tooltip">{{ $doneDisabledReason }}</div>
      @endif
    </div>

    {{-- No-Show --}}
    @if(in_array($status, ['pending','confirmed']))
      <form method="POST" action="{{ route('counselor.appointments.no_show', $appointment->id) }}"
            onsubmit="return askAction(event, this, 'no_show')">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold bg-rose-600 text-white hover:bg-rose-700 shadow-sm transition-all duration-200 active:scale-[.98]">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 5.636l-12.728 12.728M5.636 5.636l12.728 12.728"/>
          </svg>
          Mark as No-Show
        </button>
      </form>
    @endif

    {{-- Follow-up --}}
    @if ($canFollowUp && !$hasFollowUp)
      <a href="{{ route('counselor.appointments.follow.form', $appointment->id) }}"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold bg-white text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50 shadow-sm transition-all duration-200 active:scale-[.98]">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/>
        </svg>
        Create Follow-up
      </a>
    @elseif ($hasFollowUp)
      <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
        Follow-up — {{ Carbon::parse($followUp->scheduled_at)->format('M d, Y g:i A') }}
      </div>
      <a href="{{ route('counselor.appointments.show', $followUp->id) }}"
         class="inline-flex items-center px-3.5 py-2 rounded-xl text-sm font-bold bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 shadow-sm transition-all">
        View
      </a>
    @endif
  </div>
  @else
    <div class="flex items-center gap-3 px-4 py-3 bg-amber-50 rounded-2xl border border-amber-200">
      <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
      <p class="text-sm text-amber-800 font-medium">Walk-in session — status is managed from the Walk-in Case Note page.</p>
    </div>
  @endif

  {{-- ─── Info Grid ─── --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Student Card --}}
    <div class="lg:col-span-1 bg-white dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-colors duration-300">
      <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
        <svg class="w-4 h-4 text-indigo-500" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5.33 0-8 2.67-8 4v2h16v-2c0-1.33-2.67-4-8-4z"/>
        </svg>
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-600 dark:text-slate-400">Student</h3>
      </div>
      <div class="p-5">
        {{-- Avatar + Name --}}
        <div class="flex items-center gap-4 mb-5 pb-5 border-b border-slate-100">
          @if($studentAvatarUrl)
            <img src="{{ $studentAvatarUrl }}" alt="{{ $studentName }}"
                 class="w-14 h-14 rounded-2xl object-cover shadow-sm ring-2 ring-indigo-100">
          @else
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-black text-lg ring-2 ring-indigo-100 shadow-sm uppercase">
              {{ $studentInit }}
            </div>
          @endif
          <div>
            <div class="font-black text-slate-900 text-base leading-tight">{{ $studentName }}</div>
            @if($appointment->student_email)
              <div class="text-slate-500 text-xs mt-0.5">{{ $appointment->student_email }}</div>
            @endif
            @if($isWalkIn)
              <span class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span> WALK-IN
              </span>
            @endif
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <div class="text-[10px] uppercase tracking-widest text-slate-400 font-bold mb-0.5">Course / Year</div>
            <div class="text-sm font-semibold text-slate-800">{{ $appointment->student_program_year ?: '—' }}</div>
          </div>
          <div>
            <div class="text-[10px] uppercase tracking-widest text-slate-400 font-bold mb-0.5">Email Address</div>
            <div class="text-sm font-semibold text-slate-800 break-all">{{ $appointment->student_email ?: '—' }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Timing Card --}}
    <div class="lg:col-span-2 bg-white dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-colors duration-300">
      <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
        <svg class="w-4 h-4 text-indigo-500" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20zm0-2a8 8 0 100-16 8 8 0 010 16z"/>
        </svg>
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-600 dark:text-slate-400">Appointment Timing</h3>
      </div>
      <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-6">
        {{-- Booked On --}}
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0 ring-1 ring-blue-100">
            <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
              <path d="M7 2a1 1 0 0 0-1 1v1H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1z"/><path d="M21 10H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z"/>
            </svg>
          </div>
          <div>
            <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-0.5">Booked On</div>
            <div class="text-base font-black text-slate-900">{{ $bookedAt ? $bookedAt->format('F d, Y') : '—' }}</div>
            @if($bookedAt)
              <div class="text-sm text-slate-500">{{ $bookedAt->format('g:i A') }}</div>
            @endif
          </div>
        </div>

        {{-- Scheduled For --}}
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0 ring-1 ring-emerald-100">
            <svg class="w-5 h-5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 10.414V7h-2v6h5v-2h-3z"/>
            </svg>
          </div>
          <div>
            <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-0.5">Scheduled For</div>
            <div class="text-base font-black text-slate-900">{{ $dt->format('F d, Y') }}</div>
            <div class="text-sm text-slate-500">{{ $dt->format('g:i A') }}</div>
          </div>
        </div>

        {{-- Status --}}
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-xl {{ $badge['bg'] }} flex items-center justify-center shrink-0 ring-1 {{ $badge['ring'] }}">
            <span class="inline-block w-3 h-3 rounded-full {{ $badge['dot'] }}"></span>
          </div>
          <div>
            <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-0.5">Current Status</div>
            <div class="text-base font-black {{ $badge['text'] }}">{{ $statusLabel }}</div>
            <div class="text-sm text-slate-500">{{ $when }}</div>
          </div>
        </div>

        {{-- Appointment ID --}}
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center shrink-0 ring-1 ring-slate-200">
            <svg class="w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6M9 16h6M7 8h.01M17 8h-6M3 7.2v9.6c0 1.12 0 1.68.218 2.108a2 2 0 0 0 .874.874C4.52 20 5.08 20 6.2 20h11.6c1.12 0 1.68 0 2.108-.218a2 2 0 0 0 .874-.874C21 18.48 21 17.92 21 16.8V7.2c0-1.12 0-1.68-.218-2.108a2 2 0 0 0-.874-.874C19.48 4 18.92 4 17.8 4H6.2c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.874.874C3 5.52 3 6.08 3 7.2z"/>
            </svg>
          </div>
          <div>
            <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-0.5">Reference No.</div>
            <div class="text-base font-black text-slate-900">APN-{{ str_pad($appointment->id, 3, '0', STR_PAD_LEFT) }}</div>
            <div class="text-sm text-slate-500">Appointment ID #{{ $appointment->id }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ─── Case Note Form ─── --}}
  <div class="bg-white dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-colors duration-300">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-600 dark:text-slate-400">Counselor Case Note Form</h3>
      </div>
      @isset($caseNote)
        <div class="text-xs text-slate-500 flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"/><path d="M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>
          Last saved {{ Carbon::parse($caseNote->updated_at)->format('M d, Y g:i A') }}
        </div>
      @endisset
    </div>

    <div class="p-5">
      @if ($status === 'completed')
        @php
          $dateValue = old('case_note.date', isset($caseNote->note_date) ? Carbon::parse($caseNote->note_date)->format('Y-m-d') : now()->format('Y-m-d'));
          $ppInit   = mb_strlen(old('case_note.presenting_problem', $caseNote->presenting_problem ?? ''));
          $obsInit  = mb_strlen(old('case_note.observations',       $caseNote->observations ?? ''));
          $intInit  = mb_strlen(old('case_note.interventions',      $caseNote->interventions ?? ''));
          $respInit = mb_strlen(old('case_note.response',           $caseNote->response ?? ''));
          $planInit = mb_strlen(old('case_note.plan_followup',      $caseNote->plan_followup ?? ''));
        @endphp
        <form id="case-note-form" method="POST"
              action="{{ route('counselor.appointments.case_note.store', $appointment->id) }}"
              class="space-y-6" novalidate>
          @csrf
          <input type="hidden" name="type" value="case_note">

          {{-- Header Fields --}}
          <div>
            <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-3">Student Information</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Student Name <span class="text-rose-500">*</span></label>
                <input type="text" name="case_note[student_name]" required
                       value="{{ old('case_note.student_name', $caseNote->student_name ?? $appointment->student_name) }}"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent px-4 py-2.5 text-sm transition-all">
                @error('case_note.student_name')
                  <p class="mt-1 text-xs text-rose-600 server-error">{{ $message }}</p>
                @enderror
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Date <span class="text-rose-500">*</span></label>
                <input type="date" name="case_note[date]" required value="{{ $dateValue }}"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent px-4 py-2.5 text-sm transition-all">
                @error('case_note.date')
                  <p class="mt-1 text-xs text-rose-600 server-error">{{ $message }}</p>
                @enderror
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Program &amp; Year</label>
                <input type="text" name="case_note[program_year]"
                       value="{{ old('case_note.program_year', $caseNote->program_year ?? $appointment->student_program_year) }}"
                       placeholder="e.g., BSIT - 3rd Year"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent px-4 py-2.5 text-sm transition-all">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Address</label>
                <input type="text" name="case_note[address]"
                       value="{{ old('case_note.address', $caseNote->address ?? '') }}"
                       placeholder="Home address"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent px-4 py-2.5 text-sm transition-all">
              </div>
            </div>
          </div>

          <div class="h-px bg-slate-100"></div>

          {{-- Case Note Sections --}}
          <div class="space-y-5">
            <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Session Notes</div>

            @foreach([
              ['id'=>'presenting_problem','name'=>'case_note[presenting_problem]','label'=>'I. Presenting Problem','placeholder'=>'Describe the client\'s main concerns...','init'=>$ppInit,'error'=>'case_note.presenting_problem'],
              ['id'=>'observations','name'=>'case_note[observations]','label'=>'II. Observations','placeholder'=>'Counselor\'s observations (appearance, behavior, affect)...','init'=>$obsInit,'error'=>'case_note.observations'],
              ['id'=>'interventions','name'=>'case_note[interventions]','label'=>'III. Interventions / Counselor\'s Actions','placeholder'=>'What was done during the session?','init'=>$intInit,'error'=>'case_note.interventions'],
              ['id'=>'response','name'=>'case_note[response]','label'=>'IV. Student\'s Response / Insight','placeholder'=>'How did the student respond? Key insights or changes observed.','init'=>$respInit,'error'=>'case_note.response'],
              ['id'=>'plan_followup','name'=>'case_note[plan_followup]','label'=>'V. Plan / Follow-Up','placeholder'=>'Next steps, referrals, frequency of sessions, etc.','init'=>$planInit,'error'=>'case_note.plan_followup'],
            ] as $field)
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1.5">{{ $field['label'] }} <span class="text-rose-500">*</span></label>
              <div class="relative">
                <textarea id="{{ $field['id'] }}" name="{{ $field['name'] }}" rows="4" maxlength="4000" required
                          class="w-full rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent px-4 py-3 text-sm transition-all js-counted resize-none pb-7"
                          data-max="4000"
                          placeholder="{{ $field['placeholder'] }}">{{ old($field['name'], isset($caseNote) ? ($caseNote->{$field['id']} ?? '') : '') }}</textarea>
                <div class="absolute right-3 bottom-2 text-[11px] text-slate-400">
                  <span class="js-count">{{ $field['init'] }}</span>/4000
                </div>
              </div>
              @error($field['error'])
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
              @enderror
            </div>
            @endforeach
          </div>

          <div class="h-px bg-slate-100"></div>

          {{-- Emergency Safety Plan --}}
          <div>
            <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-3">VI. Emergency Safety Plan</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-rose-50/40 rounded-xl border border-rose-100">
              @foreach([
                ['id'=>'emergency_contact_person','label'=>'Contact Person','placeholder'=>'Full name'],
                ['id'=>'emergency_relationship','label'=>'Relationship','placeholder'=>'e.g., Mother'],
                ['id'=>'emergency_contact_no','label'=>'Contact No.','placeholder'=>'+63 9xx xxx xxxx'],
                ['id'=>'emergency_address','label'=>'Address','placeholder'=>'Address'],
              ] as $ef)
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">{{ $ef['label'] }} <span class="text-rose-500">*</span></label>
                <input id="{{ $ef['id'] }}" type="text" name="case_note[{{ $ef['id'] }}]" required
                       value="{{ old('case_note.'.$ef['id'], $caseNote->{$ef['id']} ?? '') }}"
                       placeholder="{{ $ef['placeholder'] }}"
                       class="w-full rounded-xl border border-rose-100 bg-white focus:ring-2 focus:ring-rose-400 focus:border-transparent px-4 py-2.5 text-sm transition-all">
                @error('case_note.'.$ef['id'])
                  <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
              </div>
              @endforeach
            </div>
          </div>

          @error('case_note')
            <div class="text-sm text-rose-600 bg-rose-50 px-4 py-3 rounded-xl border border-rose-100">{{ $message }}</div>
          @enderror

          <div class="flex justify-end pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold shadow-sm hover:bg-indigo-700 active:scale-[.98] transition-all duration-200">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              Save Case Note
            </button>
          </div>
        </form>

      @else
        <div class="flex flex-col items-center justify-center py-10 text-center">
          <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
          </div>
          <p class="text-sm font-semibold text-slate-700">Case Note Unavailable</p>
          <p class="text-sm text-slate-500 mt-1">The case note form will unlock once this appointment is marked <span class="font-bold text-emerald-600">Completed</span>.</p>
        </div>
      @endif
    </div>
  </div>

  {{-- ─── Read-Only Saved Note ─── --}}
  @if($caseNote)
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-600">Saved Case Note</h3>
      </div>
      <a href="{{ route('counselor.appointments.case_note.pdf', $appointment->id) }}"
         target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-sm active:scale-[.98] transition-all">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Print / PDF
      </a>
    </div>

    <div class="p-5 space-y-5">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach([
          ['label'=>'Student Name','value'=>$caseNote->student_name ?: '—'],
          ['label'=>'Date','value'=>$caseNote->note_date ? Carbon::parse($caseNote->note_date)->format('F d, Y') : '—'],
          ['label'=>'Program & Year','value'=>$caseNote->program_year ?: '—'],
          ['label'=>'Address','value'=>$caseNote->address ?: '—'],
        ] as $item)
        <div class="bg-slate-50 rounded-xl px-4 py-3">
          <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-0.5">{{ $item['label'] }}</div>
          <div class="text-sm font-semibold text-slate-900">{{ $item['value'] }}</div>
        </div>
        @endforeach
      </div>

      @php $toBr = fn($v) => nl2br(e($v ?? '—')); @endphp

      <div class="space-y-4">
        @foreach([
          ['label'=>'I. Presenting Problem','value'=>$caseNote->presenting_problem],
          ['label'=>'II. Observations','value'=>$caseNote->observations],
          ['label'=>'III. Interventions / Counselor\'s Actions','value'=>$caseNote->interventions],
          ['label'=>'IV. Student\'s Response / Insight','value'=>$caseNote->response],
          ['label'=>'V. Plan / Follow-Up','value'=>$caseNote->plan_followup],
        ] as $section)
        <div class="rounded-xl border border-slate-100 p-4">
          <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-2">{{ $section['label'] }}</div>
          <div class="text-sm text-slate-800 whitespace-pre-line leading-relaxed">{!! $toBr($section['value']) !!}</div>
        </div>
        @endforeach
      </div>

      <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4">
        <div class="text-[10px] uppercase tracking-widest font-bold text-rose-400 mb-3">VI. Emergency Safety Plan</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div><span class="text-slate-500">Contact Person: </span><span class="font-semibold text-slate-900">{{ $caseNote->emergency_contact_person ?: '—' }}</span></div>
          <div><span class="text-slate-500">Relationship: </span><span class="font-semibold text-slate-900">{{ $caseNote->emergency_relationship ?: '—' }}</span></div>
          <div><span class="text-slate-500">Contact No.: </span><span class="font-semibold text-slate-900">{{ $caseNote->emergency_contact_no ?: '—' }}</span></div>
          <div><span class="text-slate-500">Address: </span><span class="font-semibold text-slate-900">{{ $caseNote->emergency_address ?: '—' }}</span></div>
        </div>
      </div>
    </div>
  </div>
  @endif

</div>

<style>
  .client-error:not(.hidden){ display:block !important; }

  /* ── Disabled button tooltips ── */
  .lumi-tooltip-wrap { position: relative; display: inline-flex; }
  .lumi-tooltip {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    background: #1e293b;
    color: #f1f5f9;
    font-size: 11px;
    font-weight: 500;
    line-height: 1.4;
    padding: 6px 10px;
    border-radius: 8px;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.15s ease, transform 0.15s ease;
    transform: translateX(-50%) translateY(4px);
    z-index: 50;
    box-shadow: 0 4px 12px rgba(0,0,0,.18);
    max-width: 320px;
    white-space: nowrap;
    text-align: center;
  }
  .lumi-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1e293b;
  }
  .lumi-tooltip-wrap:hover .lumi-tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }
</style>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@if(session('swal'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const data = @json(session('swal'));
    Swal.fire({ icon: data.icon||'success', title: data.title||'Success', text: data.text||'', timer: data.timer||2200, showConfirmButton: data.showConfirmButton??false });
});
</script>
@endif
<script>
(function(){
  const el = document.getElementById('js-elapsed');
  if (!el) return;
  const startMs = Date.parse(el.getAttribute('data-start'));
  if (isNaN(startMs)) return;
  const pad = n => String(n).padStart(2,'0');
  function tick(){
    const secs = Math.max(0, Math.floor((Date.now()-startMs)/1000));
    const h=Math.floor(secs/3600), m=Math.floor((secs%3600)/60), s=secs%60;
    el.textContent=(h>0?pad(h)+':':'')+pad(m)+':'+pad(s);
  }
  tick();
  window.__lumiElapsedTimer && clearInterval(window.__lumiElapsedTimer);
  window.__lumiElapsedTimer = setInterval(tick,1000);
})();

function askAction(e, form, action){
  e.preventDefault();
  const btn=form.querySelector('button[type="submit"]');
  const disable=v=>{if(btn){btn.disabled=v;btn.classList.toggle('opacity-50',v);}};
  const cfg={
    confirm:{ title:'Confirm Appointment?', text:'Are you sure?', icon:'question', confirmButtonColor:'#2563eb' },
    start:{   title:'Start Session?', text:'Status will change to Ongoing.', icon:'info', confirmButtonColor:'#4f46e5' },
    done:{    title:'End Session?', text:'This will mark the appointment as Completed.', icon:'success', confirmButtonColor:'#059669' },
    no_show:{ title:'Mark as No-Show?', text:'This marks the student as a no-show.', icon:'warning', confirmButtonColor:'#dc2626' }
  }[action]||{title:'Are you sure?',text:'',icon:'info',confirmButtonColor:'#2563eb'};
  Swal.fire({...cfg,showCancelButton:true,confirmButtonText:'Yes, proceed',cancelButtonText:'No, keep it',
             cancelButtonColor:'#6b7280',reverseButtons:true,focusCancel:true})
      .then(res=>{if(res.isConfirmed){disable(true);form.submit();}});
  return false;
}

// Character counters
document.querySelectorAll('.js-counted').forEach(ta=>{
  const max=parseInt(ta.dataset.max)||4000;
  const ctr=ta.closest('.relative')?.querySelector('.js-count');
  if(!ctr)return;
  ta.addEventListener('input',()=>{ ctr.textContent=ta.value.length; });
});
</script>
@endpush
@endsection
