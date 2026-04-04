{{-- resources/views/Counselor_Interface/appointments/pdf-case-note.blade.php --}}
@php
  use Carbon\Carbon;

  $dt        = Carbon::parse($appointment->scheduled_at);
  $generated = $generatedAt ?? now()->format('Y-m-d H:i');

  // status → chip colors (same palette as admin)
  $status = strtolower($appointment->status ?? 'pending');
  $chipMap = [
    'pending'   => ['bg' => '#FEF3C7', 'bd' => '#FDE68A', 'fg' => '#92400E'], // amber
    'confirmed' => ['bg' => '#DBEAFE', 'bd' => '#BFDBFE', 'fg' => '#1E3A8A'], // blue
    'canceled'  => ['bg' => '#FEE2E2', 'bd' => '#FECACA', 'fg' => '#991B1B'], // rose
    'completed' => ['bg' => '#DCFCE7', 'bd' => '#BBF7D0', 'fg' => '#166534'], // emerald
    'no_show'   => ['bg' => '#FEE2E2', 'bd' => '#FECACA', 'fg' => '#991B1B'], // treat as rose
  ];
  $chip = $chipMap[$status] ?? $chipMap['pending'];

  $statusLabel = $status === 'no_show' ? 'No Show' : ucfirst($status);
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Case Note #{{ $appointment->id }}</title>
  <style>
    /* Dompdf-safe + embed DejaVu to avoid missing-font issues */
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

    *{ box-sizing:border-box; }
    body{
      margin: 18mm 14mm 22mm;
      font-family:'DejaVu Sans', sans-serif;
      color:#111827;
      font-size:12.5px;
      line-height:1.45;
    }

    /* Brand */
    .brandbar{ margin:0 0 8px; }
    .brand-title{
      display:inline-block;
      vertical-align:middle;
      margin-left:10px;
      font:700 18px/1 'DejaVu Sans', sans-serif;
    }

    /* Top gradient accent */
    .topbar{
      height:4px;
      background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef);
      border-radius:10px;
      margin:6px 0 12px;
    }

    /* Headings + meta */
    h1{
      margin:6px 0 8px;
      font-size:20px;
      font-weight: 700;
    }
    .meta-row{
      display:block;
      width:100%;
      color:#6b7280;
      font-size:11px;
    }
    .muted{ color:#6b7280; }

    /* Chip */
    .chip{
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-weight:700;
      font-size:10px;
      border:1px solid transparent;
    }

    /* Sealed Watermark */
    #watermark {
      position: fixed;
      top: 50%;
      left: 50%;
      width: 650px;
      height: 650px;
      margin-top: -325px;
      margin-left: -325px;
      z-index: -1000;
      opacity: 0.12;
    }

    /* Cards & tables */
    .cards-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .cards-table td { vertical-align: top; width: 50%; padding: 0 4px; }
    .card{
      border:1px solid #e5e7eb;
      border-radius:10px;
      padding:10px;
      min-height: 120px;
    }
    .card h2{
      margin:0 0 6px;
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.04em;
      color:#475569;
    }

    .info{ width:100%; border-collapse:collapse; }
    .info td{ padding:1px 0; vertical-align:top; }

    .section{ margin-bottom:6px; }

    .kv b{
      display:block;
      font-size:10px;
      color:#475569;
      text-transform:uppercase;
      margin-bottom:1px;
    }
    .kv span{ font-size:12px; }

    /* Case Note Sections */
    .box{
      border:1px solid #e5e7eb;
      border-radius:10px;
      padding:10px;
      margin-top:10px;
      background: #fafafa;
    }
    .box h2{
      margin:0 0 4px;
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.04em;
      color:#475569;
    }
    .box-content {
      font-size: 12px;
      white-space: pre-wrap;
      color: #334155;
    }

    .small{
      font-size:10px;
      color:#64748b;
    }

    /* Sealed Watermark */
    #watermark {
      position: fixed;
      top: 50%;
      left: 50%;
      width: 500px;
      height: 500px;
      margin-top: -250px;
      margin-left: -250px;
      z-index: -1000;
      opacity: 0.12;
    }
  </style>
</head>
<body>

  {{-- Sealed Watermark --}}
  @if(!empty($guidanceLogoData))
    <div id="watermark">
      <img src="{{ $guidanceLogoData }}" style="width: 100%;">
    </div>
  @endif

  {{-- Brand --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="40" height="40"
           style="width:40px;height:40px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>

  {{-- Accent bar --}}
  <div class="topbar"></div>

  {{-- Title --}}
  <h1>Counselor Case Note: Appointment #{{ $appointment->id }}</h1>

  {{-- Meta row --}}
  <div class="meta-row">
    <div style="float: left;">
      Status:
      <span class="chip"
            style="background:{{ $chip['bg'] }}; border-color:{{ $chip['bd'] }}; color:{{ $chip['fg'] }};">
        {{ strtoupper($statusLabel) }}
      </span>
      &nbsp;•&nbsp;
      Scheduled on:
      <strong>{{ $dt->format('F d, Y · g:i A') }}</strong>
    </div>
    <div class="muted" style="float: right;">
      Generated: {{ $generated }}
    </div>
    <div style="clear: both;"></div>
  </div>

  {{-- Participants / Timing --}}
  <table class="cards-table">
    <tr>
      <td>
        <div class="card">
          <h2>Student Details</h2>
          <div class="section">
            <table class="info">
              <tr>
                <td><strong>{{ $appointment->student_name }}</strong></td>
              </tr>
              @if(!empty($appointment->student_email))
                <tr><td class="small">{{ $appointment->student_email }}</td></tr>
              @endif
              @if(!empty($appointment->student_program_year))
                <tr><td class="small">{{ $appointment->student_program_year }}</td></tr>
              @endif
            </table>
          </div>
        </div>
      </td>
      <td>
        <div class="card">
          <h2>Session Metadata</h2>
          <div class="kv">
            <b>Note Date</b>
            <span>{{ $note->note_date ? \Carbon\Carbon::parse($note->note_date)->format('F d, Y') : '—' }}</span>
          </div>
          <div class="kv" style="margin-top:6px;">
            <b>Counselor</b>
            <span>{{ $appointment->counselor_name ?: auth()->user()->name }}</span>
          </div>
        </div>
      </td>
    </tr>
  </table>

  {{-- Case Note Sections --}}
  <div class="box">
    <h2>I. Presenting Problem</h2>
    <div class="box-content">{{ $note->presenting_problem }}</div>
  </div>

  <div class="box">
    <h2>II. Observations</h2>
    <div class="box-content">{{ $note->observations }}</div>
  </div>

  <div class="box">
    <h2>III. Interventions / Actions</h2>
    <div class="box-content">{{ $note->interventions }}</div>
  </div>

  <div class="box">
    <h2>IV. Student’s Response</h2>
    <div class="box-content">{{ $note->response }}</div>
  </div>

  <div class="box">
    <h2>V. Plan / Follow-Up</h2>
    <div class="box-content">{{ $note->plan_followup }}</div>
  </div>

  @if($note->emergency_contact_person)
  <div class="box" style="background:#fff1f2; border-color:#fecaca;">
    <h2 style="color:#9f1239;">VI. Emergency Safety Plan</h2>
    <div style="font-size:11px; margin-top:2px;">
      <strong>Contact:</strong> {{ $note->emergency_contact_person }} ({{ $note->emergency_relationship }})<br>
      <strong>Phone:</strong> {{ $note->emergency_contact_no }}<br>
      <strong>Address:</strong> {{ $note->emergency_address }}
    </div>
  </div>
  @endif

  {{-- Footer --}}
  <div class="small" style="margin-top:30px; text-align:center;">
    LumiCHAT • Confidential student support record.
  </div>

  <script type="text/php">
  if (isset($pdf)) {
      $font  = $fontMetrics->get_font("DejaVu Sans", "normal");
      $pdf->page_text(520, 810, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 8, [0,0,0]);
  }
  </script>

</body>
</html>