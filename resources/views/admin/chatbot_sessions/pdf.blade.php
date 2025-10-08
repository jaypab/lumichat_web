<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Chatbot Sessions</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body { margin: 16mm 14mm; font-size: 12.5px; color:#111827; line-height:1.45; }

    /* Brand header */
    .brandbar { margin:0 0 10px; text-align:left; }
    .brand { display:inline-block; }
    .brand-logo { width:50px; height:50px; border-radius:50%; vertical-align:middle; }
    .brand-title { display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }

    h1   { margin: 10px 0 6px; font-size: 20px; }
    .meta{ font-size: 11px; color:#6b7280; margin-bottom: 12px; }

    table { width:100%; border-collapse:collapse; }
    thead { background:#f1f5f9; color:#334155; display:table-header-group; }
    th, td { padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left; }
    tr { page-break-inside:avoid; }

    .small { font-size:10px; color:#6b7280; }
  </style>
</head>

<body>

  {{-- Brand --}}
  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData))
        <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
      @endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>Chatbot Sessions</h1>
  <div class="meta">
    Filters — Date: <strong>{{ $dateKey }}</strong>
    @if($q !== '') | Search: <strong>{{ $q }}</strong>@endif
    <span style="float:right;">Generated: {{ $generatedAt }}</span>
  </div>

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
      @forelse($rows as $s)
        @php
          // Session code (year from created_at when available)
          $codeYear = $s->created_at ? \Carbon\Carbon::parse($s->created_at)->format('Y') : now()->format('Y');
          $code     = 'LMC-' . $codeYear . '-' . str_pad($s->id, 4, '0', STR_PAD_LEFT);

          // ---- normalize emotions PER ROW ----
          $raw = $s->emotions ?? [];
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

        <tr>
          <td><strong>{{ $code }}</strong></td>
          <td>{{ $s->user->name ?? '—' }}</td>
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
          <td>{{ optional($s->created_at)->format('M d, Y') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="small" style="text-align:center; padding:18px 0;">No sessions found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
 {{-- Footer --}}
  <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>
</body>
</html>
