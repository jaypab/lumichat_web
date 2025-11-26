{{-- resources/views/Counselor_Interface/walkins/create.blade.php --}}
@extends('layouts.counselor')
@section('title', 'Walk-in Session')
@section('page_title', 'Walk-in Session')

@section('content')
@php
  use Carbon\Carbon;
  $now   = Carbon::now();
  $today = $now->toDateString();

  // *** from controller: pass $canStartWalkin = true/false
  //    e.g. $canStartWalkin = $hasAnyAvailabilityForNow;
  $canStartWalkin = $canStartWalkin ?? true;
@endphp


<div class="max-w-4xl mx-auto">
  <div class="relative overflow-hidden rounded-2xl bg-white border border-slate-200 shadow-sm">

    @if($errors->has('availability'))
      <div class="mx-6 mt-4 mb-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ $errors->first('availability') }}
      </div>
    @endif
    {{-- top accent --}}
    <span class="pointer-events-none absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500"></span>

    {{-- HEADER + TIMER --}}
    <div class="px-6 pt-5 pb-4 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div class="max-w-2xl">
        <p class="text-[11px] font-semibold tracking-wide uppercase text-indigo-500 mb-1">
          Walk-in Session
        </p>
        <h2 class="text-[20px] font-semibold text-slate-900">New Walk-in Case</h2>
        <p class="mt-1 text-sm text-slate-500">
          For students who directly visit the Guidance Office without an online booking.
          Capture the basic details, then track the session from start to end.
        </p>
      </div>

      {{-- Timer + status --}}
      <div class="shrink-0 rounded-2xl bg-slate-50/60 border border-slate-200 px-4 py-3 flex flex-col items-end gap-1">
        <span id="walkinStatusChip"
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
          Not started
        </span>

        <div class="flex items-baseline gap-2 mt-1">
          <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">
            Session Timer
          </span>
        </div>

        <div class="flex items-baseline gap-2">
          <span id="sessionTimer"
                class="text-3xl font-mono font-semibold text-indigo-600">
            00:00
          </span>
        </div>

        <div id="sessionStartLabel" class="text-[11px] text-slate-400">
          Not started yet
        </div>

        <div class="text-[10px] text-slate-400">
          {{ $now->format('M d, Y · g:i A') }}
        </div>
      </div>
    </div>

    <div class="h-px bg-slate-200/70"></div>

    {{-- FORM --}}
    <form method="POST" action="{{ route('counselor.walkins.store') }}"
          class="px-6 py-5 space-y-7" id="walkinForm">
      @csrf

      {{-- Student details --}}
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
            Walk-in Details
          </h3>
          <span class="text-[11px] text-slate-400">
            Fields with <span class="text-rose-500">*</span> are required.
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Student Name <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="student_name" value="{{ old('student_name') }}"
                   class="block w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   placeholder="e.g., Juan Dela Cruz" required>
            @error('student_name')
              <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
          </div>

          <div>
        <label for="course" class="block text-sm font-medium text-slate-700 mb-1">
            Course / Program <span class="text-rose-500">*</span>
        </label>
        <div class="mt-1 rounded-xl border border-slate-300 px-3 focus-within:ring-2 focus-within:ring-indigo-500">
            <select id="course" name="course" required
                    class="w-full h-11 border-0 bg-transparent text-sm text-slate-900 focus:outline-none focus:ring-0">
            <option disabled {{ old('course') ? '' : 'selected' }}>Select course</option>
            <option value="BSIT"      {{ old('course') == 'BSIT' ? 'selected' : '' }}>College of Information Technology</option>
            <option value="EDUC"      {{ old('course') == 'EDUC' ? 'selected' : '' }}>College of Education</option>
            <option value="CAS"       {{ old('course') == 'CAS' ? 'selected' : '' }}>College of Arts and Sciences</option>
            <option value="CRIM"      {{ old('course') == 'CRIM' ? 'selected' : '' }}>College of Criminal Justice and Public Safety</option>
            <option value="BLIS"      {{ old('course') == 'BLIS' ? 'selected' : '' }}>College of Library Information Science</option>
            <option value="MIDWIFERY" {{ old('course') == 'MIDWIFERY' ? 'selected' : '' }}>College of Midwifery</option>
            <option value="BSHM"      {{ old('course') == 'BSHM' ? 'selected' : '' }}>College of Hospitality Management</option>
            <option value="BSBA"      {{ old('course') == 'BSBA' ? 'selected' : '' }}>College of Business</option>
            </select>
        </div>
        @error('course')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        </div>


          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Year Level <span class="text-rose-500">*</span>
            </label>
            <select name="year_level"
                    class="block w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
              <option value="" disabled {{ old('year_level') ? '' : 'selected' }}>Select year</option>
              <option value="1st"  {{ old('year_level') === '1st'  ? 'selected' : '' }}>1st Year</option>
              <option value="2nd"  {{ old('year_level') === '2nd'  ? 'selected' : '' }}>2nd Year</option>
              <option value="3rd"  {{ old('year_level') === '3rd'  ? 'selected' : '' }}>3rd Year</option>
              <option value="4th"  {{ old('year_level') === '4th'  ? 'selected' : '' }}>4th Year</option>
              <option value="Other"{{ old('year_level') === 'Other'? 'selected' : '' }}>Other</option>
            </select>
            @error('year_level')
              <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
          </div>
        </div>
      </section>

      {{-- Hidden time fields (JS fills these) --}}
    <section class="space-y-2">
    <input type="hidden" name="start_time" id="start_time_input" value="{{ old('start_time') }}">
    <input type="hidden" name="end_time"   id="end_time_input"   value="{{ old('end_time') }}">
    @error('start_time')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
    @error('end_time')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
    </section>

      {{-- Reason --}}
      <section class="space-y-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Brief Reason for Visit <span class="text-slate-400 text-xs">(optional)</span>
        </label>
        <textarea name="reason" rows="3"
                  class="block w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                  placeholder="e.g., feeling overwhelmed with academics, conflict with classmates, family concerns, etc.">{{ old('reason') }}</textarea>
        @error('reason')
          <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
      </section>

      {{-- =========================
           COUNSELOR CASE NOTE
         ========================= --}}
      <section id="caseNoteSection"
         class="space-y-4 border-t border-slate-100 pt-5 hidden">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
            Counselor Case Note (Walk-in)
          </h3>
          <span class="text-[11px] text-slate-400">
            Fill right after the session for proper documentation.
          </span>
        </div>

        @php
            $cn = old('case_note', []);
            $cnDate = $cn['date'] ?? $today;   // always today by default
        @endphp

        {{-- Header fields --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Student Name</label>
            <input type="text"
                   name="case_note[student_name]"
                   value="{{ $cn['student_name'] ?? old('student_name') }}"
                   class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                   required>
            @error('case_note.student_name')
              <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
            <input type="date"
                   name="case_note[date]"
                   value="{{ $cnDate }}"
                   class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                   required>
            @error('case_note.date')
              <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Program &amp; Year</label>
            <input type="text"
                   name="case_note[program_year]"
                   value="{{ $cn['program_year'] ?? (old('course') && old('year_level') ? old('course').' - '.old('year_level') : '') }}"
                   class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                   placeholder="e.g., BSIT - 3rd Year">
            @error('case_note.program_year')
              <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
            <input type="text"
                   name="case_note[address]"
                   value="{{ $cn['address'] ?? '' }}"
                   class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                   placeholder="Home address">
            @error('case_note.address')
              <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
          </div>
        </div>

        {{-- I. Presenting Problem --}}
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">I. Presenting Problem</label>
          <textarea name="case_note[presenting_problem]" rows="4" maxlength="4000" required
                    class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                    placeholder="Describe the client's main concerns...">{{ $cn['presenting_problem'] ?? old('reason') }}</textarea>
          @error('case_note.presenting_problem')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
          @enderror
        </div>

        {{-- II. Observations --}}
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">II. Observations</label>
          <textarea name="case_note[observations]" rows="4" maxlength="4000" required
                    class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                    placeholder="Counselor's observations (appearance, behavior, affect)...">{{ $cn['observations'] ?? '' }}</textarea>
          @error('case_note.observations')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
          @enderror
        </div>

        {{-- III. Interventions --}}
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">III. Interventions / Counselor’s Actions</label>
          <textarea name="case_note[interventions]" rows="4" maxlength="4000" required
                    class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                    placeholder="What was done during the session (e.g., CBT techniques, grounding)?">{{ $cn['interventions'] ?? '' }}</textarea>
          @error('case_note.interventions')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
          @enderror
        </div>

        {{-- IV. Response --}}
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">IV. Student’s Response / Insight</label>
          <textarea name="case_note[response]" rows="4" maxlength="4000" required
                    class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                    placeholder="How did the student respond? Key insights or changes observed.">{{ $cn['response'] ?? '' }}</textarea>
          @error('case_note.response')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
          @enderror
        </div>

        {{-- V. Plan / Follow-Up --}}
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">V. Plan / Follow-Up</label>
          <textarea name="case_note[plan_followup]" rows="4" maxlength="4000" required
                    class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                    placeholder="Next steps, referrals, frequency of sessions, etc.">{{ $cn['plan_followup'] ?? '' }}</textarea>
          @error('case_note.plan_followup')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
          @enderror
        </div>

        {{-- VI. Emergency Safety Plan --}}
        <div class="rounded-xl border border-slate-200 p-4 bg-white">
          <div class="text-[13px] font-medium text-slate-700 mb-3">VI. Emergency Safety Plan</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Contact Person</label>
              <input type="text" name="case_note[emergency_contact_person]" required
                     value="{{ $cn['emergency_contact_person'] ?? '' }}"
                     class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                     placeholder="Full name">
              @error('case_note.emergency_contact_person')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Relationship</label>
              <input type="text" name="case_note[emergency_relationship]" required
                     value="{{ $cn['emergency_relationship'] ?? '' }}"
                     class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                     placeholder="e.g., Mother">
              @error('case_note.emergency_relationship')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Contact No.</label>
              <input type="text" name="case_note[emergency_contact_no]" required
                     value="{{ $cn['emergency_contact_no'] ?? '' }}"
                     class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                     placeholder="+63 9xx xxx xxxx">
              @error('case_note.emergency_contact_no')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
              <input type="text" name="case_note[emergency_address]" required
                     value="{{ $cn['emergency_address'] ?? '' }}"
                     class="w-full rounded-lg border border-slate-200 focus:ring-2 focus:ring-indigo-500 p-3"
                     placeholder="Address">
              @error('case_note.emergency_address')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
              @enderror
            </div>
          </div>
        </div>
      </section>

        {{-- Actions --}}
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-4 border-t border-slate-100">
        <a href="{{ route('counselor.dashboard') }}"
           class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
          Cancel
        </a>

        <div class="flex items-center gap-2">
          <button type="button"
                  id="btnWalkinStart"
                  {{-- *** expose flag here --}}
                  data-can-start="{{ $canStartWalkin ? '1' : '0' }}"
                  class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold
                         text-indigo-700 bg-indigo-50 ring-1 ring-indigo-200 hover:bg-indigo-100">
            Start Session
          </button>

          <button type="submit"
                  id="btnWalkinEnd"
                  class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white
                         bg-gradient-to-r from-indigo-600 to-violet-600 shadow-sm hover:shadow-md hover:from-indigo-500 hover:to-violet-500">
            End Session &amp; Save Case Note
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@push('styles')
<style>
  .hidden { display: none; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
  const startBtn        = document.getElementById('btnWalkinStart');
  const endBtn          = document.getElementById('btnWalkinEnd');
  const timerEl         = document.getElementById('sessionTimer');
  const startLabel      = document.getElementById('sessionStartLabel');
  const statusChip      = document.getElementById('walkinStatusChip');
  const startInput      = document.getElementById('start_time_input');
  const endInput        = document.getElementById('end_time_input');
  const form            = document.getElementById('walkinForm');
  const caseNoteSection = document.getElementById('caseNoteSection');

  if (!form || !startBtn || !endBtn || !timerEl) return;

  const STORAGE_KEY      = 'lumichat_walkin_session_start';
  const FORM_STORAGE_KEY = 'lumichat_walkin_form';

  let sessionStart    = null;
  let timerInterval   = null;
  let caseNoteVisible = false;

  // flag from Blade
  const canStartFlag = startBtn.dataset.canStart === '1';

  // ===== TOP FIELDS =====
  const topNameInput   = form.querySelector('input[name="student_name"]');
  const topCourseInput = form.querySelector('[name="course"]');      // <select>
  const topYearSelect  = form.querySelector('select[name="year_level"]');
  const reasonTextarea = form.querySelector('textarea[name="reason"]');

  // ===== CASE NOTE HEADER FIELDS =====
  const cnNameInput   = form.querySelector('input[name="case_note[student_name]"]');
  const cnProgramYear = form.querySelector('input[name="case_note[program_year]"]');

  function pad(n){ return String(n).padStart(2,'0'); }

  function formatForTimeInput(date){
    const h = pad(date.getHours());
    const m = pad(date.getMinutes());
    return h + ':' + m;
  }

  function updateTimer(){
    if (!sessionStart) return;
    const diffMs   = Date.now() - sessionStart.getTime();
    const totalSec = Math.max(0, Math.floor(diffMs / 1000));
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    timerEl.textContent = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
  }

  function setStatusOngoing(){
    if (!statusChip) return;
    statusChip.className =
      'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800';
    const dot = statusChip.querySelector('span.rounded-full') || document.createElement('span');
    dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-indigo-600 mr-1.5';
    if (!dot.parentNode) statusChip.prepend(dot);
    statusChip.lastChild && statusChip.lastChild.nodeType === Node.TEXT_NODE
      ? statusChip.lastChild.textContent = ' Ongoing'
      : statusChip.append(' Ongoing');
  }

  function setStatusCompleted(){
    if (!statusChip) return;
    statusChip.className =
      'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800';
    const dot = statusChip.querySelector('span.rounded-full') || document.createElement('span');
    dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5';
    if (!dot.parentNode) statusChip.prepend(dot);
    statusChip.lastChild && statusChip.lastChild.nodeType === Node.TEXT_NODE
      ? statusChip.lastChild.textContent = ' Completed'
      : statusChip.append(' Completed');
  }

  // ===== SYNC CASE NOTE HEADER FROM TOP FIELDS =====
  function syncCaseNoteHeader() {
    if (cnNameInput && topNameInput) {
      cnNameInput.value = topNameInput.value;
    }

    if (cnProgramYear && topCourseInput && topYearSelect) {
      const c = (topCourseInput.value || '').trim();
      const y = (topYearSelect.value || '').trim();
      cnProgramYear.value = (c && y) ? (c + ' - ' + y) : (c || y);
    }
  }

  // ===== PERSIST FORM VALUES =====
  function saveFormToStorage() {
    const data = {
      student_name: topNameInput   ? topNameInput.value   : '',
      course:       topCourseInput ? topCourseInput.value : '',
      year_level:   topYearSelect  ? topYearSelect.value  : '',
      reason:       reasonTextarea ? reasonTextarea.value : '',
    };
    try {
      localStorage.setItem(FORM_STORAGE_KEY, JSON.stringify(data));
    } catch (_) {}
  }

  function restoreFormFromStorage() {
    const raw = localStorage.getItem(FORM_STORAGE_KEY);
    if (!raw) return;

    try {
      const data = JSON.parse(raw);
      if (topNameInput && data.student_name)   topNameInput.value   = data.student_name;
      if (topCourseInput && data.course)       topCourseInput.value = data.course;
      if (topYearSelect && data.year_level)    topYearSelect.value  = data.year_level;
      if (reasonTextarea && data.reason)       reasonTextarea.value = data.reason;
      // after restore, keep header in sync
      syncCaseNoteHeader();
    } catch (_) {
      localStorage.removeItem(FORM_STORAGE_KEY);
    }
  }

  // hook change/input events to save
  if (topNameInput)   topNameInput.addEventListener('input',  () => { syncCaseNoteHeader(); saveFormToStorage(); });
  if (topCourseInput) topCourseInput.addEventListener('change', () => { syncCaseNoteHeader(); saveFormToStorage(); });
  if (topYearSelect)  topYearSelect.addEventListener('change',  () => { syncCaseNoteHeader(); saveFormToStorage(); });
  if (reasonTextarea) reasonTextarea.addEventListener('input', () => { saveFormToStorage(); });

  // disable agad kung walang availability
  if (!canStartFlag) {
    startBtn.disabled = true;
    startBtn.classList.add('opacity-60', 'cursor-not-allowed');
  }

  // ===== RESTORE FORM + RUNNING SESSION (if any) =====
  restoreFormFromStorage();

  (function restoreFromStorage() {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return;

    const d = new Date(raw);
    if (isNaN(d.getTime())) {
      localStorage.removeItem(STORAGE_KEY);
      return;
    }

    sessionStart     = d;
    startInput.value = formatForTimeInput(sessionStart);
    setStatusOngoing();
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);

    startBtn.disabled = true;
    startBtn.classList.add('opacity-60', 'cursor-default');
  })();

  // ===== START SESSION =====
  startBtn.addEventListener('click', function (e){
    e.preventDefault();

    // HARD BLOCK if no available time
    if (!canStartFlag) {
      Swal.fire({
        icon: 'info',
        title: 'No available time',
        text: 'You have no available walk-in time slot right now. Please update your availability before starting a session.',
        confirmButtonColor: '#4f46e5'
      });
      return;
    }

    const nameOk   = topNameInput && topNameInput.value.trim() !== '';
    const courseOk = topCourseInput && topCourseInput.value.trim() !== '';
    const yearOk   = topYearSelect && topYearSelect.value && topYearSelect.value.trim() !== '';

    if (!nameOk || !courseOk || !yearOk) {
      Swal.fire({
        icon: 'warning',
        title: 'Complete the details first',
        text: 'Fill in Student Name, Course / Program, and Year Level before starting the session.',
        confirmButtonColor: '#f59e0b'
      });
      return;
    }

    if (sessionStart) return; // already running

    sessionStart        = new Date();
    startInput.value    = formatForTimeInput(sessionStart);
    startLabel.textContent =
      'Started at ' + sessionStart.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });

    // persist form + start time
    saveFormToStorage();
    localStorage.setItem(STORAGE_KEY, sessionStart.toISOString());

    setStatusOngoing();
    updateTimer();
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(updateTimer, 1000);

    startBtn.disabled = true;
    startBtn.classList.add('opacity-60', 'cursor-default');

    Swal.fire({
      icon: 'success',
      title: 'Session started',
      text: 'The walk-in session timer is now running.',
      timer: 1600,
      showConfirmButton: false
    });
  });

  // ===== END SESSION =====
  endBtn.addEventListener('click', function (e){
    if (!sessionStart) {
      e.preventDefault();
      Swal.fire({
        icon: 'info',
        title: 'Start the session first',
        text: 'Click “Start Session” to begin tracking time before ending the session.',
        confirmButtonColor: '#4f46e5'
      });
      return false;
    }

    const now = new Date();
    endInput.value = formatForTimeInput(now);

    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }

    if (!caseNoteVisible) {
      e.preventDefault();
      caseNoteVisible = true;

      syncCaseNoteHeader();
      saveFormToStorage(); // make sure latest top fields are saved

      if (caseNoteSection) {
        caseNoteSection.classList.remove('hidden');
        caseNoteSection.style.display = '';
        caseNoteSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      setStatusCompleted();
      endBtn.textContent = 'Save Case Note';
      return false;
    }

    // final submit → linis lahat ng local storage for walk-in
    localStorage.removeItem(STORAGE_KEY);
    localStorage.removeItem(FORM_STORAGE_KEY);

    setStatusCompleted();
    return true;
  });

})();
</script>
@endpush


