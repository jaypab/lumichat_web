<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <title>{{ trim($__env->yieldContent('title')) ?: 'Lumi - Chat Interface' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @include('layouts.partials.favicons')
  @include('layouts.partials.tour')

  {{-- =========================================================
       0) CRITICAL FONT LOADING (prevents chat bubble resize)
      ========================================================= --}}
  <link rel="preload" href="/fonts/inter-var.woff2" as="font" type="font/woff2" crossorigin>
  <style>
    :root{ --bubble-w: 620px; }

    @font-face{
      font-family:"Inter";
      src:url("/fonts/inter-var.woff2") format("woff2");
      font-weight:100 900;
      font-style:normal;
      font-display:swap;
    }
    html{ background:#f9fafb; color:#111827; }
    html.dark{ background:#111827; color:#e5e7eb; }
    [x-cloak]{ display:none !important; }
  </style>

  <style id="lumi-header-glass">
  /* Frosted, sticky app header */
  .header-shell{
    position: sticky;
    top: 0;
    z-index: 2147483000;        /* below notif & modals, above content */
    background: rgba(255,255,255,.66);
    border-bottom: 1px solid rgba(148,163,184,.28);
    backdrop-filter: blur(10px) saturate(140%);
    -webkit-backdrop-filter: blur(10px) saturate(140%);
  }
  html.dark .header-shell{
    background: rgba(17,24,39,.58);
    border-bottom-color: rgba(148,163,184,.18);
  }

  /* Consistent height + horizontal padding */
  .header-inner{
    height: 64px;
    padding-left: 16px;
    padding-right: 16px;
  }

  /* If the browser can't do backdrop-filter, fall back to a stronger bg */
  @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))){
    .header-shell{ background: rgba(255,255,255,.94); }
    html.dark .header-shell{ background: rgba(17,24,39,.92); }
  }
</style>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=optional" rel="stylesheet">

  {{-- =========================================================
       1) SweetAlert2 (one version; no duplicates)
      ========================================================= --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js" defer></script>

  {{-- =========================================================
       2) Dark / sizing / motion boot (runs before CSS to avoid flicker)
      ========================================================= --}}
  @php
    $isStudent = Auth::check() && (strtolower((string)(Auth::user()->role ?? 'student')) === 'student');
  @endphp

  @if($isStudent)
  <script>
    (() => {
      try {
        const root = document.documentElement;
        root.setAttribute('data-app','student');
        const get = k => localStorage.getItem(k);

        let dark = get('lumichat_dark');
        if (dark === null) { localStorage.setItem('lumichat_dark','0'); dark = '0'; }
        if (dark === 'true') { localStorage.setItem('lumichat_dark','1'); dark = '1'; }
        if (dark === 'false'){ localStorage.setItem('lumichat_dark','0'); dark = '0'; }

        const wantsDark = dark === '1';
        root.classList.toggle('dark', wantsDark);
        root.classList.toggle('dark-theme', wantsDark);

        root.classList.toggle('reduce-motion', get('lumichat_reduce_motion') === '1');

        const fs = get('lumichat_font_size') || 'md';
        root.classList.add('font-' + (['sm','md','lg'].includes(fs) ? fs : 'md'));

        root.classList.toggle('compact', get('lumichat_compact') === '1');
      } catch(_) {}
    })();
  </script>
  @else
  <script>
    try{
      let pref = localStorage.getItem('lumichat_dark');
      if (pref === null) { localStorage.setItem('lumichat_dark','0'); pref = '0'; }
      if (pref === 'true') { localStorage.setItem('lumichat_dark','1'); pref = '1'; }
      if (pref === 'false'){ localStorage.setItem('lumichat_dark','0'); pref = '0'; }
      const wantsDark = pref === '1';
      document.documentElement.classList.toggle('dark', wantsDark);
      document.documentElement.classList.toggle('dark-theme', wantsDark);
      document.documentElement.setAttribute('data-coreui-theme', wantsDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', wantsDark ? 'dark' : 'light');
    }catch(_){}
  </script>
  @endif

  {{-- =========================================================
       3) Vite bundles (Tailwind, app JS, chat helpers)
      ========================================================= --}}
  <style id="critical-chat-lock">
    :root{ --bubble-w: 620px; }
    #chat-messages .bubble:not(.lb2){
      box-sizing:border-box !important;
      width:auto !important;
      max-width:min(var(--bubble-w),86%) !important;
      min-height:0 !important;
      padding:8px 12px !important;
      margin:0 !important;
      white-space:pre-wrap !important;
      word-break:normal !important;
      overflow-wrap:anywhere !important;
    }
    #chat-messages .bubble.bubble-tight{
      font-size:15px !important;
      line-height:22px !important;
      padding:8px 12px !important;
    }
    #lb-scope .lb2{
      width:fit-content !important;
      max-width:min(520px,46ch) !important;
      text-align:left !important;
      padding:6px 10px !important;
      display:inline-block !important;
      box-sizing:border-box !important;
      min-height:0 !important;
      margin:0 !important;
      white-space:pre-wrap !important;
      word-break:normal !important;
      overflow-wrap:anywhere !important;
      align-self:flex-start !important;
    }
    #lb-scope .msg-row.items-end .lb2,
    #lb-scope .msg-row.text-right .lb2{ align-self:flex-end !important; }
  </style>
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  {{-- =========================================================
       4) Other styles (SweetAlert theme, page-level pushes)
      ========================================================= --}}

  {{-- === Notification popover z-index fix === --}}
  <style id="nb-popover-z">
    /* Create a high stacking context for the bell and its popover */
    [data-nb-root]{ position:relative; z-index:2147483641; }
    /* If the bell renders a portal/popover container, ensure it stacks above header */
    .nb-portal, [data-nb-portal], #nb-portal{ position:absolute; z-index:2147483642 !important; }
  </style>

  @stack('styles')

  <style id="lumi-modal-zfix">
    .modal-z  { z-index: 2147483646 !important; }
    .modal-zp { z-index: 2147483647 !important; }
  </style>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
  <script>
    if (localStorage.getItem('lumichat_dark') === '1') {
      document.body.classList.add('dark-theme');
    }
  </script>
  <div class="layout-wrapper">
    {{-- ============================= SIDEBAR ============================= --}}
    <aside id="sidebar" class="sidebar-shell">
      <div class="sidebar-hdr flex items-center justify-between px-4 flex-shrink-0" style="height: var(--app-header-h);">
        <div class="sidebar-hdr-logo flex items-center gap-2.5 min-w-0">
          <img src="{{ asset('images/chatbot.png') }}" alt="Logo" class="w-6 h-6 flex-shrink-0">
          <div class="sidebar-brand-lockup min-w-0">
            <span class="sidebar-brand truncate">
              <span class="sidebar-brand-lumi">Lumi</span><span class="sidebar-brand-chat">CHAT</span>
            </span>
          </div>
        </div>
        <button id="sidebar-close" class="sidebar-x flex-shrink-0" title="Toggle sidebar" aria-label="Toggle sidebar">
          {{-- X: shown when sidebar is open --}}
          <svg class="icon-collapse" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
          {{-- Hamburger: shown when sidebar is collapsed --}}
          <svg class="icon-expand" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="3" y1="6"  x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
      </div>

      @php
        $sbInitials = '';
        $sbName = '';
        $sbSis = '';
        if (Auth::check()) {
            $parts = preg_split('/\s+/', trim(Auth::user()->name ?? ''));
            $sbInitials = strtoupper(collect($parts)->take(2)->map(fn($s) => mb_substr($s, 0, 1))->implode(''));
            $sbName = Auth::user()->name ?? '';
            $sbSis = Auth::user()->sis ?? '';
        }
      @endphp

      @php
        $mainLinks = [
          ['label' => 'Home',          'route' => 'chat.index',                 'icon' => 'home.png'],
          ['label' => 'Announcements', 'route' => 'student.announcements',       'icon' => 'alert.png'],
          ['label' => 'Profile',       'route' => 'profile.edit',               'icon' => 'user.png'],
          ['label' => 'Appointment', 'route' => 'appointment.index',          'icon' => 'appointment.png'],
          ['label' => 'Chat History','route' => Route::has('chat.history') ? 'chat.history' : null, 'icon' => 'chat-history.png'],
          ['label' => 'Settings',    'route' => Route::has('settings.index') ? 'settings.index' : null, 'icon' => 'settings.png'],
          ['label' => 'About',       'route' => 'about.index',                'icon' => 'about.png'],
        ];
      @endphp

      <nav class="flex-1 px-2.5 pt-3 space-y-4 overflow-y-auto" id="railScroll">
        <div>
          <ul class="space-y-1">
            @foreach ($mainLinks as $item)
              @if ($item['label'] === 'Appointment')
                @php
                  $showAppointment = (bool) ($appointmentEnabled ?? false);
                  $hasAppointments = (bool) ($hasAppointments ?? false);
                  $apptLabel       = $hasAppointments ? 'Appointment History' : 'Appointment';
                  $apptRoute       = $hasAppointments ? route('appointment.history') : route('appointment.index');
                  $apptIsActive    = request()->routeIs('appointment.*');

                  $apptUnseen = 0;
                  if ($showAppointment && Auth::check()) {
                      $last = Auth::user()->last_seen_appt_at ?? \Carbon\Carbon::createFromTimestamp(0);
                      $apptUnseen = \DB::table('tbl_appointments')
                          ->where('student_id', Auth::id())
                          ->where('updated_at', '>', $last)
                          ->count();
                  }
                @endphp

                @if ($showAppointment)
                  <li>
                    <a href="{{ $apptRoute }}"
                       @class(['nav-item', 'nav-item--active' => $apptIsActive, 'relative' => true])
                       id="nav-appointment-link"
                       data-tip="{{ $apptLabel }}">
                      <img src="{{ asset('images/icons/appointment.png') }}" alt="" class="sidebar-icon icon-white">
                      <span class="nav-item-label">{{ $apptLabel }}</span>
                    </a>
                  </li>
                @endif

                @continue
              @endif

              @php
                $href = $item['route'] && is_string($item['route']) ? route($item['route']) : '#';
                $isActive = $item['route'] && is_string($item['route']) ? request()->routeIs($item['route']) : false;

                /* IDs for the interactive tour */
                $extraId = match($item['label']) {
                  'Chat History' => 'nav-chat-history',
                  'Settings'     => 'nav-settings',
                  default        => null
                };
              @endphp
              <li>
                <a href="{{ $href }}"
                   @class([
                     'nav-item',
                     'nav-item--active' => $isActive,
                     'opacity-100' => $item['route'] && is_string($item['route']),
                     'opacity-70 cursor-not-allowed' => !$item['route'] || !is_string($item['route']),
                   ])
                   @if($extraId) id="{{ $extraId }}" @endif
                   data-tip="{{ $item['label'] }}">
                  <img src="{{ asset('images/icons/' . $item['icon']) }}" alt="" class="sidebar-icon icon-white">
                  <span class="nav-item-label">{{ $item['label'] }}</span>
                  @if($item['label'] === 'Announcements' && Auth::check())
                    @php
                      $lastSeen = Auth::user()->last_seen_announcement_at ?? \Carbon\Carbon::createFromTimestamp(0);
                      $newCount = \App\Models\Announcement::active()->where('created_at', '>', $lastSeen)->count();
                    @endphp
                    @if($newCount > 0)
                      <span class="ml-auto w-2 h-2 rounded-full bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0.6)]"></span>
                    @endif
                  @endif
                </a>
              </li>
            @endforeach
          </ul>

          {{-- Session CTA --}}
          <div class="sidebar-newchat-wrap pt-3">
            <p class="section-label mb-2">QUICK START</p>
            <a href="{{ route('chat.new') }}" class="nav-pill w-full" data-new-chat="1" data-tip="Start a Session">
              <img src="{{ asset('images/icons/new-chat.png') }}" alt="" class="sidebar-icon sidebar-icon--cta">
              <span class="nav-item-label font-medium">Start New Session</span>
            </a>
          </div>
        </div>
      </nav>

      {{-- Profile card footer --}}
      <div class="sidebar-profile-card">
        <div class="sidebar-user-avatar">{{ $sbInitials ?: 'U' }}<span class="sidebar-avatar-dot"></span></div>
        <div class="sidebar-user-info">
          <span class="sidebar-user-name">{{ $sbName }}</span>
          <span class="sidebar-user-role">{{ $sbSis ?: 'Student' }}</span>
        </div>
      </div>
    </aside>

    {{-- Mobile scrim backdrop --}}
    <div id="sidebar-scrim"></div>

{{-- ============================ MAIN CONTENT ============================ --}}
@php
  use Illuminate\Support\Str;
  $yieldHeading = trim($__env->yieldContent('page_title'));
  $routeName    = Route::currentRouteName();
  $autoTitle    = '';
  if (!$yieldHeading && $routeName) {
    $autoTitle = Str::of($routeName)->replace(['.', '_'], ' ')->title();
    $autoTitle = Str::of($autoTitle)->replace(['Index', 'Show'], '')->trim();
  }
  $pageTitle = $yieldHeading ?: ($autoTitle ?: 'LumiCHAT');

  $initials = '';
  if (Auth::check()) {
    $parts = preg_split('/\s+/', trim(Auth::user()->name ?? ''));
    $initials = strtoupper(collect($parts)->take(2)->map(fn($s)=>mb_substr($s,0,1))->implode(''));
  }
@endphp

<div class="main-content">
  <header class="header-shell">
    <div class="header-inner flex items-center justify-between overflow-visible">
      {{-- LEFT --}}
      <div class="flex items-center gap-3">
        <button id="sidebar-open" class="hamburger-btn header-only" aria-label="Open sidebar">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="4" y1="6"  x2="20" y2="6"/>
            <line x1="4" y1="12" x2="20" y2="12"/>
            <line x1="4" y1="18" x2="20" y2="18"/>
          </svg>
        </button>

        <h1 class="text-lg sm:text-xl font-semibold tracking-tight text-gray-900 dark:text-white">
          {{ $pageTitle }}
        </h1>
        @if(request()->routeIs('chat.index'))
          <span class="hidden sm:inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">
            Live
          </span>
        @endif
      </div>

      {{-- RIGHT --}}
      <div class="flex items-center gap-3">
        {{-- Dark / light toggle --}}
        <button id="theme-toggle" type="button" aria-label="Toggle theme"
                class="inline-flex items-center justify-center h-10 w-10 rounded-xl border border-gray-200
                       dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50
                       dark:hover:bg-gray-800 transition">
          <svg class="inline dark:hidden w-5 h-5 text-gray-600" viewBox="0 0 24 24" fill="currentColor">
            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
          </svg>
          <svg class="hidden dark:inline w-5 h-5 text-amber-400" viewBox="0 0 24 24" fill="currentColor">
            <path d="M6.76 4.84l-1.8-1.79L3.18 4.84l1.79 1.79 1.79-1.79zM1 13h3v-2H1v2zm10 10h2v-3h-2v3zm9-10v-2h-3v2h3zm-3.76 6.16l1.79 1.79 1.78-1.79-1.78-1.79-1.79 1.79zM12 7a5 5 0 100 10 5 5 0 000-10zm6.24-2.16l1.79-1.79-1.79-1.79-1.79 1.79 1.79 1.79zM4.24 17.16L2.45 18.95l1.79 1.79 1.79-1.79-1.79-1.79z"/>
          </svg>
        </button>

        {{-- Notification bell (wrapped to create a high z-index root) --}}
        @auth
          <div data-nb-root class="relative z-[2147483641]">
            <x-notification-bell class="ml-2" />
          </div>
        @endauth

        {{-- User chip + menu --}}
        <div class="relative">
          <button id="user-btn" type="button"
            class="inline-flex items-center gap-3 h-11 px-2.5 rounded-xl border border-gray-200
                   dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50
                   dark:hover:bg-gray-800 transition">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br
                        from-indigo-500 to-violet-600 text-white text-xs font-bold">
              {{ $initials ?: 'U' }}
            </div>
            <div class="hidden sm:flex flex-col text-left leading-tight min-w-0">
              <span class="text-[11px] font-medium uppercase tracking-[0.12em] text-gray-400 dark:text-gray-500">
                Welcome
              </span>
              <span class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[11rem]">
                @auth {{ Auth::user()->name }} @endauth
              </span>
            </div>
            <svg class="hidden sm:block w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>

          <div id="user-menu" class="dropdown hidden">
            <a href="{{ route('profile.edit') }}" class="dropdown-item">
              <span class="dropdown-item-icon" aria-hidden="true">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21a8 8 0 10-16 0" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
              </span>
              <span>Profile</span>
            </a>
            @if(Route::has('settings.index'))
              <a href="{{ route('settings.index') }}" class="dropdown-item">
                <span class="dropdown-item-icon" aria-hidden="true">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3" />
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 005 15.4a1.65 1.65 0 00-1.51-1H3.4a2 2 0 110-4h.09A1.65 1.65 0 005 8.89a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009.11 5c.2-.81.93-1.39 1.76-1.4h.26a2 2 0 114 0h.09c.83.01 1.56.59 1.76 1.4a1.65 1.65 0 001.51 1 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019 10.11c.81.2 1.39.93 1.4 1.76v.26a2 2 0 110 4h-.09c-.83.01-1.56.59-1.76 1.4z" />
                </svg>
              </span>
              <span>Settings</span>
            </a>
            @endif
            <div class="dropdown-sep"></div>
            <form method="POST" action="{{ route('logout') }}" data-lumi-logout="1">
              @csrf
              <button type="submit" class="dropdown-item dropdown-item--danger">
                <span class="dropdown-item-icon" aria-hidden="true">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                    <path d="M16 17l5-5-5-5" />
                    <path d="M21 12H9" />
                  </svg>
                </span>
                <span>Logout</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </header>

  {{-- The actual page content goes here --}}
  <main class="panel-scroll">
    @yield('content')
  </main>
</div>

  {{-- ============================ Minimal JS ============================ --}}
  <script>
    // Sidebar toggle (remembers state on desktop, always closed on mobile)
    (function(){
      const body = document.body;
      const openBtn = document.getElementById('sidebar-open');
      const closeBtn = document.getElementById('sidebar-close');
      const sidebar = document.getElementById('sidebar');
      const scrim = document.getElementById('sidebar-scrim');
      const isMobile = () => window.innerWidth < 1024;

      // On mobile always start closed; on desktop restore from localStorage
      const stored = localStorage.getItem('sidebarHidden') === 'true';
      body.classList.toggle('sidebar-hidden', isMobile() ? true : stored);

      const setScrim = (open) => {
        if (!scrim) return;
        if (open && isMobile()) {
          scrim.classList.add('active');
          body.style.overflow = 'hidden';
        } else {
          scrim.classList.remove('active');
          body.style.overflow = '';
        }
      };

      // Sync scrim with initial state
      setScrim(!body.classList.contains('sidebar-hidden'));

      const toggle = () => {
        body.classList.toggle('sidebar-hidden');
        const isHidden = body.classList.contains('sidebar-hidden');
        if (!isMobile()) localStorage.setItem('sidebarHidden', isHidden);
        setScrim(!isHidden);
      };

      openBtn?.addEventListener('click', toggle);
      closeBtn?.addEventListener('click', toggle);
      scrim?.addEventListener('click', toggle);

      // Close on outside click (mobile)
      document.addEventListener('click', (e) => {
        if (!isMobile()) return;
        if (!sidebar.contains(e.target) && !openBtn?.contains(e.target)) {
          if (!body.classList.contains('sidebar-hidden')) toggle();
        }
      });

      // Re-evaluate on resize
      window.addEventListener('resize', () => {
        if (!isMobile()) {
          scrim?.classList.remove('active');
          body.style.overflow = '';
        }
      });
    })();

    // Theme toggle
    (function(){
      const btn = document.getElementById('theme-toggle');
      btn?.addEventListener('click', () => {
        const html = document.documentElement;
        const body = document.body;
        const isDark = html.classList.toggle('dark');
        html.classList.toggle('dark-theme', isDark);
        body.classList.toggle('dark-theme', isDark);
        html.setAttribute('data-coreui-theme', isDark ? 'dark' : 'light');
        html.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
        localStorage.setItem('lumichat_dark', isDark ? '1' : '0');
      });
    })();

    // User menu
    (function(){
      const btn = document.getElementById('user-btn');
      const menu = document.getElementById('user-menu');
      const close = () => menu?.classList.add('hidden');
      const toggle = () => menu?.classList.toggle('hidden');

      btn?.addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
      document.addEventListener('click', (e) => {
        if (!menu?.contains(e.target) && !btn?.contains(e.target)) close();
      });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    })();

    // Clear auto-welcome on "New Chat"
    (function(){
      function clearWelcomeOnNewChat(){
        try {
          const wrap = document.querySelector('#chat-wrapper');
          const threadId = (wrap && wrap.dataset.threadId) || location.pathname;
          sessionStorage.removeItem(`lumi_welcome_${threadId}`);
          sessionStorage.removeItem('lumi_welcome');
        } catch(_) {}
      }
      document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-new-chat="1"]').forEach(el => {
          el.addEventListener('click', clearWelcomeOnNewChat, { capture: true });
        });
      });
    })();

    // Calendar .ics utility + booked modal
    (function () {
      function downloadICS({ title, description = '', location = '', startISO, endISO }) {
        const pad = s => s.replace(/[-:]/g,'').replace(/\.\d{3}Z$/,'Z');
        const dtStart = pad(new Date(startISO).toISOString());
        const dtEnd   = pad(new Date(endISO).toISOString());
        const body = [
          'BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//LumiCHAT//Appointments//EN','BEGIN:VEVENT',
          `UID:${(crypto && crypto.randomUUID ? crypto.randomUUID() : Date.now())}@lumichat.local`,
          `DTSTAMP:${pad(new Date().toISOString())}`,
          `DTSTART:${dtStart}`,`DTEND:${dtEnd}`,
          `SUMMARY:${title}`,
          `DESCRIPTION:${(description || '').replace(/\n/g,'\\n')}`,
          `LOCATION:${location || ''}`,
          'END:VEVENT','END:VCALENDAR'
        ].join('\r\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([body], { type:'text/calendar;charset=utf-8' }));
        a.download = 'LumiCHAT-appointment.ics';
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 4000);
      }

      window.showBookedModal = function ({ counselor, dateLabel, timeLabel, startISO, endISO, historyUrl }) {
        const check = `
          <div class="lumi-check-lg">
            <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true">
              <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(16,185,129,.4)" stroke-width="2"></circle>
              <path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="rgb(16,185,129)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </div>`;
        Swal.fire({
          width: 640,
          title: 'Appointment booked!',
          html: `
            ${check}
            <div class="lumi-divider"></div>
            <div class="lumi-meta">
              <div><b>Counselor:</b> ${counselor}</div>
              <div><b>Date:</b> ${dateLabel}</div>
              <div><b>Time:</b> ${timeLabel}</div>
            </div>`,
          showConfirmButton: true,
          confirmButtonText: 'OK',
          showDenyButton: true,
          denyButtonText: 'Add to calendar',
          showCancelButton: true,
          cancelButtonText: 'View history',
          reverseButtons: true
        }).then(res => {
          if (res.isDenied) {
            downloadICS({
              title:`Counseling with ${counselor}`,
              description:`LumiCHAT counseling appointment with ${counselor}.`,
              location:'Counseling Office · LumiCHAT',
              startISO, endISO
            });
          } else if (res.dismiss === Swal.DismissReason.cancel && historyUrl) {
            location.href = historyUrl;
          }
        });
      };
    })();

    // Premium logout confirmation (sidebar + user menu)
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

          Swal.fire({
            title: 'Sign out of LumiCHAT?',
            html: `
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

    // Compact toast helper
    window.lumiToast = (title, icon = 'success', ms = 2200) => {
      return Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: ms,
        timerProgressBar: true,
        icon,
        title,
        backdrop: false
      });
    };
  </script>

  @include('profile.partials.alerts')

  {{-- Fixed Credit Footer --}}
  <footer class="fixed bottom-4 right-[72px] pointer-events-none z-[100] hidden lg:block">
    <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500/60 dark:text-slate-400/40 select-none">
      Developed by <span class="text-indigo-600/60 dark:text-indigo-400/50">Team Negatron</span> <span class="mx-1 opacity-40">&bull;</span> TCC 2026
    </div>
  </footer>

  @stack('scripts')

  @if (session('swal'))
    <script>
      window.addEventListener('DOMContentLoaded', () => {
        Swal.fire(@json(session('swal')));
      });
    </script>
  @endif
</body>
</html>
