{{-- resources/views/appointment/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Lumi - Appointment')
@section('page_title', 'Appointment')

@section('content')
<div class="mx-auto max-w-6xl px-4 pt-0 pb-10 animate-fadeup">
  {{-- Banner --}}
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
            <h1 class="text-lg font-semibold tracking-tight text-white">Book Appointment</h1>
            <p class="text-white/85 text-sm">Pick a date and time. A counselor will be assigned by the admin.</p>
          </div>
        </div>

        <a href="{{ route('appointment.history') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2.5 text-sm font-medium text-white backdrop-blur transition hover:bg-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
          View Appointment
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </a>
      </div>
    </div>
  </div>

  {{-- Page grid --}}
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
    {{-- Left column --}}
    <aside class="lg:col-span-2">
      <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">How it works</h3>
        <ol class="space-y-4">
          <li class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">1</span>
            <div>
              <p class="font-medium text-gray-900 dark:text-gray-100">Pick date</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Weekends are closed (Mon–Fri only).</p>
            </div>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">2</span>
            <div>
              <p class="font-medium text-gray-900 dark:text-gray-100">Select time</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Available pooled time slots will appear for the chosen date.</p>
            </div>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">3</span>
            <div>
              <p class="font-medium text-gray-900 dark:text-gray-100">Admin assigns counselor</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">You’ll see “Awaiting assignment” until a counselor is set.</p>
            </div>
          </li>
        </ol>

        <div class="mt-6 rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">
          <p class="mb-2 font-medium">Tips</p>
          <ul class="list-inside list-disc space-y-1">
            <li>Arrive 15 minutes early.</li>
            <li>Bring student ID for verification.</li>
            <li>Cancel via Appointment History [View].</li>
          </ul>
        </div>
      </div>
    </aside>

    {{-- Right column --}}
    <section class="lg:col-span-3">
      <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800/70">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Fill Appointment Details</h2>

        <style>
          @keyframes slideUp { from {opacity:0; transform:translateY(10px) scale(.98);} to {opacity:1; transform:none;} }
          .modal-enter-active { animation:slideUp .2s ease-out; }
          .hidden-error{ display:none !important; }

          /* Step headers */
          .step-row{ display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
          .step-left{ display:flex; align-items:flex-start; gap:.75rem; }
          .step-badge{ display:inline-flex; align-items:center; justify-content:center; width:1.5rem; height:1.5rem; border-radius:9999px; background:#4f46e5; color:#fff; font-weight:700; font-size:.75rem; margin-top:.125rem; }
          .step-title{ font-weight:600; line-height:1.2; }
          .step-hint{ margin-top:.125rem; font-size:.75rem; color:rgb(100 116 139); }
          .dark .step-hint{ color:rgb(156 163 175); }

          /* Required asterisk */
          .req{ margin-left:.25rem; font-weight:700; color:#dc2626; }

          /* Time pills */
          .time-pill{ display:inline-flex; align-items:center; justify-content:center; border-radius:.75rem; border:1px solid rgb(229 231 235); background:#fff; padding:.5rem .75rem; font-size:.875rem; color:rgb(55 65 81); transition:background-color .15s, border-color .15s, color .15s, transform .08s; }
          .time-pill:hover{ border-color:rgb(165 180 252); background:rgb(239 246 255); transform:scale(1.02); }
          .time-pill:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
          .time-pill--selected{ border-color:#4f46e5; background:#4f46e5; color:#fff; }
          .time-pill--full{ background:linear-gradient(180deg,#ffffff 0%,#ffe4e6 100%); border-color:#ef4444!important; color:#b91c1c; cursor:not-allowed; box-shadow:inset 0 0 0 1px rgba(239,68,68,.15); }
          .time-pill--full:hover,.time-pill--full:active{ background:linear-gradient(180deg,#ffffff 0%,#ffe4e6 100%); }
          .time-pill--full:focus-visible{ outline:none; }
          .time-pill--full:disabled{ opacity:.85; }
          .dark .time-pill--full{ background:linear-gradient(180deg,#111827 0%,#1f2937 100%); border-color:#f87171!important; color:#fecaca; box-shadow:inset 0 0 0 1px rgba(248,113,113,.2); }

          /* Date chip */
          .date-chip{ position:relative; overflow:hidden; display:inline-flex; align-items:center; gap:.5rem; padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e5e7eb; background:#fff; color:#111827; box-shadow:0 1px 1px rgba(0,0,0,.04); cursor:pointer; user-select:none; transition:transform .08s ease, border-color .15s ease, box-shadow .15s ease, background-color .15s ease;}
          .date-chip:hover{ border-color:#a5b4fc; box-shadow:0 2px 6px rgba(99,102,241,.15); background:#f8fafc; transform:scale(1.02); }
          .date-chip:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
          .date-chip--empty{ color:#374151; border-color:#e5e7eb; background:#ffffff; }
          .date-chip--filled{ border-color:#a5b4fc; box-shadow:0 0 0 2px rgba(99,102,241,.3); background:#eef2ff; color:#111827; }

          /* Ripple */
          .chip-ripple{ position:absolute; inset:0; border-radius:inherit; overflow:hidden; pointer-events:none; }
          .chip-ripple::after{ content:""; position:absolute; left:50%; top:50%; width:0; height:0; border-radius:9999px; background:rgba(99,102,241,.18); transform:translate(-50%,-50%); animation:chipWave .4s ease-out forwards; }
          @keyframes chipWave{ from{width:0;height:0;opacity:.35;} to{width:220%;height:220%;opacity:0;} }

          /* ---------- Calendar polish ---------- */
          :root{ --cal-size:44px; --cal-size-sm:38px; }
          @media (max-width:420px){ :root{ --cal-size:var(--cal-size-sm); } }
          .cal-shell{ background:#fff; color:#0f172a; }
          .dark .cal-shell{ background:#0b0f19; color:#e5e7eb; }
          .cal-bar button:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
          .cal-head{ display:flex; align-items:center; justify-content:center; height:calc(var(--cal-size)*.75); font-weight:700; letter-spacing:.08em; font-size:.70rem; color:#6b7280; text-transform:uppercase; }
          .dark .cal-head{ color:#9ca3af; }
          .cal-grid{ display:grid; grid-template-columns:repeat(7,1fr); gap:.5rem; }
          .cal-spacer{ height:var(--cal-size); }
          .cal-btn{ width:var(--cal-size); height:var(--cal-size); margin-inline:auto; display:flex; align-items:center; justify-content:center; border-radius:9999px; font-weight:700; background:#fff; color:#0f172a; border:1px solid #e5e7eb; transition:transform .12s ease, background-color .15s ease, border-color .15s ease, box-shadow .15s ease; }
          .cal-btn:hover{ background:#eef2ff; border-color:#c7d2fe; transform:translateY(-1px); }
          .cal-btn:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
          .cal-btn--today{ box-shadow:0 0 0 2px rgba(99,102,241,.28); border-color:#a5b4fc; }
          .cal-btn--selected{ background:#4f46e5; color:#fff; border-color:#4f46e5; box-shadow:0 6px 16px rgba(79,70,229,.28); }
          .cal-btn--disabled{ background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb; cursor:not-allowed; transform:none!important; }
          .cal-btn--disabled:hover{ background:#f3f4f6; }

          /* ---------- Open animation ---------- */
          @keyframes calPop{0%{transform:translateY(10px) scale(.96);opacity:0;filter:blur(2px);}60%{transform:translateY(0) scale(1.012);opacity:1;filter:blur(0);}100%{transform:translateY(0) scale(1);opacity:1;}}
          .cal-animate-in{ animation:calPop .22s ease-out both; }
          .cal-backdrop-in{ animation:fadeIn .18s ease-out both; }
          @keyframes fadeIn{ from{opacity:0;} to{opacity:.6;} }
        </style>

        <form method="POST" action="{{ route('appointment.store') }}" class="space-y-8">
          @csrf

          {{-- STEP 1: DATE --}}
          <div class="space-y-3">
            <div class="step-row">
              <div class="step-left">
                <span class="step-badge">1</span>
                <div>
                  <p class="step-title">Choose a preferred date <span id="reqDate" class="req">*</span></p>
                  <p class="step-hint">Pick a weekday. Weekends are closed (Mon–Fri only).</p>
                </div>
              </div>
            </div>

            <div>
              <button id="dateChip" type="button" class="date-chip date-chip--empty" aria-haspopup="dialog" aria-expanded="false">
                <img src="{{ asset('images/icons/calendar.png') }}" alt="" class="h-4 w-4 opacity-90" width="16" height="16" decoding="async" loading="lazy" />
                <span id="dateChipText">Choose date</span>
              </button>
            </div>

            <input type="hidden" name="date" id="dateInput" value="{{ old('date') }}">
            @error('date')<p data-error-for="date" class="text-sm text-red-600">{{ $message }}</p>@enderror
          </div>

          {{-- STEP 2: TIME --}}
          <div class="space-y-2">
            <div class="step-row">
              <div class="step-left">
                <span class="step-badge">2</span>
                <div>
                  <p class="step-title">Select a time <span id="reqTime" class="req">*</span></p>
                  <p id="timeHint" class="step-hint">Times will appear after you choose a date.</p>
                </div>
              </div>
            </div>

            <select id="timeSelect" name="time" class="sr-only" aria-hidden="true" tabindex="-1">
              <option value="">available slots</option>
            </select>

            <div id="timeGrid" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"></div>

            <div id="timeLoading" class="hidden text-xs text-gray-500 dark:text-gray-400">Loading available times…</div>
            <p id="timeEmpty" class="text-xs text-gray-500 dark:text-gray-400 hidden">No available slots.</p>

            @error('time')<p data-error-for="time" class="text-sm text-red-600">{{ $message }}</p>@enderror
          </div>

          {{-- CONSENT --}}
          <div class="flex items-start gap-3">
            <input type="checkbox" id="consent-cbx"
                   class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-500"
                   name="consent" value="1" {{ old('consent') ? 'checked' : '' }}/>
            <label for="consent-cbx" class="text-sm text-gray-700 dark:text-gray-300">
              I understand that my information will be handled according to LumiCHAT’s privacy policy.
            </label>
          </div>
          @error('consent')<p data-error-for="consent" class="text-sm text-red-600">{{ $message }}</p>@enderror

          {{-- ACTIONS --}}
          <div class="flex items-center gap-4 pt-2">
            <a href="{{ route('chat.index') }}" class="btn-secondary">Cancel</a>
            <button id="submitBtn" type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md transition hover:shadow-lg hover:brightness-105 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                    disabled>
              Confirm Appointment
            </button>
          </div>
        </form>
      </div>
    </section>
  </div>
</div>

{{-- ===== Modal: Calendar =====
     Use modal-zp so it sits ABOVE sticky header & gets full-page blur --}}
<div id="calModal" class="fixed inset-0 hidden modal-zp" aria-hidden="true">
  <div id="calBackdrop" class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4 py-8">
    <div id="calDialog"
         class="w-full max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-black/5 dark:bg-gray-900 dark:text-gray-100 p-5 modal-enter"
         role="dialog" aria-modal="true" aria-labelledby="calTitle" tabindex="-1">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 id="calTitle" class="text-base font-semibold">Choose a date</h2>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Weekends are disabled. Use arrow keys; Enter to select. Esc to cancel.</p>
        </div>
        <button id="calCloseBtn" aria-label="Close"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:bg-gray-800">
          ✕
        </button>
      </div>

      <div class="mt-4 rounded-2xl cal-shell p-4 ring-1 ring-gray-200 dark:ring-gray-800">
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

        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
          Tip: Click a weekday to pick a date. Weekends are closed.
        </p>
      </div>

      <div class="mt-5 flex items-center justify-end gap-3">
        <button id="calCancel" class="rounded-xl px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-gray-800">Cancel</button>
        <button id="calUse" class="rounded-xl px-4 py-2 text-sm font-semibold bg-indigo-600 text-white hover:brightness-110 disabled:opacity-50" disabled>Use this date</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  /* -------------------- Elements -------------------- */
  const formEl       = document.querySelector('form[action="{{ route('appointment.store') }}"]');
  const dateInput    = document.getElementById('dateInput');
  const dateChip     = document.getElementById('dateChip');
  const dateChipText = document.getElementById('dateChipText');
  const reqDate      = document.getElementById('reqDate');
  const reqTime      = document.getElementById('reqTime');
  const submitBtn    = document.getElementById('submitBtn');

  const timeSel    = document.getElementById('timeSelect');
  const timeGrid   = document.getElementById('timeGrid');
  const timeHint   = document.getElementById('timeHint');
  const loadingEl  = document.getElementById('timeLoading');
  const emptyEl    = document.getElementById('timeEmpty');

  const slotsBase  = @json(route('appointment.slots'));

  /* -------------------- Error helpers -------------------- */
  const clearAllErrors = () => {
    document.querySelectorAll('[data-error-for]').forEach(el => el.classList.add('hidden-error'));
    if (window.Swal && Swal.isVisible()) Swal.close();
  };
  if (formEl){
    formEl.addEventListener('input', clearAllErrors, {capture:true});
    formEl.addEventListener('change', clearAllErrors, {capture:true});
    formEl.addEventListener('focusin', clearAllErrors, {capture:true});
  }
  const hideError = (field) => {
    document.querySelectorAll(`[data-error-for="${field}"]`).forEach(el => el.classList.add('hidden-error'));
    if (window.Swal && Swal.isVisible()) Swal.close();
  };

  /* -------------------- Submit enable/disable -------------------- */
  function updateSubmitState(){
    const hasDate = !!dateInput.value;
    const hasTime = !!timeSel.value;
    if (submitBtn) submitBtn.disabled = !(hasDate && hasTime);
  }

  /* -------------------- Date chip helpers -------------------- */
  function updateDateChip(){
    if (!dateInput.value){
      dateChip.classList.add('date-chip--empty');
      dateChip.classList.remove('date-chip--filled');
      dateChipText.textContent = 'Choose date';
      reqDate.classList.remove('hidden');
      return;
    }
    const d = new Date(dateInput.value + 'T00:00:00');
    const label = new Intl.DateTimeFormat(undefined,{
      weekday:'short', month:'short', day:'2-digit', year:'numeric'
    }).format(d);
    dateChipText.textContent = label;
    dateChip.classList.remove('date-chip--empty');
    dateChip.classList.add('date-chip--filled');
    reqDate.classList.add('hidden');
  }

  /* -------------------- Time slots -------------------- */
  function clearTimeUI(placeholder='available slots'){
    timeSel.innerHTML = '';
    const opt = document.createElement('option');
    opt.value=''; opt.textContent = placeholder;
    timeSel.appendChild(opt);
    timeGrid.innerHTML = '';
    emptyEl.classList.add('hidden');
    updateSubmitState();
  }

  function buildTimeGridFromSelect(){
    timeGrid.innerHTML = '';
    const current = timeSel.value;
    [...timeSel.options].forEach(o => {
      if (!o.value) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'time-pill';
      btn.dataset.value = o.value;

      const available = parseInt(o.dataset.available || '0', 10);
      btn.textContent = o.textContent;

      if (available === 0) {
        btn.disabled = true;
        btn.setAttribute('tabindex','-1');
        btn.classList.add('time-pill--full');
        btn.title = 'Fully booked — please select another time';
      }

      if (o.value === current) btn.classList.add('time-pill--selected');

      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        timeSel.value = o.value;
        hideError('time');
        document.querySelectorAll('.time-pill--selected')
          .forEach(el => el.classList.remove('time-pill--selected'));
        btn.classList.add('time-pill--selected');
        reqTime.classList.add('hidden');
        updateSubmitState();
      });

      timeGrid.appendChild(btn);
    });
    if (!timeGrid.children.length) emptyEl.classList.remove('hidden');
  }

  function isWeekend(dateStr){
    const d = new Date(dateStr + 'T00:00:00');
    const w = d.getDay();
    return w === 0 || w === 6;
  }

  async function loadSlots(){
    const date = dateInput.value;
    if (!date){
      clearTimeUI('pick a date');
      if (timeHint) timeHint.textContent='Times will appear after you choose a date.';
      return;
    }
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
      if(!res.ok){
        clearTimeUI('unable to load');
        if (timeHint) timeHint.textContent='Unable to load slots. Try again.';
        return;
      }

      const data = await res.json();

      timeSel.innerHTML = '';
      const ph = document.createElement('option');
      ph.value=''; ph.textContent='Choose a preferred time *';
      timeSel.appendChild(ph);

      if (Array.isArray(data.slots) && data.slots.length){
        data.slots.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.value;
          opt.textContent = s.available === 0
            ? `${s.label}  (Full)`
            : (s.available === 1
              ? `${s.label}  (1 slot)`
              : `${s.label}  (${s.available} slots)`);
          opt.dataset.available = String(s.available);
          timeSel.appendChild(opt);
        });
        if (timeHint) timeHint.textContent = 'Choose one available time slot.';
      } else {
        const reason = data.reason || '';
        if      (reason==='weekend')         clearTimeUI('Mon–Fri only');
        else if (reason==='no_availability') clearTimeUI('no availability on this day');
        else if (reason==='fully_booked')    clearTimeUI('fully booked');
        else if (reason==='no_slots')        clearTimeUI('no working-hour slots');
        else                                  clearTimeUI('no available slots');
        if (timeHint) timeHint.textContent = 'No slots for this date. Try another day.';
      }

      buildTimeGridFromSelect();
    }catch(e){
      console.error('Failed to load slots', e);
      clearTimeUI('unable to load');
      if (timeHint) timeHint.textContent='Something went wrong while loading slots.';
    }finally{
      loadingEl.classList.add('hidden');
      updateSubmitState();
    }
  }

  /* -------------------- Modal calendar -------------------- */
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
    if (lastFocus) lastFocus.focus();
    backdrop.classList.remove('cal-backdrop-in');
    dialog.classList.remove('cal-animate-in');
  }

  function renderCal(){
    const hdr = new Intl.DateTimeFormat('en', {month:'long', year:'numeric'}).format(view);
    calMonth.textContent = hdr.toUpperCase();

    calPrev.disabled = sameYM(view, today);
    calPrev.classList.toggle('opacity-40', calPrev.disabled);

    calGrid.innerHTML = '';
    const firstDow     = new Date(view.getFullYear(), view.getMonth(), 1).getDay();
    const daysInMonth  = new Date(view.getFullYear(), view.getMonth()+1, 0).getDate();

    for (let i=0; i<firstDow; i++){
      const s = document.createElement('div');
      s.className = 'cal-spacer';
      calGrid.appendChild(s);
    }

    for (let day=1; day<=daysInMonth; day++){
      const cell = new Date(view.getFullYear(), view.getMonth(), day);
      cell.setHours(0,0,0,0);

      const weekend  = cell.getDay()===0 || cell.getDay()===6;
      const past     = cell < today;
      const disabled = weekend || past;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = String(day);
      btn.dataset.date = cell.toISOString().slice(0,10);
      btn.className = 'cal-btn';
      if (isSameDate(cell, today)) btn.classList.add('cal-btn--today');

      if (disabled){
        btn.classList.add('cal-btn--disabled');
        btn.disabled = true;
        btn.title = weekend ? 'Closed (Sat–Sun)' : 'Past date';
      } else {
        btn.addEventListener('click', () => { pickup = cell; highlightSelection(); btnUse.disabled = false; });
        btn.addEventListener('keydown', (e) => {
          if (e.key==='Enter' || e.key===' ') {
            e.preventDefault(); pickup = cell; highlightSelection(); btnUse.disabled = false;
          }
        });
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
    dateInput.value = fmtYMD(pickup);
    hideError('date');
    updateDateChip();
    reqTime.classList.remove('hidden');
    if (timeHint) timeHint.textContent = 'Times are based on your selected date.';
    loadSlots();
    updateSubmitState();
  }

  // Date chip opens modal with ripple
  dateChip.addEventListener('click', () => {
    const r = document.createElement('span');
    r.className = 'chip-ripple';
    dateChip.appendChild(r);
    r.addEventListener('animationend', () => r.remove());

    if (dateInput.value){
      const d = new Date(dateInput.value + 'T00:00:00');
      pickup = d; view = new Date(d.getFullYear(), d.getMonth(), 1);
    } else {
      let d = new Date(); d.setHours(0,0,0,0);
      const dow = d.getDay(); if (dow===0) d.setDate(d.getDate()+1); if (dow===6) d.setDate(d.getDate()+2);
      pickup = d; view = new Date(d.getFullYear(), d.getMonth(), 1);
    }
    renderCal();
    btnUse.disabled = false;
    openModal();
  });

  [btnClose, btnCancel, backdrop].forEach(el => el.addEventListener('click', closeModal));
  btnUse.addEventListener('click', () => { applyPicked(); closeModal(); });

  calPrev.addEventListener('click', ()=>{ if (!calPrev.disabled){ view.setMonth(view.getMonth()-1); renderCal(); }});
  calNext.addEventListener('click', ()=>{ view.setMonth(view.getMonth()+1); renderCal(); });

  modal.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { e.preventDefault(); closeModal(); }
    if (e.key === 'Tab') {
      const focusables = dialog.querySelectorAll('button, [tabindex]:not([tabindex="-1"])');
      const list = Array.from(focusables).filter(n => !n.hasAttribute('disabled'));
      if (!list.length) return;
      const first = list[0], last = list[list.length-1];
      if (e.shiftKey && document.activeElement === first){ last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last){ first.focus(); e.preventDefault(); }
    }
  });

  /* -------------------- Initial states -------------------- */
  reqDate.classList.add('req');
  reqTime.classList.add('req');
  if (dateInput.value){ updateDateChip(); loadSlots(); }
  else { updateDateChip(); if (timeHint) timeHint.textContent = 'Times will appear after you choose a date.'; }
  updateSubmitState();

  /* -------------------- Final confirmation before submit -------------------- */
  if (formEl) {
    formEl.addEventListener('submit', function (e) {
      if (!dateInput.value || !timeSel.value) return;        // let server validation handle it
      if (typeof Swal === 'undefined') return;               // fallback if walang Swal

      e.preventDefault();

      const dateLabel = dateChipText ? dateChipText.textContent : dateInput.value;
      let timeLabel = '';
      if (timeSel.selectedIndex >= 0) {
        const opt = timeSel.options[timeSel.selectedIndex];
        timeLabel = opt.textContent.replace(/\s*\(.*\)$/, '');
      }

      Swal.fire({
        icon: 'question',
        title: 'Confirm this appointment?',
        width: 520,
        html: `
        <div style="text-align:left;font-size:.92rem;line-height:1.5;">
          <div style="margin-bottom:.6rem;">
            <div><strong>Date:</strong> ${dateLabel || '—'}</div>
            <div><strong>Time:</strong> ${timeLabel || '—'}</div>
          </div>

          <hr style="border:none;border-top:1px solid #e5e7eb;margin:.4rem 0 .8rem;">

          <p style="margin:0 0 .4rem;color:#374151;">
            Before you confirm, please make sure that:
          </p>

          <ul style="margin:0 0 .6rem;padding-left:0;color:#4b5563;list-style:none;">
            <li style="display:flex;gap:.45rem;align-items:flex-start;">
              <span style="margin-top:.15rem;font-size:.9rem;">•</span>
              <span>You are available on this date and time.</span>
            </li>
            <li style="display:flex;gap:.45rem;align-items:flex-start;margin-top:.15rem;">
              <span style="margin-top:.15rem;font-size:.9rem;">•</span>
              <span>You can arrive on time and complete the session.</span>
            </li>
          </ul>

          <p style="margin:0;color:#b91c1c;font-weight:600;">
            Once you confirm, you will not be able to reschedule this appointment inside LumiCHAT.
          </p>

          <p style="margin:.5rem 0 0;color:#6b7280;font-size:.86rem;">
            If you really need to change the schedule, you can still cancel your appointment
            from Appointment History [View] as long as no counselor has been assigned yet.
          </p>
        </div>
      `,
        showCancelButton: true,
        confirmButtonText: 'Yes, confirm',
        cancelButtonText: 'Go back',
        reverseButtons: true,
        focusConfirm: false,
        customClass: {
          popup: 'rounded-2xl shadow-xl',
          confirmButton: 'swal2-confirm btn-primary-ghost'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          formEl.submit(); // real submit here
        }
      });
    });
  }
});
</script>
@endpush

