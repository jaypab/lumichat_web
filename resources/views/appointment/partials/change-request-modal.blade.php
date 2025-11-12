@php
  $eligible = !empty($appointment->counselor_id)
              && \Carbon\Carbon::parse($appointment->scheduled_at)->gt(now()->addHours(24));
@endphp

<div class="mt-6">
  @if(isset($changeRequest) && $changeRequest)
    @php
      $st = $changeRequest->status;
      $badge = [
        'requested' => ['bg'=>'bg-amber-50 text-amber-700 ring-amber-200','label'=>'Pending review'],
        'approved'  => ['bg'=>'bg-emerald-50 text-emerald-700 ring-emerald-200','label'=>'Approved & reassigned'],
        'declined'  => ['bg'=>'bg-rose-50 text-rose-700 ring-rose-200','label'=>'Declined'],
        'canceled'  => ['bg'=>'bg-slate-100 text-slate-700 ring-slate-200','label'=>'Canceled'],
      ][$st] ?? ['bg'=>'bg-slate-100 text-slate-700 ring-slate-200','label'=>ucfirst($st)];
    @endphp
    <div class="rounded-xl border border-slate-200 bg-white p-4">
      <div class="flex items-center justify-between">
        <div class="text-sm text-slate-700">
          <b>Counselor change</b> — {{ $badge['label'] }}
        </div>
        <span class="inline-flex items-center h-7 rounded-full px-3 text-xs ring-1 {{ $badge['bg'] }}">{{ $badge['label'] }}</span>
      </div>
      @if($st==='declined' && !empty($changeRequest->decision_notes))
        <div class="mt-2 text-xs text-slate-600">Note: {{ $changeRequest->decision_notes }}</div>
      @endif
    </div>
  @elseif($eligible)
    <button type="button" onclick="document.getElementById('crModal').showModal()"
      class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2 text-white hover:bg-violet-700">
      Request different counselor
    </button>
  @else
    <button type="button" disabled title="Available after admin assigns a counselor and >=24h before session."
      class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2 text-white opacity-50 cursor-not-allowed">
      Request different counselor
    </button>
  @endif
</div>

<dialog id="crModal" class="rounded-2xl p-0 w-[560px] max-w-[96vw]">
  <form method="POST" action="{{ route('appointment.request_change', $appointment->id) }}" class="p-5">
    @csrf
    <h3 class="text-lg font-semibold mb-3">Request a different counselor</h3>
    <p class="text-sm text-slate-600 mb-4">
      Your reason will be reviewed by the admin. The current counselor won’t see your reason text.
    </p>

    <label class="block text-xs font-medium text-slate-600 mb-1">Reason</label>
    <select name="reason_code" required
      class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm mb-3">
      <option value="" hidden>Choose a reason</option>
      <option value="uncomfortable">I feel uncomfortable</option>
      <option value="language">Language preference</option>
      <option value="schedule">Schedule mismatch</option>
      <option value="conflict">Conflict of interest</option>
      <option value="other">Other</option>
    </select>

    <label class="block text-xs font-medium text-slate-600 mb-1">Additional context (optional)</label>
    <textarea name="reason_text" rows="3" class="w-full border border-slate-200 rounded-xl p-3 text-sm mb-3"
      placeholder="Share anything that would help the admin make a better match..."></textarea>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Preferred counselor (optional)</label>
        <input name="preference_counselor_id" type="number" min="1"
          class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm"
          placeholder="Enter ID (if you know)"/>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Preferred gender (optional)</label>
        <select name="pref_gender" class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm">
          <option value="any">Any</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
        </select>
      </div>
    </div>

    <div class="mt-3">
      <label class="block text-xs font-medium text-slate-600 mb-1">Language preference (optional)</label>
      <input name="pref_language" type="text" class="w-full h-10 bg-white border border-slate-200 rounded-xl px-3 text-sm"
        placeholder="e.g., English, Filipino, Cebuano"/>
    </div>

    <div class="mt-5 flex items-center justify-end gap-2">
      <button type="button" onclick="document.getElementById('crModal').close()"
        class="h-10 inline-flex items-center rounded-lg bg-slate-100 px-4 text-slate-700">Cancel</button>
      <button type="submit"
        class="h-10 inline-flex items-center rounded-lg bg-violet-600 px-4 text-white hover:bg-violet-700">
        Submit request
      </button>
    </div>
  </form>
</dialog>
