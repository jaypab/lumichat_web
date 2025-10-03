@php
  // dot color per status
  $dot = [
    'pending'   => 'bg-amber-500',
    'confirmed' => 'bg-blue-500',
    'canceled'  => 'bg-red-500',
    'completed' => 'bg-emerald-500',
  ][$status] ?? 'bg-gray-400';
@endphp

<span id="badge-{{ $id }}"
      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $cls }}">
  <span class="mr-2 inline-block h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
  {{ ucfirst($status) }}
</span>
