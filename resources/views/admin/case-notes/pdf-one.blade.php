@php
  $code = 'CN-'.now()->format('Y').'-'.str_pad($note->id, 4, '0', STR_PAD_LEFT);
  $date = $note->note_date ? \Carbon\Carbon::parse($note->note_date)->format('F d, Y') : '—';
  $appt = $note->scheduled_at ? \Carbon\Carbon::parse($note->scheduled_at)->format('M d, Y · g:i A') : '—';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $code }}</title>
  <style>
    /* ---------- Base ---------- */
    * { box-sizing: border-box; }
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
    body {
      font-family: "DejaVu Sans", Helvetica, Arial, sans-serif;
      margin: 16mm 14mm 20mm; color:#111827; font-size:12px; line-height:1.45;
    }

    /* spacing helpers */
    .mt-4{margin-top:4px}.mt-6{margin-top:6px}.mt-8{margin-top:8px}
    .mt-10{margin-top:10px}.mt-12{margin-top:12px}.mt-16{margin-top:16px}

    /* ---------- Brand ---------- */
    .brandbar { margin:0 0 8px }
    .brand { display:inline-block; vertical-align:middle }
    .brand-logo { width:28px; height:28px; border-radius:50%; vertical-align:middle }
    .brand-title { display:inline-block; vertical-align:middle; margin-left:8px; font:700 16px/1 "DejaVu Sans", sans-serif }
    .topbar { height:4px; background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef); border-radius:10px; margin:8px 0 14px }

    /* ---------- Headings & meta ---------- */
    h1 { margin:0 0 6px; font-size:20px }
    .meta { font-size:11px; color:#6b7280 }

    /* ---------- Cards ---------- */
    .card { border:1px solid #e5e7eb; border-radius:12px; background:#fff }
    .card-body { padding:12px 14px }

    /* ---------- Spec grid (table layout = Dompdf safe) ---------- */
    table.kv { width:100%; border-collapse:separate; border-spacing:0 10px; table-layout:fixed }
    .k { width:22%; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; }
    .v { width:28%; font-weight:700; word-wrap:break-word }
    .kR{ width:22%; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; padding-left:24px; }
    .vR{ width:28%; font-weight:700; word-wrap:break-word }
    .row { height:22px; vertical-align:middle }

    /* ---------- Section blocks ---------- */
    .section-title { font-size:12px; color:#374151; font-weight:700; margin-bottom:6px; }
    .section-box { border:1px solid #e5e7eb; background:#fafafa; border-radius:10px; padding:10px; }
    .pre { white-space:pre-line; }

    /* ---------- Footer ---------- */
    .foot { margin-top:12px; padding-top:10px; border-top:1px solid #e5e7eb; font-size:11px; color:#6b7280 }

    /* avoid row breaks inside blocks */
    .avoid-break { page-break-inside: avoid; }
  </style>
</head>
<body>

  {{-- Brand --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>
  <div class="topbar"></div>

  {{-- Title & meta --}}
  <h1>Case Note — {{ $code }}</h1>
  <div class="meta">Generated: {{ $generated ?? now()->format('F d, Y · g:i A') }}</div>

  {{-- Details --}}
  <div class="card mt-10 avoid-break">
    <div class="card-body">
      <table class="kv">
        <tr class="row">
          <td class="k">Case Note ID</td>
          <td class="v">{{ $code }}</td>
          <td class="kR">Case Note Date</td>
          <td class="vR">{{ $date }}</td>
        </tr>
        <tr class="row">
          <td class="k">Student</td>
          <td class="v">{{ $note->student_display_name ?? $note->student_name ?? '—' }}</td>
          <td class="kR">Counselor</td>
          <td class="vR">{{ $note->counselor_name ?? '—' }}</td>
        </tr>
        <tr class="row">
          <td class="k">Appointment</td>
          <td class="v">{{ $appt }}</td>
          <td class="kR">Appt. Status</td>
          <td class="vR">{{ ucfirst($note->appt_status ?? '—') }}</td>
        </tr>
      </table>
    </div>
  </div>

  {{-- Sections I–V --}}
  <div class="mt-12 avoid-break">
    <div class="section-title">I. Presenting Problem</div>
    <div class="section-box pre">{{ $note->presenting_problem ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">II. Observations</div>
    <div class="section-box pre">{{ $note->observations ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">III. Interventions / Counselor’s Actions</div>
    <div class="section-box pre">{{ $note->interventions ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">IV. Student’s Response / Insight</div>
    <div class="section-box pre">{{ $note->response ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">V. Plan / Follow-Up</div>
    <div class="section-box pre">{{ $note->plan_followup ?? '—' }}</div>
  </div>

  {{-- Footer --}}
  <div class="foot">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>

  {{-- Page numbers --}}
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
