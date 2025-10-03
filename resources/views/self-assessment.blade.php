@extends('layouts.blank')
@section('title','Self-Assessment')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="w-full max-w-5xl mx-auto px-4 py-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

    {{-- Tips (left) --}}
    <aside class="lg:col-span-1">
      <div class="rounded-xl border border-gray-200 bg-white/95 p-5 shadow-sm sticky top-6">
        <h3 class="font-semibold text-indigo-700 flex items-center gap-2 text-sm">
          <span>💡</span> Tips
        </h3>
        <ul class="mt-2 text-[13px] text-gray-600 space-y-1.5 leading-relaxed">
          <li>• Be honest — it helps us help you.</li>
          <li>• Your answers are private.</li>
          <li>• You can skip anytime.</li>
        </ul>
      </div>
    </aside>

    {{-- Main card (right) --}}
    <section class="lg:col-span-2">
      <div class="rounded-2xl overflow-hidden shadow-lg bg-white max-h-[82vh]">
        {{-- Header (slimmer) --}}
        <div class="p-6 text-center text-white bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600">
          <h1 class="text-2xl font-extrabold tracking-tight">Quick Check-In</h1>
          <p class="opacity-95 mt-1 text-sm">Before chatting, let us know how you feel. You can skip anytime.</p>
        </div>

        {{-- Body (compact) --}}
        <div class="p-6 space-y-5 overflow-auto">
          <form id="saForm" autocomplete="off" class="space-y-5">
            {{-- Mood --}}
            <div>
              <label class="block text-[13px] font-semibold text-gray-800 mb-2">Mood</label>
              <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                @php $icons=['Happy'=>'😊','Calm'=>'😌','Sad'=>'😢','Anxious'=>'😟','Stressed'=>'😫']; @endphp
                @foreach(['Happy','Calm','Sad','Anxious','Stressed'] as $m)
                  <button type="button" data-mood="{{ $m }}"
                    class="sa-chip w-full h-16 px-3 rounded-xl border border-gray-200 bg-gray-50
                           hover:bg-indigo-50 hover:border-indigo-300 transition
                           active:scale-95 shadow-sm flex flex-col items-center justify-center gap-0.5">
                    <span class="text-[22px] leading-none">{{ $icons[$m] }}</span>
                    <span class="font-medium text-[13px]">{{ $m }}</span>
                  </button>
                @endforeach
              </div>
              <input type="hidden" id="mood" name="mood" value="Happy">
            </div>

            {{-- Notes --}}
            <div>
              <label for="note" class="block text-[13px] font-semibold text-gray-800 mb-1.5">
                Optional note <span class="font-normal text-gray-400">(you can leave this blank)</span>
              </label>
              <textarea id="note" name="note" rows="3"
                class="w-full rounded-xl border-gray-200 focus:ring-indigo-500 focus:border-indigo-500 text-[14px]"
                placeholder="Type anything you'd like us to know..."></textarea>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-2.5">
              <a href="{{ route('self-assessment.skip') }}"
                 class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 active:scale-95 transition text-sm">
                Skip
              </a>
              <button id="saSubmit" type="submit"
                class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-md active:scale-95 transition inline-flex items-center gap-2 text-sm">
                <svg id="saSpinner" class="hidden animate-spin h-4 w-4" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span id="saBtnText">Continue to Chat</span>
              </button>
            </div>
          </form>

          <p class="text-[11px] text-gray-500 text-center">
            Your wellbeing matters. This check-in helps us support you better.
          </p>
        </div>
      </div>
    </section>
  </div>
</div>

{{-- JS: same behavior, refined selected state --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const chips = document.querySelectorAll('.sa-chip');
  const moodInput = document.getElementById('mood');
  const form = document.getElementById('saForm');
  const submitBtn = document.getElementById('saSubmit');
  const spinner = document.getElementById('saSpinner');
  const btnText = document.getElementById('saBtnText');

  if (chips.length) select(chips[0]);

  chips.forEach(chip => {
    chip.addEventListener('click', () => select(chip));
    chip.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(chip); }
    });
  });

  function select(chip){
    chips.forEach(c => c.classList.remove('ring-2','ring-indigo-500','bg-white'));
    chip.classList.add('ring-2','ring-indigo-500','bg-white');
    moodInput.value = chip.dataset.mood;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    toggle(true);
    const payload = { mood: moodInput.value, note: document.getElementById('note').value ?? '' };
    try{
      const res = await fetch(@json(route('self-assessment.store')), {
        method:'POST',
        headers:{ 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data?.ok) {
        await Swal.fire({ icon:'success', title:'Thanks!', text:'Your check-in has been recorded.', confirmButtonText:'Go to chat', confirmButtonColor:'#4f46e5' });
        window.location.href = data.goto ?? @json(route('chat.index'));
      } else { throw new Error(); }
    } catch {
      await Swal.fire({ icon:'error', title:'Save failed', text:'Please try again.' });
    } finally { toggle(false); }
  });

  function toggle(state){
    submitBtn.disabled = state;
    spinner.classList.toggle('hidden', !state);
    btnText.textContent = state ? 'Saving...' : 'Continue to Chat';
  }
});
</script>
@endsection
