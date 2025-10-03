/* LumiCHAT chat.nomodule.js — plain script build (no imports) */
(function () {
  if (window.LUMI_CHAT_JS_ACTIVE) return;
  window.LUMI_CHAT_JS_ACTIVE = true;

  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

  ready(function () {
    if (!window.axios) { console.error('[LumiCHAT] axios not found. Load CDN before this script.'); return; }
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf && csrf.content) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.content;

    var form     = document.querySelector('#chat-form');
    var input    = document.querySelector('#chat-message');
    var messages = document.querySelector('#chat-messages');
    var sendBtn  = document.querySelector('#sendBtn');
    var storeUrl = (form && form.getAttribute('action')) || "/chat";

    // >>> booking URL (change this when you deploy)
    var APPT_URL = "http://127.0.0.1:8000/appointment/book";

    function scrollBottom(){ if (messages) messages.scrollTop = messages.scrollHeight; }
    function sanitizeHTML(s){ return /[<>]/.test(s) ? s : String(s || '').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // Make plain URLs clickable inside bubbles
    function linkify(text){
      var urlRE = /(https?:\/\/[^\s)]+)|(www\.[^\s)]+)/gi;
      return String(text||'').replace(urlRE, function(m){
        var href = m.indexOf('http') === 0 ? m : ('http://' + m);
        return '<a href="'+href+'" target="_blank" rel="noopener">'+m+'</a>';
      });
    }

    // Render Rasa buttons. If a button has `url`, open link; otherwise send payload.
    // Also: if payload is /affirm{"confirm_topic":"referral"} → convert to link (APPT_URL)
    function renderButtons(buttons, container){
      if (!Array.isArray(buttons) || !buttons.length) return;
      var wrap = document.createElement('div');
      wrap.className = 'bot-actions mt-2 flex gap-2 flex-wrap';

      buttons.forEach(function(b){
        var referralPayload = b && typeof b.payload === 'string' && /\/affirm\s*\{\s*"confirm_topic"\s*:\s*"referral"\s*\}/i.test(b.payload);

        if (b && b.url || referralPayload){
          var href = (b && b.url) || APPT_URL;
          var a = document.createElement('a');
          a.className = 'qr-btn text-xs px-3 py-1.5 rounded-md border';
          a.textContent = (b && b.title) || 'Open link';
          a.href = href;
          a.target = '_blank';
          a.rel = 'noopener';
          wrap.appendChild(a);
        } else {
          var btn = document.createElement('button');
          btn.className = 'qr-btn text-xs px-3 py-1.5 rounded-md border';
          btn.textContent = (b && b.title) || 'Select';
          btn.addEventListener('click', function(){
            var p = (b && (b.payload || b.title)) || '';
            appendUserBubble(p, new Date().toLocaleTimeString());
            send(p);
          });
          wrap.appendChild(btn);
        }
      });

      container.appendChild(wrap);
    }

    function appendUserBubble(text, time){
      if (!messages) return;
      messages.insertAdjacentHTML('beforeend', '\
        <div class="w-full min-w-0">\
          <div class="msg-row user flex items-end justify-end gap-2">\
            <div class="bubble bubble-user px-4 py-2 rounded-2xl text-base text-left max-w-[85%]"></div>\
            <div class="avatar shrink-0 w-8 h-8 rounded-full grid place-items-center">🧑</div>\
          </div>\
          <div class="msg-time text-[10px] opacity-70 mt-1 text-right">'+(time||'')+'</div>\
        </div>');
      var bubble = messages.lastElementChild.querySelector('.bubble-user');
      bubble.textContent = text;
      scrollBottom();
    }

    // Remove affirm/confirm: for referral messages, show direct link
    function addBotActions(bubble){
      try{
        var txt = bubble.textContent || '';
        var plain = txt.toLowerCase();
        var isCoping   = /share\s+coping\s+tips/i.test(txt) || (/coping\s+mechanism/i.test(plain) && /want(\s+them)?\s+now\??/i.test(plain));
        var isReferral = /open the appointment page\??/i.test(txt) || /book\s+counselor/i.test(plain) || /appointment page/i.test(plain);
        if (!(isCoping || isReferral)) return;

        var actions = document.createElement('div');
        actions.className = 'bot-actions mt-2 flex gap-2 flex-wrap';

        if (isReferral){
          var a = document.createElement('a');
          a.className = 'qr-btn text-xs px-3 py-1.5 rounded-md border';
          a.textContent = 'Book counselor';
          a.href = APPT_URL;
          a.target = '_blank';
          a.rel = 'noopener';
          actions.appendChild(a);

          var notNow = document.createElement('button');
          notNow.className = 'qr-btn text-xs px-3 py-1.5 rounded-md border';
          notNow.textContent = 'Not now';
          notNow.addEventListener('click', function(){ sendQuick('/deny{"confirm_topic":"referral"}'); });
          actions.appendChild(notNow);
        } else if (isCoping){
          var noBtn = document.createElement('button');
          noBtn.className = 'qr-btn text-xs px-3 py-1.5 rounded-md border';
          noBtn.textContent = 'No, thanks';
          noBtn.addEventListener('click', function(){ sendQuick('/deny{"confirm_topic":"coping"}'); });
          actions.appendChild(noBtn);

          var yesBtn = document.createElement('button');
          yesBtn.className = 'qr-btn text-xs px-3 py-1.5 rounded-md border';
          yesBtn.textContent = 'Yes, show tips';
          yesBtn.addEventListener('click', function(){ sendQuick('/affirm{"confirm_topic":"coping"}'); });
          actions.appendChild(yesBtn);
        }

        bubble.appendChild(actions);
      }catch(e){ console.warn('[LumiCHAT] addBotActions failed', e); }
    }

    function typewriter(bubble, finalHTML, speed, minDotsMs){
      speed = speed || 24;
      minDotsMs = minDotsMs || 700;
      var prefersReduced = false;
      try { prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}
      return new Promise(function(resolve){
        if (prefersReduced){
          bubble.innerHTML = finalHTML;
          addBotActions(bubble);
          resolve(); return;
        }
        var start = Date.now();
        function run(){
          if (Date.now() - start < minDotsMs){ return requestAnimationFrame(run); }
          var tmp = document.createElement('div'); tmp.innerHTML = finalHTML;
          var plain = tmp.textContent || tmp.innerText || '';
          bubble.textContent = '';
          var i = 0;
          (function tick(){
            bubble.textContent = plain.slice(0, i+1);
            i++; scrollBottom();
            if (i < plain.length){ setTimeout(tick, speed); }
            else {
              bubble.innerHTML = finalHTML;
              addBotActions(bubble);
              scrollBottom();
              resolve();
            }
          })();
        }
        requestAnimationFrame(run);
      });
    }

    // Accept string OR object (so we can render buttons)
    function appendBotBubble(msgOrObj, time){
      time = time || '';
      return new Promise(function(resolve){
        messages.insertAdjacentHTML('beforeend', '\
          <div class="w-full min-w-0">\
            <div class="msg-row bot flex items-end justify-start gap-2">\
              <div class="avatar shrink-0 w-8 h-8 rounded-full grid place-items-center">🤖</div>\
              <div class="bubble bubble-ai px-4 py-2 rounded-2xl text-base text-left max-w-[85%]"></div>\
            </div>\
            <div class="msg-time text-[10px] opacity-70 mt-1">'+time+'</div>\
          </div>');
        var bubble = messages.lastElementChild.querySelector('.bubble-ai');

        bubble.innerHTML = '\
          <span class="inline-flex items-center gap-1" style="color:#6b7280">\
            <span class="dot w-2 h-2 rounded-full"></span>\
            <span class="dot w-2 h-2 rounded-full"></span>\
            <span class="dot w-2 h-2 rounded-full"></span>\
          </span>\
          <span class="sr-only">Assistant is typing…</span>';
        scrollBottom();

        var pre = 350 + Math.floor(Math.random()*400);
        setTimeout(function(){
          var obj = (msgOrObj && typeof msgOrObj === 'object') ? msgOrObj : { text: msgOrObj };
          var rawText = obj.text || obj.bot_reply || obj.message || '';
          var safe = sanitizeHTML(rawText);
          var html = linkify(safe);

          typewriter(bubble, html, 24, 700).then(function(){
            // render Rasa buttons if present
            if (obj && obj.buttons && Array.isArray(obj.buttons) && obj.buttons.length){
              renderButtons(obj.buttons, bubble);
            }
            // support { custom: { open_url: ... } }
            if (obj && obj.custom && obj.custom.open_url){
              try { window.open(obj.custom.open_url, '_blank'); } catch(e){}
            }
            scrollBottom();
            resolve();
          });
        }, pre);
      });
    }

    // strict queue
    var botQueue = Promise.resolve();
    function q(task){ botQueue = botQueue.then(task).catch(function(e){ console.warn('[LumiCHAT] queue error', e); }); return botQueue; }

    function sendQuick(text){
      appendUserBubble(text, new Date().toLocaleTimeString());
      send(text);
    }

    async function send(message){
      try{
        if (sendBtn) sendBtn.disabled = true;
        var res = await axios.post(storeUrl, { message: message });

        // Pass RAW replies (objects) so appendBotBubble can render buttons
        var replies = Array.isArray(res.data && res.data.bot_reply) ? res.data.bot_reply
                     : [res.data && res.data.bot_reply];

        for (var i=0; i<(replies||[]).length; i++){
          var msg = replies[i];
          if (!msg) continue;
          await q(function(){ return appendBotBubble(msg, (res.data && res.data.time_human) || ''); });
          await q(function(){ return new Promise(function(r){ setTimeout(r, 250); }); });
        }
      } catch(err){
        console.error('[LumiCHAT] Error:', err && (err.response || err.message));
        await q(function(){ return appendBotBubble('Sorry, I’m having trouble right now.', ''); });
      } finally {
        if (sendBtn) sendBtn.disabled = false;
        if (input) input.focus();
      }
    }

    // prevent multiple bindings
    if (form && form.dataset.bound) return;
    if (form) { form.dataset.bound = '1'; form.onsubmit = null; }
    if (form) form.addEventListener('submit', function(e){
      e.preventDefault();
      var raw = (input && input.value) || '';
      var msg = String(raw).trim();
      if (!msg) return;
      input.value = '';
      appendUserBubble(msg, new Date().toLocaleTimeString());
      send(msg);
    });

    console.log('[LumiCHAT] chat.nomodule.js loaded');
  });
})();
