<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | {{ $titulo }}</title>
</head>
<body class="min-h-screen bg-black text-white">
    <main class="mx-auto flex min-h-screen max-w-4xl items-center justify-center px-6">
        <section class="w-full rounded-2xl border border-white/10 bg-[#0b0b0d] p-8 text-center shadow-2xl">
            <p class="font-mono2 text-xs uppercase tracking-[0.25em] text-[#ff5b5b]">DevControl / módulo</p>
            <h1 class="mt-4 font-display text-3xl font-bold">{{ $titulo }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-gray-500">{{ $descripcion }}</p>
            <span class="mt-6 inline-flex rounded-full border border-yellow-400/20 bg-yellow-400/10 px-4 py-2 font-mono2 text-xs text-yellow-300">En construcción</span>
            <div class="mt-8">
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-[#d61f2c] px-5 py-3 text-sm font-bold text-white hover:bg-[#ef2937]">Volver al dashboard</a>
            </div>
        </section>
    </main>
</body>
</html>
