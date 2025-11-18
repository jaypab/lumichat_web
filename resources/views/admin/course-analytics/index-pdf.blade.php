<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Course Summary</title>
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

  @php
    // Normalize filters for display
    $yearKey    = $yearKey   ?? 'all';
    $courseKey  = $courseKey ?? 'all';

    $yearLabel = match($yearKey){
      '1'   => '1st year',
      '2'   => '2nd year',
      '3'   => '3rd year',
      '4'   => '4th year',
      default => 'All year levels',
    };

    $courseLabel = $courseKey === 'all' ? 'All courses' : $courseKey;

    // Helper: convert stored common_diagnoses (array / "a||b" / "a,b") → clean array
    $toConcerns = function ($raw){
      if (is_array($raw)) {
        $arr = $raw;
      } else {
        $str = trim((string) $raw);
        if ($str === '') return [];
        if (strpos($str,'||') !== false) {
          $arr = explode('||',$str);
        } elseif (strpos($str,',') !== false) {
          $arr = explode(',',$str);
        } else {
          $arr = [$str];
        }
      }
      $arr = array_map('trim',$arr);
      $arr = array_filter($arr, fn($v)=>$v!=='');
      $arr = array_values(array_unique($arr));
      return $arr;
    };
  @endphp

  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData))
        <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
      @endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>Course Summary</h1>
  <div class="meta">
    Filters —
    Year: <strong>{{ $yearLabel }}</strong>
    | Course: <strong>{{ $courseLabel }}</strong>
    <span style="float:right;">Generated: {{ $generatedAt }}</span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:18%;">Course</th>
        <th style="width:18%;">Year Level</th>
        <th style="width:18%;">No. of Students<br><span class="small">(with case notes)</span></th>
        <th style="width:46%;">Common Presenting Concerns</th>
      </tr>
    </thead>
    <tbody>
      @forelse($courses as $c)
        @php
          $course    = $c->course ?? ($c->course_code ?? '—');
          $year      = $c->year_level ?? '—';
          $count     = (int) ($c->student_count ?? 0);

          $concernsArr = $toConcerns($c->common_diagnoses ?? null);
          $diagnoses   = $concernsArr ? implode(', ', $concernsArr) : '—';
        @endphp
        <tr>
          <td><strong>{{ $course }}</strong></td>
          <td>{{ $year }}</td>
          <td>{{ $count }}</td>
          <td>{{ $diagnoses }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="small" style="text-align:center; padding:18px 0;">
            No course summary data found.
          </td>
        </tr>
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
