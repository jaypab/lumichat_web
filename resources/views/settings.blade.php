@extends('layouts.app')
@section('title', 'Lumi - Settings')
@section('page_title', 'Settings')  

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">

  {{-- ======= Enhanced Header (gradient band) ======= --}}
  <section class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-sm animate-fadeup">
    <div class="p-5 sm:p-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Settings</h2>
          <p class="text-white/80 text-sm mt-0.5">Quick preferences for your LumiCHAT experience.</p>
        </div>

        <div class="flex items-center gap-2">
          <button id="restoreDefaultsBtn"
                  class="inline-flex items-center gap-2 rounded-xl bg-white text-slate-900 px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50 active:scale-[.99] transition">
            Restore defaults
          </button>
        </div>
      </div>
    </div>
  </section>

  {{-- ================= Card: Display & Accessibility ================= --}}
  <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
    <div class="flex items-center gap-3 px-4 sm:px-6 py-3.5 bg-gray-50/80 dark:bg-white/5 border-b border-gray-100 dark:border-gray-800">
      <img src="{{ asset('images/icons/display.png') }}" alt="Display Icon" class="w-6 h-6 dark:invert select-none" draggable="false">
      <div class="min-w-0">
        <h3 class="text-[15px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">Display &amp; Accessibility</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">Theme, text size, and layout density.</p>
      </div>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
      {{-- Dark Mode --}}
      <div class="compact-row flex items-center justify-between gap-4 px-4 sm:px-6 py-3.5">
        <div class="min-w-0">
          <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Dark Mode</div>
          <p class="text-xs text-gray-500 dark:text-gray-400">Applies instantly and auto-saves.</p>
        </div>
        <label for="darkModeToggle" class="relative inline-flex items-center cursor-pointer select-none">
          <input id="darkModeToggle" type="checkbox" name="dark_mode" @checked($settings->dark_mode) class="sr-only peer" aria-label="Toggle dark mode">
          <span class="block w-14 h-8 rounded-full border transition-all duration-300 bg-gray-300 dark:bg-gray-700 border-gray-300 dark:border-gray-600 peer-checked:bg-indigo-600"></span>
          <span class="absolute left-1 top-1 w-6 h-6 rounded-full bg-white shadow transition-transform duration-300 peer-checked:translate-x-6"></span>
        </label>
      </div>

      {{-- Text Size --}}
      <div class="compact-row flex items-center justify-between gap-4 px-4 sm:px-6 py-3.5">
        <div class="min-w-0">
          <label for="fontSizeSelect" class="text-sm font-medium text-gray-900 dark:text-gray-100">Text Size</label>
          <p class="text-xs text-gray-500 dark:text-gray-400">Adjust overall reading size.</p>
        </div>
        <select id="fontSizeSelect"
                class="h-10 w-36 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="sm">Small</option>
          <option value="md" selected>Normal</option>
          <option value="lg">Large</option>
        </select>
      </div>

      {{-- Compact Layout --}}
      <div class="compact-row flex items-center justify-between gap-4 px-4 sm:px-6 py-3.5">
        <div class="min-w-0">
          <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Compact Layout</div>
          <p class="text-xs text-gray-500 dark:text-gray-400">Tighter paddings for smaller screens.</p>
        </div>
        <label for="compactToggle" class="relative inline-flex items-center cursor-pointer select-none">
          <input id="compactToggle" type="checkbox" class="sr-only peer" aria-label="Toggle compact layout">
          <span class="block w-14 h-8 rounded-full bg-gray-300 dark:bg-gray-700 peer-checked:bg-indigo-600 transition"></span>
          <span class="absolute left-1 top-1 w-6 h-6 rounded-full bg-white shadow transition-transform duration-200 peer-checked:translate-x-6"></span>
        </label>
      </div>
    </div>
  </section>

  {{-- ================= Card: Support ================= --}}
  <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
    <div class="flex items-center gap-3 px-4 sm:px-6 py-3.5 bg-gray-50/80 dark:bg-white/5 border-b border-gray-100 dark:border-gray-800">
      <img src="{{ asset('images/icons/support.png') }}" alt="Support Icon" class="w-6 h-6 dark:invert select-none" draggable="false">
      <div class="min-w-0">
        <h3 class="text-[15px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">Support</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">Contact the team or report an issue.</p>
      </div>
    </div>

    <div class="p-4 sm:p-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <a href="{{ route('support.contact') }}"
           class="inline-flex items-center justify-center h-11 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-sm font-medium hover:bg-indigo-100 dark:hover:bg-indigo-900/50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          Contact Support
        </a>
      </div>
    </div>
  </section>
</div>

{{-- Toast --}}
<div id="toast" class="hidden fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm shadow-lg" role="status" aria-live="polite"></div>

{{-- Helpers --}}
<style>
  html.font-sm body { font-size: 15px; }
  html.font-md body { font-size: 16px; }
  html.font-lg body { font-size: 17.5px; }
  html.compact .compact-row { padding-top:.5rem!important; padding-bottom:.5rem!important; }
  html.compact .h-11 { height:2.5rem!important; }
</style>

{{-- Logic (reduced: no reduce-motion, no privacy/data) --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const htmlEl    = document.documentElement;
  const csrf      = document.querySelector('meta[name="csrf-token"]')?.content;
  const saveUrl   = "{{ route('settings.update') }}";
  const toastEl   = document.getElementById('toast');

  const darkToggle = document.getElementById('darkModeToggle');
  const fontSel    = document.getElementById('fontSizeSelect');
  const compactTg  = document.getElementById('compactToggle');
  const restoreBtn = document.getElementById('restoreDefaultsBtn');

  function toast(msg){ if(!toastEl) return; toastEl.textContent = msg; toastEl.classList.remove('hidden'); setTimeout(()=>toastEl.classList.add('hidden'), 1400); }
  function autoSave(payload){
    const fd = new FormData();
    Object.entries(payload).forEach(([k,v]) => fd.append(k, v));
    fetch(saveUrl, { method:'POST', headers:{ 'X-CSRF-TOKEN': csrf }, body: fd })
      .then(()=>toast('Saved')).catch(()=>toast('Save failed'));
  }

  // Dark
  const savedDark = localStorage.getItem('lumichat_dark');
  if (savedDark === '1') { htmlEl.classList.add('dark'); darkToggle && (darkToggle.checked = true); }
  if (savedDark === '0') { htmlEl.classList.remove('dark'); darkToggle && (darkToggle.checked = false); }
  darkToggle?.addEventListener('change', () => {
    const on = !!darkToggle.checked;
    htmlEl.classList.toggle('dark', on);
    localStorage.setItem('lumichat_dark', on ? '1' : '0');
    autoSave({ dark_mode: on ? 1 : 0 });
  });

  // Text size
  const fsSaved = localStorage.getItem('lumichat_font_size') || 'md';
  ['font-sm','font-md','font-lg'].forEach(c => htmlEl.classList.remove(c));
  htmlEl.classList.add('font-' + fsSaved);
  if (fontSel) fontSel.value = fsSaved;
  fontSel?.addEventListener('change', () => {
    ['font-sm','font-md','font-lg'].forEach(c => htmlEl.classList.remove(c));
    htmlEl.classList.add('font-' + fontSel.value);
    localStorage.setItem('lumichat_font_size', fontSel.value);
    toast('Saved');
  });

  // Compact
  const cSaved = localStorage.getItem('lumichat_compact');
  if (cSaved === '1') { htmlEl.classList.add('compact'); compactTg && (compactTg.checked = true); }
  compactTg?.addEventListener('change', () => {
    const on = compactTg.checked;
    htmlEl.classList.toggle('compact', on);
    localStorage.setItem('lumichat_compact', on ? '1' : '0');
    toast('Saved');
  });

  // Restore defaults (only what remains)
  restoreBtn?.addEventListener('click', async () => {
    htmlEl.classList.remove('dark','compact');
    ['font-sm','font-md','font-lg'].forEach(c => htmlEl.classList.remove(c));
    htmlEl.classList.add('font-md');

    if (darkToggle) darkToggle.checked = false;
    if (compactTg) compactTg.checked = false;
    if (fontSel) fontSel.value = 'md';

    localStorage.setItem('lumichat_dark','0');
    localStorage.setItem('lumichat_compact','0');
    localStorage.setItem('lumichat_font_size','md');

    // Persist only server-backed fields
    const fd = new FormData();
    fd.append('dark_mode', 0);
    try {
      await fetch("{{ route('settings.update') }}", { method:'POST', headers:{ 'X-CSRF-TOKEN': csrf }, body: fd });
      toast('Defaults restored');
    } catch { toast('Restore failed'); }
  });
});
</script>
@endsection
