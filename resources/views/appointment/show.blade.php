{{-- resources/views/appointment/show.blade.php --}}
@extends('layouts.app')
@section('title', 'Appointment #'.$appointment->id)

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">
@if(session('success'))
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      Swal.fire({ icon:'success', title:'Success', text:@json(session('success')), timer:2200, showConfirmButton:false });
    });
  </script>
@endif
 
  {{-- Card --}}
  <div id="appointmentCard" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">

    <div class="flex items-start justify-between">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
        Appointment #{{ $appointment->id }}
      </h2>

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

      <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $s['chip'] }}">
        <span class="h-1.5 w-1.5 rounded-full {{ $s['dot'] }} {{ $s['pulse'] ? 'animate-pulse' : '' }}"></span>
        {{ ucfirst($appointment->status) }}
      </span>
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
  @endphp

  <div class="mt-6 flex items-center gap-3">
    <a id="btn-appt-close"
    href="{{ route('appointment.history') }}"
    aria-label="Back to appointment history"
    class="inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
    Close
  </a>

  @if ($canCancel)
    <form method="POST" action="{{ route('appointment.cancel', $appointment->id) }}" onsubmit="return confirmStudentCancel(event, this)">
      @csrf
      @method('PATCH')
      <button type="submit" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-white hover:bg-rose-700">
        Cancel
      </button>
    </form>
  @else
    <button type="button" disabled title="{{ $cannotReason }}"
            class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-white opacity-50 cursor-not-allowed">
      Cancel
    </button>
  @endif

 {{-- Single appointment -> PDF --}}
<a href="{{ route('appointment.show.export.pdf', $appointment->id) }}"
   target="_blank" rel="noopener"
   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-white shadow-sm
          hover:bg-emerald-700 active:scale-[.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
   title="Download appointment as PDF" aria-label="Download appointment as PDF">
  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
  </svg>
  Download PDF
</a>

@php
  // show the button only when a counselor is already assigned
  // AND the session is more than 24h away
  $eligibleForChange = !empty($appointment->counselor_id)
                       && \Carbon\Carbon::parse($appointment->scheduled_at)->gt(now()->addHours(24));
@endphp

@if(isset($changeRequest) && $changeRequest)
  @php
    $st = $changeRequest->status;
    $pill = [
      'requested' => ['class'=>'bg-amber-100 text-amber-800','label'=>'Pending review'],
      'approved'  => ['class'=>'bg-emerald-100 text-emerald-800','label'=>'Approved'],
      'declined'  => ['class'=>'bg-rose-100 text-rose-800','label'=>'Declined'],
      'canceled'  => ['class'=>'bg-slate-100 text-slate-700','label'=>'Canceled'],
    ][$st] ?? ['class'=>'bg-slate-100 text-slate-700','label'=>ucfirst($st)];
  @endphp

  <span class="inline-flex items-center h-10 px-3 rounded-lg text-sm font-medium ring-1 ring-slate-200 {{ $pill['class'] }}">
    {{ $pill['label'] }}
  </span>

@elseif($eligibleForChange)
  <button type="button"
          onclick="crOpen()"
          class="inline-flex items-center h-10 rounded-lg bg-violet-600 px-4 text-white hover:bg-violet-700">
    Request different counselor
  </button>
@else
  <button type="button" disabled
          title="Available after admin assigns a counselor and ≥24h before session."
          class="inline-flex items-center h-10 rounded-lg bg-violet-600 px-4 text-white opacity-50 cursor-not-allowed">
    Request different counselor
  </button>
@endif
</div>

<dialog id="crModal" class="rounded-2xl p-0 w-[720px] max-w-[96vw] backdrop:bg-slate-900/60 backdrop:backdrop-blur">
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
      <option value="uncomfortable">I feel uncomfortable</option>
      <option value="language">Language preference</option>
      <option value="schedule">Schedule mismatch</option>
      <option value="conflict">Conflict of interest</option>
      <option value="other">Other</option>
    </select>

    {{-- Additional explanation (required) --}}
    <div class="flex items-center justify-between">
      <label class="block text-xs font-medium text-slate-600">Additional explanation <span class="text-rose-600">*</span></label>
      <span id="crCount" class="text-[11px] text-slate-500">0/300</span>
    </div>
    <textarea id="crText" name="reason_text" rows="4" maxlength="300" required
              class="w-full border border-slate-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-violet-500 mb-2"
              placeholder="Be specific (e.g., comfort level, language, scheduling, conflict)."></textarea>

    {{-- Preferred counselor (optional) --}}
    <label class="block text-xs font-medium text-slate-600 mb-1">Preferred counselor (optional)</label>
    <select name="preferred_counselor_id"
            class="w-full h-11 bg-white border border-slate-200 rounded-xl px-3 text-sm mb-1">
      <option value="">No preference</option>
      {{-- Use real IDs from your tbl_counselors --}}
      <option value="1">Nelson L. Englatera</option>
      <option value="2">Juvy C. Magbanua</option>
      <option value="3">Jason D. Ang</option>
      <option value="4">Chrizelle Mae Gem A. Costillas</option>
    </select>
    <p class="text-[12px] text-slate-500 mb-4">
      Note: This request will first undergo admin review before any counselor change is made.
      The admin will check the availability of your selected counselor for your scheduled time.
      You’ll receive an update once it’s approved — please check your Gmail or the app regularly for notifications.
    </p>

    <div class="mt-3 flex items-center justify-end gap-2">
      <button type="button" onclick="crClose()"
              class="h-10 inline-flex items-center rounded-lg bg-slate-100 px-4 text-slate-700">Cancel</button>
      <button id="crSubmit" type="submit" disabled
              class="h-10 inline-flex items-center rounded-lg bg-violet-600 px-4 text-white disabled:opacity-50 disabled:cursor-not-allowed">
        Submit request
      </button>
    </div>
  </form>
</dialog>

@endsection

@push('scripts')
<script>
  const crDialog = document.getElementById('crModal');

  function openCR() {
    if (!crDialog.open) {
      crDialog.showModal();
      // next frame -> play enter animation
      requestAnimationFrame(() => crDialog.classList.add('animate-in'));
    }
  }

  function closeCR() {
    // play exit animation then close
    crDialog.classList.remove('animate-in');
    crDialog.classList.add('animate-out');
    setTimeout(() => {
      crDialog.classList.remove('animate-out');
      crDialog.close();
    }, 160);
  }

  // ESC closes with animation
  crDialog.addEventListener('cancel', (e) => {
    e.preventDefault(); // prevent instant close
    closeCR();
  });

  // Click outside panel to close
  crDialog.addEventListener('click', (e) => {
    const rect = crDialog.querySelector('.panel')?.getBoundingClientRect();
    if (!rect) return;
    const inPanel =
      e.clientX >= rect.left && e.clientX <= rect.right &&
      e.clientY >= rect.top  && e.clientY <= rect.bottom;
    if (!inPanel) closeCR();
  });
</script>

<script>
(function () {
  const reasonSel = document.getElementById('reason_code');
  const reasonTxt = document.getElementById('reason_text');
  const submitBtn = document.getElementById('crSubmit');
  const dialogEl  = document.getElementById('crModal');

  function isValid() {
    const selOK = !!(reasonSel && reasonSel.value);
    const txtLen = (reasonTxt?.value || '').trim().length;
    const txtOK = txtLen >= 10;
    return selOK && txtOK;
  }

  function updateState() {
    if (!submitBtn) return;
    submitBtn.disabled = !isValid();
  }

  reasonSel?.addEventListener('change', updateState);
  reasonTxt?.addEventListener('input', updateState);

  // when opening the dialog ensure initial state is correct
  window.openCR = function openCR() {
    dialogEl?.showModal();
    requestAnimationFrame(() => dialogEl?.classList.add('animate-in'));
    updateState();
  };

  window.closeCR = function closeCR() {
    dialogEl?.classList.remove('animate-in');
    dialogEl?.classList.add('animate-out');
    setTimeout(() => {
      dialogEl?.classList.remove('animate-out');
      dialogEl?.close();
    }, 160);
  };

  // safety: disable during submit to block double-clicks
  dialogEl?.querySelector('form')?.addEventListener('submit', function () {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting…';
    }
  });

  // initialize once for good measure
  document.addEventListener('DOMContentLoaded', updateState);
})();

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
function crOpen(){ document.getElementById('crModal').showModal(); }
function crClose(){ document.getElementById('crModal').close(); }

// tie to your "Request different counselor" button
// onclick="crOpen()"

(function(){
  const reason = document.getElementById('crReason');
  const text   = document.getElementById('crText');
  const count  = document.getElementById('crCount');
  const submit = document.getElementById('crSubmit');

  // keep it gentle while typing: remove tags & control chars only
  const sanitizeWhileTyping = (s) => {
    s = s.replace(/<[^>]*>/g, '');                // strip tags
    s = s.replace(/https?:\/\/\S+/gi, '[link removed]'); // redact URLs
    s = s.replace(/[\x00-\x1F\x7F]/g, ' ');       // control chars -> space
    s = s.replace(/\s{3,}/g, '  ');               // collapse 3+ spaces to 2, but allow single trailing space
    return s;
  };

  const validate = () => {
    const ok = reason.value && text.value.trim().length >= 10;
    submit.disabled = !ok;
    count.textContent = text.value.length + '/300';
  };

  text.addEventListener('input', () => {
    const cur = text.value;
    const clean = sanitizeWhileTyping(cur).slice(0, 300);
    if (clean !== cur) {
      const pos = text.selectionStart;
      text.value = clean;
      text.setSelectionRange(pos, pos);
    }
    validate();
  });

  reason.addEventListener('change', validate);

  // Final hard cleanup ONLY when submitting
  const form = document.querySelector('#crModal form');
  form?.addEventListener('submit', () => {
    // now we can trim ends and normalize spaces safely
    let v = text.value.replace(/\s{2,}/g, ' ').trim();
    text.value = v.slice(0, 300);
    submit.disabled = true;
    submit.textContent = 'Submitting…';
  });

  validate();
})();
</script>

<style>
  /* Backdrop: dark veil + blur */
  #crModal::backdrop{
    background: rgba(2, 6, 23, 0.45); /* slate-950/45 */
    backdrop-filter: blur(4px);
    animation: lumiBackdropIn .18s ease-out both;
  }
  @keyframes lumiBackdropIn { from{opacity:0} to{opacity:1} }

  /* Dialog chrome */
  #crModal{
    border: 0;
    padding: 0;
    overflow: visible; /* allow rounded corners to render cleanly */
  }

  /* Panel enter/exit */
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
