@php
  use Illuminate\Support\Facades\Route;
  $user = auth()->user();
  $name = $user?->name ?? 'Guest';
@endphp

@unless(Route::has('notifications.feed') && Route::has('notifications.mark') && Route::has('notifications.index'))
  @php return; @endphp
@endunless

<style>
  /* High z-index, independent of your app’s menus */
  .nb-panel{
    position:absolute; right:0; top:calc(100% + .5rem);
    width:24rem; max-width:92vw;
    z-index:2147483001;
  }
  .nb-scroll{ max-height:22rem; overflow:auto; }
</style>

<div {{ $attributes->class('relative inline-block align-middle') }} data-nb-root>
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
    <div class="nb-scroll divide-y dark:divide-white/10" data-nb-list>
      <!-- will be filled by JS -->
    </div>
    <div class="px-4 py-2 border-t dark:border-white/10 text-right">
      <a href="{{ route('notifications.index') }}" class="text-sm underline">View all</a>
    </div>
  </div>
</div>

<script>
(() => {
  const root   = document.currentScript.previousElementSibling?.matches?.('[data-nb-root]')
              ? document.currentScript.previousElementSibling
              : document.currentScript.closest('[data-nb-root]');
  if(!root) return;

  const btn    = root.querySelector('[data-nb-btn]');
  const panel  = root.querySelector('[data-nb-panel]');
  const listEl = root.querySelector('[data-nb-list]');
  const badge  = root.querySelector('[data-nb-badge]');

  let bootstrapped = false;
  let open = false;

  function showPanel(){
    panel.classList.remove('hidden');
    btn.setAttribute('aria-expanded','true');
    open = true;
  }
  function hidePanel(){
    panel.classList.add('hidden');
    btn.setAttribute('aria-expanded','false');
    open = false;
  }
  function togglePanel(){
    open ? hidePanel() : showPanel();
    if(open && !bootstrapped){ bootstrapped = true; loadingUI(); refresh(); }
  }

  function loadingUI(){
    listEl.innerHTML = '<div class="px-4 py-4 text-sm text-gray-500">Loading…</div>';
  }

  async function fetchJSON(url, opts){
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', ...opts });
    if(!res.ok) throw new Error('Request failed: ' + res.status);
    return await res.json();
  }

  async function refresh(){
    let data;
    try{
      data = await fetchJSON('{{ route('notifications.feed') }}');
    }catch(e){
      console.error(e);
      listEl.innerHTML = '<div class="px-4 py-4 text-sm text-rose-600">Couldn’t load notifications.</div>';
      return;
    }

    listEl.innerHTML = '';
    if(!data.items.length){
      listEl.innerHTML = '<div class="px-4 py-6 text-sm text-gray-500">No notifications yet.</div>';
    }else{
      for (const n of data.items){
        const row = document.createElement('div');
        row.className = 'px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/40';
        row.innerHTML = `
          <div class="flex items-start gap-3">
            <div class="mt-0.5">
              <span class="inline-block w-2 h-2 rounded-full ${n.read_at ? 'bg-gray-300' : 'bg-indigo-600'}"></span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium">${n.title ?? 'Notification'}</div>
              <div class="text-sm text-gray-600 dark:text-gray-300 truncate">${n.body ?? ''}</div>
              <div class="text-xs text-gray-400 mt-0.5" title="${n.created_at_human_full}">${n.created_at_human}</div>
            </div>
            ${n.read_at ? '' : '<button class="text-xs underline shrink-0" data-mark>Mark read</button>'}
          </div>`;
        const btn = row.querySelector('[data-mark]');
        if(btn){
          btn.addEventListener('click', async (e)=>{
            e.preventDefault();
            try{
              await fetchJSON('{{ route('notifications.mark', ['id' => '__ID__']) }}'.replace('__ID__', String(n.id)), {
                method:'POST',
                headers:{ 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
              });
              refresh();
            }catch(err){ console.error(err); }
          });
        }
        listEl.appendChild(row);
      }
    }

    // Badge
    const unread = data.unread_count || 0;
    if(unread > 0){
      badge.textContent = unread > 9 ? '9+' : unread;
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }
  }

  // Open/close handlers
  btn.addEventListener('click', (e)=>{ e.stopPropagation(); togglePanel(); });
  document.addEventListener('click', (e)=>{ if(open && !root.contains(e.target)) hidePanel(); });
  document.addEventListener('keydown', (e)=>{ if(open && e.key === 'Escape') hidePanel(); });

  // Periodic refresh
  setInterval(()=>{ if(document.visibilityState === 'visible') refresh(); }, 60000);
  document.addEventListener('visibilitychange', ()=>{ if(document.visibilityState === 'visible') refresh(); });
})();
</script>
