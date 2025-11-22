{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.student-guest')

@section('content')
@php
  $ctx       = strtolower((string)($loginContext ?? 'student')) === 'admin' ? 'admin' : 'student';
  $title     = $ctx === 'admin' ? 'Welcome back, Admin' : 'Welcome to LumiChat';
  $subtitle  = $ctx === 'admin' ? 'Preparing your dashboard…' : 'Signing you in…';
  $postRoute = $ctx === 'admin' ? route('admin.login.post') : route('login');
@endphp

{{-- ===== Outer layout: center, then animate to split ===== --}}
<div class="min-h-[88vh] flex items-center justify-center">
  {{-- Shell that will animate left/right --}}
  <div id="loginShell"
     class="relative w-full max-w-6xl flex items-center justify-between gap-10 px-8 md:px-10 transition-all duration-500">

    {{-- LEFT: Guidance panel --}}
    <div class="login-side hidden md:flex flex-col items-center text-center">
      <div class="relative mb-5">
        <div class="absolute inset-0 blur-3xl bg-amber-300/40 rounded-full scale-110"></div>
        <div class="relative w-40 h-40 rounded-full bg-gradient-to-tr from-amber-50 via-amber-100 to-amber-200 shadow-2xl flex items-center justify-center ring-4 ring-amber-300/70">
          <img src="{{ asset('images/icons/guidance_logo.png') }}" alt="Guidance & Counseling Office"
              class="w-32 h-32 object-contain">
        </div>
      </div>

      {{-- ⬇️ text block with wider width --}}
      <div class="space-y-1 max-w-xs">
        <p class="text-xs font-semibold tracking-[.2em] text-amber-700 uppercase">
          Guidance & Counseling Office
        </p>

        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 whitespace-nowrap">
          Tagoloan Community College
        </h2>

        <p class="text-[13px] leading-relaxed text-slate-600 mt-2 max-w-[22rem] mx-auto">
          Official LumiChat portal of Tagoloan Community College.
          Your conversations are managed by the Guidance & Counseling Office 
          with strict confidentiality.
        </p>
      </div>
    </div>

    {{-- RIGHT: Login card --}}
    <div class="relative w-full max-w-lg login-panel">
      <div class="absolute -inset-[1px] rounded-3xl bg-gradient-to-r from-violet-400/40 via-indigo-400/40 to-blue-400/40 blur opacity-60"></div>
      <div class="relative lumi-card rounded-3xl bg-white backdrop-blur-xl shadow-2xl ring-1 ring-slate-200/60 p-8">

        {{-- Header --}}
        <div class="flex flex-col items-center text-center">
          <div class="relative mb-4">
            <span class="absolute inset-0 -top-2 -left-2 -right-2 -bottom-2 rounded-full blur-2xl opacity-30"
                  style="background: radial-gradient(60% 60% at 50% 40%, #818cf8 0%, rgba(129,140,248,0) 65%);"></span>
            <img src="{{ asset('images/chatbot.png') }}" alt="LumiChat Logo" class="relative w-14 h-14 rounded-full shadow-md">
          </div>
          <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">LumiChat</h1>
          <p class="mt-1 text-sm text-slate-500">Your mental health support companion</p>

          <div class="mt-3 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-semibold text-indigo-700 ring-1 ring-indigo-200">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
            School Account Login
          </div>
        </div>

        {{-- ===== Scoped CSS ===== --}}
               {{-- ===== Scoped CSS ===== --}}
        <style>
          :root{
            --ring-hover: 165,180,252;  /* indigo-300 */
            --ring-focus: 129,140,248;  /* indigo-400 */
          }

          #loginForm .field.error{
            border-color:#ef4444;
            box-shadow:inset 0 0 0 2px rgba(239,68,68,.22);
          }

          .lumi-card { --card-bg:#ffffff; }

          /* ===== Field shell ===== */
          #loginForm .field{
            position:relative; display:flex; align-items:center;
            height:48px; background:transparent;
            border:1px solid #cbd5e1; /* slate-300 */
            border-radius:0.875rem; padding:0 .75rem;
            transition: box-shadow .18s ease, border-color .18s ease;
          }
          #loginForm .field:hover{
            border-color: rgba(99,102,241,.5);
            box-shadow:
              inset 0 0 0 1px rgba(99,102,241,.25),
              inset 0 4px 12px rgba(0,0,0,.04);
          }
          #loginForm .field:focus-within{
            border-color: rgba(99,102,241,.85);
            box-shadow:
              inset 0 0 0 2px rgba(99,102,241,.35),
              inset 0 6px 14px rgba(0,0,0,.06);
            background:transparent;
          }

          #loginForm .icon-20{
            width:20px; height:20px; margin-right:.5rem;
            opacity:.85; user-select:none; pointer-events:none;
          }

          /* Inputs */
          #loginForm input.input{
            width:100%; height:100%; background:transparent;
            border:none; outline:none; box-shadow:none;
            font-size:14px; color:#0f172a; line-height:48px;
            padding:0 2.25rem 0 .25rem;
          }
          #loginForm input:-webkit-autofill,
          #loginForm input:-webkit-autofill:hover,
          #loginForm input:-webkit-autofill:focus{
            -webkit-box-shadow: 0 0 0px 1000px transparent inset !important;
            transition: background-color 99999s ease-in-out 0s !important;
            -webkit-text-fill-color: #0f172a !important;
          }

          /* Floating label */
          #loginForm .float-label{
            position:absolute; left:3rem; top:50%; transform:translateY(-54%);
            font-size:.80rem; line-height:1; color:#64748b;
            padding:0; margin:0; pointer-events:none; z-index:2;
            transition:transform .18s ease, color .18s ease, font-size .18s ease, opacity .18s ease;
          }
          #loginForm .float-label::after{
            content:""; position:absolute; left:-1px; right:-1px; top:0; bottom:0;
            background: var(--card-bg); border-radius:6px;
            box-shadow:0 0 0 6px var(--card-bg); z-index:-1;
            transition:left .18s ease, right .18s ease, top .18s ease, bottom .18s ease, box-shadow .18s ease;
          }
          #loginForm .peer:focus ~ .float-label,
          #loginForm .peer[data-filled="true"] ~ .float-label,
          #loginForm .peer:not(:placeholder-shown) ~ .float-label{
            color:#334155; font-size:.72rem; transform:translateY(-250%) scale(.98);
          }

          /* Eye button */
          .eye-btn{
            position:absolute; right:.5rem; top:50%; transform:translateY(-50%);
            padding:.25rem; border-radius:.375rem;
          }
          .eye-btn:hover{ background:#e5e7eb; }
          .eye-btn img{ display:block; width:20px; height:20px; }

          @supports not ((-webkit-backdrop-filter: blur(24px)) or (backdrop-filter: blur(24px))){
            .lumi-card{ background:#fff; }
          }
          @media (prefers-reduced-motion: reduce){
            *{ transition:none !important; }
          }
          @media (max-width: 420px){
            .swal2-title.lumi-title{ font-size: 20px !important; }
            .swal2-html-container.lumi-body{ font-size: 14px !important; }
          }

          /* ==== Custom checkbox (Remember me) ==== */
          .checkbox-wrapper-46 .cbx span:last-child{ padding-left:12px }
          .checkbox-wrapper-46 input[type="checkbox"]{
            display:none; visibility:hidden;
          }
          .checkbox-wrapper-46 .cbx{
            -webkit-user-select:none; user-select:none; cursor:pointer;
            display:flex; align-items:center;
          }
          .checkbox-wrapper-46 .cbx span{
            display:inline-block; vertical-align:middle; transform:translate3d(0,0,0);
          }
          .checkbox-wrapper-46 .cbx span:first-child{
            position:relative; width:18px; height:18px; border-radius:3px;
            border:1px solid #9098a9; transition:all .2s ease; background:transparent;
          }
          .checkbox-wrapper-46 .cbx span:first-child svg{
            position:absolute; top:3px; left:2px; fill:none; stroke:#fff; stroke-width:2;
            stroke-linecap:round; stroke-linejoin:round;
            stroke-dasharray:16px; stroke-dashoffset:16px; transition:all .3s ease .1s;
          }
          .checkbox-wrapper-46 .cbx:hover span:first-child{ border-color:#6366f1 }
          .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child{
            background:#6366f1; border-color:#6366f1; animation:wave-46 .4s ease;
          }
          .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child svg{
            stroke-dashoffset:0;
          }
          .checkbox-wrapper-46 .cbx span:first-child:before{
            content:""; width:100%; height:100%; background:#6366f1;
            display:block; transform:scale(0); opacity:1; border-radius:50%;
          }
          .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child:before{
            transform:scale(3.5); opacity:0; transition:all .6s ease;
          }
          @keyframes wave-46{ 50%{ transform:scale(.9) } }

          /* ==== Lumi SweetAlert style ==== */
          .swal2-container.lumi-container{
            backdrop-filter: blur(2px);
            background:
              radial-gradient(40% 25% at 50% 0%, rgba(124,58,237,.18) 0%, rgba(124,58,237,0) 60%),
              radial-gradient(45% 25% at 50% 100%, rgba(79,70,229,.16) 0%, rgba(79,70,229,0) 65%),
              rgba(0,0,0,.45) !important;
          }
          .swal2-popup.lumi-alert{
            border-radius: 1rem !important;
            width: min(440px, 92vw) !important;
            padding: 20px 22px 16px !important;
            box-shadow:
              0 22px 60px rgba(2,6,23,.20),
              0 2px 8px rgba(2,6,23,.08) !important;
            border: 1px solid rgba(15,23,42,.06);
          }
          .swal2-title.lumi-title{
            margin: 4px 0 0 !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            letter-spacing: .1px;
            font-size: clamp(22px, 2.6vw, 30px) !important;
          }
          .swal2-html-container.lumi-body{
            text-wrap: pretty;
            letter-spacing: .1px;
            margin: 10px 0 0 !important;
            color:#334155 !important;
            font-size: 15px !important;
            line-height: 1.55 !important;
            text-align: center !important;
          }
          .swal2-actions.lumi-actions{
            margin-top: 18px !important;
          }
          .swal2-confirm.lumi-confirm{
            font-size: 14.5px !important;
            min-width: 108px;
            background-image: linear-gradient(90deg,#4f46e5,#7c3aed) !important;
            color:#fff !important;
            border-radius: 0.9rem !important;
            font-weight: 800 !important;
            padding: .75rem 1.35rem !important;
            border: 0 !important;
            box-shadow: 0 12px 28px rgba(79,70,229,.28);
          }
          .swal2-confirm.lumi-confirm:hover{ filter: brightness(.98); }
          .swal2-confirm.lumi-confirm:active{ transform: translateY(1px); }
          .swal2-confirm.lumi-confirm:focus-visible{
            outline: none !important;
            box-shadow:
              0 0 0 3px rgba(255,255,255,.9),
              0 0 0 6px rgba(99,102,241,.55) !important;
          }

          /* Animated error icon */
          .lumi-cross{
            margin: 6px auto 0;
            width:64px;height:64px;border-radius:9999px;
            display:grid;place-items:center;margin:10px auto 0;
            background:#fee2e2;border:1px solid #fecaca;
            box-shadow: inset 0 0 0 6px #fff; position:relative;
            transform: translateY(-6px);
          }
          .lumi-cross::before{
            content:""; position:absolute; inset:-10px; border-radius:inherit;
            background: radial-gradient(60% 60% at 50% 50%, rgba(124,58,237,.10), rgba(124,58,237,0) 70%);
          }
          .lumi-cross::after{
            content:""; position:absolute; inset:-6px; border-radius:inherit;
            box-shadow: 0 0 0 0 rgba(239,68,68,.26);
            animation:pulseRing 1.6s ease-out infinite;
          }
          @keyframes pulseRing{
            0%{box-shadow:0 0 0 0 rgba(239,68,68,.26)}
            100%{box-shadow:0 0 0 18px rgba(239,68,68,0)}
          }
          .lumi-cross-svg{ width:28px;height:28px; stroke:#ef4444; stroke-width:3; }

          /* ===== Layout / Animation for loginShell & guidance panel ===== */

          #loginShell{
            position:relative;
            display:flex;
            align-items:center;
            justify-content:center;    /* login card starts centered */
            width:100%;
          }

          /* Login card default (centered) */
          #loginShell .login-panel{
            flex:0 0 450px;
            transform:translateX(0);
            box-shadow:
              0 18px 45px rgba(15,23,42,.18),
              0 0 0 1px rgba(15,23,42,.03);
            transition:
              transform .55s cubic-bezier(.16,.84,.44,1),
              box-shadow .55s ease,
              filter .55s ease;
            z-index:10;
          }

          /* Base guidance panel: hidden on mobile, collapsed on desktop */
          #loginShell .login-side{
            display:none;
          }

          /* Desktop / big screens: enable split animation */
          @media (min-width: 1024px){
            #loginShell .login-side{
                display:flex;
                flex-direction:column;
                align-items:center;
                text-align:center;
                opacity:0;
                transform:translateX(-10px);
                max-width:0;
                width:0;
                /* overflow:hidden;  ← REMOVE this, causes clipping */
                pointer-events:none;
                transition:
                  opacity .55s cubic-bezier(.16,.84,.44,1),
                  transform .55s cubic-bezier(.16,.84,.44,1),
                  max-width .55s cubic-bezier(.16,.84,.44,1),
                  width .55s cubic-bezier(.16,.84,.44,1);
            }

            /* When user focuses/clicks → slide card right, logo far left */
            #loginShell.engaged .login-panel{
              transform:translateX(160px);   /* how far card moves to the right */
              box-shadow:
                0 34px 90px rgba(15,23,42,.35),
                0 0 0 1px rgba(129,140,248,.14);
            }

            #loginShell.engaged .login-side{
                opacity:1;
                max-width:360px;   /* mas maluwag for full text */
                width:360px;
                pointer-events:auto;
                transform:translateX(-80px); /* slight pull to the left, adjust if needed */
                overflow:visible;  /* make sure walang cut */
            }
          }

          /* Mobile: keep only centered card, no logo panel */
          @media (max-width: 1023px){
            #loginShell{
              max-width:100%;
            }
            #loginShell .login-panel{
              flex:1 1 auto;
              transform:none !important;
              box-shadow:
                0 18px 45px rgba(15,23,42,.18),
                0 0 0 1px rgba(15,23,42,.03);
            }
            #loginShell .login-side{
              display:none !important;
            }
          }
        </style>


        {{-- ===== Form ===== --}}
        <form id="loginForm" method="POST" action="{{ $postRoute }}" class="mt-6 space-y-5">
          @csrf

          {{-- Email / Student ID --}}
          <div class="field">
            <img src="{{ asset('images/icons/mail.png') }}" alt="mail" class="icon-20">
            <input
              id="login-email" name="email" aria-describedby="emailHelp"
              type="text" value="{{ old('email') }}" required placeholder=" "
              autocomplete="username" autocapitalize="off" spellcheck="false" inputmode="text" maxlength="254"
              class="input peer" data-filled="false"
            />
            <label for="login-email" class="float-label">Email or Student ID</label>
          </div>
          <p id="emailHelp" class="!mt-1 text-xs text-slate-500">
            Enter your campus email or SIS ID (e.g., 2025001).
          </p>

          {{-- Password --}}
          <div class="field">
            <img src="{{ asset('images/icons/lock.png') }}" alt="lock" class="icon-20">
            <input
              id="passwordInput" name="password" type="password" required placeholder=" "
              autocomplete="current-password" class="input pr-10 peer" data-filled="false"
            />
            <label for="passwordInput" class="float-label">Password</label>
            <button id="togglePassword" type="button" class="eye-btn" aria-label="Show password" aria-pressed="false">
              <img src="{{ asset('images/icons/eye.png') }}" alt="toggle">
            </button>
          </div>
          <p id="capsNote" class="!mt-1 text-xs text-amber-600 hidden">Caps Lock is on.</p>

          {{-- Remember me --}}
          <div class="flex items-center justify-between text-sm mt-4">
            <div class="checkbox-wrapper-46">
              <input type="checkbox" id="remember-cbx" class="inp-cbx" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}/>
              <label for="remember-cbx" class="cbx">
                <span>
                  <svg viewBox="0 0 12 10" height="10" width="12"><polyline points="1.5 6 4.5 9 10.5 1"></polyline></svg>
                </span>
                <span class="text-slate-700">Remember me</span>
              </label>
            </div>
          </div>

          {{-- Submit --}}
          <button id="loginBtn" type="submit"
                  class="w-full inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white py-2.5
                         font-semibold shadow-lg shadow-indigo-500/20 ring-1 ring-white/10 shadow-inner
                         hover:shadow-xl hover:brightness-[.99] active:translate-y-[1px] transition duration-150
                         disabled:opacity-70 disabled:cursor-not-allowed">
            Login
          </button>

          <p class="text-center text-[12px] text-slate-500 mt-2">
            For authorized campus users only. Accounts are provisioned by the school.
          </p>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Loader --}}
<div id="loginLoading" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
  <div class="text-center">
    <div class="three-body" role="status" aria-live="polite">
      <div class="three-body__dot"></div>
      <div class="three-body__dot"></div>
      <div class="three-body__dot"></div>
    </div>
    <div class="mt-4 text-white text-xl font-semibold">{{ $title }}</div>
    <p class="mt-1 text-sm text-white/90">{{ $subtitle }}</p>
  </div>
</div>

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- SweetAlert triggers (errors/toasts) --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const bladeErrors  = @json($errors->any() ? $errors->all() : []);
  const flashError   = @json(session('error'));
  const flashSuccess = @json(session('success'));
  const flashInfo    = @json(session('info'));
  const cooldown     = Number(@json(session('cooldown')) || 0);

  const escapeHtml = (s) => (s||'').toString()
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

  const fmt = (sec) => {
    const m = Math.floor(sec / 60), s = sec % 60;
    return `${String(m).padStart(1,'0')}:${String(s).padStart(2,'0')}`;
  };

  let bodyHtml = '';
  if (cooldown > 0) {
    bodyHtml = `
      <div style="margin-top:4px;color:#334155;font-size:15px;line-height:1.55;text-align:center">
        Too many login attempts. Please try again in
        <span id="lumi-count" style="font-weight:800;color:#4f46e5">${fmt(cooldown)}</span>.
      </div>`;
  } else if (bladeErrors && bladeErrors.length) {
    bodyHtml = `<ul style="margin:6px 0 0;padding:0 0 0 18px;text-align:left">
      ${bladeErrors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}
    </ul>`;
  } else if (flashError) {
    bodyHtml = `<div style="text-align:center">${escapeHtml(flashError)}</div>`;
  }

  if (bodyHtml) {
    if (Swal.isVisible()) Swal.close();

    Swal.fire({
      icon: 'none',
      title: 'Login failed',
      html: `
        <div class="lumi-cross">
          <svg class="lumi-cross-svg" viewBox="0 0 24 24" fill="none">
            <path d="M6 6l12 12M18 6L6 18"></path>
          </svg>
        </div>
        <div class="lumi-body">${bodyHtml}</div>
      `,
      showConfirmButton: true,
      confirmButtonText: 'Okay',
      width: 460,
      allowEnterKey: true,
      customClass: {
        container: 'lumi-container',
        popup: 'lumi-alert',
        title: 'lumi-title',
        htmlContainer: 'lumi-body',
        actions: 'lumi-actions',
        confirmButton: 'lumi-confirm'
      },
      didOpen: (popup) => {
        if (cooldown > 0) {
          const span = popup.querySelector('#lumi-count');
          let t = cooldown;
          const tick = () => {
            t -= 1;
            if (t <= 0) {
              span.textContent = '0:00';
              clearInterval(timer);
              location.reload();
              return;
            }
            span.textContent = fmt(t);
          };
          span.textContent = fmt(t);
          var timer = setInterval(tick, 1000);
        }
      }
    });
  }

  const toast = Swal.mixin({
    toast: true, position: 'top-end',
    showConfirmButton: false, timer: 2800, timerProgressBar: true
  });
  if (flashSuccess) toast.fire({ icon: 'success', title: flashSuccess });
  if (flashInfo)    toast.fire({ icon: 'info',    title: flashInfo    });
});
</script>

{{-- Page JS --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form      = document.getElementById('loginForm');
  const email     = document.getElementById('login-email');
  const pwd       = document.getElementById('passwordInput');
  const toggleBtn = document.getElementById('togglePassword');
  const toggleImg = toggleBtn ? toggleBtn.querySelector('img') : null;
  const loginBtn  = document.getElementById('loginBtn');
  const loading   = document.getElementById('loginLoading');
  const capsNote  = document.getElementById('capsNote');
  const loginShell = document.getElementById('loginShell');
  const loginPanel = loginShell ? loginShell.querySelector('.login-panel') : null;

  function setFilled(el){ el.dataset.filled = (el.value.trim() !== '') ? 'true' : 'false'; }
  [email, pwd].forEach(el => {
    if (!el) return;
    ['input','change','blur'].forEach(ev => el.addEventListener(ev, () => setFilled(el)));
    setTimeout(() => setFilled(el), 50);
    setTimeout(() => setFilled(el), 400);
    setTimeout(() => setFilled(el), 1200);
  });

  function canSubmit(){ return !!(email && pwd && email.value.trim() && pwd.value.trim()); }
  function syncBtn(){ if (loginBtn) loginBtn.disabled = !canSubmit(); }
  [email, pwd].forEach(el => el && ['input','change','blur'].forEach(ev => el.addEventListener(ev, syncBtn)));
  syncBtn();

  if (pwd && capsNote){
    ['keydown','keyup'].forEach(ev=>{
      pwd.addEventListener(ev, e=>{
        const on = e.getModifierState && e.getModifierState('CapsLock');
        capsNote.classList.toggle('hidden', !on);
      });
    });
  }

  if (pwd && toggleBtn && toggleImg){
    const eyeOpen   = "{{ asset('images/icons/eye.png') }}";
    const eyeClosed = "{{ asset('images/icons/eye-off.png') }}";
    toggleBtn.addEventListener('click', () => {
      const showing = pwd.type === 'text';
      pwd.type = showing ? 'password' : 'text';
      toggleImg.src = showing ? eyeOpen : eyeClosed;
      toggleBtn.setAttribute('aria-pressed', showing ? 'false' : 'true');
      pwd.focus({preventScroll:true});
    });
  }

  if (form){
    form.addEventListener('submit', () => {
      const emailInput = form.querySelector('input[name="email"]');
      if (emailInput && typeof emailInput.value === 'string') {
        emailInput.value = emailInput.value.normalize('NFKC').trim().replace(/\s+/g, '');
      }
      if (loginBtn) {
        loginBtn.disabled = true;
        loginBtn.setAttribute('aria-busy','true');
      }
      if (loading) {
        loading.classList.remove('hidden');
        loading.classList.add('flex');
      }
    });
  }

  /* ===== Animate shell when CLICKING / FOCUSING inputs ===== */

  function isInputFocused() {
    return document.activeElement === email || document.activeElement === pwd;
  }

  function refreshShellState() {
    if (!loginShell) return;
    if (isInputFocused()) {
      loginShell.classList.add('engaged');
    } else {
      loginShell.classList.remove('engaged');
    }
  }

  [email, pwd].forEach(el => {
    if (!el) return;
    el.addEventListener('focus', refreshShellState);
    el.addEventListener('blur', () => setTimeout(refreshShellState, 40));
  });

  // Click anywhere on the card to gently focus email and engage
  if (loginPanel && email) {
    loginPanel.addEventListener('click', (e) => {
      const t = e.target;
      if (t === email || t === pwd || t.closest('button')) return;
      if (!isInputFocused()) {
        email.focus();
      }
    });
  }

  // If fields already filled (autofill/remember me), start engaged
  setTimeout(() => {
    if ((email && email.value.trim()) || (pwd && pwd.value.trim())) {
      refreshShellState();
    }
  }, 300);
});
</script>
@endsection
