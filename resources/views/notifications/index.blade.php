@extends($layout ?? 'layouts.app')

@section('title', 'Notifications')
@section('page_title', 'Notifications')

@php
  /** @var \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator $notifications */
@endphp

@section('content')
  <div class="max-w-4xl mx-auto p-4 sm:p-6">

    {{-- Actions bar --}}
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Your notifications</h2>

      <div class="flex items-center gap-2">
        {{-- Refresh --}}
        <button type="button" id="nb-refresh"
                class="inline-flex items-center gap-2 h-9 px-3 rounded-xl border border-gray-200
                       dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50
                       dark:hover:bg-gray-800 text-sm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.13-3.36L23 10M1 14l5.36 4.36A9 9 0 0020.49 15"/>
          </svg>
          Refresh
        </button>

        {{-- Mark all --}}
        <form method="POST" action="{{ route('notifications.mark_all') }}" class="inline-block">
          @csrf
          <button class="inline-flex items-center h-9 px-3 rounded-xl border border-gray-200 dark:border-gray-700
                         bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm">
            Mark all as read
          </button>
        </form>
      </div>
    </div>

    {{-- List --}}
    <div id="nb-list" class="divide-y dark:divide-white/10 rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900">
      @forelse ($notifications as $n)
        @php
          $data  = $n->data ?? [];
          $title = $data['title'] ?? 'Notification';
          $body  = $data['body']  ?? '';
          $url   = $data['url']   ?? null;   
        @endphp

        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800/40 flex items-start gap-3 {{ $url ? 'cursor-pointer' : '' }}"
            data-row="{{ $n->id }}"
            @if($url) data-url="{{ $url }}" @endif></div>

        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800/40 flex items-start gap-3" data-row="{{ $n->id }}">
          {{-- unread dot --}}
          <span class="mt-1 inline-block w-2 h-2 rounded-full {{ $n->read_at ? 'bg-gray-300' : 'bg-indigo-600' }}" data-dot></span>

          <div class="flex-1 min-w-0">
            {{-- top row: title + action aligned right --}}
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-medium">{{ $title }}</div>
                @if($body)
                  <div class="text-sm text-gray-600 dark:text-gray-300 truncate">{{ $body }}</div>
                @endif
              </div>

              <div class="shrink-0">
                @if(is_null($n->read_at))
                  <button class="text-xs text-indigo-600 hover:underline" data-mark data-id="{{ $n->id }}">
                    Mark read
                  </button>
                @else
                  <span class="text-xs text-gray-400">Read</span>
                @endif
              </div>
            </div>

            <div class="text-xs text-gray-400 mt-1" title="{{ $n->created_at->toDayDateTimeString() }}">
              {{ $n->created_at->diffForHumans() }}
            </div>
          </div>
        </div>
      @empty
        <div class="p-6 text-sm text-gray-500">You have no notifications yet.</div>
      @endforelse
    </div>

    {{-- Pagination (if controller paginates) --}}
    @if(method_exists($notifications, 'links'))
      <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
  </div>

  {{-- ===== JS (emitted as raw text; Blade won’t parse inside) ===== --}}
  <script>
    const NB_MARK_URL_TEMPLATE = @json(route('notifications.mark', ['id' => '__ID__']));
  </script>
  @php
    echo <<<'SCRIPT'
<script>
(function(){
  async function post(url, body){
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body
    });
    if(!res.ok) throw new Error('Request failed: ' + res.status);
    return res.json();
  }

  // ---- bell badge helpers (keep the header badge in sync) ----
  function getBadge(){
    return document.querySelector('[data-nb-badge]'); // from <x-notification-bell>
  }
  function parseBadgeCount(el){
    if(!el || el.classList.contains('hidden')) return 0;
    const t = (el.textContent || '').trim().replace('+','');
    const n = parseInt(t, 10);
    return Number.isFinite(n) ? n : 0;
  }
  function bumpBadge(delta){
    const badge = getBadge();
    if(!badge) return;
    const cur = parseBadgeCount(badge);
    const next = Math.max(0, cur + delta);
    if(next <= 0){
      badge.classList.add('hidden');
      badge.textContent = '';
    }else{
      badge.textContent = next > 9 ? '9+' : String(next);
      badge.classList.remove('hidden');
    }
  }

// Row click: if a URL exists, mark-as-read (if unread) then navigate
document.addEventListener('click', async (e) => {
  const row = e.target.closest('[data-row]');
  if (!row) return;

  const url = row.getAttribute('data-url');
  if (!url) return; // not linkable

  const markBtn = row.querySelector('[data-mark]');
  if (markBtn) {
    markBtn.click();                    // reuses your existing mark-read flow
    setTimeout(() => location.href = url, 60); // small delay so UI updates
  } else {
    location.href = url;
  }
});


  // ---- Mark single as read (event delegation) ----
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-mark]');
    if(!btn) return;
    e.preventDefault();

    const id = btn.getAttribute('data-id');
    try{
      await post(NB_MARK_URL_TEMPLATE.replace('__ID__', id));

      const row = document.querySelector(`[data-row="${id}"]`);
      row?.querySelector('[data-dot]')?.classList.remove('bg-indigo-600');
      row?.querySelector('[data-dot]')?.classList.add('bg-gray-300');

      // swap button -> "Read" tag
      const readTag = document.createElement('span');
      readTag.className = 'text-xs text-gray-400';
      readTag.textContent = 'Read';
      btn.replaceWith(readTag);

      // keep the bell badge in sync
      bumpBadge(-1);
    }catch(err){
      console.error(err);
    }
  });

  // Refresh button: just reload the page
  document.getElementById('nb-refresh')?.addEventListener('click', () => location.reload());
})();
</script>
SCRIPT;
  @endphp
@endsection
