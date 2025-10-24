@php
  // Weekdays only (1..5)
  $days = [1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri'];

  // Rehydrate old input or prefill when editing
  $existing = old('availability', isset($counselor)
      ? $counselor->availabilities
          ->whereIn('weekday', [1,2,3,4,5])
          ->map(fn($s)=>[
            'weekday'    => (int) $s->weekday,
            'start_time' => substr($s->start_time,0,5),
            'end_time'   => substr($s->end_time,0,5),
          ])->values()->toArray()
      : []);
@endphp

<div class="max-w-3xl mx-auto p-6 space-y-6">
  <a href="{{ route('admin.counselors.index') }}" class="text-sm text-gray-500 hover:underline">&larr; Back</a>

  @if ($errors->any())
    <div class="p-3 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
      <ul class="list-disc ml-5 text-sm">
        @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ $route }}" method="POST" class="space-y-6">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    {{-- Counselor Info --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 p-6 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-slate-700 mb-1">Full Name <span class="text-rose-600">*</span></label>
          <input type="text" name="name" required
                 value="{{ old('name', $counselor->name ?? '') }}"
                 class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm text-slate-700 mb-1">Email <span class="text-rose-600">*</span></label>
          <input type="email" name="email" required
                 value="{{ old('email', $counselor->email ?? '') }}"
                 class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm text-slate-700 mb-1">Contact No.</label>
          <input type="text" name="phone"
                 value="{{ old('phone', $counselor->phone ?? '') }}"
                 class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm text-slate-700 mb-1">Status</label>
          <select name="is_active" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="1" {{ old('is_active', $counselor->is_active ?? 1) ? 'selected':'' }}>Available</option>
            <option value="0" {{ old('is_active', $counselor->is_active ?? 1) ? '' : 'selected' }}>Not Available</option>
          </select>
        </div>
      </div>
    </div>


    <div class="flex justify-end">
      <button class="h-10 px-4 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
        Save
      </button>
    </div>
  </form>
</div>

<style>
  @media print { .screen-only { display: none !important; } }
</style>
