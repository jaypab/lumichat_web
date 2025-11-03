{{-- resources/views/admin/appointments/show.blade.php --}}
@extends('layouts.admin')
@section('title', 'Appointment #'.$appointment->id)
@section('page_title', 'Appointment Details')

@php
  use Carbon\Carbon;

  $start       = Carbon::parse($appointment->scheduled_at)->second(0);
  $end         = $start->copy()->addMinutes(60); // 60-min slot
  $now         = Carbon::now();
  $bookedAt    = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;
  $status      = strtolower((string)$appointment->status);
  $hasCounselor = !empty($appointment->counselor_id);

  // “Starts in …” vs “Started …”
  $when = $now->isBefore($start)
    ? 'Starts in '.$start->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
    : 'Started '.$start->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW]);

  // Status chip styles
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
  $statusLabel = $status === 'no_show' ? 'No Show' : ucfirst($status);
  $chipCls     = $badgeMap[$status] ?? 'bg-slate-200 text-slate-700';
  $dotCls      = $dotMap[$status]   ?? 'bg-slate-500';
@endphp

@section('content')
<div class="max-w-6xl mx-auto p-6">
  <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    {{-- Top gradient bar --}}
    <span class="pointer-events-none absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>

    {{-- Header --}}
    <div class="px-6 pt-5">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <h2 class="text-[20px] font-semibold text-slate-900 truncate">
              Appointment #{{ $appointment->id }}
            </h2>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $chipCls }}">
              <span class="inline-block w-1.5 h-1.5 rounded-full {{ $dotCls }} mr-1.5"></span>
              {{ $statusLabel }}
            </span>
          </div>

          <div class="mt-1 text-sm text-slate-500 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
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
          <a href="{{ route('admin.appointments.export.show.pdf', $appointment->id) }}"
             target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 h-10 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-[.99]">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
            </svg>
            Download PDF
          </a>

          {{-- Assign (only when no counselor yet) / Already assigned pill --}}
          @if (!$hasCounselor)
            <a href="{{ route('admin.appointments.assign.form', $appointment->id) }}"
               class="inline-flex items-center gap-2 h-10 px-3.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Assign Counselor
            </a>
          @else
            <span class="inline-flex items-center gap-2 h-10 px-3.5 rounded-xl bg-slate-100 text-slate-700 ring-1 ring-slate-200 cursor-default">
              <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              Counselor already assigned
            </span>
          @endif
        </div>
      </div>
    </div>

    <div class="mt-4 h-px bg-slate-200/70"></div>

    {{-- Main content: two columns --}}
    <div class="px-6 py-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
      {{-- Student card --}}
      <section class="rounded-2xl ring-1 ring-slate-200 bg-white">
        <header class="px-4 py-2.5 bg-slate-50/60 rounded-t-2xl">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Student</h3>
        </header>
        <div class="p-4 space-y-1.5">
          <div class="font-medium text-slate-900">{{ $appointment->student_name ?? '—' }}</div>
          @if(!empty($appointment->student_email))
            <div class="text-sm text-slate-600">{{ $appointment->student_email }}</div>
          @endif
        </div>
      </section>

      {{-- Timing card --}}
      <section class="rounded-2xl ring-1 ring-slate-200 bg-white">
        <header class="px-4 py-2.5 bg-slate-50/60 rounded-t-2xl">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Appointment Timing</h3>
        </header>
        <div class="p-4 space-y-6">
          {{-- Booked On --}}
          <div class="flex gap-3">
            <div class="shrink-0 mt-0.5">
              <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7 2a1 1 0 0 0-1 1v1H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1z"/>
                <path d="M21 10H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z"/>
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

      {{-- Counselor card (read-only) --}}
      <section class="rounded-2xl ring-1 ring-slate-200 bg-white lg:col-span-2">
        <header class="px-4 py-2.5 bg-slate-50/60 rounded-t-2xl">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Counselor</h3>
        </header>

        @if($hasCounselor)
          <div class="p-4 flex items-start gap-3">
            <div class="mt-0.5">
              <svg class="w-5 h-5 text-indigo-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM2 20a10 10 0 0120 0v2H2v-2z"/>
              </svg>
            </div>
            <div>
              <div class="font-medium text-slate-900">{{ $appointment->counselor_name ?? '—' }}</div>
              @if(!empty($appointment->counselor_email))
                <div class="text-sm text-slate-600">{{ $appointment->counselor_email }}</div>
              @endif
              <div class="mt-2 inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1 ring-1 ring-emerald-200">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Counselor already assigned
              </div>
            </div>
          </div>
        @else
          <div class="p-4">
            <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200">
              No counselor has been assigned yet. Click
              <a href="{{ route('admin.appointments.assign.form', $appointment->id) }}" class="font-medium text-indigo-600 hover:text-indigo-700 underline">
                Assign Counselor
              </a>
              to select one for this slot.
            </div>
          </div>
        @endif
      </section>
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
@if (session('swal'))
  <script>Swal.fire(@json(session('swal')));</script>
@endif
@endpush
