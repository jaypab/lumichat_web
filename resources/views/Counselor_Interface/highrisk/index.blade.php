@extends('layouts.counselor')
@section('title','High-Risk Reviews')
@section('page_title','High-Risk Reviews')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

  {{-- Tabs --}}
  <div class="bg-white rounded-xl shadow p-3 flex gap-2">
    @php $status = request('status','pending'); @endphp
    <a href="{{ route('counselor.highrisk.index',['status'=>'pending']) }}"
       class="px-3 py-2 rounded {{ $status==='pending' ? 'bg-amber-500 text-white' : 'bg-slate-100' }}">Pending</a>
    <a href="{{ route('counselor.highrisk.index',['status'=>'accepted']) }}"
       class="px-3 py-2 rounded {{ $status==='accepted' ? 'bg-emerald-600 text-white' : 'bg-slate-100' }}">Accepted</a>
    <a href="{{ route('counselor.highrisk.index',['status'=>'downgraded']) }}"
       class="px-3 py-2 rounded {{ $status==='downgraded' ? 'bg-indigo-600 text-white' : 'bg-slate-100' }}">Downgraded</a>
  </div>

  {{-- Table / Empty --}}
  <div class="bg-white rounded-xl shadow">
    @if ($rows->count() === 0)
      <div class="p-6 text-slate-600">No items found.</div>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr class="text-left text-slate-600">
              <th class="px-5 py-3">When</th>
              <th class="px-5 py-3">Session</th>
              <th class="px-5 py-3">Trigger</th>
              <th class="px-5 py-3">Score</th>
              <th class="px-5 py-3">Snippet</th>
              <th class="px-5 py-3 w-20"></th>
            </tr>
          </thead>
          <tbody class="divide-y">
          @foreach ($rows as $r)
            <tr>
              <td class="px-5 py-3 text-slate-600">{{ optional($r->occurred_at)->format('Y-m-d H:i') ?? '—' }}</td>
              <td class="px-5 py-3">#{{ $r->session_id }}</td>
              <td class="px-5 py-3"><span class="text-rose-600 font-medium">{{ $r->detected_word ?? '—' }}</span></td>
              <td class="px-5 py-3">{{ $r->risk_score ?? '—' }}</td>
              <td class="px-5 py-3 italic text-slate-700">{{ Str::limit($r->snippet ?? '—', 120) }}</td>
              <td class="px-5 py-3">
                <a href="{{ route('counselor.highrisk.show',$r->id) }}" class="text-indigo-600 hover:underline">Open</a>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="px-5 py-3">
        {{ $rows->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
