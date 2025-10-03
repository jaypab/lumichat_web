@extends('layouts.admin')
@section('title','Admin · Counselors')

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">
  {{-- Header --}}
  @php $total = method_exists($counselors,'total') ? $counselors->total() : $counselors->count(); @endphp
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-800">Counselors</h2>
      <p class="text-sm text-slate-500">Manage counselor profiles and weekly availability
        <span class="mx-2 text-slate-300">•</span>
        <span class="text-slate-600">{{ $total }} {{ Str::plural('counselor', $total) }}</span>
      </p>
    </div>

    <a href="{{ route('admin.counselors.create') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-xl shadow-sm hover:bg-indigo-700 active:scale-[.99]">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Add Counselor
    </a>
  </div>

  {{-- Table --}}
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
    <div class="relative overflow-x-auto">
      <table class="min-w-full text-sm leading-6 table-auto">
        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
          <tr class="align-middle">
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Counselor</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Contact</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Status / Load</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] w-[18rem] md:w-[20rem]">Upcoming Appointment</th>
            <th class="px-6 lg:pl-12 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Weekly Availability</th>
            <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px]">Action</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          @forelse ($counselors as $c)
            <tr class="align-top even:bg-slate-50 hover:bg-slate-100/60 transition">
              {{-- Counselor --}}
              <td class="px-6 py-4 min-w-[14rem]">
                <div class="font-semibold text-slate-900 truncate">{{ $c->name }}</div>
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
              <td class="px-6 py-4 max-w-[16rem]">
                <div class="truncate" title="{{ $c->email }}">
                  <a class="hover:underline" href="mailto:{{ $c->email }}">{{ $c->email }}</a>
                </div>
                @if($c->phone)
                  <div class="text-slate-500">
                    <a class="hover:underline" href="tel:{{ $c->phone }}">{{ $c->phone }}</a>
                  </div>
                @endif
              </td>

              {{-- Status / Load --}}
              <td class="px-6 py-4 min-w-[11rem]">
                @if(!empty($c->is_busy_now))
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200 text-[12px]">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Busy now
                  </span>
                  <div class="text-[12px] text-slate-600 mt-1">until {{ optional($c->busy_until_c)->format('g:i A') ?: '—' }}</div>
                @else
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 text-[12px]">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Free now
                  </span>
                @endif
                <div class="flex flex-wrap gap-1.5 mt-1 text-[12px] text-slate-600">
                  <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5">Today: <b class="ml-1 tabular-nums">{{ (int)($c->today_count ?? 0) }}</b></span>
                  <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5">Upcoming: <b class="ml-1 tabular-nums">{{ (int)($c->upcoming_count ?? 0) }}</b></span>
                </div>
              </td>

              {{-- UPCOMING APPOINTMENT --}}
              <td class="px-6 py-4 w-[18rem] md:w-[20rem] align-top">
                <div class="flex flex-col gap-1">
                  @if(!empty($c->next_appt_id) && !empty($c->next_at_c))
                    <a href="{{ route('admin.appointments.show', $c->next_appt_id) }}"
                      class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[12px] text-slate-700 ring-1 ring-slate-200 hover:bg-indigo-50 hover:ring-indigo-200 transition">
                      {{ $c->next_at_c->format('M d, g:i A') }}
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

              {{-- Weekly availability --}}
              <td class="px-6 py-4">
                @php $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; @endphp
                <div class="grid grid-cols-2 gap-1.5 w-max ml-6"> {{-- ⬅️ 2 columns + spacing + pushed right --}}
                  @forelse ($c->availabilities->groupBy('weekday') as $weekday => $slots)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200 whitespace-nowrap">
                      <strong>{{ $days[$weekday] }}:</strong>
                      @foreach ($slots as $slot)
                        {{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}@if(!$loop->last),@endif
                      @endforeach
                    </span>
                  @empty
                    <span class="text-slate-400 text-xs">No slots</span>
                  @endforelse
                </div>
              </td>

              {{-- Actions --}}
              <td class="px-6 py-4 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-2">
                  <a href="{{ route('admin.counselors.edit',$c) }}"
                     class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300 active:scale-[.97] transition"
                     title="Edit">✏️</a>
                  <form id="delete-form-{{ $c->id }}" action="{{ route('admin.counselors.destroy',$c) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="button" onclick="confirmDelete({{ $c->id }})"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-600/10 text-rose-700 ring-1 ring-rose-200 hover:bg-rose-600/15 hover:ring-rose-300 active:scale-[.97] transition"
                            title="Delete">🗑️</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-6 py-10 text-center text-slate-500">No counselors yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($counselors->hasPages())
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200">
        {{ $counselors->links() }}
      </div>
    @endif
  </div>
</div>

{{-- Alerts --}}
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
