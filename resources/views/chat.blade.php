{{-- resources/views/chat.blade.php --}}
@extends('layouts.app')
@section('tab_title', 'Lumi - Chat Interface')
@section('page_title', 'Chat')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
  @keyframes lumiBounceDots {0%,80%,100%{transform:translateY(0);opacity:.55}40%{transform:translateY(-4px);opacity:1}}
  .typing-dots{display:inline-flex;gap:6px;align-items:center}
  .typing-dots .dot{width:6px;height:6px;border-radius:999px;background:currentColor;animation:lumiBounceDots 1.1s infinite}
  .typing-dots .dot:nth-child(2){animation-delay:.15s}
  .typing-dots .dot:nth-child(3){animation-delay:.30s}
</style>

<style>
  .lumi-qr{
    --qr-bg: rgba(99,102,241,.06);
    --qr-brd: rgba(99,102,241,.35);
    --qr-txt: inherit;
    --qr-sd: 0 0 #0000;
    font-size:12px; padding:8px 12px; border-radius:12px; border:1px solid var(--qr-brd);
    background: var(--qr-bg); color: var(--qr-txt); display:inline-flex; align-items:center; gap:.4rem;
    line-height:1; cursor:pointer; -webkit-tap-highlight-color: transparent;
    transition: background .18s ease, border-color .18s ease, transform .12s ease, box-shadow .18s ease, color .18s ease;
    box-shadow: var(--qr-sd);
  }
  .lumi-qr:hover{ background: rgba(99,102,241,.14); border-color: rgba(99,102,241,.55); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(79,70,229,.18); }
  .lumi-qr:active{ transform: translateY(0); box-shadow: 0 2px 8px rgba(79,70,229,.18); }
  .lumi-qr:focus-visible{ outline: none; box-shadow: 0 0 0 3px rgba(255,255,255,.9), 0 0 0 5px rgba(79,70,229,.55); transform: translateY(-1px); }
  .lumi-qr--primary{ --qr-bg:#4f46e5; --qr-brd:#4f46e5; --qr-txt:#fff; background: linear-gradient(180deg,#645cff 0%,#4f46e5 100%); color:#fff; border-color:#4f46e5; box-shadow: 0 3px 10px rgba(79,70,229,.22); }
  .lumi-qr--primary:hover{ filter: brightness(.98); box-shadow: 0 10px 22px rgba(79,70,229,.28); }
  .lumi-qr--primary:active{ filter: brightness(.96); box-shadow: 0 4px 12px rgba(79,70,229,.24); }
  .lumi-qr--link{ text-decoration:none; }
  .lumi-qr:disabled, .lumi-qr[disabled]{ opacity:.6; cursor:default; transform:none; box-shadow:none; }
  .dark .lumi-qr{ --qr-bg: rgba(99,102,241,.10); --qr-brd: rgba(99,102,241,.45); --qr-txt:#e5e7eb; background:var(--qr-bg); color:var(--qr-txt); border-color:var(--qr-brd); }
  .dark .lumi-qr:hover{ background: rgba(99,102,241,.18); border-color: rgba(99,102,241,.65); box-shadow: 0 8px 20px rgba(79,70,229,.30); }
  @media (prefers-reduced-motion: reduce){
    .lumi-qr{ transition: background .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .lumi-qr:hover, .lumi-qr:active, .lumi-qr:focus-visible{ transform:none; }
  }
</style>

<div class="px-2 sm:px-4 md:px-6 animate-fadeup">
  <div class="mx-auto w-full max-w-5xl h-[80vh]">

    {{-- ===================== Chat Panel ===================== --}}
    <div id="chat-wrapper"
         class="card-shell rounded-2xl overflow-hidden flex flex-col w-full"
         style="height:80vh"
         data-thread-id="{{ $thread->id ?? ('draft-'.\Illuminate\Support\Str::uuid()) }}"
         data-user-name="{{ e(auth()->user()?->first_name ?? auth()->user()?->preferred_name ?? auth()->user()?->name ?? 'there') }}"
         {{-- NEW: pass lock state from controller --}}
         data-locked="{{ !empty($isLocked) ? '1' : '0' }}">

      {{-- ===================== Header - Mobile Optimized ===================== --}}
      <div class="flex items-center gap-2 sm:gap-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-3 sm:px-5 py-2.5 sm:py-3 shadow">
        <img src="{{ asset('images/chatbot.png') }}" class="w-5 h-5 sm:w-6 sm:h-6 flex-shrink-0" alt="Bot">
        <div class="min-w-0 flex-1">
          <strong class="text-base sm:text-lg leading-tight block truncate">LumiCHAT Assistant</strong>
          <div class="text-[11px] sm:text-xs text-white/80 hidden xs:block truncate">Friendly support that respects your privacy</div>
        </div>
      </div>

      {{-- NEW: Slim banner if locked --}}
      @if(!empty($isLocked))
        <div class="bg-amber-50 text-amber-800 text-sm px-4 py-2 border-b border-amber-200">
          This conversation is closed. Start a new chat to continue.
          <a class="underline font-medium ml-2"
             href="{{ \Illuminate\Support\Facades\Route::has('chat.new') ? route('chat.new') : url('/chat/new') }}">
            Start new chat
          </a>
        </div>
      @endif

      {{-- ===================== Messages ===================== --}}
      <div class="flex-1 min-h-0 flex flex-col">
        <div id="chat-messages"
             role="log"
             aria-live="polite"
             aria-label="Chat conversation"
             class="flex-1 min-h-0 flex flex-col gap-3 p-4 overflow-y-auto bg-gray-50 dark:bg-gray-900">

          @foreach ($chats as $chat)
            @php
              $mine = $chat->sender !== 'bot';
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
              $base = 'display:inline-block !important;box-sizing:border-box !important;width:auto !important;max-width:min(520px,46ch) !important;min-height:0 !important;padding:6px 10px !important;margin:0 !important;border-radius:16px !important;white-space:pre-wrap !important;word-break:normal !important;overflow-wrap:anywhere !important;font-size:15px !important;line-height:22px !important;text-align:left !important;';
              // ✅ Let CSS classes handle colors for proper dark mode
              $bot  = $base.'align-self:flex-start !important;';
              $user = $base.'align-self:flex-end !important;margin-left:auto !important;';
              $timeStyle = 'font-size:10px;color:#9ca3af;margin-top:4px;'.($mine ? 'text-align:right;align-self:flex-end;' : 'text-align:left;align-self:flex-start;');
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

      {{-- ===================== Composer - Mobile Optimized ===================== --}}
      <form id="chat-form" action="{{ route('chat.store') }}" method="POST"
            class="px-3 sm:px-4 py-2.5 sm:py-3 border-t bg-white dark:bg-gray-800 dark:border-gray-700">
        @csrf
        <input type="hidden" id="idem" name="_idem" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <div class="group relative flex items-center h-11 sm:h-12 rounded-full bg-white dark:bg-gray-800
                    ring-1 ring-indigo-200 dark:ring-gray-700 focus-within:ring-2 focus-within:ring-indigo-400
                    transition shadow-sm">

          <textarea id="chat-message" name="message" maxlength="2000" rows="1" enterkeyhint="send"
            aria-label="Type your message here"
            aria-describedby="char-counter"
            class="flex-1 h-full px-3 sm:px-4 py-2 pr-[6.5rem] sm:pr-[7.5rem] bg-transparent border-0 rounded-l-full
                   text-[15px] sm:text-base
                   focus:outline-none focus:ring-0 focus:border-0 focus:shadow-none
                   placeholder:text-gray-400 dark:placeholder-gray-500 resize-none"
            placeholder="Type your message..." autocomplete="off" required></textarea>

          <div id="char-counter"
               role="status"
               aria-live="polite"
               class="absolute right-[5rem] sm:right-24 top-1/2 -translate-y-1/2 text-[10px] sm:text-[11px] text-gray-400 select-none hidden xs:block">
            0/2000
          </div>

          <button id="sendBtn" disabled
            aria-label="Send message"
            class="btn-primary absolute right-1 sm:right-1.5 top-1/2 -translate-y-1/2 h-8 sm:h-9 px-3 sm:px-4 rounded-full text-sm sm:text-base
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
    const wrap     = document.getElementById('chat-wrapper');

    const STORE_URL = @json(route('chat.store'));
    const MAXLEN    = 2000;
    const APPT_URL  = @json(\Illuminate\Support\Facades\Route::has('appointment.index')
                      ? route('appointment.index')
                      : url('/appointment/book'));

    // ==== NEW: Composer hard-disable helper when locked ====
    function disableComposer(reason, newChatUrl){
      const input = document.querySelector('#chat-message');
      const sendBtn = document.querySelector('#sendBtn');
      if (input){ input.disabled = true; input.placeholder = 'This conversation is closed.'; }
      if (sendBtn){ sendBtn.disabled = true; }
      if (form){ form.classList.add('opacity-60','pointer-events-none'); }
      const barId = 'lumi-closed-bar';
      if (!document.getElementById(barId)){
        const bar = document.createElement('div');
        bar.id = barId;
        bar.className = 'px-4 py-3 border-t bg-white/90 backdrop-blur sticky bottom-0';
        bar.innerHTML = `
          <div class="text-sm text-gray-700">
            This conversation is closed. <a class="text-indigo-600 underline" href="${newChatUrl}">Start new chat</a>
          </div>`;
        document.querySelector('#chat-wrapper')?.appendChild(bar);
      }
    }

    // Honor server lock on load
    if (wrap?.dataset.locked === '1') {
      const newUrl = @json(\Illuminate\Support\Facades\Route::has('chat.new') ? route('chat.new') : url('/chat/new'));
      disableComposer('declined_referral', newUrl);
    }

    // ==== (rest of your JS unchanged below) ====

    const TYPING_TWEAKS = [
      'display:inline-flex!important','align-items:center!important','justify-content:center!important',
      'padding:6px 8px!important','min-width:36px!important','min-height:22px!important',
      'width:auto!important','height:auto!important','border-radius:14px!important'
    ].join(';') + ';';

    const BASE = [
      'display:inline-block!important','box-sizing:border-box!important','width:auto!important',
      'max-width:min(520px,46ch)!important','min-height:0!important','padding:6px 10px!important',
      'margin:0!important','border-radius:16px!important','white-space:pre-wrap!important',
      'word-break:normal!important','overflow-wrap:anywhere!important','font-size:15px!important',
      'line-height:22px!important','text-align:left!important'
    ].join(';') + ';';

    const userStyle = `${BASE}background:#4f46e5!important;color:#ffffff!important;align-self:flex-end!important;margin-left:auto!important;border-radius:16px!important;`;
    const botStyle  = () => {
      const dark = document.documentElement.classList.contains('dark');
      return `${BASE}background:${dark ? '#1f2937' : '#f3f4f6'}!important;color:${dark ? '#f8fafc' : '#111827'}!important;align-self:flex-start!important;border-radius:16px!important;`;
    };

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

    // Debounce utility for performance optimization
    function debounce(fn, delay) {
      let timer = null;
      return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
      };
    }

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

    function appendUserBubble(text, time='', status='sending'){
      const statusIcons = {
        sending: '<span class="status-icon" title="Sending...">⏳</span>',
        sent: '<span class="status-icon" title="Sent">✓</span>',
        error: '<span class="status-icon" title="Failed - Click to retry" style="color:#ef4444;cursor:pointer">⚠</span>'
      };
      
      messages.insertAdjacentHTML('beforeend', `
        <div class="msg-row flex flex-col w-full min-w-0">
          <div class="bubble bubble-user" data-sender="user" style="${userStyle}"></div>
          <div style="font-size:10px;color:#9ca3af;margin-top:4px;text-align:right;align-self:flex-end;display:flex;align-items:center;gap:4px;justify-content:flex-end">
            <span class="msg-time">${time}</span>
            <span class="msg-status">${statusIcons[status] || ''}</span>
          </div>
        </div>`);
      const bubble = messages.lastElementChild.querySelector('.bubble-user');
      bubble.textContent = text;
      messages.scrollTop = messages.scrollHeight;
      return messages.lastElementChild;
    }
    
    function updateMessageStatus(msgRow, status){
      if (!msgRow) return;
      const statusSpan = msgRow.querySelector('.msg-status');
      if (!statusSpan) return;
      const statusIcons = {
        sending: '<span class="status-icon" title="Sending...">⏳</span>',
        sent: '<span class="status-icon" title="Sent">✓</span>',
        error: '<span class="status-icon" title="Failed - Click to retry" style="color:#ef4444;cursor:pointer">⚠</span>'
      };
      statusSpan.innerHTML = statusIcons[status] || '';
    }

    function appendBotBubbleShell(time=''){
      messages.insertAdjacentHTML('beforeend', `
        <div class="msg-row flex flex-col w-full min-w-0">
          <div class="bubble bubble-ai is-typing" data-sender="bot" style="${botStyle()}display:inline-flex!important;align-items:center!important;justify-content:center!important;padding:6px 8px!important;min-width:36px!important;min-height:22px!important;width:auto!important;height:auto!important;border-radius:14px!important">
            <span class="typing-dots" aria-hidden="true" style="color:#6b7280">
              <span class="dot"></span><span class="dot"></span><span class="dot"></span>
            </span>
          </div>
          <div style="font-size:10px;color:#9ca3af;margin-top:4px;text-align:left;align-self:flex-start;">${time}</div>
        </div>`);
      messages.scrollTop = messages.scrollHeight;
      return messages.lastElementChild.querySelector('.bubble-ai');
    }

   function typewriter(bubble, finalHTML, speed = 20, minDotsMs = 380, plainOverride = null){
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

  return new Promise((resolve) => {
    const finish = () => {
      bubble.style.cssText = botStyle();
      bubble.innerHTML = finalHTML;
      messages.scrollTop = messages.scrollHeight;
      resolve();
    };

    if (reduced) {  // Accessibility: no animation
      finish();
      return;
    }

    // Compute plain text length once
    const tmp = document.createElement('div');
    tmp.innerHTML = finalHTML;
    const plain = (plainOverride ?? tmp.textContent ?? tmp.innerText ?? '') || '';
    const len   = plain.length;

    // For very long messages, just show after short dots – no per-character typing
    if (len > 350) {
      const start = performance.now();
      const waitDots = () => {
        if (performance.now() - start < 220) {
          requestAnimationFrame(waitDots);
          return;
        }
        finish();   // instant render
      };
      requestAnimationFrame(waitDots);
      return;
    }

    // Medium length: faster typing + shorter dots
    if (len > 200) {
      speed     = 10;   // faster per character
      minDotsMs = 260;  // shorter “thinking” phase
    }

    const start = performance.now();
    const waitDots = () => {
      if (performance.now() - start < minDotsMs) {
        requestAnimationFrame(waitDots);
        return;
      }

      bubble.classList.remove('is-typing');
      bubble.textContent = '';
      let i = 0;

      (function tick(){
        bubble.textContent = plain.slice(0, i + 1);
        i++;
        messages.scrollTop = messages.scrollHeight;
        if (i < plain.length) {
          setTimeout(tick, speed);
        } else {
          finish();
        }
      })();
    };

    requestAnimationFrame(waitDots);
  });
}


    let Q = Promise.resolve();
    const runQ = (task) => (Q = Q.then(task).catch(()=>{}));
    const now12h = () => new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });

    // Enhanced error handling with reconnection
    let reconnectAttempts = 0;
    const MAX_RECONNECT_ATTEMPTS = 3;
    
    async function showOutageNotice(kind, retry = false){
      if (retry && reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
        reconnectAttempts++;
        const countdown = 3 - reconnectAttempts + 1;
        const msg = `Connection issue detected. Retrying in ${countdown} seconds... (Attempt ${reconnectAttempts}/${MAX_RECONNECT_ATTEMPTS})`;
        await runQ(() => appendBotBubble(msg, ''));
        return;
      }
      
      const msg = (kind === 'http') 
        ? 'LumiChat is temporarily unavailable. Please try again in a moment.' 
        : 'I\'m having trouble connecting right now. Please check your internet connection and try again.';
      await runQ(() => appendBotBubble(msg, ''));
      reconnectAttempts = 0;
    }

    let _pendingDisplayText = null;
    let _currentMsgRow = null;

    function sendAction(displayText, payloadText){
      _currentMsgRow = appendUserBubble(displayText, now12h(), 'sending');
      _pendingDisplayText = displayText;
      send(payloadText ?? displayText);
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

        if (!res.ok){ 
          updateMessageStatus(_currentMsgRow, 'error');
          await showOutageNotice('http'); 
          return; 
        }
        const data = await res.json();
        
        // Mark message as sent
        updateMessageStatus(_currentMsgRow, 'sent');
        reconnectAttempts = 0; // Reset on success

        // NEW: if backend locked the thread, disable immediately
        if (data?.locked) {
          const url = data?.new_chat_url || @json(url('/chat/new'));
          disableComposer(data.lock_reason || 'locked', url);
        }

        let replies = data?.bot_reply;
        if (!Array.isArray(replies)) replies = [replies];
        for (const r of (replies || [])){
          await runQ(() => appendBotBubble(r, data?.time_human || ''));
          await runQ(() => new Promise(done => setTimeout(done, 60)));
        }
      } catch {
        _pendingDisplayText = null;
        updateMessageStatus(_currentMsgRow, 'error');
        await showOutageNotice('net');
      } finally {
        if (sendBtn) sendBtn.disabled = true === (wrap?.dataset.locked === '1') ? true : false;
        if (wrap?.dataset.locked !== '1') { sendBtn.disabled = false; input?.focus(); }
      }
    }

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
            sendAction(label, payload);
            afterClick();
          });
          wrap.appendChild(btn);
        }
      });
      bubble.appendChild(wrap);
    }

    function addQuickActions(bubble){
      if (bubble.querySelector('[data-qa="qr"]')) return;
      const raw = (bubble.textContent || '').trim();
      const plain = raw.toLowerCase();
      const asksForTips =
        /share\s+coping\s+tips/i.test(raw) ||
        (plain.includes('coping') && /want(\s+them)?\s*now\??/.test(plain));
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

async function appendBotBubble(payload, time = ''){
  const bubble = appendBotBubbleShell(time);

  // shorter base delay
  await new Promise(r => setTimeout(r, 100 + Math.floor(Math.random() * 180)));

  const obj  = (payload && typeof payload === 'object') ? payload : { text: payload };
  const text = obj.text ?? obj.bot_reply ?? obj.message ?? '';
  const html = renderBotContent(text || '');

  // Compute length once and pass through so typewriter can decide behavior
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  const plain = tmp.textContent || tmp.innerText || '';
  const len   = plain.length;

  await typewriter(bubble, html, 20, 380, plain);

  const hasRasaButtons = Array.isArray(obj.buttons) && obj.buttons.length > 0;
  if (hasRasaButtons) {
    renderButtons(obj.buttons, bubble);
  } else {
    addQuickActions(bubble);
  }

  if (obj?.id) {
    bubble.setAttribute('data-msg-id', String(obj.id));
    try {
      sessionStorage.setItem(`lumi_btn_${obj.id}`, JSON.stringify(hasRasaButtons ? obj.buttons : []));
    } catch (_) {}
  }

  if (obj?.custom?.open_url) window.open(obj.custom.open_url, '_blank');
  messages.scrollTop = messages.scrollHeight;
}


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

    input.dispatchEvent(new Event('input'));
    updateCounter();
    messages && (messages.scrollTop = messages.scrollHeight);

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
                if (Array.isArray(btns) && btns.length) { renderButtons(btns, bubble); return; }
              } catch(_) {}
            }
          }
          addQuickActions(bubble);
        });
      } catch(_) {}
    })();

    // ✅ Show animated greeting for new sessions
    try {
      const serverGreeting = @json($greeting ?? null);
      
      if (serverGreeting) {
        // Server provided a personalized greeting - animate it!
        runQ(() => {
          const bubble = appendBotBubbleShell("");
          return new Promise(resolve => {
            setTimeout(() => {
              typewriter(bubble, serverGreeting, 20, 800)
                .then(resolve);
            }, 300);
          });
        });
      }
    } catch {}
  });
})();
</script>
@endpush
