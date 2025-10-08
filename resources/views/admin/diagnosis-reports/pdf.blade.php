@php
  $total = $reports instanceof \Illuminate\Support\Collection
    ? $reports->count()
    : (is_array($reports) ? count($reports) : (method_exists($reports,'count') ? $reports->count() : 0));
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Diagnosis Reports (PDF)</title>
  <style>
    *{ box-sizing:border-box; font-family:DejaVu Sans, sans-serif; }
    body{ margin:16mm 14mm; font-size:12.5px; color:#111827; line-height:1.45; }

    /* Brand */
    .brandbar{ margin:0 0 10px; text-align:left; }
    .brand{ display:inline-block; }
    .brand-logo{ width:50px; height:50px; border-radius:50%; vertical-align:middle; }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }

    h1{ margin:10px 0 6px; font-size:20px; }
    .meta{ font-size:11px; color:#6b7280; margin-bottom:12px; }

    table{ width:100%; border-collapse:collapse; }
    thead{ background:#f1f5f9; color:#334155; display:table-header-group; }
    th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left; }
    tr{ page-break-inside:avoid; }
    .small{ font-size:10px; color:#6b7280; }
  </style>
</head>
<body>

  <div class="brandbar">
    <div class="brand">
      @if(!empty($logoData)) <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">@endif
      <span class="brand-title">LumiCHAT</span>
    </div>
  </div>

  <h1>Diagnosis Reports</h1>
  <div class="meta">
    Filters:
    @if(!empty($dateKey) && $dateKey !== 'all') <strong>date:</strong> {{ $dateKey }} | @endif
    @if(!empty($q)) <strong>q:</strong> “{{ $q }}” | @endif
    <strong>total:</strong> {{ $total }} &nbsp; • &nbsp;
    <span>generated: {{ $generatedAt ?? now()->format('Y-m-d H:i') }}</span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:16%">ID</th>
        <th style="width:22%">Student Name</th>
        <th style="width:25%">Counselor Name</th>
        <th style="width:25%">Diagnosis Result</th>
        <th style="width:14%; text-align:right;">Date</th>
      </tr>
    </thead>
    <tbody>
      @forelse($reports as $r)
        @php
          $code = 'DRP-'.now()->format('Y').'-'.str_pad($r->id, 4, '0', STR_PAD_LEFT);
          $studentName = $r->student->name ?? '—';
          $counselorName = $r->counselor->name ?? ('Counselor #'.($r->counselor_id ?? '—'));
          $date = $r->created_at?->format('M d, Y') ?? '—';
        @endphp
        <tr>
          <td><strong>{{ $code }}</strong></td>
          <td>{{ $studentName }}</td>
          <td>{{ $counselorName }}</td>
          <td>{{ $r->diagnosis_result }}</td>
          <td style="text-align:right;">{{ $date }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="small" style="text-align:center; padding:18px 0;">No diagnosis reports found.</td></tr>
      @endforelse
    </tbody>
  </table>
   <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>
</body>
</html>
