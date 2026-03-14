{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ trim($__env->yieldContent('title')) ?: 'Admin • Dashboard' }}</title>
  <!-- CSRF for AJAX (bell + index page mark-read) -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- SweetAlert2 (same version as student side) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js" defer></script>

  @vite(['resources/css/app.css','resources/js/app.js'])
  @include('layouts.partials.favicons')

  <style>
    :root{
      --z-modal: 1100;
      --z-backdrop: 1099;
    }
    html, body { height: 100%; }
    body{ -webkit-tap-highlight-color: transparent; overflow-x: hidden; }
    .no-scroll{ overflow: hidden; }

    .modal-zp{ position: fixed; inset: 0; z-index: var(--z-modal); }
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
  </style>

  {{-- === SweetAlert global theme (same as student) === --}}
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

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
@php
  $adminInitials = '';
  if (Auth::check()) {
    $parts = preg_split('/\s+/', trim(Auth::user()->name ?? ''));
    $adminInitials = strtoupper(collect($parts)->take(2)->map(fn($s)=>mb_substr($s,0,1))->implode(''));
  }
  $displayName = auth()->user()->name ?? 'Master Admin';
@endphp

<div class="layout-wrapper">
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
        <svg class="icon-collapse" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
        <svg class="icon-expand" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="3" y1="6"  x2="21" y2="6"/>
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
    </div>

    <nav class="flex-1 px-2.5 pt-3 space-y-4 overflow-y-auto" id="railScroll">
      <div>
        <p class="section-label mb-2">MAIN</p>
        <ul class="space-y-1">
          <li>
            <a href="{{ route('admin.dashboard') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.dashboard')]) data-tip="Dashboard Overview">
              <img src="{{ asset('images/icons/home.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Dashboard Overview</span>
            </a>
          </li>
          <li>
            <a href="{{ route('admin.counselors.index') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.counselors.*')]) data-tip="Counselor">
              <img src="{{ asset('images/icons/counselor.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Counselor</span>
            </a>
          </li>
        </ul>

        <p class="section-label mt-4 mb-2">STUDENT MANAGEMENT</p>
        <ul class="space-y-1">
          <li>
            <a href="{{ route('admin.students.index') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.students.*')]) data-tip="Student Records">
              <img src="{{ asset('images/icons/user.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Student Records</span>
            </a>
          </li>
          <li>
            <a href="{{ route('admin.account-requests.index') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.account-requests.*')]) data-tip="Account Requests">
              <img src="{{ asset('images/icons/mail.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Account Requests</span>
            </a>
          </li>
          <li>
            <a href="{{ route('admin.appointments.index') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.appointments.*')]) data-tip="Appointments">
              <img src="{{ asset('images/icons/appointment.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Appointments</span>
            </a>
          </li>
        </ul>

        <p class="section-label mt-4 mb-2">REPORTS</p>
        <ul class="space-y-1">
          <li>
            <a href="{{ route('admin.counselor-logs.index') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.counselor-logs.*')]) data-tip="Counselor Logs">
              <img src="{{ asset('images/icons/logs.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Counselor Logs</span>
            </a>
          </li>
          <li>
            <a href="{{ route('admin.chatbot-sessions.index') }}" @class(['nav-item', 'nav-item--active relative' => request()->routeIs('admin.chatbot-sessions.*'), 'relative' => true]) data-tip="Chatbot Sessions">
              <img src="{{ asset('images/icons/chatbot-session.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Chatbot Sessions</span>
              @if(($adminHighRiskCount ?? 0) > 0)
                <span class="absolute -top-1 -right-1 min-w-[1.15rem] px-1 py-[1px] rounded-full text-[10px] font-semibold bg-rose-500 text-white flex items-center justify-center shadow-sm">
                  +{{ $adminHighRiskCount > 9 ? '9' : $adminHighRiskCount }}
                </span>
              @endif
            </a>
          </li>
        </ul>

        <p class="section-label mt-4 mb-2">ANALYTICS</p>
        <ul class="space-y-1">
          <li>
            <a href="{{ route('admin.case-notes.index') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.case-notes.*')]) data-tip="Case Form Summary">
              <img src="{{ asset('images/icons/casenote.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Case Form Summary</span>
            </a>
          </li>
          <li>
            <a href="{{ route('admin.course-analytics.index') }}" @class(['nav-item', 'nav-item--active' => request()->routeIs('admin.course-analytics.*')]) data-tip="Course Summary">
              <img src="{{ asset('images/icons/graduate.png') }}" alt="" class="sidebar-icon icon-white">
              <span class="nav-item-label">Course Summary</span>
            </a>
          </li>
        </ul>
      </div>
    </nav>

    <div class="sidebar-profile-card">
      <div class="sidebar-user-avatar">{{ $adminInitials ?: 'A' }}<span class="sidebar-avatar-dot"></span></div>
      <div class="sidebar-user-info">
        <span class="sidebar-user-name">{{ $displayName }}</span>
        <span class="sidebar-user-role">Admin</span>
      </div>
    </div>
  </aside>

  <div id="sidebar-scrim"></div>

  <div class="main-content">
    <header class="header-shell">
      <div class="header-inner flex items-center justify-between overflow-visible">
        <div class="flex items-center gap-3">
          <button id="sidebar-open" class="hamburger-btn header-only" aria-label="Open sidebar">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="4" y1="6"  x2="20" y2="6"/>
              <line x1="4" y1="12" x2="20" y2="12"/>
              <line x1="4" y1="18" x2="20" y2="18"/>
            </svg>
          </button>
          <h1 class="text-lg sm:text-xl font-semibold tracking-tight text-gray-900 dark:text-white">@yield('page_title','Dashboard')</h1>
        </div>

        <div class="flex items-center gap-3">
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

          @auth
            <div data-nb-root class="relative z-[2147483641]">
              <x-notification-bell
                :indexRoute="route('admin.notifications.index')"
                :feedRoute="route('admin.notifications.feed')"
                :markRoute="route('admin.notifications.mark', ['id' => ':id'])"
                :markAllRoute="route('admin.notifications.mark_all')"
              />
            </div>
          @endauth

          <div class="relative">
            <button id="user-btn" type="button"
              class="inline-flex items-center gap-3 h-11 px-2.5 rounded-xl border border-gray-200
                     dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50
                     dark:hover:bg-gray-800 transition">
              <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br
                          from-indigo-500 to-violet-600 text-white text-xs font-bold">
                {{ $adminInitials ?: 'A' }}
              </div>
              <div class="hidden sm:flex flex-col text-left leading-tight min-w-0">
                <span class="text-[11px] font-medium uppercase tracking-[0.12em] text-gray-400 dark:text-gray-500">Welcome</span>
                <span class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[11rem]">{{ $displayName }}</span>
              </div>
              <svg class="hidden sm:block w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
              </svg>
            </button>

            <div id="user-menu" class="dropdown hidden">
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

    <main class="panel-scroll">
      <div class="max-w-7xl mx-auto px-4 py-6">
        @yield('content')
      </div>
    </main>
  </div>
</div>

<script>
  // Sidebar toggle (same behavior as student)
  (function(){
    const body = document.body;
    const openBtn = document.getElementById('sidebar-open');
    const closeBtn = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('sidebar-scrim');
    const isMobile = () => window.innerWidth < 1024;

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

    document.addEventListener('click', (e) => {
      if (!isMobile()) return;
      if (!sidebar.contains(e.target) && !openBtn?.contains(e.target)) {
        if (!body.classList.contains('sidebar-hidden')) toggle();
      }
    });

    window.addEventListener('resize', () => {
      if (!isMobile()) {
        scrim?.classList.remove('active');
        body.style.overflow = '';
      }
    });
  })();
</script>

<script>
  // Theme toggle
  (function(){
    const btn = document.getElementById('theme-toggle');
    btn?.addEventListener('click', () => {
      const html = document.documentElement;
      const isDark = html.classList.toggle('dark');
      localStorage.setItem('lumichat_dark', isDark ? '1' : '0');
    });
  })();
</script>

<script>
  // User menu toggle
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
</script>

{{-- ==== Wide SweetAlert “Appointment booked!” (global) ==== --}}
<style>
  /* compact success modal */
  .swal-compact .swal2-html-container{ text-align: left !important; padding: 18px 24px !important; }
  .appt-compact{ font-size: 14.5px; line-height: 1.45; max-width: 980px; margin: 0 auto; }
  .appt-compact .kv-grid{
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px 18px;
    padding: 8px 0; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; margin: 6px 0 12px;
  }
  @media (max-width: 1100px){ .appt-compact .kv-grid{ grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 520px){ .appt-compact .kv-grid{ grid-template-columns: 1fr; } }
  .appt-compact .kv{ display:flex; gap:6px; white-space:nowrap; }
  .appt-compact .kv .label{ font-weight:600; color:#111827; }
  .appt-compact .kv .value{ color:#374151; }

  .swal-wide.swal2-popup{ width: min(92vw, 760px) !important; padding: 0 !important; border-radius: 18px !important; box-shadow: 0 30px 60px rgba(2,6,23,.25); }
  .swal-wide .swal2-title{ margin: 18px 22px 0 !important; font-size: 22px !important; font-weight: 800 !important; color: #0f172a !important; }
  .swal-wide .swal2-html-container{ margin: 0 !important; padding: 16px 22px 22px !important; text-align: left !important; }
  .swal-wide .swal2-actions{ margin: 0 !important; padding: 16px 22px 22px !important; }

  .swal-field{ width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: .55rem .75rem; }
  .swal-field:focus{ outline: 0; box-shadow: 0 0 0 3px rgba(79,70,229,.25); border-color: #c7d2fe; }

  .time-grid{ display:grid; gap:.5rem; grid-template-columns:repeat(3,minmax(0,1fr)); }
  @media (min-width:640px){ .time-grid{ grid-template-columns:repeat(4,minmax(0,1fr)); } }
  .time-btn{ display:flex; flex-direction:column; align-items:center; justify-content:center;
    border:1px solid #e2e8f0; background:#fff; color:#0f172a;
    padding:.55rem .6rem; border-radius:12px; font-size:.9rem; line-height:1.1; font-weight:600;
    transition: transform .06s ease, border-color .12s ease, background .12s ease; }
  .time-btn:hover{ background:#EEF2FF; border-color:#C7D2FE; }
  .time-btn.is-active{ box-shadow:0 0 0 3px rgba(79,70,229,.35); border-color:#a5b4fc; }
  .time-btn:disabled{ opacity:.45; background:#f8fafc; cursor:not-allowed; }
  .time-cap{ margin-top:.15rem; font-size:.72rem; opacity:.75; font-weight:500; }
  .tiny-hint{ font-size:.78rem; color:#64748b; }
</style>

<script>
  async function showBookedSuccess(html){
    await Swal.fire({
      icon: 'success',
      title: 'Appointment booked!',
      html,
      customClass: { popup: 'swal-success' },
      showCloseButton: true,
      confirmButtonText: 'OK',
    });
  }

  // === Premium logout confirmation for admin (same as student) ===
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
               You’ll be signed out from the admin panel on this device. All student records, reports, and chatbot sessions will remain safely stored in LumiCHAT.
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

@stack('scripts')
</body>
</html>
