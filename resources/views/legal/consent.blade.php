{{-- resources/views/legal/consent.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Terms & Conditions | LumiCHAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @vite('resources/css/app.css')
  <style>
    html { font-feature-settings: "liga","kern","clig","calt"; }
    .measure { max-width: 64ch; }
    @keyframes fadeIn { from { opacity:.0; transform: translateY(6px) } to { opacity:1; transform:none } }
  </style>
</head>
<body class="bg-slate-50 antialiased overflow-hidden">

  {{-- Backdrop --}}
  <div class="fixed inset-0 bg-slate-900/70"></div>

  {{-- Centered modal --}}
  <div id="modalRoot"
       class="fixed inset-0 flex items-center justify-center p-4"
       role="dialog" aria-modal="true" aria-labelledby="tc-title" aria-describedby="tc-desc">

    <div class="relative z-10 w-full max-w-4xl rounded-3xl bg-white/95 backdrop-blur
                shadow-[0_24px_80px_-20px_rgba(2,6,23,.55)] ring-1 ring-black/10
                animate-[fadeIn_.22s_ease-out] flex flex-col max-h-[88vh] md:max-h-[84vh]">

      {{-- Header --}}
      <div class="rounded-t-3xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-5">
        <div class="flex items-center justify-between">
          <div class="flex items-start gap-3">
            <div class="rounded-xl bg-white/20 p-2 text-white">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2a1 1 0 0 1 1 1v1h3.5a2.5 2.5 0 1 1 0 5H7.5a2.5 2.5 0 1 1 0-5H11V3a1 1 0 0 1 1-1zM6 10.5A3.5 3.5 0 0 0 2.5 14v3A4.5 4.5 0 0 0 7 21.5h10A4.5 4.5 0 0 0 21.5 17v-3A3.5 3.5 0 0 0 18 10.5H6zM9 15a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm6 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
              </svg>
            </div>
            <div>
              <h1 id="tc-title" class="text-white text-lg md:text-xl font-semibold tracking-tight">Terms &amp; Conditions</h1>
              <p id="tc-desc" class="text-white/85 text-xs">Please review and accept to continue.</p>
            </div>
          </div>
          <span class="rounded-full bg-white/20 px-2.5 py-1 text-[11px] font-medium text-white">v{{ $tosVersion }}</span>
        </div>
      </div>

      {{-- Body (scrollable) --}}
      <div class="px-6 py-5 flex-1 min-h-0 overflow-y-auto overscroll-contain">
        <div class="mx-auto max-w-3xl space-y-6 text-[15px] leading-7 text-slate-700">

          {{-- Friendly summary --}}
          <section class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
            <h2 class="text-slate-900 text-[15px] font-semibold mb-2">Quick summary</h2>
            <ul class="space-y-4">
            @php
                $qs = [
                'Your chat is protected with strong encryption while being sent and when saved.',
                'We only keep content if our safety system detects a possible high-risk message (e.g., self-harm, threats, or immediate danger) so the school can act to keep you safe.',
                'You can leave anytime. For emergencies, use campus hotlines or local responders.',
                'Basic profile info (e.g., name, age, program) helps personalize support and reports. We follow school policy and applicable laws.',
                ];
            @endphp

            @foreach ($qs as $line)
                <li class="relative pl-7 text-[15px] leading-7 text-slate-700">
                <span class="absolute left-1 top-[0.6rem] block h-2 w-2 rounded-full
                            bg-gradient-to-br from-indigo-500 to-violet-500"></span>
                {{ $line }}
                </li>
            @endforeach
            </ul>
          </section>

          {{-- What we collect / how we use (informative, not legalese) --}}
          <section class="rounded-2xl border border-slate-200 bg-white">
            <header class="px-5 py-4 border-b">
              <h3 class="text-slate-900 text-[16px] font-semibold">What we collect &amp; how it’s used</h3>
            </header>
            <div class="p-5 overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="text-left text-slate-500">
                  <tr class="align-top">
                    <th class="py-2 pr-4">Category</th>
                    <th class="py-2 pr-4">Examples</th>
                    <th class="py-2">Purpose</th>
                  </tr>
                </thead>
                <tbody class="text-slate-700">
                    <tr class="border-t">
                        <td class="py-3 pr-4 font-medium">Account</td>
                        <td class="py-3 pr-4">Name, student ID, email, role, program</td>
                        <td class="py-3">Sign-in, personalization, counselor linkage, student reports</td>
                    </tr>

                    <tr class="border-t">
                        <td class="py-3 pr-4 font-medium">Safety (kept only if high-risk is detected)</td>
                        <td class="py-3 pr-4">Short except around the high-risk message, risk type/level, time</td>
                        <td class="py-3">Enable rapid support, required safety logs, and follow-up by authorized staff</td>
                    </tr>

                    <tr class="border-t">
                        <td class="py-3 pr-4 font-medium">Usage</td>
                        <td class="py-3 pr-4">Device/OS, IP, session metadata</td>
                        <td class="py-3">Security, performance, troubleshooting, abuse prevention</td>
                    </tr>
                </tbody>
              </table>
            </div>
          </section>

          {{-- The actual terms (simple numbered list) --}}
          <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-slate-900 text-[16px] font-semibold">Your agreement</h3>
            <ol class="list-decimal pl-6 space-y-2 marker:text-indigo-500">
                <li>Your conversations are protected with strong encryption while being sent and when saved.</li>
                <li>We only keep content when our safety system detects a possible high-risk message (such as self-harm, threats, or imminent danger) so authorized staff can respond and keep students safe.</li>
                <li>Minimal account and usage details are handled as described above in line with school policy and applicable laws.</li>
                <li>You may leave at any time. For urgent safety issues, please contact campus hotlines or emergency services.</li>
            </ol>
          </section>

          {{-- Guidelines --}}
          <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h3 class="text-slate-900 text-[16px] font-semibold mb-2">Community guidelines</h3>
            <ul class="list-disc pl-6 space-y-1.5 marker:text-indigo-500">
              <li>Be respectful; avoid harmful, threatening, or illegal content.</li>
              <li>Do not share passwords or highly sensitive medical/financial information in chat.</li>
              <li>For minors, campus child protection & counseling rules apply.</li>
            </ul>
            <p class="mt-3 text-[12.5px] text-slate-500">
              By continuing, you acknowledge the
              <a href="{{ route('privacy.policy') }}" class="font-medium text-indigo-600 hover:text-indigo-700 underline decoration-indigo-300/70 hover:decoration-indigo-500">Privacy Policy</a>.
            </p>
          </section>

          {{-- Optional: collapsible definitions (friendly) --}}
          <section class="rounded-2xl border border-slate-200 bg-white">
            <button type="button"
                    class="w-full flex items-center justify-between px-5 py-4 text-left"
                    aria-expanded="false" data-acc="defs">
              <span class="text-slate-900 text-[16px] font-semibold">Definitions</span>
              <svg class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a1 1 0 0 1-.7-.29l-4-4a1 1 0 0 1 1.4-1.42L10 9.59l3.3-3.3a1 1 0 1 1 1.4 1.42l-4 4A1 1 0 0 1 10 12z"/></svg>
            </button>
            <div class="px-5 pb-5 hidden" id="defs-panel">
              <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div>
                  <dt class="font-medium text-slate-900">Conversation data</dt>
                  <dd class="text-slate-600">The text you exchange with LumiCHAT during a session, plus timestamps and system events.</dd>
                </div>
                <div>
                  <dt class="font-medium text-slate-900">Risk flag</dt>
                  <dd class="text-slate-600">A safety signal (e.g., crisis keywords) used to prioritize appropriate guidance or escalation.</dd>
                </div>
                <div>
                  <dt class="font-medium text-slate-900">Usage metadata</dt>
                  <dd class="text-slate-600">Technical information such as device/OS, IP, and session identifiers to maintain security.</dd>
                </div>
                <div>
                  <dt class="font-medium text-slate-900">Retention</dt>
                  <dd class="text-slate-600">How long records may be kept to operate the service and satisfy audit or legal requirements.</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-900">High-risk message</dt>
                    <dd class="text-slate-600">A message that suggests possible danger to self or others (e.g., self-harm, threats, immediate risk). Only short excerpts around these messages may be kept to enable rapid support.</dd>
                </div>
              </dl>
            </div>
          </section>
        </div>
      </div>

{{-- Sticky actions --}}
<div class="px-6 py-4 rounded-b-3xl bg-slate-50/80 border-t border-slate-200 shrink-0">
  <form method="POST" action="{{ route('legal.accept') }}"
        class="flex flex-col sm:flex-row items-center justify-between gap-3">
    @csrf
    <label class="flex items-start gap-3 select-none">
      <input id="agree" type="checkbox" name="agree" value="1"
        class="mt-1 h-5 w-5 rounded-md border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-600/50 focus:ring-offset-0">
      <span class="text-sm text-slate-700">I agree to the Terms and Conditions.</span>
    </label>

    <div class="flex items-center gap-3">
      {{-- Decline --}}
      <form id="decline-form" method="POST" action="{{ route('legal.decline') }}">
        @csrf
        <button type="submit"
          class="text-sm font-medium text-slate-500 hover:text-slate-700">
          Decline
        </button>
      </form>

      <button id="continueBtn" type="submit" disabled
        class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white
               bg-gradient-to-r from-indigo-600 to-violet-600 shadow-sm ring-1 ring-black/5
               hover:opacity-95 active:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
        Continue
      </button>
    </div>
  </form>
</div>

    </div>
  </div>

  {{-- Behavior: checkbox gating, focus trap, accordion, block ESC/backdrop clicks --}}
  <script>
    // Enable/disable Continue
    const agree = document.getElementById('agree');
    const btn = document.getElementById('continueBtn');
    agree.addEventListener('change', () => { btn.disabled = !agree.checked; });

    // Accordion (Definitions)
    const accBtn = document.querySelector('[data-acc="defs"]');
    const accPanel = document.getElementById('defs-panel');
    accBtn?.addEventListener('click', () => {
      const open = accPanel.classList.toggle('hidden') === false;
      accBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // Prevent closing with ESC / outside click
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') e.preventDefault(); });
    document.querySelector('.fixed.inset-0.bg-slate-900\\/70').addEventListener('click', e => e.stopPropagation());

    // Focus trap
    const modal = document.getElementById('modalRoot');
    const getNodes = () => modal.querySelectorAll('a,button,input,textarea,select,[tabindex]:not([tabindex="-1"])');
    document.addEventListener('keydown', e => {
      if (e.key !== 'Tab') return;
      const nodes = Array.from(getNodes()); if (!nodes.length) return;
      const first = nodes[0], last = nodes[nodes.length - 1];
      if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    });
    agree.focus();
  </script>

  <style>
    @supports (height: 100dvh) {
      .max-h-\[88vh\] { max-height: 88dvh; }
      .md\:max-h-\[84vh\] { max-height: 84dvh; }
    }
  </style>
</body>
</html>
