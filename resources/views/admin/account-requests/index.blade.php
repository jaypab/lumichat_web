@extends('layouts.admin')

@section('title', 'Admin - Account Requests')
@section('page_title', 'Account Requests')

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">
  <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm">
    <div class="p-5 sm:p-6">
      <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Student Account Requests</h2>
      <p class="text-white/85 text-sm mt-0.5">Review and approve student onboarding requests.</p>
    </div>
  </section>

  <form method="GET" action="{{ route('admin.account-requests.index') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <input type="text" name="q" value="{{ $q }}" placeholder="Search by name, email, or SIS"
             class="h-11 w-full sm:max-w-sm rounded-xl border border-slate-200 bg-white px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" />

      <select name="status" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
        <option value="all" @selected($status === 'all')>All</option>
        <option value="pending" @selected($status === 'pending')>Pending</option>
        <option value="approved" @selected($status === 'approved')>Approved</option>
        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
      </select>

      <div class="sm:ml-auto flex items-center gap-2">
        <button class="h-11 rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-500">Filter</button>
        <a href="{{ route('admin.account-requests.index') }}" class="h-11 inline-flex items-center rounded-xl border border-slate-200 px-4 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
      </div>
    </div>
  </form>

  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
    <div class="relative overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 border-b border-slate-200 text-slate-700">
          <tr>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Submitted</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Name</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">SIS</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Email</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Status</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Account Status</th>
            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wide text-[11px]">Last Online</th>
            <th class="px-6 py-3 text-right font-semibold uppercase tracking-wide text-[11px]">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($accountRequests as $item)
            <tr class="even:bg-slate-50 hover:bg-slate-100/60 transition" data-account-request-row data-request-id="{{ $item->id }}">
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">{{ optional($item->created_at)->format('M d, Y h:i A') }}</td>
              <td class="px-6 py-4 font-semibold text-slate-900">{{ $item->name }}</td>
              <td class="px-6 py-4 text-slate-700">{{ $item->sis }}</td>
              <td class="px-6 py-4 text-slate-700">{{ $item->email }}</td>
              <td class="px-6 py-4" data-request-status-cell>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                  {{ $item->status === 'pending' ? 'bg-amber-100 text-amber-800' : ($item->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">
                  {{ ucfirst($item->status) }}
                </span>
              </td>
              <td class="px-6 py-4" data-account-status-cell>
                @if($item->status === 'approved' && $item->approvedUser)
                  @php
                    $isOnline = \Illuminate\Support\Facades\Cache::has('user-online-' . $item->approvedUser->id);
                  @endphp
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $isOnline ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                    {{ $isOnline ? 'Online' : 'Offline' }}
                  </span>
                @elseif($item->status === 'approved')
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-amber-100 text-amber-800">Provisioning</span>
                @else
                  <span class="text-slate-400">N/A</span>
                @endif
              </td>
              <td class="px-6 py-4 text-slate-700 whitespace-nowrap" data-last-online-cell>
                @if($item->status === 'approved' && $item->approvedUser)
                  {{ optional($item->approvedUser->last_seen_appt_at)?->diffForHumans() ?? 'Not yet active' }}
                @else
                  <span class="text-slate-400">N/A</span>
                @endif
              </td>
              <td class="px-6 py-4 text-right">
                <a href="{{ route('admin.account-requests.show', $item) }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">
                  Review
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-6 py-10 text-center text-slate-500">No account requests found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>
    {{ $accountRequests->links() }}
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
      pending: 'bg-amber-100 text-amber-800',
      approved: 'bg-emerald-100 text-emerald-800',
      rejected: 'bg-rose-100 text-rose-800',
    };

    function accountStatusBadge(label) {
      if (label === 'Online') return 'bg-emerald-100 text-emerald-800';
      if (label === 'Offline') return 'bg-slate-100 text-slate-700';
      if (label === 'Provisioning') return 'bg-amber-100 text-amber-800';
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
            const text = item.status ? (item.status.charAt(0).toUpperCase() + item.status.slice(1)) : 'Unknown';
            const klass = statusBadgeClass[item.status] || 'bg-slate-100 text-slate-700';
            reqStatusCell.innerHTML = `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${klass}">${text}</span>`;
          }

          if (acctStatusCell) {
            const label = item.account_status || 'N/A';
            const klass = accountStatusBadge(label);
            acctStatusCell.innerHTML = klass
              ? `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${klass}">${label}</span>`
              : '<span class="text-slate-400">N/A</span>';
          }

          if (lastOnlineCell) {
            const value = item.last_online || 'N/A';
            const isNA = value === 'N/A';
            lastOnlineCell.innerHTML = isNA
              ? '<span class="text-slate-400">N/A</span>'
              : value;
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
