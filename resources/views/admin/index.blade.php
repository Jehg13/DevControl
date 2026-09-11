<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>DevControl | Dashboard</title>

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

            {{-- Logo --}}
            <div class="mb-8 flex items-center gap-2.5 px-1">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-[#d61f2c] font-mono2 text-sm font-bold text-white">&gt;_</span>
                <span class="font-display text-lg font-bold tracking-tight text-white">
                    DEV<span class="text-[#d61f2c]">CONTROL</span>
                </span>
            </div>

            {{-- Perfil --}}
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                <img src="{{ $usuario['foto'] ?? asset('storage/images/jesus-guerra.jpg') }}"
                     alt="Foto de {{ $usuario['nombre'] ?? 'Jesús Guerra' }}"
                     class="h-10 w-10 shrink-0 rounded-full border border-white/10 object-cover">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-white">{{ $usuario['nombre'] ?? 'Jesús Guerra' }}</p>
                    <p class="truncate font-mono2 text-[11px] text-gray-500">{{ $usuario['rol'] ?? 'Desarrollador' }}</p>
                </div>
            </div>

            {{-- Navegación --}}
            @include('admin.partials.menu-principal')
            <nav class="space-y-1">

                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 rounded-lg bg-[#d61f2c] px-3.5 py-2.5 text-sm font-bold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                        <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                        <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('asistente.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <span class="flex h-5 w-5 items-center justify-center rounded border border-[#d61f2c]/60 font-mono2 text-[10px] text-[#ff5b5b]">&gt;_</span>
                    Asistente IA
                </a>

                @include('admin.partials.navegacion-modulos')

                <a                 href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    Proyectos
                </a>

                <a                 href="{{ route('proyectos.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="4" width="16" height="16" rx="2"/>
                        <path d="M8 12l2.5 2.5L16 9"/>
                    </svg>
                    Tareas
                </a>

                <a                 href="{{ route('bugs.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="8" y="7" width="8" height="11" rx="4"/>
                        <path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 014 0v2M6 6l2 1.5M18 6l-2 1.5"/>
                    </svg>
                    Bugs
                </a>

                <a                 href="{{ route('actualizaciones') }}"
                   class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="12" r="2.3"/>
                        <circle cx="18" cy="6" r="2.3"/>
                        <circle cx="18" cy="18" r="2.3"/>
                        <path d="M8.1 11l7.8-4M8.1 13l7.8 4"/>
                    </svg>
                    Actualizaciones
                </a>

                <a                 href="{{ route('archivos') }}"
                   class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7l4-3h5l4 3h3v13a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/>
                        <path d="M9 12h6"/>
                    </svg>
                    Archivos
                </a>

            </nav>

            <form method="POST" action="#" class="mt-6">
                @csrf
                <button type="submit"
                        class="flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 4H5a1 1 0 00-1 1v14a1 1 0 001 1h4"/>
                        <path d="M16 17l5-5-5-5"/>
                        <path d="M21 12H9"/>
                    </svg>
                    Cerrar sesión
                </button>
            </form>

        </aside>


        {{-- ================================================================ --}}
        {{-- CONTENIDO PRINCIPAL --}}
        {{-- ================================================================ --}}

        <main class="flex-1 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">

            {{-- ---------- ENCABEZADO ---------- --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="toggleSidebar()"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 text-white transition hover:bg-white/5 lg:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </button>

                    <div>
                        <h1 class="font-display text-2xl font-bold tracking-tight text-white sm:text-3xl">Bienvenido, {{ $usuario['nombre'] ?? 'Jesús Guerra' }}</h1>
                        <p class="mt-1 font-mono2 text-xs text-gray-500 sm:text-sm">
                            ~ / <span class="font-semibold text-gray-300">dashboard</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 text-white transition hover:bg-white/5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 01-3.46 0"/>
                        </svg>
                    </button>

                    <a href="#"
                       class="flex items-center gap-2 rounded-xl bg-[#d61f2c] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#b8161f]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Nuevo proyecto
                    </a>
                </div>
            </div>


            {{-- ---------- RESUMEN GENERAL ---------- --}}
            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">

                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Proyectos</p>
                    <p class="font-display mt-1 text-3xl font-bold text-white">{{ $resumen['proyectos'] ?? 4 }}</p>
                    <p class="text-xs text-gray-500">Activos</p>
                </div>

                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Pendientes</p>
                    <p class="font-display mt-1 text-3xl font-bold text-white">{{ $resumen['pendientes'] ?? 18 }}</p>
                    <p class="text-xs text-gray-500">Tareas por hacer</p>
                </div>

                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">En proceso</p>
                    <p class="font-display mt-1 text-3xl font-bold text-white">{{ $resumen['en_progreso'] ?? 7 }}</p>
                    <p class="text-xs text-gray-500">Tareas activas</p>
                </div>

                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Completadas</p>
                    <p class="font-display mt-1 text-3xl font-bold text-white">{{ $resumen['completados'] ?? 32 }}</p>
                    <p class="text-xs text-gray-500">Este mes</p>
                </div>

                <div class="rounded-xl border border-[#d61f2c]/30 bg-[#d61f2c]/5 p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-[#ff5b5b]/80">Bugs</p>
                    <p class="font-display mt-1 text-3xl font-bold text-[#ff5b5b]">{{ $resumen['bugs'] ?? 3 }}</p>
                    <p class="text-xs text-[#ff5b5b]/70">Abiertos</p>
                </div>

            </div>


            {{-- ---------- GRID PRINCIPAL ---------- --}}
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">

                {{-- ---------- MIS PROYECTOS ---------- --}}
                <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5 lg:col-span-7">

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                </svg>
                            </div>
                            <h2 class="font-display text-lg font-bold text-white">Mis proyectos</h2>
                        </div>
                        <a href="#" class="text-sm font-bold text-[#ff5b5b] hover:text-[#ff7a7a]">Ver todos</a>
                    </div>

                    @php
                        $proyectos = [
                            ['nombre' => 'TicketPro', 'progreso' => 60, 'pendientes' => 8, 'actualizaciones' => 9, 'completados' => 10, 'bugs' => 2],
                            ['nombre' => 'Sistema de Solicitudes', 'progreso' => 35, 'pendientes' => 12, 'actualizaciones' => 4, 'completados' => 6, 'bugs' => 1],
                            ['nombre' => 'Dashboard TI', 'progreso' => 15, 'pendientes' => 9, 'actualizaciones' => 2, 'completados' => 1, 'bugs' => 0],
                        ];
                    @endphp

                    <div class="mt-4 space-y-3">
                        @foreach ($proyectos as $proyecto)
                            <a href="#"
                               class="block rounded-xl border border-white/10 bg-black/40 p-4 transition hover:border-white/20">

                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-white">{{ $proyecto['nombre'] }}</p>
                                    <span class="font-mono2 text-xs font-bold text-[#ff5b5b]">{{ $proyecto['progreso'] }}%</span>
                                </div>

                                <div class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                    <div class="h-full rounded-full bg-gradient-to-r from-[#d61f2c] to-[#ff5b5b]" style="width: {{ $proyecto['progreso'] }}%"></div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 font-mono2 text-[11px] text-gray-500">
                                    <span>{{ $proyecto['pendientes'] }} pendientes</span>
                                    <span>{{ $proyecto['actualizaciones'] }} actualizaciones</span>
                                    <span>{{ $proyecto['completados'] }} completados</span>
                                    <span class="{{ $proyecto['bugs'] > 0 ? 'text-[#ff5b5b]' : '' }}">{{ $proyecto['bugs'] }} bugs</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>


                {{-- ---------- TAREAS PRÓXIMAS A VENCER ---------- --}}
                <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5 lg:col-span-5">

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9"/>
                                    <path d="M12 7v5l3.5 2"/>
                                </svg>
                            </div>
                            <h2 class="font-display text-lg font-bold text-white">Tareas próximas</h2>
                        </div>
                        <a href="#" class="text-sm font-bold text-[#ff5b5b] hover:text-[#ff7a7a]">Ver todas</a>
                    </div>

                    @php
                        $tareasProximas = [
                            ['titulo' => 'Confirmar los roles de usuario', 'proyecto' => 'TicketPro', 'vence' => 'Hoy', 'prioridad' => 'alta'],
                            ['titulo' => 'Revisar permisos con TI', 'proyecto' => 'Dashboard TI', 'vence' => 'Mañana', 'prioridad' => 'alta'],
                            ['titulo' => 'Definir recuperación de contraseña', 'proyecto' => 'TicketPro', 'vence' => 'En 2 días', 'prioridad' => 'media'],
                            ['titulo' => 'Documentar endpoints de la API', 'proyecto' => 'Sistema de Solicitudes', 'vence' => 'En 3 días', 'prioridad' => 'media'],
                        ];
                    @endphp

                    <div class="mt-4 divide-y divide-white/5">
                        @foreach ($tareasProximas as $tarea)
                            <div class="flex items-start gap-3 py-3 first:pt-0">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $tarea['prioridad'] === 'alta' ? 'bg-[#ff5b5b]' : 'bg-gray-500' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-white">{{ $tarea['titulo'] }}</p>
                                    <p class="mt-0.5 font-mono2 text-[11px] text-gray-500">{{ $tarea['proyecto'] }}</p>
                                </div>
                                <span class="shrink-0 font-mono2 text-[11px] text-gray-500">{{ $tarea['vence'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>


                {{-- ---------- ACTIVIDAD RECIENTE ---------- --}}
                <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5 lg:col-span-7">

                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4v6h6M20 20v-6h-6"/>
                                <path d="M5 12a7 7 0 0112.8-4M19 12a7 7 0 01-12.8 4"/>
                            </svg>
                        </div>
                        <h2 class="font-display text-lg font-bold text-white">Actividad reciente</h2>
                    </div>

                    @php
                        $actividad = [
                            ['hora' => '19:30', 'texto' => 'Creaste el proyecto TicketPro', 'tipo' => 'default'],
                            ['hora' => '19:45', 'texto' => 'Creaste la tarea "Sistema de avisos"', 'tipo' => 'default'],
                            ['hora' => '20:10', 'texto' => 'Completaste "Autenticación"', 'tipo' => 'completado'],
                            ['hora' => '20:25', 'texto' => 'Registraste BUG-004', 'tipo' => 'bug'],
                            ['hora' => '20:40', 'texto' => 'Subiste TicketPro-v0.6.zip', 'tipo' => 'default'],
                        ];
                    @endphp

                    <div class="mt-4">
                        @foreach ($actividad as $item)
                            <div class="relative flex gap-3 pb-4 last:pb-0">
                                @if (!$loop->last)
                                    <span class="absolute left-[5px] top-3 h-full w-px bg-white/10"></span>
                                @endif
                                <span class="relative mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full
                                    {{ $item['tipo'] === 'bug' ? 'bg-[#ff5b5b]' : ($item['tipo'] === 'completado' ? 'bg-white' : 'bg-gray-600') }}"></span>
                                <div class="flex flex-1 items-baseline justify-between gap-3 text-sm">
                                    <p class="text-gray-300">{{ $item['texto'] }}</p>
                                    <span class="shrink-0 font-mono2 text-[11px] text-gray-600">{{ $item['hora'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>


                {{-- ---------- BUGS ABIERTOS ---------- --}}
                <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5 lg:col-span-5">

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="8" y="7" width="8" height="11" rx="4"/>
                                    <path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 014 0v2"/>
                                </svg>
                            </div>
                            <h2 class="font-display text-lg font-bold text-white">Bugs abiertos</h2>
                        </div>
                        <a href="#" class="text-sm font-bold text-[#ff5b5b] hover:text-[#ff7a7a]">Ver todos</a>
                    </div>

                    @php
                        $bugs = [
                            ['folio' => 'BUG-004', 'titulo' => 'Error al subir archivos grandes', 'proyecto' => 'TicketPro', 'estado' => 'Investigando'],
                            ['folio' => 'BUG-003', 'titulo' => 'Ícono no se muestra en móvil', 'proyecto' => 'Dashboard TI', 'estado' => 'En desarrollo'],
                            ['folio' => 'BUG-002', 'titulo' => 'Timeout al iniciar sesión', 'proyecto' => 'Sistema de Solicitudes', 'estado' => 'En pruebas'],
                        ];
                    @endphp

                    <div class="mt-4 space-y-2.5">
                        @foreach ($bugs as $bug)
                            <div class="rounded-lg border border-white/10 bg-black/40 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono2 text-[11px] font-bold text-[#ff5b5b]">{{ $bug['folio'] }}</span>
                                    <span class="rounded-full bg-white/5 px-2 py-0.5 font-mono2 text-[10px] font-semibold text-gray-400">{{ $bug['estado'] }}</span>
                                </div>
                                <p class="mt-1.5 text-sm font-semibold text-white">{{ $bug['titulo'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $bug['proyecto'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>


                {{-- ---------- ARCHIVOS RECIENTES ---------- --}}
                <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5 lg:col-span-7">

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 7l4-3h5l4 3h3v13a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/>
                                    <path d="M9 12h6"/>
                                </svg>
                            </div>
                            <h2 class="font-display text-lg font-bold text-white">Archivos recientes</h2>
                        </div>
                        <a href="#" class="text-sm font-bold text-[#ff5b5b] hover:text-[#ff7a7a]">Ver todos</a>
                    </div>

                    @php
                        $archivos = [
                            ['nombre' => 'tiketpro.sql', 'carpeta' => 'Bases de datos', 'proyecto' => 'TicketPro', 'peso' => '2.4 MB', 'fecha' => 'hace 1 día'],
                            ['nombre' => 'TicketPro-v1.zip', 'carpeta' => 'Backups', 'proyecto' => 'TicketPro', 'peso' => '18 MB', 'fecha' => 'hace 3 días'],
                            ['nombre' => 'manual.pdf', 'carpeta' => 'Documentación', 'proyecto' => 'Sistema de Solicitudes', 'peso' => '1.1 MB', 'fecha' => 'hace 5 días'],
                            ['nombre' => 'login.png', 'carpeta' => 'Diseños', 'proyecto' => 'DevControl', 'peso' => '340 KB', 'fecha' => 'hace 6 días'],
                        ];
                    @endphp

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[480px] text-left text-sm">
                            <thead>
                                <tr class="font-mono2 text-[11px] uppercase tracking-wide text-gray-600">
                                    <th class="pb-3 font-semibold">Archivo</th>
                                    <th class="pb-3 font-semibold">Proyecto</th>
                                    <th class="pb-3 font-semibold">Peso</th>
                                    <th class="pb-3 font-semibold">Subido</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach ($archivos as $archivo)
                                    <tr>
                                        <td class="py-2.5">
                                            <p class="font-semibold text-white">{{ $archivo['nombre'] }}</p>
                                            <p class="font-mono2 text-[11px] text-gray-600">{{ $archivo['carpeta'] }}</p>
                                        </td>
                                        <td class="py-2.5 text-gray-400">{{ $archivo['proyecto'] }}</td>
                                        <td class="py-2.5 font-mono2 text-xs text-gray-500">{{ $archivo['peso'] }}</td>
                                        <td class="py-2.5 font-mono2 text-xs text-gray-500">{{ $archivo['fecha'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>


                {{-- ---------- PLANIFICACIÓN: ESTA SEMANA ---------- --}}
                <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5 lg:col-span-5">

                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="17" rx="2"/>
                                <path d="M3 9h18M8 3v3M16 3v3"/>
                            </svg>
                        </div>
                        <h2 class="font-display text-lg font-bold text-white">Esta semana</h2>
                    </div>

                    @php
                        $semana = [
                            ['dia' => 'Lun', 'tarea' => 'Crear avisos'],
                            ['dia' => 'Mar', 'tarea' => 'Sistema de solicitudes'],
                            ['dia' => 'Mié', 'tarea' => 'Dashboard TI'],
                            ['dia' => 'Jue', 'tarea' => 'Pruebas'],
                            ['dia' => 'Vie', 'tarea' => 'Correcciones'],
                        ];
                    @endphp

                    <div class="mt-4 space-y-2">
                        @foreach ($semana as $dia)
                            <div class="flex items-center gap-3 rounded-lg border border-white/10 bg-black/40 px-3 py-2.5">
                                <span class="flex h-7 w-9 shrink-0 items-center justify-center rounded-md bg-white/5 font-mono2 text-[11px] font-bold text-gray-300">{{ $dia['dia'] }}</span>
                                <span class="text-sm text-gray-300">{{ $dia['tarea'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

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