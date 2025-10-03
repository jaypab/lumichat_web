{{-- resources/views/admin/appointments/follow-up.blade.php --}}
@extends('layouts.admin')
@section('title', 'Create Follow-up · Appointment #'.$appointment->id)
@section('page_title', 'Create Follow Up') 

@section('content')
@php
  use Carbon\Carbon;

  $suggest  = isset($suggest) && is_array($suggest) ? $suggest : [];
  $sDate    = $suggest['date'] ?? now()->addWeek()->toDateString();
  $sTime    = $suggest['time'] ?? '09:00';
  $sNice    = Carbon::parse($sDate.' '.$sTime)->format('M d, Y g:i A');

  $hasCoun  = !empty($appointment->counselor_id);
@endphp

<div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-slate-200/80 bg-slate-50/50">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Create follow-up</h2>
          <div class="mt-1 text-sm text-slate-600 flex items-center gap-3 flex-wrap">
            <span>
              Student: <b class="text-slate-800">{{ $appointment->student_name }}</b>
            </span>
            @if($appointment->counselor_name)
              <span class="hidden sm:inline">·</span>
              <span>
                Counselor: <b class="text-slate-800">{{ $appointment->counselor_name }}</b>
              </span>
            @endif
          </div>
        </div>

        <a href="{{ route('admin.appointments.show', $appointment->id) }}"
           class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Back
        </a>
      </div>

      <div class="mt-3 text-sm flex items-center gap-2 flex-wrap">
        <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-indigo-700 ring-1 ring-indigo-200">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
          Suggested: <b>{{ $sNice }}</b>
        </span>
      </div>
    </div>

    {{-- Form --}}
    <form id="followForm" method="POST"
          action="{{ route('admin.appointments.follow.store', $appointment->id) }}"
          class="px-5 pt-5 pb-24">
      @csrf

      {{-- Date & Time --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        {{-- Date + quick picks --}}
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
          <div class="relative">
            <input id="dateField" type="date" name="date"
                   value="{{ old('date', $sDate) }}" required autocomplete="off"
                   class="w-full rounded-lg border-slate-300 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
            <div class="mt-2 flex flex-wrap gap-2">
              <button type="button" data-pick="tomorrow" class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">Tomorrow</button>
              <button type="button" data-pick="nextmon"  class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">Next Mon</button>
              <button type="button" data-pick="+7"       class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">+1w</button>
              <button type="button" data-pick="+14"      class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">+2w</button>
            </div>
          </div>
          @error('date') <div class="mt-1 text-sm text-rose-600">• {{ $message }}</div> @enderror
          <p class="mt-1 text-[11px] text-slate-400">Weekends are skipped automatically.</p>
        </div>

        {{-- Time + presets --}}
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Time</label>
          <div class="relative">
            <input id="timeField" type="time" name="time" step="1800"
                   value="{{ old('time', $sTime) }}" required autocomplete="off"
                   class="w-full rounded-lg border-slate-300 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
            <div class="mt-2 flex flex-wrap gap-2">
              <button type="button" data-time="09:00" class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">9:00 AM</button>
              <button type="button" data-time="11:00" class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">11:00 AM</button>
              <button type="button" data-time="13:30" class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">1:30 PM</button>
              <button type="button" data-time="15:30" class="inline-flex items-center h-7 px-3 rounded-full bg-slate-100 text-slate-700 text-[13px] hover:bg-slate-200 active:scale-[.99]">3:30 PM</button>
            </div>
          </div>
          @error('time') <div class="mt-1 text-sm text-rose-600">• {{ $message }}</div> @enderror
          <p class="mt-1 text-[11px] text-slate-400">30-minute steps. Use ↑/↓ to adjust.</p>
        </div>
      </div>

      {{-- Availability badges --}}
      <div class="mt-3 flex flex-wrap items-center gap-3 text-[13px]">
        <span id="poolBadge"
              class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-slate-700 ring-1 ring-slate-200">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-400"></span>
          Checking availability…
        </span>

        @if($hasCoun)
          <span id="counBadge"
                class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-slate-700 ring-1 ring-slate-200">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            Counselor: checking…
          </span>
        @endif
      </div>

      {{-- Note --}}
      <div class="mt-6">
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
    </form>

    {{-- Sticky actions --}}
    <div class="sticky bottom-0 inset-x-0 bg-white/90 backdrop-blur border-t border-slate-200 px-5 py-3">
      <div class="max-w-3xl mx-auto flex items-center justify-end gap-3">
        <a href="{{ route('admin.appointments.show', $appointment->id) }}"
           class="h-9 inline-flex items-center px-4 rounded-lg ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">
          Cancel
        </a>

        <button type="submit" form="followForm" id="saveBtn"
                class="h-9 inline-flex items-center px-4 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 active:scale-[.99]">
          Save Follow-up
        </button>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
  // ---------- Small helpers ----------
  const pad = n => String(n).padStart(2,'0');
  const toISO = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const isWeekend = d => [0,6].includes(d.getDay()); // Sun=0, Sat=6
  const nextWeekday = d => { while(isWeekend(d)) d.setDate(d.getDate()+1); return d; };

  const dateEl = document.querySelector('#dateField');
  const timeEl = document.querySelector('#timeField');
  const poolBadge = document.querySelector('#poolBadge');
  const counBadge = document.querySelector('#counBadge'); // may be null
  const saveBtn   = document.querySelector('#saveBtn');
  const counselorId = {{ (int)($appointment->counselor_id ?? 0) }};

  // Min date = today (skip weekend)
  (function setMin(){
    const t = nextWeekday(new Date());
    dateEl.min = toISO(t);
  })();

  // Quick picks for date
  document.querySelectorAll('[data-pick]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const kind = btn.dataset.pick;
      let d = new Date(dateEl.value || dateEl.min || new Date());
      if(kind === 'tomorrow'){ d.setDate(d.getDate()+1); }
      else if(kind === 'nextmon'){ d.setDate(d.getDate() + ((8 - d.getDay()) % 7)); }
      else { d.setDate(d.getDate() + parseInt(kind,10)); }
      dateEl.value = toISO(nextWeekday(d));
      dateEl.dispatchEvent(new Event('change'));
    });
  });

  // Time presets
  document.querySelectorAll('[data-time]').forEach(btn=>{
    btn.addEventListener('click', ()=> { timeEl.value = btn.dataset.time; timeEl.dispatchEvent(new Event('change')); });
  });

  // Live counter for note
  (function () {
    document.querySelectorAll('.js-counted').forEach(el => {
      const max = parseInt(el.dataset.max || el.getAttribute('maxlength') || '4000', 10);
      const counter = el.parentElement.querySelector('.js-count');
      const paint = () => {
        if (el.value.length > max) el.value = el.value.slice(0,max);
        if (counter) counter.textContent = el.value.length;
      };
      ['input','change','paste'].forEach(ev => el.addEventListener(ev, ()=>requestAnimationFrame(paint)));
      paint();
    });
  })();

  // ---------- Availability (AJAX) ----------
  async function refreshCapacity() {
    if (!dateEl.value || !timeEl.value) return;

    paintPool('checking');
    if (counBadge) paintCoun('checking');

    try {
      const qs = new URLSearchParams({
        date: dateEl.value,
        time: timeEl.value
      });
      if (counselorId) qs.set('counselor_id', counselorId);

      const res = await fetch(`{{ route('admin.appointments.capacity') }}?${qs.toString()}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });
      
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      if (!data?.ok) throw new Error('Bad response');

      // pooled capacity
      paintPool(data.pooled_available);

      // original counselor availability (if any)
      let disable = false;

      if (counBadge && counselorId) {
        const state = data.counselor_available === true ? 'free'
                    : data.counselor_available === false ? 'busy'
                    : 'n/a';
        paintCoun(state);
        if (state === 'busy') disable = true;
      }

      // Also disable when no pooled capacity at all
      if ((Number(data.pooled_available) || 0) === 0) disable = true;

      saveBtn.disabled = disable;
      saveBtn.classList.toggle('opacity-50', disable);
      saveBtn.classList.toggle('cursor-not-allowed', disable);

    } catch (e) {
      paintPool('err');
      if (counBadge) paintCoun('err');
      saveBtn.disabled = true;
      saveBtn.classList.add('opacity-50','cursor-not-allowed');
    }
  }

  function paintPool(v){
    if (!poolBadge) return;
    if (v === 'checking') {
      poolBadge.innerHTML = `<span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-400"></span>Checking availability…`;
      return;
    }
    if (v === 'err') {
      poolBadge.innerHTML = `<span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500"></span>Error`;
      return;
    }
    const n = Number(v)||0;
    const color = n>0 ? 'bg-emerald-500' : 'bg-rose-500';
    poolBadge.innerHTML = `<span class="inline-block w-1.5 h-1.5 rounded-full ${color}"></span>${n} slot${n===1?'':'s'} free (pooled)`;
  }

  function paintCoun(state){
    if (!counBadge) return;
    if (state === 'checking') {
      counBadge.innerHTML = `<span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-400"></span>Counselor: checking…`;
      return;
    }
    if (state === 'err' || state === 'n/a') {
      counBadge.innerHTML = `<span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-400"></span>Counselor: n/a`;
      return;
    }
    const free = state === 'free';
    const color = free ? 'bg-emerald-500' : 'bg-rose-500';
    counBadge.innerHTML = `<span class="inline-block w-1.5 h-1.5 rounded-full ${color}"></span>Counselor: ${free ? 'available' : 'busy'}`;
  }

  // Trigger on load + any change
  ['change','input'].forEach(ev=>{
    dateEl.addEventListener(ev, refreshCapacity);
    timeEl.addEventListener(ev, refreshCapacity);
  });
  document.addEventListener('DOMContentLoaded', refreshCapacity);

  // Ctrl/Cmd + S to save
  window.addEventListener('keydown', e=>{
    if((e.ctrlKey || e.metaKey) && e.key.toLowerCase()==='s'){
      e.preventDefault();
      document.getElementById('followForm')?.requestSubmit();
    }
  });
</script>
@endpush
