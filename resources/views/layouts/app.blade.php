<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <title>{{ trim($__env->yieldContent('title')) ?: 'LumiCHAT' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

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
      font-display:swap;   /* was block/optional → can cause jank */
    }
    /* Gentle fallback bg before CSS loads */
    html{ background:#f9fafb; color:#111827; }
    html.dark{ background:#111827; color:#e5e7eb; }
    [x-cloak]{ display:none !important; }
  </style>

  {{-- Keep Poppins via Google; Inter is self-hosted above. Use display=optional to avoid swaps. --}}
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

          // Dark
          const dark = get('lumichat_dark');
          const wantsDark = dark === '1' || (!dark && matchMedia('(prefers-color-scheme: dark)').matches);
          root.classList.toggle('dark', !!wantsDark);

          // Reduce motion
          root.classList.toggle('reduce-motion', get('lumichat_reduce_motion') === '1');

          // Font size
          const fs = get('lumichat_font_size') || 'md';
          root.classList.add('font-' + (['sm','md','lg'].includes(fs) ? fs : 'md'));

          // Compact
          root.classList.toggle('compact', get('lumichat_compact') === '1');
        } catch(_) {}
      })();
    </script>
  @else
    <script>
      try{
        const pref = localStorage.getItem('lumichat_dark');
        const wantsDark = pref === '1' || (!pref && matchMedia('(prefers-color-scheme: dark)').matches);
        if (wantsDark) document.documentElement.classList.add('dark');
      }catch(_){}
    </script>
  @endif

  {{-- =========================================================
       3) Vite bundles (Tailwind, app JS, chat helpers)
      ========================================================= --}}
<style id="critical-chat-lock">
  :root{ --bubble-w: 620px; }

  /* Legacy bubbles (exclude .lb2) */
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

  /* v2 shrink-to-content bubbles */
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
  {{-- Style overrides for SweetAlert look --}}
  <style id="lumi-swal-theme">
    .swal2-container.swal2-backdrop-show{
      background:rgba(15,23,42,.55)!important; backdrop-filter:blur(4px) saturate(110%);
    }
    .swal2-container.swal2-top-start,
    .swal2-container.swal2-top,
    .swal2-container.swal2-top-end,
    .swal2-container.swal2-bottom-start,
    .swal2-container.swal2-bottom,
    .swal2-container.swal2-bottom-end{
      background:transparent!important; backdrop-filter:none!important; pointer-events:none!important; z-index:2147483000!important;
    }
    .swal2-container .swal2-popup{ pointer-events:auto!important; }
    .swal2-container.swal2-top-end{ padding-top:max(12px, env(safe-area-inset-top))!important; padding-right:max(12px, env(safe-area-inset-right))!important; padding-bottom:12px!important; padding-left:12px!important; }

    .swal2-popup:not(.swal2-toast){
      background:#fff!important; border-radius:22px!important; padding:28px 32px!important;
      box-shadow:0 40px 80px -20px rgba(2,6,23,.35), 0 0 0 1px rgba(2,6,23,.05), 0 30px 60px rgba(109,40,217,.08)!important;
      max-width:680px;
    }
    .dark .swal2-popup:not(.swal2-toast){ background:rgba(17,24,39,.96)!important; color:#e5e7eb!important; }
    .swal2-popup:not(.swal2-toast) .swal2-title{
      margin:12px 0 0!important; font-weight:700; font-size:26px!important; letter-spacing:.2px; text-align:center; color:#0f172a;
    }
    .dark .swal2-popup:not(.swal2-toast) .swal2-title{ color:#f8fafc; }
    .swal2-popup:not(.swal2-toast) .swal2-html-container{ margin-top:6px!important; font-size:15px!important; color:#475569!important; }
    .dark .swal2-popup:not(.swal2-toast) .swal2-html-container{ color:#cbd5e1!important; }
    .swal2-popup:not(.swal2-toast) .swal2-actions{ margin-top:22px!important; gap:10px; flex-wrap:wrap; }

    .swal2-styled{ border-radius:14px!important; padding:10px 18px!important; font-weight:700!important; box-shadow:none!important; }
    .swal2-confirm{
      background:linear-gradient(90deg,#7c3aed,#6366f1)!important; color:#fff!important; box-shadow:0 10px 24px rgba(99,102,241,.35)!important;
    }
    .swal2-cancel,.swal2-deny{ background:#fff!important; color:#334155!important; border:1px solid #e5e7eb!important; }
    .dark .swal2-cancel,.dark .swal2-deny{ background:#1f2937!important; color:#e5e7eb!important; border-color:#334155!important; }

    .lumi-check-lg{
      width:90px;height:90px;margin:.25rem auto 0;border-radius:999px;display:grid;place-items:center;
      background:rgba(16,185,129,.10);border:3px solid rgba(16,185,129,.25);
    }
    .lumi-divider{ height:1px;background:linear-gradient(90deg,transparent,rgba(148,163,184,.4),transparent); margin:.75rem 0 1rem; }
    .lumi-meta{ display:grid; gap:.35rem; font-size:.95rem; max-width:420px; margin:0 auto; text-align:left; }
    .lumi-meta b{ color:#0f172a; } .dark .lumi-meta b{ color:#f1f5f9; }

    .swal2-popup.swal2-toast{
      display:flex!important; align-items:center!important; gap:.55rem!important; border-radius:14px!important; padding:.65rem .9rem!important; min-height:44px!important;
      background:#fff!important; box-shadow:0 12px 28px rgba(2,6,23,.18); width:auto!important; max-width:360px!important;
    }
    .dark .swal2-popup.swal2-toast{ background:#111827!important; color:#e5e7eb!important; }
    .swal2-popup.swal2-toast .swal2-icon{ margin:0!important; width:22px!important; height:22px!important; min-width:22px!important; display:flex!important; align-items:center!important; justify-content:center!important; }
    .swal2-popup.swal2-toast .swal2-icon .swal2-icon-content{ display:flex; align-items:center; justify-content:center; }
    .swal2-popup.swal2-toast .swal2-title{ margin:0!important; padding:0!important; font-size:14px!important; font-weight:700!important; line-height:1.2!important; display:flex; align-items:center; }
  </style>

  {{-- Let pages push extra CSS (e.g., chat page bubble tweaks) --}}
  @stack('styles')

  {{-- Ensure teleported modals sit above header blur --}}
  <style id="lumi-modal-zfix">
    .modal-z  { z-index: 2147483646 !important; } /* backdrop */
    .modal-zp { z-index: 2147483647 !important; } /* dialog */
  </style>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
  <div class="layout-wrapper">
    {{-- ============================= SIDEBAR ============================= --}}
    <aside id="sidebar" class="sidebar-shell">
      <div class="flex items-center justify-between h-16 px-4 border-b border-r border-white/10 relative">
        <div class="flex items-center gap-2">
          <img src="{{ asset('images/chatbot.png') }}" alt="Logo" class="w-7 h-7">
          <span class="text-lg font-semibold bg-gradient-to-r from-indigo-100 to-violet-200 bg-clip-text text-transparent">LumiCHAT</span>
        </div>
        <button id="sidebar-close" class="sidebar-x" title="Close sidebar" aria-label="Close sidebar">✕</button>
      </div>

      @php
        $mainLinks = [
          ['label' => 'Home',        'route' => 'chat.index',                 'icon' => 'home.png'],
          ['label' => 'Profile',     'route' => 'profile.edit',               'icon' => 'user.png'],
          ['label' => 'Appointment', 'route' => 'appointment.index',          'icon' => 'appointment.png'],
          ['label' => 'Chat History','route' => Route::has('chat.history') ? 'chat.history' : null, 'icon' => 'chat-history.png'],
          ['label' => 'Settings',    'route' => Route::has('settings.index') ? 'settings.index' : null, 'icon' => 'settings.png'],
        ];
      @endphp

      <nav class="flex-1 px-3 pt-5 space-y-5 overflow-y-auto" id="railScroll">
        <div>
          <p class="section-label">MAIN</p>
          <ul class="space-y-2">
            @foreach ($mainLinks as $item)
             @if ($item['label'] === 'Appointment')
  @php
    // Defaults so nothing is "undefined"
    $showAppointment = (bool) ($appointmentEnabled ?? false);
    $hasAppointments = (bool) ($hasAppointments ?? false);
    $apptLabel       = $hasAppointments ? 'Appointment History' : 'Appointment';
    $apptRoute       = $hasAppointments ? route('appointment.history') : route('appointment.index');
    $apptIsActive    = request()->routeIs('appointment.*');

    // Compute unseen updates (only if student + feature visible)
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
         id="nav-appointment-link">
        <img src="{{ asset('images/icons/appointment.png') }}" alt="" class="sidebar-icon icon-white">
        <span>{{ $apptLabel }}</span>

        {{-- red dot badge --}}
        <span id="nav-appt-dot"
              class="absolute left-6 top-2 block w-2 h-2 rounded-full bg-rose-500 ring-2 ring-rose-100 {{ $apptUnseen ? '' : 'hidden' }}"
              title="New appointment updates"></span>
      </a>
    </li>
  @endif

  @continue
@endif


              @php
                $href = $item['route'] && is_string($item['route']) ? route($item['route']) : '#';
                $isActive = $item['route'] && is_string($item['route']) ? request()->routeIs($item['route']) : false;
              @endphp
              <li>
                <a href="{{ $href }}"
                   @class([
                     'nav-item',
                     'nav-item--active' => $isActive,
                     'opacity-100' => $item['route'] && is_string($item['route']),
                     'opacity-70 cursor-not-allowed' => !$item['route'] || !is_string($item['route']),
                   ])>
                  <img src="{{ asset('images/icons/' . $item['icon']) }}" alt="" class="sidebar-icon icon-white">
                  <span>{{ $item['label'] }}</span>
                </a>
              </li>
            @endforeach
          </ul>
        </div>

        <div>
          <p class="section-label">TOOLS</p>
          <a href="{{ route('chat.new') }}" class="nav-pill" data-new-chat="1">
            <img src="{{ asset('images/icons/new-chat.png') }}" alt="" class="sidebar-icon icon-white">
            <span class="font-medium">New Chat</span>
          </a>
        </div>
      </nav>

      <div class="px-3 py-4 border-t border-white/10 mt-auto">
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="nav-pill nav-pill--danger w-full">
            <img src="{{ asset('images/icons/logout.png') }}" alt="" class="sidebar-icon logout-icon">
            <span class="font-medium">Logout</span>
          </button>
        </form>
      </div>
    </aside>

    {{-- ============================ MAIN CONTENT ============================ --}}
    @php
      use Illuminate\Support\Str;
      $yieldTitle = trim($__env->yieldContent('title'));
      $routeName  = Route::currentRouteName();
      $autoTitle  = '';
      if (!$yieldTitle && $routeName) {
        $autoTitle = Str::of($routeName)->replace(['.', '_'], ' ')->title();
        $autoTitle = Str::of($autoTitle)->replace(['Index', 'Show'], '')->trim();
      }
      $pageTitle = $yieldTitle ?: ($autoTitle ?: 'LumiCHAT');

      $initials = '';
      if (Auth::check()) {
        $parts = preg_split('/\s+/', trim(Auth::user()->name ?? ''));
        $initials = strtoupper(collect($parts)->take(2)->map(fn($s)=>mb_substr($s,0,1))->implode(''));
      }
    @endphp

    <div class="main-content">
      <header class="header-shell">
        <div class="header-inner flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button id="sidebar-open" class="hamburger-btn header-only" aria-label="Open sidebar">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>
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

          <div class="flex items-center gap-2 sm:gap-3">
            <a href="{{ route('chat.new') }}"
               class="header-newchat inline-flex items-center gap-2 h-10 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500/60"
               aria-label="Start a new chat"
               data-new-chat="1">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              <span class="hidden sm:inline text-sm font-medium">New Chat</span>
            </a>

            <button id="theme-toggle" type="button" aria-label="Toggle theme"
                    class="inline-flex items-center justify-center h-10 w-10 rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
              <svg class="inline dark:hidden w-5 h-5 text-gray-600" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
              </svg>
              <svg class="hidden dark:inline w-5 h-5 text-amber-400" viewBox="0 0 24 24" fill="currentColor">
                <path d="M6.76 4.84l-1.8-1.79L3.18 4.84l1.79 1.79 1.79-1.79zM1 13h3v-2H1v2zm10 10h2v-3h-2v3zm9-10v-2h-3v2h3zm-3.76 6.16l1.79 1.79 1.78-1.79-1.78-1.79-1.79 1.79zM12 7a5 5 0 100 10 5 5 0 000-10zm6.24-2.16l1.79-1.79-1.79-1.79-1.79 1.79 1.79 1.79zM4.24 17.16L2.45 18.95l1.79 1.79 1.79-1.79-1.79-1.79z"/>
              </svg>
            </button>

            <div class="relative">
              <button id="user-btn" type="button"
                      class="inline-flex items-center gap-2 h-10 px-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-xs font-bold">
                  {{ $initials ?: 'U' }}
                </div>
                <div class="hidden sm:flex flex-col text-left leading-tight mr-1">
                  <span class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[8rem]">
                    @auth {{ Auth::user()->name }} @endauth
                  </span>
                  <span class="text-[11px] text-gray-500 dark:text-gray-400">Student</span>
                </div>
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                </svg>
              </button>

              <div id="user-menu" class="dropdown">
                <a href="{{ route('profile.edit') }}" class="dropdown-item">Profile</a>
                @if(Route::has('settings.index'))
                  <a href="{{ route('settings.index') }}" class="dropdown-item">Settings</a>
                @endif
                <div class="dropdown-sep"></div>
                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button type="submit" class="dropdown-item text-rose-600">Logout</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </header>

      <div class="panel-scroll">
        @yield('content')
      </div>
    </div>
  </div>

  {{-- ============================ Minimal JS ============================ --}}
  <script>
    // Sidebar toggle (remembers state)
    (function(){
      const body = document.body;
      const openBtn = document.getElementById('sidebar-open');
      const closeBtn = document.getElementById('sidebar-close');
      const sidebar = document.getElementById('sidebar');

      const hidden = localStorage.getItem('sidebarHidden') === 'true';
      body.classList.toggle('sidebar-hidden', hidden);

      const toggle = () => {
        body.classList.toggle('sidebar-hidden');
        localStorage.setItem('sidebarHidden', body.classList.contains('sidebar-hidden'));
      };

      openBtn?.addEventListener('click', toggle);
      closeBtn?.addEventListener('click', toggle);

      document.addEventListener('click', (e) => {
        if (innerWidth >= 1024) return;
        if (!sidebar.contains(e.target) && !openBtn.contains(e.target)) {
          if (!body.classList.contains('sidebar-hidden')) toggle();
        }
      });
    })();

    // Theme toggle
    (function(){
      const btn = document.getElementById('theme-toggle');
      btn?.addEventListener('click', () => {
        const html = document.documentElement;
        const isDark = html.classList.toggle('dark');
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
          sessionStorage.removeItem('lumi_welcome'); // legacy
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

  {{-- Global alerts --}}
  @include('profile.partials.alerts')

  {{-- Page-level scripts (e.g., chat page inline fallback) --}}
  @stack('scripts')

  {{-- Session-driven SweetAlert --}}
  @if (session('swal'))
    <script>
      window.addEventListener('DOMContentLoaded', () => {
        Swal.fire(@json(session('swal')));
      });
    </script>
  @endif
</body>
</html>
