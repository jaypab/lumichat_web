<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title ?? 'Course Summary' }}</title>
  <style>
    *{ box-sizing:border-box; }

    /* Embed DejaVu so Dompdf always finds it */
    @font-face{
      font-family:'DejaVu Sans';
      src:url('{{ public_path('fonts/DejaVuSans.ttf') }}') format('truetype');
      font-weight:400; font-style:normal;
    }
    @font-face{
      font-family:'DejaVu Sans';
      src:url('{{ public_path('fonts/DejaVuSans.ttf') }}') format('truetype');
      font-weight:700; font-style:normal;
    }

    body{
      font-family:"DejaVu Sans",sans-serif;
      margin:16mm 14mm 22mm;
      font-size:12.5px;
      color:#111827;
      line-height:1.45;
    }

    .brandbar{ margin:0 0 6px; }
    .brand-title{
      display:inline-block;
      vertical-align:middle;
      margin-left:10px;
      font:700 18px/1 "DejaVu Sans",sans-serif;
    }

    h1{ margin:6px 0 8px; font-size:22px; }
    .meta{ font-size:11px; color:#6b7280; margin:0 0 10px; }
    .meta-right{ float:right; }

    table{ width:100%; border-collapse:collapse; }
    thead{ background:#f1f5f9; color:#334155; }
    th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left; }
    tr{ page-break-inside:avoid; }

    .small{ font-size:10px; color:#6b7280; }
  </style>
</head>
<body>
@php
  $course = $course ?? (object)[];
  $courseLabel = $course->course ?? ($course->course_code ?? 'Course');
  $yearLabel   = $course->year_level ?? '—';

  // Normalize breakdown like in the show blade
  $rawItems = $course->breakdown ?? [];
  if ($rawItems instanceof \Illuminate\Support\Collection) {
      $rawItems = $rawItems->toArray();
  }
  $items = [];
  foreach ($rawItems as $row) {
      $label = (string) ($row['label'] ?? $row['diagnosis'] ?? $row['diagnosis_result'] ?? '—');
      $count = (int)    ($row['count'] ?? $row['cnt'] ?? 0);
      if ($label !== '—' && $count > 0) {
          $items[] = ['label'=>$label,'count'=>$count];
      }
  }
@endphp

  {{-- Brand --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50"
           style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
    <div class="meta">
      <span>{{ $courseLabel }} • {{ $yearLabel }}</span>
      <span class="meta-right">Generated: {{ $generatedAt }}</span>
    </div>
  </div>

  <h1>Course Summary — {{ $courseLabel }} • {{ $yearLabel }}</h1>

  {{-- Summary --}}
  <table style="margin-bottom:12px;">
    <thead>
      <tr>
        <th style="width:40%;">Course</th>
        <th style="width:30%;">Year Level</th>
        <th style="width:30%;">No. of Students<br><span class="small">(with case notes)</span></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>{{ $courseLabel }}</strong></td>
        <td>{{ $yearLabel }}</td>
        <td>{{ (int)($course->student_count ?? 0) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Breakdown --}}
  <table>
    <thead>
      <tr>
        <th>Presenting Concerns Breakdown (from case notes)</th>
        <th style="width:120px; text-align:right;">Count</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $row)
        <tr>
          <td>{{ $row['label'] }}</td>
          <td style="text-align:right;">{{ $row['count'] }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="2">No breakdown available.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- Footer --}}
  <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>

  <script type="text/php">
if (isset($pdf)) {
    $font  = $fontMetrics->get_font("DejaVu Sans", "normal");
    $size  = 9;
    $w     = $pdf->get_width();
    $h     = $pdf->get_height();
    $text  = "Page {PAGE_NUM} of {PAGE_COUNT}";
    $x     = $w - 72;
    $y     = $h - 28;
    $pdf->page_text($x, $y, $text, $font, $size, [0,0,0]);
}
  </script>
</body>
</html>
