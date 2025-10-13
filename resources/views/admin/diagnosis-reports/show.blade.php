@extends('layouts.admin')
@section('title','Admin - Diagnosis Report')
@section('page_title', 'Diagnosis Report Information') 

@section('content')
@php
  $id    = $report->id;
  $code  = 'DRP-' . now()->format('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
  $name  = $report->student->name ?? '—';
  $coun  = $report->counselor->name ?? ('Counselor #' . ($report->counselor_id ?? '—'));
  $date  = $report->created_at?->format('F d, Y · h:i A') ?? '—';
  $res   = trim((string)($report->diagnosis_result ?? '—'));

  // Unified palette used across pages
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
  $fallback = ['bg'=>'bg-slate-100','text'=>'text-slate-700','ring'=>'ring-slate-200'];

  // Size-aware pill helper (sm = table chips, lg = header chip)
  $pill = function (string $label, string $size='lg') use ($palette, $fallback) {
    $sty = $palette[$label] ?? null;

    // severity fallback if not in palette
    if (!$sty) {
      $k = strtolower($label);
      $sty = match (true) {
        str_contains($k,'severe')    || str_contains($k,'depress') => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200'],
        str_contains($k,'moderate')                               => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200'],
        str_contains($k,'mild')                                   => ['bg'=>'bg-orange-50','text'=>'text-orange-700','ring'=>'ring-orange-200'],
        str_contains($k,'normal') || $k==='ok' || $k==='stable'   => ['bg'=>'bg-emerald-50','text'=>'text-emerald-700','ring'=>'ring-emerald-200'],
        default                                                   => $fallback,
      };
    }

    $sz = $size === 'sm'
        ? 'h-6 px-2 text-[11px]'
        : 'px-3 py-1 text-xs'; // header/result chip

    return '<span class="inline-flex items-center rounded-full font-medium '.$sz.' '.$sty['bg'].' '.$sty['text'].' ring-1 '.$sty['ring'].'">'
         . e($label) . '</span>';
  };
@endphp

<div class="max-w-5xl mx-auto space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between gap-3 screen-only fade-in">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Diagnosis Report</h2>
      <p class="text-sm text-slate-600">Finalized diagnosis summary.</p>
    </div>

    <div class="flex items-center gap-2">
      <a href="{{ route('admin.diagnosis-reports.show.export.pdf', ['report' => $report->id]) }}"
        class="inline-flex items-center h-9 px-3 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700">
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Download PDF
      </a>

      <a href="{{ route('admin.diagnosis-reports.index') }}"
         class="inline-flex items-center h-9 px-3 rounded-lg bg-white text-slate-800 ring-1 ring-slate-200 hover:bg-slate-50">
        ← Back to list
      </a>
    </div>
  </div>

  {{-- ===== PRINT SCOPE (only this area is exported as PDF) ===== --}}
  <div id="print-report-root" class="space-y-6">
    {{-- Printable heading --}}
    <div class="hidden print:flex items-center justify-between">
      <h1 class="text-lg font-bold">Diagnosis Report — {{ $code }}</h1>
      <div class="text-xs text-slate-600">Generated: {{ now()->format('Y-m-d · H:i') }}</div>
    </div>

    {{-- Report Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden fade-in" style="--delay:.05s">
      {{-- animated accent bar --}}
      <span class="accent-bar"></span>

      <div class="p-6">
        <div class="flex items-start justify-between gap-4">
          <div class="fade-in">
            <div class="text-xs uppercase text-slate-500">Report ID</div>
            <div class="font-semibold text-slate-900">{{ $code }}</div>
          </div>

          <div class="text-right fade-in" style="--delay:.06s">
              <div class="text-xs uppercase text-slate-500">Diagnosis Result</div>
              {!! $pill($res, 'lg') !!}
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-3">
            <div class="stagger" style="--i:1">
              <div class="text-xs uppercase text-slate-500">Student Name</div>
              <div class="font-medium text-slate-900">{{ $name }}</div>
            </div>

            <div class="stagger" style="--i:2">
              <div class="text-xs uppercase text-slate-500">Counselor Name</div>
              <div class="font-medium text-slate-900">{{ $coun }}</div>
            </div>

            <div class="stagger" style="--i:3">
              <div class="text-xs uppercase text-slate-500">Date</div>
              <div class="font-medium text-slate-900">{{ $date }}</div>
            </div>
          </div>

          <div class="space-y-3">
            @if(!empty($report->notes))
              <div class="fade-in" style="--delay:.1s">
                <div class="text-xs uppercase text-slate-500">Notes</div>
                <div class="text-slate-800 leading-relaxed">{{ $report->notes }}</div>
              </div>
            @endif

            @if(!empty($report->attachments))
              <div class="fade-in" style="--delay:.12s">
                <div class="text-xs uppercase text-slate-500">Attachments</div>
                <ul class="list-disc pl-5 text-slate-800">
                  @foreach($report->attachments as $a)
                    <li>{{ $a->name ?? 'File' }}</li>
                  @endforeach
                </ul>
              </div>
            @endif
          </div>
        </div>

        {{-- footer line --}}
        <div class="mt-8 pt-4 border-t border-slate-200 text-xs text-slate-500">
          LumiCHAT • Tagoloan Community College — Confidential student support record.
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Print + Export styles and helper + animations --}}
<style>
  /* --------- animations (reduced-motion aware) ---------- */
  .accent-bar{
    position:absolute; inset-inline:0; top:-1px; height:4px;
    background: linear-gradient(90deg, #6366f1, #a855f7, #e879f9);
    background-size: 200% 100%;
    animation: shimmer 8s linear infinite;
    display:block;
  }
  @keyframes shimmer{ from{background-position:0% 0} to{background-position:200% 0} }

  .fade-in{ opacity:0; transform: translateY(6px); animation: fadeUp .6s ease forwards; animation-delay: var(--delay, 0s); }
  .stagger{ opacity:0; transform: translateY(6px); animation: fadeUp .5s ease forwards; animation-delay: calc(var(--i,0) * 70ms); }
  @keyframes fadeUp{ to{ opacity:1; transform:none } }

  @media (prefers-reduced-motion: reduce){
    .accent-bar{ animation:none !important; }
    .fade-in,.stagger{ opacity:1 !important; transform:none !important; animation:none !important; }
  }

  /* screen-only controls never appear in exported PDF */
  @media print { .screen-only { display:none !important; } }

  /* Nice pdf margins & preserve colors */
  @media print {
    @page { margin: 12mm; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>

@push('scripts')
<script>
  // Export only the report root with current page styles
  function printNode(selector, title = document.title) {
    const node = document.querySelector(selector) || document.body;
    const w = window.open('', '_blank', 'width=1024,height=700');
    const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
      .map(n => n.outerHTML).join('\n');

    w.document.write(`
      <html>
        <head>
          <meta charset="utf-8">
          <title>${title}</title>
          ${styles}
          <style>
            @page{ margin:12mm }
            @media print{ body{ background:#fff!important; } }
            .screen-only{ display:none !important; }
          </style>
        </head>
        <body>${node.outerHTML}</body>
      </html>
    `);

    w.document.close();
    w.focus();
    w.onload = () => w.print();
  }
</script>
@endpush
@endsection
