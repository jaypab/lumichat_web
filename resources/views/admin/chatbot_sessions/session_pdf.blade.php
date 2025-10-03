
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Chatbot Session</title>
  <style>
    *{ box-sizing:border-box }
    body{ font-family: DejaVu Sans, sans-serif; margin:14mm 14mm; font-size:12.5px; color:#111827; line-height:1.45 }

    /* Brand */
    .brandbar{ margin:0 0 6px }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif }

    /* Header */
    h1{ margin:6px 0 8px; font-size:22px }
    .meta{ font-size:11px; color:#6b7280; margin:0 0 10px; }
    .meta-right{ float:right; }

    /* Badge */
    .badge{ display:inline-block; padding:3px 8px; border-radius:999px; font-weight:700; font-size:10px; margin-left:8px }
    .badge-hr{ background:#fee2e2; color:#991b1b; border:1px solid #fecaca }
    .badge-nr{ background:#dcfce7; color:#166534; border:1px solid #bbf7d0 }

    /* Table */
    table{ width:100%; border-collapse:collapse }
    thead{ background:#f1f5f9; color:#334155 }
    th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left }
    tr{ page-break-inside:avoid }

    .small{ font-size:10px; color:#6b7280 }
  </style>
</head>
<body>

  {{-- Brand (fixed size so it never grows) --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50" style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
     <div class="meta"><span class="meta-right">Generated: {{ $generatedAt }}</span></div>  
  </div>

  <h1>
    Chatbot Session
    @if($isHighRisk)
      <span class="badge badge-hr">HIGH RISK</span>
    @else
      <span class="badge badge-nr">NORMAL</span>
    @endif
  </h1>

  <table>
    <thead>
      <tr>
        <th style="width:22%;">Session ID</th>
        <th style="width:25%;">Student</th>
        <th style="width:34%;">Initial Result</th>
        <th style="width:14%;">Initial Date</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>{{ $code }}</strong></td>
        <td>{{ $session->user->name ?? '—' }}</td>
        <td>{{ $session->topic_summary ?? '—' }}</td>
        <td>{{ \Carbon\Carbon::parse($session->created_at)->format('M d, Y • h:i A') }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Footer --}}
  <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>
</body>
</html>
