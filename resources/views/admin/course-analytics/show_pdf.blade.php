<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title }}</title>
  <style>
    *{ box-sizing:border-box }

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

    body{ font-family: "DejaVu Sans", sans-serif; margin:14mm; font-size:12.5px; color:#111827; line-height:1.45 }

    /* Brand (same as session_pdf) */
    .brandbar{ margin:0 0 6px }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 "DejaVu Sans", sans-serif }

    /* Header */
    h1{ margin:6px 0 8px; font-size:22px }
    .meta{ font-size:11px; color:#6b7280; margin:0 0 10px; }
    .meta-right{ float:right; }

    /* Table */
    table{ width:100%; border-collapse:collapse }
    thead{ background:#f1f5f9; color:#334155 }
    th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left }
    tr{ page-break-inside:avoid }

    .small{ font-size:10px; color:#6b7280 }
  </style>
</head>
<body>

  {{-- Brand (fixed size so it never grows) --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50" style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
    <div class="meta"><span class="meta-right">Generated: {{ $generatedAt }}</span></div>
  </div>

  <h1>Course Analytics</h1>

  {{-- Summary --}}
  <table style="margin-bottom:12px;">
    <thead>
      <tr>
        <th style="width:40%;">Course</th>
        <th style="width:30%;">Year Level</th>
        <th style="width:30%;">No. of Students</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>{{ $course->course ?? '—' }}</strong></td>
        <td>{{ $course->year_level ?? '—' }}</td>
        <td>{{ $course->student_count ?? 0 }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Breakdown --}}
  <table>
    <thead>
      <tr>
        <th>Common Diagnosis Breakdown</th>
        <th style="width:120px; text-align:right;">Count</th>
      </tr>
    </thead>
    <tbody>
      @php $items = $course->breakdown ?? []; @endphp
      @forelse($items as $row)
        <tr>
          <td>{{ $row['label'] ?? '—' }}</td>
          <td style="text-align:right;">{{ $row['count'] ?? 0 }}</td>
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
</body>
</html>
