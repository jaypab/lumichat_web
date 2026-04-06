{{-- resources/views/layouts/partials/tour.blade.php --}}
@if (Auth::check())
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
  <script defer src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.min.js"></script>

  <style>
    :root {
      --lumi-bg: #fff;
      --lumi-fg: #0f172a;
      --lumi-muted: #64748b;
      --lumi-ring: rgba(99, 102, 241, .38);
      --lumi-grad-a: #7c3aed;
      --lumi-grad-b: #6366f1;
      --lumi-overlay: rgba(2, 6, 23, .42);
      --fab-pad: 20px;
      --fab-size: 52px;
      --fab-gap: 12px;
    }

    .dark:root {
      --lumi-bg: #0b1220;
      --lumi-fg: #e5e7eb;
      --lumi-muted: #cbd5e1;
      --lumi-ring: rgba(129, 140, 248, .50);
      --lumi-overlay: rgba(2, 6, 23, .60);
    }

    #about-top.fab-above-help {
      right: var(--fab-pad) !important;
      bottom: calc(var(--fab-pad) + var(--fab-size) + var(--fab-gap)) !important;
      z-index: 2147482998;
    }

    .driver-overlay {
      background: var(--lumi-overlay) !important;
    }

    .driver-popover {
      border-radius: 20px !important;
      background: var(--lumi-bg) !important;
      color: var(--lumi-fg) !important;
      border: 1px solid rgba(148, 163, 184, .14) !important;
      box-shadow: 0 35px 80px -20px rgba(2, 6, 23, .4), 0 0 0 1px rgba(2, 6, 23, .03), 0 20px 40px rgba(99, 102, 241, .1) !important;
      padding: 16px !important;
      min-width: 280px;
      max-width: 380px;
      animation: lumiPopIn .3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes lumiPopIn {
      from { opacity: 0; transform: scale(.95) translateY(12px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }

    .driver-popover-title {
      font-size: 17px !important;
      font-weight: 800 !important;
      margin: 0 0 6px !important;
      letter-spacing: -.01em;
      color: var(--lumi-fg) !important;
      transition: all .3s ease;
    }

    .driver-popover-description {
      font-size: 13.5px !important;
      color: var(--lumi-muted) !important;
      line-height: 1.55 !important;
      margin-bottom: 12px !important;
      transition: all .3s ease;
    }

    .driver-popover-progress-text {
      font-size: 11px !important;
      font-weight: 700 !important;
      color: var(--lumi-grad-a) !important;
      text-transform: uppercase;
      letter-spacing: .5px;
    }

    .driver-popover-progress {
      height: 5px !important;
      background: rgba(99, 102, 241, .12) !important;
      border-radius: 999px !important;
      margin: 6px 0 14px !important;
      overflow: hidden;
    }

    .driver-popover-progress>span {
      background: linear-gradient(90deg, var(--lumi-grad-a), var(--lumi-grad-b)) !important;
      transition: width .4s cubic-bezier(0.65, 0, 0.35, 1) !important;
    }

    .driver-popover-next-btn, .driver-popover-prev-btn, .driver-popover-close-btn {
      border-radius: 9px !important;
      padding: 6px 14px !important;
      font-weight: 700 !important;
      font-size: 11.5px !important;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
      text-shadow: none !important;
      border: 1px solid rgba(148, 163, 184, 0.15) !important;
    }

    /* Primary Action: Next / Done */
    .driver-popover-next-btn {
      color: #fff !important;
      background: linear-gradient(135deg, #7c3aed, #4f46e5) !important;
      border: 0 !important;
      box-shadow: 0 8px 18px rgba(99, 102, 241, 0.35) !important;
    }

    .driver-popover-next-btn:hover:not(.driver-popover-btn-disabled) {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 12px 28px rgba(99, 102, 241, 0.45) !important;
      background: linear-gradient(135deg, #8b5cf6, #5a57f2) !important;
    }

    /* Secondary Action: Previous / Close */
    .driver-popover-prev-btn, .driver-popover-close-btn {
      background: rgba(148, 163, 184, 0.08) !important;
      color: var(--lumi-fg) !important;
    }

    .driver-popover-prev-btn:hover, .driver-popover-close-btn:hover {
      background: rgba(148, 163, 184, 0.15) !important;
      border-color: rgba(148, 163, 184, 0.3) !important;
      transform: translateY(-1px);
    }

    .driver-popover-close-btn {
       padding: 0 !important;
       display: flex !important;
       align-items: center;
       justify-content: center;
       background: transparent !important;
       border: 0 !important;
       opacity: 0.6;
    }
    
    .driver-popover-close-btn:hover {
       background: rgba(239, 68, 68, 0.1) !important;
       color: #ef4444 !important;
       opacity: 1;
    }

    .driver-popover-btn:active {
      transform: scale(0.96) translateY(0);
    }

    .driver-popover-close-btn {
      top: 10px !important;
      right: 10px !important;
      width: 26px !important;
      height: 26px !important;
      border-radius: 8px !important;
      color: var(--lumi-muted) !important;
      transition: all .2s;
    }

    .driver-popover-close-btn:hover {
      background: rgba(239, 68, 68, .08) !important;
      color: #ef4444 !important;
    }

    .driver-highlighted-element {
      transition: all .4s cubic-bezier(0.16, 1, 0.3, 1) !important;
      box-shadow: 0 0 0 6px var(--lumi-ring) !important;
      border-radius: 14px !important;
      animation: lumiPulse 2s infinite ease-in-out;
    }

    @keyframes lumiPulse {
      0% { box-shadow: 0 0 0 4px var(--lumi-ring); }
      50% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0.2); }
      100% { box-shadow: 0 0 0 4px var(--lumi-ring); }
    }

    #lumi-tour-fab {
      position: fixed;
      right: var(--fab-pad);
      bottom: var(--fab-pad);
      z-index: 2147483647 !important;
      width: var(--fab-size);
      height: var(--fab-size);
      border-radius: 16px;
      display: flex !important;
      align-items: center;
      justify-content: center;
      background: #7c3aed !important;
      background: linear-gradient(135deg, #7c3aed, #4f46e5) !important;
      color: #fff !important;
      border: 0 !important;
      cursor: pointer !important;
      box-shadow: 0 12px 28px -4px rgba(99, 102, 241, 0.5), 0 0 0 2px rgba(255, 255, 255, 0.1);
      transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      font-weight: 900 !important;
      font-size: 22px !important;
      line-height: 1;
      animation: fabPulse 3s infinite;
      visibility: visible !important;
      opacity: 1 !important;
      pointer-events: auto !important;
    }

    #lumi-tour-fab:hover {
      transform: scale(1.1) rotate(5deg);
      box-shadow: 0 20px 40px -8px rgba(99, 102, 241, .6);
    }

    @keyframes fabPulse {
      0% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.4); }
      70% { box-shadow: 0 0 0 15px rgba(124, 58, 237, 0); }
      100% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0); }
    }

    html.dark #lumi-tour-fab {
      box-shadow: 0 12px 28px rgba(129, 140, 248, .35);
    }

    .swal2-popup.lumi-tour-modal {
      border-radius: 28px !important;
      box-shadow: 0 40px 90px -20px rgba(2, 6, 23, .45), 0 0 0 1px rgba(2, 6, 23, .05) !important;
      padding: 0 !important;
      background: var(--lumi-bg) !important;
      overflow: hidden;
      border: 0 !important;
      max-height: 98vh;
      display: flex;
      flex-direction: column;
    }

    .swal2-title.lumi-tour-title {
      font-weight: 900 !important;
      letter-spacing: -.035em !important;
      color: var(--lumi-fg) !important;
      font-size: 1.6rem !important;
      padding: 1.5rem 1.5rem .2rem !important;
      line-height: 1.1 !important;
      text-align: center !important;
    }

    .swal2-html-container.lumi-tour-body {
      color: var(--lumi-muted) !important;
      font-size: .88rem !important;
      margin: 0 !important;
      padding: 0 1.5rem 1rem !important;
      line-height: 1.45 !important;
      overflow-y: visible !important;
    }

    .lumi-title-accent {
      background: linear-gradient(90deg, var(--lumi-grad-a), var(--lumi-grad-b));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      display: inline-block;
    }

    .lumi-intro-text {
      margin: 0 0 1rem;
      font-weight: 500;
      text-align: center;
      opacity: .8;
      font-size: .88rem;
    }

    .lumi-welcome-list {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: .6rem;
    }

    .lumi-welcome-item {
      display: flex;
      align-items: center;
      gap: 1.2rem;
      padding: 1rem 1.2rem;
      border-radius: 18px;
      background: rgba(148, 163, 184, .02);
      border: 1px solid rgba(148, 163, 184, .08);
      transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .lumi-welcome-item:hover {
      transform: translateX(5px);
      background: rgba(99, 102, 241, .04);
      border-color: rgba(99, 102, 241, .15);
      box-shadow: 0 12px 25px -10px rgba(99, 102, 241, .12);
    }

    .lumi-welcome-icon {
      flex-shrink: 0;
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 1);
      color: var(--lumi-grad-a);
      display: grid;
      place-items: center;
      box-shadow: 0 6px 12px rgba(99, 102, 241, .1);
      transition: transform .25s;
    }

    .dark .lumi-welcome-icon {
      background: #1e293b;
    }

    .lumi-welcome-item:hover .lumi-welcome-icon {
      transform: scale(1.05);
    }

    .lumi-welcome-text {
      flex: 1;
      font-size: .82rem;
      line-height: 1.4;
      text-align: left;
    }

    .lumi-welcome-text b {
      color: var(--lumi-fg);
      display: block;
      font-size: .9rem;
      font-weight: 800;
      margin-bottom: 0;
      letter-spacing: -.01em;
    }

    .lumi-tour-footer {
      margin-top: 1.2rem;
      text-align: center;
      padding-top: .9rem;
      border-top: 1px solid rgba(148, 163, 184, .06);
      color: var(--lumi-muted);
      font-size: .8rem;
      font-weight: 500;
    }

    .swal2-actions.lumi-tour-actions {
      padding: 0 1.5rem 1.5rem !important;
      margin-top: .2rem !important;
      width: 100%;
      box-sizing: border-box;
      display: flex !important;
      gap: 12px !important;
    }

    .swal2-confirm.btn-grad {
      flex: 1 !important;
      background: linear-gradient(90deg, var(--lumi-grad-a), #4f46e5) !important;
      color: #fff !important;
      border-radius: 14px !important;
      padding: .8rem !important;
      font-weight: 800 !important;
      font-size: .92rem !important;
      border: 0 !important;
      box-shadow: 0 8px 20px rgba(99, 102, 241, .25) !important;
      transition: all .2s ease !important;
      height: auto !important;
    }

    .swal2-confirm.btn-grad:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 28px rgba(99, 102, 241, .4) !important;
    }

    .swal2-cancel.btn-neutral {
      flex: 1 !important;
      background: rgba(226, 232, 240, .6) !important;
      color: var(--lumi-fg) !important;
      border-radius: 16px !important;
      padding: .85rem !important;
      font-weight: 700 !important;
      font-size: .95rem !important;
      border: 0 !important;
      transition: all .2s !important;
      margin: 0 !important;
    }

    .dark .swal2-cancel.btn-neutral {
      background: rgba(30, 41, 59, .8) !important;
    }

    .swal2-cancel.btn-neutral:hover {
      background: rgba(203, 213, 225, .8) !important;
      color: var(--lumi-fg) !important;
    }
  </style>

  <script>
    (function () {
      const USER_ID = @json(Auth::id());
      const ROUTE_KEY = @json(Route::currentRouteName() ?? 'unknown');
      const SHOULD_RUN_SERVER = @json($shouldRunTour ?? false);

      const USER_NAME = @json(Auth::user()->name ?? 'Counselor');
      const FIRST_NAME = (USER_NAME || '').trim().split(/\s+/)[0] || 'Counselor';

      const APP_ROLE = (document.documentElement.getAttribute('data-app') || '').toLowerCase() || 'student';

      const GLOBAL_DONE = `lumi_tour_done_v1_${APP_ROLE}_${USER_ID}`;
      const WELCOME_SEEN = `lumi_tour_welcome_seen_v1_${APP_ROLE}_${USER_ID}`;
      const PAGE_FLAG = (pageKey) => `lumi_tour_page_v1_${APP_ROLE}_${pageKey}_${USER_ID}`;

      const sleep = (ms) => new Promise(r => setTimeout(r, ms));
      const $ = (s) => document.querySelector(s);

      function normalizePageKey(route) {
        if (!route) return 'unknown';

        // --- Counselor routes (put specific ones BEFORE the generic startsWith) ---
        if (route === 'counselor.dashboard') return 'counselor.dashboard';
        if (route === 'counselor.appointments.show') return 'counselor.appointments.show';
        if (route === 'counselor.appointments.index') return 'counselor.appointments';
        if (route.startsWith('counselor.appointments')) {
          return /show|\/\d+/.test(window.location.pathname)
            ? 'counselor.appointments.show'
            : 'counselor.appointments';
        }

        // ✅ NEW: normalize walk-in routes
        if (route.startsWith('counselor.walkins')) return 'counselor.walkins';

        if (route.startsWith('counselor.availability')) return 'counselor.availability';
        if (route.startsWith('counselor.')) return 'counselor';

        // --- Student routes (unchanged) ---
        if (route === 'chat.history') return 'chat.history';
        if (route === 'appointment.history') return 'appointment.history';
        if (route === 'appointment.view' || route === 'appointment.show') return 'appointment.view';
        if (route === 'profile.edit') return 'profile.edit';
        if (route === 'about.index') return 'about.index';
        if (route === 'settings.index') return 'settings.index';
        if (route.startsWith('appointment.')) return 'appointment';
        if (route === 'home' || route === 'dashboard' || route === 'chat.index' || route === 'chat.show')
          return 'chat';
        if (route.startsWith('profile.')) return 'profile';
        if (route.startsWith('about.')) return 'about';

        return route;
      }
      const PAGE_KEY = normalizePageKey(ROUTE_KEY);

      async function ensureDriver(maxWait = 4000) {
        const start = Date.now();
        while (!window.driver && Date.now() - start < maxWait) { await sleep(100); }
        return window.driver || null;
      }

      let drv;
      function getDriver() {
        if (drv) return drv;
        const isDark = document.documentElement.classList.contains('dark');
        drv = window.driver({
          showProgress: true, animate: true, allowClose: true, smoothScroll: true, stagePadding: 6,
          overlayOpacity: isDark ? 0.50 : 0.32,
          nextBtnText: 'Next →', prevBtnText: '← Previous', doneBtnText: 'Done',
          popoverClass: 'lumi-tour'
        });
        try { drv.on?.('destroyed', () => markPageDone()); drv.on?.('reset', () => markPageDone()); } catch (_) { }
        return drv;
      }

      async function markGlobalDone() {
        localStorage.setItem(GLOBAL_DONE, '1');
        try {
          const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
          await fetch(@json(route('tour.complete')), { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
        } catch (_) { }
      }
      function markPageDone() { localStorage.setItem(PAGE_FLAG(PAGE_KEY), '1'); }
      function ensureTerminalMark(steps) {
        if (!steps?.length) return steps;
        const last = steps[steps.length - 1];
        if (last.onNextClick) {
          const prev = last.onNextClick;
          last.onNextClick = async (...a) => { try { await prev(...a); } finally { markPageDone(); } };
        } else {
          last.onNextClick = markPageDone;
        }
        return steps;
      }

      function getHeaderSteps() {
        const steps = [];
        const themeToggle = document.getElementById('theme-toggle');
        const notifyBell = document.querySelector('[data-nb-root]');
        const userBtn = document.getElementById('user-btn');

        if (themeToggle) steps.push({
          element: themeToggle,
          popover: { title: 'Personalize Theme', description: 'Switch between light and dark modes anytime.', side: 'bottom', align: 'center' }
        });

        if (notifyBell) steps.push({
          element: notifyBell,
          popover: { title: 'Notifications', description: 'Stay updated on your appointments and system alerts.', side: 'bottom', align: 'center' }
        });

        if (userBtn) steps.push({
          element: userBtn,
          popover: { title: 'Your Account', description: 'Access your profile, settings, and logout options.', side: 'bottom', align: 'end' }
        });

        return steps;
      }

      /* ---------- STEP BUILDERS ---------- */
      function counselorAppointmentShowSteps() {
        const steps = [];

        // --- PDF + Back (header) ---
        const pdfBtn =
          Array.from(document.querySelectorAll('a,button')).find(a =>
            /download\s*pdf/i.test(a.textContent || '')
          ) ||
          document.querySelector('a[href*="export"][href*="pdf"]') ||
          Array.from(document.querySelectorAll('a[target="_blank"]')).find(a =>
            /pdf/i.test(a.textContent || a.getAttribute('href') || '')
          );

        let backBtn = null;
        if (pdfBtn) {
          const wrap = pdfBtn.closest('.flex') || pdfBtn.parentElement;
          backBtn = Array.from(wrap?.querySelectorAll('a,button') || []).find(a =>
            /(^|\s)back(\s|$)/i.test(a.textContent || '')
          ) || null;
        }
        if (!backBtn) {
          backBtn = Array.from(document.querySelectorAll('a,button')).find(a => {
            const t = (a.textContent || '').trim();
            if (!/(^|\s)back(\s|$)/i.test(t)) return false;
            return !a.closest('nav, aside, [role="navigation"]');
          }) || null;
        }

        // --- Action buttons via hidden input[name="action"] ---
        const actionBtn = (action) =>
          document.querySelector(`form input[name="action"][value="${action}"]`)
            ?.closest('form')?.querySelector('button[type="submit"]') || null;

        const btnConfirm = actionBtn('confirm');
        const btnStart = actionBtn('start');
        const btnDone = actionBtn('done');

        const btnNoShow =
          document.querySelector('form[action*="no_show"] button[type="submit"]') ||
          Array.from(document.querySelectorAll('form button[type="submit"], a, button')).find(el =>
            /no[-\s]?show/i.test((el.textContent || el.value || '').trim())
          ) || null;

        const diagForm = document.querySelector('form[action*="/appointments"][action*="report"]');
        const diagBox = diagForm || document.querySelector('.rounded-2xl.bg-indigo-50\\/40, .rounded-2xl.bg-indigo-50');

        const pulse = (el) => {
          try {
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.focus?.({ preventScroll: true });
            if (!el) return;
            el.style.transition = 'transform .12s ease';
            el.style.transform = 'scale(1.03)';
            setTimeout(() => { el.style.transform = ''; }, 140);
          } catch (_) { }
        };

        if (backBtn) steps.push({
          element: backBtn,
          popover: { title: 'Back to list', description: 'Return to all appointments.', side: 'top', align: 'center' },
          onNextClick: () => pulse(backBtn)
        });

        if (pdfBtn) steps.push({
          element: pdfBtn,
          popover: { title: 'Download PDF', description: 'Open a printable PDF summary for this appointment.', side: 'top', align: 'center' },
          onNextClick: () => pulse(pdfBtn)
        });

        if (btnConfirm) steps.push({
          element: btnConfirm,
          popover: { title: 'Confirm', description: 'Approve a pending request and lock in the schedule.', side: 'top', align: 'center' },
          onNextClick: () => pulse(btnConfirm)
        });

        if (btnStart) steps.push({
          element: btnStart,
          popover: { title: 'Start Session', description: 'Sets the status to <b>Ongoing</b> when the time has come.', side: 'top', align: 'center' },
          onNextClick: () => pulse(btnStart)
        });

        if (btnDone) steps.push({
          element: btnDone,
          popover: { title: 'End Session', description: 'Marks the appointment as <b>Completed</b>.', side: 'top', align: 'center' },
          onNextClick: () => pulse(btnDone)
        });

        if (btnNoShow) steps.push({
          element: btnNoShow,
          popover: { title: 'Mark as No-Show', description: 'Use when the student did not attend.', side: 'top', align: 'center' },
          onNextClick: () => pulse(btnNoShow)
        });

        if (diagBox) steps.push({
          element: diagBox,
          popover: { title: 'Diagnosis / Report', description: 'After completion, add the final remarks here and save.', side: 'top', align: 'start' },
          onNextClick: () => pulse(diagForm?.querySelector('textarea[name="diagnosis"]') || diagBox)
        });

        return steps;
      }

      /* ✅ NEW: Walk-in counselor page tour */
      function counselorWalkinsSteps() {
        const $ = (s) => document.querySelector(s);
        const $$ = (s) => Array.from(document.querySelectorAll(s));
        const steps = [];

        const card = $('#walkinCard') || $('.max-w-4xl.mx-auto .rounded-2xl') || document.querySelector('.max-w-4xl.mx-auto');
        const header = $('#walkinHeader') || card?.querySelector('h1, h2');
        const statusChip = $('#walkinStatus') || $$('span').find(s => /walk-?in/i.test(s.textContent || ''));
        const studentBox = $('#walkin-student')
          || $('input[name="student_query"], input[name="student_search"], input[data-role="student-search"]');
        const anonToggle = $('#walkin-anon')
          || $('input[type="checkbox"][name="is_anonymous"], input[type="checkbox"][data-role="walkin-anon"]');
        const concernBox = $('#walkin-concern')
          || $('textarea[name*="concern"], textarea[name*="reason"], textarea[data-role="walkin-concern"]');
        const riskSelect = $('#walkin-risk')
          || $('select[name*="risk"], select[name*="severity"], select[data-role="walkin-risk"]');
        const startBtn = $('#btn-start-walkin')
          || $$('button').find(b => /start\s+walk-?in|begin\s+session|start\s+session/i.test(b.textContent || ''));
        const saveBtn = $('#btn-save-walkin')
          || $$('button').find(b => /save\s+(notes|walk-?in)|update\s+details/i.test(b.textContent || ''));
        const cancelBtn = $('#btn-cancel-walkin')
          || $$('a,button').find(el => /cancel/i.test(el.textContent || '') && /walk-?in/i.test(el.closest('form')?.textContent || ''));

        if (card) {
          steps.push({
            element: card,
            popover: {
              title: 'Walk-in Session',
              description: 'Use this screen when a student comes directly to the counselor without an online booking.',
              side: 'top', align: 'start'
            }
          });
        }

        if (header) {
          steps.push({
            element: header,
            popover: {
              title: 'At-a-glance header',
              description: 'Shows today’s date, current status, and quick context for the walk-in.',
              side: 'bottom', align: 'start'
            }
          });
        }

        if (studentBox) {
          steps.push({
            element: studentBox,
            popover: {
              title: 'Attach a student (optional)',
              description: 'Search and link the student’s record if they are enrolled. Otherwise, you can keep it anonymous.',
              side: 'bottom', align: 'start'
            }
          });
        }

        if (anonToggle) {
          steps.push({
            element: anonToggle,
            popover: {
              title: 'Anonymous walk-in',
              description: 'Toggle this when the student prefers not to link their identity to the case. Use with care.',
              side: 'left', align: 'center'
            }
          });
        }

        if (concernBox) {
          steps.push({
            element: concernBox,
            popover: {
              title: 'Presenting concern',
              description: 'Summarize what the student shares at the start of the session. Focus on key points only.',
              side: 'top', align: 'start'
            }
          });
        }

        if (riskSelect) {
          steps.push({
            element: riskSelect,
            popover: {
              title: 'Risk level',
              description: 'Tag the initial risk (Low / Moderate / High) based on the student’s situation for reporting and safety workflows.',
              side: 'top', align: 'start'
            }
          });
        }

        if (startBtn) {
          steps.push({
            element: startBtn,
            popover: {
              title: 'Start the walk-in',
              description: 'When you are ready to proceed, start the session so it is logged as Ongoing in LumiCHAT.',
              side: 'top', align: 'center'
            }
          });
        }

        if (saveBtn) {
          steps.push({
            element: saveBtn,
            popover: {
              title: 'Save notes',
              description: 'Capture quick notes or partial details even if the session is still in progress.',
              side: 'top', align: 'center'
            }
          });
        }

        if (cancelBtn) {
          steps.push({
            element: cancelBtn,
            popover: {
              title: 'Cancel / close',
              description: 'Use this when you need to discard a draft walk-in or the student decides not to continue.',
              side: 'top', align: 'center'
            }
          });
        }

        if (!steps.length && document.body) {
          steps.push({
            element: document.body,
            popover: {
              title: 'Walk-in workflow',
              description: 'Record unscheduled sessions by attaching (or not) a student, describing the concern, tagging risk, then starting the session.',
              side: 'top', align: 'center'
            }
          });
        }

        return steps;
      }

      function counselorDashboardSteps() {
        const S = [];
        const hero = document.getElementById('c-dash-hero');
        const quickAvail = document.getElementById('c-quick-availability');
        const quickAppt = document.getElementById('c-quick-appointments');
        const clockbox = document.getElementById('c-dash-clockbox');
        const kpiToday = document.getElementById('c-kpi-today');
        const kpiPending = document.getElementById('c-kpi-pending');
        const kpiQueue = document.getElementById('c-kpi-queue');
        const kpiHours = document.getElementById('c-kpi-openhours');
        const upHead = document.getElementById('c-upcoming-head');
        const upList = document.getElementById('c-upcoming-list');
        const upAll = document.getElementById('c-upcoming-viewall');
        const notes = document.getElementById('c-dash-notes');
        const tip = document.getElementById('c-dash-tip');

        if (hero) S.push({ element: hero, popover: { title: 'Counselor Dashboard', description: 'Your home base: quick actions, today’s KPIs, and upcoming appointments at a glance.', side: 'bottom', align: 'start' } });
        if (quickAvail) S.push({ element: quickAvail, popover: { title: 'Manage Availability', description: 'Set recurring hours or date-specific overrides.', side: 'top', align: 'start' } });
        if (quickAppt) S.push({ element: quickAppt, popover: { title: 'View Appointments', description: 'Go to the full appointments list to take action.', side: 'top', align: 'start' } });
        if (clockbox) S.push({ element: clockbox, popover: { title: 'Live Clock', description: 'Quick time reference while scheduling.', side: 'left', align: 'center' } });
        if (kpiToday) S.push({ element: kpiToday, popover: { title: 'Today’s Appointments', description: 'Count + trend for today.', side: 'top', align: 'start' } });
        if (kpiPending) S.push({ element: kpiPending, popover: { title: 'Pending', description: 'Awaiting your confirmation or action.', side: 'top', align: 'start' } });
        if (kpiQueue) S.push({ element: kpiQueue, popover: { title: 'Queue', description: 'Load across Pending, Confirmed, Ongoing.', side: 'top', align: 'start' } });
        if (kpiHours) S.push({ element: kpiHours, popover: { title: 'Open Hours', description: 'Total available hour-slots this week.', side: 'top', align: 'start' } });
        if (upHead) S.push({ element: upHead, popover: { title: 'Upcoming Appointments', description: 'Next sessions with date/time & status.', side: 'bottom', align: 'start' } });
        if (upList) S.push({ element: upList, popover: { title: 'Open a session', description: 'Click a row to view details or act.', side: 'top', align: 'start' } });
        if (upAll) S.push({ element: upAll, popover: { title: 'View all', description: 'Full list, filters, bulk ops.', side: 'left', align: 'center' } });
        if (notes) S.push({ element: notes, popover: { title: 'Notes', description: 'Quick personal reminders (coming soon).', side: 'left', align: 'center' } });
        if (tip) S.push({ element: tip, popover: { title: 'Pro tip', description: 'Use Weekday Quick Editor and per-date overrides.', side: 'top', align: 'start' } });

        S.push(...getHeaderSteps());
        return S;
      }

      function counselorAvailabilitySteps() {
        const S = [];
        const calMonth = $('#calMonth'), calPrev = $('#calPrev'), calNext = $('#calNext'), calGrid = $('#calGrid'),
          calUse = $('#calUse'), calClear = $('#calClear');
        const quickList = document.querySelector('section.border-t ul[role="list"]');
        const firstRow = quickList?.querySelector('li');
        const updBtn = firstRow?.querySelector('button[data-action="open-hour-modal"]');
        const disBtn = firstRow?.querySelector('button[data-action="disable-weekday"]');
        const tiles = $('#hourTiles');

        if (calMonth) S.push({ element: calMonth, popover: { title: 'Interactive Calendar', description: 'Weekends are disabled. Click a weekday to edit.', side: 'bottom', align: 'center' } });
        if (calPrev && calNext) S.push({ element: calNext, popover: { title: 'Navigate months', description: 'Move forward/back. Arrow keys also work.', side: 'top', align: 'center' } });
        if (calGrid) S.push({ element: calGrid, popover: { title: 'Pick a date', description: 'Set date-specific open/blocked hours.', side: 'top', align: 'start' } });
        if (calUse) S.push({ element: calUse, popover: { title: 'Use selected date', description: 'Confirm the date & open the hour editor.', side: 'left', align: 'center' } });
        if (calClear) S.push({ element: calClear, popover: { title: 'Clear', description: 'Unselect the date (Esc also works).', side: 'left', align: 'center' } });
        if (quickList) S.push({ element: quickList, popover: { title: 'Weekday Quick Editor', description: 'Edit recurring windows (Mon–Fri) or disable a weekday.', side: 'top', align: 'start' } });
        if (updBtn) S.push({ element: updBtn, popover: { title: 'Update hours', description: 'Open the tile editor to toggle hourly windows.', side: 'left', align: 'center' } });
        if (disBtn) S.push({ element: disBtn, popover: { title: 'Disable weekday', description: 'Block 9–12 & 1–5 for this weekday.', side: 'left', align: 'center' } });
        if (tiles) S.push({ element: tiles, popover: { title: 'Tile editor', description: 'Click to disable/enable hours. Lunch (12–1) is locked.', side: 'top', align: 'start' } });
        return S;
      }

      function counselorAppointmentsSteps() {
        const steps = [];

        const searchBox = document.getElementById('q');
        const filterForm = searchBox?.closest('form')
          || document.querySelector('form[action*="/counselor/appointments"]')
          || document.querySelector('form[action*="counselor.appointments.index"]');

        const statusSel = filterForm?.querySelector('select[name="status"]');
        const periodSel = filterForm?.querySelector('select[name="period"]');

        let applyBtn = filterForm?.querySelector('button[type="submit"]');
        if (!applyBtn) {
          applyBtn = Array.from(filterForm?.querySelectorAll('button, a, input') || [])
            .find(el => /apply/i.test(el.textContent || el.value || ''));
        }

        const resetBtn = filterForm?.querySelector('a[href]');

        const table = document.querySelector('table');
        const rows = table?.querySelectorAll('tbody tr');
        const firstView = table?.querySelector('tbody a[href*="/counselor/appointments/"]');

        if (statusSel) steps.push({
          element: statusSel, popover: {
            title: 'Filter by Status',
            description: 'Narrow appointments by their current state (Pending, Confirmed, Ongoing, etc.).',
            side: 'bottom', align: 'start'
          }
        });

        if (periodSel) steps.push({
          element: periodSel, popover: {
            title: 'Date Range',
            description: 'Quickly filter upcoming, today’s, or past appointments.',
            side: 'bottom', align: 'start'
          }
        });

        if (searchBox) steps.push({
          element: searchBox, popover: {
            title: 'Search Students',
            description: 'Find a student by name or email. Typing auto-searches after a short pause.',
            side: 'bottom', align: 'start'
          }
        });

        if (applyBtn) steps.push({
          element: applyBtn,
          popover: {
            title: 'Apply Filters',
            description: 'Click to apply the selected filters.',
            side: 'top', align: 'center'
          },
          onNextClick: () => {
            try {
              applyBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
              applyBtn.focus({ preventScroll: true });
              applyBtn.style.transition = 'transform .12s ease';
              applyBtn.style.transform = 'scale(1.03)';
              setTimeout(() => (applyBtn.style.transform = ''), 140);
            } catch (_) { }
          }
        });

        if (resetBtn) steps.push({
          element: resetBtn, popover: {
            title: 'Reset Filters',
            description: 'Show everything again.',
            side: 'top', align: 'center'
          }
        });

        if (rows?.length) steps.push({
          element: rows[0], popover: {
            title: 'Appointments Table',
            description: 'Each row shows the student, date/time, booking details, and status.',
            side: 'top', align: 'start'
          }
        });

        if (firstView) steps.push({
          element: firstView, popover: {
            title: 'View Details',
            description: 'Open an appointment to see notes and actions (confirm, cancel, mark done).',
            side: 'left', align: 'center'
          }
        });

        return steps;
      }

      /* student page helpers kept for reuse */
      function chatSteps() {
        const $ = (s) => document.querySelector(s);
        const $$ = (s) => Array.from(document.querySelectorAll(s));
        const steps = [];

        const FT_KEY = `lumi_first_chat_seen_${USER_ID}`;
        const isFirstTimeUser = !localStorage.getItem(FT_KEY);
        try { localStorage.setItem(FT_KEY, '1'); } catch (_) { }

        const sidebar = $('#sidebar');
        const findByText = (txt) => {
          if (!sidebar) return null;
          const links = Array.from(sidebar.querySelectorAll('a.nav-item, a.nav-pill, a[href]'));
          return links.find(a => new RegExp(`\\b${txt}\\b`, 'i').test(a.textContent || '')) || null;
        };

        const newChatBtn = document.querySelector('.nav-pill[data-new-chat="1"]');
        const chatArea = $('#chat-messages') || $('#lb-scope') || $('.msg-area') || $('[data-chat-area]');
        const inputBox = $('#chat-message');
        const composer = $('#chat-form');

        const linkAnnouncements = findByText('Announcements');
        const linkChatHist = $('#nav-chat-history') || findByText('Chat History');
        const linkProfile = findByText('Profile');
        const linkSettings = $('#nav-settings') || findByText('Settings');
        const linkAbout = findByText('About');

        // 1. Start with New Chat
        if (newChatBtn) steps.push({
          element: newChatBtn,
          popover: { title: 'New Chat', description: 'Start a fresh conversation here.', side: 'right', align: 'center' }
        });

        // 2. Jump to Chat Interface (Home content)
        if (chatArea) steps.push({
          element: chatArea,
          popover: { title: 'Messages', description: 'Your conversation appears here.', side: 'left', align: 'start' }
        });

        if (inputBox) steps.push({
          element: inputBox,
          popover: { title: 'Type & Send', description: 'Press Enter to send. Shift+Enter for a new line.', side: 'top', align: 'start' }
        });

        const triggerHtml = isFirstTimeUser
          ? `<div style="text-align:left;line-height:1.45">
              <p><b>First chat:</b> appointment suggestions are <b>disabled</b>. Send your first message normally.</p>
              <p style="margin:.45rem 0 0">After that you can:</p>
              <ol style="margin:.25rem 0 0 1.1rem;padding:0;">
                <li><b>Tap “Book counselor”</b> when offered.</li>
                <li><b>Type</b> “book appointment”, “schedule with counselor”.</li>
                <li><b>Safety auto-offer</b> appears on critical keywords (e.g., self-harm). Confirm with “Yes/Okay/Sige”.</li>
              </ol>
            </div>`
          : `<div style="text-align:left;line-height:1.45">
              <p>Open booking via:</p>
              <ol style="margin:.25rem 0 0 1.1rem;padding:0;">
                <li><b>“Book counselor”</b> pill when offered.</li>
                <li><b>Typing</b> “book appointment”, “schedule with counselor”.</li>
                <li><b>Safety auto-offer</b> on critical keywords, then confirm.</li>
              </ol>
            </div>`;

        steps.push({
          element: composer || inputBox || chatArea || document.body,
          popover: { title: 'Appointments from Chat', description: triggerHtml, side: 'top', align: 'center' }
        });

        // 3. Continue to Sidebar Links
        if (linkAnnouncements) steps.push({
          element: linkAnnouncements,
          popover: { title: 'Announcements', description: 'Stay updated with the latest news and alerts.', side: 'right', align: 'center' }
        });

        if (linkChatHist) steps.push({
          element: linkChatHist,
          popover: { title: 'Chat History', description: 'Revisit previous conversations anytime.', side: 'right', align: 'center' }
        });

        if (linkProfile) steps.push({
          element: linkProfile,
          popover: { title: 'Profile', description: 'View/update your details and password.', side: 'right', align: 'center' }
        });

        if (linkSettings) steps.push({
          element: linkSettings,
          popover: { title: 'Settings', description: 'Theme, text size, and other preferences.', side: 'right', align: 'center' }
        });

        if (linkAbout) steps.push({
          element: linkAbout,
          popover: { title: 'About', description: 'How Lumi works and our privacy commitments.', side: 'right', align: 'center' }
        });

        // 4. Header Lastly
        steps.push(...getHeaderSteps());

        return steps;
      }

      function profileSteps() { const steps = []; const editBtn = document.querySelector('[data-edit-profile-btn]'); const readView = document.querySelector('[data-edit-profile-view]'); const editForm = document.querySelector('[data-edit-profile-form]:not(.hidden)'); const nameFld = document.getElementById('edit-name'); const emailFld = document.getElementById('edit-email'); const pwdSection = document.getElementById('update-password-section'); const delBtn = document.getElementById('btn-delete-account'); if (editBtn) steps.push({ element: editBtn, popover: { title: 'Edit Profile', description: 'Update your info.', side: 'bottom', align: 'start' } }); if (editForm && nameFld) steps.push({ element: nameFld, popover: { title: 'Your Name', description: 'Update then save.', side: 'top', align: 'start' } }); if (editForm && emailFld) steps.push({ element: emailFld, popover: { title: 'Email', description: 'Keep this correct.', side: 'top', align: 'start' } }); if (pwdSection) steps.push({ element: pwdSection, popover: { title: 'Update Password', description: 'Use a strong password.', side: 'left', align: 'start' } }); if (delBtn) steps.push({ element: delBtn, popover: { title: 'Delete Account', description: 'Danger zone.', side: 'top', align: 'start' } }); if (!steps.length && readView) steps.push({ element: readView, popover: { title: 'Profile', description: 'View account details.', side: 'left', align: 'start' } }); return steps; }

      function historySteps() {
        const $ = (s, r = document) => r.querySelector(s);
        const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
        const steps = [];

        const searchBox = $('#historySearch') || $('#chat-history-search') || $('input[type="search"][name="q"]');
        const manageBtn = $('#manageToggle') || $('[data-manage-toggle]');
        const bulkBar = $('#bulkBar') || $('[data-bulk-bar]');
        const grid = $('[data-history-grid]') || $('section[aria-label="Chat history"]') || document;
        const firstCard = $('[data-session-card]') || $('.session-card');
        const firstLink = firstCard?.querySelector('form[action*="chat/activate"] button, a[href*="/chat/"]');
        const firstDelete = firstCard?.querySelector('.single-delete-form button[type="submit"], [data-action="delete-session"]');

        const pulse = (el) => {
          try {
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.focus?.({ preventScroll: true });
            el.style.transition = 'transform .12s ease';
            el.style.transform = 'scale(1.03)';
            setTimeout(() => (el.style.transform = ''), 140);
          } catch (_) { }
        };

        if (searchBox) {
          steps.push({
            element: searchBox,
            popover: {
              title: 'Search conversations',
              description: 'Filter by keywords or titles. Typing will auto-filter after a short pause.',
              side: 'bottom',
              align: 'start'
            },
            onNextClick: () => pulse(searchBox)
          });
        }

        if (manageBtn) {
          steps.push({
            element: manageBtn,
            popover: {
              title: 'Manage mode',
              description: 'Enable bulk select to remove multiple conversations at once.',
              side: 'left',
              align: 'center'
            },
            onNextClick: () => {
              if (manageBtn && (bulkBar?.classList.contains('hidden') || bulkBar?.hidden)) {
                manageBtn.click();
              }
              pulse(bulkBar || manageBtn);
            }
          });
        }

        if (firstCard) {
          steps.push({
            element: firstCard,
            popover: {
              title: 'Session card',
              description: 'See the title, last message, and risk tag. Click to open details.',
              side: 'top',
              align: 'start'
            }
          });
        }

        if (firstLink) {
          steps.push({
            element: firstLink,
            popover: {
              title: 'Continue in Chat',
              description: 'Resume this conversation in the chat screen.',
              side: 'top',
              align: 'start'
            },
            onNextClick: () => pulse(firstLink)
          });
        }

        if (firstDelete) {
          steps.push({
            element: firstDelete,
            popover: {
              title: 'Delete (single)',
              description: 'Remove just this conversation. This action cannot be undone.',
              side: 'left',
              align: 'center'
            }
          });
        }

        const emptyState = $('[data-empty-state]') || $('.empty-state');
        if (!steps.length && emptyState) {
          steps.push({
            element: emptyState,
            popover: {
              title: 'No conversations yet',
              description: 'Start a new chat from the main screen to see it listed here.',
              side: 'top',
              align: 'center'
            }
          });
        } else if (!steps.length && document.body) {
          steps.push({
            element: grid || document.body,
            popover: {
              title: 'Chat History',
              description: 'Review and manage your past conversations here.',
              side: 'top',
              align: 'start'
            }
          });
        }

        return steps;
      }
      function settingsSteps() { const steps = []; const darkToggle = document.getElementById('darkModeToggle'); const fontSelect = document.getElementById('fontSizeSelect'); const reduceTgl = document.getElementById('reduceMotionToggle'); const compactTgl = document.getElementById('compactToggle'); const supportBtn = document.querySelector('a[href="{{ route('support.contact') }}"]') || document.querySelector('a[href*="/support"]'); if (darkToggle) steps.push({ element: darkToggle, popover: { title: 'Theme', description: 'Toggle light/dark.', side: 'left', align: 'center' } }); if (fontSelect) steps.push({ element: fontSelect, popover: { title: 'Text Size', description: 'Adjust reading size.', side: 'top', align: 'start' } }); if (reduceTgl) steps.push({ element: reduceTgl, popover: { title: 'Reduce Motion', description: 'Turn off animations.', side: 'left', align: 'center' } }); if (compactTgl) steps.push({ element: compactTgl, popover: { title: 'Compact Layout', description: 'Tighter paddings.', side: 'left', align: 'center' } }); if (supportBtn) steps.push({ element: supportBtn, popover: { title: 'Support', description: 'Contact or report an issue.', side: 'top', align: 'start' } }); return steps; }

      function appointmentSteps() {
        const $ = (s) => document.querySelector(s);
        const $$ = (s) => Array.from(document.querySelectorAll(s));
        const steps = [];

        const historyBtn = $$('a').find(a => /appointment\/history/i.test(a.getAttribute('href') || '') || /View Appointment/i.test(a.textContent || '')) || null;
        const dateChip = $('#dateChip');
        const dateInput = $('#dateInput');
        const calModal = $('#calModal');
        const calDialog = $('#calDialog');
        const calGrid = $('#calGrid');
        const timeGrid = $('#timeGrid');
        const timeEmpty = $('#timeEmpty');
        const consent = $('#consent-cbx');
        const submitBtn = $('#submitBtn');

        if (historyBtn) {
          steps.push({
            element: historyBtn,
            popover: {
              title: 'Appointment History',
              description: 'Review, reschedule, or cancel your bookings.',
              side: 'left', align: 'center'
            }
          });
        }

        if (dateChip) {
          steps.push({
            element: dateChip,
            popover: {
              title: 'Pick a date',
              description: 'Weekends are closed. Click to open the calendar.',
              side: 'bottom', align: 'start'
            },
            onNextClick: () => dateChip.click?.()
          });
        }

        if (timeGrid) {
          steps.push({
            element: timeGrid,
            popover: {
              title: 'Select a time',
              description: 'Available pooled slots appear here after choosing a date. “(Full)” pills are disabled.',
              side: 'top', align: 'start'
            }
          });
        }

        if (consent) {
          steps.push({
            element: consent,
            popover: {
              title: 'Privacy consent',
              description: 'Confirm you agree with LumiCHAT’s privacy policy to proceed.',
              side: 'left', align: 'center'
            }
          });
        }

        if (submitBtn) {
          steps.push({
            element: submitBtn,
            popover: {
              title: 'Confirm appointment',
              description: 'Enabled after you choose a date, a time, and tick consent.',
              side: 'top', align: 'start'
            }
          });
        }

        if (!steps.length && document.body) {
          steps.push({
            element: document.body,
            popover: {
              title: 'Appointments',
              description: 'Pick a weekday, choose an available time, tick consent, then confirm.',
              side: 'top', align: 'center'
            }
          });
        }

        return steps;
      }

      function appointmentHistorySteps() {
        const $ = (s) => document.querySelector(s);
        const $$ = (s) => Array.from(document.querySelectorAll(s));
        const steps = [];

        const headerBand = $('section.rounded-2xl.bg-gradient-to-r');
        const bookNewBtn = $$('a').find(a => /appointment\/create/i.test(a.getAttribute('href') || ''));
        const pdfBtn = $$('a').find(a => /appointment\/history\/export\/pdf/i.test(a.getAttribute('href') || ''));

        const filtersForm = $$('form').find(f => /appointment\/history/i.test((f.getAttribute('action') || '')));
        const periodWrap = filtersForm ? filtersForm.querySelector('.flex.flex-wrap') : null;
        const statusSel = filtersForm ? filtersForm.querySelector('select[name="status"]') : null;
        const searchInput = $('#qInput');
        const resetBtn = filtersForm ? Array.from(filtersForm.querySelectorAll('a')).find(a => /appointment\/history$/.test(a.getAttribute('href') || '')) : null;
        const applyBtn = filtersForm ? filtersForm.querySelector('button[type="submit"]') : null;

        const tableEl = $('table');
        const anyViewBtn = $$('a').find(a => /appointment\/view\/\d+/i.test(a.getAttribute('href') || '') || a.textContent.trim() === 'View');

        if (headerBand) {
          steps.push({
            element: headerBand,
            popover: {
              title: 'Appointment History',
              description: 'Overview of all your bookings with quick stats and actions.',
              side: 'top', align: 'start'
            }
          });
        }

        if (bookNewBtn) {
          steps.push({
            element: bookNewBtn,
            popover: {
              title: 'Book New',
              description: 'Create a new counseling appointment.',
              side: 'left', align: 'center'
            }
          });
        }

        if (pdfBtn) {
          steps.push({
            element: pdfBtn,
            popover: {
              title: 'Download PDF',
              description: 'Export your current view (filters respected) to PDF.',
              side: 'left', align: 'center'
            }
          });
        }

        if (periodWrap) {
          steps.push({
            element: periodWrap,
            popover: {
              title: 'Date filters',
              description: 'Quickly switch between All, Upcoming, Today, This Week, This Month, or Past.',
              side: 'bottom', align: 'start'
            }
          });
        }

        if (statusSel) {
          steps.push({
            element: statusSel,
            popover: {
              title: 'Status filter',
              description: 'Narrow results to Pending, Confirmed, Completed, Canceled, or show all.',
              side: 'top', align: 'start'
            }
          });
        }

        if (searchInput) {
          steps.push({
            element: searchInput,
            popover: {
              title: 'Search counselor',
              description: 'Type a counselor name to filter the list.',
              side: 'top', align: 'start'
            }
          });
        }

        if (applyBtn) {
          steps.push({
            element: applyBtn,
            popover: {
              title: 'Apply filters',
              description: 'Click to run your selected filters. Use Reset to clear.',
              side: 'top', align: 'end'
            }
          });
        } else if (resetBtn) {
          steps.push({
            element: resetBtn,
            popover: {
              title: 'Reset filters',
              description: 'Return to the full list.',
              side: 'top', align: 'end'
            }
          });
        }

        if (tableEl) {
          steps.push({
            element: tableEl,
            popover: {
              title: 'Results',
              description: 'Each row shows counselor, schedule, live countdown, and status.',
              side: 'top', align: 'start'
            }
          });
        }

        if (anyViewBtn) {
          steps.push({
            element: anyViewBtn,
            popover: {
              title: 'View details',
              description: 'Open the appointment for full information and actions.',
              side: 'left', align: 'center'
            }
          });
        }

        if (!steps.length) {
          steps.push({
            element: document.body,
            popover: {
              title: 'Manage History',
              description: 'Filter by date or status, search, and open a record to view details.',
              side: 'top', align: 'center'
            }
          });
        }

        return steps;
      }

      function appointmentViewSteps() {
        const $ = (s, r = document) => r.querySelector(s);
        const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
        const steps = [];

        const card = $('#appointmentCard');

        const closeBtn = $('#btn-appt-close')
          || $$('a').find(a => /\/appointment\/history$/.test(a.getAttribute('href') || '') || /close/i.test(a.textContent || ''));
        const pdfBtn = $$('a').find(a => /show\.export\.pdf/i.test(a.getAttribute('href') || '') || /download\s*pdf/i.test(a.textContent || ''));
        const cancelBtn = $$('button').find(b => /cancel/i.test(b.textContent || ''));

        const statusChip = card ? $('h2 + span.inline-flex', card) : null;
        const countdown = card ? $$('.inline-flex.rounded-full, .rounded-full.inline-flex', card)
          .find(el => /starts in|ago|starting now/i.test(el.textContent || '')) : null;
        const counselorHdr = card ? $$('.text-xs', card).find(h => /counselor/i.test(h.textContent || '')) : null;
        const counselorBox = counselorHdr ? counselorHdr.nextElementSibling : null;
        const scheduleHdr = card ? $$('.text-xs', card).find(h => /scheduled/i.test(h.textContent || '')) : null;
        const scheduleBox = scheduleHdr ? scheduleHdr.nextElementSibling : null;

        if (card) steps.push({ element: card, popover: { title: 'Appointment details', description: 'Booking number, countdown, counselor, and schedule.', side: 'top', align: 'start' } });
        if (statusChip) steps.push({ element: statusChip, popover: { title: 'Status', description: 'Pending, Confirmed, Completed, Canceled, or No-show.', side: 'left', align: 'center' } });
        if (countdown) steps.push({ element: countdown, popover: { title: 'Countdown', description: 'Time until session (or how long since it passed).', side: 'bottom', align: 'start' } });
        if (counselorBox) steps.push({ element: counselorBox, popover: { title: 'Counselor', description: 'Assigned counselor & contacts.', side: 'top', align: 'start' } });
        if (scheduleBox) steps.push({ element: scheduleBox, popover: { title: 'Schedule', description: 'Exact day and start time.', side: 'top', align: 'end' } });

        if (cancelBtn) steps.push({ element: cancelBtn, popover: { title: 'Cancel booking', description: 'Enabled only if the appointment is Pending and in the future.', side: 'top', align: 'start' } });
        if (pdfBtn) steps.push({ element: pdfBtn, popover: { title: 'Download PDF', description: 'Export this appointment.', side: 'top', align: 'end' } });
        if (closeBtn) steps.push({ element: closeBtn, popover: { title: 'Back to History', description: 'Return to your appointment list.', side: 'top', align: 'end' } });

        if (!steps.length) steps.push({ element: document.body, popover: { title: 'Appointment View', description: 'See details, export, cancel if allowed, or go back to history.', side: 'top', align: 'center' } });
        return steps;
      }

      function aboutSteps() { const steps = []; const hero = document.querySelector('.about-hero'); const toc = document.getElementById('about-toc'); const flow = document.getElementById('flow'); const faq = document.getElementById('faq'); const topFab = document.getElementById('about-top'); if (hero) steps.push({ element: hero, popover: { title: 'About LumiCHAT', description: 'Quick overview and purpose.', side: 'bottom', align: 'start' } }); if (toc) steps.push({ element: toc, popover: { title: 'On this page', description: 'Jump between sections; active item updates as you scroll.', side: 'right', align: 'start' } }); if (flow) steps.push({ element: flow, popover: { title: 'How it works', description: 'From message to response timeline.', side: 'top', align: 'start' } }); if (faq) steps.push({ element: faq, popover: { title: 'FAQ', description: 'Common questions and answers.', side: 'top', align: 'start' } }); if (topFab) steps.push({ element: topFab, popover: { title: 'Back to top', description: 'Appears after you scroll.', side: 'left', align: 'center' } }); return steps; }

      const STEP_BUILDERS = {
        chat: chatSteps, 'chat.index': chatSteps,
        'profile.edit': profileSteps, 'chat.history': historySteps,
        'settings.index': settingsSteps, 'about.index': aboutSteps,

        appointment: appointmentSteps,
        'appointment.index': appointmentSteps,
        'appointment.create': appointmentSteps,
        'appointment.history': appointmentHistorySteps,
        'appointment.view': appointmentViewSteps,
        'appointment.show': appointmentViewSteps,

        'counselor.dashboard': counselorDashboardSteps,
        'counselor.availability': counselorAvailabilitySteps,
        'counselor.appointments': counselorAppointmentsSteps,
        'counselor.appointments.show': counselorAppointmentShowSteps,

        // ✅ NEW: walk-ins
        'counselor.walkins': counselorWalkinsSteps,
      };

      async function startPageTour(pageKey = PAGE_KEY) {
        const build = STEP_BUILDERS[pageKey] || STEP_BUILDERS[ROUTE_KEY];
        if (!build) return;
        let steps = build();
        if (!steps?.length) return;
        steps = ensureTerminalMark(steps);
        const D = await ensureDriver(); if (!D) return;
        markPageDone();
        const inst = getDriver(); inst.setSteps(steps); inst.drive();
        try { inst.on?.('highlightStarted', () => { if (!window.__lumiTourMarkedOnce) { markPageDone(); window.__lumiTourMarkedOnce = true; } }); } catch (_) { }
        setTimeout(() => { if (!document.querySelector('.driver-overlay')) markPageDone(); }, 1500);
      }
      window.LumiTour = { start: startPageTour };

      async function maybeShowWelcome() {
        if (localStorage.getItem(WELCOME_SEEN) === '1') return false;
        const isCounselor = APP_ROLE === 'counselor';
        const title = 'Welcome to <span class="lumi-title-accent">LumiCHAT</span> ✨';

        const iconChat = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;
        const iconCalendar = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>`;
        const iconSettings = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>`;
        const iconFlash = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m13 2-2 10h3L11 22l2-10h-3l2-10z"/></svg>`;

        const counselorBody = `
          <div class="lumi-welcome-body">
            <p class="lumi-intro-text">Ready to help? Let's walkthrough your new dashboard:</p>
            <div class="lumi-welcome-list">
              <div class="lumi-welcome-item">
                <div class="lumi-welcome-icon">${iconFlash}</div>
                <div class="lumi-welcome-text"><b>At-a-glance KPI</b> Today's sessions & pending client requests.</div>
              </div>
              <div class="lumi-welcome-item">
                <div class="lumi-welcome-icon">${iconCalendar}</div>
                <div class="lumi-welcome-text"><b>Schedule Power</b> Update your availability in a few clicks.</div>
              </div>
              <div class="lumi-welcome-item">
                <div class="lumi-welcome-icon">${iconSettings}</div>
                <div class="lumi-welcome-text"><b>Live Actions</b> Open details and add diagnostic notes for each case.</div>
              </div>
            </div>
            <div class="lumi-tour-footer">
              Replay this anytime using the purple <b>?</b> in the corner.
            </div>
          </div>`;

        const studentBody = `
          <div class="lumi-welcome-body">
            <p class="lumi-intro-text">LumiCHAT is here when you need to talk. This short tour shows you:</p>
            <div class="lumi-welcome-list">
              <div class="lumi-welcome-item">
                <div class="lumi-welcome-icon">${iconChat}</div>
                <div class="lumi-welcome-text"><b>Smart AI Chat</b> Start fresh, meaningful conversations with Lumi.</div>
              </div>
              <div class="lumi-welcome-item">
                <div class="lumi-welcome-icon">${iconCalendar}</div>
                <div class="lumi-welcome-text"><b>Expert Booking</b> Book or review your professional counseling sessions.</div>
              </div>
              <div class="lumi-welcome-item">
                <div class="lumi-welcome-icon">${iconSettings}</div>
                <div class="lumi-welcome-text"><b>Personal Settings</b> Adjust theme, text size, and your preferences.</div>
              </div>
            </div>
            <div class="lumi-tour-footer">
              Replay this anytime using the purple <b>?</b> in the corner.
            </div>
          </div>`;

        if (window.Swal) {
          const res = await Swal.fire({
            title, html: isCounselor ? counselorBody : studentBody,
            confirmButtonText: 'Start tour',
            showCancelButton: true, cancelButtonText: 'Not now',
            width: 540, background: 'var(--lumi-bg)',
            showClass: { popup: 'animate__animated animate__fadeInUp animate__faster' },
            hideClass: { popup: 'animate__animated animate__fadeOutDown animate__faster' },
            customClass: {
              popup: 'lumi-tour-modal',
              title: 'lumi-tour-title',
              htmlContainer: 'lumi-tour-body',
              actions: 'lumi-tour-actions',
              confirmButton: 'btn-grad',
              cancelButton: 'btn-neutral'
            }
          });
          localStorage.setItem(WELCOME_SEEN, '1');
          if (res.isConfirmed) { await markGlobalDone(); return true; }
          return false;
        } else {
          const ok = confirm(title + '\nStart a quick tour now?');
          localStorage.setItem(WELCOME_SEEN, '1');
          if (ok) { await markGlobalDone(); return true; }
          return false;
        }
      }

      function addRestartFab() {
        let b = document.getElementById('lumi-tour-fab');
        if (!b) {
          b = document.createElement('button');
          b.id = 'lumi-tour-fab'; b.type = 'button';
          b.title = 'Help – Restart tutorial';
          b.setAttribute('aria-label', 'Restart tutorial');
          b.textContent = '?';
          // Try appending to wrapper first for better visibility
          const container = document.querySelector('.layout-wrapper') || document.body;
          container.appendChild(b);
        }
        b.onclick = async () => {
          localStorage.removeItem(PAGE_FLAG(PAGE_KEY));
          await sleep(60);
          await startPageTour(PAGE_KEY);
        };
        const stackFabs = () => document.getElementById('about-top')?.classList.add('fab-above-help');
        stackFabs(); window.addEventListener('resize', stackFabs);
      }

      (function runWhenReady() {
        if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
        else document.addEventListener('DOMContentLoaded', boot, { once: true });
      })();

      async function boot() {
        addRestartFab();
        const pageSeen = localStorage.getItem(PAGE_FLAG(PAGE_KEY)) === '1';
        const globalDone = localStorage.getItem(GLOBAL_DONE) === '1';

        if (!globalDone) {
          const ok = await maybeShowWelcome();
          if (ok) await startPageTour(PAGE_KEY);
          return;
        }
        if (SHOULD_RUN_SERVER && !pageSeen) {
          await startPageTour(PAGE_KEY);
          return;
        }
        // else: manual via "?"
      }
    })();
  </script>
@endif