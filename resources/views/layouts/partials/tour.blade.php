{{-- resources/views/layouts/partials/tour.blade.php --}}
@if (Auth::check())
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
  <script defer src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.min.js"></script>

  <style>
    :root{
      --lumi-bg:#fff; --lumi-fg:#0f172a; --lumi-muted:#64748b;
      --lumi-ring:rgba(99,102,241,.38);
      --lumi-grad-a:#7c3aed; --lumi-grad-b:#6366f1;
      --lumi-overlay:rgba(2,6,23,.42);
      --fab-pad:16px; --fab-size:44px; --fab-gap:12px;
    }
    .dark:root{
      --lumi-bg:#0b1220; --lumi-fg:#e5e7eb; --lumi-muted:#cbd5e1;
      --lumi-ring:rgba(129,140,248,.50); --lumi-overlay:rgba(2,6,23,.60);
    }
    #about-top.fab-above-help{ right:var(--fab-pad)!important; bottom:calc(var(--fab-pad) + var(--fab-size) + var(--fab-gap))!important; z-index:2147482999; }

    .driver-overlay{ background:var(--lumi-overlay)!important; }
    .driver-popover{
      border-radius:18px!important; background:var(--lumi-bg)!important; color:var(--lumi-fg)!important;
      border:1px solid rgba(148,163,184,.16)!important;
      box-shadow:0 28px 60px -24px rgba(2,6,23,.36), 0 0 0 1px rgba(2,6,23,.05), 0 16px 32px rgba(99,102,241,.08)!important;
      padding:14px 14px 12px!important; min-width:260px; max-width:360px;
    }
    .driver-popover-title{ font-size:16px!important; font-weight:800!important; margin:2px 0 6px!important; letter-spacing:.2px; }
    .driver-popover-description{ font-size:13px!important; color:var(--lumi-muted)!important; line-height:1.45!important; }
    .driver-popover-progress-text{ font-size:11px!important; color:var(--lumi-muted)!important; letter-spacing:.2px; }
    .driver-popover-progress{ height:6px!important; background:rgba(99,102,241,.18)!important; border-radius:999px!important; margin:8px 0 0!important; overflow:hidden; }
    .driver-popover-progress>span{ background:linear-gradient(90deg,var(--lumi-grad-a),var(--lumi-grad-b))!important; }
    .driver-popover-footer{ gap:8px!important; }
    .driver-popover-btn{
      border-radius:12px!important; padding:8px 12px!important; font-weight:700!important; font-size:12px!important;
      border:1px solid rgba(148,163,184,.25)!important; background:transparent!important; color:var(--lumi-fg)!important;
    }
    .driver-popover-btn-primary{ border:0!important; color:#fff!important; background:linear-gradient(90deg,#7c3aed,#6366f1)!important; box-shadow:0 10px 24px rgba(99,102,241,.35)!important; }
    .driver-popover-close-btn{ top:8px!important; right:8px!important; width:28px!important; height:28px!important; border-radius:9px!important; color:var(--lumi-muted)!important; }
    .driver-highlighted-element{ box-shadow:0 0 0 5px var(--lumi-ring)!important; border-radius:14px!important; }

    #lumi-tour-fab{
      position:fixed; right:var(--fab-pad); bottom:var(--fab-pad); z-index:2147483000;
      width:var(--fab-size); height:var(--fab-size); border-radius:12px; display:grid; place-items:center;
      background:linear-gradient(90deg,#7c3aed,#6366f1); color:#fff; border:0; cursor:pointer;
      box-shadow:0 12px 28px rgba(99,102,241,.35); transition:transform .15s, box-shadow .15s;
      font-weight:800; font-size:18px; line-height:1;
    }
    #lumi-tour-fab:hover{ transform:translateY(-1px); box-shadow:0 16px 34px rgba(99,102,241,.42); }
    html.dark #lumi-tour-fab{ box-shadow:0 12px 28px rgba(129,140,248,.35); }

    .swal2-popup.lumi-tour-modal{ border-radius:22px!important; box-shadow:0 22px 60px rgba(2,6,23,.32)!important; padding:1.2rem 1.3rem 1.5rem!important; background:var(--lumi-bg)!important; }
    .swal2-title.lumi-tour-title{ font-weight:800!important; letter-spacing:.2px!important; color:var(--lumi-fg)!important; }
    .swal2-html-container.lumi-tour-body{ color:var(--lumi-muted)!important; font-size:.98rem!important; margin-top:.25rem!important; }
    .swal2-confirm.btn-grad{ background:linear-gradient(90deg,var(--lumi-grad-a),var(--lumi-grad-b))!important; color:#fff!important; border-radius:.8rem!important; padding:.7rem 1.2rem!important; }
    .swal2-cancel.btn-neutral{ background:#eef2ff!important; color:#1f2937!important; border-radius:.8rem!important; padding:.6rem 1.1rem!important; }
    .dark .swal2-cancel.btn-neutral{ background:#1f2937!important; color:#e5e7eb!important; }
  </style>

  <script>
  (function(){
    const USER_ID   = @json(Auth::id());
    const ROUTE_KEY = @json(Route::currentRouteName() ?? 'unknown');
    const SHOULD_RUN_SERVER = @json($shouldRunTour ?? false);

    const USER_NAME  = @json(Auth::user()->name ?? 'Counselor');
    const FIRST_NAME = (USER_NAME || '').trim().split(/\s+/)[0] || 'Counselor';

    const APP_ROLE = (document.documentElement.getAttribute('data-app') || '').toLowerCase() || 'student';

    const GLOBAL_DONE  = `lumi_tour_done_v1_${APP_ROLE}_${USER_ID}`;
    const WELCOME_SEEN = `lumi_tour_welcome_seen_v1_${APP_ROLE}_${USER_ID}`;
    const PAGE_FLAG    = (pageKey)=> `lumi_tour_page_v1_${APP_ROLE}_${pageKey}_${USER_ID}`;

    const sleep = (ms)=>new Promise(r=>setTimeout(r,ms));
    const $     = (s)=>document.querySelector(s);

    function normalizePageKey(route){
    if (!route) return 'unknown';

    // --- Counselor routes (put specific ones BEFORE the generic startsWith) ---
    if (route === 'counselor.dashboard')                  return 'counselor.dashboard';
    if (route === 'counselor.appointments.show')          return 'counselor.appointments.show';
    if (route === 'counselor.appointments.index')         return 'counselor.appointments';
    if (route.startsWith('counselor.appointments')) {
      return /show|\/\d+/.test(window.location.pathname) 
        ? 'counselor.appointments.show'
        : 'counselor.appointments';
    }

    if (route.startsWith('counselor.availability'))       return 'counselor.availability';
    if (route.startsWith('counselor.'))                   return 'counselor';

    // --- Student routes (unchanged) ---
    if (route === 'chat.history')        return 'chat.history';
    if (route === 'appointment.history') return 'appointment.history';
    if (route === 'profile.edit')        return 'profile.edit';
    if (route === 'about.index')         return 'about.index';
    if (route === 'settings.index')      return 'settings.index';
    if (route.startsWith('appointment.'))return 'appointment';
    if (route === 'home' || route === 'dashboard' || route === 'chat.index' || route === 'chat.show')
                                          return 'chat';
    if (route.startsWith('profile.'))    return 'profile';
    if (route.startsWith('about.'))      return 'about';

    return route;
  }
    const PAGE_KEY = normalizePageKey(ROUTE_KEY);

    async function ensureDriver(maxWait = 4000){
      const start = Date.now();
      while (!window.driver && Date.now() - start < maxWait) { await sleep(100); }
      return window.driver || null;
    }

    let drv;
    function getDriver(){
      if (drv) return drv;
      const isDark = document.documentElement.classList.contains('dark');
      drv = window.driver({
        showProgress:true, animate:true, allowClose:true, smoothScroll:true, stagePadding:6,
        overlayOpacity: isDark ? 0.50 : 0.32,
        nextBtnText:'Next →', prevBtnText:'← Previous', doneBtnText:'Done',
        popoverClass:'lumi-tour'
      });
      try{ drv.on?.('destroyed',()=>markPageDone()); drv.on?.('reset',()=>markPageDone()); }catch(_){}
      return drv;
    }

    async function markGlobalDone(){
      localStorage.setItem(GLOBAL_DONE,'1');
      try{
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        await fetch(@json(route('tour.complete')), { method:'POST', headers:{ 'X-CSRF-TOKEN':token, 'Accept':'application/json' } });
      }catch(_){}
    }
    function markPageDone(){ localStorage.setItem(PAGE_FLAG(PAGE_KEY),'1'); }
    function ensureTerminalMark(steps){
      if (!steps?.length) return steps;
      const last = steps[steps.length-1];
      if (last.onNextClick) {
        const prev = last.onNextClick;
        last.onNextClick = async (...a)=>{ try{ await prev(...a); } finally { markPageDone(); } };
      } else {
        last.onNextClick = markPageDone;
      }
      return steps;
    }

    /* ---------- STEP BUILDERS ---------- */
   function counselorAppointmentShowSteps(){
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
          return !a.closest('nav, aside, [role="navigation"]'); // exclude sidebar/nav
        }) || null;
      }

      // --- Action buttons via hidden input[name="action"] ---
      const actionBtn = (action) =>
        document.querySelector(`form input[name="action"][value="${action}"]`)
          ?.closest('form')?.querySelector('button[type="submit"]') || null;

      const btnConfirm = actionBtn('confirm');
      const btnStart   = actionBtn('start');
      const btnDone    = actionBtn('done');

      // ✅ Robust No-Show detection (route OR visible text)
      const btnNoShow =
        document.querySelector('form[action*="no_show"] button[type="submit"]') ||
        Array.from(document.querySelectorAll('form button[type="submit"], a, button')).find(el =>
          /no[-\s]?show/i.test((el.textContent || el.value || '').trim())
        ) || null;

      // Diagnosis area
      const diagForm = document.querySelector('form[action*="/appointments"][action*="report"]');
      const diagBox  = diagForm || document.querySelector('.rounded-2xl.bg-indigo-50\\/40, .rounded-2xl.bg-indigo-50');

      // Pulse helper
      const pulse = (el) => {
        try {
          el?.scrollIntoView({ behavior:'smooth', block:'center' });
          el?.focus?.({ preventScroll:true });
          if (!el) return;
          el.style.transition = 'transform .12s ease';
          el.style.transform  = 'scale(1.03)';
          setTimeout(()=>{ el.style.transform=''; }, 140);
        } catch(_) {}
      };

      // Steps
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

      // ✅ Now included, with resilient selector:
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

    /* Register this page in your STEP_BUILDERS map */
    window.__LumiTour = window.__LumiTour || {};
    if (window.STEP_BUILDERS) {
      STEP_BUILDERS['counselor.appointments.show'] = counselorAppointmentShowSteps;
    }

    function counselorDashboardSteps(){
      const S=[];
      const hero=document.getElementById('c-dash-hero');
      const quickAvail=document.getElementById('c-quick-availability');
      const quickAppt=document.getElementById('c-quick-appointments');
      const clockbox=document.getElementById('c-dash-clockbox');
      const kpiToday=document.getElementById('c-kpi-today');
      const kpiPending=document.getElementById('c-kpi-pending');
      const kpiQueue=document.getElementById('c-kpi-queue');
      const kpiHours=document.getElementById('c-kpi-openhours');
      const upHead=document.getElementById('c-upcoming-head');
      const upList=document.getElementById('c-upcoming-list');
      const upAll=document.getElementById('c-upcoming-viewall');
      const notes=document.getElementById('c-dash-notes');
      const tip=document.getElementById('c-dash-tip');

      if (hero) S.push({element:hero,popover:{title:'Counselor Dashboard',description:'Your home base: quick actions, today’s KPIs, and upcoming appointments at a glance.',side:'bottom',align:'start'}});
      if (quickAvail) S.push({element:quickAvail,popover:{title:'Manage Availability',description:'Set recurring hours or date-specific overrides.',side:'top',align:'start'}});
      if (quickAppt) S.push({element:quickAppt,popover:{title:'View Appointments',description:'Go to the full appointments list to take action.',side:'top',align:'start'}});
      if (clockbox) S.push({element:clockbox,popover:{title:'Live Clock',description:'Quick time reference while scheduling.',side:'left',align:'center'}});
      if (kpiToday) S.push({element:kpiToday,popover:{title:'Today’s Appointments',description:'Count + trend for today.',side:'top',align:'start'}});
      if (kpiPending) S.push({element:kpiPending,popover:{title:'Pending',description:'Awaiting your confirmation or action.',side:'top',align:'start'}});
      if (kpiQueue) S.push({element:kpiQueue,popover:{title:'Queue',description:'Load across Pending, Confirmed, Ongoing.',side:'top',align:'start'}});
      if (kpiHours) S.push({element:kpiHours,popover:{title:'Open Hours',description:'Total available hour-slots this week.',side:'top',align:'start'}});
      if (upHead) S.push({element:upHead,popover:{title:'Upcoming Appointments',description:'Next sessions with date/time & status.',side:'bottom',align:'start'}});
      if (upList) S.push({element:upList,popover:{title:'Open a session',description:'Click a row to view details or act.',side:'top',align:'start'}});
      if (upAll) S.push({element:upAll,popover:{title:'View all',description:'Full list, filters, bulk ops.',side:'left',align:'center'}});
      if (notes) S.push({element:notes,popover:{title:'Notes',description:'Quick personal reminders (coming soon).',side:'left',align:'center'}});
      if (tip) S.push({element:tip,popover:{title:'Pro tip',description:'Use Weekday Quick Editor and per-date overrides.',side:'top',align:'start'}});
      return S;
    }

    function counselorAvailabilitySteps(){
      const S=[];
      const calMonth=$('#calMonth'), calPrev=$('#calPrev'), calNext=$('#calNext'), calGrid=$('#calGrid'),
            calUse=$('#calUse'), calClear=$('#calClear');
      const quickList=document.querySelector('section.border-t ul[role="list"]');
      const firstRow=quickList?.querySelector('li');
      const updBtn=firstRow?.querySelector('button[data-action="open-hour-modal"]');
      const disBtn=firstRow?.querySelector('button[data-action="disable-weekday"]');
      const tiles=$('#hourTiles');

      if (calMonth) S.push({element:calMonth,popover:{title:'Interactive Calendar',description:'Weekends are disabled. Click a weekday to edit.',side:'bottom',align:'center'}});
      if (calPrev && calNext) S.push({element:calNext,popover:{title:'Navigate months',description:'Move forward/back. Arrow keys also work.',side:'top',align:'center'}});
      if (calGrid) S.push({element:calGrid,popover:{title:'Pick a date',description:'Set date-specific open/blocked hours.',side:'top',align:'start'}});
      if (calUse) S.push({element:calUse,popover:{title:'Use selected date',description:'Confirm the date & open the hour editor.',side:'left',align:'center'}});
      if (calClear) S.push({element:calClear,popover:{title:'Clear',description:'Unselect the date (Esc also works).',side:'left',align:'center'}});
      if (quickList) S.push({element:quickList,popover:{title:'Weekday Quick Editor',description:'Edit recurring windows (Mon–Fri) or disable a weekday.',side:'top',align:'start'}});
      if (updBtn) S.push({element:updBtn,popover:{title:'Update hours',description:'Open the tile editor to toggle hourly windows.',side:'left',align:'center'}});
      if (disBtn) S.push({element:disBtn,popover:{title:'Disable weekday',description:'Block 9–12 & 1–5 for this weekday.',side:'left',align:'center'}});
      if (tiles) S.push({element:tiles,popover:{title:'Tile editor',description:'Click to disable/enable hours. Lunch (12–1) is locked.',side:'top',align:'start'}});
      return S;
    }

    function counselorAppointmentsSteps(){
      const steps = [];

      // Find the filter form in a resilient way
      const searchBox  = document.getElementById('q');
      const filterForm = searchBox?.closest('form')
                      || document.querySelector('form[action*="/counselor/appointments"]')
                      || document.querySelector('form[action*="counselor.appointments.index"]');

      const statusSel  = filterForm?.querySelector('select[name="status"]');
      const periodSel  = filterForm?.querySelector('select[name="period"]');

      // Robust Apply selector:
      // 1) type="submit" inside the form
      // 2) OR a button whose text includes "apply"
      let applyBtn = filterForm?.querySelector('button[type="submit"]');
      if (!applyBtn) {
        applyBtn = Array.from(filterForm?.querySelectorAll('button, a, input') || [])
          .find(el => /apply/i.test(el.textContent || el.value || ''));
      }

      // First link inside the form that looks like a reset (your Reset button)
      const resetBtn   = filterForm?.querySelector('a[href]');

      // Table + useful targets
      const table   = document.querySelector('table');
      const rows    = table?.querySelectorAll('tbody tr');
      // first "View" action link in the table
      const firstView = table?.querySelector('tbody a[href*="/counselor/appointments/"]');

      if (statusSel) steps.push({ element: statusSel, popover: {
        title: 'Filter by Status',
        description: 'Narrow appointments by their current state (Pending, Confirmed, Ongoing, etc.).',
        side: 'bottom', align: 'start'
      }});

      if (periodSel) steps.push({ element: periodSel, popover: {
        title: 'Date Range',
        description: 'Quickly filter upcoming, today’s, or past appointments.',
        side: 'bottom', align: 'start'
      }});

      if (searchBox) steps.push({ element: searchBox, popover: {
        title: 'Search Students',
        description: 'Find a student by name or email. Typing auto-searches after a short pause.',
        side: 'bottom', align: 'start'
      }});

      // ✅ New, explicit Apply step with safe focus pulse (no actual submit)
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
            // tiny pulse
            applyBtn.style.transition = 'transform .12s ease';
            applyBtn.style.transform = 'scale(1.03)';
            setTimeout(() => (applyBtn.style.transform = ''), 140);
          } catch(_) {}
        }
      });

      if (resetBtn) steps.push({ element: resetBtn, popover: {
        title: 'Reset Filters',
        description: 'Show everything again.',
        side: 'top', align: 'center'
      }});

      if (rows?.length) steps.push({ element: rows[0], popover: {
        title: 'Appointments Table',
        description: 'Each row shows the student, date/time, booking details, and status.',
        side: 'top', align: 'start'
      }});

      if (firstView) steps.push({ element: firstView, popover: {
        title: 'View Details',
        description: 'Open an appointment to see notes and actions (confirm, cancel, mark done).',
        side: 'left', align: 'center'
      }});

      return steps;
    }

    /* student page helpers kept for reuse */
    function chatSteps(){ const steps=[]; const newChatBtn=$('.nav-pill[data-new-chat="1"]'); const navHistory=$('#nav-chat-history'); const navSettings=$('#nav-settings'); const chatArea=$('#chat-messages')||$('#lb-scope')||$('.msg-area')||$('[data-chat-area]'); const chatInput=document.querySelector('#chat-form textarea, #chat-form input[type="text"], textarea[name="message"], input[name="message"]'); if (newChatBtn) steps.push({element:newChatBtn,popover:{title:'New Chat',description:'Start a fresh conversation here.',side:'top',align:'start'}}); if (navHistory) steps.push({element:navHistory,popover:{title:'Chat History',description:'Review previous conversations.',side:'right',align:'center'}}); if (navSettings) steps.push({element:navSettings,popover:{title:'Settings',description:'Theme and preferences.',side:'right',align:'center'}}); if (chatArea) steps.push({element:chatArea,popover:{title:'Messages',description:'Your conversation appears here.',side:'left',align:'start'}}); if (chatInput) steps.push({element:chatInput,popover:{title:'Type & Send',description:'Press Enter to send.',side:'top',align:'start'}}); return steps; }
    function profileSteps(){ const steps=[]; const editBtn=document.querySelector('[data-edit-profile-btn]'); const readView=document.querySelector('[data-edit-profile-view]'); const editForm=document.querySelector('[data-edit-profile-form]:not(.hidden)'); const nameFld=document.getElementById('edit-name'); const emailFld=document.getElementById('edit-email'); const pwdSection=document.getElementById('update-password-section'); const delBtn=document.getElementById('btn-delete-account'); if (editBtn) steps.push({element:editBtn,popover:{title:'Edit Profile',description:'Update your info.',side:'bottom',align:'start'}}); if (editForm&&nameFld) steps.push({element:nameFld,popover:{title:'Your Name',description:'Update then save.',side:'top',align:'start'}}); if (editForm&&emailFld) steps.push({element:emailFld,popover:{title:'Email',description:'Keep this correct.',side:'top',align:'start'}}); if (pwdSection) steps.push({element:pwdSection,popover:{title:'Update Password',description:'Use a strong password.',side:'left',align:'start'}}); if (delBtn) steps.push({element:delBtn,popover:{title:'Delete Account',description:'Danger zone.',side:'top',align:'start'}}); if (!steps.length&&readView) steps.push({element:readView,popover:{title:'Profile',description:'View account details.',side:'left',align:'start'}}); return steps; }
    function historySteps(){ const steps=[]; const searchBox=document.getElementById('historySearch'); const manageBtn=document.getElementById('manageToggle'); const bulkBar=document.getElementById('bulkBar'); const firstCard=document.querySelector('[data-session-card]'); const firstLink=firstCard?.querySelector('form[action*="chat/activate"] button'); const firstDelete=firstCard?.querySelector('.single-delete-form button[type="submit"]'); if (searchBox) steps.push({element:searchBox,popover:{title:'Search conversations',description:'Filter by keywords.',side:'bottom',align:'start'}}); if (manageBtn) steps.push({element:manageBtn,popover:{title:'Manage mode',description:'Bulk-select to delete.',side:'left',align:'center'},onNextClick:()=>{ if (manageBtn&&bulkBar?.classList.contains('hidden')) manageBtn.click(); }}); if (bulkBar) steps.push({element:bulkBar,popover:{title:'Bulk actions',description:'Select all / clear / delete.',side:'bottom',align:'start'}}); if (firstCard) steps.push({element:firstCard,popover:{title:'Session card',description:'Title, risk, last message.',side:'top',align:'start'}}); if (firstLink) steps.push({element:firstLink,popover:{title:'Continue in Chat',description:'Resume this conversation.',side:'top',align:'start'}}); if (firstDelete) steps.push({element:firstDelete,popover:{title:'Delete (single)',description:'Permanent removal.',side:'left',align:'center'}}); if (!steps.length&&document.body) steps.push({element:document.body,popover:{title:'Chat History',description:'Review and manage past chats.',side:'top',align:'start'}}); return steps; }
    function settingsSteps(){ const steps=[]; const darkToggle=document.getElementById('darkModeToggle'); const fontSelect=document.getElementById('fontSizeSelect'); const reduceTgl=document.getElementById('reduceMotionToggle'); const compactTgl=document.getElementById('compactToggle'); const supportBtn=document.querySelector('a[href="{{ route('support.contact') }}"]') || document.querySelector('a[href*="/support"]'); if (darkToggle) steps.push({element:darkToggle,popover:{title:'Theme',description:'Toggle light/dark.',side:'left',align:'center'}}); if (fontSelect) steps.push({element:fontSelect,popover:{title:'Text Size',description:'Adjust reading size.',side:'top',align:'start'}}); if (reduceTgl) steps.push({element:reduceTgl,popover:{title:'Reduce Motion',description:'Turn off animations.',side:'left',align:'center'}}); if (compactTgl) steps.push({element:compactTgl,popover:{title:'Compact Layout',description:'Tighter paddings.',side:'left',align:'center'}}); if (supportBtn) steps.push({element:supportBtn,popover:{title:'Support',description:'Contact or report an issue.',side:'top',align:'start'}}); return steps; }
    function appointmentSteps(){ const steps=[]; const historyBtn=document.querySelector('a[href*="/appointment/history"]'); const dateInput=document.getElementById('dateInput'); const openDate=document.getElementById('openDateBtn'); const timeGrid=document.getElementById('timeGrid'); const consent=document.getElementById('consent-cbx'); const submitBtn=document.querySelector('form[action*="appointment/store"] button[type="submit"]') || document.querySelector('form[action*="appointment"] button[type="submit"]'); if (historyBtn) steps.push({element:historyBtn,popover:{title:'Appointment History',description:'Review, reschedule, or cancel.',side:'left',align:'center'}}); if (dateInput) steps.push({element:dateInput,popover:{title:'Pick a date',description:'Weekends closed (Mon–Fri).',side:'bottom',align:'start'}}); if (openDate) steps.push({element:openDate,popover:{title:'Open calendar',description:'Open the date picker.',side:'left',align:'center'},onNextClick:()=>openDate.click?.()}); if (timeGrid) steps.push({element:timeGrid,popover:{title:'Select a time',description:'Available slots appear here.',side:'top',align:'start'}}); if (consent) steps.push({element:consent,popover:{title:'Privacy consent',description:'Please confirm.',side:'left',align:'center'}}); if (submitBtn) steps.push({element:submitBtn,popover:{title:'Confirm appointment',description:'Submit your booking.',side:'top',align:'start'}}); if (!steps.length&&document.body) steps.push({element:document.body,popover:{title:'Appointments',description:'Book a date and time, then confirm.',side:'top',align:'start'}}); return steps; }
    function appointmentHistorySteps(){ const list=document.querySelector('[data-appt-list], .appt-list, main'); return [{element:list||document.body,popover:{title:'Your appointments',description:'Manage upcoming and past bookings.',side:'top',align:'start'}}]; }
    /* ✅ MISSING BEFORE: define this to avoid ReferenceError */
    function aboutSteps(){ const steps=[]; const hero=document.querySelector('.about-hero'); const toc=document.getElementById('about-toc'); const flow=document.getElementById('flow'); const faq=document.getElementById('faq'); const topFab=document.getElementById('about-top'); if (hero) steps.push({element:hero,popover:{title:'About LumiCHAT',description:'Quick overview and purpose.',side:'bottom',align:'start'}}); if (toc) steps.push({element:toc,popover:{title:'On this page',description:'Jump between sections; active item updates as you scroll.',side:'right',align:'start'}}); if (flow) steps.push({element:flow,popover:{title:'How it works',description:'From message to response timeline.',side:'top',align:'start'}}); if (faq) steps.push({element:faq,popover:{title:'FAQ',description:'Common questions and answers.',side:'top',align:'start'}}); if (topFab) steps.push({element:topFab,popover:{title:'Back to top',description:'Appears after you scroll.',side:'left',align:'center'}}); return steps; }

    const STEP_BUILDERS = {
      chat: chatSteps, 'chat.index': chatSteps,
      'profile.edit': profileSteps, 'chat.history': historySteps,
      'settings.index': settingsSteps, 'about.index': aboutSteps,
      appointment: appointmentSteps, 'appointment.index': appointmentSteps,
      'appointment.create': appointmentSteps, 'appointment.history': appointmentHistorySteps,
      'counselor.dashboard': counselorDashboardSteps,
      'counselor.availability': counselorAvailabilitySteps,
      'counselor.appointments': counselorAppointmentsSteps,
      'counselor.appointments.show': counselorAppointmentShowSteps,
    };

    async function startPageTour(pageKey = PAGE_KEY){
      const build = STEP_BUILDERS[pageKey] || STEP_BUILDERS[ROUTE_KEY];
      if (!build) return;
      let steps = build();
      if (!steps?.length) return;
      steps = ensureTerminalMark(steps);
      const D = await ensureDriver(); if (!D) return;
      markPageDone();
      const inst = getDriver(); inst.setSteps(steps); inst.drive();
      try{ inst.on?.('highlightStarted',()=>{ if (!window.__lumiTourMarkedOnce){ markPageDone(); window.__lumiTourMarkedOnce = true; } }); }catch(_){}
      setTimeout(()=>{ if (!document.querySelector('.driver-overlay')) markPageDone(); }, 1500);
    }
    window.LumiTour = { start: startPageTour };

    async function maybeShowWelcome(){
      if (localStorage.getItem(WELCOME_SEEN) === '1') return false;
      const isCounselor = APP_ROLE === 'counselor';
      const title = isCounselor ? `Welcome, Counselor ${FIRST_NAME} ✨` : 'Welcome to LumiCHAT ✨';
      const body  = isCounselor
        ? `<div style="text-align:left;line-height:1.45"><p>We’ll give you a quick tour of your dashboard:</p><ul style="margin:.5rem 0 0 1rem;padding:0;list-style:disc;"><li>Quick actions for Availability & Appointments</li><li>Reading KPIs at a glance</li><li>Navigating upcoming sessions</li></ul></div>`
        : `<div style="text-align:left;line-height:1.45"><p>We’ll give you a quick tour so you know where everything is.</p><ul style="margin:.5rem 0 0 1rem;padding:0;list-style:disc;"><li>Where to start a new chat</li><li>Where your messages appear</li><li>How to tweak settings</li></ul></div>`;
      if (window.Swal){
        const res = await Swal.fire({
          title, html: body, confirmButtonText:'Start tour',
          showCancelButton:true, cancelButtonText:'Not now',
          width:560, background:'var(--lumi-bg)',
          customClass:{ popup:'lumi-tour-modal', title:'lumi-tour-title', htmlContainer:'lumi-tour-body', confirmButton:'btn-grad', cancelButton:'btn-neutral' }
        });
        localStorage.setItem(WELCOME_SEEN,'1');
        if (res.isConfirmed) { await markGlobalDone(); return true; }
        return false;
      } else {
        const ok = confirm(title + '\nStart a quick tour now?');
        localStorage.setItem(WELCOME_SEEN,'1');
        if (ok) { await markGlobalDone(); return true; }
        return false;
      }
    }

    function addRestartFab(){
      let b = document.getElementById('lumi-tour-fab');
      if (!b) {
        b = document.createElement('button');
        b.id='lumi-tour-fab'; b.type='button';
        b.title='Help – Restart tutorial';
        b.setAttribute('aria-label','Restart tutorial');
        b.textContent='?';
        document.body.appendChild(b);
      }
      b.onclick = async () => {
        localStorage.removeItem(PAGE_FLAG(PAGE_KEY));
        await sleep(60);
        await startPageTour(PAGE_KEY);
      };
      const stackFabs = () => document.getElementById('about-top')?.classList.add('fab-above-help');
      stackFabs(); window.addEventListener('resize', stackFabs);
    }

    (function runWhenReady(){
      if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
      else document.addEventListener('DOMContentLoaded', boot, { once:true });
    })();

    async function boot(){
      addRestartFab();
      const pageSeen   = localStorage.getItem(PAGE_FLAG(PAGE_KEY)) === '1';
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
