@php
  $reg = $registration ?? null;

  $courses = [
    'BSIT' => 'College of Information Technology',
    'EDUC' => 'College of Education',
    'CAS' => 'College of Arts and Sciences',
    'CRIM' => 'College of Criminal Justice and Public Safety',
    'BLIS' => 'College of Library Information Science',
    'MIDWIFERY' => 'College of Midwifery',
    'BSHM' => 'College of Hospitality Management',
    'BSBA' => 'College of Business',
  ];

  $yearLevels = [
    '1st year' => '1st year',
    '2nd year' => '2nd year',
    '3rd year' => '3rd year',
    '4th year' => '4th year',
  ];
@endphp

<div class="space-y-6">

  {{-- ================= READ VIEW ================= --}}
  <section data-edit-profile-view>
    {{-- Top: Picture + Header --}}
    <div class="flex flex-col sm:flex-row gap-6 items-start mb-8 relative group">
      {{-- Profile Picture (Red Area) - Rectangular with Premium Touch --}}
      <div class="flex-shrink-0 relative">
        <div class="absolute -inset-0.5 bg-gradient-to-br from-indigo-500/20 to-violet-500/20 rounded-xl blur opacity-75 group-hover:opacity-100 transition duration-500"></div>
        @if($user->profile_picture)
          <img src="{{ asset('storage/' . $user->profile_picture) }}" alt="{{ $user->name }}'s profile picture"
            class="relative w-28 h-44 rounded-xl object-cover border border-white dark:border-gray-700 shadow-lg ring-1 ring-black/5 dark:ring-white/5">
        @else
          <div
            class="relative w-28 h-44 rounded-xl bg-slate-50 dark:bg-gray-800 flex flex-col items-center justify-center text-slate-300 dark:text-gray-600 border border-slate-200 dark:border-gray-700 shadow-inner">
            <svg class="w-10 h-10 opacity-30 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="text-[10px] font-black uppercase tracking-widest opacity-40">No Image</span>
          </div>
        @endif
      </div>

      {{-- Header Text (Green Area) --}}
      <div class="flex-1 pt-2">
        <div class="inline-flex items-center gap-2 px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-[10px] font-black uppercase tracking-widest border border-indigo-100 dark:border-indigo-500/20 mb-3">
          Campus Identity
        </div>
        <h3 class="title-dynamic text-2xl font-black tracking-tight leading-tight">Profile Overview</h3>
        <p class="muted-dynamic text-sm font-medium mt-1.5 max-w-sm">
          {{ __("Manage your student account's personal information and university credentials.") }}
        </p>

        {{-- Button Area (Yellow Area) - Positioned for balance --}}
        <div class="mt-6">
          <button type="button" data-edit-profile-btn class="h-10 px-6 rounded-xl bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/50 hover:bg-indigo-50 dark:hover:bg-gray-700/50 shadow-sm font-black text-[10px] uppercase tracking-widest transition-all btn-press">
            Modify Profile Settings
          </button>
        </div>
      </div>
    </div>

    {{-- Info Grid --}}
    <dl class="meta-grid">
      <div class="row">
        <dt>Name</dt>
        <dd class="title-dynamic">{{ $user->name }}</dd>
      </div>

      <div class="row">
        <dt>Student ID (SIS)</dt>
        <dd class="title-dynamic">{{ $user->sis ?? '—' }}</dd>
      </div>

      <div class="row">
        <dt>Email</dt>
        <dd class="title-dynamic break-all">{{ $user->email }}</dd>
      </div>

      <div class="row">
        <dt>Course</dt>
        <dd class="title-dynamic">{{ $user->course ?? '—' }}</dd>
      </div>

      <div class="row">
        <dt>Year level</dt>
        <dd class="title-dynamic">{{ $user->year_level ?? '—' }}</dd>
      </div>

      <div class="row">
        <dt>Contact number</dt>
        <dd class="title-dynamic">{{ $user->contact_number ?? '—' }}</dd>
      </div>
    </dl>
  </section>

  {{-- ================= EDIT FORM ================= --}}
  <section data-edit-profile-form class="hidden">
    <div class="form-head">
      <div>
        <h3 class="title-dynamic text-lg font-semibold">Edit Profile</h3>
        <p class="muted-dynamic text-sm">Make changes to your details, then save.</p>
      </div>
      <span class="btn-size invisible" aria-hidden="true"></span>
    </div>

    <form method="POST" action="{{ route('profile.update') }}" class="space-y-6" enctype="multipart/form-data"
      novalidate>
      @csrf
      @method('PUT')
      <div class="grid gap-5 sm:grid-cols-2">
        {{-- Profile Picture --}}
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium title-dynamic mb-2">Profile Picture</label>
          <div class="mt-2 flex items-center gap-6">
            {{-- Clickable Avatar Container --}}
            <div id="avatar-container" 
                 class="relative w-20 h-28 rounded-xl bg-slate-100 dark:bg-gray-800 overflow-hidden flex-shrink-0 flex items-center justify-center border-2 border-dashed border-slate-200 dark:border-gray-700 cursor-pointer group hover:border-indigo-400 dark:hover:border-indigo-500 transition-all duration-300 shadow-sm"
                 title="Click to crop or change photo">
              
              @if($user->profile_picture)
                <img id="profile-preview" src="{{ asset('storage/' . $user->profile_picture) }}" alt="Profile preview"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
              @else
                <div id="profile-placeholder" class="text-slate-400 flex flex-col items-center">
                  <svg class="w-8 h-8 opacity-40 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  <span class="text-[9px] font-black uppercase tracking-tighter opacity-50">Upload</span>
                </div>
              @endif

              {{-- Hover Overlay --}}
              <div class="absolute inset-0 bg-indigo-600/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-300">
                <svg class="w-6 h-6 text-white animate-bounce-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                </svg>
              </div>
            </div>

            <div class="flex-1 space-y-3">
              <input id="edit-profile-picture" name="profile_picture" type="file" accept="image/*" class="hidden">
              <input type="hidden" name="remove_profile_picture" id="remove-profile-picture" value="0">
              <div class="flex flex-wrap gap-2">
                <button type="button" onclick="document.getElementById('edit-profile-picture').click()"
                        class="h-9 px-4 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-black uppercase tracking-widest border border-indigo-100 dark:border-indigo-500/20 hover:bg-indigo-100 transition-colors">
                  Choose New Photo
                </button>
                <button type="button" id="remove-photo-btn"
                        class="{{ $user->profile_picture ? '' : 'hidden' }} h-9 px-4 rounded-lg bg-slate-50 dark:bg-gray-800 text-slate-500 dark:text-gray-400 text-xs font-black uppercase tracking-widest border border-slate-200 dark:border-gray-700 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-100 dark:hover:bg-rose-500/10 dark:hover:text-rose-400 dark:hover:border-rose-500/20 transition-all">
                  Remove Photo
                </button>
              </div>
              <p id="picture-help" class="muted-dynamic text-[11px] leading-relaxed max-w-xs">
                Pick a clear photo (JPG, PNG). We'll help you crop it to a perfect 7:11 rectangle. Max 5MB.
              </p>
            </div>
          </div>
          @error('profile_picture')
            <p class="text-sm text-rose-500 mt-1 server-error" data-error-for="profile_picture">{{ $message }}</p>
          @enderror
        </div>

        {{-- Name (read-only) --}}
        <div>
          <label for="edit-name" class="block text-sm font-medium title-dynamic">Name</label>
          <input id="edit-name" name="name" type="text"
            class="mt-1 w-full input-dynamic bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300 cursor-not-allowed"
            value="{{ $user->name }}" disabled readonly
            title="Your name is managed by the school (SIS) and cannot be changed here.">
          <p class="muted-dynamic text-xs mt-1">
            Your name is managed by the school (SIS) and cannot be changed here.
          </p>
        </div>

        {{-- Email --}}
        <div>
          <label for="edit-email" class="block text-sm font-medium title-dynamic">Email</label>
          <input id="edit-email" name="email" type="email" class="mt-1 w-full input-dynamic break-all"
            value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" inputmode="email"
            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
          @error('email')
            <p class="text-sm text-rose-500 mt-1 server-error" data-error-for="email">{{ $message }}</p>
          @enderror
        </div>

        {{-- SIS (read-only) --}}
        <div class="sm:col-span-2">
          <label for="edit-sis" class="block text-sm font-medium title-dynamic">Student ID (SIS)</label>
          <input id="edit-sis" type="text"
            class="mt-1 w-full input-dynamic bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300 cursor-not-allowed"
            value="{{ $user->sis ?? 'Not set' }}" disabled readonly
            title="Your SIS ID is managed by the school and cannot be changed here.">
          <p class="muted-dynamic text-xs mt-1">
            Your SIS ID is managed by the school and cannot be changed here.
          </p>
        </div>

        {{-- Course --}}
        <div>
          <label for="edit-course" class="block text-sm font-medium title-dynamic">Course</label>
          <select id="edit-course" name="course" class="mt-1 w-full input-dynamic"
            aria-invalid="{{ $errors->has('course') ? 'true' : 'false' }}">
            <option value="" disabled {{ old('course', $user->course ?? '') === '' ? 'selected' : '' }}>
              Select your course
            </option>
            @foreach($courses as $value => $label)
              <option value="{{ $value }}" {{ old('course', $user->course ?? '') === $value ? 'selected' : '' }}>
                {{ $label }}
              </option>
            @endforeach
          </select>
          @error('course')
            <p class="text-sm text-rose-500 mt-1 server-error" data-error-for="course">{{ $message }}</p>
          @enderror
        </div>

        {{-- Year level --}}
        <div>
          <label for="edit-year" class="block text-sm font-medium title-dynamic">Year level</label>
          <select id="edit-year" name="year_level" class="mt-1 w-full input-dynamic"
            aria-invalid="{{ $errors->has('year_level') ? 'true' : 'false' }}">
            <option value="" disabled {{ old('year_level', $user->year_level ?? '') === '' ? 'selected' : '' }}>
              Select your year level
            </option>
            @foreach($yearLevels as $value => $label)
              <option value="{{ $value }}" {{ old('year_level', $user->year_level ?? '') === $value ? 'selected' : '' }}>
                {{ $label }}
              </option>
            @endforeach
          </select>
          @error('year_level')
            <p class="text-sm text-rose-500 mt-1 server-error" data-error-for="year_level">{{ $message }}</p>
          @enderror
        </div>

        {{-- Contact number --}}
        <div class="sm:col-span-2">
          <label for="edit-phone" class="block text-sm font-medium title-dynamic">Contact number</label>
          <input id="edit-phone" name="contact_number" type="text" class="mt-1 w-full input-dynamic"
            value="{{ old('contact_number', $user->contact_number ?? optional($reg)->contact_number) }}"
            inputmode="numeric" pattern="\d*" minlength="10" maxlength="15" aria-describedby="phone-help"
            aria-invalid="{{ $errors->has('contact_number') ? 'true' : 'false' }}">
          <p id="phone-help" class="muted-dynamic text-xs mt-1">
            Digits only (10–15). PH 09… will be stored as 639…
          </p>
          @error('contact_number')
            <p class="text-sm text-rose-500 mt-1 server-error" data-error-for="contact_number">{{ $message }}</p>
          @enderror
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary btn-press">Save changes</button>
        <button type="button" data-edit-cancel class="btn-secondary">Cancel</button>
      </div>
    </form>
  </section>
</div>

@push('styles')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
  <style>
    /* Left-align all SweetAlert body content */
    .swal2-popup .swal2-html-container {
      text-align: left !important;
    }

    /* Pretty, hanging-indented bullets for our error list */
    .swal-bullets {
      margin: .35rem 0 0;
      padding: 0;
      list-style: none;
      line-height: 1.7;
      font-size: .98rem;
      color: #475569;
      /* slate-600 */
    }

    .swal-bullets li {
      display: flex;
      gap: .5rem;
      align-items: flex-start;
    }

    .swal-bullets li>span:first-child {
      line-height: 1.7;
      /* keeps bullet aligned with multi-line text */
    }

    /* SweetAlert confirm button (parity with login/register) */
    .swal2-confirm.btn-primary-ghost {
      background: #4f46e5 !important;
      color: #fff !important;
      border-radius: .65rem !important;
      padding: .6rem 1.1rem !important;
      box-shadow: 0 8px 20px rgba(79, 70, 229, .25) !important;
    }

    .swal2-confirm.btn-primary-ghost:hover {
      filter: brightness(0.96);
    }

    /* Equal header heights (matches Update Password) */
    .form-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      margin-bottom: .75rem;
      min-height: 44px;
    }

    .btn-size {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 40px;
      padding: 0 1rem;
      min-width: 116px;
      border-radius: .75rem;
    }

    .btn-press {
      transition: transform .12s ease, box-shadow .12s ease;
    }

    .btn-press:active {
      transform: translateY(1px) scale(.985);
    }

    /* Label/value list */
    .meta-grid {
      display: grid;
      gap: .25rem;
      padding: .25rem 0;
    }

    .meta-grid .row {
      display: grid;
      align-items: start;
      grid-template-columns: 1fr;
      row-gap: .125rem;
      padding: .6rem 0;
      border-top: 1px solid rgb(229 231 235 / .4);
    }

    .dark .meta-grid .row {
      border-top-color: rgb(55 65 81 / .5);
    }

    .meta-grid .row:first-child {
      border-top: 0;
    }

    .meta-grid dt {
      font-size: .72rem;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: rgb(107 114 128);
    }

    .dark .meta-grid dt {
      color: rgb(156 163 175);
    }

    .meta-grid dd {
      font-weight: 600;
    }

    @media (min-width: 640px) {
      .meta-grid .row {
        grid-template-columns: 200px minmax(0, 1fr);
        column-gap: 1.25rem;
      }

      .meta-grid dt {
        padding-top: .1rem;
      }
    }

    /* Cropper modal overrides */
    .cropper-view-box, .cropper-face {
      border-radius: 0.75rem;
    }
    .cropper-container {
      margin-top: 1rem;
      border-radius: 1rem;
      overflow: hidden;
      z-index: 2147483647 !important; /* Ensure it stays above Swal */
    }
    .cropper-drag-box, .cropper-wrap-box, .cropper-canvas {
      z-index: 2147483647 !important;
    }
    @keyframes bounce-subtle {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-3px); }
    }
    .animate-bounce-subtle { animation: bounce-subtle 2s infinite; }
  </style>
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const view = document.querySelector('[data-edit-profile-view]');
      const form = document.querySelector('[data-edit-profile-form]');
      const openBtn = document.querySelector('[data-edit-profile-btn]');
      const cancel = document.querySelector('[data-edit-cancel]');
      const nameEl = document.getElementById('edit-name');
      const phoneEl = document.getElementById('edit-phone');

      const openEdit = () => {
        view?.classList.add('hidden');
        form?.classList.remove('hidden');
        requestAnimationFrame(() => nameEl?.focus());
      };
      const closeEdit = () => {
        form?.classList.add('hidden');
        view?.classList.remove('hidden');
      };

      openBtn?.addEventListener('click', openEdit);
      cancel?.addEventListener('click', closeEdit);

      if (@json($errors->any())) openEdit();

      phoneEl?.addEventListener('blur', () => {
        if (!phoneEl.value) return;
        let digits = (phoneEl.value || '').replace(/\D+/g, '');
        if (digits.startsWith('09')) digits = '63' + digits.slice(1);
        else if (digits.startsWith('9') && digits.length === 10) digits = '63' + digits;
        else if (digits.startsWith('00')) digits = digits.slice(2);
        phoneEl.value = digits;
      });

      function prettyError(htmlInner) {
        const crossIcon = `<div style="width:84px;height:84px;margin:0 auto 12px;position:relative;"><div style="position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 6px rgba(239,68,68,.12), inset 0 0 0 2px rgba(239,68,68,.35);animation:pulseRing 1.8s ease-out infinite;"></div><div style="position:absolute;inset:10px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;border:2px solid #fca5a5"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></div></div><style>@keyframes pulseRing { 0%{ box-shadow:0 0 0 6px rgba(239,68,68,.12), inset 0 0 0 2px rgba(239,68,68,.35) } 70%{ box-shadow:0 0 0 16px rgba(239,68,68,0), inset 0 0 0 2px rgba(239,68,68,.35) } 100%{ box-shadow:0 0 0 6px rgba(239,68,68,0), inset 0 0 0 2px rgba(239,68,68,.35) } }</style>`;
        return {
          html: `<h2 style="margin:0 0 .55rem;font-size:1.55rem;font-weight:800;color:#0f172a;letter-spacing:.2px;text-align:center;">Please fix the following</h2>${crossIcon}<div>${htmlInner}</div>`,
          showConfirmButton: true,
          confirmButtonText: 'OK',
          width: 540,
          customClass: { popup: 'rounded-2xl shadow-2xl', confirmButton: 'swal2-confirm btn-primary-ghost' }
        };
      }

      @if ($errors->any())
        (function () {
          const errs = @json($errors->all()).map(e => e.replace(/hypens/ig, 'hyphens'));
          const list = `<ul class="swal-bullets">${errs.map(e => `<li><span>•</span><span>${e}</span></li>`).join('')}</ul>`;
          Swal.fire(prettyError(list));
        })();
      @endif

      @if (session('success'))
        toastSuccess(@json(session('success')));
      @elseif (session('status') && in_array(session('status'), ['profile-updated', 'profile-information-updated']))
        toastSuccess('Profile updated');
      @endif

      const SERVER_FIELDS = ['email', 'course', 'year_level', 'contact_number'];
      SERVER_FIELDS.forEach((name) => {
        const input = document.querySelector(`[name="${name}"]`);
        const errEl = document.querySelector(`[data-error-for="${name}"]`);
        if (!input || !errEl) return;
        const hide = () => errEl.classList.add('hidden');
        ['input','change','keydown','blur'].forEach(ev => input.addEventListener(ev, hide));
      });

      const profilePictureInput = document.getElementById('edit-profile-picture');
      const removeProfilePictureInput = document.getElementById('remove-profile-picture');
      const removePhotoBtn = document.getElementById('remove-photo-btn');
      const avatarContainer = document.getElementById('avatar-container');
      let cropper = null;

      // Reusable cropping function
      const startCropping = (imageUrl, fileName) => {
        Swal.fire({
          title: 'Crop your photo',
          html: `<div class="cropper-container" style="max-height: 400px; overflow: hidden;"><img id="crop-image" src="${imageUrl}" style="max-width: 100%;"></div><p class="text-xs text-slate-400 mt-4 leading-relaxed font-medium">Drag to crop. Ratio is locked to <span class="text-indigo-500 font-bold">7:11</span>.</p>`,
          showCancelButton: true,
          confirmButtonText: 'Apply Crop',
          confirmButtonColor: '#4f46e5',
          width: 500,
          didOpen: () => {
            setTimeout(() => {
              const image = Swal.getPopup().querySelector('#crop-image');
              if (!image) return;
              cropper = new Cropper(image, { aspectRatio: 7/11, viewMode: 1, dragMode: 'move', autoCropArea: 0.9, checkCrossOrigin: true });
            }, 100);
          },
          preConfirm: () => {
            if (!cropper) return null;
            return new Promise((resolve) => {
              const canvas = cropper.getCroppedCanvas({ width: 350, height: 550 });
              canvas.toBlob((blob) => {
                resolve({ blob, dataUrl: canvas.toDataURL('image/jpeg') });
              }, 'image/jpeg', 0.9);
            });
          },
          willClose: () => { if (cropper) { cropper.destroy(); cropper = null; } }
        }).then((result) => {
          if (result.isConfirmed && result.value) {
            const { blob, dataUrl } = result.value;
            const croppedFile = new File([blob], fileName || 'profile_picture.jpg', { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(croppedFile);
            profilePictureInput.files = dataTransfer.files;
            
            // Reset removal flag
            if (removeProfilePictureInput) removeProfilePictureInput.value = '0';
            if (removePhotoBtn) removePhotoBtn.classList.remove('hidden');

            if (avatarContainer) {
              let previewImg = document.getElementById('profile-preview');
              if (!previewImg) {
                avatarContainer.innerHTML = '';
                previewImg = document.createElement('img');
                previewImg.id = 'profile-preview';
                previewImg.className = 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500';
                avatarContainer.appendChild(previewImg);
                
                const overlay = document.createElement('div');
                overlay.className = 'absolute inset-0 bg-indigo-600/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-300';
                overlay.innerHTML = '<svg class="w-6 h-6 text-white animate-bounce-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /></svg>';
                avatarContainer.appendChild(overlay);
              }
              previewImg.src = dataUrl;
            }
          }
        });
      };

      // Handler for avatar container click
      if (avatarContainer) {
        avatarContainer.addEventListener('click', (e) => {
          const previewImg = document.getElementById('profile-preview');
          if (previewImg && previewImg.src && removeProfilePictureInput.value === '0') {
            startCropping(previewImg.src, 'recropped_at_' + Date.now() + '.jpg');
          } else {
            profilePictureInput.click();
          }
        });
      }

      // Handler for Remove Photo button
      if (removePhotoBtn) {
        removePhotoBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          if (removeProfilePictureInput) removeProfilePictureInput.value = '1';
          profilePictureInput.value = ''; // clear file input
          
          if (avatarContainer) {
            avatarContainer.innerHTML = `
              <div id="profile-placeholder" class="text-slate-400 flex flex-col items-center">
                <svg class="w-8 h-8 opacity-40 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span class="text-[9px] font-black uppercase tracking-tighter opacity-50">Upload</span>
              </div>
              <div class="absolute inset-0 bg-indigo-600/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-300">
                <svg class="w-6 h-6 text-white animate-bounce-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                </svg>
              </div>
            `;
          }
          removePhotoBtn.classList.add('hidden');
        });
      }

      if (profilePictureInput) {
        profilePictureInput.addEventListener('change', (event) => {
          const file = event.target.files[0];
          if (!file) return;
          if (!file.type.startsWith('image/')) {
            Swal.fire('Error', 'Invalid image file.', 'error');
            profilePictureInput.value = '';
            return;
          }

          const reader = new FileReader();
          reader.onload = (e) => startCropping(e.target.result, file.name);
          reader.readAsDataURL(file);
        });
      }
    });
  </script>
@endpush