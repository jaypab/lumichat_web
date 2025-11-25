{{-- resources/views/emails/appointments/assigned-counselor.blade.php --}}
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
  <title>LumiCHAT — New Appointment Assigned</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
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
      background-color: #0f172a;
    }
    .wrapper {
      width: 100%;
      table-layout: fixed;
      background-color: #0f172a;
      padding: 24px 0;
    }
    .main-container {
      width: 100%;
      max-width: 640px;
      margin: 0 auto;
      background-color: #020617;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 36px rgba(15, 23, 42, 0.55);
      border: 1px solid rgba(148, 163, 184, 0.4);
    }
    .header {
      background: radial-gradient(circle at top left, #4f46e5, #020617);
      padding: 24px 32px 20px;
      color: #f9fafb;
    }
    .header-title {
      font-size: 19px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #e0f2fe;
    }
    .header-subtitle {
      margin-top: 4px;
      font-size: 13px;
      color: #e5e7eb;
    }
    .badge {
      display: inline-block;
      margin-top: 16px;
      padding: 6px 12px;
      border-radius: 999px;
      background-color: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(191, 219, 254, 0.4);
      font-size: 11px;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      color: #bfdbfe;
    }
    .content {
      padding: 24px 32px 26px;
      color: #e5e7eb;
      font-size: 14px;
      line-height: 1.6;
    }
    .greeting {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 8px;
      color: #f9fafb;
    }
    .highlight-box {
      margin-top: 16px;
      border-radius: 12px;
      background: radial-gradient(circle at top left, rgba(129, 140, 248, 0.13), rgba(15, 23, 42, 0.9));
      border: 1px solid rgba(148, 163, 184, 0.7);
      padding: 14px 16px;
      font-size: 13px;
    }
    .highlight-label {
      font-size: 11px;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      color: #a5b4fc;
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
      color: #9ca3af;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .details-table td {
      padding: 4px 0 10px;
      color: #e5e7eb;
      font-weight: 500;
    }
    .divider {
      border-top: 1px solid rgba(55, 65, 81, 0.8);
      margin: 20px 0 16px;
    }
    .cta-wrapper {
      text-align: left;
      margin: 4px 0 4px;
    }
    .cta-button {
      display: inline-block;
      padding: 10px 20px;
      border-radius: 999px;
      background: linear-gradient(135deg, #22c55e, #16a34a);
      color: #f9fafb;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      box-shadow: 0 6px 20px rgba(22, 163, 74, 0.45);
    }
    .tip-text {
      margin-top: 12px;
      font-size: 12px;
      color: #9ca3af;
    }
    .footer {
      padding: 16px 24px 6px;
      text-align: center;
      font-size: 11px;
      color: #6b7280;
    }
    .footer b {
      color: #e5e7eb;
    }
    .meta-id {
      margin-top: 4px;
      font-size: 10px;
      color: #6b7280;
    }

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
              <div class="header-title">LumiCHAT • New Appointment</div>
              <div class="header-subtitle">
                A student has been assigned to your counseling schedule.
              </div>
              <div class="badge">
                Counselor Notification · {{ $whenNice }}
              </div>
            </td>
          </tr>

          {{-- Content --}}
          <tr>
            <td class="content">
              <div class="greeting">
                Hi {{ $counselorName }},
              </div>

              <p>
                A new appointment has been <strong>assigned to you</strong> through LumiCHAT.
                Please review the session details and prepare accordingly.
              </p>

              <div class="highlight-box">
                <div class="highlight-label">Assigned session</div>
                <div>
                  Student: <strong>{{ $studentName }}</strong>
                </div>
                <div style="margin-top: 4px;">
                  Schedule:
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
                {{-- Optional: replace with actual counselor URL (e.g. route to counselor appointments) --}}
                <a href="{{ url('/') }}" class="cta-button">
                  Open in LumiCHAT Dashboard
                </a>
              </div>

              <p class="tip-text">
                You may update the appointment status, add case notes,
                or reschedule directly from your LumiCHAT counselor panel.
              </p>

              <p style="margin-top: 18px;">
                Regards,<br>
                <strong>LumiCHAT System</strong>
              </p>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td class="footer">
              <b>LumiCHAT · Counselor Portal</b><br>
              This automated message keeps you updated on your assigned sessions.
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
