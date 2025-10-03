@extends('layouts.app')
@section('title', 'Contact Support')

@section('content')
<div class="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">

  {{-- Header --}}
  <div class="flex items-start justify-between gap-3">
    <div class="space-y-1">
      <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Contact Support</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">Reach the LumiCHAT developers directly.</p>
    </div>
    <a href="{{ url('/settings') }}"
       class="inline-flex items-center h-10 px-3 rounded-lg text-sm font-medium border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
      ← Back to Settings
    </a>
  </div>

  {{-- Quick Actions --}}
  <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4 sm:p-6 space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Quick email</h3>

    <div class="grid sm:grid-cols-2 gap-3">
      <label class="block">
        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Subject</span>
        <input id="subjectAll" type="text" placeholder="LumiCHAT Support Request"
               class="mt-1 w-full h-11 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm px-3">
      </label>
      <label class="block">
        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Message (optional)</span>
        <input id="bodyAll" type="text" placeholder="Hi team, I need help with…"
               class="mt-1 w-full h-11 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm px-3">
      </label>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <a id="mailtoAll"
         href="mailto:earlsepida63@gmail.com,labininaycloyd5@gmail.com,lowelljaypabua@gmail.com,lorenzmanillasaldivar@gmail.com?subject=LumiCHAT%20Support%20Request&body="
         class="inline-flex items-center h-11 px-5 rounded-xl bg-indigo-600 text-white text-sm font-medium shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <!-- mail icon -->
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4l-8 5L4 8V6l8 5 8-5v2z"/></svg>
        Email All Developers
      </a>

      <button type="button" id="copyAllBtn"
              class="inline-flex items-center h-11 px-4 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <!-- clipboard icon -->
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M9 2h6a2 2 0 012 2h1a2 2 0 012 2v12a2 2 0 01-2 2h-1a2 2 0 01-2 2H9a2 2 0 01-2-2H6a2 2 0 01-2-2V6a2 2 0 012-2h1a2 2 0 012-2zm0 2v2h6V4H9z"/></svg>
        Copy all emails
      </button>
    </div>
  </section>

  {{-- Developer Emails --}}
  <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4 sm:p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Developers</h3>

    <div class="grid sm:grid-cols-2 gap-3">
      @php
        $devs = [
          ['name'=>'Earl Sepida','email'=>'earlsepida63@gmail.com'],
          ['name'=>'Cloyd Labininay','email'=>'labininaycloyd5@gmail.com'],
          ['name'=>'Lowell Jay Pabua','email'=>'lowelljaypabua@gmail.com'],
          ['name'=>'Lorenz Manilla Saldivar','email'=>'lorenzmanillasaldivar@gmail.com'],
        ];
      @endphp

      @foreach($devs as $d)
      <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/40 px-4 py-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-9 h-9 rounded-full bg-indigo-600/10 dark:bg-indigo-400/10 flex items-center justify-center text-indigo-700 dark:text-indigo-300 text-sm font-semibold">
            {{ \Illuminate\Support\Str::of($d['name'])->explode(' ')->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('') }}
          </div>
          <div class="min-w-0">
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $d['name'] }}</div>
            <a href="mailto:{{ $d['email'] }}?subject=LumiCHAT%20Support%20Request"
               class="text-sm text-indigo-700 dark:text-indigo-300 hover:underline truncate">{{ $d['email'] }}</a>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="mailto:{{ $d['email'] }}?subject=LumiCHAT%20Support%20Request"
             class="inline-flex items-center h-9 px-3 rounded-lg bg-indigo-600 text-white text-xs font-medium shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            Mail
          </a>
          <button type="button" data-email="{{ $d['email'] }}"
                  class="copy-one inline-flex items-center h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-xs text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            Copy
          </button>
        </div>
      </div>
      @endforeach
    </div>
  </section>
</div>

{{-- Toast --}}
<div id="toast" class="hidden fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm shadow-lg" role="status" aria-live="polite"></div>

{{-- Scripts --}}
<script>
(function(){
  const enc = encodeURIComponent;
  const mailtoAll = document.getElementById('mailtoAll');
  const subjectAll = document.getElementById('subjectAll');
  const bodyAll = document.getElementById('bodyAll');
  const copyAllBtn = document.getElementById('copyAllBtn');

  function updateHref(){
    const base = 'mailto:earlsepida63@gmail.com,labininaycloyd5@gmail.com,lowelljaypabua@gmail.com,lorenzmanillasaldivar@gmail.com';
    const params = `?subject=${enc(subjectAll.value || 'LumiCHAT Support Request')}&body=${enc(bodyAll.value || '')}`;
    mailtoAll.setAttribute('href', base + params);
  }
  subjectAll?.addEventListener('input', updateHref);
  bodyAll?.addEventListener('input', updateHref);
  updateHref();

  function toast(msg){
    const t = document.getElementById('toast');
    if(!t) return;
    t.textContent = msg;
    t.classList.remove('hidden');
    setTimeout(()=> t.classList.add('hidden'), 2200);
  }

  function copy(text){
    if(navigator.clipboard?.writeText){
      navigator.clipboard.writeText(text).then(()=>toast('Copied to clipboard'));
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      toast('Copied to clipboard');
    }
  }

  copyAllBtn?.addEventListener('click', ()=>{
    copy('earlsepida63@gmail.com, labininaycloyd5@gmail.com, lowelljaypabua@gmail.com, lorenzmanillasaldivar@gmail.com');
  });

  document.querySelectorAll('.copy-one').forEach(btn=>{
    btn.addEventListener('click', ()=> copy(btn.dataset.email));
  });
})();
</script>
@endsection
