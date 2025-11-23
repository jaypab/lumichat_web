<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Case Note – Appointment #{{ $appointment->id }}</title>
  <style>
    * { box-sizing: border-box; font-family: Helvetica, Arial, sans-serif; }
    body { margin: 16mm 14mm 22mm; font-size: 12px; color: #111827; }

    /* Common UI */
    .card { border:1px solid #e5e7eb; border-radius:8px; padding:10px; }
    .row { width:100%; border-collapse: separate; border-spacing: 12px 0; }
    .row td { vertical-align: top; width: 50%; }
    .kv b { display:block; font-size: 11px; color:#475569; text-transform:uppercase; margin-bottom:2px; }
    .kv span { font-size: 13px; }
    .spacer { height: 12px; }

    /* Sections */
    h2 { margin: 12px 0 6px; font-size: 14px; }
    .box { border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-bottom:10px; }
    .pre { white-space: pre-wrap; }

    /* Masthead */
    .mast { border:1px solid #e5e7eb; border-radius:10px; padding:14px; background:#f8fafc; margin-bottom:12px; }
    .mast-row { width:100%; border-collapse:collapse; }
    .mast-row td { vertical-align:middle; }
    .brand-pack { border-collapse:collapse; }
    .brand-pack td { vertical-align:middle; }

    .brand-logo { width:50px; height:50px; border-radius:50%; }
    .brand-fallback {
      width:50px; height:50px; border-radius:50%;
      background:#e5e7eb; border:1px solid #cbd5e1;
    }

    .title { font-weight:700; font-size:18px; line-height:1; }
    .subtitle { margin-top:2px; font-size:16px; font-weight:700; }
    .text-right { text-align:right; }
    .meta-sm { font-size:11px; color:#6b7280; }
    .pill {
      display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700;
      color:#111827; border:1px solid #e5e7eb; background:#fff;
    }
  </style>
</head>
<body>

@php
  $status = strtolower((string)$appointment->status);
  $pillBg = match($status) {
    'completed' => '#ecfdf5', 'confirmed' => '#eff6ff', 'pending' => '#fffbeb',
    'no_show'   => '#fef2f2', 'ongoing'  => '#eef2ff', default => '#f8fafc',
  };
  $pillBd = match($status) {
    'completed' => '#a7f3d0', 'confirmed' => '#bfdbfe', 'pending' => '#fde68a',
    'no_show'   => '#fecaca', 'ongoing'  => '#c7d2fe', default => '#e5e7eb',
  };
  $pillTx = match($status) {
    'completed' => '#065f46', 'confirmed' => '#1e3a8a', 'pending' => '#92400e',
    'no_show'   => '#7f1d1d', 'ongoing'  => '#3730a3', default => '#111827',
  };
@endphp

{{-- Masthead with logo to the LEFT of the title --}}
<div class="mast">
  <table class="mast-row" cellspacing="0" cellpadding="0">
    <tr>
      <td>
        <table class="brand-pack" cellspacing="0" cellpadding="0">
          <tr>
            <td>
              @if(!empty($logoData))
                <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT Logo">
              @else
                <div class="brand-fallback"></div>
              @endif
            </td>
            <td style="padding-left:10px;">
              <div class="title">LumiCHAT</div>
              <div class="subtitle">Case Note – Appointment #{{ $appointment->id }}</div>
            </td>
          </tr>
        </table>
      </td>
      <td class="text-right">
        <div class="meta-sm">Generated: {{ $generatedAt }}</div>
        <div class="pill" style="margin-top:6px; background:{{ $pillBg }}; border-color:{{ $pillBd }}; color:{{ $pillTx }};">
          {{ $status === 'no_show' ? 'No Show' : ucfirst($status) }}
        </div>
      </td>
    </tr>
  </table>
</div>

<table class="row">
  <tr>
    <td>
      <div class="card">
        <div class="kv">
          <b>Student Name</b>
          <span>{{ $note->student_name ?: '—' }}</span>
        </div>
        <br>
         <div class="kv">
      <b>Course / Year</b>
      <span>{{ $appointment->student_program_year ?: ($note->program_year ?: '—') }}</span>
    </div>
    <br>
        <div class="kv">
          <b>Email</b>
          <span>{{ $appointment->student_email ?: '—' }}</span>
        </div>
        <br>
        <div class="kv">
          <b>Address</b>
          <span>{{ $note->address ?: '—' }}</span>
        </div>
      </div>
    </td>

    <td>
      <div class="card">
        <div class="kv"><b>Case Note Date</b>
          <span>{{ $note->note_date ? \Carbon\Carbon::parse($note->note_date)->format('F d, Y') : '—' }}</span>
        </div>
        <br>
        <div class="kv"><b>Scheduled For</b>
          <span>{{ \Carbon\Carbon::parse($appointment->scheduled_at)->format('F d, Y · g:i A') }}</span>
        </div>
        <br>
        <div class="kv"><b>Status</b><span>{{ ucfirst($appointment->status) }}</span></div>
      </div>
    </td>
  </tr>
</table>

<div class="spacer"></div>

<div class="box">
  <h2>I. Presenting Problem</h2>
  <div class="pre">{{ $note->presenting_problem }}</div>
</div>

<div class="box">
  <h2>II. Observations</h2>
  <div class="pre">{{ $note->observations }}</div>
</div>

<div class="box">
  <h2>III. Interventions / Counselor’s Actions</h2>
  <div class="pre">{{ $note->interventions }}</div>
</div>

<div class="box">
  <h2>IV. Student’s Response / Insight</h2>
  <div class="pre">{{ $note->response }}</div>
</div>

<div class="box">
  <h2>V. Plan / Follow-Up</h2>
  <div class="pre">{{ $note->plan_followup }}</div>
</div>

<div class="box">
  <h2>VI. Emergency Safety Plan (Optional)</h2>
  <table class="row" style="border-spacing: 8px 0;">
    <tr>
      <td><div class="kv"><b>Contact Person</b><span>{{ $note->emergency_contact_person ?: '—' }}</span></div></td>
      <td><div class="kv"><b>Relationship</b><span>{{ $note->emergency_relationship ?: '—' }}</span></div></td>
    </tr>
    <tr>
      <td><div class="kv"><b>Contact No.</b><span>{{ $note->emergency_contact_no ?: '—' }}</span></div></td>
      <td><div class="kv"><b>Address</b><span>{{ $note->emergency_address ?: '—' }}</span></div></td>
    </tr>
  </table>
</div>

{{-- Page numbers (keep Helvetica as you requested) --}}
<script type="text/php">
  if (isset($pdf)) {
      $font  = $fontMetrics->get_font("Helvetica", "normal");
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
