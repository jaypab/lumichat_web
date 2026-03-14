@extends('layouts.student-guest')

@section('content')
<style>
  #accountRequestForm select {
    color-scheme: dark;
  }

  #accountRequestForm select option {
    color: #f8fafc;
    background: #0b1120;
  }

  #accountRequestForm select option[value=""] {
    color: #94a3b8;
  }
</style>

<div id="beams-root" class="fixed inset-0 z-0 pointer-events-none overflow-hidden"></div>

<div class="relative z-10 min-h-screen w-full flex items-center justify-center overflow-hidden p-6 md:p-10">
  <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">
    <section class="hidden lg:flex lg:col-span-2 flex-col justify-center text-left pr-4">
      <p class="text-[10px] font-black tracking-[0.35em] text-amber-300/85 uppercase">Guidance & Counseling Office</p>
      <h2 class="mt-3 text-4xl font-bold tracking-tight text-white leading-tight">Request Your LumiChat Access</h2>
      <p class="mt-4 text-sm leading-relaxed text-slate-300/85 max-w-sm">
        Submit your student details and proof of enrollment. Your request will be reviewed by the admin team before account activation.
      </p>
      <div class="mt-5 rounded-xl border border-amber-300/25 bg-violet-500/10 px-4 py-3 text-sm text-violet-100/95 max-w-md">
        <p class="font-semibold text-amber-200">Before you submit:</p>
        <ul class="mt-2 space-y-1.5 list-disc pl-5 text-violet-100/95">
          <li><span class="font-semibold text-amber-100">Review time:</span> Admin review takes 2-3 business days.</li>
          <li><span class="font-semibold text-amber-100">Delivery:</span> Account setup details will be sent to your email.</li>
          <li><span class="font-semibold text-amber-100">Important:</span> Use a valid, active email address.</li>
        </ul>
      </div>
      <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-violet-300/30 bg-violet-500/10 px-3 py-1.5 text-[11px] font-semibold text-violet-200 w-fit">
        <span class="inline-block h-1.5 w-1.5 rounded-full bg-violet-300 animate-pulse"></span>
        Approval Required
      </div>
    </section>

    <section class="lg:col-span-3 rounded-[2.5rem] border border-white/10 bg-white/5 backdrop-blur-3xl shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] overflow-hidden relative group">
      <div class="absolute inset-0 bg-gradient-to-br from-violet-500/5 to-fuchsia-500/5 pointer-events-none"></div>
      <div class="p-8 sm:p-10 relative z-10">
        <div class="text-center lg:text-left">
          <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">Request Student Account</h1>
          <p class="text-sm text-violet-200/80 mt-2">Submit your details and proof of enrollment. Admin will review your request.</p>
        </div>

        @if ($errors->any())
          <div class="mt-5 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc pl-5 space-y-0.5">
              @foreach ($errors->all() as $error)
                @if($error === $errors->first('request_access'))
                  @continue
                @endif
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form id="accountRequestForm" method="POST" action="{{ route('account-request.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
          @csrf
          <input type="hidden" name="device_key" id="deviceKeyInput" value="{{ old('device_key') }}">

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="sis" class="block text-xs font-semibold uppercase tracking-wide text-violet-200/90 mb-1.5">SIS ID</label>
              <input id="sis" name="sis" type="text" value="{{ old('sis') }}" required data-persist-field
                    data-error-field="sis"
                    @class([
                      'w-full rounded-xl bg-black/20 px-3 py-2.5 text-sm text-white placeholder:text-slate-400 focus:ring-2',
                      'border border-rose-400/70 focus:border-rose-400 focus:ring-rose-500/30' => $errors->has('sis'),
                      'border border-white/15 focus:border-violet-400 focus:ring-violet-500/40' => !$errors->has('sis'),
                    ]) />
              @error('sis')<p class="mt-1 text-xs text-rose-300" data-error-message="sis">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-violet-200/90 mb-1.5">Full Name</label>
              <input id="name" name="name" type="text" value="{{ old('name') }}" required data-persist-field
                    data-error-field="name"
                    @class([
                      'w-full rounded-xl bg-black/20 px-3 py-2.5 text-sm text-white placeholder:text-slate-400 focus:ring-2',
                      'border border-rose-400/70 focus:border-rose-400 focus:ring-rose-500/30' => $errors->has('name'),
                      'border border-white/15 focus:border-violet-400 focus:ring-violet-500/40' => !$errors->has('name'),
                    ]) />
              @error('name')<p class="mt-1 text-xs text-rose-300" data-error-message="name">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-violet-200/90 mb-1.5">Email</label>
              <input id="email" name="email" type="email" value="{{ old('email') }}" required data-persist-field
                    data-error-field="email"
                    @class([
                      'w-full rounded-xl bg-black/20 px-3 py-2.5 text-sm text-white placeholder:text-slate-400 focus:ring-2',
                      'border border-rose-400/70 focus:border-rose-400 focus:ring-rose-500/30' => $errors->has('email'),
                      'border border-white/15 focus:border-violet-400 focus:ring-violet-500/40' => !$errors->has('email'),
                    ]) />
              @error('email')<p class="mt-1 text-xs text-rose-300" data-error-message="email">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="contact_number" class="block text-xs font-semibold uppercase tracking-wide text-violet-200/90 mb-1.5">Contact Number</label>
              <input id="contact_number" name="contact_number" type="text" value="{{ old('contact_number') }}" required data-persist-field
                    data-error-field="contact_number"
                    @class([
                      'w-full rounded-xl bg-black/20 px-3 py-2.5 text-sm text-white placeholder:text-slate-400 focus:ring-2',
                      'border border-rose-400/70 focus:border-rose-400 focus:ring-rose-500/30' => $errors->has('contact_number'),
                      'border border-white/15 focus:border-violet-400 focus:ring-violet-500/40' => !$errors->has('contact_number'),
                    ]) />
              @error('contact_number')<p class="mt-1 text-xs text-rose-300" data-error-message="contact_number">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="course" class="block text-xs font-semibold uppercase tracking-wide text-violet-200/90 mb-1.5">Course</label>
              <select id="course" name="course" required data-persist-field
                      data-error-field="course"
                      @class([
                        'w-full rounded-xl bg-black/20 px-3 py-2.5 text-sm text-white focus:ring-2',
                        'border border-rose-400/70 focus:border-rose-400 focus:ring-rose-500/30' => $errors->has('course'),
                        'border border-white/15 focus:border-violet-400 focus:ring-violet-500/40' => !$errors->has('course'),
                      ])>
                <option value="">Select course</option>
                @foreach (['BSIT','EDUC','CAS','CRIM','BLIS','MIDWIFERY','BSHM','BSBA'] as $course)
                  <option value="{{ $course }}" @selected(old('course') === $course)>{{ $course }}</option>
                @endforeach
              </select>
              @error('course')<p class="mt-1 text-xs text-rose-300" data-error-message="course">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="year_level" class="block text-xs font-semibold uppercase tracking-wide text-violet-200/90 mb-1.5">Year Level</label>
              <select id="year_level" name="year_level" required data-persist-field
                      data-error-field="year_level"
                      @class([
                        'w-full rounded-xl bg-black/20 px-3 py-2.5 text-sm text-white focus:ring-2',
                        'border border-rose-400/70 focus:border-rose-400 focus:ring-rose-500/30' => $errors->has('year_level'),
                        'border border-white/15 focus:border-violet-400 focus:ring-violet-500/40' => !$errors->has('year_level'),
                      ])>
                <option value="">Select year level</option>
                @foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $level)
                  <option value="{{ $level }}" @selected(old('year_level') === $level)>{{ $level }}</option>
                @endforeach
              </select>
              @error('year_level')<p class="mt-1 text-xs text-rose-300" data-error-message="year_level">{{ $message }}</p>@enderror
            </div>
          </div>

          <div>
            <label for="attachment" class="block text-sm font-semibold tracking-tight text-white mb-1">Proof of Enrollment or TCC ID</label>
            <p class="mb-2 text-xs leading-relaxed text-violet-200/75">Accepted proof: COR or school ID. File types: JPG, PNG, or PDF up to 5MB.</p>
            <input id="attachment" name="attachment" type="file" accept=".jpg,.jpeg,.png,.pdf" required
              data-error-field="attachment"
              @class([
                'block w-full rounded-xl bg-black/20 px-3 py-2 text-sm text-white file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white',
                'border border-rose-400/70 file:bg-rose-500 hover:file:bg-rose-400' => $errors->has('attachment'),
                'border border-white/15 file:bg-violet-500 hover:file:bg-violet-400' => !$errors->has('attachment'),
              ]) />
            @error('attachment')<p class="mt-1 text-xs text-rose-300" data-error-message="attachment">{{ $message }}</p>@enderror
            <div id="attachmentPreview" class="mt-3 hidden rounded-2xl border border-white/10 bg-black/20 p-3">
              <div class="flex items-center justify-between gap-3 mb-3">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-200/80">Preview</p>
                <div class="flex items-center gap-3 min-w-0">
                  <p id="attachmentMeta" class="text-xs text-slate-300 truncate"></p>
                  <button
                    id="removeAttachmentBtn"
                    type="button"
                    class="inline-flex items-center rounded-lg border border-rose-300/25 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-100 transition hover:bg-rose-500/20"
                  >
                    Remove
                  </button>
                </div>
              </div>
              <div id="attachmentPreviewBody" class="overflow-hidden rounded-xl border border-white/10 bg-black/30"></div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
            <button id="submitRequestBtn" type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-violet-500 via-indigo-500 to-fuchsia-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:brightness-110">
              Submit Request
            </button>
            <a href="{{ route('login') }}"
              class="inline-flex items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-medium text-violet-100 transition hover:bg-white/10">
              Back to Login
            </a>
          </div>
        </form>
      </div>
    </section>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('accountRequestForm');
  const submitBtn = document.getElementById('submitRequestBtn');
  const attachmentInput = document.getElementById('attachment');
  const attachmentPreview = document.getElementById('attachmentPreview');
  const attachmentPreviewBody = document.getElementById('attachmentPreviewBody');
  const attachmentMeta = document.getElementById('attachmentMeta');
  const removeAttachmentBtn = document.getElementById('removeAttachmentBtn');
  const persistKey = 'lumichat.accountRequestDraft';
  const deviceStorageKey = 'lumichat.deviceKey';
  const persistFields = Array.from(document.querySelectorAll('[data-persist-field]'));
  const errorFields = Array.from(document.querySelectorAll('[data-error-field]'));
  const requestAccessBlockedMessage = @json($errors->first('request_access'));
  const deviceKeyInput = document.getElementById('deviceKeyInput');
  if (!form) return;

  const ensureDeviceKey = () => {
    if (!deviceKeyInput) return;

    if (deviceKeyInput.value && deviceKeyInput.value.trim() !== '') {
      try {
        localStorage.setItem(deviceStorageKey, deviceKeyInput.value.trim());
      } catch (_) {}
      return;
    }

    let key = '';
    try {
      key = localStorage.getItem(deviceStorageKey) || '';
    } catch (_) {
      key = '';
    }

    if (!key) {
      const randomPart = (self.crypto && crypto.randomUUID)
        ? crypto.randomUUID()
        : (Math.random().toString(36).slice(2) + Date.now().toString(36));
      key = 'ldk_' + randomPart;
      try {
        localStorage.setItem(deviceStorageKey, key);
      } catch (_) {}
    }

    deviceKeyInput.value = key;
  };

  ensureDeviceKey();

  if (requestAccessBlockedMessage && typeof Swal !== 'undefined') {
    Swal.fire({
      title: 'Request blocked',
      text: requestAccessBlockedMessage,
      icon: 'warning',
      confirmButtonText: 'Okay',
      confirmButtonColor: '#7c3aed',
      background: '#0f172a',
      color: '#e2e8f0'
    });
  }

  let confirmed = false;
  let currentPreviewUrl = null;

  const saveDraft = () => {
    if (!persistFields.length) return;
    const draft = {};
    persistFields.forEach((field) => {
      draft[field.name] = field.value;
    });
    try {
      sessionStorage.setItem(persistKey, JSON.stringify(draft));
    } catch (_) {}
  };

  const restoreDraft = () => {
    if (!persistFields.length) return;
    try {
      const raw = sessionStorage.getItem(persistKey);
      if (!raw) return;
      const draft = JSON.parse(raw);
      persistFields.forEach((field) => {
        if ((field.value ?? '') !== '') return;
        if (typeof draft[field.name] === 'string') {
          field.value = draft[field.name];
        }
      });
    } catch (_) {}
  };

  const clearDraft = () => {
    try {
      sessionStorage.removeItem(persistKey);
    } catch (_) {}
  };

  const clearPreviewUrl = () => {
    if (currentPreviewUrl) {
      URL.revokeObjectURL(currentPreviewUrl);
      currentPreviewUrl = null;
    }
  };

  const clearAttachmentPreview = () => {
    clearPreviewUrl();
    if (attachmentInput) {
      attachmentInput.value = '';
    }
    if (attachmentPreviewBody) {
      attachmentPreviewBody.innerHTML = '';
    }
    if (attachmentMeta) {
      attachmentMeta.textContent = '';
    }
    if (attachmentPreview) {
      attachmentPreview.classList.add('hidden');
    }
  };

  restoreDraft();
  persistFields.forEach((field) => {
    ['input', 'change', 'blur'].forEach((eventName) => {
      field.addEventListener(eventName, saveDraft);
    });
  });

  const clearFieldErrorState = (field) => {
    const key = field.dataset.errorField;
    if (!key) return;

    field.classList.remove('border-rose-400/70', 'focus:border-rose-400', 'focus:ring-rose-500/30');
    field.classList.add('border-white/15', 'focus:border-violet-400', 'focus:ring-violet-500/40');

    if (field.type === 'file') {
      field.classList.remove('file:bg-rose-500', 'hover:file:bg-rose-400');
      field.classList.add('file:bg-violet-500', 'hover:file:bg-violet-400');
    }

    const errorMessage = document.querySelector(`[data-error-message="${key}"]`);
    if (errorMessage) {
      errorMessage.remove();
    }
  };

  errorFields.forEach((field) => {
    const eventName = field.tagName === 'SELECT' || field.type === 'file' ? 'change' : 'input';
    field.addEventListener(eventName, () => clearFieldErrorState(field), { once: true });
  });

  const renderAttachmentPreview = () => {
    if (!attachmentInput || !attachmentPreview || !attachmentPreviewBody || !attachmentMeta) return;

    clearPreviewUrl();
    attachmentPreviewBody.innerHTML = '';

    const file = attachmentInput.files && attachmentInput.files[0];
    if (!file) {
      attachmentPreview.classList.add('hidden');
      attachmentMeta.textContent = '';
      return;
    }

    attachmentPreview.classList.remove('hidden');
    const fileSizeMb = (file.size / (1024 * 1024)).toFixed(2);
    attachmentMeta.textContent = `${file.name} • ${fileSizeMb} MB`;

    currentPreviewUrl = URL.createObjectURL(file);

    if (file.type === 'application/pdf') {
      attachmentPreviewBody.innerHTML = `
        <iframe
          src="${currentPreviewUrl}#toolbar=0&navpanes=0&scrollbar=1"
          title="PDF preview"
          class="h-64 w-full bg-white"
        ></iframe>`;
      return;
    }

    if (file.type.startsWith('image/')) {
      attachmentPreviewBody.innerHTML = `
        <img
          src="${currentPreviewUrl}"
          alt="Attachment preview"
          class="max-h-72 w-full object-contain bg-black/40"
        >`;
      return;
    }

    attachmentPreviewBody.innerHTML = `
      <div class="flex items-center justify-center px-4 py-8 text-sm text-slate-300">
        Preview is not available for this file type.
      </div>`;
  };

  if (attachmentInput) {
    attachmentInput.addEventListener('change', renderAttachmentPreview);
    window.addEventListener('beforeunload', clearPreviewUrl);
  }

  if (removeAttachmentBtn) {
    removeAttachmentBtn.addEventListener('click', clearAttachmentPreview);
  }

  form.addEventListener('submit', async (e) => {
    if (confirmed) return;
    e.preventDefault();

    const sisInput = document.getElementById('sis');
    const emailInput = document.getElementById('email');
    const contactInput = document.getElementById('contact_number');
    const nameInput = document.getElementById('name');

    if (sisInput) sisInput.value = sisInput.value.replace(/\D+/g, '').trim();
    if (contactInput) contactInput.value = contactInput.value.replace(/\D+/g, '').trim();
    if (emailInput) emailInput.value = emailInput.value.trim().toLowerCase();
    if (nameInput) nameInput.value = nameInput.value.replace(/\s+/g, ' ').trim();

    saveDraft();

    if (typeof Swal === 'undefined') {
      if (window.confirm('Are you sure you want to submit this account request?')) {
        confirmed = true;
        clearDraft();
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Submitting...';
        }
        form.submit();
      }
      return;
    }

    const result = await Swal.fire({
      title: 'Are you sure?',
      text: 'Please confirm that your details and email are correct before submitting.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, submit request',
      cancelButtonText: 'Review details',
    });

    if (result.isConfirmed) {
      confirmed = true;
      clearDraft();
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
      }
      form.submit();
    }
  });
});
</script>
@endsection
