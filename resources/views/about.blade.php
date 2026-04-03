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
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: rgb(75,85,99);
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 500;
    transition: all .2s ease;
  }
  .toc-link:hover{ 
    background: rgba(79,70,229,.05);
    color: rgb(79,70,229);
  }
  .toc-link.active{
    background: rgba(79,70,229,.08);
    color: rgb(79,70,229);
    font-weight: 600;
  }
  /* Left indicator bar for active state */
  .toc-link::before {
    content: "";
    position: absolute;
    left: 0;
    top: 20%;
    bottom: 20%;
    width: 2px;
    background: rgb(79,70,229);
    border-radius: 99px;
    opacity: 0;
    transform: scaleY(0);
    transition: all .2s ease;
  }
  .toc-link.active::before {
    opacity: 1;
    transform: scaleY(1);
  }

  .dark .toc-link{ color: rgb(148,163,184); }
  .dark .toc-link:hover{ 
    background: rgba(129,140,248,.1); 
    color: rgb(165,180,252);
  }
  .dark .toc-link.active{
    background: rgba(129,140,248,.15);
    color: rgb(165,180,252);
  }
  .dark .toc-link::before {
    background: rgb(129,140,248);
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
<div class="max-w-6xl mx-auto p-3 sm:p-4 md:p-6 lg:p-8 space-y-6 sm:space-y-8">

  {{-- ======= HERO - Mobile Optimized ======= --}}
  <section class="about-hero rounded-2xl sm:rounded-3xl shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-4 sm:p-6 md:p-10 reveal">
    <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-5">
      <div class="shrink-0 relative">
        <img src="{{ asset('images/chatbot.png') }}" alt="LumiCHAT logo"
             class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 rounded-xl sm:rounded-2xl shadow ring-1 ring-black/5 dark:ring-white/10
                    transition-transform duration-300 will-change-transform hover:scale-[1.03]">
      </div>
      <div class="grow">
        <h2 class="text-xl sm:text-2xl md:text-3xl font-extrabold tracking-tight">What is LumiCHAT?</h2>
        <p class="mt-2 text-sm sm:text-[15px] md:text-[16px] leading-relaxed text-gray-600 dark:text-gray-300 max-w-3xl">
          {{ $build['name'] }} is a student-focused, expert-aligned support chatbot built for {{ $build['institution'] }}.
          It offers empathetic, guided conversations, basic self-help suggestions, and counselor referrals — <span class="font-semibold">without</span> providing medical diagnosis.
        </p>
        <div class="mt-3 sm:mt-4 text-xs sm:text-[13px] text-gray-500 dark:text-gray-400">
          <span class="font-semibold">Version:</span> {{ $build['version'] }}
        </div>
      </div>
    </div>

    {{-- Quick stats - Mobile responsive --}}
    <div class="mt-4 sm:mt-6 grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
      <div class="rounded-xl sm:rounded-2xl bg-white/70 dark:bg-gray-800/60 p-3 sm:p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">Stack</div>
        <div class="text-xs sm:text-sm font-semibold mt-0.5">Laravel + React + Rasa</div>
      </div>
      <div class="rounded-xl sm:rounded-2xl bg-white/70 dark:bg-gray-800/60 p-3 sm:p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">Encryption</div>
        <div class="text-xs sm:text-sm font-semibold mt-0.5">Data-at-Rest (opt-in)</div>
      </div>
      <div class="rounded-xl sm:rounded-2xl bg-white/70 dark:bg-gray-800/60 p-3 sm:p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">Safety</div>
        <div class="text-xs sm:text-sm font-semibold mt-0.5">High-risk escalation</div>
      </div>
      <div class="rounded-xl sm:rounded-2xl bg-white/70 dark:bg-gray-800/60 p-3 sm:p-4 ring-1 ring-gray-200/60 dark:ring-gray-700/60 text-center">
        <div class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">Audience</div>
        <div class="text-xs sm:text-sm font-semibold mt-0.5">TCC Students</div>
      </div>
    </div>
  </section>

  {{-- ======= WRAPPER: sticky section nav + main ======= --}}
  <div class="grid lg:grid-cols-[220px,1fr] gap-4 sm:gap-6">
    @php
      $anchors = [
        ['id'=>'build','label'=>'How we built it', 'icon' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-5c1.62-2.2 5-3 5-3"/><path d="M12 15v5s3.03-.55 5-2c2.2-1.62 3-5 3-5"/>'],
        ['id'=>'flow','label'=>'How it works', 'icon' => '<path d="M11 11H5a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h6"/><path d="M7 15l4 4"/><path d="M7 19l4-4"/><path d="M13 13h6a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2h-6"/><path d="M17 8l-4-4"/><path d="M17 4l-4 4"/>'],
        ['id'=>'responses','label'=>'Response sources', 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
        ['id'=>'rasa','label'=>'Rasa integration', 'icon' => '<rect width="16" height="16" x="4" y="4" rx="2"/><rect width="6" height="6" x="9" y="9" rx="1"/><path d="M15 2v2"/><path d="M15 20v2"/><path d="M2 15h2"/><path d="M2 9h2"/><path d="M20 15h2"/><path d="M20 9h2"/><path d="M9 2v2"/><path d="M9 20v2"/>'],
        ['id'=>'privacy','label'=>'Privacy & Safety', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>'],
        ['id'=>'faq','label'=>'FAQ', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>'],
        ['id'=>'credits','label'=>'Acknowledgments', 'icon' => '<path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/>'],
      ];
    @endphp

    {{-- Section Nav (sticky, with scroll-spy) --}}
    <nav aria-label="About sections" class="hidden lg:block sticky top-20 self-start reveal">
      <ul id="about-toc" class="space-y-1 text-[14px]">
        @foreach ($anchors as $a)
          <li>
            <a href="#{{ $a['id'] }}" class="toc-link kb-focus block pl-4 pr-3 py-2.5 transition" data-target="{{ $a['id'] }}">
              <svg class="w-4 h-4 shrink-0 opacity-70 group-hover:opacity-100 transition-opacity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                {!! $a['icon'] !!}
              </svg>
              <span>{{ $a['label'] }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    </nav>

    {{-- ======= MAIN COLUMN ======= --}}
    <div class="space-y-8">

      {{-- Mobile "On this page" dropdown --}}
      <div class="lg:hidden -mt-2 mb-4 relative z-20">
        <label for="about-toc-mobile" class="sr-only">On this page</label>
        <div class="relative">
          <select id="about-toc-mobile" class="w-full appearance-none rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/90 py-2.5 pl-4 pr-10 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition-shadow">
            @foreach ($anchors as $a)
              <option value="{{ $a['id'] }}">{{ $a['label'] }}</option>
            @endforeach
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
            <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </div>
        </div>
      </div>


      {{-- Build / Stack - Mobile Fixed --}}
      <section id="build" class="section-anchor space-y-4 reveal">
        <h3 class="text-lg sm:text-xl font-bold break-words">How we created & implemented LumiCHAT</h3>
        <div class="grid md:grid-cols-2 gap-3 sm:gap-4">
          <div class="rounded-xl sm:rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-4 sm:p-5 transition hover:shadow-md hover:-translate-y-[2px] overflow-hidden">
            <h4 class="font-semibold text-sm sm:text-base break-words">Frontend (Student UI)</h4>
            <ul class="mt-2 list-disc pl-4 sm:pl-5 text-gray-600 dark:text-gray-300 space-y-1 text-xs sm:text-sm break-words">
              @foreach ($techStack['Frontend'] as $item) <li class="break-words">{{ $item }}</li> @endforeach
            </ul>
          </div>
          <div class="rounded-xl sm:rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-4 sm:p-5 transition hover:shadow-md hover:-translate-y-[2px] overflow-hidden">
            <h4 class="font-semibold text-sm sm:text-base break-words">Backend (Server)</h4>
            <ul class="mt-2 list-disc pl-4 sm:pl-5 text-gray-600 dark:text-gray-300 space-y-1 text-xs sm:text-sm break-words">
              @foreach ($techStack['Backend'] as $item) <li class="break-words">{{ $item }}</li> @endforeach
            </ul>
          </div>
          <div class="rounded-xl sm:rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-4 sm:p-5 transition hover:shadow-md hover:-translate-y-[2px] overflow-hidden">
            <h4 class="font-semibold text-sm sm:text-base break-words">NLP & Chat Brain</h4>
            <ul class="mt-2 list-disc pl-4 sm:pl-5 text-gray-600 dark:text-gray-300 space-y-1 text-xs sm:text-sm break-words">
              @foreach ($techStack['NLP / Chat'] as $item) <li class="break-words">{{ $item }}</li> @endforeach
            </ul>
          </div>
          <div class="rounded-xl sm:rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-4 sm:p-5 transition hover:shadow-md hover:-translate-y-[2px] overflow-hidden">
            <h4 class="font-semibold text-sm sm:text-base break-words">Build & Config</h4>
            <ul class="mt-2 list-disc pl-4 sm:pl-5 text-gray-600 dark:text-gray-300 space-y-1 text-xs sm:text-sm break-words">
              @foreach ($techStack['Infra / Build'] as $item) <li class="break-words">{{ $item }}</li> @endforeach
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

        <div class="space-y-4">
          {{-- Explanation card --}}
          <div class="rounded-2xl bg-white/80 dark:bg-gray-800/70 shadow-sm ring-1 ring-gray-200/60 dark:ring-gray-700/60 p-5">
            <p class="text-gray-600 dark:text-gray-300">
              Responses are defined in Rasa’s main <code class="px-1 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-800">domain.yml</code>
              file under the <code class="px-1 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-800">responses</code> section.  
              From there, Rasa uses stories/rules and custom actions to decide which
              response to send. Content is aligned with counselor-approved guidance and
              student support resources. Language avoids diagnosis; it uses reflective
              prompts and referrals for high-risk cues.
            </p>
          </div>

          {{-- Proof: real excerpt from domain.yml --}}
          <div class="rounded-xl bg-slate-900 text-slate-100 p-4 text-[12px] leading-relaxed overflow-x-auto ring-1 ring-slate-700/60">
            <div class="mb-2 text-[11px] font-semibold text-slate-300 flex items-center gap-2">
              <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
              Excerpt from <code class="px-1 py-[1px] rounded bg-slate-800 border border-slate-700">domain.yml</code>
            </div>
            <pre class="whitespace-pre">
            responses:
              utter_greet:
                - text: "Hi! How can I help you today?"
                - text: "Hello! How are you feeling today?"

              utter_thanks:
                - text: "You're welcome! I'm here to help."

              utter_goodbye:
                - text: "Take care, and thank you for chatting today."

              utter_mood_sad/p0001:
                - text: >
                    I’m really sorry you’re feeling sad right now. It’s okay to have
                    those moments—feelings like that mean you’ve been trying hard.
                    You don’t have to rush yourself; little by little, things can
                    get lighter again.

              utter_mood_happy/p0001:
                - text: >
                    That’s so nice to hear! You deserve moments like this—simple,
                    calm, and happy. I hope you keep that feeling with you today;
                    you’ve earned it.
            </pre>
          </div>
        </div>
      </section>

      {{-- Rasa integration (code sample) --}}
      <section id="rasa" class="section-anchor space-y-3 reveal">
        <h3 class="text-xl font-bold">Rasa ↔ Frontend integration (REST / Webhook)</h3>
        <div class="rounded-xl bg-slate-900 text-slate-100 p-4 text-[13px] overflow-x-auto ring-1 ring-slate-700/60">
      <pre>// ChatController::store (excerpt)
      $r = Http::timeout($timeout)
          ->withOptions(['verify' => $verify])
          ->withHeaders(['Accept' => 'application/json'])
          ->post($rasaUrl, [
              'sender'   => 'u_' . $userId . '_s_' . $sessionId,
              'message'  => $rasaMessage,
              'metadata' => $metadata,
          ]);

      $payload = $r->json() ?? [];
      foreach ($payload as $piece) {
          $txt = isset($piece['text']) ? (string) $piece['text'] : '';
          $btn = (isset($piece['buttons']) && is_array($piece['buttons'])) ? $piece['buttons'] : [];
          // ...
      }</pre>
        </div>
        <p class="text-gray-600 dark:text-gray-300">
          The frontend sends the student’s message plus metadata to Rasa using this webhook, then
          parses the JSON replies (text + buttons) and shows them in the chat UI.
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

/* Smooth-scroll + Scroll-spy + Mobile TOC */
(function(){
  const html = document.documentElement;
  const headerOffset = 92; 
  const scroller = document.querySelector('.panel-scroll') || document.querySelector('.main-content') || window;
  const isWindow = scroller === window;

  const links = Array.from(document.querySelectorAll('.toc-link[data-target], .toc-link[href^="#"]'))
    .map(a => { if (!a.dataset.target) a.dataset.target = a.getAttribute('href').slice(1); return a; });

  const sections = links
    .map(a => document.getElementById(a.dataset.target))
    .filter(Boolean);

  function setActiveLink(id){
    links.forEach(l => l.classList.toggle('active', l.dataset.target === id));
  }

  // Desktop Clicks
  links.forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      const id = a.dataset.target;
      const el = document.getElementById(id);
      if (!el) return;
      
      const behavior = html.classList.contains('reduce-motion') ? 'auto' : 'smooth';
      el.scrollIntoView({ behavior: behavior, block: 'start' });
      
      el.setAttribute('tabindex','-1');
      el.focus({ preventScroll: true });
      setActiveLink(id);
    });
  });

  // Mobile Dropdown
  const mobileSel = document.getElementById('about-toc-mobile');
  mobileSel?.addEventListener('change', () => {
    const id = mobileSel.value;
    const el = document.getElementById(id);
    if (!el) return;
    
    const behavior = html.classList.contains('reduce-motion') ? 'auto' : 'smooth';
    el.scrollIntoView({ behavior: behavior, block: 'start' });
    
    el.setAttribute('tabindex','-1');
    el.focus({ preventScroll: true });
  });

  // Scroll Spy
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
