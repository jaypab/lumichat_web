
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Chatbot Session</title>
  <style>
    *{ box-sizing:border-box }
    body{ font-family: DejaVu Sans, sans-serif; margin:14mm 14mm 22mm; font-size:12.5px; color:#111827; line-height:1.45 }

    /* Brand */
    .brandbar{ margin:0 0 6px }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif }

    /* Header */
    h1{ margin:6px 0 8px; font-size:22px }
    .meta{ font-size:11px; color:#6b7280; margin:0 0 10px; }
    .meta-right{ float:right; }

   
    /* Table */
    table{ width:100%; border-collapse:collapse }
    thead{ background:#f1f5f9; color:#334155 }
    th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left }
    tr{ page-break-inside:avoid }

    .small{ font-size:10px; color:#6b7280 }
  </style>
</head>
<body>

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

  {{-- NEW: session count summary for this student --}}
  @if(!empty($sessionCounts['all']))
    <div class="meta" style="margin-top:2px;margin-bottom:12px;">
      Student sessions — Total: <strong>{{ $sessionCounts['all'] }}</strong>
      @if(!is_null($sessionCounts['d30'])) | Last 30 days: <strong>{{ $sessionCounts['d30'] }}</strong>@endif
      @if(!is_null($sessionCounts['d7']))  | Last 7 days: <strong>{{ $sessionCounts['d7'] }}</strong>@endif
    </div>
  @endif

  @php
    // normalize emotions to counts for this session
    $raw = $session->emotions ?? [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    $counts = [];
    if (is_array($raw)) {
        $isList = array_keys($raw) === range(0, count($raw) - 1);
        if ($isList) {
            foreach ($raw as $lbl) {
                if (!is_string($lbl) || $lbl === '') continue;
                $k = strtolower($lbl);
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
        } else {
            foreach ($raw as $k => $v) {
                if (!is_string($k)) continue;
                $counts[strtolower($k)] = max(0, (int) $v);
            }
        }
    }
    arsort($counts);
    $total = array_sum($counts);
    $top   = array_slice($counts, 0, 6, true);
  @endphp

  <table>
    <thead>
      <tr>
        <th style="width:22%;">Session ID</th>
        <th style="width:25%;">Student</th>
        <th style="width:34%;">Emotions Mentioned</th>
        <th style="width:14%;">Initial Date</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>{{ $code }}</strong></td>
        <td>{{ $session->user->name ?? '—' }}</td>
        <td>
          @if($total === 0 || empty($top))
            —
          @else
            @foreach($top as $name => $cnt)
              @php $pct = $total ? round($cnt / $total * 100) : 0; @endphp
              <span class="chip">{{ ucfirst($name) }} <small>({{ $pct }}%)</small></span>
            @endforeach
          @endif
        </td>
        <td>{{ \Carbon\Carbon::parse($session->created_at)->format('M d, Y • h:i A') }}</td>
      </tr>
    </tbody>
  </table>
  {{-- Footer --}}
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
    $x     = $w - 72;   // ~1 inch from right
    $y     = $h - 28;   // ~28pt from bottom
    $pdf->page_text($x, $y, $text, $font, $size, [0,0,0]);
}
</script>
</body>
</html>
