@extends('layouts.admin')
@section('title', 'Admin - Update Counselor')
@section('page_title', 'Update Counselor  ')

@section('content')
  <div class="max-w-5xl mx-auto p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
      <a href="{{ route('admin.counselors.index') }}" class="inline-flex items-center gap-2 h-10 px-3.5 rounded-xl bg-white ring-1 ring-slate-200 text-slate-700
                hover:bg-slate-50 active:scale-[.99] transition">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to list
      </a>
      <h1 class="sr-only">Edit Counselor</h1>
    </div>

    @php
      $initialSlots = old(
        'availability',
        optional($counselor->availabilities)->map(function ($a) {
          return [
            'weekday' => (int) $a->weekday,
            'start_time' => $a->start_time,
            'end_time' => $a->end_time,
          ];
        })->values() ?? []
      );
    @endphp

    {{-- Alpine state wrapper (single form spans two cards) --}}
    <div x-data="CounselorForm()" x-init="init(@js($initialSlots))">

      <form method="POST" action="{{ route('admin.counselors.update', $counselor) }}" novalidate>
        @csrf
        @method('PUT')

        {{-- ===================== CARD 1: Counselor Details ===================== --}}
        <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-slate-200/70">
          {{-- violet accent inside the card --}}
          <span class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r
                       from-indigo-500 via-purple-500 to-fuchsia-500"></span>

          <div class="p-6 sm:p-8">
            <h2 class="text-lg font-semibold text-slate-800">Counselor Details</h2>
            <p class="text-sm text-slate-500">Edit the counselor’s basic info and status.</p>

            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700">
                  Full Name <span class="text-rose-600">*</span>
                </label>
                <input name="name" value="{{ old('name', $counselor->name) }}" required
                  class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"
                  type="text" placeholder="e.g., Juan Dela Cruz">
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700">
                  Email <span class="text-rose-600">*</span>
                </label>
                <input name="email" value="{{ old('email', $counselor->email) }}" required
                  class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"
                  type="email" placeholder="name@school.edu">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700">Contact No.</label>
                <input name="phone" value="{{ old('phone', $counselor->phone) }}"
                  class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500"
                  type="text" placeholder="09XXXXXXXXX">
                @error('phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700">Status</label>
                <select name="is_active"
                  class="mt-1 w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:ring-2 focus:ring-indigo-500">
                  <option value="1" @selected(old('is_active', $counselor->is_active) == 1)>Available</option>
                  <option value="0" @selected(old('is_active', $counselor->is_active) == 0)>Not Available</option>
                </select>
                @error('is_active') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
              </div>
            </div>
          </div>
        </div>

        {{-- space between cards --}}
        <div class="h-4"></div>

        {{-- Footer --}}
        <div class="px-6 sm:px-8 py-4 bg-slate-50 border-t border-slate-200/70 flex items-center justify-end gap-3">
          <a href="{{ route('admin.counselors.index') }}" class="inline-flex items-center h-10 px-4 rounded-xl bg-white text-slate-700 ring-1 ring-slate-200
                    hover:bg-slate-100 active:scale-[.99] transition">Cancel</a>

          <button type="submit" class="inline-flex items-center h-10 px-5 rounded-xl bg-indigo-600 text-white font-medium
                         hover:bg-indigo-700 active:scale-[.99] transition">
            Update
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    // SweetAlert flashes and validation summary
    @if (session('success'))
      Swal.fire({ icon: 'success', title: 'Updated', text: @json(session('success')), confirmButtonColor: '#4f46e5' });
    @endif
    @if (session('error'))
      Swal.fire({ icon: 'error', title: 'Error', text: @json(session('error')), confirmButtonColor: '#ef4444' });
    @endif
    @if ($errors->any())
      (function () {
        const errs = @json($errors->all());
        const list = '<ul class="text-left m-0 p-0" style="list-style:none">' +
          errs.map(e => `<li>• ${e}</li>`).join('') +
          '</ul>';
        Swal.fire({ icon: 'error', title: 'Please fix the following', html: list, confirmButtonColor: '#ef4444' });
      })();
    @endif

      // HH:mm -> hh:mm AM/PM (for display in prompts)
      function fmt12(t) {
        if (!t) return '';
        const [H, M] = t.split(':').map(n => parseInt(n, 10));
        const ampm = H >= 12 ? 'PM' : 'AM';
        const h12 = ((H % 12) || 12).toString().padStart(2, '0');
        const mm = (isNaN(M) ? 0 : M).toString().padStart(2, '0');
        return `${h12}:${mm} ${ampm}`;
      }

    function CounselorForm() {
      return {
        days: [
          { value: 1, short: 'Mon', long: 'Monday' },
          { value: 2, short: 'Tue', long: 'Tuesday' },
          { value: 3, short: 'Wed', long: 'Wednesday' },
          { value: 4, short: 'Thu', long: 'Thursday' },
          { value: 5, short: 'Fri', long: 'Friday' },
        ],
        selectedDays: [],               // start empty (no auto-selected days)
        range: { start: '09:00', end: '12:00' },
        slots: [],

        to24(t) {
          if (!t) return '';
          t = ('' + t).trim();
          const mmss = t.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
          if (mmss) return `${String(mmss[1]).padStart(2, '0')}:${mmss[2]}`;
          const ampm = t.match(/^(\d{1,2}):(\d{2})\s*([ap]m)$/i);
          if (ampm) {
            let hh = parseInt(ampm[1], 10), mm = ampm[2], ap = ampm[3].toLowerCase();
            if (ap === 'pm' && hh !== 12) hh += 12;
            if (ap === 'am' && hh === 12) hh = 0;
            return `${String(hh).padStart(2, '0')}:${mm}`;
          }
          return '';
        },

        init(oldOrExistingSlots) {
          const allowed = new Set([1, 2, 3, 4, 5]);
          if (Array.isArray(oldOrExistingSlots) && oldOrExistingSlots.length) {
            this.slots = oldOrExistingSlots
              .filter(s => allowed.has(Number(s.weekday)))
              .map(s => ({
                weekday: Number(s.weekday),
                start_time: this.to24(s.start_time),
                end_time: this.to24(s.end_time),
              }))
              .filter(s => s.start_time && s.end_time);
            this.sortSlots();
          }
          this.range.start = this.to24(this.range.start) || '09:00';
          this.range.end = this.to24(this.range.end) || '12:00';
        },

        dayLabel(wd) { return ({ 1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday' })[wd] || ''; },
        isSelected(d) { return this.selectedDays.includes(d); },
        toggleDay(d) { this.isSelected(d) ? this.selectedDays = this.selectedDays.filter(x => x !== d) : this.selectedDays = [...this.selectedDays, d].sort((a, b) => a - b); },
        preset() { this.selectedDays = [1, 2, 3, 4, 5]; },
        clearSelection() { this.selectedDays = []; },
        sortSlots() { this.slots.sort((a, b) => a.weekday - b.weekday || a.start_time.localeCompare(b.start_time)); },

        overlaps(d, start, end) { return this.slots.some(s => s.weekday === d && (start < s.end_time && s.start_time < end)); },

        async bulkAdd() {
          const start = this.to24(this.range.start), end = this.to24(this.range.end);
          if (!this.selectedDays.length || !start || !end) {
            Swal.fire({ icon: 'warning', title: 'Incomplete', text: 'Pick weekday(s) and set a time range first.', confirmButtonColor: '#4f46e5' });
            return;
          }
          if (end <= start) {
            Swal.fire({ icon: 'error', title: 'Time invalid', text: 'End time must be after start time.', confirmButtonColor: '#ef4444' });
            return;
          }

          const names = this.selectedDays.map(d => this.dayLabel(d)).join(', ');
          const confirmed = await Swal.fire({
            icon: 'question',
            title: 'Add availability?',
            html: `<div class="text-left">Days: <b>${names}</b><br/>Time: <b>${start}</b> to <b>${end}</b></div>`,
            showCancelButton: true,
            confirmButtonText: 'Yes, add',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#64748b'
          }).then(r => r.isConfirmed);

          if (!confirmed) return;

          let skippedOverlap = false;
          this.selectedDays.forEach(d => {
            const exists = this.slots.some(s => s.weekday === d && s.start_time === start && s.end_time === end);
            if (exists) return;
            if (this.overlaps(d, start, end)) { skippedOverlap = true; return; }
            this.slots.push({ weekday: d, start_time: start, end_time: end });
          });
          this.sortSlots();
          if (skippedOverlap) {
            Swal.fire({ icon: 'info', title: 'Overlap skipped', text: 'Some selected days were skipped due to overlapping times.', confirmButtonColor: '#4f46e5' });
          }
        },

        remove(i) { this.slots.splice(i, 1); },
      }
    }
  </script>

  <style>
    @media print {
      .screen-only {
        display: none !important;
      }
    }
  </style>
@endsection