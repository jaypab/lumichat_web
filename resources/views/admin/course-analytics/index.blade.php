{{-- resources/views/admin/course-analytics/index.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Course Summary')
@section('page_title','Course Summary')

@php
  use Illuminate\Support\Str;

  $yearKey       = request('year','all');             // all|1|2|3|4
  $courseOptions = $courseOptions ?? collect();
  $courseKey     = $courseKey     ?? request('course','all');

  $total         = is_countable($courses) ? count($courses) : ($courses?->count() ?? 0);

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

  {{-- ===== Header band (gradient) ===== --}}
  <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm screen-only">
    <div class="p-5 sm:p-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Course Summary</h2>
          <p class="text-white/80 text-sm mt-0.5">
            Summary of case notes and presenting concerns across programs.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-1.5 text-sm ring-1 ring-white/20">
            <svg class="h-4 w-4 opacity-90" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 1a7 7 0 0 0-7 7v3.126a4 4 0 0 1-.832 2.4L2.6 16.6A1 1 0 0 0 3.4 18h17.2a1 1 0 0 0 .8-1.6l-1.568-3.074A4 4 0 0 1 19 11.126V8a7 7 0 0 0-7-7Zm0 22a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z"/>
            </svg>
            <strong class="font-semibold">{{ $total }}</strong>
            <span class="opacity-90">course-year records</span>
          </span>

          {{-- PDF --}}
          <a href="{{ route('admin.course-analytics.export.pdf', request()->only('year','course')) }}"
             target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
            </svg>
            Download PDF
          </a>
        </div>
      </div>
    </div>
  </section>

  {{-- ===== Filters ===== --}}
  <form method="GET" action="{{ route('admin.course-analytics.index') }}" class="screen-only">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">

      {{-- Year chips --}}
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-slate-500 mr-1.5">Year:</span>

        {{-- single hidden to keep current course across year taps --}}
        <input type="hidden" name="course" value="{{ $courseKey }}">

        @foreach($yearChips as $key => $label)
          @php $active = $yearKey === $key; @endphp

          <button type="submit"
                  name="year"
                  value="{{ $key }}"
                  class="inline-flex items-center h-8 px-3 rounded-lg text-xs font-medium
                         {{ $active ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'bg-slate-50 text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100' }}">
            {{ $label }}
          </button>
        @endforeach
      </div>

      {{-- Course select + actions --}}
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-8">
          <label class="block text-xs font-medium text-slate-600 mb-1">Course</label>
          <div class="relative">
            <select name="course"
                    class="w-full h-10 rounded-xl border border-slate-200 bg-white pr-10 pl-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="all" {{ $courseKey==='all'?'selected':'' }}>All courses</option>
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
            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M6 9l6 6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <div class="md:col-span-4 flex items-end justify-end gap-2">
          <a href="{{ route('admin.course-analytics.index') }}"
             class="h-10 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 active:scale-[.99]">
            Reset
          </a>
          {{-- Apply removed – course auto-submits on change --}}
        </div>
      </div>
    </div>
  </form>

  {{-- ===== Table ===== --}}
  <section id="print-analytics-index">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-[980px] w-full text-sm text-left">
          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700 sticky top-0 z-10">
            <tr class="text-[12px] uppercase tracking-wide">
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Course</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Year Level</th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">
                No. of Students<br>
                <span class="text-[10px] font-normal text-slate-500">(with case notes)</span>
              </th>
              <th class="px-6 py-3 font-semibold whitespace-nowrap">Common Presenting Concerns</th>
              <th class="px-6 py-3 text-right font-semibold whitespace-nowrap screen-only">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-800">
            @forelse($courses as $c)
              @php
                $id     = $c->id ?? null;
                $course = $c->course ?? ($c->course_code ?? '—');
                $year   = $c->year_level ?? '—';
                $count  = (int) ($c->student_count ?? 0);

                // common_diagnoses now stores aggregated presenting problems from case notes
                $concernsArr = $toConcernsArray($c->common_diagnoses ?? []);
                $concernsArr = array_values(array_unique(array_filter(array_map('trim',$concernsArr))));
                $chips       = array_slice($concernsArr, 0, 6);
                $moreN       = max(0, count($concernsArr) - count($chips));
                $moreTxt     = $moreN>0 ? implode(', ', array_slice($concernsArr, 6)) : '';
              @endphp
              <tr class="hover:bg-slate-50 transition align-top">
                <td class="px-6 py-4 font-medium text-slate-900 whitespace-nowrap">{{ $course }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $year }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium bg-slate-50 text-slate-700 ring-1 ring-slate-200">
                    {{ $count }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex flex-wrap gap-1.5">
                    @forelse($chips as $label)
                      {!! $pill($label) !!}
                    @empty
                      <span class="text-slate-400">—</span>
                    @endforelse

                    @if($moreN>0)
                      <span
                        class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium bg-slate-50 text-slate-600 ring-1 ring-slate-200"
                        title="{{ $moreTxt }}">
                        +{{ $moreN }} more
                      </span>
                    @endif
                  </div>
                </td>
                <td class="px-6 py-4 text-right screen-only">
                  @if($id)
                    <a href="{{ route('admin.course-analytics.show',$id) }}"
                       class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
                      View
                    </a>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-6 pt-14 pb-10 text-center">
                  <div class="mx-auto w-full max-w-sm">
                    <div class="mx-auto w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400">
                      ☰
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700">No course summary found</p>
                    <p class="text-xs text-slate-500 mb-6">
                      Try a different year or course filter, or ensure case notes have been recorded.
                    </p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if(method_exists($courses,'hasPages') && $courses->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 screen-only">
          {{ $courses->withQueryString()->links() }}
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
