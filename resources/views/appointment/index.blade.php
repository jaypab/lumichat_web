@extends('layouts.app')

@section('title', 'Lumi - Appointment')
@section('page_title', 'Appointment')  

@section('content')
<div class="mx-auto max-w-6xl px-4 pt-0 pb-8 animate-fadeup">
  {{-- Banner --}}
  <div class="mb-2">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 p-6 shadow-sm mb-4">
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="rounded-2xl bg-white/15 p-2 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M7 2a1 1 0 0 0-1 1v1H5a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1ZM5 9h14v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V9Z"/>
            </svg>
          </div>
          <div class="-mt-0.5">
            <h1 class="text-lg font-semibold tracking-tight text-white">Book Appointment</h1>
            <p class="text-white/80 text-sm">Pick a date and time. A counselor will be assigned by the admin.</p>
          </div>
        </div>

        <a href="{{ route('appointment.history') }}"
           class="self-center inline-flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2.5 text-sm font-medium text-white backdrop-blur transition hover:bg-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
          View Appointment
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </a>
      </div>
    </div>
  </div>

  {{-- ======= Two-column layout ======= --}}
  <div class="grid grid-cols-1 gap-6 md:grid-cols-5">
    {{-- Left: How it works --}}
    <aside class="md:col-span-2">
      <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">How it works</h3>
        <ol class="space-y-3">
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
          {{-- STEP 3: Available counselors for the chosen time --}}
<div id="cWrap" class="space-y-2 mt-4 hidden">
  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
    Available counselors for <span id="cWhen" class="font-semibold"></span>
  </label>
  <div id="cList" class="flex flex-wrap gap-2"></div>
  <p id="cEmpty" class="text-xs text-gray-500 dark:text-gray-400 hidden">
    No counselors are free at the selected time.
  </p>
</div>

        </ol>

        <div class="mt-5 rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">
          <p class="mb-2 font-medium">Tips</p>
          <ul class="list-inside list-disc space-y-1">
            <li>Arrive 15 minutes early.</li>
            <li>Bring student ID for verification.</li>
            <li>Reschedule via Appointment History.</li>
          </ul>
        </div>
      </div>
    </aside>

    {{-- Right: Form --}}
    <section class="md:col-span-3">
      <div class="rounded-2xl border border-gray-200 bg-white/80 p-8 shadow-lg backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/70">

        <h2 class="mb-6 text-lg font-semibold text-gray-900 dark:text-gray-100">Fill Appointment Details</h2>

        <style>
          #dateInput{ background-image:none!important; padding-right:3rem; }
          #dateInput::-webkit-calendar-picker-indicator{ display:none!important; }
          .hidden-error{ display: none !important; }
          .time-pill{
            @apply inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm
            text-gray-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-gray-700 dark:bg-gray-900
            dark:text-gray-200 dark:hover:border-indigo-500 dark:hover:bg-gray-800;
          }
          .time-pill--selected{
            @apply border-indigo-600 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-500;
          }
          .slot-cap { @apply inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] bg-slate-100 text-slate-700 ml-2; }
          .swal2-html-container.lumi{ text-align:left !important; }
          .swal2-html-container.lumi .lumi-divider{ margin:.5rem 0 1rem; }
        </style>

        <form method="POST" action="{{ route('appointment.store') }}" class="space-y-7">
          @csrf

          {{-- STEP 1: Date --}}
          <div class="space-y-2">
            <label for="dateInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              1. Choose a preferred date *
            </label>
            <div class="relative">
              <input id="dateInput" type="date" name="date" value="{{ old('date') }}"
                     min="{{ now()->toDateString() }}" class="input-ui pr-12">
              <button type="button" id="openDateBtn"
                      class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                      aria-label="Open calendar">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M7 2a1 1 0 0 0-1 1v1H5a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1ZM5 9h14v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V9Z"/>
                </svg>
              </button>
            </div>
            @error('date')<p data-error-for="date" class="text-sm text-red-600">{{ $message }}</p>@enderror
          </div>

          {{-- STEP 2: Time (modern grid + hidden select) --}}
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              2. Select a time *
            </label>

            {{-- Hidden native select (submission + a11y) --}}
            <select id="timeSelect" name="time" class="sr-only" aria-hidden="true" tabindex="-1">
              <option value="">available slots</option>
            </select>

            {{-- Pretty grid --}}
            <div id="timeGrid" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"></div>

            {{-- Loading / empty states --}}
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
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md transition hover:shadow-lg hover:brightness-105 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              Confirm Appointment
            </button>
          </div>
        </form>
      </div>
    </section>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const dateInput    = document.getElementById('dateInput');
  const openDateBtn  = document.getElementById('openDateBtn');

  const timeSel      = document.getElementById('timeSelect');
  const timeGrid     = document.getElementById('timeGrid');
  const loadingEl    = document.getElementById('timeLoading');
  const emptyEl      = document.getElementById('timeEmpty');

  const consentCbx   = document.getElementById('consent-cbx');
  const formEl       = document.querySelector('form[action="{{ route('appointment.store') }}"]');

  // Counselors panel
  const cWrap  = document.getElementById('cWrap');
  const cWhen  = document.getElementById('cWhen');
  const cList  = document.getElementById('cList');
  const cEmpty = document.getElementById('cEmpty');

  // Endpoints
  const slotsBase       = @json(route('appointment.slots'));
  const counselorsBase  = @json(route('appointment.counselors'));

  // --- helpers ----------------------------------------------------------
  const clearAllErrors = () => {
    document.querySelectorAll('[data-error-for]').forEach(el => el.classList.add('hidden-error'));
    if (window.Swal && Swal.isVisible()) Swal.close();
  };
  if (formEl) {
    formEl.addEventListener('input',   clearAllErrors, { capture: true });
    formEl.addEventListener('change',  clearAllErrors, { capture: true });
    formEl.addEventListener('focusin', clearAllErrors, { capture: true });
  }

  const toast = (title, icon='info', timer=2500) =>
    Swal.fire({ toast:true, position:'top-end', showConfirmButton:false, timer, icon, title });

  const showFormErrors = (title, items) => {
    const html = '<div class="lumi-divider"></div><ul style="text-align:left;margin:0;padding-left:1rem;line-height:1.6">'
      + items.map(i => `<li>• ${i}</li>`).join('') + '</ul>';
    Swal.fire({
      icon: 'error', title, html, confirmButtonText: 'OK', buttonsStyling: false,
      didRender: () => Swal.getConfirmButton().classList.add('btn-pill','btn-primary')
    });
  };

  const successMsg = @json(session('status'));
  const pageErrors = @json($errors->all());
  if (successMsg) Swal.fire({ icon:'success', title:'Success', text:successMsg, timer:2200, showConfirmButton:false });
  if (Array.isArray(pageErrors) && pageErrors.length) showFormErrors('Please fix the following', pageErrors);

  const hideError = (field) => {
    document.querySelectorAll(`[data-error-for="${field}"]`).forEach(el => el.classList.add('hidden-error'));
    if (window.Swal && Swal.isVisible()) Swal.close();
  };

  openDateBtn.addEventListener('click', () => {
    if (dateInput.showPicker) { dateInput.showPicker(); }
    else { dateInput.focus(); dateInput.click(); }
  });

  function clearCounselors() {
    cWrap.classList.add('hidden');
    cList.innerHTML = '';
    cEmpty.classList.add('hidden');
    cWhen.textContent = '';
  }

  async function loadCounselors(dateStr, hhmm) {
    clearCounselors();
    if (!dateStr || !hhmm) return;

    // heading context
    const whenFmt = new Date(`${dateStr}T${hhmm}:00`);
    cWhen.textContent = whenFmt.toLocaleString(undefined, {
      month:'short', day:'2-digit', year:'numeric', hour:'numeric', minute:'2-digit'
    });

    try {
      const url = `${counselorsBase}?date=${encodeURIComponent(dateStr)}&time=${encodeURIComponent(hhmm)}`;
      const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();

      cWrap.classList.remove('hidden');
      if (!Array.isArray(data.counselors) || data.counselors.length === 0) {
        cEmpty.classList.remove('hidden');
        return;
      }

      cList.innerHTML = '';
      data.counselors.forEach(c => {
        const chip = document.createElement('div');
        chip.className = 'c-chip';
        chip.innerHTML = `
          <span class="font-medium">${c.name}</span>
          <a class="underline text-gray-500 hover:text-gray-700" href="mailto:${c.email}">${c.email}</a>
        `;
        cList.appendChild(chip);
      });
    } catch (e) {
      console.error('Load counselors failed', e);
      cWrap.classList.remove('hidden');
      cEmpty.textContent = 'Unable to load counselors.';
      cEmpty.classList.remove('hidden');
    }
  }

  function clearTimeUI(placeholder='available slots'){
    timeSel.innerHTML = '';
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = placeholder;
    timeSel.appendChild(opt);
    timeGrid.innerHTML = '';
    emptyEl.classList.add('hidden');
    clearCounselors(); // reset counselor panel
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
        btn.classList.add('opacity-50', 'cursor-not-allowed');
        btn.title = 'Fully booked';
      }

      if (o.value === current) btn.classList.add('time-pill--selected');

      // ⬇️ choose time => fetch counselors
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        timeSel.value = o.value;
        hideError('time');
        document.querySelectorAll('.time-pill--selected').forEach(el => el.classList.remove('time-pill--selected'));
        btn.classList.add('time-pill--selected');
        loadCounselors(dateInput.value, o.value);
      });

      timeGrid.appendChild(btn);
    });

    if (timeGrid.children.length === 0) {
      emptyEl.classList.remove('hidden');
    }
  }

  function isWeekend(dateStr){
    const d = new Date(dateStr + 'T00:00:00');
    const day = d.getDay(); // 0 Sun .. 6 Sat
    return day === 0 || day === 6;
  }

  async function loadSlots(){
    const date = dateInput.value;
    if (!date){ clearTimeUI('pick a date'); return; }
    if (isWeekend(date)){
      clearTimeUI('closed (Mon–Fri only)');
      toast('Appointments are available Mon–Fri only.','info');
      return;
    }

    loadingEl.classList.remove('hidden');
    clearTimeUI('loading…');

    try{
      const url = `${slotsBase}?date=${encodeURIComponent(date)}`;
      const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      if(!res.ok){ clearTimeUI('unable to load'); toast('Failed to load time slots.','error'); return; }

      const data = await res.json();

      timeSel.innerHTML = '';
      const ph = document.createElement('option');
      ph.value = '';
      ph.textContent = 'Choose a preferred time *';
      timeSel.appendChild(ph);

      if (Array.isArray(data.slots) && data.slots.length) {
        data.slots.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.value;
          opt.textContent = s.label + (s.available > 1 ? `  (${s.available} slots)` : (s.available === 1 ? '  (1 slot)' : '  (full)'));
          opt.dataset.available = String(s.available);
          timeSel.appendChild(opt);
        });
        buildTimeGridFromSelect();
      } else {
        const reason = data.reason || '';
        const message = data.message || '';
        if      (reason === 'weekend')          clearTimeUI('Mon–Fri only');
        else if (reason === 'no_availability')  clearTimeUI('no availability on this day');
        else if (reason === 'fully_booked')     clearTimeUI('fully booked');
        else if (reason === 'no_slots')         clearTimeUI('no working-hour slots');
        else                                    clearTimeUI('no available slots');
        if (message) toast(message,'info');
        buildTimeGridFromSelect();
      }
    } catch(e){
      console.error('Failed to load slots', e);
      clearTimeUI('unable to load');
      toast('Something went wrong while loading slots.','error');
      buildTimeGridFromSelect();
    } finally{
      loadingEl.classList.add('hidden');
    }
  }

  dateInput.addEventListener('change', () => { hideError('date'); loadSlots(); });
  consentCbx.addEventListener('change', () => hideError('consent'));

  if (dateInput.value) loadSlots();
});
</script>
@endpush
@endsection
