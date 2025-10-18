@extends('layouts.counselor')
@section('title','Review High-Risk Snippet')
@section('page_title','Review High-Risk Snippet')

@section('content')
<div class="max-w-3xl mx-auto p-6 space-y-6">
  <div class="bg-white rounded-xl shadow p-6 space-y-3">
    <div class="text-sm text-slate-600">Session #{{ $item->session_id }}</div>
    <div>Trigger word: <span class="font-semibold text-rose-600">{{ $item->detected_word ?? '—' }}</span></div>
    <div>Detected score: <span class="font-semibold">{{ $item->risk_score }}</span></div>
    <div>Occurred: {{ $item->occurred_at?->format('Y-m-d H:i') ?? '—' }}</div>
    <div class="mt-2">
      <div class="text-sm font-medium text-slate-700">Flagged line(s)</div>
      <blockquote class="mt-1 p-3 bg-slate-50 rounded border italic">
        {{ $item->snippet }}
      </blockquote>
    </div>
  </div>

  <form method="POST" action="{{ route('counselor.highrisk.update',$item->id) }}" class="bg-white rounded-xl shadow p-6 space-y-4">
    @csrf @method('PUT')
    <div>
      <label class="text-sm font-medium">Decision</label>
      <select name="review_status" class="mt-1 w-full border rounded-lg px-3 py-2">
        <option value="accepted">Keep as High-Risk</option>
        <option value="downgraded">Downgrade to Normal</option>
      </select>
    </div>
    <div>
      <label class="text-sm font-medium">Notes (optional)</label>
      <textarea name="review_notes" rows="3" class="mt-1 w-full border rounded-lg px-3 py-2"></textarea>
    </div>
    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Save Review</button>
  </form>
</div>
@endsection
