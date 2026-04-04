{{-- resources/views/Counselor_Interface/appointments/pdf-index.blade.php --}}
@php
  $total = count($appointments);

  // Helper for nicer labels in the meta line
  $statusLabel = (!empty($status) && $status !== 'all')
      ? ($status === 'no_show' ? 'No Show' : ucfirst($status))
      : null;

  $periodLabel = (!empty($period) && $period !== 'all')
      ? str_replace('_', ' ', $period)
      : null;
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Counselor Appointments (PDF)</title>
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

    * { box-sizing: border-box; }
    body {
      margin: 14mm 12mm 20mm;
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 11.5px;
      color: #111827;
    }

    .brandbar {
      margin: 0 0 8px;
    }
    .brand-logo {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      vertical-align: middle;
    }
    .brand-title {
      display: inline-block;
      vertical-align: middle;
      margin-left: 8px;
      font: 700 16px/1 'DejaVu Sans', sans-serif;
    }

    /* Gradient accent bar */
    .topbar {
      height: 3px;
      background: linear-gradient(90deg, #6366f1, #a855f7, #d946ef);
      border-radius: 6px;
      margin-bottom: 10px;
    }

    h1 {
      margin: 0 0 4px;
      font-size: 19px;
    }

    .meta {
      font-size: 10.5px;
      color: #64748b;
      margin-bottom: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    thead {
      background: #f8fafc;
    }
    th {
      padding: 8px 10px;
      border-bottom: 2px solid #e2e8f0;
      text-align: left;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .025em;
      color: #475569;
    }
    td {
      padding: 8px 10px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: top;
      word-wrap: break-word;
    }

    /* repeat header on each page */
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }

    .small {
      font-size: 10px;
      color: #64748b;
    }
    .text-right { text-align: right; }

    .status-pill {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 999px;
      font-size: 9px;
      font-weight: 700;
      border: 1px solid transparent;
    }
  </style>
</head>
<body>

  {{-- Brand --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>

  <div class="topbar"></div>

  {{-- Title --}}
  <h1>Appointment List</h1>

  {{-- Meta / Filters --}}
  <div class="meta">
    @php $filters = []; @endphp
    @if($statusLabel) @php $filters[] = "Status: <b>{$statusLabel}</b>"; @endphp @endif
    @if($periodLabel) @php $filters[] = "Period: <b>" . ucfirst($periodLabel) . "</b>"; @endphp @endif
    @if(!empty($q)) @php $filters[] = "Search: <b>\"{$q}\"</b>"; @endphp @endif
    
    {!! implode(' &nbsp;•&nbsp; ', $filters) !!}
    @if(count($filters) > 0) &nbsp;•&nbsp; @endif
    Total: <b>{{ $total }}</b>
    &nbsp;•&nbsp;
    Generated: {{ $generatedAt ?? now()->format('Y-m-d H:i') }}
  </div>

  {{-- Table --}}
  <table>
    <thead>
      <tr>
        <th style="width:8%">ID</th>
        <th style="width:32%">Student Name</th>
        <th style="width:20%">Scheduled Date</th>
        <th style="width:15%">Time</th>
        <th style="width:15%">Status</th>
        <th style="width:10%; text-align:right;">Booked At</th>
      </tr>
    </thead>
    <tbody>
      @forelse($appointments as $row)
        @php
          $dt       = \Carbon\Carbon::parse($row->scheduled_at);
          $bookedAt = $row->booked_at ? \Carbon\Carbon::parse($row->booked_at) : null;
          $s        = strtolower($row->status ?? 'pending');
        @endphp
        <tr>
          <td><strong>#{{ $row->id }}</strong></td>
          <td>{{ $row->student_name ?? '—' }}</td>
          <td>{{ $dt->format('M d, Y') }}</td>
          <td>{{ $dt->format('g:i A') }}</td>
          <td>{{ $s === 'no_show' ? 'NO SHOW' : strtoupper($s) }}</td>
          <td style="text-align:right;" class="small">
            {{ $bookedAt ? $bookedAt->format('M d') : '—' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="small" style="text-align:center; padding:20px;">
            No appointments found for the selected criteria.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- Footer --}}
  <div class="small" style="margin-top:20px; text-align:center;">
    LumiCHAT • Confidential student support record.
  </div>

  {{-- Footer page numbers --}}
  <script type="text/php">
  if (isset($pdf)) {
      $font  = $fontMetrics->get_font("DejaVu Sans", "normal");
      $pdf->page_text(740, 560, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 9, [0,0,0]);
  }
  </script>

</body>
</html>
