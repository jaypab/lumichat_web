{{-- resources/views/chat.blade.php --}}
@extends('layouts.app')
@section('tab_title', 'Lumi - Chat Interface')   {{-- browser tab text --}}
@section('page_title', 'Chat')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- tiny CSS just for typing dots --}}
<style>
  @keyframes lumiBounceDots {0%,80%,100%{transform:translateY(0);opacity:.55}40%{transform:translateY(-4px);opacity:1}}
  .typing-dots{display:inline-flex;gap:6px;align-items:center}
  .typing-dots .dot{width:6px;height:6px;border-radius:999px;background:currentColor;animation:lumiBounceDots 1.1s infinite}
  .typing-dots .dot:nth-child(2){animation-delay:.15s}
  .typing-dots .dot:nth-child(3){animation-delay:.30s}
</style>

<style>
  /* Quick-reply buttons (with hover) */
  .lumi-qr{
    font-size:12px; padding:6px 10px; border-radius:12px;
    border:1px solid rgba(99,102,241,.35); background:rgba(99,102,241,.06);
    transition: background .15s ease, box-shadow .15s ease, transform .06s ease;
    cursor:pointer;
  }
  .lumi-qr:hover{
    background:rgba(99,102,241,.12); transform:translateY(-1px);
    box-shadow:0 2px 10px rgba(99,102,241,.18);
  }
  .lumi-qr--primary{
    border-color:#4f46e5; background:#4f46e5; color:#fff;
  }
  .lumi-qr--primary:hover{
    filter:brightness(.95); transform:translateY(-1px);
    box-shadow:0 2px 12px rgba(79,70,229,.30);
  }
  .lumi-qr:disabled, .lumi-qr[disabled]{
    opacity:.55; cursor:default; transform:none; box-shadow:none;
  }
  .lumi-qr--link{ text-decoration:none; display:inline-block; }
</style>

<div class="px-4 sm:px-6 animate-fadeup">
  <div class="mx-auto w-full max-w-5xl h-[80vh]">

    {{-- ===================== Chat Panel ===================== --}}
    <div id="chat-wrapper"
         class="card-shell rounded-2xl overflow-hidden flex flex-col w-full"
         style="height:80vh"
         data-thread-id="{{ $thread->id ?? ('draft-'.\Illuminate\Support\Str::uuid()) }}">

      {{-- ===================== Header ===================== --}}
      <div class="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-purple-600
                  text-white px-5 py-3 shadow">
        <img src="{{ asset('images/chatbot.png') }}" class="w-6 h-6" alt="Bot">
        <div class="min-w-0">
          <strong class="text-lg leading-tight">LumiCHAT Assistant</strong>
          <div class="text-xs text-white/80 hidden sm:block">Friendly support that respects your privacy</div>
        </div>
      </div>

      {{-- ===================== Messages ===================== --}}
      <div class="flex-1 min-h-0 flex flex-col">
        <div id="chat-messages"
             class="flex-1 min-h-0 flex flex-col gap-3 p-4 overflow-y-auto bg-gray-50 dark:bg-gray-900">

          @foreach ($chats as $chat)
            @php
              $mine = $chat->sender !== 'bot';

              // sanitize bot HTML (allow only http(s) links + <br>)
              $msg = $mine ? $chat->message : strip_tags($chat->message, '<a><br>');
              if (!$mine) {
                $msg = preg_replace_callback(
                  '/<a\b([^>]*?)href="([^"]+)"([^>]*)>(.*?)<\/a>/i',
                  function ($m) {
                    $href = $m[2];
                    $text = strip_tags($m[4]);
                    if (!preg_match('~^https?://~i', $href)) return e($text);
                    return '<a href="'.e($href).'" style="color:#4f46e5;text-decoration:underline">'.e($text).'</a>';
                  },
                  $msg
                );
              }

              // HARD inline bubble styles (protect against global overrides)
              $base = 'display:inline-block !important;box-sizing:border-box !important;'.
                      'width:auto !important;max-width:min(520px,46ch) !important;'.
                      'min-height:0 !important;padding:6px 10px !important;margin:0 !important;'.
                      'border-radius:16px !important;white-space:pre-wrap !important;'.
                      'word-break:normal !important;overflow-wrap:anywhere !important;'.
                      'font-size:15px !important;line-height:22px !important;text-align:left !important;';
              $bot  = $base.'background:#f3f4f6 !important;color:#111827 !important;align-self:flex-start !important;';
              $user = $base.'background:#4f46e5 !important;color:#ffffff !important;align-self:flex-end !important;margin-left:auto !important;';

              $timeStyle = 'font-size:10px;color:#9ca3af;margin-top:4px;'
                         . ($mine ? 'text-align:right;align-self:flex-end;' : 'text-align:left;align-self:flex-start;');
            @endphp

            <div class="msg-row flex flex-col w-full min-w-0">
              <div
                class="bubble {{ $mine ? 'bubble-user' : 'bubble-ai' }}"
                data-sender="{{ $mine ? 'user' : 'bot' }}"
                @if (!$mine) data-msg-id="{{ $chat->id }}" @endif
                style="{{ $mine ? $user : $bot }}"
              >{!! $mine ? e($chat->message) : $msg !!}</div>

              <div style="{{ $timeStyle }}">
                {{ \Carbon\Carbon::parse($chat->sent_at ?? $chat->created_at)->format('g:i:s A') }}
              </div>
            </div>
          @endforeach

        </div>
      </div>

      {{-- ===================== Composer ===================== --}}
      <form id="chat-form" action="{{ route('chat.store') }}" method="POST"
            class="px-4 py-3 border-t bg-white dark:bg-gray-800 dark:border-gray-700">
        @csrf
        <input type="hidden" id="idem" name="_idem" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <div class="group relative flex items-center h-12 rounded-full bg-white dark:bg-gray-800
                    ring-1 ring-indigo-200 dark:ring-gray-700 focus-within:ring-2 focus-within:ring-indigo-400
                    transition shadow-sm">

          <textarea id="chat-message" name="message" maxlength="2000" rows="1" enterkeyhint="send"
            class="flex-1 h-full px-4 py-2 pr-[7.5rem] bg-transparent border-0 rounded-l-full
                   focus:outline-none focus:ring-0 focus:border-0 focus:shadow-none
                   placeholder:text-gray-400 dark:placeholder-gray-500 resize-none"
            placeholder="Type your message..." autocomplete="off" required></textarea>

          <div id="char-counter"
               class="absolute right-24 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 select-none">
            0/2000
          </div>

          <button id="sendBtn" disabled
            class="btn-primary absolute right-1.5 top-1/2 -translate-y-1/2 h-9 px-4 rounded-full
                   disabled:opacity-50 disabled:pointer-events-none" type="submit">
            Send
          </button>
        </div>
      </form>
    </div>

    <p class="text-center text-gray-400 dark:text-gray-500 text-xs mt-3">
      Your conversations are encrypted and private.
    </p>
  </div>
</div>
@endsection

@push('scripts')
<script>
/* ========= Inline fallback (runs only if resources/js/chat.js is not active) ========= */
(function(){
  if (window.LUMI_CHAT_JS_ACTIVE) return;
  window.LUMI_CHAT_JS_ACTIVE = true;

  document.addEventListener('DOMContentLoaded', () => {
    const $ = s => document.querySelector(s);
    const messages = $('#chat-messages');
    const form     = $('#chat-form');
    const input    = $('#chat-message');
    const counter  = $('#char-counter');
    const sendBtn  = $('#sendBtn');
    const idemEl   = $('#idem');

    const STORE_URL = @json(route('chat.store'));
    const MAXLEN    = 2000;
    const APPT_URL  = @json(\Illuminate\Support\Facades\Route::has('appointment.index')
                      ? route('appointment.index')
                      : url('/appointment/book'));

    // Compact style only while the dots are showing
    const TYPING_TWEAKS = [
      'display:inline-flex!important',
      'align-items:center!important',
      'justify-content:center!important',
      'padding:6px 8px!important',
      'min-width:36px!important',
      'min-height:22px!important',
      'width:auto!important',
      'height:auto!important',
      'border-radius:14px!important'
    ].join(';') + ';';

    // Base bubble style
    const BASE = [
      'display:inline-block!important',
      'box-sizing:border-box!important',
      'width:auto!important',
      'max-width:min(520px,46ch)!important',
      'min-height:0!important',
      'padding:6px 10px!important',
      'margin:0!important',
      'border-radius:16px!important',
      'white-space:pre-wrap!important',
      'word-break:normal!important',
      'overflow-wrap:anywhere!important',
      'font-size:15px!important',
      'line-height:22px!important',
      'text-align:left!important'
    ].join(';') + ';';

    // User & bot bubble styles
    const userStyle = `${BASE}background:#4f46e5!important;color:#ffffff!important;align-self:flex-end!important;margin-left:auto!important;border-radius:16px!important;`;
    const botStyle  = () => {
      const dark = document.documentElement.classList.contains('dark');
      return `${BASE}background:${dark ? '#1f2937' : '#f3f4f6'}!important;color:${dark ? '#f8fafc' : '#111827'}!important;align-self:flex-start!important;border-radius:16px!important;`;
    };

    /* Sanitizers */
    const INVISIBLE_RE = /[\u200B\u200C\u200D\u2060\uFEFF]/g;
    const URL_RE = /(https?:\/\/[^\s<>"']+)/gi;
    const sanitizeClient = raw => (raw || '').replace(INVISIBLE_RE,'').replace(/\s+/g,' ').trim();
    const linkify = t => String(t||'').replace(URL_RE, m => `<a href="${m}" style="color:#4f46e5;text-decoration:underline">${m}</a>`);

    function sanitizeBotHtml(html){
      const tmp = document.createElement('div'); tmp.innerHTML = html;
      const walk = (node) => {
        for (const child of Array.from(node.childNodes)) {
          if (child.nodeType === Node.ELEMENT_NODE) {
            const tag = child.tagName.toLowerCase();
            if (tag === 'a') {
              const href = child.getAttribute('href') || '';
              if (!/^https?:\/\//i.test(href)) { child.replaceWith(document.createTextNode(child.textContent)); continue; }
              child.setAttribute('style','color:#4f46e5;text-decoration:underline');
              Array.from(child.attributes).forEach(a => { if (!['href','style'].includes(a.name)) child.removeAttribute(a.name); });
            } else if (tag !== 'br') {
              child.replaceWith(document.createTextNode(child.textContent));
            }
            walk(child);
          }
        }
      };
      walk(tmp);
      return tmp.innerHTML;
    }
    const renderBotContent = s => /[<>]/.test(s) ? sanitizeBotHtml(s) : sanitizeBotHtml(linkify(s));

    /* Counter */
    function updateCounter(){
      let v = input.value || '';
      if (v.length > MAXLEN){ v = v.slice(0, MAXLEN); input.value = v; }
      counter.textContent = `${v.length}/${MAXLEN}`;
      sendBtn.disabled = sanitizeClient(v).length === 0;
      counter.classList.toggle('text-red-600', v.length >= MAXLEN);
    }
    input.addEventListener('input', updateCounter);
    input.addEventListener('paste', (e)=>{
      const cd = e.clipboardData || window.clipboardData; if (!cd) return; e.preventDefault();
      const clip = cd.getData('text'); if (clip == null) return;
      const sanitized = String(clip).replace(INVISIBLE_RE, '');
      const start = input.selectionStart ?? input.value.length, end = input.selectionEnd ?? input.value.length;
      const before = input.value.slice(0, start), after = input.value.slice(end);
      const remaining = Math.max(0, MAXLEN - (before.length + after.length));
      const toInsert  = sanitized.slice(0, remaining);
      input.value = before + toInsert + after;
      const caret  = start + toInsert.length; input.setSelectionRange?.(caret, caret);
      updateCounter();
    });

    /* Bubble appenders */
    function appendUserBubble(text, time=''){
      messages.insertAdjacentHTML('beforeend', `
        <div class="msg-row flex flex-col w-full min-w-0">
          <div class="bubble bubble-user" data-sender="user" style="${userStyle}"></div>
          <div style="font-size:10px;color:#9ca3af;margin-top:4px;text-align:right;align-self:flex-end;">${time}</div>
        </div>`);
      const bubble = messages.lastElementChild.querySelector('.bubble-user');
      bubble.textContent = text;
      messages.scrollTop = messages.scrollHeight;
    }

    function appendBotBubbleShell(time=''){
      messages.insertAdjacentHTML('beforeend', `
        <div class="msg-row flex flex-col w-full min-w-0">
          <div class="bubble bubble-ai is-typing" data-sender="bot" style="${botStyle()}${TYPING_TWEAKS}">
            <span class="typing-dots" aria-hidden="true" style="color:#6b7280">
              <span class="dot"></span><span class="dot"></span><span class="dot"></span>
            </span>
          </div>
          <div style="font-size:10px;color:#9ca3af;margin-top:4px;text-align:left;align-self:flex-start;">${time}</div>
        </div>`);
      messages.scrollTop = messages.scrollHeight;
      return messages.lastElementChild.querySelector('.bubble-ai');
    }

    /* Typewriter */
    function typewriter(bubble, finalHTML, speed=24, minDotsMs=650){
      const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
      return new Promise((resolve)=>{
        const finish = () => {
          bubble.style.cssText = botStyle();
          bubble.innerHTML = finalHTML;
          messages.scrollTop = messages.scrollHeight;
          resolve();
        };

        if (reduced){ finish(); return; }

        const start = performance.now();
        const waitDots = () => {
          if (performance.now() - start < minDotsMs) return requestAnimationFrame(waitDots);
          const tmp = document.createElement('div'); tmp.innerHTML = finalHTML;
          const plain = tmp.textContent || tmp.innerText || '';
          bubble.classList.remove('is-typing');
          bubble.textContent = '';
          let i = 0;
          (function tick(){
            bubble.textContent = plain.slice(0, i+1);
            i++; messages.scrollTop = messages.scrollHeight;
            if (i < plain.length) setTimeout(tick, speed);
            else finish();
          })();
        };
        requestAnimationFrame(waitDots);
      });
    }

    /* ---------- Send queue (defined BEFORE send) ---------- */
    let Q = Promise.resolve();
    const runQ = (task) => (Q = Q.then(task).catch(()=>{}));

    /* ---------- Helpers: time, sendAction, send ---------- */

    // time like "5:47:28 PM"
    const now12h = () =>
      new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });

    let _pendingDisplayText = null;

    function sendAction(displayText, payloadText){
      appendUserBubble(displayText, now12h());   // show the nice label + time
      _pendingDisplayText = displayText;         // remember label so backend gets display_text
      send(payloadText ?? displayText);          // send actual payload
    }

    function sendQuick(text){ sendAction(text, text); }

    async function send(message){
      try{
        if (sendBtn) sendBtn.disabled = true;
        const idem = (crypto?.randomUUID?.() ?? (Date.now() + '-' + Math.random().toString(16).slice(2)));
        if (idemEl) idemEl.value = idem;

        const body = { message, _idem: idem };
        if (_pendingDisplayText) body.display_text = _pendingDisplayText;

        const res = await fetch(STORE_URL, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'Accept':'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(body)
        });

        _pendingDisplayText = null;

        if (!res.ok){ await runQ(()=>appendBotBubble('No reply from LumiCHAT Assistant.', '')); return; }
        const data = await res.json();

        let replies = data?.bot_reply;
        if (!Array.isArray(replies)) replies = [replies];

        for (const r of (replies || [])){
          await runQ(() => appendBotBubble(r, data?.time_human || ''));
          await runQ(() => new Promise(done => setTimeout(done, 220)));
        }
      } catch {
        _pendingDisplayText = null;
        await runQ(()=>appendBotBubble('Sorry, I’m having trouble right now.', ''));
      } finally {
        if (sendBtn) sendBtn.disabled = false;
        input?.focus();
      }
    }

    /* Rasa/structured buttons from backend */
    function renderButtons(buttons, bubble){
      if (!Array.isArray(buttons) || !buttons.length) return;
      const wrap = document.createElement('div');
      wrap.style.cssText = 'margin-top:8px;display:flex;flex-wrap:wrap;gap:8px';
      wrap.setAttribute('data-qa','qr');

      const btnClass = 'lumi-qr';
      const afterClick = () => {
        wrap.querySelectorAll('button').forEach(b=>{
          b.disabled = true; b.style.opacity = '.55'; b.style.cursor = 'default';
        });
      };

      buttons.forEach(b => {
        if (b?.url){
          const a = document.createElement('a');
          a.textContent = b.title || 'Open';
          a.href = b.url; a.rel = 'noopener';
          a.className = btnClass + ' lumi-qr--link';
          wrap.appendChild(a);
        } else {
          const btn = document.createElement('button');
          btn.type = 'button';
          const label   = b.title || 'Okay';
          const payload = String(b.payload ?? label);
          btn.textContent = label;
          btn.className = btnClass;
          btn.addEventListener('click', ()=>{
            sendAction(label, payload); // show label, send payload
            afterClick();               // prevent double clicks
          });
          wrap.appendChild(btn);
        }
      });
      bubble.appendChild(wrap);
    }

    /* Quick actions (tips / referral) */
    function addQuickActions(bubble){
      if (bubble.querySelector('[data-qa="qr"]')) return;

      const raw = (bubble.textContent || '').trim();
      const plain = raw.toLowerCase();

      const asksForTips =
        /share\s+coping\s+tips/i.test(raw) ||
        (plain.includes('coping') && /want(\s+them)?\s*now\??/.test(plain));

      // Only trigger referral CTA when message explicitly mentions booking/appointment
      const mentionsReferral =
        /book\s+(a\s*)?counselor|appointment\s+page|open\s+the\s+appointment|schedule\s+an?\s*appointment/i.test(plain);

      const box = document.createElement('div');
      box.setAttribute('data-qa','qr');
      box.style.cssText = 'margin-top:8px;display:flex;flex-wrap:wrap;gap:8px';

      const pill = 'lumi-qr';
      const pillPrimary = 'lumi-qr lumi-qr--primary';

      if (asksForTips){
        const noBtn = document.createElement('button');
        noBtn.className = pill; noBtn.textContent = 'No, thanks';
        noBtn.addEventListener('click', ()=> sendAction('No, thanks', '/deny{"confirm_topic":"coping"}'));
        box.appendChild(noBtn);

        const yesBtn = document.createElement('button');
        yesBtn.className = pillPrimary; yesBtn.textContent = 'Yes, show tips';
        yesBtn.addEventListener('click', ()=> sendAction('Yes, show tips', '/affirm{"confirm_topic":"coping"}'));
        box.appendChild(yesBtn);

      } else if (mentionsReferral){
        const a = document.createElement('a');
        a.className = pillPrimary + ' lumi-qr--link'; a.textContent = 'Book counselor';
        a.href = APPT_URL; a.rel = 'noopener';
        box.appendChild(a);

        const laterBtn = document.createElement('button');
        laterBtn.className = pill; laterBtn.textContent = 'Not now';
        laterBtn.addEventListener('click', ()=> sendAction('Not now', '/deny{"confirm_topic":"referral"}'));
        box.appendChild(laterBtn);
      } else {
        return;
      }

      bubble.appendChild(box);
    }

    async function appendBotBubble(payload, time=''){
      const bubble = appendBotBubbleShell(time);
      await new Promise(r => setTimeout(r, 300 + Math.floor(Math.random()*420)));

      const obj  = (payload && typeof payload === 'object') ? payload : { text: payload };
      const text = obj.text ?? obj.bot_reply ?? obj.message ?? '';
      const html = renderBotContent(text || '');

      await typewriter(bubble, html, 24, 650);

      const hasRasaButtons = Array.isArray(obj.buttons) && obj.buttons.length > 0;
      if (hasRasaButtons) {
        renderButtons(obj.buttons, bubble);
      } else {
        addQuickActions(bubble); // fallback UI only if no Rasa buttons
      }

      if (obj?.id) {
        bubble.setAttribute('data-msg-id', String(obj.id));
        try {
          sessionStorage.setItem(
            `lumi_btn_${obj.id}`,
            JSON.stringify(hasRasaButtons ? obj.buttons : [])
          );
        } catch (_) {}
      }

      if (obj?.custom?.open_url) window.open(obj.custom.open_url, '_blank');
      messages.scrollTop = messages.scrollHeight;
    }

    /* Enter to send + Submit — use sendAction so display_text + timestamp match quick-replies */
    input.addEventListener('keydown', (e) => {
      if (e.isComposing) return;
      if (e.key === 'Enter' && !e.shiftKey){
        e.preventDefault();
        const raw = input.value; input.value = ''; updateCounter();
        const cleaned = sanitizeClient(raw); if (!cleaned) return;
        sendAction(cleaned, cleaned);
      }
    });

    if (!form.dataset.bound){
      form.dataset.bound = '1';
      form.addEventListener('submit', (e)=>{
        e.preventDefault();
        const raw = input.value; input.value = ''; updateCounter();
        const cleaned = sanitizeClient(raw); if (!cleaned) return;
        sendAction(cleaned, cleaned);
      });
    }

    /* Init */
    input.dispatchEvent(new Event('input'));
    updateCounter();
    messages && (messages.scrollTop = messages.scrollHeight);

    // try to rehydrate buttons/quick actions for the last few messages
    (function rehydrateQuickActions(){
      try {
        const bots = Array.from(messages.querySelectorAll('.bubble-ai[data-sender="bot"]'));
        bots.slice(-12).forEach(bubble => {
          if (bubble.querySelector('[data-qa="qr"]') || bubble.querySelector('button')) return;
          const id = bubble.getAttribute('data-msg-id');
          if (id) {
            const raw = sessionStorage.getItem(`lumi_btn_${id}`);
            if (raw != null) {
              try {
                const btns = JSON.parse(raw);
                if (Array.isArray(btns) && btns.length) {
                  renderButtons(btns, bubble);
                  return;
                }
              } catch(_) {}
            }
          }
          addQuickActions(bubble);
        });
      } catch(_) {}
    })();

    // One-time auto welcome (per thread, 60 min)
    try {
      const hasMessages = !!messages.querySelector('.msg-row');
      const wrap = document.getElementById('chat-wrapper');
      const threadId = wrap?.dataset.threadId || location.pathname;
      const KEY = `lumi_welcome_${threadId}`;
      const now = Date.now();
      let last = 0;
      try { last = JSON.parse(sessionStorage.getItem(KEY))?.ts || 0; } catch {}
      const elapsedMin = (now - last) / 60000;
      if (!hasMessages && (!last || elapsedMin >= 60)){
        sessionStorage.setItem(KEY, JSON.stringify({ ts: now }));
        runQ(() => appendBotBubble("Hi! I’m Lumi — how can I help you today?", ""));
      }
    } catch {}
  });
})();
</script>
@endpush
