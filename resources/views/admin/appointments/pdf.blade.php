{{-- resources/views/admin/appointments/pdf.blade.php --}}
@php
  $total = $appointments instanceof \Illuminate\Support\Collection
    ? $appointments->count()
    : (is_array($appointments) ? count($appointments) : 0);

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
  <title>Appointments (PDF)</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body {
      margin: 18mm 14mm 22mm;
      font-size: 12px;
      color: #111827;
    }

    .brandbar {
      margin: 0 0 10px;
      text-align: left;
    }
    .brand {
      display: inline-block;
    }
    .brand-logo {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      vertical-align: middle;
    }
    .brand-title {
      display: inline-block;
      vertical-align: middle;
      margin-left: 10px;
      font: 700 18px/1 DejaVu Sans, sans-serif;
      white-space: nowrap;
    }

    h1 {
      margin: 0 0 6px;
      font-size: 20px;
    }

    .meta {
      font-size: 11px;
      color: #6b7280;
      margin-bottom: 12px;
      line-height: 1.5;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }
    thead {
      background: #f1f5f9;
      color: #334155;
    }
    th, td {
      padding: 8px 10px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
    }
    th:last-child,
    td:last-child {
      text-align: right;
    }

    thead { display: table-header-group; } /* repeat header on each page */
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }

    .small {
      font-size: 10px;
      color: #6b7280;
    }
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

  {{-- Title --}}
  <h1>Appointments</h1>

  {{-- Meta / Filters --}}
  <div class="meta">
    @php $hasFilter = false; @endphp
    @if($statusLabel)
      @php $hasFilter = true; @endphp
      <strong>Status:</strong> {{ $statusLabel }}
      &nbsp;|&nbsp;
    @endif

    @if($periodLabel)
      @php $hasFilter = true; @endphp
      <strong>Date Range:</strong> {{ ucfirst($periodLabel) }}
      &nbsp;|&nbsp;
    @endif

    @if(!empty($q))
      @php $hasFilter = true; @endphp
      <strong>Search:</strong> “{{ $q }}”
      &nbsp;|&nbsp;
    @endif

    <strong>Total:</strong> {{ $total }}
    &nbsp;•&nbsp;
    <span>Generated: {{ $generatedAt ?? now()->format('Y-m-d H:i') }}</span>
  </div>

  {{-- Table --}}
  <table>
    <thead>
      <tr>
        <th style="width:8%">ID</th>
        <th style="width:22%">Student</th>
        <th style="width:22%">Counselor</th>
        <th style="width:18%">Date</th>
        <th style="width:14%">Time</th>
        <th style="width:10%">Status</th>
        <th style="width:6%; text-align:right;">Booked</th>
      </tr>
    </thead>
    <tbody>
      @forelse($appointments as $row)
        @php
          $dt       = \Carbon\Carbon::parse($row->scheduled_at);
          $bookedAt = $row->booked_at ? \Carbon\Carbon::parse($row->booked_at) : null;

          $statusText = $row->status === 'no_show'
              ? 'No Show'
              : ucfirst($row->status ?? '—');
        @endphp
        <tr>
          <td><strong>{{ $row->id }}</strong></td>
          <td>{{ $row->student_name ?? '—' }}</td>
          <td>{{ $row->counselor_name ?? '—' }}</td>
          <td>{{ $dt->format('M d, Y') }}</td>
          <td>{{ $dt->format('g:i A') }}</td>
          <td>{{ $statusText }}</td>
          <td style="text-align:right;">
            @if($bookedAt)
              {{ $bookedAt->format('M d') }}
            @else
              —
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="small" style="text-align:center; padding:16px 0;">
            No appointments found for the selected filters.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- Footer --}}
  <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>

  {{-- Footer page numbers --}}
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
