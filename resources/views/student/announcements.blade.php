@extends('layouts.app')

@section('title', 'Announcements • LumiCHAT')
@section('page_title', 'Announcements')

@section('content')
<div id="announcement-feed" class="max-w-4xl mx-auto px-4 py-6 pb-20">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6 border-b border-slate-100 dark:border-slate-800 pb-6">
        <div class="flex-1">
            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                Updates <span class="text-indigo-600 dark:text-indigo-400">&</span> News
            </h2>
            <p class="mt-2 sm:mt-4 text-base sm:text-lg text-slate-500 dark:text-slate-400 font-medium max-w-xl">
                Stay connected with the latest updates from your counselors.
            </p>
        </div>
        <div class="flex items-center gap-2 px-4 py-2 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800 text-[13px] font-bold text-slate-500 dark:text-slate-400 shrink-0">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            Live Updates
        </div>
    </div>

    {{-- 1. Pinned Critical Updates (HCI: Visual Saliency) --}}
    @if($pinnedAnnouncements->count() > 0)
        <section class="mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="h-px flex-1 bg-rose-100 dark:bg-rose-900/30"></div>
                <h3 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-rose-500 dark:text-rose-400">
                    <span class="animate-pulse">🔔</span> Important Announcements
                </h3>
                <div class="h-px flex-1 bg-rose-100 dark:bg-rose-900/30"></div>
            </div>
            
            <div class="grid gap-4 sm:grid-cols-{{ $pinnedAnnouncements->count() > 1 ? '2' : '1' }}">
                @foreach($pinnedAnnouncements as $pinned)
                    <article class="relative group bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/50 dark:border-rose-900/50 rounded-3xl sm:rounded-[1.5rem] p-5 sm:p-6 hover:shadow-xl hover:shadow-rose-500/10 transition-all duration-300">
                        <div class="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full bg-rose-500 text-white text-xs font-bold shadow-lg shadow-rose-500/40">
                             📌
                        </div>
                        <time class="text-[10px] font-black text-rose-400 dark:text-rose-500 uppercase tracking-widest mb-3 block">
                            {{ $pinned->created_at->diffForHumans() }}
                        </time>
                        <h4 class="text-xl font-black text-slate-900 dark:text-white leading-tight mb-2 group-hover:text-rose-600 transition-colors break-all max-w-full">
                            {{ $pinned->title }}
                        </h4>
                        <div class="pinned-expansion-wrapper transition-all duration-300">
                            <p 
                                class="text-sm text-slate-600 dark:text-slate-200 leading-relaxed announcement-content-container max-h-[60px] overflow-hidden transition-all duration-500 break-all max-w-full"
                            >
                                {{ $pinned->content }}
                            </p>
                            @if(strlen($pinned->content) > 120)
                                <button 
                                    onclick="lumiToggleAnnouncement(this)" 
                                    class="mt-2 text-[10px] font-black text-rose-500 uppercase tracking-widest hover:text-rose-600 transition-colors focus:outline-none ring-0 outline-none"
                                >
                                    <div class="flex items-center gap-1">
                                        <span class="button-text">Read More</span>
                                        <svg class="w-2.5 h-2.5 transition-transform duration-300 arrow-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </button>
                            @endif
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">By {{ $pinned->author->name ?? 'Admin' }}</span>
                             <a href="#feed-{{ $pinned->id }}" class="text-[11px] font-black text-rose-500 hover:text-rose-600 uppercase tracking-widest">View Details →</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 2. Search & Filter Bar (HCI: Hick's Law & Fitts's Law) --}}
    <div class="mb-6 p-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm flex flex-col sm:flex-row items-center gap-2">
        <div class="relative flex-1 w-full">
            <svg class="absolute left-8 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input 
                id="announcementSearch"
                oninput="lumiApplyFilters()"
                type="text" 
                placeholder="Search announcements..." 
                class="w-full bg-transparent border-none ring-0 focus:ring-0 outline-none focus:outline-none pl-12 sm:pl-16 pr-4 py-3 sm:py-3.5 text-sm font-semibold text-slate-600 dark:text-slate-300 placeholder-slate-400 text-left"
                style="border: none !important; outline: none !important; box-shadow: none !important;"
            >
        </div>
        
        <div class="flex items-center gap-1 p-0.5 bg-slate-100/50 dark:bg-slate-900/50 rounded-2xl w-full sm:w-auto relative filter-container">
            {{-- Sliding Background --}}
            <div 
                id="filterIndicator"
                class="absolute bg-white dark:bg-slate-800 rounded-[14px] shadow-sm transition-all duration-300 ease-out z-0"
                style="left: 2px; width: 60px; height: calc(100% - 4px); top: 2px;"
            ></div>

            <button 
                onclick="lumiSetFilter('all', this)"
                id="filterBtnAll"
                class="relative z-10 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all min-w-[60px] text-indigo-600 active-filter outline-none focus:outline-none ring-0 focus:ring-0 hover:bg-white/50 dark:hover:bg-slate-800/50"
            >
                All
            </button>
            <button 
                onclick="lumiSetFilter('high', this)"
                id="filterBtnHigh"
                class="relative z-10 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all min-w-[90px] text-slate-500 outline-none focus:outline-none ring-0 focus:ring-0 hover:bg-white/50 dark:hover:bg-slate-800/50"
            >
                Priority
            </button>

            {{-- Vertical Divider --}}
            <div class="h-4 w-px bg-slate-200 dark:border-slate-800 mx-1"></div>

            {{-- Sort Order Toggle --}}
            <button 
                onclick="lumiToggleSort(this)"
                class="px-3 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider text-slate-500 hover:bg-white dark:hover:bg-slate-800 hover:text-indigo-600 transition-all flex items-center gap-1 outline-none focus:outline-none ring-0 focus:ring-0"
                id="sortToggleButton"
                data-sort="newest"
            >
                <span id="sortOrderText">newest</span>
                <svg 
                    id="sortOrderIcon"
                    class="w-3 h-3 transition-transform duration-300"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Main Feed --}}
    <div class="space-y-6" id="announcements-list">
        @php 
            $lastDate = null; 
        @endphp

        @forelse($announcements as $announcement)
            @php 
                $currentDate = $announcement->created_at->startOfDay();
                $isNewGroup = !$lastDate || !$currentDate->equalTo($lastDate);
                $lastDate = $currentDate;
                
                $groupLabel = 'Earlier';
                if ($currentDate->isToday()) $groupLabel = 'Today';
                elseif ($currentDate->isYesterday()) $groupLabel = 'Yesterday';
            @endphp

            {{-- 3. Date Grouping Header (HCI: Chunking) --}}
            @if($isNewGroup)
                <div class="relative py-8 date-group-header">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-slate-200 dark:border-slate-800/80"></div>
                    </div>
                    <div class="relative flex justify-start">
                        <span class="px-5 py-1.5 rounded-full bg-white dark:bg-slate-950 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-[0.4em] pointer-events-none border border-slate-100 dark:border-slate-800 shadow-sm ring-8 ring-white dark:ring-slate-950">
                            {{ $groupLabel }}
                        </span>
                    </div>
                </div>
            @endif

            <article 
                id="feed-{{ $announcement->id }}"
                data-date="{{ $announcement->created_at->toIso8601String() }}"
                data-priority="{{ $announcement->priority }}"
                @class([
                    "group relative bg-white dark:bg-slate-900 rounded-3xl sm:rounded-[2.5rem] border transition-all duration-500 announcement-card",
                    "border-slate-200 dark:border-slate-800 hover:border-indigo-500/50 hover:shadow-2xl hover:shadow-indigo-500/5",
                    "ring-2 ring-rose-500/10 dark:ring-rose-500/20 border-rose-100" => $announcement->priority === 'high'
                ])
            >
                <div class="p-5 sm:p-7 space-y-4">
                    {{-- Priority & Meta --}}
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            @php $pColor = $announcement->priority_color; @endphp
                            <div @class([
                                "px-3.5 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5",
                                "bg-{$pColor}-100 text-{$pColor}-700 dark:bg-{$pColor}-900/30 dark:text-{$pColor}-300 border border-{$pColor}-200/50 dark:border-{$pColor}-700/50"
                            ])>
                                @if($announcement->priority === 'high')
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                    </span>
                                @endif
                                {{ $announcement->priority }} Priority
                            </div>
                            
                            <div class="h-1 w-1 rounded-full bg-slate-300 dark:bg-slate-700"></div>
                            
                            <time class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-tight">
                                {{ $announcement->created_at->format('M d, g:i A') }}
                            </time>
                        </div>

                        @auth
                            @if($announcement->created_at > (Auth::user()->last_seen_announcement_at ?? \Carbon\Carbon::createFromTimestamp(0)))
                                <div class="px-3 py-1 rounded-full bg-indigo-600 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-600/30 animate-bounce">
                                    New
                                </div>
                            @endif
                        @endauth
                    </div>

                    <div class="flex flex-col items-start space-y-3">
                        <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white leading-[1.1] tracking-tight group-hover:text-indigo-600 transition-colors break-all max-w-full">
                            {{ $announcement->title }}
                        </h3>
                        
                        <div class="relative overflow-hidden transition-all duration-500 announcement-content-container max-h-[140px] break-all max-w-full">
                             <div 
                                class="text-left text-[17px] text-slate-600 dark:text-slate-100 leading-relaxed font-medium whitespace-normal break-all"
                            >
                                {!! nl2br(e(trim($announcement->content))) !!}
                            </div>
                            
                            
                            {{-- Fade effect when collapsed --}}
                            @if(strlen($announcement->content) > 200)
                                <div class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-white dark:from-slate-900 to-transparent pointer-events-none truncation-fade"></div>
                            @endif
                        </div>
                        
                        @if(strlen($announcement->content) > 200)
                            <button 
                                onclick="lumiToggleAnnouncement(this)"
                                type="button"
                                class="mt-2 flex items-center gap-2 text-[13px] font-black text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition-colors focus:outline-none focus:ring-0 toggle-button"
                            >
                                <span class="button-text">Read Full Update</span>
                                <svg 
                                    class="w-4 h-4 transition-transform duration-300 arrow-icon" 
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="pt-5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 text-xs border border-slate-200 dark:border-slate-700 font-bold">
                                {{ strtoupper(mb_substr($announcement->author->name ?? 'A', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ $announcement->author->name ?? 'Administrator' }}</h4>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Official Post</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 opacity-50 hover:opacity-100 transition-opacity hidden sm:flex">
                             <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                             <span class="text-[10px] font-bold text-slate-400">Public Feed</span>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="py-24 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-[3rem] bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 mb-8 border border-indigo-100 dark:border-indigo-900/50 shadow-inner">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </div>
                <h3 class="text-3xl font-black text-slate-900 dark:text-white mb-4 italic">No News Found</h3>
                <p class="text-lg text-slate-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                    Check your spelling or filter settings. All current announcements have been read or hidden.
                </p>
            </div>
        @endforelse

        {{-- Hidden Empty State for Search --}}
                        <div x-show="false" class="py-24 text-center hidden" :class="search !== '' || filter !== 'all' ? 'hidden' : ''">
             <!-- This logic is handled by the loop above but search might need a JS-only empty state if matching 0 -->
        </div>

        @if($announcements->hasPages())
            <div class="mt-12 pt-8 border-t border-slate-100 dark:border-slate-800" x-show="search === '' && filter === 'all'">
                {{ $announcements->links() }}
            </div>
        @endif

        {{-- Scroll to Top Button (HCI: Fitts's Law & Accessibility) --}}
        <button 
            id="scrollTopBtn"
            style="display: none;"
            class="fixed bottom-24 right-8 w-12 h-12 rounded-full bg-indigo-600 text-white shadow-2xl shadow-indigo-600/40 hover:bg-indigo-700 transition-all z-[9999] flex items-center justify-center focus:outline-none focus:ring-0 group"
            aria-label="Scroll to top"
        >
            <svg class="w-5 h-5 transition-transform group-hover:-translate-y-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
            </svg>
        </button>
    </div>
</div>

@push('styles')
<style>
    /* Premium Hover & Animation */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .article-animate {
        animation: fadeInUp 0.6s cubic-bezier(0.2, 1, 0.2, 1) both;
    }
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .line-clamp-4 {
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Vanilla Expansion Styles */
    /* Pinned Section Specific */
    .pinned-expansion-wrapper .announcement-content-container {
        overflow: hidden;
        max-height: 48px;
        transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .announcement-card.is-expanded .announcement-content-container,
    .pinned-expansion-wrapper.is-expanded .announcement-content-container {
        -webkit-line-clamp: unset !important;
        display: block !important;
    }
    .is-expanded.pinned-expansion-wrapper .truncation-fade,
    .announcement-card.is-expanded .truncation-fade {
        opacity: 0 !important;
        pointer-events: none !important;
    }
</style>
@endpush
@push('scripts')
<script>
    // State Management (Vanilla)
    let currentFilter = 'all';
    let currentSort = 'newest';

    window.lumiSetFilter = function(filter, btn) {
        currentFilter = filter;
        
        // Update UI Toggle
        const indicator = document.getElementById('filterIndicator');
        const btnAll = document.getElementById('filterBtnAll');
        const btnHigh = document.getElementById('filterBtnHigh');
        
        // Dynamic Sizing & Positioning
        indicator.style.width = btn.offsetWidth + 'px';
        indicator.style.left = btn.offsetLeft + 'px';
        indicator.style.height = 'calc(100% - 4px)';
        indicator.style.top = '2px';

        if (filter === 'all') {
            btnAll.classList.add('text-indigo-600');
            btnAll.classList.remove('text-slate-500');
            btnHigh.classList.add('text-slate-500');
            btnHigh.classList.remove('text-rose-600');
        } else {
            btnHigh.classList.add('text-rose-600');
            btnHigh.classList.remove('text-slate-500');
            btnAll.classList.add('text-slate-500');
            btnAll.classList.remove('text-indigo-600');
        }

        lumiApplyFilters();
    };

    window.lumiToggleSort = function(btn) {
        currentSort = currentSort === 'newest' ? 'oldest' : 'newest';
        
        const text = document.getElementById('sortOrderText');
        const icon = document.getElementById('sortOrderIcon');
        
        text.innerText = currentSort;
        if (currentSort === 'oldest') {
            icon.classList.add('rotate-180');
        } else {
            icon.classList.remove('rotate-180');
        }

        lumiApplySorting();
    };

    window.lumiApplySorting = function() {
        const list = document.getElementById('announcements-list');
        const items = Array.from(list.querySelectorAll('.announcement-card'));
        
        items.sort((a, b) => {
            const dateA = new Date(a.getAttribute('data-date'));
            const dateB = new Date(b.getAttribute('data-date'));
            return currentSort === 'newest' ? dateB - dateA : dateA - dateB;
        });

        // Hide headers if search/filter or non-standard sort is active
        const headers = list.querySelectorAll('.date-group-header');
        const shouldHideHeaders = currentSort === 'oldest' || currentFilter !== 'all' || document.getElementById('announcementSearch').value.length > 0;
        
        headers.forEach(h => h.style.display = shouldHideHeaders ? 'none' : 'block');

        items.forEach(item => list.appendChild(item));
    };

    window.lumiApplyFilters = function() {
        const searchTerm = document.getElementById('announcementSearch').value.toLowerCase();
        const cards = document.querySelectorAll('.announcement-card');
        
        cards.forEach(card => {
            const title = card.querySelector('h3')?.innerText.toLowerCase() || '';
            const content = card.querySelector('.announcement-content-container')?.innerText.toLowerCase() || '';
            const priority = card.getAttribute('data-priority');
            
            const matchesSearch = title.includes(searchTerm) || content.includes(searchTerm);
            const matchesFilter = currentFilter === 'all' || priority === currentFilter;
            
            if (matchesSearch && matchesFilter) {
                card.style.display = 'block';
                // Trigger an animation class if you like
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            } else {
                card.style.display = 'none';
            }
        });

        // Manage headers during search/filter
        const headers = document.querySelectorAll('.date-group-header');
        const hasActiveFiltering = currentFilter !== 'all' || searchTerm.length > 0;
        headers.forEach(h => h.style.display = hasActiveFiltering ? 'none' : 'block');
    };

    // Vanilla Expansion Toggle (Smooth Height-Aware)
    window.lumiToggleAnnouncement = function(btn) {
        const card = btn.closest('.announcement-card') || btn.closest('.pinned-expansion-wrapper');
        const container = card.querySelector('.announcement-content-container');
        const textSpan = btn.querySelector('.button-text');
        const arrow = btn.querySelector('.arrow-icon');
        const isExpanded = card.classList.contains('is-expanded');
        
        if (isExpanded) {
            // Collapse
            container.style.maxHeight = wrapperIsPinned(card) ? '48px' : '140px';
            card.classList.remove('is-expanded');
            if (textSpan) textSpan.innerText = wrapperIsPinned(card) ? 'Read More' : 'Read Full Update';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        } else {
            // Expand
            container.style.display = 'block';
            container.style.webkitLineClamp = 'unset';
            const fullHeight = container.scrollHeight;
            container.style.maxHeight = fullHeight + 'px';
            card.classList.add('is-expanded');
            if (textSpan) textSpan.innerText = 'Show Less';
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        }
    }

    function wrapperIsPinned(el) {
        return el.classList.contains('pinned-expansion-wrapper') || el.closest('.pinned-expansion-wrapper');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('scrollTopBtn');
        if (!btn) return;

        const scrollTarget = document.querySelector('.panel-scroll') || document.querySelector('main') || window;
        
        scrollTarget.addEventListener('scroll', () => {
            const currentTop = (scrollTarget === window) ? window.pageYOffset : scrollTarget.scrollTop;
            if (currentTop > 400) {
                btn.style.display = 'flex';
            } else {
                btn.style.display = 'none';
            }
        }, { passive: true });

        btn.addEventListener('click', () => {
            scrollTarget.scrollTo({ top: 0, behavior: 'smooth' });
            
            if (scrollTarget !== window) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            
            setTimeout(() => {
                if (((scrollTarget === window) ? window.pageYOffset : scrollTarget.scrollTop) > 10) {
                    scrollTarget.scrollTop = 0;
                    window.scrollTo(0, 0);
                }
            }, 1000);
        });
    });
</script>
@endpush
@endsection
