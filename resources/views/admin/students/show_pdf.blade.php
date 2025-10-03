<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Student {{ $student->name }}</title>
  <style>
    @font-face{ font-family:'DejaVu Sans'; src:url('{{ public_path('fonts/DejaVuSans.ttf') }}') format('truetype'); font-weight:400; }
    @font-face{ font-family:'DejaVu Sans'; src:url('{{ public_path('fonts/DejaVuSans-Bold.ttf') }}') format('truetype'); font-weight:700; }
    *{ box-sizing:border-box }
    body{ margin:14mm; font-family:'DejaVu Sans', sans-serif; color:#111827; font-size:12.5px; line-height:1.45 }

    .brandbar{ margin:0 0 8px }
    .brand-title{ display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 'DejaVu Sans', sans-serif }
    .topbar{ height:4px; background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef); border-radius:10px; margin:6px 0 12px }

    h1{ margin:6px 0 2px; font-size:20px }
    .meta{ font-size:11px; color:#6b7280; margin-bottom:10px }

    .card{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:12px }
    .grid{ display:flex; gap:16px }
    .col{ flex:1 1 0 }

    table{ width:100%; border-collapse:collapse }
    thead{ background:#f1f5f9; color:#334155 }
    th,td{ padding:8px 10px; border-bottom:1px solid #e5e7eb; text-align:left }
    .small{ font-size:11px; color:#64748b }
  </style>
</head>
<body>

  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50" style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>

  <div class="topbar"></div>

  <h1>Student Details</h1>
  <div class="small" style="margin-bottom:6px;">
    {{ $student->name }} • {{ $student->course }} • {{ $student->year_level }} • {{ $student->email }}
  </div>
  <div class="meta">Generated: {{ $generatedAt }}</div>

  <div class="card">
    <div class="grid">
      <div class="col">
        <div class="small">FULL NAME</div>
        <div><strong>{{ $student->name }}</strong></div>
      </div>
      <div class="col">
        <div class="small">EMAIL</div>
        <div>{{ $student->email }}</div>
      </div>
    </div>

    <div class="grid" style="margin-top:10px;">
      <div class="col">
        <div class="small">CONTACT NUMBER</div>
        <div>{{ $student->contact_number ?: '—' }}</div>
      </div>
      <div class="col">
        <div class="small">COURSE / YEAR</div>
        <div>{{ $student->course }} — {{ $student->year_level }}</div>
      </div>
    </div>

    <div class="grid" style="margin-top:10px;">
      <div class="col">
        <div class="small">CREATED</div>
        <div>{{ $student->created_at->format('F d, Y • h:i A') }}</div>
      </div>
      <div class="col">
        <div class="small">UPDATED</div>
        <div>{{ $student->updated_at->format('F d, Y • h:i A') }}</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="small" style="text-transform:uppercase; letter-spacing:.05em; color:#475569; margin-bottom:6px;">
      Appointments — Monthly totals ({{ $year }})
    </div>
    <table>
      <thead>
        <tr>
          <th style="width:70%;">Month</th>
          <th style="width:30%; text-align:right;">Appointments</th>
        </tr>
      </thead>
      <tbody>
        @foreach($labels as $i => $label)
          <tr>
            <td>{{ $label }}</td>
            <td style="text-align:right;">{{ $series[$i] ?? 0 }}</td>
          </tr>
        @endforeach
        <tr>
          <td style="text-align:right;"><strong>Total</strong></td>
          <td style="text-align:right;"><strong>{{ $total }}</strong></td>
        </tr>
      </tbody>
    </table>
  </div>

</body>
</html>
