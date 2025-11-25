{{-- resources/views/layouts/counselor.blade.php --}}
<!DOCTYPE html>
<html lang="en" data-app="counselor">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ trim($__env->yieldContent('title')) ?: 'Counselor • Dashboard' }}</title>

  {{-- SweetAlert2 (aligned with student/admin) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js" defer></script>

  @vite(['resources/css/app.css','resources/js/app.js'])
  @include('layouts.partials.favicons')
  @include('layouts.partials.tour')

  <style>
    :root{
      --z-modal: 1100;     /* panel */
      --z-backdrop: 1099;  /* backdrop */
      --rail-expanded: 18rem;
      --rail-collapsed: 84px;
      --header-h: 56px;
      --sidebar-grad-a: #4f46e5;
      --sidebar-grad-b: #7c3aed;
    }

    /* Use on the modal root container */
    .modal-zp{ position: fixed; inset: 0; z-index: var(--z-modal); }

    /* Backdrop: dark tint + blur */
    .modal-zp .modal-backdrop{
      position: absolute; inset: 0;
      background: rgba(2, 6, 23, 0.60);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: var(--z-backdrop);
    }

    .modal-zp .modal-panel{ position: relative; z-index: calc(var(--z-modal) + 1); }

    .modal-zp .modal-backdrop--lg{
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    
    body.no-scroll { overflow: hidden; }
    html,body{height:100%}
    body{overflow-x:hidden;-webkit-tap-highlight-color:transparent}

    /* ------- Sidebar & main sizing ------- */
    #cslSidebar{ width:var(--rail-expanded); transition: width .25s ease, transform .25s ease; }
    #cslMain{ transition: padding .25s ease; }
    @media (min-width:1024px){
      #cslMain{ padding-left: var(--rail-expanded); }
      .csl-collapsed #cslMain{ padding-left: var(--rail-collapsed); }
    }
    .csl-collapsed #cslSidebar{ width: var(--rail-collapsed); }
    .rail-header{ height: var(--header-h); }

    /* ------- Background polish (match admin look) ------- */
    #cslSidebar{
      border-radius:0 0 12px 0;
      background-color:#4f46e5;
      background-image:linear-gradient(135deg, var(--sidebar-grad-a), var(--sidebar-grad-b));
      background-size:200% 200%;
      animation:sidebarGradient 14s ease infinite;
      box-shadow:0 10px 30px rgba(0,0,0,.18);
    }
    @keyframes sidebarGradient{
      0%{ background-position:0% 50%; }
      50%{ background-position:100% 50%; }
      100%{ background-position:0% 50%; }
    }

    /* ------- Collapsed (desktop) ------- */
    @media (min-width:1024px){
      .csl-collapsed .brand-text,
      .csl-collapsed .nav-label,
      .csl-collapsed .hide-when-collapsed{ display:none!important; }
      .csl-collapsed #railClose{ display:none!important; }
      .csl-collapsed .nav-item{ justify-content:center; }
    }

    /* ------- Hamburger visibility ------- */
    #railOpen{ display:inline-flex; }
    @media (min-width:1024px){
      body:not(.csl-collapsed) #railOpen{ display:none; }
      body.csl-collapsed #railOpen{ display:inline-flex; }
    }
    body.mobile-rail-open #railOpen{ display:none; }

    /* ------- Nav item look ------- */
    .nav-item{
      display:flex; align-items:center; gap:.75rem;
      position:relative;
      padding:.75rem 1rem; border-radius:.9rem;
      ring:1px solid transparent;
      transition:background .2s, transform .12s ease;
    }
    .nav-item:hover{ background:rgba(255,255,255,.14); transform:translateY(-1px); }
    .nav-item.is-active{
      background:rgba(255,255,255,.22); border:1px solid rgba(255,255,255,.18);
      box-shadow:0 4px 12px rgba(0,0,0,.08);
    }
    .nav-item.is-active::before{
      content:""; position:absolute; left:10px; top:50%;
      transform:translateY(-50%); width:4px; height:26px; border-radius:999px; background:rgba(255,255,255,.96);
    }
    .nav-item > span.inline-flex{ border-radius:.75rem; background:rgba(255,255,255,.1); }
    .nav-item.is-active > span.inline-flex{ background:rgba(255,255,255,.2); }

    /* ------- Make PNG icons white ------- */
    #cslSidebar nav a.nav-item > span > img{
      width:22px; height:22px; object-fit:contain;
      -webkit-filter: invert(1) brightness(1.15); filter: invert(1) brightness(1.15);
    }

    /* ------- Sidebar inner scroll ------- */
    #railScroll{
      height: calc(100vh - var(--header-h));
      overflow-y:auto; overflow-x:hidden; -webkit-overflow-scrolling:touch;
      scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.7) transparent;
    }
    @supports (height: 100dvh){ #railScroll{ height: calc(100dvh - var(--header-h)); } }
    #railScroll::-webkit-scrollbar{ width:10px; }
    #railScroll::-webkit-scrollbar-thumb{
      background:rgba(255,255,255,.65);
      border-radius:9999px; border:2px solid rgba(255,255,255,.25); background-clip:padding-box;
    }

    /* ------- Tooltips for collapsed rail ------- */
    .nav-item .rail-tip{
      position:absolute; inset:auto auto 50% 100%;
      transform: translateY(50%) translateX(8px);
      padding:.35rem .6rem; font-size:.75rem; white-space:nowrap;
      background:#0f172a; color:#fff; border-radius:.5rem;
      box-shadow:0 10px 24px rgba(15,23,42,.35);
      opacity:0; pointer-events:none;
      transition:opacity .12s ease, transform .12s ease;
    }
    @media (min-width:1024px){
      .csl-collapsed .nav-item:hover .rail-tip{
        opacity:1; transform: translateY(50%) translateX(12px);
      }
      .nav-item .danger { color:#ef4444; }
      .nav-item:hover .danger { color:#f87171; }
      .nav-item.is-active .danger { color:#fecaca; }
    }

    #cslSidebar{ overflow-x:clip; }
  </style>

  {{-- === Notification popover z-index fix (same as student/admin) === --}}
  <style id="nb-popover-z">
    [data-nb-root]{ position:relative; z-index:2147483641; }
    .nb-portal, [data-nb-portal], #nb-portal{ position:absolute; z-index:2147483642 !important; }
  </style>

  {{-- === Global SweetAlert theme (same as student/admin) === --}}
  <style id="lumi-swal-theme">
    .swal2-container.swal2-backdrop-show{
      background:rgba(15,23,42,.55)!important;
      backdrop-filter:blur(4px) saturate(110%);
    }
    .swal2-container.swal2-top-start,
    .swal2-container.swal2-top,
    .swal2-container.swal2-top-end,
    .swal2-container.swal2-bottom-start,
    .swal2-container.swal2-bottom,
    .swal2-container.swal2-bottom-end{
      background:transparent!important;
      backdrop-filter:none!important;
      pointer-events:none!important;
      z-index:2147483000!important;
    }
    .swal2-container .swal2-popup{ pointer-events:auto!important; }
    .swal2-container.swal2-top-end{
      padding-top:max(12px, env(safe-area-inset-top))!important;
      padding-right:max(12px, env(safe-area-inset-right))!important;
      padding-bottom:12px!important;
      padding-left:12px!important;
    }

    .swal2-popup:not(.swal2-toast){
      background:#fff!important;
      border-radius:22px!important;
      padding:28px 32px!important;
      box-shadow:
        0 40px 80px -20px rgba(2,6,23,.35),
        0 0 0 1px rgba(2,6,23,.05),
        0 30px 60px rgba(109,40,217,.08)!important;
      max-width:680px;
    }
    .dark .swal2-popup:not(.swal2-toast){
      background:rgba(17,24,39,.96)!important;
      color:#e5e7eb!important;
    }
    .swal2-popup:not(.swal2-toast) .swal2-title{
      margin:12px 0 0!important;
      font-weight:700;
      font-size:26px!important;
      letter-spacing:.2px;
      text-align:center;
      color:#0f172a;
    }
    .dark .swal2-popup:not(.swal2-toast) .swal2-title{ color:#f8fafc; }
    .swal2-popup:not(.swal2-toast) .swal2-html-container{
      margin-top:6px!important;
      font-size:15px!important;
      color:#475569!important;
    }
    .dark .swal2-popup:not(.swal2-toast) .swal2-html-container{
      color:#cbd5e1!important;
    }
    .swal2-popup:not(.swal2-toast) .swal2-actions{
      margin-top:22px!important;
      gap:10px;
      flex-wrap:wrap;
    }
    .swal2-styled{
      border-radius:14px!important;
      padding:10px 18px!important;
      font-weight:700!important;
      box-shadow:none!important;
    }
    .swal2-confirm{
      background:linear-gradient(90deg,#7c3aed,#6366f1)!important;
      color:#fff!important;
      box-shadow:0 10px 24px rgba(99,102,241,.35)!important;
    }
    .swal2-cancel,.swal2-deny{
      background:#fff!important;
      color:#334155!important;
      border:1px solid #e5e7eb!important;
    }
    .dark .swal2-cancel,.dark .swal2-deny{
      background:#1f2937!important;
      color:#e5e7eb!important;
      border-color:#334155!important;
    }
  </style>
</head>

<body class="bg-slate-50 text-slate-800 antialiased">

{{-- ===== SIDEBAR / RAIL (counselor) ===== --}}
<aside id="cslSidebar" class="fixed inset-y-0 left-0 z-40 -translate-x-full lg:translate-x-0 text-white shadow-xl">
  <div class="rail-header px-4 flex items-center justify-between border-b border-white/20">
    <div class="flex items-center gap-2">
      <img src="{{ asset('images/chatbot.png') }}" class="w-9 h-9 rounded-full ring-2 ring-white/30 object-cover" alt="LumiCHAT">
      <span class="brand-text font-semibold tracking-wide">LumiChat</span>
    </div>
    <button id="railClose" class="p-2 rounded-md hover:bg-white/10" title="Collapse / Close" aria-label="Collapse / Close">
      <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <nav class="h-[calc(100vh-var(--header-h))] flex flex-col">
    <div id="railScroll" class="px-3 py-3 grow">
      <p class="px-3 text-[11px] uppercase tracking-wider/relaxed opacity-90 nav-label">Main</p>

      {{-- Dashboard --}}
      <a href="{{ route('counselor.dashboard') }}"
         aria-current="{{ request()->routeIs('counselor.dashboard') ? 'page' : 'false' }}"
         class="nav-item group relative mt-2 px-3 py-2.5 ring-1 ring-transparent
                {{ request()->routeIs('counselor.dashboard') ? 'is-active' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/home.png') }}" alt="">
        </span>
        <span class="nav-label font-medium">Dashboard</span>
        <span class="rail-tip">Dashboard</span>
      </a>

      {{-- My Availability --}}
      <a href="{{ route('counselor.availability.index') }}"
         aria-current="{{ request()->routeIs('counselor.availability.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                {{ request()->routeIs('counselor.availability.*') ? 'is-active' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/appointment.png') }}" alt="">
        </span>
        <span class="nav-label font-medium">My Availability</span>
        <span class="rail-tip">My Availability</span>
      </a>

      {{-- My Appointments --}}
      <a href="{{ route('counselor.appointments.index') }}"
        aria-current="{{ request()->routeIs('counselor.appointments.*') ? 'page' : 'false' }}"
        class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                {{ request()->routeIs('counselor.appointments.*') ? 'is-active' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/appointment.png') }}" alt="">
        </span>
        <span class="nav-label font-medium">Appointments</span>
        <span class="rail-tip">Appointments</span>
      </a>

    </div>

    <div class="px-3 py-3 border-t border-white/15 hide-when-collapsed">
      <form method="POST" action="{{ route('logout') }}" data-lumi-logout="1">
        @csrf
        <button type="submit" class="lumi-logout-btn">
          <img src="{{ asset('images/icons/logout.png') }}" alt="" class="sidebar-icon logout-icon">
          <span class="font-medium">Logout</span>
        </button>
      </form>
    </div>
  </nav>
</aside>

{{-- Mobile scrim --}}
<div id="sidebarScrim" class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm hidden lg:hidden"></div>

{{-- ===== MAIN ===== --}}
<div id="cslMain" class="min-h-screen">
  <header class="sticky top-0 z-20 h-[var(--header-h)] bg-white/80 backdrop-blur border-b border-slate-200">
    <div class="h-full max-w-7xl mx-auto px-4 flex items-center justify-between overflow-visible">
      {{-- LEFT cluster: hamburger + title --}}
      <div class="flex items-center gap-3">
        <button id="railOpen" class="p-2 rounded-md hover:bg-slate-100" title="Open sidebar" aria-label="Open sidebar">
          <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <h1 class="text-lg font-semibold">@yield('page_title','Dashboard')</h1>
      </div>

      @php
        $u = auth()->user();
        $displayName = trim((string)($u->name ?? 'Counselor'));
        $initials = collect(preg_split('/\s+/', $displayName))
                      ->filter()
                      ->take(2)
                      ->map(fn($p) => mb_substr($p, 0, 1))
                      ->implode('');
      @endphp

      {{-- RIGHT cluster: bell + user chip --}}
      <div class="flex items-center gap-3">
        @auth
          <div data-nb-root class="relative z-[2147483641]">
            <x-notification-bell
              :indexRoute="route('counselor.notifications.index')"
              :feedRoute="route('counselor.notifications.feed')"
              :markRoute="route('counselor.notifications.mark', ['id' => ':id'])"
              :markAllRoute="route('counselor.notifications.mark_all')"
            />
          </div>
        @endauth

        <div class="relative">
          <button id="cslUserBtn"
            class="inline-flex items-center gap-2 h-10 px-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-xs font-bold">
              {{ $initials ?: 'U' }}
            </div>
            <div class="hidden sm:flex flex-col text-left leading-tight mr-1">
              <span class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[8rem]">
                {{ $displayName }}
              </span>
              <span class="text-[11px] text-gray-500 dark:text-gray-400">Counselor</span>
            </div>
          </button>

          {{-- Dropdown --}}
          <div id="cslUserMenu"
               class="hidden absolute right-0 mt-2 w-44 rounded-xl bg-white shadow-lg ring-1 ring-black/5 overflow-hidden z-30">
            <a href="{{ route('profile.edit') }}" class="block px-3 py-2.5 text-sm hover:bg-slate-50">Profile</a>
            @if (Route::has('settings.index'))
              <a href="{{ route('settings.index') }}" class="block px-3 py-2.5 text-sm hover:bg-slate-50">Settings</a>
            @endif
            <div class="h-px bg-slate-200 my-1"></div>
            <form method="POST" action="{{ route('logout') }}" data-lumi-logout="1">
              @csrf
              <button type="submit" class="w-full text-left px-3 py-2.5 text-sm text-rose-600 hover:bg-rose-50">Logout</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-6">
    @yield('content')
  </main>
</div>

<script>
(function () {
  const body      = document.body;
  const sidebar   = document.getElementById('cslSidebar');
  const scrim     = document.getElementById('sidebarScrim');
  const openBtn   = document.getElementById('railOpen');
  const closeBtn  = document.getElementById('railClose');
  const mqDesktop = window.matchMedia('(min-width: 1024px)');
  const LS_KEY    = 'cslSidebarCollapsed';
  const isDesktop = () => mqDesktop.matches;

  function setCollapsed(on){
    body.classList.toggle('csl-collapsed', !!on);
    localStorage.setItem(LS_KEY, on ? '1' : '0');
  }
  const getCollapsed = () => localStorage.getItem(LS_KEY) === '1';

  function openMobile(){
    sidebar.classList.remove('-translate-x-full');
    scrim.classList.remove('hidden');
    body.classList.add('no-scroll','mobile-rail-open');
  }
  function closeMobile(){
    sidebar.classList.add('-translate-x-full');
    scrim.classList.add('hidden');
    body.classList.remove('no-scroll','mobile-rail-open');
  }

  openBtn?.addEventListener('click', () => {
    if (isDesktop()) { if (getCollapsed()) setCollapsed(false); }
    else { openMobile(); }
  });
  closeBtn?.addEventListener('click', () => {
    if (isDesktop()) setCollapsed(true);
    else closeMobile();
  });
  scrim?.addEventListener('click', closeMobile);
  window.addEventListener('keydown', e => { if (e.key === 'Escape' && !isDesktop()) closeMobile(); });

  function applyMode(){
    if (isDesktop()){
      body.classList.remove('mobile-rail-open');
      sidebar.classList.remove('-translate-x-full');
      scrim.classList.add('hidden');
      body.classList.remove('no-scroll');
      setCollapsed(getCollapsed());
    } else {
      sidebar.classList.add('-translate-x-full');
      body.classList.remove('csl-collapsed','mobile-rail-open');
    }
  }
  mqDesktop.addEventListener('change', applyMode);
  applyMode();
})();
</script>

<script>
/* Counselor user menu toggle */
(function(){
  const btn  = document.getElementById('cslUserBtn');
  const menu = document.getElementById('cslUserMenu');
  if (!btn || !menu) return;

  const close = () => menu.classList.add('hidden');
  const toggle = () => menu.classList.toggle('hidden');

  btn.addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target) && !btn.contains(e.target)) close();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>

<button id="lumi-tour-fab"
        type="button"
        title="Help – Restart tutorial"
        aria-label="Restart tutorial">?</button>
@stack('scripts')

{{-- ===== Global SweetAlert flash renderer (robust + guarded) ===== --}}
@php
  if (!function_exists('lumi_flash_text')) {
    function lumi_flash_text($key) {
        $val = session($key);
        if ($val instanceof \Illuminate\Support\MessageBag) return implode(' ', $val->all());
        if ($val instanceof \Illuminate\View\View) return trim((string)$val);
        if (is_array($val)) {
            $flat = [];
            array_walk_recursive($val, function($v) use (&$flat){ $flat[] = $v; });
            return implode(' ', array_map('strval', $flat));
        }
        return is_scalar($val) ? (string)$val : '';
    }
  }
@endphp

@php
  $flashPayload = null;
  if (session('swal')) {
      $flashPayload = ['type' => 'swal', 'data' => session('swal')];
  } elseif (session()->has('error')) {
      $flashPayload = ['type' => 'error', 'text' => lumi_flash_text('error')];
  } elseif (session()->has('warning')) {
      $flashPayload = ['type' => 'warning', 'text' => lumi_flash_text('warning')];
  } elseif (session()->has('info')) {
      $flashPayload = ['type' => 'info', 'text' => lumi_flash_text('info')];
  } elseif (session()->has('success')) {
      $flashPayload = ['type' => 'success', 'text' => lumi_flash_text('success')];
  } elseif ($errors->any()) {
      $flashPayload = ['type' => 'error', 'text' => $errors->first()];
  }
@endphp

@if ($flashPayload)
  <script>
    (function(){
      const payload = @json($flashPayload);
      function fireSwal(){
        try{
          if (payload.type === 'swal') { return Swal.fire(payload.data); }
          const base = {
            icon: payload.type,
            title: payload.type === 'error' ? 'Error'
                 : payload.type === 'warning' ? 'Warning'
                 : payload.type === 'info' ? 'Info' : 'Success',
            text: payload.text || '',
          };
          if (payload.type === 'error')   base.confirmButtonColor = '#e11d48';
          if (payload.type === 'success') base.confirmButtonColor = '#4f46e5';
          if (payload.type === 'warning') base.confirmButtonColor = '#f59e0b';
          if (payload.type === 'info')    base.confirmButtonColor = '#4f46e5';
          Swal.fire(base);
        } catch(e){
          alert((payload.type.toUpperCase()) + ': ' + (payload.text || ''));
        }
      }
      if (document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', fireSwal);
      } else {
        fireSwal();
      }
    })();
  </script>
@endif

{{-- === Premium logout confirmation for counselor (same as student/admin) === --}}
<script>
  (function () {
    const forms = document.querySelectorAll('form[data-lumi-logout="1"]');
    if (!forms.length) return;

    forms.forEach(form => {
      form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (typeof Swal === 'undefined') {
          form.submit();
          return;
        }

        const iconHtml = `
              <path d="M12 7v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
              <circle cx="12" cy="16.5" r="1.2" fill="currentColor"></circle>
            </svg>
          </div>
        `;

        Swal.fire({
          title: 'Sign out of LumiCHAT?',
          html: `
            ${iconHtml}
            <p class="mt-2 text-[14px] text-slate-600 dark:text-slate-300">
              You’ll be logged out from this device. Your conversations and appointments will stay saved in your account.
            </p>
          `,
          focusConfirm: false,
          showCancelButton: true,
          showCloseButton: true,
          confirmButtonText: 'Logout',
          cancelButtonText: 'Stay signed in',
          reverseButtons: true,
          customClass: {
            popup: 'swal2-logout-popup',
            confirmButton: 'swal2-logout-confirm',
            cancelButton: 'swal2-logout-cancel'
          }
        }).then(result => {
          if (result.isConfirmed) {
            form.submit();
          }
        });
      });
    });
  })();
</script>

</body>
</html>
