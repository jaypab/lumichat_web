{{-- resources/views/admin/counselors/index.blade.php --}}
@extends('layouts.admin')

@section('title','Admin - Counselors')
@section('page_title','Manage Counselors')

@php
  use Illuminate\Support\Str;
  use Carbon\Carbon;

  // —— Simple time formatter: "09:00" / "09:00:00" -> "9am"
  $fmtTime = function ($t) {
      if (!$t) return '—';
      try {
          // normalize "HH:MM" to "HH:MM:SS"
          $raw = (strlen($t) === 5) ? ($t . ':00') : $t;
          return strtolower(Carbon::createFromFormat('H:i:s', $raw)->format('ga'));
      } catch (\Throwable $e) {
          return e($t); // fall back to raw
      }
  };
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  @php
    $total = method_exists($counselors,'total') ? $counselors->total() : $counselors->count();
  @endphp

  {{-- ========= Header band (gradient) ========= --}}
 <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm screen-only">
    <div class="p-5 sm:p-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Counselors</h2>
          <p class="text-white/85 text-sm mt-0.5">Manage counselor profiles and weekly availability.</p>
        </div>

        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-1.5 text-sm ring-1 ring-white/20">
            <span class="inline-block size-2 rounded-full bg-emerald-300"></span>
            <strong class="font-semibold">{{ $total }}</strong>
            <span class="opacity-90">{{ Str::plural('counselor', $total) }}</span>
          </span>
          {{-- (Optional) Add button slot if you later expose create route
          <a href="{{ route('admin.counselors.create') }}"
             class="hidden sm:inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
            Add Counselor
          </a>
          --}}
        </div>
      </div>
    </div>
  </section>

  {{-- ========= Table ========= --}}
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
    <div class="relative overflow-x-auto">
      <table class="min-w-full text-sm leading-6 table-fixed">
        <colgroup>
          <col style="width:14rem">
          <col style="width:16rem">
          <col style="width:12rem">
          <col style="width:18rem">
          <col style="width:24rem">
        </colgroup>

        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700 sticky top-0 z-10">
          <tr class="align-middle">
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Counselor</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Contact</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Status / Load</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Upcoming Appointment</th>
            <th class="px-6 lg:pl-12 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Weekly Availability</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse ($counselors as $c)
            <tr class="align-top even:bg-slate-50 hover:bg-slate-100/60 transition">
              {{-- Counselor --}}
              <td class="px-6 py-4">
                <div class="font-semibold text-slate-900 truncate" title="{{ $c->name }}">{{ $c->name }}</div>
                <div class="mt-1">
                  @if($c->is_active)
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Available
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] bg-rose-50 text-rose-700 ring-1 ring-rose-200">
                      <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Not available
                    </span>
                  @endif
                </div>
              </td>

              {{-- Contact --}}
              <td class="px-6 py-4">
                <div class="truncate" title="{{ $c->email }}">
                  <a class="hover:underline" href="mailto:{{ $c->email }}">{{ $c->email }}</a>
                </div>
                @if($c->phone)
                  <div class="text-slate-500 truncate">
                    <a class="hover:underline" href="tel:{{ $c->phone }}">{{ $c->phone }}</a>
                  </div>
                @endif
              </td>

              {{-- Status / Load --}}
              <td class="px-6 py-4">
                @if(!empty($c->is_busy_now))
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200 text-[12px]">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Busy now
                  </span>
                  <div class="text-[12px] text-slate-600 mt-1">
                    {{-- CHANGED: show non-technical time --}}
                    until {{ optional($c->busy_until_c)->format('ga') ?: '—' }}
                  </div>
                @else
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 text-[12px]">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Free now
                  </span>
                @endif

                <div class="flex flex-wrap gap-1.5 mt-1 text-[12px] text-slate-600">
                  <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5">
                    Today: <b class="ml-1 tabular-nums">{{ (int)($c->today_count ?? 0) }}</b>
                  </span>
                  <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5">
                    Upcoming: <b class="ml-1 tabular-nums">{{ (int)($c->upcoming_count ?? 0) }}</b>
                  </span>
                </div>
              </td>

              {{-- Upcoming appointment --}}
              <td class="px-6 py-4 align-top">
                <div class="flex flex-col gap-1">
                  @if(!empty($c->next_appt_id) && !empty($c->next_at_c))
                    <a href="{{ route('admin.appointments.show', $c->next_appt_id) }}"
                       class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[12px] text-slate-700 ring-1 ring-slate-200 hover:bg-indigo-50 hover:ring-indigo-200 transition">
                      {{-- CHANGED: date with non-technical time --}}
                      {{ $c->next_at_c->format('M d, ga') }}
                    </a>
                  @else
                    <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[12px] text-slate-700 ring-1 ring-slate-200">—</span>
                  @endif

                  @if(!empty($c->next_student_id) && !empty($c->next_student_name))
                    <a href="{{ route('admin.students.show', $c->next_student_id) }}"
                       class="text-indigo-600 hover:underline text-sm leading-5">
                      {{ $c->next_student_name }}
                    </a>
                  @endif
                </div>
              </td>

              {{-- Weekly availability (cleaner, more organized layout) --}}
              <td class="px-6 lg:pl-6 py-4 align-top">
                @php
                  $dayLabel = [0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
                  $weekly   = ($c->availabilities ?? collect())->filter(fn($a) => !is_null($a->weekday));
                @endphp

                <div class="max-w-xl">
                  @if($weekly->isEmpty())
                    <span class="text-slate-400 text-xs italic">No availability set</span>
                  @else
                    <div class="space-y-2">
                      @foreach ($weekly->groupBy('weekday') as $weekday => $slots)
                        @php $label = $dayLabel[(int)$weekday] ?? '—'; @endphp
                        <div class="flex items-start gap-2">
                          {{-- Day label --}}
                          <div class="flex-shrink-0 w-12">
                            <span class="inline-flex items-center justify-center w-10 h-6 text-[11px] font-semibold text-indigo-700 bg-indigo-50 rounded border border-indigo-200">
                              {{ $label }}
                            </span>
                          </div>
                          
                          {{-- Time slots as inline pills --}}
                          <div class="flex-1 flex flex-wrap gap-1.5">
                            @foreach ($slots as $slot)
                              <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-medium text-slate-700 bg-slate-100 rounded-md border border-slate-200 whitespace-nowrap">
                                {{ $fmtTime($slot->start_time) }}–{{ $fmtTime($slot->end_time) }}
                              </span>
                            @endforeach
                          </div>
                        </div>
                      @endforeach
                    </div>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-6 py-10 text-center text-slate-500">No counselors yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($counselors,'hasPages') && $counselors->hasPages())
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 screen-only">
        {{ $counselors->links() }}
      </div>
    @endif
  </div>
</div>

{{-- Alerts (kept lightweight) --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
@if (session('success'))
  Swal.fire({title:'Success',text:@json(session('success')),icon:'success',confirmButtonColor:'#4f46e5'});
@endif
@if (session('error'))
  Swal.fire({title:'Error',text:@json(session('error')),icon:'error',confirmButtonColor:'#ef4444'});
@endif

function confirmDelete(id){
  Swal.fire({
    title:'Delete counselor?',
    text:'This action cannot be undone.',
    icon:'warning',
    showCancelButton:true,
    confirmButtonText:'Yes, delete',
    cancelButtonText:'Cancel',
    confirmButtonColor:'#ef4444',
    cancelButtonColor:'#6b7280'
  }).then(r => { if(r.isConfirmed) document.getElementById('delete-form-'+id).submit(); });
}
</script>
@endsection
