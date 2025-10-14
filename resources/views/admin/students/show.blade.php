@extends('layouts.admin')
@section('title', 'Admin - Student Details')
@section('page_title', 'Student Details') 

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- Header --}}
  <div class="flex items-start justify-between screen-only">
    <div>
      <h2 class="text-2xl font-semibold text-slate-900">Student Details</h2>
      <p class="text-sm text-slate-500">
        {{ $student->course }} • {{ $student->year_level }}
        <span class="text-slate-400">—</span>
        <span class="text-slate-600">{{ $student->email }}</span>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <a href="{{ route('admin.students.index') }}"
         class="h-11 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200
                shadow-sm hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to list
      </a>

     <a href="{{ route('admin.students.show.export.pdf', ['student'=>$student->id, 'year'=>$year]) }}"
   target="_blank" rel="noopener"
   class="h-11 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 text-white shadow-sm
          hover:bg-emerald-700 active:scale-[.99] transition">
  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
  </svg>
  Download PDF
</a>

    </div>
  </div>

  {{-- ===== PRINT SCOPE START ===== --}}
  <div id="print-details-root" class="space-y-6">

    {{-- Chart Card --}}
    <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
     <span class="pointer-events-none absolute left-0 right-0 top-0 h-1
               bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>

      <div class="p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
          <div class="flex items-center gap-3">
            <h3 class="text-lg font-semibold text-gray-900">
              Appointments — <span class="font-normal text-gray-600">Monthly totals</span>
            </h3>

            @if(isset($total))
              <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                Total: {{ $total }}
              </span>
            @endif

            @isset($peakLabel)
              <span class="hidden sm:inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                Peak: {{ $peakLabel }}
              </span>
            @endisset
          </div>

          {{-- Year selector (screen only) --}}
          <form method="GET" action="{{ route('admin.students.show', $student->id) }}" class="flex items-center gap-2 screen-only">
            <input type="hidden" name="year" id="yearInput" value="{{ $year }}">
            @php
              $minYear = min($yearsAvailable);
              $maxYear = max($yearsAvailable);
            @endphp

            <button type="button"
                    class="rounded-lg border px-2.5 py-1 text-sm disabled:opacity-40"
                    onclick="bumpYear(-1)"
                    {{ $year <= $minYear ? 'disabled' : '' }}
                    aria-label="Previous year">‹</button>

            <label for="yearSelect" class="text-sm text-gray-600">Year</label>
            <select id="yearSelect"
                    class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    onchange="document.getElementById('yearInput').value=this.value; this.form.submit()">
              @foreach ($yearsAvailable as $y)
                <option value="{{ $y }}" @selected((int)$year === (int)$y)>{{ $y }}</option>
              @endforeach
            </select>

            <button type="button"
                    class="rounded-lg border px-2.5 py-1 text-sm disabled:opacity-40"
                    onclick="bumpYear(1)"
                    {{ $year >= $maxYear ? 'disabled' : '' }}
                    aria-label="Next year">›</button>
          </form>
        </div>

        <div class="relative h-72 md:h-80">
          <canvas id="studentApptsChart" role="img" aria-label="Bar chart of monthly appointments for year {{ $year }}"></canvas>

          @if (($total ?? 0) === 0)
            <div class="absolute inset-0 grid place-items-center">
              <div class="text-center text-sm text-gray-500">
                No appointments recorded for {{ $year }}.
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Info Card --}}
     <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm p-8 space-y-6 border">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <p class="text-sm text-gray-500">FULL NAME</p>
          <p class="text-lg font-medium text-slate-900">{{ $student->name }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500">EMAIL</p>
          <p class="text-lg font-medium text-slate-900">{{ $student->email }}</p>
        </div>

        <div>
          <p class="text-sm text-gray-500">CONTACT NUMBER</p>
          <p class="text-lg font-medium text-slate-900">{{ $student->contact_number }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500">COURSE</p>
          <p class="text-lg font-medium text-slate-900">{{ $student->course }}</p>
        </div>

        <div>
          <p class="text-sm text-gray-500">YEAR LEVEL</p>
          <p class="text-lg font-medium text-slate-900">{{ $student->year_level }}</p>
        </div>
      </div>

      <div class="border-t pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <p class="text-sm text-gray-500">CREATED</p>
          <p class="text-lg font-medium text-slate-900">{{ \Carbon\Carbon::parse($student->created_at)->format('F d, Y • h:i A') }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500">UPDATED</p>
          <p class="text-lg font-medium text-slate-900">{{ \Carbon\Carbon::parse($student->updated_at)->format('F d, Y • h:i A') }}</p>
        </div>
      </div>

      <div class="flex gap-4 pt-4 screen-only">
        <a href="mailto:{{ $student->email }}"
           class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
          Email Student
        </a>
        <button onclick="navigator.clipboard.writeText('{{ $student->email }}')"
                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
          Copy Email
        </button>
      </div>
    </div>
  </div>
  {{-- ===== PRINT SCOPE END ===== --}}
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
  window.bumpYear = function(delta){
    const sel = document.getElementById('yearSelect');
    const values = Array.from(sel.options).map(o=>parseInt(o.value,10));
    const current = parseInt(sel.value,10);
    const idx = values.indexOf(current);
    const target = values[idx + (delta > 0 ? -1 : +1)];
    if (typeof target !== 'undefined') {
      sel.value = String(target);
      document.getElementById('yearInput').value = String(target);
      sel.form.submit();
    }
  };

  (function(){
    const canvas = document.getElementById('studentApptsChart');
    if (!canvas) return;

    const series = (@json($series ?? [])).map(v => parseInt(v, 10) || 0);
    const labels = @json($labels ?? []);
    const total = series.reduce((a,b)=>a+b,0);

    if (window.Chart && Chart.getChart) {
      const prev = Chart.getChart(canvas);
      if (prev) prev.destroy();
    }

    if (total === 0) {
      const ctx = canvas.getContext('2d');
      if (ctx) ctx.clearRect(0,0,canvas.width, canvas.height);
      return;
    }

    new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets: [{
        data: series,
        borderColor: '#4f46e5',
        backgroundColor: 'rgba(99,102,241,0.35)',
        hoverBackgroundColor: 'rgba(99,102,241,0.55)',
        borderWidth: 1.5,
      }]},
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 300 },
        elements: { bar: { borderRadius: 6 } },
        scales: {
          x: { grid: { display:false }, ticks: { color:'#334155' } },
          y: { beginAtZero:true, ticks:{ precision:0, color:'#334155' }, grid:{ color:'rgba(148,163,184,0.25)' } }
        },
        plugins: {
          legend: { display:false },
          tooltip: {
            backgroundColor:'#111827', padding:10, displayColors:false,
            callbacks:{
              title: items => `Month: ${items[0].label}`,
              label: ctx => {
                const y = ctx.parsed.y || 0;
                return `${y} appointment${y===1?'':'s'}`;
              }
            }
          },
          title: {
            display:true, text:'Appointments in ' + @json($year),
            color:'#0f172a', font:{ size:14, weight:'600' }, padding:{ top:4, bottom:10 }
          }
        }
      }
    });
  })();
</script>

<style media="print">
  body * { visibility: hidden !important; }
  #print-details-root, #print-details-root * { visibility: visible !important; }
  #print-details-root .rounded-2xl,
  #print-details-root .shadow-sm,
  #print-details-root .border { border:0 !important; box-shadow:none !important; }
  .screen-only { display:none !important; }
</style>
@endpush
