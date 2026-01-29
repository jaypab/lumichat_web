@extends('layouts.app')
@section('title', 'Contact Support')

@section('content')
<div class="max-w-5xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">

  {{-- ======= Header (gradient band) - Mobile Optimized ======= --}}
  <section class="rounded-xl sm:rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm animate-fadeup">
    <div class="p-4 sm:p-5 md:p-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg sm:text-xl md:text-2xl font-bold tracking-tight">Contact Support</h2>
          <p class="text-white/80 text-xs sm:text-sm mt-0.5">Reach the LumiCHAT developers directly.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          {{-- in the gradient header pill --}}
          <span class="inline-flex items-center gap-2 rounded-lg sm:rounded-xl bg-white/15 px-2.5 sm:px-3 py-1.5 text-xs sm:text-sm ring-1 ring-white/20">
            <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4 opacity-90" viewBox="0 0 24 24" fill="currentColor"><path d="M3 5a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5z"/></svg>
            5 developers
          </span>

          <a href="{{ url('/settings') }}"
             class="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg sm:rounded-xl bg-white text-slate-900 px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
            ← Back to Settings
          </a>
        </div>
      </div>

      {{-- compact search/inputs row (kept simple here) --}}
      <div class="mt-4 grid gap-2 sm:grid-cols-2">
        <div class="relative">
          <input id="subjectAll" type="text" maxlength="120"
                 placeholder="Subject (e.g., LumiCHAT Support Request)"
                 class="w-full h-10 bg-white border-0 rounded-xl pl-3 pr-10 text-sm text-slate-900 placeholder-slate-400"/>
          <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" id="subCount">0/120</span>
        </div>
        <div class="relative">
          <input id="bodyAll" type="text" maxlength="500"
                 placeholder="Message (optional)"
                 class="w-full h-10 bg-white border-0 rounded-xl pl-3 pr-10 text-sm text-slate-900 placeholder-slate-400"/>
          <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" id="bodyCount">0/500</span>
        </div>
      </div>
    </div>
  </section>

  {{-- ======= Quick Actions ======= --}}
  <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4 sm:p-6 space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Quick email</h3>

    <div class="flex flex-wrap items-center gap-3">
      <a id="mailtoAll"
         href="#"
         class="inline-flex items-center h-11 px-5 rounded-xl bg-indigo-600 text-white text-sm font-medium shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4l-8 5L4 8V6l8 5 8-5v2z"/></svg>
        Email All Developers
      </a>

      <button type="button" id="copyAllBtn"
              class="inline-flex items-center h-11 px-4 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M9 2h6a2 2 0 012 2h1a2 2 0 012 2v12a2 2 0 01-2 2h-1a2 2 0 01-2 2H9a2 2 0 01-2-2H6a2 2 0 01-2-2V6a2 2 0 012-2h1a2 2 0 012-2zm0 2v2h6V4H9z"/></svg>
        Copy all emails
      </button>
    </div>
  </section>

  {{-- ======= Developer Emails ======= --}}
  <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4 sm:p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Developers</h3>

    <div class="grid sm:grid-cols-2 gap-3">
      @php
      $devs = [
        ['name'=>'Earl Sepida','email'=>'earlsepida63@gmail.com'],
        ['name'=>'Cloyd Labininay','email'=>'labininaycloyd5@gmail.com'],
        ['name'=>'Lowell Jay Pabua','email'=>'lowelljaypabua@gmail.com'],
        ['name'=>'Lorenz Manilla Saldivar','email'=>'lorenzmanillasaldivar@gmail.com'],
        ['name'=>'Kesha Jade S. Sabanpan','email'=>'zeaaang@gmail.com'], // NEW
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
            <a href="#" data-one-mail
               class="text-sm text-indigo-700 dark:text-indigo-300 hover:underline truncate"
               data-email="{{ $d['email'] }}">
              {{ $d['email'] }}
            </a>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="#" data-one-mail
             class="inline-flex items-center h-9 px-3 rounded-lg bg-indigo-600 text-white text-xs font-medium shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
             data-email="{{ $d['email'] }}">
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
    const devs = [
      'earlsepida63@gmail.com',
      'labininaycloyd5@gmail.com',
      'lowelljaypabua@gmail.com',
      'lorenzmanillasaldivar@gmail.com',
      'zeaaang@gmail.com'
    ];

  const subjectAll = document.getElementById('subjectAll');
  const bodyAll    = document.getElementById('bodyAll');
  const mailtoAll  = document.getElementById('mailtoAll');
  const copyAllBtn = document.getElementById('copyAllBtn');
  const subCount   = document.getElementById('subCount');
  const bodyCount  = document.getElementById('bodyCount');

  function toast(msg){
    const t = document.getElementById('toast');
    if(!t) return;
    t.textContent = msg;
    t.classList.remove('hidden');
    setTimeout(()=> t.classList.add('hidden'), 1800);
  }

  function updateCounters(){
    if (subCount)  subCount.textContent  = `${(subjectAll.value||'').length}/120`;
    if (bodyCount) bodyCount.textContent = `${(bodyAll.value||'').length}/500`;
  }

  function buildMailto(to){
    const subj = enc(subjectAll.value || 'LumiCHAT Support Request');
    const body = enc(bodyAll.value || '');
    return `mailto:${to}?subject=${subj}&body=${body}`;
  }

  function refreshAllHref(){
    mailtoAll.setAttribute('href', buildMailto(devs.join(',')));
  }

  subjectAll?.addEventListener('input', ()=>{ updateCounters(); refreshAllHref(); });
  bodyAll?.addEventListener('input', ()=>{ updateCounters(); refreshAllHref(); });
  updateCounters(); refreshAllHref();

  copyAllBtn?.addEventListener('click', ()=>{
    const str = devs.join(', ');
    if(navigator.clipboard?.writeText){
      navigator.clipboard.writeText(str).then(()=>toast('Emails copied'));
    } else {
      const ta = document.createElement('textarea'); ta.value = str; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); ta.remove(); toast('Emails copied');
    }
  });

  // Per-developer "Mail" & email links respect current subject/body
  document.querySelectorAll('[data-one-mail]').forEach(el=>{
    el.addEventListener('click', (e)=>{
      e.preventDefault();
      const to = e.currentTarget.getAttribute('data-email');
      window.location.href = buildMailto(to);
    });
  });

  // Copy single email
  document.querySelectorAll('.copy-one').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const email = btn.getAttribute('data-email');
      if(navigator.clipboard?.writeText){
        navigator.clipboard.writeText(email).then(()=>toast('Email copied'));
      } else {
        const ta = document.createElement('textarea'); ta.value = email; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy'); ta.remove(); toast('Email copied');
      }
    });
  });
})();
</script>
@endsection
