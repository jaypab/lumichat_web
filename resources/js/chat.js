// resources/js/chat.js
import axios from "axios";

axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
console.log("%c[LumiCHAT] chat.js loaded", "color:#6d28d9;font-weight:bold");

if (!window.LUMI_CHAT_JS_ACTIVE) {
  window.LUMI_CHAT_JS_ACTIVE = true;

  document.addEventListener("DOMContentLoaded", () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) axios.defaults.headers.common["X-CSRF-TOKEN"] = csrf;

    const form     = document.querySelector("#chat-form");
    const input    = document.querySelector("#chat-message");
    const messages = document.querySelector("#chat-messages");
    const sendBtn  = document.querySelector("#sendBtn");
    const STORE_URL = form?.getAttribute("action") || "/chat";

    // Change on deploy if needed
    const APPT_URL = "http://127.0.0.1:8000/appointment/book";

    const scrollBottom = () => { if (messages) messages.scrollTop = messages.scrollHeight; };

    const sanitizeHTML = (s) => /[<>]/.test(s) ? s : String(s||"").replace(/</g,"&lt;").replace(/>/g,"&gt;");
    const linkify = (text) => {
      const urlRE = /(https?:\/\/[^\s)]+)|(www\.[^\s)]+)/gi;
      return String(text||"").replace(urlRE, (m) => {
        const href = m.startsWith("http") ? m : `http://${m}`;
        return `<a href="${href}" target="_blank" rel="noopener">${m}</a>`;
      });
    };

    function renderButtons(buttons, { container, sendPayload }) {
      if (!Array.isArray(buttons) || !buttons.length) return;
      const wrap = document.createElement("div");
      wrap.className = "bot-actions mt-2 flex gap-2 flex-wrap";

      buttons.forEach((b) => {
        const isReferralPayload =
          typeof b?.payload === "string" &&
          /\/affirm\s*\{\s*"confirm_topic"\s*:\s*"referral"\s*\}/i.test(b.payload);

        if (b?.url || isReferralPayload) {
          const href = b?.url || APPT_URL;
          const a = document.createElement("a");
          a.className = "qr-btn text-xs px-3 py-1.5 rounded-md border";
          a.textContent = b?.title || "Open link";
          a.href = href;
          a.target = "_blank";
          a.rel = "noopener";
          wrap.appendChild(a);
        } else {
          const btn = document.createElement("button");
          btn.className = "qr-btn text-xs px-3 py-1.5 rounded-md border";
          btn.textContent = b?.title || "Select";
          btn.addEventListener("click", () => sendPayload(b?.payload || b?.title || ""));
          wrap.appendChild(btn);
        }
      });

      container.appendChild(wrap);
    }

    function appendUserBubble(text, time = "") {
      messages.insertAdjacentHTML("beforeend", `
        <div class="msg-row flex flex-col w-full min-w-0 items-end text-right">
          <div class="bubble lb2 bubble-user bubble-tight rounded-2xl text-left"></div>
          <div class="msg-time text-[10px] text-gray-400 dark:text-gray-500 mt-1">${time}</div>
        </div>
      `);
      messages.lastElementChild.querySelector(".bubble-user").textContent = text;
      scrollBottom();
    }

    function rehydrateQuickActions(){
        try{
          if (!messages) return;
          const bots = Array.from(messages.querySelectorAll(".bubble-ai"));
          bots.slice(-4).forEach(b => maybeAddQuickActions(b));
        }catch(_){}
    }

    function maybeAddQuickActions(bubble){
      try{
        const raw = bubble.textContent || "";
        const plain = raw.toLowerCase();
        const isCoping   = /share\s+coping\s+tips/i.test(raw) || (/coping\s+mechanism/i.test(plain) && /want(\s+them)?\s+now\??/i.test(plain));
        const isReferral = /open the appointment page\??/i.test(raw) || /book\s+counselor/i.test(plain) || /appointment page/i.test(plain);
        if (!(isCoping || isReferral)) return;

        const box = document.createElement("div");
        box.className = "bot-actions mt-2 flex gap-2 flex-wrap";

        if (isReferral) {
          const a = document.createElement("a");
          a.className = "qr-btn text-xs px-3 py-1.5 rounded-md border";
          a.textContent = "Book counselor";
          a.href = APPT_URL;
          a.target = "_blank";
          a.rel = "noopener";
          box.appendChild(a);

          const btn = document.createElement("button");
          btn.className = "qr-btn text-xs px-3 py-1.5 rounded-md border";
          btn.textContent = "Not now";
          btn.addEventListener("click", () => sendQuick('/deny{"confirm_topic":"referral"}'));
          box.appendChild(btn);
        } else {
          const noBtn = document.createElement("button");
          noBtn.className = "qr-btn text-xs px-3 py-1.5 rounded-md border";
          noBtn.textContent = "No, thanks";
          noBtn.addEventListener("click", () => sendQuick('/deny{"confirm_topic":"coping"}'));
          box.appendChild(noBtn);

          const yesBtn = document.createElement("button");
          yesBtn.className = "qr-btn text-xs px-3 py-1.5 rounded-md border";
          yesBtn.textContent = "Yes, show tips";
          yesBtn.addEventListener("click", () => sendQuick('/affirm{"confirm_topic":"coping"}'));
          box.appendChild(yesBtn);
        }

        bubble.appendChild(box);
      }catch{}
    }

    function typewriter(bubble, finalHTML, speed = 24, minDotsMs = 650){
      const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches;
      return new Promise((resolve)=>{
        if (reduced){
          bubble.innerHTML = finalHTML; maybeAddQuickActions(bubble); return resolve();
        }
        const start = performance.now();
        const waitDots = () => {
          if (performance.now() - start < minDotsMs) return requestAnimationFrame(waitDots);
          const tmp = document.createElement("div"); tmp.innerHTML = finalHTML;
          const plain = tmp.textContent || tmp.innerText || "";
          bubble.textContent = "";
          let i = 0;
          (function tick(){
            bubble.textContent = plain.slice(0, i+1);
            i++; scrollBottom();
            if (i < plain.length) setTimeout(tick, speed);
            else { bubble.innerHTML = finalHTML; maybeAddQuickActions(bubble); scrollBottom(); resolve(); }
          })();
        };
        requestAnimationFrame(waitDots);
      });
    }

    async function appendBotBubble(payload, time = ""){
      return new Promise(async (resolve)=>{
        messages.insertAdjacentHTML("beforeend", `
          <div class="msg-row flex flex-col w-full min-w-0 items-start">
            <div class="bubble lb2 bubble-ai bubble-tight rounded-2xl text-left"></div>
            <div class="msg-time text-[10px] text-gray-400 dark:text-gray-500 mt-1">${time}</div>
          </div>
        `);
        const bubble = messages.lastElementChild.querySelector(".bubble-ai");

        bubble.classList.add("is-typing");
        bubble.innerHTML = `
          <span class="inline-flex items-center gap-1 text-gray-500">
            <span class="dot"></span><span class="dot"></span><span class="dot"></span>
          </span>
          <span class="sr-only">Assistant is typing…</span>
        `;
        scrollBottom();

        await new Promise(r => setTimeout(r, 320 + Math.floor(Math.random()*420)));

        const obj = (payload && typeof payload === "object") ? payload : { text: payload };
        const textRaw = obj.text ?? obj.bot_reply ?? obj.message ?? "";
        const html = linkify(sanitizeHTML(textRaw));
        await typewriter(bubble, html, 24, 650);

        bubble.classList.remove("is-typing");

        if (Array.isArray(obj.buttons) && obj.buttons.length) {
          renderButtons(obj.buttons, {
            container: bubble,
            sendPayload: (payloadStr) => {
              if (input) input.value = payloadStr;
              form?.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
            }
          });
        }

        if (obj?.custom?.open_url) window.open(obj.custom.open_url, "_blank");
        scrollBottom();
        resolve();
      });
    }

    // strict queue
    let Q = Promise.resolve();
    const runQ = (task) => (Q = Q.then(task).catch(e => console.warn("[LumiCHAT] queue error", e)));

    const sendQuick = (text) => { appendUserBubble(text, new Date().toLocaleTimeString()); send(text); };

    async function send(message){
      try{
        if (sendBtn) sendBtn.disabled = true;
        const res = await axios.post(STORE_URL, { message });
        let replies = res.data?.bot_reply;
        if (!Array.isArray(replies)) replies = [replies];
        for (const r of (replies || [])){
          await runQ(() => appendBotBubble(r, res.data?.time_human || ""));
          await runQ(() => new Promise(done => setTimeout(done, 240)));
        }
      }catch(err){
        console.error("[LUMI_CHAT] Error:", err?.response || err?.message);
        await runQ(() => appendBotBubble("Sorry, I’m having trouble right now.", ""));
      }finally{
        if (sendBtn) sendBtn.disabled = false;
        input?.focus();
      }
    }

    if (form?.dataset.bound) return;
    if (form){
      form.dataset.bound = "1";
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const raw = input?.value ?? "";
        const msg = String(raw).trim();
        if (!msg) return;
        input.value = "";
        appendUserBubble(msg, new Date().toLocaleTimeString());
        await send(msg);
      });
    }

    // welcome (per-thread, 60min cooldown)
    try{
      const hasMessages = !!messages?.querySelector(".msg-row");
      const wrap = document.getElementById("chat-wrapper");
      const threadId = wrap?.dataset?.threadId || location.pathname;
      const KEY = `lumi_welcome_${threadId}`;
      const now = Date.now();
      let last = 0;
      try { last = JSON.parse(sessionStorage.getItem(KEY))?.ts || 0; } catch {}
      const elapsedMin = (now - last) / 60000;
      if (!hasMessages && (!last || elapsedMin >= 60)){
        sessionStorage.setItem(KEY, JSON.stringify({ ts: now }));
        runQ(() => appendBotBubble("Hi! I’m Lumi — how can I help you today?", ""));
      }
    }catch(e){ console.warn("[LumiCHAT] welcome skipped:", e); }
  });
}
