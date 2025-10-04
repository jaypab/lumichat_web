@extends('layouts.admin')
@section('title','Admin - Add Counselor')
@section('page_title', 'Add Counselor') 

@section('content')
<div class="max-w-5xl mx-auto p-6 space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <a href="{{ route('admin.counselors.index') }}"
       class="inline-flex items-center gap-2 h-10 px-3.5 rounded-xl bg-white ring-1 ring-slate-200 text-slate-700
              hover:bg-slate-50 active:scale-[.99] transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
      Back to list
    </a>
    <h1 class="sr-only">Add Counselor</h1>
  </div>

  {{-- Alpine state wrapper (single form spans two cards) --}}
  <div x-data="CounselorForm()" x-init="init({{ json_encode(old('availability', [])) }})">

    <form method="POST" action="{{ route('admin.counselors.store') }}" novalidate>
      @csrf

      {{-- ===================== CARD 1: Counselor Details ===================== --}}
      <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-slate-200/70">
        {{-- violet accent inside the card --}}
        <span class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r
                     from-indigo-500 via-purple-500 to-fuchsia-500"></span>

        <div class="p-6 sm:p-8">
          <h2 class="text-lg font-semibold text-slate-800">Counselor Details</h2>
          <p class="text-sm text-slate-500">Add the counselor’s basic info and status.</p>

          <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700">
                Full Name <span class="text-rose-600">*</span>
              </label>
              <input name="name" value="{{ old('name') }}" required
                     class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"
                     type="text" placeholder="e.g., Juan Dela Cruz">
              @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700">
                Email <span class="text-rose-600">*</span>
              </label>
              <input name="email" value="{{ old('email') }}" required
                     class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"
                     type="email" placeholder="name@school.edu">
              @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700">Contact No.</label>
              <input name="phone" value="{{ old('phone') }}"
                     class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"
                     type="text" placeholder="09XXXXXXXXX">
              @error('phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700">Status</label>
              <select name="is_active"
                      class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="1" @selected(old('is_active',1)==1)>Available</option>
                <option value="0" @selected(old('is_active',1)==0)>Not Available</option>
              </select>
              @error('is_active') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
          </div>
        </div>
      </div>

      {{-- space between cards --}}
      <div class="h-4"></div>

      {{-- ===================== CARD 2: Weekly Availability ===================== --}}
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
        <div class="p-6 sm:p-8">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-800">Weekly Availability</h2>
              <p class="text-sm text-slate-500">Pick weekdays (Mon–Fri), set a time range, and add.</p>
            </div>
            <div class="inline-flex rounded-xl ring-1 ring-slate-200 bg-white overflow-hidden">
              <button type="button" @click="preset()" class="px-3 py-1.5 text-sm hover:bg-slate-50">Mon–Fri</button>
              <div class="w-px bg-slate-200/80"></div>
              <button type="button" @click="clearSelection()" class="px-3 py-1.5 text-sm hover:bg-rose-50 text-rose-700">Clear</button>
            </div>
          </div>

          <div class="mt-4 rounded-2xl border border-slate-200/70 bg-white">
            <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              {{-- Days --}}
              <div class="flex flex-wrap gap-1.5" role="group" aria-label="Select weekdays">
                <template x-for="d in days" :key="d.value">
                  <button type="button"
                          @click="toggleDay(d.value)"
                          :aria-pressed="isSelected(d.value)"
                          class="h-9 w-[72px] rounded-lg ring-1 text-sm font-medium transition
                                 flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          :class="isSelected(d.value)
                                  ? 'bg-indigo-600 text-white ring-indigo-600'
                                  : 'bg-white text-slate-700 hover:bg-slate-50 ring-slate-200'">
                    <span x-text="d.short"></span>
                  </button>
                </template>
              </div>

              {{-- Time + Add --}}
              <div class="flex items-center gap-2 w-full md:w-auto" aria-describedby="time-hint">
                <label for="time-in" class="sr-only">Time in (start)</label>
                <input id="time-in" x-model="range.start" type="time"
                       class="h-10 min-w-[150px] w-[150px] text-center rounded-lg border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"/>
                <span class="text-slate-500" aria-hidden="true">to</span>
                <label for="time-out" class="sr-only">Time out (end)</label>
                <input id="time-out" x-model="range.end" type="time"
                       class="h-10 min-w-[150px] w-[150px] text-center rounded-lg border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"/>
                <button
                  type="button"
                  @click="bulkAdd()"
                  :disabled="!selectedDays.length || !range.start || !range.end || range.end <= range.start"
                  class="inline-flex items-center gap-1.5 px-3.5 py-2 h-10 rounded-lg text-white text-sm font-medium
                        bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:hover:bg-indigo-600">
                  + Add
                </button>
              </div>
              <p id="time-hint" class="sr-only">Set time in first, then time out, then click Add.</p>
            </div>

            <div class="h-px bg-slate-200/70"></div>

            {{-- Slots list --}}
            <div class="p-4">
              <template x-if="!slots.length">
                <div class="px-4 py-8 text-center text-slate-500">
                  <span class="uppercase tracking-wide text-[11px]">No availability added yet.</span>
                </div>
              </template>

              <div class="grid gap-2.5" x-show="slots.length">
                <template x-for="(row, i) in slots" :key="i">
                  <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                    <div class="grid grid-cols-12 gap-2 items-center">
                      <div class="col-span-12 sm:col-span-3 lg:col-span-2">
                        <span class="inline-flex items-center h-8 px-3 rounded-full text-xs font-semibold
                                     bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200 whitespace-nowrap"
                              x-text="dayLabel(row.weekday)"></span>
                      </div>

                      <div class="col-span-12 sm:col-span-6 lg:col-span-7 grid grid-cols-9 gap-2 items-center">
                        <div class="col-span-4">
                          <label class="text-[11px] text-slate-500">Start</label>
                          <input type="time" x-model="row.start_time"
                                 :name="`availability[${i}][start_time]`"
                                 class="mt-0.5 h-9 w-full min-w-[150px] text-center rounded-lg border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="col-span-1 text-center text-slate-500 mt-4">–</div>
                        <div class="col-span-4">
                          <label class="text-[11px] text-slate-500">End</label>
                          <input type="time" x-model="row.end_time"
                                 :name="`availability[${i}][end_time]`"
                                 class="mt-0.5 h-9 w-full min-w-[150px] text-center rounded-lg border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <input type="hidden" :name="`availability[${i}][weekday]`" :value="row.weekday">
                      </div>

                      <div class="col-span-12 sm:col-span-3 lg:col-span-3 flex justify-start sm:justify-end sm:pr-[8px] lg:pr-[50px] mt-2 sm:mt-0">
                        <button type="button" @click="remove(i)"
                                title="Remove slot"
                                class="inline-flex items-center gap-2 h-9 px-3 rounded-xl bg-white text-rose-700
                                       ring-1 ring-rose-300 hover:bg-rose-50 hover:ring-rose-400
                                       focus:outline-none focus:ring-2 focus:ring-rose-500/60 text-sm font-medium">
                          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M19 7l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 7m3 0V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M4 7h16"
                                  stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                          </svg>
                          <span>Remove</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </template>
              </div>

              @if($errors->has('availability') || $errors->has('availability.*.start_time') || $errors->has('availability.*.end_time'))
                <p class="mt-3 text-xs text-rose-600">Please check your availability entries and time order.</p>
              @endif
            </div>
          </div>
        </div>
      </div>

      {{-- Helper Note --}}
      <div class="p-4 mt-2 screen-only" role="note" aria-label="How to add availability">
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

      {{-- Footer (single set of actions for the whole form) --}}
      <div class="px-6 sm:px-8 py-4 bg-slate-50 border-t border-slate-200/70 flex items-center justify-end gap-3">
        <a href="{{ route('admin.counselors.index') }}"
           class="inline-flex items-center h-10 px-4 rounded-xl bg-white text-slate-700 ring-1 ring-slate-200
                  hover:bg-slate-100 active:scale-[.99] transition">Cancel</a>

        <button type="submit"
                class="inline-flex items-center h-10 px-5 rounded-xl bg-indigo-600 text-white font-medium
                       hover:bg-indigo-700 active:scale-[.99] transition">
          Save
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  // HH:mm -> hh:mm AM/PM (display only)
  function fmt12(t) {
    if (!t) return '';
    const [H, M] = t.split(':').map(n => parseInt(n, 10));
    const ampm = H >= 12 ? 'PM' : 'AM';
    const h12  = ((H % 12) || 12).toString().padStart(2, '0');
    const mm   = (isNaN(M) ? 0 : M).toString().padStart(2, '0');
    return `${h12}:${mm} ${ampm}`;
  }

  function CounselorForm() {
    return {
      days: [
        { value:1, short:'Mon', long:'Monday' },
        { value:2, short:'Tue', long:'Tuesday' },
        { value:3, short:'Wed', long:'Wednesday' },
        { value:4, short:'Thu', long:'Thursday' },
        { value:5, short:'Fri', long:'Friday' },
      ],
      selectedDays: [],
      range: { start: '09:00', end: '12:00' }, // keep 24h for inputs & DB
      slots: [],

      init(oldSlots) {
        if (Array.isArray(oldSlots) && oldSlots.length) {
          const allowed = new Set([1,2,3,4,5]);
          this.slots = oldSlots
            .filter(s => allowed.has(Number(s.weekday)))
            .map(s => ({
              weekday: Number(s.weekday),
              start_time: (''+s.start_time).slice(0,5), // keep 24h in state
              end_time:   (''+s.end_time).slice(0,5),
            }))
            .filter(s => s.start_time && s.end_time)
            .sort((a,b)=> a.weekday - b.weekday || a.start_time.localeCompare(b.start_time));
        }
      },

      dayLabel(wd) { return ({1:'Monday',2:'Tuesday',3:'Wednesday',4:'Thursday',5:'Friday'})[wd] || ''; },
      isSelected(d) { return this.selectedDays.includes(d); },
      toggleDay(d) { this.isSelected(d) ? this.selectedDays = this.selectedDays.filter(x => x !== d) : this.selectedDays.push(d); this.selectedDays.sort((a,b)=>a-b); },
      preset() { this.selectedDays = [1,2,3,4,5]; },
      clearSelection() { this.selectedDays = []; },

      overlaps(d, start, end) {
        return this.slots.some(s => s.weekday===d && (start < s.end_time && s.start_time < end));
      },

      async bulkAdd() {
        if (!this.selectedDays.length || !this.range.start || !this.range.end) return;
        if (this.range.end <= this.range.start) {
          Swal.fire({icon:'error', title:'Time invalid', text:'End time must be after start time.', confirmButtonColor:'#ef4444'});
          return;
        }

        const start24 = this.range.start;
        const end24   = this.range.end;

        const names = this.selectedDays.map(d => this.dayLabel(d)).join(', ');
        const confirmed = await Swal.fire({
          icon: 'question',
          title: 'Add availability?',
          html: `<div class="text-left">Days: <b>${names}</b><br/>Time: <b>${fmt12(start24)}</b> to <b>${fmt12(end24)}</b></div>`,
          showCancelButton: true,
          confirmButtonText: 'Yes, add',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#4f46e5',
          cancelButtonColor: '#64748b'
        }).then(r => r.isConfirmed);

        if (!confirmed) return;

        this.selectedDays.forEach(d => {
          const exists = this.slots.some(s => s.weekday===d && s.start_time===start24 && s.end_time===end24);
          if (exists) return;
          if (this.overlaps(d, start24, end24)) return;
          this.slots.push({ weekday:d, start_time:start24, end_time:end24 });
        });
        this.slots.sort((a,b)=> a.weekday - b.weekday || a.start_time.localeCompare(b.start_time));
      },

      remove(i) { this.slots.splice(i,1); },
    }
  }
</script>

<script>
  // Success flash (optional if you redirect back with 'success')
  @if (session('success'))
    Swal.fire({
      icon: 'success',
      title: 'Saved',
      text: @json(session('success')),
      confirmButtonColor: '#4f46e5'
    });
  @endif

  // Error flash (optional)
  @if (session('error'))
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: @json(session('error')),
      confirmButtonColor: '#ef4444'
    });
  @endif

  // Validation errors -> show them in a single modal
  @if ($errors->any())
    (function () {
      const errs = @json($errors->all());
      const list = '<ul class="text-left m-0 p-0" style="list-style:none">' +
                   errs.map(e => `<li>• ${e}</li>`).join('') +
                   '</ul>';
      Swal.fire({
        icon: 'error',
        title: 'Please fix the following',
        html: list,
        confirmButtonColor: '#ef4444'
      });
    })();
  @endif
</script>

<style>
  @media print { .screen-only { display: none !important; } }
</style>
@endsection
