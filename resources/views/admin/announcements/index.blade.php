@extends('layouts.admin')

@section('page_title', 'Announcements')

@section('content')
  <div class="px-6 pb-12 relative" id="bulk-admin-container" x-data="{ scrolled: false }" x-init="$el.querySelector('.panel-scroll').addEventListener('scroll', e => { scrolled = e.target.scrollTop > 10 })">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-3xl font-black text-slate-900 tracking-tight leading-none">Broadcast History</h2>
                <div class="px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100/50 shadow-sm shadow-indigo-500/5">
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ $announcements->total() }} Records</span>
                </div>
            </div>
            <p class="text-slate-500 text-[13px] font-medium mt-3 tracking-tight">Manage and track all school-wide updates.</p>
        </div>
      <div class="flex items-center gap-3">
        <a href="{{ route('admin.announcements.create') }}"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black uppercase tracking-widest text-[11px] transition-all shadow-xl shadow-indigo-500/25 active:scale-95 group italic">
          <svg class="w-4 h-4 transition-transform group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
          </svg>
          New Broadcast
        </a>
      </div>
    </div>

    {{-- Results Table --}}
    <div class="rounded-3xl bg-white border border-slate-200/60 shadow-xl shadow-slate-200/50 overflow-hidden transition-all duration-300 group-hover/table:shadow-slate-300/50">
      <div class="panel-scroll relative overflow-x-auto min-h-[500px] max-h-[calc(100vh-280px)] custom-scrollbar">
        <table class="w-full text-sm text-left border-separate border-spacing-0">
          <thead class="sticky top-0 z-20 transition-all duration-300" :class="scrolled ? 'bg-indigo-50/95 backdrop-blur-md shadow-sm' : 'bg-indigo-50'">
            <tr>
              <th scope="col" class="px-6 py-4 w-12 border-b-2 border-indigo-100 transition-all duration-300" :class="scrolled ? 'rounded-none' : 'rounded-tl-[1.4rem]'">
                <div class="flex items-center">
                  <input type="checkbox" id="selectAll"
                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20 cursor-pointer transition-all">
                </div>
              </th>
              <th scope="col" class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">
                Details & Source
              </th>
              <th scope="col" class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">
                Status
              </th>
              <th scope="col" class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">
                Priority
              </th>
              <th scope="col" class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100">
                Schedule
              </th>
              <th scope="col" class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-indigo-900/80 border-b-2 border-indigo-100 text-right pr-6 transition-all duration-300" :class="scrolled ? 'rounded-none' : 'rounded-tr-[1.4rem]'">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse($announcements as $item)
              <tr class="group/row hover:bg-slate-50/80 transition-all duration-150 align-middle">
                <td class="px-6 py-4 whitespace-nowrap">
                  <input type="checkbox" name="announcement_ids[]" value="{{ $item->id }}"
                    class="announcement-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20 cursor-pointer transition-all">
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    @if($item->priority === 'high')
                      <div class="shrink-0 flex items-center justify-center w-7 h-7 rounded-lg bg-amber-50 text-amber-500 ring-1 ring-amber-100 shadow-sm" title="Pinned Announcement">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
                      </div>
                    @else
                      <div class="shrink-0 flex items-center justify-center w-7 h-7 rounded-lg bg-slate-50 text-slate-400 ring-1 ring-slate-100 group-hover/row:bg-white transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                      </div>
                    @endif
                    <div class="flex flex-col">
                      <div class="font-black text-slate-900 group-hover/row:text-indigo-600 transition-colors leading-tight max-w-sm">
                        {{ $item->title }}
                      </div>
                      <div class="text-[10px] font-black text-slate-400 mt-1 uppercase tracking-widest flex items-center gap-1.5">
                        <span class="size-1 rounded-full bg-slate-200"></span>
                        BY {{ $item->author->name ?? 'System' }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-5 whitespace-nowrap">
                  @if(!$item->is_active)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-black tracking-widest uppercase ring-1 ring-inset ring-slate-200 group-hover/row:bg-white transition-colors">
                      <span class="size-1.5 rounded-full bg-slate-400"></span>
                      Draft
                    </span>
                  @elseif($item->expires_at && $item->expires_at->isPast())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 text-[10px] font-black tracking-widest uppercase border border-rose-100 group-hover/row:bg-white transition-colors">
                      <span class="size-1.5 rounded-full bg-rose-400 transition-all group-hover/row:scale-125 group-hover/row:animate-pulse"></span>
                      Expired
                    </span>
                  @elseif($item->starts_at && $item->starts_at->isFuture())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-600 text-[10px] font-black tracking-widest uppercase border border-amber-100 group-hover/row:bg-white transition-colors">
                      <span class="size-1.5 rounded-full bg-amber-400"></span>
                      Scheduled
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-[10px] font-black tracking-widest uppercase border border-emerald-100 group-hover/row:bg-white transition-colors">
                      <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                      Active
                    </span>
                  @endif
                </td>
                <td class="px-6 py-5 whitespace-nowrap">
                  @php 
                    $pColor = $item->priority_color === 'slate' ? 'slate' : ($item->priority_color === 'indigo' ? 'indigo' : 'rose');
                    $bg = "bg-{$pColor}-50";
                    $txt = "text-{$pColor}-600";
                    $dot = "bg-{$pColor}-400";
                    $ring = "ring-{$pColor}-100";
                  @endphp
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $bg }} {{ $txt }} text-[10px] font-black tracking-widest uppercase ring-1 ring-inset {{ $ring }} group-hover/row:bg-white transition-colors">
                    <span class="size-1.5 rounded-full {{ $dot }}"></span>
                    {{ $item->priority }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex flex-col gap-1">
                    @if($item->starts_at)
                      <div class="flex items-center gap-2">
                        <span class="text-[9px] font-black text-slate-300 uppercase tracking-tighter w-10">Starts</span>
                        <span class="text-[12px] font-bold text-slate-600 tracking-tight">{{ $item->starts_at->format('M d, Y') }}</span>
                      </div>
                    @endif
                    @if($item->expires_at)
                      <div class="flex items-center gap-2">
                        <span class="text-[9px] font-black text-slate-300 uppercase tracking-tighter w-10">Ends</span>
                        <span class="text-[12px] font-bold text-slate-600 tracking-tight">{{ $item->expires_at->format('M d, Y') }}</span>
                      </div>
                    @else
                      <div class="text-[11px] font-bold text-slate-400 italic">No Expiry</div>
                    @endif
                  </div>
                </td>
                <td class="px-6 py-4 text-right overflow-visible pr-6">
                  <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false" :class="{ 'z-50': open }">
                    <button type="button" 
                            @click="open = !open"
                            class="flex items-center justify-center ml-auto w-9 h-9 rounded-xl hover:bg-slate-100 transition-all duration-200 text-slate-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" 
                            title="Options">
                      <svg class="w-5 h-5 pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                      </svg>
                    </button>

                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 rounded-2xl bg-white shadow-2xl shadow-indigo-500/20 ring-1 ring-slate-200 divide-y divide-slate-100 z-50 origin-top-right"
                         style="display: none;">
                      <div class="px-2 py-2">
                        <a href="{{ route('admin.announcements.edit', $item) }}" class="group flex items-center px-3 py-2.5 text-xs font-black uppercase tracking-widest text-slate-600 rounded-xl hover:bg-indigo-50 hover:text-indigo-700 transition-all duration-150">
                          <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 mr-3 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                          </div>
                          Edit Detail
                        </a>
                      </div>

                      <div class="px-2 py-2">
                        <form action="{{ route('admin.announcements.destroy', $item) }}" method="POST" class="inline delete-form">
                          @csrf @method('DELETE')
                          <button type="button" class="group flex w-full items-center px-3 py-2.5 text-xs font-black uppercase tracking-widest text-slate-600 rounded-xl hover:bg-rose-50 hover:text-rose-700 transition-all duration-150 text-left delete-confirm-btn">
                            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-rose-50 text-rose-500 group-hover:bg-rose-100 group-hover:text-rose-600 mr-3 transition-colors">
                              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                              </svg>
                            </div>
                            Delete post
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-6 py-20">
                  <div class="flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 rounded-3xl bg-slate-50 flex items-center justify-center text-slate-300 mb-4 border border-slate-100 shadow-inner">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    </div>
                    <p class="text-slate-800 font-black uppercase tracking-widest text-[11px]">No Broadcasts Found</p>
                    <p class="text-slate-400 text-xs mt-1 font-medium">Create your first announcement to reach students.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($announcements->hasPages())
        <div class="px-6 py-4 bg-indigo-50/30 border-t border-indigo-100/50 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">
            Showing <span class="text-slate-700">{{ $announcements->firstItem() }}</span>–<span class="text-slate-700">{{ $announcements->lastItem() }}</span> 
            of <span class="text-slate-700">{{ $announcements->total() }}</span> Broadcasts
          </p>
          <div class="modern-pagination">
            {{ $announcements->links() }}
          </div>
        </div>
      @endif
    </div>

    {{-- Floating Bulk Actions Bar --}}
    <div id="bulkActionBar"
      class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 dark:bg-slate-800 text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-6 z-[60] transition-all duration-300 translate-y-32 opacity-0">
      <div class="flex items-center gap-3">
        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-[11px] font-black"
          id="selectedCount">0</span>
        <span class="text-sm font-bold tracking-tight">Items Selected</span>
      </div>
      <div class="h-6 w-px bg-slate-700"></div>
      <button onclick="lumiBulkDelete()"
        class="inline-flex items-center gap-2 px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-[12px] font-black uppercase tracking-wider transition-all active:scale-95 shadow-lg shadow-rose-500/20">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
          </path>
        </svg>
        Delete Selected
      </button>
    </div>
  </div>

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
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
          selectAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkBar();
          });
        }

        checkboxes.forEach(cb => {
          cb.addEventListener('change', updateBulkBar);
        });

        // Bulk Delete Action
        window.lumiBulkDelete = function () {
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
          btn.addEventListener('click', function () {
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