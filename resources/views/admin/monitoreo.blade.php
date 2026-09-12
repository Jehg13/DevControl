<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Monitoreo</title>
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
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p>
                    <p class="truncate font-mono2 text-[11px] text-gray-500">{{ auth()->user()->rol }}</p>
                </div>
            </div>
            @include('admin.partials.menu-principal')
            <nav class="space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span>▦</span>Dashboard</a>
                <a href="{{ route('asistente.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span class="font-mono2 text-[#ff5b5b]">&gt;_</span>Asistente IA</a>
                <a href="{{ route('proyectos.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span>▰</span>Proyectos</a>
                <a href="{{ route('tareas.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span>☑</span>Tareas</a>
                <a href="{{ route('bugs.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span>♧</span>Bugs</a>
                <a href="{{ route('actualizaciones') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span>⌘</span>Actualizaciones</a>
                <a href="{{ route('archivos') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span>▱</span>Archivos</a>
                @include('admin.partials.navegacion-modulos')
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white"><span>↪</span>Cerrar sesión</button>
            </form>
        </aside>

        <main class="min-w-0 flex-1 px-5 py-6 sm:px-8 lg:px-10">
            <button type="button" onclick="toggleSidebar()" class="mb-5 flex h-10 w-10 items-center justify-center rounded-full border border-white/10 lg:hidden" aria-label="Abrir navegación">☰</button>
            <header class="flex flex-col gap-4 border-b border-white/10 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="font-mono2 text-xs text-gray-500">~ / monitoreo</p>
                    <h1 class="mt-2 font-display text-3xl font-bold">Monitoreo</h1>
                    <p class="mt-2 text-sm text-gray-500">Comprueba la disponibilidad de tus aplicaciones y APIs desde DevControl.</p>
                </div>
                <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 font-mono2 text-xs text-emerald-300">Monitoreo HTTP activo</span>
            </header>

            @if(session('success'))
                <div class="mt-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mt-5 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mt-5 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm text-red-200">{{ $errors->first() }}</div>
            @endif

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['Monitores', count($monitores), 'URLs configuradas', 'text-cyan-300'],
                    ['Operativos', collect($monitores)->where('estado', 'operativo')->count(), 'Responden correctamente', 'text-emerald-300'],
                    ['Con fallos', collect($monitores)->where('estado', 'fallo')->count(), 'Requieren revisión', 'text-red-300'],
                    ['Pendientes', collect($monitores)->where('estado', 'pendiente')->count(), 'Aún sin comprobar', 'text-yellow-300'],
                ] as $metric)
                    <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                        <p class="font-mono2 text-[10px] uppercase tracking-widest text-gray-500">{{ $metric[0] }}</p>
                        <p class="mt-4 font-display text-3xl font-bold {{ $metric[3] }}">{{ $metric[1] }}</p>
                        <p class="mt-2 text-xs text-gray-600">{{ $metric[2] }}</p>
                    </article>
                @endforeach
            </div>

            <section class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.5fr]">
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">Agregar monitor HTTP</h2>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500">Registra la URL pública de una aplicación o API. No requiere todavía acceso al VPS.</p>
                    <form method="POST" action="{{ route('monitoreo.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <input name="nombre" required maxlength="100" placeholder="Ej. API de producción" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                        <select name="proyecto_id" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                            <option value="">Sin proyecto asociado</option>
                            @foreach($proyectos as $proyecto)
                                <option value="{{ $proyecto->id }}">{{ $proyecto->nombre }}</option>
                            @endforeach
                        </select>
                        <input type="url" name="url" required placeholder="https://tu-dominio.com/api/health" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                        <button class="w-full rounded-lg bg-[#d61f2c] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#ef3340]">Agregar monitor</button>
                    </form>
                </article>
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div><h2 class="font-display text-lg font-bold">Monitores configurados</h2><p class="mt-1 text-xs text-gray-500">Ejecuta una comprobación manual mientras conectamos el VPS.</p></div>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse($monitores as $monitor)
                            @php
                                $estadoColor = ['operativo' => 'bg-emerald-400', 'fallo' => 'bg-red-400', 'pendiente' => 'bg-gray-500'][$monitor['estado']] ?? 'bg-gray-500';
                            @endphp
                            <div class="rounded-xl border border-white/5 bg-black/30 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-200">{{ $monitor['nombre'] }}</p><p class="truncate font-mono2 text-[10px] text-gray-600">{{ $monitor['url'] }}</p><p class="mt-1 text-[10px] text-[#ff5b5b]">{{ optional($proyectos->firstWhere('id', $monitor['proyecto_id'] ?? null))->nombre ?? 'Sin proyecto asociado' }}</p></div>
                                    <span class="flex items-center gap-2 font-mono2 text-[10px] text-gray-400"><span class="h-2 w-2 rounded-full {{ $estadoColor }}"></span>{{ ucfirst($monitor['estado']) }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-[10px] text-gray-500">
                                    @if($monitor['codigo']) <span>HTTP {{ $monitor['codigo'] }}</span> · <span>{{ $monitor['latencia_ms'] }} ms</span> · @endif
                                    <span>{{ $monitor['ultima_comprobacion'] ? \Carbon\Carbon::parse($monitor['ultima_comprobacion'])->diffForHumans() : 'Sin comprobar' }}</span>
                                    <form method="POST" action="{{ route('monitoreo.check', $monitor['id']) }}" class="ml-auto">@csrf<button class="rounded-md border border-white/10 px-2 py-1 text-gray-300 hover:bg-white/10">Comprobar</button></form>
                                    <form method="POST" action="{{ route('monitoreo.destroy', $monitor['id']) }}">@csrf @method('DELETE')<button class="rounded-md border border-red-400/20 px-2 py-1 text-red-300 hover:bg-red-400/10">Eliminar</button></form>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-gray-500">Todavía no hay monitores configurados.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <div class="flex items-center justify-between">
                        <div><h2 class="font-display text-lg font-bold">Siguiente etapa</h2><p class="mt-1 text-xs text-gray-500">La conexión SSH permitirá revisar recursos y servicios del VPS.</p></div>
                        <span class="font-mono2 text-xs text-gray-600">VPS KVM</span>
                    </div>
                    <div class="mt-8 flex h-48 items-center justify-center rounded-xl border border-dashed border-white/10 bg-black/20 px-6 text-center">
                        <p class="max-w-sm text-sm leading-relaxed text-gray-500">Aquí se mostrarán las métricas históricas de latencia y disponibilidad cuando haya monitores con comprobaciones registradas.</p>
                    </div>
                    <div class="mt-3 flex justify-between font-mono2 text-[10px] text-gray-600"><span>Histórico</span><span>Próximamente</span></div>
                </article>
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">Resumen de conexión</h2>
                    <div class="mt-5 rounded-xl border border-dashed border-white/10 bg-black/30 p-5 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/5 text-xl text-gray-500">⌁</div>
                        <p class="mt-4 text-sm font-semibold text-gray-300">Aún no hay fuentes conectadas</p>
                        <p class="mt-2 text-xs leading-relaxed text-gray-600">Cuando conectes un repositorio o entorno, aquí aparecerá su estado general.</p>
                    </div>
                </article>
            </section>

            <div class="mt-6 rounded-2xl border border-dashed border-yellow-400/20 bg-yellow-400/5 p-5 text-sm text-yellow-200/80">
                El monitoreo HTTP ya está disponible. El monitoreo profundo del VPS (CPU, RAM, disco, procesos y logs) se conectará cuando configuremos el acceso SSH del cliente.
            </div>
        </main>
    </div>
    @include('admin.partials.asistente-flotante')
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>
