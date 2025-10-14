{{-- resources/views/admin/chatbot_sessions/show.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Chatbot Details')
@section('page_title', 'Chatbot Session Summary')

@section('content')
{{-- ─────────────────────────────────────────────────────────────────────────────
   Prepare data (emotions, risk flags, codes)
────────────────────────────────────────────────────────────────────────────── --}}
@php
  // Normalize emotions to counts: {"sad":3,"tired":2,"anxious":1}
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
  arsort($counts);                 // highest first
  $total = array_sum($counts);
  $top   = array_slice($counts, 0, 6, true); // show up to 6 badges here

  // Code + risk flags
  $codeYear = $session->created_at?->format('Y') ?? now()->format('Y');
  $code     = 'LMC-' . $codeYear . '-' . str_pad($session->id, 4, '0', STR_PAD_LEFT);

  $isHighRisk = in_array(strtolower((string)($session->risk_level ?? $session->risk ?? '')), ['high','high-risk','high_risk'], true)
                || (int)($session->risk_score ?? 0) >= 80;

  // Book only if high-risk AND no active appt AND no completed after this session
  $canBook = $isHighRisk && !$hasAnyActiveForStudent && !$hasCompletedForThisSession;

  // One-time reschedule protection
  $wasExpedited  = (bool) ($wasExpedited ?? ($session->expedited_at ?? false));
  // Move earlier only if: high-risk + has active appt + not yet completed + not already expedited
  $canMoveEarlier = $isHighRisk && $hasAnyActiveForStudent && !$hasCompletedForThisSession && !$wasExpedited;
@endphp

<div class="max-w-5xl mx-auto p-6 space-y-6">

  {{-- Header row --}}
  <div class="flex items-center justify-between no-print fade-in">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-800 flex items-center gap-2">
        Chatbot Session
        @if($isHighRisk)
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700">
            HIGH RISK
          </span>
        @endif

        {{-- Small "upcoming appt" pill right in the title --}}
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
      </h2>
      <div class="mt-1 text-sm text-slate-500">Manage and export a single session record</div>
    </div>

    <div class="flex items-center gap-2">
      {{-- High-risk booking / badges --}}
      @if($isHighRisk)
        @if(!empty($hasCompletedForThisSession))
          <span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12.75 11.25 15 15 9.75"/></svg>
            Appointment Completed
          </span>
        @elseif($canBook)
          <button type="button" id="btnAdminBook"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M7 2a1 1 0 0 0-1 1v1H5a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1ZM5 9h14v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V9Z"/>
            </svg>
            Book (High-Risk)
          </button>
        @elseif($canMoveEarlier && empty($nextAppt))
          <button type="button" id="btnAdminMove"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M13 5l7 7-7 7M4 5h7v14H4z"/>
            </svg>
            Move earlier (High-Risk)
          </button>
        @endif
      @endif

      {{-- Export PDF --}}
   <a href="{{ url('admin/chatbot-sessions/'.$session->id.'/pdf') }}"
   target="_blank" rel="noopener"
   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
  </svg>
  Download PDF
</a>

      {{-- Back --}}
      <a href="{{ route('admin.chatbot-sessions.index') }}"
         class="inline-flex items-center gap-2 h-10 px-4 rounded-xl bg-white text-slate-700 ring-1 ring-slate-200 shadow-sm
                hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to list
      </a>
    </div>
  </div>

  {{-- Upcoming Appointment Banner --}}
  @if(!empty($nextAppt))
    @php
      $start   = \Carbon\Carbon::parse($nextAppt->scheduled_at);
      $isSoon  = now()->diffInMinutes($start) <= 60*24; // within 24h
      $viewUrl = route('admin.appointments.show', $nextAppt->id ?? 0);
    @endphp
    <div class="no-print fade-in" style="--delay:.05s">
      <div class="mt-3 flex items-start gap-3 rounded-2xl border p-4
                  {{ $isSoon ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm.75 4.5a.75.75 0 00-1.5 0v5.25c0 .199.079.39.22.53l3.75 3.75a.75.75 0 101.06-1.06l-3.53-3.53V6.75z" clip-rule="evenodd"/>
        </svg>

        <div class="flex-1">
          <div class="font-semibold text-slate-900">
            {{ $isSoon ? 'Upcoming appointment (within 24 hours)' : 'Upcoming appointment scheduled' }}
          </div>
          <div class="mt-1 text-sm text-slate-700">
            <span class="font-medium">{{ $start->format('l, F d, Y • h:i A') }}</span>
            with <span class="font-medium">{{ $nextAppt->counselor_name ?? 'Counselor' }}</span>
            @if(!empty($nextAppt->location)) at <span class="font-medium">{{ $nextAppt->location }}</span>@endif
          </div>
          @if(!empty($nextAppt->note))
            <div class="mt-1 text-xs text-slate-600">Note: {{ $nextAppt->note }}</div>
          @endif
        </div>

        <div class="flex items-center gap-2">
          <a href="{{ $viewUrl }}"
             class="inline-flex items-center px-3 py-2 rounded-lg bg-white ring-1 ring-slate-200 hover:bg-slate-50">
            View appointment
          </a>
          <button type="button" id="btnAdminMove"
                  class="inline-flex items-center px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
            Reschedule
          </button>
        </div>
      </div>
    </div>
  @endif

  {{-- PRINTABLE AREA --}}
  <div id="sessionPrintable" class="space-y-6 print-area">

    {{-- Summary card --}}
    <div class="relative bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden fade-in" style="--delay:.08s">
      <span class="pointer-events-none accent-bar"></span>

      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="fade-in">
            <div class="text-xs text-slate-500 uppercase">Session ID</div>
            <div class="mt-1 font-semibold text-slate-900 flex items-center gap-2">
              <span id="sessionCode">{{ $code }}</span>
              <button type="button"
                      onclick="copyText('#sessionCode')"
                      class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 no-print"
                      title="Copy Session ID">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/>
                  <rect x="3" y="3" width="13" height="13" rx="2" ry="2" stroke-width="2"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="fade-in" style="--delay:.05s">
            <div class="text-xs text-slate-500 uppercase">Initial Result</div>
            <div class="md:col-span-2">
              <div class="text-xs text-slate-500 uppercase">Emotions Mentioned</div>
              <div class="mt-1">
                @if($total === 0 || empty($top))
                  <div class="text-slate-500">—</div>
                @else
                  <div class="flex flex-wrap gap-1.5">
                    @php $__i = 0; @endphp
                    @foreach($top as $name => $cnt)
                      @php
                        $pct = $total ? round($cnt / $total * 100) : 0;
                        $__i++;
                      @endphp
                      <span class="stagger inline-flex items-center rounded-full px-2 py-0.5 text-[12px] font-medium
                                   bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200"
                            style="--i: {{ $__i }}"
                            title="{{ $cnt }} mention{{ $cnt===1?'':'s' }}">
                        {{ ucfirst($name) }}
                        <span class="ml-1 text-[11px] opacity-70">({{ $pct }}%)</span>
                      </span>
                    @endforeach
                  </div>
                @endif
              </div>
            </div>
          </div>

          <div class="fade-in" style="--delay:.1s">
            <div class="text-xs text-slate-500 uppercase">Student</div>
            <div class="mt-1 font-medium text-slate-800">{{ $session->user->name ?? '—' }}</div>
          </div>

          <div class="fade-in" style="--delay:.15s">
            <div class="text-xs text-slate-500 uppercase">Initial Date</div>
            <div class="mt-1 font-medium text-slate-800">{{ $session->created_at?->format('F d, Y • h:i A') }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Session Counts --}}
    <div class="relative bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden fade-in" style="--delay:.12s">
      <div class="p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-semibold text-slate-900">Session Counts</h3>

          <div class="flex items-center gap-2 no-print">
            <button id="calPrev"
                    class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm ring-1 ring-slate-200 hover:bg-slate-50">
              ← Prev
            </button>

            <button id="calToday"
                    class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm bg-indigo-600 text-white hover:bg-indigo-700">
              Today
            </button>

            <button id="calNext"
                    class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm ring-1 ring-slate-200 hover:bg-slate-50">
              Next →
            </button>
          </div>
        </div>

        <div id="calRange" class="mt-2 text-sm text-slate-500"></div>

        <div class="mt-4 overflow-hidden rounded-xl ring-1 ring-slate-200/70">
          <div class="grid grid-cols-7 bg-slate-50/60 text-xs font-medium uppercase tracking-wide text-slate-600">
            <div class="px-3 py-2 text-center">Sun</div>
            <div class="px-3 py-2 text-center">Mon</div>
            <div class="px-3 py-2 text-center">Tue</div>
            <div class="px-3 py-2 text-center">Wed</div>
            <div class="px-3 py-2 text-center">Thu</div>
            <div class="px-3 py-2 text-center">Fri</div>
            <div class="px-3 py-2 text-center">Sat</div>
          </div>

          <div class="grid grid-cols-7 divide-x divide-slate-200/70 text-center">
            @for ($i = 0; $i < 7; $i++)
              <div class="px-3 py-6">
                <div id="cnt{{ $i }}" class="text-xl font-semibold text-slate-900">—</div>
                <div class="mt-1 text-xs text-slate-500">sessions</div>
              </div>
            @endfor
          </div>
        </div>
      </div>
    </div>
  </div> {{-- /#sessionPrintable --}}

  {{-- Footer actions --}}
  <div class="flex items-center justify-end gap-2 no-print fade-in" style="--delay:.14s">
    @if(!empty($session->user?->email))
      <a href="mailto:{{ $session->user->email }}"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white text-slate-800 ring-1 ring-slate-200 hover:bg-slate-50">
        Email Student
      </a>
    @endif

    <button type="button"
            onclick="copyText('#sessionCode')"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white text-slate-800 ring-1 ring-slate-200 hover:bg-slate-50">
      Copy Session ID
    </button>
  </div>
</div>

{{-- Inline styles for booking/reschedule pills (unchanged) --}}
<style>
  .pill {
    font-weight: 600;
    border-radius: 0.75rem;
    padding: .7rem .9rem;
    line-height: 1.1;
    box-shadow: 0 1px 0 0 rgba(2,6,23,.06), 0 0 0 1px rgba(15,23,42,.06) inset;
  }
  .pill .time { display:block; font-size:.95rem; }
  .pill .cap  { display:block; font-size:.72rem; opacity:.8; margin-top:.15rem; }
  .pill[disabled] { opacity:.5; cursor:not-allowed; }
  .pill--active { outline: 3px solid rgba(79,70,229,.8); }
  .pill:hover:not([disabled]) { background:#EEF2FF; border-color:#C7D2FE; }
  .grid-times { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  @media (min-width: 640px){ .grid-times{ grid-template-columns: repeat(4, minmax(0, 1fr)); } }
</style>

{{-- Shared helpers (copy) --}}
<script>
  function copyText(selector){
    const el = document.querySelector(selector);
    if(!el) return;
    const text = el.textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
      if (window.Swal) {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Copied', showConfirmButton:false, timer:1400 });
      }
    });
  }
</script>

@push('scripts')
<script>
/* ======================= Animations (vanilla, reduced-motion aware) ======================= */
(function(){
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Fade-in on page load (CSS handles timing)
  if(prefersReduced){
    document.querySelectorAll('.fade-in,.stagger').forEach(el=>{
      el.style.opacity = 1;
      el.style.transform = 'none';
      el.style.animation = 'none';
    });
  }

  // Mini utility: animated number
  function animateNumber(el, to){
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const start = Number(el.textContent.replace(/[^\d]/g,'')) || 0;
    const end = Number(to) || 0;
    if(prefersReduced || start === end){ el.textContent = end; return; }
    const dur = 600 + Math.min(600, Math.abs(end-start)*40);
    const t0 = performance.now();
    const ease = t => 1 - Math.pow(1 - t, 3);
    (function tick(now){
      const p = Math.min(1, (now - t0) / dur);
      const v = Math.round(start + (end - start) * ease(p));
      el.textContent = v;
      if(p < 1) requestAnimationFrame(tick);
    })(t0);
  }

  // Expose to calendar loader
  window.__animateNumber = animateNumber;
})();
</script>

{{-- Calendar counts (header mini-calendar) --}}
<script>
(() => {
  const endpoint = @json(route('admin.chatbot-sessions.calendar', $session->id));
  const rangeEl = document.getElementById('calRange');
  const prevBtn = document.getElementById('calPrev');
  const nextBtn = document.getElementById('calNext');
  const todayBtn = document.getElementById('calToday');
  const cntEls = [...Array(7)].map((_, i) => document.getElementById('cnt' + i));
  let anchor = new Date();

  const pad = n => String(n).padStart(2, '0');
  const ymdLocal = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const fmtPretty = d => d.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
  function startOfWeek(d){ const x=new Date(d); x.setHours(0,0,0,0); x.setDate(x.getDate()-x.getDay()); return x; }
  function endOfWeek(d){ const s=startOfWeek(d), e=new Date(s); e.setDate(s.getDate()+6); return e; }
  function highlightToday(cells, from){
    const todayStr = ymdLocal(new Date());
    cells.forEach((el,i)=>{ const cur=new Date(from); cur.setDate(from.getDate()+i);
      el.parentElement.classList.toggle('bg-indigo-50', ymdLocal(cur)===todayStr);
    });
  }

  async function loadWeek(){
    const from = startOfWeek(anchor);
    const to   = endOfWeek(anchor);
    rangeEl.textContent = `${fmtPretty(from)} – ${fmtPretty(to)}`;

    try{
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('from', ymdLocal(from));
      url.searchParams.set('to',   ymdLocal(to));
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      const data = await res.json();

      for (let i=0;i<7;i++){
        const cur = new Date(from); cur.setDate(from.getDate()+i);
        const key = ymdLocal(cur); const n = data?.counts?.[key] ?? 0;
        // animate each cell to its new number
        window.__animateNumber?.(cntEls[i], n);
      }
      highlightToday(cntEls, from);
    }catch(e){
      console.error(e);
      cntEls.forEach(el => el.textContent = '—');
    }
  }

  prevBtn.addEventListener('click',()=>{ anchor.setDate(anchor.getDate()-7); loadWeek(); });
  nextBtn.addEventListener('click',()=>{ anchor.setDate(anchor.getDate()+7); loadWeek(); });
  todayBtn.addEventListener('click',()=>{ anchor=new Date(); loadWeek(); });

  loadWeek();
  setInterval(loadWeek, 30000);
})();
</script>

{{-- BOOK (High-Risk) — unchanged logic --}}
<script>
(() => {
  if (window.__LUMI_BOOK_BOUND__) return;
  window.__LUMI_BOOK_BOUND__ = true;

  const btn = document.getElementById('btnAdminBook');
  if (!btn) return;

  const slotsEndpoint = @json(route('admin.chatbot-sessions.slots', $session->id));
  const bookEndpoint  = @json(route('admin.chatbot-sessions.book', $session->id));

  const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
  const TIME_RE = /^\d{2}:\d{2}$/;
  const pad = n => String(n).padStart(2,'0');
  const isWeekday = ymd => { const [y,m,d]=ymd.split('-').map(Number); const t=new Date(y,m-1,d).getDay(); return t>=1&&t<=5; };
  const notPast   = ymd => { const [y,m,d]=ymd.split('-').map(Number); const dt=new Date(y,m-1,d,23,59,59,999); const now=new Date(); return dt>=new Date(now.getFullYear(),now.getMonth(),now.getDate()); };

  function updateTotal(pooledMap){
    const el = document.getElementById('adm-total');
    if (!el) return;
    const total = Object.values(pooledMap||{}).reduce((a,b)=>a+Number(b||0),0);
    el.textContent = total ? `• ${total} total counselor-slots` : '';
  }

  function buildTimePills(container, items, pooledMap, selected=''){
    container.innerHTML = '';
    const emptyEl = document.getElementById('adm-empty');
    const times = Array.isArray(items) ? items : [];

    if (!times.length){
      emptyEl?.classList.remove('hidden');
      container.dataset.selected='';
      updateTotal(pooledMap);
      return;
    }
    emptyEl?.classList.add('hidden');
    updateTotal(pooledMap);

    times.forEach(s=>{
      const b = document.createElement('button');
      b.type='button';
      b.className='pill inline-flex flex-col items-center justify-center border border-slate-200 bg-white text-slate-800';
      b.dataset.value=s.value;

      const cap = Math.max(0, Number((pooledMap && pooledMap[s.value]) ?? s.pooled ?? 0));
      b.innerHTML = `
        <span class="time">${s.label}</span>
        <span class="cap">(${cap} ${cap === 1 ? 'slot' : 'slots'})</span>
      `;

      if (s.disabled){
        b.disabled = true;
        b.classList.add('opacity-50','cursor-not-allowed');
      }else{
        b.addEventListener('click', ()=>{
          [...container.querySelectorAll('button')].forEach(x=>x.classList.remove('pill--active'));
          b.classList.add('pill--active');
          container.dataset.selected = s.value;
          b.scrollIntoView({block:'nearest', inline:'nearest', behavior:'smooth'});
        });
        b.addEventListener('keydown', e=>{
          if (e.key==='Enter'||e.key===' '){ e.preventDefault(); b.click(); }
        });
      }

      if (!s.disabled && s.value===selected) b.classList.add('pill--active');
      container.appendChild(b);
    });

    if (selected && !times.some(t=>!t.disabled && t.value===selected)){
      container.dataset.selected='';
    }
  }

  async function loadSlots(date){
    const url = new URL(slotsEndpoint, window.location.origin);
    url.searchParams.set('date', date);
    const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    if (!res.ok) throw new Error('Failed to load slots');
    return res.json(); // { counselors, slots, pooled }
  }

  async function onAdminBookClick(){
    try{
      const today = new Date();
      const defaultDate = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;

      // Fetch BEFORE opening modal
      const first = await loadSlots(defaultDate);

      let pollId = null;

      const { value: form } = await Swal.fire({
        title: 'Book appointment',
        html: `
          <div style="text-align:left">
            <label class="text-sm font-medium text-slate-700">1) Pick date *</label>
            <input id="adm-date" type="date" value="${defaultDate}" min="{{ now()->toDateString() }}"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"/>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-slate-700">2) Counselor *</label>
                <select id="adm-counselor" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  ${(first.counselors||[]).map(c=>`<option value="${String(c.id)}">${String(c.name).replace(/</g,'&lt;')}</option>`).join('')}
                </select>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">3) Time * <small id="adm-total" class="text-slate-500 font-normal"></small></label>
                <div id="adm-times" class="mt-1 grid grid-times gap-2" data-selected="" tabindex="0" aria-label="Available times"></div>
                <div id="adm-empty" class="text-xs text-slate-500 mt-1 hidden">No available times.</div>
              </div>
            </div>
          </div>
        `,
        customClass: { popup: 'swal-wide' },
        showCancelButton: true,
        confirmButtonText: 'Confirm Booking',
        focusConfirm: false,

        didOpen: () => {
          const dateEl   = document.getElementById('adm-date');
          const counEl   = document.getElementById('adm-counselor');
          const timeWrap = document.getElementById('adm-times');

          let slotsMap  = first.slots  || {};
          let pooledMap = first.pooled || {};

          const compose = (cid, keepSelected=true)=>{
            const prevSel = keepSelected ? (timeWrap.dataset.selected || '') : '';
            const items = (slotsMap?.[cid] || []);
            buildTimePills(timeWrap, items, pooledMap, prevSel);
          };

          const refetch = async ()=>{
            const val = dateEl.value;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) { buildTimePills(timeWrap, [], {}, ''); return; }
            Swal.showLoading();
            try{
              const data = await loadSlots(val);
              slotsMap  = data.slots  || {};
              pooledMap = data.pooled || {};
              const list = data.counselors || [];
              const prev = counEl.value;
              counEl.innerHTML = list.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
              if (list.length){
                const keep = list.some(c => String(c.id)===String(prev));
                counEl.value = keep ? prev : String(list[0].id);
              }
              compose(counEl.value, true);
            } finally {
              try { Swal.hideLoading(); } catch(e){}
            }
          };

          compose(counEl.value);
          dateEl.addEventListener('change', refetch);
          counEl.addEventListener('change', ()=>compose(counEl.value, false));

          // live polling every 5s while modal is open
          pollId = setInterval(refetch, 5000);
        },

        willClose: () => {
          if (pollId) clearInterval(pollId);
          try { Swal.hideLoading(); } catch(e) {}
        },

        preConfirm: () => {
          const date = /** @type {HTMLInputElement} */(document.getElementById('adm-date')).value;
          const counselorId = /** @type {HTMLSelectElement} */(document.getElementById('adm-counselor')).value;
          const time = /** @type {HTMLElement} */(document.getElementById('adm-times')).dataset.selected || '';

          if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) { swalToast('error','Invalid date format'); return false; }
          if (!/^\d{2}:\d{2}$/.test(time)) { swalToast('error','Invalid time selected'); return false; }
          if (!counselorId)        { swalToast('warning','Please choose a counselor'); return false; }

          const buttons = Array.from(document.querySelectorAll('#adm-times button:not([disabled])')).map(b=>b.dataset.value);
          if (!buttons.includes(time)) { swalToast('info','That time just filled','Please pick another slot.'); return false; }

          return { date, counselorId, time };
        }
      });

      if (!form) return;

      const fd = new FormData();
      fd.append('_token', @json(csrf_token()));
      fd.append('date', form.date);
      fd.append('time', form.time);
      fd.append('counselor_id', form.counselorId);

      const resp = await fetch(bookEndpoint, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
      const data = await resp.json().catch(()=>({}));
      if (!resp.ok) throw new Error(data?.message || 'Booking failed.');

      await Swal.fire({
        icon:'success',
        title:'Appointment booked!',
        html:`<div class="appt-compact">${data.html}</div>`,
        customClass:{ popup:'swal-success swal-compact' },
        width: Math.min(window.innerWidth - 32, 1200),
        showCloseButton:true,
        confirmButtonText:'OK',
      });

      toastOnceSet('booked');
      window.location.reload();
    } catch (e) {
      console.error(e);
      Swal.fire({ icon:'error', title:'Unable to book', text: e.message || 'Please try again.' });
    }
  }

  btn.onclick = onAdminBookClick;
})();
</script>

{{-- MOVE EARLIER (High-Risk Reschedule) — unchanged logic --}}
<script>
(() => {
  const moveBtn = document.getElementById('btnAdminMove');
  if (!moveBtn) return;

  const slotsEndpoint      = @json(route('admin.chatbot-sessions.slots', $session->id));
  const rescheduleEndpoint = @json(route('admin.chatbot-sessions.reschedule', $session->id));

  const DATE_RE=/^\d{4}-\d{2}-\d{2}$/; const TIME_RE=/^\d{2}:\d{2}$/;
  const pad=n=>String(n).padStart(2,'0');

  async function loadSlots(date){
    const u=new URL(slotsEndpoint, window.location.origin); u.searchParams.set('date',date);
    const res=await fetch(u,{headers:{'X-Requested-With':'XMLHttpRequest'}}); if(!res.ok) throw new Error('Failed to load slots');
    return res.json();
  }

  function buildTimePills(container, items, pooledMap, selected=''){
    container.innerHTML='';
    const emptyEl=document.getElementById('adm-empty');
    const times=Array.isArray(items)?items:[];
    if(!times.length){ emptyEl?.classList.remove('hidden'); container.dataset.selected=''; return; }
    emptyEl?.classList.add('hidden');

    const total = Object.values(pooledMap||{}).reduce((a,b)=>a+Number(b||0),0);
    const totalEl = document.getElementById('adm-total');
    if (totalEl) totalEl.textContent = total ? `• ${total} total counselor-slots` : '';

    times.forEach(s=>{
      const cap=Math.max(0, Number((pooledMap && pooledMap[s.value]) ?? s.pooled ?? 0));
      const b=document.createElement('button');
      b.type='button'; b.dataset.value=s.value;
      b.className='pill inline-flex flex-col items-center justify-center border border-slate-200 bg-white text-slate-800';
      b.innerHTML=`<span class="time">${s.label}</span><span class="cap">(${cap} ${cap===1?'slot':'slots'})</span>`;
      if(s.disabled){ b.disabled=true; b.classList.add('opacity-50','cursor-not-allowed'); }
      else{
        b.addEventListener('click',()=>{
          [...container.querySelectorAll('button')].forEach(x=>x.classList.remove('pill--active'));
          b.classList.add('pill--active'); container.dataset.selected=s.value;
        });
      }
      if(!s.disabled && s.value===selected) b.classList.add('pill--active');
      container.appendChild(b);
    });

    if(selected && !times.some(t=>!t.disabled && t.value===selected)){
      container.dataset.selected='';
    }
  }

  moveBtn.addEventListener('click', async ()=>{
    const today=new Date();
    const defaultDate=`${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;

    let first;
    try{ first=await loadSlots(defaultDate); }
    catch(err){ return Swal.fire({icon:'error', title:'Unable to load slots', text:String(err)}); }

    const { value: form } = await Swal.fire({
      title: 'Move appointment earlier',
      html: `
        <div style="text-align:left">
          <label class="text-sm font-medium text-slate-700">1) Pick date *</label>
          <input id="adm-date" type="date" value="${defaultDate}" min="{{ now()->toDateString() }}"
                 class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"/>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-slate-700">2) Counselor *</label>
              <select id="adm-counselor" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                ${(first.counselors||[]).map(c=>`<option value="${String(c.id)}">${String(c.name).replace(/</g,'&lt;')}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="text-sm font-medium text-slate-700">3) Time * <small id="adm-total" class="text-slate-500 font-normal"></small></label>
              <div id="adm-times" class="mt-1 grid grid-times gap-2" data-selected="" tabindex="0" aria-label="Available times"></div>
              <div id="adm-empty" class="text-xs text-slate-500 mt-1 hidden">No available times.</div>
            </div>
          </div>
        </div>
      `,
      customClass:{ popup:'swal-wide' },
      showCancelButton:true,
      confirmButtonText:'Reschedule',
      focusConfirm:false,
      didOpen:()=>{
        const dateEl=document.getElementById('adm-date');
        const counEl=document.getElementById('adm-counselor');
        const timeWrap=document.getElementById('adm-times');

        let slotsMap=first.slots||{}; let pooledMap=first.pooled||{};

        const compose=(cid, keepSel=true)=>{
          const prev=keepSel?(timeWrap.dataset.selected||''):''; const items=(slotsMap?.[cid]||[]);
          buildTimePills(timeWrap, items, pooledMap, prev);
        };
        const refetch=async ()=>{
          const val=dateEl.value; if(!DATE_RE.test(val)){ buildTimePills(timeWrap,[],{},''); return; }
          Swal.showLoading();
          try{
            const data=await loadSlots(val);
            slotsMap=data.slots||{}; pooledMap=data.pooled||{};
            const list=data.counselors||[]; const prev=counEl.value;
            counEl.innerHTML=list.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
            if(list.length){ const keep=list.some(c=>String(c.id)===String(prev)); counEl.value=keep?prev:String(list[0].id); }
            compose(counEl.value,true);
          } finally{ try{ Swal.hideLoading(); }catch(_){ } }
        };
        compose(counEl.value);
        dateEl.addEventListener('change', refetch);
        counEl.addEventListener('change', ()=>compose(counEl.value,false));
      },
      preConfirm:()=>{
        const date=document.getElementById('adm-date')?.value||'';
        const counselorId=document.getElementById('adm-counselor')?.value||'';
        const time=document.getElementById('adm-times')?.dataset.selected||'';
        if(!DATE_RE.test(date)) return Swal.showValidationMessage('Invalid date format'), false;
        if(!TIME_RE.test(time)) return Swal.showValidationMessage('Please pick a time'), false;
        if(!counselorId)        return Swal.showValidationMessage('Please choose a counselor'), false;
        return { date, counselorId, time };
      }
    });

    if(!form) return;

    const fd=new FormData();
    fd.append('_token', @json(csrf_token()));
    fd.append('date', form.date);
    fd.append('time', form.time);
    fd.append('counselor_id', form.counselorId);

    try{
      const resp=await fetch(rescheduleEndpoint,{ method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
      const data=await resp.json().catch(()=>({}));
      if(!resp.ok) throw new Error(data?.message || 'Reschedule failed.');

      await Swal.fire({
        icon:'success',
        title:'Appointment rescheduled!',
        html:`<div class="appt-compact">${data.html}</div>`,
        customClass:{ popup:'swal-success swal-compact' },
        width: Math.min(window.innerWidth - 32, 1200),
        showCloseButton:true,
        confirmButtonText:'OK',
      });

      toastOnceSet('rescheduled');
      window.location.reload();
    }catch(e){
      Swal.fire({ icon:'error', title:'Unable to reschedule', text:String(e) });
    }
  });
})();
</script>

{{-- Toast helpers (unchanged) --}}
<script>
  function swalToast(icon, title, text='') {
    Swal.fire({ toast: true, position: 'top-end', icon, title, text, timer: 2200, showConfirmButton: false });
  }
  function toastOnceSet(key, payload = {}) {
    try { sessionStorage.setItem('__once_toast__', JSON.stringify({ key, payload, t: Date.now() })); } catch (e) {}
  }
  function toastOnceConsume() {
    try {
      const raw = sessionStorage.getItem('__once_toast__');
      if (!raw) return;
      sessionStorage.removeItem('__once_toast__');
      const { key } = JSON.parse(raw);
      if (key === 'rescheduled') swalToast('success', 'Rescheduled successfully', 'Appointment moved earlier.');
      else if (key === 'booked') swalToast('success', 'Booked successfully', 'Appointment created.');
    } catch (e) {}
  }
  document.addEventListener('DOMContentLoaded', toastOnceConsume);
</script>
@endpush

{{-- Print + micro-animations CSS (only design) --}}
<style>
  /* gradient accent line */
  .accent-bar{
    position:absolute; inset-inline:0; top:-1px; height:4px;
    background: linear-gradient(90deg, #6366f1, #a855f7, #e879f9);
    background-size: 200% 100%;
    animation: shimmer 8s linear infinite;
  }
  @keyframes shimmer{ from{ background-position:0% 0 } to{ background-position:200% 0 } }

  /* Fade/slide entrances */
  .fade-in{ opacity:0; transform: translateY(6px); animation: fadeUp .6s ease forwards; animation-delay: var(--delay, 0s); }
  .stagger{ opacity:0; transform: translateY(6px); animation: fadeUp .45s ease forwards; animation-delay: calc(var(--i,0) * 60ms); display:inline-flex; }
  @keyframes fadeUp{ to{ opacity:1; transform:none } }

  /* Respect reduced motion */
  @media (prefers-reduced-motion: reduce){
    .accent-bar{ animation: none !important; }
    .fade-in,.stagger{ opacity:1 !important; transform:none !important; animation:none !important; }
  }

  /* Print-only isolation */
  @media print{
    body *{ visibility:hidden !important; }
    .print-area, .print-area *{ visibility:visible !important; }
    .print-area{ position:fixed; inset:0; margin:12mm !important; background:#fff; }
    .shadow-sm{ box-shadow:none !important; }
    .border{ border:0 !important; }
    .no-print{ display:none !important; }
    @page{ size:A4; margin:12mm 14mm; }
  }
</style>
@endsection
