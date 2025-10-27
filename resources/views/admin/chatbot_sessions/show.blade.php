{{-- resources/views/admin/chatbot_sessions/show.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Chatbot Details')
@section('page_title', 'Chatbot Session Summary')

@section('content')
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

  // ---- Booking rules (may be used elsewhere later)
  $canBook        = $isHighRisk && !$hasAnyActiveForStudent && !$hasCompletedForThisSession;
  $wasExpedited   = (bool) ($wasExpedited ?? ($session->expedited_at ?? false));
  $canMoveEarlier = $isHighRisk && $hasAnyActiveForStudent && !$hasCompletedForThisSession && !$wasExpedited;

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
        @if(!empty($nextAppt))
          @php
            $__start   = \Carbon\Carbon::parse($nextAppt->scheduled_at);
            $__minutes = now()->diffInMinutes($__start, false);
            $__pillClr = $__minutes <= 60*24 ? 'bg-amber-100 text-amber-800 ring-amber-200'
                                            : 'bg-emerald-100 text-emerald-800 ring-emerald-200';
          @endphp
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 {{ $__pillClr }}">
            Upcoming appt: {{ $__start->format('M d, Y • h:i A') }}
          </span>
        @endif
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
      <div class="mt-2" role="group" aria-label="Set risk level">
        <div class="seg w-full rounded-xl ring-1 ring-slate-200 overflow-hidden">
          @foreach([['low','Low','bg-emerald-600'],['moderate','Moderate','bg-amber-500'],['high','High','bg-rose-600']] as [$val,$lab,$dot])
            @php $active = ($riskStr === $val) || ($val==='high' && in_array($riskStr,['high','high-risk','high_risk'])); @endphp
            <button type="button" class="riskChip seg-btn {{ $active ? 'seg-active' : '' }}"
              data-level="{{ $val }}" aria-pressed="{{ $active ? 'true' : 'false' }}" title="{{ $lab }}">
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
                <div class="font-semibold text-rose-700">High-risk trigger (student message)</div>
                <div class="mt-0.5 text-xs text-rose-800/80">
                  {{ isset($highRisk)
                        ? \Carbon\Carbon::parse($highRisk->sent_at ?? $highRisk->created_at)->format('F d, Y • h:i A')
                        : (isset($hrTime) ? $hrTime->format('F d, Y • h:i A') : '') }}
                  @if(isset($highRisk->id) && $highRisk->id)
                    <span class="ml-1 opacity-70">• Chat ID: #{{ $highRisk->id }}</span>
                  @endif
                </div>
                <blockquote class="mt-1.5 rounded-xl bg-white ring-1 ring-rose-100 p-3 text-sm text-slate-800">
                  “{{ $highRisk->text ?? $hrText ?? '' }}”
                </blockquote>

                @php $hrCount = is_countable($allHighRisk ?? []) ? count($allHighRisk) : 0; @endphp
                @if($hrCount > 0)
                  <div class="mt-3">
                    <div class="flex items-center justify-between">
                      <div class="text-sm font-semibold text-slate-900">
                        All high-risk / critical lines
                        <span class="ml-1 text-slate-500 font-normal">({{ $hrCount }})</span>
                      </div>
                      <button
                        id="btnShowAllHR"
                        type="button"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                        data-state="closed"
                        aria-expanded="false"
                        aria-controls="hrAllList">
                        View all ({{ $hrCount }})
                      </button>
                    </div>

                    <div id="hrAllList" class="mt-2 space-y-2 hidden">
                      @foreach($allHighRisk as $hr)
                        <div class="rounded-xl ring-1 ring-slate-200 p-3 bg-white">
                          <div class="text-[12px] text-slate-500">
                            {{ $hr['at'] ?? '' }}
                            @if(!empty($hr['sender']))
                              <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] ring-1 {{ strtolower($hr['sender'])==='user' ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                {{ $hr['sender'] }}
                              </span>
                            @endif
                          </div>
                          <div class="mt-1 text-slate-900">#{{ $hr['id'] }}</div>
                          <blockquote class="mt-1 text-sm text-slate-800 leading-relaxed">{{ $hr['text'] }}</blockquote>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif
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
                    title="Click to unlock">
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
        <h3 class="text-sm font-semibold text-slate-900">Emotions Mentioned</h3>
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
  /* ===== View/Hide all high-risk items (animated) ===== */
  function wireHRListToggle(scope){
    const root = scope || document;
    const btn  = root.getElementById?.('btnShowAllHR') || root.querySelector?.('#btnShowAllHR');
    const list = root.getElementById?.('hrAllList')    || root.querySelector?.('#hrAllList');

    if (!btn || !list) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const closedLabel = btn.dataset.closedLabel || (btn.textContent || 'View all');
    const openLabel   = btn.dataset.openLabel   || 'Hide';

    list.classList.add('hr-collapse','hr-stagger');
    list.classList.add('hidden');

    function expand(el){
      el.classList.remove('hidden');
      if (reduced) return;

      el.style.height = '0px';
      el.style.opacity = '0';
      el.style.transform = 'translateY(4px)';
      el.getBoundingClientRect();
      const h = el.scrollHeight;

      el.style.transition = 'height 240ms cubic-bezier(.2,.65,.3,1), opacity 200ms ease, transform 200ms ease';
      el.style.height = h + 'px';
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';

      const kids = Array.from(el.children);
      kids.forEach((k,i) => {
        k.style.transition = 'opacity 220ms ease, transform 220ms ease';
        k.style.transitionDelay = (80 + i*25) + 'ms';
        k.style.opacity = '1';
        k.style.transform = 'translateY(0)';
      });

      el.addEventListener('transitionend', function tidy(e){
        if (e.propertyName === 'height'){
          el.style.height = '';
          el.style.transition = '';
          el.style.opacity = '';
          el.style.transform = '';
          kids.forEach(k => { k.style.transition=''; k.style.transitionDelay=''; });
          el.removeEventListener('transitionend', tidy);
        }
      });
    }

    function collapse(el){
      if (reduced){ el.classList.add('hidden'); return; }
      const kids = Array.from(el.children);
      kids.forEach(k => {
        k.style.transition = 'opacity 180ms ease, transform 180ms ease';
        k.style.transitionDelay = '0ms';
        k.style.opacity = '0';
        k.style.transform = 'translateY(6px)';
      });

      const h = el.scrollHeight;
      el.style.height = h + 'px';
      el.getBoundingClientRect();
      el.style.transition = 'height 220ms cubic-bezier(.2,.65,.3,1), opacity 160ms ease, transform 160ms ease';
      el.style.height = '0px';
      el.style.opacity = '0';
      el.style.transform = 'translateY(4px)';

      el.addEventListener('transitionend', function tidy(e){
        if (e.propertyName === 'height'){
          el.classList.add('hidden');
          el.style.height = '';
          el.style.transition = '';
          el.style.opacity = '';
          el.style.transform = '';
          kids.forEach(k => { k.style.transition=''; k.style.transitionDelay=''; });
          el.removeEventListener('transitionend', tidy);
        }
      });
    }

    btn.setAttribute('data-state','closed');
    btn.setAttribute('aria-expanded','false');
    btn.textContent = closedLabel;

    btn.addEventListener('click', () => {
      const isOpen = btn.getAttribute('data-state') === 'open';
      if (isOpen){
        collapse(list);
        btn.setAttribute('data-state','closed');
        btn.setAttribute('aria-expanded','false');
        btn.textContent = closedLabel;
      } else {
        expand(list);
        btn.setAttribute('data-state','open');
        btn.setAttribute('aria-expanded','true');
        btn.textContent = openLabel;
      }
    });
  }

  // wire once for server-rendered content (works whether DOM is already ready or not)
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
    const epAll       = @json(route('admin.chatbot-sessions.highrisk_all', $session->id));

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
        const res = await fetch(epAll, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
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

        // step 4: render into card (show only latest; others hidden until "View all")
        if (card){
          const listItems = Array.isArray(allHighRiskItems) ? allHighRiskItems : [];

          const listHtml = listItems.length ? `
            <div class="mt-3">
              <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">
                  All high-risk / critical lines
                  <span class="ml-1 text-slate-500 font-normal">(${listItems.length})</span>
                </div>
                <button id="btnShowAllHR" type="button"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                        data-state="closed" aria-expanded="false" aria-controls="hrAllList">
                  View all (${listItems.length})
                </button>
              </div>
              <div id="hrAllList" class="mt-2 space-y-2 hidden">
                ${listItems.map(item => `
                  <div class="rounded-xl ring-1 ring-slate-200 p-3 bg-white">
                    <div class="text-[12px] text-slate-500">
                      ${item.at || ''}
                      ${item.sender ? `<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] ring-1 ${String(item.sender).toLowerCase()==='user'?'bg-rose-50 text-rose-700 ring-rose-200':'bg-slate-50 text-slate-700 ring-slate-200'}">${item.sender}</span>` : ''}
                    </div>
                    <div class="mt-1 text-slate-900">#${item.id}</div>
                    <blockquote class="mt-1 text-sm text-slate-800 leading-relaxed">
                      ${(item.text||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
                    </blockquote>
                  </div>
                `).join('')}
              </div>
            </div>
          ` : '';

          card.innerHTML = `
            <div class="flex items-start gap-2.5">
              <img src="{{ asset('images/icons/alert.png') }}" alt="High risk" class="h-5 w-5 mt-0.5 object-contain" />
              <div class="flex-1">
                <div class="font-semibold text-rose-700">High-risk trigger (student message)</div>
                <div class="mt-0.5 text-xs text-rose-800/80">
                  ${data.at ? data.at : ''} ${data.id ? `<span class="ml-1 opacity-70">• Chat ID: #${data.id}</span>` : ''}
                </div>

                <!-- Only the latest trigger is shown initially -->
                <blockquote class="mt-1.5 rounded-xl bg-white ring-1 ring-rose-100 p-3 text-sm text-slate-800">
                  “${(data.txt || '').replace(/</g,'&lt;').replace(/>/g,'&gt;')}”
                </blockquote>

                ${listHtml}
              </div>
            </div>
          `;
          card.classList.remove('border-slate-200','bg-white');
          card.classList.add('border-rose-200','bg-rose-50');

          // re-wire the toggle inside the freshly replaced card
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
