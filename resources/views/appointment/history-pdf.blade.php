@php
  $total = $appointments instanceof \Illuminate\Support\Collection ? $appointments->count() : (is_array($appointments) ? count($appointments) : 0);
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>My Appointments (PDF)</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body { margin: 16mm 14mm 22mm; font-size: 12px; color:#111827; }

    .brandbar { margin:0 0 8px; text-align:left; }
    .brand{ display:inline-block; }
    .brand-logo{ width:50px;height:50px;border-radius:50%;vertical-align:middle; }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }

    h1{ margin:10px 0 6px; font-size:20px; }
    .meta{ font-size:11px; color:#6b7280; margin-bottom:12px; }

    table{ width:100%; border-collapse:collapse; }
    thead{ background:#f1f5f9; color:#334155; }
    th,td{ padding:9px 10px; border-bottom:1px solid #e5e7eb; text-align:left; }
    th:last-child, td:last-child{ text-align:right; }
    thead{ display:table-header-group; }
    tr{ page-break-inside:avoid; }
    .small{ font-size:10px; color:#6b7280; }
  </style>
</head>
<body>

  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData))
        <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
      @endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>My Appointments</h1>
  <div class="meta">
    @if(!empty($status) && $status!=='all') <strong>status:</strong> {{ ucfirst($status) }} | @endif
    @if(!empty($period) && $period!=='all') <strong>period:</strong> {{ str_replace('_',' ', $period) }} | @endif
    @if(!empty($q)) <strong>q:</strong> “{{ $q }}” | @endif
    <strong>total:</strong> {{ $total }} &nbsp; • &nbsp; generated: {{ $generatedAt ?? now()->format('Y-m-d H:i') }}
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:10%">ID</th>
        <th style="width:30%">Counselor</th>
        <th style="width:24%">Date</th>
        <th style="width:18%">Time</th>
        <th style="width:18%;text-align:right;">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($appointments as $row)
        @php $dt = \Carbon\Carbon::parse($row->scheduled_at); @endphp
        <tr>
          <td><strong>{{ $row->id }}</strong></td>
          <td>
            @php $cname = trim((string) ($row->counselor_name ?? '')); @endphp
            {{ $row->status==='canceled' ? 'Appointment Canceled' : ($cname !== '' ? $cname : 'Awaiting admin assignment') }}
          </td>
          <td>{{ $dt->format('M d, Y') }}</td>
          <td>{{ $dt->format('g:i A') }}</td>
          <td style="text-align:right;">{{ ucfirst($row->status) }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="small" style="text-align:center; padding:16px 0;">No appointments found.</td></tr>
      @endforelse
    </tbody>
  </table>

  <p class="small" style="margin-top:10px;">* This PDF lists all matching records based on your current filters.</p>
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
