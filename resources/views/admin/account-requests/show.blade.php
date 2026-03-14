@extends('layouts.admin')

@section('title', 'Admin - Account Request Detail')
@section('page_title', 'Account Request Detail')

@section('content')
<div class="max-w-4xl mx-auto p-6 space-y-6">
  <div class="flex items-center justify-between">
    <h2 class="text-2xl font-bold text-slate-900">{{ $accountRequest->name }}</h2>
    <a href="{{ route('admin.account-requests.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Back</a>
  </div>

  <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div><p class="text-xs uppercase text-slate-500">Status</p><p class="font-semibold text-slate-900">{{ ucfirst($accountRequest->status) }}</p></div>
    <div><p class="text-xs uppercase text-slate-500">Submitted</p><p class="font-semibold text-slate-900">{{ optional($accountRequest->created_at)->format('M d, Y h:i A') }}</p></div>
    <div><p class="text-xs uppercase text-slate-500">SIS</p><p class="font-semibold text-slate-900">{{ $accountRequest->sis }}</p></div>
    <div><p class="text-xs uppercase text-slate-500">Email</p><p class="font-semibold text-slate-900">{{ $accountRequest->email }}</p></div>
    <div><p class="text-xs uppercase text-slate-500">Course</p><p class="font-semibold text-slate-900">{{ $accountRequest->course }}</p></div>
    <div><p class="text-xs uppercase text-slate-500">Year Level</p><p class="font-semibold text-slate-900">{{ $accountRequest->year_level }}</p></div>
    <div><p class="text-xs uppercase text-slate-500">Contact Number</p><p class="font-semibold text-slate-900">{{ $accountRequest->contact_number }}</p></div>
    <div>
      <p class="text-xs uppercase text-slate-500">Attachment</p>
      @if($accountRequest->attachment_path)
        <a href="{{ Storage::disk('public')->url($accountRequest->attachment_path) }}" target="_blank" rel="noopener" class="font-semibold text-indigo-600 hover:text-indigo-500">Open Attachment</a>
      @else
        <p class="font-semibold text-slate-500">None</p>
      @endif
    </div>
  </div>

  @if($accountRequest->status === 'pending')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <form method="POST" action="{{ route('admin.account-requests.approve', $accountRequest) }}" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 space-y-3">
        @csrf
        <h3 class="text-base font-bold text-emerald-800">Approve Request</h3>
        <p class="text-sm text-emerald-700">Creates a student account and sends a secure password setup email.</p>
        <label class="block text-sm text-emerald-800">
          Notes (optional)
          <textarea name="review_notes" rows="3" class="mt-1 w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm"></textarea>
        </label>
        <button class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
          Approve and Send Setup Email
        </button>
      </form>

      <form method="POST" action="{{ route('admin.account-requests.reject', $accountRequest) }}" class="rounded-2xl border border-rose-200 bg-rose-50 p-5 space-y-3">
        @csrf
        <h3 class="text-base font-bold text-rose-800">Reject Request</h3>
        <p class="text-sm text-rose-700">Reject this request and provide a reason.</p>
        <label class="block text-sm text-rose-800">
          Rejection reason
          <textarea name="review_notes" rows="3" required class="mt-1 w-full rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm"></textarea>
        </label>
        <button class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">
          Reject Request
        </button>
      </form>
    </div>
  @else
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
      <h3 class="text-base font-bold text-slate-900">Review Information</h3>
      <p class="text-sm text-slate-700"><span class="font-semibold">Reviewed by:</span> {{ optional($accountRequest->reviewer)->name ?? 'N/A' }}</p>
      <p class="text-sm text-slate-700"><span class="font-semibold">Reviewed at:</span> {{ optional($accountRequest->reviewed_at)->format('M d, Y h:i A') ?? 'N/A' }}</p>
      <p class="text-sm text-slate-700"><span class="font-semibold">Notes:</span> {{ $accountRequest->review_notes ?: 'None' }}</p>
      @if($accountRequest->status === 'approved' && $accountRequest->approvedUser)
        <p class="text-sm text-slate-700"><span class="font-semibold">Created account:</span> {{ $accountRequest->approvedUser->name }} ({{ $accountRequest->approvedUser->email }})</p>
      @endif
    </div>
  @endif
</div>
@endsection
