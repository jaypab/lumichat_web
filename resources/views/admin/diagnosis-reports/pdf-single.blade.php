{{-- resources/views/admin/diagnosis-reports/pdf-single.blade.php --}}
@php
  $code = 'DRP-'.now()->format('Y').'-'.str_pad($report->id, 4, '0', STR_PAD_LEFT);
  $date = $report->created_at?->format('F d, Y - h:i A') ?? '—';
  $dx   = trim((string)($report->diagnosis_result ?? '—'));

  // Accessible chip color (soft bg + clear text)
  $dxStyle = 'background:#eef2f7;border:1px solid #e5e7eb;color:#334155;';
  $k = strtolower($dx);
  if ($k !== '' && $k !== '—') {
      if (str_contains($k,'severe') || str_contains($k,'depress') || str_contains($k,'grief')) {
          $dxStyle = 'background:#fee2e2;border:1px solid #fecaca;color:#991b1b;';
      } elseif (str_contains($k,'moderate') || str_contains($k,'stress') || str_contains($k,'anxiety') || str_contains($k,'burnout')) {
          $dxStyle = 'background:#fef3c7;border:1px solid #fde68a;color:#92400e;';
      } elseif (str_contains($k,'mild') || str_contains($k,'ok') || str_contains($k,'normal') || str_contains($k,'stable')) {
          $dxStyle = 'background:#dcfce7;border:1px solid #bbf7d0;color:#065f46;';
      }
  }
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Diagnosis Report {{ $code }}</title>
  <style>
    /* ---------- Type & base ---------- */
    * { box-sizing:border-box; font-family:DejaVu Sans, sans-serif; }
    body { margin:16mm 14mm; color:#111827; font-size:12px; line-height:1.45; }

    /* spacing scale: 4 / 8 / 12 / 16 */
    .mt-4{margin-top:4px}.mt-8{margin-top:8px}.mt-12{margin-top:12px}.mt-16{margin-top:16px}

    /* ---------- Brand ---------- */
    .brandbar { margin:0 0 8px }
    .brand-logo { width:26px; height:26px; border-radius:50%; vertical-align:middle }
    .brand-title { display:inline-block; vertical-align:middle; margin-left:8px; font:700 16px/1 DejaVu Sans, sans-serif }
    .topbar { height:4px; background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef); border-radius:10px; margin:8px 0 14px }

    /* ---------- Heading & meta ---------- */
    h1 { margin:0 0 6px; font-size:20px }
    .meta { font-size:11px; color:#6b7280; margin:0 0 12px }

    /* ---------- Card (consistent container) ---------- */
    .card { border:1px solid #e5e7eb; border-radius:12px; background:#fff }
    .card-body { padding:14px }

    /* ---------- 4-column spec grid (HCI: fixed widths) ---------- */
    table.grid { width:100%; border-collapse:separate; border-spacing:0 10px; table-layout:fixed }
    /* widths: 22 + 28 + 22 + 28 = 100 (keeps perfect alignment) */
    .k  { width:22%; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; }
    .v  { width:28%; font-weight:700; word-wrap:break-word; }
    .kR { width:22%; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; padding-left:24px; }
    .vR { width:28%; font-weight:700; word-wrap:break-word;}
    /* equal row rhythm (HCI: consistent height) */
    .row { height:22px; vertical-align:middle }

    /* ---------- Chip ---------- */
    .chip { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700 }

    /* ---------- Sections ---------- */
    .soft-hr { height:1px; background:#eef2f7; border:0; margin:12px 0 10px }
    .foot { margin-top:10px; padding-top:10px; border-top:1px solid #e5e7eb; font-size:11px; color:#6b7280 }

    a { color:inherit; text-decoration:none } /* prevent viewer styling */
  </style>
</head>
<body>

  {{-- Brand header --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img class="brand-logo" src="{{ $logoData }}" alt="LumiCHAT">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>
  <div class="topbar"></div>

  {{-- Title & meta --}}
  <h1>Diagnosis Report — {{ $code }}</h1>
  <div class="meta">Generated: {{ $generatedAt }}</div>

  {{-- Details card --}}
  <div class="card">
    <div class="card-body">
        <table class="grid">
        <tr class="row">
            <td class="k">Report ID</td>
            <td class="v">{{ $code }}</td>

            <td class="kR">Date</td>
            <td class="vR">{{ $date }}</td>
        </tr>

        <tr class="row">
            <td class="k">Student Name</td>
            <td class="v">{{ $report->student->name ?? '—' }}</td>

            <td class="k">DIAGNOSIS RESULT</td>
            <td class="v vC">
                <span class="chip" style="{{ $dxStyle }}">{{ $dx !== '' ? $dx : '—' }}</span>
            </td>
        </tr>
        </table>

        @if(!empty($report->notes))
        <hr class="soft-hr">
        <div class="k" style="display:block; margin-bottom:6px; text-transform:uppercase;">Notes</div>
        <div style="border:1px solid #e5e7eb; background:#fafafa; padding:10px; border-radius:10px;">
            {{ $report->notes }}
        </div>
        @endif
    </div>
  </div>

  <div class="foot mt-12">
    LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>

</body>
</html>
