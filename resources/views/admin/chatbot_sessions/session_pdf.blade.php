<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Chatbot Session</title>
  <style>
    * { box-sizing:border-box; }
    body {
      font-family: DejaVu Sans, sans-serif;
      margin:14mm 14mm 22mm;
      font-size:12.5px;
      color:#111827;
      line-height:1.45;
    }

    /* Brand */
    .brandbar { margin:0 0 6px; }
    .brand-title {
      display:inline-block;
      vertical-align:middle;
      margin-left:10px;
      font:700 18px/1 DejaVu Sans, sans-serif;
    }

    /* Header */
    h1 { margin:6px 0 4px; font-size:22px; }
    .meta { font-size:11px; color:#6b7280; margin:0 0 10px; }
    .meta-right { float:right; }

    .section-title {
      margin-top:12px;
      margin-bottom:4px;
      font-weight:700;
      font-size:13px;
      color:#111827;
    }

    /* Table */
    table { width:100%; border-collapse:collapse; }
    thead { background:#f1f5f9; color:#334155; }
    th,td {
      padding:8px 10px;
      border-bottom:1px solid #e5e7eb;
      text-align:left;
      vertical-align:top;
    }
    tr { page-break-inside:avoid; }

    .small { font-size:10px; color:#6b7280; }

    /* Chips / badges */
    .badge {
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-size:10px;
      vertical-align:middle;
      margin-left:6px;
    }
    .badge-hr {
      background:#fee2e2;
      color:#b91c1c;
      border:1px solid #fecaca;
    }
    .badge-nr {
      background:#e0f2fe;
      color:#075985;
      border:1px solid #bae6fd;
    }

    .chip {
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      background:#eff6ff;
      color:#1e3a8a;
      font-size:11px;
      margin:1px 4px 3px 0;
    }

    .box {
      border:1px solid #e5e7eb;
      border-radius:6px;
      padding:8px 10px;
      margin-top:4px;
      background:#f9fafb;
    }
    .box-hr {
      border-color:#fecaca;
      background:#fff1f2;
    }

    .label { font-size:11px; color:#6b7280; }
    .value { font-size:12px; color:#111827; }
  </style>
</head>
<body>

  @php
    use Carbon\Carbon;

    // ===== SAFE DEFAULTS (avoid nulls) =====
    $highRisk       = $highRisk       ?? null;
    $allHighRisk    = $allHighRisk    ?? [];
    $sessionCounts  = $sessionCounts  ?? ['all' => null, 'd30' => null, 'd7' => null];
    $nextAppt       = $nextAppt       ?? null;

    // ---- Risk normalization (similar to show.blade) ----
    $riskStrRaw = strtolower((string)($session->risk_level ?? $session->risk ?? ''));
    $isHighRisk = in_array($riskStrRaw, ['high','high-risk','high_risk'], true)
                  || (int)($session->risk_score ?? 0) >= 80;

    $riskLabel = $riskStrRaw
      ? ucfirst(str_replace(['_','-'], ' ', $riskStrRaw))
      : '—';

    $scoreFromLevel = match($riskStrRaw){
      'high','high-risk','high_risk' => 90,
      'moderate'                     => 60,
      'low'                          => 20,
      default                        => 0,
    };
    $riskScore = max(0, min(100, (int)($session->risk_score ?? $scoreFromLevel)));

    // ---- Upcoming appointment (if passed) ----
    $nextLabel  = null;
    $nextDetail = null;
    if (!empty($nextAppt?->scheduled_at)) {
      $dt = Carbon::parse($nextAppt->scheduled_at);
      $rel = $dt->isFuture()
        ? 'in '.$dt->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
        : 'Started '.$dt->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW]);

      $nextLabel  = 'Upcoming appointment '.$rel;
      $nextDetail = $dt->format('M d, Y • g:i A')
                    . (!empty($nextAppt->counselor_name) ? ' • '.$nextAppt->counselor_name : '');
    }

    // ---- Latest high-risk trigger (if available) ----
    $latestHRText = null;
    $latestHRAt   = null;
    $latestHRId   = null;
    if (!empty($highRisk)) {
      $latestHRText = trim($highRisk->text ?? $highRisk->message ?? '');
      $ts = $highRisk->sent_at ?? $highRisk->created_at ?? null;
      $latestHRAt = $ts ? Carbon::parse($ts)->format('F d, Y • h:i A') : null;
      $latestHRId = $highRisk->id ?? null;
    }

    // ---- Normalize emotions for this session (same logic as index/show) ----
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

    // ---- Student name ----
    $studentName = trim($session->user->name ?? '') ?: '—';

    // ---- Session counts for this student ----
    $cntAll = $sessionCounts['all'] ?? null;
    $cnt30  = $sessionCounts['d30'] ?? null;
    $cnt7   = $sessionCounts['d7']  ?? null;

    // ---- Safe high-risk count (this is where error came from) ----
    $hrSource = $allHighRisk ?? [];
    $hrCount  = is_countable($hrSource) ? count($hrSource) : 0;
  @endphp

  {{-- Brand / generated --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50"
           style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
    <div class="meta">
      <span class="meta-right">Generated: {{ $generatedAt }}</span>
    </div>
  </div>

  <h1>
    Chatbot Session
    @if($isHighRisk)
      <span class="badge badge-hr">HIGH RISK</span>
    @else
      <span class="badge badge-nr">NORMAL</span>
    @endif
  </h1>

  {{-- Session-level counts for this student --}}
  @if(!empty($cntAll))
    <div class="meta" style="margin-top:2px;margin-bottom:10px;">
      Student sessions — Total: <strong>{{ $cntAll }}</strong>
      @if(!is_null($cnt30)) | Last 30 days: <strong>{{ $cnt30 }}</strong>@endif
      @if(!is_null($cnt7))  | Last 7 days: <strong>{{ $cnt7 }}</strong>@endif
    </div>
  @endif

  {{-- SECTION: Overview --}}
  <div class="section-title">Session overview</div>
  <table>
    <tbody>
      <tr>
        <th style="width:24%;">Session ID</th>
        <td style="width:76%;"><strong>{{ $code }}</strong></td>
      </tr>
      <tr>
        <th>Student</th>
        <td>{{ $studentName }}</td>
      </tr>
      <tr>
        <th>Risk level</th>
        <td>
          {{ $riskLabel }}
          @if($riskScore > 0)
            <span class="small"> • Score: {{ $riskScore }} / 100</span>
          @endif
        </td>
      </tr>
      <tr>
        <th>Initial date</th>
        <td>{{ \Carbon\Carbon::parse($session->created_at)->format('M d, Y • h:i A') }}</td>
      </tr>
      @if(!empty($session->updated_at))
        <tr>
          <th>Last update</th>
          <td>{{ $session->updated_at->format('M d, Y • h:i A') }}</td>
        </tr>
      @endif
      @if($nextLabel && $nextDetail)
        <tr>
          <th>Appointment</th>
          <td>
            <div class="label">{{ $nextLabel }}</div>
            <div class="value">{{ $nextDetail }}</div>
          </td>
        </tr>
      @endif
    </tbody>
  </table>

  {{-- SECTION: Emotions --}}
  <div class="section-title">Detected emotions</div>
  <div class="box">
    @if($total === 0 || empty($top))
      <span class="small">No emotions were detected for this session.</span>
    @else
      @foreach($top as $name => $cnt)
        @php $pct = $total ? round($cnt / $total * 100) : 0; @endphp
        <span class="chip">{{ ucfirst($name) }} <small>({{ $pct }}%)</small></span>
      @endforeach
      <div class="small" style="margin-top:4px;">Total mentions: {{ $total }}</div>
    @endif
  </div>

 

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
