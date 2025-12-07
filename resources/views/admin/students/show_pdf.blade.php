{{-- resources/views/admin/students/show_pdf.blade.php --}}
@php
    use Carbon\Carbon;

    $generated = $generatedAt ?? now()->format('Y-m-d H:i');

    $labels      = $labels      ?? [];
    $series      = $series      ?? [];
    $appointments = $appointments ?? collect();
    $caseNotes    = $caseNotes    ?? collect();
    $chatSessions = $chatSessions ?? collect();

    // Peak month for chart
    $peakLabel = null;
    $maxVal    = 0;
    foreach ($series as $idx => $v) {
        $v = (int) $v;
        if ($v > $maxVal) {
            $maxVal    = $v;
            $peakLabel = $labels[$idx] ?? null;
        }
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student #{{ $student->id }} — Full Record {{ $year }}</title>
    <style>
      .compact-year td { padding:2px 4px; font-size:11px; }

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

        *{ box-sizing:border-box; }
        body{
            margin: 18mm 14mm 22mm;
            font-family:'DejaVu Sans', sans-serif;
            color:#111827;
            font-size:12.5px;
            line-height:1.45;
        }

        .brandbar{ margin:0 0 8px; }
        .brand-title{
            display:inline-block;
            vertical-align:middle;
            margin-left:10px;
            font:700 18px/1 'DejaVu Sans', sans-serif;
        }

        .topbar{
            height:4px;
            background:linear-gradient(90deg,#6366f1,#a855f7,#d946ef);
            border-radius:10px;
            margin:6px 0 12px;
        }

        h1{
            margin:6px 0 4px;
            font-size:20px;
        }
        h2{
            margin:0 0 6px;
            font-size:13px;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:#475569;
        }

        .meta-row{
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:8px;
            color:#6b7280;
            font-size:11px;
        }
        .muted{ color:#6b7280; }

        .cards{
            display:flex;
            gap:12px;
            margin-top:10px;
        }
        .card{
            flex:1 1 0;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:12px;
        }

        .info-table{ width:100%; border-collapse:collapse; }
        .info-table td{ padding:2px 0; vertical-align:top; font-size:12px; }
        .label{
            font-size:11px;
            color:#6b7280;
            text-transform:uppercase;
        }

        .box{
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:12px;
            margin-top:14px;
        }

        table.simple{
            width:100%;
            border-collapse:collapse;
            font-size:11.5px;
            margin-top:4px;
        }
        table.simple th,
        table.simple td{
            border-bottom:1px solid #e5e7eb;
            padding:4px 6px;
            text-align:left;
        }
        table.simple th{
            background:#f9fafb;
            font-size:10.5px;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:#6b7280;
        }

        .small{
            font-size:11px;
            color:#64748b;
        }

        .tag{
            display:inline-block;
            padding:2px 8px;
            border-radius:999px;
            border:1px solid #d1d5db;
            font-size:10px;
            color:#374151;
            background:#f9fafb;
        }
    </style>
</head>
<body>

  {{-- Brand --}}
  <div class="brandbar">
    @if(!empty($logoData))
      <img src="{{ $logoData }}" alt="LumiCHAT" width="50" height="50"
           style="width:50px;height:50px;border-radius:50%;vertical-align:middle;">
    @endif
    <span class="brand-title">LumiCHAT</span>
  </div>

  <div class="topbar"></div>

  <h1>Student Full Record</h1>
  <div class="meta-row">
      <div>
          Student #{{ $student->id }}
          @if(!empty($student->sis))
              • SIS: <strong>{{ $student->sis }}</strong>
          @endif
          <br>
          Year Covered: <strong>{{ $year }}</strong>
          @if(isset($total))
              &nbsp;•&nbsp; Total Appointments: <strong>{{ $total }}</strong>
          @endif
          @if($peakLabel && $maxVal > 0)
              &nbsp;•&nbsp; Peak Month: <strong>{{ $peakLabel }}</strong>
          @endif
      </div>
      <div class="muted">
          Generated: {{ $generated }}
      </div>
  </div>

  {{-- Student + Year overview --}}
  <div class="cards" style="margin-top:12px;">
      <div class="card">
          <h2>Student Details</h2>
          <table class="info-table">
              <tr>
                  <td class="label" style="width:32%;">Full Name</td>
                  <td><strong>{{ $student->name }}</strong></td>
              </tr>
              <tr>
                  <td class="label">Email</td>
                  <td>{{ $student->email }}</td>
              </tr>
              @if(!empty($student->contact_number))
              <tr>
                  <td class="label">Contact</td>
                  <td>{{ $student->contact_number }}</td>
              </tr>
              @endif
              <tr>
                  <td class="label">Course / Year</td>
                  <td>
                      {{ $student->course ?? '—' }}
                      @if(!empty($student->year_level))
                          &nbsp;•&nbsp; {{ $student->year_level }}
                      @endif
                  </td>
              </tr>
              <tr>
                  <td class="label">Created</td>
                  <td>
                      {{ $student->created_at ? Carbon::parse($student->created_at)->format('F d, Y · h:i A') : '—' }}
                  </td>
              </tr>
              <tr>
                  <td class="label">Updated</td>
                  <td>
                      {{ $student->updated_at ? Carbon::parse($student->updated_at)->format('F d, Y · h:i A') : '—' }}
                  </td>
              </tr>
          </table>
      </div>

 <div class="card">
    <h2>Year Overview</h2>
    <p class="small" style="margin-bottom:4px;">
        Monthly appointments for {{ $student->name }} in {{ $year }}.
    </p>

    @php
        $cells = [];
        foreach ($labels as $idx => $label) {
            $count    = (int) ($series[$idx] ?? 0);
            $cells[]  = $label . ' — ' . $count;
        }

        $cols = 4; // 4 columns, 3 rows
        $rows = max(1, (int) ceil(count($cells) / $cols));
    @endphp

    <table class="simple compact-year" style="margin-top:2px;">
        <tbody>
        @for ($r = 0; $r < $rows; $r++)
            <tr>
                @for ($c = 0; $c < $cols; $c++)
                    @php
                        $i    = $r * $cols + $c;
                        $text = $cells[$i] ?? null;
                    @endphp
                    <td style="padding:2px 4px; font-size:11px;">
                        @if($text)
                            {{ $text }}
                        @endif
                    </td>
                @endfor
            </tr>
        @endfor
        </tbody>
    </table>

    <p class="small" style="margin-top:4px;">
        <span class="tag">Total {{ $year }}: {{ $total ?? 0 }}</span>
        @if($peakLabel && $maxVal > 0)
            &nbsp;<span class="tag">Peak: {{ $peakLabel }} ({{ $maxVal }})</span>
        @endif
    </p>
</div>


  {{-- Appointments --}}
  <div class="box">
      <h2>Appointments</h2>

      @if($appointments->isEmpty())
          <p class="small">No appointment records found for this student.</p>
      @else
          <table class="simple">
              <thead>
                  <tr>
                      <th style="width:10%;">#</th>
                      <th style="width:25%;">Schedule</th>
                      <th style="width:25%;">Counselor</th>
                      <th style="width:17%;">Type</th>
                      <th style="width:15%;">Status</th>
                  </tr>
              </thead>
              <tbody>
                  @foreach($appointments as $appt)
                      @php
                          $dt = $appt->scheduled_at ? Carbon::parse($appt->scheduled_at) : null;
                          $status = strtolower((string) $appt->status);
                          $statusLabel = $status ? ucfirst($status) : 'N/A';

                          $srcRaw = strtolower((string) $appt->appointment_source);
                          $typeLabel = match ($srcRaw) {
                              'walk_in', 'walk-in'       => 'Walk-in Session',
                              'chatbot', 'chat_referral' => 'Chatbot Referral',
                              'manual'                   => 'Manual Booking',
                              'system', 'online'         => 'Online Booking',
                              ''                         => 'System Booking',
                              default                    => ucwords(str_replace('_',' ', $srcRaw)),
                          };
                      @endphp
                      <tr>
                          <td>#{{ $appt->id }}</td>
                          <td>
                              @if($dt)
                                  {{ $dt->format('M d, Y · h:i A') }}
                              @else
                                  —
                              @endif
                          </td>
                          <td>
                              {{ $appt->counselor->name ?? $appt->counselor_name ?? '—' }}
                          </td>
                          <td>{{ $typeLabel }}</td>
                          <td>{{ $statusLabel }}</td>
                      </tr>
                  @endforeach
              </tbody>
          </table>
      @endif
  </div>

  {{-- Case Notes --}}
  <div class="box">
      <h2>Case Notes</h2>

      @if($caseNotes->isEmpty())
          <p class="small">No case notes recorded for this student.</p>
      @else
          <table class="simple">
              <thead>
                  <tr>
                      <th style="width:20%;">Case Note</th>
                      <th style="width:20%;">Created</th>
                      <th style="width:20%;">Counselor</th>
                      <th style="width:20%;">Appointment</th>
                      <th style="width:15%;">Type</th>
                  </tr>
              </thead>
              <tbody>
                  @foreach($caseNotes as $note)
                      @php
                          $created = $note->created_at ? Carbon::parse($note->created_at) : null;

                          $typeValue = $note->note_source;
                          if (!$typeValue && ($note->appointment?->id ?? null)) {
                              $typeValue = 'Appointment';
                          }
                          $typeLabel = $typeValue ?: '—';

                          $appt      = $note->appointment ?? null;
                          $apptId    = $appt?->id;
                          $apptSched = $appt?->scheduled_at;
                          $apptStr   = null;
                          if ($apptSched) {
                              try {
                                  $apptStr = Carbon::parse($apptSched)->format('M d, Y');
                              } catch (\Throwable $e) {
                                  $apptStr = null;
                              }
                          }
                      @endphp
                      <tr>
                          <td>{{ $note->title ?? 'Case Note #'.$note->id }}</td>
                          <td>
                              @if($created)
                                  {{ $created->format('M d, Y · h:i A') }}
                              @else
                                  —
                              @endif
                          </td>
                          <td>{{ $note->counselor->name ?? '—' }}</td>
                          <td>
                              @if($apptId)
                                  #{{ $apptId }}
                                  @if($apptStr)
                                      ({{ $apptStr }})
                                  @endif
                              @else
                                  —
                              @endif
                          </td>
                          <td>{{ $typeLabel }}</td>
                      </tr>
                  @endforeach
              </tbody>
          </table>
      @endif
  </div>

  {{-- Chatbot Sessions --}}
  <div class="box">
      <h2>Chatbot Sessions</h2>

      @if($chatSessions->isEmpty())
          <p class="small">No chatbot sessions found for this student.</p>
      @else
          <table class="simple">
              <thead>
                  <tr>
                      <th style="width:10%;">#</th>
                      <th style="width:15%;">Title</th>
                      <th style="width:27%;">Started</th>
                      <th style="width:28%;">Last Updated</th>
                  </tr>
              </thead>
              <tbody>
                  @foreach($chatSessions as $session)
                      @php
                          $started = $session->created_at ? Carbon::parse($session->created_at) : null;
                          $updated = $session->updated_at ? Carbon::parse($session->updated_at) : null;
                          $title   = $session->title ?? $session->chat_title ?? 'Chat Session';
                      @endphp
                      <tr>
                          <td>#{{ $session->id }}</td>
                          <td>{{ $title }}</td>
                          <td>
                              @if($started)
                                  {{ $started->format('M d, Y · h:i A') }}
                              @else
                                  —
                              @endif
                          </td>
                          <td>
                              @if($updated)
                                  {{ $updated->format('M d, Y · h:i A') }}
                              @else
                                  —
                              @endif
                          </td>
                      </tr>
                  @endforeach
              </tbody>
          </table>
      @endif
  </div>

  <div class="small" style="margin-top:14px;">
      LumiCHAT • Tagoloan Community College — Confidential student support record.
  </div>

  <script type="text/php">
  if (isset($pdf)) {
      $font  = $fontMetrics->get_font("DejaVu Sans", "normal");
      $size  = 9;
      $w     = $pdf->get_width();
      $h     = $pdf->get_height();
      $text  = "Page {PAGE_NUM} of {PAGE_COUNT}";
      $x     = $w - 72;
      $y     = $h - 28;
      $pdf->page_text($x, $y, $text, $font, $size, [0,0,0]);
  }
  </script>

</body>
</html>
