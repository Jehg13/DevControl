<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Actividad</title>
</head>
<body class="min-h-screen bg-black text-white">
    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 z-40 hidden bg-black/70 lg:hidden"></div>
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overflow-y-auto border-r border-white/10 bg-[#0a0a0a] px-5 py-8 shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:w-64 lg:shrink-0 lg:translate-x-0 lg:shadow-none">
            <div class="mb-8 flex items-center gap-2.5 px-1"><span class="flex h-8 w-8 items-center justify-center rounded-md bg-[#d61f2c] font-mono2 text-sm font-bold">&gt;_</span><span class="font-display text-lg font-bold tracking-tight">DEV<span class="text-[#d61f2c]">CONTROL</span></span></div>
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3"><div class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-[#d61f2c]/20 font-bold text-[#ff5b5b]">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div><div class="min-w-0"><p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p><p class="truncate font-mono2 text-[11px] text-gray-500">{{ auth()->user()->rol }}</p></div></div>
            @include('admin.partials.menu-principal')
            <nav class="space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span>▦</span>Dashboard</a>
                <a href="{{ route('asistente.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span class="font-mono2 text-[#ff5b5b]">&gt;_</span>Asistente IA</a>
                <a href="{{ route('proyectos.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span>▰</span>Proyectos</a>
                <a href="{{ route('tareas.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span>☑</span>Tareas</a>
                <a href="{{ route('bugs.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span>♧</span>Bugs</a>
                <a href="{{ route('actualizaciones') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span>⌘</span>Actualizaciones</a>
                <a href="{{ route('archivos') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span>▱</span>Archivos</a>
                @include('admin.partials.navegacion-modulos')
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="mt-6">@csrf<button class="flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white"><span>↪</span>Cerrar sesión</button></form>
        </aside>

        <main class="min-w-0 flex-1 px-5 py-6 sm:px-8 lg:px-10">
            <button type="button" onclick="toggleSidebar()" class="mb-5 flex h-10 w-10 items-center justify-center rounded-full border border-white/10 lg:hidden" aria-label="Abrir navegación">☰</button>
            <header class="flex flex-col gap-4 border-b border-white/10 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="font-mono2 text-xs text-gray-500">~ / actividad</p><h1 class="mt-2 font-display text-3xl font-bold">Actividad</h1><p class="mt-2 text-sm text-gray-500">Historial de acciones realizadas por usuarios y por DevControl.</p></div>
                <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 font-mono2 text-xs text-emerald-300">Auditoría activa</span>
            </header>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([['Acciones hoy', $metricas['hoy'], 'Actividad registrada', 'text-cyan-300'], ['Acciones de usuarios', $metricas['usuarios'], 'Participaron hoy', 'text-purple-300'], ['Acciones de DevControl', $metricas['sistema'], 'Procesos automáticos', 'text-[#ff6b6b]'], ['Historial total', $metricas['total'], 'Eventos registrados', 'text-yellow-300']] as $metric)
                    <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5"><p class="font-mono2 text-[10px] uppercase tracking-widest text-gray-500">{{ $metric[0] }}</p><p class="mt-4 font-display text-3xl font-bold {{ $metric[3] }}">{{ $metric[1] }}</p><p class="mt-2 text-xs text-gray-600">{{ $metric[2] }}</p></article>
                @endforeach
            </div>

            <section class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-display text-lg font-bold">Historial reciente</h2><p class="mt-1 text-xs text-gray-500">Acciones de usuarios y eventos automáticos.</p></div><span class="font-mono2 text-xs text-gray-600">Últimas 24 horas</span></div>
                    <div class="mt-5 space-y-4">
                        @forelse($actividades as $actividad)
                            @php($color = $actividad->origen === 'DevControl' ? 'text-[#ff6b6b]' : 'text-cyan-300')
                            <div class="flex gap-3 border-b border-white/5 pb-4 last:border-0 last:pb-0"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/5 font-mono2 text-xs {{ $color }}">{{ strtoupper(substr($actividad->usuario?->name ?? 'D', 0, 1)) }}</div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold text-white">{{ $actividad->usuario?->name ?? 'DevControl' }}</p><span class="rounded-full bg-white/5 px-2 py-0.5 font-mono2 text-[9px] {{ $color }}">{{ $actividad->origen }}</span></div><p class="mt-1 text-sm text-gray-400">{{ $actividad->accion }}: {{ $actividad->descripcion }}</p><p class="mt-1 text-xs text-gray-600">{{ $actividad->proyecto?->nombre ?? 'Sistema' }}</p></div><time class="shrink-0 font-mono2 text-[10px] text-gray-600">{{ $actividad->created_at->diffForHumans() }}</time></div>
                        @empty
                            <p class="text-sm text-gray-500">Todavía no hay actividad registrada.</p>
                        @endforelse
                    </div>
                </article>
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">Actividad por origen</h2><p class="mt-1 text-xs text-gray-500">Distribución visual del historial.</p>
                    <div class="mt-6 space-y-5">
                        @foreach([['Usuarios', '13 acciones', '54%', 'bg-cyan-400'], ['DevControl', '11 acciones', '46%', 'bg-[#d61f2c]']] as $origin)
                            <div><div class="flex justify-between text-sm"><span class="text-gray-300">{{ $origin[0] }}</span><span class="font-mono2 text-xs text-gray-500">{{ $origin[1] }}</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-white/5"><div class="h-full rounded-full {{ $origin[3] }}" style="width: {{ $origin[2] }}"></div></div></div>
                        @endforeach
                    </div>
                    <div class="mt-8 rounded-xl border border-white/5 bg-black/30 p-4"><p class="font-mono2 text-[10px] uppercase tracking-widest text-gray-600">Tipos frecuentes</p><div class="mt-4 flex flex-wrap gap-2">@foreach(['Navegación', 'Bugs', 'Tareas', 'Actualizaciones'] as $type)<span class="rounded-full border border-white/10 px-2.5 py-1 text-[10px] text-gray-400">{{ $type }}</span>@endforeach</div></div>
                </article>
            </section>

            <div class="mt-6">{{ $actividades->links() }}</div>
        </main>
    </div>
    @include('admin.partials.asistente-flotante')
    <script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}</script>
</body>
</html>
