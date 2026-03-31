<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error | LumiChat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; }
        .glass-card::before {
            content: ''; position: absolute; inset: 0;
            border-radius: inherit; padding: 1.5px;
            background: linear-gradient(to bottom right, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: xor; -webkit-mask-composite: destination-out;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-[#0a0910] text-white antialiased">
    <div class="relative min-h-screen w-full flex items-center justify-center overflow-hidden">
        <!-- Animated Background Beams -->
        <div class="pointer-events-none fixed inset-0 z-0">
            <div class="absolute -left-1/4 -top-1/4 h-full w-full animate-pulse rounded-full bg-violet-600/10 blur-[120px]"></div>
            <div class="absolute -right-1/4 -bottom-1/4 h-full w-full animate-pulse rounded-full bg-fuchsia-600/10 blur-[120px]" style="animation-delay: 2s"></div>
        </div>

        <div class="relative z-10 p-6 max-w-lg w-full text-center">
            <div class="glass-card relative bg-white/5 backdrop-blur-3xl rounded-[2.5rem] p-10 sm:p-14 shadow-2xl">
                <h1 class="text-8xl font-bold bg-gradient-to-br from-violet-400 to-fuchsia-400 bg-clip-text text-transparent opacity-50 mb-4 selection:bg-transparent">500</h1>
                <h2 class="text-2xl font-bold mb-4">Something Broke...</h2>
                <p class="text-white/60 mb-10 leading-relaxed">
                    Servers are behaving unexpectedly. Don't worry, our team is on it. Please try refreshing the page or come back in a few minutes.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button onclick="window.location.reload()" class="px-8 py-4 rounded-2xl bg-gradient-to-r from-violet-600 to-fuchsia-600 font-semibold hover:scale-[1.02] active:scale-[0.98] transition-all shadow-lg shadow-violet-500/20 cursor-pointer">
                        Refresh Page
                    </button>
                    <a href="/" class="px-8 py-4 rounded-2xl bg-white/5 border border-white/10 font-semibold hover:bg-white/10 transition-all">
                        Back to Portal
                    </a>
                </div>
            </div>
        </div>

        <!-- Decorative Glow -->
        <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-violet-500/5 rounded-full blur-[150px] pointer-events-none"></div>
    </div>
</body>
</html>
