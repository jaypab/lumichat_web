{{-- resources/views/admin/course-analytics/show.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Course Summary')
@section('page_title', 'Course Summary')

@php
  $c        = is_array($course ?? null) ? (object)$course : ($course ?? (object)[]);
  $courseId = $courseId ?? data_get($c, 'id') ?? request()->route('course');

  $courseLabel = trim(($c->course ?? $c->course_code ?? 'Course'));
  $yearLabel   = trim(($c->year_level ?? '—'));
  $pageTitle   = ($title ?? "{$courseLabel} • {$yearLabel}");

  $rawItems = data_get($c, 'breakdown', []);
  if ($rawItems instanceof \Illuminate\Support\Collection) $rawItems = $rawItems->toArray();

  // breakdown is already [{label, count}, ...] from case notes (presenting_problem)
  $items = [];
  foreach ($rawItems as $row) {
    $label = (string) (data_get($row,'label') ?? data_get($row,'diagnosis') ?? data_get($row,'diagnosis_result') ?? '—');
    $count = (int)    (data_get($row,'count') ?? data_get($row,'cnt') ?? 0);
    if ($label !== '—' && $count > 0) $items[] = ['label'=>$label,'count'=>$count];
  }
  usort($items, fn($a,$b)=>$b['count']<=>$a['count']);
  $totalDx = array_sum(array_column($items,'count')); // actually total case notes / concerns

  $palette = [
    'Stress'              => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200','bar'=>'bg-amber-400/70'],
    'Depression'          => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200','bar'=>'bg-rose-400/70'],
    'Anxiety'             => ['bg'=>'bg-sky-50','text'=>'text-sky-700','ring'=>'ring-sky-200','bar'=>'bg-sky-400/70'],
    'Family Problems'     => ['bg'=>'bg-yellow-50','text'=>'text-yellow-800','ring'=>'ring-yellow-200','bar'=>'bg-yellow-400/70'],
    'Relationship Issues' => ['bg'=>'bg-orange-50','text'=>'text-orange-700','ring'=>'ring-orange-200','bar'=>'bg-orange-400/70'],
    'Low Self-Esteem'     => ['bg'=>'bg-fuchsia-50','text'=>'text-fuchsia-700','ring'=>'ring-fuchsia-200','bar'=>'bg-fuchsia-400/70'],
    'Sleep Problems'      => ['bg'=>'bg-indigo-50','text'=>'text-indigo-700','ring'=>'ring-indigo-200','bar'=>'bg-indigo-400/70'],
    'Time Management'     => ['bg'=>'bg-violet-50','text'=>'text-violet-700','ring'=>'ring-violet-200','bar'=>'bg-violet-400/70'],
    'Academic Pressure'   => ['bg'=>'bg-blue-50','text'=>'text-blue-700','ring'=>'ring-blue-200','bar'=>'bg-blue-400/70'],
    'Financial Stress'    => ['bg'=>'bg-teal-50','text'=>'text-teal-700','ring'=>'ring-teal-200','bar'=>'bg-teal-400/70'],
    'Bullying'            => ['bg'=>'bg-lime-50','text'=>'text-lime-700','ring'=>'ring-lime-200','bar'=>'bg-lime-400/70'],
    'Burnout'             => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200','bar'=>'bg-rose-400/70'],
    'Grief / Loss'        => ['bg'=>'bg-stone-50','text'=>'text-stone-700','ring'=>'ring-stone-200','bar'=>'bg-stone-400/70'],
    'Loneliness'          => ['bg'=>'bg-cyan-50','text'=>'text-cyan-700','ring'=>'ring-cyan-200','bar'=>'bg-cyan-400/70'],
    'Substance Abuse'     => ['bg'=>'bg-red-50','text'=>'text-red-700','ring'=>'ring-red-200','bar'=>'bg-red-400/70'],
  ];
  $fallback = ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200','bar'=>'bg-slate-400/70'];

  $pill = function(string $label) use ($palette,$fallback){
    $s = $palette[$label] ?? $fallback;
    return '<span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium '
        . $s['bg'].' '.$s['text'].' ring-1 '.$s['ring'].'">'.e($label).'</span>';
  };
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

  {{-- ========= Page Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-2 screen-only">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <a href="{{ route('admin.course-analytics.index') }}" 
           class="group flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50/30 transition-all duration-200 shadow-sm active:scale-95">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
          Course <span class="text-indigo-600">Breakdown</span>
        </h1>
      </div>
      <p class="text-slate-500 text-sm font-medium ml-13">{{ $courseLabel }} • {{ $yearLabel }}</p>
    </div>

    <div class="flex items-center gap-3 ml-13 sm:ml-0">
      <a href="{{ route('admin.course-analytics.show.export.pdf', ['course' => $courseId]) }}"
         target="_blank" rel="noopener"
         class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400 group-hover:text-amber-500 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        <span>Export PDF</span>
      </a>
    </div>
  </header>

  {{-- ===== PRINT SCOPE ===== --}}
  <div id="print-analytics-show" class="space-y-6">

    {{-- Print title (Hidden on screen) --}}
    <h1 class="hidden print:block text-xl font-bold uppercase tracking-widest text-slate-900 mb-6">
      Course Breakdown — {{ $courseLabel }} • {{ $yearLabel }}
    </h1>

    {{-- KPI Dashboard --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      {{-- Students KPI --}}
      <div class="group relative bg-white rounded-3xl border border-slate-200/60 p-5 shadow-xl shadow-slate-200/40 hover:shadow-indigo-500/10 transition-all duration-300 overflow-hidden">
        <div class="flex items-start justify-between relative z-10">
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Enrolled Students</p>
            <h3 class="text-3xl font-black text-slate-900 mt-1 countup" data-target="{{ (int)($c->student_count ?? 0) }}">0</h3>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 shadow-sm border border-indigo-100/50 group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          </div>
        </div>
      </div>

      {{-- Records KPI --}}
      <div class="group relative bg-white rounded-3xl border border-slate-200/60 p-5 shadow-xl shadow-slate-200/40 hover:shadow-emerald-500/10 transition-all duration-300 overflow-hidden">
        <div class="flex items-start justify-between relative z-10">
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Records</p>
            <h3 class="text-3xl font-black text-slate-900 mt-1 countup" data-target="{{ (int)$totalDx }}">0</h3>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 shadow-sm border border-emerald-100/50 group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </div>
        </div>
      </div>

      {{-- Course Info KPI --}}
      <div class="sm:col-span-2 group relative bg-indigo-600 rounded-3xl p-5 shadow-xl shadow-indigo-200 transition-all duration-300 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>
        <div class="flex items-start justify-between relative z-10 h-full">
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-white/60">Target Population</p>
            <h3 class="text-xl font-black text-white mt-1 leading-tight">{{ $courseLabel }}</h3>
            <p class="text-[11px] font-bold text-white/50 uppercase tracking-widest mt-1">{{ $yearLabel }} Level</p>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center text-white shadow-sm border border-white/20">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          </div>
        </div>
      </div>
    </div>

    {{-- Top Presenting Concerns Bar Chart --}}
    <div class="rounded-3xl bg-white border border-slate-200/60 shadow-xl shadow-slate-200/40 overflow-hidden group/chart">
      <div class="p-6 sm:p-8">
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center gap-3">
             <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600 shadow-sm border border-rose-100/50">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
             </div>
             <div>
               <h3 class="text-lg font-black tracking-tight text-slate-900 uppercase tracking-widest text-[13px]">Presenting Concerns Breakdown</h3>
               <p class="text-xs text-slate-400 font-bold uppercase tracking-tight mt-0.5">Top recurring issues identified in session notes</p>
             </div>
          </div>
        </div>

        @if(count($items))
          <div role="list" class="space-y-3">
            @foreach($items as $idx => $row)
              @php
                $label = $row['label'];
                $count = (int)$row['count'];
                $pct   = $totalDx > 0 ? round($count / $totalDx * 100) : 0;
                $sty   = $palette[$label] ?? $fallback;
              @endphp
              <div role="listitem" class="flex items-center gap-4 fade-in" style="--delay:{{ 0.05 + ($idx * 0.03) }}s">
                <div class="w-44 shrink-0">
                  <div class="flex items-center gap-2">
                    {!! $pill($label) !!}
                    <span class="text-[11px] text-slate-500">{{ $pct }}%</span>
                  </div>
                </div>
                <div class="grow">
                  <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-2 rounded-full bar {{ $sty['bar'] }}"
                         data-width="{{ $pct }}"></div>
                  </div>
                </div>
                <div class="w-10 text-right text-slate-700 font-medium">{{ $count }}</div>
              </div>
            @endforeach
          </div>
        @else
          <div class="py-12 text-center">
            <div class="mx-auto w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
              <img src="{{ asset('images/icons/nodata.png') }}" class="w-6 h-6 opacity-60" alt="">
            </div>
            <p class="mt-3 text-sm font-medium text-slate-700">No breakdown available</p>
            <p class="text-xs text-slate-500">
              This course has no compiled case-note data yet.
            </p>
          </div>
        @endif
      </div>
    </div>

  </div>
  {{-- /PRINT SCOPE --}}
</div>

{{-- Animations (vanilla, reduced-motion aware) --}}
<script class="screen-only">
(function(){
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Count-up KPI
  if(!prefersReduced){
    const ease = t => 1- Math.pow(1-t, 3);
    document.querySelectorAll('.countup').forEach(el=>{
      const target = +el.dataset.target || 0;
      let start=0, startTs=null;
      const dur = Math.min(1200, 300 + target*20);
      const step = ts=>{
        if(startTs===null) startTs=ts;
        const p = Math.min(1, (ts-startTs)/dur);
        const val = Math.round(ease(p)*target);
        el.textContent = val.toLocaleString();
        if(p<1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  } else {
    document.querySelectorAll('.countup').forEach(el=>{
      el.textContent = (+el.dataset.target || 0).toLocaleString();
    });
  }

  // Animate bars when visible
  const growBars = entries=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.style.setProperty('--w', entry.target.dataset.width + '%');
        observer.unobserve(entry.target);
      }
    });
  };
  const observer = new IntersectionObserver(growBars, {threshold: 0.2});
  document.querySelectorAll('.bar').forEach(b=>{
    if(prefersReduced){
      b.style.width = (b.dataset.width || 0) + '%';
    }else{
      b.style.setProperty('--w', '0%'); // start at 0
      observer.observe(b);
    }
  });
})();
</script>

{{-- Print + micro-animations CSS --}}
<style>
  .card{ @apply bg-white rounded-2xl shadow-sm border border-slate-200/70; }
  .kpi-label{ @apply text-[11px] uppercase tracking-wide text-slate-500; }
  .chip{ @apply inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium; }

  /* gradient accent line */
  .accent-bar{
    position:absolute; inset-inline:0; top:-1px; height:4px;
    background: linear-gradient(90deg, #6366f1, #a855f7, #e879f9);
    background-size: 200% 100%;
    animation: shimmer 8s linear infinite;
  }
  @keyframes shimmer{
    from{ background-position: 0% 0; }
    to  { background-position: 200% 0; }
  }

  /* Fade/slide */
  .fade-in{
    opacity:0; transform: translateY(6px);
    animation: fadeUp .6s ease forwards;
    animation-delay: var(--delay, 0s);
  }
  .stagger{ display:inline-block; opacity:0; transform: translateY(6px); animation: fadeUp .45s ease forwards; animation-delay: calc(var(--i,0) * 60ms); }
  @keyframes fadeUp{
    to{ opacity:1; transform: translateY(0); }
  }

  /* Bars */
  .bar{
    width: var(--w, 0%);
    transition: width .9s cubic-bezier(.22,1,.36,1);
  }

  /* Respect reduced motion */
  @media (prefers-reduced-motion: reduce){
    .accent-bar{ animation: none !important; }
    .fade-in,.stagger{ opacity:1 !important; transform:none !important; animation:none !important; }
    .bar{ transition: none !important; }
  }

  /* Print-only isolation */
  @media print{
    body *{ visibility:hidden !important; }
    #print-analytics-show, #print-analytics-show *{ visibility:visible !important; }
    #print-analytics-show{ position:fixed; inset:0; margin:12mm !important; background:#fff; }
    #print-analytics-show .shadow-sm{ box-shadow:none !important; }
    #print-analytics-show .border{ border:0 !important; }
    .screen-only{ display:none !important; }
    @page{ size:A4; margin:12mm 14mm; }
  }
</style>
@endsection
