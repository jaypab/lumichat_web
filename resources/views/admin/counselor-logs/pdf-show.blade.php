{{-- resources/views/admin/counselor-logs/pdf-show.blade.php --}}
@php
  $cName = $counselor->full_name ?? $counselor->name ?? 'Unknown Counselor';
  $title = 'Counselor Logs — '.$cName.' ('.$label.')';

  // Normalize dxCounts to a simple [label => count] array.
  if ($dxCounts instanceof \Illuminate\Support\Collection) {
      // if it's collection of objects {diagnosis_result, cnt} from old version
      if ($dxCounts->isNotEmpty() && isset($dxCounts->first()->diagnosis_result)) {
          $dxItems = [];
          foreach ($dxCounts as $dx) {
              $dxItems[$dx->diagnosis_result] = (int)$dx->cnt;
          }
      } else {
          $dxItems = $dxCounts->toArray();
      }
  } elseif (is_array($dxCounts)) {
      $dxItems = $dxCounts;        // new version: ['Stress' => 3, ...]
  } else {
      $dxItems = [];
  }
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title }}</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }

    body {
      margin: 16mm 14mm 22mm;
      font-size: 12.5px;
      color: #111827;
      line-height: 1.45;
    }

    /* Brand header (logo + title side-by-side) */
    .brandbar {
      margin: 0 0 10px;
      padding-bottom: 6px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
    }
    .brand { display: inline-block; }
    .brand-logo { width: 50px; height: 50px; border-radius: 50%; vertical-align: middle; }
    .brand-title {
      display: inline-block;
      vertical-align: middle;
      margin-left: 10px;
      font: 700 18px/1 DejaVu Sans, sans-serif;
      white-space: nowrap;
    }

    h1 {
      margin: 10px 0 4px;
      font-size: 20px;
    }
    .meta {
      font-size: 11px;
      color: #6b7280;
      margin-bottom: 10px;
    }
    .meta-line {
      margin-bottom: 2px;
    }

    /* Chips for presenting-problem summary */
    .chip {
      display: inline-block;
      padding: 4px 9px;
      border-radius: 999px;
      font-size: 11px;
      border: 1px solid #bae6fd;
      color: #0369a1;
      background: #e0f2fe;
      margin: 0 6px 6px 0;
      white-space: nowrap;
    }

    /* Section label above table */
    .section-title {
      font-size: 12px;
      font-weight: 700;
      margin-top: 6px;
      margin-bottom: 4px;
      color: #374151;
    }

    .card {
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 10px 10px 6px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }
    thead {
      background: #f1f5f9;
      color: #334155;
    }
    th, td {
      padding: 9px 10px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
      vertical-align: top;
    }
    th {
      font-weight: 700;
      font-size: 11.5px;
    }
    thead { display: table-header-group; }  /* repeat header each page */
    tr { page-break-inside: avoid; }

    tbody tr:nth-child(even) {
      background: #f9fafb;
    }

    .wrap {
      word-wrap: break-word;
      word-break: break-word;
      white-space: normal;
    }

    .small   { font-size: 10.5px; color:#6b7280; }
    .footer-note {
      margin-top: 10px;
      padding-top: 6px;
      border-top: 1px solid #e5e7eb;
    }
  </style>
</head>
<body>

  {{-- Brand --}}
  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData))
        <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
      @endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>{{ $title }}</h1>
  <div class="meta">
    <div class="meta-line">
      <strong>Counselor:</strong> {{ $cName }} &nbsp; | &nbsp;
      <strong>Period:</strong> {{ $label }}
    </div>
    <div class="meta-line">
      <strong>Generated:</strong> {{ $generatedAt }}
    </div>
  </div>

  {{-- Presenting problem summary chips --}}
  @if(count($dxItems))
    <div style="margin: 4px 0 10px;">
      @foreach($dxItems as $label => $cnt)
        <span class="chip">{{ $label }} • {{ (int)$cnt }}</span>
      @endforeach
    </div>
  @endif

  <div class="section-title">Appointments and presenting problems</div>

  {{-- Table --}}
  <div class="card">
    <table>
      <thead>
        <tr>
          <th style="width:34%">Student</th>
          <th style="width:24%">Date</th>
          <th style="width:42%">Presenting Problem</th>
        </tr>
      </thead>
      <tbody>
        @forelse($students as $row)
          @php
            $date = $row->note_date
              ? \Carbon\Carbon::parse($row->note_date)->format('M d, Y')
              : '—';
          @endphp
          <tr>
            <td class="wrap"><strong>{{ $row->student_name ?? '—' }}</strong></td>
            <td>{{ $date }}</td>
            <td class="wrap">{{ $row->presenting_problem ? trim($row->presenting_problem) : '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="3" style="text-align:center; padding:18px 0; color:#6b7280;">
              No appointments this month.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="small footer-note">
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
