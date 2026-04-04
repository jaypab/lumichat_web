@extends('layouts.admin')

@section('title', 'Admin - Account Request Detail')
@section('page_title', 'Account Request Detail')

@php
  $status = strtolower($accountRequest->status);
  $badgeMap = [
    'pending'   => 'bg-amber-50 text-amber-800 ring-amber-500/30',
    'approved'  => 'bg-emerald-50 text-emerald-800 ring-emerald-500/30',
    'rejected'  => 'bg-rose-50 text-rose-800 ring-rose-500/30',
  ];
  $dotMap = [
    'pending'   => 'bg-amber-500',
    'approved'  => 'bg-emerald-500',
    'rejected'  => 'bg-rose-500',
  ];
  $chipCls = $badgeMap[$status] ?? 'bg-slate-100 text-slate-800 ring-slate-500/30';
  $dotCls  = $dotMap[$status] ?? 'bg-slate-500';
@endphp

@section('content')
<div class="max-w-4xl mx-auto p-6 space-y-6">

  {{-- ========= Header band ========= --}}
  <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 screen-only mb-4 mt-2">
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 leading-none">
          {{ $accountRequest->name }}
        </h1>
        
        {{-- Status chip --}}
        <span class="inline-flex items-center px-2.5 h-6 rounded-full text-[10px] font-bold tracking-wide leading-none ring-1 ring-inset {{ $chipCls }}">
          <span class="inline-block size-1.5 rounded-full {{ $dotCls }} mr-1.5"></span>
          {{ strtoupper($status) }}
        </span>
      </div>

      <div class="text-slate-500 text-[13px] font-medium flex items-center gap-1.5 mt-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="12" cy="12" r="10" stroke-width="2.5"></circle>
          <polyline points="12 6 12 12 16 14" stroke-width="2.5"></polyline>
        </svg>
        Submitted {{ optional($accountRequest->created_at)->diffForHumans() }}
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
      <a href="{{ route('admin.account-requests.index') }}"
         class="inline-flex items-center gap-2 rounded-xl bg-white text-slate-700 border border-slate-200 px-4 py-2 text-[13px] font-bold shadow-[0_1px_2px_rgba(0,0,0,0.02)] hover:bg-slate-50 hover:text-slate-900 active:scale-[.98] transition-all duration-200">
        <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to list
      </a>
    </div>
  </header>

  {{-- ========= Data Container ========= --}}
  <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200/60 overflow-visible mt-2 p-6 lg:p-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 lg:gap-10">
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Status</p>
        <p class="font-bold text-[15px] text-slate-900 leading-tight tracking-tight">{{ ucfirst($accountRequest->status) }}</p>
      </div>
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Submitted</p>
        <p class="font-bold text-[15px] text-slate-900 leading-tight tracking-tight">{{ optional($accountRequest->created_at)->format('M d, Y h:i A') }}</p>
      </div>
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">SIS</p>
        <p class="font-bold text-[15px] text-slate-900 leading-tight tracking-tight">{{ $accountRequest->sis }}</p>
      </div>
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Email</p>
        <p class="font-bold text-[15px] text-slate-900 leading-tight tracking-tight">{{ $accountRequest->email }}</p>
      </div>
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Course</p>
        <p class="font-bold text-[15px] text-slate-900 leading-tight tracking-tight">{{ $accountRequest->course }}</p>
      </div>
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Year Level</p>
        <p class="font-bold text-[15px] text-slate-900 leading-tight tracking-tight">{{ $accountRequest->year_level }}</p>
      </div>
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Contact Number</p>
        <p class="font-bold text-[15px] text-slate-900 leading-tight tracking-tight">{{ $accountRequest->contact_number }}</p>
      </div>
      <div class="flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Attachment</p>
        @if($accountRequest->attachment_path)
          <a href="{{ Storage::disk('public')->url($accountRequest->attachment_path) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-bold text-[14px] text-indigo-600 hover:text-indigo-700 underline underline-offset-2 w-fit">
             <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
             </svg>
            Open Attachment
          </a>
        @else
          <p class="font-bold text-[15px] text-slate-400 leading-tight tracking-tight">None</p>
        @endif
      </div>
    </div>
  </div>

  @if($accountRequest->status === 'pending')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-4">
      <form method="POST" action="{{ route('admin.account-requests.approve', $accountRequest) }}" class="flex flex-col justify-between rounded-3xl border border-emerald-200/60 bg-gradient-to-br from-emerald-50/50 to-emerald-50/20 p-6 sm:p-8 shadow-sm">
        @csrf
        <div>
          <h3 class="text-base font-black text-emerald-800 tracking-tight mb-1">Approve Request</h3>
          <p class="text-sm font-medium text-emerald-700/80 leading-relaxed mb-6">Creates a student account and sends a secure password setup email.</p>
          
          <label class="block mb-6">
            <span class="text-[11px] font-black uppercase tracking-widest text-emerald-600/70 mb-1.5 block">Notes (optional)</span>
            <textarea name="review_notes" rows="3" class="w-full rounded-2xl border-none bg-white px-4 py-3 text-sm font-medium shadow-sm ring-1 ring-emerald-200 focus:ring-2 focus:ring-emerald-500 transition-all duration-200 placeholder:text-emerald-300" placeholder="Add any approval notes here..."></textarea>
          </label>
        </div>
        
        <button class="inline-flex justify-center items-center rounded-2xl bg-emerald-600 px-5 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 active:scale-[.98] transition-all duration-200 w-full">
          Approve and Send Setup Email
        </button>
      </form>

      <form method="POST" action="{{ route('admin.account-requests.reject', $accountRequest) }}" class="flex flex-col justify-between rounded-3xl border border-rose-200/60 bg-gradient-to-br from-rose-50/50 to-rose-50/20 p-6 sm:p-8 shadow-sm">
        @csrf
        <div>
          <h3 class="text-base font-black text-rose-800 tracking-tight mb-1">Reject Request</h3>
          <p class="text-sm font-medium text-rose-700/80 leading-relaxed mb-6">Reject this request and provide a mandatory reason.</p>
          
          <label class="block mb-6">
            <span class="text-[11px] font-black uppercase tracking-widest text-rose-600/70 mb-1.5 block">Rejection Reason</span>
            <textarea name="review_notes" rows="3" required class="w-full rounded-2xl border-none bg-white px-4 py-3 text-sm font-medium shadow-sm ring-1 ring-rose-200 focus:ring-2 focus:ring-rose-500 transition-all duration-200 placeholder:text-rose-300" placeholder="Why is this request being rejected?"></textarea>
          </label>
        </div>

        <button class="inline-flex justify-center items-center rounded-2xl bg-rose-600 px-5 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-rose-700 active:scale-[.98] transition-all duration-200 w-full">
          Reject Request
        </button>
      </form>
    </div>
  @else
    <div class="rounded-3xl border border-slate-200/60 bg-indigo-50/30 p-6 sm:p-8 shadow-sm mt-4">
      <h3 class="text-[11px] font-black uppercase tracking-widest text-indigo-500 mb-5">Review Information</h3>
      
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="flex items-start gap-3">
          <div class="mt-0.5 shrink-0">
             <div class="flex items-center justify-center size-8 rounded-xl bg-white text-indigo-600 ring-1 ring-inset ring-slate-200 shadow-sm">
               <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                 <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM2 20a10 10 0 0120 0v2H2v-2z"/>
               </svg>
             </div>
          </div>
          <div class="flex flex-col">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Reviewed By</p>
            <p class="font-bold text-[14px] text-slate-900 leading-tight">{{ optional($accountRequest->reviewer)->name ?? 'N/A' }}</p>
            <p class="text-[13px] font-medium text-slate-500 mt-0.5">{{ optional($accountRequest->reviewed_at)->format('M d, Y h:i A') ?? 'N/A' }}</p>
          </div>
        </div>

        @if($accountRequest->status === 'approved' && $accountRequest->approvedUser)
        <div class="flex items-start gap-3">
          <div class="mt-0.5 shrink-0">
             <div class="flex items-center justify-center size-8 rounded-xl bg-white text-emerald-600 ring-1 ring-inset ring-slate-200 shadow-sm">
               <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
               </svg>
             </div>
          </div>
          <div class="flex flex-col">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Created Account</p>
            <p class="font-bold text-[14px] text-slate-900 leading-tight">{{ $accountRequest->approvedUser->name }}</p>
            <p class="text-[13px] font-medium text-slate-500 mt-0.5">{{ $accountRequest->approvedUser->email }}</p>
          </div>
        </div>
        @endif

        <div class="sm:col-span-2 mt-2 pt-4 border-t border-slate-200/60">
           <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Internal Notes</p>
           <p class="text-[14px] font-medium text-slate-700 leading-relaxed">{{ $accountRequest->review_notes ?: 'No additional notes provided.' }}</p>
        </div>
      </div>
    </div>
  @endif
</div>
@endsection
