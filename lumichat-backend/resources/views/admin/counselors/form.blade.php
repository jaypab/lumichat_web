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

    {{-- Weekly Availability --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 p-6 space-y-5">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="font-semibold text-slate-800">Weekly Availability</h3>
          <p class="text-sm text-slate-500">Pick weekdays (Mon–Fri), set a time range, and add.</p>
        </div>
        <div class="inline-flex rounded-xl ring-1 ring-slate-200 bg-white overflow-hidden">
          <button type="button" id="presetWeekdays" class="px-3 py-1.5 text-sm hover:bg-slate-50">Mon–Fri</button>
          <div class="w-px bg-slate-200/80"></div>
          <button type="button" id="clearAllBtn" class="px-3 py-1.5 text-sm hover:bg-rose-50 text-rose-700">Clear</button>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <div>
            <p class="text-sm font-medium mb-2 text-slate-700">Select days</p>
            <fieldset aria-label="Select weekdays">
              <div class="grid grid-cols-5 gap-2 text-sm">
                @foreach($days as $value => $label)
                  <label class="inline-flex items-center gap-2">
                    <input type="checkbox" class="day-check rounded" value="{{ $value }}">
                    <span>{{ $label }}</span>
                  </label>
                @endforeach
              </div>
            </fieldset>
          </div>

          <div>
            <p class="text-sm font-medium mb-2 text-slate-700">Time range</p>
            <div class="grid grid-cols-2 gap-2" aria-describedby="qp-time-hint">
              <label for="qpStart" class="sr-only">Time in (start)</label>
              <input type="time" id="qpStart"
                     class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm text-center focus:ring-2 focus:ring-indigo-500" />
              <label for="qpEnd" class="sr-only">Time out (end)</label>
              <input type="time" id="qpEnd"
                     class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm text-center focus:ring-2 focus:ring-indigo-500" />
            </div>
            <p id="qp-time-hint" class="text-xs text-slate-500 mt-1">Set time in first, then time out, then click + Add.</p>
          </div>

          <div class="flex items-end">
            <button type="button" id="addQuickBtn"
                    class="w-full inline-flex items-center justify-center gap-1.5 h-10 px-3.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
              + Add to selected
            </button>
          </div>
        </div>
      </div>
a
      {{-- Existing slot rows --}}
      <div id="slots" class="space-y-3">
        @forelse ($existing as $i => $slot)
          <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end slot-row">
            <div>
              <label class="block text-[11px] uppercase tracking-wide text-slate-600 mb-1">Day</label>
              <select name="availability[{{ $i }}][weekday]" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
                @foreach($days as $value=>$label)
                  <option value="{{ $value }}" {{ $slot['weekday']==$value?'selected':'' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-[11px] uppercase tracking-wide text-slate-600 mb-1">Start</label>
              <input type="time" name="availability[{{ $i }}][start_time]"
                     value="{{ $slot['start_time'] }}" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500" required>
            </div>
            <div>
              <label class="block text-[11px] uppercase tracking-wide text-slate-600 mb-1">End</label>
              <input type="time" name="availability[{{ $i }}][end_time]"
                     value="{{ $slot['end_time'] }}" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500" required>
            </div>
            <button type="button" title="Remove slot"
                  class="remove-slot inline-flex items-center gap-2 h-9 px-3 rounded-xl bg-white text-rose-700
                        ring-1 ring-rose-300 hover:bg-rose-50 hover:ring-rose-400
                        focus:outline-none focus:ring-2 focus:ring-rose-500/60 text-sm font-medium">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path d="M19 7l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 7m3 0V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M4 7h16"
                      stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span class="sr-only">Remove</span>
            </button>
          </div>
        @empty
          <div class="px-4 py-8 text-center text-slate-500">
            <span class="uppercase tracking-wide text-[11px]">No availability added yet.</span>
          </div>
        @endforelse
      </div>

      {{-- Template for new rows --}}
      <template id="slotTemplate">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end slot-row">
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-slate-600 mb-1">Day</label>
            <select name="availability[__i__][weekday]" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
              @foreach($days as $value=>$label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-slate-600 mb-1">Start</label>
            <input type="time" name="availability[__i__][start_time]" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500" required>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-slate-600 mb-1">End</label>
            <input type="time" name="availability[__i__][end_time]" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500" required>
          </div>
          <button type="button"
                  class="remove-slot h-10 px-3 rounded-xl ring-1 ring-rose-200 bg-rose-50 text-rose-700 text-sm hover:bg-rose-100">
            Remove
          </button>
        </div>
      </template>

      {{-- SweetAlert2 --}}
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

      {{-- Planner & rows script --}}
      <script>
        (() => {
          const dayNames = {1:'Mon',2:'Tue',3:'Wed',4:'Thu',5:'Fri'};

          const slots = document.getElementById('slots');
          let i = document.querySelectorAll('#slots .slot-row').length || 0;

          const qpStart = document.getElementById('qpStart');
          const qpEnd   = document.getElementById('qpEnd');
          const checks  = Array.from(document.querySelectorAll('.day-check'));

          function timeOK(start, end) { return start && end && end > start; }
          function rowValues(row) {
            return {
              day: row.querySelector('select[name^="availability"]').value,
              start: row.querySelector('input[name$="[start_time]"]').value,
              end: row.querySelector('input[name$="[end_time]"]').value
            }
          }
          function overlaps(day, start, end) {
            const rows = Array.from(document.querySelectorAll('#slots .slot-row'));
            return rows.some(r => {
              const v = rowValues(r);
              return String(v.day) === String(day) && (start < v.end && v.start < end);
            });
          }
          function exactExists(day, start, end) {
            const rows = Array.from(document.querySelectorAll('#slots .slot-row'));
            return rows.some(r => {
              const v = rowValues(r);
              return String(v.day) === String(day) && v.start === start && v.end === end;
            });
          }

          function addSlot(dayIdx, start, end) {
            if (!timeOK(start, end)) { Swal.fire({icon:'error', title:'Time invalid', text:'End time must be after start time.', confirmButtonColor:'#ef4444'}); return; }
            if (exactExists(dayIdx, start, end)) { return; }
            if (overlaps(dayIdx, start, end)) { Swal.fire({icon:'warning', title:'Overlap', text:'This time overlaps another slot for the same day.', confirmButtonColor:'#4f46e5'}); return; }

            const tpl = document.getElementById('slotTemplate').innerHTML.replaceAll('__i__', i++);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = tpl.trim();
            const row = wrapper.firstChild;

            row.querySelector('select[name^="availability"]').value = dayIdx;
            row.querySelector('input[name$="[start_time]"]').value = start;
            row.querySelector('input[name$="[end_time]"]').value = end;

            slots.appendChild(row);
          }

          // Remove row
          slots?.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-slot')) {
              e.target.closest('.slot-row').remove();
            }
          });

          // Add from quick planner with confirmation
          document.getElementById('addQuickBtn')?.addEventListener('click', async () => {
            const selectedDays = checks.filter(c => c.checked).map(c => parseInt(c.value, 10));
            if (selectedDays.length === 0) { Swal.fire({icon:'warning', title:'Choose day(s)', text:'Select at least one weekday.', confirmButtonColor:'#4f46e5'}); return; }
            if (!timeOK(qpStart.value, qpEnd.value)) { Swal.fire({icon:'error', title:'Time invalid', text:'End time must be after start time.', confirmButtonColor:'#ef4444'}); return; }

            const list = selectedDays.map(d => dayNames[d]).join(', ');
            const confirmed = await Swal.fire({
              icon: 'question',
              title: 'Add availability?',
              html: `<div class="text-left">Days: <b>${list}</b><br/>Time: <b>${qpStart.value}</b> to <b>${qpEnd.value}</b></div>`,
              showCancelButton: true,
              confirmButtonText: 'Yes, add',
              cancelButtonText: 'Cancel',
              confirmButtonColor: '#4f46e5',
              cancelButtonColor: '#64748b'
            }).then(r => r.isConfirmed);

            if (!confirmed) return;

            selectedDays.forEach(d => addSlot(d, qpStart.value, qpEnd.value));
          });

          // Presets
          document.getElementById('presetWeekdays')?.addEventListener('click', () => {
            checks.forEach(c => c.checked = [1,2,3,4,5].includes(parseInt(c.value,10)));
          });

          // Clear all rows
          document.getElementById('clearAllBtn')?.addEventListener('click', () => {
            document.querySelectorAll('#slots .slot-row').forEach(r => r.remove());
          });
        })();
      </script>
    </div>

    {{-- Helper Note (outside the card) --}}
    <div class="p-4 mt-4 screen-only" role="note" aria-label="How to add availability">
      <div class="rounded-xl bg-indigo-50/70 text-indigo-900 ring-1 ring-indigo-200/70 p-3">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
            <path d="M12 17v-5m0-3h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <div class="text-sm leading-6">
            <p class="font-medium">How to build the counselor’s availability:</p>
            <ol class="list-decimal pl-5 mt-1 space-y-1.5">
              <li>Click the <strong>weekday buttons (Mon–Fri)</strong> to choose the day(s) you want.</li>
              <li>Set the <strong>time in</strong> (start) and <strong>time out</strong> (end). Make sure end is after start.</li>
              <li>Click <strong>+ Add</strong> to create the availability slot for the selected day(s).</li>
              <li>Repeat steps 1–3 as needed for other day(s) or time ranges.</li>
              <li>Review the list: you can <strong>edit times</strong> inline or <strong>Remove</strong> a slot.</li>
              <li>When finished, click <strong>Save</strong> to store the changes.</li>
            </ol>
          </div>
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
