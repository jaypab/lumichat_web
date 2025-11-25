{{-- resources/views/emails/appointments/assigned-student.blade.php --}}
@php
  use Carbon\Carbon;

  $dt = $scheduledAt instanceof \DateTimeInterface
      ? Carbon::instance($scheduledAt)
      : Carbon::parse($scheduledAt);

  $dateLine = $dt->format('F j, Y (l)');
  $timeLine = $dt->format('g:i A');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>LumiCHAT — Appointment Approved</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Basic reset for email */
    body, table, td, a {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      text-size-adjust: 100%;
    }
    img {
      border: 0;
      outline: none;
      text-decoration: none;
      display: block;
      max-width: 100%;
    }
    body {
      margin: 0;
      padding: 0;
      background-color: #f3f4f6;
    }
    .wrapper {
      width: 100%;
      table-layout: fixed;
      background-color: #f3f4f6;
      padding: 24px 0;
    }
    .main-container {
      width: 100%;
      max-width: 640px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.10);
    }
    .header {
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      padding: 24px 32px;
      color: #e5e7eb;
    }
    .header-title {
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      color: #e0f2fe;
    }
    .header-subtitle {
      margin-top: 4px;
      font-size: 14px;
      color: #e5e7eb;
    }
    .brand-pill {
      display: inline-block;
      margin-top: 16px;
      padding: 6px 12px;
      border-radius: 999px;
      background-color: rgba(15, 23, 42, 0.25);
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #e5e7eb;
    }
    .content {
      padding: 24px 32px 28px;
      color: #111827;
      font-size: 14px;
      line-height: 1.6;
    }
    .greeting {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 8px;
    }
    .highlight-box {
      margin-top: 16px;
      border-radius: 12px;
      background-color: #f5f3ff;
      border: 1px solid #ddd6fe;
      padding: 14px 16px;
      font-size: 13px;
    }
    .highlight-label {
      font-size: 11px;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      color: #6b21a8;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .details-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 18px;
      font-size: 13px;
    }
    .details-table th {
      text-align: left;
      padding: 6px 0;
      color: #6b7280;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .details-table td {
      padding: 4px 0 10px;
      color: #111827;
      font-weight: 500;
    }
    .divider {
      border-top: 1px solid #e5e7eb;
      margin: 20px 0 18px;
    }
    .cta-wrapper {
      text-align: center;
      margin: 8px 0 4px;
    }
    .cta-button {
      display: inline-block;
      padding: 11px 22px;
      border-radius: 999px;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: #f9fafb;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      box-shadow: 0 6px 20px rgba(55, 48, 163, 0.35);
    }
    .tip-text {
      margin-top: 12px;
      font-size: 12px;
      color: #6b7280;
      text-align: center;
    }
    .footer {
      padding: 18px 24px 6px;
      text-align: center;
      font-size: 11px;
      color: #9ca3af;
    }
    .footer b {
      color: #6b7280;
    }
    .meta-id {
      margin-top: 4px;
      font-size: 10px;
      color: #9ca3af;
    }

    /* Mobile tweaks */
    @media screen and (max-width: 600px) {
      .main-container {
        border-radius: 0;
      }
      .header, .content {
        padding-left: 18px !important;
        padding-right: 18px !important;
      }
    }
  </style>
</head>
<body>
  <table role="presentation" class="wrapper" cellpadding="0" cellspacing="0" width="100%">
    <tr>
      <td align="center">
        <table role="presentation" class="main-container" cellpadding="0" cellspacing="0">
          {{-- Header --}}
          <tr>
            <td class="header">
              <div class="header-title">LumiCHAT • Appointment Approved</div>
              <div class="header-subtitle">
                Your counseling session has been successfully scheduled.
              </div>
              <div class="brand-pill">
                Student Notification · {{ $whenNice }}
              </div>
            </td>
          </tr>

          {{-- Content --}}
          <tr>
            <td class="content">
              <div class="greeting">
                Hi {{ $studentName }},
              </div>

              <p>
                Your counseling appointment has been
                <strong>approved</strong> and added to the schedule. Please review the
                details below and make sure to arrive on time.
              </p>

              <div class="highlight-box">
                <div class="highlight-label">Upcoming session</div>
                <div>
                  You’re booked for a counseling session with
                  <strong>{{ $counselorName }}</strong>.
                </div>
                <div style="margin-top: 4px;">
                  <strong>{{ $dateLine }}</strong> at
                  <strong>{{ $timeLine }}</strong>
                  ({{ $whenNice }}).
                </div>
              </div>

              <table class="details-table">
                <tr>
                  <th>Student</th>
                  <td>{{ $studentName }}</td>
                </tr>
                <tr>
                  <th>Counselor</th>
                  <td>{{ $counselorName }}</td>
                </tr>
                <tr>
                  <th>Date</th>
                  <td>{{ $dateLine }}</td>
                </tr>
                <tr>
                  <th>Time</th>
                  <td>{{ $timeLine }}</td>
                </tr>
                <tr>
                  <th>Reference</th>
                  <td>#{{ $appointmentId }}</td>
                </tr>
              </table>

              <div class="divider"></div>

              <div class="cta-wrapper">
                {{-- Optional: replace with actual URL if you have a student portal link --}}
                <a href="{{ url('/') }}" class="cta-button">
                  View Appointment in LumiCHAT
                </a>
              </div>

              <p class="tip-text">
                If you’re unable to attend, please contact your counselor or update your
                appointment through the LumiCHAT portal as early as possible.
              </p>

              <p style="margin-top: 18px;">
                Take care,<br>
                <strong>LumiCHAT Support Team</strong>
              </p>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td class="footer">
              <b>LumiCHAT · Mental Health Support for Students</b><br>
              This is an automated message about your counseling appointment.
              <div class="meta-id">
                Ref: APPT-{{ $appointmentId }}
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
