@extends('layouts.student-guest')

@section('content')
<style>
  #accountRequestForm select { color-scheme: dark; }
  #accountRequestForm select option { color: #f8fafc; background: #0b1120; }
  #accountRequestForm select option[value=""] { color: #94a3b8; }

  /* ===== Scoped CSS Suite (Adapted from Login) ===== */
  #accountRequestForm .field {
    position: relative; display: flex; align-items: center;
    height: 48px; background: rgba(13, 11, 26, 0.65) !important;
    border: none; border-radius: 0.875rem; padding: 0 0.75rem;
    transition: all .3s ease;
  }
  
  .lumi-border-subtle {
    border-color: rgba(139, 92, 246, 0.15) !important;
  }

  #accountRequestForm .field::before {
    content: ''; position: absolute; inset: 0;
    border: 1px solid rgba(139, 92, 246, 0.1);
    border-radius: inherit; pointer-events: none;
    transition: all 0.3s ease;
  }

  #accountRequestForm .field:hover::before { border-color: rgba(167,139,250,.3); }

  #accountRequestForm .field:focus-within,
  #accountRequestForm .field:has(.open) {
    z-index: 100 !important;
  }

  #accountRequestForm .field:focus-within::before,
  #accountRequestForm .field:has(.peer:not(:placeholder-shown))::before,
  #accountRequestForm .field:has(.peer[data-filled="true"])::before {
    border-color: rgba(167,139,250,.6);
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
    background: rgba(13, 11, 26, 0.85);
  }

  /* Only apply the surgical cut-line if a label is present */
  #accountRequestForm .field:has(.float-label):focus-within::before,
  #accountRequestForm .field:has(.float-label):has(.peer:not(:placeholder-shown))::before,
  #accountRequestForm .field:has(.float-label):has(.peer[data-filled="true"])::before {
    clip-path: polygon(0% 0%, 15% 0%, 15% -100%, 65% -100%, 65% 0%, 100% 0%, 100% 100%, 0% 100%);
  }

  /* Specific cut-widths for different labels if needed, but generic works okay for most */

  #accountRequestForm .icon-20 {
    width: 18px; height: 18px; margin-right: .625rem;
    opacity: .4; filter: invert(1); transition: all .2s;
    z-index: 1; pointer-events: none; /* Allows clicks to pass through */
  }
  #accountRequestForm .field:hover .icon-20,
  #accountRequestForm .field:focus-within .icon-20 { opacity: 0.9; filter: invert(1) brightness(1.2); }

  #accountRequestForm input.input, #accountRequestForm select.input, #accountRequestForm div.input {
    width: 100%; height: 100%; background: transparent;
    border: none !important; outline: none !important; box-shadow: none !important;
    font-size: 13.5px; color: #fff; line-height: 48px;
    padding: 0 1rem 0 .25rem; font-weight: 500;
    position: relative; z-index: 50; /* High z-index to stay on top */
    border-radius: inherit;
    cursor: pointer;
    flex: 1;
  }

  #accountRequestForm .float-label {
    position: absolute; left: 2.75rem; top: 50%; transform: translateY(-50%);
    font-size: 13.5px; color: rgba(255,255,255,0.3);
    padding: 0; margin: 0; pointer-events: none; z-index: 10;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    background: transparent !important;
  }

  #accountRequestForm .peer:focus ~ .float-label,
  #accountRequestForm .peer[data-filled="true"] ~ .float-label,
  #accountRequestForm .peer:-webkit-autofill ~ .float-label,
  #accountRequestForm .peer:not(:placeholder-shown) ~ .float-label {
    color: #a78bfa; font-size: 10px; transform: translateY(-220%);
    font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em;
    text-shadow: 0 0 10px rgba(167,139,250,0.3);
  }

  #accountRequestForm input:-webkit-autofill {
    transition: background-color 50000s ease-in-out 0s;
    background-color: transparent !important;
    -webkit-text-fill-color: #ffffff !important;
  }

  /* ===== Enhanced Dropdown UI ===== */
  .lumi-dropdown { width: 100%; height: 100%; position: relative; display: flex; align-items: center; }
  .lumi-dropdown-menu {
    position: absolute; top: calc(100% + 8px); left: 0; right: 0;
    background: rgba(13, 11, 26, 0.98); backdrop-filter: blur(25px);
    border: 1px solid rgba(139, 92, 246, 0.15); border-radius: 1rem;
    padding: 0.5rem; z-index: 1000;
    max-height: 250px; overflow-y: auto;
    opacity: 0; transform: translateY(-10px) scale(0.95);
    visibility: hidden; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 20px 40px rgba(0,0,0,0.5);
  }
  
  /* Custom Scrollbar for Dropdown */
  .lumi-dropdown-menu::-webkit-scrollbar { width: 4px; }
  .lumi-dropdown-menu::-webkit-scrollbar-track { background: transparent; }
  .lumi-dropdown-menu::-webkit-scrollbar-thumb { 
    background: rgba(167,139,250,0.3); border-radius: 10px; 
  }
  .lumi-dropdown-menu::-webkit-scrollbar-thumb:hover { background: rgba(167,139,250,0.5); }

  .lumi-dropdown.open .lumi-dropdown-menu {
    opacity: 1; transform: translateY(0) scale(1); visibility: visible;
  }
  .lumi-dropdown-item {
    padding: 0.625rem 0.875rem; border-radius: 0.625rem; font-size: 13.5px;
    color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s;
  }
  .lumi-dropdown-item:hover {
    background: rgba(167,139,250,0.15); color: #fff;
  }
  .lumi-dropdown-item.selected {
    background: rgba(167,139,250,0.25); color: #a78bfa; font-weight: 700;
  }
  
  .lumi-dropdown-chevron {
    position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
    width: 14px; height: 14px; opacity: 0.3; transition: transform 0.3s;
    pointer-events: none; filter: invert(1);
  }
  .lumi-dropdown.open .lumi-dropdown-chevron { transform: translateY(-50%) rotate(180deg); opacity: 0.8; }
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
      <div class="mt-5 rounded-xl bg-violet-500/10 px-4 py-3 text-sm text-violet-100/95 max-w-md">
        <p class="font-semibold text-amber-200">Before you submit:</p>
        <ul class="mt-2 space-y-1.5 list-disc pl-5 text-violet-100/95">
          <li><span class="font-semibold text-amber-100">Review time:</span> Admin review takes 2-3 business days.</li>
          <li><span class="font-semibold text-amber-100">Delivery:</span> Account setup details will be sent to your email.</li>
          <li><span class="font-semibold text-amber-100">Important:</span> Use a valid, active email address.</li>
        </ul>
      </div>
      <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-violet-500/10 px-3 py-1.5 text-[11px] font-semibold text-violet-200 w-fit">
        <span class="inline-block h-1.5 w-1.5 rounded-full bg-violet-300 animate-pulse"></span>
        Approval Required
      </div>
    </section>

    <section class="lg:col-span-3 rounded-[2.5rem] bg-white/5 backdrop-blur-3xl shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] relative group">
      <div class="absolute inset-0 bg-gradient-to-br from-violet-500/5 to-fuchsia-500/5 pointer-events-none"></div>
      <div class="p-8 sm:p-10 relative z-10">
        <div class="text-center lg:text-left">
          <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">Request Student Account</h1>
          <p class="text-sm text-violet-200/80 mt-2">Submit your details and proof of enrollment. Admin will review your request.</p>
        </div>

        @if (session('error'))
          <div class="mt-5 flex items-center gap-3 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100 animate-in fade-in slide-in-from-top-2 duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p>{{ session('error') }}</p>
          </div>
        @endif

        @if ($errors->any())
          <div class="mt-5 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100 animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="flex items-center gap-2 font-semibold mb-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Please fix the following:
            </div>
            <ul class="list-disc pl-9 space-y-0.5 opacity-90">
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

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-8">
            {{-- SIS ID --}}
            <div class="field">
              <img src="{{ asset('images/icons/user.png') }}" alt="user" class="icon-20">
              <input id="sis" name="sis" type="text" value="{{ old('sis') }}" required placeholder=" " 
                     data-persist-field data-filled="false" data-error-field="sis" class="input peer @error('sis') border-rose-400/40 @enderror" />
              <label for="sis" class="float-label">SIS ID</label>
              @error('sis')<p class="absolute -bottom-7 left-0 text-[10px] text-rose-300" data-error-message="sis">{{ $message }}</p>@enderror
            </div>

            {{-- Full Name --}}
            <div class="field">
              <img src="{{ asset('images/icons/user.png') }}" alt="user" class="icon-20">
              <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder=" "
                     data-persist-field data-filled="false" data-error-field="name" class="input peer @error('name') border-rose-400/40 @enderror" />
              <label for="name" class="float-label">Full Name</label>
              @error('name')<p class="absolute -bottom-7 left-0 text-[10px] text-rose-300" data-error-message="name">{{ $message }}</p>@enderror
            </div>

            {{-- Email --}}
            <div class="field">
              <img src="{{ asset('images/icons/mail.png') }}" alt="mail" class="icon-20">
              <input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder=" "
                     data-persist-field data-filled="false" data-error-field="email" class="input peer @error('email') border-rose-400/40 @enderror" />
              <label for="email" class="float-label">Email Address</label>
              @error('email')<p class="absolute -bottom-7 left-0 text-[10px] text-rose-300" data-error-message="email">{{ $message }}</p>@enderror
            </div>

            {{-- Contact Number --}}
            <div class="field">
              <img src="{{ asset('images/icons/phone.png') }}" alt="phone" class="icon-20">
              <input id="contact_number" name="contact_number" type="text" value="{{ old('contact_number') }}" required placeholder=" "
                     data-persist-field data-filled="false" data-error-field="contact_number" class="input peer @error('contact_number') border-rose-400/40 @enderror" />
              <label for="contact_number" class="float-label">Contact Number</label>
              @error('contact_number')<p class="absolute -bottom-7 left-0 text-[10px] text-rose-300" data-error-message="contact_number">{{ $message }}</p>@enderror
            </div>

            {{-- Course --}}
            <div class="field">
              <img src="{{ asset('images/icons/graduate.png') }}" alt="course" class="icon-20">
              @php
                $courses = [
                  'BSIT'      => 'BSIT - College of Information Technology',
                  'EDUC'      => 'College of Education',
                  'CAS'       => 'College of Arts and Sciences',
                  'CRIM'      => 'College of Criminal Justice and Public Safety',
                  'BLIS'      => 'College of Library Information Science',
                  'MIDWIFERY' => 'College of Midwifery',
                  'BSHM'      => 'BSHM - College of Hospitality Management',
                  'BSBA'      => 'BSBA - College of Business Management',
                ];
              @endphp
              <div class="lumi-dropdown" id="course-dropdown">
                <input type="hidden" name="course" id="course" value="{{ old('course') }}" required data-persist-field data-error-field="course" />
                <div class="input peer flex items-center" data-filled="{{ old('course') ? 'true' : 'false' }}">
                  <span class="dropdown-label truncate text-white {{ old('course') ? '' : 'opacity-20' }}">
                    {{ old('course') ? $courses[old('course')] : 'Select course...' }}
                  </span>
                  <img src="{{ asset('images/icons/hamburger.png') }}" class="lumi-dropdown-chevron" style="filter: invert(1) rotate(90deg) scale(0.6)">
                </div>
                <div class="lumi-dropdown-menu">
                  @foreach ($courses as $abbr => $full)
                    <div class="lumi-dropdown-item {{ old('course') === $abbr ? 'selected' : '' }}" data-value="{{ $abbr }}">
                      {{ $full }}
                    </div>
                  @endforeach
                </div>
              </div>
              @error('course')<p class="absolute -bottom-7 left-0 text-[10px] text-rose-300" data-error-message="course">{{ $message }}</p>@enderror
            </div>

            {{-- Year Level --}}
            <div class="field">
              <img src="{{ asset('images/icons/calendar.png') }}" alt="level" class="icon-20">
              <div class="lumi-dropdown" id="year-dropdown">
                <input type="hidden" name="year_level" id="year_level" value="{{ old('year_level') }}" required data-persist-field data-error-field="year_level" />
                <div class="input peer flex items-center" data-filled="{{ old('year_level') ? 'true' : 'false' }}">
                  <span class="dropdown-label truncate text-white {{ old('year_level') ? '' : 'opacity-20' }}">
                    {{ old('year_level') ?: 'Select year level...' }}
                  </span>
                  <img src="{{ asset('images/icons/hamburger.png') }}" class="lumi-dropdown-chevron" style="filter: invert(1) rotate(90deg) scale(0.6)">
                </div>
                <div class="lumi-dropdown-menu">
                  @foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $level)
                    <div class="lumi-dropdown-item {{ old('year_level') === $level ? 'selected' : '' }}" data-value="{{ $level }}">{{ $level }}</div>
                  @endforeach
                </div>
              </div>
              @error('year_level')<p class="absolute -bottom-7 left-0 text-[10px] text-rose-300" data-error-message="year_level">{{ $message }}</p>@enderror
            </div>
          </div>

          <div>
            <label for="attachment" class="block text-sm font-semibold tracking-tight text-white mb-1">Proof of Enrollment or TCC ID</label>
            <p class="mb-2 text-xs leading-relaxed text-violet-200/75">Accepted proof: COR or school ID. File types: JPG, PNG, or PDF up to 5MB.</p>
            <input id="attachment" name="attachment" type="file" accept=".jpg,.jpeg,.png,.pdf" required
              data-error-field="attachment"
              @class([
                'block w-full rounded-xl bg-black/20 px-3 py-2 text-sm text-white file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white lumi-border-subtle',
                'border border-rose-400/70 file:bg-rose-500 hover:file:bg-rose-400' => $errors->has('attachment'),
                'border file:bg-violet-500 hover:file:bg-violet-400' => !$errors->has('attachment'),
              ]) />
            @error('attachment')<p class="mt-1 text-xs text-rose-300" data-error-message="attachment">{{ $message }}</p>@enderror
            <div id="attachmentPreview" class="mt-3 hidden rounded-2xl bg-black/20 p-3">
              <div class="flex items-center justify-between gap-3 mb-3">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-200/80">Preview</p>
                <div class="flex items-center gap-3 min-w-0">
                  <p id="attachmentMeta" class="text-xs text-slate-300 truncate"></p>
                  <button
                    id="removeAttachmentBtn"
                    type="button"
                    class="inline-flex items-center rounded-lg bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-100 transition hover:bg-rose-500/20"
                  >
                    Remove
                  </button>
                </div>
              </div>
              <div id="attachmentPreviewBody" class="overflow-hidden rounded-xl border lumi-border-subtle bg-black/30"></div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
            <button id="submitRequestBtn" type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-violet-500 via-indigo-500 to-fuchsia-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:brightness-110">
              Submit Request
            </button>
            <a href="{{ route('login') }}"
              class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium text-violet-100 transition hover:bg-white/10">
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
  // ===== 1. Selectors & State =====
  const form = document.getElementById('accountRequestForm');
  if (!form) return;

  const submitBtn = document.getElementById('submitRequestBtn');
  const attachmentInput = document.getElementById('attachment');
  const attachmentPreview = document.getElementById('attachmentPreview');
  const attachmentPreviewBody = document.getElementById('attachmentPreviewBody');
  const attachmentMeta = document.getElementById('attachmentMeta');
  const removeAttachmentBtn = document.getElementById('removeAttachmentBtn');
  const deviceKeyInput = document.getElementById('deviceKeyInput');
  const persistKey = 'lumichat.accountRequestDraft';
  const deviceStorageKey = 'lumichat.deviceKey';
  
  const persistFields = Array.from(document.querySelectorAll('[data-persist-field]'));
  const errorFields   = Array.from(document.querySelectorAll('[data-error-field]'));
  const inputs        = Array.from(document.querySelectorAll('#accountRequestForm .input'));

  let confirmed = false;
  let currentPreviewUrl = null;

  // ===== 2. Floating Label Logic =====
  function setFilled(el){ 
    if (el.tagName === 'DIV' || el.classList.contains('peer')) {
      const parentField = el.closest('.field');
      const hidden = parentField?.querySelector('input[type="hidden"]');
      if (hidden) {
        el.dataset.filled = (hidden.value && hidden.value.trim() !== '') ? 'true' : 'false';
        return;
      }
    }
    const val = (el.value !== undefined) ? el.value : '';
    el.dataset.filled = (val.trim() !== '') ? 'true' : 'false'; 
  }

  inputs.forEach(el => {
    ['input','change','blur'].forEach(ev => el.addEventListener(ev, () => setFilled(el)));
    // Immediate checks for autofill/restoration
    [50, 400, 1200].forEach(ms => setTimeout(() => setFilled(el), ms));
  });

  // ===== 3. Custom Dropdown Logic =====
  document.querySelectorAll('.lumi-dropdown').forEach(dropdown => {
    const trigger = dropdown.querySelector('.peer');
    const input   = dropdown.querySelector('input[type="hidden"]');
    const items   = dropdown.querySelectorAll('.lumi-dropdown-item');
    const label   = trigger.querySelector('.dropdown-label');

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      document.querySelectorAll('.lumi-dropdown').forEach(other => {
        if (other !== dropdown) other.classList.remove('open');
      });
      dropdown.classList.toggle('open');
    });

    items.forEach(item => {
      item.addEventListener('click', (e) => {
        e.stopPropagation();
        const val = item.dataset.value;
        const text = item.innerText;
        
        input.value = val;
        label.textContent = text;
        label.classList.remove('opacity-20');
        trigger.dataset.filled = 'true';
        
        items.forEach(i => i.classList.remove('selected'));
        item.classList.add('selected');
        dropdown.classList.remove('open');
        
        // Trigger validation/clearing
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.lumi-dropdown')) {
      document.querySelectorAll('.lumi-dropdown').forEach(d => d.classList.remove('open'));
    }
  });

  // ===== 4. Error Handling Logic =====
  const clearFieldErrorState = (field) => {
    const key = field.dataset.errorField;
    if (!key) return;

    const parentField = field.closest('.field') || field.parentElement;
    if (parentField) parentField.classList.remove('border-rose-400/40');

    if (field.type === 'file') {
      field.classList.remove('border-rose-400/70', 'file:bg-rose-500', 'hover:file:bg-rose-400');
      field.classList.add('border-violet-500/15', 'file:bg-violet-500', 'hover:file:bg-violet-400');
    }

    const errorMessage = document.querySelector(`[data-error-message="${key}"]`);
    if (errorMessage) {
      errorMessage.style.opacity = '0';
      setTimeout(() => errorMessage.remove(), 200);
    }
  };

  errorFields.forEach((field) => {
    const eventName = (field.type === 'file' || field.type === 'hidden') ? 'change' : 'input';
    field.addEventListener(eventName, () => clearFieldErrorState(field), { once: true });
  });

  // ===== 5. Draft & Device Key Persistence =====
  const ensureDeviceKey = () => {
    if (!deviceKeyInput) return;
    let key = deviceKeyInput.value || localStorage.getItem(deviceStorageKey) || '';
    if (!key) {
      key = 'ldk_' + (crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now().toString(36));
    }
    deviceKeyInput.value = key;
    localStorage.setItem(deviceStorageKey, key);
  };

  const saveDraft = () => {
    const draft = {};
    persistFields.forEach(f => draft[f.name] = f.value);
    sessionStorage.setItem(persistKey, JSON.stringify(draft));
  };

  const restoreDraft = () => {
    try {
      const draft = JSON.parse(sessionStorage.getItem(persistKey) || '{}');
      persistFields.forEach(f => {
        if (!f.value && draft[f.name]) {
          f.value = draft[f.name];
          // Handle dropdown labels if value restored
          if (f.type === 'hidden' && f.dataset.errorField) {
             const dropdown = f.closest('.lumi-dropdown');
             const item = dropdown?.querySelector(`.lumi-dropdown-item[data-value="${f.value}"]`);
             if (item) item.click(); // Reuse item click to restore label/state
          }
        }
      });
    } catch (_) {}
  };

  ensureDeviceKey();
  restoreDraft();
  persistFields.forEach(f => ['input', 'change', 'blur'].forEach(ev => f.addEventListener(ev, saveDraft)));

  // ===== 6. File Attachment & Preview =====
  const clearPreviewUrl = () => { if (currentPreviewUrl) { URL.revokeObjectURL(currentPreviewUrl); currentPreviewUrl = null; } };

  const renderAttachmentPreview = () => {
    if (!attachmentInput || !attachmentPreviewBody) return;
    clearPreviewUrl();
    attachmentPreviewBody.innerHTML = '';
    const file = attachmentInput.files?.[0];
    if (!file) {
      attachmentPreview?.classList.add('hidden');
      if (submitBtn) submitBtn.disabled = false;
      return;
    }

    const fileSizeMb = (file.size / (1024 * 1024)).toFixed(2);
    if (file.size > 5 * 1024 * 1024) {
      Swal.fire({ title: 'File too large', text: `Limit is 5MB. Your file is ${fileSizeMb}MB.`, icon: 'error', background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#7c3aed' });
      attachmentInput.value = '';
      attachmentPreview?.classList.add('hidden');
      if (submitBtn) submitBtn.disabled = true;
      return;
    }

    if (submitBtn) submitBtn.disabled = false;
    attachmentPreview?.classList.remove('hidden');
    attachmentMeta.textContent = `${file.name} • ${fileSizeMb} MB`;
    currentPreviewUrl = URL.createObjectURL(file);

    if (file.type === 'application/pdf') {
      attachmentPreviewBody.innerHTML = `<iframe src="${currentPreviewUrl}#toolbar=0" class="h-64 w-full bg-white"></iframe>`;
    } else if (file.type.startsWith('image/')) {
      attachmentPreviewBody.innerHTML = `<img src="${currentPreviewUrl}" class="max-h-72 w-full object-contain bg-black/40">`;
    } else {
      attachmentPreviewBody.innerHTML = `<div class="px-4 py-8 text-sm text-slate-300 text-center">Preview not available.</div>`;
    }
  };

  attachmentInput?.addEventListener('change', renderAttachmentPreview);
  removeAttachmentBtn?.addEventListener('click', () => { attachmentInput.value = ''; renderAttachmentPreview(); });

  // ===== 7. Submit Confirmation =====
  form.addEventListener('submit', async (e) => {
    if (confirmed) return;
    e.preventDefault();

    // Clean inputs
    const sisInput = document.getElementById('sis');
    const contactInput = document.getElementById('contact_number');
    if (sisInput) sisInput.value = sisInput.value.replace(/\D+/g, '').trim();
    if (contactInput) contactInput.value = contactInput.value.replace(/\D+/g, '').trim();

    const result = await Swal.fire({
      title: 'Are you sure?',
      text: 'Please confirm that your details and email are correct.',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, submit request', cancelButtonText: 'Review'
    });

    if (result.isConfirmed) {
      confirmed = true;
      sessionStorage.removeItem(persistKey);
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
      form.submit();
    }
  });

  const requestAccessBlockedMessage = @json($errors->first('request_access'));
  if (requestAccessBlockedMessage) {
    Swal.fire({ title: 'Request blocked', text: requestAccessBlockedMessage, icon: 'warning', background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#7c3aed' });
  }
});
</script>
@endsection
