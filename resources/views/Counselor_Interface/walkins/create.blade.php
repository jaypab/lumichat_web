{{-- resources/views/Counselor_Interface/walkins/create.blade.php --}}
@extends('layouts.counselor')
@section('title', 'Walk-in Session')
@section('page_title', 'Walk-in Session')

@section('content')
  @php
    use Carbon\Carbon;
    $now = Carbon::now();
    $today = $now->toDateString();

    // *** from controller: pass $canStartWalkin = true/false
    //    e.g. $canStartWalkin = $hasAnyAvailabilityForNow;
    $canStartWalkin = $canStartWalkin ?? true;
  @endphp
  <style>
    #studentAccountInfo b {
      font-weight: 700;
    }
  </style>


  <div class="max-w-5xl mx-auto" x-data="walkinSession()">
    {{-- ========= EDGE-ALIGNED SINGLE-TRACK STEPPER ========= --}}
    <nav aria-label="Progress" class="mb-10 relative">
      {{-- Full-Width Background Track --}}
      <div class="absolute bottom-0 left-0 right-0 h-[2px] bg-slate-100 dark:bg-slate-800 rounded-full"></div>
      
      {{-- Full-Width Animated Progress Line --}}
      <div class="absolute bottom-0 left-0 h-[2px] bg-indigo-600 rounded-full transition-all duration-1000 ease-in-out shadow-[0_0_15px_rgba(79,70,229,0.4)]"
           :style="`width: ${step === 1 ? '33.33%' : (step === 2 ? '66.66%' : '100%')}`">
      </div>

      <ol role="list" class="flex items-center">
        {{-- Step 1 --}}
        <li class="flex-1 pb-6 transition-all duration-500 px-6 md:px-10">
          <div class="flex items-center gap-3">
             <span :class="step >= 1 ? 'text-indigo-600' : 'text-slate-300'" class="text-xs font-black transition-colors">01</span>
             <span :class="step >= 1 ? 'text-slate-900 dark:text-white' : 'text-slate-400'" class="text-sm font-bold tracking-tight transition-colors">Student Profile</span>
             <template x-if="step === 1">
               <div class="relative flex h-2 w-2">
                 <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                 <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
               </div>
             </template>
          </div>
        </li>

        {{-- Step 2 --}}
        <li class="flex-1 pb-6 transition-all duration-500 px-6 md:px-10">
          <div class="flex items-center gap-3">
             <span :class="step >= 2 ? 'text-indigo-600' : 'text-slate-300'" class="text-xs font-black transition-colors">02</span>
             <span :class="step >= 2 ? 'text-slate-900 dark:text-white' : 'text-slate-400'" class="text-sm font-bold tracking-tight transition-colors">Active Consultation</span>
             <template x-if="step === 2">
                <div class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                </div>
             </template>
          </div>
        </li>

        {{-- Step 3 --}}
        <li class="flex-1 pb-6 transition-all duration-500 px-6 md:px-10">
          <div class="flex items-center gap-3">
             <span :class="step >= 3 ? 'text-indigo-600' : 'text-slate-300'" class="text-xs font-black transition-colors">03</span>
             <span :class="step >= 3 ? 'text-slate-900 dark:text-white' : 'text-slate-400'" class="text-sm font-bold tracking-tight transition-colors">Documentation</span>
             <template x-if="step === 3">
                <div class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                </div>
             </template>
          </div>
        </li>
      </ol>
    </nav>

    <div
      class="relative overflow-visible rounded-[2rem] bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-2xl shadow-slate-200/60 dark:shadow-none min-h-[500px] flex flex-col">
      {{-- Top accent --}}

      {{-- Form --}}
      <form method="POST" action="{{ route('counselor.walkins.store') }}" id="walkinForm" class="flex-1 flex flex-col"
        @submit="localStorage.removeItem('lumichat_walkin_start'); localStorage.removeItem('lumichat_walkin_form');">
        @csrf

        {{-- Hidden Fields --}}
        <input type="hidden" name="start_time" x-model="formData.start_time">
        <input type="hidden" name="end_time" x-model="formData.end_time">

        {{-- ========= STEP 1: IDENTITY ========= --}}
        <div x-show="step === 1" x-transition:enter="transition ease-out duration-300"
          x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
          class="p-6 md:p-10 space-y-6 flex-1">
          <div class="max-w-2xl">
            <h2 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Identify Student
            </h2>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400 font-medium">Verify or create a student account to
              begin the session.</p>
          </div>

          {{-- Counselor Guidance Card --}}
          <div
            class="bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-100/50 dark:border-indigo-800/30 rounded-2xl p-5 md:p-6">
            <div class="flex items-start gap-4">
              <div class="p-2 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-xl">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="space-y-1">
                <h4
                  class="text-sm font-black text-indigo-900 dark:text-indigo-300 uppercase tracking-widest leading-none">
                  Counselor Guidance</h4>
                <p class="text-[13px] text-indigo-700/80 dark:text-indigo-400/80 font-medium leading-relaxed">
                  Follow these three steps to record a walk-in session:
                  <span class="block mt-2 font-bold">• Step 1: Identify</span> Enter the student's email to match their
                  record or auto-generate a new account.
                  <span class="block mt-1 font-bold">• Step 2: Session</span> Start the live timer and record the nature
                  of the visit as you talk.
                  <span class="block mt-1 font-bold">• Step 3: Document</span> Finalize the case note and save the session
                  permanently.
                </p>
              </div>
            </div>
          </div>

          <div class="space-y-6 max-w-3xl">
            <div class="relative group">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2">Full Name <span
                  class="text-rose-500">*</span></label>
              <input type="text" name="student_name" x-model="formData.student_name" :readonly="lockInput"
                :class="lockInput ? 'bg-slate-50 dark:bg-slate-800/50 cursor-not-allowed shadow-none' : 'focus:ring-2 focus:ring-indigo-500'"
                class="h-12 md:h-14 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm text-base font-semibold text-slate-900 dark:text-white placeholder:text-slate-300 dark:placeholder:text-slate-600 transition-all duration-200"
                placeholder="e.g., Juan Dela Cruz" required>

              <div x-show="studentChecked && studentData" x-transition class="absolute right-4 top-[2.75rem]">
                <div
                  class="flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-full ring-1 ring-inset ring-emerald-500/20">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                  Matched
                </div>
              </div>
            </div>

            <div class="relative group">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2">Email Address
                <span class="text-rose-500">*</span></label>
              <input type="email" name="email" x-model="formData.email" @input="studentChecked = false; lockInput = false"
                class="h-12 md:h-14 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm text-base font-semibold text-slate-900 dark:text-white placeholder:text-slate-300 dark:placeholder:text-slate-600 focus:ring-2 focus:ring-indigo-500 transition-all duration-200"
                placeholder="e.g., juan.tcc@example.com" required>
            </div>

            {{-- Custom Course Select --}}
            <div class="relative group" x-data="{ open: false }" @click.away="open = false">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2">Course / Program
                <span class="text-rose-500">*</span></label>
              <button type="button" @click="if(!lockInput) open = !open"
                :class="lockInput ? 'bg-slate-50 dark:bg-slate-800/50 cursor-not-allowed border-slate-200 dark:border-slate-700' : (open ? 'ring-2 ring-indigo-500 border-indigo-500' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600')"
                class="flex items-center justify-between min-h-[48px] md:min-h-[56px] w-full px-4 py-2 rounded-xl border bg-white dark:bg-slate-800 shadow-sm text-base font-semibold text-slate-900 dark:text-white transition-all duration-200">
                <span x-text="courses.find(cd => cd.val === formData.course)?.label || 'Select course'"
                  class="mr-4 leading-tight text-left" :class="!formData.course && 'text-slate-300'"></span>
                <svg class="w-4 h-4 text-slate-400 flex-shrink-0 transition-transform duration-200"
                  :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <input type="hidden" name="course" x-model="formData.course" required>

              <div x-show="open" x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                class="absolute z-50 bottom-full mb-2 w-full origin-bottom rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border border-slate-200/60 dark:border-slate-700 shadow-2xl py-2 overflow-hidden ring-1 ring-black/5">
                <div class="max-h-44 overflow-y-auto custom-scrollbar">
                  <template x-for="c in courses" :key="c.val">
                    <div @click="formData.course = c.val; open = false"
                      class="px-4 py-2 text-[13px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 hover:text-indigo-700 dark:hover:text-indigo-300 cursor-pointer transition-colors flex items-center justify-between gap-3">
                      <span x-text="c.label" class="leading-relaxed"></span>
                      <svg x-show="formData.course === c.val" class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                      </svg>
                    </div>
                  </template>
                </div>
              </div>
            </div>

            <div class="relative group" x-data="{ open: false }" @click.away="open = false">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2">Year Level <span
                  class="text-rose-500">*</span></label>
              <button type="button" @click="if(!lockInput) open = !open"
                :class="lockInput ? 'bg-slate-50 dark:bg-slate-800/50 cursor-not-allowed border-slate-200 dark:border-slate-700' : (open ? 'ring-2 ring-indigo-500 border-indigo-500' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600')"
                class="flex items-center justify-between min-h-[48px] md:min-h-[56px] w-full px-4 py-2 rounded-xl border bg-white dark:bg-slate-800 shadow-sm text-base font-semibold text-slate-900 dark:text-white transition-all duration-200">
                <span
                  x-text="formData.year_level ? (formData.year_level + (formData.year_level !== 'Other' ? ' Year' : '')) : 'Select year'"
                  class="mr-4 leading-tight text-left" :class="!formData.year_level && 'text-slate-300'"></span>
                <svg class="w-4 h-4 text-slate-400 flex-shrink-0 transition-transform duration-200"
                  :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <input type="hidden" name="year_level" x-model="formData.year_level" required>

              <div x-show="open" x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                class="absolute z-50 bottom-full mb-2 w-full origin-bottom rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border border-slate-200/60 dark:border-slate-700 shadow-2xl py-2 overflow-hidden ring-1 ring-black/5">
                <div class="max-h-48 overflow-y-auto custom-scrollbar">
                  <template x-for="y in ['1st', '2nd', '3rd', '4th', 'Other']">
                    <div @click="formData.year_level = y; open = false"
                      class="px-4 py-2 text-[13px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 hover:text-indigo-700 dark:hover:text-indigo-300 cursor-pointer transition-colors flex items-center justify-between gap-3">
                      <span x-text="y + (y !== 'Other' ? ' Year' : '')" class="leading-relaxed"></span>
                      <svg x-show="formData.year_level === y" class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                      </svg>
                    </div>
                  </template>
                </div>
              </div>
            </div>

            <div class="relative group">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2">Contact
                Number</label>
              <input type="text" name="contact_number" x-model="formData.contact_number"
                class="h-12 md:h-14 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm text-base font-semibold text-slate-900 dark:text-white placeholder:text-slate-300 dark:placeholder:text-slate-600 focus:ring-2 focus:ring-indigo-500 transition-all duration-200"
                placeholder="+63 9xx xxx xxxx">
            </div>
          </div>

          {{-- Lookup Info Card --}}
          <div x-show="studentChecked" x-transition
            class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5 flex items-start gap-4">
            <div :class="studentData?.created ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'"
              class="rounded-2xl p-3">
              <template x-if="studentData?.created">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
              </template>
              <template x-if="!studentData?.created">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </template>
            </div>
            <div>
              <h4 class="text-sm font-black text-slate-900 uppercase tracking-wide"
                x-text="studentData?.created ? 'New Account Generated' : 'Existing Student Linked'"></h4>
              <p class="text-sm text-slate-500 font-medium leading-relaxed mt-1" x-html="lookupMessage"></p>
              <template x-if="studentData?.student?.sis">
                <div
                  class="mt-2 text-[11px] font-bold text-indigo-600 bg-indigo-50 inline-block px-2 py-0.5 rounded-lg ring-1 ring-inset ring-indigo-500/20">
                  SIS: <span x-text="studentData.student.sis"></span>
                </div>
              </template>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="{{ route('counselor.dashboard') }}"
              class="px-6 py-3 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
            <button type="button" @click="handleStep1Next()" :disabled="lookupLoading"
              class="h-11 md:h-12 px-8 rounded-xl bg-slate-900 text-white text-sm font-bold shadow-lg shadow-slate-200 hover:bg-black hover:-translate-y-0.5 active:scale-95 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
              <span x-show="!lookupLoading">Next: Start Session</span>
              <span x-show="lookupLoading" class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                  </path>
                </svg>
                Verifying...
              </span>
            </button>
          </div>
        </div>

        {{-- ========= STEP 2: ACTIVE SESSION ========= --}}
        <div x-show="step === 2" x-transition:enter="transition ease-out duration-300"
          x-transition:enter-start="opacity-0 translate-x-12" x-transition:enter-end="opacity-100 translate-x-0"
          class="p-6 md:p-10 space-y-6 flex-1 bg-slate-50/30">

          <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="max-w-md">
              <h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Active Session</h2>
              <p class="mt-1.5 text-sm text-slate-500 font-medium leading-relaxed">
                Recording session time. You can record notes in the field below during your conversation. When finished,
                click <b>"End Session & Document"</b> to finalize the record.
              </p>
            </div>

            <div
              class="bg-white rounded-2xl border border-slate-200 shadow-lg p-6 flex flex-col items-center min-w-[200px]">
              <div class="relative">
                {{-- Pulse ring --}}
                <div class="absolute inset-0 rounded-full bg-indigo-500/20 animate-ping"></div>
                <div class="relative w-3.5 h-3.5 rounded-full bg-indigo-600 ring-4 ring-indigo-100"></div>
              </div>
              <span class="mt-3 text-[9px] font-black uppercase tracking-[0.2em] text-indigo-500">Live Duration</span>
              <div class="mt-1 text-4xl md:text-5xl font-mono font-black text-slate-900" x-text="timerDisplay">00:00</div>
              <div
                class="mt-3 text-[10px] font-semibold text-slate-400 bg-slate-50 px-3 py-1 rounded-full border border-slate-100"
                x-text="'Started at ' + startTimeNice"></div>
            </div>
          </div>

          <div class="space-y-4">
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500">Nature of Visit / Quick
              Reason</label>
            <textarea name="reason" x-model="formData.reason" rows="4"
              class="block w-full rounded-2xl border-slate-200 shadow-sm text-base font-semibold text-slate-900 placeholder:text-slate-300 focus:ring-2 focus:ring-indigo-500 p-5 transition-all duration-200"
              placeholder="e.g., feeling overwhelmed with academics, personal conflict, urgent emotional support..."></textarea>
            <p class="text-[10px] text-slate-400 font-medium">This reason will be auto-filled into the case note for
              documentation.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-8 border-t border-slate-200/60">
            <button type="button" @click="handleEndSession()"
              class="h-12 md:h-14 px-10 rounded-2xl bg-indigo-600 text-white text-base font-black shadow-xl shadow-indigo-100 hover:bg-indigo-700 hover:-translate-y-1 active:scale-95 transition-all duration-300 inline-flex items-center gap-3">
              End Session & Document
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
              </svg>
            </button>
          </div>
        </div>

        {{-- ========= STEP 3: DOCUMENTATION ========= --}}
        <div x-show="step === 3" x-transition:enter="transition ease-out duration-300"
          x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
          class="p-6 md:p-10 space-y-8 flex-1">

          <div class="flex flex-col md:flex-row md:items-end justify-between border-b border-slate-100 pb-6">
            <div>
              <h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Case Documentation</h2>
              <p class="mt-1.5 text-sm text-slate-500 font-medium leading-relaxed">
                Finalize the professional case note below. Ensure all sections are accurately documented before saving.
              </p>
            </div>
            <div class="mt-4 md:mt-0 text-right">
              <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Session Duration</div>
              <div class="text-lg font-mono font-bold text-slate-900" x-text="timerDisplay"></div>
            </div>
          </div>

          {{-- Case Note Sections --}}
          <div class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50/50 p-6 rounded-2xl border border-slate-100">
              <div class="md:col-span-2 text-xs font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Meta
                Information</div>

              <div class="space-y-2">
                <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">Student Name</label>
                <input type="text" name="case_note[student_name]" x-model="formData.student_name" readonly
                  class="h-12 w-full rounded-xl bg-white border-slate-200 text-sm font-bold text-slate-900 shadow-sm cursor-not-allowed">
              </div>

              <div class="space-y-2">
                <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">Note Date</label>
                <input type="date" name="case_note[date]" value="{{ $today }}" required
                  class="h-12 w-full rounded-xl border-slate-200 text-sm font-bold text-slate-900 shadow-sm focus:ring-2 focus:ring-indigo-500">
              </div>

              <div class="space-y-2">
                <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">Program & Year</label>
                <input type="text" name="case_note[program_year]" x-model="programYear" readonly
                  class="h-12 w-full rounded-xl bg-white border-slate-200 text-sm font-bold text-slate-900 shadow-sm cursor-not-allowed">
              </div>

              <div class="space-y-2">
                <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">Address</label>
                <input type="text" name="case_note[address]" x-model="formData.address"
                  class="h-12 w-full rounded-xl border-slate-200 text-sm font-bold text-slate-900 shadow-sm focus:ring-2 focus:ring-indigo-500"
                  placeholder="Home address">
              </div>
            </div>

            <div class="space-y-8">
              <div class="relative group">
                <label class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-black uppercase tracking-widest text-slate-500">I. Presenting
                    Problem</span>
                  <span class="text-[10px] text-slate-400" x-text="(formData.reason.length) + '/4000'"></span>
                </label>
                <textarea name="case_note[presenting_problem]" rows="4" x-model="formData.reason" required
                  maxlength="4000"
                  class="w-full rounded-2xl border-slate-200 focus:ring-2 focus:ring-indigo-500 p-5 text-sm font-medium leading-relaxed shadow-sm transition-all"
                  placeholder="Nature of visit..."></textarea>
              </div>

              <div class="relative group">
                <label class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-black uppercase tracking-widest text-slate-500">II. Observations</span>
                  <span class="text-[10px] text-slate-400" x-text="(formData.observations.length) + '/4000'"></span>
                </label>
                <textarea name="case_note[observations]" rows="4" x-model="formData.observations" required
                  maxlength="4000"
                  class="w-full rounded-2xl border-slate-200 focus:ring-2 focus:ring-indigo-500 p-5 text-sm font-medium leading-relaxed shadow-sm transition-all"
                  placeholder="Appearance, affect, behavior..."></textarea>
              </div>

              <div class="relative group">
                <label class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-black uppercase tracking-widest text-slate-500">III. Counselor’s
                    Actions</span>
                  <span class="text-[10px] text-slate-400" x-text="(formData.interventions.length) + '/4000'"></span>
                </label>
                <textarea name="case_note[interventions]" rows="4" x-model="formData.interventions" required
                  maxlength="4000"
                  class="w-full rounded-2xl border-slate-200 focus:ring-2 focus:ring-indigo-500 p-5 text-sm font-medium leading-relaxed shadow-sm transition-all"
                  placeholder="Techniques used, actions taken..."></textarea>
              </div>

              <div class="relative group">
                <label class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-black uppercase tracking-widest text-slate-500">IV. Student
                    Response</span>
                  <span class="text-[10px] text-slate-400" x-text="(formData.response.length) + '/4000'"></span>
                </label>
                <textarea name="case_note[response]" rows="4" x-model="formData.response" required maxlength="4000"
                  class="w-full rounded-2xl border-slate-200 focus:ring-2 focus:ring-indigo-500 p-5 text-sm font-medium leading-relaxed shadow-sm transition-all"
                  placeholder="Student's insight or change..."></textarea>
              </div>

              <div class="relative group">
                <label class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-black uppercase tracking-widest text-slate-500">V. Plan / Follow-Up</span>
                  <span class="text-[10px] text-slate-400" x-text="(formData.plan.length) + '/4000'"></span>
                </label>
                <textarea name="case_note[plan_followup]" rows="4" x-model="formData.plan" required maxlength="4000"
                  class="w-full rounded-2xl border-slate-200 focus:ring-2 focus:ring-indigo-500 p-5 text-sm font-medium leading-relaxed shadow-sm transition-all"
                  placeholder="Next steps, referrals..."></textarea>
              </div>

              {{-- VI. Emergency --}}
              <div class="rounded-2xl bg-rose-50/30 border border-rose-100 p-6 space-y-5">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-rose-100 text-rose-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                  </div>
                  <h3 class="text-sm font-black text-rose-900 uppercase tracking-widest">VI. Emergency Safety Plan</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-2">
                    <label class="text-[10px] font-bold uppercase text-rose-700">Contact Person</label>
                    <input type="text" name="case_note[emergency_contact_person]" x-model="formData.emergency_person"
                      required
                      class="h-12 w-full rounded-2xl bg-white border-rose-200 text-sm font-semibold focus:ring-2 focus:ring-rose-500">
                  </div>
                  <div class="space-y-2">
                    <label class="text-[10px] font-bold uppercase text-rose-700">Relationship</label>
                    <input type="text" name="case_note[emergency_relationship]" x-model="formData.emergency_rel" required
                      class="h-12 w-full rounded-2xl bg-white border-rose-200 text-sm font-semibold focus:ring-2 focus:ring-rose-500">
                  </div>
                  <div class="space-y-2">
                    <label class="text-[10px] font-bold uppercase text-rose-700">Contact No.</label>
                    <input type="text" name="case_note[emergency_contact_no]" x-model="formData.emergency_no" required
                      class="h-12 w-full rounded-2xl bg-white border-rose-200 text-sm font-semibold focus:ring-2 focus:ring-rose-500">
                  </div>
                  <div class="space-y-2">
                    <label class="text-[10px] font-bold uppercase text-rose-700">Address</label>
                    <input type="text" name="case_note[emergency_address]" x-model="formData.emergency_addr" required
                      class="h-12 w-full rounded-2xl bg-white border-rose-200 text-sm font-semibold focus:ring-2 focus:ring-rose-500">
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between pt-10 border-t border-slate-100">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Review carefully before saving.
              Data cannot be modified after.</p>
            <div class="flex items-center gap-4">
              <button type="button" @click="step = 2"
                class="px-6 py-3 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Go Back</button>
              <button type="submit"
                class="h-12 md:h-14 px-10 rounded-2xl bg-black text-white text-base font-black shadow-xl hover:bg-slate-900 hover:-translate-y-1 active:scale-95 transition-all duration-300 inline-flex items-center gap-3">
                Save Detailed Case Note
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('styles')
  <style>
    .custom-scrollbar::-webkit-scrollbar {
      width: 5px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
      background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
  </style>
@endpush

@push('scripts')
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('walkinSession', () => ({
        step: 1,
        lookupLoading: false,
        studentChecked: false,
        lockInput: false,
        studentData: null,
        lookupMessage: '',

        courses: [
          { val: 'BSIT', label: 'BSIT - Bachelor of Science in Information Technology' },
          { val: 'EDUC', label: 'BSED - Bachelor of Secondary Education' },
          { val: 'CAS', label: 'CAS - Bachelor of Arts and Sciences' },
          { val: 'CRIM', label: 'BSCRIM - Bachelor of Science in Criminology' },
          { val: 'BLIS', label: 'BLIS - Bachelor of Library and Information Science' },
          { val: 'MIDWIFERY', label: 'BSM - Bachelor of Science in Midwifery' },
          { val: 'BSHM', label: 'BSHM - Bachelor of Science in Hospitality Management' },
          { val: 'BSBA', label: 'BSBA - Bachelor of Science in Business Administration' }
        ],

        sessionStart: null,
        timerDisplay: '00:00',
        timerInterval: null,
        startTimeNice: '',

        formData: {
          student_name: '{{ old('student_name') }}',
          email: '{{ old('email') }}',
          course: '{{ old('course', '') }}',
          year_level: '{{ old('year_level', '') }}',
          contact_number: '{{ old('contact_number') }}',
          reason: '{{ old('reason') }}',
          observations: '',
          interventions: '',
          response: '',
          plan: '',
          address: '',
          emergency_person: '',
          emergency_rel: '',
          emergency_no: '',
          emergency_addr: '',
          start_time: '{{ old('start_time') }}',
          end_time: '{{ old('end_time') }}',
        },

        init() {
          this.restoreSession();
          this.$watch('formData.course', () => this.syncMeta());
          this.$watch('formData.year_level', () => this.syncMeta());
        },

        get programYear() {
          if (!this.formData.course && !this.formData.year_level) return '';
          return `${this.formData.course} - ${this.formData.year_level}`;
        },

        syncMeta() { },

        async handleStep1Next() {
          if (!this.formData.student_name || !this.formData.email || !this.formData.course || !this.formData.year_level) {
            Swal.fire({ icon: 'warning', title: 'Required Fields', text: 'Please fill in all required student details.' });
            return;
          }

          this.lookupLoading = true;
          try {
            const res = await fetch('{{ route('counselor.walkins.check_student') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
              },
              body: JSON.stringify(this.formData)
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Verification failed');

            this.studentData = data;
            this.studentChecked = true;

            if (data.student && !data.created) {
              this.formData.student_name = data.student.name;
              this.formData.course = data.student.course;
              this.formData.year_level = data.student.year_level;
              this.formData.contact_number = data.student.contact_number || '';
              this.lockInput = true;
              this.lookupMessage = 'Student record identified. Details auto-filled and locked to avoid mismatch. <b>Change email to unlock.</b>';
            } else {
              this.lockInput = false;
              this.lookupMessage = 'New student account will be generated upon saving.';
            }

            this.startSession();
            this.step = 2;
          } catch (err) {
            Swal.fire({ icon: 'error', title: 'Verification Error', text: err.message });
          } finally {
            this.lookupLoading = false;
          }
        },

        startSession() {
          if (this.sessionStart) return;
          this.sessionStart = new Date();
          this.formData.start_time = this.formatTime(this.sessionStart);
          this.startTimeNice = this.sessionStart.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          localStorage.setItem('lumichat_walkin_start', this.sessionStart.toISOString());
          localStorage.setItem('lumichat_walkin_form', JSON.stringify(this.formData));
          this.startTimer();
        },

        startTimer() {
          if (this.timerInterval) clearInterval(this.timerInterval);
          this.timerInterval = setInterval(() => {
            const diff = Date.now() - this.sessionStart.getTime();
            const hrs = Math.floor(diff / 3600000);
            const mins = Math.floor((diff % 3600000) / 60000);
            const secs = Math.floor((diff % 60000) / 1000);
            this.timerDisplay = (hrs > 0 ? this.pad(hrs) + ':' : '') + this.pad(mins) + ':' + this.pad(secs);
          }, 1000);
        },

        handleEndSession() {
          this.formData.end_time = this.formatTime(new Date());
          this.step = 3;
        },

        restoreSession() {
          const savedStart = localStorage.getItem('lumichat_walkin_start');
          const savedForm = localStorage.getItem('lumichat_walkin_form');
          if (savedForm) { try { Object.assign(this.formData, JSON.parse(savedForm)); } catch (e) { } }
          if (savedStart) {
            this.sessionStart = new Date(savedStart);
            this.startTimeNice = this.sessionStart.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            this.startTimer();
            this.step = 2;
          }
        },

        formatTime(date) {
          return date.getHours().toString().padStart(2, '0') + ':' + date.getMinutes().toString().padStart(2, '0');
        },

        pad(n) { return n.toString().padStart(2, '0'); }
      }));
    });
  </script>
@endpush