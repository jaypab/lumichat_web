@extends('layouts.counselor')
@section('title','Counselor - Dashboard')
@section('page_title','Counselor Dashboard')

@section('content')
<div class="max-w-6xl mx-auto p-6 space-y-6">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-xl font-semibold">Welcome, Counselor</h2>
    <p class="text-sm text-slate-600 mt-2">Use the menu to manage availability and review high-risk snippets.</p>
    <div class="mt-4 flex gap-3">
      <a class="px-4 py-2 rounded-lg bg-indigo-600 text-white" href="{{ route('counselor.availability.index') }}">Manage Availability</a>
      <a class="px-4 py-2 rounded-lg bg-rose-600 text-white" href="{{ route('counselor.highrisk.index') }}">Review High-Risk</a>
    </div>
  </div>
</div>
@endsection
