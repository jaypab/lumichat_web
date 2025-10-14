@extends('layouts.admin')
@section('title','Counselor Logs - '.$counselor->full_name)
@section('page_title', 'Counselors Logs Summary')

@section('content')
@php
  $label = \Carbon\Carbon::create($year,$month,1)->format('F Y');

  // KPIs (robust to missing data)
  $totalAppts     = $students->count();
  $uniqueStudents = $students->pluck('student_id')->filter()->unique()->count();
  $withDx         = $students->where('diagnosis_result','<>','—')->count();
  $noDx           = max($totalAppts - $withDx, 0);

  // Tailwind palette (shared with index)
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
  $default = ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200'];

  $pill = function(string $label, ?int $count=null, bool $active=false) use ($palette,$default){
    $s = $palette[$label] ?? $default;
    $cnt = $count !== null ? ' • '.$count : '';
    $activeCls = $active ? ' ring-2 ring-offset-1 ring-indigo-500 ' : '';
    return '<button type="button" data-chip="'.$label.'" title="'.e($label.$cnt).'"
              class="dx-chip inline-flex items-center h-8 px-3 rounded-full text-xs font-medium '.$s['bg'].' '.$s['text'].' ring-1 '.$s['ring'].$activeCls.'">'
          .e($label.$cnt).'</button>';
  };

  $badge = function(?string $label) use ($palette,$default){
    $label = $label && trim($label) !== '' ? $label : '—';
    if ($label === '—') {
      return '<span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium bg-slate-50 text-slate-600 ring-1 ring-slate-200">—</span>';
    }
    $s = $palette[$label] ?? $default;
    return '<span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-medium '.$s['bg'].' '.$s['text'].' ring-1 '.$s['ring'].'">'.e($label).'</span>';
  };
@endphp

<div class="max-w-6xl mx-auto space-y-6">

  {{-- Header --}}
  <div class="flex items-start justify-between gap-3 screen-only">
    <div>
      <h2 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $counselor->full_name }}</h2>
      <p class="text-sm text-slate-500">Logs for <span class="font-medium text-slate-700">{{ $label }}</span></p>
    </div>

    <div class="flex items-center gap-2">
<a href="{{ route('admin.counselor-logs.show.export', ['counselor'=>$counselor->id, 'month'=>$month, 'year'=>$year]) }}"
   target="_blank" rel="noopener"
   class="inline-flex items-center h-10 px-4 rounded-xl text-sm font-medium bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 active:scale-[.99] transition">
  <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
  </svg>
  Download PDF
</a>


      <a href="{{ route('admin.counselor-logs.index', request()->only('month','year','counselor_id')) }}"
         class="inline-flex items-center h-10 px-4 rounded-xl text-sm font-medium bg-white border border-slate-200 text-slate-700 shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
        ← Back
      </a>
    </div>
  </div>

  {{-- KPI bar --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="text-[11px] uppercase tracking-wide text-slate-500">Appointments</div>
      <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $totalAppts }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="text-[11px] uppercase tracking-wide text-slate-500">Unique students</div>
      <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $uniqueStudents }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="text-[11px] uppercase tracking-wide text-slate-500">With diagnosis</div>
      <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ $withDx }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-[11px] uppercase tracking-wide text-slate-500">No diagnosis</div>
          <div class="mt-1 text-2xl font-semibold text-rose-700">{{ $noDx }}</div>
        </div>
        <label class="screen-only inline-flex items-center gap-2 text-xs text-slate-600">
          <input id="toggle-nodx" type="checkbox" class="rounded border-slate-300">
          <span>Show only without diagnosis</span>
        </label>
      </div>
    </div>
  </div>

  {{-- ===== PRINT SCOPE ===== --}}
  <div id="print-counselor-show" class="space-y-5">

    {{-- Print title --}}
    <h1 class="hidden print:block text-xl font-semibold">
      Counselor Logs — {{ $counselor->full_name }} ({{ $label }})
    </h1>

    {{-- Diagnosis Summary (filterable chips) --}}
    @if($dxCounts->count())
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden relative" aria-labelledby="dx-summary-h">
        <span class="pointer-events-none absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>
        <div class="px-5 py-4">
          <div id="dx-summary-h" class="text-sm font-semibold text-slate-800 mb-2">Diagnosis Summary</div>
          <div role="list" class="flex flex-wrap gap-2">
            {{-- “All” chip to clear filter --}}
            <button type="button" data-chip="__ALL__"
                    class="dx-chip inline-flex items-center h-8 px-3 rounded-full text-xs font-medium bg-slate-50 text-slate-700 ring-1 ring-slate-200 ring-2 ring-offset-1 ring-indigo-500"
                    title="Show all">All</button>
            @foreach($dxCounts as $dx)
              {!! $pill((string)$dx->diagnosis_result, (int)$dx->cnt, false) !!}
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Controls: search (screen only) --}}
    <div class="screen-only flex items-center justify-between gap-3">
      <div class="relative w-full max-w-sm">
        <input id="search-student" type="text" placeholder="Search student name..."
               class="w-full h-10 pl-9 pr-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
               aria-label="Search student by name">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="11" cy="11" r="7" />
          <path d="M21 21l-4.3-4.3" />
        </svg>
      </div>
    </div>

    {{-- Table --}}
    <div class="relative rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-200 text-sm font-semibold text-slate-800">
        Student Diagnosis Summary
      </div>

      <div class="relative overflow-x-auto">
        <table class="min-w-full text-sm leading-6">
          <colgroup>
            <col style="width:40%">
            <col style="width:25%">
            <col style="width:35%">
          </colgroup>
          <thead class="bg-slate-50 text-slate-600">
            <tr class="text-left">
              <th class="px-5 py-3 font-medium">Student</th>
              <th class="px-5 py-3 font-medium">Scheduled</th>
              <th class="px-5 py-3 font-medium">Diagnosis / Result</th>
            </tr>
          </thead>
          <tbody id="rows" class="divide-y divide-slate-100">
            @forelse($students as $row)
              @php
                $noDxRow = ($row->diagnosis_result === '—' || trim((string)$row->diagnosis_result) === '');
                $dxVal   = $noDxRow ? '' : (string)$row->diagnosis_result;
              @endphp
              <tr class="hover:bg-slate-50/60 {{ $noDxRow ? 'nodx' : '' }}"
                  data-dx="{{ e($dxVal) }}">
                <td class="px-5 py-3 student-cell">{{ $row->student_name ?? '—' }}</td>
                <td class="px-5 py-3 whitespace-nowrap">{{ $row->scheduled_at_fmt }}</td>
                <td class="px-5 py-3">{!! $badge($row->diagnosis_result) !!}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="px-5 py-10 text-center text-slate-500">No appointments this month.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>{{-- /print scope --}}
</div>

{{-- Client-side filtering (diagnosis chips, search, no-dx toggle) --}}
<script class="screen-only">
  (function(){
    const rows = Array.from(document.querySelectorAll('#rows tr'));
    const chips = Array.from(document.querySelectorAll('.dx-chip'));
    const nodx = document.getElementById('toggle-nodx');
    const search = document.getElementById('search-student');

    let activeDx = '__ALL__';  // '__ALL__' | label string
    let onlyNoDx = false;
    let q = '';

    function normalize(t){ return (t||'').toLowerCase().trim(); }

    function applyFilters(){
      const qn = normalize(q);
      rows.forEach(tr => {
        const isNoDx = tr.classList.contains('nodx');
        const dx = tr.getAttribute('data-dx') || '';
        const matchesDx = (activeDx === '__ALL__') ? true : (dx === activeDx);
        const matchesNoDx = onlyNoDx ? isNoDx : true;
        const nameCell = tr.querySelector('.student-cell');
        const nameTxt = normalize(nameCell ? nameCell.textContent : '');
        const matchesSearch = qn ? nameTxt.includes(qn) : true;

        tr.style.display = (matchesDx && matchesNoDx && matchesSearch) ? '' : 'none';
      });
    }

    chips.forEach(btn => {
      btn.addEventListener('click', () => {
        // update visual active state
        chips.forEach(c => c.classList.remove('ring-2','ring-indigo-500','ring-offset-1'));
        btn.classList.add('ring-2','ring-indigo-500','ring-offset-1');

        const val = btn.getAttribute('data-chip');
        activeDx = val || '__ALL__';
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
  })();
</script>

{{-- Print rules --}}
<style>
@media print{
  body *{ visibility:hidden !important; }
  #print-counselor-show, #print-counselor-show *{ visibility:visible !important; }
  #print-counselor-show{ position:fixed; inset:0; margin:12mm !important; background:#fff; }
  #print-counselor-show .overflow-x-auto{ overflow:visible !important; }
  #print-counselor-show .shadow-sm{ box-shadow:none !important; }
  #print-counselor-show .border{ border:0 !important; }
  .screen-only{ display:none !important; }
  @page{ size:A4; margin:12mm 14mm; }
}
</style>
@endsection
