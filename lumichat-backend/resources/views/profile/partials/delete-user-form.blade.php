{{-- resources/views/profile/partials/delete-user-form.blade.php --}}
<section class="space-y-4">
  <header>
    <h2 class="title-dynamic text-lg font-medium">{{ __('Delete Account') }}</h2>
    <p class="mt-1 muted-dynamic text-sm">
      {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
    </p>
  </header>

  {{-- Primary trigger --}}
  <button
    type="button"
    id="btn-delete-account"
    class="!bg-rose-600 hover:!bg-rose-700 !text-white !rounded-xl !px-5 !py-2"
  >
    {{ __('Delete Account') }}
  </button>

  {{-- Hidden form used by the SweetAlert flow (we submit this after password) --}}
  <form id="deleteAccountForm" action="{{ route('profile.destroy') }}" method="POST" class="hidden">
    @csrf
    @method('delete')
    <input type="password" id="delete_account_password_hidden" name="password">
  </form>

  {{-- Alpine controller: fallback modal if SweetAlert isn’t present --}}
  <div
    x-data="{ show: @js($errors->userDeletion->isNotEmpty()) }"
    x-cloak
    x-init="if (show) document.documentElement.classList.add('modal-open')"
    x-on:open-delete-modal.window="
      show = true;
      document.documentElement.classList.add('modal-open');
      $nextTick(() => $refs.pwd?.focus());
    "
    x-effect="if (!show) document.documentElement.classList.remove('modal-open')"
    x-on:keydown.escape.window="show=false"
  >
    <template x-teleport="body">
      <div x-show="show" class="fixed inset-0 modal-zp" x-cloak>
        {{-- Backdrop --}}
        <div
          class="fixed inset-0 modal-z bg-black/40 backdrop-blur-sm"
          x-transition.opacity.duration.150ms
          @click="show=false"
          aria-hidden="true"
        ></div>

        {{-- Dialog (fallback) --}}
        <div class="absolute inset-0 grid place-items-center p-4 pointer-events-none">
          <form
            method="post"
            action="{{ route('profile.destroy') }}"
            class="modal-zp pointer-events-auto w-full max-w-md rounded-xl bg-white dark:bg-gray-800 shadow-2xl p-5 ring-1 ring-gray-200 dark:ring-gray-700"
            x-ref="form"
            x-show="show"
            x-transition
            @submit.prevent="
              $refs.submitBtn.disabled = true;
              $refs.submitBtn.classList.add('opacity-70','cursor-not-allowed');
              $refs.submitBtn.textContent = '{{ __('Deleting…') }}';
              show = false;
              setTimeout(() => $refs.form.submit(), 160);
            "
            role="dialog" aria-modal="true"
          >
            @csrf
            @method('delete')

            <h2 class="title-dynamic text-base font-medium">
              {{ __('Are you sure you want to delete your account?') }}
            </h2>
            <p class="mt-1 muted-dynamic text-sm">
              {{ __('This action is permanent and cannot be undone.') }}
            </p>

            <div class="mt-4">
              <x-text-input
                x-ref="pwd"
                id="delete_password"
                name="password"
                type="password"
                class="block w-full input-dynamic"
                placeholder="{{ __('Enter your password to confirm') }}"
                autocomplete="current-password"
                required minlength="6"
              />
              <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-5 flex justify-end gap-3">
              <button type="button" class="btn-secondary" @click="show=false">
                {{ __('Cancel') }}
              </button>
              <button
                type="submit"
                x-ref="submitBtn"
                class="!bg-rose-600 hover:!bg-rose-700 !text-white !rounded-lg !px-4 !py-2"
              >
                {{ __('Delete Account') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </template>
  </div>
</section>

@once
@push('styles')
<style>
  [x-cloak]{display:none!important}
  html.modal-open, html.modal-open body{overflow:hidden!important}

  /* SweetAlert buttons (danger + secondary) */
  .swal2-confirm.btn-danger-ghost{
    background:#dc2626!important; color:#fff!important;
    border-radius:.65rem!important; padding:.6rem 1.1rem!important;
    box-shadow:0 8px 20px rgba(220,38,38,.25)!important;
  }
  .swal2-cancel.btn-secondary-ghost{
    background:#e5e7eb!important; color:#111827!important;
    border-radius:.65rem!important; padding:.6rem 1.1rem!important;
  }
  .swal2-cancel.btn-secondary-ghost:hover{filter:brightness(.98)}

  /* Blur the SweetAlert backdrop to match your UI */
  .swal2-container.swal2-backdrop-blur{
    background: rgba(15,23,42,.42) !important; /* slate-900/42 */
    backdrop-filter: blur(8px) saturate(110%);
  }

  /* Rounded focusable input inside SweetAlert */
  .swal2-input-neo{
    height:48px!important; border-radius:.8rem!important; padding:0 .9rem!important;
    font-size:1rem!important; border:1.5px solid rgba(99,102,241,.35)!important; /* indigo-500/35 */
  }
  .swal2-input-neo:focus{
    outline:0!important; border-color:rgb(99,102,241)!important;
    box-shadow:0 0 0 4px rgba(99,102,241,.14)!important;
  }
</style>
@endpush
@endonce

@once
@push('scripts')
{{-- SweetAlert2 is loaded globally via profile.partials.alerts --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btn-delete-account');
  const openFallbackModal = () => window.dispatchEvent(new CustomEvent('open-delete-modal'));

  // Fancy warning confirm (icon + bold title) — matches your other modal
  function prettyDangerConfirm({ title, bodyHtml, confirmText, cancelText }){
    const warnIcon = `
      <div style="width:92px;height:92px;margin:0 auto 10px;position:relative;">
        <div style="position:absolute;inset:0;border-radius:50%;
                    box-shadow:0 0 0 6px rgba(245,158,11,.12), inset 0 0 0 2px rgba(245,158,11,.35);
                    animation:pulseRing 1.8s ease-out infinite;"></div>
        <div style="position:absolute;inset:8px;border-radius:50%;background:#fff;display:flex;
                    align-items:center;justify-content:center;border:2px solid rgba(245,158,11,.6)">
          <svg width="42" height="42" viewBox="0 0 24 24" fill="none"
               stroke="#f59e0b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v4"></path><path d="M12 17h.01"></path>
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          </svg>
        </div>
      </div>
      <style>
        @keyframes pulseRing{
          0%{box-shadow:0 0 0 6px rgba(245,158,11,.12), inset 0 0 0 2px rgba(245,158,11,.35)}
          70%{box-shadow:0 0 0 16px rgba(245,158,11,0), inset 0 0 0 2px rgba(245,158,11,.35)}
          100%{box-shadow:0 0 0 6px rgba(245,158,11,0), inset 0 0 0 2px rgba(245,158,11,.35)}
        }
      </style>
    `;

    return Swal.fire({
      html: `
        ${warnIcon}
        <h2 style="margin:0 0 .45rem;font-size:1.55rem;font-weight:800;color:#0f172a;letter-spacing:.2px;text-align:center;">
          ${title}
        </h2>
        <p style="margin:.25rem 0 0;color:#475569;font-size:.98rem;text-align:center;">
          ${bodyHtml}
        </p>
      `,
      showCancelButton: true,
      focusCancel: true,
      reverseButtons: true,
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      width: 560,
      padding: '1.1rem 1.2rem 1.35rem',
      background: '#ffffff',
      customClass: {
        container: 'swal2-backdrop-blur',
        popup: 'rounded-2xl shadow-2xl',
        confirmButton: 'swal2-confirm btn-danger-ghost',
        cancelButton: 'swal2-cancel btn-secondary-ghost'
      }
    });
  }

  // Matching password prompt (same bold title + blurred backdrop)
  async function prettyPasswordPrompt(){
    return await Swal.fire({
      html: `
        <h2 style="margin:0 0 .55rem;font-size:1.55rem;font-weight:800;letter-spacing:.2px;color:#0f172a;text-align:center;">
          {{ __('Confirm with password') }}
        </h2>
        <p style="margin:.1rem 0 .6rem;color:#475569;font-size:.98rem;text-align:center;">
          {{ __('Enter your password to confirm deletion') }}
        </p>
      `,
      input: 'password',
      inputPlaceholder: '{{ __('Password') }}',
      inputAttributes: { autocomplete:'current-password', autocapitalize:'off', autocorrect:'off' },
      showCancelButton: true,
      reverseButtons: true,
      confirmButtonText: '{{ __('Delete') }}',
      cancelButtonText: '{{ __('Cancel') }}',
      width: 560,
      padding: '1.1rem 1.2rem 1.35rem',
      background: '#ffffff',
      customClass: {
        container: 'swal2-backdrop-blur',
        popup: 'rounded-2xl shadow-2xl',
        input: 'swal2-input-neo',
        confirmButton: 'swal2-confirm btn-danger-ghost',
        cancelButton: 'swal2-cancel btn-secondary-ghost'
      },
      preConfirm: (value) => {
        if (!value) {
          Swal.showValidationMessage('{{ __('Password is required') }}');
          return false;
        }
        return value;
      },
      didOpen: () => { Swal.getInput()?.focus(); }
    });
  }

  // Click flow: confirm → password → submit
  btn?.addEventListener('click', async () => {
    if (typeof Swal === 'undefined') return openFallbackModal();

    const res = await prettyDangerConfirm({
      title: 'Delete account permanently?',
      bodyHtml: 'This action <b>cannot be undone</b>. Your account and related data will be deleted forever.',
      confirmText: '{{ __('Yes, delete permanently') }}',
      cancelText: '{{ __('Cancel') }}'
    });
    if (!res.isConfirmed) return;

    const pwd = await prettyPasswordPrompt();
    if (!pwd.isConfirmed) return;

    const form  = document.getElementById('deleteAccountForm');
    const input = document.getElementById('delete_account_password_hidden');
    if (!form || !input) return openFallbackModal();

    input.value = pwd.value;
    form.submit();
  });
});
</script>
@endpush
@endonce
