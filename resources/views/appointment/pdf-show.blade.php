<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Appointment #{{ $appointment->id }}</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body { margin: 16mm 14mm 22mm; font-size: 12px; color: #111827; }

    /* brand */
    .brandbar { margin:0 0 10px; text-align:left; }
    .brand { display:inline-block; }
    .brand-logo { width:50px; height:50px; border-radius:50%; vertical-align:middle; }
    .brand-title { display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }

    h1 { margin: 6px 0 10px; font-size: 20px; }
    .meta { font-size: 11px; color: #6b7280; margin-bottom: 12px; }

    .card { border:1px solid #e5e7eb; border-radius:8px; padding:10px; }
    .row { width:100%; border-collapse: separate; border-spacing: 12px 0; }
    .row td { vertical-align: top; width: 50%; }

    .kv b { display:block; font-size: 11px; color:#475569; text-transform:uppercase; margin-bottom:2px; }
    .kv span { font-size: 13px; }
    .spacer { height: 12px; }
  </style>
</head>
<body>

  {{-- brand header --}}
  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData))
        <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
      @endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>Appointment #{{ $appointment->id }}</h1>
  <div class="meta">Generated: {{ $generatedAt }}</div>

  <table class="row">
    <tr>
      <td>
        <div class="card">
          <div class="kv">
            <b>Counselor</b>
            <span>
              @if(empty($appointment->counselor_name))
                Awaiting admin assignment
              @else
                {{ $appointment->counselor_name }}
                @if(!empty($appointment->counselor_email)) · {{ $appointment->counselor_email }} @endif
                @if(!empty($appointment->counselor_phone)) · {{ $appointment->counselor_phone }} @endif
              @endif
            </span>
          </div>
        </div>
      </td>
      <td>
        <div class="card">
          <div class="kv">
            <b>Scheduled For</b>
            <span>{{ \Carbon\Carbon::parse($appointment->scheduled_at)->format('F d, Y · g:i A') }}</span>
          </div>
          <div class="kv">
            <b>Status</b>
            <span>{{ ucfirst($appointment->status) }}</span>
          </div>
        </div>
      </td>
    </tr>
  </table>

  @if(!empty($appointment->note))
    <div class="spacer"></div>
    <div class="card">
      <div class="kv">
        <b>Note from Counseling Office</b>
        <span>{!! nl2br(e($appointment->note)) !!}</span>
      </div>
    </div>
  @endif

  @if(!empty($appointment->final_note))
    <div class="spacer"></div>
    <div class="card">
      <div class="kv">
        <b>Final Diagnosis / Counselor Note</b>
        <span>{!! nl2br(e($appointment->final_note)) !!}</span>
      </div>
      @if(!empty($appointment->finalized_at))
        <div class="kv" style="margin-top:6px;">
          <b>Updated</b>
          <span>{{ \Carbon\Carbon::parse($appointment->finalized_at)->format('F d, Y · g:i A') }}</span>
        </div>
      @endif
    </div>
  @endif
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
