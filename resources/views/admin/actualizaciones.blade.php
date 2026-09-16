<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>DevControl | Actualizaciones</title>

    <style>
        .font-display { font-family: 'Space Grotesk', sans-serif; }
        .font-mono2 { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen bg-black">

    <div class="flex min-h-screen">

        {{-- ================================================================ --}}
        {{-- OVERLAY (solo móvil) --}}
        {{-- ================================================================ --}}

        <div id="sidebarOverlay" onclick="toggleSidebar()"
             class="fixed inset-0 z-40 hidden bg-black/70 lg:hidden"></div>

        {{-- ================================================================ --}}
        {{-- SIDEBAR --}}
        {{-- ================================================================ --}}

        <aside id="sidebar"
               class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overflow-y-auto border-r border-white/10 bg-[#0a0a0a] px-5 py-8 shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:w-64 lg:shrink-0 lg:translate-x-0 lg:shadow-none">

            <div class="mb-8 flex items-center gap-2.5 px-1">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-[#d61f2c] font-mono2 text-sm font-bold text-white">&gt;_</span>
                <span class="font-display text-lg font-bold tracking-tight text-white">
                    DEV<span class="text-[#d61f2c]">CONTROL</span>
                </span>
            </div>

            <div class="mb-6 flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                <img src="{{ $usuario['foto'] ?? asset('favicon.ico') }}"
                     alt="Foto de {{ $usuario['nombre'] ?? 'Jesús Guerra' }}"
                     class="h-10 w-10 shrink-0 rounded-full border border-white/10 object-cover">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-white">{{ $usuario['nombre'] ?? 'Jesús Guerra' }}</p>
                    <p class="truncate font-mono2 text-[11px] text-gray-500">{{ $usuario['rol'] ?? 'Desarrollador' }}</p>
                </div>
            </div>

            @include('admin.partials.menu-principal')
            <nav class="space-y-1">

                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                        <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                        <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('proyectos.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    Proyectos
                </a>

                <a href="{{ route('tareas.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="4" width="16" height="16" rx="2"/>
                        <path d="M8 12l2.5 2.5L16 9"/>
                    </svg>
                    Tareas
                </a>

                <a href="{{ route('bugs.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="8" y="7" width="8" height="11" rx="4"/>
                        <path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 014 0v2M6 6l2 1.5M18 6l-2 1.5"/>
                    </svg>
                    Bugs
                </a>

                <a href="{{ route('actualizaciones') }}" class="flex items-center gap-3 rounded-lg bg-[#d61f2c] px-3.5 py-2.5 text-sm font-bold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="12" r="2.3"/>
                        <circle cx="18" cy="6" r="2.3"/>
                        <circle cx="18" cy="18" r="2.3"/>
                        <path d="M8.1 11l7.8-4M8.1 13l7.8 4"/>
                    </svg>
                    Actualizaciones
                </a>

                <a href="{{ route('archivos') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7l4-3h5l4 3h3v13a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/>
                        <path d="M9 12h6"/>
                    </svg>
                    Archivos
                </a>

                @include('admin.partials.navegacion-modulos')
            </nav>

            <button type="button"
                    class="mt-6 flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 4H5a1 1 0 00-1 1v14a1 1 0 001 1h4"/>
                    <path d="M16 17l5-5-5-5"/>
                    <path d="M21 12H9"/>
                </svg>
                Cerrar sesión
            </button>

        </aside>


        {{-- ================================================================ --}}
        {{-- CONTENIDO PRINCIPAL --}}
        {{-- ================================================================ --}}

        <main class="flex-1 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">

            {{-- ---------- ENCABEZADO ---------- --}}
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleSidebar()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-white transition hover:bg-white/5 lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                <div>
                    <p class="font-mono2 text-xs text-gray-500">~ / <span class="font-semibold text-gray-300">actualizaciones</span></p>
                    <h1 class="font-display mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Actualizaciones</h1>
                </div>
            </div>


            {{-- ================================================================ --}}
            {{-- COMPOSITOR DE NUEVA ACTUALIZACIÓN --}}
            {{-- ================================================================ --}}
            @if(session('success'))
                <div class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-6 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('actualizaciones.store') }}" class="mt-6 rounded-2xl border border-white/10 bg-[#0f0f11] p-5 sm:p-6">
                @csrf

                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="6" cy="12" r="2.3"/><circle cx="18" cy="6" r="2.3"/><circle cx="18" cy="18" r="2.3"/>
                            <path d="M8.1 11l7.8-4M8.1 13l7.8 4"/>
                        </svg>
                    </div>
                    <h2 class="font-display text-lg font-bold text-white">Nueva actualización</h2>
                </div>

                <div class="mt-5 space-y-4">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Proyecto</label>
                            <select name="proyecto_id" required class="w-full rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-sm font-semibold text-white outline-none focus:border-[#d61f2c]/50">
                                <option value="">Selecciona un proyecto</option>
                                @foreach($proyectos as $proyecto)
                                    <option value="{{ $proyecto->id }}" @selected(old('proyecto_id') == $proyecto->id)>{{ $proyecto->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Commit (autogenerado)</label>
                            <div class="flex w-full items-center gap-2 rounded-xl border border-white/10 bg-black/40 px-4 py-3">
                                <input type="text" name="commit" maxlength="40" placeholder="a82f31c" class="w-full bg-transparent font-mono2 text-sm text-gray-400 outline-none placeholder:text-gray-700">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Título</label>
                        <input type="text" name="titulo" value="{{ old('titulo') }}" required placeholder="✨ Implementado sistema de autenticación"
                               class="w-full rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-sm text-white placeholder-gray-600 outline-none transition focus:border-[#d61f2c]/50">
                    </div>

                    <div>
                        <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Detalles <span class="normal-case text-gray-600">(uno por línea)</span></label>
                        <textarea name="detalles" rows="4" required placeholder="Login&#10;Logout&#10;Middleware&#10;Roles"
                                  class="w-full resize-none rounded-xl border border-white/10 bg-black/40 px-4 py-3 font-mono2 text-sm text-white placeholder-gray-600 outline-none transition focus:border-[#d61f2c]/50">{{ old('detalles') }}</textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-white/10 pt-4">
                        <button type="reset" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5">
                            Cancelar
                        </button>
                        <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#d61f2c] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#b8161f]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/><path d="M12 3v6M12 15v6"/>
                            </svg>
                            Publicar actualización
                        </button>
                    </form>

                </div>
            </div>


            {{-- ================================================================ --}}
            {{-- BARRA DE FILTROS DEL FEED --}}
            {{-- ================================================================ --}}
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Historial de desarrollo</p>

                <div class="flex flex-wrap items-center gap-2">
                    <select name="proyecto_id" form="filtroActualizaciones" class="rounded-lg border border-white/10 bg-[#0f0f11] px-3 py-2 font-mono2 text-xs font-semibold text-gray-300 outline-none">
                        <option value="">Proyecto: Todos</option>
                        @foreach($proyectos as $proyecto)
                            <option value="{{ $proyecto->id }}" @selected(($filtros['proyecto_id'] ?? '') == $proyecto->id)>{{ $proyecto->nombre }}</option>
                        @endforeach
                    </select>
                    <div class="relative w-full sm:w-56">
                        <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
                        </svg>
                        <input type="text" name="buscar" form="filtroActualizaciones" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Buscar por commit o título..."
                               class="w-full rounded-lg border border-white/10 bg-[#0f0f11] py-2 pl-8 pr-3 font-mono2 text-xs text-white placeholder-gray-600 outline-none transition focus:border-[#d61f2c]/50">
                    </div>
                    <form id="filtroActualizaciones" method="GET" action="{{ route('actualizaciones') }}">
                        <button class="rounded-lg border border-white/10 px-3 py-2 font-mono2 text-xs text-gray-400 hover:bg-white/5">Filtrar</button>
                    </form>
                </div>
            </div>


            {{-- ================================================================ --}}
            {{-- FEED DE ACTUALIZACIONES --}}
            {{-- ================================================================ --}}

            <div class="mt-4 space-y-8">
                @forelse ($actualizaciones as $actualizacion)
                    <div>
                        <p class="font-mono2 text-xs font-semibold text-gray-500">{{ $actualizacion->created_at->translatedFormat('d \d\e F, Y') }}</p>

                        <div class="relative mt-3 space-y-4 border-l border-white/10 pl-6">
                                <div class="relative">
                                    <span class="absolute -left-[27px] top-5 h-2.5 w-2.5 rounded-full border-2 border-black bg-[#d61f2c]"></span>

                                    <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4 sm:p-5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="rounded-full bg-white/5 px-2.5 py-1 font-mono2 text-[11px] font-semibold text-gray-300">{{ $actualizacion->proyecto->nombre }}</span>
                                            <span class="font-mono2 text-[11px] text-gray-600">{{ $actualizacion->created_at->format('H:i') }}</span>
                                        </div>

                                        <div class="mt-2.5 flex items-start justify-between gap-4"><p class="font-semibold text-white">{{ $actualizacion->titulo }}</p><div class="flex gap-2 text-xs"><a href="{{ route('actualizaciones.edit', $actualizacion) }}" class="text-gray-300 hover:text-white">Editar</a><form method="POST" action="{{ route('actualizaciones.destroy', $actualizacion) }}" onsubmit="return confirm('¿Eliminar esta actualización?')">@csrf @method('DELETE')<button class="text-red-300">Eliminar</button></form></div></div>

                                        <ul class="mt-2 space-y-0.5 text-sm text-gray-400">
                                            @foreach (preg_split('/\r\n|\r|\n/', $actualizacion->detalles) as $linea)
                                                @if(trim($linea) !== '')<li>• {{ $linea }}</li>@endif
                                            @endforeach
                                        </ul>

                                        <p class="mt-3 flex items-center gap-1.5 font-mono2 text-xs text-gray-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="6" cy="12" r="2.3"/><circle cx="18" cy="6" r="2.3"/><circle cx="18" cy="18" r="2.3"/><path d="M8.1 11l7.8-4M8.1 13l7.8 4"/>
                                            </svg>
                                            Commit: {{ $actualizacion->commit ?: 'No especificado' }}
                                        </p>
                                    </div>
                                </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-gray-500">Todavía no hay actualizaciones registradas.</div>
                @endforelse
            </div>

        </main>

    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>

@include('admin.partials.asistente-flotante')
</body>
</html>