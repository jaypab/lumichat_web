@extends('layouts.student-guest')

@section('content')
@php
  // Controller passes $loginContext = 'admin' | 'student'
  $ctx = strtolower((string)($loginContext ?? 'student')) === 'admin' ? 'admin' : 'student';
  $title = $ctx === 'admin' ? 'Welcome back admin' : 'Welcome to lumichat';
  $subtitle = $ctx === 'admin' ? 'preparing your dashboard..' : 'Signing you in....';
  $postRoute = $ctx === 'admin' ? route('admin.login.post') : route('login');
@endphp

<div class="bg-white p-10 rounded-2xl shadow-xl w-full max-w-md animate-fadeup">
  <div class="flex flex-col items-center mb-6">
    <div class="relative">
      <span class="absolute inset-0 -top-1 -left-1 -right-1 -bottom-1 rounded-full blur-xl opacity-30"
            style="background: radial-gradient(60% 60% at 50% 40%, #818cf8 0%, rgba(129,140,248,0) 65%);"></span>
      <img src="{{ asset('images/chatbot.png') }}" alt="LumiChat Logo"
           class="relative w-14 h-14 rounded-full mb-3 shadow-md">
    </div>
    <h2 class="text-2xl font-bold text-gray-800 font-poppins">LumiChat</h2>
    <p class="text-sm">Your mental health support companion</p>
  </div>

  {{-- ===== UI styles: checkbox, eye toggle, loader, and SweetAlert theme ===== --}}
  <style>
    /* Custom checkbox */
    .checkbox-wrapper-46 input[type="checkbox"]{display:none;visibility:hidden}
    .checkbox-wrapper-46 .cbx{margin:auto;-webkit-user-select:none;user-select:none;cursor:pointer;display:flex;align-items:center}
    .checkbox-wrapper-46 .cbx span{display:inline-block;vertical-align:middle;transform:translate3d(0,0,0)}
    .checkbox-wrapper-46 .cbx span:first-child{
      position:relative;width:18px;height:18px;border-radius:3px;border:1px solid #9098a9;transition:all .2s ease;background:transparent
    }
    .checkbox-wrapper-46 .cbx span:first-child svg{
      position:absolute;top:3px;left:2px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;
      stroke-dasharray:16px;stroke-dashoffset:16px;transition:all .3s ease .1s
    }
    .checkbox-wrapper-46 .cbx span:first-child:before{content:"";width:100%;height:100%;background:#6366f1;display:block;transform:scale(0);opacity:1;border-radius:50%}
    .checkbox-wrapper-46 .cbx span:last-child{padding-left:10px}
    .checkbox-wrapper-46 .cbx:hover span:first-child{border-color:#6366f1}
    .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child{background:#6366f1;border-color:#6366f1;animation:wave-46 .4s ease}
    .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child svg{stroke-dashoffset:0}
    .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child:before{transform:scale(3.5);opacity:0;transition:all .6s ease}
    @keyframes wave-46{50%{transform:scale(.9)}}

    /* Eye toggle */
    .eye-btn{position:absolute;right:.5rem;top:50%;transform:translateY(-50%);padding:.25rem;border-radius:.375rem}
    .eye-btn:hover{background:#e5e7eb}
    .eye-btn img{display:block;width:20px;height:20px;user-select:none;pointer-events:none}
    .eye-btn.is-hidden::after{
      content:"";position:absolute;left:50%;top:50%;width:2px;height:18px;background:#9ca3af;
      transform:translate(-50%,-50%) rotate(45deg);border-radius:1px;
    }

    /* Loader */
    .three-body{ --uib-size:35px; --uib-speed:.8s; --uib-color:#5D3FD3; position:relative; display:inline-block; height:var(--uib-size); width:var(--uib-size); animation:spin78236 calc(var(--uib-speed)*2.5) infinite linear; }
    .three-body__dot{position:absolute;height:100%;width:30%}
    .three-body__dot:after{content:'';position:absolute;height:0%;width:100%;padding-bottom:100%;background-color:var(--uib-color);border-radius:50%}
    .three-body__dot:nth-child(1){bottom:5%;left:0;transform:rotate(60deg);transform-origin:50% 85%}
    .three-body__dot:nth-child(1)::after{bottom:0;left:0;animation:wobble1 var(--uib-speed) infinite ease-in-out;animation-delay:calc(var(--uib-speed)*-0.3)}
    .three-body__dot:nth-child(2){bottom:5%;right:0;transform:rotate(-60deg);transform-origin:50% 85%}
    .three-body__dot:nth-child(2)::after{bottom:0;left:0;animation:wobble1 var(--uib-speed) infinite calc(var(--uib-speed)*-0.15) ease-in-out}
    .three-body__dot:nth-child(3){bottom:-5%;left:0;transform:translateX(116.666%)}
    .three-body__dot:nth-child(3)::after{top:0;left:0;animation:wobble2 var(--uib-speed) infinite ease-in-out}
    @keyframes spin78236{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
    @keyframes wobble1{0%,100%{transform:translateY(0) scale(1);opacity:1}50%{transform:translateY(-66%) scale(.65);opacity:.8}}
    @keyframes wobble2{0%,100%{transform:translateY(0) scale(1);opacity:1}50%{transform:translateY(66%) scale(.65);opacity:.8}}

    /* ===== SweetAlert "LumiAlert" theme ===== */
    .swal-lumi-popup{border-radius:1.25rem !important;box-shadow:0 30px 80px rgba(2,6,23,.25) !important;padding:1.6rem !important}
    .swal-lumi-title{font-size:1.65rem !important;line-height:1.2;font-weight:800;color:#111827;margin:.35rem 0 .9rem}
    .swal-lumi-body{color:#4b5563;font-size:1rem}
    .swal-lumi-actions{margin-top:1rem;gap:.5rem}
    .swal-lumi-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:.75rem;padding:.65rem 1.1rem;font-weight:600}
    .swal-lumi-confirm{background:#4f46e5;color:#fff;box-shadow:0 10px 24px rgba(79,70,229,.28)}
    .swal-lumi-confirm:hover{filter:brightness(.96)}
    .swal-lumi-cancel{background:#fff;border:1px solid #e5e7eb;color:#111827}
    .swal-lumi-icon-wrap{width:86px;height:86px;margin:0 auto 14px;position:relative}
    .swal-lumi-ring{position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 6px rgba(239,68,68,.12), inset 0 0 0 2px rgba(239,68,68,.35);animation:lumiPulse 1.8s ease-out infinite}
    @keyframes lumiPulse{0%{box-shadow:0 0 0 6px rgba(239,68,68,.12), inset 0 0 0 2px rgba(239,68,68,.35)}70%{box-shadow:0 0 0 18px rgba(239,68,68,0), inset 0 0 0 2px rgba(239,68,68,.35)}100%{box-shadow:0 0 0 6px rgba(239,68,68,0), inset 0 0 0 2px rgba(239,68,68,.35)}}
    .swal-lumi-x{position:absolute;inset:10px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;border:2px solid #fca5a5}
    .swal-lumi-list{margin:.25rem 0 0;padding-left:1.15rem}
    .swal-lumi-list li{margin:.35rem 0}
  </style>

  <form id="loginForm" method="POST" action="{{ $postRoute }}" class="space-y-5">
    @csrf

    <div>
      <label class="block text-gray-700 text-sm mb-1 font-medium">Email or Student ID</label>
      <div class="flex items-center bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 focus-within:ring-2 ring-blue-300">
        <img src="{{ asset('images/icons/mail.png') }}" alt="Mail Icon" class="w-5 h-5 mr-2">
        <input
          type="text"
          name="email"
          value="{{ old('email') }}"
          placeholder="Enter your email or Student ID"
          required
          autofocus
          autocomplete="username"
          autocapitalize="off"
          spellcheck="false"
          inputmode="email"
          maxlength="254"
          class="w-full bg-transparent text-sm placeholder-gray-400 appearance-none border-none shadow-none
                 focus:outline-none focus:ring-0"
        />
      </div>
    </div>

    <div>
      <label class="block text-gray-700 text-sm mb-1 font-medium">Password</label>
      <div class="relative flex items-center bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 focus-within:ring-2 ring-blue-300">
        <img src="{{ asset('images/icons/lock.png') }}" alt="Lock Icon" class="w-5 h-5 mr-2">
        <input
          id="passwordInput"
          type="password"
          name="password"
          autocomplete="current-password"
          placeholder="Enter your password"
          required
          aria-describedby="capsHint"
          class="w-full bg-transparent text-sm placeholder-gray-400 appearance-none border-none shadow-none pr-10
                 focus:outline-none focus:ring-0"
        />
        <button
          id="togglePassword"
          type="button"
          class="eye-btn is-hidden"
          aria-label="Show password"
          aria-pressed="false"
          title="Show password"
        >
          <img src="{{ asset('images/icons/seepassword.png') }}" alt="Toggle password visibility" draggable="false" />
        </button>
      </div>
      <p id="capsHint" class="mt-1 text-xs text-amber-600 hidden">Caps Lock is ON</p>
    </div>

    <div class="flex items-center justify-between text-sm">
      <div class="checkbox-wrapper-46">
        <input type="checkbox" id="remember-cbx" class="inp-cbx" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}/>
        <label for="remember-cbx" class="cbx">
          <span>
            <svg viewBox="0 0 12 10" height="10px" width="12px"><polyline points="1.5 6 4.5 9 10.5 1"></polyline></svg>
          </span>
          <span class="text-gray-700">Remember me</span>
        </label>
      </div>
    </div>

    <button id="loginBtn" type="submit"
            class="w-full bg-blue-500 hover:bg-blue-600 transition text-white py-2.5 rounded-lg font-medium shadow
                   disabled:opacity-70 disabled:cursor-not-allowed">
      Login
    </button>

    <p class="text-center text-sm text-gray-600 mt-4">
      Don't have an account? <a href="{{ route('register') }}" class="text-blue-600 hover:underline font-medium">Sign up here</a>
    </p>
  </form>
</div>

{{-- Full-screen loader (role-aware) --}}
<div id="loginLoading"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
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

{{-- SweetAlert2 (single include) --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  // ===== Password eye toggle ===== <a href="{{ route('password.request') }}" class="text-blue-600 hover:underline">Forgot password?</a>
  const pwd = document.getElementById('passwordInput');
  const btn = document.getElementById('togglePassword');
  function syncEye(){
    const hidden = (pwd.type === 'password');
    btn.classList.toggle('is-hidden', hidden);
    btn.setAttribute('aria-pressed', hidden ? 'false':'true');
    btn.setAttribute('aria-label', hidden ? 'Show password':'Hide password');
    btn.setAttribute('title', hidden ? 'Show password':'Hide password');
  }
  btn.addEventListener('click', () => { pwd.type = (pwd.type === 'password') ? 'text' : 'password'; syncEye(); pwd.focus({preventScroll:true}); });
  syncEye();

  // ===== Caps lock hint =====
  const caps = document.getElementById('capsHint');
  function updateCaps(e){ const on = e.getModifierState && e.getModifierState('CapsLock'); caps.classList.toggle('hidden', !on); }
  ['keydown','keyup'].forEach(ev => pwd.addEventListener(ev, updateCaps));
  pwd.addEventListener('blur', () => caps.classList.add('hidden'));

  // ===== Submit: sanitize email + show loader =====
  const form = document.getElementById('loginForm');
  const loginBtn = document.getElementById('loginBtn');
  const loading = document.getElementById('loginLoading');
  form.addEventListener('submit', () => {
    const emailInput = form.querySelector('input[name="email"]');
    if (emailInput && typeof emailInput.value === 'string') {
      emailInput.value = emailInput.value.normalize('NFKC').trim().replace(/\s+/g, '');
    }
    loginBtn.disabled = true;
    loading.classList.remove('hidden');
    loading.classList.add('flex');
  });

  // ===== LumiAlert mixin =====
  const LumiAlert = Swal.mixin({
    width: 560,
    padding: '1.2rem',
    backdrop: 'rgba(17,24,39,.55)',
    buttonsStyling: false,
    focusConfirm: false,
    showClass: { popup: 'swal2-show' },
    hideClass: { popup: 'swal2-hide' },
    customClass: {
      popup: 'swal-lumi-popup',
      title: 'swal-lumi-title',
      htmlContainer: 'swal-lumi-body',
      actions: 'swal-lumi-actions',
      confirmButton: 'swal-lumi-btn swal-lumi-confirm',
      cancelButton:  'swal-lumi-btn swal-lumi-cancel'
    }
  });

  // Error icon block
  const errorIconHTML = `
    <div class="swal-lumi-icon-wrap">
      <div class="swal-lumi-ring"></div>
      <div class="swal-lumi-x">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </div>
    </div>
  `;

  function buildList(items){
    return `<ul class="swal-lumi-list">${items.map(e=>`<li>• ${e}</li>`).join('')}</ul>`;
  }

  // ===== Server-side messages =====
  @if ($errors->any())
    (function(){
      const errs = @json($errors->all());
      const joined = errs.join(' ');
      const throttled = /too many login attempts|throttle/i.test(joined);
      const secMatch = joined.match(/(\d+)\s*seconds?/i);
      let wait = throttled && secMatch ? parseInt(secMatch[1],10) : 0;

      let html = errorIconHTML;
      if (throttled){
        html += `
          <div class="swal-lumi-body">
            <p class="mb-2">For your security, login is temporarily locked.</p>
            <p class="mb-2">Try again in <b id="retryCountdown" style="font-variant-numeric:tabular-nums">${wait}</b> seconds.</p>
          </div>`;
      } else {
        html += `<div class="swal-lumi-body">${buildList(errs)}</div>`;
      }

      LumiAlert.fire({
        title: 'Please fix the following',
        html,
        confirmButtonText: 'OK'
      }).then(() => {
        const email = document.querySelector('input[name="email"]');
        if (email) email.focus();
      });

      if (throttled && wait > 0){
        const el = () => document.getElementById('retryCountdown');
        const iv = setInterval(()=>{
          wait = Math.max(0, wait-1);
          const node = el(); if (node) node.textContent = String(wait);
          if (wait <= 0) clearInterval(iv);
        }, 1000);
      }
    })();
  @endif

  @if (session('success'))
    LumiAlert.fire({ icon:'success', title:'Success', html:@json(session('success')), confirmButtonText:'Continue' });
  @endif

  @if (session('status'))
    LumiAlert.fire({ icon:'success', title:'Success', html:@json(session('status')), confirmButtonText:'OK' });
  @endif
})();
</script>
@endsection
