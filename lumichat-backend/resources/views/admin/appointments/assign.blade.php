{{-- resources/views/admin/appointments/assign.blade.php --}}
@extends('layouts.admin')
@section('title','Assign Counselor · Appointment #'.$appointment->id)

@section('content')
@php
  use Carbon\Carbon;
  $dt = Carbon::parse($appointment->scheduled_at);
@endphp

<div class="max-w-5xl mx-auto px-6 pt-4">
  {{-- Top bar --}}
  <div class="flex items-center justify-between gap-3 mb-4">
    <div class="min-w-0">
      <h1 class="text-[20px] sm:text-[22px] font-semibold text-slate-900 flex items-center gap-2">
        Assign Counselor
        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">#{{ $appointment->id }}</span>
      </h1>
      <div class="mt-1 flex items-center gap-4 text-sm text-slate-600">
        <span class="inline-flex items-center gap-1.5">
          <svg class="w-4 h-4 text-indigo-600" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2a1 1 0 0 0-1 1v1H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1z"/><path d="M21 10H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z"/></svg>
          {{ $dt->format('M d, Y') }}
        </span>
        <span class="inline-flex items-center gap-1.5">
          <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 10.414V7h-2v6h5v-2h-3z"/></svg>
          {{ $dt->format('g:i A') }}
        </span>
        @if(!empty($appointment->student_name))
          <span class="inline-flex items-center gap-1.5">
            <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zM2 20a10 10 0 0120 0v2H2v-2z"/></svg>
            {{ $appointment->student_name }}
          </span>
        @endif
      </div>
    </div>

    <a href="{{ route('admin.appointments.show', $appointment->id) }}"
       class="inline-flex items-center gap-2 h-10 px-3.5 rounded-xl bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 active:scale-[.99]">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
      Back
    </a>
  </div>

  {{-- Card --}}
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-5 py-3 bg-slate-50/60 text-[13px] text-slate-600 flex items-center justify-between">
      <div class="font-medium tracking-wide">
        APPOINTMENT #{{ $appointment->id }} · {{ $dt->format('M d, Y · g:i A') }}
      </div>
      <div class="hidden sm:flex items-center gap-4">
        <span class="inline-flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          Available = selectable
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-rose-500"></span>
          Not available
        </span>
      </div>
    </div>

    <div class="p-5">
      <form method="POST" action="{{ route('admin.appointments.assign', $appointment->id) }}" class="space-y-6">
        @csrf
        @method('PATCH')

        {{-- Counselor select (no search) --}}
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Counselor <span class="text-rose-600">*</span>
          </label>

          <div class="relative">
            <select name="counselor_id"
                    required
                    class="w-full h-11 appearance-none rounded-xl border border-slate-200 bg-white pr-10 pl-3 text-sm text-slate-800
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">Select available counselor</option>
              @forelse($counselors as $c)
                {{-- If you ever pass availability flags, you can disable here:
                   <option value="{{ $c->id }}" {{ $c->available ? '' : 'disabled' }}>
                --}}
                <option value="{{ $c->id }}">{{ $c->name }}@if($c->email) — {{ $c->email }} @endif</option>
              @empty
                <option value="" disabled>No active counselors</option>
              @endforelse
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>

          {{-- replace the legend row with this note --}}
          <p class="mt-2 text-[13px] text-slate-500">
            Only counselors who are free at {{ \Carbon\Carbon::parse($appointment->scheduled_at)->format('M d, Y · g:i A') }} are listed.
          </p>

          @error('counselor_id')
            <p class="text-sm text-rose-600 mt-1">• {{ $message }}</p>
          @enderror
        </div>

        <div class="flex items-center gap-3">
          <a href="{{ route('admin.appointments.show', $appointment->id) }}"
             class="inline-flex items-center h-10 rounded-xl bg-slate-100 text-slate-700 px-4 hover:bg-slate-200">
            Cancel
          </a>
          <button
            class="inline-flex items-center h-10 rounded-xl bg-indigo-600 text-white px-5 shadow-sm hover:bg-indigo-700 active:scale-[.99]">
            <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5v14m-7-7h14"/></svg>
            Assign
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const swal = @json(session('swal'));
    if (swal) Swal.fire(swal);
  });
</script>
@endpush
