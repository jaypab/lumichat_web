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
@viteReactRefresh
@vite('resources/js/login-bg.jsx')
<div id="beams-root" class="fixed inset-0 z-0 pointer-events-none overflow-hidden"></div>

<div class="h-screen w-full flex items-center justify-center overflow-hidden p-6 md:p-12">
  {{-- Shell: Centered & Balanced --}}
  <div id="loginShell"
     class="relative w-full max-w-5xl flex items-center justify-between gap-16 transition-all duration-700 px-8">

    {{-- LEFT: Guidance & Counseling Office (Compact Centered) --}}
    <div class="login-side hidden lg:flex flex-col items-center justify-center text-center max-w-xs">
      {{-- Luxury Logo Emblem --}}
      <div class="relative mb-6 group">
        {{-- Radiant Aura --}}
        <div class="absolute inset-x-0 -top-4 -bottom-4 blur-[50px] bg-amber-500/10 rounded-full scale-110"></div>
        <div class="absolute inset-0 blur-[20px] bg-violet-600/5 rounded-full"></div>
        
        <div class="relative w-36 h-36 rounded-full p-1 bg-gradient-to-b from-amber-400/30 via-amber-900/10 to-violet-900/30 shadow-xl overflow-hidden ring-1 ring-amber-500/20">
          <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/brushed-alum.png')] opacity-10 mix-blend-overlay"></div>
          <div class="w-full h-full rounded-full bg-black/30 backdrop-blur-2xl flex items-center justify-center p-6 border border-white/5">
            <img src="{{ asset('images/icons/guidance_logo.png') }}" alt="Guidance Logo" 
                 class="w-full h-full object-contain filter drop-shadow-[0_8px_16px_rgba(0,0,0,0.4)] brightness-110">
          </div>
          {{-- Subtle shimmer --}}
          <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
        </div>
      </div>

      <div class="space-y-4 flex flex-col items-center">
        <div class="space-y-2">
          <p class="text-[10px] font-black tracking-[0.4em] text-[#d4af37] uppercase drop-shadow-sm font-inter">
            Guidance & Counseling Office
          </p>
          <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-500/40 to-transparent mx-auto"></div>
          <h2 class="text-3xl font-bold tracking-tight text-white font-poppins leading-tight">
            Tagoloan Community College
          </h2>
        </div>

        <p class="text-[14px] leading-relaxed text-slate-400 font-medium max-w-[320px] font-inter mx-auto">
          Official LumiChat portal of <span class="text-white">Tagoloan Community College</span>. 
          Your conversations are managed by the <span class="text-amber-200/80 font-semibold italic">Guidance & Counseling Office</span> with <span class="text-white/80">strict confidentiality</span>.
        </p>

        {{-- Luxury detail ornament --}}
        <div class="flex items-center justify-center gap-4 pt-4 opacity-30">
          <div class="h-px w-16 bg-gradient-to-r from-transparent to-amber-500/30"></div>
          <div class="w-1.5 h-1.5 rounded-full bg-amber-500 rotate-45 shadow-[0_0_8px_rgba(251,191,36,0.3)]"></div>
          <div class="h-px w-16 bg-gradient-to-l from-transparent to-amber-500/30"></div>
        </div>
      </div>
    </div>

    {{-- RIGHT: Login card (Sleeker & Spread) --}}
    <div class="relative w-full max-w-[400px] login-panel rounded-[2rem] overflow-hidden group shadow-2xl">
      <div class="absolute inset-0 bg-gradient-to-br from-violet-500/5 via-transparent to-fuchsia-500/5 opacity-30"></div>
      <div class="relative lumi-card bg-white/5 backdrop-blur-3xl p-8">

        {{-- Header --}}   
        <div class="flex flex-col items-center text-center">
          <div class="relative mb-4">
            <span class="absolute inset-0 -top-4 -left-4 -right-4 -bottom-4 rounded-full blur-2xl opacity-10"
                  style="background: radial-gradient(circle at 50% 50%, #a855f7 0%, rgba(168,85,247,0) 70%);"></span>
            <div class="w-14 h-14 rounded-2xl bg-white/5 p-3 backdrop-blur-md rotate-2 group-hover:rotate-0 transition-transform duration-700">
               <img src="{{ asset('images/chatbot.png') }}" alt="LumiChat Logo" class="w-full h-full object-contain">
            </div>
          </div>
          <h1 class="text-2xl font-black tracking-tight text-white mb-0.5 font-poppins">LumiChat</h1>
          <p class="text-[12px] text-slate-400 font-medium italic mb-4">Mental health support companion</p>

          <div class="inline-flex items-center gap-2 rounded-full bg-white/5 px-4 py-1.5 text-[8px] font-bold text-violet-300 uppercase tracking-widest">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-violet-400 animate-pulse"></span>
            School Core Access
          </div>
        </div>

        {{-- ===== Scoped CSS ===== --}}
        <style>
          :root{
            --ring-hover: 167,139,250; 
            --ring-focus: 139,92,246; 
          }

          #loginForm .field.error{
            border-color:#ef4444;
            box-shadow:inset 0 0 0 2px rgba(239,68,68,.15);
          }

          .lumi-card { --card-bg: rgba(255,255,255,0.02); }

          /* ===== Field shell (Masterfully Refactored for Line-Cut) ===== */
          #loginForm .field{
            position:relative; display:flex; align-items:center;
            height:48px; background: rgba(255,255,255,0.02);
            /* Remove standard border to use pseudo-element for cut-logic */
            border: none; 
            border-radius:0.875rem; padding:0 0.75rem;
            transition: all .3s ease;
          }
          
          /* The actual border line */
          #loginForm .field::before {
            content: ''; position: absolute; inset: 0;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: inherit;
            pointer-events: none;
            transition: all 0.3s ease;
          }

          #loginForm .field:hover::before{
            border-color: rgba(167,139,250,.3);
            background: rgba(255,255,255,0.02);
          }
          
          /* The Magic: Carving a hole in the line for the label (Tighter gap) */
          #loginForm .field:focus-within::before,
          #loginForm .field:has(.peer:not(:placeholder-shown))::before,
          #loginForm .field:has(.peer[data-filled="true"])::before {
            border-color: rgba(167,139,250,.5);
            /* Surgical Cut: Tuned for a precise fit around "Email or Student ID" */
            clip-path: polygon(0 0, 2.6rem 0, 2.6rem 2px, 11.6rem 2px, 11.6rem 0, 100% 0, 100% 100%, 0 100%);
            box-shadow: 0 0 0 3px rgba(167,139,250,0.06);
          }

          #loginForm .icon-20{
            width:18px; height:18px; margin-right:.625rem;
            opacity:.4; filter: invert(1); transition: all .2s;
            z-index: 1;
          }
          #loginForm .field:hover .icon-20,
          #loginForm .field:focus-within .icon-20{ opacity: 0.9; filter: invert(1) brightness(1.2); }

          /* Inputs */
          #loginForm input.input{
            width:100%; height:100%; background:transparent;
            border:none; outline:none; box-shadow:none;
            font-size:13.5px; color:#fff; line-height:48px;
            padding:0 2rem 0 .25rem; font-weight: 500;
            position: relative; z-index: 1;
            border-radius: inherit; /* Critical fix for rounded autofill corners */
          }
          #loginForm input::placeholder { color: rgba(255,255,255,0.15); font-weight: 400; }

          /* Floating label - Truly Transparent & Seated */
          #loginForm .float-label{
            position:absolute; left:2.75rem; top:50%; transform:translateY(-50%);
            font-size:13.5px; color:rgba(255,255,255,0.3);
            padding: 0; margin:0; pointer-events:none; z-index:10;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent !important; /* Total transparency as requested */
          }
          #loginForm .peer:focus ~ .float-label,
          #loginForm .peer[data-filled="true"] ~ .float-label,
          #loginForm .peer:-webkit-autofill ~ .float-label,
          #loginForm .peer:autofill ~ .float-label,
          #loginForm .peer:not(:placeholder-shown) ~ .float-label{
            color:#a78bfa; font-size:10px; transform:translateY(-220%);
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em;
            text-shadow: 0 0 10px rgba(167,139,250,0.3);
            background: transparent !important;
          }

          /* Eye button */
          .eye-btn{
            position:absolute; right:.5rem; top:50%; transform:translateY(-50%);
            padding:.35rem; border-radius:.625rem; opacity: 0.3; transition: all 0.2s;
            z-index: 5;
            pointer-events: auto;
          }
          .eye-btn:hover{ background:rgba(255,255,255,0.08); opacity: 0.8; }
          .eye-btn img{ display:block; width:16px; height:16px; filter: invert(1); }

          /* ==== Custom checkbox ==== */
          .checkbox-wrapper-46 .cbx span:last-child{ padding-left:8px; color: rgba(221,214,254,0.92); font-size: 12.5px; font-weight: 600; }
          .checkbox-wrapper-46 input[type="checkbox"]{ display:none; }
          .checkbox-wrapper-46 .cbx{ cursor:pointer; display:flex; align-items:center; }
          .checkbox-wrapper-46 .cbx span:first-child{
            position:relative; width:16px; height:16px; border-radius:4px;
            border:1px solid rgba(221,214,254,0.55); transition:all .2s ease; background:transparent;
          }
          .checkbox-wrapper-46 .cbx span:first-child svg{
            position:absolute; top:3px; left:2px; fill:none; stroke:#fff; stroke-width:3;
            stroke-dasharray:16px; stroke-dashoffset:16px; transition:all .3s ease .05s;
          }
          .checkbox-wrapper-46 .cbx:hover span:first-child{ border-color:#a78bfa }
          .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child{
            background:#8b5cf6; border-color:#8b5cf6;
          }
          .checkbox-wrapper-46 .inp-cbx:checked + .cbx span:first-child svg{ stroke-dashoffset:0; }

          @media (max-width: 420px){
            .lumi-card { padding: 20px !important; }
          }

          /* Ensure legal footer text stays readable over dark/violet backgrounds */
          .login-footnote{
            color: rgba(221, 214, 254, 0.92) !important;
            text-shadow: 0 1px 10px rgba(76, 29, 149, 0.25);
          }

          /* ===== Autofill Culprit Fix ===== */
          #loginForm input:-webkit-autofill,
          #loginForm input:-webkit-autofill:hover, 
          #loginForm input:-webkit-autofill:focus, 
          #loginForm input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px #1a1631 inset !important;
            -webkit-text-fill-color: #ffffff !important;
            transition: background-color 5000s ease-in-out 0s;
          }
        </style>


        {{-- ===== Form ===== --}}
        <form id="loginForm" method="POST" action="{{ $postRoute }}" class="mt-6 space-y-5">
          @csrf

          {{-- Email / Student ID --}}
          <div class="space-y-1">
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
          </div>

          {{-- Password --}}
          <div class="field">
            <img src="{{ asset('images/icons/lock.png') }}" alt="lock" class="icon-20">
            <input
              id="passwordInput" name="password" type="password" required placeholder=" "
              autocomplete="current-password" class="input pr-10 peer" data-filled="false"
            />
            <label for="passwordInput" class="float-label">Secure Password</label>
            <button id="togglePassword" type="button" class="eye-btn" aria-label="Show password" aria-pressed="false">
              <img src="{{ asset('images/icons/eye-off.png') }}" alt="Toggle password visibility">
            </button>
          </div>
          <p id="capsNote" class="!mt-1 text-[10px] text-amber-500 font-bold hidden">⚠️ CAPS LOCK IS ON</p>

          {{-- Remember me --}}
          <div class="flex items-center justify-between mt-3">
            <div class="checkbox-wrapper-46">
              <input type="checkbox" id="remember-cbx" class="inp-cbx" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}/>
              <label for="remember-cbx" class="cbx">
                <span>
                  <svg viewBox="0 0 12 10" height="10" width="12"><polyline points="1.5 6 4.5 9 10.5 1"></polyline></svg>
                </span>
                <span>Keep me signed in</span>
              </label>
            </div>
          </div>

          {{-- Submit --}}
          <div class="relative group/btn pt-1">
            <div class="absolute -inset-0.5 bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-lg blur opacity-15 group-hover/btn:opacity-30 transition duration-700"></div>
            <button id="loginBtn" type="submit"
                    class="relative w-full flex items-center justify-center rounded-lg bg-gradient-to-r from-violet-500 via-indigo-500 to-fuchsia-500 text-white py-3
                           font-bold text-sm shadow-xl transition-all duration-300
                           hover:-translate-y-0.5 hover:brightness-110 hover:shadow-violet-500/30 active:translate-y-0
                           disabled:opacity-70 disabled:cursor-not-allowed">
              Log In
            </button>
          </div>
        </form>

        <div class="mt-4 text-center">
          <p class="text-[11px] font-medium text-violet-100/75">
            Need a student account?
            <a
              href="{{ route('account-request.create') }}"
              class="ml-1 font-semibold text-violet-200 underline decoration-violet-300/70 underline-offset-4 transition hover:text-white hover:decoration-violet-200"
            >
              Request access
            </a>
          </p>
        </div>

      </div>
    </div>
  </div>

  <p class="login-footnote text-center text-[11px] font-medium leading-relaxed absolute bottom-4 left-1/2 -translate-x-1/2 w-full px-6 pointer-events-none">
    Private & Secure Mental Health Portal • TCC GCO
  </p>
</div>

{{-- Loader --}}
<div id="loginLoading" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/55 backdrop-blur-sm">
  <div class="text-center">
    <div class="lumi-loader-wrapper" role="status" aria-live="polite">
      <div class="lumi-loader-circle">
        Loading
        <span></span>
      </div>
    </div>

    <div class="mt-4 text-white text-xl font-semibold">{{ $title }}</div>
    <p class="mt-1 text-sm text-white/90">{{ $subtitle }}</p>
  </div>
</div>

<style>
  /* ===== LumiCHAT Loading & Modal Suite ===== */
  #loginLoading { background: rgba(0,0,0,0.4); backdrop-blur: 12px; }
  
  #loginLoading .lumi-loader-wrapper {
    position: relative; width: 140px; height: 140px;
    display: flex; align-items: center; justify-content: center; margin: 0 auto;
  }

  #loginLoading .lumi-loader-circle {
    position: relative; width: 90px; height: 90px;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.05), transparent 70%);
    border-radius: 9999px; border: 2px solid rgba(167,139,250,0.1);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    font-family: 'Poppins', sans-serif; font-size: 10px; font-weight: 800;
    letter-spacing: .2em; text-transform: uppercase; color: #fff;
    text-shadow: 0 0 20px rgba(167,139,250,0.6);
    box-shadow: 0 0 40px rgba(0,0,0,0.4), inset 0 0 20px rgba(167,139,250,0.05);
  }

  #loginLoading .lumi-loader-circle::before {
    content: ""; position: absolute; inset: -4px; border-radius: inherit;
    border: 2px solid transparent; border-top-color: #a78bfa; border-right-color: #f472b6;
    animation: lumi-spin 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  }

  /* ===== SweetAlert2 Premium Redesign (The "Surgical" Modal) ===== */
  .lumi-container { backdrop-filter: blur(8px) !important; background: rgba(0,0,0,0.6) !important; }
  
  .lumi-alert {
    background: rgba(15, 12, 26, 0.6) !important;
    backdrop-filter: blur(30px) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
    border-radius: 2rem !important;
    padding: 2.5rem !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
  }

  .lumi-title {
    color: #fff !important; font-family: 'Poppins', sans-serif !important;
    font-weight: 800 !important; font-size: 1.5rem !important;
    letter-spacing: -0.02em !important; margin-bottom: 0.5rem !important;
  }

  .lumi-body {
    color: rgba(255,255,255,0.6) !important; font-family: 'Inter', sans-serif !important;
    font-size: 0.95rem !important; line-height: 1.6 !important;
    font-weight: 500 !important;
  }

  .lumi-body ul { list-style: none !important; padding: 0 !important; margin: 1rem 0 !important; }
  .lumi-body li::before { content: "•"; margin-right: 0.5rem; opacity: 0.5; }

  .lumi-cooldown-msg { text-align: center; font-weight: 500; color: rgba(255,255,255,0.7); margin-top: 0.5rem; }
  .lumi-timer { color: #a78bfa; font-weight: 800; font-size: 1.25rem; display: block; margin-top: 0.25rem; text-shadow: 0 0 10px rgba(167,139,250,0.3); }

  .lumi-cross {
    width: 64px; height: 64px; margin: 0 auto 1.5rem;
    background: rgba(239, 68, 68, 0.1); border-radius: 1.25rem;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(239, 68, 68, 0.2);
    box-shadow: 0 0 20px rgba(239, 68, 68, 0.1);
  }
  .lumi-cross-svg { width: 28px; height: 28px; stroke: #ef4444; stroke-width: 2.5; stroke-linecap: round; }

  .lumi-actions { margin-top: 2rem !important; width: 100% !important; }
  
  .lumi-confirm {
    background: #fff !important; color: #0f172a !important;
    border-radius: 0.875rem !important; padding: 0.875rem 2rem !important;
    font-weight: 800 !important; font-size: 0.875rem !important;
    width: 100% !important; transition: all 0.3s ease !important;
    border: none !important; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1) !important;
  }
  .lumi-confirm:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2) !important; }

  .lumi-toast-popup {
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border: none !important;
    border-radius: 1rem !important;
    box-shadow: none !important;
    padding: 0.85rem 1rem !important;
    min-width: 320px !important;
    overflow: hidden !important;
  }

  .lumi-toast-title {
    color: #f8fafc !important;
    font-family: 'Inter', sans-serif !important;
    font-size: 0.92rem !important;
    font-weight: 700 !important;
    line-height: 1.4 !important;
    margin: 0 !important;
  }

  .lumi-toast-container {
    padding-top: 16px !important;
    padding-right: 16px !important;
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    pointer-events: none !important;
  }

  .swal2-container.lumi-toast-container.swal2-backdrop-show,
  .swal2-container.lumi-toast-container.swal2-top-end,
  .swal2-container.lumi-toast-container.swal2-top-right,
  .swal2-container.lumi-toast-container.swal2-top {
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
  }

  .swal2-container.lumi-toast-container {
    background: transparent !important;
  }

  .swal2-container.lumi-toast-container .swal2-popup {
    pointer-events: auto !important;
  }

  .swal2-icon.swal2-success.lumi-toast-icon,
  .swal2-icon.swal2-info.lumi-toast-icon {
    margin: 0 0.65rem 0 0 !important;
    transform: scale(0.78);
  }

  .swal2-timer-progress-bar {
    background: linear-gradient(90deg, rgba(168,85,247,0.95), rgba(244,114,182,0.9)) !important;
    height: 3px !important;
  }

  @keyframes lumi-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- SweetAlert triggers (errors/toasts) --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const bladeErrors   = @json($errors->any() ? $errors->all() : []);
  const flashError    = @json(session('error'));
  const flashSuccess  = @json(session('success'));
  const flashInfo     = @json(session('info'));
  const flashStatus   = @json(session('status'));          
  const flashDeleted  = @json(session('account_deleted')); 
  const cooldown      = Number(@json(session('cooldown')) || 0);

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
      <div class="lumi-cooldown-inner">
        Too many login attempts. Please try again in<br/>
        <span id="lumi-count" class="lumi-timer">${fmt(cooldown)}</span>.
      </div>`;
  } else if (bladeErrors && bladeErrors.length) {
    bodyHtml = `<ul>
      ${bladeErrors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}
    </ul>`;
  } else if (flashError) {
    bodyHtml = `<div class="text-center">${escapeHtml(flashError)}</div>`;
  }

  if (bodyHtml) {
    if (Swal.isVisible()) Swal.close();

    Swal.fire({
      icon: 'none',
      title: 'Login failed',
      html: `
        <div class="lumi-cross">
          <svg class="lumi-cross-svg" viewBox="0 0 24 24" fill="none">
            <path stroke="currentColor" d="M6 6l12 12M18 6L6 18"></path>
          </svg>
        </div>
        <div class="lumi-body-inner">${bodyHtml}</div>
      `,
      showConfirmButton: true,
      confirmButtonText: 'Okay',
      width: 420,
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
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2800,
    timerProgressBar: true,
    customClass: {
      container: 'lumi-toast-container',
      popup: 'lumi-toast-popup',
      title: 'lumi-toast-title',
      icon: 'lumi-toast-icon'
    }
  });

  // Generic success/info toasts
  if (flashSuccess) {
    toast.fire({ icon: 'success', title: flashSuccess });
  }
  if (flashInfo) {
    toast.fire({ icon: 'info', title: flashInfo });
  }

  // ✅ Account deletion toast (custom key)
  if (flashDeleted) {
    toast.fire({ icon: 'success', title: flashDeleted });
  }

  // ✅ Jetstream-style "status" (e.g. after delete, password reset, etc.)
  // Only fire if we didn’t already handle it via success/deleted
  if (flashStatus && !flashSuccess && !flashDeleted) {
    toast.fire({ icon: 'success', title: flashStatus });
  }
});
</script>

{{-- Page JS --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form       = document.getElementById('loginForm');
  const email      = document.getElementById('login-email');
  const pwd        = document.getElementById('passwordInput');
  const toggleBtn  = document.getElementById('togglePassword');
  const toggleImg  = toggleBtn ? toggleBtn.querySelector('img') : null;
  const loginBtn   = document.getElementById('loginBtn');
  const loading    = document.getElementById('loginLoading');
  const capsNote   = document.getElementById('capsNote');
  const loginShell = document.getElementById('loginShell');
  const loginPanel = loginShell ? loginShell.querySelector('.login-panel') : null;

  // ===== Floating label filled-state =====
  function setFilled(el){ el.dataset.filled = (el.value.trim() !== '') ? 'true' : 'false'; }
  [email, pwd].forEach(el => {
    if (!el) return;
    ['input','change','blur'].forEach(ev => el.addEventListener(ev, () => setFilled(el)));
    setTimeout(() => setFilled(el), 50);
    setTimeout(() => setFilled(el), 400);
    setTimeout(() => setFilled(el), 1200);
  });

  // ===== Enable/disable submit button =====
  function canSubmit(){ return !!(email && pwd && email.value.trim() && pwd.value.trim()); }
  function syncBtn(){ if (loginBtn) loginBtn.disabled = !canSubmit(); }
  [email, pwd].forEach(el => el && ['input','change','blur'].forEach(ev => el.addEventListener(ev, syncBtn)));
  syncBtn();

  // ===== Caps Lock notice =====
  if (pwd && capsNote){
    ['keydown','keyup'].forEach(ev=>{
      pwd.addEventListener(ev, e=>{
        const on = e.getModifierState && e.getModifierState('CapsLock');
        capsNote.classList.toggle('hidden', !on);
      });
    });
  }

  // ===== Show / hide password (NO animation trigger) =====
  if (pwd && toggleBtn && toggleImg){
    const eyeOpen   = "{{ asset('images/icons/eye.png') }}";
    const eyeClosed = "{{ asset('images/icons/eye-off.png') }}";

    const syncPasswordToggleUI = () => {
      const isVisible = pwd.type === 'text';
      toggleImg.src = isVisible ? eyeOpen : eyeClosed;
      toggleBtn.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
      toggleBtn.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
    };

    // Ensure UI matches the initial input state.
    syncPasswordToggleUI();

    toggleBtn.addEventListener('click', () => {
      pwd.type = pwd.type === 'text' ? 'password' : 'text';
      syncPasswordToggleUI();
      // keep focus on the field, but we won't use blur/focus to toggle animation anymore
      pwd.focus({preventScroll:true});
    });
  }

  // ===== Submit → show loading =====
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

  /* ===== Shell animation: only when entering/exiting the form =====
   * - Once engaged, it stays engaged while user clicks inside (including eye button)
   * - It only collapses back when clicking OUTSIDE the login shell
   */

  let shellEngaged = false;

  function engageShell() {
    if (!loginShell || shellEngaged) return;
    loginShell.classList.add('engaged');
    shellEngaged = true;
  }

  function disengageShell() {
    if (!loginShell || !shellEngaged) return;
    loginShell.classList.remove('engaged');
    shellEngaged = false;
  }

  // Focus on email/password → engage once
  [email, pwd].forEach(el => {
    if (!el) return;
    el.addEventListener('focus', () => {
      engageShell();
    });
  });

  // Click on the card background → focus email & engage
  if (loginPanel && email) {
    loginPanel.addEventListener('click', (e) => {
      const t = e.target;
      // Don't interfere when clicking inputs or ANY button (including eye)
      if (t === email || t === pwd || t.closest('button')) return;
      email.focus({preventScroll:true});
      engageShell();
    });
  }

  // Click OUTSIDE the login shell → collapse animation
  document.addEventListener('click', (e) => {
    if (!loginShell) return;
    if (!loginShell.contains(e.target)) {
      disengageShell();
    }
  });

  // If fields already filled (autofill/remember), start in engaged state
  setTimeout(() => {
    if ((email && email.value.trim()) || (pwd && pwd.value.trim())) {
      engageShell();
    }
  }, 300);
});
</script>
@endsection
