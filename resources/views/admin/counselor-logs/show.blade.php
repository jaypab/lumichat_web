@extends('layouts.admin')

@php
  // Safe counselor name, works even if only "name" column exists
  $cName = $counselor->full_name ?? $counselor->name ?? 'Unknown Counselor';
@endphp

@section('title','Counselor Logs - '.$cName)
@section('page_title', 'Counselors Logs Summary')

@@section('content')
@php
  $label = \Carbon\Carbon::create($year,$month,1)->format('F Y');
  $totalAppts     = $students->count();
  $uniqueStudents = $students->pluck('student_id')->filter()->unique()->count();
  $withDx = $students->filter(fn($r)=>isset($r->presenting_problem) && trim((string)$r->presenting_problem) !== '')->count();
  $noDx = max($totalAppts - $withDx, 0);

  $palette = [
    'Stress' => 'amber', 'Depression' => 'rose', 'Anxiety' => 'sky', 'Relationship Issues' => 'orange',
    'Family Problems' => 'yellow', 'Academic Pressure' => 'blue', 'Financial Stress' => 'teal',
    'Low Self-Esteem' => 'fuchsia', 'Sleep Problems' => 'indigo', 'Time Management' => 'violet'
  ];
@endphp

<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">{{ $cName }}</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-violet-50 text-violet-700 text-[11px] font-bold ring-1 ring-inset ring-violet-500/20">
          {{ $label }}
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">Detailed performance breakdown and student load for this period.</p>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ route('admin.counselor-logs.index', request()->only('month','year','counselor_id')) }}"
         class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Dashboard
      </a>

      <a href="{{ route('admin.counselor-logs.show.export', ['counselor'=>$counselor->id, 'month'=>$month, 'year'=>$year]) }}"
         target="_blank" rel="noopener"
         class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 text-white px-4 py-2.5 text-sm font-bold shadow-sm shadow-indigo-500/20 hover:bg-indigo-700 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Download Report
      </a>
    </div>
  </header>

  {{-- ========= KPI Grid (Student Records Style) ========= --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 screen-only">
    <div class="bg-white p-5 rounded-3xl border border-slate-200/60 shadow-sm relative overflow-hidden group">
      <span class="absolute top-0 right-0 w-24 h-24 bg-indigo-50/50 rounded-full -mr-12 -mt-12 transition-transform group-hover:scale-110 duration-500"></span>
      <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Appointments</p>
      <p class="text-3xl font-black text-slate-900 mt-1">{{ $totalAppts }}</p>
    </div>

    <div class="bg-white p-5 rounded-3xl border border-slate-200/60 shadow-sm relative overflow-hidden group">
      <span class="absolute top-0 right-0 w-24 h-24 bg-violet-50/50 rounded-full -mr-12 -mt-12 transition-transform group-hover:scale-110 duration-500"></span>
      <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Unique Students</p>
      <p class="text-3xl font-black text-slate-900 mt-1">{{ $uniqueStudents }}</p>
    </div>

    <div class="bg-white p-5 rounded-3xl border border-slate-200/60 shadow-sm relative overflow-hidden group">
      <span class="absolute top-0 right-0 w-24 h-24 bg-emerald-50/50 rounded-full -mr-12 -mt-12 transition-transform group-hover:scale-110 duration-500"></span>
      <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600/60">With Presenting Problem</p>
      <p class="text-3xl font-black text-emerald-600 mt-1">{{ $withDx }}</p>
    </div>

    <div class="bg-white p-5 rounded-3xl border border-slate-200/60 shadow-sm relative overflow-hidden group">
      <span class="absolute top-0 right-0 w-24 h-24 bg-rose-50/50 rounded-full -mr-12 -mt-12 transition-transform group-hover:scale-110 duration-500"></span>
      <div class="flex items-start justify-between">
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-rose-600/60">No Presenting Problem</p>
          <p class="text-3xl font-black text-rose-600 mt-1">{{ $noDx }}</p>
        </div>
        <input id="toggle-nodx" type="checkbox" class="rounded-lg border-rose-200 text-rose-500 focus:ring-rose-500/20 transition-all cursor-pointer">
      </div>
    </div>
  </div>

  {{-- ===== PRINT SCOPE START ===== --}}
  <div id="print-counselor-show" class="space-y-6">

    {{-- Analysis Header (High Density) --}}
    @if(!empty($dxCounts))
      <div class="bg-white rounded-3xl border border-slate-200/60 shadow-sm overflow-hidden relative">
        <span class="absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>
        <div class="p-6">
          <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Presenting Problem Distribution</h3>
          <div role="list" class="flex flex-wrap gap-2">
            <button type="button" data-chip="__ALL__"
                    class="dx-chip inline-flex items-center h-9 px-4 rounded-xl text-[11px] font-black uppercase tracking-widest bg-slate-50 text-slate-700 border border-slate-200 shadow-sm ring-2 ring-transparent ring-offset-2 hover:bg-slate-100 transition-all active:scale-[.97] ring-indigo-500 select-none">All</button>
            @foreach($dxCounts as $dxLabel => $dxCnt)
               @php $c = $palette[(string)$dxLabel] ?? 'slate'; @endphp
               <button type="button" data-chip="{{ e($dxLabel) }}"
                       class="dx-chip inline-flex items-center h-9 px-4 rounded-xl text-[11px] font-black uppercase tracking-widest bg-{{$c}}-50 text-{{$c}}-700 border border-{{$c}}-200/60 shadow-sm ring-2 ring-transparent ring-offset-2 hover:bg-{{$c}}-100 transition-all active:scale-[.97] select-none">
                 {{ $dxLabel }} <span class="ml-2 px-1.5 py-0.5 rounded-lg bg-white/50 text-[10px]">{{ $dxCnt }}</span>
               </button>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Detail Table (Matching Students Table Design) --}}
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 overflow-hidden transition-all duration-300">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4 screen-only">
        <h3 class="text-sm font-bold text-slate-900">Breakdown of Sessions</h3>
        <div class="relative w-64 group">
            <input id="search-student" type="text" placeholder="Search student name..."
                   class="w-full h-10 pl-9 pr-3 rounded-xl border-none bg-slate-50 text-sm font-medium text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="11" cy="11" r="7" stroke-width="2.5"/><path d="M21 21l-4.3-4.3" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
      </div>

      <div class="relative overflow-visible">
        <table class="min-w-full text-sm leading-relaxed table-auto">
          <thead x-data="{ scrolled: false }" 
                 x-init="const s = document.querySelector('.panel-scroll'); if(s){ s.addEventListener('scroll', () => scrolled = s.scrollTop > 10) }"
                 class="text-slate-700 sticky top-0 z-20">
            <tr>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                  :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">Student</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Date of Session</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                  :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Presenting Problem</th>
            </tr>
          </thead>

          <tbody id="rows" class="divide-y divide-slate-100">
            @forelse($students as $row)
              @php
                $presenting = isset($row->presenting_problem) ? trim((string)$row->presenting_problem) : '';
                $noDxRow    = ($presenting === '');
                $dxVal      = $noDxRow ? '' : (string)$presenting;
                $scheduled  = $row->note_date ? \Carbon\Carbon::parse($row->note_date)->format('M d, Y') : '—';
                $c = $palette[$presenting] ?? 'slate';
              @endphp
              <tr class="group hover:bg-indigo-50/30 transition-colors duration-200 align-middle {{ $noDxRow ? 'nodx' : '' }}"
                  data-dx="{{ e($dxVal) }}">
                <td class="px-6 py-5 whitespace-nowrap">
                   <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center font-black text-slate-500 text-[10px] shadow-sm transform group-hover:scale-105 transition-transform">
                        {{ \Illuminate\Support\Str::of($row->student_name)->explode(' ')->map(fn($p)=>mb_substr($p,0,1))->take(2)->join('') }}
                      </div>
                      <span class="font-bold text-slate-900 group-hover:text-indigo-600 transition-colors student-cell">{{ $row->student_name ?? '—' }}</span>
                   </div>
                </td>
                <td class="px-6 py-5 whitespace-nowrap text-slate-600 font-medium">{{ $scheduled }}</td>
                <td class="px-6 py-5">
                   @if($presenting)
                      <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-{{$c}}-50 text-{{$c}}-700 border border-{{$c}}-200/60 shadow-sm">
                        {{ $presenting }}
                      </span>
                   @else
                      <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-wider border border-slate-200/60">—</span>
                   @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="px-6 py-20 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center shadow-inner">
                            <svg class="w-8 h-8 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="text-slate-400 font-black uppercase tracking-widest text-[11px]">No Sessions Found</span>
                    </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script class="screen-only">
  document.addEventListener('DOMContentLoaded', () => {
    const rows   = Array.from(document.querySelectorAll('#rows tr'));
    const chips  = Array.from(document.querySelectorAll('.dx-chip'));
    const nodx   = document.getElementById('toggle-nodx');
    const search = document.getElementById('search-student');

    let activeDx = '__ALL__';
    let onlyNoDx = false;
    let q        = '';

    function normalize(t){ return (t||'').toLowerCase().trim(); }

    function applyFilters(){
      const qn = normalize(q);
      rows.forEach(tr => {
        const isNoDx        = tr.classList.contains('nodx');
        const dx            = tr.getAttribute('data-dx') || '';
        const matchesDx     = (activeDx === '__ALL__') ? true : (dx === activeDx);
        const matchesNoDx   = onlyNoDx ? isNoDx : true;
        const nameCell      = tr.querySelector('.student-cell');
        const nameTxt       = normalize(nameCell ? nameCell.textContent : '');
        const matchesSearch = qn ? nameTxt.includes(qn) : true;

        tr.style.display = (matchesDx && matchesNoDx && matchesSearch) ? '' : 'none';
      });
    }

    chips.forEach(btn => {
      btn.addEventListener('click', () => {
        chips.forEach(c => c.classList.remove('ring-indigo-500'));
        btn.classList.add('ring-indigo-500');

        const val = btn.getAttribute('data-chip');
        activeDx  = val || '__ALL__';
        applyFilters();
      });
    });

    if (nodx) {
      nodx.addEventListener('change', () => {
        onlyNoDx = !!nodx.checked;
        applyFilters();
      });
    }

    if (search) {
      search.addEventListener('input', (e) => {
        q = e.target.value || '';
        applyFilters();
      });
    }
  });
</script>

<style media="print">
  body * { visibility: hidden !important; }
  #print-counselor-show, #print-counselor-show * { visibility: visible !important; }
  #print-counselor-show { position: fixed; inset: 0; background: #fff; padding: 2rem; }
  .screen-only { display: none !important; }
</style>
@endsection
/style>
@endsection
