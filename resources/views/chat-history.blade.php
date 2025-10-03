@extends('layouts.app')

@section('title', 'Lumi - Chat History')
@section('page_title', 'Manage History')  

@section('content')
<div class="max-w-4xl mx-auto p-6 space-y-6">

  {{-- Header --}}
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h2 class="title-dynamic text-2xl font-semibold">Your Chat History</h2>
      <p class="muted-dynamic text-sm">Review past conversations or resume them in the main chat.</p>
    </div>

    <div class="flex items-center gap-2 w-full sm:w-auto">
      {{-- Search --}}
      <div class="relative flex-1 sm:flex-initial sm:w-72">
        <input
          id="historySearch"
          type="text"
          placeholder="Search conversations… (try: sad, depress, anonymous)"
          class="input-dynamic w-full pl-10 pr-3 py-2 text-sm"
        />
        <svg class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
        </svg>
      </div>

      {{-- Manage toggle --}}
      <button id="manageToggle" class="btn-secondary">Manage</button>
    </div>
  </div>

  {{-- Bulk actions toolbar --}}
  <div id="bulkBar"
       class="hidden sticky top-0 z-10 -mx-6 px-6 py-3 bg-white/90 backdrop-blur border-b border-gray-100
              dark:bg-gray-800/90 dark:border-gray-700">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button id="selectAllBtn"
                class="px-3 py-1.5 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200
                       dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
          Select all
        </button>
        <button id="clearAllBtn"
                class="px-3 py-1.5 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200
                       dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
          Clear
        </button>
      </div>
      <div class="flex items-center gap-2">
        <button id="deleteSelectedBtn"
                class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm hover:bg-rose-700">
          Delete selected
        </button>
        <button id="doneManageBtn" class="btn-secondary">Done</button>
      </div>
    </div>
  </div>

  {{-- Sessions list --}}
  @forelse ($sessions as $session)
    @php
      $title   = $session->topic_summary ?: 'Untitled conversation';
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
  /* Refs */
  const manageToggle  = document.getElementById('manageToggle');
  const bulkBar       = document.getElementById('bulkBar');
  const doneManageBtn = document.getElementById('doneManageBtn');
  const selectAllBtn  = document.getElementById('selectAllBtn');
  const clearAllBtn   = document.getElementById('clearAllBtn');
  const deleteSelBtn  = document.getElementById('deleteSelectedBtn');
  const searchInput   = document.getElementById('historySearch');

  let managing = false;

  /* Manage mode */
  function setManaging(state) {
    managing = state;
    bulkBar.classList.toggle('hidden', !managing);

    document.querySelectorAll('[data-session-card]').forEach(card => {
      const box = card.querySelector('.bulk-box');
      const singleDelete = card.querySelector('.single-delete-form');
      if (box) box.classList.toggle('hidden', !managing);
      if (singleDelete) singleDelete.classList.toggle('hidden', managing);
    });

    manageToggle.textContent = managing ? 'Managing…' : 'Manage';
  }

  manageToggle?.addEventListener('click', () => setManaging(true));
  doneManageBtn?.addEventListener('click', () => {
    document.querySelectorAll('.bulk-box:checked').forEach(cb => cb.checked = false);
    setManaging(false);
  });

  /* Select/Clear */
  selectAllBtn?.addEventListener('click', () => {
    document.querySelectorAll('.bulk-box').forEach(cb => cb.checked = true);
  });
  clearAllBtn?.addEventListener('click', () => {
    document.querySelectorAll('.bulk-box:checked').forEach(cb => cb.checked = false);
  });

  /* Single delete confirm (SweetAlert2) */
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
          confirmButtonColor: '#dc2626',   // rose-600
          cancelButtonColor: '#6b7280',    // gray-500
          reverseButtons: true
        }).then((r) => { if (r.isConfirmed) form.submit(); });
      } else if (!confirm('Delete this conversation?')) {
        e.preventDefault();
      }
    });
  });

  /* Bulk delete (loop through existing DELETE endpoint) */
  deleteSelBtn?.addEventListener('click', async () => {
    const ids = Array.from(document.querySelectorAll('.bulk-box:checked')).map(cb => cb.value);
    if (!ids.length) {
      if (window.Swal) {
        Swal.fire({
          icon:'info',
          title:'No conversations selected',
          toast:true,
          position:'top-end',
          timer:2000,
          showConfirmButton:false
        });
      } else {
        alert('No conversations selected.');
      }
      return;
    }

    const proceed = async () => {
      for (const id of ids) {
        await fetch(`{{ url('/chat/session') }}/${id}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ _token: CSRF_TOKEN, _method: 'DELETE' })
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

  /* Client-side search: title + "anonymous" flag */
  function filter() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    document.querySelectorAll('[data-session-card]').forEach(card => {
      const title = (card.getAttribute('data-title') || '');
      const isAnon = card.getAttribute('data-anon') === '1';
      const hay = [title, isAnon ? 'anonymous' : 'identified'].join(' ');
      card.classList.toggle('hidden', q && !hay.includes(q));
    });
  }
  searchInput?.addEventListener('input', filter);
})();
</script>
@endsection
