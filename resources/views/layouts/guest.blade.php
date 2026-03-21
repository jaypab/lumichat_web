<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
      try{
        let pref = localStorage.getItem('lumichat_dark');
        if (pref === null) { localStorage.setItem('lumichat_dark','0'); pref = '0'; }
        if (pref === 'true') { localStorage.setItem('lumichat_dark','1'); pref = '1'; }
        if (pref === 'false'){ localStorage.setItem('lumichat_dark','0'); pref = '0'; }
        const wantsDark = pref === '1';
        document.documentElement.classList.toggle('dark', wantsDark);
        document.documentElement.classList.toggle('dark-theme', wantsDark);
      }catch(_){}
    </script>
</head>
@php
    // Props for overriding the logo when needed
    $logoPath = $attributes->get('logo-path');   // kebab-case in component → $logoPath here
    $logoAlt  = $attributes->get('logo-alt', 'Logo');
@endphp
<body class="font-sans text-gray-900 antialiased">
  <script>
    if (localStorage.getItem('lumichat_dark') === '1') {
      document.body.classList.add('dark-theme');
    }
  </script>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
        <div>
            <a href="{{ route('login') }}">
                @if ($logoPath)
                    <img
                        src="{{ asset($logoPath) }}"
                        alt="{{ $logoAlt }}"
                        class="h-20 w-20 object-contain shadow-none border-0 ring-0 rounded-none bg-transparent"
                        style="box-shadow:none; border:none; background:transparent;"
                    >
                @else
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                @endif
            </a>
        </div>
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
