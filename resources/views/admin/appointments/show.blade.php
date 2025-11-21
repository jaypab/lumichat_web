{{-- resources/views/admin/appointments/show.blade.php --}}
@extends('layouts.admin')
@section('title', 'Appointment #'.$appointment->id)
@section('page_title', 'Appointment Details')

@php
  use Carbon\Carbon;

  // Core times
  $start    = Carbon::parse($appointment->scheduled_at)->second(0);
  $end      = $start->copy()->addMinutes(60); // 60-min slot
  $bookedAt = $appointment->created_at ? Carbon::parse($appointment->created_at) : null;
  $now      = Carbon::now();

  // Header subtext: Starts in ... / Started ...
  $whenText = $now->isBefore($start)
      ? 'Starts in '.$start->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
      : 'Started '.$start->diffForHumans($now, ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW]);

  // Status -> chips
  $status       = strtolower((string) $appointment->status);
  $statusLabel  = $status === 'no_show' ? 'No Show' : ucfirst($status ?: 'scheduled');

  $badgeMap = [
    'pending'   => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'canceled'  => 'bg-rose-100 text-rose-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'no_show'   => 'bg-rose-100 text-rose-800',
    ''          => 'bg-slate-100 text-slate-800',
  ];
  $dotMap = [
    'pending'   => 'bg-amber-500',
    'confirmed' => 'bg-blue-500',
    'canceled'  => 'bg-rose-500',
    'completed' => 'bg-emerald-500',
    'no_show'   => 'bg-rose-500',
    ''          => 'bg-slate-500',
  ];
  $chipCls = $badgeMap[$status] ?? $badgeMap[''];
  $dotCls  = $dotMap[$status]   ?? $dotMap[''];

  $hasCounselor = !empty($appointment->counselor_id);
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Header band (gradient) ========= --}}
  <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm screen-only">
    <div class="p-5 sm:p-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {{-- Left: Title + chips --}}
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight truncate">
              Appointment #{{ $appointment->id }}
            </h2>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $chipCls }}">
              <span class="inline-block w-1.5 h-1.5 rounded-full {{ $dotCls }} mr-1.5"></span>
              {{ $statusLabel }}
            </span>
          </div>

          <p class="text-white/85 text-sm mt-0.5 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-90" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M12 8v5l3.5 3.5 1.5-1.5-3-3V8z"></path>
              <path d="M12 22a10 10 0 110-20 10 10 0 010 20zm0-2a8 8 0 100-16 8 8 0 010 16z"></path>
            </svg>
            {{ $whenText }}
          </p>
        </div>

        {{-- Right: Actions --}}
        @php
          // reuse normalized $status and $hasCounselor from above
          $canAssign = ($status === 'pending') || ($status === 'confirmed' && !$hasCounselor);
        @endphp

        <div class="flex items-center gap-2">
          <a href="{{ route('admin.appointments.index') }}"
            class="inline-flex items-center gap-2 rounded-xl bg-white/95 text-slate-800 px-4 py-2 text-sm font-medium shadow-sm ring-1 ring-white/25 hover:bg-white active:scale-[.99] transition">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to list
          </a>

          <a href="{{ route('admin.appointments.export.show.pdf', $appointment->id) }}"
            target="_blank" rel="noopener"
            class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
            </svg>
            Download PDF
          </a>

          @if ($hasCounselor)
            <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-1.5 text-sm ring-1 ring-white/20">
              <span class="inline-block size-2 rounded-full bg-emerald-300"></span>
              Counselor already assigned
            </span>
          @elseif ($canAssign)
            <a href="{{ route('admin.appointments.assign.form', $appointment->id) }}"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-800 active:scale-[.99] transition">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Assign Counselor
            </a>
          @else
            <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-1.5 text-sm ring-1 ring-white/20">
              <span class="inline-block size-2 rounded-full bg-amber-300"></span>
              Assignment locked for status: {{ $statusLabel }}
            </span>
          @endif
        </div>
      </div>
    </div>
  </section>

  {{-- ========= Content cards ========= --}}
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
    <div class="p-5 lg:p-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Student card --}}
        <section class="rounded-2xl ring-1 ring-slate-200 bg-white overflow-hidden">
          <header class="px-4 py-2.5 bg-slate-50/60">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Student</h3>
          </header>
          <div class="p-4 space-y-1.5">
            <div class="font-medium text-slate-900">
              {{ $appointment->student_name ?? '—' }}
            </div>

            @php
              $yearRaw = $appointment->student_year_level ?? null;
              $yearLbl = match((string) $yearRaw) {
                '1' => '1st Year',
                '2' => '2nd Year',
                '3' => '3rd Year',
                '4' => '4th Year',
                default => $yearRaw,
              };
            @endphp

            @if(!empty($appointment->student_course) || !empty($appointment->student_year_level))
              <div class="text-sm text-slate-600">
                {{ $appointment->student_course ?? 'N/A' }}
                @if(!empty($appointment->student_year_level))
                  · {{ $yearLbl }}
                @endif
              </div>
            @endif

            @if(!empty($appointment->student_email))
              <div class="text-sm text-slate-600">
                {{ $appointment->student_email }}
              </div>
            @endif
          </div>
        </section>

        {{-- Timing card --}}
        <section class="rounded-2xl ring-1 ring-slate-200 bg-white overflow-hidden">
          <header class="px-4 py-2.5 bg-slate-50/60">
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

        {{-- Counselor card --}}
        <section class="rounded-2xl ring-1 ring-slate-200 bg-white overflow-hidden lg:col-span-2">
          <header class="px-4 py-2.5 bg-slate-50/60">
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

        {{-- Counselor Change Request --}}
        @if(!empty($changeReq))
          <section class="rounded-2xl ring-1 ring-slate-200 bg-white overflow-hidden lg:col-span-2">
            <header class="px-4 py-2.5 bg-slate-50/60 flex items-center justify-between">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                Counselor Change Request
              </h3>
              <span class="inline-flex items-center gap-1.5 rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-200">
                {{ \Illuminate\Support\Str::headline($changeReq->status) }}
                <span class="text-violet-500/70">• {{ \Carbon\Carbon::parse($changeReq->created_at)->diffForHumans() }}</span>
              </span>
            </header>

            <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <div class="text-[13px] uppercase tracking-wide text-slate-500">Reason</div>
                <div class="font-medium text-slate-900">{{ \Illuminate\Support\Str::headline($changeReq->reason_code) }}</div>
                <div class="text-sm text-slate-700 mt-1">{{ $changeReq->reason_text }}</div>
              </div>

              <div>
                <div class="text-[13px] uppercase tracking-wide text-slate-500">
                  Preferred Counselor
                </div>
                <div class="font-medium text-slate-900">
                  {{ $preferredCounselorName ?? 'No preference' }}
                </div>
              </div>

              <div class="md:text-right">
                @if($changeReq->status === 'requested')
                  {{-- Approve form (auto-assign to preferred counselor if possible) --}}
                  <form method="POST"
                        action="{{ route('admin.appointments.change_request.handle', [$appointment->id, 'approve']) }}"
                        class="inline"
                        data-cr-action="approve">
                    @csrf
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 js-cr-approve-btn">
                      Approve request
                    </button>
                  </form>
                @else
                  <span class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-1.5 text-sm text-slate-700 ring-1 ring-slate-200">
                    Decision recorded
                  </span>
                @endif
              </div>
            </div>
          </section>
        @endif

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

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ----- Approve with confirm -----
  const approveForm = document.querySelector('form[data-cr-action="approve"]');
  if (approveForm) {
    const approveBtn = approveForm.querySelector('.js-cr-approve-btn');
    if (approveBtn) {
      approveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        Swal.fire({
          title: 'Approve change request?',
          text: 'This will approve the student\'s request and move the appointment to their preferred counselor if available. If not, you will be asked to assign a different counselor.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes, approve',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          focusCancel: true,
        }).then((result) => {
          if (result.isConfirmed) {
            approveForm.submit();
          }
        });
      });
    }
  }
});
</script>
@endpush
