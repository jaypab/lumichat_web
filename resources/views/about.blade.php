@extends('layouts.app')
@section('title','About LumiCHAT')
@section('page_title','About LumiCHAT')

@push('styles')
<style>
  /* Reusable helper for slightly larger left padding inside cards */
  .card-pad-left{ padding-left: clamp(1.5rem, 3vw, 2.5rem); }

  /* =========================
     HERO (light/dark gradients)
     ========================= */
  .about-hero {
    background:
      radial-gradient(1200px 480px at -10% -20%, rgba(99,102,241,.15), transparent 60%),
      radial-gradient(1000px 520px at 110% -30%, rgba(139,92,246,.16), transparent 60%),
      linear-gradient(180deg, rgba(255,255,255,.84), rgba(255,255,255,.66));
  }
  .dark .about-hero {
    background:
      radial-gradient(1200px 480px at -10% -20%, rgba(99,102,241,.22), transparent 60%),
      radial-gradient(1000px 520px at 110% -30%, rgba(139,92,246,.22), transparent 60%),
      linear-gradient(180deg, rgba(17,24,39,.86), rgba(17,24,39,.74));
  }

  /* =========================
     REVEAL ANIMATION (respects reduced motion)
     ========================= */
  @media (prefers-reduced-motion: no-preference) {
    html:not(.reduce-motion) .reveal {
      opacity: 0;
      transform: translateY(12px) scale(.995);
      transition: opacity .5s ease, transform .6s cubic-bezier(.2,.7,.2,1);
      will-change: transform, opacity;
    }
    html:not(.reduce-motion) .reveal.in {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  /* =========================
     ACCESSIBILITY & TOC
     ========================= */
  .kb-focus:focus-visible {
    outline: 3px solid rgba(99,102,241,.65);
    outline-offset: 2px;
    border-radius: 14px;
  }

  /* Sections stop with some space under the sticky header */
  .section-anchor{ scroll-margin-top: 92px; }

  .toc-link{
    color: rgb(75,85,99);
    border-radius: 12px;
    transition: background-color .15s ease, color .15s ease;
  }
  .toc-link:hover{ background: rgba(79,70,229,.06); }
  .toc-link.active{
    background: rgba(79,70,229,.10);
    color: rgb(79,70,229);
    font-weight: 600;
  }
  .dark .toc-link{ color: rgb(203,213,225); }
  .dark .toc-link:hover{ background: rgba(79,70,229,.16); }
  .dark .toc-link.active{
    background: rgba(79,70,229,.20);
    color: rgb(165,180,252);
  }

  /* =========================
     FAQ CHEVRON
     ========================= */
  details > summary .chev { transition: transform .25s ease; }
  details[open] > summary .chev { transform: rotate(90deg); }

  /* =========================
     TIMELINE (centered, aligned)
     ========================= */

  /* Grid with a fixed left rail and flexible right column */
  .tl-grid {
    display: grid;
    grid-template-columns: 52px 1fr;  /* left rail width + main content */
    gap: 14px;
    align-items: center;              /* center badge + card vertically per row */
    position: relative;
  }

  /* Vertical spine perfectly centered in the left rail */
  .tl-grid::before {
    content: "";
    position: absolute;
    left: 26px;                       /* half of 52px rail */
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, rgba(148,163,184,.24), rgba(148,163,184,.10));
  }
  .dark .tl-grid::before {
    background: linear-gradient(180deg, rgba(100,116,139,.5), rgba(100,116,139,.15));
  }

  /* Badge + halo */
  .tl-badge {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    font-weight: 700;
    font-size: .85rem;
    box-shadow: 0 6px 16px rgba(79,70,229,.25);
  }

  /* Fill the rail height so the badge is always vertically centered */
  .tl-badge-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    position: relative;
    margin-top: 0 !important;        /* override any previous margin */
  }

  .tl-badge-wrap::after {            /* soft halo on hover */
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 999px;
    filter: blur(10px);
    background: rgba(99,102,241,.3);
    opacity: 0;
    transition: opacity .25s ease;
    pointer-events: none;
  }

  .tl-card:hover .tl-badge-wrap::after { opacity: 1; }

  /* Optional: keep card heights consistent for nicer centering */
  .tl-card {
    min-height: 78px;
    padding-top: 1.1rem;
    padding-bottom: 1.1rem;
  }
</style>
@endpush

@section('content')
<div class="max-w-6xl mx-auto p-6 lg:p-8 space-y-8">

  {{-- ======= HERO ======= --}}
  <section class="about-hero rounded-3xl shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-6 md:p-10 reveal">
    <div class="flex items-start gap-5">
      <div class="shrink-0 relative">
        <img src="{{ asset('images/chatbot.png') }}" alt="LumiCHAT logo"
             class="w-14 h-14 md:w-16 md:h-16 rounded-2xl shadow ring-1 ring-black/5 dark:ring-white/10
                    transition-transform duration-300 will-change-transform hover:scale-[1.03]">
      </div>
      <div class="grow">
        <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">What is LumiCHAT?</h2>
        <p class="mt-2 text-[15px] md:text-[16px] leading-relaxed text-gray-600 dark:text-gray-300 max-w-3xl">
          {{ $build['name'] }} is a student-focused, expert-aligned support chatbot built for {{ $build['institution'] }}.
          It offers empathetic, guided conversations, basic self-help suggestions, and counselor referrals — <span class="font-semibold">without</span> providing medical diagnosis.
        </p>
        <div class="mt-4 text-[13px] text-gray-500 dark:text-gray-400">
          <span class="font-semibold">Version:</span> {{ $build['version'] }}
        </div>
      </div>
    </div>

    {{-- Quick stats --}}
    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="rounded-2xl bg-white/70 dark:bg-gray-800/60 p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-xs text-gray-500 dark:text-gray-400">Stack</div>
        <div class="text-sm font-semibold">Laravel + React + Rasa</div>
      </div>
      <div class="rounded-2xl bg-white/70 dark:bg-gray-800/60 p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-xs text-gray-500 dark:text-gray-400">Encryption</div>
        <div class="text-sm font-semibold">Data-at-Rest (opt-in)</div>
      </div>
      <div class="rounded-2xl bg-white/70 dark:bg-gray-800/60 p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-xs text-gray-500 dark:text-gray-400">Safety</div>
        <div class="text-sm font-semibold">High-risk escalation</div>
      </div>
      <div class="rounded-2xl bg-white/70 dark:bg-gray-800/60 p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-xs text-gray-500 dark:text-gray-400">Audience</div>
        <div class="text-sm font-semibold">TCC Students</div>
      </div>
    </div>
  </section>

  {{-- ======= WRAPPER: sticky section nav + main ======= --}}
  <div class="grid lg:grid-cols-[220px,1fr] gap-6">
    @php
      $anchors = [
        ['id'=>'build','label'=>'How we built it'],
        ['id'=>'flow','label'=>'How it works'],
        ['id'=>'responses','label'=>'Response sources'],
        ['id'=>'rasa','label'=>'Rasa integration'],
        ['id'=>'privacy','label'=>'Privacy & Safety'],
        ['id'=>'faq','label'=>'FAQ'],
        ['id'=>'credits','label'=>'Acknowledgments'],
      ];
    @endphp

    {{-- Section Nav (sticky, with scroll-spy) --}}
    <nav aria-label="About sections" class="hidden lg:block sticky top-20 self-start reveal">
      <ul id="about-toc" class="space-y-1 text-[14px]">
        @foreach ($anchors as $a)
          <li>
            <a href="#{{ $a['id'] }}" class="toc-link kb-focus block px-3 py-2 transition" data-target="{{ $a['id'] }}">
              {{ $a['label'] }}
            </a>
          </li>
        @endforeach
      </ul>
    </nav>

    {{-- ======= MAIN COLUMN ======= --}}
    <div class="space-y-8">

      {{-- Mobile "On this page" dropdown --}}
      <div class="lg:hidden -mt-2 mb-2">
        <label for="about-toc-mobile" class="sr-only">On this page</label>
        <select id="about-toc-mobile" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-800/70 p-2">
          @foreach ($anchors as $a)
            <option value="{{ $a['id'] }}">{{ $a['label'] }}</option>
          @endforeach
        </select>
      </div>

      {{-- Build / Stack --}}
      <section id="build" class="section-anchor space-y-4 reveal">
        <h3 class="text-xl font-bold">How we created & implemented LumiCHAT</h3>
        <div class="grid md:grid-cols-2 gap-4">
          <div class="rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5 transition hover:shadow-md hover:-translate-y-[2px]">
            <h4 class="font-semibold">Frontend (Student UI)</h4>
            <ul class="mt-2 list-disc pl-5 text-gray-600 dark:text-gray-300 space-y-1">
              @foreach ($techStack['Frontend'] as $item) <li>{{ $item }}</li> @endforeach
            </ul>
          </div>
          <div class="rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5 transition hover:shadow-md hover:-translate-y-[2px]">
            <h4 class="font-semibold">Backend (Server)</h4>
            <ul class="mt-2 list-disc pl-5 text-gray-600 dark:text-gray-300 space-y-1">
              @foreach ($techStack['Backend'] as $item) <li>{{ $item }}</li> @endforeach
            </ul>
          </div>
          <div class="rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5 transition hover:shadow-md hover:-translate-y-[2px]">
            <h4 class="font-semibold">NLP & Chat Brain</h4>
            <ul class="mt-2 list-disc pl-5 text-gray-600 dark:text-gray-300 space-y-1">
              @foreach ($techStack['NLP / Chat'] as $item) <li>{{ $item }}</li> @endforeach
            </ul>
          </div>
          <div class="rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5 transition hover:shadow-md hover:-translate-y-[2px]">
            <h4 class="font-semibold">Build & Config</h4>
            <ul class="mt-2 list-disc pl-5 text-gray-600 dark:text-gray-300 space-y-1">
              @foreach ($techStack['Infra / Build'] as $item) <li>{{ $item }}</li> @endforeach
            </ul>
          </div>
        </div>
      </section>

      {{-- End-to-end Flow (aligned grid) --}}
      <section id="flow" class="section-anchor space-y-4 reveal">
        <h3 class="text-xl font-bold">How it works (end-to-end)</h3>
        <ol class="space-y-4">
          @foreach ($dataFlow as $i => $step)
            <li class="tl-grid">
              {{-- Left column: badge --}}
              <div class="tl-badge-wrap">
                <div class="tl-badge">{{ $i + 1 }}</div>
              </div>

              {{-- Right column: card --}}
              <div class="tl-card rounded-2xl bg-white/80 dark:bg-gray-800/70 ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-4 hover:shadow-md transition">
                <div class="font-semibold">{{ $step['title'] }}</div>
                <p class="text-gray-600 dark:text-gray-300 mt-1">{{ $step['text'] }}</p>
              </div>
            </li>
          @endforeach
        </ol>
      </section>

      {{-- Responses origin --}}
      <section id="responses" class="section-anchor space-y-3 reveal">
        <h3 class="text-xl font-bold">Where the bot’s responses come from</h3>
        <div class="rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5">
          <p class="text-gray-600 dark:text-gray-300">
            Responses are defined in Rasa’s domain (<code class="px-1 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-800">responses.yml</code>),
            learned from stories/rules, and extended via custom actions. Content is aligned with counselor-approved guidance and student support resources.
            Language avoids diagnosis; it uses reflective prompts and referrals for high-risk cues.
          </p>
        </div>
      </section>

      {{-- Rasa integration (code sample) --}}
      <section id="rasa" class="section-anchor space-y-3 reveal">
        <h3 class="text-xl font-bold">Rasa ↔ Frontend integration (REST / Webhook)</h3>
        <div class="rounded-xl bg-slate-900 text-slate-100 p-4 text-[13px] overflow-x-auto ring-1 ring-slate-700/60">
        <pre>// Laravel controller (simplified idea)
        $payload = [
        'sender'   => $sessionId,       // keeps convo scoped
        'message'  => $text,            // sanitized user text
        'metadata' => ['riskProbe' => true],
        ];

        $response = Http::post(
        rtrim(config('services.rasa.url'),'/').'/webhooks/rest/webhook',
        $payload
        );

        $replies = $response->json(); // [{text:"..."}, {buttons:[...]} ...]
        </pre>
        </div>
        <p class="text-gray-600 dark:text-gray-300">
          We preserve per-conversation <span class="font-semibold">sender IDs</span>, support metadata, and handle multi-message replies (text, buttons, suggestions).
          High-risk triggers suggest an appointment flow (non-diagnostic).
        </p>
      </section>

      {{-- Privacy & Safety --}}
      <section id="privacy" class="section-anchor space-y-3 reveal">
        <h3 class="text-xl font-bold">Privacy & Safety</h3>
        <ul class="rounded-2xl bg-white/80 dark:bg-gray-800/70 ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5 list-disc pl-8 sm:pl-10 text-gray-600 dark:text-gray-300 space-y-2 leading-relaxed">
          @foreach ($privacy as $item) <li>{{ $item }}</li> @endforeach
        </ul>
      </section>

      {{-- FAQ (animated details) --}}
      <section id="faq" class="section-anchor space-y-3 reveal">
        <h3 class="text-xl font-bold">FAQ</h3>
        <div class="space-y-3">
          @foreach ($faq as $f)
            <details class="group rounded-2xl bg-white/80 dark:bg-gray-800/70 ring-1 ring-gray-200/60 dark:ring-gray-700/60">
              <summary class="kb-focus cursor-pointer list-none p-4 font-semibold flex items-center gap-2">
                <svg class="chev w-4 h-4 text-gray-400 group-open:text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"></path></svg>
                <span>{{ $f['q'] }}</span>
              </summary>
              <div class="p-4 pt-0 text-gray-600 dark:text-gray-300">
                <div class="border-t border-gray-200/70 dark:border-gray-700/60 pt-3">{{ $f['a'] }}</div>
              </div>
            </details>
          @endforeach
        </div>
      </section>

      {{-- Credits --}}
      <section id="credits" class="section-anchor space-y-3 reveal">
        <h3 class="text-xl font-bold">Acknowledgments</h3>
        <ul class="rounded-2xl bg-white/80 dark:bg-gray-800/70 ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5 list-disc pl-8 sm:pl-10 text-gray-600 dark:text-gray-300 space-y-2 leading-relaxed">
          @foreach ($credits as $c) <li>{{ $c }}</li> @endforeach
        </ul>
      </section>

    </div>
  </div>

  {{-- Back-to-top FAB --}}
  <button id="about-top"
          class="fixed bottom-6 right-6 z-[999] hidden h-11 px-4 rounded-xl
                 bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg
                 focus:outline-none focus:ring-2 focus:ring-indigo-400/60
                 transition">
    ↑ Top
  </button>

</div>
@endsection

@push('scripts')
<script>
/* Reveal on view (respects reduced motion) */
(function(){
  const html = document.documentElement;
  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const els = document.querySelectorAll('.reveal');
  if (prefersReduced || html.classList.contains('reduce-motion')) {
    els.forEach(el => el.classList.add('in'));
    return;
  }
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
  }, { rootMargin: '0px 0px -10% 0px', threshold: .15 });
  els.forEach(el => io.observe(el));
})();

/* Smooth-scroll + Scroll-spy + Mobile TOC
   —— works inside .panel-scroll (your layout's scroll container) */
(function(){
  const html = document.documentElement;
  const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const headerOffset = 92; // keep in sync with .section-anchor scroll-margin-top

  // Use the page scroller (layout) if present, else fallback to window
  const scroller = document.querySelector('.panel-scroll') || window;
  const isWindow = scroller === window;

  // Helper to get current scrollTop
  const getScrollTop = () => isWindow ? window.scrollY : scroller.scrollTop;

  // Helper to scroll
  function smoothScrollTo(y) {
    if (isWindow) {
      window.scrollTo({ top: y, behavior: reduced || html.classList.contains('reduce-motion') ? 'auto' : 'smooth' });
    } else {
      scroller.scrollTo({ top: y, behavior: reduced || html.classList.contains('reduce-motion') ? 'auto' : 'smooth' });
    }
  }

  // Compute target Y relative to scroller
  function targetY(el) {
    const elRect = el.getBoundingClientRect();
    if (isWindow) {
      return elRect.top + window.scrollY - headerOffset;
    } else {
      const scRect = scroller.getBoundingClientRect();
      return elRect.top - scRect.top + scroller.scrollTop - headerOffset;
    }
  }

  // Collect links/sections
  const links = Array.from(document.querySelectorAll('.toc-link[data-target], .toc-link[href^="#"]'))
    .map(a => { if (!a.dataset.target) a.dataset.target = a.getAttribute('href').slice(1); return a; });

  const sections = links
    .map(a => document.getElementById(a.dataset.target))
    .filter(Boolean);

  // Active state
  function setActiveLink(id){
    links.forEach(l => l.classList.toggle('active', l.dataset.target === id));
  }

  // Clicks (desktop TOC)
  links.forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      const id = a.dataset.target;
      const el = document.getElementById(id);
      if (!el) return;
      smoothScrollTo( targetY(el) );
      el.setAttribute('tabindex','-1');
      el.focus({ preventScroll: true });
      setActiveLink(id);
    });
  });

  // Mobile dropdown
  const mobileSel = document.getElementById('about-toc-mobile');
  mobileSel?.addEventListener('change', () => {
    const id = mobileSel.value;
    const el = document.getElementById(id);
    if (!el) return;
    smoothScrollTo( targetY(el) );
    el.setAttribute('tabindex','-1');
    el.focus({ preventScroll: true });
  });

  // Scroll-spy using the scroller as root
  const spy = new IntersectionObserver((entries) => {
    const visible = entries
      .filter(e => e.isIntersecting)
      .sort((a,b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
    if (!visible) return;
    setActiveLink(visible.target.id);
  }, {
    root: isWindow ? null : scroller,
    rootMargin: `-${headerOffset + 10}px 0px -60% 0px`,
    threshold: [0, .25, .5, 1]
  });
  sections.forEach(s => spy.observe(s));

  // Back-to-top FAB visibility & action (listen on the scroller)
  const fab = document.getElementById('about-top');
  fab?.addEventListener('click', () => smoothScrollTo(0));

  const toggleFab = () => {
    if (getScrollTop() > 480) fab?.classList.remove('hidden'); else fab?.classList.add('hidden');
  };
  toggleFab();

  const onScroll = () => toggleFab();
  (isWindow ? window : scroller).addEventListener('scroll', onScroll, { passive: true });
})();
</script>
@endpush
