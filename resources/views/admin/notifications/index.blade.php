@extends('layouts.admin')

@section('title', 'Notifications')
@section('page_title', 'Notifications')

@php
  use Carbon\Carbon;
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  {{-- Header --}}
  <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm screen-only">
    <div class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Notifications</h2>
        <p class="text-white/80 text-sm mt-0.5">View your recent updates and system alerts.</p>
      </div>

      <div class="flex items-center gap-2">
        <button type="button" id="nb-refresh"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-xl bg-white/20 text-white shadow-sm
                       hover:bg-white/30 active:scale-[.98] transition text-sm">
          <svg class="w-4 h-4 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.13-3.36L23 10M1 14l5.36 4.36A9 9 0 0020.49 15"/>
          </svg>
          Refresh
        </button>

        <form method="POST" action="{{ route('admin.notifications.mark_all') }}">
          @csrf
          <button class="inline-flex items-center h-10 px-4 rounded-xl bg-white text-indigo-700 shadow-sm
                         hover:bg-slate-50 active:scale-[.98] transition text-sm">
            Mark all as read
          </button>
        </form>
      </div>
    </div>
  </section>

  @php
    $groups = ['Today'=>[], 'Yesterday'=>[], 'This Week'=>[], 'Last Week'=>[], 'Earlier'=>[]];
    foreach ($notifications as $n) {
        $date = $n->created_at;
        if ($date->isToday())                            $groups['Today'][] = $n;
        elseif ($date->isYesterday())                    $groups['Yesterday'][] = $n;
        elseif ($date->greaterThan(now()->startOfWeek()))          $groups['This Week'][] = $n;
        elseif ($date->greaterThan(now()->subWeek()->startOfWeek())) $groups['Last Week'][] = $n;
        else                                             $groups['Earlier'][] = $n;
    }
  @endphp

  <div id="nb-list" class="rounded-2xl border border-slate-200 bg-white dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
    @foreach ($groups as $label => $items)
      @if(count($items) > 0)
        <div class="px-4 py-2 text-xs font-semibold text-slate-500 bg-slate-50 border-b border-slate-200">
          {{ $label }}
        </div>

        @foreach ($items as $n)
          @php
            $data  = (array)($n->data ?? []);
            $title = $data['title'] ?? 'Notification';
            $body  = $data['body'] ?? '';
            $url   = $data['url']  ?? null;
          @endphp

          <div class="nb-item p-4 flex items-start gap-3 rounded-xl"
               data-row="{{ $n->id }}"
               @if($url) data-url="{{ $url }}" @endif>
            <span class="mt-1 inline-block w-2 h-2 rounded-full {{ $n->read_at ? 'bg-gray-300' : 'bg-indigo-600' }}" data-dot></span>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-sm font-medium">{{ $title }}</div>
                  @if($body)
                    <div class="text-sm text-gray-600 dark:text-gray-300 truncate">{{ $body }}</div>
                  @endif
                </div>

                <div class="shrink-0">
                  @if(!$n->read_at)
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

              {{-- Open button: mark as read THEN navigate --}}
              @if($url)
                <button
                  type="button"
                  class="mt-1 inline-block text-xs underline text-indigo-700 hover:text-indigo-900"
                  data-open
                  data-id="{{ $n->id }}"
                  data-url="{{ $url }}"
                >
                  Open
                </button>
              @endif
            </div>
          </div>
        @endforeach
      @endif
    @endforeach
  </div>

  @if(method_exists($notifications, 'links'))
    <div class="mt-4">{{ $notifications->links() }}</div>
  @endif
</div>

<style>
  .nb-item { transition: background-color .15s ease, transform .15s ease, box-shadow .15s ease; }
  .nb-item:hover { background-color: rgba(99,102,241,0.06); transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
  .dark .nb-item:hover { background-color: rgba(99,102,241,0.15); box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
</style>

<script>
  const NB_MARK_URL_TEMPLATE = @json(route('admin.notifications.mark', ['id' => '__ID__']));

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
      if(!res.ok) throw new Error('Request failed');
      // some mark routes may return empty 204; guard for that:
      let json = null;
      try { json = await res.json(); } catch {}
      return json ?? { ok: true };
    }

    // Mark-only button
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

        const readTag = document.createElement('span');
        readTag.className = 'text-xs text-gray-400';
        readTag.textContent = 'Read';
        btn.replaceWith(readTag);

        // decrement bell badge (if present)
        const badge = document.querySelector('[data-nb-badge]');
        if (badge && !badge.classList.contains('hidden')) {
          const t = (badge.textContent || '').trim().replace('+','');
          const n = parseInt(t,10);
          const next = isFinite(n) ? Math.max(0, n-1) : 0;
          if (next <= 0) { badge.classList.add('hidden'); badge.textContent = ''; }
          else { badge.textContent = next > 9 ? '9+' : String(next); }
        }
      } catch(err){ console.error(err); }
    });

    // Open button → mark then navigate
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-open]');
      if(!btn) return;
      e.preventDefault();

      const id  = btn.getAttribute('data-id');
      const url = btn.getAttribute('data-url');
      if (!url) return;

      try {
        await post(NB_MARK_URL_TEMPLATE.replace('__ID__', id));
      } catch(err) {
        // Even if mark fails, still navigate to avoid blocking UX
        console.warn('Mark read failed, continuing to navigate', err);
      }
      window.location.assign(url);
    });

    document.getElementById('nb-refresh')?.addEventListener('click', () => location.reload());
  })();
</script>
@endsection
