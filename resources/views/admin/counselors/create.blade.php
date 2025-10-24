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
