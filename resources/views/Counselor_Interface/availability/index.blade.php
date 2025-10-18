@extends('layouts.counselor')
@section('title','My Availability')
@section('page_title','My Availability')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

  {{-- 2-column layout: Calendar | Form --}}
  <div class="bg-white rounded-xl shadow p-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      {{-- Calendar --}}
      <section>
        <div class="rounded-2xl bg-slate-800 text-white p-4">
          <div class="flex items-center justify-between">
            <button id="calPrev" type="button" class="px-3 py-1 rounded-lg bg-white/10 hover:bg-white/20 active:scale-[.98]">
              ‹
            </button>
            <div class="text-center">
              <div id="calMonth" class="tracking-widest text-blue-300 font-bold text-lg"></div>
            </div>
            <button id="calNext" type="button" class="px-3 py-1 rounded-lg bg-white/10 hover:bg-white/20 active:scale-[.98]">
              ›
            </button>
          </div>

          <div class="mt-4 grid grid-cols-7 text-center text-pink-300 font-semibold">
            <div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
          </div>

          <div id="calGrid" class="mt-2 grid grid-cols-7 gap-2 select-none"></div>

          <p class="mt-3 text-xs text-blue-200">
            Tip: Click a date to set availability for that day, or toggle “Repeat weekly” to save a recurring weekday window.
          </p>
        </div>
      </section>

      {{-- Form --}}
      <section>
        <form id="availForm" class="space-y-4" method="POST" action="{{ route('counselor.availability.store') }}">
          @csrf

          <div class="rounded-2xl border bg-white p-5">
            <h3 class="text-center text-slate-700 font-semibold mb-4">Available Time</h3>

            {{-- Selected date / weekday preview --}}
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm">
                <div class="text-slate-500">Selected date</div>
                <div id="datePreview" class="font-medium">—</div>
              </div>
              <label class="inline-flex items-center gap-2 text-sm">
                <input id="repeatToggle" type="checkbox" class="rounded border-slate-300">
                <span class="text-slate-700">Repeat weekly</span>
              </label>
            </div>

            {{-- Hidden fields that the controller expects --}}
            <input type="hidden" name="date" id="dateInput">
            <input type="hidden" name="weekday" id="weekdayInput">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">From</label>
            <input type="time" name="start_time" id="startTime"
                  class="input-ui w-full"
                  step="3600"    {{-- 60 min --}}
                  value="{{ old('start_time','09:00') }}" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">To</label>
            <input type="time" name="end_time" id="endTime"
                  class="input-ui w-full"
                  step="3600"    {{-- 60 min --}}
                  value="{{ old('end_time','10:00') }}" required>
          </div>
        </div>


            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                <select name="slot_type" class="select-ui w-full" required>
                  @php $st = old('slot_type','available'); @endphp
                  <option value="available" {{ $st==='available'?'selected':'' }}>Available</option>
                  <option value="blocked" {{ $st==='blocked'?'selected':'' }}>Blocked</option>
                </select>
              </div>
              <div class="flex items-end">
                <button class="btn-primary w-full">Save</button>
              </div>
            </div>

            {{-- Laravel errors --}}
            @if ($errors->any())
              <div class="mt-4 text-sm text-rose-600">
                <ul class="list-disc pl-5">
                  @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
              </div>
            @endif

            {{-- Success --}}
            @if (session('success'))
              <div class="mt-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                {{ session('success') }}
              </div>
            @endif
          </div>
        </form>
      </section>
    </div>
  </div>

  {{-- Entries card --}}
  <div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b">
      <h3 class="font-semibold">Saved Windows</h3>
    </div>

    @if ($entries->count() === 0)
      <div class="p-6 text-slate-600">No availability entries yet.</div>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
          <tr class="text-left text-slate-600">
            <th class="px-5 py-3">Date</th>
            <th class="px-5 py-3">Weekday</th>
            <th class="px-5 py-3">Type</th>
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
              <td class="px-5 py-3">
                {{ $row->date ? \Carbon\Carbon::parse($row->date)->format('M d, Y') : '—' }}
              </td>
              <td class="px-5 py-3 font-medium">{{ $wdMap[$row->weekday] ?? '—' }}</td>
              <td class="px-5 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs
                  {{ $row->slot_type==='available' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                  {{ ucfirst($row->slot_type) }}
                </span>
              </td>
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

{{-- Calendar JS (vanilla) --}}
<script>
(function(){
  const calGrid   = document.getElementById('calGrid');
  const calMonth  = document.getElementById('calMonth');
  const prevBtn   = document.getElementById('calPrev');
  const nextBtn   = document.getElementById('calNext');
  const dateInput = document.getElementById('dateInput');
  const weekdayInput = document.getElementById('weekdayInput');
  const datePreview = document.getElementById('datePreview');
  const repeatToggle = document.getElementById('repeatToggle');

  // Today @ 00:00 for safe comparisons
  const today = new Date(); today.setHours(0,0,0,0);

  let view = new Date();  // month in view
  view.setDate(1);
  let selected = null;

  function fmtYMD(d){
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const dd = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${dd}`;
  }
  function weekdayISO(d){ // 1..7 (Mon..Sun)
    const n = d.getDay(); // 0..6 (Sun..Sat)
    return n === 0 ? 7 : n;
  }
  function sameYM(a,b){ return a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth(); }

  function render(){
    // Header
    const monthName = new Intl.DateTimeFormat('en', {month:'long', year:'numeric'}).format(view);
    calMonth.textContent = monthName.toUpperCase();

    // Disable prev if at current month
    prevBtn.disabled = sameYM(view, today);
    prevBtn.classList.toggle('opacity-40', prevBtn.disabled);
    prevBtn.classList.toggle('cursor-not-allowed', prevBtn.disabled);

    // Grid
    calGrid.innerHTML = '';
    const firstDow = new Date(view.getFullYear(), view.getMonth(), 1).getDay(); // 0..6 Sun..Sat
    const daysInMonth = new Date(view.getFullYear(), view.getMonth()+1, 0).getDate();

    // leading blanks
    for (let i=0;i<firstDow;i++){
      const d = document.createElement('div');
      calGrid.appendChild(d);
    }

    for (let day=1; day<=daysInMonth; day++){
      const cellDate = new Date(view.getFullYear(), view.getMonth(), day);
      cellDate.setHours(0,0,0,0);

      const isWeekend = (cellDate.getDay() === 0) || (cellDate.getDay() === 6); // Sun(0) or Sat(6)
      const isPast    = cellDate < today;

      const disabled = isWeekend || isPast;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = String(day);
      btn.className =
        'w-10 h-10 mx-auto rounded-full flex items-center justify-center font-semibold ' +
        (disabled
          ? 'bg-white/50 text-slate-400 cursor-not-allowed'
          : 'bg-white text-slate-900 hover:bg-slate-100 active:scale-[.98]'
        );

      // selection ring
      if (!disabled && selected && fmtYMD(cellDate) === fmtYMD(selected)){
        btn.classList.add('ring-2','ring-indigo-400');
      }

      if (!disabled){
        btn.addEventListener('click', () => {
          selected = cellDate;
          updateSelection();
          render(); // repaint selection ring
        });
      }

      calGrid.appendChild(btn);
    }
  }

  function updateSelection(){
    if (!selected){
      datePreview.textContent = '—';
      dateInput.value = '';
      weekdayInput.value = '';
      return;
    }
    // If repeat weekly -> keep weekday (Mon..Fri only, because weekends are not selectable anyway)
    if (repeatToggle.checked){
      datePreview.textContent =
        new Intl.DateTimeFormat('en',{weekday:'long'}).format(selected) + ' (recurring)';
      dateInput.value = '';
      weekdayInput.value = String(weekdayISO(selected)); // 1..5 guaranteed
    } else {
      datePreview.textContent =
        new Intl.DateTimeFormat('en',{month:'short', day:'2-digit', year:'numeric'}).format(selected);
      dateInput.value = fmtYMD(selected);
      weekdayInput.value = '';
    }
  }

  repeatToggle.addEventListener('change', updateSelection);
  prevBtn.addEventListener('click', ()=>{
    if (prevBtn.disabled) return;
    view.setMonth(view.getMonth()-1);
    render();
  });
  nextBtn.addEventListener('click', ()=>{
    view.setMonth(view.getMonth()+1);
    render();
  });

  // Initialize to today (if weekend, jump to next Monday)
  (function initSelect(){
    let d = new Date(); d.setHours(0,0,0,0);
    const dow = d.getDay(); // 0 Sun .. 6 Sat
    if (dow === 0) d.setDate(d.getDate()+1);       // Sun -> Mon
    if (dow === 6) d.setDate(d.getDate()+2);       // Sat -> Mon
    selected = d;
  })();

  render();
  updateSelection();
    // --- 1h snap helpers ------------------------------------------
  const startEl = document.getElementById('startTime');
  const endEl   = document.getElementById('endTime');

  function pad2(n){ return String(n).padStart(2,'0'); }
  function addHour(hhmm, hours){
    const [h,m] = hhmm.split(':').map(Number);
    let d = new Date(2000,0,1,h,m||0,0,0);
    d.setHours(d.getHours()+hours);
    return pad2(d.getHours())+':'+pad2(d.getMinutes());
  }
  function snapToHour(hhmm){
    const [h] = hhmm.split(':').map(Number);
    return pad2(h)+':00';
  }

  function syncEndToStart(){
    if (!startEl || !endEl) return;
    // snap start to :00, then set end = start + 1h
    startEl.value = snapToHour(startEl.value || '09:00');
    endEl.value   = addHour(startEl.value, 1);
  }

  // When start changes -> force :00 and set end = +1h
  if (startEl) {
    startEl.addEventListener('change', syncEndToStart);
    startEl.addEventListener('input',  ()=>{ /* live typing: just keep last valid */});
  }

  // When end changes -> snap to :00 and ensure >= start+1h
  if (endEl) {
    endEl.addEventListener('change', ()=>{
      endEl.value = snapToHour(endEl.value || '10:00');
      // minimum 1h after start
      const minEnd = addHour(startEl.value || '09:00', 1);
      if (endEl.value <= minEnd) endEl.value = minEnd;
    });
  }

  // Initialize defaults at page load
  syncEndToStart();

})();
</script>
@endsection
