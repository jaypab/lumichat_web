<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Course Analytics</title>
  <style>
    *{ box-sizing:border-box; font-family:DejaVu Sans, sans-serif; }
    body{ margin:16mm 14mm 22mm; font-size:12.5px; color:#111827; line-height:1.45; }

    .brandbar{ margin:0 0 10px; text-align:left; }
    .brand{ display:inline-block; }
    .brand-logo{ width:50px; height:50px; border-radius:50%; vertical-align:middle; }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }

    h1{ margin:10px 0 6px; font-size:20px; }
    .meta{ font-size:11px; color:#6b7280; margin-bottom:12px; }

    table{ width:100%; border-collapse:collapse; }
    thead{ background:#f1f5f9; color:#334155; display:table-header-group; }
    th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left; }
    tr{ page-break-inside:avoid; }
    .small{ font-size:10px; color:#6b7280; }
  </style>
</head>
<body>

  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData)) <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">@endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>Course Analytics</h1>
  <div class="meta">
    Filters — Year: <strong>{{ $yearKey }}</strong>
    @if(($q ?? '') !== '') | Search: <strong>{{ $q }}</strong>@endif
    <span style="float:right;">Generated: {{ $generatedAt }}</span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:15%;">Course</th>
        <th style="width:20%;">Year</th>
        <th style="width:25%;">Students</th>
        <th style="width:42%;">Common Diagnosis</th>
      </tr>
    </thead>
    <tbody>
      @forelse($courses as $c)
        @php
          $course    = $c->course ?? '—';
          $year      = $c->year_level ?? '—';
          $count     = $c->student_count ?? '—';
          $list      = is_array($c->common_diagnoses ?? null) ? $c->common_diagnoses : [];
          $diagnoses = count($list) ? implode(', ', $list) : '—';
        @endphp
        <tr>
          <td><strong>{{ $course }}</strong></td>
          <td>{{ $year }}</td>
          <td>{{ $count }}</td>
          <td>{{ $diagnoses }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="small" style="text-align:center; padding:18px 0;">No course analytics found.</td></tr>
      @endforelse
    </tbody>
  </table>
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
    $x     = $w - 72;   // ~1 inch from right
    $y     = $h - 28;   // ~28pt from bottom
    $pdf->page_text($x, $y, $text, $font, $size, [0,0,0]);
}
</script>
</body>
</html>
