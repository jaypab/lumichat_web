{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ trim($__env->yieldContent('title')) ?: 'Admin • Dashboard' }}</title>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  @vite(['resources/css/app.css','resources/js/app.js'])
  @include('layouts.partials.favicons')
  <style>
    :root{
      --rail-expanded: 18rem;   /* 288px */
      --rail-collapsed: 84px;   /* compact width */
      --header-h: 56px;
    }
    /* Base */
    html, body { height: 100%; }
    body{
      -webkit-tap-highlight-color: transparent;
      overflow-x: hidden;                 /* never show horizontal scroll */
    }
    .no-scroll{ overflow: hidden; }

    /* Layout transitions */
    #adminSidebar{ width: var(--rail-expanded); transition: width .25s ease, transform .25s ease; }
    #adminMain{ transition: padding .25s ease; }
    @media (min-width: 1024px){
      #adminMain{ padding-left: var(--rail-expanded); }
      .admin-collapsed #adminMain{ padding-left: var(--rail-collapsed); }
    }
    .admin-collapsed #adminSidebar{ width: var(--rail-collapsed); }

    /* Rail header height parity */
    .rail-header{ height: var(--header-h); }

    /* Collapse behavior (desktop) */
    @media (min-width:1024px){
      .admin-collapsed .brand-text,
      .admin-collapsed .nav-label,
      .admin-collapsed .hide-when-collapsed{ display: none !important; }
      .admin-collapsed #railClose{ display: none !important; }  /* hide the X when collapsed */
      .admin-collapsed .nav-item{ justify-content: center; }
    }

    /* Hamburger visibility */
    #railOpen{ display:inline-flex; }
    @media (min-width:1024px){
      body:not(.admin-collapsed) #railOpen{ display:none; }   /* desktop + expanded rail => hide */
      body.admin-collapsed #railOpen{ display:inline-flex; }  /* desktop + collapsed rail => show */
    }
    body.mobile-rail-open #railOpen{ display:none; }          /* mobile overlay open => hide */

    /* Active marker on the left of item */
    .nav-item.is-active::before{
      content:""; position:absolute; left:10px; top:50%;
      transform:translateY(-50%); width:4px; height:22px; border-radius:999px;
      background:rgba(255,255,255,.92);
    }

    /* ---- Make PNG icons white (force) ---- */
    #adminSidebar nav a.nav-item > span > img{
      -webkit-filter: invert(1) brightness(1000%) saturate(0) contrast(100%) !important;
              filter: invert(1) brightness(1000%) saturate(0) contrast(100%) !important;
    }

    /* ---- Sidebar inner scroll ----
       visible when open, hidden when collapsed (desktop) */
    #railScroll{
      height: calc(100vh - var(--header-h));
      overflow-y: auto;
      overflow-x: hidden;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;                               /* Firefox */
      scrollbar-color: rgba(255,255,255,.7) transparent;
    }
    @supports (height: 100dvh){
      #railScroll{ height: calc(100dvh - var(--header-h)); }
    }
    #railScroll::-webkit-scrollbar{ width: 10px; }         /* WebKit */
    #railScroll::-webkit-scrollbar-thumb{
      background: rgba(255,255,255,.65);
      border-radius: 9999px;
      border: 2px solid rgba(255,255,255,.25);
      background-clip: padding-box;
    }
    #railScroll::-webkit-scrollbar-track{ background: transparent; }
    @media (min-width:1024px){
      .admin-collapsed #railScroll{ overflow: hidden; }     /* hide inner scroll when collapsed */
    }

    /* prevent horizontal bar inside rail */
    #adminSidebar{ overflow-x: clip; }

    /* Tooltips when collapsed (desktop) */
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
      .admin-collapsed .nav-item:hover .rail-tip{
        opacity:1; transform: translateY(50%) translateX(12px);
      }
    }
  </style>
</head>

<body class="bg-slate-50 text-slate-800 antialiased">

{{-- ===== SIDEBAR / RAIL ===== --}}
<aside id="adminSidebar"
  class="fixed inset-y-0 left-0 z-40 -translate-x-full lg:translate-x-0 text-white shadow-xl">

  {{-- Brand --}}
  <div class="rail-header px-4 flex items-center justify-between border-b border-white/20">
    <div class="flex items-center gap-2">
      <img src="{{ asset('images/chatbot.png') }}"
           class="w-9 h-9 rounded-full ring-2 ring-white/30 object-cover"
           alt="LumiCHAT">
      <span class="brand-text font-semibold tracking-wide">LumiCHAT</span>
    </div>

    {{-- X: collapse on desktop / close on mobile --}}
    <button id="railClose"
            class="p-2 rounded-md hover:bg-white/10 focus:outline-none"
            aria-label="Collapse/Close sidebar"
            title="Collapse (desktop) / Close (mobile)">
      <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  {{-- Nav --}}
  <nav class="h-[calc(100vh-var(--header-h))] flex flex-col">
    <div id="railScroll" class="px-3 py-3 grow">

      <p class="px-3 text-[11px] uppercase tracking-wider/relaxed opacity-90 nav-label">Main</p>

      {{-- Dashboard --}}
      <a href="{{ route('admin.dashboard') }}"
         aria-current="{{ request()->routeIs('admin.dashboard') ? 'page' : 'false' }}"
         class="nav-item group relative mt-2 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.dashboard') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/home.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Dashboard Overview</span>
        <span class="rail-tip">Dashboard Overview</span>
      </a>

      {{-- Counselor --}}
      <a href="{{ route('admin.counselors.index') }}"
         aria-current="{{ request()->routeIs('admin.counselors.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.counselors.*') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/counselor.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Counselor</span>
        <span class="rail-tip">Counselor</span>
      </a>

      <p class="mt-4 px-3 text-[11px] uppercase tracking-wider/relaxed opacity-90 nav-label">Student Management</p>

      {{-- Student Records --}}
      <a href="{{ route('admin.students.index') }}"
         aria-current="{{ request()->routeIs('admin.students.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.students.*') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/user.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Student Records</span>
        <span class="rail-tip">Student Records</span>
      </a>

      {{-- Appointments --}}
      <a href="{{ route('admin.appointments.index') }}"
         aria-current="{{ request()->routeIs('admin.appointments.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.appointments.*') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/appointment.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Appointments</span>
        <span class="rail-tip">Appointments</span>
      </a>

      <p class="mt-4 px-3 text-[11px] uppercase tracking-wider/relaxed opacity-90 nav-label">Reports</p>

      {{-- Counselor Logs --}}
      <a href="{{ route('admin.counselor-logs.index') }}"
         aria-current="{{ request()->routeIs('admin.counselor-logs.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.counselor-logs.*') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/logs.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Counselor Logs</span>
        <span class="rail-tip">Counselor Logs</span>
      </a>

      {{-- Chatbot Sessions --}}
      <a href="{{ route('admin.chatbot-sessions.index') }}"
         aria-current="{{ request()->routeIs('admin.chatbot-sessions.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.chatbot-sessions.*') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/chatbot-session.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Chatbot Sessions</span>
        <span class="rail-tip">Chatbot Sessions</span>
      </a>

      {{-- Diagnosis Reports --}}
      <a href="{{ route('admin.diagnosis-reports.index') }}"
         aria-current="{{ request()->routeIs('admin.diagnosis-reports.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.diagnosis-reports.*') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/diagnosis.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Diagnosis Reports</span>
        <span class="rail-tip">Diagnosis Reports</span>
      </a>

      <p class="mt-4 px-3 text-[11px] uppercase tracking-wider/relaxed opacity-90 nav-label">Analytics</p>

      {{-- Course Analytics --}}
      <a href="{{ route('admin.course-analytics.index') }}"
         aria-current="{{ request()->routeIs('admin.course-analytics.*') ? 'page' : 'false' }}"
         class="nav-item group relative mt-1.5 px-3 py-2.5 ring-1 ring-transparent
                hover:bg-white/10 hover:ring-white/10
                {{ request()->routeIs('admin.course-analytics.*') ? 'is-active bg-white/15 ring-white/10' : '' }}">
        <span class="inline-flex w-10 h-10 items-center justify-center">
          <img src="{{ asset('images/icons/graduate.png') }}" class="sidebar-icon" alt="">
        </span>
        <span class="nav-label font-medium">Course Analytics</span>
        <span class="rail-tip">Course Analytics</span>
      </a>
    </div>

    {{-- Logout — visible only when rail is expanded --}}
    <div class="px-3 py-3 border-t border-white/15 hide-when-collapsed">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="w-full text-left px-3 py-2.5 rounded-lg bg-rose-600/90 hover:bg-rose-600 text-white font-medium">
          Logout
        </button>
      </form>
    </div>
  </nav>
</aside>


  {{-- Mobile scrim --}}
  <div id="sidebarScrim" class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm hidden lg:hidden"></div>

  {{-- ===== MAIN ===== --}}
  <div id="adminMain" class="min-h-screen">
    {{-- Top bar --}}
    <header class="sticky top-0 z-20 h-[var(--header-h)] bg-white/80 backdrop-blur border-b border-slate-200">
      <div class="h-full max-w-7xl mx-auto px-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          {{-- Hamburger: expand (desktop) / open (mobile) --}}
          <button id="railOpen" class="p-2 rounded-md hover:bg-slate-100"
                  aria-label="Open sidebar" title="Open sidebar">
            <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
          <h1 class="text-lg font-semibold">@yield('page_title','Dashboard')</h1>
        </div>
        <div class="text-sm text-slate-600">{{ auth()->user()->name ?? 'Master Admin' }}</div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
      @yield('content')
    </main>
  </div>

  <script>
    (function () {
      const body      = document.body;
      const sidebar   = document.getElementById('adminSidebar');
      const scrim     = document.getElementById('sidebarScrim');
      const openBtn   = document.getElementById('railOpen');
      const closeBtn  = document.getElementById('railClose');
      const mqDesktop = window.matchMedia('(min-width: 1024px)');
      const LS_KEY    = 'adminSidebarCollapsed';
      const isDesktop = () => mqDesktop.matches;

      function setCollapsed(on) {
        if (on) {
          body.classList.add('admin-collapsed');
          localStorage.setItem(LS_KEY, '1');
        } else {
          body.classList.remove('admin-collapsed');
          localStorage.setItem(LS_KEY, '0');
        }
      }
      const getCollapsed = () => localStorage.getItem(LS_KEY) === '1';

      /* Mobile open/close */
      function openMobile(){
        sidebar.classList.remove('-translate-x-full');
        scrim.classList.remove('hidden');
        body.classList.add('no-scroll');
        body.classList.add('mobile-rail-open');     // mark mobile open (hides hamburger)
      }
      function closeMobile(){
        sidebar.classList.add('-translate-x-full');
        scrim.classList.add('hidden');
        body.classList.remove('no-scroll');
        body.classList.remove('mobile-rail-open');  // show hamburger again
      }

      /* Buttons */
      openBtn?.addEventListener('click', () => {
        if (isDesktop()) {
          if (getCollapsed()) setCollapsed(false);   // expand on desktop
        } else {
          openMobile();
        }
      });

      closeBtn?.addEventListener('click', () => {
        if (isDesktop()) {
          setCollapsed(true);                        // collapse on desktop (X hides via CSS)
        } else {
          closeMobile();                             // close overlay on mobile
        }
      });

      scrim?.addEventListener('click', closeMobile);
      window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !isDesktop()) closeMobile();
      });

      /* Init per viewport */
      function applyMode(){
        if (isDesktop()){
          body.classList.remove('mobile-rail-open');
          sidebar.classList.remove('-translate-x-full');
          scrim.classList.add('hidden');
          body.classList.remove('no-scroll');
          setCollapsed(getCollapsed());              // restore saved state
        } else {
          sidebar.classList.add('-translate-x-full'); // hidden by default on mobile
          body.classList.remove('admin-collapsed');   // mobile uses full rail (no collapsed UI)
          body.classList.remove('mobile-rail-open');
        }
      }
      mqDesktop.addEventListener('change', applyMode);
      applyMode();
    })();
  </script>
  {{-- ==== Wide SweetAlert “Appointment booked!” (global) ==== --}}
<style>
  /* Force left-aligned body for our compact success modal */
  .swal-compact .swal2-html-container{
    text-align: left !important;       /* <-- stops the centered look */
    padding: 18px 24px !important;
  }

  /* Optional: constrain body to a readable width */
  .appt-compact{
    font-size: 14.5px;
    line-height: 1.45;
    max-width: 980px;
    margin: 0 auto;                    /* center the content block inside the modal */
  }

  /* 4-col key/value header that collapses nicely */
  .appt-compact .kv-grid{
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px 18px;
    padding: 8px 0;
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    margin: 6px 0 12px;
  }
  @media (max-width: 1100px){
    .appt-compact .kv-grid{ grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 520px){
    .appt-compact .kv-grid{ grid-template-columns: 1fr; }
  }

  .appt-compact .kv{ display:flex; gap:6px; white-space:nowrap; }
  .appt-compact .kv .label{ font-weight:600; color:#111827; }
  .appt-compact .kv .value{ color:#374151; }
</style>

<style>
  /* ------ Shared booking modal styles (used by index & show) ------ */
  .swal-wide.swal2-popup{
    width: min(92vw, 760px) !important;
    padding: 0 !important;
    border-radius: 18px !important;
    box-shadow: 0 30px 60px rgba(2,6,23,.25);
  }
  .swal-wide .swal2-title{
    margin: 18px 22px 0 !important;
    font-size: 22px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
  }
  .swal-wide .swal2-html-container{
    margin: 0 !important;
    padding: 16px 22px 22px !important;
    text-align: left !important;
  }
  .swal-wide .swal2-actions{
    margin: 0 !important;
    padding: 16px 22px 22px !important;
  }

  /* Inputs */
  .swal-field{
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: .55rem .75rem;
  }
  .swal-field:focus{
    outline: 0;
    box-shadow: 0 0 0 3px rgba(79,70,229,.25);
    border-color: #c7d2fe;
  }

  /* Time buttons */
  .time-grid{ display:grid; gap:.5rem; grid-template-columns:repeat(3,minmax(0,1fr)); }
  @media (min-width:640px){ .time-grid{ grid-template-columns:repeat(4,minmax(0,1fr)); } }

  .time-btn{
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    border:1px solid #e2e8f0; background:#fff; color:#0f172a;
    padding:.55rem .6rem; border-radius:12px; font-size:.9rem; line-height:1.1; font-weight:600;
    transition: transform .06s ease, border-color .12s ease, background .12s ease;
  }
  .time-btn:hover{ background:#EEF2FF; border-color:#C7D2FE; }
  .time-btn.is-active{ box-shadow:0 0 0 3px rgba(79,70,229,.35); border-color:#a5b4fc; }
  .time-btn:disabled{ opacity:.45; background:#f8fafc; cursor:not-allowed; }
  .time-cap{ margin-top:.15rem; font-size:.72rem; opacity:.75; font-weight:500; }

  .tiny-hint{ font-size:.78rem; color:#64748b; }
</style>

  <script>
    /**
     * Show the “Appointment booked!” dialog with a wide, responsive layout.
     * @param {string} html - server-rendered HTML preview (e.g., your note + details)
     * @returns {Promise<void>}
     */
    async function showBookedSuccess(html){
    await Swal.fire({
      icon: 'success',
      title: 'Appointment booked!',
      html, // <-- use the parameter
      customClass: { popup: 'swal-success' },
      showCloseButton: true,
      confirmButtonText: 'OK',
    });
  }

  </script>
  @stack('scripts')
  </body>
  </html>
