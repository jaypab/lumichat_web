{{-- resources/views/Counselor_Interface/appointments/pdf-appointment.blade.php --}}
@php
  use Carbon\Carbon;

  $dt        = Carbon::parse($appointment->scheduled_at);
  $bookedAt  = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;
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
  <title>Appointment #{{ $appointment->id }}</title>
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
      font-size:22px;
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
      padding:3px 9px;
      border-radius:999px;
      font-weight:700;
      font-size:10.5px;
      border:1px solid transparent;
    }

    /* Cards & tables */
    .cards-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .cards-table td { vertical-align: top; width: 50%; padding: 0 6px; }
    .card{
      border:1px solid #e5e7eb;
      border-radius:12px;
      padding:12px;
      min-height: 140px;
    }
    .card h2{
      margin:0 0 8px;
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.04em;
      color:#475569;
    }

    .info{ width:100%; border-collapse:collapse; }
    .info td{ padding:2px 0; vertical-align:top; }

    .section{ margin-bottom:8px; }

    .kv b{
      display:block;
      font-size:11px;
      color:#475569;
      text-transform:uppercase;
      margin-bottom:2px;
    }
    .kv span{ font-size:13px; }

    /* Final Diagnosis/Notes box */
    .box{
      border:1px solid #e5e7eb;
      border-radius:12px;
      padding:12px;
      margin-top:14px;
    }
    .box h2{
      margin:0 0 6px;
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.04em;
      color:#475569;
    }
    .small{
      font-size:11px;
      color:#64748b;
    }

    .spacer{ height:10px; }

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
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50"
           style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>

  {{-- Accent bar --}}
  <div class="topbar"></div>

  {{-- Title --}}
  <h1>Appointment Summary #{{ $appointment->id }}</h1>

  {{-- Meta row: status chip + created + generated --}}
  <div class="meta-row">
    <div style="float: left;">
      Status:
      <span class="chip"
            style="background:{{ $chip['bg'] }}; border-color:{{ $chip['bd'] }}; color:{{ $chip['fg'] }};">
        {{ strtoupper($statusLabel) }}
      </span>
      &nbsp;•&nbsp;
      Booked on:
      <strong>{{ $bookedAt ? $bookedAt->format('F d, Y · g:i A') : '—' }}</strong>
    </div>
    <div class="muted" style="float: right;">
      Generated: {{ $generated }}
    </div>
    <div style="clear: both;"></div>
  </div>

  <div class="spacer"></div>

  {{-- Two columns via table (layout-neutral for Dompdf) --}}
  <table class="cards-table">
    <tr>
      <td>
        <div class="card">
          <h2>Participants</h2>

          {{-- Student --}}
          <div class="section">
            <table class="info">
              <tr>
                <td class="small" style="text-transform:uppercase;">Student</td>
              </tr>
              <tr>
                <td><strong>{{ $appointment->student_name }}</strong></td>
              </tr>
              @if(!empty($appointment->student_email))
                <tr>
                  <td class="small">{{ $appointment->student_email }}</td>
                </tr>
              @endif
              @if(!empty($appointment->student_id))
                <tr>
                  <td class="small">Student ID: {{ $appointment->student_id }}</td>
                </tr>
              @endif
              @if(!empty($appointment->student_course) || !empty($appointment->student_program_year))
                <tr>
                  <td class="small">
                    {{ $appointment->student_course ?? ($appointment->student_program_year ?? '') }}
                  </td>
                </tr>
              @endif
            </table>
          </div>

          {{-- Counselor --}}
          <div class="section">
            <table class="info">
              <tr>
                <td class="small" style="text-transform:uppercase;">Counselor</td>
              </tr>
              <tr>
                <td><strong>{{ $appointment->counselor_name ?: '—' }}</strong></td>
              </tr>
            </table>
          </div>
        </div>
      </td>

      <td>
        <div class="card">
          <h2>Appointment Timing</h2>

          <div class="kv">
            <b>Scheduled For</b>
            <span>{{ $dt->format('F d, Y · g:i A') }}</span>
          </div>

          @if(!empty($appointment->location))
            <div class="kv" style="margin-top:10px;">
              <b>Location</b>
              <span>{{ $appointment->location }}</span>
            </div>
          @endif

          <div class="kv" style="margin-top:10px;">
            <b>Source</b>
            <span>{{ strtoupper(str_replace('_',' ',$appointment->appointment_source ?? 'ONLINE')) }}</span>
          </div>
        </div>
      </td>
    </tr>
  </table>

  @if(!empty($appointment->note))
    <div class="box">
      <h2>Student’s Note/Concern</h2>
      <div style="font-size:13px; margin-top:4px;">{!! nl2br(e($appointment->note)) !!}</div>
    </div>
  @endif

  {{-- Footer --}}
  <div class="small" style="margin-top:40px; text-align:center;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>

  {{-- Footer page numbers --}}
  <script type="text/php">
  if (isset($pdf)) {
      $font  = $fontMetrics->get_font("DejaVu Sans", "normal");
      $size  = 9;
      $w     = $pdf->get_width();
      $h     = $pdf->get_height();
      $text  = "Page {PAGE_NUM} of {PAGE_COUNT}";
      $x     = $w - 100;
      $y     = $h - 35;
      $pdf->page_text($x, $y, $text, $font, $size, [0,0,0]);
  }
  </script>

</body>
</html>