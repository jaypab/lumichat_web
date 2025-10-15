{{-- resources/views/admin/appointments/assign.blade.php --}}
@extends('layouts.admin')
@section('title','Assign - Appointment #'.$appointment->id)

@section('content')
@php
  use Carbon\Carbon;
  $dt = Carbon::parse($appointment->scheduled_at);
@endphp

<div class="max-w-5xl mx-auto px-6 pt-4 pb-10">
  {{-- Breadcrumbs / top actions --}}
  <nav class="flex items-center justify-between gap-3 mb-4" aria-label="Breadcrumb">
    <ol class="flex items-center gap-2 min-w-0 text-sm">
      <li class="truncate"><a href="{{ route('admin.appointments.index') }}" class="text-slate-500 hover:text-slate-700">Appointments</a></li>
      <li class="text-slate-400">/</li>
      <li class="truncate text-slate-700 font-medium">Assign (#{{ $appointment->id }})</li>
    </ol>
  </nav>

  {{-- Page header --}}
  <header class="mb-4">
    <h1 class="text-[22px] sm:text-[24px] font-semibold text-slate-900 tracking-tight flex items-center gap-2">
      Assign Counselor
      <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">#{{ $appointment->id }}</span>
    </h1>
    <p class="mt-1 text-sm text-slate-600">
      Choose an available counselor for this specific date and time. Only free counselors are shown.
    </p>
  </header>

  {{-- Context / summary card --}}
  <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6" aria-labelledby="appt-summary">
    <div class="px-5 py-3 bg-slate-50/60 text-[13px] text-slate-700 font-medium" id="appt-summary">
      Appointment summary
    </div>

    <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="flex items-start gap-3">
        <div class="mt-0.5">
          <svg class="w-5 h-5 text-indigo-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M7 2a1 1 0 0 0-1 1v1H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1z"/>
            <path d="M21 10H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z"/>
          </svg>
        </div>
        <div>
          <div class="text-xs text-slate-500">Date</div>
          <div class="font-medium text-slate-800">{{ $dt->format('M d, Y') }}</div>
        </div>
      </div>

      <div class="flex items-start gap-3">
        <div class="mt-0.5">
          <svg class="w-5 h-5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 10.414V7h-2v6h5v-2h-3z"/>
          </svg>
        </div>
        <div>
          <div class="text-xs text-slate-500">Time</div>
          <div class="font-medium text-slate-800">{{ $dt->format('g:i A') }}</div>
        </div>
      </div>

      <div class="flex items-start gap-3">
        <div class="mt-0.5">
          <svg class="w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM2 20a10 10 0 0120 0v2H2v-2z"/>
          </svg>
        </div>
        <div>
          <div class="text-xs text-slate-500">Student</div>
          <div class="font-medium text-slate-800">{{ $appointment->student_name ?? '—' }}</div>
        </div>
      </div>
    </div>
  </section>

  {{-- Error summary (HCI: put errors near the top + link to fields) --}}
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert" aria-live="assertive">
      <p class="font-medium mb-1">Please fix the following:</p>
      <ul class="list-disc pl-5 space-y-1">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Form card --}}
  <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" aria-labelledby="assign-form">
    <div class="px-5 py-3 bg-slate-50/60 text-[13px] text-slate-700 font-medium" id="assign-form">
      Assign counselor
    </div>

    <div class="p-5">
      <form method="POST" action="{{ route('admin.appointments.assign', $appointment->id) }}" class="space-y-6" id="assignCounselorForm" novalidate>
        @csrf
        @method('PATCH')

        {{-- Counselor select --}}
        <div>
          <label for="counselor_id" class="block text-sm font-medium text-slate-800">
            Counselor <span class="text-rose-600">*</span>
          </label>

          <div class="relative mt-1">
            @php
  $available = collect($counselors)->filter(fn($x) => (int)$x->available === 1);
  $busy      = collect($counselors)->reject(fn($x) => (int)$x->available === 1);
  @endphp

  <div class="relative mt-1">
    <select
      id="counselor_id"
      name="counselor_id"
      required
      class="w-full h-11 appearance-none rounded-xl border border-slate-300 bg-white pr-10 pl-3 text-sm text-slate-900
            focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 aria-[invalid=true]:border-rose-400"
      aria-describedby="counselorHelp"
      @error('counselor_id') aria-invalid="true" @enderror
    >
      <option value="">Select an available counselor…</option>

      @if($available->isNotEmpty())
        <optgroup label="Available ({{ $available->count() }})">
          @foreach($available as $c)
            <option value="{{ $c->id }}" @selected(old('counselor_id') == $c->id)>
              {{ $c->name }}@if($c->email) — {{ $c->email }} @endif
            </option>
          @endforeach
        </optgroup>
      @endif

      @if($busy->isNotEmpty())
        <optgroup label="Busy ({{ $busy->count() }})">
          @foreach($busy as $c)
            <option value="{{ $c->id }}" disabled>
              {{ $c->name }}@if($c->email) — {{ $c->email }} @endif
              @if($c->busy_reason) ({{ $c->busy_reason }}) @endif
            </option>
          @endforeach
        </optgroup>
      @endif
    </select>

    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"
        viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
    </svg>
  </div>

  <p id="counselorHelp" class="mt-2 text-[13px] text-slate-500">
    <span class="font-medium">Legend:</span> Available = selectable • Busy = disabled
    · Showing counselors for <span class="font-medium">{{ $dt->format('M d, Y · g:i A') }}</span>.
  </p>


            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>

          <p id="counselorHelp" class="mt-2 text-[13px] text-slate-500">
            Showing counselors who are free on <span class="font-medium">{{ $dt->format('M d, Y · g:i A') }}</span>.
          </p>

          @error('counselor_id')
            <p class="text-sm text-rose-600 mt-1" id="counselorError">• {{ $message }}</p>
          @enderror
        </div>

        {{-- Actions --}}
        <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3">
          <a href="{{ route('admin.appointments.show', $appointment->id) }}"
             class="inline-flex items-center justify-center h-11 rounded-xl bg-slate-100 text-slate-700 px-4 hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-300"
             role="button">
            Cancel
          </a>

          <button
            id="assignBtn"
            type="submit"
            class="inline-flex items-center justify-center h-11 rounded-xl bg-indigo-600 text-white px-5 shadow-sm hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            disabled
          >
            Assign
          </button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Inline feedback from controller
  document.addEventListener('DOMContentLoaded', () => {
    const swal = @json(session('swal'));
    if (swal) Swal.fire(swal);

    // HCI: prevent accidental empty submit & double-submit; enable when valid
    const form = document.getElementById('assignCounselorForm');
    const select = document.getElementById('counselor_id');
    const btn = document.getElementById('assignBtn');

    const setButtonState = () => {
      btn.disabled = !select.value;
    };
    setButtonState();
    select.addEventListener('change', setButtonState);

    form.addEventListener('submit', (e) => {
      if (!select.value) {
        e.preventDefault();
        select.focus();
        select.setAttribute('aria-invalid', 'true');
        Swal.fire({
          icon: 'warning',
          title: 'Counselor required',
          text: 'Please select a counselor before assigning.',
          confirmButtonColor: '#4f46e5'
        });
        return;
      }
      // lock UI for safety
      btn.disabled = true;
      btn.innerHTML = `
        <svg class="animate-spin w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        Assigning…`;
    });
  });
</script>
@endpush
