{{-- resources/views/admin/appointments/pdf.blade.php --}}
@php
  $total = $appointments instanceof \Illuminate\Support\Collection
    ? $appointments->count()
    : (is_array($appointments) ? count($appointments) : 0);
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Appointments (PDF)</title>
  <style>
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body { margin: 18mm 14mm; font-size: 12px; color: #111827; }
    .brandbar { margin:0 0 8px; text-align:left; }
  .brand { display:inline-block; }
  .brand-logo { width:50px; height:50px; border-radius:50%; vertical-align:middle; }
  .brand-title { display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }

    h1 { margin: 0 0 6px; font-size: 20px; }
    .meta { font-size: 11px; color: #6b7280; margin-bottom: 12px; }
    table { width:100%; border-collapse: collapse; }
    thead { background:#f1f5f9; color:#334155; }
    th, td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    th:last-child, td:last-child { text-align: right; }
    thead { display: table-header-group; } /* repeat header on each page */
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }
    .small { font-size: 10px; color:#6b7280; }
  </style>
</head>


<div class="brandbar">
  <div class="brand">
    @if(!empty($logoData))
      <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>
</div>

<body>
  <h1>Appointments</h1>
  <div class="meta">
    Filters:
    @if(!empty($status) && $status !== 'all') <strong>status:</strong> {{ ucfirst($status) }} | @endif
    @if(!empty($period) && $period !== 'all') <strong>period:</strong> {{ str_replace('_',' ', $period) }} | @endif
    @if(!empty($q)) <strong>q:</strong> “{{ $q }}” | @endif
    <strong>total:</strong> {{ $total }} &nbsp; • &nbsp;
    <span>generated: {{ $generatedAt ?? now()->format('Y-m-d H:i') }}</span>
  </div>

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
        @endphp
        <tr>
          <td><strong>{{ $row->id }}</strong></td>
          <td>{{ $row->student_name ?? '—' }}</td>
          <td>{{ $row->counselor_name ?? '—' }}</td>
          <td>{{ $dt->format('M d, Y') }}</td>
          <td>{{ $dt->format('g:i A') }}</td>
          <td>{{ ucfirst($row->status) }}</td>
          <td style="text-align:right;">
            @if($bookedAt)
              {{ $bookedAt->format('M d') }}
            @else
              —
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="small" style="text-align:center; padding:16px 0;">No appointments found.</td></tr>
      @endforelse
    </tbody>
  </table>
   {{-- Footer --}}
  <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>
</body>
</html>
