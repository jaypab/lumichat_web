{{-- resources/views/layouts/partials/tour.blade.php --}}
@if (Auth::check())
  {{-- Driver.js (CDN) --}}
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
  <script defer src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.min.js"></script>

  <style>
    :root{
      --lumi-bg:#fff; --lumi-fg:#0f172a; --lumi-muted:#64748b;
      --lumi-ring:rgba(99,102,241,.38);
      --lumi-grad-a:#7c3aed; --lumi-grad-b:#6366f1;
      --lumi-overlay:rgba(2,6,23,.42);

      /* FAB layout */
      --fab-pad:16px; --fab-size:44px; --fab-gap:12px;
    }
    .dark:root{
      --lumi-bg:#0b1220; --lumi-fg:#e5e7eb; --lumi-muted:#cbd5e1;
      --lumi-ring:rgba(129,140,248,.50); --lumi-overlay:rgba(2,6,23,.60);
    }

    /* Keep About page back-to-top FAB above the help FAB */
    #about-top.fab-above-help{
      right:var(--fab-pad)!important;
      bottom:calc(var(--fab-pad) + var(--fab-size) + var(--fab-gap))!important;
      z-index:2147482999;
    }

    /* Driver look & feel */
    .driver-overlay{ backdrop-filter:none!important; -webkit-backdrop-filter:none!important; background:var(--lumi-overlay)!important; }
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
    .driver-highlighted-element{ box-shadow:0 0 0 5px var(--lumi-ring)!important; border-radius:14px!important; transition:box-shadow .2s ease; }
    .driver-stage-no-animation{ box-shadow:0 26px 60px -22px rgba(2,6,23,.38), 0 0 0 1px rgba(2,6,23,.06)!important; border-radius:16px!important; }

    /* Help/Restart FAB ("?" - always shown) */
    #lumi-tour-fab{
      position:fixed; right:var(--fab-pad); bottom:var(--fab-pad); z-index:2147483000;
      width:var(--fab-size); height:var(--fab-size); border-radius:12px; display:grid; place-items:center;
      background:linear-gradient(90deg,#7c3aed,#6366f1); color:#fff; border:0; cursor:pointer;
      box-shadow:0 12px 28px rgba(99,102,241,.35); transition:transform .15s, box-shadow .15s;
      font-weight:800; font-size:18px; line-height:1;
    }
    #lumi-tour-fab:hover{ transform:translateY(-1px); box-shadow:0 16px 34px rgba(99,102,241,.42); }
    html.dark #lumi-tour-fab{ box-shadow:0 12px 28px rgba(129,140,248,.35); }
    @media (max-width:640px){ :root{ --fab-pad:12px; } }

    /* SweetAlert2 modal polish (optional if Swal is present) */
    .swal2-popup.lumi-tour-modal{
      border-radius:22px!important; box-shadow:0 22px 60px rgba(2,6,23,.32)!important;
      padding:1.2rem 1.3rem 1.5rem!important; background:var(--lumi-bg)!important;
    }
    .swal2-title.lumi-tour-title{ font-weight:800!important; letter-spacing:.2px!important; color:var(--lumi-fg)!important; }
    .swal2-html-container.lumi-tour-body{ color:var(--lumi-muted)!important; font-size:.98rem!important; margin-top:.25rem!important; }
    .swal2-confirm.btn-grad{
      background:linear-gradient(90deg,var(--lumi-grad-a),var(--lumi-grad-b))!important; color:#fff!important;
      border-radius:.8rem!important; padding:.7rem 1.2rem!important; box-shadow:0 12px 28px rgba(99,102,241,.30)!important;
    }
    .swal2-cancel.btn-neutral{
      background:#eef2ff!important; color:#1f2937!important; border-radius:.8rem!important; padding:.6rem 1.1rem!important;
    }
    .swal2-deny.btn-outline{
      background:transparent!important; color:#111827!important; border:2px solid rgba(99,102,241,.3)!important;
      border-radius:.8rem!important; padding:.6rem 1.1rem!important;
    }
    .dark .swal2-cancel.btn-neutral{ background:#1f2937!important; color:#e5e7eb!important; }
    .dark .swal2-deny.btn-outline{ color:#e5e7eb!important; border-color:rgba(165,180,252,.45)!important; }
  </style>

  <script>
  (function(){
    /* ----------------------- Constants ----------------------- */
    const USER_ID   = @json(Auth::id());
    const ROUTE_KEY = @json(Route::currentRouteName() ?? 'unknown');
    const SHOULD_RUN_SERVER = @json($shouldRunTour ?? false); // optional server toggle

    // Storage keys (per-user, per-browser)
    const GLOBAL_DONE  = `lumi_tour_done_v1_${USER_ID}`;      // has the user ever completed/started a tour?
    const WELCOME_SEEN = `lumi_tour_welcome_seen_v1_${USER_ID}`; // has the welcome been shown at least once?
    const PAGE_FLAG    = (pageKey)=> `lumi_tour_page_v1_${pageKey}_${USER_ID}`;

    const sleep = (ms)=>new Promise(r=>setTimeout(r,ms));
    const $ = (s)=>document.querySelector(s);

    /* ----------------------- Page grouping ----------------------- */
     function normalizePageKey(route){
      if (!route) return 'unknown';

      // 1) Exact matches FIRST (so they don't get collapsed by broader rules)
      if (route === 'chat.history')    return 'chat.history';
      if (route === 'appointment.history') return 'appointment.history';
      if (route === 'profile.edit')    return 'profile.edit';
      if (route === 'about.index')     return 'about.index';
      if (route === 'settings.index')  return 'settings.index';

      // 2) Friendly groupings for families of pages
      if (route.startsWith('appointment.')) return 'appointment';
      if (route === 'home' || route === 'dashboard' || route === 'chat.index' || route === 'chat.show') {
        return 'chat';
      }
      if (route.startsWith('profile.')) return 'profile';
      if (route.startsWith('about.'))   return 'about';

      // 3) Fallback to the raw route name
      return route;
    }
    const PAGE_KEY = normalizePageKey(ROUTE_KEY);

    /* ----------------------- Driver.js helpers ----------------------- */
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

    // ✅ Ensure page is marked done even if user hits "Done" or closes the tour
    try {
        drv.on?.('destroyed', () => markPageDone());
        drv.on?.('reset',     () => markPageDone());
    } catch(_) {}

    return drv;
    }


    async function markGlobalDone(){
      localStorage.setItem(GLOBAL_DONE,'1');
      try{
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        // Optional: ping backend that user completed/started the tour
        await fetch(@json(route('tour.complete')), { method:'POST', headers:{ 'X-CSRF-TOKEN':token, 'Accept':'application/json' } });
      }catch(_){}
    }

    function markPageDone(){
        localStorage.setItem(PAGE_FLAG(PAGE_KEY),'1');
    }

    /* ---- Ensure last step marks page as done ---- */
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

    /* ----------------------- STEP BUILDERS ----------------------- */
    function chatSteps(){
      const newChatBtn = $('.nav-pill[data-new-chat="1"]');
      const navHistory = $('#nav-chat-history');
      const navSettings= $('#nav-settings');
      const chatArea   = $('#chat-messages') || $('#lb-scope') || $('.msg-area') || $('[data-chat-area]');
      const chatInput  = document.querySelector('#chat-form textarea, #chat-form input[type="text"], textarea[name="message"], input[name="message"]');

      const steps = [];
      if (newChatBtn) steps.push({ element:newChatBtn, popover:{ title:'New Chat', description:'Start a fresh conversation from here anytime.', side:'top', align:'start' }});
      if (navHistory) steps.push({ element:navHistory, popover:{ title:'Chat History', description:'Review and revisit your previous conversations here.', side:'right', align:'center' }});
      if (navSettings) steps.push({ element:navSettings, popover:{ title:'Settings', description:'Adjust theme, text size, and accessibility preferences.', side:'right', align:'center' }});
      if (chatArea)   steps.push({ element:chatArea, popover:{ title:'Messages', description:'Your conversation appears here. Lumi won’t diagnose, but will guide & refer when needed.', side:'left', align:'start' }});
      if (chatInput)  steps.push({ element:chatInput, popover:{ title:'Type & Send', description:'Type your message then press Enter. Use “New Chat” to change the topic.', side:'top', align:'start' }});
      return steps;
    }

    function profileSteps(){
      const editBtn    = document.querySelector('[data-edit-profile-btn]');
      const readView   = document.querySelector('[data-edit-profile-view]');
      const editForm   = document.querySelector('[data-edit-profile-form]:not(.hidden)');
      const nameFld    = document.getElementById('edit-name');
      const emailFld   = document.getElementById('edit-email');
      const pwdSection = document.getElementById('update-password-section');
      const delBtn     = document.getElementById('btn-delete-account');

      const steps = [];
      if (editBtn) steps.push({ element:editBtn, popover:{ title:'Edit Profile', description:'Update your profile information here.', side:'bottom', align:'start' }});
      if (editForm && nameFld) steps.push({ element:nameFld, popover:{ title:'Your Name', description:'Update your display name, then save.', side:'top', align:'start' }});
      if (editForm && emailFld) steps.push({ element:emailFld, popover:{ title:'Email', description:'Make sure this is correct so we can reach you.', side:'top', align:'start' }});
      if (pwdSection) steps.push({ element:pwdSection, popover:{ title:'Update Password', description:'Use strong passwords. The strength meter and checklist help.', side:'left', align:'start' }});
      if (delBtn) steps.push({ element:delBtn, popover:{ title:'Delete Account', description:'Danger zone — this permanently removes your account.', side:'top', align:'start' }});
      if (!steps.length && readView) steps.push({ element:readView, popover:{ title:'Profile', description:'View your account details here.', side:'left', align:'start' }});
      return steps;
    }

    function historySteps(){
      const searchBox   = document.getElementById('historySearch');
      const manageBtn   = document.getElementById('manageToggle');
      const bulkBar     = document.getElementById('bulkBar');
      const firstCard   = document.querySelector('[data-session-card]');
      const firstLink   = firstCard?.querySelector('form[action*="chat/activate"] button');
      const firstDelete = firstCard?.querySelector('.single-delete-form button[type="submit"]');

      const steps = [];
      if (searchBox) steps.push({ element:searchBox, popover:{ title:'Search conversations', description:'Filter by keywords (e.g., “sad”, “depress”, or “anonymous”).', side:'bottom', align:'start' }});
      if (manageBtn) steps.push({ element: manageBtn, popover:{ title:'Manage mode', description:'Bulk-select multiple chats to delete. Click to open the toolbar.', side:'left', align:'center' }, onNextClick: () => { if (manageBtn && bulkBar?.classList.contains('hidden')) manageBtn.click(); }});
      if (bulkBar)   steps.push({ element: bulkBar, popover:{ title:'Bulk actions', description:'Select all, clear selection, or delete selected conversations.', side:'bottom', align:'start' }});
      if (firstCard) steps.push({ element:firstCard, popover:{ title:'Session card', description:'Shows title, risk level, and last interaction.', side:'top', align:'start' }});
      if (firstLink) steps.push({ element:firstLink, popover:{ title:'Continue in Chat', description:'Resume this conversation in the main chat.', side:'top', align:'start' }});
      if (firstDelete) steps.push({ element:firstDelete, popover:{ title:'Delete (single)', description:'Removes this conversation permanently (with a confirmation).', side:'left', align:'center' }});
      if (!steps.length && document.body) steps.push({ element:document.body, popover:{ title:'Chat History', description:'Review and manage your past conversations here.', side:'top', align:'start' }});
      return steps;
    }

    function settingsSteps(){
      const darkToggle  = document.getElementById('darkModeToggle');
      const fontSelect  = document.getElementById('fontSizeSelect');
      const reduceTgl   = document.getElementById('reduceMotionToggle');
      const compactTgl  = document.getElementById('compactToggle');
      const supportBtn  = document.querySelector('a[href="{{ route('support.contact') }}"]') || document.querySelector('a[href*="/support"]');

      const steps = [];
      if (darkToggle) steps.push({ element:darkToggle, popover:{ title:'Theme', description:'Toggle light/dark mode.', side:'left', align:'center' }});
      if (fontSelect) steps.push({ element:fontSelect, popover:{ title:'Text Size', description:'Adjust overall reading size.', side:'top', align:'start' }});
      if (reduceTgl)  steps.push({ element:reduceTgl,  popover:{ title:'Reduce Motion', description:'Turn off animations and transitions.', side:'left', align:'center' }});
      if (compactTgl) steps.push({ element:compactTgl, popover:{ title:'Compact Layout', description:'Tighter paddings for smaller screens.', side:'left', align:'center' }});
      if (supportBtn) steps.push({ element:supportBtn, popover:{ title:'Support', description:'Contact the team or report an issue.', side:'top', align:'start' }});
      return steps;
    }

    function aboutSteps(){
      const hero   = document.querySelector('.about-hero');
      const toc    = document.getElementById('about-toc');
      const flow   = document.getElementById('flow');
      const faq    = document.getElementById('faq');
      const topFab = document.getElementById('about-top');

      const steps = [];
      if (hero) steps.push({ element:hero, popover:{ title:'About LumiCHAT', description:'A quick overview of what LumiCHAT is and who it’s for.', side:'bottom', align:'start' }});
      if (toc)  steps.push({ element:toc,  popover:{ title:'On this page', description:'Jump between sections; the active item updates as you scroll.', side:'right', align:'start' }});
      if (flow) steps.push({ element:flow, popover:{ title:'How it works', description:'A step-by-step timeline from message to response.', side:'top', align:'start' }});
      if (faq)  steps.push({ element:faq,  popover:{ title:'FAQ', description:'Common questions with concise answers.', side:'top', align:'start' }});
      if (topFab) steps.push({ element:topFab, popover:{ title:'Back to top', description:'Appears after you scroll. Click to return to top smoothly.', side:'left', align:'center' }});
      return steps;
    }

    /* ---- Appointment: booking page ---- */
    function appointmentSteps(){
      const historyBtn = document.querySelector('a[href*="/appointment/history"]');
      const dateInput  = document.getElementById('dateInput');
      const openDate   = document.getElementById('openDateBtn');
      const timeGrid   = document.getElementById('timeGrid');
      const consent    = document.getElementById('consent-cbx');
      const submitBtn  = document.querySelector('form[action*="appointment/store"] button[type="submit"]')
                      || document.querySelector('form[action*="appointment"] button[type="submit"]');

      const steps = [];
      if (historyBtn) steps.push({ element:historyBtn, popover:{ title:'Appointment History', description:'Review, reschedule, or cancel here after booking.', side:'left', align:'center' }});
      if (dateInput)  steps.push({ element:dateInput,  popover:{ title:'Pick a date', description:'Choose your preferred date. Weekends are closed (Mon–Fri).', side:'bottom', align:'start' }});
      if (openDate)   steps.push({ element:openDate,   popover:{ title:'Open calendar', description:'Click to open the date picker.', side:'left', align:'center' }, onNextClick: () => openDate.click?.() });
      if (timeGrid)   steps.push({ element:timeGrid,   popover:{ title:'Select a time', description:'Available slots appear here after you pick a date. Click a pill to select.', side:'top', align:'start' }});
      if (consent)    steps.push({ element:consent,    popover:{ title:'Privacy consent', description:'Please confirm you agree with LumiCHAT’s privacy policy.', side:'left', align:'center' }});
      if (submitBtn)  steps.push({ element:submitBtn,  popover:{ title:'Confirm appointment', description:'Submit your booking. An admin will assign a counselor.', side:'top', align:'start' }});
      if (!steps.length && document.body) steps.push({ element:document.body, popover:{ title:'Appointments', description:'Book a date and time, then confirm.', side:'top', align:'start' }});
      return steps;
    }

    function appointmentHistorySteps(){
      const list = document.querySelector('[data-appt-list], .appt-list, main');
      return [{
        element: list || document.body,
        popover: { title:'Your appointments', description:'Manage upcoming and past bookings here (reschedule or cancel).', side:'top', align:'start' }
      }];
    }

    const STEP_BUILDERS = {
      chat: chatSteps,                        // grouped
      'chat.index': chatSteps,
      'profile.edit': profileSteps,
      'chat.history': historySteps,
      'settings.index': settingsSteps,
      'about.index': aboutSteps,
      appointment: appointmentSteps,          // grouped for booking/create variants
      'appointment.index': appointmentSteps,
      'appointment.create': appointmentSteps,
      'appointment.history': appointmentHistorySteps,
    };

    /* ----------------------- Start a page tour ----------------------- */
    async function startPageTour(pageKey = PAGE_KEY){
    const build = STEP_BUILDERS[pageKey] || STEP_BUILDERS[ROUTE_KEY];
    if (!build) return;
    let steps = build();
    if (!steps || !steps.length) return;
    steps = ensureTerminalMark(steps);

    const D = await ensureDriver();
    if (!D) return;

    // ✅ Immediately mark this page as "done" to prevent re-triggers on reloads
    markPageDone();

    const inst = getDriver();
    inst.setSteps(steps);
    inst.drive();

    // (Optional extra safety: mark on first highlight)
    try {
        inst.on?.('highlightStarted', ()=>{
        if (!window.__lumiTourMarkedOnce){ markPageDone(); window.__lumiTourMarkedOnce = true; }
        });
    } catch(_) {}

    setTimeout(()=>{ if (!document.querySelector('.driver-overlay')) markPageDone(); }, 1500);
    }

    /* ----------------------- Welcome (first-time only) ----------------------- */
    async function maybeShowWelcome(){
      if (localStorage.getItem(WELCOME_SEEN) === '1') return false;

      if (window.Swal){
        const res = await Swal.fire({
          title: 'Welcome to LumiCHAT ✨',
          html: `<div style="text-align:left;line-height:1.45">
                  <p>We’ll give you a quick tour so you know where everything is.</p>
                  <ul style="margin:.5rem 0 0 1rem;padding:0;list-style:disc;">
                    <li>Where to start a new chat</li>
                    <li>Where your messages appear</li>
                    <li>How to tweak settings</li>
                  </ul>
                 </div>`,
          confirmButtonText:'Start tour',
          showCancelButton:true,
          cancelButtonText:'Not now',
          width:560,
          background:'var(--lumi-bg)',
          customClass:{
            popup:'lumi-tour-modal', title:'lumi-tour-title',
            htmlContainer:'lumi-tour-body',
            confirmButton:'btn-grad', cancelButton:'btn-neutral'
          }
        });
        localStorage.setItem(WELCOME_SEEN,'1');
        if (res.isConfirmed) { await markGlobalDone(); return true; }
        return false;
      } else {
        const ok = confirm('Welcome to LumiCHAT! Start a quick tour now?');
        localStorage.setItem(WELCOME_SEEN,'1');
        if (ok) { await markGlobalDone(); return true; }
        return false;
      }
    }

    /* ----------------------- FAB ("?") = refresh tutorial ----------------------- */
    function addRestartFab(){
      if (document.getElementById('lumi-tour-fab')) return;
      const b = document.createElement('button');
      b.id='lumi-tour-fab'; b.type='button'; b.title='Help – Restart tutorial';
      b.setAttribute('aria-label','Restart tutorial');
      b.textContent='?';
      document.body.appendChild(b);

      // Keep About's ↑ Top above the help FAB
      const stackFabs = () => document.getElementById('about-top')?.classList.add('fab-above-help');
      stackFabs(); window.addEventListener('resize', stackFabs);

      // On click: clear page-done flag and immediately re-run this page’s tour
      b.addEventListener('click', async ()=>{
        localStorage.removeItem(PAGE_FLAG(PAGE_KEY));
        await sleep(60);
        await startPageTour(PAGE_KEY);
    });

    }

    /* ----------------------- Boot ----------------------- */
    (function runWhenReady(){
      if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
      else document.addEventListener('DOMContentLoaded', boot, { once:true });
    })();

    async function boot(){
      addRestartFab();

      const pageSeen    = localStorage.getItem(PAGE_FLAG(PAGE_KEY)) === '1';
      const globalDone = localStorage.getItem(GLOBAL_DONE) === '1';

      // 1) First-time user: show welcome, then start tour on the current page
      if (!globalDone) {
        const ok = await maybeShowWelcome();
        if (ok) await startPageTour(PAGE_KEY);
        return;
      }

      // 2) Optional server nudges (e.g., after big UI changes)
      if (SHOULD_RUN_SERVER && !pageSeen) {
        await startPageTour(PAGE_KEY);
        return;
    }

      // 3) Otherwise: do nothing automatically once a page is marked done.
      //    The "?" FAB lets users restart anytime.
    }
  })();
  </script>
@endif
