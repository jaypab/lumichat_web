{{-- resources/views/admin/counselor-logs/pdf.blade.php --}}
@php
  // $rows: collection of rows (counselor_name, month_year, students_list, students_count, common_dx)
  // $cName, $mName, $yName, $generatedAt, $logoData (base64 image, optional)
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Counselor Logs (PDF)</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans; }
    body { margin: 20mm 16mm; font-size: 12px; line-height: 1.45; color: #111827; }

    /* Brand header */
    .brandbar { margin: 0 0 14px; text-align: left; }
    .brand { display: inline-block; }
    .brand-logo  { width: 50px; height: 50px; border-radius: 50%; vertical-align: middle; }
    .brand-title { display: inline-block; vertical-align: middle; margin-left: 10px;
                   font: 700 18px/1 DejaVu Sans; white-space: nowrap; }

    h1 { margin: 0 0 12px; font-size: 22px; letter-spacing: .2px; }
    .meta { font-size: 11.5px; color: #6b7280; margin-bottom: 16px; }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead { background: #f1f5f9; color: #334155; }
    th, td { padding: 11px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
    th { font-weight: 700; }
    th:last-child, td:last-child { text-align: right; }
    thead { display: table-header-group; }   /* repeat header on new pages */
    tr { page-break-inside: avoid; }
    tbody tr:nth-child(even) { background: #fbfbfd; }

    .small { font-size: 10.5px; color: #6b7280; }
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
    <strong>Counselor:</strong> {{ $cName }} &nbsp; | &nbsp;
    <strong>Month:</strong> {{ $mName }} &nbsp; | &nbsp;
    <strong>Year:</strong> {{ $yName }} &nbsp; • &nbsp;
    <span>generated: {{ $generatedAt }}</span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:15%">Counselor</th>
        <th style="width:18%">Month / Year</th>
        <th style="width:30%">Students handled</th>
        <th style="width:25%; text-align:right;">Common diagnosis</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td><strong>{{ $r->counselor_name }}</strong></td>
          <td>{{ $r->month_year }}</td>
          <td>
            @if(!empty($r->students_list))
              {{ str_replace(' | ', ', ', $r->students_list) }}
              <span class="small"> &nbsp; ({{ (int)($r->students_count ?? 0) }} unique)</span>
            @else
              <span class="small">—</span>
            @endif
          </td>
          <td style="text-align:right;">{{ $r->common_dx ?: '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="small" style="text-align:center; padding:16px 0;">No records found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
