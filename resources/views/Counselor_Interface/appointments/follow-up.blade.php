@extends('layouts.counselor')
@section('title', 'Create Follow-up · Appointment #'.$appointment->id)
@section('page_title', 'Create Follow Up')

@php
  use Carbon\Carbon;
  $suggest  = isset($suggest) && is_array($suggest) ? $suggest : [];
  $sDate    = $suggest['date'] ?? now()->addWeek()->toDateString();
  $sTime    = $suggest['time'] ?? '09:00';
  $sNice    = Carbon::parse($sDate.' '.$sTime)->format('M d, Y g:i A');
@endphp

@section('content')
<div class="mx-auto max-w-4xl px-4 pt-0 pb-10 animate-fadeup">

  {{-- Header card --}}
  <div class="mb-6">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 p-6 shadow-sm">
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="rounded-2xl bg-white/15 p-2 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M7 2a1 1 0 0 0-1 1v1H5a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1ZM5 9h14v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V9Z"/>
            </svg>
          </div>
          <div class="-mt-0.5">
            <h1 class="text-lg font-semibold tracking-tight text-white">Create follow-up</h1>
            <p class="text-white/85 text-sm">
              Student: <b>{{ $appointment->student_name ?? '—' }}</b>
              @if(!empty($appointment->counselor_name))
                • Counselor: <b>{{ $appointment->counselor_name }}</b>
              @endif
            </p>
          </div>
        </div>

        <a href="{{ route('counselor.appointments.show', $appointment->id) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2.5 text-sm font-medium text-white backdrop-blur transition hover:bg-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
          Back
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

      <div class="mt-3 text-sm">
        <span class="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-white">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-white"></span>
          Suggested: <b>{{ $sNice }}</b>
        </span>
      </div>
    </div>
  </div>

  {{-- Form card --}}
  <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800/70">
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Follow-up Details</h2>

    <style>
      @keyframes slideUp { from {opacity:0; transform:translateY(10px) scale(.98);} to {opacity:1; transform:none;} }
      .modal-enter{ animation:slideUp .2s ease-out; }
      .hidden-error{ display:none !important; }

      /* date chip */
      .date-chip{ position:relative; overflow:hidden; display:inline-flex; align-items:center; gap:.5rem; padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e5e7eb; background:#fff; color:#111827; box-shadow:0 1px 1px rgba(0,0,0,.04); cursor:pointer; transition:transform .08s, border-color .15s, box-shadow .15s, background-color .15s;}
      .date-chip:hover{ border-color:#a5b4fc; box-shadow:0 2px 6px rgba(99,102,241,.15); background:#f8fafc; transform:scale(1.02); }
      .date-chip:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
      .date-chip--empty{ color:#374151; }
      .date-chip--filled{ border-color:#a5b4fc; box-shadow:0 0 0 2px rgba(99,102,241,.3); background:#eef2ff; color:#111827; }
      .chip-ripple{ position:absolute; inset:0; border-radius:inherit; overflow:hidden; pointer-events:none; }
      .chip-ripple::after{ content:""; position:absolute; left:50%; top:50%; width:0; height:0; border-radius:9999px; background:rgba(99,102,241,.18); transform:translate(-50%,-50%); animation:chipWave .4s ease-out forwards; }
      @keyframes chipWave{ from{width:0;height:0;opacity:.35;} to{width:220%;height:220%;opacity:0;} }

      /* calendar */
      :root{ --cal-size:44px; --cal-size-sm:38px; }
      @media (max-width:420px){ :root{ --cal-size:var(--cal-size-sm); } }
      .cal-shell{ background:#fff; color:#0f172a; }
      .cal-head{ display:flex; align-items:center; justify-content:center; height:calc(var(--cal-size)*.75); font-weight:700; letter-spacing:.08em; font-size:.70rem; color:#6b7280; text-transform:uppercase; }
      .cal-grid{ display:grid; grid-template-columns:repeat(7,1fr); gap:.5rem; }
      .cal-spacer{ height:var(--cal-size); }
      .cal-btn{ width:var(--cal-size); height:var(--cal-size); margin-inline:auto; display:flex; align-items:center; justify-content:center; border-radius:9999px; font-weight:700; background:#fff; color:#0f172a; border:1px solid #e5e7eb; transition:transform .12s, background-color .15s, border-color .15s, box-shadow .15s; }
      .cal-btn:hover{ background:#eef2ff; border-color:#c7d2fe; transform:translateY(-1px); }
      .cal-btn:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
      .cal-btn--today{ box-shadow:0 0 0 2px rgba(99,102,241,.28); border-color:#a5b4fc; }
      .cal-btn--selected{ background:#4f46e5; color:#fff; border-color:#4f46e5; box-shadow:0 6px 16px rgba(79,70,229,.28); }
      .cal-btn--disabled{ background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb; cursor:not-allowed; transform:none!important; }
      .cal-btn--disabled:hover{ background:#f3f4f6; }

      @keyframes calPop{0%{transform:translateY(10px) scale(.96);opacity:0;filter:blur(2px);}60%{transform:translateY(0) scale(1.012);opacity:1;filter:blur(0);}100%{transform:translateY(0) scale(1);opacity:1;}}
      .cal-animate-in{ animation:calPop .22s ease-out both; }
      .cal-backdrop-in{ animation:fadeIn .18s ease-out both; }
      @keyframes fadeIn{ from{opacity:0;} to{opacity:.6;} }

      /* ===== UNIFORM TIME PILLS ===== */
      .slot-grid{
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(140px,1fr));
        gap:12px;
      }
      .slot-pill{
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        height:64px;
        border-radius:12px;
        padding:8px 10px;
        border:1px solid #e2e8f0;      /* slate-200 */
        background:#fff;
        color:#0f172a;                  /* slate-900 */
        text-align:center;
        line-height:1.1;
        transition:background .15s, border-color .15s, box-shadow .15s, transform .08s;
      }
      .slot-pill:hover{ border-color:#6366f1; background:#eef2ff; transform:translateY(-1px); }
      .slot-pill:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
      .slot-pill.is-selected{
        border-color:#4f46e5; background:#eef2ff; box-shadow:0 0 0 3px rgba(79,70,229,.12);
      }
      .slot-pill[aria-disabled="true"]{
        opacity:.55; cursor:not-allowed; background:#f8fafc;
      }
      .slot-time{ font-weight:600; font-size:.95rem; }
      .slot-sub { font-size:11px; opacity:.75; margin-top:2px; }
    </style>

    <form method="POST" action="{{ route('counselor.appointments.follow.store', $appointment->id) }}" class="space-y-8" id="followForm">
      @csrf

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- DATE --}}
        <div class="space-y-3">
          <div class="flex items-start gap-3">
            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">1</span>
            <div>
              <p class="font-semibold text-gray-900 dark:text-gray-100">Choose a date <span class="text-rose-600">*</span></p>
              <p class="text-xs text-gray-500 dark:text-gray-400">Weekends are closed (Mon–Fri only).</p>
            </div>
          </div>

          <div>
            <button id="dateChip" type="button" class="date-chip {{ old('date', $sDate) ? 'date-chip--filled' : 'date-chip--empty' }}" aria-haspopup="dialog" aria-expanded="false">
              <img src="{{ asset('images/icons/calendar.png') }}" alt="" class="h-4 w-4 opacity-90" width="16" height="16" decoding="async" loading="lazy" />
              <span id="dateChipText">
                {{ old('date', $sDate) ? Carbon::parse(old('date', $sDate))->format('D, M d, Y') : 'Choose date' }}
              </span>
            </button>
            <input type="hidden" id="dateInput" name="date" value="{{ old('date', $sDate) }}">
          </div>

          <div class="flex flex-wrap gap-2">
            <button type="button" data-pick="tomorrow" class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200">Tomorrow</button>
            <button type="button" data-pick="nextmon"  class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200">Next Mon</button>
            <button type="button" data-pick="+7"       class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200">+1w</button>
            <button type="button" data-pick="+14"      class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200">+2w</button>
          </div>

          @error('date')<p data-error-for="date" class="text-sm text-red-600">• {{ $message }}</p>@enderror
        </div>

        {{-- TIME (dynamic, counselor-specific) --}}
        <div class="space-y-2">
          <div class="flex items-start gap-3">
            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">2</span>
            <div>
              <p class="font-semibold text-gray-900 dark:text-gray-100">Select a time <span class="text-rose-600">*</span></p>
              <p id="timeHint" class="text-xs text-gray-500 dark:text-gray-400">Times will appear after you choose a date.</p>
            </div>
          </div>

          {{-- hidden select used for form post --}}
          <select id="timeSelect" name="time" class="sr-only" aria-hidden="true" tabindex="-1">
            <option value="">available slots</option>
          </select>

          {{-- UNIFORM time pills container --}}
          <div id="timeGrid" class="slot-grid"></div>

          <div id="timeLoading" class="hidden text-xs text-gray-500 dark:text-gray-400">Loading available times…</div>
          <p id="timeEmpty" class="hidden text-xs text-gray-500 dark:text-gray-400">No available slots.</p>

          @error('time')<p data-error-for="time" class="text-sm text-red-600">• {{ $message }}</p>@enderror
        </div>
      </div>

      {{-- NOTE --}}
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Note (optional)</label>
        <div class="relative">
          <textarea id="noteField" name="note" rows="4" maxlength="4000"
                    class="w-full rounded-lg border-slate-300 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 p-3 js-counted"
                    data-max="4000"
                    placeholder="Optional context for the follow-up…">{{ old('note') }}</textarea>
          <div class="absolute right-2 bottom-2 text-[11px] text-slate-400">
            <span class="js-count">0</span>/4000
          </div>
        </div>
      </div>

      {{-- Actions --}}
      <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('counselor.appointments.show', $appointment->id) }}"
           class="btn-secondary inline-flex items-center rounded-xl px-4 py-2.5 text-sm ring-1 ring-gray-200 text-slate-700 hover:bg-gray-50">Cancel</a>
        <button id="submitBtn" type="submit"
                class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md transition hover:shadow-lg hover:brightness-105 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                disabled>
          Save Follow-up
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: Calendar --}}
<div id="calModal" class="fixed inset-0 hidden modal-zp" aria-hidden="true">
  <div id="calBackdrop" class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4 py-8">
    <div id="calDialog"
         class="w-full max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-black/5 p-5 modal-enter"
         role="dialog" aria-modal="true" aria-labelledby="calTitle" tabindex="-1">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 id="calTitle" class="text-base font-semibold">Choose a date</h2>
          <p class="mt-1 text-xs text-gray-500">Weekends are disabled. Use arrow keys; Enter to select. Esc to cancel.</p>
        </div>
        <button id="calCloseBtn" aria-label="Close"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-indigo-500">✕</button>
      </div>

      <div class="mt-4 rounded-2xl cal-shell p-4 ring-1 ring-gray-200">
        <div class="cal-bar flex items-center justify-between">
          <button id="calPrev" type="button" class="px-3 py-1 rounded-lg text-slate-600 hover:bg-gray-100 hover:text-slate-800 ring-1 ring-gray-200 disabled:opacity-40">‹</button>
          <div class="text-center">
            <div id="calMonth" class="tracking-widest text-indigo-600 font-bold text-lg"></div>
          </div>
          <button id="calNext" type="button" class="px-3 py-1 rounded-lg text-slate-600 hover:bg-gray-100 hover:text-slate-800 ring-1 ring-gray-200">›</button>
        </div>

        <div class="mt-4 grid grid-cols-7">
          <div class="cal-head">S</div><div class="cal-head">M</div><div class="cal-head">T</div>
          <div class="cal-head">W</div><div class="cal-head">T</div><div class="cal-head">F</div><div class="cal-head">S</div>
        </div>

        <div id="calGrid" class="mt-2 cal-grid select-none"></div>
        <p class="mt-3 text-xs text-slate-500">Tip: Click a weekday to pick a date. Weekends are closed.</p>
      </div>

      <div class="mt-5 flex items-center justify-end gap-3">
        <button id="calCancel" class="rounded-xl px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">Cancel</button>
        <button id="calUse" class="rounded-xl px-4 py-2 text-sm font-semibold bg-indigo-600 text-white hover:brightness-110 disabled:opacity-50" disabled>Use this date</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  /* Elements */
  const formEl     = document.getElementById('followForm');
  const dateInput  = document.getElementById('dateInput');
  const dateChip   = document.getElementById('dateChip');
  const dateChipText = document.getElementById('dateChipText');
  const submitBtn  = document.getElementById('submitBtn');

  const timeSel    = document.getElementById('timeSelect');
  const timeGrid   = document.getElementById('timeGrid');
  const timeHint   = document.getElementById('timeHint');
  const loadingEl  = document.getElementById('timeLoading');
  const emptyEl    = document.getElementById('timeEmpty');

  const slotsBase  = @json(route('counselor.appointments.follow.slots', $appointment->id));

  /* Error helper */
  const hideError = (field) => {
    document.querySelectorAll(`[data-error-for="${field}"]`).forEach(el => el.classList.add('hidden-error'));
    if (window.Swal && Swal.isVisible()) Swal.close();
  };
  formEl?.addEventListener('input', (e)=>{ hideError(e.target.name || ''); }, {capture:true});

  /* Submit state */
  function updateSubmitState(){
    const hasDate = !!dateInput.value;
    const hasTime = !!timeSel.value;
    submitBtn.disabled = !(hasDate && hasTime);
  }

  /* Date chip label */
  function updateDateChip(){
    if (!dateInput.value){
      dateChip.classList.add('date-chip--empty');
      dateChip.classList.remove('date-chip--filled');
      dateChipText.textContent = 'Choose date';
      return;
    }
    const d = new Date(dateInput.value + 'T00:00:00');
    const label = new Intl.DateTimeFormat(undefined,{ weekday:'short', month:'short', day:'2-digit', year:'numeric' }).format(d);
    dateChipText.textContent = label;
    dateChip.classList.remove('date-chip--empty');
    dateChip.classList.add('date-chip--filled');
  }

  /* ===== UNIFORM TIME UI ===== */
  function clearTimeUI(placeholder='available slots'){
    timeSel.innerHTML = '';
    const opt = document.createElement('option'); opt.value=''; opt.textContent = placeholder;
    timeSel.appendChild(opt);
    timeGrid.innerHTML = '';
    emptyEl.classList.add('hidden');
    updateSubmitState();
  }

  function renderTimePillsFromOptions(){
    timeGrid.innerHTML = '';
    const current = timeSel.value;

    [...timeSel.options].forEach(o => {
      if (!o.value) return;

      const available = parseInt(o.dataset.available || '0', 10);

      const pill = document.createElement('button');
      pill.type = 'button';
      pill.className = 'slot-pill';
      pill.dataset.value = o.value;

      if (available === 0){
        pill.disabled = true;
        pill.setAttribute('aria-disabled','true');
      } else {
        pill.setAttribute('aria-disabled','false');
      }

      // split label into time + sub (e.g., "1:00 PM (1 slot)")
      const text = o.textContent || '';
      const m = text.match(/^(.+?)\s*\((.+)\)$/);
      const timeTxt = m ? m[1] : text;
      const subTxt  = m ? m[2] : (available === 1 ? '1 slot' : (available > 1 ? `${available} slots` : 'Full'));

      pill.innerHTML = `
        <span class="slot-time">${timeTxt}</span>
        <span class="slot-sub">${subTxt}</span>
      `;

      if (o.value === current) pill.classList.add('is-selected');

      pill.addEventListener('click', () => {
        if (pill.disabled) return;
        timeSel.value = o.value;
        hideError('time');
        [...timeGrid.querySelectorAll('.slot-pill')].forEach(x => x.classList.remove('is-selected'));
        pill.classList.add('is-selected');
        updateSubmitState();
      });

      timeGrid.appendChild(pill);
    });

    if (!timeGrid.children.length) emptyEl.classList.remove('hidden');
  }

  function isWeekend(dateStr){
    const d = new Date(dateStr + 'T00:00:00'); const w = d.getDay();
    return w === 0 || w === 6;
  }

  async function loadSlots(){
    const date = dateInput.value;
    if (!date){ clearTimeUI('pick a date'); if (timeHint) timeHint.textContent='Times will appear after you choose a date.'; return; }
    if (isWeekend(date)){
      clearTimeUI('closed (Mon–Fri only)');
      if (timeHint) timeHint.textContent = 'Closed on weekends. Please choose a weekday.';
      return;
    }

    loadingEl.classList.remove('hidden');
    clearTimeUI('loading…');
    if (timeHint) timeHint.textContent = 'Fetching available time slots…';

    try{
      const url = `${slotsBase}?date=${encodeURIComponent(date)}`;
      const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      if(!res.ok){ clearTimeUI('unable to load'); if (timeHint) timeHint.textContent='Unable to load slots. Try again.'; return; }

      const data = await res.json();

      timeSel.innerHTML = '';
      const ph = document.createElement('option'); ph.value=''; ph.textContent='Choose a preferred time *'; timeSel.appendChild(ph);

      if (Array.isArray(data.slots) && data.slots.length){
        data.slots.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.value;
          opt.dataset.available = String(s.available);
          opt.textContent = s.available === 0 ? `${s.label} (Full)`
                         : s.available === 1 ? `${s.label} (1 slot)`
                         : `${s.label} (${s.available} slots)`;
          timeSel.appendChild(opt);
        });
        if (timeHint) timeHint.textContent = 'Choose one available time slot.';
      } else {
        const reason = data.reason || '';
        if      (reason==='weekend')         clearTimeUI('Mon–Fri only');
        else if (reason==='no_availability') clearTimeUI('no counselor availability');
        else if (reason==='fully_booked')    clearTimeUI('fully booked');
        else if (reason==='no_slots')        clearTimeUI('no working-hour slots');
        else                                  clearTimeUI('no available slots');
        if (timeHint) timeHint.textContent = 'No slots for this date. Try another day.';
      }

      renderTimePillsFromOptions();
    }catch(e){
      console.error('Failed to load slots', e);
      clearTimeUI('unable to load');
      if (timeHint) timeHint.textContent='Something went wrong while loading slots.';
    }finally{
      loadingEl.classList.add('hidden');
      updateSubmitState();
    }
  }

  /* Modal calendar */
  const modal     = document.getElementById('calModal');
  const dialog    = document.getElementById('calDialog');
  const backdrop  = document.getElementById('calBackdrop');
  const btnClose  = document.getElementById('calCloseBtn');
  const btnCancel = document.getElementById('calCancel');
  const btnUse    = document.getElementById('calUse');

  const calGrid   = document.getElementById('calGrid');
  const calMonth  = document.getElementById('calMonth');
  const calPrev   = document.getElementById('calPrev');
  const calNext   = document.getElementById('calNext');

  const today = new Date(); today.setHours(0,0,0,0);
  let view = new Date(); view.setDate(1);
  let pickup = null;
  let lastFocus = null;

  function fmtYMD(d){ return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
  function sameYM(a,b){ return a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth(); }
  function isSameDate(a,b){ return a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate(); }

  function openModal(){
    lastFocus = document.activeElement;
    dateChip.setAttribute('aria-expanded', 'true');
    modal.classList.remove('hidden');
    backdrop.classList.add('cal-backdrop-in');
    dialog.classList.add('cal-animate-in');
    document.body.style.overflow = 'hidden';
    setTimeout(()=>dialog.focus(), 10);
  }
  function closeModal(){
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    btnUse.disabled = true;
    dateChip.setAttribute('aria-expanded', 'false');
    backdrop.classList.remove('cal-backdrop-in');
    dialog.classList.remove('cal-animate-in');
    if (lastFocus) lastFocus.focus();
  }

  function renderCal(){
    const hdr = new Intl.DateTimeFormat('en', {month:'long', year:'numeric'}).format(view);
    calMonth.textContent = hdr.toUpperCase();

    calPrev.disabled = sameYM(view, today);
    calPrev.classList.toggle('opacity-40', calPrev.disabled);

    calGrid.innerHTML = '';
    const firstDow     = new Date(view.getFullYear(), view.getMonth(), 1).getDay();
    const daysInMonth  = new Date(view.getFullYear(), view.getMonth()+1, 0).getDate();

    for (let i=0; i<firstDow; i++){ const s=document.createElement('div'); s.className='cal-spacer'; calGrid.appendChild(s); }

    for (let day=1; day<=daysInMonth; day++){
      const cell = new Date(view.getFullYear(), view.getMonth(), day); cell.setHours(0,0,0,0);
      const weekend  = cell.getDay()===0 || cell.getDay()===6;
      const past     = cell < today;
      const disabled = weekend || past;

      const btn = document.createElement('button');
      btn.type='button'; btn.textContent=String(day); btn.dataset.date=cell.toISOString().slice(0,10); btn.className='cal-btn';
      if (isSameDate(cell, today)) btn.classList.add('cal-btn--today');

      if (disabled){ btn.classList.add('cal-btn--disabled'); btn.disabled = true; btn.title = weekend ? 'Closed (Sat–Sun)' : 'Past date'; }
      else {
        btn.addEventListener('click', () => { pickup = cell; highlightSelection(); btnUse.disabled = false; });
        btn.addEventListener('keydown', (e) => { if (e.key==='Enter' || e.key===' ') { e.preventDefault(); pickup = cell; highlightSelection(); btnUse.disabled = false; }});
      }
      calGrid.appendChild(btn);
    }
    highlightSelection();
  }

  function highlightSelection(){
    const buttons = calGrid.querySelectorAll('button.cal-btn');
    buttons.forEach(b => b.classList.remove('cal-btn--selected'));
    if (!pickup) return;
    if (pickup.getFullYear()===view.getFullYear() && pickup.getMonth()===view.getMonth()){
      const d = pickup.getDate();
      buttons.forEach(b => {
        if (b.textContent === String(d) && !b.classList.contains('cal-btn--disabled')) {
          b.classList.add('cal-btn--selected');
        }
      });
    }
  }

  function applyPicked(){
    if (!pickup) return;
    const iso = fmtYMD(pickup);
    dateInput.value = iso;
    updateDateChip();
    hideError('date');
    loadSlots();
    updateSubmitState();
  }

  // open modal with ripple
  dateChip.addEventListener('click', () => {
    const r = document.createElement('span'); r.className = 'chip-ripple'; dateChip.appendChild(r);
    r.addEventListener('animationend', () => r.remove());

    if (dateInput.value){
      const d = new Date(dateInput.value + 'T00:00:00');
      pickup = d; view = new Date(d.getFullYear(), d.getMonth(), 1);
    } else {
      let d = new Date(); d.setHours(0,0,0,0);
      if (d.getDay()===0) d.setDate(d.getDate()+1);
      if (d.getDay()===6) d.setDate(d.getDate()+2);
      pickup = d; view = new Date(d.getFullYear(), d.getMonth(), 1);
    }
    renderCal(); btnUse.disabled = false; openModal();
  });

  [btnClose, btnCancel, backdrop].forEach(el => el.addEventListener('click', closeModal));
  btnUse.addEventListener('click', () => { applyPicked(); closeModal(); });
  calPrev.addEventListener('click', ()=>{ if (!calPrev.disabled){ view.setMonth(view.getMonth()-1); renderCal(); }});
  calNext.addEventListener('click', ()=>{ view.setMonth(view.getMonth()+1); renderCal(); });

  /* Quick picks */
  const nextWeekday = d => { while ([0,6].includes(d.getDay())) d.setDate(d.getDate()+1); return d; }
  document.querySelectorAll('[data-pick]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      let base = dateInput.value ? new Date(dateInput.value+'T00:00:00') : new Date();
      base.setHours(0,0,0,0);
      const kind = btn.dataset.pick;
      if(kind === 'tomorrow'){ base.setDate(base.getDate()+1); }
      else if(kind === 'nextmon'){
        const dow = base.getDay(); const add = (8 - dow) % 7; base.setDate(base.getDate() + (add === 0 ? 7 : add));
      } else { base.setDate(base.getDate() + parseInt(kind,10)); }
      nextWeekday(base);
      dateInput.value = `${base.getFullYear()}-${String(base.getMonth()+1).padStart(2,'0')}-${String(base.getDate()).padStart(2,'0')}`;
      updateDateChip(); loadSlots(); updateSubmitState();
    });
  });

  /* Counters */
  (function () {
    const fields = document.querySelectorAll('.js-counted');
    const clamp = (s, m) => s.length > m ? s.slice(0, m) : s;
    fields.forEach(el => {
      const max = parseInt(el.dataset.max || el.getAttribute('maxlength') || '4000', 10);
      const counter = el.parentElement.querySelector('.js-count');
      const paint = () => { if (el.value.length > max) el.value = clamp(el.value, max); if (counter) counter.textContent = el.value.length; };
      paint(); el.addEventListener('input', paint); el.addEventListener('paste', () => requestAnimationFrame(paint));
    });
  })();

  /* Initial */
  updateDateChip();
  if (dateInput.value) loadSlots(); else clearTimeUI('pick a date');
  updateSubmitState();

  /* Auto-refresh pills when hidden select changes programmatically */
  const obs = new MutationObserver(renderTimePillsFromOptions);
  obs.observe(timeSel, { childList: true, subtree: true, attributes: true, attributeFilter:['value'] });
});
</script>
@endpush
