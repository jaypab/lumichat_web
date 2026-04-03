{{-- resources/views/admin/students/index.blade.php --}}
@extends('layouts.admin')

@section('title','Admin - Student Records')
@section('page_title', 'Manage Students')

@php
  use Illuminate\Support\Str;
@endphp

@section('content')
<script>
  // GLOBAL CLICK DEBUGGER: Logs every click to help identify if an invisible element is blocking the dropdown button.
  document.addEventListener('click', function(e) {
      console.log('DEBUGGER - Element Clicked:', e.target);
      console.log('DEBUGGER - Element Classes:', e.target.className);
      
      // Check if it's the 3-dot trigger specifically
      const trigger = e.target.closest('.dropdown-trigger');
      if (trigger) {
          console.log('DEBUGGER - 🎯 3-DOT TRIGGER SUCCESSFULLY CLICKED!');
          console.log('DEBUGGER - Parent Alpine state (if applicable):', trigger.closest('.relative')?.__x);
      }
  });
</script>


<div class="max-w-7xl mx-auto p-6 space-y-6">

  @php
    $totalStudents = method_exists($students, 'total') ? $students->total() : $students->count();
  @endphp

  {{-- ========= Page Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Student Records</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20">
          {{ number_format($totalStudents) }} Total
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">Manage and monitor student academic profiles and credentials.</p>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ route('admin.students.create') }}"
         class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 text-white px-4 py-2.5 text-sm font-bold shadow-sm shadow-indigo-500/20 hover:bg-indigo-700 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
        </svg>
        Add Student
      </a>

      <a href="{{ route('admin.students.export.pdf', request()->only('q','year')) }}"
         target="_blank" rel="noopener"
         class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
        </svg>
        Export PDF
      </a>
    </div>
  </header>

  {{-- ========= Filters (Modern Search) ========= --}}
  <form id="filterForm" method="GET" action="{{ route('admin.students.index') }}" class="screen-only">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="relative flex-1 group">
        <input
          id="q-input"
          type="text"
          name="q"
          value="{{ old('q', request('q')) }}"
          placeholder="Search by name, email, or SIS ID..."
          autocomplete="off"
          class="h-12 w-full rounded-2xl border-none bg-white px-11 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 group-hover:ring-slate-300"
        />
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400 group-hover:text-indigo-500 transition-colors duration-200"
             viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="11" cy="11" r="7" stroke-width="2.5"></circle>
          <path d="M21 21l-4.3-4.3" stroke-width="2.5" stroke-linecap="round"></path>
        </svg>

        @if(request('q'))
          <button type="button"
                  onclick="document.getElementById('q-input').value=''; document.getElementById('filterForm').submit();"
                  class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all"
                  aria-label="Clear search">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        @endif
      </div>

      <div class="flex items-center gap-2">
        <a href="{{ route('admin.students.index') }}"
           class="h-12 inline-flex items-center gap-2 rounded-2xl bg-white px-5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 group transition-all duration-200">
          <svg class="h-4 w-4 text-slate-400 group-hover:rotate-180 transition-transform duration-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <path d="M12 8v8M8 12h8" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
          Reset View
        </a>
      </div>
    </div>
  </form>

  {{-- ========= STUDENT TABLE (Enhanced) ========= --}}
  <div id="print-root" class="space-y-4">
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 overflow-visible transition-all duration-300">
      <div class="relative overflow-visible">
        <table class="min-w-full text-sm leading-relaxed table-auto">
          <thead x-data="{ scrolled: false }" 
                 x-init="const s = document.querySelector('.panel-scroll'); if(s){ s.addEventListener('scroll', () => scrolled = s.scrollTop > 10) }"
                 class="text-slate-700 sticky top-0 z-20">
            <tr>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                  :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">Student Details</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Contact Info</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Academic Info</th>
              <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 col-action transition-all duration-300"
                  :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            @forelse ($students as $s)
              <tr class="group hover:bg-indigo-50/30 transition-colors duration-200">
                {{-- Student Details (Avatar + Name + SIS) --}}
                <td class="px-6 py-5 whitespace-nowrap">
                  <div class="flex items-center gap-4">
                    <div class="relative flex-shrink-0">
                      <div class="w-12 h-12 rounded-2xl overflow-hidden bg-gradient-to-br from-indigo-100 to-slate-100 dark:from-indigo-900/20 dark:to-slate-800/20 flex items-center justify-center ring-2 ring-white shadow-sm group-hover:scale-105 transition-transform duration-300">
                        @if($s->avatar_url)
                          <img src="{{ $s->avatar_url }}" alt="{{ $s->name }}" class="w-full h-full object-cover">
                        @else
                          <span class="text-sm font-black text-indigo-600 bg-clip-text">{{ $s->initials }}</span>
                        @endif
                      </div>
                      <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 border-2 border-white shadow-sm"></div>
                    </div>
                    <div class="flex flex-col">
                      <span class="font-bold text-slate-900 group-hover:text-indigo-600 transition-colors duration-200">{{ $s->name }}</span>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-md font-bold tracking-wider uppercase border border-slate-200/50">SIS: {{ $s->sis ?? 'N/A' }}</span>
                      </div>
                    </div>
                  </div>
                </td>

                {{-- Contact Info (Email + Phone) --}}
                <td class="px-6 py-5 whitespace-nowrap">
                  <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2 text-slate-600 font-medium">
                      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                      </svg>
                      {{ $s->email }}
                    </div>
                    @if($s->contact_number)
                      <div class="flex items-center gap-2 text-slate-400 text-xs font-semibold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $s->contact_number }}
                      </div>
                    @endif
                  </div>
                </td>

                {{-- Academic Info (Course + Year) --}}
                <td class="px-6 py-5 whitespace-nowrap">
                  <div class="flex flex-wrap gap-2">
                    @if($s->course)
                      <span class="inline-flex items-center px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ $s->course }}
                      </span>
                    @endif
                    @if($s->year_level)
                      <span class="inline-flex items-center px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-violet-50 text-violet-700 border border-violet-100">
                        {{ $s->year_level }}
                      </span>
                    @endif
                  </div>
                </td>

                {{-- Actions (Alpine.js Dropdown) --}}
                <td class="px-6 py-5 text-right overflow-visible">
                  <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false" :class="{ 'z-50': open }">
                    <button type="button" 
                            @click="open = !open"
                            class="flex items-center justify-center ml-auto w-9 h-9 rounded-xl hover:bg-slate-100 transition-all duration-200 text-slate-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" 
                            title="Options">
                      <svg class="w-5 h-5 pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                      </svg>
                    </button>

                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-52 rounded-2xl bg-white shadow-2xl shadow-indigo-500/10 ring-1 ring-slate-200 divide-y divide-slate-100 z-50 origin-top-right"
                         style="display: none;">
                      <div class="px-2 py-2">
                        <a href="{{ route('admin.students.show', $s->id) }}" class="group flex items-center px-3 py-2.5 text-sm font-semibold text-slate-700 rounded-xl hover:bg-indigo-50 hover:text-indigo-700 transition-all duration-150">
                          <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 mr-3 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                          </div>
                          View Details
                        </a>
                      </div>

                      <div class="px-2 py-2 space-y-1">
                        <a href="mailto:{{ $s->email }}" class="group flex items-center px-3 py-2.5 text-sm font-semibold text-slate-700 rounded-xl hover:bg-emerald-50 hover:text-emerald-700 transition-all duration-150">
                          <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 group-hover:bg-emerald-100 group-hover:text-emerald-600 mr-3 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                          </div>
                          Send Email
                        </a>

                        <button type="button" onclick="copyToClipboard('{{ $s->email }}')" class="group flex w-full items-center px-3 py-2.5 text-sm font-semibold text-slate-700 rounded-xl hover:bg-amber-50 hover:text-amber-700 transition-all duration-150 text-left">
                          <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-500 group-hover:bg-amber-100 group-hover:text-amber-600 mr-3 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2.5"/>
                              <rect x="3" y="3" width="13" height="13" rx="2" ry="2" stroke-width="2.5"/>
                            </svg>
                          </div>
                          Copy Email
                        </button>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="px-6 py-20 text-center">
                  <div class="flex flex-col items-center">
                    <div class="p-4 rounded-3xl bg-slate-50 mb-3">
                      <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                      </svg>
                    </div>
                    <h3 class="text-slate-900 font-bold">No students found</h3>
                    <p class="text-slate-500 text-sm">Try adjusting your search or filters.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      </div>

      @if($students->hasPages())
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 not-print">
          {{ $students->appends(['q'=>request('q')])->links() }}
        </div>
      @endif
    </div>
  </div>
</div>

{{-- SweetAlert styling for success popup --}}
<style>
  .lumi-swal-popup {
      border-radius: 28px;
      padding: 2.5rem 2.75rem 2.25rem;
      box-shadow:
          0 24px 60px rgba(15,23,42,0.45),
          0 0 0 1px rgba(148,163,184,0.12);
      backdrop-filter: blur(14px);
  }

  .lumi-swal-icon {
      border-width: 0;
      margin: 0 auto 0.75rem auto;
      box-shadow: 0 0 0 6px rgba(34,197,94,0.18); /* green glow for success */
  }

  .lumi-swal-title {
      font-size: 1.35rem;
      font-weight: 700;
      color: #111827; /* slate-900 */
      margin-bottom: 0.4rem;
  }

  .lumi-swal-body {
      font-size: 0.9rem;
      color: #4b5563; /* slate-600 */
      text-align: center;
  }

  .lumi-swal-confirm {
      padding: 0.65rem 2.5rem;
      border-radius: 9999px;
      border: none;
      font-size: 0.9rem;
      font-weight: 600;
      background-image: linear-gradient(to right, #22c55e, #4ade80);
      color: #ffffff;
      box-shadow: 0 10px 25px rgba(34,197,94,0.45);
      transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  }

  .lumi-swal-confirm:hover {
      transform: translateY(-1px);
      filter: brightness(1.03);
      box-shadow: 0 16px 35px rgba(34,197,94,0.6);
  }

  .lumi-swal-confirm:active {
      transform: translateY(0);
      box-shadow: 0 8px 18px rgba(34,197,94,0.45);
  }
</style>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form   = document.getElementById('filterForm');
    const qInput = document.getElementById('q-input');

    // === existing search behavior ===
    if (form && qInput) {
      qInput.focus();
      const len = qInput.value.length;
      try {
        qInput.setSelectionRange(len, len);
      } catch (e) {}

      qInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();   // wag mag line break
          form.submit();
        }
      });
    }

    // === SUCCESS SWEETALERT (after create) ===
    @if(session('success'))
      Swal.fire({
        icon: 'success',
        title: 'Student added',
        html: `<p class="lumi-swal-body">{{ addslashes(session('success')) }}</p>`,
        allowOutsideClick: true,
        allowEscapeKey: true,
        buttonsStyling: false,
        backdrop: 'rgba(15,23,42,0.55)',
        customClass: {
          popup: 'lumi-swal-popup',
          title: 'lumi-swal-title',
          htmlContainer: 'lumi-swal-body',
          confirmButton: 'lumi-swal-confirm',
          icon: 'lumi-swal-icon'
        },
        confirmButtonText: 'OK'
      });
    @endif
  });

  // Old dropdown logic removed to prevent conflicts with the new atomic inline handler.

  // === copy email toast ===
  function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function () {
      if (window.Swal) {
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'Email copied',
          showConfirmButton: false,
          timer: 1500,
          timerProgressBar: true,
          buttonsStyling: false
        });
      }
    });
  }
</script>
@endpush
@endsection
