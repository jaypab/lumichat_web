{{-- resources/views/appointment/show.blade.php --}}
@extends('layouts.app')
@section('title', 'Appointment #'.$appointment->id)

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">

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
  <a href="{{ route('appointment.history') }}"
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

  {{-- NEW: Download PDF (replaces Print) --}}
  <a href="{{ route('appointment.show.export.pdf', $appointment->id) }}"
     class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-white shadow-sm
            hover:bg-emerald-700 active:scale-[.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
     title="Download appointment as PDF" aria-label="Download appointment as PDF">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
    </svg>
    Download PDF
  </a>
</div>

@endsection

@push('scripts')
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
@endpush
