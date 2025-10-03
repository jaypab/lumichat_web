<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title') · LumiCHAT</title>
  @vite('resources/css/app.css')
  <style>
    /* Subtle animated gradient background */
    @keyframes bgShift {
      0%{ background-position: 0% 50% }
      50%{ background-position: 100% 50% }
      100%{ background-position: 0% 50% }
    }
    .page-bg{
      background: radial-gradient(1200px 600px at 0% 0%, #e9d5ff55, transparent 60%),
                  radial-gradient(1000px 500px at 100% 100%, #93c5fd55, transparent 60%),
                  linear-gradient(120deg, #eef2ff, #fff, #f5f3ff);
      background-size: 200% 200%;
      animation: bgShift 18s ease-in-out infinite;
    }
    /* Glass card + hover */
    .glass{
      @apply backdrop-blur-xl bg-white/70 dark:bg-gray-900/60 border border-white/60 dark:border-gray-800/50 shadow-2xl;
    }
    /* Entrance animations */
    @keyframes rise { from{opacity:0; transform: translateY(24px)} to{opacity:1; transform:none} }
    .animate-rise{ animation: rise .6s ease-out both; }
    .animate-rise-delayed{ animation: rise .6s ease-out .15s both; }
  </style>
  @stack('styles')
</head>
<body class="page-bg min-h-screen text-gray-900 dark:text-gray-100 flex items-center justify-center p-6">
  @yield('content')
  @vite('resources/js/app.js')
  @stack('scripts')
</body>
</html>
