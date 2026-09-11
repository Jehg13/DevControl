<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Crear cuenta</title>
    <style>
        @keyframes caret-blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
        .caret { animation: caret-blink 1s step-end infinite; }
        .bg-grid {
            background-image: linear-gradient(rgba(255,255,255,0.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.045) 1px, transparent 1px);
            background-size: 34px 34px;
        }
    </style>
</head>
<body class="min-h-screen bg-black">
    <div class="flex min-h-screen">
        <div class="relative hidden w-full max-w-xl flex-col justify-between overflow-hidden bg-[#0a0a0a] px-10 py-10 md:flex lg:max-w-2xl lg:px-14 lg:py-14">
            <div class="bg-grid pointer-events-none absolute inset-0 opacity-60"></div>
            <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(circle at 20% 10%, rgba(214,31,44,0.16), transparent 55%);"></div>

            <div class="relative flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-[#d61f2c] font-['JetBrains_Mono'] text-sm font-bold text-white">&gt;_</span>
                <span class="font-['Space_Grotesk'] text-lg font-bold tracking-tight text-white">DEV<span class="text-[#d61f2c]">CONTROL</span></span>
                <span class="ml-1 rounded-full border border-white/10 px-2 py-0.5 font-['JetBrains_Mono'] text-[10px] font-medium text-gray-500">v1.0</span>
            </div>

            <div class="relative mt-10 space-y-4">
                <div class="rounded-xl border border-white/10 bg-[#141416] p-5 font-['JetBrains_Mono'] text-sm shadow-[0_0_40px_rgba(0,0,0,0.4)]">
                    <p class="text-gray-500">$ devcontrol init --usuario</p>
                    <div class="mt-4 space-y-2 text-gray-400">
                        <div class="flex items-center justify-between"><span>Proyectos</span><span class="font-semibold text-white">Organizados</span></div>
                        <div class="flex items-center justify-between"><span>Tareas</span><span class="font-semibold text-white">Controladas</span></div>
                        <div class="flex items-center justify-between"><span>DevControl</span><span class="font-semibold text-green-400">Listo</span></div>
                    </div>
                    <p class="mt-4 text-gray-600">_<span class="caret">|</span></p>
                </div>
                <div class="rounded-xl border-l-2 border-[#d61f2c] bg-[#141416] p-5">
                    <p class="font-['JetBrains_Mono'] text-[11px] uppercase tracking-widest text-gray-500">Tu centro de control</p>
                    <p class="mt-2 text-sm font-semibold text-white">Todo tu desarrollo, en un solo lugar.</p>
                    <p class="mt-1 text-sm text-gray-400">Proyectos · tareas · bugs · actualizaciones</p>
                </div>
            </div>

            <div class="relative mt-10">
                <div class="flex items-center gap-2 font-['JetBrains_Mono'] text-xs text-gray-500">
                    <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span></span>
                    Sistema en línea
                </div>
                <p class="mt-3 max-w-xs text-sm text-gray-500">Un solo lugar para llevar el control de tus proyectos.</p>
            </div>
        </div>

        <div class="flex w-full flex-1 items-center justify-center bg-white px-6 py-12 sm:px-10">
            <div class="w-full max-w-sm">
                <div class="mb-10 flex items-center justify-center gap-3 md:hidden">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-[#d61f2c] font-['JetBrains_Mono'] text-sm font-bold text-white">&gt;_</span>
                    <span class="font-['Space_Grotesk'] text-lg font-bold tracking-tight text-[#111113]">DEV<span class="text-[#d61f2c]">CONTROL</span></span>
                </div>

                <h1 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-[#111113]">Crea tu cuenta</h1>
                <p class="mt-2 text-sm text-gray-500">Comienza a organizar y entender tus proyectos.</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-[#d61f2c]/30 bg-[#fef2f2] px-4 py-3 text-sm text-[#b8161f]">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-semibold text-[#111113]">Nombre</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Tu nombre" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 transition focus:border-[#d61f2c] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#d61f2c]/10">
                    </div>
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-[#111113]">Correo electrónico</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="tucorreo@devcontrol.com" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 transition focus:border-[#d61f2c] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#d61f2c]/10">
                    </div>
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-[#111113]">Contraseña</label>
                        <input id="password" type="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 transition focus:border-[#d61f2c] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#d61f2c]/10">
                    </div>
                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-[#111113]">Confirmar contraseña</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" placeholder="Repite tu contraseña" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 transition focus:border-[#d61f2c] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#d61f2c]/10">
                    </div>
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#d61f2c] py-3 text-sm font-bold text-white transition hover:bg-[#b8161f] focus:outline-none focus:ring-4 focus:ring-[#d61f2c]/20">
                        Crear cuenta
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <p class="mt-8 text-center text-sm text-gray-500">¿Ya tienes una cuenta?
                    <a href="{{ route('login') }}" class="font-semibold text-[#d61f2c] hover:underline">Inicia sesión</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
