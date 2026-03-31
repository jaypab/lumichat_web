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

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('layouts.partials.favicons')
  @include('layouts.partials.tour')

  <style>
    :root {
      --z-modal: 1100;
      /* panel */
      --z-backdrop: 1099;
      /* backdrop */
    }

    /* Use on the modal root container */
    .modal-zp {
      position: fixed;
      inset: 0;
      z-index: var(--z-modal);
    }

    /* Backdrop: dark tint + blur */
    .modal-zp .modal-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(2, 6, 23, 0.60);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: var(--z-backdrop);
    }

    .modal-zp .modal-panel {
      position: relative;
      z-index: calc(var(--z-modal) + 1);
    }

    .modal-zp .modal-backdrop--lg {
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    body.no-scroll {
      overflow: hidden;
    }

    html,
    body {
      height: 100%
    }

    body {
      overflow-x: hidden;
      -webkit-tap-highlight-color: transparent
    }
  </style>

  {{-- === Notification popover z-index fix (same as student/admin) === --}}
  <style id="nb-popover-z">
    [data-nb-root] {
      position: relative;
      z-index: 2147483641;
    }

    .nb-portal,
    [data-nb-portal],
    #nb-portal {
      position: absolute;
      z-index: 2147483642 !important;
    }
  </style>

  {{-- === Global SweetAlert theme (same as student/admin) === --}}
  <style id="lumi-swal-theme">
    .swal2-container.swal2-backdrop-show {
      background: rgba(15, 23, 42, .55) !important;
      backdrop-filter: blur(4px) saturate(110%);
    }

    .swal2-container.swal2-top-start,
    .swal2-container.swal2-top,
    .swal2-container.swal2-top-end,
    .swal2-container.swal2-bottom-start,
    .swal2-container.swal2-bottom,
    .swal2-container.swal2-bottom-end {
      background: transparent !important;
      backdrop-filter: none !important;
      pointer-events: none !important;
      z-index: 2147483000 !important;
    }

    .swal2-container .swal2-popup {
      pointer-events: auto !important;
    }

    .swal2-container.swal2-top-end {
      padding-top: max(12px, env(safe-area-inset-top)) !important;
      padding-right: max(12px, env(safe-area-inset-right)) !important;
      padding-bottom: 12px !important;
      padding-left: 12px !important;
    }

    .swal2-popup:not(.swal2-toast) {
      background: #fff !important;
      border-radius: 22px !important;
      padding: 28px 32px !important;
      box-shadow:
        0 40px 80px -20px rgba(2, 6, 23, .35),
        0 0 0 1px rgba(2, 6, 23, .05),
        0 30px 60px rgba(109, 40, 217, .08) !important;
      max-width: 680px;
    }

    .dark .swal2-popup:not(.swal2-toast) {
      background: rgba(17, 24, 39, .96) !important;
      color: #e5e7eb !important;
    }

    .swal2-popup:not(.swal2-toast) .swal2-title {
      margin: 12px 0 0 !important;
      font-weight: 700;
      font-size: 26px !important;
      letter-spacing: .2px;
      text-align: center;
      color: #0f172a;
    }

    .dark .swal2-popup:not(.swal2-toast) .swal2-title {
      color: #f8fafc;
    }

    .swal2-popup:not(.swal2-toast) .swal2-html-container {
      margin-top: 6px !important;
      font-size: 15px !important;
      color: #475569 !important;
    }

    .dark .swal2-popup:not(.swal2-toast) .swal2-html-container {
      color: #cbd5e1 !important;
    }

    .swal2-popup:not(.swal2-toast) .swal2-actions {
      margin-top: 22px !important;
      gap: 10px;
      flex-wrap: wrap;
    }

    .swal2-styled {
      border-radius: 14px !important;
      padding: 10px 18px !important;
      font-weight: 700 !important;
      box-shadow: none !important;
    }

    .swal2-confirm {
      background: linear-gradient(90deg, #7c3aed, #6366f1) !important;
      color: #fff !important;
      box-shadow: 0 10px 24px rgba(99, 102, 241, .35) !important;
    }

    .swal2-cancel,
    .swal2-deny {
      background: #fff !important;
      color: #334155 !important;
      border: 1px solid #e5e7eb !important;
    }

    .dark .swal2-cancel,
    .dark .swal2-deny {
      background: #1f2937 !important;
      color: #e5e7eb !important;
      border-color: #334155 !important;
    }
  </style>

  <script>
    try {
      let pref = localStorage.getItem('lumichat_dark');
      if (pref === null) { localStorage.setItem('lumichat_dark', '0'); pref = '0'; }
      if (pref === 'true') { localStorage.setItem('lumichat_dark', '1'); pref = '1'; }
      if (pref === 'false') { localStorage.setItem('lumichat_dark', '0'); pref = '0'; }
      const wantsDark = pref === '1';
      document.documentElement.classList.toggle('dark', wantsDark);
      document.documentElement.classList.toggle('dark-theme', wantsDark);
      document.documentElement.setAttribute('data-coreui-theme', wantsDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', wantsDark ? 'dark' : 'light');
    } catch (_) { }
  </script>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
  <script>
    if (localStorage.getItem('lumichat_dark') === '1') {
      document.body.classList.add('dark-theme');
    }
  </script>
  @php
    $u = auth()->user();
    $displayName = trim((string) ($u->name ?? 'Counselor'));
    $roleLabel = 'Counselor';
    $initials = collect(preg_split('/\s+/', $displayName))
      ->filter()
      ->take(2)
      ->map(fn($p) => mb_substr($p, 0, 1))
      ->implode('');
  @endphp

  <div class="layout-wrapper">
    <aside id="sidebar" class="sidebar-shell">
      <div class="sidebar-hdr flex items-center justify-between px-4 flex-shrink-0"
        style="height: var(--app-header-h);">
        <div class="sidebar-hdr-logo flex items-center gap-2.5 min-w-0">
          <img src="{{ asset('images/chatbot.png') }}" alt="Logo" class="w-6 h-6 flex-shrink-0">
          <div class="sidebar-brand-lockup min-w-0">
            <span class="sidebar-brand truncate">
              <span class="sidebar-brand-lumi">Lumi</span><span class="sidebar-brand-chat">CHAT</span>
            </span>
          </div>
        </div>
        <button id="sidebar-close" class="sidebar-x flex-shrink-0" title="Toggle sidebar" aria-label="Toggle sidebar">
          <svg class="icon-collapse" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
          <svg class="icon-expand" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
          </svg>
        </button>
      </div>

      @php
        $mainLinks = [
          ['label' => 'Dashboard', 'href' => route('counselor.dashboard'), 'active' => request()->routeIs('counselor.dashboard'), 'icon' => 'home.png'],
          ['label' => 'My Availability', 'href' => route('counselor.availability.index'), 'active' => request()->routeIs('counselor.availability.*'), 'icon' => 'appointment.png'],
          ['label' => 'Appointments', 'href' => route('counselor.appointments.index'), 'active' => request()->routeIs('counselor.appointments.*'), 'icon' => 'appointment.png'],
          ['label' => 'Walk-in Session', 'href' => route('counselor.walkins.create'), 'active' => request()->routeIs('counselor.walkins.*'), 'icon' => 'appointment.png'],
        ];
      @endphp

      <nav class="flex-1 px-2.5 pt-3 space-y-4 overflow-y-auto" id="railScroll">
        <div>
          <ul class="space-y-1">
            @foreach ($mainLinks as $item)
              <li>
                <a href="{{ $item['href'] }}" @class(['nav-item', 'nav-item--active' => $item['active']])
                  data-tip="{{ $item['label'] }}">
                  <img src="{{ asset('images/icons/' . $item['icon']) }}" alt="" class="sidebar-icon icon-white">
                  <span class="nav-item-label">{{ $item['label'] }}</span>
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      </nav>

      <div class="sidebar-profile-card">
        <div class="sidebar-user-avatar">{{ $initials ?: 'U' }}<span class="sidebar-avatar-dot"></span></div>
        <div class="sidebar-user-info">
          <span class="sidebar-user-name">{{ $displayName }}</span>
          <span class="sidebar-user-role">{{ $roleLabel }}</span>
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
                <line x1="4" y1="6" x2="20" y2="6" />
                <line x1="4" y1="12" x2="20" y2="12" />
                <line x1="4" y1="18" x2="20" y2="18" />
              </svg>
            </button>
            <h1 class="text-lg sm:text-xl font-semibold tracking-tight text-gray-900 dark:text-white">
              @yield('page_title', 'Dashboard')</h1>
          </div>

          <div class="flex items-center gap-3">
            <button id="theme-toggle" type="button" aria-label="Toggle theme" class="inline-flex items-center justify-center h-10 w-10 rounded-xl border border-gray-200
                         dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50
                         dark:hover:bg-gray-800 transition">
              <svg class="inline dark:hidden w-5 h-5 text-gray-600" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
              </svg>
              <svg class="hidden dark:inline w-5 h-5 text-amber-400" viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M6.76 4.84l-1.8-1.79L3.18 4.84l1.79 1.79 1.79-1.79zM1 13h3v-2H1v2zm10 10h2v-3h-2v3zm9-10v-2h-3v2h3zm-3.76 6.16l1.79 1.79 1.78-1.79-1.78-1.79-1.79 1.79zM12 7a5 5 0 100 10 5 5 0 000-10zm6.24-2.16l1.79-1.79-1.79-1.79-1.79 1.79 1.79 1.79zM4.24 17.16L2.45 18.95l1.79 1.79 1.79-1.79-1.79-1.79z" />
              </svg>
            </button>

            @auth
              <div data-nb-root class="relative z-[2147483641]">
                <x-notification-bell :indexRoute="route('counselor.notifications.index')"
                  :feedRoute="route('counselor.notifications.feed')" :markRoute="route('counselor.notifications.mark', ['id' => ':id'])" :markAllRoute="route('counselor.notifications.mark_all')" />
              </div>
            @endauth

            <div class="relative">
              <button id="user-btn" type="button" class="inline-flex items-center gap-3 h-11 px-2.5 rounded-xl border border-gray-200
                     dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50
                     dark:hover:bg-gray-800 transition">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br
                          from-indigo-500 to-violet-600 text-white text-xs font-bold">
                  {{ $initials ?: 'U' }}
                </div>
                <div class="hidden sm:flex flex-col text-left leading-tight min-w-0">
                  <span
                    class="text-[11px] font-medium uppercase tracking-[0.12em] text-gray-400 dark:text-gray-500">Welcome</span>
                  <span
                    class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[11rem]">{{ $displayName }}</span>
                </div>
                <svg class="hidden sm:block w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0" viewBox="0 0 20 20"
                  fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd" />
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
    (function () {
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
    (function () {
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
  </script>

  <script>
    // User menu toggle
    (function () {
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

  <button id="lumi-tour-fab" type="button" title="Help – Restart tutorial" aria-label="Restart tutorial">?</button>
  @stack('scripts')

  {{-- ===== Global SweetAlert flash renderer (robust + guarded) ===== --}}
  @php
    if (!function_exists('lumi_flash_text')) {
      function lumi_flash_text($key)
      {
        $val = session($key);
        if ($val instanceof \Illuminate\Support\MessageBag)
          return implode(' ', $val->all());
        if ($val instanceof \Illuminate\View\View)
          return trim((string) $val);
        if (is_array($val)) {
          $flat = [];
          array_walk_recursive($val, function ($v) use (&$flat) {
            $flat[] = $v;
          });
          return implode(' ', array_map('strval', $flat));
        }
        return is_scalar($val) ? (string) $val : '';
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
      (function () {
        const payload = @json($flashPayload);
        function fireSwal() {
          try {
            if (payload.type === 'swal') { return Swal.fire(payload.data); }
            const base = {
              icon: payload.type,
              title: payload.type === 'error' ? 'Error'
                : payload.type === 'warning' ? 'Warning'
                  : payload.type === 'info' ? 'Info' : 'Success',
              text: payload.text || '',
            };
            if (payload.type === 'error') base.confirmButtonColor = '#e11d48';
            if (payload.type === 'success') base.confirmButtonColor = '#4f46e5';
            if (payload.type === 'warning') base.confirmButtonColor = '#f59e0b';
            if (payload.type === 'info') base.confirmButtonColor = '#4f46e5';
            Swal.fire(base);
          } catch (e) {
            alert((payload.type.toUpperCase()) + ': ' + (payload.text || ''));
          }
        }
        if (document.readyState === 'loading') {
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
               You’ll be signed out from this device. Your appointments, case notes, and student records will remain safely stored in LumiCHAT.
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