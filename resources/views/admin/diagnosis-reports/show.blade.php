@extends('layouts.admin')
@section('title','Diagnosis Report Details')

@section('content')
@php
  $id    = $report->id;
  $code  = 'DRP-' . now()->format('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
  $name  = $report->student->name ?? '—';
  $coun  = $report->counselor->name ?? ('Counselor #' . ($report->counselor_id ?? '—'));
  $date  = $report->created_at?->format('F d, Y · h:i A') ?? '—';
  $res   = $report->diagnosis_result ?? '—';

  // chip style by result
  $resKey = strtolower(trim($res));
  $badge = match ($resKey) {
    'depressed', 'severe', 'severe anxiety'   => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
    'moderate', 'moderate anxiety'            => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    'mild', 'mild anxiety'                    => 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
    'normal', 'ok', 'stable'                  => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    default                                   => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
  };
@endphp

<div class="max-w-5xl mx-auto space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between gap-3 screen-only">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Diagnosis Report</h2>
      <p class="text-sm text-slate-600">Finalized diagnosis summary.</p>
    </div>

    <div class="flex items-center gap-2">
      {{-- Download PDF (prints only the report card) --}}
      <button type="button"
              onclick="printNode('#print-report-root','Diagnosis Report {{ $code }}')"
              class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Download PDF
      </button>

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
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="h-1 w-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500"></div>

      <div class="p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs uppercase text-slate-500">Report ID</div>
            <div class="font-semibold text-slate-900">{{ $code }}</div>
          </div>

          <div class="text-right">
            <div class="text-xs uppercase text-slate-500">Diagnosis Result</div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $badge }}">
              {{ $res }}
            </span>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-3">
            <div>
              <div class="text-xs uppercase text-slate-500">Student Name</div>
              <div class="font-medium text-slate-900">{{ $name }}</div>
            </div>

            <div>
              <div class="text-xs uppercase text-slate-500">Counselor Name</div>
              <div class="font-medium text-slate-900">{{ $coun }}</div>
            </div>

            <div>
              <div class="text-xs uppercase text-slate-500">Date</div>
              <div class="font-medium text-slate-900">{{ $date }}</div>
            </div>
          </div>

          <div class="space-y-3">
            @if(!empty($report->notes))
              <div>
                <div class="text-xs uppercase text-slate-500">Notes</div>
                <div class="text-slate-800 leading-relaxed">{{ $report->notes }}</div>
              </div>
            @endif

            @if(!empty($report->attachments))
              <div>
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

{{-- Print + Export styles and helper --}}
<style>
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
