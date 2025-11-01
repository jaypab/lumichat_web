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
        <div class="inline-flex items-center gap-2">
          <button id="calPrev" type="button" class="ui-ghost" aria-label="Previous month">‹</button>
          <div id="calMonth" class="font-semibold text-indigo-600 tabular-nums"></div>
          <button id="calNext" type="button" class="ui-ghost" aria-label="Next month">›</button>
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
          <button id="calUse"   type="button" class="ui-primary" aria-label="Use selected date" disabled>Use date</button>
        </div>
      </div>

      <input type="hidden" id="pickedDate" value="">
    </section>

    {{-- Weekday Quick Editor --}}
    <section class="border-t border-slate-200/70 bg-white/80 p-5 lg:p-8">
      <div class="mb-3 flex items-center justify-between">
        <div>
          <h3 class="text-slate-900 font-semibold tracking-tight">Weekday Quick Editor</h3>
          <p class="text-sm text-slate-500">Click “Update” to disable hour tiles (students won’t see disabled hours).</p>
        </div>
        <a id="openTableBtn"
          href="{{ route('counselor.availability.table') }}"
          class="table-btn-floating ui-peel inline-flex items-center gap-2"
          aria-label="Open full availability table"
          title="Open full availability table • Shortcut: T"
        >
          <img
            class="btn-icon-img"
            src="{{ asset('images/icons/table.png') }}"
            width="18" height="18"
            alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"
          />
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
            <div class="flex items-center gap-2">
              <button class="ui-primary ui-peel" type="button" data-action="open-hour-modal" data-weekday="{{ $wd }}" aria-label="Update {{ $wdLabel }}">Update</button>
              <button class="ui-danger ui-peel"  type="button" data-action="disable-weekday" data-weekday="{{ $wd }}" aria-label="Disable {{ $wdLabel }}">Disable</button>
            </div>
          </li>
        @endforeach
      </ul>

      <p class="mt-3 text-[12px] text-slate-500">“Disable” creates a full-day block (09:00–17:00). “Update” lets you toggle hourly blocks.</p>
    </section>
  </div>

</div>

{{-- ============================
     MODAL: HOUR TILE TOGGLER
     ============================ --}}
<div id="hourModal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
  <div id="hourBackdrop" class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px] opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-end sm:items-center justify-center px-3 py-6">
    <div id="hourDialog"
         class="w-full max-w-lg translate-y-4 sm:translate-y-0 opacity-0 scale-[0.98] rounded-2xl bg-white shadow-xl ring-1 ring-black/5 p-5 sm:p-6 transition-all"
         role="dialog" aria-modal="true" aria-labelledby="hourTitle" tabindex="-1" aria-describedby="hourDesc">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 id="hourTitle" class="text-base font-semibold">Update weekday windows</h2>
          <p id="hourDesc" class="mt-1 text-[12px] text-slate-500">Toggle tiles to <span class="font-semibold">disable</span> hours for students. <span class="font-medium">12–1 PM lunch is locked.</span></p>
        </div>
        <button id="hourCloseBtn" type="button" class="ui-ghost" aria-label="Close">✕</button>
      </div>

      <div class="mt-4 grid grid-cols-3 gap-2" id="hourTiles" role="group" aria-label="Hour tiles"></div>

      <div class="mt-5 flex items-center justify-between gap-3">
        <div class="text-xs text-slate-500">Press <kbd class="kbd">Space</kbd> to toggle a focused tile.</div>
        <div class="flex items-center gap-2">
          <button id="clearTiles" type="button" class="ui-ghost" aria-label="Clear all">Clear</button>
          <button id="saveTiles"  type="button" class="ui-primary" aria-label="Save changes">Save</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ============================
     DATA SEED for modal
     ============================ --}}
<script>
  window.RECUR_BLOCKS = @json($blockedByWeekday ?? []);
  const WEEKDAY_BLOCKS_ENDPOINT = @json(route('counselor.availability.weekdayBlocks', [], false));
  const DATE_BLOCKS_GET         = @json(route('counselor.availability.dateBlocks.get', [], false));
  const DATE_WINDOWS_SAVE       = @json(route('counselor.availability.dateWindows.save', [], false));
</script>

{{-- ============================
     SWEETALERT HELPERS
     ============================ --}}
<script>
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
     SCRIPTS
     ============================ --}}
{{-- ============================
     SCRIPTS (drop-in replacement)
     ============================ --}}
<script>
(function(){
  const btn = document.getElementById('openTableBtn');
  if(!btn) return;

  // Open with loader unless user is using new-tab gestures
  function shouldBypass(e){
    return e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button === 1; // new tab/window
  }

  btn.addEventListener('click', (e) => {
    if (shouldBypass(e)) return;        // let browser handle it
    e.preventDefault();

    const url = btn.getAttribute('href');
    // visual state on the button
    btn.classList.add('is-loading');
    btn.setAttribute('aria-busy','true');

    // SweetAlert loader
    Swal.fire({
      title: 'Opening full table…',
      html: '<div style="font-size:12px;color:#64748b">Please wait</div>',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading()
    });

    // small delay for UX (and to show the effect)
    setTimeout(() => {
      window.location.assign(url);
    }, 600);
  });

  // Keyboard shortcut: T
  document.addEventListener('keydown', (e) => {
    // ignore when typing in inputs/textareas/contenteditable
    const a = document.activeElement;
    const typing = a && (
      a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable
    );
    if (typing) return;

    if ((e.key === 't' || e.key === 'T')) {
      e.preventDefault();
      btn.click();
    }
  });
})();
/* ------- tiny helpers ------- */
const $  = (s, r=document)=>r.querySelector(s);
const $$ = (s, r=document)=>Array.from(r.querySelectorAll(s));
const ymd = (d)=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
const fmtMonth = (d)=>new Intl.DateTimeFormat(undefined,{month:'long',year:'numeric'}).format(d);
const isWeekend = (d)=>[0,6].includes(d.getDay());

/* ============================
   CALENDAR
   ============================ */
(function(){
  const calGrid=$('#calGrid'), calMonth=$('#calMonth');
  const calPrev=$('#calPrev'), calNext=$('#calNext'), calUse=$('#calUse'), calClear=$('#calClear');
  const pickedInput=$('#pickedDate');

  const today=new Date(); today.setHours(0,0,0,0);
  let view=new Date(today.getFullYear(),today.getMonth(),1);
  let picked=null;

  function render(){
    calMonth.textContent=fmtMonth(view);
    calGrid.innerHTML='';
    const lead=new Date(view.getFullYear(),view.getMonth(),1).getDay();
    for(let i=0;i<lead;i++) calGrid.appendChild(document.createElement('div'));

    const last=new Date(view.getFullYear(),view.getMonth()+1,0).getDate();
    for(let d=1; d<=last; d++){
      const cd=new Date(view.getFullYear(),view.getMonth(),d); cd.setHours(0,0,0,0);
      const weekend=isWeekend(cd), past=cd<today, disabled=weekend||past;
      const btn=document.createElement('button');
      btn.type='button'; btn.className='cal-day'; btn.textContent=d; btn.setAttribute('role','gridcell');
      btn.setAttribute('aria-label',cd.toDateString());

      if(disabled){
        btn.disabled=true; btn.classList.add('cal-day--disabled');
        btn.title = weekend ? 'Closed (Sat–Sun)' : 'Past date';
      }else{
        const open = ()=>{ picked=new Date(cd); highlight(); updateUse(); openForDate(cd); };
        btn.addEventListener('click',open);
        btn.addEventListener('keydown',(e)=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); open(); }});
      }

      if(picked && ymd(picked)===ymd(cd)) btn.classList.add('cal-day--selected');
      calGrid.appendChild(btn);
    }
    highlight(); updateUse();
  }

  function updateUse(){ calUse.disabled=!picked; }
  function highlight(){
    $$('.cal-day',calGrid).forEach(b=>b.classList.remove('cal-day--selected'));
    if(!picked) return;
    if(picked.getFullYear()===view.getFullYear() && picked.getMonth()===view.getMonth()){
      const d=picked.getDate();
      $$('.cal-day',calGrid).forEach(b=>{ if(b.textContent==String(d) && !b.disabled) b.classList.add('cal-day--selected'); });
    }
  }

  calPrev.addEventListener('click',()=>{ view.setMonth(view.getMonth()-1); render(); });
  calNext.addEventListener('click',()=>{ view.setMonth(view.getMonth()+1); render(); });
  calUse.addEventListener('click',()=>{ if(!picked) return; pickedInput.value=ymd(picked); toastInfo('Date selected'); });
  calClear.addEventListener('click',()=>{ picked=null; pickedInput.value=''; highlight(); updateUse(); toastInfo('Cleared'); });

  // Open modal in DATE mode from outside
  window.openForDate = async function(dateObj){
    const dateStr = ymd(dateObj);
    const wd = (dateObj.getDay()+6)%7 + 1; // ISO weekday 1..7
    if (wd >= 6) return; // ignore weekends
    await window.HourTiles.open('date', { date: dateStr, weekday: wd });
  };

  document.addEventListener('keydown',(e)=>{
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Escape'].includes(e.key)) return;
    if(e.key==='Escape'){ picked=null; pickedInput.value=''; highlight(); updateUse(); return; }
    e.preventDefault();
    if(!picked) picked=new Date(today);
    const delta = e.key==='ArrowLeft'?-1 : e.key==='ArrowRight'?1 : e.key==='ArrowUp'?-7 : 7;
    picked.setDate(picked.getDate()+delta);
    while(isWeekend(picked) || picked<today){ picked.setDate(picked.getDate()+(delta>0?1:-1)); }
    view=new Date(picked.getFullYear(),picked.getMonth(),1);
    render();
  });

  render();
})();

/* ============================
   HOUR MODAL (weekday + date)
   ============================ */
(function(){
  const modal=$('#hourModal'), backdrop=$('#hourBackdrop'), dialog=$('#hourDialog');
  const btnClose=$('#hourCloseBtn'), tilesWrap=$('#hourTiles'), btnClear=$('#clearTiles'), btnSave=$('#saveTiles');
  const titleEl=$('#hourTitle'), descEl=$('#hourDesc');

  // Safety (if ever inside a form)
  btnClear?.setAttribute('type','button');
  btnSave?.setAttribute('type','button');
  btnClose?.setAttribute('type','button');

  const HOURS=[
    ['09:00','10:00'], ['10:00','11:00'], ['11:00','12:00'],
    ['12:00','13:00','locked'],
    ['13:00','14:00'], ['14:00','15:00'], ['15:00','16:00']
  ];

  let mode='weekday', activeWeekday=null, activeDate=null;
  let blocked=new Set();
  const key=(a,b)=>`${a}-${b}`;
  const to12=t=>{ const [H,M]=t.split(':').map(Number); const ap=H>=12?'PM':'AM'; const h=((H+11)%12)+1; return `${h}:${String(M).padStart(2,'0')} ${ap}`; };
  const norm=(t)=> (t||'').slice(0,5); // "09:00:00" -> "09:00"

  function setFromArray(arr){
    blocked.clear();
    arr.forEach(([s,e]) => blocked.add(key(norm(s), norm(e))));
  }
  function preloadWeekday(wd){
    const arr=(window.RECUR_BLOCKS && window.RECUR_BLOCKS[String(wd)])||[];
    setFromArray(arr);
  }
  async function preloadDate(dateStr){
    blocked.clear();
    try{
      const url = `${DATE_BLOCKS_GET}?date=${encodeURIComponent(dateStr)}`;
      const resp = await fetch(url, { credentials:'same-origin', headers:{'Accept':'application/json'} });
      if(!resp.ok) return;
      const data = await resp.json();
      const arr = (data.blocks||[]).map(b=>[norm(b.start), norm(b.end)]);
      setFromArray(arr);
    }catch(_){}
  }

  function buildTiles(){
    tilesWrap.innerHTML='';
    HOURS.forEach(([s,e,lock])=>{
      const b=document.createElement('button');
      b.type='button'; b.className='tile';
      b.textContent=`${to12(s)}–${to12(e)}`;
      b.dataset.start=s; b.dataset.end=e;

      const isOff = blocked.has(key(s,e));
      b.setAttribute('aria-pressed', isOff ? 'false' : 'true');
      if(isOff) b.classList.add('tile-off','tile-off--danger');

      if(lock){
        b.classList.add('tile-locked'); b.setAttribute('aria-disabled','true'); b.title='Lunch break (locked)';
      }else{
        b.addEventListener('click',()=>toggle(b));
        b.addEventListener('keydown',(ev)=>{ if(ev.key===' '||ev.key==='Spacebar'){ ev.preventDefault(); toggle(b); }});
      }
      tilesWrap.appendChild(b);
    });
  }

  function toggle(b){
    const k=key(b.dataset.start,b.dataset.end);
    const isOff = blocked.has(k);
    if(isOff){
      blocked.delete(k);
      b.classList.remove('tile-off','tile-off--danger');
      b.setAttribute('aria-pressed','true');
    }else{
      blocked.add(k);
      b.classList.add('tile-off','tile-off--danger');
      b.setAttribute('aria-pressed','false');
    }
  }

  function clearAll(){
    blocked.clear();
    $$('.tile',tilesWrap).forEach(b=>{
      if(!b.classList.contains('tile-locked')){
        b.classList.remove('tile-off','tile-off--danger');
        b.setAttribute('aria-pressed','true');
      }
    });
  }

  async function open(_mode, payload){
    mode = _mode;
    if(mode==='weekday'){
      activeWeekday = Number(payload.weekday);
      activeDate    = null;
      titleEl.textContent = 'Update weekday windows';
      descEl.innerHTML    = 'Toggle tiles to <span class="font-semibold">disable</span> hours for students. <span class="font-medium">12–1 PM lunch is locked.</span>';
      preloadWeekday(activeWeekday);
    }else{
      activeDate    = payload.date;
      activeWeekday = Number(payload.weekday);
      titleEl.textContent = `Update windows for ${activeDate}`;
      descEl.innerHTML    = 'Toggle tiles to <span class="font-semibold">disable</span> hours for students (this date only). <span class="font-medium">12–1 PM lunch is locked.</span>';
      await preloadDate(activeDate);
    }

    buildTiles();
    modal.classList.remove('hidden');
    requestAnimationFrame(()=>{ backdrop.classList.add('opacity-100'); dialog.classList.remove('opacity-0','scale-[0.98]','translate-y-4'); dialog.focus(); });
  }
  function close(){
    backdrop.classList.remove('opacity-100');
    dialog.classList.add('opacity-0','scale-[0.98]','translate-y-4');
    setTimeout(()=>modal.classList.add('hidden'),140);
  }

  btnClear.addEventListener('click',clearAll);
  btnClose.addEventListener('click',close);
  backdrop.addEventListener('click',close);
  modal.addEventListener('keydown',(e)=>{ if(e.key==='Escape') close(); });

  btnSave.addEventListener('click', async (ev)=>{
    ev.preventDefault();
    ev.stopPropagation();

    // Build blocked + opens from UI state
    const blocks = [], opens = [];
    HOURS.forEach(([s,e,lock])=>{
      if(lock) return;
      const k = key(s,e);
      if(blocked.has(k)) blocks.push({start:norm(s), end:norm(e)});
      else               opens.push({start:norm(s),  end:norm(e)});
    });

    btnSave.disabled = true;
    Swal.fire({ title: 'Saving changes…', didOpen: () => Swal.showLoading(), allowOutsideClick: false, showConfirmButton: false });

    try{
      let ok=false;

      if(mode==='weekday'){
        const resp = await fetch(WEEKDAY_BLOCKS_ENDPOINT, {
          method:'POST',
          credentials:'same-origin',
          headers:{
            'Content-Type':'application/json',
            'Accept':'application/json',
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': @json(csrf_token()),
          },
          body: JSON.stringify({ weekday: activeWeekday, blocks })
        });
        ok = resp.ok;
        if(ok){ window.RECUR_BLOCKS[String(activeWeekday)] = blocks.map(o=>[o.start,o.end]); }
      }else{
        const resp = await fetch(DATE_WINDOWS_SAVE, {
          method:'POST',
          credentials:'same-origin',
          headers:{
            'Content-Type':'application/json',
            'Accept':'application/json',
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': @json(csrf_token()),
          },
          body: JSON.stringify({ date: activeDate, opens, blocks })
        });

        if (!resp.ok) {
        let j = null;
        try {
          const ct = resp.headers.get('content-type') || '';
          j = ct.includes('application/json') ? await resp.json()
                                              : JSON.parse(await resp.text());
        } catch (_) {}

        // ✅ close the spinner FIRST
        Swal.close();

        if (j?.reason === 'has_appointments') {
          const esc = (s='') => String(s)
            .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
            .replaceAll('"','&quot;').replaceAll("'",'&#39;');

          const rows = (j.conflicts || [])
            .sort((a,b)=>String(a.time).localeCompare(String(b.time)))
            .map(c => {
              const time = esc(c.time12 || c.time || '');
              const name = esc(c.student_name || 'Student');
              const url  = c.appt_url || '#';
              return `
              <span class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-slate-700">
                <span class="px-1.5 py-0.5 text-xs font-semibold rounded-md bg-slate-100 ring-1 ring-slate-200">${time}</span>
                <a href="${url}" class="swal-link underline decoration-indigo-400 hover:decoration-2" title="Open appointment">${name}</a>
              </span>`;
            }).join('<br>');

          await Swal.fire({
            icon: 'error',
            title: "Can't disable – booked slot(s)",
            html: `
              <p class="text-slate-700 mb-2">There are appointment(s) on <b>${esc(activeDate)}</b> inside the time(s) you tried to disable.</p>
              <div class="text-left">${rows || '—'}</div>
              <p class="mt-3 text-[12px] text-slate-500">Tip: click a student name to open the appointment.</p>
            `,
            confirmButtonColor: '#e11d48',
          });
        } else if (resp.status === 419) {
          await Swal.fire({ icon:'error', title:'Session expired', text:'Please reload the page and try again.' });
        } else if (j?.message) {
          await Swal.fire({ icon:'error', title:'Save failed', text:j.message });
        } else {
          await Swal.fire({ icon:'error', title:'Save failed', text:`HTTP ${resp.status}` });
        }

        btnSave.disabled = false;
        return;
      }

        ok = true;
      }

      Swal.close();
      btnSave.disabled=false;
      if(!ok){ toastError('Save failed'); return; }
      close();
      toastSuccess('Update saved');
    }catch(_e){
      Swal.close();
      btnSave.disabled=false;
      toastError('Network error');
    }
  });

  // expose to calendar + weekday buttons
  window.HourTiles = { open };

  // Quick editor actions
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('button[data-action]'); if(!btn) return;
    const wd = Number(btn.dataset.weekday);

    if (btn.dataset.action === 'open-hour-modal') {
      window.HourTiles.open('weekday', { weekday: wd });
      return;
    }
    if (btn.dataset.action === 'disable-weekday') {
      const ok = await confirmDialog({
        title: 'Disable this weekday?',
        text: 'This will block 9–12 AM and 1–5 PM for students.',
        confirmText: 'Disable',
        danger: true
      });
      if (!ok) return;
      await fullBlock(wd);
    }
  });

  async function fullBlock(wd){
    const blocks=[{start:'09:00',end:'12:00'},{start:'13:00',end:'17:00'}];
    Swal.fire({ title: 'Applying disable…', didOpen: () => Swal.showLoading(), allowOutsideClick: false, showConfirmButton: false });
    try{
      const resp = await fetch(@json(route('counselor.availability.weekdayBlocks')), {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN': @json(csrf_token()),
        },
        body: JSON.stringify({ weekday: wd, blocks })
      });
      Swal.close();
      if (!resp.ok) { toastError('Failed to disable'); return; }
      window.RECUR_BLOCKS[String(wd)] = blocks.map(o=>[o.start,o.end]);
      toastSuccess('Weekday disabled');
    }catch(_){
      Swal.close(); toastError('Network error');
    }
  }
})();
</script>

{{-- ============================
     SERVER FLASH -> SweetAlert
     ============================ --}}
@if (session('swal'))
<script>
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a.swal-link');
    if (!a) return;
    e.preventDefault();
    const href = a.getAttribute('href');
    try { if (typeof Swal !== 'undefined') Swal.close(); } catch(_){}
    window.location.assign(href); // same tab
  });

  (function(){
    const data = @json(session('swal'));
    Swal.fire({
      icon: data.icon ?? 'info',
      title: data.title ?? '',
      text: data.text ?? '',
      confirmButtonColor: data.confirmButtonColor ?? '#4f46e5'
    });
  })();
</script>
@endif

{{-- ============================
     STYLES
     ============================ --}}
<style>
:root { --ui-h: 44px; }
.kbd{ padding:2px 6px; border:1px solid #e2e8f0; border-bottom-width:2px; border-radius:6px; font-size:11px; background:#fff;}
.tabular-nums{ font-variant-numeric: tabular-nums; }

/* Buttons */
.ui-primary,.ui-ghost,.ui-danger{ height:var(--ui-h); padding:0 14px; border-radius:12px; font-weight:700; transition:all .15s ease; }
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

/* PNG icon + spinner */
.table-btn { position: relative; }
.table-btn .btn-icon-img{
  width: 18px; height: 18px;
  display: inline-block;
  object-fit: contain;       /* keep aspect ratio */
  image-rendering: -webkit-optimize-contrast; /* crisper on some displays */
  opacity: .9;
}

.kbd-ghost { background: #f8fafc; border-color: #e5e7eb; }

.table-btn .btn-spinner{
  display: none;
  width: 16px; height: 16px;
  border-radius: 999px;
  border: 2px solid rgba(15,23,42,.2);
  border-top-color: rgba(79,70,229,1);
  animation: spin .7s linear infinite;
  margin-left: 6px;
}

.table-btn.is-loading{
  pointer-events: none;
  opacity: .9;
}

.table-btn.is-loading .btn-spinner{
  display: inline-block;
}
/* Floating pill button (Option C) */
.table-btn-floating{
  --btn-h: 42px;
  height: var(--btn-h);
  padding: 0 14px;
  border-radius: 999px;
  background: #ffffff;
  border: 1px solid rgba(148,163,184,.35); /* slate-400-ish */
  box-shadow: 0 1px 2px rgba(15,23,42,.06), 0 6px 16px rgba(99,102,241,.10);
  color: #0f172a;
  font-weight: 700;
  transition: border-color .15s ease, box-shadow .2s ease, transform .18s ease, background-color .15s ease, opacity .2s ease;
  will-change: transform, box-shadow, opacity;
}

.table-btn-floating:hover{
  transform: translateY(-1px);
  box-shadow: 0 3px 10px rgba(15,23,42,.08), 0 12px 26px rgba(99,102,241,.18);
}

/* Fade-in on hover (icon + label) */
.table-btn-floating .btn-icon-img,
.table-btn-floating .btn-label{
  opacity: .92;
  transition: transform .18s ease, opacity .18s ease;
}

.table-btn-floating:hover .btn-icon-img,
.table-btn-floating:hover .btn-label{
  opacity: 1;
  transform: translateY(-1px);
}

/* Icon size + spacing */
.table-btn-floating .btn-icon-img{
  width: 18px; height: 18px;
  display: inline-block;
  vertical-align: middle;
}

/* KBD chip style that matches ghost chips you use */
.kbd-ghost{
  margin-left: 6px;
  font-weight: 600;
  background: #fff;
  color: #475569;
  border-color: #e2e8f0;
}

/* Focus ring for a11y */
.table-btn-floating:focus-visible{
  outline: 2px solid #6366f1; /* indigo-500 */
  outline-offset: 2px;
}

/* Loading state (spinner shows, label dims) */
.table-btn-floating.is-loading{
  pointer-events: none;
  opacity: .85;
}

.table-btn-floating .btn-spinner{
  display: none;
  width: 14px; height: 14px; margin-left: 6px;
  border-radius: 50%;
  border: 2px solid #c7d2fe;          /* indigo-200 */
  border-top-color: #4f46e5;          /* indigo-600 */
  animation: spin .7s linear infinite;
}

.table-btn-floating.is-loading .btn-spinner{ display: inline-block; }

@keyframes spin { to { transform: rotate(360deg); } }

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce){
  .table-btn-floating, .table-btn-floating *{
    transition: none !important;
    animation: none !important;
  }
}
@keyframes spin{ to { transform: rotate(360deg); } }
</style>
@endsection
