@extends('layouts.admin')

@section('title', 'Admin - Account Requests')
@section('page_title', 'Account Requests')

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  @php
    $totalRequests = method_exists($accountRequests, 'total') ? $accountRequests->total() : $accountRequests->count();
  @endphp

  {{-- ========= Page Header (Clean SaaS Layout) ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 screen-only mb-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Account Requests</h1>
        <div class="hidden sm:flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold ring-1 ring-inset ring-indigo-500/20">
          {{ number_format($totalRequests) }} Total
        </div>
      </div>
      <p class="text-slate-500 text-sm font-medium">Review and approve student onboarding requests.</p>
    </div>
  </header>

  {{-- ========= Filters (Modern Search) ========= --}}
  <form id="filterForm" method="GET" action="{{ route('admin.account-requests.index') }}" class="screen-only">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="relative flex-1 group">
        <input
          id="q-input"
          type="text"
          name="q"
          value="{{ old('q', $q) }}"
          placeholder="Search by name, email, or SIS..."
          autocomplete="off"
          class="h-12 w-full rounded-2xl border-none bg-white px-11 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 group-hover:ring-slate-300"
          onchange="document.getElementById('filterForm').submit();"
        />
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400 group-hover:text-indigo-500 transition-colors duration-200"
             viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="11" cy="11" r="7" stroke-width="2.5"></circle>
          <path d="M21 21l-4.3-4.3" stroke-width="2.5" stroke-linecap="round"></path>
        </svg>

        @if(request('q'))
          <button type="button"
                  onclick="document.getElementById('q-input').value=''; document.getElementById('filterForm').submit();"
                  class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all"
                  aria-label="Clear search">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        @endif
      </div>

      <div class="flex items-center gap-3">
        <select name="status" id="status-select" class="h-12 rounded-2xl border-none bg-white px-4 py-0 pl-4 pr-10 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all duration-200 hover:ring-slate-300" onchange="document.getElementById('filterForm').submit();">
          <option value="all" @selected($status === 'all')>All Statuses</option>
          <option value="pending" @selected($status === 'pending')>Pending</option>
          <option value="approved" @selected($status === 'approved')>Approved</option>
          <option value="rejected" @selected($status === 'rejected')>Rejected</option>
        </select>

        <a href="{{ route('admin.account-requests.index') }}"
           class="h-12 inline-flex items-center gap-2 rounded-2xl bg-white px-5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50 group transition-all duration-200">
          <svg class="h-4 w-4 text-slate-400 group-hover:rotate-180 transition-transform duration-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <path d="M12 8v8M8 12h8" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
          Reset
        </a>
      </div>
    </div>
  </form>

  <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 transition-all duration-300 flex flex-col">
    <div class="relative overflow-x-auto rounded-t-3xl border-b border-transparent">
      <table class="min-w-full text-sm leading-relaxed table-auto">
        <thead x-data="{ scrolled: false }" 
               x-init="const s = document.querySelector('.panel-scroll'); if(s){ s.addEventListener('scroll', () => scrolled = s.scrollTop > 10) }"
               class="text-slate-700 sticky top-0 z-20">
          <tr>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">Submitted</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Name</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">SIS</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Email</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Status</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Acct. Status</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-left font-black uppercase tracking-wider text-[11px] text-indigo-900/80">Last Online</th>
            <th class="bg-indigo-50 border-b-2 border-indigo-100 px-6 py-4 text-right font-black uppercase tracking-wider text-[11px] text-indigo-900/80 transition-all duration-300"
                :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($accountRequests as $item)
            <tr class="group hover:bg-indigo-50/30 transition-colors duration-200" data-account-request-row data-request-id="{{ $item->id }}">
              <td class="px-6 py-4 whitespace-nowrap text-slate-500 font-medium text-[13px]">{{ optional($item->created_at)->format('M d, Y') }}<br><span class="text-slate-400 text-[11px]">{{ optional($item->created_at)->format('h:i A') }}</span></td>
              <td class="px-6 py-4 font-bold text-slate-800">{{ $item->name }}</td>
              <td class="px-6 py-4 text-slate-500 font-medium">
                <span class="inline-flex items-center rounded-lg bg-slate-100 border border-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-600">
                  SIS: {{ $item->sis }}
                </span>
              </td>
              <td class="px-6 py-4 text-slate-600 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                {{ $item->email }}
              </td>
              <td class="px-6 py-4" data-request-status-cell>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide
                  {{ $item->status === 'pending' ? 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-500/20' : ($item->status === 'approved' ? 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-500/20' : 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-500/20') }}">
                  {{ strtoupper($item->status) }}
                </span>
              </td>
              <td class="px-6 py-4" data-account-status-cell>
                @if($item->status === 'approved' && $item->approvedUser)
                  @php
                    $isOnline = \Illuminate\Support\Facades\Cache::has('user-online-' . $item->approvedUser->id);
                  @endphp
                  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide {{ $isOnline ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-500/20' }}">
                    @if($isOnline) <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> @endif
                    {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                  </span>
                @elseif($item->status === 'approved')
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20">PROVISIONING</span>
                @else
                  <span class="text-slate-300 font-medium">—</span>
                @endif
              </td>
              <td class="px-6 py-4 text-slate-500 text-[12px] whitespace-nowrap" data-last-online-cell>
                @if($item->status === 'approved' && $item->approvedUser)
                  {{ optional($item->approvedUser->last_seen_appt_at)?->diffForHumans() ?? 'Never' }}
                @else
                  <span class="text-slate-300">—</span>
                @endif
              </td>
              <td class="px-6 py-4 text-right">
                <a href="{{ route('admin.account-requests.show', $item) }}" 
                   class="inline-flex items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 px-4 py-2 text-xs font-bold hover:bg-indigo-600 hover:text-white transition-all duration-200">
                  Review
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-6 py-20 text-center">
                <div class="flex flex-col items-center">
                  <div class="p-4 rounded-3xl bg-slate-50 mb-3">
                    <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                  </div>
                  <h3 class="text-slate-900 font-bold">No account requests found</h3>
                  <p class="text-slate-500 text-sm">Everything is fully caught up.</p>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($accountRequests->hasPages())
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/70 rounded-b-3xl">
        {{ $accountRequests->appends(request()->all())->onEachSide(1)->links() }}
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    const rows = Array.from(document.querySelectorAll('[data-account-request-row]'));
    if (!rows.length) return;

    const ids = rows.map(row => row.dataset.requestId).filter(Boolean);
    if (!ids.length) return;

    const url = new URL(@json(route('admin.account-requests.live-status')));
    ids.forEach(id => url.searchParams.append('ids[]', id));

    const statusBadgeClass = {
      pending: 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-500/20',
      approved: 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-500/20',
      rejected: 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-500/20',
    };

    function accountStatusBadge(label) {
      if (label === 'Online') return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20';
      if (label === 'Offline') return 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-500/20';
      if (label === 'Provisioning') return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20';
      return '';
    }

    async function refreshLiveStatus() {
      try {
        const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !Array.isArray(data.items)) return;

        data.items.forEach(item => {
          const row = document.querySelector(`[data-account-request-row][data-request-id="${item.id}"]`);
          if (!row) return;

          const reqStatusCell = row.querySelector('[data-request-status-cell]');
          const acctStatusCell = row.querySelector('[data-account-status-cell]');
          const lastOnlineCell = row.querySelector('[data-last-online-cell]');

          if (reqStatusCell) {
            const text = item.status ? item.status.toUpperCase() : 'UNKNOWN';
            const klass = statusBadgeClass[item.status] || 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/20';
            reqStatusCell.innerHTML = `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide ${klass}">${text}</span>`;
          }

          if (acctStatusCell) {
            const label = item.account_status || 'N/A';
            const htmlLabel = label === 'Online' ? `<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> ONLINE` : label.toUpperCase();
            const klass = accountStatusBadge(label);
            acctStatusCell.innerHTML = klass
              ? `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide ${klass}">${htmlLabel}</span>`
              : '<span class="text-slate-300 font-medium">—</span>';
          }

          if (lastOnlineCell) {
            const value = item.last_online || 'N/A';
            const isNA = value === 'N/A';
            lastOnlineCell.innerHTML = isNA
              ? '<span class="text-slate-300">—</span>'
              : `<span class="text-slate-500 text-[12px] whitespace-nowrap">${value}</span>`;
          }
        });
      } catch (_) {
        // Keep silent; polling should not break the page UI.
      }
    }

    refreshLiveStatus();
    setInterval(refreshLiveStatus, 5000);
  })();
</script>
@endpush
