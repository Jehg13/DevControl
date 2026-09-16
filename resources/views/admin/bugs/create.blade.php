<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">@vite(['resources/css/app.css', 'resources/js/app.js'])<title>DevControl | Crear bug</title></head>
<body class="min-h-screen bg-black text-white">
<main class="mx-auto max-w-3xl px-5 py-8 sm:px-8">
    <div class="mb-6 flex items-center justify-between gap-4"><div><p class="font-mono2 text-[11px] uppercase tracking-widest text-[#ff5b5b]">DevControl / Bugs</p><h1 class="mt-2 font-display text-3xl font-bold">Nuevo bug</h1></div><a href="{{ route('bugs.index') }}" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300">Volver</a></div>
    @include('admin.bugs._form', ['action' => route('bugs.store'), 'method' => 'POST', 'bug' => null])
</main>
</body>
</html>
