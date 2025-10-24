{{-- resources/views/Counselor_Interface/appointments/show.blade.php --}}
@extends('layouts.admin')
@section('title', 'Appointment #'.$appointment->id)
@section('page_title', 'Appointment Details')

@php
  use Carbon\Carbon;
  use Illuminate\Support\Str;

  $start    = Carbon::parse($appointment->scheduled_at)->second(0);
  $end      = $start->copy()->addMinutes(60); // 60-min slot
  $now      = Carbon::now();
  $bookedAt = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;

  // Friendly “when” text
  $when = $now->isBefore($start)
      ? 'Starts in '.$start->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
      : 'Started '.$start->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW]);

  // Status chips (include no_show)
  $badgeMap = [
    'pending'   => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'canceled'  => 'bg-rose-100 text-rose-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'no_show'   => 'bg-rose-100 text-rose-800',
  ];
  $dotMap = [
    'pending'   => 'bg-amber-500',
    'confirmed' => 'bg-blue-500',
    'canceled'  => 'bg-rose-500',
    'completed' => 'bg-emerald-500',
    'no_show'   => 'bg-rose-500',
  ];
  $status = strtolower((string)$appointment->status);
  $cls = $badgeMap[$status] ?? 'bg-slate-200 text-slate-700';
  $dot = $dotMap[$status] ?? 'bg-slate-500';

  // Button gating
  $isPending   = $status === 'pending';
  $isConfirmed = $status === 'confirmed';

  $canConfirm = $isPending   && $now->lt($start);      // before start only
  $canDone    = $isConfirmed && $now->gte($start);     // at/after start
  $canNoShow  = ($isPending || $isConfirmed) && $now->gte($end);

  // Nice label for "no_show"
  $statusLabel = $status === 'no_show' ? 'No Show' : ucfirst($status);
@endphp

@section('content')
<div class="max-w-6xl mx-auto p-6">
  <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <span class="pointer-events-none absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>

    {{-- Header row --}}
    <div class="px-6 pt-5">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <h2 class="text-[20px] font-semibold text-slate-900 truncate">
              Appointment #{{ $appointment->id }}
            </h2>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $cls }}">
              <span class="inline-block w-1.5 h-1.5 rounded-full {{ $dot }} mr-1.5"></span>
              {{ $statusLabel }}
            </span>
          </div>
          <div class="mt-1 text-sm text-slate-500 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"></path>
              <path d="M12 22a10 10 0 110-20 10 10 0 010 20zm0-2a8 8 0 100-16 8 8 0 010 16z"></path>
            </svg>
            {{ $when }}
          </div>
        </div>

        <div class="flex items-center gap-3">
          <a href="{{ route('admin.appointments.index') }}"
             class="inline-flex items-center gap-2 h-10 px-3.5 rounded-xl bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">
            Back
          </a>
          <a href="{{ route('counselor.appointments.export.show.pdf', $appointment->id) }}"
             target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99]">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
            </svg>
            Download PDF
          </a>
        </div>
      </div>

      {{-- Action buttons --}}
      <div class="mt-3 flex items-center gap-3">
        {{-- Confirm --}}
        <form method="POST"
              action="{{ route('counselor.appointments.status', $appointment->id) }}"
              onsubmit="return askAction(event, this, 'confirm')">
          @csrf @method('PATCH')
          <input type="hidden" name="action" value="confirm">
          <button type="submit"
                  class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  {{ $canConfirm ? '' : 'disabled' }}>
            Confirm
          </button>
        </form>

        {{-- Done (available at/after start) --}}
        <form method="POST"
              action="{{ route('counselor.appointments.status', $appointment->id) }}"
              onsubmit="return {{ $canDone ? 'askAction(event, this, \'done\')' : 'false' }}">
          @csrf @method('PATCH')
          <input type="hidden" name="action" value="done">
          <button type="submit"
                  class="px-4 py-2 rounded-lg text-white {{ $canDone ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed' }}"
                  {{ $canDone ? '' : 'disabled' }}>
            Done
          </button>
        </form>

        {{-- No-Show (only after end) --}}
        <form method="POST"
              action="{{ route('counselor.appointments.no_show', $appointment->id) }}"
              onsubmit="return {{ $canNoShow ? 'askAction(event, this, \'no_show\')' : 'false' }}">
          @csrf
          <button type="submit"
                  class="px-4 py-2 rounded-lg text-white
                  {{ $canNoShow
                      ? 'bg-rose-600 hover:bg-rose-700'
                      : 'bg-slate-300 text-slate-600 cursor-not-allowed' }}"
                  {{ $canNoShow ? '' : 'disabled' }}>
            Mark as No-Show
          </button>
        </form>
      </div>
    </div>

    <div class="mt-4 h-px bg-slate-200/70"></div>

    {{-- Two columns --}}
    <div class="px-6 py-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
      {{-- Student --}}
      <section class="rounded-2xl ring-1 ring-slate-200 bg-white">
        <header class="px-4 py-2.5 bg-slate-50/60 rounded-t-2xl">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Student</h3>
        </header>
        <div class="p-4">
          <div class="font-medium text-slate-900">{{ $appointment->student_name }}</div>
          @if(!empty($appointment->student_email))
            <div class="text-sm text-slate-600">{{ $appointment->student_email }}</div>
          @endif
        </div>
      </section>

      {{-- Timing --}}
      <section class="rounded-2xl ring-1 ring-slate-200 bg-white">
        <header class="px-4 py-2.5 bg-slate-50/60 rounded-t-2xl">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Appointment Timing</h3>
        </header>
        <div class="p-4 space-y-6">
          {{-- Booked On --}}
          <div class="flex gap-3">
            <div class="shrink-0 mt-0.5">
              <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7 2a1 1 0 0 0-1 1v1H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1z"/><path d="M21 10H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z"/>
              </svg>
            </div>
            <div>
              <div class="text-[13px] uppercase tracking-wide text-slate-500">Booked On</div>
              <div class="font-medium text-slate-900">
                {{ $bookedAt ? $bookedAt->format('F d, Y') : '—' }}
              </div>
              @if($bookedAt)
                <div class="text-sm text-slate-600">{{ $bookedAt->format('g:i A') }}</div>
              @endif
            </div>
          </div>

          {{-- Scheduled For --}}
          <div class="flex gap-3">
            <div class="shrink-0 mt-0.5">
              <svg class="w-5 h-5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 10.414V7h-2v6h5v-2h-3z"/>
              </svg>
            </div>
            <div>
              <div class="text-[13px] uppercase tracking-wide text-slate-500">Scheduled For</div>
              <div class="font-medium text-slate-900">{{ $start->format('F d, Y') }}</div>
              <div class="text-sm text-slate-600">
                {{ $start->format('g:i A') }} – {{ $end->format('g:i A') }}
              </div>
              @if(!empty($appointment->location))
                <div class="text-xs text-slate-500">{{ $appointment->location }}</div>
              @endif
            </div>
          </div>
        </div>
      </section>
    </div>

    {{-- Final Diagnosis --}}
    <div class="px-6 pb-6">
      <div class="rounded-2xl bg-indigo-50/40 ring-1 ring-indigo-200/70 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3">
          <div class="text-xs font-semibold tracking-wide uppercase text-slate-700">Final Diagnosis (Report)</div>
          @isset($latestReport)
            <div class="text-xs text-slate-500">
              Last saved {{ \Carbon\Carbon::parse($latestReport->updated_at)->format('M d, Y g:i A') }}
            </div>
          @endisset
        </div>

        <div class="px-4 pb-4">
          @if($status === 'completed')
            <form method="POST" action="{{ route('counselor.appointments.report', $appointment->id) }}" class="space-y-5">
              @csrf
              {{-- Diagnosis --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  Final Diagnosis <span class="text-rose-600">*</span>
                </label>
                <div class="relative">
                  <textarea id="dxField" name="diagnosis" rows="4" required maxlength="4000"
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000"
                            placeholder="Write the final diagnosis... (clear, concise, student-friendly)">{{ old('diagnosis') }}</textarea>
                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400">
                    <span class="js-count">0</span>/4000
                  </div>
                </div>
                @error('diagnosis') <div class="text-sm text-rose-600 mt-1">• {{ $message }}</div> @enderror
              </div>

              {{-- Note --}}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Note (optional)</label>
                <div class="relative">
                  <textarea id="noteField" name="final_note" rows="3" maxlength="4000"
                            class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3 js-counted"
                            data-max="4000"
                            placeholder="Additional context for internal use...">{{ old('final_note') }}</textarea>
                  <div class="absolute right-2 bottom-1.5 text-[11px] text-slate-400">
                    <span class="js-count">0</span>/4000
                  </div>
                </div>
                @error('final_note') <div class="text-sm text-rose-600 mt-1">• {{ $message }}</div> @enderror
              </div>

              <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                  Save Diagnosis
                </button>
              </div>
            </form>
          @else
            <div class="bg-white rounded-lg p-3">
              <textarea rows="3" class="w-full rounded-md border-0 ring-0 bg-transparent" disabled
                        placeholder="Available after the appointment is marked Completed."></textarea>
              <div class="text-xs text-slate-500 mt-2">
                You can add the final diagnosis once this appointment is <b>Completed</b>.
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Footer --}}
    <div class="px-6 pb-6 border-t border-slate-200 flex items-center justify-between">
      <span></span>
      <div class="text-xs text-slate-500">
        Status: <span class="font-medium">{{ $statusLabel }}</span>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function askAction(e, form, action){
    e.preventDefault();
    const cfg = {
      confirm:{ title:'Confirm appointment?', text:'Are you sure?',                    icon:'question', confirmButtonColor:'#2563eb' },
      done:   { title:'Mark as Completed?',   text:'This will mark as completed.',     icon:'success',  confirmButtonColor:'#059669' },
      no_show:{ title:'Mark as No-Show?',     text:'Student did not attend this slot.',icon:'warning',  confirmButtonColor:'#dc2626' }
    }[action] || { title:'Proceed?', text:'', icon:'info', confirmButtonColor:'#2563eb' };

    Swal.fire({
      title: cfg.title, text: cfg.text, icon: cfg.icon,
      showCancelButton: true, confirmButtonText: 'Yes, proceed', cancelButtonText: 'Cancel',
      confirmButtonColor: cfg.confirmButtonColor, cancelButtonColor: '#6b7280',
      reverseButtons: true, focusCancel: true
    }).then(res => { if (res.isConfirmed) form.submit(); });
    return false;
  }

  // live counters
  (function () {
    const fields = document.querySelectorAll('.js-counted');
    const clamp = (s, m) => s.length > m ? s.slice(0, m) : s;

    fields.forEach(el => {
      const max = parseInt(el.dataset.max || el.getAttribute('maxlength') || '4000', 10);
      const counter = el.parentElement.querySelector('.js-count');

      const paint = () => {
        if (el.value.length > max) el.value = clamp(el.value, max);
        if (counter) counter.textContent = el.value.length;
        const wrap = counter?.parentElement;
        if (!wrap) return;
        const ratio = el.value.length / max;
        wrap.style.color = ratio >= 1 ? '#dc2626' : (ratio >= .9 ? '#f59e0b' : '#94a3b8');
      };

      paint();
      el.addEventListener('input', paint);
      el.addEventListener('paste', () => requestAnimationFrame(paint));
    });
  })();

  @if (session('swal'))
    Swal.fire(@json(session('swal')));
  @endif
</script>
@endpush
