{{-- resources/views/Counselor_Interface/availability/table.blade.php --}}
@extends('layouts.counselor')
@section('title','Counselor - Availability Table')
@section('page_title','Availability Table')

@section('content')
<div class="mx-auto max-w-6xl px-4 pt-0 pb-10 space-y-6">

  {{-- ===== Top bar / breadcrumbs ===== --}}
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
    <div class="flex items-center gap-3">
      <a href="{{ route('counselor.availability.index') }}"
         class="inline-flex items-center gap-2 px-4 h-10 rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80 transition shadow-sm"
         aria-label="Back to calendar">
        ← <span class="font-medium">Back to calendar</span>
      </a>
      <div class="hidden sm:flex items-center gap-2 text-sm text-slate-500">
        <span>Manage recurring &amp; date-specific windows</span>
      </div>
    </div>

    {{-- Quick stats --}}
    @php
      $recCount   = $recurring->total();
      $datedCount = $dated->total();
    @endphp
    <div class="hidden sm:flex items-center gap-2 text-xs">
      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800/50">
        <strong class="tabular-nums">{{ $recCount }}</strong> recurring
      </span>
      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-violet-50 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 ring-1 ring-violet-200 dark:ring-violet-800/50">
        <strong class="tabular-nums">{{ $datedCount }}</strong> upcoming
      </span>
    </div>
  </div>

  {{-- ============================
       INSTRUCTIONS
     ============================ --}}
  <div class="bg-indigo-50/60 dark:bg-indigo-900/20 border border-indigo-100/80 dark:border-indigo-800/60 rounded-3xl p-5 shadow-sm backdrop-blur-md flex gap-4 text-[13px] text-indigo-900 dark:text-indigo-100 transition-all mb-2">
    <div class="flex-shrink-0 mt-0.5">
      <svg class="w-6 h-6 text-indigo-500 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01" stroke-width="3"></path>
      </svg>
    </div>
    <div class="leading-relaxed">
      <h4 class="font-bold text-indigo-950 dark:text-white text-sm mb-1.5">How to use the Availability Table</h4>
      <ul class="list-disc list-outside space-y-1 ml-4 marker:text-indigo-400 dark:marker:text-indigo-500 text-slate-700 dark:text-slate-300">
        <li><strong class="text-indigo-900 dark:text-indigo-200">Bulk Manage:</strong> Click the "Manage" button in the corner to toggle checkboxes, allowing you to delete multiple records instantly.</li>
        <li><strong class="text-indigo-900 dark:text-indigo-200">Recurring Windows:</strong> These form your foundation (e.g., standard weekly hours). Deleting one removes your availability for that entire weekday.</li>
        <li><strong class="text-indigo-900 dark:text-indigo-200">Date-Specific Windows:</strong> These are single-day exceptions (like holidays). Deleting an exception reverts that day back to your recurring schedule.</li>
      </ul>
    </div>
  </div>

  {{-- ===== Card container ===== --}}
  <div class="bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.2)] border border-white/60 dark:border-slate-700/50 p-6 lg:p-8 overflow-hidden transition-all duration-300">

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

    <div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3 flex-wrap">
      <h3 class="text-slate-900 dark:text-white font-bold text-lg tracking-tight">Recurring Weekday Windows</h3>

      <div class="flex items-center gap-3 flex-wrap">
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
      <div id="wrapRecurring" class="manage-off rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-700/50 bg-white/40 dark:bg-slate-800/30 backdrop-blur-sm">
        <form id="bulkDeleteRecurringForm" action="{{ route('counselor.availability.bulkDestroyRecurring') }}" method="POST">
          @csrf
          <table class="min-w-full text-sm hci-table">
            <thead class="bg-slate-50/80 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-semibold sticky-th border-b border-slate-200/50 dark:border-slate-700/50">
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
            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-700/50">
              @foreach($recurring as $r)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                  <td class="py-3 px-4 sel-col">
                    <input name="ids[]" value="{{ $r->id }}" type="checkbox" class="rowck-rec accent-indigo-500 w-4 h-4"
                           aria-label="Select every {{ $wdLong[$r->weekday] ?? '—' }}">
                  </td>
                  <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">
                    <span class="inline-flex items-center gap-3">
                      <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs font-black shadow-sm ring-1 ring-indigo-100 dark:ring-indigo-800/50">{{ $wdShort[$r->weekday] ?? '—' }}</span>
                      Every {{ $wdLong[$r->weekday] ?? '—' }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold {{ $pill($r->slot_type) }}">
                      {{ ucfirst($r->slot_type) }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-slate-600 dark:text-slate-400 font-medium">{{ $to12($r->start_time) }} <span class="text-slate-300 dark:text-slate-600">→</span> {{ $to12($r->end_time) }}</td>
                  <td class="py-3 px-4 text-right">
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
      <div id="wrapUpcoming" class="manage-off rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-700/50 bg-white/40 dark:bg-slate-800/30 backdrop-blur-sm">
        <form id="bulkDeleteForm" action="{{ route('counselor.availability.bulkDestroy') }}" method="POST">
          @csrf
          <table id="datedTable" class="min-w-full text-sm hci-table">
            <thead class="bg-slate-50/80 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-semibold sticky-th border-b border-slate-200/50 dark:border-slate-700/50">
              <tr>
                <th class="w-10 py-3 px-4 sel-col">
                  <input id="ckAll" type="checkbox" class="accent-indigo-500 w-4 h-4" aria-label="Select all rows">
                </th>
                <th class="text-left py-3 px-4">Date</th>
                <th class="text-left py-3 px-4">Weekday</th>
                <th class="text-left py-2.5 px-3">Type</th>
                <th class="text-left py-2.5 px-3">Time</th>
                <th class="text-right py-2.5 px-3">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-700/50">
              @foreach($dated as $d)
              <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors"
                  data-type="{{ $d->slot_type }}"
                  data-text="{{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }} {{ $wdShort[$d->weekday] ?? '' }} {{ strtolower($d->slot_type) }}">
                <td class="py-3 px-4 sel-col">
                  <input name="ids[]" value="{{ $d->id }}" type="checkbox" class="rowck accent-indigo-500 w-4 h-4"
                         aria-label="Select row for {{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }}">
                </td>
                <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">
                  {{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }}
                </td>
                <td class="py-3 px-4 text-slate-500 dark:text-slate-400 font-medium">{{ $wdShort[$d->weekday] ?? '—' }}</td>
                <td class="py-3 px-4">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold {{ $pill($d->slot_type) }}">
                    {{ ucfirst($d->slot_type) }}
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-600 dark:text-slate-400 font-medium">{{ $to12($d->start_time) }} <span class="text-slate-300 dark:text-slate-600">→</span> {{ $to12($d->end_time) }}</td>
                <td class="py-3 px-4 text-right">
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
  border: 1px solid rgba(226,232,240,0.8);
  background: rgba(255,255,255,0.7);
  backdrop-filter: blur(8px);
  color: #334155;
  font-weight: 600;
  font-size: 13px;
  line-height: 1.2;
  min-height: 38px;
  padding: 0.35rem 0.75rem;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.dark .chip-select, .dark .chip-search{ background: rgba(30,41,59,0.5); color: #cbd5e1; border-color: rgba(255,255,255,0.05); }

/* Select tweaks */
.chip-select{
  color:#0f172a;
  font-weight:500;
  font-size:14px;
  appearance:none; -webkit-appearance:none; -moz-appearance:none;
  padding-left:.85rem;
  padding-right:2.25rem;
  display:inline-block;
}
.dark .chip-select{ color:#f8fafc; }

.chip-chevron{
  position:absolute; right:12px; top:50%; transform:translateY(-50%);
  width:14px; height:14px; color:#64748b;
  pointer-events:none;
}

.chip-search{
  font-weight:600;
  padding-left:2.25rem;
}

.chip-select:hover, .chip-search:hover{ border-color:#818cf8; background:#fff; }
.dark .chip-select:hover, .dark .chip-search:hover{ background:rgba(30,41,59,0.8); }
.chip-select:focus, .chip-search:focus{
  outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.20);
}
.chip-search::placeholder{ color:#94a3b8; font-weight:500; }

/* ---------- Header Pills (Show/Hide + Manage) ---------- */
.btn-soft,
.btn-outline-strong{
  display:inline-flex; align-items:center; gap:.45rem;
  padding:0 1rem; height:38px; border-radius:999px;
  font-weight:700; font-size:13px; line-height:1;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-soft{
  background: rgba(238,242,255,0.7); color: #4338ca;
  border: 1px solid rgba(199,210,254,0.6);
  box-shadow: 0 2px 4px rgba(99,102,241,0.05);
  backdrop-filter: blur(4px);
}
.dark .btn-soft{ background: rgba(55,48,163,0.3); color: #818cf8; border-color: rgba(79,70,229,0.4); }
.btn-soft:hover{ background: #e0e7ff; border-color: #a5b4fc; transform:translateY(-1px); }
.dark .btn-soft:hover{ background: rgba(55,48,163,0.5); }
.btn-soft:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
.btn-soft[data-active="true"]{
  background: #c7d2fe; border-color: #818cf8; color: #312e81;
  box-shadow: 0 4px 14px rgba(99,102,241,.2);
}
.dark .btn-soft[data-active="true"]{ background: #4f46e5; color: #fff; border-color: transparent; }

.btn-outline-strong{
  background: rgba(255,255,255,0.7); color: #0f172a; border: 1px solid rgba(203,213,225,0.8); box-shadow: 0 2px 4px rgba(2,6,23,.04); backdrop-filter: blur(4px);
}
.dark .btn-outline-strong{ background: rgba(30,41,59,0.5); color: #f8fafc; border-color: rgba(255,255,255,0.1); }
.btn-outline-strong:hover{
  background: #fff; border-color: #6366f1; color: #4f46e5; box-shadow: 0 6px 16px rgba(99,102,241,.15);
  transform: translateY(-1px);
}
.dark .btn-outline-strong:hover{ background: rgba(30,41,59,0.8); border-color: #818cf8; color: #818cf8; }
.btn-outline-strong:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; }
.btn-outline-strong:active{ transform:translateY(0); }
.btn-soft svg, .btn-outline-strong svg{ flex:0 0 auto; }

/* ---------- Action Buttons ---------- */
.btn-ghost-sm{ height:36px; padding:0 14px; border-radius:12px; border:1px solid rgba(226,232,240,0.8); background:rgba(255,255,255,0.7); color:#334155; font-weight:600; box-shadow:0 2px 4px rgba(0,0,0,.02); transition:all 0.2s ease;}
.dark .btn-ghost-sm{ background:rgba(30,41,59,0.5); color:#cbd5e1; border-color:rgba(255,255,255,0.05); }
.btn-ghost-sm:hover{ background:#fff; transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.05); }
.dark .btn-ghost-sm:hover{ background:rgba(30,41,59,0.8); color:#fff;}

.btn-danger-sm{ height:36px; padding:0 14px; border-radius:12px; background:linear-gradient(135deg,#f43f5e,#e11d48); color:#fff; border:1px solid rgba(255,255,255,0.1); font-weight:700; box-shadow:0 4px 12px rgba(225,29,72,.3); transition:all 0.2s ease;}
.btn-danger-sm:hover{ box-shadow:0 6px 16px rgba(225,29,72,.4); transform:translateY(-1px); filter:brightness(1.05); }

/* ---------- Tables ---------- */
.hci-table th, .hci-table td { vertical-align: middle; }
:root { --stick-top: 0px; }
.sticky-th th{
  position:sticky; top:var(--stick-top); z-index:1;
  background:rgba(248,250,252,.8);
  backdrop-filter:blur(8px);
}
.dark .sticky-th th{ background:rgba(15,23,42,.8); }

/* ---------- Manage mode: selection column & bulk bar ---------- */
.manage-off .sel-col{ display:none; }
.manage-on  .sel-col{ display:table-cell; }

.bulk-card { box-shadow: 0 10px 30px rgba(99,102,241,.18), 0 3px 10px rgba(2,6,23,.06); border:1px solid rgba(199,210,254,0.5); }
.dark .bulk-card { background: #1e293b; box-shadow: 0 10px 30px rgba(0,0,0,.4); border-color: rgba(255,255,255,0.1); }
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
