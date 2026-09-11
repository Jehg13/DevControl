<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Incidentes</title>
</head>
<body class="min-h-screen bg-black text-white">
    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 z-40 hidden bg-black/70 lg:hidden"></div>
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overflow-y-auto border-r border-white/10 bg-[#0a0a0a] px-5 py-8 shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:w-64 lg:shrink-0 lg:translate-x-0 lg:shadow-none">
            <div class="mb-8 flex items-center gap-2.5 px-1">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-[#d61f2c] font-mono2 text-sm font-bold">&gt;_</span>
                <span class="font-display text-lg font-bold tracking-tight">DEV<span class="text-[#d61f2c]">CONTROL</span></span>
            </div>
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-[#d61f2c]/20 font-bold text-[#ff5b5b]">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                <div class="min-w-0"><p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p><p class="truncate font-mono2 text-[11px] text-gray-500">{{ auth()->user()->rol }}</p></div>
            </div>
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
                <div>
                    <p class="font-mono2 text-xs text-gray-500">~ / incidentes</p>
                    <h1 class="mt-2 font-display text-3xl font-bold">Incidentes</h1>
                    <p class="mt-2 text-sm text-gray-500">Agrupa errores relacionados y sigue su avance hasta resolverlos.</p>
                </div>
                <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 font-mono2 text-xs text-emerald-300">Conectado</span>
            </header>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([['Abiertos', 'Abierto', 'Requieren seguimiento', 'text-red-300'], ['En investigación', 'En investigación', 'Analizando causa raíz', 'text-yellow-300'], ['En resolución', 'En resolución', 'Con acciones activas', 'text-cyan-300'], ['Resueltos', 'Resuelto', 'Historial del periodo', 'text-emerald-300']] as $metric)
                    <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5"><p class="font-mono2 text-[10px] uppercase tracking-widest text-gray-500">{{ $metric[0] }}</p><p class="mt-4 font-display text-3xl font-bold {{ $metric[3] }}">{{ $incidentes->where('estado', $metric[1])->count() }}</p><p class="mt-2 text-xs text-gray-600">{{ $metric[2] }}</p></article>
                @endforeach
            </div>

            <section class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <div class="flex items-center justify-between"><div><h2 class="font-display text-lg font-bold">Incidentes activos</h2><p class="mt-1 text-xs text-gray-500">Errores agrupados por causa o impacto.</p></div><span class="font-mono2 text-xs text-gray-600">3 casos</span></div>
                    <div class="mt-5 space-y-3">
                        @forelse($incidentes as $incident)
                            <div class="rounded-xl border border-white/5 bg-black/30 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div><div class="flex flex-wrap items-center gap-2"><span class="font-mono2 text-[10px] text-gray-600">INC-{{ str_pad($incident->id, 3, '0', STR_PAD_LEFT) }}</span><span class="rounded-full bg-red-400/10 px-2 py-1 font-mono2 text-[9px] text-red-300">{{ $incident->prioridad }}</span></div><h3 class="mt-2 font-semibold text-white">{{ $incident->titulo }}</h3><p class="mt-1 text-xs text-gray-600">{{ $incident->proyecto->nombre }} · {{ $incident->descripcion ?: 'Sin descripción' }}</p></div>
                                    <span class="shrink-0 rounded-full border border-white/10 px-2.5 py-1 text-[10px] text-gray-400">{{ $incident->estado }}</span>
                                </div>
                                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/5"><div class="h-full rounded-full bg-gradient-to-r from-[#d61f2c] to-[#ff5b5b]" style="width: {{ $incident->estado === 'Resuelto' ? '100' : ($incident->estado === 'En resolución' ? '72' : ($incident->estado === 'En investigación' ? '38' : '15')) }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No hay incidencias registradas.</p>
                        @endforelse
                    </div>
                </article>
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">Flujo de resolución</h2>
                    <p class="mt-1 text-xs text-gray-500">Seguimiento visual de cada incidente.</p>
                    <div class="mt-6 space-y-5">
                        @foreach([['Detectado', 'El error fue identificado', 'bg-red-400'], ['Agrupado', 'Se relacionó con otros errores', 'bg-yellow-400'], ['Analizado', 'Se busca la causa raíz', 'bg-cyan-400'], ['Resuelto', 'Se verificó la solución', 'bg-emerald-400']] as $step)
                            <div class="flex gap-3"><span class="mt-1 h-3 w-3 shrink-0 rounded-full {{ $step[2] }}"></span><div><p class="text-sm font-semibold text-gray-300">{{ $step[0] }}</p><p class="mt-1 text-xs text-gray-600">{{ $step[1] }}</p></div></div>
                        @endforeach
                    </div>
                </article>
            </section>

            <div class="mt-6 rounded-2xl border border-dashed border-white/10 bg-white/[0.03] p-5 text-sm text-gray-400">Nexus puede registrar incidencias desde el asistente solicitando proyecto, título, prioridad, estado y descripción.</div>
        </main>
    </div>
    @include('admin.partials.asistente-flotante')
    <script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}</script>
</body>
</html>
