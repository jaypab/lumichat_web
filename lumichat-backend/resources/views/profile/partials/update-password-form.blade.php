{{-- resources/views/profile/partials/update-password-form.blade.php --}}
<section id="update-password-section">
  <div class="form-head">
    <h3 class="title-dynamic text-lg font-semibold">{{ __('Update Password') }}</h3>
    <span class="btn-size invisible"></span>
  </div>

  <p class="mt-1 muted-dynamic text-sm">
    {{ __('Ensure your account is using a long, random password to stay secure.') }}
  </p>

  <form id="pwd-form" method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5" novalidate>
    @csrf
    @method('put')

    {{-- Current --}}
    <div>
      <x-input-label for="pwd_current" :value="__('Current Password')" class="title-dynamic"/>
      <div class="relative">
        <x-text-input id="pwd_current" name="current_password" type="password"
          class="mt-1 block w-full input-dynamic pr-10" autocomplete="current-password" />
        <button type="button"
                class="reveal-btn"
                data-target="pwd_current"
                aria-label="Show current password"
                aria-pressed="false">
          {{-- DEFAULT: hidden => show eye-off icon --}}
          <img class="eye-img" src="{{ asset('images/icons/eye-off.png') }}" alt="">
        </button>
      </div>
      <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
    </div>

    {{-- New --}}
    <div>
      <x-input-label for="pwd_new" :value="__('New Password')" class="title-dynamic"/>
      <div class="relative">
        <x-text-input id="pwd_new" name="password" type="password"
          class="mt-1 block w-full input-dynamic pr-10" autocomplete="new-password" />
        <button type="button"
                class="reveal-btn"
                data-target="pwd_new"
                aria-label="Show new password"
                aria-pressed="false">
          <img class="eye-img" src="{{ asset('images/icons/eye-off.png') }}" alt="">
        </button>
      </div>

      {{-- Strength + checklist --}}
      <div class="mt-2" aria-live="polite">
        <div class="meter-track"><div class="meter-fill" style="width:0%"></div></div>
        <ul id="pwd_checks" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs muted-dynamic">
          <li data-check="len">• at least 8 characters</li>
          <li data-check="upper">• uppercase letter</li>
          <li data-check="lower">• lowercase letter</li>
          <li data-check="num">• number</li>
          <li data-check="sym">• symbol</li>
          <li data-check="match">• matches confirmation</li>
        </ul>
      </div>

      <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
    </div>

    {{-- Confirm --}}
    <div>
      <x-input-label for="pwd_confirm" :value="__('Confirm Password')" class="title-dynamic"/>
      <div class="relative">
        <x-text-input id="pwd_confirm" name="password_confirmation" type="password"
          class="mt-1 block w-full input-dynamic pr-10" autocomplete="new-password" />
        <button type="button"
                class="reveal-btn"
                data-target="pwd_confirm"
                aria-label="Show confirm password"
                aria-pressed="false">
          <img class="eye-img" src="{{ asset('images/icons/eye-off.png') }}" alt="">
        </button>
      </div>
      <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
    </div>

    <div class="flex items-center gap-4">
      <button id="pwd_save" type="submit"
              class="btn-primary btn-press px-5 py-2.5 disabled:opacity-60 disabled:cursor-not-allowed">
        {{ __('Save') }}
      </button>
    </div>
  </form>
</section>

@push('styles')
<style>
  .reveal-btn{
    position:absolute; right:.5rem; top:50%; transform:translateY(-50%);
    display:inline-flex; align-items:center; justify-content:center;
    width:28px; height:28px; border-radius:.5rem;
    background:transparent; opacity:.75; transition:opacity .15s;
  }
  .reveal-btn:hover{ opacity:1; }
  .eye-img{ width:18px; height:18px; object-fit:contain; }

  .meter-track{ height:8px; border-radius:9999px; overflow:hidden; background: rgba(99,102,241,.15); }
  .meter-fill{ height:100%; width:0%; background: linear-gradient(90deg,#f87171,#f59e0b,#34d399,#22c55e); transition: width .25s ease; }
  .ok{ color:#16a34a !important; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const pwd  = document.getElementById('pwd_new');
  const cfm  = document.getElementById('pwd_confirm');
  const save = document.getElementById('pwd_save');
  const fill = document.querySelector('.meter-fill');
  const list = document.getElementById('pwd_checks');

  // Eye toggle (now with correct default states)
  const EYE_OPEN = "{{ asset('images/icons/eye.png') }}";      // text visible
  const EYE_CLOSED = "{{ asset('images/icons/eye-off.png') }}"; // dots

  document.querySelectorAll('.reveal-btn').forEach(btn => {
    const inputId = btn.getAttribute('data-target');
    const img = btn.querySelector('img');

    btn.addEventListener('click', () => {
      const el = document.getElementById(inputId);
      if (!el) return;

      const isHidden = el.type === 'password';
      el.type = isHidden ? 'text' : 'password';

      // swap icon + aria
      img.src = isHidden ? EYE_OPEN : EYE_CLOSED;
      btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
      const base = btn.getAttribute('aria-label')?.replace(/^Show|Hide/i, '').trim() || 'password';
      btn.setAttribute('aria-label', (isHidden ? 'Hide ' : 'Show ') + base);
    });
  });

  // Strength + match
  const evaluate = () => {
    const v = pwd.value || '';
    const ok = {
      len   : v.length >= 8,
      upper : /[A-Z]/.test(v),
      lower : /[a-z]/.test(v),
      num   : /[0-9]/.test(v),
      sym   : /[^A-Za-z0-9]/.test(v),
      match : v && v === (cfm.value || '')
    };

    Object.keys(ok).forEach(k => {
      const li = list.querySelector(`[data-check="${k}"]`);
      li?.classList.toggle('ok', ok[k]);
    });

    const score = Object.values(ok).filter(Boolean).length;
    fill.style.width = Math.min(100, Math.round((score/6)*100)) + '%';

    save.disabled = !(ok.len && ok.match);
  };

  pwd.addEventListener('input', evaluate);
  cfm.addEventListener('input', evaluate);
  evaluate();

  @if($errors->updatePassword->any())
    save.disabled = false;
  @endif
});
</script>
@endpush
