@extends('layouts.counselor')
@section('title','Counselor - Manage Appointments')
@section('page_title','Counselor - Manage Appointments')

@php
  use Carbon\Carbon;

  // Preserve current filters (fallback to request values)
  $status = $status ?? request('status', 'all');
  $period = $period ?? request('period', 'all');
  $q      = $q      ?? request('q', '');

  $statusOptions = [
    'all'       => 'All',
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'ongoing'   => 'Ongoing',
    'completed' => 'Completed',
    'canceled'  => 'Canceled',
    'no_show'   => 'No Show',
  ];
  $periodOptions = [
    'all'        => 'All Dates',
    'upcoming'   => 'Upcoming',
    'today'      => 'Today',
    'this_week'  => 'This Week',
    'this_month' => 'This Month',
    'past'       => 'Past',
  ];

  // Status chip tokens (matches your system palette)
  $statusMap = [
    'pending'   => ['bg'=>'bg-amber-50','text'=>'text-amber-700','ring'=>'ring-amber-200','dot'=>'bg-amber-500','label'=>'Pending'],
    'confirmed' => ['bg'=>'bg-blue-50','text'=>'text-blue-700','ring'=>'ring-blue-200','dot'=>'bg-blue-500','label'=>'Confirmed'],
    'ongoing'   => ['bg'=>'bg-indigo-50','text'=>'text-indigo-700','ring'=>'ring-indigo-200','dot'=>'bg-indigo-600','label'=>'Ongoing'],
    'completed' => ['bg'=>'bg-emerald-50','text'=>'text-emerald-700','ring'=>'ring-emerald-200','dot'=>'bg-emerald-500','label'=>'Completed'],
    'canceled'  => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200','dot'=>'bg-rose-500','label'=>'Canceled'],
    'no_show'   => ['bg'=>'bg-rose-50','text'=>'text-rose-700','ring'=>'ring-rose-200','dot'=>'bg-rose-500','label'=>'No Show'],
  ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

  {{-- Header --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
    <div>
      <h2 class="text-2xl font-bold text-slate-900">Manage Appointments</h2>
      <p class="text-sm text-slate-600">
        Appointments assigned to you •
        <span class="text-slate-500">{{ $appointments->total() }}</span>
      </p>
    </div>
  </div>

  {{-- Filters --}}
  <form method="GET" action="{{ route('counselor.appointments.index') }}" class="mb-3">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
      <div class="md:col-span-3">
        <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
        <select name="status"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500">
          @foreach($statusOptions as $v=>$label)
            <option value="{{ $v }}" @selected($status===$v)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-3">
        <label class="block text-xs font-medium text-slate-600 mb-1">Date Range</label>
        <select name="period"
                class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm focus:ring-2 focus:ring-indigo-500">
          @foreach($periodOptions as $v=>$label)
            <option value="{{ $v }}" @selected($period===$v)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-4">
        <label class="block text-xs font-medium text-slate-600 mb-1">Search student</label>
        <div class="relative">
          <input id="q" name="q" value="{{ $q }}" placeholder="Name or email"
                 class="w-full h-10 bg-white border border-slate-200 rounded-xl pl-10 pr-3 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="11" cy="11" r="7" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <div class="md:col-span-2 flex gap-2">
        <a href="{{ route('counselor.appointments.index') }}"
           class="h-10 inline-flex items-center justify-center rounded-xl bg-white px-4 ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">
          Reset
        </a>
        <button class="h-10 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 text-white hover:bg-indigo-700">
          Apply
        </button>
      </div>
    </div>
  </form>

  {{-- Table --}}
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
    <div class="relative overflow-x-auto">
      <table class="min-w-full text-sm leading-6">
        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
          <tr>
            <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">ID</th>
            <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Student</th>
            <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Date &amp; Time</th>
            <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Booked On</th>
            <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Status</th>
            <th class="px-6 py-3 text-right uppercase tracking-wide text-[11px]">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse($appointments as $row)
            @php
              $dt       = Carbon::parse($row->scheduled_at);
              $bookedAt = $row->booked_at ? Carbon::parse($row->booked_at) : null;

              $s      = $statusMap[$row->status] ?? ['bg'=>'bg-slate-50','text'=>'text-slate-700','ring'=>'ring-slate-200','dot'=>'bg-slate-400','label'=>ucfirst($row->status)];
              $chipCl = "{$s['bg']} {$s['text']} ring-1 {$s['ring']}";
              $dotCl  = $s['dot'];
              $label  = $s['label'];
              $crPending = isset($row->cr_status) && $row->cr_status === 'requested';
              $crTime    = !empty($row->cr_created_at)
                          ? Carbon::parse($row->cr_created_at)->diffForHumans()
                          : null;
            @endphp

            <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
              <td class="px-6 py-4 font-semibold text-slate-900">{{ $row->id }}</td>

              <td class="px-6 py-4">
                <div class="font-medium text-slate-900">{{ $row->student_name }}</div>
                @if(!empty($row->student_email))
                  <div class="text-slate-500 text-xs">{{ $row->student_email }}</div>
                @endif
              </td>

              <td class="px-6 py-4">
                <div class="leading-tight">
                  <div class="font-medium text-slate-900">{{ $dt->format('M d, Y') }}</div>
                  <div class="text-slate-500 text-xs">{{ $dt->format('g:i A') }}</div>
                </div>
              </td>

              <td class="px-6 py-4">
                @if($bookedAt)
                  <div class="leading-tight">
                    <div class="font-medium text-slate-900">{{ $bookedAt->format('M d, Y') }}</div>
                    <div class="text-slate-500 text-xs">{{ $bookedAt->format('g:i A') }}</div>
                  </div>
                @else
                  <span class="text-slate-400">—</span>
                @endif
              </td>

              <td class="px-6 py-4">
                <span class="relative inline-flex items-center h-7 w-[118px] rounded-full text-xs font-medium leading-none {{ $chipCl }}">
                  <span class="absolute left-3 inline-block size-2 rounded-full {{ $dotCl }}"></span>
                  <span class="mx-auto">{{ $label }}</span>
                </span>

                @if($crPending)
                  <div class="mt-1">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-200"
                          title="Student requested counselor reassignment for this appointment.">
                      <span class="inline-block size-1.5 rounded-full bg-violet-500"></span>
                      Change requested
                      @if($crTime) <span class="text-violet-500/70">• {{ $crTime }}</span> @endif
                    </span>
                  </div>
                @endif 
              </td>

              <td class="px-6 py-4 text-right">
                <a href="{{ route('counselor.appointments.show', $row->id) }}"
                   class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">
                  View
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="px-6 py-10 text-center text-slate-500">No appointments found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($appointments->hasPages())
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70">
        {{ $appointments->withQueryString()->links() }}
      </div>
    @endif
  </div>
</div>

{{-- =========================
     Re-Assigned Appointments
     (history from tbl_appointment_counselor_history)
   ========================= --}}
@if(isset($reassignedAppointments) && $reassignedAppointments->count() > 0)
  <div class="max-w-7xl mx-auto space-y-4 mt-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">Re-Assigned Appointments</h3>
        <p class="text-xs text-slate-500">
          Appointments that were previously assigned to you and later reassigned to another counselor.
        </p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="relative overflow-x-auto">
        <table class="min-w-full text-sm leading-6">
          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
            <tr>
              <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">ID</th>
              <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Student</th>
              <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Date &amp; Time</th>
              <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Booked On</th>
              <th class="px-6 py-3 text-left uppercase tracking-wide text-[11px]">Status</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            @foreach($reassignedAppointments as $row)
              @php
                $dt       = Carbon::parse($row->scheduled_at);
                $bookedAt = $row->booked_at ? Carbon::parse($row->booked_at) : null;

                $changedLabel = $row->changed_at
                    ? Carbon::parse($row->changed_at)->diffForHumans()
                    : null;
              @endphp

              <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
                <td class="px-6 py-4 font-semibold text-slate-900">{{ $row->id }}</td>

                <td class="px-6 py-4">
                  <div class="font-medium text-slate-900">{{ $row->student_name }}</div>
                  @if(!empty($row->student_email))
                    <div class="text-slate-500 text-xs">{{ $row->student_email }}</div>
                  @endif
                </td>

                <td class="px-6 py-4">
                  <div class="leading-tight">
                    <div class="font-medium text-slate-900">{{ $dt->format('M d, Y') }}</div>
                    <div class="text-slate-500 text-xs">{{ $dt->format('g:i A') }}</div>
                  </div>
                </td>

                <td class="px-6 py-4">
                  @if($bookedAt)
                    <div class="leading-tight">
                      <div class="font-medium text-slate-900">{{ $bookedAt->format('M d, Y') }}</div>
                      <div class="text-slate-500 text-xs">{{ $bookedAt->format('g:i A') }}</div>
                    </div>
                  @else
                    <span class="text-slate-400">—</span>
                  @endif
                </td>

                <td class="px-6 py-4">
                  <span class="inline-flex items-center h-7 rounded-full bg-slate-900 text-white text-xs font-medium px-3">
                    <span class="inline-block size-1.5 rounded-full bg-yellow-300 mr-1.5"></span>
                    Re-Assigned
                  </span>
                  @if($changedLabel)
                    <div class="mt-1 text-[11px] text-slate-500">
                      Reassigned {{ $changedLabel }}
                    </div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endif

{{-- Micro-UX: auto-submit search after a short pause --}}
<script>
  const q = document.getElementById('q');
  const form = q?.closest('form');
  let t = null;
  q?.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => form?.submit(), 400);
  });
</script>
@endsection
