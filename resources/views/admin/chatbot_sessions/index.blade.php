@extends('layouts.admin')
@section('title','Admin - Chatbot Sessions')
@section('page_title', 'Chatbot Sessions')

@php
  $q       = $q ?? request('q', '');
  $dateKey = $dateKey ?? request('date','all');
  $total   = method_exists($sessions,'total') ? $sessions->total() : $sessions->count();
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- Header --}}
  <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between animate-fadeup screen-only">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Chatbot Sessions</h2>
      <p class="text-sm text-slate-600">
        View conversation histories and emotional trends from chatbot sessions.
        <span class="ml-2 text-slate-400">•</span>
        <span class="ml-2 text-slate-500">{{ $total }} {{ Str::plural('session', $total) }}</span>
      </p>
    </div>

    <a href="{{ route('admin.chatbot-sessions.export.pdf', request()->only('date','q')) }}"
       class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
      </svg>
      Download PDF
    </a>
  </div>

  {{-- Filter Bar --}}
  <form method="GET" action="{{ route('admin.chatbot-sessions.index') }}" class="mb-6 screen-only">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end animate-fadeup">
      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Date Range</label>
        <select name="date"
          class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="all"    @selected($dateKey==='all')>All Dates</option>
          <option value="7d"     @selected($dateKey==='7d')>Last 7 days</option>
          <option value="30d"    @selected($dateKey==='30d')>Last 30 days</option>
          <option value="month"  @selected($dateKey==='month')>This month</option>
        </select>
      </div>

      <div class="md:col-span-3 min-w-0">
        <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
        <div class="relative">
          <input type="text" name="q" value="{{ $q }}" placeholder="Search student or session ID"
            class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <div class="md:col-span-6 md:col-start-7 flex items-center justify-end gap-2">
        <a href="{{ route('admin.chatbot-sessions.index') }}"
           class="h-11 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h7M4 10h16M4 16h10"/>
          </svg>
          Reset
        </a>

        <button class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm">
          Apply
        </button>
      </div>
    </div>
  </form>

  {{-- Table --}}
  <div id="cb-print-root" class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
    <div class="relative overflow-x-auto">
      <table class="min-w-full text-sm leading-6 table-auto">
        <colgroup>
          <col style="width:22%">
          <col style="width:26%">
          <col style="width:28%">
          <col style="width:16%">
          <col class="col-action" style="width:8%">
        </colgroup>

        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
          <tr class="align-middle">
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Session ID</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Student Name</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Initial Result</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Initial Date</th>
            <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap col-action">Action</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse ($sessions as $s)
            @php
              // Normalize risk fields
              $riskRaw = strtolower((string) ($s->risk_level ?? $s->risk ?? ''));
              $score   = (int) ($s->risk_score ?? 0);

              // High when label says high OR score >= 80
              $isHigh  = in_array($riskRaw, ['high','high-risk','high_risk'], true) || $score >= 80;

              // Maps provided by controller (session_id => bool)
              $handled = (bool) ($handledAfter[$s->id] ?? false);  // pending/confirmed AFTER this session
              $cleared = (bool) ($clearedAfter[$s->id] ?? false);  // completed AFTER this session

              // Show red only when actionable
              $showRed      = $isHigh && !$handled && !$cleared;
              $canQuickBook = $showRed;

              // Code for display
              $year = $s->created_at?->format('Y') ?? now()->format('Y');
              $code = 'LMC-' . $year . '-' . str_pad($s->id, 4, '0', STR_PAD_LEFT);
            @endphp

            <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition {{ $showRed ? 'bg-rose-50/40' : '' }}">
              {{-- SESSION ID --}}
              <td class="px-6 py-4 font-semibold">
                <div class="flex items-center gap-2">
                  @if($showRed)
                    <span class="inline-block size-2.5 rounded-full bg-rose-600 ring-4 ring-rose-100/70"
                          title="High risk since last booking" aria-label="High risk"></span>
                  @endif

                  @if($canQuickBook)
                    {{-- Quick book directly from the list --}}
                    <a href="#"
                       class="js-fast-book hover:underline focus:underline
                              text-slate-900 visited:text-slate-900
                              {{ $showRed ? 'text-rose-700 hover:text-rose-800 focus:text-rose-800' : '' }}"
                       data-slots="{{ route('admin.chatbot-sessions.slots', $s->id) }}"
                       data-book="{{ route('admin.chatbot-sessions.book',  $s->id) }}"
                       data-session="{{ $s->id }}">
                      {{ $code }}
                    </a>
                  @else
                    <a href="{{ route('admin.chatbot-sessions.show', $s) }}"
                       class="hover:underline focus:underline text-slate-900 visited:text-slate-900">
                      {{ $code }}
                    </a>
                  @endif
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ $s->user->name ?? '—' }}
              </td>

              <td class="px-6 py-4 text-slate-700">
                {{ $s->topic_summary ?? '—' }}
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ $s->created_at?->format('M d, Y') }}
              </td>

              <td class="px-6 py-4 text-right col-action">
                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                  <a href="{{ route('admin.chatbot-sessions.show', $s) }}"
                     class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-600 text-white hover:-translate-y-0.5 active:scale-[.98] transition"
                     title="View" aria-label="View session">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                      <circle cx="12" cy="12" r="3" stroke-width="2" />
                    </svg>
                  </a>
                </div>
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
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 not-print">
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
  /* SweetAlert2 modal sizing/look */
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
(() => {
  // Helpers
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

  // Fast-book only on actionable (red) rows
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
        const resp = await fetch(link.dataset.book,{ method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
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
