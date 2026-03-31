@extends('layouts.admin')

@section('page_title', 'Edit Announcement')

@section('content')
<div class="max-w-4xl mx-auto px-4 pb-12">
    <div class="mb-8">
        <a href="{{ route('admin.announcements.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-600 transition-colors mb-4 group">
            <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Back to Dashboard
        </a>
        <h2 class="text-3xl font-bold text-slate-800 dark:text-white text-indigo-600 dark:text-indigo-400">Modify Broadcast</h2>
        <p class="text-slate-500 dark:text-slate-400 mt-2 text-lg italic opacity-80">Update the details or schedule of your announcement.</p>
    </div>

    <form action="{{ route('admin.announcements.update', $announcement) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')
        
        <div class="bg-white dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden backdrop-blur-md">
            <div class="p-6 sm:p-8 space-y-6">
                {{-- Title --}}
                <div class="space-y-2">
                    <label for="title" class="text-sm font-bold text-slate-700 dark:text-slate-300">Broadcast Title</label>
                    <input type="text" name="title" id="title" required
                           class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all @error('title') border-rose-500 @enderror"
                           placeholder="e.g., Final Examination Schedule..." value="{{ old('title', $announcement->title) }}">
                    @error('title') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Content --}}
                <div class="space-y-2">
                    <label for="content" class="text-sm font-bold text-slate-700 dark:text-slate-300">Message Content</label>
                    <textarea name="content" id="content" rows="6" required
                              class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all @error('content') border-rose-500 @enderror"
                              placeholder="Write your announcement details here...">{{ old('content', $announcement->content) }}</textarea>
                    @error('content') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                    {{-- Priority --}}
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-slate-700 dark:text-slate-300">Priority Level</label>
                        <div class="flex gap-3">
                            @foreach(['low' => 'slate', 'normal' => 'indigo', 'high' => 'rose'] as $label => $color)
                            <label class="relative flex-1 cursor-pointer">
                                <input type="radio" name="priority" value="{{ $label }}" class="peer sr-only" {{ old('priority', $announcement->priority) === $label ? 'checked' : '' }}>
                                <div class="px-3 py-2.5 text-center rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 peer-checked:border-{{$color}}-500 peer-checked:bg-{{$color}}-50 dark:peer-checked:bg-{{$color}}-900/20 peer-checked:text-{{$color}}-600 dark:peer-checked:text-{{$color}}-400 transition-all">
                                    <span class="text-sm font-bold capitalize">{{ $label }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Visibility Toggle --}}
                    <div class="space-y-2 flex flex-col">
                        <label class="text-sm font-bold text-slate-700 dark:text-slate-300">Visibility</label>
                        <label class="mt-1 relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $announcement->is_active) ? 'checked' : '' }}>
                            <div class="w-14 h-7 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-500"></div>
                            <span class="ml-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Live for Students</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-slate-700/50">
                    {{-- Starts At --}}
                    <div class="space-y-2">
                        <label for="starts_at" class="text-sm font-bold text-slate-700 dark:text-slate-300">Schedule Start (Optional)</label>
                        <input type="datetime-local" name="starts_at" id="starts_at" 
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all"
                               value="{{ old('starts_at', $announcement->starts_at ? $announcement->starts_at->format('Y-m-d\TH:i') : '') }}">
                        <p class="text-[11px] text-slate-400">Leave blank for immediate broadcast.</p>
                    </div>

                    {{-- Expires At --}}
                    <div class="space-y-2">
                        <label for="expires_at" class="text-sm font-bold text-slate-700 dark:text-slate-300">Expiration Date (Optional)</label>
                        <input type="datetime-local" name="expires_at" id="expires_at" 
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all font-inter"
                               value="{{ old('expires_at', $announcement->expires_at ? $announcement->expires_at->format('Y-m-d\TH:i') : '') }}">
                        <p class="text-[11px] text-slate-400">Automatically hide after this time.</p>
                    </div>
                </div>
            </div>
            
            {{-- Submit Footer --}}
            <div class="px-6 py-5 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-700/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-slate-400 max-w-sm italic">Created by {{ $announcement->author->name ?? 'System' }} on {{ $announcement->created_at->format('M d, Y') }}</p>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <a href="{{ route('admin.announcements.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
                        Cancel Changes
                    </a>
                    <button type="submit" class="flex-1 sm:flex-none px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold transition-all shadow-lg shadow-indigo-500/20 active:scale-95 shadow-indigo-500/20">
                        Update Broadcast
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
