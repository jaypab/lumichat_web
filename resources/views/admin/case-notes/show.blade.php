@extends('layouts.admin')
@section('title','Admin - Case Note #'.$note->id)
@section('page_title', 'Case Note')

@section('content')
@php
  $year = $note->note_date
          ? \Carbon\Carbon::parse($note->note_date)->format('Y')
          : (\Carbon\Carbon::parse($note->created_at)->format('Y') ?? now()->format('Y'));
  $code = 'CN-'.$year.'-'.str_pad($note->id, 4, '0', STR_PAD_LEFT);
  $date = $note->note_date ? \Carbon\Carbon::parse($note->note_date)->format('F d, Y') : '—';
  $toBr = fn($v) => nl2br(e($v ?? '—'));

  // be defensive: some old notes might not have this column
  $noteSource = $note->note_source ?? null;
@endphp

{{-- ========= Page Header (Clean SaaS Layout) ========= --}}
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-2">
  <div class="flex flex-col gap-1.5">
    <div class="flex items-center gap-3">
      <a href="{{ route('admin.case-notes.index') }}" 
         class="group flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50/30 transition-all duration-200 shadow-sm active:scale-95">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
        Case Note <span class="text-indigo-600">#{{ $code }}</span>
      </h1>
      @if($noteSource === 'Walk-in')
        <div class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 text-[11px] font-black uppercase tracking-wider ring-1 ring-inset ring-amber-500/20 shadow-sm">
          Walk-in
        </div>
      @endif
    </div>
    <p class="text-slate-500 text-sm font-medium ml-13">Saved and encrypted on {{ \Carbon\Carbon::parse($note->updated_at)->format('M d, Y • g:i A') }}</p>
  </div>

  <div class="flex items-center gap-3 ml-13 sm:ml-0">
    <a href="{{ route('admin.case-notes.show.export.pdf', $note->id) }}"
       target="_blank" rel="noopener"
       class="group inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2.5 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
      <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 10l5 5 5-5M12 15V3M5 19h14a2 2 0 002-2v-2H3v2a2 2 0 002 2z"/>
      </svg>
      <span>Download PDF</span>
    </a>
  </div>
</header>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  {{-- Left Sidebar: Key Info --}}
  <aside class="lg:col-span-4 space-y-6">
    <div class="rounded-3xl bg-white border border-slate-200/60 shadow-xl shadow-slate-200/40 p-6 space-y-6">
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Student Informant</label>
        <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-100 shadow-inner group transition-colors hover:bg-white hover:border-slate-200">
          <div class="w-12 h-12 rounded-[1.25rem] bg-indigo-600 flex items-center justify-center text-white text-lg font-black shadow-lg shadow-indigo-200 group-hover:scale-105 transition-transform duration-200">
            {{ strtoupper(substr($note->student_display_name ?? $note->student_name ?? 'U', 0, 1)) }}
          </div>
          <div>
            <div class="text-sm font-black text-slate-900 leading-tight">{{ $note->student_display_name ?? $note->student_name ?? 'Unknown Student' }}</div>
            <div class="text-[11px] font-bold text-slate-500 mt-1 uppercase tracking-tight">Active Student</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4">
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Session Date</label>
          <div class="px-4 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-sm font-bold text-slate-700 flex items-center gap-3">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ $date }}
          </div>
        </div>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Attending Counselor</label>
          <div class="px-4 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-sm font-bold text-slate-700 flex items-center gap-3">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            {{ $note->counselor_name ?? 'Not Assigned' }}
          </div>
        </div>
      </div>

      @if($note->scheduled_at)
        <div class="pt-4 border-t border-slate-100">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Linked Appointment</label>
          <div class="p-3 rounded-2xl bg-indigo-50/50 border border-indigo-100/50">
            <div class="text-[11px] font-black text-indigo-900 leading-tight">{{ \Carbon\Carbon::parse($note->scheduled_at)->format('M d, Y • g:i A') }}</div>
            <div class="flex items-center gap-2 mt-2">
               <span class="px-2 py-0.5 rounded-lg bg-indigo-100 text-indigo-700 text-[10px] font-black uppercase tracking-widest">
                 {{ $note->appt_status ?? 'Confirmed' }}
               </span>
            </div>
          </div>
        </div>
      @endif
    </div>
  </aside>

  {{-- Main Content: Note Body --}}
  <main class="lg:col-span-8 space-y-6">
    <div class="rounded-3xl bg-white border border-slate-200/60 shadow-xl shadow-slate-200/40 overflow-hidden">
      <div class="bg-slate-50/80 border-b border-slate-100 px-8 py-4 flex items-center justify-between">
        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Clinical Narrative</h3>
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
          <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">System Verified</span>
        </div>
      </div>

      <div class="p-8 space-y-10">
        {{-- Section 1 --}}
        <section class="group">
          <div class="flex items-center gap-4 mb-4">
            <span class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-black ring-1 ring-slate-200 transition-colors group-hover:bg-indigo-600 group-hover:text-white group-hover:ring-indigo-500">01</span>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-900 transition-colors">Presenting Problem</h4>
          </div>
          <div class="pl-12">
            <div class="text-[15px] text-slate-700 leading-relaxed font-medium whitespace-pre-line bg-slate-50/30 p-6 rounded-2xl border border-slate-100/60 shadow-inner group-hover/main:bg-white transition-colors duration-300">
               {!! $toBr($note->presenting_problem) !!}
            </div>
          </div>
        </section>

        {{-- Section 2 --}}
        <section class="group">
          <div class="flex items-center gap-4 mb-4">
            <span class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-black ring-1 ring-slate-200 transition-colors group-hover:bg-indigo-600 group-hover:text-white group-hover:ring-indigo-500">02</span>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-900 transition-colors">Observations</h4>
          </div>
          <div class="pl-12">
            <div class="text-[15px] text-slate-700 leading-relaxed font-medium whitespace-pre-line bg-slate-50/30 p-6 rounded-2xl border border-slate-100/60 shadow-inner">
               {!! $toBr($note->observations) !!}
            </div>
          </div>
        </section>

        {{-- Section 3 --}}
        <section class="group">
          <div class="flex items-center gap-4 mb-4">
            <span class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-black ring-1 ring-slate-200 transition-colors group-hover:bg-indigo-600 group-hover:text-white group-hover:ring-indigo-500">03</span>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-900 transition-colors">Interventions</h4>
          </div>
          <div class="pl-12">
            <div class="text-[15px] text-slate-700 leading-relaxed font-medium whitespace-pre-line bg-slate-50/30 p-6 rounded-2xl border border-slate-100/60 shadow-inner">
               {!! $toBr($note->interventions) !!}
            </div>
          </div>
        </section>

        {{-- Section 4 --}}
        <section class="group">
          <div class="flex items-center gap-4 mb-4">
            <span class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-black ring-1 ring-slate-200 transition-colors group-hover:bg-indigo-600 group-hover:text-white group-hover:ring-indigo-500">04</span>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-900 transition-colors">Response / Insight</h4>
          </div>
          <div class="pl-12">
            <div class="text-[15px] text-slate-700 leading-relaxed font-medium whitespace-pre-line bg-slate-50/30 p-6 rounded-2xl border border-slate-100/60 shadow-inner">
               {!! $toBr($note->response) !!}
            </div>
          </div>
        </section>

        {{-- Section 5 --}}
        <section class="group">
          <div class="flex items-center gap-4 mb-4">
            <span class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-black ring-1 ring-slate-200 transition-colors group-hover:bg-indigo-600 group-hover:text-white group-hover:ring-indigo-500">05</span>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-900 transition-colors">Plan / Follow-Up</h4>
          </div>
          <div class="pl-12">
            <div class="text-[15px] text-slate-700 leading-relaxed font-medium whitespace-pre-line bg-indigo-50/20 p-6 rounded-2xl border border-indigo-100/50 shadow-inner shadow-indigo-900/5 transition-all group-hover:bg-indigo-50/40">
               {!! $toBr($note->plan_followup) !!}
            </div>
          </div>
        </section>
      </div>

      <div class="bg-slate-50/50 border-t border-slate-100 px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
          </div>
          <div>
            <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 leading-tight italic">Digital Signature Verified</div>
            <div class="text-[12px] font-black text-slate-900 leading-tight mt-0.5">{{ $note->counselor_name ?? 'Authorized Counselor' }}</div>
          </div>
        </div>
        <div class="text-[10px] font-black uppercase tracking-widest text-slate-300">Confidential Administration Record</div>
      </div>
    </div>
  </main>
</div>
@endsection
