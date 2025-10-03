@extends('layouts.app')
@section('title', 'Report a Bug')

@section('content')
<div class="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">

  {{-- Header --}}
  <div class="flex items-start justify-between gap-3">
    <div class="space-y-1">
      <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Report a Bug</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">Send your report to the developers with a couple of clicks.</p>
    </div>
    <a href="{{ url('/settings') }}"
       class="inline-flex items-center h-10 px-3 rounded-lg text-sm font-medium border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-rose-500">
      ← Back to Settings
    </a>
  </div>

  {{-- Quick Bug Template --}}
  <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4 sm:p-6 space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Quick email</h3>

    <div class="grid sm:grid-cols-2 gap-3">
      <label class="block">
        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Subject</span>
        <input id="bugSubject" type="text" placeholder="LumiCHAT Bug Report: (short title)"
               class="mt-1 w-full h-11 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 text-sm px-3">
      </label>
      <label class="block">
        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Template (edit if you like)</span>
        <input id="bugBody" type="text" value="Issue: ... | Steps: ... | Expected: ... | Actual: ... | Device/Browser: ..."
               class="mt-1 w-full h-11 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 text-sm px-3">
      </label>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <a id="bugMailAll"
         href="mailto:earlsepida63@gmail.com,labininaycloyd5@gmail.com,lowelljaypabua@gmail.com,lorenzmanillasaldivar@gmail.com?subject=LumiCHAT%20Bug%20Report&body="
         class="inline-flex items-center h-11 px-5 rounded-xl bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500">
        <!-- bug icon -->
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M14 6V4a2 2 0 10-4 0v2H7a1 1 0 000 2h10a1 1 0 100-2h-3zm7 7a1 1 0 00-1-1h-1V9a1 1 0 00-2 0v3H7V9a1 1 0 00-2 0v3H4a1 1 0 000 2h1v3a1 1 0 002 0v-3h10v3a1 1 0 002 0v-3h1a1 1 0 001-1z"/></svg>
        Email All Developers (Bug)
      </a>

      <button type="button" id="bugCopyAll"
              class="inline-flex items-center h-11 px-4 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-rose-500">
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
          <div class="w-9 h-9 rounded-full bg-rose-600/10 dark:bg-rose-400/10 flex items-center justify-center text-rose-700 dark:text-rose-300 text-sm font-semibold">
            {{ \Illuminate\Support\Str::of($d['name'])->explode(' ')->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('') }}
          </div>
          <div class="min-w-0">
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $d['name'] }}</div>
            <a href="mailto:{{ $d['email'] }}?subject=LumiCHAT%20Bug%20Report"
               class="text-sm text-rose-700 dark:text-rose-300 hover:underline truncate">{{ $d['email'] }}</a>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="mailto:{{ $d['email'] }}?subject=LumiCHAT%20Bug%20Report"
             class="inline-flex items-center h-9 px-3 rounded-lg bg-rose-600 text-white text-xs font-medium shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500">
            Mail
          </a>
          <button type="button" data-email="{{ $d['email'] }}"
                  class="copy-one inline-flex items-center h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-xs text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-rose-500">
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
  const bugMailAll = document.getElementById('bugMailAll');
  const bugSubject = document.getElementById('bugSubject');
  const bugBody    = document.getElementById('bugBody');
  const copyAllBtn = document.getElementById('bugCopyAll');

  function updateHref(){
    const base = 'mailto:earlsepida63@gmail.com,labininaycloyd5@gmail.com,lowelljaypabua@gmail.com,lorenzmanillasaldivar@gmail.com';
    const params = `?subject=${enc(bugSubject.value || 'LumiCHAT Bug Report')}&body=${enc(bugBody.value || '')}`;
    bugMailAll.setAttribute('href', base + params);
  }
  bugSubject?.addEventListener('input', updateHref);
  bugBody?.addEventListener('input', updateHref);
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
      document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
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
