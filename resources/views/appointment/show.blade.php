{{-- resources/views/appointment/show.blade.php --}}
@extends('layouts.app')
@section('title', 'Appointment #'.$appointment->id)

@section('content')
  <div class="max-w-3xl mx-auto py-8 px-4">
  @if(session('success'))
    @php
      $confirmedAt = \Carbon\Carbon::parse($appointment->scheduled_at);
    @endphp
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
          icon: 'success',
          title: 'Appointment confirmed',
          html: `
            <div style="text-align:left;font-size:14px;">
              <p>{{ e(session('success')) }}</p>
              <div style="margin-top:10px;">
                <div><strong>Date:</strong> {{ $confirmedAt->format('l, M d, Y') }}</div>
                <div><strong>Time:</strong> {{ $confirmedAt->format('g:i A') }}</div>
              </div>
            </div>
          `,
          confirmButtonText: 'OK'
        });
      });
    </script>
  @endif
 
  {{-- Card --}}
  <div id="appointmentCard" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">

    @php
      $styles = [
        'pending'   => ['chip'=>'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200','dot'=>'bg-amber-500','pulse'=>true],
        'confirmed' => ['chip'=>'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200','dot'=>'bg-blue-500','pulse'=>false],
        'canceled'  => ['chip'=>'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200','dot'=>'bg-rose-500','pulse'=>false],
        'completed' => ['chip'=>'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200','dot'=>'bg-emerald-500','pulse'=>false],
      ];
      $s = $styles[$appointment->status] ?? ['chip'=>'bg-gray-100 text-gray-700','dot'=>'bg-gray-400','pulse'=>false];

      $now   = \Carbon\Carbon::now();
      $start = \Carbon\Carbon::parse($appointment->scheduled_at);
      $mins  = $now->diffInMinutes($start, false);
      $abs   = abs($mins);
      $d     = intdiv($abs, 1440); $r=$abs%1440; $h=intdiv($r,60); $m=$r%60;
      $parts = []; if ($d) $parts[] = "{$d}d"; if ($h) $parts[] = "{$h}h"; if (!$d && $m) $parts[] = "{$m}m";
      $countdown = $mins === 0 ? 'Starting now' : ($mins > 0 ? ('Starts in '.implode(' ', $parts)) : (implode(' ', $parts).' ago'));
      $countColor = $mins >= 0 ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200'
                               : 'bg-gray-100 text-gray-700 dark:bg-gray-700/40 dark:text-gray-200';

      $noCounselor = empty($appointment->counselor_name);
    @endphp

    {{-- Header row: title + actions (PDF + status pill) --}}
    <div class="flex items-start justify-between gap-4">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
        Appointment #{{ $appointment->id }}
      </h2>

      <div class="flex items-center gap-3">
        {{-- Download PDF (top-right) --}}
        <a href="{{ route('appointment.show.export.pdf', $appointment->id) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 shadow-sm
                  hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
          </svg>
          Download PDF
        </a>

        {{-- Status pill --}}
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $s['chip'] }}">
          <span class="h-1.5 w-1.5 rounded-full {{ $s['dot'] }} {{ $s['pulse'] ? 'animate-pulse' : '' }}"></span>
          {{ ucfirst($appointment->status) }}
        </span>
      </div>
    </div>

    <div class="mt-3">
      <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium {{ $countColor }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 8a1 1 0 011 1v3.382l2.447 1.224a1 1 0 11-.894 1.788l-3-1.5A1 1 0 0111 13V9a1 1 0 011-1z"></path>
          <path fill-rule="evenodd" d="M12 22a10 10 0 100-20 10 10 0 000 20zm0-2a8 8 0 110-16 8 8 0 010 16z" clip-rule="evenodd"></path>
        </svg>
        {{ $countdown }}
      </span>
    </div>

    {{-- Info --}}
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <h3 class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Counselor</h3>

        @if ($noCounselor)
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[13px] text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
            Awaiting admin assignment
          </div>
        @else
          <div class="space-y-1 text-sm">
            <p class="text-gray-900 dark:text-gray-100 font-medium">{{ $appointment->counselor_name }}</p>
            @if(!empty($appointment->counselor_email))
              <p class="text-gray-600 dark:text-gray-300">{{ $appointment->counselor_email }}</p>
            @endif
            @if(!empty($appointment->counselor_phone))
              <p class="text-gray-600 dark:text-gray-300">{{ $appointment->counselor_phone }}</p>
            @endif
          </div>
        @endif
      </div>
        
      <div>
        <h3 class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Scheduled</h3>
        <p class="text-sm text-gray-900 dark:text-gray-100 font-medium">
          {{ \Carbon\Carbon::parse($appointment->scheduled_at)->format('l, M d, Y · g:i A') }}
        </p>
      </div>
    </div>
    
    {{-- Admin/Counselor note to the student --}}
    @if(!empty($appointment->note))
      <div class="mt-6 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
        <div class="flex items-center gap-2 text-indigo-800 font-medium text-sm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20zM11 6h2v7h-2V6zm0 9h2v2h-2v-2z"/>
          </svg>
          Note from Counseling Office
        </div>
        <div class="mt-2 text-slate-800 text-sm leading-relaxed">
          {!! nl2br(e($appointment->note)) !!}
        </div>
      </div>
    @endif

    @if(!empty($appointment->final_note))
      <div class="mt-6">
        <h3 class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
          Final Diagnosis / Counselor Note
        </h3>
        <div class="rounded-lg p-3 text-sm text-gray-700 dark:text-gray-200 bg-indigo-50/50 dark:bg-indigo-900/20">
          {!! nl2br(e($appointment->final_note)) !!}
          @if(!empty($appointment->finalized_at))
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
              Updated {{ \Carbon\Carbon::parse($appointment->finalized_at)->format('M d, Y g:i A') }}
            </div>
          @endif
        </div>
      </div>
    @endif

    {{-- Counselor change request status (inside card, under notes) --}}
    @if(isset($changeRequest) && $changeRequest)
      @php
        $st = $changeRequest->status;
        $pill = [
          'requested' => ['class'=>'bg-amber-100 text-amber-800','label'=>'Pending review'],
          'approved'  => ['class'=>'bg-emerald-100 text-emerald-800','label'=>'Approved'],
          'declined'  => ['class'=>'bg-rose-100 text-rose-800','label'=>'Declined'],
          'canceled'  => ['class'=>'bg-slate-100 text-slate-700','label'=>'Canceled'],
        ][$st] ?? ['class'=>'bg-slate-100 text-slate-700','label'=>ucfirst($st)];

        $hasPref   = !empty($changeRequest->preference_counselor_id);
        $adminNote = trim((string)($changeRequest->decision_notes ?? $changeRequest->decline_note ?? ''));
        $handledAt = $changeRequest->handled_at
            ? \Carbon\Carbon::parse($changeRequest->handled_at)->format('M d, Y · g:i A')
            : null;
      @endphp

      <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">

        {{-- TOP ROW: STATUS + PREFERRED + TIMESTAMP --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

          {{-- Status pill + preferred counselor --}}
          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center h-8 px-3 rounded-full text-xs font-medium ring-1 ring-slate-200 {{ $pill['class'] }}">
              {{ $pill['label'] }}
            </span>

            <span class="text-xs text-slate-600">
              Preferred counselor:
              @if($hasPref)
                {{ $changeRequest->preferred_counselor_name ?? ('Counselor #'.$changeRequest->preference_counselor_id) }}
              @else
                No preference
              @endif
            </span>
          </div>

          {{-- Right-side status message --}}
          @if($changeRequest->status === 'requested')
            <span class="text-[11px] text-slate-500">
              Your request was sent to the admin.
            </span>

          @elseif($changeRequest->status === 'approved')
            <div class="flex flex-col sm:items-end text-[11px]">
              <span class="text-emerald-600">
                Approved. A new counselor will be assigned.
              </span>
              @if($handledAt)
                <span class="mt-0.5 text-slate-400">Approved at: {{ $handledAt }}</span>
              @endif
            </div>

          @elseif($changeRequest->status === 'declined')
            <div class="flex flex-col sm:items-end text-[11px]">
              <span class="text-rose-600">Declined by admin.</span>
              @if($handledAt)
                <span class="mt-0.5 text-slate-400">Declined at: {{ $handledAt }}</span>
              @endif
            </div>
          @endif
        </div> {{-- END TOP ROW --}}

        {{-- ADMIN NOTE BOX — FULL WIDTH + BELOW --}}
        @if($changeRequest->status === 'declined' && $adminNote !== '')
          <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2">
            <div class="flex items-center gap-2 text-[11px] font-semibold tracking-wide text-rose-800 uppercase mb-1">
              Admin Note
            </div>
            <div class="text-[13px] text-rose-900 leading-relaxed">
              {!! nl2br(e($adminNote)) !!}
            </div>
          </div>
        @endif
      </div>
    @endif

    {{-- Hidden footer for print popup --}}
    <div id="printFooter" class="hidden">
      <div style="margin-top:18px;font-size:12px;color:#555;">
        <strong>LumiCHAT</strong> · Appointment Report<br>
        Generated on {{ now()->format('M d, Y g:i A') }}
      </div>
    </div>
  </div>

  {{-- Actions --}}
  @php
      $isFuture  = \Carbon\Carbon::parse($appointment->scheduled_at)->gt(now());
      $canCancel = ($appointment->status === 'pending') && $isFuture;
      $cannotReason = match (true) {
          $appointment->status !== 'pending' => 'Only pending appointments can be canceled.',
          !$isFuture => 'This appointment has already started/passed.',
          default => 'Cancel not available.',
      };

      $hasCounselor = !empty($appointment->counselor_id);
      $isFuture24   = \Carbon\Carbon::parse($appointment->scheduled_at)->gt(now()->addHours(24));

      // ONLY confirmed appointments can request counselor change
      $eligibleForChange = ($appointment->status === 'confirmed')
                          && $hasCounselor
                          && $isFuture24;

      // Student can confirm only when pending + future
      $canConfirm = $appointment->status === 'pending'
          && \Carbon\Carbon::parse($appointment->scheduled_at)->isFuture();
  @endphp

  <div class="mt-6">
    <div class="flex flex-wrap items-center gap-3">

      {{-- Confirm appointment (only when pending + future) --}}
      @if ($canConfirm)
        <form method="POST"
              action="{{ route('appointment.confirm', $appointment->id) }}"
              onsubmit="return confirmStudentConfirm(event, this)"
              class="inline-flex">
          @csrf
          @method('PATCH')
          <button type="submit"
                  class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">
            Confirm appointment
          </button>
        </form>
      @endif

      {{-- Close --}}
      <a id="btn-appt-close"
         href="{{ route('appointment.history') }}"
         aria-label="Back to appointment history"
         class="inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
        Close
      </a>

      {{-- Cancel --}}
      @if ($canCancel)
        <form method="POST"
              action="{{ route('appointment.cancel', $appointment->id) }}"
              onsubmit="return confirmStudentCancel(event, this)">
          @csrf
          @method('PATCH')
          <button type="submit"
                  class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-white hover:bg-rose-700">
            Cancel
          </button>
        </form>
      @else
        <button type="button" disabled title="{{ $cannotReason }}"
                class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-white opacity-50 cursor-not-allowed">
          Cancel
        </button>
      @endif

      {{-- Request different counselor button (only when NO existing change request) --}}
      @if(!isset($changeRequest) || !$changeRequest)
        @if($eligibleForChange)
          <button type="button"
                  onclick="crOpen()"
                  class="inline-flex items-center h-10 rounded-lg bg-violet-600 px-4 text-white hover:bg-violet-700">
            Request different counselor
          </button>
        @else
          <button type="button" disabled
                  title="Available only for confirmed appointments, ≥24h before session, with an assigned counselor."
                  class="inline-flex items-center h-10 rounded-lg bg-violet-600 px-4 text-white opacity-50 cursor-not-allowed">
            Request different counselor
          </button>
        @endif
      @endif

    </div>
  </div>

  {{-- Change counselor dialog --}}
  <dialog id="crModal" class="rounded-2xl p-0 w-[720px] max-w-[96vw] backdrop:bg-slate-900/60 backdrop:backdrop-blur">
    <div class="panel rounded-2xl bg-white shadow-xl max-w-[720px] w-full">
      <form method="POST" action="{{ route('appointment.request_change', $appointment->id) }}" class="p-5">
        @csrf

        <div class="flex items-center justify-between mb-2">
          <h3 class="text-lg font-semibold">Request a different counselor</h3>
          <button type="button" onclick="crClose()" class="p-1.5 rounded-lg hover:bg-slate-100">
            ✕
          </button>
        </div>
        <p class="text-sm text-slate-600 mb-4">
          Your request is private to the admin. The current counselor won’t see your reason text.
        </p>

        {{-- Reason (required) --}}
        <label class="block text-xs font-medium text-slate-600 mb-1">Reason <span class="text-rose-600">*</span></label>
        <select id="crReason" name="reason_code" required
                class="w-full h-11 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-violet-500 mb-3">
          <option value="" hidden>Choose a reason</option>
          <option value="comfort_mismatch">Comfort or rapport mismatch</option>
          <option value="communication_style">Communication style mismatch</option>
          <option value="language">Language preference</option>
          <option value="conflict">Conflict of interest</option>
          <option value="gender_preference">Gender preference</option>
          <option value="cultural_preference">Cultural or personal preference</option>
          <option value="other">Other</option>
        </select>

        {{-- Additional explanation (optional, required only for "Other") --}}
        <div class="flex items-center justify-between">
              <label data-cr-extra-label class="block text-xs font-medium text-slate-600">
                Additional explanation (optional)
              </label>
              <span id="crCount" class="text-[11px] text-slate-500">0/300</span>
        </div>
        <textarea id="crText" name="reason_text" rows="4" maxlength="300"
                  class="w-full border border-slate-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-violet-500 mb-2"
                  placeholder="Be specific (e.g., comfort level, language, scheduling, conflict)."></textarea>

        {{-- Preferred counselor (optional) --}}
        @php
          $allCounselors = collect($counselors ?? []);
          $freeIdsArr    = is_array($freeIds ?? null) ? $freeIds : [];
          $availablePref = $allCounselors->filter(fn($c) => in_array($c->id, $freeIdsArr, true));
          $busyPref      = $allCounselors->reject(fn($c) => in_array($c->id, $freeIdsArr, true));

          $preferredId = optional($changeRequest)->preference_counselor_id ?? null;
        @endphp

        <label class="block text-xs font-medium text-slate-600 mb-1">
          Preferred counselor <span class="text-rose-600">*</span>
        </label>
        <select id="crCounselor" name="preference_counselor_id" required
                class="w-full h-11 bg-white border border-slate-200 rounded-xl px-3 text-sm mb-1">
          <option value="" hidden>Select a counselor</option>

          @if($availablePref->isNotEmpty())
            <optgroup label="Available ({{ $availablePref->count() }})">
              @foreach($availablePref as $c)
                <option value="{{ $c->id }}" @selected($preferredId == $c->id)>
                  {{ $c->name }}
                </option>
              @endforeach
            </optgroup>
          @endif

          @if($busyPref->isNotEmpty())
            <optgroup label="Busy / Not selectable ({{ $busyPref->count() }})" disabled>
              @foreach($busyPref as $c)
                <option value="{{ $c->id }}" disabled>
                  {{ $c->name }} (Currently booked for this time)
                </option>
              @endforeach
            </optgroup>
          @endif
        </select>
        
        {{-- Submit --}}
        <p class="text-[12px] text-slate-500 mb-4">
          Available counselors are currently free for your selected date and time.
          Counselors under “Busy / Not selectable” are already booked for this exact time,
          so they appear disabled in the list.
        </p>

        <div class="mt-3 flex items-center justify-end gap-2">
          <button type="button" onclick="crClose()"
                  class="h-10 inline-flex items-center rounded-lg bg-slate-100 px-4 text-slate-700">
            Cancel
          </button>
          <button id="crSubmit" type="submit" disabled
                  class="h-10 inline-flex items-center rounded-lg bg-violet-600 px-4 text-white disabled:opacity-50 disabled:cursor-not-allowed">
            Submit request
          </button>
        </div>
      </form>
    </div>
  </dialog>

@endsection

@push('scripts')
{{-- Dialog open/close + backdrop click + ESC --}}
<script>
(function () {
  const dialogEl = document.getElementById('crModal');
  const panelEl  = dialogEl?.querySelector('.panel');

  if (!dialogEl) return;

  window.crOpen = function crOpen() {
    if (dialogEl.open) return;
    dialogEl.showModal();
    requestAnimationFrame(() => dialogEl.classList.add('animate-in'));
  };

  window.crClose = function crClose() {
    dialogEl.classList.remove('animate-in');
    dialogEl.classList.add('animate-out');
    setTimeout(() => {
      dialogEl.classList.remove('animate-out');
      dialogEl.close();
    }, 160);
  };

  dialogEl.addEventListener('cancel', (e) => {
    e.preventDefault();
    window.crClose();
  });

  dialogEl.addEventListener('click', (e) => {
    if (!panelEl) return;
    const rect = panelEl.getBoundingClientRect();
    const inside =
      e.clientX >= rect.left && e.clientX <= rect.right &&
      e.clientY >= rect.top  && e.clientY <= rect.bottom;
    if (!inside) window.crClose();
  });
})();
</script>

{{-- Cancel appointment + print --}}
<script>
function confirmStudentCancel(e, form) {
  e.preventDefault();
  Swal.fire({
    icon: 'warning',
    title: 'Cancel this appointment?',
    text: 'This action cannot be undone.',
    showCancelButton: true,
    confirmButtonText: 'Yes, cancel',
    cancelButtonText: 'No, keep it',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    reverseButtons: true,
    focusCancel: true
  }).then(res => { if (res.isConfirmed) form.submit(); });
  return false;
}

function confirmStudentConfirm(e, form) {
  e.preventDefault();
  Swal.fire({
    icon: 'question',
    title: 'Confirm this appointment?',
    text: 'Once confirmed, the counseling office will be notified.',
    showCancelButton: true,
    confirmButtonText: 'Yes, confirm',
    cancelButtonText: 'No, not yet',
    reverseButtons: true,
    focusCancel: true,
  }).then(res => {
    if (res.isConfirmed) form.submit();
  });
  return false;
}

function printAppointmentCard() {
  const cardEl   = document.getElementById('appointmentCard');
  const footerEl = document.getElementById('printFooter');
  if (!cardEl) { window.print(); return; }

  const docHtml = `
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8">
        <title>Appointment Report</title>
        <style>
          body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 24px; color:#111827; }
          .report { max-width: 800px; margin: 0 auto; }
          .stamp { margin-top: 18px; font-size: 12px; color:#6b7280; text-align:left; }
        </style>
      </head>
      <body>
        <div class="report">
          ${cardEl.innerHTML}
          <div class="stamp">${footerEl ? footerEl.innerHTML : ''}</div>
        </div>
        <script>window.onload = () => { window.print(); setTimeout(() => window.close(), 300); };<\/script>
      </body>
    </html>
  `;
  const w = window.open('', '_blank', 'width=900,height=650');
  if (!w) return;
  w.document.open(); w.document.write(docHtml); w.document.close();
}
</script>

{{-- Change-request form validation + SweetAlert confirm --}}
<script>
(function () {
  const reason    = document.getElementById('crReason');
  const text      = document.getElementById('crText');
  const count     = document.getElementById('crCount');
  const submit    = document.getElementById('crSubmit');
  const counselor = document.getElementById('crCounselor');
  const dialogEl  = document.getElementById('crModal');
  const form      = dialogEl?.querySelector('form');
  const labelEl   = document.querySelector('[data-cr-extra-label]');

  if (!reason || !text || !submit || !form || !count || !counselor) return;

  const sanitizeWhileTyping = (s) => {
    s = s.replace(/<[^>]*>/g, '');
    s = s.replace(/https?:\/\/\S+/gi, '[link removed]');
    s = s.replace(/[\x00-\x1F\x7F]/g, ' ');
    s = s.replace(/\s{3,}/g, '  ');
    return s;
  };

  const validate = () => {
    const reasonVal    = reason.value.trim();
    const counselorVal = counselor.value.trim();
    const trimmed      = text.value.trim();

    let ok = false;

    if (!reasonVal || !counselorVal) {
      ok = false;
    } else if (reasonVal === 'other') {
      ok = trimmed.length >= 10;
    } else {
      ok = true;
    }

    submit.disabled   = !ok;
    count.textContent = text.value.length + '/300';

    if (labelEl) {
      if (reasonVal === 'other') {
        labelEl.innerHTML = 'Additional explanation <span class="text-rose-600">*</span>';
      } else {
        labelEl.textContent = 'Additional explanation (optional)';
      }
    }
  };

  text.addEventListener('input', () => {
    const cur   = text.value;
    const clean = sanitizeWhileTyping(cur).slice(0, 300);
    if (clean !== cur) {
      const pos = text.selectionStart ?? clean.length;
      text.value = clean;
      text.setSelectionRange(pos, pos);
    }
    validate();
  });

  reason.addEventListener('change', validate);
  counselor.addEventListener('change', validate);

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const reasonVal    = reason.value.trim();
    const counselorVal = counselor.value.trim();

    if (!reasonVal) {
      Swal.fire({
        icon: 'warning',
        title: 'Select a reason',
        text: 'Please choose why you want to change counselor.',
      });
      return;
    }

    if (!counselorVal) {
      Swal.fire({
        icon: 'warning',
        title: 'Select a counselor',
        text: 'Please choose your preferred counselor for this request.',
      });
      return;
    }

    let v = text.value.replace(/\s{2,}/g, ' ').trim();
    text.value = v.slice(0, 300);

    if (reasonVal === 'other' && text.value.length < 10) {
      Swal.fire({
        icon: 'warning',
        title: 'Add a short explanation',
        text: 'Please describe your reason in a bit more detail (at least 10 characters).',
      });
      return;
    }

    Swal.fire({
      icon: 'question',
      title: 'Confirm Your Request',
      html: `
        <p class="text-slate-600 text-sm">
          You are allowed <strong>only one counselor-change request</strong> for this appointment.<br><br>
          After submitting, you <strong>cannot undo or submit another request</strong> for this session.<br><br>
          Are you sure you want to continue?
        </p>
      `,
      showCancelButton: true,
      confirmButtonText: 'Yes, submit',
      cancelButtonText: 'No, review first',
      reverseButtons: true,
      focusCancel: true,
      target: '#crModal',
      backdrop: false,
      showClass: { popup: 'swal2-show' },
      hideClass: { popup: 'swal2-hide' }
    }).then((result) => {
      if (!result.isConfirmed) return;

      submit.disabled    = true;
      submit.textContent = 'Submitting…';
      form.submit();
    });
  });

  validate();
})();
</script>

<style>
  #crModal::backdrop{
    background: rgba(2, 6, 23, 0.45);
    backdrop-filter: blur(4px);
    animation: lumiBackdropIn .18s ease-out both;
  }
  @keyframes lumiBackdropIn { from{opacity:0} to{opacity:1} }

  #crModal{
    border: 0;
    padding: 0;
    overflow: visible;
  }

  #crModal .panel{
    transform: translateY(8px) scale(.98);
    opacity: 0;
    transition: transform .18s ease-out, opacity .18s ease-out;
  }
  #crModal.animate-in .panel{
    transform: translateY(0) scale(1);
    opacity: 1;
  }
  #crModal.animate-out .panel{
    transform: translateY(8px) scale(.98);
    opacity: 0;
    transition: transform .14s ease-in, opacity .14s ease-in;
  }
</style>
@endpush
