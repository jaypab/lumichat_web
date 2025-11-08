{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.student-guest')

@section('content')
@php
  $ctx       = strtolower((string)($loginContext ?? 'student')) === 'admin' ? 'admin' : 'student';
  $title     = $ctx === 'admin' ? 'Welcome back, Admin' : 'Welcome to LumiChat';
  $subtitle  = $ctx === 'admin' ? 'Preparing your dashboard…' : 'Signing you in…';
  $postRoute = $ctx === 'admin' ? route('admin.login.post') : route('login');
@endphp


  <div class="relative w-full max-w-md">
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
      <style>
        /* Softer ring colors so the float chip doesn't look like it has padding */
        :root{
          --ring-hover: 165,180,252;  /* indigo-300 */
          --ring-focus: 129,140,248;  /* indigo-400 */
          --ring-shadow: 2,6,23;      /* deep slate for subtle inner glow */
        }

        #loginForm .field.error{ border-color:#ef4444; box-shadow:inset 0 0 0 2px rgba(239,68,68,.22); }  

        .lumi-card { --card-bg:#ffffff; }
        /* Field shell */
        #loginForm .field{
          position:relative;
          display:flex; align-items:center;
          height:48px;
          background: transparent;
          border:1px solid #cbd5e1;           /* slate-300 */
          border-radius:0.875rem;              /* ~rounded-xl */
          padding:0 .75rem;
          transition: box-shadow .18s ease, border-color .18s ease;
        }

        /* Hover – subtle, does NOT match focus glow */
          #loginForm .field:hover{
            border-color: rgba(99,102,241,.85);
            box-shadow:
              inset 0 0 0 2px rgba(99,102,241,.35),
              inset 0 6px 14px rgba(0,0,0,.06);
          }

        /* Focus – cleaner + LESS violet glow */
        #loginForm .field:focus-within {
          border-color: rgba(99,102,241,.85);     /* indigo-500 */
          box-shadow:
            inset 0 0 0 2px rgba(99,102,241,.35), /* smaller glow */
            inset 0 6px 14px rgba(0,0,0,.06);
          background: transparent;
        }

        #loginForm .icon-20{
          width:20px;height:20px;margin-right:.5rem;
          opacity:.85;user-select:none;pointer-events:none;
        }
        
        #loginForm .float-label::after {
          box-shadow: 0 0 0 6px var(--card-bg); /* ensure it clears the glow fully */
        }

        /* Inputs look transparent; must be .peer for the label states */
        #loginForm input.input{
          width:100%; height:100%;
          background:transparent; border:none; outline:none; box-shadow:none;
          font-size:16px; color:#0f172a;       /* slate-900 */
          line-height:48px;
          padding:0 2.25rem 0 .25rem;          /* room for eye on password */
        }
        #loginForm input.input.peer{ padding:0 2.25rem 0 .25rem; }

        /* Chrome autofill: keep transparent look */
        #loginForm input:-webkit-autofill,
        #loginForm input:-webkit-autofill:hover,
        #loginForm input:-webkit-autofill:focus{
          -webkit-box-shadow: 0 0 0px 1000px transparent inset !important;
          transition: background-color 99999s ease-in-out 0s !important;
          -webkit-text-fill-color: #0f172a !important;
        }

        /* ===== Floating label (compact) ===== */
        #loginForm .float-label{
          position:absolute;
          left:3rem;                 /* keep near the icon */
          top:50%;
          transform: translateY(-54%);;/* slightly lower baseline */
          font-size:.80rem;          /* base size (smaller than before) */
          line-height:1;             /* tighter height */
          color:#64748b;
          padding:0;                 /* <<< zero padding */
          margin:0;
          pointer-events:none;
          z-index:2;
          transition:transform .18s ease, color .18s ease, font-size .18s ease, opacity .18s ease;
        }

        /* Knockout chip — thinner & narrower */
        #loginForm .float-label::after{
          content:"";
          position:absolute;
          left:-1px; right:-1px; 
          top:0; bottom:0;   
          background: var(--card-bg);
          border-radius:6px;         /* tighter corners */
          box-shadow:0 0 0 4px var(--card-bg);
          opacity:1;                 /* same color as card → invisible */
          z-index:-1;
          transition:left .18s ease, right .18s ease, top .18s ease, bottom .18s ease, box-shadow .18s ease;
        }

        /* Floated state — small & higher, chip stays compact */
        #loginForm .peer:focus ~ .float-label,
        #loginForm .peer[data-filled="true"] ~ .float-label,
        #loginForm .peer:not(:placeholder-shown) ~ .float-label{
          color:#334155;
          font-size:.72rem;          /* smaller floated text */
          transform:translateY(-250%) scale(.98); /* lift without huge travel */
        }

        /* Keep the chip the same size when floated (no expansion) */
        #loginForm .peer:focus ~ .float-label::after,
        #loginForm .peer[data-filled="true"] ~ .float-label::after,
        #loginForm .peer:not(:placeholder-shown) ~ .float-label::after{
          left:-2px; right:-2px; top:-1px; bottom:-1px;
          box-shadow:0 0 0 4px var(--card-bg);
        }

        /* Eye button */
        .eye-btn{position:absolute;right:.5rem;top:50%;transform:translateY(-50%);padding:.25rem;border-radius:.375rem}
        .eye-btn:hover{background:#e5e7eb}
        .eye-btn img{display:block;width:20px;height:20px}

        /* Checkbox spacing + style */
        .checkbox-wrapper-46 .cbx span:last-child{padding-left:12px}
        .checkbox-wrapper-46 input[type="checkbox"]{display:none;visibility:hidden}
        .checkbox-wrapper-46 .cbx{margin:auto;-webkit-user-select:none;user-select:none;cursor:pointer;display:flex;align-items:center}
        .checkbox-wrapper-46 .cbx span{display:inline-block;vertical-align:middle;transform:translate3d(0,0,0)}
        .checkbox-wrapper-46 .cbx span:first-child{position:relative;width:18px;height:18px;border-radius:3px;border:1px solid #9098a9;transition:all .2s ease;background:transparent}
        .checkbox-wrapper-46 .cbx span:first-child svg{position:absolute;top:3px;left:2px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:16px;stroke-dashoffset:16px;transition:all .3s ease .1s}
        .checkbox-wrapper-46 .cbx span:first-child:before{content:"";width:100%;height:100%;background:#6366f1;display:block;transform:scale(0);opacity:1;border-radius:50%}
        .checkbox-wrapper-46 .cbx:hover span:first-child{border-color:#6366f1}
        .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child{background:#6366f1;border-color:#6366f1;animation:wave-46 .4s ease}
        .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child svg{stroke-dashoffset:0}
        .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child:before{transform:scale(3.5);opacity:0;transition:all .6s ease}
        @keyframes wave-46{50%{transform:scale(.9)}}

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

        @supports not ((-webkit-backdrop-filter: blur(24px)) or (backdrop-filter: blur(24px))){
          .lumi-card{ background:#fff; }
        }
        @media (prefers-reduced-motion: reduce){ *{ transition:none !important; } }
      </style>

      {{-- ===== Form ===== --}}
      <form id="loginForm" method="POST" action="{{ $postRoute }}" class="mt-6 space-y-5">
        @csrf

        {{-- Email / Student ID --}}
        <div class="field">
          <img src="{{ asset('images/icons/mail.png') }}" alt="mail" class="icon-20">
          <input
            id="login-email"
            name="email"
            aria-describedby="emailHelp"
            type="text"
            value="{{ old('email') }}"
            required
            placeholder=" "
            autocomplete="username"
            autocapitalize="off"
            spellcheck="false"
            inputmode="email"
            maxlength="254"
            class="input peer"
            data-filled="false"
          />
          <label for="login-email" class="float-label">Email or Student ID</label>
        </div>
        <p id="emailHelp" class="!mt-1 text-xs text-slate-500">Use your school-provisioned account.</p>

        {{-- Password --}}
        <div class="field">
          <img src="{{ asset('images/icons/lock.png') }}" alt="lock" class="icon-20">
          <input
            id="passwordInput"
            name="password"
            type="password"
            required
            placeholder=" "
            autocomplete="current-password"
            class="input pr-10 peer"
            data-filled="false"
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
   </div>        <!-- closes .lumi-card -->
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

<script>
function canSubmit(){ 
  return (email.value.trim() !== '' && pwd.value.trim() !== '');
}
function toggleBtn(){ 
  loginBtn.disabled = !canSubmit(); 
}

// ---- elements
const email   = document.getElementById('login-email');
const pwd     = document.getElementById('passwordInput');
const loginBtn= document.getElementById('loginBtn');
const capsNote= document.getElementById('capsNote');

// live enable/disable
[email, pwd].forEach(el => ['input','change','blur'].forEach(ev => el.addEventListener(ev, toggleBtn)));
toggleBtn();

// caps-lock indicator
['keydown','keyup'].forEach(ev=>{
  pwd.addEventListener(ev, e=>{
    const on = e.getModifierState && e.getModifierState('CapsLock');
    if (capsNote) capsNote.classList.toggle('hidden', !on);
  });
});

// submit: sanitize + busy state + loader
form.addEventListener('submit', () => {
  const emailInput = form.querySelector('input[name="email"]');
  if (emailInput && typeof emailInput.value === 'string') {
    emailInput.value = emailInput.value.normalize('NFKC').trim().replace(/\s+/g, '');
  }
  loginBtn.disabled = true;
  loginBtn.setAttribute('aria-busy','true');   // a11y
  loading.classList.remove('hidden'); loading.classList.add('flex');
});

(() => {
  // Mark filled (handles autofill + programmatic)
  function setFilled(el){
    el.dataset.filled = (el.value && el.value.trim() !== '') ? 'true' : 'false';
  }
  ['#login-email','#passwordInput'].forEach(sel => {
    const el = document.querySelector(sel); if(!el) return;
    ['input','change','blur'].forEach(ev => el.addEventListener(ev, () => setFilled(el)));
    setTimeout(() => setFilled(el), 50);
    setTimeout(() => setFilled(el), 400);
    setTimeout(() => setFilled(el), 1200);
  });

  // Eye toggle
  const pwd = document.getElementById('passwordInput');
  const btn = document.getElementById('togglePassword');
  const img = btn.querySelector('img');
  const eyeOpen   = "{{ asset('images/icons/eye.png') }}";
  const eyeClosed = "{{ asset('images/icons/eye-off.png') }}";
  btn.addEventListener('click', () => {
    const showing = pwd.type === 'text';
    pwd.type = showing ? 'password' : 'text';
    img.src = showing ? eyeOpen : eyeClosed;
    btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
    pwd.focus({preventScroll:true});
  });

  // Submit: sanitize + loader
  const form     = document.getElementById('loginForm');
  const loginBtn = document.getElementById('loginBtn');
  const loading  = document.getElementById('loginLoading');
  form.addEventListener('submit', () => {
    const emailInput = form.querySelector('input[name="email"]');
    if (emailInput && typeof emailInput.value === 'string') {
      emailInput.value = emailInput.value.normalize('NFKC').trim().replace(/\s+/g, '');
    }
    loginBtn.disabled = true;
    loading.classList.remove('hidden'); loading.classList.add('flex');
  });
})();
</script>
@endsection
