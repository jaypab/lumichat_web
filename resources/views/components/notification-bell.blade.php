@php
  $user = auth()->user();
  $name = $user?->name ?? 'Guest';
@endphp

@props([
  'indexRoute'   => route('notifications.index'),
  'feedRoute'    => route('notifications.feed'),
  'markRoute'    => route('notifications.mark', ['id' => ':id']),  // keep :id placeholder
  'markAllRoute' => route('notifications.mark_all'),
])

<div {{ $attributes->class('relative inline-block align-middle') }} data-nb-root
     data-nb-index="{{ $indexRoute }}"
     data-nb-feed="{{ $feedRoute }}"
     data-nb-mark="{{ $markRoute }}"
     data-nb-markall="{{ $markAllRoute }}">
  <!-- Trigger -->
  <button type="button" data-nb-btn aria-expanded="false" aria-haspopup="true"
          class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-transparent
                 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500
                 dark:hover:bg-gray-800 relative">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
    </svg>
    <span data-nb-badge
          class="absolute -top-1 -right-1 text-[10px] leading-none rounded-full px-1.5 py-0.5 bg-red-600 text-white hidden"></span>
  </button>

  <!-- Panel -->
  <div data-nb-panel class="nb-panel hidden rounded-2xl shadow-xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="px-4 py-3 border-b dark:border-white/10">
      <div class="font-semibold">Notifications</div>
      <div class="text-sm text-gray-500 dark:text-gray-400">Signed in as {{ $name }}</div>
    </div>
    <div class="nb-scroll divide-y dark:divide-white/10" data-nb-list></div>
    <div class="px-4 py-2 border-t dark:border-white/10 text-right">
      <a data-nb-index-link href="{{ $indexRoute }}" class="text-sm underline">View all</a>
    </div>
  </div>
</div>

<style>
  .nb-panel{position:absolute;right:0;top:calc(100% + .5rem);width:24rem;max-width:92vw;z-index:2147483001}
  .nb-scroll{max-height:22rem;overflow:auto}
</style>

<script>
(function () {
  function initBell(root) {
    if (!root || root.dataset.nbReady === '1') return;
    root.dataset.nbReady = '1';

    const btn    = root.querySelector('[data-nb-btn]');
    const panel  = root.querySelector('[data-nb-panel]');
    const listEl = root.querySelector('[data-nb-list]');
    const badge  = root.querySelector('[data-nb-badge]');

    const FEED_URL    = root.getAttribute('data-nb-feed')    || '';
    const MARK_URL_T  = root.getAttribute('data-nb-mark')    || '';
    const MARKALL_URL = root.getAttribute('data-nb-markall') || '';
    const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Helper to validate a route string
    const isValidUrl = (u) => !!u && u !== 'null' && u !== 'undefined';

    let open = false;
    let bootstrapped = false;

    // single-flight guard
    let activeCtrl = null;
    let lastSeq = 0;

    function showPanel(){ panel?.classList.remove('hidden'); btn?.setAttribute('aria-expanded','true'); open = true; }
    function hidePanel(){ panel?.classList.add('hidden');    btn?.setAttribute('aria-expanded','false'); open = false; }
    function togglePanel(){
      if (!panel) return;
      open ? hidePanel() : showPanel();
      if (open && !bootstrapped) {
        bootstrapped = true;
        if (!isValidUrl(FEED_URL)) {
          // Bell rendered without proper routes — show graceful message once
          if (listEl) listEl.innerHTML = '<div class="px-4 py-4 text-sm text-gray-500">Notifications are unavailable on this page.</div>';
          return;
        }
        setLoading();
        refresh({ silent:false });
      }
    }

    function setLoading(){
      if (listEl) listEl.innerHTML = '<div class="px-4 py-4 text-sm text-gray-500">Loading…</div>';
    }

    async function fetchJSON(url, opts = {}, {silent} = {silent:true}){
      if (!isValidUrl(url)) return null; // <- do not issue /null requests

      // cancel older request
      if (activeCtrl) activeCtrl.abort();
      const ctrl = new AbortController();
      activeCtrl = ctrl;

      const seq = ++lastSeq;

      const headers = { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' };
      if (CSRF) headers['X-CSRF-TOKEN'] = CSRF;

      try{
        const res = await fetch(url, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers,
          signal: ctrl.signal,
          ...opts
        });

        let data = null;
        try { data = await res.clone().json(); } catch (_) {}

        if (!res.ok || data === null) throw new Error('HTTP ' + res.status);
        if (seq === lastSeq) return data;
      } catch (e){
        if (ctrl.signal.aborted) return null;
        if (!silent && listEl) {
          listEl.innerHTML = '<div class="px-4 py-4 text-sm text-rose-600">Couldn’t load notifications.</div>';
        }
        return null;
      }
    }

    async function refresh({silent = true} = {}){
      if (!isValidUrl(FEED_URL)) return; // <- no feed, no refresh
      const data = await fetchJSON(FEED_URL, {}, {silent});
      if (!data) return;

      // Badge
      const unread = data.unread_count || 0;
      if (badge){
        if (unread > 0){
          badge.textContent = unread > 9 ? '9+' : unread;
          badge.classList.remove('hidden');
        } else {
          badge.classList.add('hidden');
        }
      }

      // If background refresh, don’t repaint panel list
      if (silent || !listEl) return;

      // Panel list
      listEl.innerHTML = '';
      if (!data.items || !data.items.length){
        listEl.innerHTML = '<div class="px-4 py-6 text-sm text-gray-500">No notifications yet.</div>';
        return;
      }

      for (const n of data.items){
        const row = document.createElement('div');
        row.className = 'px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/40';
        row.innerHTML = `
          <div class="flex items-start gap-3">
            <div class="mt-0.5"><span class="inline-block w-2 h-2 rounded-full ${n.read_at ? 'bg-gray-300' : 'bg-indigo-600'}"></span></div>
            <div class="flex-1 min-w-0">
              <div class="nb-title text-sm font-medium"></div>
              <div class="nb-body text-sm text-gray-600 dark:text-gray-300 truncate"></div>
              <div class="text-xs text-gray-400 mt-0.5" title="${n.created_at_human_full ?? ''}">${n.created_at_human ?? ''}</div>
              ${n.url ? `<a href="${n.url}" class="text-xs underline mt-1 inline-block">Open</a>` : ''}
            </div>
            ${n.read_at ? '' : '<button class="text-xs underline shrink-0" data-mark>Mark read</button>'}
          </div>`;

        row.querySelector('.nb-title').textContent = n.title ?? 'Notification';
        row.querySelector('.nb-body').textContent  = n.body ?? '';

        const markBtn = row.querySelector('[data-mark]');
        if (markBtn && isValidUrl(MARK_URL_T)){
          markBtn.addEventListener('click', async (e)=>{
            e.preventDefault();
            const markUrl = MARK_URL_T.replace(':id', String(n.id));
            await fetchJSON(markUrl, { method:'POST' }, {silent:true});
            refresh({silent:false});
          });
        }
        listEl.appendChild(row);
      }
    }

    // Events
    btn?.addEventListener('click', (e)=>{ e.stopPropagation(); togglePanel(); });
    document.addEventListener('click', (e)=>{ if (open && !root.contains(e.target)) hidePanel(); });
    document.addEventListener('keydown', (e)=>{ if (open && e.key === 'Escape') hidePanel(); });

    // Background badge refresh only when FEED_URL is valid
    let interval = null;
    if (isValidUrl(FEED_URL)) {
      const tick = () => { if (document.visibilityState === 'visible') refresh({silent:true}); };
      interval = setInterval(tick, 60000);
      document.addEventListener('visibilitychange', tick);
      // Prime badge silently once
      refresh({silent:true});
    }

    root.addEventListener('nb:dispose', () => { if (interval) clearInterval(interval); if (activeCtrl) activeCtrl.abort(); });
  }

  function bootstrap(){ document.querySelectorAll('[data-nb-root]').forEach(initBell); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootstrap); else bootstrap();
  window.__NBellReinit = bootstrap;
})();
</script>
