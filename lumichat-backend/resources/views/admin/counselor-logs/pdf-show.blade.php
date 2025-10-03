{{-- resources/views/admin/counselor-logs/pdf-show.blade.php --}}
@php
  $title = 'Counselor Logs — '.$counselor->full_name.' ('.$label.')';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title }}</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body { margin: 16mm 14mm; font-size: 12.5px; color:#111827; line-height:1.45; }

    /* Brand header (logo + title side-by-side) */
    .brandbar { margin:0 0 10px; text-align:left; }
    .brand { display:inline-block; }
    .brand-logo { width:50px; height:50px; border-radius:50%; vertical-align:middle; }
    .brand-title { display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }

    h1 { margin: 10px 0 6px; font-size: 20px; }
    .meta { font-size: 11px; color:#6b7280; margin-bottom: 12px; }

    /* Chips for diagnosis summary */
    .chip { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; border:1px solid #bae6fd; color:#0369a1; background:#e0f2fe; margin:0 6px 6px 0; }

    table { width:100%; border-collapse: collapse; }
    thead { background:#f1f5f9; color:#334155; }
    th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    th:last-child, td:last-child { text-align: left; }
    thead { display: table-header-group; }  /* repeat header each page */
    tr { page-break-inside: avoid; }

    /* Cards (Dompdf-safe) */
    .card    { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
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
    <strong>Generated:</strong> {{ $generatedAt }}
  </div>

  {{-- Diagnosis summary chips --}}
  @if($dxCounts->count())
    <div style="margin: 4px 0 12px;">
      @foreach($dxCounts as $dx)
        <span class="chip">{{ $dx->diagnosis_result }} • {{ $dx->cnt }}</span>
      @endforeach
    </div>
  @endif

  {{-- Table --}}
  <div class="card" style="margin-top:6px;">
    <table>
      <thead>
        <tr>
          <th style="width:34%">Student</th>
          <th style="width:24%">Scheduled</th>
          <th style="width:42%">Diagnosis / Result</th>
        </tr>
      </thead>
      <tbody>
        @forelse($students as $row)
          <tr>
            <td><strong>{{ $row->student_name ?? '—' }}</strong></td>
            <td>{{ $row->scheduled_at_fmt ?? '—' }}</td>
            <td>{{ $row->diagnosis_result ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="3" style="text-align:center; padding:18px 0; color:#6b7280;">No appointments this month.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

</body>
</html>
