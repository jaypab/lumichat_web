@extends('layouts.admin')

@section('page_title', 'Announcements')

@section('content')
<div class="px-4 pb-8 relative" id="bulk-admin-container">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Broadcast History</h2>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Manage and track all school-wide updates.</p>
        </div>
        <a href="{{ route('admin.announcements.create') }}" 
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold transition-all shadow-lg shadow-indigo-500/20 active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Broadcast
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm backdrop-blur-md">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700/50">
                        <th class="px-6 py-3.5 w-10">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        </th>
                        <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">Details & Source</th>
                        <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">Status</th>
                        <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">Priority</th>
                        <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">Schedule</th>
                        <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($announcements as $item)
                    <tr class="hover:bg-slate-50/30 dark:hover:bg-white/[0.02] transition-colors announcement-row">
                        <td class="px-6 py-3.5">
                            <input type="checkbox" name="announcement_ids[]" value="{{ $item->id }}" class="announcement-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        </td>
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-2">
                                @if($item->priority === 'high')
                                    <span class="shrink-0 text-amber-500" title="Pinned Announcement">📌</span>
                                @endif
                                <div class="font-bold text-slate-800 dark:text-slate-100 leading-tight break-all max-w-sm">{{ $item->title }}</div>
                            </div>
                            <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 mt-1 uppercase tracking-wider break-all max-w-sm">By {{ $item->author->name ?? 'System' }}</div>
                        </td>
                        <td class="px-6 py-3">
                            @if(!$item->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                    Draft
                                </span>
                            @elseif($item->expires_at && $item->expires_at->isPast())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400 border border-rose-200/50 dark:border-rose-800/50">
                                    Expired
                                </span>
                            @elseif($item->starts_at && $item->starts_at->isFuture())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200/50 dark:border-amber-800/50">
                                    Scheduled
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">
                                    Active
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5">
                            @php $pColor = $item->priority_color; @endphp
                            <span @class([
                                "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider",
                                "bg-{$pColor}-100 text-{$pColor}-700 dark:bg-{$pColor}-900/30 dark:text-{$pColor}-300 border border-{$pColor}-200/50 dark:border-{$pColor}-800/50"
                            ])>
                                <span class="w-1 h-1 rounded-full bg-current"></span>
                                {{ $item->priority }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 space-y-0.5">
                                @if($item->starts_at)
                                    <div><span class="font-black text-slate-400 dark:text-slate-500 uppercase tracking-tighter mr-1">Starts:</span> {{ $item->starts_at->format('M d, Y') }}</div>
                                @endif
                                @if($item->expires_at)
                                    <div><span class="font-black text-slate-400 dark:text-slate-500 uppercase tracking-tighter mr-1">Ends:</span> {{ $item->expires_at->format('M d, Y') }}</div>
                                @else
                                    <div class="italic text-slate-400">No Expiry</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.announcements.edit', $item) }}" 
                                   class="p-1.5 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400 dark:hover:bg-indigo-900/40 rounded-lg transition-all"
                                   title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <form action="{{ route('admin.announcements.destroy', $item) }}" method="POST" class="inline delete-form">
                                    @csrf @method('DELETE')
                                    <button type="button" 
                                            class="p-1.5 text-rose-600 bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-400 dark:hover:bg-rose-900/40 rounded-lg transition-all delete-confirm-btn"
                                            title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                           </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center opacity-40">
                                <img src="{{ asset('images/icons/nodata.png') }}" class="w-16 h-16 grayscale mb-4" alt="">
                                <p class="text-slate-500 font-medium">No broadcasts found yet.</p>
                                <p class="text-slate-400 text-sm">Create your first announcement to reach students.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($announcements->hasPages())
        <div class="px-6 py-4 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-700/50">
            {{ $announcements->links() }}
        </div>
        @endif
    </div>

    {{-- Floating Bulk Actions Bar --}}
    <div id="bulkActionBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 dark:bg-slate-800 text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-6 z-[60] transition-all duration-300 translate-y-32 opacity-0">
        <div class="flex items-center gap-3">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-[11px] font-black" id="selectedCount">0</span>
            <span class="text-sm font-bold tracking-tight">Items Selected</span>
        </div>
        <div class="h-6 w-px bg-slate-700"></div>
        <button onclick="lumiBulkDelete()" class="inline-flex items-center gap-2 px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-[12px] font-black uppercase tracking-wider transition-all active:scale-95 shadow-lg shadow-rose-500/20">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            Delete Selected
        </button>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.announcement-checkbox');
        const bulkBar = document.getElementById('bulkActionBar');
        const countBadge = document.getElementById('selectedCount');

        function updateBulkBar() {
            const checked = document.querySelectorAll('.announcement-checkbox:checked');
            const count = checked.length;
            
            if (count > 0) {
                bulkBar.classList.remove('translate-y-32', 'opacity-0');
                countBadge.innerText = count;
            } else {
                bulkBar.classList.add('translate-y-32', 'opacity-0');
            }
            
            selectAll.checked = (count === checkboxes.length && checkboxes.length > 0);
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateBulkBar();
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkBar);
        });

        // Bulk Delete Action
        window.lumiBulkDelete = function() {
            const checked = document.querySelectorAll('.announcement-checkbox:checked');
            const ids = Array.from(checked).map(cb => cb.value);

            Swal.fire({
                title: `Delete ${ids.length} announcements?`,
                text: "This will permanently remove all selected updates. This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete all'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('admin.announcements.bulk-delete') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ ids: ids })
                    })
                    .then(async response => {
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Validation failed');
                        return data;
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted!', data.message, 'success')
                            .then(() => window.location.reload());
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', error.message, 'error');
                    });
                }
            });
        }

        document.querySelectorAll('.delete-confirm-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('.delete-form');
                Swal.fire({
                    title: 'Delete this broadcast?',
                    text: "Students will no longer be able to see this announcement. This action cannot be undone.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e11d48',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete it'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush
@endsection
