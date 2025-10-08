{{-- resources/views/admin/appointments/pdf-show.blade.php --}}
@php
  use Carbon\Carbon;

  $dt         = Carbon::parse($appointment->scheduled_at);
  $bookedAt   = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;
  $generated  = $generatedAt ?? now()->format('Y-m-d H:i');

  // status → chip colors (same palette as app)
  $status = strtolower($appointment->status ?? 'pending');
  $chipMap = [
    'pending'   => ['bg' => '#FEF3C7', 'bd' => '#FDE68A', 'fg' => '#92400E'], // amber
    'confirmed' => ['bg' => '#DBEAFE', 'bd' => '#BFDBFE', 'fg' => '#1E3A8A'], // blue
    'canceled'  => ['bg' => '#FEE2E2', 'bd' => '#FECACA', 'fg' => '#991B1B'], // rose
    'completed' => ['bg' => '#DCFCE7', 'bd' => '#BBF7D0', 'fg' => '#166534'], // emerald
  ];
  $chip = $chipMap[$status] ?? $chipMap['pending'];
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
    body{ margin:14mm; font-family:'DejaVu Sans', sans-serif; color:#111827; font-size:12.5px; line-height:1.45; }

    /* Brand */
    .brandbar{ margin:0 0 8px; }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 'DejaVu Sans', sans-serif; }

    /* Top gradient accent (like your other PDFs) */
    .topbar{ height:4px; background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef); border-radius:10px; margin:6px 0 12px; }

    /* Headings + meta */
    h1{ margin:6px 0 8px; font-size:22px; }
    .meta-row{ display:flex; align-items:center; justify-content:space-between; gap:8px; color:#6b7280; font-size:11px; }
    .muted{ color:#6b7280; }

    /* Chip */
    .chip{ display:inline-block; padding:3px 9px; border-radius:999px; font-weight:700; font-size:10.5px; border:1px solid transparent; }

    /* Cards & tables */
    .cards{ display:flex; gap:12px; }
    .card{ flex:1 1 0; border:1px solid #e5e7eb; border-radius:12px; padding:12px; }
    .card h2{ margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#475569; }
    .kv b{ display:block; font-size:11px; color:#475569; text-transform:uppercase; margin-bottom:2px; }
    .kv span{ font-size:13px; }
    .info{ width:100%; border-collapse:collapse; }
    .info td{ padding:2px 0; vertical-align:top; }
    .section{ margin-bottom:8px; }

    /* Final Diagnosis box */
    .box{ border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-top:12px; }
    .small{ font-size:11px; color:#64748b; }

    /* Spacing helper */
    .spacer{ height:10px; }
  </style>
</head>
<body>

  {{-- Brand --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50" style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>

  {{-- Accent bar --}}
  <div class="topbar"></div>

  {{-- Title --}}
  <h1>Appointment #{{ $appointment->id }}</h1>

  {{-- Meta row: status chip (left) • created on (center-left) • generated (right) --}}
  <div class="meta-row">
    <div>
      Status:
      <span class="chip"
            style="background:{{ $chip['bg'] }}; border-color:{{ $chip['bd'] }}; color:{{ $chip['fg'] }};">
        {{ ucfirst($status) }}
      </span>
      &nbsp;•&nbsp; Created on:
      <strong>{{ $bookedAt ? $bookedAt->format('F d, Y · g:i A') : '—' }}</strong>
    </div>
    <div class="muted">Generated: {{ $generated }}</div>
  </div>

  <div class="spacer"></div>

  {{-- Two cards: Participants / Timing --}}
  <div class="cards">
    <div class="card">
      <h2>Participants</h2>

      <div class="section">
        <table class="info">
          <tr><td class="small" style="text-transform:uppercase;">Student</td></tr>
          <tr><td><strong>{{ $appointment->student_name }}</strong></td></tr>
          @if(!empty($appointment->student_email))
            <tr><td class="small">{{ $appointment->student_email }}</td></tr>
          @endif
          @if(!empty($appointment->student_id))
            <tr><td class="small">Student ID: {{ $appointment->student_id }}</td></tr>
          @endif
        </table>
      </div>

      <div class="section">
        <table class="info">
          <tr><td class="small" style="text-transform:uppercase;">Counselor</td></tr>
          <tr><td><strong>{{ $appointment->counselor_name ?: '—' }}</strong></td></tr>
          <tr>
            <td class="small">
              {{ $appointment->counselor_email }}
              @if(!empty($appointment->counselor_phone)) · {{ $appointment->counselor_phone }} @endif
            </td>
          </tr>
          @if(!empty($appointment->counselor_dept))
            <tr><td class="small">{{ $appointment->counselor_dept }}</td></tr>
          @endif
        </table>
      </div>
    </div>

    <div class="card" style="margin-top:12px;">
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

  {{-- Final Diagnosis --}}
  <div class="box">
    <h2 style="margin:0 0 6px; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#475569;">
      Final Diagnosis (Report)
    </h2>

    <div class="kv">
      <b>Diagnosis</b>
      <span>
        @if(isset($latestReport) && ($latestReport->diagnosis_result ?? '') !== '')
          {!! nl2br(e($latestReport->diagnosis_result)) !!}
        @else
          —
        @endif
      </span>
    </div>

    @if(isset($latestReport) && ($latestReport->notes ?? '') !== '')
      <div class="kv" style="margin-top:6px;">
        <b>Note</b>
        <span>{!! nl2br(e($latestReport->notes)) !!}</span>
      </div>
    @endif
  </div>
 {{-- Footer --}}
  <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>
</body>
</html>
