{{-- resources/views/Counselor_Interface/availability/index.blade.php --}}
@extends('layouts.counselor')
@section('title','Counselor - Manage Availability')
@section('page_title','My Availability')

@section('content')
<div class="mx-auto max-w-6xl px-4 pt-0 pb-10 space-y-6">

  {{-- ============================
       CALENDAR (wide) + QUICK EDITOR (bottom)
     ============================ --}}
  <div class="bg-white rounded-2xl shadow-md ring-1 ring-slate-200/70">
    {{-- Header / Calendar --}}
    <section class="p-5 lg:p-8">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-slate-900 font-semibold tracking-tight">Interactive Calendar</h3>
          <p class="text-sm text-slate-500">Weekends disabled. Hover to preview, click to select. Keyboard friendly.</p>
        </div>
        <div class="flex items-center gap-3">

          {{-- 🔹 Accepting/Pause toggle --}}
            <form action="{{ route('counselor.availability.accepting') }}" method="POST" class="inline-flex items-center">
              @csrf
              <input type="hidden" name="accepting" value="{{ ($accepting ?? true) ? 0 : 1 }}">
              <button
                type="submit"
                class="px-3 py-1.5 rounded-lg text-sm font-semibold transition
                      {{ ($accepting ?? true)
                          ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                          : 'bg-slate-200 text-slate-700 hover:bg-slate-300' }}">
                {{ ($accepting ?? true) ? 'Accepting appointments' : 'Paused (hidden from students)' }}
              </button>
            </form>
            
          {{-- Month nav --}}
          <div class="inline-flex items-center gap-2">
            <button id="calPrev" type="button" class="ui-ghost" aria-label="Previous month">‹</button>
            <div id="calMonth" class="font-semibold text-indigo-600 tabular-nums"></div>
            <button id="calNext" type="button" class="ui-ghost" aria-label="Next month">›</button>
          </div>
        </div>
      </div>

      {{-- Calendar shell --}}
      <div class="mt-4 rounded-2xl bg-white ring-1 ring-slate-200/80 shadow-sm overflow-hidden">
        <div class="grid grid-cols-7 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500 bg-slate-50/60">
          <div class="py-2">Sun</div><div class="py-2">Mon</div><div class="py-2">Tue</div>
          <div class="py-2">Wed</div><div class="py-2">Thu</div><div class="py-2">Fri</div><div class="py-2">Sat</div>
        </div>
        <div id="calGrid" class="cal-grid grid grid-cols-7 gap-1 p-2 sm:p-3 lg:gap-2 xl:gap-3" role="grid" aria-label="Calendar dates"></div>
      </div>

      <div class="mt-3 flex items-center justify-between gap-3">
        <div class="text-xs text-slate-500">Tip: Use arrow keys to move, <kbd class="kbd">Enter</kbd> to select, <kbd class="kbd">Esc</kbd> to clear.</div>
        <div class="flex items-center gap-2">
          <button id="calClear" type="button" class="ui-ghost" aria-label="Clear selected date">Clear</button>
         <button id="calUse"   type="button" class="ui-primary" aria-label="Edit selected date" disabled>Edit date</button>
        </div>
      </div>

      <input type="hidden" id="pickedDate" value="">
    </section>

   {{-- Weekday Quick Editor --}}
    <section class="border-t border-slate-200/70 bg-white/80 p-5 lg:p-8">
      <div class="mb-3 flex items-center justify-between">
        <div>
          <h3 class="text-slate-900 font-semibold tracking-tight">Weekday Quick Editor</h3>
          <p class="text-sm text-slate-500">
            Use <span class="font-semibold">Disable</span> for a full-day block, or <span class="font-semibold">Edit hours</span> to fine-tune hourly blocks.
          </p>
        </div>
        <a id="openTableBtn"
           href="{{ route('counselor.availability.table') }}"
           class="table-btn-floating ui-peel inline-flex items-center gap-2"
           aria-label="Open full availability table"
           title="Open full availability table • Shortcut: T">
          <img class="btn-icon-img" src="{{ asset('images/icons/table.png') }}" width="18" height="18" alt="" />
          <span class="btn-label">View full availability table</span>
          <kbd class="kbd kbd-ghost" aria-hidden="true">T</kbd>
          <span class="btn-spinner" aria-hidden="true"></span>
        </a>
      </div>

      <ul class="mt-2 space-y-2.5" role="list">
        @php $wdList=[1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday']; @endphp
        @foreach($wdList as $wd => $wdLabel)
          <li class="group flex items-center justify-between p-3 rounded-xl ring-1 ring-slate-200/70 bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] hover:shadow-md transition">
            <div class="flex items-center gap-3">
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white text-sm font-bold shadow-sm select-none">
                {{ substr($wdLabel,0,3) }}
              </span>
              <div>
                <div class="font-semibold text-slate-800 leading-tight">{{ $wdLabel }}</div>
                <div class="text-xs text-slate-500 leading-tight">Defaults: 9–12 AM • 1–4 PM</div>
              </div>
            </div>

            {{-- Balanced buttons (no "Update") --}}
            <div class="flex items-center gap-2 w-[260px] max-w-full justify-end">
              <button
                class="ui-peel ui-sm btn-eq text-sm btn-toggle"
                type="button"
                data-action="disable-weekday"
                data-weekday="{{ $wd }}"
                aria-label="Disable {{ $wdLabel }}">
                Disable
              </button>

              <button
                type="button"
                class="js-open-recurring ui-primary ui-sm ui-peel btn-eq text-sm"
                data-weekday="{{ $wd }}"
                aria-label="Edit hours for {{ $wdLabel }}">
                Edit hours
              </button>
            </div>
          </li>
        @endforeach
      </ul>

      <p class="mt-3 text-[12px] text-slate-500">
        <span class="font-semibold">Disable</span> = full-day block (09:00–17:00). For date-specific edits, use the interactive calendar above.
      </p>
    </section>
  </div>

</div>

  {{-- ============================
      MODALS
      ============================ --}}

  {{-- Hour Tile Modal (unified for Date + Weekday scope) --}}
  <div id="hourModal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div id="hourBackdrop" class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px] opacity-0 transition-opacity"></div>
    <div class="absolute inset-0 flex items-end sm:items-center justify-center px-3 py-6">
      <div id="hourDialog"
         class="w-full max-w-2xl translate-y-4 sm:translate-y-0 opacity-0 scale-[0.98] rounded-2xl bg-white shadow-xl ring-1 ring-black/5 p-6 sm:p-8 transition-all"
          role="dialog" aria-modal="true" aria-labelledby="hourTitle" tabindex="-1" aria-describedby="hourDesc">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h2 id="hourTitle" class="text-base font-semibold">Edit hours</h2>
            <p id="hourDesc" class="mt-1 text-[12px] text-slate-500">
              <span id="scopeNote">Applies to this date only.</span>
              &nbsp;Toggle tiles to <span class="font-semibold">disable</span> hours for students.
              <span class="font-medium">12–1 PM lunch is locked.</span>
            </p>
          </div>

          <!-- Scope switcher -->
          <div class="flex items-center gap-2">
            <label for="scopeSelect" class="sr-only">Scope</label>
            <select id="scopeSelect"
            class="h-10 rounded-lg border border-slate-200 bg-white px-3 pr-9 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <!-- options are injected dynamically based on open() payload -->
            </select>
            <button id="hourCloseBtn" type="button" class="ui-ghost" aria-label="Close">✕</button>
          </div>
        </div>

        <!-- Quick actions (simplified) -->
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <button type="button" id="qaBlockAll" class="ui-ghost" title="Make all hours unavailable for students">Block all day</button>
          <button type="button" id="qaRestore"  class="ui-ghost" title="Restore the default available hours">Restore defaults</button>
        </div>

        <!-- Tiles -->
        <div class="mt-4 grid grid-cols-3 gap-2" id="hourTiles" role="group" aria-label="Hour tiles"></div>

        <!-- Footer -->
        <div class="mt-5 flex items-center justify-between gap-3">
          <div class="text-xs text-slate-500">
            <span>Press <kbd class="kbd">Space</kbd> to toggle a focused tile.</span>
            <span id="countSummary" class="ml-2"></span>
          </div>
          <div class="flex items-center gap-2">
            <button id="clearTiles" type="button" class="ui-ghost" aria-label="Clear all">Clear</button>
            <button id="saveTiles"  type="button" class="ui-primary" aria-label="Save changes">Save</button>
          </div>
        </div>

      </div>
    </div>
  </div>

{{-- ============================
     DATA SEED + ENDPOINTS
     ============================ --}}
<script>
  window.RECUR_BLOCKS = @json($blockedByWeekday ?? []);

  // Important: correct names that exist in routes/counselor.php
  const WEEKDAY_BLOCKS_ENDPOINT = @json(route('counselor.availability.weekdayBlocks', [], false));
  const DATE_BLOCKS_GET         = @json(route('counselor.availability.dateBlocks.get', [], false));
  const DATE_WINDOWS_SAVE       = @json(route('counselor.availability.dateWindows.save', [], false));
  const WEEKDAY_STORE_ENDPOINT  = @json(route('counselor.availability.weekdayStore',  [], false));
  const WEEKDAY_DISABLE_PRECHECK = @json(route('counselor.availability.weekdayDisable.precheck', [], false));

  // Helpers: safe JSON parsing from fetch responses that might be HTML on error
  async function safeJson(resp) {
    const ct = (resp.headers.get('content-type') || '').toLowerCase();
    if (ct.includes('application/json')) {
      return await resp.json();
    }
    // fallback: try text -> maybe 419/500 HTML
    const t = await resp.text();
    try { return JSON.parse(t); } catch { return { __html: t }; }
  }
</script>

{{-- ============================
     SWEETALERT HELPERS
     ============================ --}}
<script>

function fmtLongDate(iso) {
  const dt = new Date(String(iso));
  if (Number.isNaN(dt.getTime())) return String(iso); // fallback
  return new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric', year: 'numeric' }).format(dt);
}

const SwalTheme = { confirm:'#4f46e5', cancel:'#94a3b8', danger:'#e11d48' };
const Toast = Swal.mixin({ toast:true, position:'top-end', showConfirmButton:false, timer:1700, timerProgressBar:true });
function toastSuccess(t='Saved successfully'){ Toast.fire({ icon:'success', title:t }); }
function toastInfo(t='Updated'){ Toast.fire({ icon:'info', title:t }); }
function toastError(t='Something went wrong'){ Toast.fire({ icon:'error', title:t }); }
async function confirmDialog({ title='Are you sure?', text='', confirmText='Yes', danger=false }){
  const res = await Swal.fire({
    icon:'question', title, text, showCancelButton:true, confirmButtonText:confirmText, cancelButtonText:'Cancel',
    focusCancel:true, confirmButtonColor: danger ? SwalTheme.danger : SwalTheme.confirm, cancelButtonColor: SwalTheme.cancel, reverseButtons:true
  }); return res.isConfirmed;
}
</script>

{{-- ============================
     MAIN SCRIPTS
     ============================ --}}
<script>
/* ========== Open “full table” button ========== */
(function () {
  const btn = document.getElementById('openTableBtn');
  if (!btn) return;

  const shouldBypass = (e) => e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button === 1;

  btn.addEventListener('click', (e) => {
    if (shouldBypass(e)) return;
    e.preventDefault();

    const url = btn.getAttribute('href');
    btn.classList.add('is-loading');
    btn.setAttribute('aria-busy', 'true');

    Swal.fire({ title: 'Opening full table…', html: '<div style="font-size:12px;color:#64748b">Please wait</div>', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

    setTimeout(() => window.location.assign(url), 600);
  });

  // Keyboard shortcut: T
  document.addEventListener('keydown', (e) => {
    const a = document.activeElement;
    const typing = a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable);
    if (!typing && (e.key === 't' || e.key === 'T')) { e.preventDefault(); btn.click(); }
  });
})();

/* ------- tiny helpers ------- */
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
const fmtMonth = (d) => new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(d);
const isWeekend = (d) => [0, 6].includes(d.getDay());

  // --- state helpers for the Disable/Enable toggle (recurring weekdays) ---
  function isWeekdayDisabled(wd) {
    const m = window.RECUR_BLOCKS || {};
    const arr = m[String(wd)] || [];
    return Array.isArray(arr) && arr.length > 0; // has any recurring blocked tiles
  }

  function styleToggleButton(btn, wd) {
    const disabled = isWeekdayDisabled(wd);
    btn.dataset.state = disabled ? 'disabled' : 'enabled';
    btn.textContent   = disabled ? 'Enable' : 'Disable';

    // swap visual styles
    btn.classList.remove('ui-danger','ui-success');
    if (disabled) btn.classList.add('ui-success'); else btn.classList.add('ui-danger');
    // keep the rest: ui-peel ui-sm btn-eq text-sm
  }

  // Initialize all weekday toggle buttons based on RECUR_BLOCKS
 function initWeekdayToggleButtons() {
  document.querySelectorAll('button[data-action="disable-weekday"]').forEach((btn) => {
    const wd = Number(btn.dataset.weekday || 0);
      // reset any residual classes from SSR / previous states
      btn.classList.remove('ui-danger','ui-success');
        styleToggleButton(btn, wd);
      // reveal after styling to avoid red flash
      btn.classList.remove('prehide');
      btn.style.opacity = '1';
      });
  }

  // Call on first paint (after DOM is ready); safe to call again after saves
  document.addEventListener('DOMContentLoaded', initWeekdayToggleButtons);

/* ========== Calendar ========== */
(function () {
  const calGrid = $('#calGrid'), calMonth = $('#calMonth');
  const calPrev = $('#calPrev'), calNext = $('#calNext'), calUse = $('#calUse'), calClear = $('#calClear');
  const pickedInput = $('#pickedDate');

  const today = new Date(); today.setHours(0,0,0,0);
  let view = new Date(today.getFullYear(), today.getMonth(), 1);
  let picked = null;

  function render() {
    calMonth.textContent = fmtMonth(view);
    calGrid.innerHTML = '';

    const lead = new Date(view.getFullYear(), view.getMonth(), 1).getDay();
    for (let i = 0; i < lead; i++) calGrid.appendChild(document.createElement('div'));

    const last = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
    for (let d = 1; d <= last; d++) {
      const cd = new Date(view.getFullYear(), view.getMonth(), d); cd.setHours(0,0,0,0);
      const weekend = isWeekend(cd), past = cd < today, disabled = weekend || past;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cal-day';
      btn.textContent = d;
      btn.setAttribute('role', 'gridcell');
      btn.setAttribute('aria-label', cd.toDateString());

      if (disabled) {
        btn.disabled = true;
        btn.classList.add('cal-day--disabled');
        btn.title = weekend ? 'Closed (Sat–Sun)' : 'Past date';
      } else {
        const open = () => { picked = new Date(cd); highlight(); updateUse(); openForDate(cd); };
        btn.addEventListener('click', open);
        btn.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
      }

      if (picked && ymd(picked) === ymd(cd)) btn.classList.add('cal-day--selected');
      calGrid.appendChild(btn);
    }
    highlight(); updateUse();
  }

  function updateUse() { calUse.disabled = !picked; }
  function highlight() {
    $$('.cal-day', calGrid).forEach(b => b.classList.remove('cal-day--selected'));
    if (!picked) return;
    if (picked.getFullYear() === view.getFullYear() && picked.getMonth() === view.getMonth()) {
      const d = picked.getDate();
      $$('.cal-day', calGrid).forEach(b => { if (b.textContent == String(d) && !b.disabled) b.classList.add('cal-day--selected'); });
    }
  }

  calPrev.addEventListener('click', () => { view.setMonth(view.getMonth() - 1); render(); });
  calNext.addEventListener('click', () => { view.setMonth(view.getMonth() + 1); render(); });
  calUse.addEventListener('click', () => { if (!picked) return; pickedInput.value = ymd(picked); toastInfo('Date selected'); });
  calClear.addEventListener('click', () => { picked = null; pickedInput.value = ''; highlight(); updateUse(); toastInfo('Cleared'); });

  // Expose to open modal in DATE mode
  window.openForDate = async function (dateObj) {
    const dateStr = ymd(dateObj);
    const wd = (dateObj.getDay() + 6) % 7 + 1; // ISO weekday 1..7
    if (wd >= 6) return; // ignore weekends
    await window.HourTiles.open('date', { date: dateStr, weekday: wd });
  };

  document.addEventListener('keydown', (e) => {
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Escape'].includes(e.key)) return;
    if (e.key === 'Escape') { picked = null; pickedInput.value = ''; highlight(); updateUse(); return; }
    e.preventDefault();
    if (!picked) picked = new Date(today);
    const delta = e.key === 'ArrowLeft' ? -1 : e.key === 'ArrowRight' ? 1 : e.key === 'ArrowUp' ? -7 : 7;
    picked.setDate(picked.getDate() + delta);
    while (isWeekend(picked) || picked < today) { picked.setDate(picked.getDate() + (delta > 0 ? 1 : -1)); }
    view = new Date(picked.getFullYear(), picked.getMonth(), 1);
    render();
  });

  render();
})();

/* ========== Hour Modal (unified Date + Weekday scope) ========== */
(function () {
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;

  const modal = $('#hourModal'), backdrop = $('#hourBackdrop'), dialog = $('#hourDialog');
  const btnClose = $('#hourCloseBtn'), tilesWrap = $('#hourTiles'), btnClear = $('#clearTiles'), btnSave = $('#saveTiles');
  const titleEl = $('#hourTitle'), descEl = $('#hourDesc'), scopeNote = $('#scopeNote');
  const scopeSelect = $('#scopeSelect');
  const qaBlockAll = $('#qaBlockAll'), qaRestore = $('#qaRestore');
  const countSummary = $('#countSummary');

  btnClear?.setAttribute('type', 'button');
  btnSave?.setAttribute('type', 'button');
  btnClose?.setAttribute('type', 'button');

  const HOURS = [
    ['09:00','10:00'], ['10:00','11:00'], ['11:00','12:00'],
    ['12:00','13:00','locked'],
    ['13:00','14:00'], ['14:00','15:00'], ['15:00','16:00']
  ];

  let mode = 'weekday';              // 'weekday' | 'date'
  let activeWeekday = null;          // 1..7
  let activeDate = null;             // 'YYYY-MM-DD'
  let blocked = new Set();           // 'HH:MM-HH:MM'

  const key = (a,b) => `${a}-${b}`;
  const norm = (t) => (t || '').slice(0,5);
  const to12 = (t) => { const [H,M] = t.split(':').map(Number); const ap = H>=12 ? 'PM':'AM'; const h=((H+11)%12)+1; return `${h}:${String(M).padStart(2,'0')} ${ap}`; };
  const wdLong = {1:'Monday',2:'Tuesday',3:'Wednesday',4:'Thursday',5:'Friday',6:'Saturday',7:'Sunday'};

  function setFromArray(arr){ blocked.clear(); arr.forEach(([s,e]) => blocked.add(key(norm(s), norm(e)))); }
  function preloadWeekday(wd){ const arr = (window.RECUR_BLOCKS && window.RECUR_BLOCKS[String(wd)]) || []; setFromArray(arr); }
  async function preloadDate(dateStr){
    blocked.clear();
    try{
      const url = `${DATE_BLOCKS_GET}?date=${encodeURIComponent(dateStr)}`;
      const resp = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      if(!resp.ok) return;
      const data = await safeJson(resp);
      const arr = (data.blocks || []).map(b => [norm(b.start), norm(b.end)]);
      setFromArray(arr);
    }catch(_){}
  }

  function buildTiles(){
    tilesWrap.innerHTML = '';
    HOURS.forEach(([s,e,lock]) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'tile';
      b.textContent = `${to12(s)}–${to12(e)}`;
      b.dataset.start = s; b.dataset.end = e;

      const isOff = blocked.has(key(s,e));
      b.setAttribute('aria-pressed', isOff ? 'false' : 'true');
      if (isOff) b.classList.add('tile-off','tile-off--danger');

      if (lock) {
        b.classList.add('tile-locked'); b.setAttribute('aria-disabled','true'); b.title = 'Lunch break (locked)';
      } else {
        b.addEventListener('click', () => toggle(b));
        b.addEventListener('keydown', (ev) => { if (ev.key === ' ' || ev.key === 'Spacebar') { ev.preventDefault(); toggle(b); }});
      }
      tilesWrap.appendChild(b);
    });
    refreshCounts();
  }

  function toggle(b){
    const k = key(b.dataset.start, b.dataset.end);
    const isOff = blocked.has(k);
    if (isOff) { blocked.delete(k); b.classList.remove('tile-off','tile-off--danger'); b.setAttribute('aria-pressed','true'); }
    else       { blocked.add(k);    b.classList.add('tile-off','tile-off--danger');   b.setAttribute('aria-pressed','false'); }
    refreshCounts();
  }

  function clearAll(){
    blocked.clear();
    $$('.tile', tilesWrap).forEach(b => {
      if (!b.classList.contains('tile-locked')) {
        b.classList.remove('tile-off','tile-off--danger');
        b.setAttribute('aria-pressed','true');
      }
    });
    refreshCounts();
  }

  function refreshCounts(){
    const unlocks = HOURS.filter(([,,lock]) => !lock).length;
    const off = blocked.size;
    const on  = unlocks - off;
    if (countSummary) countSummary.textContent = `• ${on} available, ${off} blocked`;
  }

  // Quick actions (with click feedback)
  function blockAllDay(){
    blocked.clear();
    HOURS.forEach(([s,e,lock]) => { if (!lock) blocked.add(key(s,e)); });
    buildTiles();
  }
  function restoreDefaults(){
    blocked.clear(); // your default is all non-lunch tiles available
    buildTiles();
  }

  // subtle success flash on the button that was clicked
  function flashDone(button){
    if (!button) return;
    button.classList.add('btn-done');
    setTimeout(() => button.classList.remove('btn-done'), 900);
  }

  qaBlockAll?.addEventListener('click', () => { blockAllDay();  flashDone(qaBlockAll);  });
  qaRestore ?.addEventListener('click', () => { restoreDefaults(); flashDone(qaRestore); });

  function renderScopeOptions(){
    scopeSelect.innerHTML = '';
    if (mode === 'date') {
      // Option A: This date; Option B: Every <weekday>
      const wd = ((new Date(activeDate)).getDay() + 6) % 7 + 1; // ISO 1..7
      scopeSelect.append(new Option(`Date • ${activeDate}`, 'date', true, true));
      scopeSelect.append(new Option(`Every ${wdLong[wd]}`, `weekday:${wd}`));
      scopeNote.textContent = `Applies to ${activeDate} only.`;
    } else {
      scopeSelect.append(new Option(`Every ${wdLong[activeWeekday]}`, `weekday:${activeWeekday}`, true, true));
      scopeSelect.append(new Option('Date • pick…', 'date:pick'));
      scopeNote.textContent = `Applies to every ${wdLong[activeWeekday]}.`;
    }
  }

  scopeSelect.addEventListener('change', async (e) => {
    const v = String(e.target.value);
    if (v === 'date:pick') {
      // simple native date picker
      const dt = prompt('Pick a date (YYYY-MM-DD):', activeDate || ymd(new Date()));
      if (!dt) { renderScopeOptions(); return; }
      mode = 'date'; activeDate = dt; await preloadDate(activeDate);
    } else if (v.startsWith('weekday:')) {
      mode = 'weekday'; activeWeekday = Number(v.split(':')[1]); preloadWeekday(activeWeekday);
    } else if (v === 'date') {
      mode = 'date'; await preloadDate(activeDate);
    }
    buildTiles(); renderScopeOptions();
  });

  async function open(_mode, payload){
    mode = _mode;
    if (mode === 'weekday') {
      activeWeekday = Number(payload.weekday);
      activeDate = null;
      titleEl.textContent = 'Edit hours';
      preloadWeekday(activeWeekday);
    } else {
      activeDate = payload.date;
      activeWeekday = Number(payload.weekday);
      titleEl.textContent = 'Edit hours';
      await preloadDate(activeDate);
    }
    buildTiles();
    renderScopeOptions();

    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      backdrop.classList.add('opacity-100');
      dialog.classList.remove('opacity-0','scale-[0.98]','translate-y-4');
      dialog.focus();
    });
  }

  function close(){
    // If there are unsaved changes, optionally guard here (lightweight):
    // (omitted by default to keep behavior same)
    backdrop.classList.remove('opacity-100');
    dialog.classList.add('opacity-0','scale-[0.98]','translate-y-4');
    setTimeout(() => modal.classList.add('hidden'), 140);
  }

  btnClear.addEventListener('click', clearAll);
  btnClose.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  modal.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

  /* ===== SAVE ===== */
  btnSave.addEventListener('click', async (ev) => {
    ev.preventDefault(); ev.stopPropagation();

    const HOURS_DEF = HOURS.filter(([,,lock]) => !lock);
    const blocks = [], opens = [];
    HOURS_DEF.forEach(([s,e]) => {
      const k = `${s}-${e}`;
      if (blocked.has(k)) blocks.push({ start: s, end: e });
      else                opens.push({ start: s, end: e });
    });

    btnSave.disabled = true;
    Swal.fire({ title: 'Saving changes…', didOpen: () => Swal.showLoading(), allowOutsideClick: false, showConfirmButton: false });

    try{
      let ok = false;
      if (mode === 'weekday') {
        const resp = await fetch(WEEKDAY_BLOCKS_ENDPOINT, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': @json(csrf_token()),
          },
          body: JSON.stringify({ weekday: activeWeekday, blocks })
        });
        const data = await safeJson(resp);
        if (!resp.ok) {
          Swal.close();
          const msg = data?.message || (resp.status === 419 ? 'Session expired. Please reload.' : `HTTP ${resp.status}`);
          await Swal.fire({ icon: 'error', title: 'Save failed', text: msg });
          btnSave.disabled = false; return;
        }
        window.RECUR_BLOCKS[String(activeWeekday)] = blocks.map(o => [o.start, o.end]);
        // NEW: restyle the corresponding toggle button immediately
        const tBtn = document.querySelector(`button[data-action="disable-weekday"][data-weekday="${activeWeekday}"]`);
        if (tBtn) styleToggleButton(tBtn, activeWeekday);
        ok = true;
      } else {
        const resp = await fetch(DATE_WINDOWS_SAVE, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': @json(csrf_token()),
          },
          body: JSON.stringify({ date: activeDate, opens, blocks })
        });
        const data = await safeJson(resp);
        if (!resp.ok) {
          Swal.close();
          if (data?.reason === 'has_appointments') {
            const esc = (s='') => String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;');
            const rows = (data.conflicts || []).sort((a,b)=>String(a.time).localeCompare(String(b.time))).map(c => {
              const time = esc(c.time12 || c.time || '');
              const name = esc(c.student_name || 'Student');
              const url  = c.appt_url || '#';
              return `
                <span class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-slate-700">
                  <span class="px-1.5 py-0.5 text-xs font-semibold rounded-md bg-slate-100 ring-1 ring-slate-200">${time}</span>
                  <a href="${url}" class="swal-link underline decoration-indigo-400 hover:decoration-2" title="Open appointment">${name}</a>
                </span>`;
            }).join('<br>');
            await Swal.fire({ icon: 'error', title: "Can't disable booked slot(s)", html: `
              <p class="text-slate-700 mb-2">There are appointment(s) on <b>${esc(fmtLongDate(activeDate))}</b> inside the time(s) you tried to disable.</p>
              <div class="text-left">${rows || '—'}</div>
              <p class="mt-3 text-[12px] text-slate-500">Tip: click a student name to open the appointment.</p>
            `, confirmButtonColor: '#e11d48' });
          } else {
            const msg = data?.message || (resp.status === 419 ? 'Session expired. Please reload.' : `HTTP ${resp.status}`);
            await Swal.fire({ icon:'error', title:'Save failed', text: msg });
          }
          btnSave.disabled = false; return;
        }
        ok = true;
      }

      Swal.close();
      btnSave.disabled = false;
      if (!ok) { toastError('Save failed'); return; }
      close();
      toastSuccess('Update saved');
    }catch(_e){
      Swal.close();
      btnSave.disabled = false;
      toastError('Network error');
    }
  });

  // expose
  window.HourTiles = { open };
})();
// ---------- Quick Editor buttons (Edit hours / Disable) ----------
// ---------- Quick Editor buttons (Edit hours / Disable<->Enable toggle) ----------
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-action], .js-open-recurring');
  if (!btn) return;

  const wd = Number(btn.dataset.weekday || 0);

  // "Edit hours" — open unified modal in WEEKDAY mode
  if (btn.classList.contains('js-open-recurring')) {
    window.HourTiles.open('weekday', { weekday: wd });
    return;
  }

  // Toggle click for Disable/Enable
  if (btn.dataset.action === 'disable-weekday') {
    const currentlyDisabled = isWeekdayDisabled(wd);

    // === ENABLE flow (was disabled -> make it enabled by clearing blocks) ===
    if (currentlyDisabled) {
      const ok = await confirmDialog({
        title: 'Enable this weekday?',
        text: 'Students will see your default recurring slots again (09–12 • 1–4) unless you’ve customized them.',
        confirmText: 'Enable',
      });
      if (!ok) return;

      try {
        Swal.fire({ title:'Applying…', didOpen:()=>Swal.showLoading(), allowOutsideClick:false, showConfirmButton:false });
        const resp = await fetch(WEEKDAY_BLOCKS_ENDPOINT, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': @json(csrf_token()),
          },
          body: JSON.stringify({ weekday: wd, blocks: [] }) // 👈 empty = clear blocks
        });
        const data = await safeJson(resp);
        Swal.close();

        if (!resp.ok) {
          toastError(data?.message || `HTTP ${resp.status}`);
          return;
        }

        // update local cache: no blocks for this weekday
        window.RECUR_BLOCKS[String(wd)] = [];
        styleToggleButton(btn, wd);
        toastSuccess('Weekday enabled');
      } catch {
        Swal.close();
        toastError('Network error');
      }
      return;
    }

    // === DISABLE flow (was enabled -> apply full block) ===
    // keep your precheck & fullBlock flow
    try {
      const url  = `${WEEKDAY_DISABLE_PRECHECK}?weekday=${encodeURIComponent(wd)}`;
      const resp = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const data = await safeJson(resp);
      const conflicts = Array.isArray(data?.conflicts) ? data.conflicts : [];

      if (conflicts.length) {
        const esc = (s='') => String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;');
        const rows = conflicts
          .sort((a,b)=>String(a.time).localeCompare(String(b.time)))
          .map(c => `
            <span class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-slate-700">
              <span class="px-1.5 py-0.5 text-xs font-semibold rounded-md bg-slate-100 ring-1 ring-slate-200">${esc(c.time12 || c.time || '')}</span>
              <a href="${c.appt_url || '#'}" class="swal-link underline decoration-indigo-400 hover:decoration-2" title="Open appointment">${esc(c.student_name || 'Student')}</a>
            </span>`).join('<br>');

        await Swal.fire({
          icon:'error',
          title:"Can't disable booked slot(s)",
          html:`<div class="text-left">${rows || '—'}</div>`,
          confirmButtonColor:'#e11d48'
        });
        return;
      }
    } catch (_) {
      toastError('Precheck failed. Please retry.');
      return;
    }

    const ok = await confirmDialog({
      title: 'Disable this weekday?',
      text: 'This will block 9–12 AM and 1–4 PM for students.',
      confirmText: 'Disable',
      danger: true
    });
    if (!ok) return;

    // Reuse your existing function
    await fullBlock(wd);

    // update button state immediately
    window.RECUR_BLOCKS[String(wd)] = [['09:00','12:00'],['13:00','17:00']]; // mirror what you sent
    styleToggleButton(btn, wd);
  }
});

// Sends a full-day block to the server and updates RECUR_BLOCKS cache
async function fullBlock(wd) {
  const blocks = [{ start: '09:00', end: '12:00' }, { start: '13:00', end: '17:00' }];

  // No precheck here — it's already done in the click handler
  Swal.fire({
    title: 'Applying disable…',
    didOpen: () => Swal.showLoading(),
    allowOutsideClick: false,
    showConfirmButton: false
  });

  try {
    const resp = await fetch(WEEKDAY_BLOCKS_ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': @json(csrf_token()),
      },
      body: JSON.stringify({ weekday: wd, blocks })
    });

    const data = await safeJson(resp);
    Swal.close();

    if (!resp.ok) {
      const msg = data?.message || (resp.status === 419 ? 'Session expired. Please reload.' : `HTTP ${resp.status}`);
      toastError(msg);
      return;
    }

    // Update cache so the next open reflects latest
    window.RECUR_BLOCKS = window.RECUR_BLOCKS || {};
    window.RECUR_BLOCKS[String(wd)] = blocks.map(o => [o.start, o.end]);

    toastSuccess('Weekday disabled');
  } catch {
    Swal.close();
    toastError('Network error');
  }
}
</script>

{{-- ============================
     STYLES
     ============================ --}}
<style>
:root { --ui-h: 44px; }
.kbd{ padding:2px 6px; border:1px solid #e2e8f0; border-bottom-width:2px; border-radius:6px; font-size:11px; background:#fff;}
.tabular-nums{ font-variant-numeric: tabular-nums; }

/* Buttons */
.ui-primary,.ui-ghost,.ui-danger,.ui-success{
   height:var(--ui-h);
   padding:0 14px;
   border-radius:12px;
   font-weight:700;
   transition:all .15s ease;
 }
 
.ui-primary{ background:linear-gradient(180deg,#6366f1,#4f46e5); color:#fff; border:1px solid #4f46e5; box-shadow:0 2px 10px rgba(99,102,241,.18);}
.ui-primary:hover{ filter:brightness(1.05); }
.ui-danger{ background:linear-gradient(180deg,#f43f5e,#e11d48); color:#fff; border:1px solid #e11d48; box-shadow:0 2px 10px rgba(225,29,72,.18);}
.ui-danger:hover{ filter:brightness(1.05); }
.ui-ghost{ background:#fff; color:#334155; border:1px solid #e5e7eb;}
.ui-ghost:hover{ background:#f8fafc; }
.ui-peel{ position:relative; overflow:hidden; }
.ui-peel::after{ content:''; position:absolute; inset:auto -40% -60% -40%; height:120%; transform:skewY(-8deg);
  background:radial-gradient(80% 60% at 50% 20%,rgba(255,255,255,.35),rgba(255,255,255,0)); opacity:.3; transition:opacity .2s;}
.ui-peel:hover::after{ opacity:.6; }

/* Calendar days */
.cal-day{ height:40px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; font-weight:700; color:#0f172a;
  box-shadow:0 1px 0 rgba(0,0,0,.02); transition:transform .08s ease, box-shadow .15s ease, border-color .15s ease, background-color .15s ease; }
.cal-day:hover{ background:#eef2ff; border-color:#c7d2fe; box-shadow:0 3px 10px rgba(99,102,241,.15); transform:translateY(-1px);}
.cal-day:focus-visible{ outline:2px solid #6366f1; outline-offset:2px;}
.cal-day--selected{ background:#4f46e5; color:#fff; border-color:#4f46e5; box-shadow:0 0 0 2px rgba(99,102,241,.35), 0 6px 18px rgba(99,102,241,.35);}
.cal-day--disabled{ background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb; cursor:not-allowed;}
@media (min-width: 1024px){ .cal-grid .cal-day{ height: 52px; font-size: 15px; } }
@media (min-width: 1280px){ .cal-grid .cal-day{ height: 56px; font-size: 16px; } }

/* Modal tiles */
.tile{ height:44px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; font-weight:800; color:#0f172a; display:flex; align-items:center; justify-content:center; transition:all .15s ease; }
.tile:hover{ background:#eef2ff; border-color:#c7d2fe; transform:translateY(-1px);}
.tile-locked{ background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb; cursor:not-allowed;}
.tile-locked:hover{ transform:none; }

/* Disabled (OFF) visual + RED emphasis) */
.tile-off{ text-decoration:line-through; }
.tile-off--danger{ background: linear-gradient(180deg,#fda4af,#fb7185); border-color:#fb7185; color:#fff; box-shadow:0 2px 10px rgba(225,29,72,.18);}
.tile-off--danger:hover{ filter:brightness(1.03); transform:none; }

/* Floating pill button */
.table-btn-floating{
  --btn-h: 42px;
  height: var(--btn-h);
  padding: 0 14px;
  border-radius: 999px;
  background: #ffffff;
  border: 1px solid rgba(148,163,184,.35);
  box-shadow: 0 1px 2px rgba(15,23,42,.06), 0 6px 16px rgba(99,102,241,.10);
  color: #0f172a;
  font-weight: 700;
  transition: border-color .15s ease, box-shadow .2s ease, transform .18s ease, background-color .15s ease, opacity .2s ease;
}
.table-btn-floating:hover{ transform: translateY(-1px); box-shadow: 0 3px 10px rgba(15,23,42,.08), 0 12px 26px rgba(99,102,241,.18); }
.table-btn-floating .btn-icon-img{ width: 18px; height: 18px; display: inline-block; vertical-align: middle; }
.kbd-ghost{ margin-left: 6px; font-weight: 600; background: #fff; color: #475569; border-color: #e2e8f0; }
.table-btn-floating.is-loading{ pointer-events: none; opacity: .85; }
.table-btn-floating .btn-spinner{ display:none; width: 14px; height: 14px; margin-left: 6px; border-radius: 50%; border: 2px solid #c7d2fe; border-top-color: #4f46e5; animation: spin .7s linear infinite; }
.table-btn-floating.is-loading .btn-spinner{ display:inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce){
  .table-btn-floating, .table-btn-floating *{ transition: none !important; animation: none !important; }
}
/* Compact button size */
.ui-sm{
  --ui-h: 36px;           /* shorter height */
  padding: 0 10px;        /* tighter horizontal padding */
  border-radius: 10px;    /* slightly smaller radius */
  font-weight: 600;       /* a bit lighter than 700 */
  line-height: 1;
}

/* Equal-ish widths but smaller */
.btn-eq{ min-width: 7.25rem; text-align:center; } /* ~116px */
@media (max-width: 480px){
  .btn-eq{ min-width: 0; flex:1 1 auto; }
}
/* Larger quick-action buttons */
.ui-lg{
  --ui-h: 48px;
  padding: 0 16px;
  border-radius: 14px;
  font-size: 14px;
  font-weight: 700;
}

/* More spacious tiles */
.tile{
  height: 54px;                 /* was 44px */
  font-size: 14px;              /* bump */
  padding: 0 10px;              /* breathing room on narrow screens */
}
@media (min-width: 640px){
  .tile{ height: 58px; font-size: 15px; }
}

/* Slightly larger body text inside modal footnote */
#countSummary{ font-size: 12px; }

/* Compact scope select with a visible arrow */
#hourDialog #scopeSelect{
  /* size */
  min-width: 190px;         /* was 220px */
  height: 38px;             /* was 42px */
  padding: 0 28px 0 10px;   /* room for arrow on the right */
  border-radius: 10px;

  /* visuals */
  font-weight: 700;
  border: 1px solid #e5e7eb;
  background-color: #fff;

  /* ensure arrow renders consistently */
  appearance: none;                 /* hide native arrow */
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 14px 14px;

  /* custom chevron (always visible) */
  background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M5.5 7.5L10 12l4.5-4.5' stroke='%2364758b' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}
#hourDialog #scopeSelect:focus{
  outline: none;
  box-shadow: 0 0 0 3px rgba(99,102,241,.25);
  border-color: #6366f1;
}
/* Dropdown option sizing (where supported) */
#hourDialog #scopeSelect option{
  padding: 6px 10px;
  font-size: 14px;
}
/* Click feedback for Block/Restore (green flash) */
.btn-done{
  background: linear-gradient(180deg,#22c55e,#16a34a) !important;
  color:#fff !important;
  border-color:#16a34a !important;
  box-shadow: 0 2px 10px rgba(16,185,129,.25) !important;
}
/* Green success button used for "Enable" */
.ui-success{
  background: linear-gradient(180deg,#22c55e,#16a34a);
  color:#fff;
  border:1px solid #16a34a;
  box-shadow:0 2px 10px rgba(16,185,129,.18);
}
.ui-success:hover{ filter:brightness(1.05); }

/* Prevent first-paint color flash until JS styles the toggle */
[data-action="disable-weekday"].btn-toggle{
  opacity: 0;                 /* hidden until JS sets opacity to 1 */
  transition: opacity .12s ease;
}

/* Clearer focus states on keyboard nav */
.ui-danger:focus-visible,
.ui-success:focus-visible,
.ui-primary:focus-visible,
.ui-ghost:focus-visible{
  outline: 2px solid #6366f1;
  outline-offset: 2px;
}

/* Subtle hover lift for success/danger to match primary feel */
.ui-success:hover { filter: brightness(1.05); }
.ui-danger:hover  { filter: brightness(1.05); }
</style>
@endsection
