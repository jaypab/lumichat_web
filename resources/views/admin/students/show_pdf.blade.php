{{-- resources/views/admin/appointments/pdf-show.blade.php --}}
@php
  use Carbon\Carbon;

  $dt        = Carbon::parse($appointment->scheduled_at);
  $bookedAt  = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;
  $generated = $generatedAt ?? now()->format('Y-m-d H:i');

  // status → chip colors (same palette as app)
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

  // helper flag: at least one case-note field filled?
  $hasCaseNoteContent = isset($caseNote) && $caseNote && (
      !empty($caseNote->presenting_concern ?? null) ||
      !empty($caseNote->session_summary ?? null) ||
      !empty($caseNote->assessment ?? null) ||
      !empty($caseNote->intervention ?? null) ||
      !empty($caseNote->plan ?? null) ||
      !empty($caseNote->risk_level ?? null) ||
      !empty($caseNote->notes ?? null)
  );
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Appointment #{{ $appointment->id }}</title>
  <style>
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

    .brandbar{ margin:0 0 8px; }
    .brand-title{
      display:inline-block;
      vertical-align:middle;
      margin-left:10px;
      font:700 18px/1 'DejaVu Sans', sans-serif;
    }

    .topbar{
      height:4px;
      background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef);
      border-radius:10px;
      margin:6px 0 12px;
    }

    h1{
      margin:6px 0 8px;
      font-size:22px;
    }
    .meta-row{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      color:#6b7280;
      font-size:11px;
    }
    .muted{ color:#6b7280; }

    .chip{
      display:inline-block;
      padding:3px 9px;
      border-radius:999px;
      font-weight:700;
      font-size:10.5px;
      border:1px solid transparent;
    }

    .cards{
      display:flex;
      gap:12px;
      margin-top:10px;
    }
    .card{
      flex:1 1 0;
      border:1px solid #e5e7eb;
      border-radius:12px;
      padding:12px;
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
  </style>
</head>
<body>

  {{-- Brand --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50"
           style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>

  <div class="topbar"></div>

  <h1>Appointment #{{ $appointment->id }}</h1>

  <div class="meta-row">
    <div>
      Status:
      <span class="chip"
            style="background:{{ $chip['bg'] }}; border-color:{{ $chip['bd'] }}; color:{{ $chip['fg'] }};">
        {{ $statusLabel }}
      </span>
      &nbsp;•&nbsp;
      Created on:
      <strong>{{ $bookedAt ? $bookedAt->format('F d, Y · g:i A') : '—' }}</strong>
    </div>
    <div class="muted">
      Generated: {{ $generated }}
    </div>
  </div>

  <div class="spacer"></div>

  {{-- Participants / Timing --}}
  <div class="cards">
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
          @if(!empty($appointment->student_course) || !empty($appointment->student_year_level))
            <tr>
              <td class="small">
                {{ $appointment->student_course ?? '' }}
                @if(!empty($appointment->student_year_level))
                  @php
                    $yearRaw = $appointment->student_year_level;
                    $yearLbl = match((string) $yearRaw) {
                      '1' => '1st Year',
                      '2' => '2nd Year',
                      '3' => '3rd Year',
                      '4' => '4th Year',
                      default => $yearRaw,
                    };
                  @endphp
                  &nbsp;·&nbsp; {{ $yearLbl }}
                @endif
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
          @if(!empty($appointment->counselor_email) || !empty($appointment->counselor_phone))
            <tr>
              <td class="small">
                {{ $appointment->counselor_email }}
                @if(!empty($appointment->counselor_phone))
                  &nbsp;·&nbsp; {{ $appointment->counselor_phone }}
                @endif
              </td>
            </tr>
          @endif
          @if(!empty($appointment->counselor_dept))
            <tr>
              <td class="small">{{ $appointment->counselor_dept }}</td>
            </tr>
          @endif
        </table>
      </div>
    </div>

    <div class="card">
      <h2>Appointment Timing</h2>

      <div class="kv">
        <b>Booked On</b>
        <span>{{ $bookedAt ? $bookedAt->format('F d, Y · g:i A') : '—' }}</span>
      </div>

      <div class="kv" style="margin-top:6px;">
        <b>Scheduled For</b>
        <span>{{ $dt->format('F d, Y · g:i A') }}</span>
      </div>

      @if(!empty($appointment->location))
        <div class="kv" style="margin-top:6px;">
          <b>Location</b>
          <span>{{ $appointment->location }}</span>
        </div>
      @endif
    </div>
  </div>

  {{-- CASE NOTE SUMMARY (INSTEAD OF DIAGNOSIS REPORT) --}}
  <div class="box">
    <h2>Case Note Summary</h2>

    @if($hasCaseNoteContent)
      @if(!empty($caseNote->created_at))
        <div class="small" style="margin-bottom:6px;">
          Case note date: {{ \Carbon\Carbon::parse($caseNote->created_at)->format('F d, Y · g:i A') }}
        </div>
      @endif

      @if(!empty($caseNote->presenting_concern ?? null))
        <div class="kv" style="margin-top:4px;">
          <b>Presenting Concern(s)</b>
          <span>{!! nl2br(e($caseNote->presenting_concern)) !!}</span>
        </div>
      @endif

      @if(!empty($caseNote->session_summary ?? null) || !empty($caseNote->assessment ?? null))
        <div class="kv" style="margin-top:6px;">
          <b>Session Summary / Assessment</b>
          <span>
            @if(!empty($caseNote->session_summary ?? null))
              {!! nl2br(e($caseNote->session_summary)) !!}
            @elseif(!empty($caseNote->assessment ?? null))
              {!! nl2br(e($caseNote->assessment)) !!}
            @endif
          </span>
        </div>
      @endif

      @if(!empty($caseNote->intervention ?? null))
        <div class="kv" style="margin-top:6px;">
          <b>Interventions</b>
          <span>{!! nl2br(e($caseNote->intervention)) !!}</span>
        </div>
      @endif

      @if(!empty($caseNote->plan ?? null) || !empty($caseNote->recommendation ?? null))
        <div class="kv" style="margin-top:6px;">
          <b>Plan / Recommendations</b>
          <span>
            {!! nl2br(e($caseNote->plan ?? $caseNote->recommendation ?? '')) !!}
          </span>
        </div>
      @endif

      @if(!empty($caseNote->risk_level ?? null))
        <div class="kv" style="margin-top:6px;">
          <b>Risk Level</b>
          <span>{{ $caseNote->risk_level }}</span>
        </div>
      @endif

      @if(!empty($caseNote->notes ?? null))
        <div class="kv" style="margin-top:6px;">
          <b>Additional Notes</b>
          <span>{!! nl2br(e($caseNote->notes)) !!}</span>
        </div>
      @endif
    @else
      <div class="small">
        No case note has been recorded yet for this appointment.
      </div>
    @endif
  </div>

  <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>

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
