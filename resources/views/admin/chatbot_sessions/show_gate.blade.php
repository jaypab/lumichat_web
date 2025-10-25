{{-- resources/views/admin/chatbot_sessions/show_gate.blade.php --}}
@extends('layouts.admin')
@section('title','Admin - Confirm Access')
@section('page_title', 'Confirm Access')

@section('content')
<div class="max-w-3xl mx-auto p-5 md:p-8">
  <!-- Subtle card explaining why we ask -->
  <div class="rounded-2xl bg-white/70 ring-1 ring-slate-200 backdrop-blur-sm shadow-sm">
    <div class="p-5 md:p-6">
      <div class="flex items-start gap-3">
        <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-xl ring-1 ring-amber-200 bg-amber-50">
          <img src="{{ asset('images/icons/security.png') }}" alt="Info" class="h-5 w-5 object-contain opacity-90">
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-slate-900">Re-authentication required</h2>
          <p class="mt-1 text-sm text-slate-600">
            For your safety and the student’s privacy, please confirm your password before viewing this session’s details.
          </p>
          <div class="mt-4 flex items-center gap-2">
            <a href="{{ route('admin.chatbot-sessions.index') }}"
               class="inline-flex items-center h-9 px-3 rounded-lg ring-1 ring-slate-200 text-slate-700 bg-white hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
              ← Back to list
            </a>
            <button type="button" id="openGateModal"
              class="inline-flex items-center h-9 px-3 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
              Confirm password
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal (centered) -->
<div id="gateModal"
     class="fixed inset-0 z-[70] hidden flex items-center justify-center"
     aria-hidden="true" aria-labelledby="gateTitle" role="dialog">
  <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px] opacity-0 transition-opacity duration-200"></div>

  <div class="relative z-[71] w-full max-w-md origin-center scale-95 opacity-0 translate-y-2
              rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-5 md:p-6
              transition-all duration-200">
    <div class="flex items-center justify-between">
      <h3 id="gateTitle" class="text-base font-semibold text-slate-900">Confirm your password</h3>
      <button type="button" id="gateClose"
        class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        aria-label="Close">
        ✕
      </button>
    </div>

    <form id="gateForm" class="mt-4 space-y-4" novalidate>
      @csrf
      <input type="hidden" name="session_id" value="{{ $sessionId }}">

      <!-- Input -->
      <div>
        <label for="gatePassword" class="block text-sm font-medium text-slate-700">Password</label>
        <div class="mt-1 relative">
          <input id="gatePassword" name="password" type="password" autocomplete="current-password" required
                 class="peer block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 pr-10 text-slate-900
                        placeholder-slate-400 shadow-sm outline-none transition
                        focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/60">
          <!-- toggle visibility (PNG) -->
          <button type="button" id="togglePw"
                  class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex items-center justify-center rounded-lg px-2
                         text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                  aria-label="Show or hide password">
            <img id="eyeIcon" src="{{ asset('images/icons/eye.png') }}" alt="Show password" class="h-5 w-5 object-contain opacity-80">
          </button>
        </div>
        <p id="gateError" class="mt-2 text-sm text-rose-600 hidden"></p>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-2">
        <button type="button" id="gateCancel"
                class="rounded-xl px-3 py-2 ring-1 ring-slate-200 bg-white text-slate-700 hover:bg-slate-50
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
          Cancel
        </button>

        <button type="submit" id="gateSubmit"
                class="inline-flex items-center gap-2 rounded-xl px-3 py-2
                       bg-indigo-600 text-white hover:bg-indigo-700
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
          <svg id="spinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24">
            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"/>
            <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"/>
          </svg>
          <span id="submitLabel">Confirm</span>
          <span class="text-xs opacity-80">(Enter)</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<script>
(() => {
  const endpoint = @json(route('admin.reauth.confirm'));
  const root     = document.getElementById('gateModal');
  const overlay  = root?.firstElementChild;
  const panel    = root?.lastElementChild;
  const openBtn  = document.getElementById('openGateModal');
  const closeBtn = document.getElementById('gateClose');
  const cancel   = document.getElementById('gateCancel');
  const form     = document.getElementById('gateForm');
  const pwdEl    = document.getElementById('gatePassword');
  const errEl    = document.getElementById('gateError');
  const submit   = document.getElementById('gateSubmit');
  const spinner  = document.getElementById('spinner');
  const label    = document.getElementById('submitLabel');
  const togglePw = document.getElementById('togglePw');
  const eyeIcon  = document.getElementById('eyeIcon');

  // PNG paths
  const eyeOn  = @json(asset('images/icons/eye.png'));
  const eyeOff = @json(asset('images/icons/eye-off.png'));

  // Simple focus trap + centered open/close animations
  let lastActive = null;
  function trapStart(){
    lastActive = document.activeElement;
    root.setAttribute('aria-hidden','false');
    root.classList.remove('hidden');
    // animate in
    requestAnimationFrame(() => {
      overlay.classList.remove('opacity-0');
      panel.classList.remove('opacity-0','scale-95','translate-y-2');
      setTimeout(() => pwdEl?.focus(), 10);
    });
  }
  function trapEnd(){
    overlay.classList.add('opacity-0');
    panel.classList.add('opacity-0','scale-95','translate-y-2');
    setTimeout(() => {
      root.classList.add('hidden');
      root.setAttribute('aria-hidden','true');
      if (lastActive && lastActive.focus) lastActive.focus();
      if (errEl){ errEl.classList.add('hidden'); errEl.textContent=''; }
      if (pwdEl) pwdEl.value='';
    }, 180);
  }

  // Toggle password visibility (PNG swap)
  togglePw?.addEventListener('click', () => {
    const isPw = pwdEl.getAttribute('type') === 'password';
    pwdEl.setAttribute('type', isPw ? 'text' : 'password');
    eyeIcon.src = isPw ? eyeOff : eyeOn;
    eyeIcon.alt = isPw ? 'Hide password' : 'Show password';
  });

  // open/close
  const open = () => trapStart();
  const close= () => trapEnd();

  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  cancel?.addEventListener('click', close);
  // click outside to close
  overlay?.addEventListener('click', close);
  // ESC / Enter shortcuts
  window.addEventListener('keydown', (e) => {
    if (root.classList.contains('hidden')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'Enter' && document.activeElement !== cancel) {
      form?.requestSubmit();
    }
  });

  // Open automatically on load
  open();

  // Friendly loading state
  function setBusy(b){
    submit.disabled = b;
    if (b){ spinner.classList.remove('hidden'); label.textContent = 'Verifying…'; }
    else { spinner.classList.add('hidden'); label.textContent = 'Confirm'; }
  }

  // Gentle shake animation on error
  function shake(el){
    el.classList.remove('ux-shake');
    void el.offsetWidth; // reflow
    el.classList.add('ux-shake');
  }

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    errEl.classList.add('hidden'); errEl.textContent='';
    setBusy(true);

    try{
      const fd  = new FormData(form);
      const res = await fetch(endpoint, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
      const data= await res.json().catch(()=> ({}));

      if (!res.ok || !data?.ok) {
        const msg = data?.message || 'Verification failed.';
        if (window.Swal) {
          Swal.fire({ icon:'error', title:'Try again', text: msg });
        } else {
          errEl.textContent = msg; errEl.classList.remove('hidden'); shake(panel);
        }
        setBusy(false);
        pwdEl?.focus();
        return;
      }

      if (window.Swal) {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Verified', timer:900, showConfirmButton:false });
      }
      setTimeout(() => window.location.reload(), 250);
    } catch (err) {
      const msg = (err && (err.message || String(err))) || 'Something went wrong.';
      if (window.Swal) {
        Swal.fire({ icon:'error', title:'Error', text: msg });
      } else {
        errEl.textContent = msg; errEl.classList.remove('hidden'); shake(panel);
      }
      setBusy(false);
    }
  });
})();
</script>

<style>
/* micro-interactions */
@keyframes shake {
  0%,100% { transform: translateX(0); }
  15%     { transform: translateX(-6px); }
  30%     { transform: translateX(5px); }
  45%     { transform: translateX(-4px); }
  60%     { transform: translateX(3px); }
  75%     { transform: translateX(-2px); }
  90%     { transform: translateX(1px); }
}
.ux-shake { animation: shake .35s ease-in-out; }
</style>
@endsection
