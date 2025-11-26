@php
  use Carbon\Carbon;

  $code = 'WN-'.now()->format('Y').'-'.str_pad($walkin->id, 4, '0', STR_PAD_LEFT);
  $date = $walkin->note_date
          ? Carbon::parse($walkin->note_date)->format('F d, Y')
          : '—';

  $start = $walkin->start_time ? Carbon::parse($walkin->start_time) : null;
  $end   = $walkin->end_time   ? Carbon::parse($walkin->end_time)   : null;

  $appt = ($start && $end)
          ? $start->format('M d, Y · g:i A').' – '.$end->format('g:i A')
          : ($start ? $start->format('M d, Y · g:i A') : '—');

  $duration = null;
  if ($start && $end) {
      $diffMins = $end->diffInMinutes($start);
      $h = intdiv($diffMins, 60);
      $m = $diffMins % 60;
      $duration = ($h ? $h.' hr'.($h>1?'s':'') : '').($h && $m ? ' ' : '').($m ? $m.' min' : '');
  }

  $courseYear = trim(
      ($walkin->course ?? '').($walkin->year_level ? ' - '.$walkin->year_level : '')
  ) ?: '—';

  $generated = $generated ?? now()->format('F d, Y · g:i A');
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $code }}</title>
  <style>
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

    .mt-4{margin-top:4px}.mt-6{margin-top:6px}.mt-8{margin-top:8px}
    .mt-10{margin-top:10px}.mt-12{margin-top:12px}.mt-16{margin-top:16px}

    .brandbar { margin:0 0 8px }
    .brand { display:inline-block; vertical-align:middle }
    .brand-logo { width:28px; height:28px; border-radius:50%; vertical-align:middle }
    .brand-title { display:inline-block; vertical-align:middle; margin-left:8px; font:700 16px/1 "DejaVu Sans", sans-serif }
    .topbar { height:4px; background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef); border-radius:10px; margin:8px 0 14px }

    h1 { margin:0 0 6px; font-size:20px }
    .meta { font-size:11px; color:#6b7280 }

    .card { border:1px solid #e5e7eb; border-radius:12px; background:#fff }
    .card-body { padding:12px 14px }

    table.kv { width:100%; border-collapse:separate; border-spacing:0 10px; table-layout:fixed }
    .k { width:22%; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; }
    .v { width:28%; font-weight:700; word-wrap:break-word }
    .kR{ width:22%; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; padding-left:24px; }
    .vR{ width:28%; font-weight:700; word-wrap:break-word }
    .row { height:22px; vertical-align:middle }

    .section-title { font-size:12px; color:#374151; font-weight:700; margin-bottom:6px; }
    .section-box { border:1px solid #e5e7eb; background:#fafafa; border-radius:10px; padding:10px; }
    .pre { white-space:pre-line; }

    .foot { margin-top:12px; padding-top:10px; border-top:1px solid #e5e7eb; font-size:11px; color:#6b7280 }

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

  <h1>Walk-in Case Note — {{ $code }}</h1>
  <div class="meta">Generated: {{ $generated }}</div>

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
          <td class="v">{{ $walkin->student_name ?? '—' }}</td>
          <td class="kR">Counselor</td>
          <td class="vR">{{ $walkin->counselor_name ?? '—' }}</td>
        </tr>
        <tr class="row">
          <td class="k">Program &amp; Year</td>
          <td class="v">{{ $courseYear }}</td>
          <td class="kR">Session Time</td>
          <td class="vR">
            {{ $appt }}
            @if($duration)
              ({{ $duration }})
            @endif
          </td>
        </tr>
        <tr class="row">
          <td class="k">Brief Reason</td>
          <td class="v">{{ $walkin->reason ?? '—' }}</td>
          <td class="kR"></td>
          <td class="vR"></td>
        </tr>
      </table>
    </div>
  </div>

  {{-- Sections I–V --}}
  <div class="mt-12 avoid-break">
    <div class="section-title">I. Presenting Problem</div>
    <div class="section-box pre">{{ $walkin->presenting_problem ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">II. Observations</div>
    <div class="section-box pre">{{ $walkin->observations ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">III. Interventions / Counselor’s Actions</div>
    <div class="section-box pre">{{ $walkin->interventions ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">IV. Student’s Response / Insight</div>
    <div class="section-box pre">{{ $walkin->response ?? '—' }}</div>
  </div>

  <div class="mt-10 avoid-break">
    <div class="section-title">V. Plan / Follow-Up</div>
    <div class="section-box pre">{{ $walkin->plan_followup ?? '—' }}</div>
  </div>

  {{-- VI. Emergency Safety Plan --}}
  <div class="mt-10 avoid-break">
    <div class="section-title">VI. Emergency Safety Plan</div>
    <div class="section-box">
      <table class="kv">
        <tr class="row">
          <td class="k">Contact Person</td>
          <td class="v">{{ $walkin->emergency_contact_person ?? '—' }}</td>
          <td class="kR">Relationship</td>
          <td class="vR">{{ $walkin->emergency_relationship ?? '—' }}</td>
        </tr>
        <tr class="row">
          <td class="k">Contact No.</td>
          <td class="v">{{ $walkin->emergency_contact_no ?? '—' }}</td>
          <td class="kR">Address</td>
          <td class="vR">{{ $walkin->emergency_address ?? '—' }}</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="foot">
    LumiCHAT • Tagoloan Community College — Confidential walk-in case record.
  </div>

  {{-- Page numbers --}}
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
