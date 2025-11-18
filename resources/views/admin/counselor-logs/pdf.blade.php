{{-- resources/views/admin/counselor-logs/pdf.blade.php --}}
@php
  // $rows: collection of rows (counselor_name, month_num, year_num, students_list, students_count, dx_list)
  // $cName, $mName, $yName, $generatedAt, $logoData (base64 image, optional)
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Counselor Logs (PDF)</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans; }

    body {
      margin: 20mm 16mm 22mm;
      font-size: 12px;
      line-height: 1.45;
      color: #111827;
    }

    /* Brand header */
    .brandbar {
      margin: 0 0 10px;
      padding-bottom: 6px;
      border-bottom: 1px solid #e5e7eb;
    }
    .brand { display: inline-block; }
    .brand-logo  { width: 50px; height: 50px; border-radius: 50%; vertical-align: middle; }
    .brand-title {
      display: inline-block;
      vertical-align: middle;
      margin-left: 10px;
      font: 700 18px/1 DejaVu Sans;
      white-space: nowrap;
    }

    h1 {
      margin: 10px 0 4px;
      font-size: 22px;
      letter-spacing: .2px;
    }

    .meta {
      font-size: 11.5px;
      color: #4b5563;
      margin-bottom: 12px;
    }
    .meta-line {
      margin-bottom: 2px;
    }

    /* Card wrapper for table to feel like a block */
    .card {
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 10px 10px 6px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
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
    thead { display: table-header-group; }   /* repeat header on new pages */
    tr { page-break-inside: avoid; }
    tbody tr:nth-child(even) { background: #fbfbfd; }

    /* Ensure long lists wrap nicely in PDF */
    .wrap {
      word-wrap: break-word;
      word-break: break-word;
      white-space: normal;
    }

    .small { font-size: 10.5px; color: #6b7280; }
    .footer-note {
      margin-top: 10px;
      padding-top: 6px;
      border-top: 1px solid #e5e7eb;
    }
  </style>
</head>
<body>

  {{-- Brand header --}}
  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData))
        <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
      @endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>Counselor Logs</h1>
  <div class="meta">
    <div class="meta-line">
      <strong>Counselor filter:</strong> {{ $cName }} &nbsp; | &nbsp;
      <strong>Month:</strong> {{ $mName }} &nbsp; | &nbsp;
      <strong>Year:</strong> {{ $yName }}
    </div>
    <div class="meta-line">
      <strong>Generated:</strong> {{ $generatedAt }}
    </div>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th style="width:18%">Counselor</th>
          <th style="width:20%">Month / Year</th>
          <th style="width:32%">Students handled</th>
          <th style="width:30%">Presenting problems</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          @php
            // Build label from year_num + month_num; fallback if old month_year is present
            try {
                if (!empty($r->year_num) && !empty($r->month_num)) {
                    $monthLabel = \Carbon\Carbon::create($r->year_num, $r->month_num, 1)->format('F Y');
                } else {
                    $monthLabel = $r->month_year ?? '';
                }
            } catch (\Exception $e) {
                $monthLabel = $r->month_year ?? '';
            }
          @endphp
          <tr>
            <td><strong>{{ $r->counselor_name }}</strong></td>
            <td>{{ $monthLabel }}</td>
            <td class="wrap">
              @if(!empty($r->students_list))
                {{ str_replace(' | ', ', ', $r->students_list) }}
              @else
                <span class="small">—</span>
              @endif
            </td>
            <td class="wrap">
              @if(!empty($r->dx_list))
                {{-- dx_list now contains DISTINCT presenting_problem values separated by "||" --}}
                {{ str_replace('||', ', ', $r->dx_list) }}
              @else
                <span class="small">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="small" style="text-align:center; padding:16px 0;">
              No records found for this filter.
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
