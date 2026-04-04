{{-- resources/views/admin/course-analytics/index.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Course Summary')
@section('page_title','Course Summary')

@php
  use Illuminate\Support\Str;

  $yearKey       = request('year','all');             // all|1|2|3|4
  $courseOptions = $courseOptions ?? collect();
  $courseKey     = $courseKey     ?? request('course','all');

  $total         = is_object($courses) && method_exists($courses, 'total') ? $courses->total() : (is_countable($courses) ? count($courses) : 0);

  // --- Pill palette for “Common Presenting Concerns” (from Case Notes) ---
  $palette = [
    'Stress'              => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200'],
    'Depression'          => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200'],
    'Anxiety'             => ['bg'=>'bg-sky-50','text'=>'text-sky-700','ring'=>'ring-sky-200'],
    'Family Problems'     => ['bg'=>'bg-yellow-50','text'=>'text-yellow-800','ring'=>'ring-yellow-200'],
    'Relationship Issues' => ['bg'=>'bg-orange-50','text'=>'text-orange-700','ring'=>'ring-orange-200'],
    'Low Self-Esteem'     => ['bg'=>'bg-fuchsia-50','text'=>'text-fuchsia-700','ring'=>'ring-fuchsia-200'],
    'Sleep Problems'      => ['bg'=>'bg-indigo-50','text'=>'text-indigo-700','ring'=>'ring-indigo-200'],
    'Time Management'     => ['bg'=>'bg-violet-50','text'=>'text-violet-700','ring'=>'ring-violet-200'],
    'Academic Pressure'   => ['bg'=>'bg-blue-50','text'=>'text-blue-700','ring'=>'ring-blue-200'],
    'Financial Stress'    => ['bg'=>'bg-teal-50','text'=>'text-teal-700','ring'=>'ring-teal-200'],
    'Bullying'            => ['bg'=>'bg-lime-50','text'=>'text-lime-700','ring'=>'ring-lime-200'],
    'Burnout'             => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200'],
    'Grief / Loss'        => ['bg'=>'bg-stone-50','text'=>'text-stone-700','ring'=>'ring-stone-200'],
    'Loneliness'          => ['bg'=>'bg-cyan-50','text'=>'text-cyan-700','ring'=>'ring-cyan-200'],
    'Substance Abuse'     => ['bg'=>'bg-red-50','text'=>'text-red-700','ring'=>'ring-red-200'],
  ];
  $defaultPill = ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200'];

  // Render a concern chip
  $pill = function(string $label) use ($palette,$defaultPill){
    $s = $palette[$label] ?? $defaultPill;
    return '<span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium '
      .$s['bg'].' '.$s['text'].' ring-1 '.$s['ring'].'">'.e($label).'</span>';
  };

  // Normalize concerns array/string → array (stored in common_diagnoses column as pipe/comma list)
  $toConcernsArray = function($raw){
    if (is_array($raw)) return array_values(array_filter(array_map('trim',$raw)));
    $str = (string)$raw; if ($str==='') return [];
    if (Str::contains($str,'||')) return array_values(array_filter(array_map('trim',explode('||',$str))));
    if (Str::contains($str,',' )) return array_values(array_filter(array_map('trim',explode(',',$str))));
    return [$str];
  };

  // Year chips
  $yearChips = [
    'all' => 'All',
    '1'   => '1st year',
    '2'   => '2nd year',
    '3'   => '3rd year',
    '4'   => '4th year',
  ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Page Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Course Summary</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20 shadow-sm">
          {{ number_format($total) }} Records
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">Summary of case notes and presenting concerns across programs.</p>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ route('admin.course-analytics.export.pdf', request()->only('year','course')) }}"
         target="_blank" rel="noopener"
         class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400 group-hover:text-amber-500 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        <span>Export PDF</span>
      </a>
    </div>
  </header>

  {{-- ========= Modern Inline Filter Bar ========= --}}
  <form id="filterForm" method="GET" action="{{ route('admin.course-analytics.index') }}" class="screen-only group/form">
    <div class="flex flex-col lg:flex-row gap-4">
      {{-- Course Select --}}
      <div class="relative flex-1 group">
         <div class="relative">
           <select id="courseSelect" name="course"
                   class="h-12 w-full rounded-2xl border-none bg-white px-5 pr-12 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 group-hover:ring-slate-300 appearance-none cursor-pointer">
             <option value="all" {{ $courseKey==='all'?'selected':'' }}>All Courses</option>
             @foreach($courseOptions as $opt)
               @php
                 $code = is_array($opt) ? ($opt['code'] ?? $opt['value'] ?? $opt[0] ?? '') : ($opt->code ?? (string)$opt);
                 $name = is_array($opt) ? ($opt['name'] ?? $opt['label'] ?? $code) : ($opt->name ?? $code);
               @endphp
               <option value="{{ $code }}" {{ $courseKey===$code ? 'selected' : '' }}>
                 {{ $code }} — {{ $name }}
               </option>
             @endforeach
           </select>
           <svg class="absolute right-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400 group-hover:text-indigo-500 transition-colors duration-200 pointer-events-none"
                viewBox="0 0 24 24" fill="none" stroke="currentColor">
             <path d="M6 9l6 6 6-6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
           </svg>
         </div>
      </div>

      {{-- Year Presets --}}
      <div class="flex items-center gap-1.5 p-1 bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm shrink-0 overflow-x-auto custom-scrollbar">
        @foreach($yearChips as $key => $label)
          @php $active = $yearKey === $key; @endphp
          <button type="submit" name="year" value="{{ $key }}"
                  class="h-10 px-4 rounded-xl text-[11px] font-black uppercase tracking-wider whitespace-nowrap transition-all duration-200
                         {{ $active ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
            {{ $label }}
          </button>
        @endforeach
      </div>

      {{-- Reset --}}
      <div class="flex items-center gap-2 lg:ml-2">
        <a href="{{ route('admin.course-analytics.index') }}"
           class="h-12 px-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-white text-slate-600 border border-slate-200 text-xs font-black uppercase tracking-widest shadow-sm hover:bg-slate-50 active:scale-[.98] transition-all">
          <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Reset</span>
        </a>
      </div>
    </div>
  </form>

  {{-- ========= Modern Sticky Results Table ========= --}}
  <section id="print-analytics-index" class="relative group/table" x-data="{ scrolled: false }" x-init="$el.querySelector('.panel-scroll').addEventListener('scroll', e => { scrolled = e.target.scrollTop > 10 })">
    <div class="rounded-3xl bg-white border border-slate-200/60 shadow-xl shadow-slate-200/50 overflow-hidden transition-all duration-300 group-hover/table:shadow-slate-300/50">
      <div class="panel-scroll relative overflow-x-auto max-h-[calc(100vh-320px)] custom-scrollbar">
        <table class="w-full text-sm text-left border-separate border-spacing-0">
          <thead class="sticky top-0 z-20 transition-all duration-300" :class="scrolled ? 'bg-indigo-50/95 backdrop-blur-md shadow-sm' : 'bg-indigo-50'">
            <tr>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100 transition-all duration-300" :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">Course</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Year Level</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Students</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">Common Concerns</th>
              <th scope="col" class="px-6 py-4 text-[11px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100 text-right pr-6 transition-all duration-300" :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            @forelse($courses as $c)
              @php
                $id     = $c->id ?? null;
                $course = $c->course ?? ($c->course_code ?? '—');
                $year   = $c->year_level ?? '—';
                $count  = (int) ($c->student_count ?? 0);
                $concernsArr = $toConcernsArray($c->common_diagnoses ?? []);
                $concernsArr = array_values(array_unique(array_filter(array_map('trim',$concernsArr))));
                $chips       = array_slice($concernsArr, 0, 8);
                $moreN       = max(0, count($concernsArr) - count($chips));
                $moreTxt     = $moreN>0 ? implode(', ', array_slice($concernsArr, 8)) : '';
              @endphp
              <tr class="group/row hover:bg-slate-50/80 transition-all duration-150">
                <td class="px-6 py-5 whitespace-nowrap">
                   <div class="text-sm font-black text-slate-900 group-hover/row:text-indigo-600 transition-colors">{{ $course }}</div>
                </td>

                <td class="px-6 py-5 whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-[11px] font-bold ring-1 ring-inset ring-slate-200/60 group-hover/row:bg-white transition-colors uppercase tracking-tight">
                    {{ $year }}
                  </span>
                </td>

                <td class="px-6 py-5 whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-black text-slate-700">{{ $count }}</span>
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Students</span>
                  </div>
                </td>

                <td class="px-6 py-5">
                  <div class="flex flex-wrap gap-1.5">
                    @forelse($chips as $label)
                      {!! $pill($label) !!}
                    @empty
                      <span class="text-slate-300 italic text-xs">No concerns logged</span>
                    @endforelse

                    @if($moreN>0)
                      <span
                        class="inline-flex items-center h-6 px-2.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-slate-50 text-slate-400 ring-1 ring-slate-200"
                        title="{{ $moreTxt }}">
                        +{{ $moreN }}
                      </span>
                    @endif
                  </div>
                </td>

                <td class="px-6 py-5 text-right pr-6">
                  @if($id)
                    <a href="{{ route('admin.course-analytics.show',$id) }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 hover:text-indigo-600 hover:border-indigo-200 shadow-sm transition-all duration-200 active:scale-95 group/btn">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </a>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-6 py-20 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="w-16 h-16 rounded-3xl bg-slate-50 flex items-center justify-center text-slate-300 mb-4 border border-slate-100 shadow-inner">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-slate-800 font-black uppercase tracking-widest text-[11px]">No Course Summary Found</p>
                    <p class="text-slate-400 text-xs mt-1 font-medium">Try adjusting your filters or year level selection.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @php
        $hasPages = is_object($courses) && method_exists($courses, 'hasPages') && $courses->hasPages();
      @endphp
      @if($hasPages)
        <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">
            Showing <span class="text-slate-700">{{ $courses->firstItem() }}</span>–<span class="text-slate-700">{{ $courses->lastItem() }}</span> 
            of <span class="text-slate-700">{{ $courses->total() }}</span> Entries
          </p>
          <div class="modern-pagination">
            {{ $courses->withQueryString()->links() }}
          </div>
        </div>
      @endif
    </div>
  </section>
</div>

{{-- Print rules + tiny fallback --}}
<style>
@media print{
  body *{ visibility:hidden !important; }
  #print-analytics-index, #print-analytics-index *{ visibility:visible !important; }
  #print-analytics-index{ position:fixed; inset:0; margin:12mm !important; background:#fff; }
  #print-analytics-index .overflow-x-auto{ overflow:visible !important; }
  #print-analytics-index .shadow-sm{ box-shadow:none !important; }
  #print-analytics-index .border{ border:0 !important; }
  .screen-only{ display:none !important; }
  @page{ size:A4; margin:12mm 14mm; }
}
/* Force hide native select arrow across all browsers */
#courseSelect {
  -webkit-appearance: none !important;
  -moz-appearance: none !important;
  appearance: none !important;
  background-image: none !important;
}
#courseSelect::-ms-expand {
  display: none !important;
}
/* utility fallbacks if Tailwind fails to load */
.min-w-\[980px]{min-width:980px}
.rounded-2xl{border-radius:1rem}
</style>
@endsection

@push('scripts')
<script>
  (function () {
    const form = document.querySelector('form[action="{{ route('admin.course-analytics.index') }}"]');
    if (!form) return;

    const courseSelect = form.querySelector('select[name="course"]');
    if (!courseSelect) return;

    const submitWithResetPage = () => {
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

    courseSelect.addEventListener('change', submitWithResetPage);
  })();
</script>
@endpush
