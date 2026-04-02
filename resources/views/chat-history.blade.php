@extends('layouts.app')

@section('title', 'Lumi - Chat History')
@section('page_title', 'Manage History')  

@section('content')
<div class="max-w-4xl mx-auto p-6 space-y-6">

{{-- ======= Enhanced Header (gradient band) ======= --}}
@php
  // total sessions (supports paginator or plain collection)
  $totalSessions = method_exists($sessions, 'total') ? $sessions->total() : (is_countable($sessions) ? count($sessions) : 0);
@endphp

<section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm shadow-indigo-200/50">
  <div class="p-5 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Chat History</h2>
        <p class="text-white/80 text-sm mt-0.5">Review past conversations or resume them in the main chat.</p>
      </div>

      <div class="flex items-center gap-2">
        {{-- records pill --}}
        <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-1.5 text-sm ring-1 ring-white/20">
          <svg class="h-4 w-4 opacity-90" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 1a7 7 0 0 0-7 7v3.126a4 4 0 0 1-.832 2.4L2.6 16.6A1 1 0 0 0 3.4 18h17.2a1 1 0 0 0 .8-1.6l-1.568-3.074A4 4 0 0 1 19 11.126V8a7 7 0 0 0-7-7Zm0 22a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z"/>
          </svg>
          <strong class="font-semibold">{{ $totalSessions }}</strong><span class="opacity-90">records</span>
        </span>

        {{-- Manage toggle --}}
        <button id="manageToggle"
                class="inline-flex items-center gap-2 rounded-xl bg-white text-slate-900 px-4 py-2 text-sm font-semibold shadow-sm hover:bg-slate-50 active:scale-[.99] transition ring-1 ring-slate-200">
          <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round"/>
          </svg>
          Manage & Bulk Delete
        </button>
      </div>
    </div>

    {{-- compact search (shorter) --}}
    <div class="mt-4">
      <div class="relative w-full sm:max-w-[24rem]"> {{-- ~384px on ≥640px screens --}}
        <input id="historySearch" type="text"
              placeholder="Search conversations… (try: sad, depress, anonymous)"
              class="w-full h-10 bg-white border-0 rounded-xl pl-10 pr-3 text-sm text-slate-900 placeholder-slate-400"/>
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"
            viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="11" cy="11" r="7" stroke-width="2"/>
          <path d="M21 21l-4.3-4.3" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
    </div>
  </div>
</section>

{{-- ======= Manage Mode Toolbar (enhanced) ======= --}}
<div id="bulkBar"
     class="hidden sticky top-0 z-30 -mx-6 px-6 py-3 bg-white/90 backdrop-blur border-b border-slate-200 shadow-sm">
  <div class="max-w-4xl mx-auto flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3 flex-wrap">
      <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 text-slate-700 px-3 h-9 text-sm">
        <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M3 6h18M3 12h18M3 18h18" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Selected: <strong id="selCount" class="font-semibold">0</strong>
      </span>

      <button id="selectAllBtn"
              class="h-9 px-3 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200">
        Select All
      </button>

      <button id="clearAllBtn"
              class="h-9 px-3 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200">
        Clear
      </button>

      <span class="text-xs text-slate-500 hidden sm:inline">Tip: Shift-click to select a range • Press Esc to exit</span>
    </div>

    <div class="flex items-center gap-2">
      <button id="deleteSelectedBtn"
              class="h-9 px-4 rounded-lg text-white text-sm bg-rose-400 cursor-not-allowed opacity-60"
              disabled>
        Delete selected
      </button>
      <button id="doneManageBtn"
              class="h-9 px-4 rounded-lg bg-white text-slate-800 ring-1 ring-slate-200 text-sm hover:bg-slate-50">
        Done
      </button>
    </div>
  </div>
</div>

  {{-- Sessions list --}}
  @forelse ($sessions as $session)
    @php
      $latestChat = $session->chats->first();
      $latestMsg  = $latestChat ? $latestChat->message : '';
      
      // Fallback hierarchy: Summary -> Latest Message Snippet -> Placeholder
      $title = $session->topic_summary;
      if (!$title || $title === 'Starting conversation...') {
          $title = $latestMsg ? \Illuminate\Support\Str::limit($latestMsg, 50, '...') : 'Empty conversation';
      }
      
      $last    = optional($session->updated_at)->diffForHumans() ?? 'just now';
      $risk    = $session->risk_level ?? 'low';          // 'low' | 'moderate' | 'high'
      $isAnon  = (int) ($session->is_anonymous ?? 0);

      $riskClass = match($risk) {
        'high'     => 'bg-red-500',
        'moderate' => 'bg-yellow-500',
        default    => 'bg-green-500',
      };
      $riskLabel = ucfirst($risk) . ' risk';
    @endphp

    <div class="session-card card-shell p-5 sm:p-6 transition hover:shadow-md
                flex items-start sm:items-center justify-between gap-4"
         data-session-card
         data-session-id="{{ $session->id }}"
         data-title="{{ strtolower($title) }}"
         data-anon="{{ $isAnon }}">

      <div class="flex items-start gap-3 min-w-0">
        {{-- Hidden checkbox for Manage mode --}}
        <input type="checkbox"
               class="bulk-box mt-1.5 h-4 w-4 text-indigo-600 border-gray-300 rounded hidden
                      dark:border-gray-600"
               value="{{ $session->id }}" />

        {{-- Risk level dot --}}
        <span class="mt-1.5 inline-block w-3 h-3 rounded-full {{ $riskClass }}"
              aria-label="{{ $riskLabel }}"
              title="{{ $riskLabel }}"></span>

        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <h3 class="title-dynamic text-lg font-semibold truncate">{{ $title }}</h3>

            @if ($isAnon === 1)
              {{-- Anonymous badge --}}
              <span class="px-2 py-0.5 text-xs rounded-full border
                           border-gray-300 text-gray-600
                           dark:text-gray-200 dark:border-gray-600"
                    title="This conversation was started in anonymous mode">
                Anonymous
              </span>
            @endif
          </div>

          <p class="muted-dynamic text-xs sm:text-sm">Last interaction: {{ $last }}</p>

          {{-- Link-style Continue in Chat --}}
          <form method="POST" action="{{ route('chat.activate', $session->id) }}" class="mt-2">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-700
                           dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
              Continue in Chat <span aria-hidden="true">→</span>
            </button>
          </form>
        </div>
      </div>

      {{-- Delete on the right --}}
      <form method="POST" action="{{ route('chat.deleteSession', $session->id) }}"
            class="single-delete-form shrink-0">
        @csrf
        @method('DELETE')
        <button type="submit"
                class="px-4 py-2 rounded-lg text-white bg-rose-600 border border-rose-600
                       hover:bg-rose-700 text-sm">
          Delete
        </button>
      </form>
    </div>
  @empty
    <div class="card-shell p-10 text-center">
      <p class="muted-dynamic">No chat sessions found yet.</p>
      <a href="{{ route('chat.new') }}" class="btn-primary mt-4 inline-flex">
        Start your first chat
      </a>
    </div>
  @endforelse

  {{-- Pagination (if using paginate() in controller) --}}
  @if (method_exists($sessions, 'links'))
    <div class="pt-2">{{ $sessions->links() }}</div>
  @endif
</div>

{{-- CSRF for JS fetch --}}
<script>const CSRF_TOKEN = @json(csrf_token());</script>

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function () {
  const manageToggle   = document.getElementById('manageToggle');
  const bulkBar        = document.getElementById('bulkBar');
  const doneManageBtn  = document.getElementById('doneManageBtn');
  const selectAllBtn   = document.getElementById('selectAllBtn');
  const clearAllBtn    = document.getElementById('clearAllBtn');
  const deleteSelBtn   = document.getElementById('deleteSelectedBtn');
  const searchInput    = document.getElementById('historySearch');
  const selCountEl     = document.getElementById('selCount');

  let managing = false;
  let lastChecked = null;  // for Shift+click range selection

  function highlight(card, on) {
    card.classList.toggle('ring-2', on);
    card.classList.toggle('ring-indigo-500/40', on);
    card.classList.toggle('bg-indigo-50/30', on);
  }

  function boxes() {
    return Array.from(document.querySelectorAll('.bulk-box'));
  }

  function visibleCards() {
    return Array.from(document.querySelectorAll('[data-session-card]'))
      .filter(c => !c.classList.contains('hidden'));
  }

  function updateSelectedUI() {
    const selected = boxes().filter(cb => cb.checked).length;
    selCountEl.textContent = String(selected);

    if (selected > 0) {
      deleteSelBtn.disabled = false;
      deleteSelBtn.classList.remove('opacity-60','cursor-not-allowed','bg-rose-400');
      deleteSelBtn.classList.add('bg-rose-600','hover:bg-rose-700');
    } else {
      deleteSelBtn.disabled = true;
      deleteSelBtn.classList.add('opacity-60','cursor-not-allowed','bg-rose-400');
      deleteSelBtn.classList.remove('bg-rose-600','hover:bg-rose-700');
    }
  }

  function setManaging(state) {
    managing = state;
    bulkBar.classList.toggle('hidden', !managing);

    document.querySelectorAll('[data-session-card]').forEach(card => {
      const box = card.querySelector('.bulk-box');
      const singleDelete = card.querySelector('.single-delete-form');
      if (box) box.classList.toggle('hidden', !managing);
      if (singleDelete) singleDelete.classList.toggle('hidden', managing);
      // clear selection + highlight when leaving
      if (!managing && box) { box.checked = false; highlight(card, false); }
    });

    if (managing) {
      manageToggle.innerHTML = `
        <svg class="h-4 w-4 text-rose-500 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <path d="M15 9l-6 6M9 9l6 6" stroke-linecap="round"/>
        </svg>
        Cancel Selection
      `;
      manageToggle.classList.replace('text-slate-900', 'text-rose-600');
    } else {
      manageToggle.innerHTML = `
        <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round"/>
        </svg>
        Manage & Bulk Delete
      `;
      manageToggle.classList.replace('text-rose-600', 'text-slate-900');
    }
    updateSelectedUI();
  }

  // Activate/deactivate manage mode
  manageToggle?.addEventListener('click', () => setManaging(true));
  doneManageBtn?.addEventListener('click', () => setManaging(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && managing) setManaging(false); });

  // Select page (visible)
  selectAllBtn?.addEventListener('click', () => {
    visibleCards().forEach(card => {
      const cb = card.querySelector('.bulk-box');
      if (cb) { cb.checked = true; highlight(card, true); }
    });
    updateSelectedUI();
  });

  // Clear all selections
  clearAllBtn?.addEventListener('click', () => {
    boxes().forEach(cb => {
      cb.checked = false;
      const card = cb.closest('[data-session-card]');
      if (card) highlight(card, false);
    });
    updateSelectedUI();
  });

  // Checkbox interactions: click + Shift+click range + visual highlight
  document.addEventListener('click', (e) => {
    if (!(e.target instanceof HTMLInputElement)) return;
    if (!e.target.classList.contains('bulk-box')) return;

    const cb = e.target;
    const card = cb.closest('[data-session-card]');
    if (card) highlight(card, cb.checked);

    const all = boxes();
    if (e.shiftKey && lastChecked && lastChecked !== cb) {
      const start = all.indexOf(lastChecked);
      const end   = all.indexOf(cb);
      if (start !== -1 && end !== -1) {
        const [lo, hi] = start < end ? [start, end] : [end, start];
        for (let i = lo; i <= hi; i++) {
          all[i].checked = cb.checked;
          const c = all[i].closest('[data-session-card]');
          if (c) highlight(c, cb.checked);
        }
      }
    }
    lastChecked = cb;
    updateSelectedUI();
  });

  // Single delete confirm (unchanged)
  document.querySelectorAll('.single-delete-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      if (window.Swal) {
        e.preventDefault();
        Swal.fire({
          title: 'Delete this conversation?',
          text: 'This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Delete',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          reverseButtons: true
        }).then((r) => { if (r.isConfirmed) form.submit(); });
      } else if (!confirm('Delete this conversation?')) {
        e.preventDefault();
      }
    });
  });

  // Bulk delete loop with confirm
  deleteSelBtn?.addEventListener('click', async () => {
    const ids = Array.from(document.querySelectorAll('.bulk-box:checked')).map(cb => cb.value);
    if (!ids.length) return;

    const proceed = async () => {
      for (const id of ids) {
        await fetch(`{{ url('/chat/session') }}/${id}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ _token: @json(csrf_token()), _method: 'DELETE' })
        });
      }
      window.location.reload();
    };

    if (window.Swal) {
      Swal.fire({
        title: `Delete ${ids.length} selected conversation(s)?`,
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
      }).then(r => { if (r.isConfirmed) proceed(); });
    } else if (confirm(`Delete ${ids.length} selected conversation(s)?`)) {
      proceed();
    }
  });

  // Client-side search keeps working; selection/highlights stay intact
  function filter() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    document.querySelectorAll('[data-session-card]').forEach(card => {
      const title  = (card.getAttribute('data-title') || '');
      const isAnon = card.getAttribute('data-anon') === '1';
      const hay = [title, isAnon ? 'anonymous' : 'identified'].join(' ');
      card.classList.toggle('hidden', q && !hay.includes(q));
    });
  }
  searchInput?.addEventListener('input', filter);
})();
</script>
@endsection
