@extends('layouts.counselor')
@section('title','My Availability')
@section('page_title','My Availability')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

  {{-- Form card --}}
  <div class="bg-white rounded-xl shadow p-5">
    <form class="grid gap-4 sm:grid-cols-4 md:grid-cols-5 items-end" method="POST" action="{{ route('counselor.availability.store') }}">
      @csrf

      {{-- Weekday --}}
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Weekday</label>
        <select name="weekday" class="select-ui" required>
          @php $wd = old('weekday'); @endphp
          <option value="">Select…</option>
          <option value="1" {{ $wd==1?'selected':'' }}>Monday</option>
          <option value="2" {{ $wd==2?'selected':'' }}>Tuesday</option>
          <option value="3" {{ $wd==3?'selected':'' }}>Wednesday</option>
          <option value="4" {{ $wd==4?'selected':'' }}>Thursday</option>
          <option value="5" {{ $wd==5?'selected':'' }}>Friday</option>
          <option value="6" {{ $wd==6?'selected':'' }}>Saturday</option>
          <option value="7" {{ $wd==7?'selected':'' }}>Sunday</option>
        </select>
      </div>

      {{-- Start --}}
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Start</label>
        <input type="time" name="start_time" class="input-ui" value="{{ old('start_time') }}" required>
      </div>

      {{-- End --}}
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">End</label>
        <input type="time" name="end_time" class="input-ui" value="{{ old('end_time') }}" required>
      </div>

      {{-- Submit --}}
      <div>
        <button class="btn-primary w-full">Save</button>
      </div>
    </form>

    {{-- errors --}}
    @if ($errors->any())
      <div class="mt-4 text-sm text-rose-600">
        <ul class="list-disc pl-5">
          @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    {{-- success --}}
    @if (session('success'))
      <div class="mt-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
        {{ session('success') }}
      </div>
    @endif
  </div>

  {{-- Entries card --}}
  <div class="bg-white rounded-xl shadow">
    <div class="px-5 py-4 border-b">
      <h3 class="font-semibold">Entries</h3>
    </div>

    @if ($entries->count() === 0)
      <div class="p-6 text-slate-600">No availability entries yet.</div>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
          <tr class="text-left text-slate-600">
            <th class="px-5 py-3">Weekday</th>
            <th class="px-5 py-3">Start</th>
            <th class="px-5 py-3">End</th>
            <th class="px-5 py-3 w-24">Actions</th>
          </tr>
          </thead>
          <tbody class="divide-y">
          @php
            $wdMap=[1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
          @endphp
          @foreach ($entries as $row)
            <tr>
              <td class="px-5 py-3 font-medium">{{ $wdMap[$row->weekday] ?? $row->weekday }}</td>
              <td class="px-5 py-3">{{ \Illuminate\Support\Str::substr($row->start_time,0,5) }}</td>
              <td class="px-5 py-3">{{ \Illuminate\Support\Str::substr($row->end_time,0,5) }}</td>
              <td class="px-5 py-3">
                <form method="POST" action="{{ route('counselor.availability.destroy',$row->id) }}"
                      onsubmit="return confirm('Remove this window?');">
                  @csrf @method('DELETE')
                  <button class="inline-flex items-center px-3 py-1.5 rounded-lg text-white bg-rose-600 hover:bg-rose-700">
                    Delete
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="px-5 py-3">
        {{ $entries->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
