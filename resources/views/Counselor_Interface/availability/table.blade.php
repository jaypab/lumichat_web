{{-- resources/views/Counselor_Interface/availability/table.blade.php --}}
@extends('layouts.counselor')
@section('title','Counselor - Availability Table')
@section('page_title','Availability Table')

@section('content')
<div class="mx-auto max-w-6xl px-4 pt-0 pb-10 space-y-6">

  {{-- ===== Top bar / breadcrumbs ===== --}}
  <div class="sticky top-[56px] z-10 -mx-4 px-4 py-3 bg-white/85 backdrop-blur supports-[backdrop-filter]:bg-white/70
              border-b border-slate-200/70 flex items-center justify-between rounded-t-2xl">
    <div class="flex items-center gap-3">
      <a href="{{ route('counselor.availability.index') }}"
         class="inline-flex items-center gap-2 px-3 h-9 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50"
         aria-label="Back to calendar">
        ← <span class="font-medium">Back to calendar</span>
      </a>
      <div class="hidden sm:flex items-center gap-2 text-sm text-slate-500">
        <span>Manage recurring weekday windows &amp; date-specific windows</span>
      </div>
    </div>

    {{-- Quick stats --}}
    @php
      $recCount   = $recurring->total();
      $datedCount = $dated->total();
    @endphp
    <div class="hidden sm:flex items-center gap-2 text-xs">
      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">
        <strong class="tabular-nums">{{ $recCount }}</strong> recurring
      </span>
      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-violet-50 text-violet-700 ring-1 ring-violet-200">
        <strong class="tabular-nums">{{ $datedCount }}</strong> upcoming
      </span>
    </div>
  </div>

  {{-- ===== Card container ===== --}}
  <div class="bg-white rounded-2xl shadow-md ring-1 ring-slate-200/70 p-5 lg:p-8">

    {{-- ========= Helpers / formatters ========= --}}
    @php
      $wdShort = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
      $wdLong  = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
      $to12 = function($t){ [$H,$M] = array_map('intval', explode(':', substr($t,0,5))); $ap=$H>=12?'PM':'AM'; $h=(($H+11)%12)+1; return sprintf('%d:%02d %s',$h,$M,$ap); };
      $pill = function($type){
        return $type==='blocked'
          ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200'
          : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
      };
    @endphp
    
    @php $show = $show ?? request('show','available'); @endphp

    <div class="mb-3 flex items-center justify-between gap-2 flex-wrap">
      <h3 class="text-slate-900 font-semibold tracking-tight">Recurring Weekday Windows</h3>

      <div class="flex items-center gap-2">
        <span class="hidden sm:inline text-[12px] text-slate-500">
          Tip: Recurring rows affect every matching weekday.
        </span>

        {{-- Toggle: Show/Hide blocked --}}
        @if($show === 'all')
          <a href="{{ route('counselor.availability.table', [
                'show' => 'available',
                'recurring_page' => $recurring->currentPage(),
                'dated_page' => $dated->currentPage(),
            ]) }}"
            class="btn-soft"
            data-active="true"
            aria-pressed="true"
            title="Hide blocked rows">
            {{-- eye-off icon --}}
            <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10 6.2A9.5 9.5 0 0 1 21 12c-1.1 2.3-3.7 4.8-9 4.8a10.3 10.3 0 0 1-3.3-.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <span>Hide blocked</span>
          </a>
        @else
          <a href="{{ route('counselor.availability.table', [
                'show' => 'all',
                'recurring_page' => $recurring->currentPage(),
                'dated_page' => $dated->currentPage(),
            ]) }}"
            class="btn-soft"
            data-active="false"
            aria-pressed="false"
            title="Show blocked rows">
            {{-- eye icon --}}
            <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
            <span>Show blocked</span>
          </a>
        @endif

        {{-- Manage button --}}
        <button id="btnManageRecurring" type="button" class="btn-outline-strong" title="Manage (bulk delete)">
          {{-- sliders icon --}}
          <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="none"><path d="M4 6h16M4 12h16M4 18h16M9 6v4M15 12v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span>Manage</span>
        </button>
      </div>
    </div>

    @if($recurring->isEmpty())
      <div class="rounded-xl ring-1 ring-slate-200/70 bg-slate-50 text-slate-600 p-4 text-sm">
        No recurring windows yet. Configure weekly defaults from the Calendar page.
      </div>
    @else
      <div id="wrapRecurring" class="manage-off rounded-xl overflow-hidden ring-1 ring-slate-200/70">
        <form id="bulkDeleteRecurringForm" action="{{ route('counselor.availability.bulkDestroyRecurring') }}" method="POST">
          @csrf
          <table class="min-w-full text-sm hci-table">
            <thead class="bg-slate-50/80 text-slate-600 sticky-th">
              <tr>
                <th class="w-10 py-2.5 px-3 sel-col">
                  <input id="ckAllRecurring" type="checkbox" class="accent-indigo-600" aria-label="Select all recurring">
                </th>
                <th class="text-left py-2.5 px-3">When</th>
                <th class="text-left py-2.5 px-3">Type</th>
                <th class="text-left py-2.5 px-3">Time</th>
                <th class="text-right py-2.5 px-3">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @foreach($recurring as $r)
                <tr class="hover:bg-slate-50/70 transition-colors">
                  <td class="py-2.5 px-3 sel-col">
                    <input name="ids[]" value="{{ $r->id }}" type="checkbox" class="rowck-rec accent-indigo-600"
                           aria-label="Select every {{ $wdLong[$r->weekday] ?? '—' }}">
                  </td>
                  <td class="py-2.5 px-3 font-medium text-slate-800">
                    <span class="inline-flex items-center gap-2">
                      <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-slate-900/5 text-slate-700 text-xs font-bold">{{ $wdShort[$r->weekday] ?? '—' }}</span>
                      Every {{ $wdLong[$r->weekday] ?? '—' }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold {{ $pill($r->slot_type) }}">
                      {{ ucfirst($r->slot_type) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-slate-700">{{ $to12($r->start_time) }} – {{ $to12($r->end_time) }}</td>
                  <td class="py-2.5 px-3 text-right">
                    <form id="del-rec-{{ $r->id }}" action="{{ route('counselor.availability.destroy', $r->id) }}" method="POST" class="inline">
                      @csrf @method('DELETE')
                      <button type="button"
                              class="btn-danger-sm ml-1 js-del-one"
                              data-form="del-rec-{{ $r->id }}"
                              data-label="Every {{ $wdLong[$r->weekday] ?? '—' }} • {{ $to12($r->start_time) }} – {{ $to12($r->end_time) }}">
                        Delete
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </form>
      </div>

      {{-- Recurring footer (OUTSIDE table) --}}
      <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
        <div class="text-[12px] text-slate-500">
          Showing
          <span class="tabular-nums">{{ $recurring->firstItem() }}</span>
          to
          <span class="tabular-nums">{{ $recurring->lastItem() }}</span>
          of
          <span class="tabular-nums">{{ $recurring->total() }}</span>
          results
        </div>
        <div class="pagination text-sm text-slate-600">
          {{-- keep default Tailwind pagination --}}
          {{ $recurring->appends(['dated_page' => $dated->currentPage()])->links() }}
        </div>
      </div>

      {{-- Floating bulk bar (shows only in Manage + when selected > 0) --}}
      <div id="bulkBarRecurring" class="bulkbar sticky bottom-4 inset-x-0 flex justify-center pointer-events-none">
        <div class="bulk-card hidden pointer-events-auto px-3 py-2 rounded-xl bg-white shadow-lg ring-1 ring-slate-200 flex items-center gap-3">
          <span class="text-sm text-slate-600"><span id="bulkCountRecurring" class="font-semibold tabular-nums">0</span> selected</span>
          <button id="bulkDeleteRecurringBtn" type="button" class="btn-danger-sm" disabled>Delete selected</button>
        </div>
      </div>
    @endif

    {{-- ========= Upcoming Date-Specific Windows ========= --}}
    <div class="mt-10 mb-2 flex items-center justify-between gap-2 flex-wrap">
      <h3 class="text-slate-900 font-semibold tracking-tight">Upcoming Date-Specific Windows</h3>

      <div class="flex items-center gap-2 w-full md:w-auto md:ml-auto">
        {{-- Filter (select with custom chevron) --}}
        <div class="relative">
          <label class="sr-only" for="filterType">Filter by type</label>
          <select id="filterType" class="chip-select">
            <option value="all">All types</option>
            <option value="available">Available only</option>
            <option value="blocked">Blocked only</option>
          </select>
          <svg class="chip-chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        {{-- Search (fixed, compact width + correct icon) --}}
        <div class="relative w-[220px] md:w-[280px] lg:w-[320px]">
          <label class="sr-only" for="searchRows">Search</label>
          <input id="searchRows" type="search" placeholder="Search date or weekday…" class="chip-search w-full">
          {{-- search icon (properly centered) --}}
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500"
              viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
            <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round"/>
          </svg>
        </div>

        {{-- Manage --}}
        <button id="btnManageUpcoming" type="button" class="btn-outline-strong" title="Manage (bulk delete)">
          <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="none">
            <path d="M4 6h16M4 12h16M4 18h16M9 6v4M15 12v4"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span>Manage</span>
        </button>
      </div>
    </div>

    @if($dated->isEmpty())
      <div class="rounded-xl ring-1 ring-slate-200/70 bg-slate-50 text-slate-600 p-4 text-sm">
        No upcoming dated windows.
      </div>
    @else
      <div id="wrapUpcoming" class="manage-off rounded-xl overflow-hidden ring-1 ring-slate-200/70">
        <form id="bulkDeleteForm" action="{{ route('counselor.availability.bulkDestroy') }}" method="POST">
          @csrf
          <table id="datedTable" class="min-w-full text-sm hci-table">
            <thead class="text-slate-600 sticky-th">
              <tr>
                <th class="w-10 py-2.5 px-3 sel-col">
                  <input id="ckAll" type="checkbox" class="accent-indigo-600" aria-label="Select all rows">
                </th>
                <th class="text-left py-2.5 px-3">Date</th>
                <th class="text-left py-2.5 px-3">Weekday</th>
                <th class="text-left py-2.5 px-3">Type</th>
                <th class="text-left py-2.5 px-3">Time</th>
                <th class="text-right py-2.5 px-3">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @foreach($dated as $d)
              <tr class="hover:bg-slate-50/70 transition-colors"
                  data-type="{{ $d->slot_type }}"
                  data-text="{{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }} {{ $wdShort[$d->weekday] ?? '' }} {{ strtolower($d->slot_type) }}">
                <td class="py-2.5 px-3 sel-col">
                  <input name="ids[]" value="{{ $d->id }}" type="checkbox" class="rowck accent-indigo-600"
                         aria-label="Select row for {{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }}">
                </td>
                <td class="py-2.5 px-3 font-medium text-slate-800">
                  {{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }}
                </td>
                <td class="py-2.5 px-3 text-slate-700">{{ $wdShort[$d->weekday] ?? '—' }}</td>
                <td class="py-2.5 px-3">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold {{ $pill($d->slot_type) }}">
                    {{ ucfirst($d->slot_type) }}
                  </span>
                </td>
                <td class="py-2.5 px-3 text-slate-700">{{ $to12($d->start_time) }} – {{ $to12($d->end_time) }}</td>
                <td class="py-2.5 px-3 text-right">
                  <form id="del-dated-{{ $d->id }}" action="{{ route('counselor.availability.destroy', $d->id) }}" method="POST" class="inline">
                    @csrf @method('DELETE')
                    <button type="button"
                            class="btn-danger-sm ml-1 js-del-one"
                            data-form="del-dated-{{ $d->id }}"
                            data-label="{{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }} • {{ $to12($d->start_time) }} – {{ $to12($d->end_time) }}">
                      Delete
                    </button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </form>
      </div>

      {{-- Dated footer (OUTSIDE table) --}}
      <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
        <div class="text-[12px] text-slate-500">
          Showing
          <span class="tabular-nums">{{ $dated->firstItem() }}</span>
          to
          <span class="tabular-nums">{{ $dated->lastItem() }}</span>
          of
          <span class="tabular-nums">{{ $dated->total() }}</span>
          results
        </div>
        <div class="pagination text-sm text-slate-600">
          {{ $dated->appends(['recurring_page' => $recurring->currentPage()])->links() }}
        </div>
      </div>

      {{-- Floating bulk bar (shows only in Manage + when selected > 0) --}}
      <div id="bulkBar" class="bulkbar sticky bottom-4 inset-x-0 flex justify-center pointer-events-none">
        <div class="bulk-card hidden pointer-events-auto px-3 py-2 rounded-xl bg-white shadow-lg ring-1 ring-slate-200 flex items-center gap-3">
          <span class="text-sm text-slate-600"><span id="bulkCount" class="font-semibold tabular-nums">0</span> selected</span>
          <button id="bulkDeleteBtn" type="button" class="btn-danger-sm" disabled>Delete selected</button>
        </div>
      </div>
    @endif

  </div>
</div>

{{-- ===== SweetAlert helpers ===== --}}
<script>
  
function fmtLongDate(iso) {
  const dt = new Date(String(iso));
  if (Number.isNaN(dt.getTime())) return String(iso);
  return new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric', year: 'numeric' }).format(dt);
}

document.addEventListener('click', (e)=>{
  const a = e.target.closest('a.swal-link');
  if(!a) return;
  e.preventDefault();
  const href = a.getAttribute('href');
  if (typeof Swal !== 'undefined') Swal.close();
  window.location.assign(href);
});

const SwalTheme={confirm:'#4f46e5',cancel:'#94a3b8',danger:'#e11d48'};
async function confirmDialog({title='Are you sure?',text='',confirmText='Yes',danger=false}){
  const res = await Swal.fire({icon:'question',title,text,showCancelButton:true,confirmButtonText:confirmText,cancelButtonText:'Cancel',focusCancel:true,confirmButtonColor: danger?SwalTheme.danger:SwalTheme.confirm,cancelButtonColor: SwalTheme.cancel,reverseButtons:true});
  return res.isConfirmed;
}
document.addEventListener('click', async (e)=>{
  const btn = e.target.closest('.js-del-one'); if(!btn) return;
  const ok = await confirmDialog({title:'Delete this window?', text:btn.dataset.label||'this window', confirmText:'Delete', danger:true});
  if(!ok) return; document.getElementById(btn.dataset.form)?.submit();
});
</script>

{{-- ===== UX scripts: sticky header, filters/search, Manage toggles & bulk bars ===== --}}
<script>
document.addEventListener('DOMContentLoaded',()=>{

  /* ---------- Search / Filter (Upcoming) ---------- */
  const filter = document.getElementById('filterType');
  const search = document.getElementById('searchRows');
  const rows   = () => Array.from(document.querySelectorAll('#datedTable tbody tr'));

  function applyFilter(){
    const type = filter?.value || 'all';
    const q = (search?.value || '').trim().toLowerCase();
    rows().forEach(tr=>{
      const matchesType = type==='all' || tr.dataset.type===type;
      const matchesText = !q || (tr.dataset.text || '').toLowerCase().includes(q);
      tr.style.display = (matchesType && matchesText) ? '' : 'none';
    });
  }
  filter?.addEventListener('change', applyFilter);
  search?.addEventListener('input', applyFilter);

  /* ---------- Manage toggles (shared) ---------- */
  function setupManage({wrapId, btnId, rowSelector, selectAllSelector, bulkBarId, bulkCountId, bulkBtnId, formId}){
    const wrap   = document.getElementById(wrapId);
    const btn    = document.getElementById(btnId);
    const bulkCt = document.getElementById(bulkCountId);
    const bulkBtn= document.getElementById(bulkBtnId);
    const bulkBar= document.getElementById(bulkBarId)?.querySelector('.bulk-card');
    const form   = document.getElementById(formId);
    if(!wrap || !btn) return;

    let manageOn=false;
    const checks     = () => Array.from(wrap.querySelectorAll(rowSelector));
    const selAll     = wrap.querySelector(selectAllSelector);

    function refresh(){
      const n = checks().filter(c=>c.checked && c.closest('tr')?.style.display!=='none').length;
      if (bulkCt) bulkCt.textContent = String(n);
      // Only show bar when Manage is ON + there are selections
      if (bulkBar) bulkBar.classList.toggle('hidden', !manageOn || n===0);
      if (bulkBtn) bulkBtn.disabled = (n===0);
    }

    function clearSelection(){
      checks().forEach(c=>c.checked=false);
      if (selAll) selAll.checked=false;
      refresh();
    }

    function setManage(on){
      manageOn = on;
      wrap.classList.toggle('manage-on',  on);
      wrap.classList.toggle('manage-off', !on);
      btn.textContent = on ? 'Cancel' : 'Manage';
      if(!on){
        clearSelection();
        if (bulkBar) bulkBar.classList.add('hidden'); // force hide on exit
      }
    }

    btn.addEventListener('click', ()=> setManage(!manageOn));
    btn.addEventListener('keydown', (e)=>{ if(e.key==='m' || e.key==='M'){ e.preventDefault(); setManage(!manageOn); }});

    selAll?.addEventListener('change', ()=>{
      checks().forEach(c => { if (c.closest('tr').style.display !== 'none') c.checked = selAll.checked; });
      refresh();
    });

    wrap.addEventListener('change', (e)=>{ if (e.target.matches(rowSelector)) refresh(); });

    // Bulk button submits after confirm
    bulkBtn?.addEventListener('click', async ()=>{
      const n = checks().filter(c=>c.checked).length;
      if(!n) return;
      const ok = await confirmDialog({title:'Delete selected window(s)?', text:'This cannot be undone.', confirmText:'Delete', danger:true});
      if(!ok) return;
      form?.submit();
    });
  }

  // Recurring manage
  setupManage({
    wrapId:'wrapRecurring',
    btnId:'btnManageRecurring',
    rowSelector:'.rowck-rec',
    selectAllSelector:'thead .sel-col input[type="checkbox"]',
    bulkBarId:'bulkBarRecurring',
    bulkCountId:'bulkCountRecurring',
    bulkBtnId:'bulkDeleteRecurringBtn',
    formId:'bulkDeleteRecurringForm'
  });

  // Upcoming manage
  setupManage({
    wrapId:'wrapUpcoming',
    btnId:'btnManageUpcoming',
    rowSelector:'.rowck',
    selectAllSelector:'thead .sel-col input[type="checkbox"]',
    bulkBarId:'bulkBar',
    bulkCountId:'bulkCount',
    bulkBtnId:'bulkDeleteBtn',
    formId:'bulkDeleteForm'
  });

  applyFilter();
});
</script>

{{-- ===== Local styles ===== --}}
<style>
/* ===========================
   Availability Table – Styles
   =========================== */

/* Numeric alignment utility */
.tabular-nums{ font-variant-numeric: tabular-nums; }

/* ---------- Pill Inputs (Select + Search) ---------- */
.chip-select,
.chip-search{
  border-radius: 999px;
  border: 1px solid #e5e7eb;          /* slate-200 */
  background: #fff;
  color: #334155;                      /* slate-700 */
  font-weight: 600;
  font-size: 13px;
  line-height: 1.2;                    /* prevent y/g/j clipping */
  height: auto;                        /* let text define height */
  min-height: 36px;                    /* comfy touch target */
  padding: 0.35rem 0.75rem;            /* real vertical padding */
  transition: box-shadow .18s ease, border-color .15s ease, transform .12s ease;
}

/* Select tweaks: darker text, remove native arrow, make room for custom chevron */
.chip-select{
  color:#0f172a;                       /* slate-900 for contrast */
  font-weight:500;
  font-size:14px;
  border:1px solid #e2e8f0;            /* slate-200 to slate-300 */
  appearance:none; -webkit-appearance:none; -moz-appearance:none;
  padding-left:.85rem;
  padding-right:2.25rem;               /* space for chevron */
  display:inline-block;
}

/* Chevron icon for select */
.chip-chevron{
  position:absolute; right:12px; top:50%; transform:translateY(-50%);
  width:14px; height:14px; color:#475569; /* slate-600 */
  pointer-events:none;
}

/* Search pill (fixed, compact widths by breakpoint) */
.chip-search{
  font-weight:600;
  padding-left:2.25rem;                /* room for magnifier icon */
}

/* Shared hover/focus */
.chip-select:hover, .chip-search:hover{ border-color:#cbd5e1; }    /* slate-300 */
.chip-select:focus, .chip-search:focus{
  outline:none; border-color:#a5b4fc; box-shadow:0 0 0 3px rgba(99,102,241,.20); /* indigo-400 ring */
}
.chip-search::placeholder{ color:#94a3b8; font-weight:500; }       /* slate-400 */

/* ---------- Header Pills (Show/Hide + Manage) ---------- */
.btn-soft,
.btn-outline-strong{
  display:inline-flex; align-items:center; gap:.45rem;
  padding:0 .85rem; height:36px; border-radius:999px;
  font-weight:700; font-size:13px; line-height:1;
  transition: box-shadow .18s ease, transform .12s ease, background-color .15s ease, border-color .15s ease, color .15s ease;
}
.btn-soft{                                /* toggle chip */
  background:#f5f7ff; color:#4338ca;      /* indigo-700 */
  border:1px solid #c7d2fe;               /* indigo-200 */
  box-shadow:0 1px 2px rgba(15,23,42,.05);
}
.btn-soft:hover{ background:#eef2ff; border-color:#a5b4fc; transform:translateY(-1px); }
.btn-soft:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; } /* indigo-500 */
.btn-soft[data-active="true"]{
  background:#e0e7ff; border-color:#6366f1; color:#3730a3;
  box-shadow:0 4px 14px rgba(99,102,241,.18);
}

.btn-outline-strong{
  background:#fff; color:#0f172a; border:1px solid #cbd5e1; box-shadow:0 1px 2px rgba(2,6,23,.04);
}
.btn-outline-strong:hover{
  background:#ffffff; border-color:#94a3b8; box-shadow:0 6px 18px rgba(2,6,23,.10);
  transform:translateY(-1px);
}
.btn-outline-strong:focus-visible{ outline:2px solid #334155; outline-offset:2px; }
.btn-outline-strong:active{ transform:translateY(0); }
.btn-soft svg, .btn-outline-strong svg{ flex:0 0 auto; }

/* ---------- Action Buttons ---------- */
.btn-ghost-sm{ height:34px; padding:0 12px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#334155; font-weight:600; }
.btn-ghost-sm:hover{ background:#f8fafc; }
.btn-danger-sm{ height:34px; padding:0 12px; border-radius:10px; background:linear-gradient(180deg,#f43f5e,#e11d48); color:#fff; border:1px solid #e11d48; font-weight:700; box-shadow:0 2px 10px rgba(225,29,72,.18); }
.btn-danger-sm:hover{ filter:brightness(1.05); }

/* ---------- Tables ---------- */
.hci-table th, .hci-table td { vertical-align: middle; }
:root { --stick-top: 0px; }              /* where sticky TH pins */
.sticky-th th{
  position:sticky; top:var(--stick-top); z-index:1;
  background:rgba(248,250,252,.96);      /* slate-50 */
  backdrop-filter:blur(2px);
  box-shadow:0 1px 0 rgba(226,232,240,.9);
}

/* Pills for "Available"/"Blocked" inside table (from PHP helper classes) */
/* already applied via $pill($type), included here just for reference
   .bg-emerald-50 .text-emerald-700 .ring-emerald-200 etc. */

/* ---------- Manage mode: selection column & bulk bar ---------- */
.manage-off .sel-col{ display:none; }
.manage-on  .sel-col{ display:table-cell; }

.bulk-card { box-shadow: 0 10px 25px rgba(2,6,23,.08), 0 3px 10px rgba(2,6,23,.06); }
#wrapRecurring.manage-off ~ #bulkBarRecurring .bulk-card{ display:none !important; }
#wrapUpcoming.manage-off  ~ #bulkBar          .bulk-card{ display:none !important; }

/* ---------- Pagination ---------- */
.pagination nav { display:inline-flex; gap:.25rem; }
.pagination nav > * { margin-left:.125rem; }
.pagination { margin-left:.25rem; }

/* ---------- Responsive tweaks ---------- */
@media (max-width: 1024px){
  /* keep the search pill from stretching too wide on md screens (applied on container) */
  .chip-search.w-fixed-md{ width:280px; }
}
@media (max-width: 768px){
  .chip-search.w-fixed-md{ width:240px; }
}
@media (max-width: 640px){
  /* Convert table rows to cards for mobile */
  .hci-table thead{ display:none; }
  .hci-table tbody tr{ display:block; padding:10px 12px; }
  .hci-table tbody tr + tr{ border-top:1px solid rgba(226,232,240,.6); }
  .hci-table tbody td{ display:flex; justify-content:space-between; gap:10px; padding:8px 6px; border:0; }
  .hci-table tbody td:first-child{ order:6; justify-content:flex-start; gap:8px; }
  .hci-table tbody td:last-child{ order:7; }
  .manage-off .hci-table tbody td.sel-col { display:none !important; }
  .manage-on  .hci-table tbody td.sel-col { display:flex !important; }
}
</style>
@endsection
