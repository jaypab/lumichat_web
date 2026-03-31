@extends('layouts.admin')

@section('page_title', 'Create Announcement')

@section('content')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* Custom Switch from Uiverse.io by lenin55 */ 
.cl-toggle-switch {
 position: relative;
}
.cl-switch {
 position: relative;
 display: inline-block;
}
.cl-switch > input {
 appearance: none !important;
 -webkit-appearance: none !important;
 -moz-appearance: none !important;
 z-index: -1;
 position: absolute;
 right: 6px;
 top: -8px;
 display: block;
 margin: 0;
 border-radius: 50%;
 width: 40px;
 height: 40px;
 background-color: rgb(0, 0, 0, 0.38);
 outline: none;
 opacity: 0 !important;
 transform: scale(1);
 pointer-events: none;
 transition: opacity 0.3s 0.1s, transform 0.2s 0.1s;
}
.cl-switch > span::before {
 content: "";
 float: right;
 display: inline-block;
 margin: 5px 0 5px 10px;
 border-radius: 7px;
 width: 36px;
 height: 14px;
 background-color: rgb(0, 0, 0, 0.38);
 vertical-align: top;
 transition: background-color 0.2s, opacity 0.2s;
}
.cl-switch > span::after {
 content: "";
 position: absolute;
 top: 2px;
 right: 16px;
 border-radius: 50%;
 width: 20px;
 height: 20px;
 background-color: #fff;
 box-shadow: 0 3px 1px -2px rgba(0, 0, 0, 0.2), 0 2px 2px 0 rgba(0, 0, 0, 0.14), 0 1px 5px 0 rgba(0, 0, 0, 0.12);
 transition: background-color 0.2s, transform 0.2s;
}
.cl-switch > input:checked {
 right: -10px;
 background-color: #85b8b7;
}
.cl-switch > input:checked + span::before {
 background-color: #85b8b7;
}
.cl-switch > input:checked + span::after {
 background-color: #018786;
 transform: translateX(16px);
}
.cl-switch:hover > input {
 opacity: 0.04;
}
.cl-switch > input:focus {
 opacity: 0.12;
}
.cl-switch:hover > input:focus {
 opacity: 0.16;
}
.cl-switch > input:active {
 opacity: 1;
 transform: scale(0);
 transition: transform 0s, opacity 0s;
}
.cl-switch > input:active + span::before {
 background-color: #8f8f8f;
}
.cl-switch > input:checked:active + span::before {
 background-color: #85b8b7;
}
.cl-switch > input:disabled {
 opacity: 0;
}
.cl-switch > input:disabled + span::before {
 background-color: #ddd;
}
.cl-switch > input:checked:disabled + span::before {
 background-color: #bfdbda;
}
.cl-switch > input:checked:disabled + span::after {
 background-color: #61b5b4;
}

/* Premium Flatpickr Theme */
.flatpickr-calendar {
    background: rgba(255, 255, 255, 0.98) !important;
    border-radius: 20px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15) !important;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
    backdrop-filter: blur(10px);
}
.flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange {
    background: #6366f1 !important;
    border-color: #6366f1 !important;
    box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4) !important;
}
.flatpickr-day:hover {
    background: #f1f5f9 !important;
}
.flatpickr-months .flatpickr-month {
    color: #0f172a !important;
    font-weight: 800 !important;
}
.flatpickr-current-month .flatpickr-monthDropdown-months {
    font-weight: 800 !important;
}
.flatpickr-time input:focus {
    background: #f8fafc !important;
}
</style>
@endpush

<div class="max-w-4xl mx-auto px-4 pb-12">
    {{-- Navigation & Header --}}
    <div class="mb-8">
        <a href="{{ route('admin.announcements.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-indigo-600 transition-all mb-4 group">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 transition-colors">
                <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </div>
            Back to List
        </a>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            </div>
            <div>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tighter leading-none italic">Compose New Broadcast</h2>
                <p class="text-slate-500 dark:text-slate-400 mt-1.5 text-base font-medium">Reach out to your students with important updates and news.</p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.announcements.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <div class="bg-white/80 dark:bg-slate-900/50 rounded-[2.5rem] border border-slate-200/60 dark:border-slate-700 shadow-[0_20px_50px_rgba(8,112,184,0.05)] overflow-hidden backdrop-blur-xl">
            <div class="p-6 sm:p-10 space-y-8">
                {{-- Title --}}
                <div class="space-y-3">
                    <label for="title" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        Broadcast Title
                    </label>
                    <input type="text" name="title" id="title" required
                           class="w-full px-5 py-4 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/60 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-semibold text-slate-700 dark:text-slate-200 placeholder-slate-400/70"
                           placeholder="e.g., Final Examination Schedule for 2nd Semester" value="{{ old('title') }}">
                    @error('title') <p class="text-xs font-bold text-rose-500 mt-1 pl-1">⚠ {{ $message }}</p> @enderror
                </div>

                {{-- Content --}}
                <div class="space-y-3">
                    <label for="content" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        Message Content
                    </label>
                    <textarea name="content" id="content" rows="6" required
                              class="w-full px-5 py-4 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/60 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-600 dark:text-slate-300 placeholder-slate-400/70 leading-relaxed"
                              placeholder="Write your announcement details here...">{{ old('content') }}</textarea>
                    @error('content') <p class="text-xs font-bold text-rose-500 mt-1 pl-1">⚠ {{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 pt-4">
                    {{-- Priority --}}
                    <div class="space-y-4">
                        <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Priority Level
                        </label>
                        <div class="flex gap-3">
                            @php
                                $priorities = [
                                    'low'    => [
                                        'hover'  => 'group-hover:border-emerald-400 group-hover:bg-emerald-50/50',
                                        'active' => 'peer-checked:border-emerald-500 peer-checked:bg-emerald-500 dark:peer-checked:bg-emerald-600 peer-checked:shadow-emerald-500/20'
                                    ],
                                    'normal' => [
                                        'hover'  => 'group-hover:border-indigo-400 group-hover:bg-indigo-50/50',
                                        'active' => 'peer-checked:border-indigo-500 peer-checked:bg-indigo-500 dark:peer-checked:bg-indigo-600 peer-checked:shadow-indigo-500/20'
                                    ],
                                    'high'   => [
                                        'hover'  => 'group-hover:border-rose-400 group-hover:bg-rose-50/50',
                                        'active' => 'peer-checked:border-rose-500 peer-checked:bg-rose-500 dark:peer-checked:bg-rose-600 peer-checked:shadow-rose-500/20'
                                    ],
                                ];
                            @endphp
                            @foreach($priorities as $label => $theme)
                            <label class="relative flex-1 cursor-pointer group">
                                <input type="radio" name="priority" value="{{ $label }}" class="peer sr-only" {{ old('priority', 'normal') === $label ? 'checked' : '' }}>
                                <div @class([
                                    "px-3 py-3 text-center rounded-2xl border transition-all duration-300 active:scale-95",
                                    "border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40",
                                    $theme['hover'],
                                    $theme['active'],
                                    "peer-checked:text-white peer-checked:shadow-lg"
                                ])>
                                    <span class="text-[11px] font-black uppercase tracking-widest">{{ $label }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Visibility Toggle --}}
                    <div class="space-y-4">
                        <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Visibility
                        </label>
                        <div class="flex items-center p-1 bg-slate-100/50 dark:bg-slate-900/60 rounded-2xl border border-slate-200 dark:border-slate-800 w-full sm:w-fit shrink-0">
                            <div class="flex items-center gap-3 px-4 py-2 w-full">
                                <div class="cl-toggle-switch flex-shrink-0">
                                    <label class="cl-switch">
                                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                        <span></span>
                                    </label>
                                </div>
                                <span class="text-[13px] font-black uppercase tracking-tight text-slate-600 dark:text-slate-300 transition-colors">Live for Students</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Temporal Config --}}
                <div class="pt-8 border-t border-slate-100 dark:border-slate-800/60">
                    <div class="mb-6">
                        <h3 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 w-fit px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Temporal Configuration
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 p-6 bg-slate-50/50 dark:bg-slate-900/30 rounded-3xl border border-slate-100 dark:border-slate-800/50">
                        {{-- Starts At --}}
                        <div class="space-y-3">
                            <label for="starts_at" class="text-[11px] font-black uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">Schedule Start (Optional)</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <input type="text" name="starts_at" id="starts_at" 
                                       class="flatpickr-input w-full pl-12 pr-12 py-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-semibold text-sm text-slate-600 dark:text-slate-300"
                                       placeholder="Pick start date..." value="{{ old('starts_at') }}">
                                <button type="button" onclick="clearFlatpickr('starts_at')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-300 hover:text-rose-500 transition-colors" title="Clear Date">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p class="text-[10px] font-bold text-slate-400 tracking-tight italic opacity-70">Leave blank for immediate broadcast.</p>
                        </div>

                        {{-- Expires At --}}
                        <div class="space-y-3">
                            <label for="expires_at" class="text-[11px] font-black uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">Expiration Date (Optional)</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <input type="text" name="expires_at" id="expires_at" 
                                       class="flatpickr-input w-full pl-12 pr-12 py-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-semibold text-sm text-slate-600 dark:text-slate-300"
                                       placeholder="Pick expiry date..." value="{{ old('expires_at') }}">
                                <button type="button" onclick="clearFlatpickr('expires_at')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-300 hover:text-rose-500 transition-colors" title="Clear Date">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p class="text-[10px] font-bold text-slate-400 tracking-tight italic opacity-70">Automatically hide after this time.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Submit Footer --}}
            <div class="px-6 py-6 sm:px-10 bg-slate-100/50 dark:bg-slate-950/20 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-3 max-w-[280px]">
                    <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-[11px] font-medium leading-relaxed text-slate-400 dark:text-slate-500 uppercase tracking-tight">Public broadcast visible to all registered students.</p>
                </div>
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <button type="reset" class="px-6 py-3.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 hover:text-rose-500 dark:hover:text-rose-400 transition-colors">
                        Reset Form
                    </button>
                    <button type="submit" class="flex-1 sm:flex-none px-10 py-4 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white rounded-2xl font-black uppercase tracking-[0.15em] text-[11px] transition-all shadow-[0_12px_24px_rgba(79,70,229,0.25)] hover:shadow-[0_15px_30px_rgba(79,70,229,0.35)] active:scale-95">
                        Post Announcement
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const config = {
            enableTime: true,
            altInput: true,
            altFormat: "F j, Y h:i K",
            dateFormat: "Y-m-d H:i",
            disableMobile: true, // Forces flatpickr on mobile for consistency
            animate: true
        };

        const pickers = {};
        
        pickers.starts_at = flatpickr("#starts_at", config);
        pickers.expires_at = flatpickr("#expires_at", config);

        window.clearFlatpickr = function(id) {
            if (pickers[id]) {
                pickers[id].clear();
            }
        };
    });
</script>
@endpush
@endsection
