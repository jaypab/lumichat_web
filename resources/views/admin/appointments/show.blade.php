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
    'pending'   => 'bg-amber-50 text-amber-800 ring-amber-500/30',
    'confirmed' => 'bg-blue-50 text-blue-800 ring-blue-500/30',
    'canceled'  => 'bg-slate-100 text-slate-800 ring-slate-500/30',
    'completed' => 'bg-emerald-50 text-emerald-800 ring-emerald-500/30',
    'no_show'   => 'bg-rose-50 text-rose-800 ring-rose-500/30',
    ''          => 'bg-slate-100 text-slate-800 ring-slate-500/30',
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

  // 🔹 NEW: detect walk-in origin on the appointment itself
  $isWalkIn = ($appointment->appointment_source ?? null) === 'walk_in';
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- ========= Header band ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 screen-only mb-4 mt-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 leading-none">
          Appointment #{{ $appointment->id }}
        </h1>
        
        {{-- Status chip --}}
        <span class="inline-flex items-center px-2.5 h-6 rounded-full text-[10px] font-bold tracking-wide leading-none ring-1 ring-inset {{ $chipCls }}">
          <span class="inline-block size-1.5 rounded-full {{ $dotCls }} mr-1.5"></span>
          {{ strtoupper($statusLabel) }}
        </span>

        {{-- Walk-in origin chip --}}
        @if($isWalkIn)
          <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide leading-none bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-500/20 shadow-sm">
             <span class="inline-block size-1.5 rounded-full bg-amber-500 mr-1.5"></span>
             WALK-IN
          </span>
        @endif
      </div>

      <div class="text-slate-500 text-[13px] font-medium flex items-center gap-1.5 mt-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="12" cy="12" r="10" stroke-width="2.5"></circle>
          <polyline points="12 6 12 12 16 14" stroke-width="2.5"></polyline>
        </svg>
        {{ $whenText }}
      </div>
    </div>

    {{-- Right: Actions --}}
    @php
      $canAssign = ($status === 'pending') || ($status === 'confirmed' && !$hasCounselor);
    @endphp

    <div class="flex flex-wrap items-center gap-2.5">
      <a href="{{ route('admin.appointments.index') }}"
         class="inline-flex items-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2 text-[13px] font-bold shadow-[0_1px_2px_rgba(0,0,0,0.02)] hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to list
      </a>

      <a href="{{ route('admin.appointments.export.show.pdf', $appointment->id) }}"
         target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2 text-[13px] font-bold shadow-[0_1px_2px_rgba(0,0,0,0.02)] hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Download PDF
      </a>

      @if ($hasCounselor)
        <span class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-[13px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-500/20 shadow-sm cursor-default" title="Counselor Assigned">
          <span class="inline-block size-1.5 rounded-full bg-emerald-500"></span>
          Assigned
        </span>
      @elseif ($canAssign)
        <a href="{{ route('admin.appointments.assign.form', $appointment->id) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-[13px] font-bold text-white shadow-sm hover:bg-indigo-700 active:scale-[.98] transition-all duration-200">
          <svg class="w-4 h-4 text-indigo-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
          </svg>
          Assign Counselor
        </a>
      @else
        <span class="inline-flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-[13px] font-bold text-amber-700 ring-1 ring-inset ring-amber-500/20 shadow-sm cursor-default" title="Assignment locked for status: {{ $statusLabel }}">
          <svg class="w-3.5 h-3.5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
             <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"></rect>
             <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-width="2.5"></path>
          </svg>
          Locked
        </span>
      @endif
    </div>
  </header>

  {{-- ========= Content cards ========= --}}
  <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 overflow-visible mt-2">
    <div class="p-6 lg:p-8">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">

        {{-- Student block --}}
        <section class="flex flex-col">
          <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-4">Student</h3>
          
          <div class="font-bold text-lg text-slate-900 tracking-tight leading-none mb-1">
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
            <div class="text-[13px] font-medium text-slate-500 mb-1">
              {{ $appointment->student_course ?? 'N/A' }}
              @if(!empty($appointment->student_year_level))
                · {{ $yearLbl }}
              @endif
            </div>
          @endif

          @if(!empty($appointment->student_email))
            <div class="text-[13px] font-medium text-slate-500 leading-tight">
              {{ $appointment->student_email }}
            </div>
          @endif
        </section>

        {{-- Timing block --}}
        <section class="flex flex-col">
          <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-4">Appointment Timing</h3>
          
          <div class="flex flex-col gap-5">
            {{-- Booked On --}}
            <div class="flex gap-4">
              <div class="shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-8 rounded-xl bg-blue-50 text-blue-600 ring-1 ring-inset ring-blue-500/20">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M7 2a1 1 0 0 0-1 1v1H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1z"/>
                    <path d="M21 10H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z"/>
                  </svg>
                </div>
              </div>
              <div class="flex flex-col pt-0.5">
                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Booked On</div>
                <div class="font-bold text-[14px] text-slate-900 leading-tight tracking-tight">
                  {{ $bookedAt ? $bookedAt->format('F d, Y') : '—' }}
                </div>
                @if($bookedAt)
                  <div class="text-[13px] font-medium text-slate-500">{{ $bookedAt->format('g:i A') }}</div>
                @endif
              </div>
            </div>

            {{-- Scheduled For --}}
            <div class="flex gap-4">
              <div class="shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-8 rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-500/20">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 10.414V7h-2v6h5v-2h-3z"/>
                  </svg>
                </div>
              </div>
              <div class="flex flex-col pt-0.5">
                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Scheduled For</div>
                <div class="font-bold text-[14px] text-slate-900 leading-tight tracking-tight">{{ $start->format('F d, Y') }}</div>
                <div class="text-[13px] font-medium text-slate-500">
                  {{ $start->format('g:i A') }} – {{ $end->format('g:i A') }}
                </div>
                @if(!empty($appointment->location))
                  <div class="text-xs font-semibold text-slate-400 mt-0.5 flex items-center gap-1.5">
                    <span class="inline-block size-1 rounded-full bg-slate-300"></span>
                    {{ $appointment->location }}
                  </div>
                @endif
              </div>
            </div>
          </div>
        </section>

        <div class="col-span-1 lg:col-span-2">
          <hr class="border-slate-100" />
        </div>

        {{-- Counselor block --}}
        <section class="flex flex-col lg:col-span-2">
           <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-4">Counselor</h3>

          @if($hasCounselor)
            <div class="flex items-start gap-4">
              <div class="mt-0.5 hidden sm:block">
                <div class="flex items-center justify-center size-10 rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-inset ring-indigo-500/20 shrink-0">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM2 20a10 10 0 0120 0v2H2v-2z"/>
                  </svg>
                </div>
              </div>
              <div class="flex flex-col pt-1">
                <div class="font-bold text-lg text-slate-900 leading-none tracking-tight mb-1">{{ $appointment->counselor_name ?? '—' }}</div>
                @if(!empty($appointment->counselor_email))
                  <div class="text-[13px] font-medium text-slate-500">{{ $appointment->counselor_email }}</div>
                @endif
              </div>
            </div>
          @else
            <div class="rounded-2xl bg-slate-50 px-5 py-4 text-[13px] font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
              No counselor has been assigned yet. Click 
              <a href="{{ route('admin.appointments.assign.form', $appointment->id) }}" class="font-bold text-indigo-600 hover:text-indigo-700 underline underline-offset-2">
                Assign Counselor
              </a>
              to select one for this slot.
            </div>
          @endif
        </section>

        {{-- Counselor Change Request --}}
        @if(!empty($changeReq))
          <div class="col-span-1 lg:col-span-2 mt-4">
            <section class="rounded-3xl ring-1 ring-violet-200 bg-violet-50/50 p-6">
              <div class="flex items-center justify-between mb-5">
                <h3 class="text-[11px] font-black uppercase tracking-widest text-violet-600">
                  Counselor Change Request
                </h3>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide leading-none 
                   bg-violet-100 text-violet-800 ring-1 ring-inset ring-violet-500/20 shadow-sm">
                   {{ strtoupper(\Illuminate\Support\Str::headline($changeReq->status)) }}
                   <span class="text-violet-500/80 ml-2 border-l border-violet-500/20 pl-2">{{ \Carbon\Carbon::parse($changeReq->created_at)->diffForHumans() }}</span>
                </span>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <div class="text-[10px] font-bold uppercase tracking-widest text-violet-400 mb-0.5">Reason</div>
                  <div class="font-bold text-[14px] text-slate-900 leading-tight tracking-tight mb-1">{{ \Illuminate\Support\Str::headline($changeReq->reason_code) }}</div>
                  <div class="text-[13px] font-medium text-slate-600 leading-snug">{{ $changeReq->reason_text }}</div>
                </div>

                <div>
                  <div class="text-[10px] font-bold uppercase tracking-widest text-violet-400 mb-0.5">
                    Preferred Counselor
                  </div>
                  <div class="font-bold text-[14px] text-slate-900 leading-tight tracking-tight">
                    {{ $preferredCounselorName ?? 'No preference' }}
                  </div>
                </div>

                <div class="md:text-right md:pt-4">
                  @if($changeReq->status === 'requested')
                    {{-- Approve form (auto-assign to preferred counselor if possible) --}}
                    <form method="POST"
                          action="{{ route('admin.appointments.change_request.handle', [$appointment->id, 'approve']) }}"
                          class="inline"
                          data-cr-action="approve">
                      @csrf
                      <button type="button"
                              class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700 shadow-sm active:scale-[.98] transition-all js-cr-approve-btn">
                        Approve Request
                      </button>
                    </form>
                  @else
                    <span class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-500 ring-1 ring-inset ring-slate-200 cursor-default">
                      Decision Recorded
                    </span>
                  @endif
                </div>
              </div>
            </section>
          </div>
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
