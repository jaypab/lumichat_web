<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title ?? 'Case Form Summary' }}</title>
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
      src:url('{{ public_path('fonts/DejaVuSans-Bold.ttf') }}') format('truetype');
      font-weight:700; font-style:normal;
    }

    body{ font-family:'DejaVu Sans',sans-serif; margin:16mm 14mm 20mm; font-size:12.5px; color:#0f172a; line-height:1.45 }

    /* Brand / header */
    .brandbar{ margin:0 0 6px }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 'DejaVu Sans',sans-serif }
    .topbar{ height:4px; background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef); border-radius:999px; margin:8px 0 12px }

    h1{ margin:6px 0 8px; font-size:20px }
    .meta{ font-size:11px; color:#6b7280; margin:0 0 12px }
    .muted{ color:#6b7280 }

    /* Table */
    table{ width:100%; border-collapse:collapse; table-layout:fixed }
    thead{ background:#f1f5f9; color:#334155 }
    th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top }
    th{ font-weight:700; font-size:12px; letter-spacing:.02em; text-transform:uppercase }
    .idcol{ width:16% } .studcol{ width:26% } .councol{ width:22% } .ppcol{ width:26% } .datecol{ width:10% }

    /* Watermark */
    .wm{ position:fixed; inset:0; opacity:.05; font-size:120px; font-weight:700; color:#312e81; display:flex; align-items:center; justify-content:center; z-index:-1 }
  </style>
</head>
<body>
  <div class="wm">LumiCHAT</div>

  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="46" height="46" style="width:46px;height:46px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>
  <div class="topbar"></div>

  <h1>Case Form Summary</h1>
  <div class="meta">
    Generated: {{ $generated }}
    • Range:
    @php $dk=$filters['date']??'all'; @endphp
    @if($dk==='all') All Dates
    @elseif($dk==='range') {{ $filters['from'] ?? '—' }} → {{ $filters['to'] ?? '—' }}
    @elseif($dk==='today') Today
    @elseif($dk==='last7') Last 7 days
    @elseif($dk==='last30') Last 30 days
    @elseif($dk==='month') This Month
    @else {{ $dk }}
    @endif
    @if(!empty($filters['q'])) • Search: “{{ $filters['q'] }}” @endif
  </div>

  <table>
    <thead>
      <tr>
        <th class="idcol">ID</th>
        <th class="studcol">Student</th>
        <th class="councol">Counselor</th>
        <th class="ppcol">Presenting Problem (snippet)</th>
        <th class="datecol">Date</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        @php
          // Use the NOTE'S YEAR (or created_at) for the code, not now()
          $year = $r->note_date
                    ? \Carbon\Carbon::parse($r->note_date)->format('Y')
                    : (\Carbon\Carbon::parse($r->created_at)->format('Y') ?? now()->format('Y'));
          $code = 'CN-'.$year.'-'.str_pad($r->id,4,'0',STR_PAD_LEFT);
          $date = $r->note_date ? \Carbon\Carbon::parse($r->note_date)->format('M d, Y') : '—';
          $pp   = \Illuminate\Support\Str::limit((string)($r->presenting_problem ?? ''), 120);
        @endphp
        <tr>
          <td><strong>{{ $code }}</strong></td>
          <td>{{ $r->student_name_display ?? $r->student_name ?? '—' }}</td>
          <td>{{ $r->counselor_name ?? '—' }}</td>
          <td>{{ $pp }}</td>
          <td>{{ $date }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="muted">No records.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="muted" style="margin-top:12px;">LumiCHAT • Tagoloan Community College — Confidential student support record.</div>

  <!-- Page numbers -->
  <script type="text/php">
  if (isset($pdf)) {
      $font = $fontMetrics->get_font("DejaVu Sans","normal");
      $size = 9;
      $w = $pdf->get_width(); $h = $pdf->get_height();
      $pdf->page_text($w-72, $h-28, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, $size, [0,0,0]);
  }
  </script>
</body>
</html>
