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

  $items = [];
  foreach ($rawItems as $row) {
    $label = (string) (data_get($row,'label') ?? data_get($row,'diagnosis') ?? data_get($row,'diagnosis_result') ?? '—');
    $count = (int)    (data_get($row,'count') ?? data_get($row,'cnt') ?? 0);
    if ($label !== '—' && $count > 0) $items[] = ['label'=>$label,'count'=>$count];
  }
  usort($items, fn($a,$b)=>$b['count']<=>$a['count']);
  $totalDx = array_sum(array_column($items,'count'));

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

  {{-- Header / actions --}}
  <div class="flex items-start justify-between gap-4 screen-only">
    <div>
      <h2 class="text-2xl font-semibold tracking-tight text-slate-800">Course Summary</h2>
      <p class="text-sm text-slate-500">{{ $courseLabel }} • {{ $yearLabel }}</p>
    </div>

    <div class="flex gap-2">
      {{-- Show: Single Course -> PDF --}}
<a href="{{ route('admin.course-analytics.show.export.pdf', ['course' => $courseId]) }}"
   target="_blank" rel="noopener"
   class="inline-flex items-center h-9 px-3 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700">
  <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
  </svg>
  Download PDF
</a>

      <a href="{{ route('admin.course-analytics.index') }}"
         class="inline-flex items-center h-9 px-3 rounded-lg text-sm font-medium bg-white border border-slate-200 shadow-sm hover:bg-slate-50">
        ← Back to list
      </a>
    </div>
  </div>

  {{-- ===== PRINT SCOPE ===== --}}
  <div id="print-analytics-show" class="space-y-6">

    {{-- Print title --}}
    <h1 class="hidden print:block text-xl font-semibold">Course Summary — {{ $courseLabel }} • {{ $yearLabel }}</h1>

    {{-- Summary card --}}
    <div class="relative bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <span class="pointer-events-none accent-bar"></span>

      <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="fade-in">
            <div class="text-[11px] uppercase tracking-wide text-slate-500">Course</div>
            <div class="font-semibold text-slate-900">{{ $courseLabel }}</div>
          </div>
          <div class="fade-in" style="--delay:0.05s">
            <div class="kpi-label">Year Level</div>
            <div class="font-medium text-slate-900">{{ $yearLabel }}</div>
          </div>
          <div class="fade-in" style="--delay:0.1s">
            <div class="kpi-label">No. of Students</div>
            <div class="font-medium text-slate-900">
              <span class="countup" data-target="{{ (int)($c->student_count ?? 0) }}">0</span>
            </div>
          </div>
          <div class="fade-in" style="--delay:0.15s">
            <div class="kpi-label">Total Diagnoses</div>
            <div class="font-medium text-slate-900">
              <span class="countup" data-target="{{ (int)$totalDx }}">0</span>
            </div>
          </div>
        </div>

        {{-- Top diagnoses chips --}}
        @if(count($items))
          <div class="mt-4">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 mb-2">Top diagnoses</div>
            <div class="flex flex-wrap gap-1.5">
              @foreach(array_slice($items,0,6) as $it)
                {!! $pill($it['label']) !!}
              @endforeach

              @if(count($items) > 6)
                @php
                  $rest       = array_slice($items, 6);
                  $restLabels = implode(', ', array_map(fn($r)=>$r['label'], $rest));
                @endphp
                <span
                  class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium bg-slate-50 text-slate-600 ring-1 ring-slate-200 cursor-default whitespace-nowrap"
                  title="{{ $restLabels }}"
                >
                  +{{ count($rest) }} more
                </span>
              @endif
            </div>
          </div>
        @endif
      </div>
    </div>

    {{-- Breakdown (summary) --}}
    <div class="relative bg-white rounded-2xl shadow-sm border border-slate-200/70">

      <div class="p-5">
        <h3 class="text-base font-semibold text-slate-800 mb-3">Diagnosis Breakdown (summary)</h3>

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
            <p class="text-xs text-slate-500">This course has no compiled diagnosis data yet.</p>
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
