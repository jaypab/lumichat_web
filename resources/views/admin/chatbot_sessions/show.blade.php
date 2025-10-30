{{-- resources/views/admin/chatbot_sessions/show.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Chatbot Details')
@section('page_title', 'Chatbot Session Summary')

@section('content')

@php
  // ==== SAFE DEFAULTS (avoid undefined notices) ====
  $sensitiveLocked             = $sensitiveLocked        ?? false;
  $highRisk                    = $highRisk               ?? null;      // latest high-risk Chat row (object)
  $allHighRisk                 = $allHighRisk            ?? [];        // array of all high-risk lines
  $riskLogs                    = $riskLogs               ?? collect(); // Collection of risk change logs
  $lastRisk                    = $lastRisk               ?? null;      // last risk-change log
  $nextAppt                    = $nextAppt               ?? null;      // upcoming appointment (object)
  $hasAnyActiveForStudent      = $hasAnyActiveForStudent ?? false;     // bool
  $hasCompletedForThisSession  = $hasCompletedForThisSession ?? false; // bool
@endphp

@php
  // ---- Normalize emotions -> counts
  $raw = $session->emotions ?? [];
  if (is_string($raw)) {
    $decoded = json_decode($raw, true);
    $raw = is_array($decoded) ? $decoded : [];
  }
  $counts = [];
  if (is_array($raw)) {
    $isList = array_keys($raw) === range(0, count($raw) - 1);
    if ($isList) {
      foreach ($raw as $lbl) {
        if (!is_string($lbl) || $lbl==='') continue;
        $k = strtolower($lbl);
        $counts[$k] = ($counts[$k] ?? 0) + 1;
      }
    } else {
      foreach ($raw as $k => $v) {
        if (!is_string($k)) continue;
        $counts[strtolower($k)] = max(0, (int) $v);
      }
    }
  }
  arsort($counts);
  $total = array_sum($counts);
  $top   = array_slice($counts, 0, 6, true);

  // ---- Codes / risk
  $codeYear   = $session->created_at?->format('Y') ?? now()->format('Y');
  $code       = 'LMC-' . $codeYear . '-' . str_pad($session->id, 4, '0', STR_PAD_LEFT);
  $riskStr    = strtolower((string)($session->risk_level ?? $session->risk ?? ''));
  $isHighRisk = in_array($riskStr, ['high','high-risk','high_risk'], true)
                || (int)($session->risk_score ?? 0) >= 80;

  // ---- Booking rules
  $__hasActive  = (bool) ($hasAnyActiveForStudent ?? false);
  $__doneThis   = (bool) ($hasCompletedForThisSession ?? false);
  $__expedited  = (bool) ($wasExpedited ?? ($session->expedited_at ?? false));

  $canBook        = $isHighRisk && !$__hasActive && !$__doneThis;
  $canMoveEarlier = $isHighRisk && $__hasActive && !$__doneThis && !$__expedited;

  $studentName = trim($session->user->name ?? '') ?: 'Unknown Student';

  // ---- Lightweight stats for KPI chips
  try {
    $msgCount = null; $minsDur = null; $lastAct = $session->updated_at;
  } catch (\Throwable $e) { $msgCount=null; $minsDur=null; $lastAct=$session->updated_at; }

  // ---- Latest high-risk trigger message (ONLY if controller passed it and not locked)
  if (!($sensitiveLocked ?? false)) {
    $lastHighRisk = $highRisk;

    if ($lastHighRisk) {
      $hrId   = $lastHighRisk->id ?? null;
      $hrText = trim($lastHighRisk->text ?? $lastHighRisk->message ?? '');
      $ts     = $lastHighRisk->sent_at ?? $lastHighRisk->created_at ?? null;
      $hrTime = $ts ? \Carbon\Carbon::parse($ts) : null;
    }
  }
@endphp

<div class="max-w-7xl mx-auto p-5 md:p-6 space-y-4">

  {{-- ===== Header ===== --}}
  <section aria-labelledby="hdr-title" class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <h2 id="hdr-title" class="text-2xl font-bold tracking-tight text-slate-900">Chatbot Session</h2>
        @if($isHighRisk)
          <span class="inline-flex items-center gap-1.5 h-6 px-2 rounded-full text-[11px] font-medium ring-1 bg-rose-100 text-rose-700 ring-rose-200">
            <img src="{{ asset('images/icons/alert.png') }}" alt="High risk" class="w-3.5 h-3.5 object-contain" />
            HIGH RISK
          </span>
        @endif
          @php
            $__start   = !empty($nextAppt?->scheduled_at) ? \Carbon\Carbon::parse($nextAppt->scheduled_at) : null;
            $__pillCls = $__start
                ? (now()->diffInMinutes($__start, false) <= 60*24 ? 'bg-amber-100 text-amber-800 ring-amber-200'
                                                                : 'bg-emerald-100 text-emerald-800 ring-emerald-200')
                : 'bg-emerald-100 text-emerald-800 ring-emerald-200';
          @endphp
          <span id="hdrUpcomingPill"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 {{ $__pillCls }} {{ empty($nextAppt) ? 'hidden' : '' }}">
            @if(!empty($nextAppt) && $__start)
              Upcoming appt: {{ $__start->format('M d, Y • h:i A') }}
            @endif
          </span>
      </div>
      <p class="mt-0.5 text-sm text-slate-600 leading-snug">Manage and export a single session record.</p>
    </div>

    <div class="flex items-center gap-2">
      <a href="{{ route('admin.chatbot-sessions.index') }}"
         class="inline-flex items-center gap-2 h-9 px-3 rounded-xl bg-white text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to list
      </a>

      <a href="{{ url('admin/chatbot-sessions/'.$session->id.'/pdf') }}" target="_blank"
         class="inline-flex items-center gap-2 h-9 px-3 rounded-xl bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
        Download PDF
      </a>
    </div>
  </section>

  {{-- ===== GRID: KPIs etc. ===== --}}
  <div class="kpi-grid grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">

    {{-- TOP: Session ID --}}
    <div class="kpi card h-full flex flex-col rounded-2xl bg-white ring-1 ring-slate-200 p-3 md:p-4 lg:col-span-3">
      <div class="text-[10px] font-medium tracking-wide text-slate-500 uppercase">Session ID</div>
      <div class="mt-1 font-semibold text-slate-900 flex items-center gap-1.5">
        <span id="sessionCode">{{ $code }}</span>
        <button type="button" onclick="copyText('#sessionCode')"
          class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
          title="Copy Session ID" aria-label="Copy Session ID">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/>
            <rect x="3" y="3" width="13" height="13" rx="2" ry="2" stroke-width="2"/>
          </svg>
        </button>
      </div>
      @if(!is_null($msgCount))
        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-slate-600">
          <span class="inline-flex items-center gap-1 rounded-md bg-slate-50 ring-1 ring-slate-200 px-2 py-0.5">
            <span class="font-medium">{{ $msgCount }}</span> msgs
          </span>
          @if(!is_null($minsDur))
            <span class="inline-flex items-center gap-1 rounded-md bg-slate-50 ring-1 ring-slate-200 px-2 py-0.5">~{{ $minsDur }} mins</span>
          @endif
          @if(!empty($lastAct))
            <span class="inline-flex items-center gap-1 rounded-md bg-slate-50 ring-1 ring-slate-200 px-2 py-0.5">last {{ \Carbon\Carbon::parse($lastAct)->diffForHumans() }}</span>
          @endif
        </div>
      @endif
    </div>

    {{-- TOP: Student --}}
    <div class="kpi card h-full flex flex-col rounded-2xl bg-white ring-1 ring-slate-200 p-3 md:p-4 lg:col-span-3">
      <div class="text-[10px] font-medium tracking-wide text-slate-500 uppercase">Student</div>
      <div class="mt-1 text-base font-medium text-slate-800 {{ $studentName==='Unknown Student' ? 'opacity-70' : '' }}">
        {{ $studentName }}
      </div>
      @if(!empty($session->user?->email))
        <div class="mt-2 text-[11px] text-slate-600">
          <a href="mailto:{{ $session->user->email }}" class="underline underline-offset-2 hover:text-indigo-600">
            {{ $session->user->email }}
          </a>
        </div>
      @endif
    </div>

    {{-- TOP: Initial Date --}}
    <div class="kpi card h-full flex flex-col rounded-2xl bg-white ring-1 ring-slate-200 p-3 md:p-4 lg:col-span-3">
      <div class="text-[10px] font-medium tracking-wide text-slate-500 uppercase">Initial Date</div>
      <div class="mt-1 text-base font-medium text-slate-800">{{ $session->created_at?->format('F d, Y • h:i A') }}</div>
      @if(!empty($session->updated_at))
        <div class="mt-2 text-[11px] text-slate-600">Updated {{ $session->updated_at->diffForHumans() }}</div>
      @endif
    </div>

    {{-- RIGHT: Risk (row-span) --}}
<div class="risk-card card flex flex-col rounded-2xl bg-white ring-1 ring-slate-200 p-3 md:p-4 lg:col-span-3 lg:row-span-2">

  {{-- header --}}
  <div class="flex items-center justify-between gap-2">
    <div class="text-[10px] font-medium tracking-wide text-slate-500 uppercase">Risk</div>
    @php
      $riskLbl = ucfirst($riskStr ?: '—');
      $pillCls = match ($riskStr) {
        'high','high-risk','high_risk' => 'bg-rose-100 text-rose-700 ring-rose-200',
        'moderate'                     => 'bg-amber-100 text-amber-800 ring-amber-200',
        'low'                          => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        default                        => 'bg-slate-100 text-slate-700 ring-slate-200',
      };
    @endphp
    <span id="riskPill" class="inline-flex items-center gap-1.5 h-6 px-2 rounded-full text-[11px] font-medium ring-1 {{ $pillCls }}">
      @if(in_array($riskStr, ['high','high-risk','high_risk'], true))
        <img src="{{ asset('images/icons/alert.png') }}" alt="High" class="w-3.5 h-3.5 object-contain">
      @endif
      <span id="riskPillText">{{ $riskLbl }}</span>
    </span>
  </div>

  {{-- controls --}}
  @php
    $riskButtons = [
      ['low', 'Low', 'bg-emerald-600'],
      ['moderate', 'Moderate', 'bg-amber-500'],
      ['high', 'High', 'bg-rose-600'],
    ];
  @endphp

  <div class="mt-2" role="group" aria-label="Set risk level">
    <div class="seg w-full rounded-xl ring-1 ring-slate-200 overflow-hidden">
      @foreach($riskButtons as $rb)
        @php
          $val    = $rb[0];
          $lab    = $rb[1];
          $dot    = $rb[2];
          $active = ($riskStr === $val) || ($val==='high' && in_array($riskStr,['high','high-risk','high_risk'], true));
        @endphp
        <button type="button"
                class="riskChip seg-btn {{ $active ? 'seg-active' : '' }}"
                data-level="{{ $val }}"
                aria-pressed="{{ $active ? 'true' : 'false' }}"
                title="{{ $lab }}">
          <span class="w-2.5 h-2.5 rounded-full {{ $dot }}"></span>
          <span class="truncate">{{ $lab }}</span>
        </button>
      @endforeach
    </div>
  </div>

  <p class="risk-hint mt-1">
    Use <kbd class="kbd">L</kbd>, <kbd class="kbd">M</kbd>, or <kbd class="kbd">H</kbd>. Downgrades require a brief reason.
  </p>

  @if(isset($lastRisk))
    <div class="risk-last mt-2">
      <div class="flex items-start justify-between gap-3">
        <div class="text-sm text-slate-900">
          <strong>{{ ucfirst($lastRisk->from_level ?: '—') }}</strong>
          <span class="mx-1.5 text-slate-400">→</span>
          <strong>{{ ucfirst($lastRisk->to_level) }}</strong>
        </div>
        @if(($riskLogs->count() ?? 0) > 1)
          <button type="button" id="btnRiskHistory" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
            View history
          </button>
        @endif
      </div>

      <div class="risk-meta">{{ $lastRisk->created_at->format('M d, Y • h:i A') }}</div>

      @if($lastRisk->note)
        <div class="risk-note line-clamp-2">{{ $lastRisk->note }}</div>
      @endif
    </div>
  @endif

  @php
    $scoreFromLevel = match($riskStr){
      'high','high-risk','high_risk' => 90,
      'moderate'                     => 60,
      'low'                          => 20,
      default                        => 0,
    };
    $riskScore = max(0, min(100, (int)($session->risk_score ?? $scoreFromLevel)));
    $riskLevel = ucfirst($riskStr ?: '—');
  @endphp

  <div class="mt-3 risk-extras">
    <div class="risk-meter">
      <div class="risk-meter-head">
        <span class="risk-meter-title">Risk level</span>
      </div>

      <div class="risk-meter-bar" aria-label="Risk level {{ $riskLevel }}">
        <div class="risk-meter-pin" style="left: {{ $riskScore }}%"></div>
      </div>

      <div class="risk-meter-legend">
        <span class="leg leg-low">Low</span>
        <span class="leg leg-mod">Moderate</span>
        <span class="leg leg-high">High</span>
      </div>
    </div>
  </div>
</div>

    {{-- ROW 2: High-risk (6) + Emotions (3) --}}
    @php $lockIcon = asset('images/icons/security.png'); @endphp

    {{-- High-risk trigger --}}
    <div id="hrWrap" class="relative lg:col-span-6">
      <div id="hrCard"
          class="hr-card card rounded-2xl border p-3 md:p-4 {{ ($sensitiveLocked ?? false) ? 'border-slate-200 bg-white' : 'border-rose-200 bg-rose-50' }}">
        @if(!($sensitiveLocked ?? false))
          @if(!empty($highRisk?->text ?? $hrText ?? null))
            <div class="flex items-start gap-2.5">
              <img src="{{ asset('images/icons/alert.png') }}" alt="High risk" class="h-5 w-5 mt-0.5 object-contain" />
              <div class="flex-1">
                <div class="flex items-center justify-between">
                    <div class="font-semibold text-rose-700">High-risk trigger (student message)</div>
                      <!-- Hidden by default; shown after booking -->
                      <button id="hrMarkDone"
                              type="button"
                              class="hidden text-xs font-medium rounded-lg px-2 py-1 ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50"
                              title="Mark as handled (visual only)">
                        Done
                      </button>
                    </div>
                <div class="mt-0.5 text-xs text-rose-800/80">
                  {{ isset($highRisk)
                        ? \Carbon\Carbon::parse($highRisk->sent_at ?? $highRisk->created_at)->format('F d, Y • h:i A')
                        : (isset($hrTime) ? $hrTime->format('F d, Y • h:i A') : '') }}
                  @if(isset($highRisk->id) && $highRisk->id)
                    <span class="ml-1 opacity-70">• Chat ID: #{{ $highRisk->id }}</span>
                  @endif
                </div>

                {{-- Latest trigger preview --}}
                <blockquote class="mt-1.5 rounded-xl bg-white ring-1 ring-rose-100 p-3 text-sm text-slate-800">
                  “{{ $highRisk->text ?? $hrText ?? '' }}”
                </blockquote>

                @php
                  $hasAppt  = !empty($nextAppt?->scheduled_at);
                  $hasActive = $hasAppt || (bool)($hasAnyActiveForStudent ?? false);
                @endphp

                {{-- Upcoming appointment banner (always render placeholder) --}}
                <div id="js-nextAppt" class="{{ !empty($nextAppt?->scheduled_at) ? '' : 'hidden' }}">
                  @if(!empty($nextAppt?->scheduled_at))
                    <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 p-3 text-sm">
                      <div class="font-medium">
                        Upcoming appointment
                        {{ \Carbon\Carbon::parse($nextAppt->scheduled_at)->isFuture()
                            ? 'in ' . \Carbon\Carbon::parse($nextAppt->scheduled_at)->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>\Carbon\Carbon::DIFF_RELATIVE_TO_NOW])
                            : 'Started ' . \Carbon\Carbon::parse($nextAppt->scheduled_at)->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>\Carbon\Carbon::DIFF_RELATIVE_TO_NOW]) }}
                      </div>
                      <div class="text-xs opacity-80">
                        {{ \Carbon\Carbon::parse($nextAppt->scheduled_at)->format('M d, Y') }} •
                        {{ \Carbon\Carbon::parse($nextAppt->scheduled_at)->format('g:i A') }}
                        @if(!empty($nextAppt->counselor_name)) • {{ $nextAppt->counselor_name }} @endif
                      </div>
                    </div>
                  @endif
                </div>

                {{-- Already booked chip --}}
                @if($hasActive)
                  <div id="js-hasActiveMsg" class="mt-2">
                    <div class="inline-flex items-center rounded-lg bg-slate-100 text-slate-700 text-xs px-3 py-1.5">
                      You’ve booked an appointment already
                    </div>
                  </div>
                @else
                  <div id="js-hasActiveMsg" class="mt-2 hidden"></div>
                @endif  

                {{-- All high-risk lines --}} 

                @php $hrCount = is_countable($allHighRisk ?? []) ? count($allHighRisk) : 0; @endphp
                <div class="mt-3">
                  <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-900">
                      All high-risk / critical lines
                      @if($hrCount > 0)
                        <span class="ml-1 text-slate-500 font-normal">({{ $hrCount }})</span>
                      @endif
                    </div>
                    <button
                      id="btnShowAllHR"
                      type="button"
                      class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                      data-state="closed"
                      aria-expanded="false"
                      aria-controls="hrAllList"
                      data-preloaded="{{ $hrCount > 0 ? '1' : '0' }}"
                    >
                      View all{{ $hrCount > 0 ? ' ('.$hrCount.')' : '' }}
                    </button>
                  </div>

                  <!-- hidden list shell; may be empty initially -->
                  <div id="hrAllList" class="mt-2 space-y-2 hidden">
                    @foreach($allHighRisk as $hr)
                      <div class="rounded-xl ring-1 ring-slate-200 p-3 bg-white">
                        <div class="text-[12px] text-slate-500">
                          {{ $hr['at'] ?? '' }}
                          @if(!empty($hr['sender']))
                            @php $isUser = strtolower($hr['sender'])==='user'; @endphp
                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] ring-1 {{ $isUser ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                              {{ $hr['sender'] }}
                            </span>
                          @endif
                        </div>
                        <div class="mt-1 text-slate-900">#{{ $hr['id'] }}</div>
                        <blockquote class="mt-1 text-sm text-slate-800 leading-relaxed">{{ $hr['text'] }}</blockquote>
                      </div>
                    @endforeach

                    <div id="hrActionsWrap" class="mt-3 {{ $hrCount>0 ? '' : 'hidden' }}">
                      <div class="no-print flex flex-wrap items-center gap-2">
                        @if($isHighRisk)
                          @if($canBook)
                            <button id="btnBookHR" type="button"
                                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">
                              Book urgent appointment
                            </button>
                          @endif
                          @if($canMoveEarlier)
                            <button id="btnReschedHR" type="button"
                                    class="inline-flex items-center gap-2 rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">
                              Move to earlier slot
                            </button>
                          @endif
                        @endif
                        <div id="hrActionMsg" class="text-sm text-slate-600"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          @elseif(in_array($riskStr, ['high','high-risk','high_risk'], true))
            <div class="flex items-start gap-2.5">
              <svg class="h-5 w-5 mt-0.5 text-amber-700" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1010 10A10.012 10.012 0 0012 2zm1 15h-2v-2h2zm0-4h-2V7h2z"/></svg>
              <div class="flex-1 text-amber-900">
                Session is HIGH-risk, but the exact trigger message wasn’t located.
              </div>
            </div>

          @else
            <div class="text-sm text-slate-600">No recent high-risk trigger.</div>
          @endif

        @else
          {{-- LOCKED view --}}
          <div class="relative overflow-hidden">
            <div class="h-28 md:h-32 rounded-xl bg-slate-100 ring-1 ring-slate-200 flex items-center justify-center">
              <div class="text-center">
                <div class="text-sm font-semibold text-slate-600">High-risk trigger (student message)</div>
                <div class="mt-1 text-xs text-slate-500">Extra verification required</div>
              </div>
            </div>
            <div class="absolute inset-0 pointer-events-none" style="backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);"></div>
            <button id="hrUnlock" type="button"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-2 focus-visible:outline-none group"
                    aria-label="Unlock high-risk message"
                    title="Unlock the student’s high-risk trigger message">
              <img src="{{ asset('images/icons/security.png') }}" alt="Security"
                  class="h-12 w-12 md:h-14 md:w-14 opacity-90 drop-shadow-sm transition-transform duration-150 group-hover:scale-105">
              <span class="inline-flex items-center gap-1 rounded-full bg-white/80 text-slate-700 ring-1 ring-slate-200
                          px-2.5 py-1 text-xs font-medium shadow-sm backdrop-blur-sm">
                Click to unlock the student's high-risk trigger message
              </span>
            </button>
          </div>
        @endif
      </div>
    </div>

    {{-- Emotions Mentioned --}}
    <div class="emo-card card rounded-2xl bg-white ring-1 ring-slate-200 p-3 md:p-4 lg:col-span-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-900">Detected Emotions</h3>
        @if($total>0)
          <span class="text-[11px] text-slate-500">{{ $total }} total</span>
        @endif
      </div>

      @if($total === 0)
        <div class="mt-2 text-sm text-slate-500">—</div>
      @else
        <ul class="mt-3 space-y-2">
          @foreach($top as $k => $n)
            @php
              $pct = $total ? round($n * 100 / $total) : 0;
              $label = ucfirst($k);
            @endphp
            <li>
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-700 truncate">{{ $label }}</span>
                <span class="text-slate-500 text-xs">{{ $n }} • {{ $pct }}%</span>
              </div>
              <div class="mt-1 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                <div class="h-2 rounded-full"
                     style="width: {{ $pct }}%; background: linear-gradient(90deg, rgba(99,102,241,.45), rgba(99,102,241,.85));"></div>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </div>

    {{-- ROW 3: Session Counts --}}
    <div class="lg:col-span-9 space-y-4">
      <div id="sessionPrintable" class="space-y-4">
        <div class="card relative rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
          <div class="p-4 md:p-5">
            <div class="flex flex-wrap items-center gap-2 justify-between">
              <h3 class="text-base font-semibold text-slate-900">Session Counts</h3>
              <div class="flex items-center gap-2 no-print">
                <button id="calPrev"  class="inline-flex items-center rounded-lg px-3 py-1.5 text-sm ring-1 ring-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">← Prev</button>
                <button id="calToday" class="inline-flex items-center rounded-lg px-3 py-1.5 text-sm bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Today</button>
                <button id="calNext"  class="inline-flex items-center rounded-lg px-3 py-1.5 text-sm ring-1 ring-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Next →</button>
              </div>
            </div>

            <div id="calRange" class="mt-1 text-sm text-slate-500" role="status" aria-live="polite"></div>

            <div id="calSkeleton" class="mt-3 grid grid-cols-7 gap-px">
              @for ($i=0; $i<7; $i++)
                <div class="animate-pulse bg-slate-100 h-14 rounded"></div>
              @endfor
            </div>

            <div class="mt-3 overflow-hidden rounded-xl ring-1 ring-slate-200/70">
              <div class="grid grid-cols-7 bg-slate-50/60 text-xs font-medium uppercase tracking-wide text-slate-600">
                <div class="px-3 py-2 text-center">Sun</div><div class="px-3 py-2 text-center">Mon</div><div class="px-3 py-2 text-center">Tue</div>
                <div class="px-3 py-2 text-center">Wed</div><div class="px-3 py-2 text-center">Thu</div><div class="px-3 py-2 text-center">Fri</div>
                <div class="px-3 py-2 text-center">Sat</div>
              </div>
              <div class="grid grid-cols-7 divide-x divide-slate-200/70 text-center">
                @for ($i = 0; $i < 7; $i++)
                  <div class="px-3 py-5 group">
                    <div id="cnt{{ $i }}" class="text-lg md:text-xl font-semibold text-slate-900 transition-transform group-hover:scale-105">—</div>
                    <div class="mt-0.5 text-xs text-slate-500">sessions</div>
                  </div>
                @endfor
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

{{-- ===== Risk history modal ===== --}}
<div id="riskHistoryModal" class="fixed inset-0 z-[70] hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/30"></div>
  <div class="relative z-[71] w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-lg font-semibold text-slate-900">Risk change history</h3>
      <button type="button" id="riskHistoryClose" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">✕</button>
    </div>
    <div class="max-h-[60vh] overflow-auto">
      @if(($riskLogs->count() ?? 0) === 0)
        <div class="text-slate-500 text-sm">No changes recorded.</div>
      @else
        <ul class="space-y-3">
          @foreach($riskLogs as $log)
            <li class="rounded-xl ring-1 ring-slate-200 p-3">
              <div class="text-sm">
                <span class="font-medium">{{ ucfirst($log->from_level ?: '—') }}</span>
                → <span class="font-semibold">{{ ucfirst($log->to_level) }}</span>
                <span class="text-slate-500"> • {{ $log->created_at->format('M d, Y • h:i A') }}</span>
              </div>
              @if($log->note)
                <div class="mt-1 text-sm text-slate-700 whitespace-pre-wrap">{{ $log->note }}</div>
              @endif
            </li>
          @endforeach
        </ul>
      @endif
    </div>
    <div class="mt-4 text-right">
      <button type="button" id="riskHistoryClose2" class="rounded-lg bg-slate-100 px-4 py-2 text-slate-700 hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Close</button>
    </div>
  </div>
</div>

{{-- ===== Book / Reschedule modal (centered + custom calendar) ===== --}}
<div id="hrActionModal" class="fixed inset-0 z-[74] hidden flex items-center justify-center">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>

  <div class="relative z-[75] w-full max-w-3xl rounded-2xl bg-white p-5 md:p-6 shadow-2xl ring-1 ring-black/5">
    <div class="flex items-start justify-between">
      <h3 id="hrActionTitle" class="text-base font-semibold text-slate-900">Book urgent appointment</h3>
        <button id="hrActionClose" type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button>
        </div>

        <form id="hrActionForm" class="mt-4 space-y-5" novalidate>
          @csrf

          <!-- Hidden date field set by calendar -->
          <input id="hrDate" name="date" type="hidden" required>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- LEFT: Calendar picker (weekdays only) --}}
        <section class="rounded-2xl ring-1 ring-slate-200 bg-white p-4">
          <div class="flex items-center justify-between">
            <button id="bkCalPrev" type="button"
              class="px-2.5 py-1.5 rounded-lg ring-1 ring-slate-200 text-slate-600 hover:bg-slate-50">‹</button>
            <div class="text-center">
              <div id="bkCalMonth" class="text-indigo-600 font-bold tracking-wide"></div>
              <div class="text-[11px] text-slate-500">Weekends are closed (Mon–Fri)</div>
            </div>
            <button id="bkCalNext" type="button"
              class="px-2.5 py-1.5 rounded-lg ring-1 ring-slate-200 text-slate-600 hover:bg-slate-50">›</button>
          </div>

          <div class="mt-3 grid grid-cols-7 text-center text-[11px] font-semibold text-slate-500">
            <div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
          </div>

          <div id="bkCalGrid" class="mt-2 grid grid-cols-7 gap-2 select-none"></div>

          <p class="mt-3 text-[11px] text-slate-500" id="bkCalHint">Choose a weekday. Past dates disabled.</p>
        </section>

        {{-- RIGHT: Counselor / Time + pooled capacity --}}
        <section class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700">Counselor</label>
            <select id="hrCounselor" name="counselor_id"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500"
                    required></select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Time</label>

            <!-- kept for form submit; driven by pills -->
            <select id="hrTime" name="time" class="sr-only" tabindex="-1" aria-hidden="true"></select>

            <!-- Pills render here -->
            <div id="hrTimePills" class="mt-2 time-pills"></div>

            <!-- “No slots” helper + ref suggestion -->
            <div id="hrNoSlotsHint" class="mt-2 text-xs text-slate-500 hidden"></div>
          </div>

          <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 p-3">
            <div class="text-xs text-slate-500">Note preview</div>
            <div class="text-sm text-slate-800">
              The student will receive a compassionate, confidential note. (Generated by system on submit.)
            </div>
          </div>
        </section>
      </div>

      <div class="flex items-center justify-end gap-2">
        <button type="button" id="hrActionCancel"
                class="rounded-xl px-3 py-2 ring-1 ring-slate-200 hover:bg-slate-50">Cancel</button>
        <button type="submit" id="hrActionSubmit"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
          <svg id="hrActionSpin" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24">
            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"/>
            <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"/>
          </svg>
          <span id="hrActionLabel">Book</span>
        </button>
      </div>
    </form>

    <div id="hrActionResult" class="mt-4 hidden rounded-xl ring-1 ring-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"></div>
  </div>
</div>

{{-- ===== Sensitive unlock modal (second 2FA) ===== --}}
<div id="hrModal" class="fixed inset-0 z-[75] hidden flex items-center justify-center" aria-hidden="true" role="dialog" aria-labelledby="hrTitle">
  <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px] opacity-0 transition-opacity duration-200"></div>
  <div class="relative z-[76] w-full max-w-md origin-center scale-95 opacity-0 translate-y-2
              rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-5 md:p-6 transition-all duration-200">
    <div class="flex items-center justify-between">
      <h3 id="hrTitle" class="text-base font-semibold text-slate-900">Confirm again to view sensitive content</h3>
      <button type="button" id="hrClose" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <form id="hrForm" class="mt-4 space-y-4" novalidate>
      @csrf
      <div>
        <label for="hrPassword" class="block text-sm font-medium text-slate-700">Password</label>
        <div class="mt-1 relative">
          <input id="hrPassword" name="password" type="password" autocomplete="current-password" required
                 class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 pr-10 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/60">
          <button type="button" id="hrToggle" class="absolute inset-y-0 right-0 my-1 mr-1 rounded-lg px-2 hover:bg-slate-100">
            <img id="hrEye" src="{{ asset('images/icons/eye.png') }}" class="h-5 w-5 opacity-80" alt="Show">
          </button>
        </div>
        <p id="hrErr" class="mt-2 text-sm text-rose-600 hidden"></p>
      </div>
      <div class="flex items-center justify-end gap-2">
        <button type="button" id="hrCancel" class="rounded-xl px-3 py-2 ring-1 ring-slate-200 hover:bg-slate-50">Cancel</button>
        <button type="submit" id="hrSubmit" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 bg-indigo-600 text-white hover:bg-indigo-700">
          <svg id="hrSpin" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24">
            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"/>
            <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"/>
          </svg>
          <span id="hrLabel">Confirm</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
    function setHRVisual(mode){
    const card = document.getElementById('hrCard');
    if (!card) return;

    // remove all known states
    card.classList.remove('border-rose-200','bg-rose-50','border-amber-200','bg-amber-50','border-slate-200','bg-white');

    // title color tweak (optional)
    const titleEl = card.querySelector('.font-semibold');
    if (titleEl) titleEl.classList.remove('text-rose-700');

    // apply target state
    if (mode === 'danger'){
      card.classList.add('border-rose-200','bg-rose-50');
      if (titleEl) titleEl.classList.add('text-rose-700');
    } else if (mode === 'booked'){
      // after booking, soften to amber “attention” state
      card.classList.add('border-amber-200','bg-amber-50');
    } else {
      // neutral
      card.classList.add('border-slate-200','bg-white');
    }
  }

  (() => {
    const btnDone = document.getElementById('hrMarkDone');
    if (btnDone){
      btnDone.addEventListener('click', () => {
        setHRVisual('booked');
        btnDone.classList.add('hidden'); // hide after press
      });
    }
  })();
  /* ===== Server data we want available during JS-only updates ===== */
    @php
    $__NEXT_APPT = null;
    if (!empty($nextAppt?->scheduled_at)) {
      $dt  = \Carbon\Carbon::parse($nextAppt->scheduled_at);
      $__NEXT_APPT = [
        'date_label'     => $dt->format('M d, Y'),
        'time_label'     => $dt->format('g:i A'),
        'rel_label'      => $dt->isFuture()
                              ? 'in ' . $dt->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>\Carbon\Carbon::DIFF_RELATIVE_TO_NOW])
                              : 'Started ' . $dt->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>\Carbon\Carbon::DIFF_RELATIVE_TO_NOW]),
        'counselor_name' => $nextAppt->counselor_name ?? null,
      ];
    }
  @endphp
  const HAS_ACTIVE = @json( (bool)($hasAnyActiveForStudent ?? false) );
  const NEXT_APPT = @json($__NEXT_APPT);

  let epAllHR = @json(route('admin.chatbot-sessions.highrisk_all', $session->id));

  /* ===== View/Hide all high-risk items (animated) ===== */
  function wireHRListToggle(scope){
    const root    = scope || document;
    const btn     = root.getElementById?.('btnShowAllHR') || root.querySelector?.('#btnShowAllHR');
    const list    = root.getElementById?.('hrAllList')    || root.querySelector?.('#hrAllList');
    const actions = root.getElementById?.('hrActionsWrap') || root.querySelector?.('#hrActionsWrap');
    if (!btn || !list) return;

    const reduced     = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const closedLabel = btn.dataset.closedLabel || (btn.textContent || 'View all');
    const openLabel   = btn.dataset.openLabel   || 'Hide';

    list.classList.add('hr-collapse','hr-stagger');
    list.classList.add('hidden');
    btn.setAttribute('data-state','closed');
    btn.setAttribute('aria-expanded','false');
    btn.textContent = closedLabel;

    let loaded = btn.dataset.preloaded === '1';

    async function ensureLoaded(){
      if (loaded) return true;
      if (!epAllHR) { loaded = true; return true; }
      try{
        const res = await fetch(epAllHR, { headers:{ 'X-Requested-With':'XMLHttpRequest' } });
        const data  = await res.json().catch(()=> ({}));
        const items = Array.isArray(data?.items) ? data.items : [];
        if (!items.length){
          list.innerHTML = `<div class="rounded-xl ring-1 ring-slate-200 p-3 bg-white text-sm text-slate-600">No high-risk lines found.</div>`;
          actions?.classList.add('hidden');
          loaded = true; return true;
        }
        list.innerHTML = items.map(item => `
          <div class="rounded-xl ring-1 ring-slate-200 p-3 bg-white">
            <div class="text-[12px] text-slate-500">
              ${item.at || ''}
              ${item.sender ? `<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] ring-1 ${String(item.sender).toLowerCase()==='user' ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-slate-50 text-slate-700 ring-slate-200'}">${item.sender}</span>` : ''}
            </div>
            <div class="mt-1 text-slate-900">#${item.id}</div>
            <blockquote class="mt-1 text-sm text-slate-800 leading-relaxed">
              ${String(item.text||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
            </blockquote>
          </div>
        `).join('');
        actions?.classList.remove('hidden');
        loaded = true; return true;
      } catch {
        list.innerHTML = `<div class="rounded-xl ring-1 ring-rose-200 p-3 bg-rose-50 text-sm text-rose-800">Couldn’t load items.</div>`;
        actions?.classList.add('hidden');
        loaded = true; return false;
      }
    }

    function expand(el){
      el.classList.remove('hidden');
      if (reduced) return;
      el.style.height='0px'; el.style.opacity='0'; el.style.transform='translateY(4px)'; el.getBoundingClientRect();
      const h = el.scrollHeight;
      el.style.transition='height 240ms cubic-bezier(.2,.65,.3,1), opacity 200ms ease, transform 200ms ease';
      el.style.height=h+'px'; el.style.opacity='1'; el.style.transform='translateY(0)';
      const kids = Array.from(el.children);
      kids.forEach((k,i)=>{ k.style.transition='opacity 220ms ease, transform 220ms ease'; k.style.transitionDelay=(80+i*25)+'ms'; k.style.opacity='1'; k.style.transform='translateY(0)'; });
      el.addEventListener('transitionend', function tidy(e){ if(e.propertyName==='height'){ el.style.height=''; el.style.transition=''; el.style.opacity=''; el.style.transform=''; kids.forEach(k=>{k.style.transition=''; k.style.transitionDelay='';}); el.removeEventListener('transitionend', tidy);} });
    }

    function collapse(el){
      if (reduced){ el.classList.add('hidden'); return; }
      const kids = Array.from(el.children);
      kids.forEach(k=>{ k.style.transition='opacity 180ms ease, transform 180ms ease'; k.style.transitionDelay='0ms'; k.style.opacity='0'; k.style.transform='translateY(6px)'; });
      const h = el.scrollHeight; el.style.height=h+'px'; el.getBoundingClientRect();
      el.style.transition='height 220ms cubic-bezier(.2,.65,.3,1), opacity 160ms ease, transform 160ms ease';
      el.style.height='0px'; el.style.opacity='0'; el.style.transform='translateY(4px)';
      el.addEventListener('transitionend', function tidy(e){ if(e.propertyName==='height'){ el.classList.add('hidden'); el.style.height=''; el.style.transition=''; el.style.opacity=''; el.style.transform=''; kids.forEach(k=>{k.style.transition=''; k.style.transitionDelay='';}); el.removeEventListener('transitionend', tidy);} });
    }

    btn.addEventListener('click', async () => {
      const isOpen = btn.getAttribute('data-state') === 'open';
      if (isOpen){
        collapse(list);
        btn.setAttribute('data-state','closed');
        btn.setAttribute('aria-expanded','false');
        btn.textContent = closedLabel;
      } else {
        if (!loaded) await ensureLoaded();
        expand(list);
        btn.setAttribute('data-state','open');
        btn.setAttribute('aria-expanded','true');
        btn.textContent = openLabel;
      }
    });
  }

  // Wire once for server-rendered content
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => wireHRListToggle(document));
  } else {
    wireHRListToggle(document);
  }

  /* ===== Small util ===== */
  function copyText(selector){
    const el = document.querySelector(selector);
    if(!el) return;
    const text = (el.textContent || '').trim();
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
      if (window.Swal) {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Copied', timer:1400, showConfirmButton:false });
      }
    }).catch(()=>{ /* ignore */ });
  }

  (() => {
  const canBook        = @json($canBook);
  const canMoveEarlier = @json($canMoveEarlier);
  const csrf           = @json(csrf_token());

  const epSlots  = @json(route('admin.chatbot-sessions.slots', $session->id));
  const epBook   = @json(route('admin.chatbot-sessions.book', $session->id));
  const epRebook = @json(route('admin.chatbot-sessions.reschedule', $session->id));

  const DEFAULT_HOURLY_SLOTS = [ "09:00","10:00","11:00","13:00","14:00","15:00" ];

  // ---- DOM refs
  const modal    = document.getElementById('hrActionModal');
  const titleEl  = document.getElementById('hrActionTitle');
  const closeBtn = document.getElementById('hrActionClose');   // ✕ button
  const cancelBtn= document.getElementById('hrActionCancel');
  const form     = document.getElementById('hrActionForm');
  const submit   = document.getElementById('hrActionSubmit');
  const spin     = document.getElementById('hrActionSpin');
  const label    = document.getElementById('hrActionLabel');
  const result   = document.getElementById('hrActionResult');

  const iDate    = document.getElementById('hrDate');
  const iCoun    = document.getElementById('hrCounselor');
  const iTime    = document.getElementById('hrTime');
  const timePills   = document.getElementById('hrTimePills');
  const noSlotsHint = document.getElementById('hrNoSlotsHint');

  const calGrid  = document.getElementById('bkCalGrid');
  const calMonth = document.getElementById('bkCalMonth');
  const calPrev  = document.getElementById('bkCalPrev');
  const calNext  = document.getElementById('bkCalNext');

  // ---- NEW: track successful action so ✕ can refresh
  let __justBookedOrRescheduled = false;

  // ---- helpers
  const pad = n => String(n).padStart(2,'0');
  const ymd = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const hourKey = hhmm => {
    const m = /^(\d{2}):(\d{2})$/.exec(String(hhmm||'').trim());
    return m ? `${m[1]}:00` : '';
  };
  const fmt12 = hhmm => {
    const m = /^(\d{2}):(\d{2})$/.exec(String(hhmm||'').trim());
    if (!m) return String(hhmm||'');
    let h = +m[1], mm = m[2]; const ampm = h>=12 ? 'PM':'AM'; h = h%12 || 12;
    return `${h}:${mm} ${ampm}`;
  };

  function normalizeHourlyRows(rawRows, occupiedArr, currentStr){
    const occSet = new Set((occupiedArr||[]).map(v => String(v).slice(0,5)));
    const curH   = hourKey(currentStr||'');
    const bucket = new Map();
    DEFAULT_HOURLY_SLOTS.forEach(h => { if (h!=='12:00') bucket.set(h, {present:false, disabledAll:true, busy:false, current:false}); });
    (Array.isArray(rawRows) ? rawRows : []).forEach(s => {
      const val  = String(s?.value ?? s?.label ?? '').trim();
      const hour = hourKey(val); if (!hour || hour==='12:00') return;
      if(!bucket.has(hour)) bucket.set(hour, {present:false, disabledAll:true, busy:false, current:false});
      const b = bucket.get(hour);
      const isBusy = Boolean(s?.busy || s?.occupied || occSet.has(val.slice(0,5)) || occSet.has(hour));
      const isDis  = Boolean(s?.disabled);
      b.present=true; b.disabledAll = b.disabledAll && isDis; b.busy = b.busy || isBusy; b.current = b.current || (hour===curH);
    });
    return Array.from(bucket.keys())
      .filter(h => h!=='12:00')
      .map(h => {
        const b = bucket.get(h);
        const disabled = b.disabledAll || b.busy || b.current;
        return { value:h, label:fmt12(h), disabled, busy:b.busy, current:b.current };
      });
  }

  function setBusy(b){ submit.disabled=b; spin.classList.toggle('hidden', !b); label.textContent = b ? (mode==='book'?'Booking…':'Rescheduling…') : (mode==='book'?'Book':'Reschedule'); }

  // ---- wire main action buttons (already present elsewhere)
  function rewireHRActionButtons(scope=document){
    const b1 = scope.querySelector('#btnBookHR');
    const b2 = scope.querySelector('#btnReschedHR');
    if (b1) b1.addEventListener('click', () => open('book'), { once:true });
    if (b2) b2.addEventListener('click', () => open('resched'), { once:true });
  }
  rewireHRActionButtons();
  document.addEventListener('click', (e) => {
    const b1 = e.target.closest?.('#btnBookHR');
    const b2 = e.target.closest?.('#btnReschedHR');
    if (b1) { e.preventDefault(); open('book'); }
    if (b2) { e.preventDefault(); open('resched'); }
  });

  // ---- calendar state
  let mode = 'book';
  const today = new Date(); today.setHours(0,0,0,0);
  let view = new Date(today.getFullYear(), today.getMonth(), 1);
  let picked = null;

  function renderCalendar(){
    calGrid.innerHTML = '';
    calMonth.textContent = new Intl.DateTimeFormat('en',{month:'long',year:'numeric'}).format(view).toUpperCase();
    const firstDow = new Date(view.getFullYear(), view.getMonth(), 1).getDay();
    const days = new Date(view.getFullYear(), view.getMonth()+1, 0).getDate();
    for (let i=0;i<firstDow;i++) calGrid.appendChild(document.createElement('div'));
    const isWeekend = d => [0,6].includes(d.getDay());
    for (let d=1; d<=days; d++){
      const cur = new Date(view.getFullYear(), view.getMonth(), d); cur.setHours(0,0,0,0);
      const disabled = cur < today || isWeekend(cur);
      const btn = document.createElement('button');
      btn.type='button'; btn.textContent=d; btn.className='bk-day'+(disabled?' bk-day--disabled':'');
      if (!disabled){
        btn.addEventListener('click', () => {
          picked = cur;
          iDate.value = ymd(cur);
          Array.from(calGrid.querySelectorAll('.bk-day')).forEach(b => b.classList.remove('bk-day--selected'));
          btn.classList.add('bk-day--selected');
          loadSlots();
        });
      } else { btn.title = isWeekend(cur) ? 'Closed (Sat–Sun)' : 'Past date'; btn.disabled = true; }
      if (picked && cur.getTime() === picked.getTime()) btn.classList.add('bk-day--selected');
      calGrid.appendChild(btn);
    }
  }
  const sameYM = (a,b) => a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth();
  document.getElementById('bkCalPrev')?.addEventListener('click',()=>{ if (!sameYM(view,today)) { view.setMonth(view.getMonth()-1); renderCalendar(); }});
  document.getElementById('bkCalNext')?.addEventListener('click',()=>{ view.setMonth(view.getMonth()+1); renderCalendar(); });

  async function loadSlots(forceCounselorId=null){
    const date = iDate.value; if (!date) return;
    try{
      const url = new URL(epSlots, window.location.origin);
      url.searchParams.set('date', date);
      const cid = forceCounselorId || iCoun.value || '';
      if (cid) url.searchParams.set('counselor_id', cid);

      const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      const j = await res.json();

      iCoun.innerHTML = '';
      (j.counselors||[]).forEach(c => iCoun.add(new Option(c.name, c.id)));
      if (!iCoun.options.length){
        timePills.innerHTML=''; noSlotsHint.textContent='No active counselors available for booking.'; noSlotsHint.classList.remove('hidden'); return;
      }
      if (cid && [...iCoun.options].some(o => o.value===cid)) iCoun.value=cid;

      const occBy = j.occupied_by || {};
      const slots = j.slots || {};
      const current = (j.current_time || '').trim();
      const ref     = j.ref_time || j.suggest || null;

      const activeCid = iCoun.value;
      const occRaw = occBy[activeCid] || [];
      const occNorm = occRaw.map(x => String(x).padStart(5,'0'));
      fillTimes(activeCid, slots, { occupied: occNorm, current, ref });

      iCoun.onchange = () => loadSlots(iCoun.value);
    }catch(e){
      iCoun.innerHTML=''; iTime.innerHTML=''; timePills && (timePills.innerHTML=''); noSlotsHint && noSlotsHint.classList.add('hidden');
      console.error(e);
    }
  }

  function fillTimes(counselorId, slotsByCounselor, extras={}){
    const occ = Array.isArray(extras.occupied) ? extras.occupied.map(String) : [];
    const cur = (extras.current || '').trim();
    const raw  = (slotsByCounselor?.[counselorId] || []);
    const rows = normalizeHourlyRows(raw, occ, cur);

    iTime.innerHTML=''; rows.forEach(s => { const opt = new Option(s.label, s.value, false, false); if (s.disabled) opt.disabled=true; iTime.add(opt); });

    timePills.innerHTML=''; let anyEnabled=false;
    rows.forEach(s => {
      const btn = document.createElement('button');
      btn.type='button'; btn.className='time-pill';
      btn.innerHTML = s.busy ? `${s.label} <span class="time-pill-badge time-pill-badge--danger">No Slots</span>` : s.label;

      if (s.busy){ btn.classList.add('time-pill--busy'); btn.setAttribute('aria-disabled','true'); btn.title='Occupied'; }
      else if (s.current){ btn.classList.add('time-pill--current'); btn.setAttribute('aria-disabled','true'); btn.title='Current';
        const badge=document.createElement('span'); badge.className='time-pill-badge'; badge.textContent='Current'; btn.appendChild(badge);
      } else if (s.disabled){ btn.setAttribute('aria-disabled','true'); }

      if (!s.disabled){
        anyEnabled=true;
        btn.addEventListener('click', () => {
          iTime.value=s.value;
          [...timePills.querySelectorAll('.time-pill')].forEach(p => p.classList.remove('time-pill--active'));
          btn.classList.add('time-pill--active');
        }, { passive:true });
      }
      timePills.appendChild(btn);
    });

    if (!anyEnabled){
      const ref = (extras.ref || '').trim() || '09:00';
      noSlotsHint.textContent = 'No available slots. You may try this reference time:'; noSlotsHint.classList.remove('hidden');
      const refBtn=document.createElement('button'); refBtn.type='button'; refBtn.className='time-pill time-pill--ref'; refBtn.innerHTML=`Ref: ${fmt12(ref)}`;
      refBtn.addEventListener('click', () => {
        let opt=[...iTime.options].find(o=>o.value===ref); if(!opt)iTime.add(new Option(fmt12(ref), ref, true, true)); else iTime.value=ref;
        [...timePills.querySelectorAll('.time-pill')].forEach(p => p.classList.remove('time-pill--active'));
        refBtn.classList.add('time-pill--active');
      }, { passive:true });
      timePills.appendChild(refBtn);
    } else {
      noSlotsHint.classList.add('hidden');
      timePills.querySelector('.time-pill:not([aria-disabled="true"])')?.click();
    }
  }

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    setBusy(true);
    try{
      if (!iDate.value) {
        const d = new Date(); d.setDate(d.getDate()+1); if (d.getDay()==6) d.setDate(d.getDate()+2); if (d.getDay()==0) d.setDate(d.getDate()+1);
        picked=d; iDate.value = ymd(d);
      }
      if (!iTime.value) timePills.querySelector('.time-pill:not([aria-disabled="true"])')?.click();

      const fd = new FormData(form);
      fd.append('_token', csrf);
      if (!fd.get('date')) fd.set('date', iDate.value || '');

      const ep = (mode==='book') ? epBook : epRebook;
      const res = await fetch(ep, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
      const j = await res.json().catch(()=> ({}));
      if (!res.ok || !j?.ok) { throw new Error(j?.message || 'Request failed.'); }

      // success view
      result.innerHTML = j.html || '<div class="text-slate-700">Done.</div>';
      form.classList.add('hidden');
      result.classList.remove('hidden');

      // FLAG: used by ✕ to trigger page refresh
      __justBookedOrRescheduled = true;

      if (j.appt){
        // soften card + show banners handled by your other code
        const hdr = document.getElementById('hdrUpcomingPill');
        if (hdr){ hdr.textContent = `Upcoming appt: ${j.appt.date_label} • ${j.appt.time_label}`; hdr.classList.remove('hidden'); }
        const chip = document.getElementById('js-hasActiveMsg'); chip?.classList.remove('hidden');
        const wrap = document.getElementById('js-nextAppt');
        if (wrap){ wrap.classList.remove('hidden'); wrap.innerHTML =
          `<div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 p-3 text-sm">
             <div class="font-medium">Upcoming appointment ${j.appt.rel_label}</div>
             <div class="text-xs opacity-80">${j.appt.date_label} • ${j.appt.time_label}${j.appt.counselor_name ? ' • '+j.appt.counselor_name : ''}</div>
           </div>`; }
        document.getElementById('btnBookHR')?.classList.add('hidden');
        document.getElementById('hrActionsWrap')?.classList.add('hidden');
        document.getElementById('hrMarkDone')?.classList.remove('hidden');
      }
    } catch(err){
      console.error(err);
      if (window.Swal) Swal.fire({ icon:'error', title:'Error', text:String(err.message||err) });
    } finally {
      setBusy(false);
    }
  });

  function open(m){
    mode = m;
    titleEl.textContent = (mode==='book') ? 'Book urgent appointment' : 'Move appointment earlier';
    label.textContent   = (mode==='book') ? 'Book' : 'Reschedule';
    result.classList.add('hidden'); form.classList.remove('hidden');
    modal.classList.remove('hidden');
    __justBookedOrRescheduled = false;   // reset flag on open

    const d = new Date(); d.setDate(d.getDate()+1); if (d.getDay()==6) d.setDate(d.getDate()+2); if (d.getDay()==0) d.setDate(d.getDate()+1);
    picked=d; iDate.value = ymd(d);
    view = new Date(d.getFullYear(), d.getMonth(), 1);
    renderCalendar(); loadSlots();
  }
  function close(){ modal.classList.add('hidden'); }

  // ✕ should refresh only after a successful booking/rescheduling
  closeBtn?.addEventListener('click', () => {
    const successVisible = __justBookedOrRescheduled && !result.classList.contains('hidden');
    close();
    if (successVisible) setTimeout(() => window.location.reload(), 120);
  });

  // cancel/backdrop = just close, no reload
  cancelBtn?.addEventListener('click', close);
  modal?.firstElementChild?.addEventListener('click', close);
})();


  /* ===== Calendar (weekly counts) ===== */
  (() => {
    const endpoint = @json(route('admin.chatbot-sessions.calendar', $session->id));
    const rangeEl = document.getElementById('calRange');
    const prevBtn = document.getElementById('calPrev');
    const nextBtn = document.getElementById('calNext');
    const todayBtn = document.getElementById('calToday');
    const cntEls = [...Array(7)].map((_, i) => document.getElementById('cnt' + i));
    if (!rangeEl) return;

    const pad = n => String(n).padStart(2, '0');
    const ymdLocal = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const fmtPretty = d => d.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
    function startOfWeek(d){ const x=new Date(d); x.setHours(0,0,0,0); x.setDate(x.getDate()-x.getDay()); return x; }
    function endOfWeek(d){ const s=startOfWeek(d), e=new Date(s); e.setDate(s.getDate()+6); return e; }
    function animateNumber(el, to){
      if (!el) return;
      const start = Number((el.textContent||'').replace(/[^\d]/g,'')) || 0;
      const end = Number(to) || 0;
      if(start===end){ el.textContent=end; return; }
      const dur = 600 + Math.min(600, Math.abs(end-start)*40);
      const t0 = performance.now(); const ease = t => 1 - Math.pow(1 - t, 3);
      (function tick(now){ const p=Math.min(1,(now-t0)/dur); el.textContent=Math.round(start+(end-start)*ease(p)); if(p<1) requestAnimationFrame(tick); })(t0);
    }
    function highlightToday(cells, from){
      const todayStr = ymdLocal(new Date());
      cells.forEach((el,i)=>{ if(!el) return; const cur=new Date(from); cur.setDate(from.getDate()+i);
        el.parentElement?.classList.toggle('bg-indigo-50', ymdLocal(cur)===todayStr);
      });
    }

    let anchor = new Date();
    let inflight = false;
    async function loadWeek(){
      if (inflight) return;
      inflight = true;
      const from = startOfWeek(anchor);
      const to   = endOfWeek(anchor);
      rangeEl.textContent = `${fmtPretty(from)} – ${fmtPretty(to)}`;
      try{
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('from', ymdLocal(from));
        url.searchParams.set('to',   ymdLocal(to));
        const res = await fetch(url, { headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }});
        const data = await res.json();
        for (let i=0;i<7;i++){
          const cur = new Date(from); cur.setDate(from.getDate()+i);
          const key = ymdLocal(cur); const n = data?.counts?.[key] ?? 0;
          animateNumber(cntEls[i], n);
        }
        document.getElementById('calSkeleton')?.remove();
        highlightToday(cntEls, from);
      }catch(e){
        cntEls.forEach(el => { if (el) el.textContent = '—'; });
        if (window.Swal) Swal.fire({toast:true, position:'top-end', icon:'warning', title:'Couldn’t load weekly counts', timer:1800, showConfirmButton:false});
      } finally {
        inflight = false;
      }
    }
    prevBtn?.addEventListener('click',()=>{ anchor.setDate(anchor.getDate()-7); loadWeek(); });
    nextBtn?.addEventListener('click',()=>{ anchor.setDate(anchor.getDate()+7); loadWeek(); });
    todayBtn?.addEventListener('click',()=>{ anchor=new Date(); loadWeek(); });
    loadWeek(); setInterval(loadWeek, 30000);
  })();

  /* ===== Risk chips + history modal ===== */
  (() => {
    const chips = document.querySelectorAll('.riskChip');
    const pill  = document.getElementById('riskPill');
    const txt   = document.getElementById('riskPillText');
    if (!chips.length || !pill || !txt) return;

    const csrf = @json(csrf_token());
    const endpoint = @json(route('admin.chatbot-sessions.setRisk', $session->id));
    const current = () => (txt.textContent || '').trim().toLowerCase();
    const clsFor = lvl => ({
      high:     'bg-rose-100 text-rose-700 ring-rose-200',
      moderate: 'bg-amber-100 text-amber-800 ring-amber-200',
      low:      'bg-emerald-100 text-emerald-800 ring-emerald-200',
      _:        'bg-slate-100 text-slate-700 ring-slate-200',
    }[lvl] || 'bg-slate-100 text-slate-700 ring-slate-200');
    const scoreMap = { high:90, moderate:60, low:20 };

    function applyPill(level){
      [
        'bg-rose-100','text-rose-700','ring-rose-200',
        'bg-amber-100','text-amber-800','ring-amber-200',
        'bg-emerald-100','text-emerald-800','ring-emerald-200',
        'bg-slate-100','text-slate-700','ring-slate-200'
      ].forEach(c => pill.classList.remove(c));
      pill.classList.add(...clsFor(level).split(' '));
      txt.textContent = level.charAt(0).toUpperCase() + level.slice(1);
      const img = pill.querySelector('img');
      if (level === 'high') {
        if (!img) {
          const i = document.createElement('img');
          i.src = @json(asset('images/icons/alert.png'));
          i.alt = 'High';
          i.className = 'w-3.5 h-3.5 object-contain';
          pill.insertBefore(i, txt);
        }
      } else if (img) { img.remove(); }
    }

    async function choose(level){
      const prev = current();
      if (level === prev) return;

      let note = '';
      const demotion = (prev === 'high') && (level === 'moderate' || level === 'low');

      if (demotion) {
        if (window.Swal) {
          const { value } = await Swal.fire({
            title: 'Downgrade risk level?',
            html: `
              <p class="text-slate-600 mb-3">This session was previously flagged. Please record a brief reason for your professional judgement.</p>
              <textarea id="riskNote" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" rows="4" placeholder="Reason (required)"></textarea>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Save change',
            didOpen: () => setTimeout(() => document.getElementById('riskNote')?.focus(), 0),
            preConfirm: () => {
              const v = (document.getElementById('riskNote')?.value || '').trim();
              if (!v) { Swal.showValidationMessage('Reason is required.'); return false; }
              return v;
            }
          });
          if (!value) return;
          note = value;
        } else {
          note = prompt('Reason for downgrade (required):') || '';
          if (!note.trim()) return;
        }
      }

      chips.forEach(c => c.setAttribute('aria-pressed', c.dataset.level === level ? 'true' : 'false'));
      applyPill(level);

      const fd = new FormData();
      fd.append('_token', csrf);
      fd.append('_method', 'PATCH');
      fd.append('risk_level', level);
      fd.append('risk_score', String(scoreMap[level] ?? 0));
      if (note) fd.append('risk_note', note);

      try{
        const res = await fetch(endpoint, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        if (!res.ok) {
          let msg = 'Update failed.';
          try { const j = await res.json(); if (j?.message) msg = j.message; } catch {}
          throw new Error(msg);
        }
        if (window.Swal) Swal.fire({toast:true, position:'top-end', icon:'success', title:'Risk updated', timer:1200, showConfirmButton:false});
        setTimeout(() => window.location.reload(), 250);
      }catch(e){
        chips.forEach(c => c.setAttribute('aria-pressed', c.dataset.level === current() ? 'true' : 'false'));
        if (window.Swal) Swal.fire({icon:'error', title:'Update failed', text:String(e.message||e)});
        applyPill(current());
      }
    }

    chips.forEach(btn => btn.addEventListener('click', () => choose(btn.dataset.level)));
    window.addEventListener('keydown', (e) => {
      if (['INPUT','TEXTAREA'].includes((e.target?.tagName||'').toUpperCase())) return;
      if (e.key.toLowerCase() === 'l') choose('low');
      if (e.key.toLowerCase() === 'm') choose('moderate');
      if (e.key.toLowerCase() === 'h') choose('high');
    });

    const modal = document.getElementById('riskHistoryModal');
    document.getElementById('btnRiskHistory')?.addEventListener('click', () => { if (modal) modal.classList.remove('hidden'); });
    document.getElementById('riskHistoryClose')?.addEventListener('click', () => { if (modal) modal.classList.add('hidden'); });
    document.getElementById('riskHistoryClose2')?.addEventListener('click', () => { if (modal) modal.classList.add('hidden'); });
  })();

  /* ===== High-risk sensitive unlock (second 2FA) ===== */
  (() => {
    const locked = @json($sensitiveLocked ?? false);
    const isHighRisk     = @json($isHighRisk);
    const canBook        = @json($canBook);
    const canMoveEarlier = @json($canMoveEarlier);
    if (!locked) return;

    const hrUnlock  = document.getElementById('hrUnlock');
    const card      = document.getElementById('hrCard');

    const modal   = document.getElementById('hrModal');
    const overlay = modal?.firstElementChild;
    const panel   = modal?.lastElementChild;
    const close   = document.getElementById('hrClose');
    const cancel  = document.getElementById('hrCancel');
    const form    = document.getElementById('hrForm');
    const pwd     = document.getElementById('hrPassword');
    const err     = document.getElementById('hrErr');
    const submit  = document.getElementById('hrSubmit');
    const spin    = document.getElementById('hrSpin');
    const label   = document.getElementById('hrLabel');
    const eyeBtn  = document.getElementById('hrToggle');
    const eyeImg  = document.getElementById('hrEye');
    const eyeOn   = @json(asset('images/icons/eye.png'));
    const eyeOff  = @json(asset('images/icons/eye-off.png'));

    const epConfirm   = @json(route('admin.reauth.confirm_sensitive'));
    const epSensitive = @json(route('admin.chatbot-sessions.sensitive', $session->id));

    if (hrUnlock) hrUnlock.style.zIndex = '10';

    function open(){
      if (!modal || !overlay || !panel) return;
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        overlay.classList.remove('opacity-0');
        panel.classList.remove('opacity-0','scale-95','translate-y-2');
        setTimeout(() => pwd?.focus(), 10);
      });
    }
    function closeModal(){
      if (!modal || !overlay || !panel) return;
      overlay.classList.add('opacity-0');
      panel.classList.add('opacity-0','scale-95','translate-y-2');
      setTimeout(() => {
        modal.classList.add('hidden');
        err?.classList.add('hidden'); if (err) err.textContent = '';
        if (pwd) pwd.value = '';
      }, 180);
    }
    function busy(b){
      if (!submit || !spin || !label) return;
      submit.disabled = b;
      if (b){ spin.classList.remove('hidden'); label.textContent = 'Verifying…'; }
      else  { spin.classList.add('hidden');     label.textContent = 'Confirm'; }
    }
    function shake(el){ if(!el) return; el.classList.remove('ux-shake'); void el.offsetWidth; el.classList.add('ux-shake'); }

    eyeBtn?.addEventListener('click', () => {
      if (!pwd || !eyeImg) return;
      const isPw = pwd.getAttribute('type') === 'password';
      pwd.setAttribute('type', isPw ? 'text' : 'password');
      eyeImg.src = isPw ? eyeOff : eyeOn; eyeImg.alt = isPw ? 'Hide' : 'Show';
    });

    hrUnlock?.addEventListener('click', open);
    document.getElementById('btnShowAllHRLocked')?.addEventListener('click', open);
    close?.addEventListener('click', closeModal);
    cancel?.addEventListener('click', closeModal);
    overlay?.addEventListener('click', closeModal);

    async function fetchAllHighRisk() {
      try {
        const res = await fetch(epAllHR, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        const data = await res.json().catch(()=> ({}));
        if (!res.ok || !data?.ok) throw new Error(data?.message || 'Failed to load.');
        return data.items || [];
      } catch { return []; }
    }

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (err){ err.classList.add('hidden'); err.textContent = ''; }
      busy(true);

      try {
        // step 1: sensitive confirm
        const fd  = new FormData(form);
        let res   = await fetch(epConfirm, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
        let data  = await res.json().catch(()=> ({}));
        if (!res.ok || !data?.ok) {
          const msg = data?.message || 'Verification failed.';
          if (window.Swal) Swal.fire({ icon:'error', title:'Try again', text: msg });
          else { if (err){ err.textContent = msg; err.classList.remove('hidden'); } shake(panel); }
          busy(false); return;
        }

        // step 2: fetch sensitive text (latest trigger)
        res  = await fetch(epSensitive, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        data = await res.json().catch(()=> ({}));
        if (!res.ok || !data?.ok) {
          const msg = data?.message || 'Could not load sensitive details.';
          if (window.Swal) Swal.fire({ icon:'error', title:'Error', text: msg });
          else { if (err){ err.textContent = msg; err.classList.remove('hidden'); } shake(panel); }
          busy(false); return;
        }

        // step 3: fetch ALL matched high-risk lines (for expandable list)
        const allHighRiskItems = await fetchAllHighRisk();

        // step 4: render into card (show latest + controls)
        if (card){
          const listItems = Array.isArray(allHighRiskItems) ? allHighRiskItems : [];

          const listHtml = `
            <div class="mt-3">
              <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">
                  All high-risk / critical lines
                  ${listItems.length ? `<span class="ml-1 text-slate-500 font-normal">(${listItems.length})</span>` : ''}
                </div>
                <button id="btnShowAllHR" type="button"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                        data-state="closed" aria-expanded="false" aria-controls="hrAllList"
                        data-preloaded="1">
                  ${listItems.length ? `View all (${listItems.length})` : 'View all'}
                </button>
              </div>

              <div id="hrAllList" class="mt-2 space-y-2 hidden">
                ${listItems.map(item => `
                  <div class="rounded-xl ring-1 ring-slate-200 p-3 bg-white">
                    <div class="text-[12px] text-slate-500">
                      ${item.at || ''}
                      ${item.sender ? `<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] ring-1 ${
                          String(item.sender).toLowerCase()==='user'
                            ? 'bg-rose-50 text-rose-700 ring-rose-200'
                            : 'bg-slate-50 text-slate-700 ring-slate-200'
                        }">${item.sender}</span>` : ''}
                    </div>
                    <div class="mt-1 text-slate-900">#${item.id}</div>
                    <blockquote class="mt-1 text-sm text-slate-800 leading-relaxed">
                      ${(item.text||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
                    </blockquote>
                  </div>
                `).join('')}

                <div id="hrActionsWrap" class="mt-3 ${ (listItems.length && isHighRisk && (canBook || canMoveEarlier)) ? '' : 'hidden' }">
                  <div class="no-print flex flex-wrap items-center gap-2">
                    ${ canBook ? `
                      <button id="btnBookHR" type="button"
                              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">
                        Book urgent appointment
                      </button>` : '' }
                    ${ canMoveEarlier ? `
                      <button id="btnReschedHR" type="button"
                              class="inline-flex items-center gap-2 rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">
                        Move to earlier slot
                      </button>` : '' }
                    <div id="hrActionMsg" class="text-sm text-slate-600"></div>
                  </div>
                </div>
              </div>
            </div>
          `;

          card.innerHTML = `
            <div class="flex items-start gap-2.5">
              <img src="{{ asset('images/icons/alert.png') }}" alt="High risk" class="h-5 w-5 mt-0.5 object-contain" />
              <div class="flex-1">
                <div class="font-semibold text-rose-700">High-risk trigger (student message)</div>
                <div class="mt-0.5 text-xs text-rose-800/80">
                  ${data.at ? data.at : ''} ${data.id ? `<span class="ml-1 opacity-70">• Chat ID: #${data.id}</span>` : ''}
                </div>

                <!-- Latest trigger -->
                <blockquote class="mt-1.5 rounded-xl bg-white ring-1 ring-rose-100 p-3 text-sm text-slate-800">
                  “${(data.txt || '').replace(/</g,'&lt;').replace(/>/g,'&gt;')}”
                </blockquote>

                <!-- Upcoming appointment banner (rendered on unlock if available) -->
                ${
                  NEXT_APPT
                    ? `
                      <div id="js-nextAppt">
                        <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 p-3 text-sm">
                          <div class="font-medium">Upcoming appointment ${NEXT_APPT.rel_label}</div>
                          <div class="text-xs opacity-80">
                            ${NEXT_APPT.date_label} • ${NEXT_APPT.time_label}${NEXT_APPT.counselor_name ? ' • ' + NEXT_APPT.counselor_name : ''}
                          </div>
                        </div>
                      </div>
                    `
                    : `<div id="js-nextAppt" class="hidden"></div>`
                }

                <!-- Already-booked chip -->
                ${
                  HAS_ACTIVE
                    ? `
                      <div id="js-hasActiveMsg" class="mt-2">
                        <div class="inline-flex items-center rounded-lg bg-slate-100 text-slate-700 text-xs px-3 py-1.5">
                          You’ve booked an appointment already
                        </div>
                      </div>
                    `
                    : `<div id="js-hasActiveMsg" class="mt-2 hidden"></div>`
                }

                ${listHtml}
              </div>
            </div>
          `;
          card.classList.remove('border-slate-200','bg-white');
          card.classList.add('border-rose-200','bg-rose-50');

          // Sync header pill right after unlock
          const hdr = document.getElementById('hdrUpcomingPill');
          if (hdr && NEXT_APPT) {
            hdr.textContent = `Upcoming appt: ${NEXT_APPT.date_label} • ${NEXT_APPT.time_label}`;
            hdr.classList.remove('hidden');
          }

          wireHRListToggle(card);
        }

        if (window.Swal) {
          Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Unlocked', timer:900, showConfirmButton:false });
        }
        closeModal();
      } catch (ex) {
        const msg = ex?.message || String(ex) || 'Something went wrong.';
        if (window.Swal) Swal.fire({ icon:'error', title:'Error', text: msg });
        else { if (err){ err.textContent = msg; err.classList.remove('hidden'); } shake(panel); }
      } finally {
        busy(false);
      }
    });
  })();
</script>

{{-- ===== Styles ===== --}}
<style>

  #js-nextAppt { transition: opacity .25s ease; opacity: 1; }
/* Busy (occupied) slot = red outline, light background */
.time-pill--busy{
  position: relative;
  background:#fff5f5;
  border-color:#fecaca;   /* red-200 */
  color:#b91c1c;          /* red-700 */
}
.time-pill--busy::before{
  content:"✕";
  font-weight:700;
  margin-right:.4rem;
}

/* Current appointment time = neutral/gray with badge */
.time-pill--current{
  position:relative;
  background:#f8fafc;
  border-color:#cbd5e1;   /* slate-300 */
  color:#334155;          /* slate-700 */
}
.time-pill-badge{
  margin-left:.5rem;
  font-size:.65rem; line-height:1;
  padding:.2rem .35rem;
  border-radius:.375rem;
  background:#e2e8f0;     /* slate-200 */
  color:#334155;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.02em;
}
.time-pill-badge--danger{
  margin-left:.5rem;
  font-size:.65rem; line-height:1;
  padding:.2rem .35rem;
  border-radius:.375rem;
  background:#fee2e2;   /* red-200 */
  color:#b91c1c;        /* red-700 */
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.02em;
}

  /* Equal-size pill grid */
.time-pills{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
  gap:.5rem;
}

/* Time slot pills */
.time-pill{
  width:100%;
  display:inline-flex; align-items:center; justify-content:center;
  padding:.55rem .6rem; border-radius:.75rem;
  font-size:.875rem; font-weight:600; line-height:1;
  background:#fff; color:#0f172a; border:1px solid #e5e7eb;
  transition:background .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
}
.time-pill:hover{ background:#f8fafc; border-color:#cbd5e1; }
.time-pill[aria-disabled="true"]{ opacity:.55; cursor:not-allowed; }
.time-pill--active{
  background:#4f46e5; color:#fff; border-color:#4f46e5;
  box-shadow:0 0 0 2px rgba(99,102,241,.30);
}
.time-pill--ref{
  background:#eef2ff; color:#3730a3; border-color:#c7d2fe;
}
.time-pill:active{ transform:scale(.98); }

/* Booking calendar mini-UI */
.bk-day{
  width: 2.4rem; height: 2.4rem; border-radius: 9999px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 600; background: #fff; color:#0f172a;
  border:1px solid #e5e7eb; transition: .15s ease;
}
.bk-day:hover{ background:#eef2ff; border-color:#c7d2fe; }
.bk-day--selected{ background:#4f46e5; color:#fff; border-color:#4f46e5; box-shadow:0 0 0 2px rgba(99,102,241,.35); }
.bk-day--disabled{ background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb; cursor:not-allowed; }
.bk-day--disabled:hover{ background:#f3f4f6; }

/* Collapsing container */
.hr-collapse{ overflow:hidden; will-change:height; }
/* Optional: staggered children on expand */
.hr-stagger > *{ opacity:0; transform:translateY(6px); }
@media (prefers-reduced-motion: reduce){
  .hr-collapse, .hr-stagger > *{ transition:none !important; }
}

#hrUnlock span { transform: translateY(2px); opacity: .95; }
#hrUnlock:hover span { transform: translateY(0); opacity: 1; transition: all .15s ease; }
.card{ box-shadow:0 1px 2px rgba(15,23,42,.06),0 1px 1px rgba(15,23,42,.04); }

.kpi{ min-height:132px; }
.hr-card{  min-height:150px; }
.emo-card{ min-height:150px; }

:root{  --risk-kpi-height-lg: 292px; }
@media (min-width:1024px){
  .kpi-grid > .risk-card{ height: var(--risk-kpi-height-lg); }
}

.seg{ display:flex; flex-wrap:wrap; background:#fff; }
.seg-btn{
  flex:1 1 33.333%;
  display:flex; align-items:center; justify-content:center; gap:.5rem;
  padding:.48rem .68rem; font-size:.9rem; min-width:0; background:#fff; transition:background .15s ease;
  border-right:1px solid rgba(15,23,42,.08);
}
.seg-btn:nth-child(3n){ border-right:none; }
.seg-btn:nth-child(n+4){ border-top:1px solid rgba(15,23,42,.08); }
.seg-btn:hover{ background:#F8FAFC; }
.seg-btn:focus-visible{ outline:none; box-shadow:0 0 0 2px rgba(99,102,241,.55) inset; }
.seg-active{ background:#F1F5F9; box-shadow:inset 0 0 0 2px rgba(99,102,241,.25); }
.seg-btn span.truncate{ max-width:7.5rem; }

.note-1line{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

@media print{
  body *{ visibility:hidden !important; }
  #sessionPrintable, #sessionPrintable *{ visibility:visible !important; }
  #sessionPrintable{ position:fixed; inset:0; margin:12mm !important; background:#fff; }
  .shadow-sm{ box-shadow:none !important; } .ring-1{ box-shadow:none !important; }
  .no-print{ display:none !important; }
  @page{ size:A4; margin:12mm 14mm; }
}

.risk-card .risk-hint{
  font-size:11px; line-height:1.25rem; color:rgb(100 116 139);
  white-space:normal; overflow:visible; text-overflow:clip;
}
.risk-card .risk-hint .kbd{
  padding:0.125rem 0.25rem; border:1px solid rgb(203 213 225); border-radius:0.25rem;
}

.risk-card .risk-last{
  border:1px solid rgb(226 232 240);
  background:rgb(248 250 252);
  border-radius:0.75rem;
  padding:0.5rem;
}
.risk-card .risk-last .risk-meta{
  margin-top:0.125rem; font-size:10.5px; color:rgb(100 116 139);
}
.risk-card .risk-last .risk-note{
  margin-top:0.25rem; font-size:12px; color:rgb(71 85 105);
}
.risk-card .line-clamp-2{
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}

.risk-card .risk-extras{ margin-top:.25rem; }
.risk-card .risk-meter-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:.25rem; }
.risk-card .risk-meter-title{ font-size:12px; font-weight:600; color:rgb(15 23 42); }
.risk-card .risk-meter-bar{
  position:relative; height:8px; border-radius:9999px; overflow:hidden;
  background:linear-gradient(90deg,
    rgba(16,185,129,.22) 0% , rgba(16,185,129,.22) 33.33%,
    rgba(245,158,11,.22) 33.34% , rgba(245,158,11,.22) 66.66%,
    rgba(244,63,94,.22) 66.67% , rgba(244,63,94,.22) 100%
  );
}
.risk-card .risk-meter-pin{
  position:absolute; top:50%; transform:translate(-50%, -50%);
  width:0; height:0; border-left:6px solid transparent; border-right:6px solid transparent;
  border-top:8px solid rgb(51 65 85);
  filter:drop-shadow(0 1px 1px rgba(0,0,0,.15));
}
.risk-card .risk-meter-legend{
  display:flex; justify-content:space-between; margin-top:.25rem;
  font-size:10.5px; color:rgb(100 116 139);
}
.risk-card .risk-meter-legend .leg-low::before,
.risk-card .risk-meter-legend .leg-mod::before,
.risk-card .risk-meter-legend .leg-high::before{
  content:''; display:inline-block; width:.5rem; height:.5rem; border-radius:9999px; margin-right:.35rem; vertical-align:middle;
}
.risk-card .risk-meter-legend .leg-low::before{ background:rgb(16 185 129); }
.risk-card .risk-meter-legend .leg-mod::before{ background:rgb(245 158 11); }
.risk-card .risk-meter-legend .leg-high::before{ background:rgb(244 63 94); }

@keyframes shake { 0%,100%{transform:translateX(0)}15%{transform:translateX(-6px)}30%{transform:translateX(5px)}45%{transform:translateX(-4px)}60%{transform:translateX(3px)}75%{transform:translateX(-2px)}90%{transform:translateX(1px)}}
.ux-shake{animation:shake .35s ease-in-out}
</style>
@endsection
