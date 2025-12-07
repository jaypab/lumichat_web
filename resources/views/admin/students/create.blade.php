{{-- resources/views/admin/students/create.blade.php --}}
@extends('layouts.admin')

@section('title','Admin - Add Student')
@section('page_title','Add Student')

@section('content')

@php
  $courses = [
      'BSIT'      => 'College of Information Technology',
      'EDUC'      => 'College of Education',
      'CAS'       => 'College of Arts and Sciences',
      'CRIM'      => 'College of Criminal Justice and Public Safety',
      'BLIS'      => 'College of Library Information Science',
      'MIDWIFERY' => 'College of Midwifery',
      'BSHM'      => 'College of Hospitality Management',
      'BSBA'      => 'College of Business',
  ];
@endphp

<div class="max-w-4xl mx-auto p-6 space-y-6">

  {{-- Header band --}}
  <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm screen-only">
    <div class="p-5 sm:p-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Add Student</h2>
        <p class="text-white/85 text-sm mt-0.5">
          Create a new student account in LumiCHAT.
        </p>
      </div>

      <a href="{{ route('admin.students.index') }}"
         class="inline-flex items-center gap-2 rounded-xl bg-white text-slate-900 px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 19l-7-7 7-7"/>
        </svg>
        Back to list
      </a>
    </div>
  </section>

  {{-- Form --}}
  <form method="POST"
        action="{{ route('admin.students.store') }}"
        class="space-y-5"
        novalidate>
    @csrf

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      {{-- ROW 1: SIS ID + FULL NAME --}}

        {{-- SIS ID --}}
    <div>
      <label for="sis" class="block text-xs font-semibold text-slate-600 mb-1 uppercase tracking-wide">
        SIS ID <span class="text-rose-500">*</span>
      </label>
      <input
        type="text"
        id="sis"
        name="sis"
        inputmode="numeric"
        value="{{ old('sis', $nextSis) }}"
        class="w-full h-10 rounded-xl border px-3 text-sm focus:ring-2
          @error('sis')
            border-rose-400 focus:border-rose-500 focus:ring-rose-500
          @else
            border-slate-200 focus:border-indigo-500 focus:ring-indigo-500
          @enderror"
        required
      >
      @error('sis')
        <p class="mt-1 text-xs text-rose-600" data-error-for="sis">{{ $message }}</p>
      @enderror
    </div>


      {{-- FULL NAME --}}
      <div>
        <label for="name" class="block text-xs font-semibold text-slate-600 mb-1 uppercase tracking-wide">
          Full Name <span class="text-rose-500">*</span>
        </label>
        <input
          type="text"
          id="name"
          name="name"
          value="{{ old('name') }}"
          class="w-full h-10 rounded-xl border px-3 text-sm focus:ring-2
            @error('name')
              border-rose-400 focus:border-rose-500 focus:ring-rose-500
            @else
              border-slate-200 focus:border-indigo-500 focus:ring-indigo-500
            @enderror"
          required
        >
        @error('name')
          <p class="mt-1 text-xs text-rose-600" data-error-for="name">{{ $message }}</p>
        @enderror
      </div>

      {{-- ROW 2: EMAIL + CONTACT NUMBER --}}

      {{-- EMAIL --}}
      <div>
        <label for="email" class="block text-xs font-semibold text-slate-600 mb-1 uppercase tracking-wide">
          Email <span class="text-rose-500">*</span>
        </label>
        <input
          type="email"
          id="email"
          name="email"
          value="{{ old('email') }}"
          class="w-full h-10 rounded-xl border px-3 text-sm focus:ring-2
            @error('email')
              border-rose-400 focus:border-rose-500 focus:ring-rose-500
            @else
              border-slate-200 focus:border-indigo-500 focus:ring-indigo-500
            @enderror"
          required
        >
        @error('email')
          <p class="mt-1 text-xs text-rose-600" data-error-for="email">{{ $message }}</p>
        @enderror
      </div>

      {{-- CONTACT NUMBER --}}
      <div>
        <label for="contact_number" class="block text-xs font-semibold text-slate-600 mb-1 uppercase tracking-wide">
          Contact Number
        </label>
        <input
          type="text"
          id="contact_number"
          name="contact_number"
          inputmode="numeric"
          value="{{ old('contact_number') }}"
          class="w-full h-10 rounded-xl border px-3 text-sm focus:ring-2
            @error('contact_number')
              border-rose-400 focus:border-rose-500 focus:ring-rose-500
            @else
              border-slate-200 focus:border-indigo-500 focus:ring-indigo-500
            @enderror"
        >
        @error('contact_number')
          <p class="mt-1 text-xs text-rose-600" data-error-for="contact_number">{{ $message }}</p>
        @enderror
      </div>

      {{-- ROW 3: COURSE + YEAR LEVEL --}}

      {{-- COURSE --}}
      <div>
        <label for="course" class="block text-xs font-semibold text-slate-600 mb-1 uppercase tracking-wide">
          Course
        </label>
        <select
          id="course"
          name="course"
          class="w-full h-10 rounded-xl border px-3 text-sm focus:ring-2
            @error('course')
              border-rose-400 focus:border-rose-500 focus:ring-rose-500
            @else
              border-slate-200 focus:border-indigo-500 focus:ring-indigo-500
            @enderror"
        >
          <option value="">Select course</option>
          @foreach ($courses as $code => $label)
            <option value="{{ $code }}" @selected(old('course') === $code)>
              {{ $code }} — {{ $label }}
            </option>
          @endforeach
        </select>
        @error('course')
          <p class="mt-1 text-xs text-rose-600" data-error-for="course">{{ $message }}</p>
        @enderror
      </div>

      {{-- YEAR LEVEL --}}
      <div>
        <label for="year_level" class="block text-xs font-semibold text-slate-600 mb-1 uppercase tracking-wide">
          Year Level
        </label>
        <select
          id="year_level"
          name="year_level"
          class="w-full h-10 rounded-xl border px-3 text-sm focus:ring-2
            @error('year_level')
              border-rose-400 focus:border-rose-500 focus:ring-rose-500
            @else
              border-slate-200 focus:border-indigo-500 focus:ring-indigo-500
            @enderror"
        >
          <option value="">Select year</option>
          @foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $yl)
            <option value="{{ $yl }}" @selected(old('year_level') === $yl)>{{ $yl }}</option>
          @endforeach
        </select>
        @error('year_level')
          <p class="mt-1 text-xs text-rose-600" data-error-for="year_level">{{ $message }}</p>
        @enderror
      </div>
    </div>

    <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-600">
      <p>
        <span class="font-semibold text-slate-800">Note:</span>
        New accounts are created with role <span class="font-semibold">student</span> and
        default password <span class="font-mono">12345678</span>. Ask the student to change
        their password after first login.
      </p>
    </div>

    <div class="flex items-center justify-end gap-2">
      <a href="{{ route('admin.students.index') }}"
         class="inline-flex items-center h-10 px-4 rounded-xl bg-white text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 active:scale-[.99]">
        Cancel
      </a>
      <button type="submit"
              class="inline-flex items-center h-10 px-5 rounded-xl bg-indigo-600 text-white text-sm font-semibold shadow-sm hover:bg-indigo-700 active:scale-[.99]">
        Save Student
      </button>
    </div>
  </form>
</div>

{{-- Modern SweetAlert styling for errors --}}
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
      box-shadow: 0 0 0 6px rgba(248,113,113,0.18); /* red glow for error */
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
      text-align: left;
  }

  .lumi-error-list {
      margin: 0;
      padding-left: 1.1rem;
  }

  .lumi-error-list li {
      margin-bottom: 0.15rem;
  }

  .lumi-swal-confirm {
      padding: 0.65rem 2.5rem;
      border-radius: 9999px;
      border: none;
      font-size: 0.9rem;
      font-weight: 600;
      background-image: linear-gradient(to right, #4f46e5, #a855f7); /* indigo → violet */
      color: #ffffff;
      box-shadow: 0 10px 25px rgba(79,70,229,0.45);
      transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  }

  .lumi-swal-confirm:hover {
      transform: translateY(-1px);
      filter: brightness(1.03);
      box-shadow: 0 16px 35px rgba(79,70,229,0.6);
  }

  .lumi-swal-confirm:active {
      transform: translateY(0);
      box-shadow: 0 8px 18px rgba(79,70,229,0.45);
  }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // === SweetAlert for validation errors ===
    const errors = @json($errors->all());

    if (Array.isArray(errors) && errors.length) {
        const listHtml = `
            <ul class="lumi-error-list">
                ${errors.map(e => `<li>${e}</li>`).join('')}
            </ul>
        `;

        Swal.fire({
            icon: 'error',
            title: 'Please fix the following',
            html: listHtml,
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
    }

    // === Live clearing of field errors (remove red + message on input/change) ===
    function setupLiveClear(fieldId, errorKey) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        const handler = () => {
            const errEl = document.querySelector(`[data-error-for="${errorKey}"]`);
            if (errEl) {
                errEl.remove();
            }

            field.classList.remove(
                'border-rose-400',
                'focus:border-rose-500',
                'focus:ring-rose-500'
            );
            field.classList.add(
                'border-slate-200',
                'focus:border-indigo-500',
                'focus:ring-indigo-500'
            );
        };

        field.addEventListener('input', handler);
        field.addEventListener('change', handler);
    }

    setupLiveClear('sis', 'sis');
    setupLiveClear('name', 'name');
    setupLiveClear('email', 'email');
    setupLiveClear('course', 'course');
    setupLiveClear('year_level', 'year_level');
    setupLiveClear('contact_number', 'contact_number');
});
</script>
@endpush
@endsection
