{{-- resources/views/profile/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Lumi - Profile')
@section('page_title', 'Profile Information')

@section('content')
  @php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
  @endphp

  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-3">
    {{-- Greeting / hero --}}
    <header class="profile-hero relative overflow-hidden rounded-3xl border border-slate-200/60 dark:border-gray-700
                   bg-white dark:bg-gray-800/50 shadow-[0_10px_40px_-15px_rgba(0,0,0,0.05)] p-8 md:p-10 text-center animate-card">
      
      {{-- Background Accents --}}
      <div class="absolute -top-24 -left-24 w-64 h-64 bg-indigo-500/5 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-violet-500/5 rounded-full blur-3xl"></div>

      <div class="relative z-10">
        <div class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-[10px] font-black uppercase tracking-widest
                    bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400
                    border border-indigo-100 dark:border-indigo-500/20 mb-4">
          <span class="animate-pulse text-indigo-400">✨</span><span>Dashboard</span>
        </div>

        <h1 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
          {{ $greeting }}, <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-violet-600">{{ explode(' ', Auth::user()->name)[0] }}</span>
        </h1>

        <p class="mt-3 text-sm md:text-base text-slate-500 dark:text-gray-400 font-medium max-w-xl mx-auto">
          Manage your digital campus identity, security preferences, and personal information in one secure location.
        </p>
      </div>
    </header>

    {{-- Cards --}}
    <div class="mt-6 md:mt-8 grid gap-6 md:gap-8 lg:grid-cols-12 items-stretch" data-sync-group="profile-password">

      {{-- Profile Information --}}
      <section class="lg:col-span-7 card-shell p-5 sm:p-6 lg:p-7 animate-card" data-sync-root>
        @include('profile.partials.update-profile-information-form', [
          'user' => $user,
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










    /* Shared card style */
    .card-shell {
      background: white;
      border: 1px solid rgba(226, 232, 240, 0.8);
      border-radius: 1.5rem;
      box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    .dark .card-shell {
      background: rgba(31, 41, 55, 0.4);
      border-color: rgba(55, 65, 81, 0.5);
      box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.3);
    }
    .card-shell:hover {
      border-color: rgba(99, 102, 241, 0.3);
      box-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.08);
    }

    /* Entrance animation */
    .animate-card { animation: fadeSlideUp .6s cubic-bezier(.16,1,.3,1) both; }
    @keyframes fadeSlideUp { 
      0% { opacity: 0; transform: translateY(20px); }
      100% { opacity: 1; transform: translateY(0); }
    }







    /* Shared header row */
    .form-head{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.75rem; min-height:44px; }
    @media (m i
  n   -width: 640px
  )   { .form- head{ m
  i   n-height:46px;  } }




    /* Button  f
  o   otprint so headers align */


     .btn-size{ height :
  4   0px; padding:0 1rem; border-radius:.75r
   em; display:inline-flex; align-items:center; }

    .btn-press{ transition: transform .12s ease, box-shadow .12s ease; }
    .btn-press:active{ transform: translateY(1px) scale(.985); }

     /* Ensure the profile hero border always darkens in dark mode. */
    .dark .profile-hero,
    html.dark-theme .profile-hero{
      border-color: rgb(55 65 81 / .9) !important;
    }
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
          pwForm.addEventListener('submit',  () => {
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
