<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>DevControl | Archivos</title>

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
                <img src="{{ $usuario['foto'] ?? asset('storage/images/jesus-guerra.jpg') }}"
                     alt="Foto de {{ $usuario['nombre'] ?? 'Jesús Guerra' }}"
                     class="h-10 w-10 shrink-0 rounded-full border border-white/10 object-cover">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-white">{{ $usuario['nombre'] ?? 'Jesús Guerra' }}</p>
                    <p class="truncate font-mono2 text-[11px] text-gray-500">{{ $usuario['rol'] ?? 'Desarrollador' }}</p>
                </div>
            </div>

            <nav class="space-y-1">

                <a href="#" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                        <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                        <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                    </svg>
                    Dashboard
                </a>

                <a href="#" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    Proyectos
                </a>

                <a href="#" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="4" width="16" height="16" rx="2"/>
                        <path d="M8 12l2.5 2.5L16 9"/>
                    </svg>
                    Tareas
                </a>

                <a href="#" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="8" y="7" width="8" height="11" rx="4"/>
                        <path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 014 0v2M6 6l2 1.5M18 6l-2 1.5"/>
                    </svg>
                    Bugs
                </a>

                <a href="#" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="12" r="2.3"/>
                        <circle cx="18" cy="6" r="2.3"/>
                        <circle cx="18" cy="18" r="2.3"/>
                        <path d="M8.1 11l7.8-4M8.1 13l7.8 4"/>
                    </svg>
                    Actualizaciones
                </a>

                <a href="#" class="flex items-center gap-3 rounded-lg bg-[#d61f2c] px-3.5 py-2.5 text-sm font-bold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7l4-3h5l4 3h3v13a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/>
                        <path d="M9 12h6"/>
                    </svg>
                    Archivos
                </a>

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
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="toggleSidebar()"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-white transition hover:bg-white/5 lg:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </button>

                    <div>
                        <p class="font-mono2 text-xs text-gray-500">~ / <span class="font-semibold text-gray-300">archivos</span></p>
                        <h1 class="font-display mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Archivos</h1>
                    </div>
                </div>

                <button type="button" onclick="toggleComposer()"
                        class="flex items-center gap-2 rounded-xl bg-[#d61f2c] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#b8161f]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4v12M6 12l6 6 6-6"/><path d="M4 20h16"/>
                    </svg>
                    Subir archivo
                </button>
            </div>


            {{-- ================================================================ --}}
            {{-- COMPOSITOR PARA SUBIR ARCHIVO (oculto por defecto) --}}
            {{-- ================================================================ --}}
            <div id="composer" class="mt-6 hidden rounded-2xl border border-white/10 bg-[#0f0f11] p-5 sm:p-6">

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 7l4-3h5l4 3h3v13a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/><path d="M9 12h6"/>
                            </svg>
                        </div>
                        <h2 class="font-display text-lg font-bold text-white">Subir archivo</h2>
                    </div>
                    <button type="button" onclick="toggleComposer()" class="text-gray-500 hover:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <div class="mt-5 space-y-4">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Proyecto</label>
                            <button type="button" class="flex w-full items-center justify-between rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-sm font-semibold text-white transition hover:border-white/20">
                                TicketPro
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                        </div>
                        <div>
                            <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Carpeta</label>
                            <button type="button" class="flex w-full items-center justify-between rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-sm font-semibold text-white transition hover:border-white/20">
                                Backups
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Descripción</label>
                            <input type="text" placeholder="Respaldo antes de la migración de roles"
                                   class="w-full rounded-xl border border-white/10 bg-black/40 px-4 py-3 text-sm text-white placeholder-gray-600 outline-none transition focus:border-[#d61f2c]/50">
                        </div>
                        <div>
                            <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Versión</label>
                            <input type="text" placeholder="v1.1"
                                   class="w-full rounded-xl border border-white/10 bg-black/40 px-4 py-3 font-mono2 text-sm text-white placeholder-gray-600 outline-none transition focus:border-[#d61f2c]/50">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Archivo</label>
                        <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-white/15 bg-black/40 px-4 py-8 text-center transition hover:border-[#d61f2c]/40">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 4v12M6 12l6 6 6-6"/><path d="M4 20h16"/>
                            </svg>
                            <p class="text-sm text-gray-400">Arrastra un archivo aquí o <span class="font-semibold text-[#ff5b5b]">haz clic para subir</span></p>
                            <p class="font-mono2 text-[11px] text-gray-600">SQL, ZIP, PDF, PNG, JPG — máx. 50 MB</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-white/10 pt-4">
                        <button type="button" onclick="toggleComposer()" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5">
                            Cancelar
                        </button>
                        <button type="button" class="flex items-center gap-2 rounded-xl bg-[#d61f2c] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#b8161f]">
                            Subir archivo
                        </button>
                    </div>

                </div>
            </div>


            {{-- ================================================================ --}}
            {{-- RESUMEN ---------- --}}
            {{-- ================================================================ --}}
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Archivos</p>
                    <p class="font-display mt-1 text-2xl font-bold text-white">9</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Espacio usado</p>
                    <p class="font-display mt-1 text-2xl font-bold text-white">38.3 MB</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Backups</p>
                    <p class="font-display mt-1 text-2xl font-bold text-white">3</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-[#0f0f11] p-4">
                    <p class="font-mono2 text-[11px] uppercase tracking-widest text-gray-500">Subido hoy</p>
                    <p class="font-display mt-1 text-2xl font-bold text-[#ff5b5b]">1</p>
                </div>
            </div>


            {{-- ================================================================ --}}
            {{-- FILTROS + TOGGLE DE VISTA ---------- --}}
            {{-- ================================================================ --}}
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
                    </svg>
                    <input type="text" placeholder="Buscar archivo..."
                           class="w-full rounded-xl border border-white/10 bg-[#0f0f11] py-2.5 pl-10 pr-4 text-sm text-white placeholder-gray-500 outline-none transition focus:border-[#d61f2c]/50">
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="flex items-center gap-1.5 rounded-lg border border-white/10 bg-[#0f0f11] px-3 py-2 font-mono2 text-xs font-semibold text-gray-300 transition hover:bg-white/5">
                        Tipo: <span class="text-white">Todos</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                    </button>

                    <div class="flex items-center gap-1 rounded-lg border border-white/10 bg-[#0f0f11] p-1">
                        <button type="button" onclick="mostrarVista('carpetas', this)" data-vista="carpetas"
                                class="vista-btn rounded-md bg-[#d61f2c] px-3 py-1.5 font-mono2 text-xs font-bold text-white">Por carpeta</button>
                        <button type="button" onclick="mostrarVista('lista', this)" data-vista="lista"
                                class="vista-btn rounded-md px-3 py-1.5 font-mono2 text-xs font-bold text-gray-500 hover:text-gray-300">Lista</button>
                    </div>
                </div>
            </div>


            {{-- ================================================================ --}}
            {{-- VISTA POR CARPETA (AGRUPADO POR PROYECTO) --}}
            {{-- ================================================================ --}}
            <div id="vista-carpetas" class="vista-panel mt-4 space-y-3">

                @php
                    $proyectosArchivos = [
                        [
                            'nombre' => 'TicketPro',
                            'carpetas' => [
                                ['nombre' => 'Bases de datos', 'archivos' => [
                                    ['n' => 'tiketpro.sql', 'tipo' => 'SQL', 'v' => 'v1.0', 'p' => '2.4 MB', 'f' => 'hace 1 día'],
                                ]],
                                ['nombre' => 'Backups', 'archivos' => [
                                    ['n' => 'TicketPro-v1.zip', 'tipo' => 'ZIP', 'v' => 'v1.0', 'p' => '18 MB', 'f' => 'hace 3 días'],
                                    ['n' => 'TicketPro-v0.6.zip', 'tipo' => 'ZIP', 'v' => 'v0.6', 'p' => '15 MB', 'f' => 'hace 9 días'],
                                ]],
                                ['nombre' => 'Documentación', 'archivos' => [
                                    ['n' => 'manual.pdf', 'tipo' => 'PDF', 'v' => 'v1', 'p' => '1.1 MB', 'f' => 'hace 5 días'],
                                ]],
                                ['nombre' => 'Diseños', 'archivos' => [
                                    ['n' => 'login.png', 'tipo' => 'PNG', 'v' => '—', 'p' => '340 KB', 'f' => 'hace 6 días'],
                                    ['n' => 'dashboard.png', 'tipo' => 'PNG', 'v' => '—', 'p' => '410 KB', 'f' => 'hace 6 días'],
                                ]],
                            ],
                        ],
                        [
                            'nombre' => 'Sistema de Solicitudes',
                            'carpetas' => [
                                ['nombre' => 'Documentación', 'archivos' => [
                                    ['n' => 'requerimientos.pdf', 'tipo' => 'PDF', 'v' => 'v1', 'p' => '800 KB', 'f' => 'hace 4 días'],
                                ]],
                                ['nombre' => 'Diseños', 'archivos' => [
                                    ['n' => 'mockup-listado.png', 'tipo' => 'PNG', 'v' => '—', 'p' => '290 KB', 'f' => 'hace 4 días'],
                                ]],
                            ],
                        ],
                        [
                            'nombre' => 'Dashboard TI',
                            'carpetas' => [
                                ['nombre' => 'Código', 'archivos' => [
                                    ['n' => 'dashboard-ti.zip', 'tipo' => 'ZIP', 'v' => 'v0.2', 'p' => '5 MB', 'f' => 'hace 2 días'],
                                ]],
                            ],
                        ],
                    ];
                @endphp

                @foreach ($proyectosArchivos as $index => $proyecto)
                    <div class="rounded-2xl border border-white/10 bg-[#0f0f11]">
                        <button type="button" onclick="toggleCarpeta({{ $index }})"
                                class="flex w-full items-center justify-between px-5 py-4 text-left">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                    </svg>
                                </div>
                                <span class="font-display text-base font-bold text-white">{{ $proyecto['nombre'] }}</span>
                            </div>
                            <svg id="carpeta-icon-{{ $index }}" xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-gray-500 transition-transform duration-200 {{ $index === 0 ? 'rotate-90' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 6 15 12 9 18"/>
                            </svg>
                        </button>

                        <div id="carpeta-body-{{ $index }}" class="{{ $index === 0 ? '' : 'hidden' }} space-y-4 border-t border-white/10 px-5 pb-5 pt-4">
                            @foreach ($proyecto['carpetas'] as $carpeta)
                                <div>
                                    <p class="flex items-center gap-1.5 font-mono2 text-[11px] uppercase tracking-widest text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                                        {{ $carpeta['nombre'] }}
                                    </p>
                                    <div class="mt-2 divide-y divide-white/5 rounded-lg border border-white/10">
                                        @foreach ($carpeta['archivos'] as $archivo)
                                            <div class="flex flex-wrap items-center justify-between gap-2 px-3.5 py-2.5">
                                                <div class="flex min-w-0 items-center gap-2.5">
                                                    <span class="rounded bg-white/5 px-1.5 py-0.5 font-mono2 text-[10px] font-bold text-gray-400">{{ $archivo['tipo'] }}</span>
                                                    <span class="truncate text-sm font-medium text-white">{{ $archivo['n'] }}</span>
                                                </div>
                                                <span class="shrink-0 font-mono2 text-xs text-gray-500">{{ $archivo['v'] }} · {{ $archivo['p'] }} · {{ $archivo['f'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

            </div>


            {{-- ================================================================ --}}
            {{-- VISTA LISTA --}}
            {{-- ================================================================ --}}
            <div id="vista-lista" class="vista-panel mt-4 hidden">
                <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">

                    @php
                        $todosArchivos = [
                            ['n' => 'tiketpro.sql', 'c' => 'Bases de datos', 'proy' => 'TicketPro', 'tipo' => 'SQL', 'v' => 'v1.0', 'p' => '2.4 MB', 'f' => 'hace 1 día'],
                            ['n' => 'dashboard-ti.zip', 'c' => 'Código', 'proy' => 'Dashboard TI', 'tipo' => 'ZIP', 'v' => 'v0.2', 'p' => '5 MB', 'f' => 'hace 2 días'],
                            ['n' => 'TicketPro-v1.zip', 'c' => 'Backups', 'proy' => 'TicketPro', 'tipo' => 'ZIP', 'v' => 'v1.0', 'p' => '18 MB', 'f' => 'hace 3 días'],
                            ['n' => 'requerimientos.pdf', 'c' => 'Documentación', 'proy' => 'Sistema de Solicitudes', 'tipo' => 'PDF', 'v' => 'v1', 'p' => '800 KB', 'f' => 'hace 4 días'],
                            ['n' => 'mockup-listado.png', 'c' => 'Diseños', 'proy' => 'Sistema de Solicitudes', 'tipo' => 'PNG', 'v' => '—', 'p' => '290 KB', 'f' => 'hace 4 días'],
                            ['n' => 'manual.pdf', 'c' => 'Documentación', 'proy' => 'TicketPro', 'tipo' => 'PDF', 'v' => 'v1', 'p' => '1.1 MB', 'f' => 'hace 5 días'],
                            ['n' => 'login.png', 'c' => 'Diseños', 'proy' => 'TicketPro', 'tipo' => 'PNG', 'v' => '—', 'p' => '340 KB', 'f' => 'hace 6 días'],
                            ['n' => 'dashboard.png', 'c' => 'Diseños', 'proy' => 'TicketPro', 'tipo' => 'PNG', 'v' => '—', 'p' => '410 KB', 'f' => 'hace 6 días'],
                            ['n' => 'TicketPro-v0.6.zip', 'c' => 'Backups', 'proy' => 'TicketPro', 'tipo' => 'ZIP', 'v' => 'v0.6', 'p' => '15 MB', 'f' => 'hace 9 días'],
                        ];
                    @endphp

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr class="font-mono2 text-[11px] uppercase tracking-wide text-gray-600">
                                    <th class="pb-3 font-semibold">Archivo</th>
                                    <th class="pb-3 font-semibold">Proyecto</th>
                                    <th class="pb-3 font-semibold">Carpeta</th>
                                    <th class="pb-3 font-semibold">Versión</th>
                                    <th class="pb-3 font-semibold">Peso</th>
                                    <th class="pb-3 font-semibold">Subido</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach ($todosArchivos as $archivo)
                                    <tr>
                                        <td class="py-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="rounded bg-white/5 px-1.5 py-0.5 font-mono2 text-[10px] font-bold text-gray-400">{{ $archivo['tipo'] }}</span>
                                                <span class="font-medium text-white">{{ $archivo['n'] }}</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 text-gray-400">{{ $archivo['proy'] }}</td>
                                        <td class="py-2.5 text-gray-400">{{ $archivo['c'] }}</td>
                                        <td class="py-2.5 font-mono2 text-xs text-gray-500">{{ $archivo['v'] }}</td>
                                        <td class="py-2.5 font-mono2 text-xs text-gray-500">{{ $archivo['p'] }}</td>
                                        <td class="py-2.5 font-mono2 text-xs text-gray-500">{{ $archivo['f'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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

        function toggleComposer() {
            document.getElementById('composer').classList.toggle('hidden');
        }

        function toggleCarpeta(index) {
            const body = document.getElementById('carpeta-body-' + index);
            const icon = document.getElementById('carpeta-icon-' + index);
            body.classList.toggle('hidden');
            icon.classList.toggle('rotate-90');
        }

        function mostrarVista(id, btn) {
            document.querySelectorAll('.vista-panel').forEach(panel => panel.classList.add('hidden'));
            document.getElementById('vista-' + id).classList.remove('hidden');

            document.querySelectorAll('.vista-btn').forEach(b => {
                b.classList.remove('bg-[#d61f2c]', 'text-white');
                b.classList.add('text-gray-500');
            });
            btn.classList.add('bg-[#d61f2c]', 'text-white');
            btn.classList.remove('text-gray-500');
        }
    </script>

</body>
</html>