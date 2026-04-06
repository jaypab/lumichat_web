@extends('layouts.admin')
@section('title','Admin - Chatbot Sessions')
@section('page_title', 'Chatbot Sessions')

@php
  $q       = $q ?? request('q', '');
  $dateKey = $dateKey ?? request('date','all');
  $sort    = $sort ?? request('sort','newest');
  $only    = $only ?? request('only','all');
  $total   = method_exists($sessions,'total') ? $sessions->total() : $sessions->count();

  // keep existing query params while swapping one key
  function keepq(array $overrides = []) {
      $params = request()->query();
      foreach ($overrides as $k => $v) { $params[$k] = $v; }
      return $params;
  }
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Chatbot Sessions</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20">
          {{ number_format($total) }} Records
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">View conversation histories and emotional trends from chatbot sessions.</p>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ route('admin.chatbot-sessions.export.pdf', request()->only('date','q','sort','only')) }}"
         target="_blank" rel="noopener"
         class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 text-white px-4 py-2.5 text-sm font-bold shadow-sm shadow-indigo-500/20 hover:bg-indigo-700 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Download CSV/PDF
      </a>
      
      {{-- NEW: High Risk Toggle --}}
      <a href="{{ route('admin.chatbot-sessions.index', keepq(['only' => ($only === 'high' ? 'all' : 'high'), 'page' => ''])) }}"
         class="inline-flex items-center justify-center gap-2 rounded-xl transition-all duration-200 px-4 py-2.5 text-sm font-bold shadow-sm whitespace-nowrap {{ $only === 'high' ? 'bg-rose-100 text-rose-700 ring-1 ring-rose-200 active:scale-[.98]' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-rose-50 hover:text-rose-600 active:scale-[.98]' }}">
        <img src="{{ asset('images/icons/alert.png') }}" class="w-4 h-4 {{ $only === 'high' ? '' : 'grayscale opacity-60' }}" alt="">
        {{ $only === 'high' ? 'Showing High Risk' : 'Filter High Risk' }}
      </a>
    </div>
  </header>

  {{-- ========= Filters (Modern Inline Bar) ========= --}}
  <form method="GET" action="{{ route('admin.chatbot-sessions.index') }}" class="screen-only" id="cbFilterForm">
    <div class="bg-white rounded-2xl border border-slate-200/60 p-2 shadow-sm flex flex-wrap items-center gap-2">
      <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-2">
        {{-- Date Range --}}
        <div class="md:col-span-3 relative group">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <select name="date" id="dateSelect"
                  class="w-full h-12 bg-white border-none rounded-xl pl-10 pr-4 text-sm font-bold text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all appearance-none cursor-pointer">
            <option value="all" @selected($dateKey==='all')>All Dates</option>
            <option value="7d" @selected($dateKey==='7d')>Last 7 days</option>
            <option value="30d" @selected($dateKey==='30d')>Last 30 days</option>
            <option value="month" @selected($dateKey==='month')>This month</option>
          </select>
        </div>

        {{-- Search --}}
        <div class="md:col-span-6 relative group">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="2.5"/><path d="M21 21l-4.3-4.3" stroke-width="2.5" stroke-linecap="round"/></svg>
          </div>
          <input id="cb-q" type="text" name="q" value="{{ $q }}" placeholder="Search student or session ID..."
                 class="w-full h-12 bg-white border-none rounded-xl pl-10 pr-4 text-sm font-bold text-slate-700 placeholder:text-slate-400 placeholder:font-medium ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>

        {{-- Sort --}}
        <div class="md:col-span-3 relative group">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
          </div>
          <select name="sort" id="sortSelect"
                  class="w-full h-12 bg-white border-none rounded-xl pl-10 pr-4 text-sm font-bold text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all appearance-none cursor-pointer">
            <option value="newest" @selected($sort==='newest')>Newest First</option>
            <option value="oldest" @selected($sort==='oldest')>Oldest First</option>
            <option value="risk" @selected($sort==='risk')>High Risk First</option>
            <option value="unresolved" @selected($sort==='unresolved')>Unresolved First</option>
            <option value="handled" @selected($sort==='handled')>Handled First</option>
            <option value="student_asc" @selected($sort==='student_asc')>Student A→Z</option>
            <option value="session_asc" @selected($sort==='session_asc')>Session ID ↑</option>
            <option value="session_desc" @selected($sort==='session_desc')>Session ID ↓</option>
          </select>
        </div>
      </div>

      {{-- Reset --}}
      <a href="{{ route('admin.chatbot-sessions.index') }}"
         class="h-12 inline-flex items-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 transition-all duration-200 group"
         title="Clear all filters">
        <svg class="h-4 w-4 text-slate-400 group-hover:rotate-180 transition-transform duration-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Reset Filter
      </a>
      
      <input type="hidden" name="only" value="{{ $only }}">
    </div>
  </form>

  {{-- ========= Table (High Density + Sticky) ========= --}}
  <div id="cb-print-root" class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 overflow-visible transition-all duration-300">
    <div class="relative overflow-visible">
      <table class="min-w-full text-sm leading-relaxed table-auto">
        <thead x-data="{ scrolled: false }" 
               x-init="const s = document.querySelector('.panel-scroll'); if(s){ s.addEventListener('scroll', () => scrolled = s.scrollTop > 10) }"
               class="text-slate-700 sticky top-0 z-20">
          <tr>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">Session ID</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Student Name</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Detected Emotions</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Initial Date</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse ($sessions as $s)
            @php
              $riskRaw = strtolower((string) ($s->risk_level ?? $s->risk ?? ''));
              $score   = (int) ($s->risk_score ?? 0);
              $isHigh  = in_array($riskRaw, ['high','high-risk','high_risk'], true) || $score >= 80;

              $riskLevelNorm = 'unknown';
              if ($isHigh) { $riskLevelNorm = 'high'; }
              elseif (in_array($riskRaw, ['moderate','medium'], true) || ($score >= 40 && $score < 80)) { $riskLevelNorm = 'moderate'; }
              elseif (in_array($riskRaw, ['low'], true) || ($score > 0 && $score < 40)) { $riskLevelNorm = 'low'; }

              $riskPillClass = match($riskLevelNorm) {
                'high'     => 'bg-rose-50 text-rose-700 border-rose-200/60',
                'moderate' => 'bg-amber-50 text-amber-800 border-amber-200/60',
                'low'      => 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
                default    => 'bg-slate-50 text-slate-600 border-slate-200/60'
              };

              $riskLabel = ucfirst($riskLevelNorm) . ' Risk';

              $handled = (bool) ($handledAfter[$s->id] ?? false);
              $cleared = (bool) ($clearedAfter[$s->id] ?? false);
              $showRed = $isHigh && !$handled && !$cleared;
              $canQuickBook = $showRed;

              $year = $s->created_at?->format('Y') ?? now()->format('Y');
              $code = 'LMC-' . $year . '-' . str_pad($s->id, 4, '0', STR_PAD_LEFT);

              $counts = is_array($s->emotions) ? $s->emotions : (json_decode($s->emotions ?? '[]', true) ?: []);
              $norm = []; foreach ($counts as $k=>$v) { if (is_string($k)) $norm[strtolower($k)] = max(0,(int)$v); }
              arsort($norm); $etotal = array_sum($norm); $top = array_slice($norm, 0, 3, true);
            @endphp

            <tr class="group hover:bg-indigo-50/30 transition-colors duration-200 align-middle {{ $showRed ? 'bg-rose-50/30' : '' }}">
              {{-- SESSION ID --}}
              <td class="px-6 py-5 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  @if($showRed)
                    <div class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-600 shadow-sm ring-2 ring-white"></span>
                    </div>
                  @endif

                  <div class="flex flex-col gap-1">
                    @if($canQuickBook)
                      <a href="#" class="js-fast-book font-black text-slate-900 group-hover:text-indigo-600 transition-colors"
                         data-slots="{{ route('admin.chatbot-sessions.slots', $s->id) }}"
                         data-book="{{ route('admin.chatbot-sessions.book',  $s->id) }}"
                         data-session="{{ $s->id }}">
                        {{ $code }}
                      </a>
                    @else
                      <a href="{{ route('admin.chatbot-sessions.show', $s) }}" class="font-black text-slate-900 group-hover:text-indigo-600 transition-colors">
                        {{ $code }}
                      </a>
                    @endif
                    <span class="inline-flex items-center w-fit px-2 py-0.5 rounded-lg border text-[10px] font-black uppercase tracking-wider {{ $riskPillClass }}">
                      {{ $riskLabel }}
                    </span>
                  </div>
                </div>
              </td>


              {{-- STUDENT --}}
              <td class="px-6 py-5 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center font-black text-slate-500 text-[10px] shadow-sm transform group-hover:scale-105 transition-transform">
                    @if($s->user)
                      {{ \Illuminate\Support\Str::of($s->user->name)->explode(' ')->map(fn($p)=>mb_substr($p,0,1))->take(2)->join('') }}
                    @else
                      AN
                    @endif
                  </div>
                  <span class="font-bold text-slate-700">{{ $s->user->name ?? 'Anonymous' }}</span>
                </div>
              </td>

              {{-- Detected Emotions --}}
              <td class="px-6 py-5">
                @if(empty($top))
                  <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-wider border border-slate-200/60">—</span>
                @else
                  <div class="flex flex-wrap gap-1.5">
                    @foreach($top as $name => $cnt)
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-indigo-50 text-indigo-700 border border-indigo-200/60 shadow-sm whitespace-nowrap">
                        {{ $name }}
                        @if($etotal > 0)
                          <span class="ml-1 text-[9px] text-indigo-400">({{ number_format($cnt / $etotal * 100, 0) }}%)</span>
                        @endif
                      </span>
                    @endforeach
                  </div>
                @endif
              </td>

              {{-- DATE --}}
              <td class="px-6 py-5 whitespace-nowrap text-slate-600 font-medium">
                {{ $s->created_at?->format('M d, Y') }}
              </td>

              {{-- ACTION --}}
              <td class="px-6 py-5 text-right">
                <a href="{{ route('admin.chatbot-sessions.show', $s) }}"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-2xl bg-white text-slate-600 border border-slate-200 shadow-sm hover:border-indigo-200 hover:text-indigo-600 hover:bg-indigo-50 transition-all active:scale-[.95] group/btn">
                  <svg class="w-5 h-5 group-hover/btn:scale-110 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><circle cx="12" cy="12" r="3" stroke-width="2.5"/>
                  </svg>
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-6 py-10 text-center text-slate-500">No sessions found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($sessions->hasPages())
      <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 rounded-b-3xl">
        {{ $sessions->withQueryString()->links() }}
      </div>
    @endif
  </div>
</div>

{{-- Print styles --}}
<style media="print">
  @page { margin: 12mm; }
  body * { visibility: hidden !important; }
  #cb-print-root, #cb-print-root * { visibility: visible !important; }
  #cb-print-root {
    position: fixed !important; inset: 0 !important; margin: 12mm !important;
    background:#fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  #cb-print-root .rounded-2xl, #cb-print-root .shadow-sm, #cb-print-root .border { border:0 !important; box-shadow:none !important; }
  #cb-print-root .overflow-hidden, #cb-print-root .overflow-x-auto { overflow: visible !important; }

  #cb-print-root th.col-action,
  #cb-print-root td.col-action,
  #cb-print-root col.col-action,
  #cb-print-root thead th:last-child,
  #cb-print-root tbody td:last-child { display:none !important; visibility:hidden !important; }

  #cb-print-root tr { page-break-inside: avoid !important; }
</style>
@endsection

@push('scripts')
<style>
  /* SweetAlert2 modal look */
  .swal-wide.swal2-popup{
    width:min(92vw,760px)!important;
    padding:0!important;
    border-radius:18px!important;
    box-shadow:0 30px 60px rgba(2,6,23,.25);
  }
  .swal-wide .swal2-title{ margin:18px 22px 0!important; font-size:22px!important; font-weight:800!important; color:#0f172a!important; }
  .swal-wide .swal2-html-container{ margin:0!important; padding:16px 22px 22px!important; text-align:left!important; }
  .swal-wide .swal2-actions{ margin:0!important; padding:16px 22px 22px!important; }

  .swal-field{ width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:.55rem .75rem; }
  .swal-field:focus{ outline:0; box-shadow:0 0 0 3px rgba(79,70,229,.25); border-color:#c7d2fe; }

  .time-grid{ display:grid; gap:.5rem; grid-template-columns:repeat(3,minmax(0,1fr)); }
  @media (min-width:640px){ .time-grid{ grid-template-columns:repeat(4,minmax(0,1fr)); } }

  .time-btn{
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    border:1px solid #e2e8f0; background:#fff; color:#0f172a;
    padding:.55rem .6rem; border-radius:12px; font-size:.9rem; line-height:1.1; font-weight:600;
    transition: transform .06s ease, border-color .12s ease, background .12s ease;
  }
  .time-btn:hover{ background:#EEF2FF; border-color:#C7D2FE; }
  .time-btn.is-active{ box-shadow:0 0 0 3px rgba(79,70,229,.35); border-color:#a5b4fc; }
  .time-btn:disabled{ opacity:.45; background:#f8fafc; cursor:not-allowed; }
  .time-cap{ margin-top:.15rem; font-size:.72rem; opacity:.75; font-weight:500; }

  .tiny-hint{ font-size:.78rem; color:#64748b; }
</style>

<script>
  // Smooth filters: Search on Enter, keep focus + value
  (function () {
    const form       = document.getElementById('cbFilterForm');
    if (!form) return;

    const qInput     = document.getElementById('cb-q');
    const dateSelect = document.getElementById('dateSelect');
    const sortSelect = document.getElementById('sortSelect');

    // Common submit helper (also reset pagination)
    const submitWithReset = () => {
      let pageInput = form.querySelector('input[name="page"]');
      if (!pageInput) {
        pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        form.appendChild(pageInput);
      }
      pageInput.value = ''; // back to page 1
      form.submit();
    };

    // --- Search: only submit when user presses Enter ---
    if (qInput) {
      // Keep focus + caret at the end after every reload
      setTimeout(() => {
        qInput.focus();
        const val = qInput.value;
        if (typeof qInput.setSelectionRange === 'function') {
          qInput.setSelectionRange(val.length, val.length);
        }
      }, 0);

      qInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();   // wag yung default submit
          submitWithReset();    // controlled submit
        }
      });
    }

    // --- Date + Sort: submit immediately on change ---
    [dateSelect, sortSelect].forEach((sel) => {
      if (!sel) return;
      sel.addEventListener('change', submitWithReset);
    });
  })();
</script>

<script>
(() => {
  // (unchanged) Fast-book JS...
  const DATE_RE=/^\d{4}-\d{2}-\d{2}$/; const TIME_RE=/^\d{2}:\d{2}$/;
  const pad=n=>String(n).padStart(2,'0');
  const isWeekday=ymd=>{const[y,m,d]=ymd.split('-').map(Number);const t=new Date(y,m-1,d).getDay();return t>=1&&t<=5;}
  const notPast=ymd=>{const[y,m,d]=ymd.split('-').map(Number);const dt=new Date(y,m-1,d,23,59,59,999);const now=new Date();return dt>=new Date(now.getFullYear(),now.getMonth(),now.getDate());}

  async function loadSlots(url,date){
    const u=new URL(url,window.location.origin); u.searchParams.set('date',date);
    const res=await fetch(u,{headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(!res.ok) throw new Error('Failed to load slots');
    return res.json();
  }

  function buildTimeButtons(container, items, pooledMap, selected=''){
    container.innerHTML='';
    const empty=document.getElementById('adm-empty');
    const times=Array.isArray(items)?items:[];
    if(!times.length){ empty?.classList.remove('hidden'); container.dataset.selected=''; return; }
    empty?.classList.add('hidden');

    times.forEach(s=>{
      const cap=Math.max(0, Number((pooledMap && pooledMap[s.value]) ?? s.pooled ?? 0));
      const b=document.createElement('button');
      b.type='button'; b.dataset.value=s.value;
      b.className='time-btn';
      b.innerHTML=`<span>${s.label}</span><span class="time-cap">(${cap} ${cap===1?'slot':'slots'})</span>`;

      if(s.disabled){ b.disabled=true; }
      else{
        b.addEventListener('click',()=>{
          [...container.querySelectorAll('.time-btn')].forEach(x=>x.classList.remove('is-active'));
          b.classList.add('is-active');
          container.dataset.selected=s.value;
        });
      }
      if(!s.disabled && s.value===selected) b.classList.add('is-active');
      container.appendChild(b);
    });

    if(selected && !times.some(t=>!t.disabled && t.value===selected)){
      container.dataset.selected='';
    }
  }

  document.querySelectorAll('.js-fast-book').forEach(link=>{
    link.addEventListener('click', async (e)=>{
      e.preventDefault();

      const slotsEndpoint=link.dataset.slots;
      const bookEndpoint =link.dataset.book;

      const today=new Date();
      const defaultDate=`${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;

      let first;
      try{ first=await loadSlots(slotsEndpoint, defaultDate); }
      catch(err){ return Swal.fire({icon:'error', title:'Unable to load slots', text:String(err)}); }

      let pollId=null;

      const { value: form } = await Swal.fire({
        title:'Book appointment',
        html: `
          <div>
            <label class="text-sm font-medium text-slate-700">1) Pick date *</label>
            <input id="adm-date" type="date" value="${defaultDate}" min="{{ now()->toDateString() }}"
                   class="swal-field mt-1"/>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-slate-700">2) Counselor *</label>
                <select id="adm-counselor" class="swal-field mt-1">
                  ${(first.counselors||[]).map(c=>`<option value="${String(c.id)}">${String(c.name).replace(/</g,'&lt;')}</option>`).join('')}
                </select>
                <p class="tiny-hint mt-1">Choose who will take the session.</p>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">
                  3) Time * <small id="adm-total" class="text-slate-500 font-normal"></small>
                </label>
                <div id="adm-times" class="time-grid mt-1" data-selected="" tabindex="0" aria-label="Available times"></div>
                <div id="adm-empty" class="text-xs text-slate-500 mt-1 hidden">No available times.</div>
                <p class="tiny-hint mt-1">Past times are disabled automatically.</p>
              </div>
            </div>
          </div>
        `,
        customClass:{ popup:'swal-wide' },
        showCancelButton:true,
        confirmButtonText:'Confirm Booking',
        focusConfirm:false,

        didOpen:()=> {
          const dateEl=document.getElementById('adm-date');
          const counEl=document.getElementById('adm-counselor');
          const timeWrap=document.getElementById('adm-times');

          let slotsMap=first.slots||{};
          let pooledMap=first.pooled||{};

          const updateTotal=()=>{
            const total=Object.values(pooledMap||{}).reduce((a,b)=>a+Number(b||0),0);
            const el=document.getElementById('adm-total');
            el.textContent = total ? `• ${total} total counselor-slots` : '';
          };

          const compose=(cid, keepSelected=true)=>{
            const prevSel=keepSelected?(timeWrap.dataset.selected||''):'';
            const items=(slotsMap?.[cid]||[]);
            buildTimeButtons(timeWrap, items, pooledMap, prevSel);
            updateTotal();
          };

          const refetch=async ()=>{
            const val=dateEl.value;
            if(!DATE_RE.test(val) || !isWeekday(val) || !notPast(val)){
              buildTimeButtons(timeWrap, [], {}, ''); updateTotal(); return;
            }
            Swal.showLoading();
            try{
              const data=await loadSlots(slotsEndpoint, val);
              slotsMap=data.slots||{}; pooledMap=data.pooled||{};
              const list=data.counselors||[]; const prev=counEl.value;
              counEl.innerHTML=list.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
              if(list.length){ const keep=list.some(c=>String(c.id)===String(prev)); counEl.value=keep?prev:String(list[0].id); }
              compose(counEl.value, true);
            } finally { try{ Swal.hideLoading(); }catch(_){ } }
          };

          compose(counEl.value);
          dateEl.addEventListener('change', refetch);
          counEl.addEventListener('change', ()=>compose(counEl.value, false));
          pollId=setInterval(refetch, 5000);
        },

        willClose:()=>{ if(pollId) clearInterval(pollId); },

        preConfirm:()=>{
          const date=document.getElementById('adm-date')?.value||'';
          const counselorId=document.getElementById('adm-counselor')?.value||'';
          const time=document.getElementById('adm-times')?.dataset.selected||'';

          if(!DATE_RE.test(date)) return Swal.showValidationMessage('Invalid date format'), false;
          if(!isWeekday(date))    return Swal.showValidationMessage('Weekends are closed (Mon–Fri only)'), false;
          if(!notPast(date))      return Swal.showValidationMessage('Pick a future date'), false;
          if(!TIME_RE.test(time)) return Swal.showValidationMessage('Please pick a time'), false;
          if(!counselorId)        return Swal.showValidationMessage('Please choose a counselor'), false;

          const buttons=Array.from(document.querySelectorAll('#adm-times .time-btn:not([disabled])')).map(b=>b.dataset.value);
          if(!buttons.includes(time)) return Swal.showValidationMessage('That slot just filled. Pick another.'), false;

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
        const resp = await fetch(bookEndpoint,{ method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const data = await resp.json().catch(()=>({}));
        if (!resp.ok) throw new Error(data?.message || 'Booking failed.');

        await Swal.fire({
          icon: 'success',
          title: 'Appointment booked!',
          html: `<div class="appt-compact">${data.html}</div>`,
          customClass: { popup: 'swal-success swal-compact' },
          width: Math.min(window.innerWidth - 32, 1200),
          showCloseButton: true,
          confirmButtonText: 'OK',
        });

        window.location.reload();
      } catch (err) {
        Swal.fire({ icon:'error', title:'Unable to book', text:String(err) });
      }
    });
  });
})();
</script>
@endpush
