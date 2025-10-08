{{-- resources/views/admin/students/pdf.blade.php --}}
@php
  $total = $students instanceof \Illuminate\Support\Collection
    ? $students->count()
    : (is_array($students) ? count($students) : (method_exists($students,'count') ? $students->count() : 0));

  $logoSrc = !empty($logoPath) ? 'file://'.str_replace('\\','/',$logoPath) : null;
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Student Records (PDF)</title>
  <style>
  * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
  body { margin: 16mm 14mm; font-size: 12px; color: #111827; }

   .brandbar { margin:0 0 8px; text-align:left; }
  .brand { display:inline-block; }
  .brand-logo { width:50px; height:50px; border-radius:50%; vertical-align:middle; }
  .brand-title { display:inline-block; vertical-align:middle; margin-left:10px; font:700 18px/1 DejaVu Sans, sans-serif; white-space:nowrap; }


  .meta { font-size: 11px; color: #6b7280; margin-bottom: 10px; }

  table { width:100%; border-collapse: collapse; }
  thead { background:#f1f5f9; color:#334155; }
  th, td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
  th:last-child, td:last-child { text-align: right; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .small { font-size: 10px; color:#6b7280; }
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

  <div class="meta">
    Filters:
    @if(!empty($q)) <strong>q:</strong> “{{ $q }}” | @endif
    @if(!empty($year)) <strong>year:</strong> {{ $year }} | @endif
    <strong>total:</strong> {{ $total }} &nbsp; • &nbsp;
    <span>generated: {{ $generatedAt ?? now()->format('Y-m-d H:i') }}</span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:24%">Student Name</th>
        <th style="width:25%">Email</th>
        <th style="width:18%">Contact No.</th>
        <th style="width:15%">Course</th>
        <th style="width:18%; text-align:right;">Year Level</th>
      </tr>
    </thead>
    <tbody>
      @forelse($students as $s)
        <tr>
          <td><strong>{{ $s->name }}</strong></td>
          <td>{{ $s->email }}</td>
          <td>{{ $s->contact_number ?? '—' }}</td>

          {{-- Plain text (no borders/pills) --}}
          <td>{{ $s->course ?: '—' }}</td>
          <td style="text-align:right;">{{ $s->year_level ?: '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="small" style="text-align:center; padding:16px 0;">No students found.</td></tr>
      @endforelse
    </tbody>
  </table>
   <div class="small" style="margin-top:14px;">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>
</body>
</html>
