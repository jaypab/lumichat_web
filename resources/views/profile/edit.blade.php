{{-- resources/views/profile/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Profile')

@section('content')
@php
  $hour     = now()->hour;
  $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-3">
  {{-- Greeting / hero --}}
  <header class="rounded-2xl border border-gray-200/70 dark:border-gray-700
                 bg-gradient-to-r from-indigo-50/80 via-violet-50/70 to-fuchsia-50/60
                 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800/70
                 shadow-sm p-6 md:p-8 text-center animate-card">
    <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold
                bg-white/80 dark:bg-gray-900/60 text-indigo-600 dark:text-indigo-300
                border border-indigo-100/60 dark:border-gray-700">
      <span>👋</span><span>Welcome</span>
    </div>

    <h1 class="mt-3 text-2xl md:text-3xl font-semibold tracking-tight title-dynamic">
      {{ $greeting }}, {{ Auth::user()->name }}
    </h1>

    <p class="mt-1 text-sm md:text-[15px] muted-dynamic">
      You can manage your personal information and security settings below.
    </p>
  </header>

  {{-- Cards --}}
  <div class="mt-6 md:mt-8 grid gap-6 md:gap-8 lg:grid-cols-12 items-stretch" data-sync-group="profile-password">

    {{-- Profile Information --}}
    <section class="lg:col-span-7 card-shell p-5 sm:p-6 lg:p-7 animate-card" data-sync-root>
      @include('profile.partials.update-profile-information-form', [
        'user'         => $user,
        'registration' => $registration ?? null,
      ])
    </section>

    {{-- Update Password --}}
    <section class="lg:col-span-5 card-shell p-5 sm:p-6 lg:p-7 animate-card" data-sync-root>
      @include('profile.partials.update-password-form')
    </section>

    {{-- Delete Account --}}
    <section class="lg:col-span-12 card-shell p-5 sm:p-6 lg:p-7 animate-card">
      @include('profile.partials.delete-user-form')
    </section>
  </div>
</div>

{{-- Enhanced toasts + error SweetAlerts --}}
@include('profile.partials.alerts')
@endsection

@push('styles')
<style>
  /* Entrance animation */
  .animate-card { animation: fadeSlideUp .35s cubic-bezier(.21,.8,.26,1) both; }
  @keyframes fadeSlideUp { 0%{opacity:0;transform:translateY(10px) scale(.98)} 100%{opacity:1;transform:translateY(0) scale(1)} }

  /* Shared header row */
  .form-head{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.75rem; min-height:44px; }
  @media (min-width:640px){ .form-head{ min-height:46px; } }

  /* Button footprint so headers align */
  .btn-size{ height:40px; padding:0 1rem; border-radius:.75rem; display:inline-flex; align-items:center; }

  .btn-press{ transition: transform .12s ease, box-shadow .12s ease; }
  .btn-press:active{ transform: translateY(1px) scale(.985); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  /* Equalize header heights */
  function equalizeHeads() {
    document.querySelectorAll('[data-sync-group]').forEach(group => {
      const heads = group.querySelectorAll('.form-head');
      let max = 0;
      heads.forEach(h => { h.style.minHeight = 'auto'; max = Math.max(max, h.getBoundingClientRect().height); });
      heads.forEach(h => h.style.minHeight = Math.ceil(max) + 'px');
    });
  }
  equalizeHeads();
  window.addEventListener('resize', equalizeHeads);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(equalizeHeads);

  /* Smooth “Saving…” UX on Update Password */
  const pwForm = document.querySelector('#update-password-section form');
  if (pwForm) {
    const btn = pwForm.querySelector('button[type="submit"]');
    pwForm.addEventListener('submit', () => {
      if (!btn) return;
      btn.disabled = true;
      btn.classList.add('opacity-80','cursor-not-allowed');
      btn.dataset._label = btn.textContent;
      btn.textContent = 'Saving…';
    }, { once: true });
  }
});
</script>
@endpush
