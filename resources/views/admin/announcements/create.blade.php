@extends('layouts.admin')

@section('page_title', 'New Broadcast')

@section('content')
<div class="px-6 pb-12 relative" x-data="{ scrolled: false }" x-init="$window.addEventListener('scroll', () => { scrolled = window.scrollY > 10 })">
    {{-- Breadcrumb & Header --}}
    <div class="mb-10">
        <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-4 px-1">
            <a href="{{ route('admin.announcements.index') }}" class="hover:text-indigo-600 transition-colors">Broadcast History</a>
            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
            <span class="text-indigo-600/60 lowercase first-letter:uppercase">New Broadcast</span>
        </nav>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-xl shadow-indigo-500/20 active:scale-95 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <h2 class="text-3xl font-black text-slate-900 tracking-tight leading-none">Compose Broadcast</h2>
                <p class="text-slate-500 text-[13px] font-medium mt-3 tracking-tight">Reach out to your students with important updates.</p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.announcements.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        @csrf
        
        {{-- Main Content Column --}}
        <div class="lg:col-span-8 space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200/60 shadow-xl shadow-slate-200/50 overflow-hidden">
                <div class="p-6 sm:p-8 space-y-6">
                    <div class="space-y-4">
                        <label for="title" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-400 px-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                            Broadcast Title
                        </label>
                        <input type="text" name="title" id="title" required
                            class="w-full px-5 py-4 rounded-2xl border border-slate-100 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-black text-base text-slate-900 placeholder-slate-300"
                            placeholder="e.g., Final Examination Dates for SY 2023-2024" value="{{ old('title') }}">
                        @error('title') <p class="text-xs font-bold text-rose-500 mt-2 pl-2 tracking-tight italic">⚠ {{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-4">
                        <label for="content" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-400 px-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Message Details
                        </label>
                        <textarea name="content" id="content" rows="10" required
                            class="w-full px-5 py-4 rounded-2xl border border-slate-100 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-sm text-slate-700 placeholder-slate-300 leading-relaxed"
                            placeholder="Write the full details of your announcement here...">{{ old('content') }}</textarea>
                        @error('content') <p class="text-xs font-bold text-rose-500 mt-2 pl-2 tracking-tight italic">⚠ {{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Help Card --}}
            <div class="bg-indigo-600 rounded-3xl p-6 text-white shadow-xl shadow-indigo-200 overflow-hidden relative group">
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-700"></div>
                <div class="relative z-10">
                    <h4 class="text-lg font-black uppercase tracking-widest mb-2 italic">Pro-Tip for Broadcasters</h4>
                    <p class="text-indigo-100 font-medium leading-relaxed max-w-xl">
                        Pinned announcements (High Priority) will automatically appear at the top of the student dashboard. Use scheduling to time your messages perfectly.
                    </p>
                </div>
            </div>
        </div>

        {{-- Configuration Sidebar Column --}}
        <div class="lg:col-span-4 space-y-6">
            {{-- Settings Panel --}}
            <div class="bg-white rounded-3xl border border-slate-200/60 shadow-xl shadow-slate-200/50 overflow-hidden sticky top-8">
                <div class="p-6 space-y-6">
                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 border-b border-slate-50 pb-6 italic">Configuration</h3>

                    {{-- Visibility Toggle (Alpine.js) --}}
                    <div class="space-y-4" x-data="{ active: {{ old('is_active', 1) ? 'true' : 'false' }} }">
                        <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-400 px-1">
                            Broadcast Status
                        </label>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 transition-all" :class="active ? 'bg-emerald-50/50 border-emerald-100' : ''">
                            <span class="text-[12px] font-black uppercase tracking-tight" :class="active ? 'text-emerald-700' : 'text-slate-500'">Live for Students</span>
                            <input type="hidden" name="is_active" :value="active ? 1 : 0">
                            <button type="button" @click="active = !active" 
                                class="relative w-12 h-6 rounded-full transition-colors duration-200 focus:outline-none"
                                :class="active ? 'bg-emerald-500' : 'bg-slate-300'">
                                <span class="absolute left-1 top-1 w-4 h-4 rounded-full bg-white transition-transform duration-200"
                                    :class="active ? 'translate-x-6' : ''"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Priority Select --}}
                    <div class="space-y-4">
                        <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-400 px-1">
                            Priority Level
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['low' => 'slate', 'normal' => 'indigo', 'high' => 'rose'] as $label => $color)
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="priority" value="{{ $label }}" class="peer sr-only" {{ old('priority', 'normal') === $label ? 'checked' : '' }}>
                                <div class="px-2 py-2 text-center rounded-xl border border-slate-100 bg-slate-50/50 group-hover:bg-white peer-checked:border-{{$color}}-500 peer-checked:bg-{{$color}}-500 peer-checked:text-white transition-all peer-checked:shadow-lg peer-checked:shadow-{{$color}}-500/20 active:scale-95">
                                    <span class="text-[10px] font-black uppercase tracking-widest">{{ $label }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Temporal Configuration --}}
                    <div class="space-y-6 pt-6 border-t border-slate-50">
                        <div class="space-y-4">
                            <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-400 px-1">
                                Schedule Start
                            </label>
                            <div class="relative group">
                                <input type="datetime-local" name="starts_at" id="starts_at" 
                                    class="w-full px-5 py-4 rounded-2xl border border-slate-100 bg-slate-50 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-bold text-sm text-slate-700"
                                    value="{{ old('starts_at') }}">
                            </div>
                        </div>

                        <div class="space-y-4">
                            <label class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-slate-400 px-1">
                                Auto-Expiration
                            </label>
                            <div class="relative group">
                                <input type="datetime-local" name="expires_at" id="expires_at" 
                                    class="w-full px-5 py-4 rounded-2xl border border-slate-100 bg-slate-50 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-bold text-sm text-slate-700"
                                    value="{{ old('expires_at') }}">
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="pt-6 space-y-3">
                        <button type="submit" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-[0.2em] text-[10px] transition-all shadow-xl shadow-indigo-500/25 hover:shadow-indigo-500/40 active:scale-95 italic">
                            Launch Broadcast
                        </button>
                        <a href="{{ route('admin.announcements.index') }}" class="flex h-12 items-center justify-center w-full text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-rose-500 transition-colors">
                            Cancel Changes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
