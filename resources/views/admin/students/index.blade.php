@extends('layouts.admin')
@section('title','Admin - Student Records')
@section('page_title', 'Manage Students') 

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  @php
    $totalStudents = method_exists($students, 'total') ? $students->total() : $students->count();
  @endphp

  {{-- ========= Page header / Toolbar (like Counselor Logs) ========= --}}
<div class="screen-only space-y-4">

  {{-- Row 1: title on the left, Download PDF on the right --}}
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Student Records</h2>
      <p class="text-sm text-slate-600">
        View and manage student accounts and their academic details.
        <span class="ml-2 text-slate-400">•</span>
        <span class="ml-2 text-slate-600">
          {{ $totalStudents }} {{ Str::plural('student', $totalStudents) }}
        </span>
      </p>
    </div>

  <a href="{{ route('admin.students.export.pdf', request()->only('q','year')) }}"
   target="_blank" rel="noopener"
   class="h-11 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 text-white shadow-sm
          hover:bg-emerald-700 active:scale-[.99] transition">
  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
  </svg>
  Download PDF
</a>

  </div>

  {{-- Row 2: filters row (search on the left; Reset/Apply on the right) --}}
  <form id="filterForm" method="GET" action="{{ route('admin.students.index') }}"
        class="flex flex-col gap-3 sm:flex-row sm:items-center">
    {{-- left side: search --}}
    <div class="relative w-full sm:max-w-sm">
      <input
        id="q-input"
        type="text"
        name="q"
        value="{{ old('q', request('q')) }}"
        placeholder="Search student"
        autocomplete="off"
        class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-10 text-sm
               focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
      />
      <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400"
           viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="11" cy="11" r="7" stroke-width="2"></circle>
        <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"></path>
      </svg>

      @if(request('q'))
        <button type="button"
                onclick="document.getElementById('q-input').value=''; document.getElementById('filterForm').submit();"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400
                       hover:bg-slate-100 hover:text-slate-600"
                aria-label="Clear search">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      @endif
    </div>

    {{-- spacer pushes buttons to the right --}}
    <div class="sm:ml-auto"></div>

    {{-- right side: Reset / Apply --}}
    <div class="flex items-center gap-2">
      <a href="{{ route('admin.students.index') }}"
         class="h-11 inline-flex items-center gap-2 rounded-xl bg-white px-4 text-slate-700 ring-1 ring-slate-200
                shadow-sm hover:bg-slate-50 hover:ring-slate-300 active:scale-[.99] transition">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h7M4 10h16M4 16h10"/>
        </svg>
        Reset
      </a>

      <button type="submit"
              class="h-11 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 text-white shadow-sm
                     hover:bg-indigo-700 active:scale-[.99] transition">
        Apply
      </button>
    </div>
  </form>
</div>


  {{-- ========= TABLE ========= --}}
  <div id="print-root" class="space-y-2">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
      <div class="relative overflow-x-auto">
        <table class="min-w-full text-sm leading-6 table-auto">
          <colgroup>
            <col style="width:24%">
            <col style="width:25%">
            <col style="width:18%">
            <col style="width:15%">
            <col style="width:15%">
            <col class="col-action" style="width:0">
          </colgroup>

          <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
            <tr class="align-middle">
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Student Name</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Email</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Contact No.</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Course</th>
              <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap">Year Level</th>
              <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px] whitespace-nowrap col-action">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            @forelse ($students as $s)
              <tr class="align-middle even:bg-slate-50 hover:bg-slate-100/60 transition">
                <td class="px-6 py-4 whitespace-nowrap font-semibold text-slate-900">{{ $s->name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $s->email }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ $s->contact_number ?? '—' }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  @if($s->course)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">
                      {{ $s->course }}
                    </span>
                  @else
                    <span class="text-slate-400">—</span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  @if($s->year_level)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs bg-violet-50 text-violet-700 ring-1 ring-violet-200">
                      {{ $s->year_level }}
                    </span>
                  @else
                    <span class="text-slate-400">—</span>
                  @endif
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                    <a href="{{ route('admin.students.show', $s->id) }}"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-600 text-white hover:-translate-y-0.5 active:scale-[.98] transition"
                       title="View" aria-label="View student">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                        <circle cx="12" cy="12" r="3" stroke-width="2" />
                      </svg>
                    </a>

                    <a href="mailto:{{ $s->email }}"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300 active:scale-[.98] transition"
                       title="Send Email" aria-label="Send email">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                      </svg>
                    </a>

                    <button type="button" onclick="copyToClipboard('{{ $s->email }}')"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300 active:scale-[.98] transition"
                            title="Copy Email" aria-label="Copy email">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/>
                        <rect x="3" y="3" width="13" height="13" rx="2" ry="2" stroke-width="2"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-6 py-10 text-center text-slate-500">No students found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($students->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 not-print">
          {{ $students->appends(['q'=>request('q')])->links() }}
        </div>
      @endif
    </div>
  </div>
</div>

{{-- No auto-submit, no print JS --}}
<script>
  function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function () {
      if (window.Swal) {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Email copied', showConfirmButton:false, timer:1500 });
      }
    });
  }
</script>
@endsection
