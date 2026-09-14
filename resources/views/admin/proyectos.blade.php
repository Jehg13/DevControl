<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"
          rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>
        DevControl | {{ ($proyecto ?? null)?->nombre ?? 'Proyectos' }}
    </title>

    <style>
        .font-display {
            font-family: 'Space Grotesk', sans-serif;
        }

        .font-mono2 {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>

<body class="min-h-screen bg-black text-white">

@php
    /*
    |--------------------------------------------------------------------------
    | VARIABLES SEGURAS
    |--------------------------------------------------------------------------
    | Evitamos errores de "Undefined variable" si el controlador
    | no manda alguna variable.
    */

    $proyectoActual = $proyecto ?? null;

    $proyectosLista = $proyectos ?? collect();

    $usuarioActual = $usuario ?? [];

    $estadisticasActuales = $estadisticas ?? [];

    $actividadesLista = $actividades ?? collect();

    $tareasLista = $tareas ?? collect();

    $bugsLista = $bugs ?? collect();

    $actualizacionesLista = $actualizaciones ?? collect();

    $archivosLista = $archivos ?? collect();

    $notasLista = $notas ?? collect();

    $seccionesLista = $proyectoActual?->secciones ?? collect();

    $estadoProyecto =
        $proyectoActual?->estado ?? 'Activo';

    $progresoProyecto = min(
        100,
        max(
            0,
            (int) ($proyectoActual?->progreso ?? 0)
        )
    );

    $estadoClases = [

        'Activo' =>
            'bg-white/5 text-gray-300 border border-white/10',

        'Pausado' =>
            'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20',

        'Completado' =>
            'bg-white text-black',

        'Cancelado' =>
            'bg-[#d61f2c]/10 text-[#ff5b5b] border border-[#d61f2c]/20',

    ];

    $tabs = [
        'resumen' => 'Resumen',
        'tareas' => 'Tareas',
        'bugs' => 'Bugs',
        'actualizaciones' => 'Actualizaciones',
        'archivos' => 'Archivos',
        'notas' => 'Notas',
    ];
@endphp


<div class="flex min-h-screen">

    {{-- ============================================================ --}}
    {{-- OVERLAY MOBILE --}}
    {{-- ============================================================ --}}

    <div id="sidebarOverlay"
         onclick="toggleSidebar()"
         class="fixed inset-0 z-40 hidden bg-black/70 lg:hidden">
    </div>


    {{-- ============================================================ --}}
    {{-- SIDEBAR --}}
    {{-- ============================================================ --}}

    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-50 flex w-64
                  -translate-x-full flex-col overflow-y-auto
                  border-r border-white/10 bg-[#0a0a0a]
                  px-5 py-8 shadow-2xl transition-transform duration-300
                  lg:static lg:z-auto lg:w-64 lg:shrink-0
                  lg:translate-x-0 lg:shadow-none">

        {{-- LOGO --}}

        <div class="mb-8 flex items-center gap-2.5 px-1">

            <span class="flex h-8 w-8 items-center justify-center
                         rounded-md bg-[#d61f2c]
                         font-mono2 text-sm font-bold text-white">

                &gt;_

            </span>

            <span class="font-display text-lg font-bold tracking-tight text-white">

                DEV<span class="text-[#d61f2c]">CONTROL</span>

            </span>

        </div>


        {{-- USUARIO --}}

        <div class="mb-6 flex items-center gap-3 rounded-xl
                    border border-white/10 bg-white/5 px-3 py-3">

            <img
                src="{{ $usuarioActual['foto'] ?? asset('favicon.ico') }}"
                alt="Foto de {{ $usuarioActual['nombre'] ?? 'Jesús Guerra' }}"
                class="h-10 w-10 shrink-0 rounded-full
                       border border-white/10 object-cover">

            <div class="min-w-0">

                <p class="truncate text-sm font-bold text-white">

                    {{ $usuarioActual['nombre'] ?? 'Jesús Guerra' }}

                </p>

                <p class="truncate font-mono2 text-[11px] text-gray-500">

                    {{ $usuarioActual['rol'] ?? 'Desarrollador' }}

                </p>

            </div>

        </div>


        {{-- NAVEGACIÓN --}}

        @include('admin.partials.menu-principal')
        <nav class="space-y-1">

            {{-- DASHBOARD --}}

            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 rounded-lg px-3.5 py-2.5
                      text-sm font-semibold text-gray-400
                      transition hover:bg-white/5 hover:text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                    <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                    <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                    <rect x="3" y="16" width="7" height="5" rx="1.5"/>

                </svg>

                Dashboard

            </a>


            {{-- PROYECTOS --}}

            <a href="{{ route('proyectos.index') }}"
               class="flex items-center gap-3 rounded-lg
                      bg-[#d61f2c] px-3.5 py-2.5
                      text-sm font-bold text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>

                </svg>

                Proyectos

            </a>


            {{-- TAREAS --}}

            <a href="{{ route('tareas.index') }}"
               class="flex items-center gap-3 rounded-lg px-3.5 py-2.5
                      text-sm font-semibold text-gray-400
                      transition hover:bg-white/5 hover:text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <rect x="4" y="4" width="16" height="16" rx="2"/>
                    <path d="M8 12l2.5 2.5L16 9"/>

                </svg>

                Tareas

            </a>


            {{-- BUGS --}}

            <a href="{{ route('bugs.index') }}"
               class="flex items-center gap-3 rounded-lg px-3.5 py-2.5
                      text-sm font-semibold text-gray-400
                      transition hover:bg-white/5 hover:text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <rect x="8" y="7" width="8" height="11" rx="4"/>
                    <path d="M8 10H4M16 10h4M8 15H4M16 15h4"/>
                    <path d="M10 7V5a2 2 0 014 0v2"/>
                    <path d="M6 6l2 1.5M18 6l-2 1.5"/>

                </svg>

                Bugs

            </a>


            {{-- ACTUALIZACIONES --}}

            <a href="{{ route('actualizaciones') }}"
               class="flex items-center gap-3 rounded-lg px-3.5 py-2.5
                      text-sm font-semibold text-gray-400
                      transition hover:bg-white/5 hover:text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <circle cx="6" cy="12" r="2.3"/>
                    <circle cx="18" cy="6" r="2.3"/>
                    <circle cx="18" cy="18" r="2.3"/>
                    <path d="M8.1 11l7.8-4M8.1 13l7.8 4"/>

                </svg>

                Actualizaciones

            </a>


            {{-- ARCHIVOS --}}

            <a href="{{ route('archivos') }}"
               class="flex items-center gap-3 rounded-lg px-3.5 py-2.5
                      text-sm font-semibold text-gray-400
                      transition hover:bg-white/5 hover:text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M4 7l4-3h5l4 3h3v13a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/>
                    <path d="M9 12h6"/>

                </svg>

                Archivos

            </a>

            @include('admin.partials.navegacion-modulos')
        </nav>


        {{-- LOGOUT --}}

        <form action="{{ route('logout') }}"
              method="POST"
              class="mt-6">

            @csrf

            <button type="submit"
                    class="flex w-full items-center gap-3
                           rounded-lg px-3.5 py-2.5 text-sm font-semibold
                           text-gray-400 transition hover:bg-white/5
                           hover:text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M9 4H5a1 1 0 00-1 1v14a1 1 0 001 1h4"/>
                    <path d="M16 17l5-5-5-5"/>
                    <path d="M21 12H9"/>

                </svg>

                Cerrar sesión

            </button>

        </form>

    </aside>


    {{-- ============================================================ --}}
    {{-- MAIN --}}
    {{-- ============================================================ --}}

    <main class="flex-1 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">

        {{-- HEADER --}}

        <div class="flex items-center gap-3">

            <button type="button"
                    onclick="toggleSidebar()"
                    class="flex h-10 w-10 shrink-0 items-center justify-center
                           rounded-full border border-white/10 text-white
                           transition hover:bg-white/5 lg:hidden">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-5 w-5"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M4 7h16M4 12h16M4 17h16"/>

                </svg>

            </button>


            <span class="font-mono2 text-xs text-gray-500">

                ~ / proyectos /

                <span class="font-semibold text-gray-300">

                    {{ \Illuminate\Support\Str::slug($proyectoActual?->nombre ?? 'proyectos') }}

                </span>

            </span>

        </div>


        {{-- MENSAJE SUCCESS --}}

        @if(session('success'))

            <div class="mt-4 rounded-xl border border-emerald-500/20
                        bg-emerald-500/10 px-4 py-3
                        text-sm font-semibold text-emerald-400">

                {{ session('success') }}

            </div>

        @endif


        {{-- ERRORES --}}

        @if($errors->any())

            <div class="mt-4 rounded-xl border border-[#d61f2c]/30
                        bg-[#d61f2c]/10 px-4 py-3">

                <p class="font-semibold text-[#ff5b5b]">

                    No se pudo completar la operación:

                </p>

                <ul class="mt-2 list-disc pl-5 text-sm text-gray-300">

                    @foreach($errors->all() as $error)

                        <li>{{ $error }}</li>

                    @endforeach

                </ul>

            </div>

        @endif


        {{-- ============================================================ --}}
        {{-- LISTA DE PROYECTOS --}}
        {{-- ============================================================ --}}

        <div class="mt-6">

            <div class="mb-4 flex items-center justify-between">

                <div>

                    <h1 class="font-display text-2xl font-bold text-white">

                        Proyectos

                    </h1>

                    <p class="mt-1 text-sm text-gray-500">

                        Selecciona un proyecto para ver sus detalles.

                    </p>

                </div>


                <div class="flex items-center gap-3">
                    <span class="font-mono2 text-xs text-gray-600">
                        {{ method_exists($proyectosLista, 'total')
                            ? $proyectosLista->total()
                            : $proyectosLista->count()
                        }}
                        proyecto(s)
                    </span>

                    <button type="button"
                            onclick="openProjectModal('crear')"
                            class="flex items-center gap-2 rounded-xl bg-[#d61f2c]
                                   px-4 py-2.5 text-sm font-bold text-white
                                   transition hover:bg-[#b8161f]">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Crear proyecto
                    </button>
                    <button type="button"
                            onclick="openProjectModal('vincular')"
                            class="flex items-center gap-2 rounded-xl border border-purple-500/30 bg-purple-500/10 px-4 py-2.5 text-sm font-bold text-purple-300 transition hover:bg-purple-500/20">
                        Vincular o importar
                    </button>
                </div>

            </div>


            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                @forelse($proyectosLista as $item)

                    <a href="{{ route('proyectos.show', $item->id) }}"
                       class="group rounded-2xl border p-5 transition
                       {{ $proyectoActual && $proyectoActual->id == $item->id
                            ? 'border-[#d61f2c]/50 bg-[#d61f2c]/10'
                            : 'border-white/10 bg-[#0f0f11] hover:border-white/20'
                       }}">

                        <div class="flex items-start justify-between gap-4">

                            <div class="min-w-0">

                                <div class="flex items-center gap-2">

                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full
                                        {{ $item->estado === 'Activo'
                                            ? 'bg-emerald-500'
                                            : ($item->estado === 'Pausado'
                                                ? 'bg-yellow-500'
                                                : ($item->estado === 'Cancelado'
                                                    ? 'bg-[#d61f2c]'
                                                    : 'bg-gray-500'))
                                        }}">
                                    </span>

                                    <h2 class="truncate font-display text-lg
                                               font-bold text-white">

                                        {{ $item->nombre }}

                                    </h2>

                                </div>


                                <p class="mt-2 line-clamp-2 text-sm text-gray-500">

                                    {{ $item->descripcion ?: 'Sin descripción.' }}

                                </p>

                            </div>


                            <span class="shrink-0 rounded-full border
                                         border-white/10 bg-white/5
                                         px-2.5 py-1 font-mono2 text-[10px]
                                         font-semibold text-gray-400">

                                {{ $item->estado }}

                            </span>

                        </div>


                        <div class="mt-5">

                            <div class="mb-2 flex items-center justify-between">

                                <span class="font-mono2 text-[10px]
                                             uppercase tracking-widest
                                             text-gray-600">

                                    Progreso

                                </span>


                                <span class="font-mono2 text-xs font-bold
                                             text-[#ff5b5b]">

                                    {{ min(100, max(0, (int) ($item->progreso ?? 0))) }}%

                                </span>

                            </div>


                            <div class="h-2 w-full overflow-hidden rounded-full
                                        bg-white/10">

                                <div class="h-full rounded-full
                                            bg-gradient-to-r
                                            from-[#d61f2c] to-[#ff5b5b]"
                                     style="width: {{ min(100, max(0, (int) ($item->progreso ?? 0))) }}%;">
                                </div>

                            </div>

                        </div>


                        <div class="mt-4 flex items-center justify-between">

                            <span class="font-mono2 text-[10px] text-gray-600">

                                {{ $item->fecha_inicio
                                    ? \Carbon\Carbon::parse($item->fecha_inicio)->format('d M Y')
                                    : 'Sin fecha'
                                }}

                            </span>


                            <span class="text-xs font-bold text-gray-500
                                         transition group-hover:text-[#ff5b5b]">

                                Ver proyecto →

                            </span>

                        </div>

                    </a>

                @empty

                    <div class="md:col-span-2 rounded-2xl border
                                border-white/10 bg-[#0f0f11] p-8 text-center">

                        <p class="text-sm text-gray-500">

                            No hay proyectos registrados.

                        </p>

                    </div>

                @endforelse

            </div>


            {{-- PAGINACIÓN --}}

            @if(method_exists($proyectosLista, 'hasPages') && $proyectosLista->hasPages())

                <div class="mt-5 flex items-center justify-between">

                    @if($proyectosLista->onFirstPage())

                        <span class="rounded-lg border border-white/10
                                     px-3 py-2 font-mono2 text-xs
                                     text-gray-700">

                            ← Anterior

                        </span>

                    @else

                        <a href="{{ $proyectosLista->previousPageUrl() }}"
                           class="rounded-lg border border-white/10
                                  px-3 py-2 font-mono2 text-xs
                                  text-gray-400 transition hover:bg-white/5
                                  hover:text-white">

                            ← Anterior

                        </a>

                    @endif


                    <span class="font-mono2 text-xs text-gray-600">

                        Página {{ $proyectosLista->currentPage() }}
                        de {{ $proyectosLista->lastPage() }}

                    </span>


                    @if($proyectosLista->hasMorePages())

                        <a href="{{ $proyectosLista->nextPageUrl() }}"
                           class="rounded-lg border border-white/10
                                  px-3 py-2 font-mono2 text-xs
                                  text-gray-400 transition hover:bg-white/5
                                  hover:text-white">

                            Siguiente →

                        </a>

                    @else

                        <span class="rounded-lg border border-white/10
                                     px-3 py-2 font-mono2 text-xs
                                     text-gray-700">

                            Siguiente →

                        </span>

                    @endif

                </div>

            @endif

        </div>


        {{-- ============================================================ --}}
        {{-- PROYECTO SELECCIONADO --}}
        {{-- ============================================================ --}}

        @if($proyectoActual)

            <div class="mt-6 rounded-2xl border border-white/10
                        bg-[#0f0f11] p-5 sm:p-6">

                <div class="flex flex-col gap-5 sm:flex-row
                            sm:items-start sm:justify-between">

                    <div class="min-w-0">

                        <div class="flex flex-wrap items-center gap-3">

                            <h1 class="font-display text-2xl font-bold
                                       tracking-tight text-white sm:text-3xl">

                                {{ $proyectoActual->nombre ?? 'Proyecto sin nombre' }}

                            </h1>


                            <span class="rounded-full px-2.5 py-1
                                         font-mono2 text-[11px] font-semibold
                                         {{ $estadoClases[$estadoProyecto] ?? $estadoClases['Activo'] }}">

                                {{ $estadoProyecto }}

                            </span>

                        </div>


                        <p class="mt-2 max-w-xl text-sm text-gray-500">

                            {{ $proyectoActual->descripcion ?: 'Sin descripción.' }}

                        </p>

                    </div>


                    {{-- BOTONES --}}

                    <div class="flex shrink-0 flex-wrap items-center gap-2">

                        {{-- CREAR --}}

                        <button type="button"
                                onclick="openProjectModal('crear')"
                                class="flex items-center gap-2 rounded-xl
                                       border border-white/10 px-4 py-2.5
                                       text-sm font-semibold text-gray-300
                                       transition hover:bg-white/5">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2.2">

                                <path d="M12 5v14M5 12h14"/>

                            </svg>

                            Crear proyecto

                        </button>
                        <button type="button"
                                onclick="openProjectModal('vincular')"
                                class="flex items-center gap-2 rounded-xl border border-purple-500/30 bg-purple-500/10 px-4 py-2.5 text-sm font-semibold text-purple-300 transition hover:bg-purple-500/20">
                            Vincular desde GitHub
                        </button>


                        {{-- EDITAR --}}

                        <button type="button"
                                onclick="openProjectModal('editar')"
                                class="flex items-center gap-2 rounded-xl
                                       border border-white/10 px-4 py-2.5
                                       text-sm font-semibold text-gray-300
                                       transition hover:bg-white/5">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">

                                <path d="M12 20h9"/>
                                <path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>

                            </svg>

                            Editar

                        </button>


                        {{-- ELIMINAR --}}

                        <form action="{{ route('proyectos.destroy', $proyectoActual->id) }}"
                              method="POST"
                              onsubmit="return confirm('¿Seguro que quieres eliminar este proyecto? Esta acción no se puede deshacer.');">

                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="flex items-center gap-2 rounded-xl
                                           border border-[#d61f2c]/20
                                           bg-[#d61f2c]/5 px-4 py-2.5
                                           text-sm font-semibold text-[#ff5b5b]
                                           transition hover:bg-[#d61f2c]/10">

                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="h-4 w-4"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.8">

                                    <path d="M4 7h16"/>
                                    <path d="M9 7V4h6v3"/>
                                    <path d="M6 7l1 13a2 2 0 002 2h6a2 2 0 002-2L18 7"/>

                                </svg>

                                Eliminar

                            </button>

                        </form>

                    </div>

                </div>


                {{-- INFORMACIÓN SUPERIOR --}}

                <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2
                            border-t border-white/10 pt-4
                            font-mono2 text-xs text-gray-500">

                    <span class="flex items-center gap-1.5">

                        Inicio:

                        <span class="text-gray-300">

                            {{ $proyectoActual->fecha_inicio
                                ? \Carbon\Carbon::parse($proyectoActual->fecha_inicio)->format('d M Y')
                                : 'Sin definir'
                            }}

                        </span>

                    </span>


                    <span class="flex items-center gap-1.5">

                        Fecha objetivo:

                        <span class="text-gray-300">

                            {{ $proyectoActual->fecha_meta
                                ? \Carbon\Carbon::parse($proyectoActual->fecha_meta)->format('d M Y')
                                : 'Sin definir'
                            }}

                        </span>

                    </span>


                    <span class="flex items-center gap-1.5">

                        Responsable:

                        <span class="text-gray-300">

                            {{ $usuarioActual['nombre'] ?? 'Jesús Guerra' }}

                        </span>

                    </span>

                </div>


                {{-- PROGRESO --}}

                <div class="mt-5">

                    <div class="flex items-center justify-between">

                        <p class="font-mono2 text-[11px]
                                  uppercase tracking-widest text-gray-500">

                            Progreso general

                        </p>

                        <span class="font-mono2 text-sm font-bold
                                     text-[#ff5b5b]">

                            {{ $progresoProyecto }}%

                        </span>

                    </div>


                    <div class="mt-2 h-2 w-full overflow-hidden
                                rounded-full bg-white/10">

                        <div class="h-full rounded-full
                                    bg-gradient-to-r
                                    from-[#d61f2c] to-[#ff5b5b]"
                             style="width: {{ $progresoProyecto }}%;">
                        </div>

                    </div>

                </div>


                {{-- ESTADÍSTICAS --}}

                <div class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-4">

                    <div class="rounded-lg border border-white/10
                                bg-black/40 px-3 py-2.5 text-center">

                        <p class="font-display text-xl font-bold text-white">

                            {{ $estadisticasActuales['pendientes'] ?? 0 }}

                        </p>

                        <p class="font-mono2 text-[10px]
                                  uppercase tracking-wide text-gray-500">

                            Pendientes

                        </p>

                    </div>


                    <div class="rounded-lg border border-white/10
                                bg-black/40 px-3 py-2.5 text-center">

                        <p class="font-display text-xl font-bold text-white">

                            {{ $estadisticasActuales['actualizaciones'] ?? 0 }}

                        </p>

                        <p class="font-mono2 text-[10px]
                                  uppercase tracking-wide text-gray-500">

                            Actualiz.

                        </p>

                    </div>


                    <div class="rounded-lg border border-white/10
                                bg-black/40 px-3 py-2.5 text-center">

                        <p class="font-display text-xl font-bold text-white">

                            {{ $estadisticasActuales['completados'] ?? 0 }}

                        </p>

                        <p class="font-mono2 text-[10px]
                                  uppercase tracking-wide text-gray-500">

                            Completados

                        </p>

                    </div>


                    <div class="rounded-lg border border-[#d61f2c]/30
                                bg-[#d61f2c]/5 px-3 py-2.5 text-center">

                        <p class="font-display text-xl font-bold text-[#ff5b5b]">

                            {{ $estadisticasActuales['bugs'] ?? 0 }}

                        </p>

                        <p class="font-mono2 text-[10px]
                                  uppercase tracking-wide text-[#ff5b5b]/70">

                            Bugs

                        </p>

                    </div>

                </div>

            </div>


            {{-- ======================================================== --}}
            {{-- PESTAÑAS --}}
            {{-- ======================================================== --}}

            <div class="mt-6 flex gap-1 overflow-x-auto
                        border-b border-white/10">

                @foreach($tabs as $key => $label)

                    <button
                        type="button"
                        onclick="showTab('{{ $key }}', this)"
                        data-tab="{{ $key }}"
                        class="tab-btn shrink-0 border-b-2
                               px-4 py-3 font-mono2 text-sm font-semibold
                               {{ $key === 'resumen'
                                    ? 'border-[#d61f2c] text-white'
                                    : 'border-transparent text-gray-500 hover:text-gray-300'
                               }}">

                        {{ $label }}

                    </button>

                @endforeach

            </div>


            {{-- ======================================================== --}}
            {{-- RESUMEN --}}
            {{-- ======================================================== --}}

            <div id="tab-resumen"
                 class="tab-panel mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">

                <div class="flex flex-col gap-6 lg:col-span-7">

                    {{-- DESCRIPCIÓN --}}

                    <div class="rounded-2xl border border-white/10
                                bg-[#0f0f11] p-5">

                        <h2 class="font-display text-lg font-bold text-white">

                            Descripción

                        </h2>

                        <p class="mt-3 text-sm leading-relaxed text-gray-400">

                            {{ $proyectoActual->descripcion
                                ?: 'Este proyecto no tiene una descripción registrada.'
                            }}

                        </p>

                    </div>

                    {{-- CONTEXTO --}}

                    <div class="rounded-2xl border border-white/10
                                bg-[#0f0f11] p-5">

                        <div class="flex items-center justify-between gap-4">
                            <h2 class="font-display text-lg font-bold text-white">
                                Contexto del proyecto
                            </h2>

                            @if($proyectoActual->repositorio_url)
                                <a href="{{ $proyectoActual->repositorio_url }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="font-mono2 text-xs font-semibold text-[#ff5b5b] hover:underline">
                                    Ver repositorio
                                </a>
                            @endif
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Qué se hará</p>
                                <p class="mt-1 text-sm leading-relaxed text-gray-400">
                                    {{ $proyectoActual->contexto ?: 'Sin contexto registrado.' }}
                                </p>
                            </div>
                            <div>
                                <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Objetivo</p>
                                <p class="mt-1 text-sm leading-relaxed text-gray-400">
                                    {{ $proyectoActual->objetivo ?: 'Sin objetivo registrado.' }}
                                </p>
                            </div>
                            <div>
                                <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Tecnologías</p>
                                <p class="mt-1 text-sm leading-relaxed text-gray-400">
                                    {{ $proyectoActual->tecnologias ?: 'Sin tecnologías registradas.' }}
                                </p>
                            </div>
                            <div>
                                <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Reglas del proyecto</p>
                                <p class="mt-1 text-sm leading-relaxed text-gray-400">
                                    {{ $proyectoActual->reglas ?: 'Sin reglas registradas.' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-white/10 pt-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-display font-bold text-white">Secciones y funcionalidades</h3>
                                <span class="font-mono2 text-xs text-gray-600">{{ $seccionesLista->count() }} secciones</span>
                            </div>

                            @forelse($seccionesLista as $seccion)
                                <div class="mt-4 rounded-xl border border-white/10 bg-black/20 p-4">
                                    <p class="font-semibold text-white">{{ $seccion->nombre }}</p>
                                    @if($seccion->descripcion)
                                        <p class="mt-1 text-sm text-gray-500">{{ $seccion->descripcion }}</p>
                                    @endif
                                    <div class="mt-3 space-y-2">
                                        @forelse($seccion->funcionalidades as $funcionalidad)
                                            <div class="flex items-start justify-between gap-3 text-sm">
                                                <div>
                                                    <p class="text-gray-300">{{ $funcionalidad->nombre }}</p>
                                                    @if($funcionalidad->descripcion)
                                                        <p class="text-xs text-gray-600">{{ $funcionalidad->descripcion }}</p>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 font-mono2 text-[10px] text-gray-500">{{ $funcionalidad->estado }}</span>
                                            </div>
                                        @empty
                                            <p class="text-xs text-gray-600">Sin funcionalidades registradas.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @empty
                                <p class="mt-3 text-sm text-gray-600">Todavía no hay secciones registradas.</p>
                            @endforelse
                        </div>
                    </div>

                    @php
                        $github = $proyectoActual->integracionGithub;
                    @endphp

                    <div class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="font-display text-lg font-bold text-white">Integración GitHub</h2>
                                <p class="mt-1 text-sm text-gray-500">Estado del repositorio asociado al proyecto.</p>
                            </div>
                            @if($github)
                                <span class="rounded-full border px-2.5 py-1 font-mono2 text-[10px] font-semibold
                                    {{ $github->estado === 'sincronizado'
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400'
                                        : ($github->estado === 'error'
                                            ? 'border-[#d61f2c]/20 bg-[#d61f2c]/10 text-[#ff5b5b]'
                                            : ($github->estado === 'manual'
                                                ? 'border-blue-500/20 bg-blue-500/10 text-blue-400'
                                                : 'border-yellow-500/20 bg-yellow-500/10 text-yellow-400')) }}">
                                    {{ $github->estado === 'sincronizado'
                                        ? 'GitHub conectado'
                                        : ($github->estado === 'manual'
                                            ? 'Configurado manualmente'
                                            : ucfirst($github->estado)) }}
                                </span>
                            @endif
                        </div>

                        @if($github)
                            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Repositorio</p>
                                    <a href="{{ $github->repositorio_url }}" target="_blank" rel="noopener noreferrer"
                                       class="mt-1 block truncate text-gray-300 hover:text-[#ff5b5b]">
                                        {{ $github->repositorio_propietario && $github->repositorio_nombre
                                            ? $github->repositorio_propietario . '/' . $github->repositorio_nombre
                                            : $github->repositorio_url }}
                                    </a>
                                </div>
                                <div>
                                    <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Rama principal</p>
                                    <p class="mt-1 text-gray-300">{{ $github->rama_principal ?: 'Pendiente de sincronización' }}</p>
                                </div>
                                <div>
                                    <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Último commit</p>
                                    <p class="mt-1 truncate text-gray-300" title="{{ $github->ultimo_commit_mensaje }}">
                                        {{ $github->ultimo_commit_sha ? substr($github->ultimo_commit_sha, 0, 7) : 'Pendiente de sincronización' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Última sincronización</p>
                                    <p class="mt-1 text-gray-300">
                                        {{ $github->ultima_sincronizacion?->diffForHumans() ?: 'Nunca' }}
                                    </p>
                                </div>
                            </div>

                            @if($github->ultimo_error)
                                <p class="mt-3 rounded-lg border border-[#d61f2c]/20 bg-[#d61f2c]/5 px-3 py-2 text-xs text-[#ff5b5b]">
                                    {{ $github->ultimo_error }}
                                </p>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('proyectos.github.manual', $proyectoActual) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-xl border border-blue-500/20 bg-blue-500/10 px-4 py-2.5 text-sm font-bold text-blue-400 transition hover:bg-blue-500/20">
                                        Configurar manualmente
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('proyectos.github.sync', $proyectoActual) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-xl bg-[#d61f2c] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#b8161f]">
                                        Sincronizar ahora
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('proyectos.github.analyze', $proyectoActual) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-2.5 text-sm font-bold text-emerald-400 transition hover:bg-emerald-500/20">
                                        Analizar repositorio
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('proyectos.github.import', $proyectoActual) }}">
                                    @csrf
                                    <button type="submit"
                                            onclick="return confirm('Esto reemplazará las secciones importadas y agregará como completados los elementos detectados. ¿Continuar?')"
                                            class="rounded-xl border border-purple-500/20 bg-purple-500/10 px-4 py-2.5 text-sm font-bold text-purple-300 transition hover:bg-purple-500/20">
                                        Importar proyecto completo
                                    </button>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('proyectos.github.commit', $proyectoActual) }}" class="mt-4 grid gap-3 rounded-xl border border-white/10 bg-black/20 p-4">
                                @csrf
                                <p class="text-xs text-gray-500">Crear un commit explícito en la rama configurada. Requiere <code>GITHUB_TOKEN</code> con permiso de contenido.</p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input name="mensaje" required maxlength="500" placeholder="Mensaje del commit" class="rounded-lg border border-white/10 bg-black px-3 py-2 text-sm text-white">
                                    <input name="ruta" required maxlength="500" placeholder="Ruta, por ejemplo README.md" class="rounded-lg border border-white/10 bg-black px-3 py-2 text-sm text-white">
                                </div>
                                <textarea name="contenido" required maxlength="1000000" rows="5" placeholder="Contenido completo del archivo" class="rounded-lg border border-white/10 bg-black px-3 py-2 text-sm text-white"></textarea>
                                <button type="submit" class="w-fit rounded-xl border border-yellow-500/20 bg-yellow-500/10 px-4 py-2.5 text-sm font-bold text-yellow-300 transition hover:bg-yellow-500/20">
                                    Crear commit autorizado
                                </button>
                            </form>
                        @else
                            <p class="mt-4 text-sm text-gray-600">
                                Guarda una URL de GitHub en la configuración del proyecto para preparar la integración.
                            </p>
                        @endif
                    </div>


                    {{-- ACTIVIDAD --}}

                    <div class="rounded-2xl border border-white/10
                                bg-[#0f0f11] p-5">

                        <h2 class="font-display text-lg font-bold text-white">

                            Actividad del proyecto

                        </h2>


                        @if($actividadesLista->count())

                            <div class="mt-4">

                                @foreach($actividadesLista as $actividad)

                                    <div class="relative flex gap-3 pb-4 last:pb-0">

                                        @if(!$loop->last)

                                            <span class="absolute left-[5px]
                                                         top-3 h-full w-px bg-white/10">
                                            </span>

                                        @endif


                                        <span class="relative mt-1.5 h-2.5 w-2.5
                                                     shrink-0 rounded-full
                                                     bg-gray-600">
                                        </span>


                                        <div class="flex flex-1 items-baseline
                                                    justify-between gap-3 text-sm">

                                            <p class="text-gray-300">

                                                {{ $actividad->detalles
                                                    ?? $actividad->detalle
                                                    ?? $actividad->descripcion
                                                    ?? $actividad->texto
                                                    ?? $actividad->titulo
                                                    ?? 'Actividad registrada'
                                                }}

                                            </p>


                                            <span class="shrink-0 font-mono2
                                                         text-[11px] text-gray-600">

                                                {{ $actividad->created_at
                                                    ? $actividad->created_at->format('d/m/Y H:i')
                                                    : ''
                                                }}

                                            </span>

                                        </div>

                                    </div>

                                @endforeach

                            </div>

                        @else

                            <p class="mt-4 text-sm text-gray-600">

                                No hay actividad registrada todavía.

                            </p>

                        @endif

                    </div>

                </div>


                {{-- COLUMNA DERECHA --}}

                <div class="flex flex-col gap-6 lg:col-span-5">

                    {{-- INFORMACIÓN --}}

                    <div class="rounded-2xl border border-white/10
                                bg-[#0f0f11] p-5">

                        <h2 class="font-display text-lg font-bold text-white">

                            Información

                        </h2>


                        <dl class="mt-4 space-y-3 text-sm">

                            <div class="flex items-center justify-between">

                                <dt class="text-gray-500">

                                    Estado

                                </dt>

                                <dd class="font-semibold text-white">

                                    {{ $proyectoActual->estado ?? 'Activo' }}

                                </dd>

                            </div>


                            <div class="flex items-center justify-between">

                                <dt class="text-gray-500">

                                    Fecha de inicio

                                </dt>

                                <dd class="font-semibold text-white">

                                    {{ $proyectoActual->fecha_inicio
                                        ? \Carbon\Carbon::parse($proyectoActual->fecha_inicio)->format('d M Y')
                                        : 'Sin definir'
                                    }}

                                </dd>

                            </div>


                            <div class="flex items-center justify-between">

                                <dt class="text-gray-500">

                                    Fecha objetivo

                                </dt>

                                <dd class="font-semibold text-white">

                                    {{ $proyectoActual->fecha_meta
                                        ? \Carbon\Carbon::parse($proyectoActual->fecha_meta)->format('d M Y')
                                        : 'Sin definir'
                                    }}

                                </dd>

                            </div>


                            <div class="flex items-center justify-between">

                                <dt class="text-gray-500">

                                    Responsable

                                </dt>

                                <dd class="font-semibold text-white">

                                    {{ $usuarioActual['nombre'] ?? 'Jesús Guerra' }}

                                </dd>

                            </div>


                            <div class="flex items-center justify-between gap-4">

                                <dt class="text-gray-500">

                                    Stack

                                </dt>

                                <dd class="font-mono2 text-xs
                                           font-semibold text-gray-300">

                                    {{ $proyectoActual->stack ?? 'No definido' }}

                                </dd>

                            </div>

                        </dl>

                    </div>


                    {{-- BUGS ABIERTOS --}}

                    <div class="rounded-2xl border border-white/10
                                bg-[#0f0f11] p-5">

                        <div class="flex items-center justify-between">

                            <h2 class="font-display text-lg font-bold text-white">

                                Bugs abiertos

                            </h2>


                            <button
                                type="button"
                                onclick="showTab('bugs', document.querySelector('[data-tab=bugs]'))"
                                class="text-xs font-bold text-[#ff5b5b]
                                       hover:text-[#ff7a7a]">

                                Ver todos

                            </button>

                        </div>


                        @if($bugsLista->count())

                            <div class="mt-3 space-y-2.5">

                                @foreach($bugsLista->take(3) as $bug)

                                    <div class="rounded-lg border border-white/10
                                                bg-black/40 p-3">

                                        <span class="font-mono2 text-[11px]
                                                     font-bold text-[#ff5b5b]">

                                            {{ $bug->folio ?? 'BUG-'.$bug->id }}

                                        </span>


                                        <p class="mt-1 text-sm font-semibold text-white">

                                            {{ $bug->titulo
                                                ?? $bug->nombre
                                                ?? 'Bug sin título'
                                            }}

                                        </p>

                                    </div>

                                @endforeach

                            </div>

                        @else

                            <p class="mt-3 text-sm text-gray-600">

                                No hay bugs abiertos.

                            </p>

                        @endif

                    </div>

                </div>

            </div>


            {{-- ======================================================== --}}
            {{-- TAREAS --}}
            {{-- ======================================================== --}}

            <div id="tab-tareas"
                 class="tab-panel mt-6 hidden">

                <div class="rounded-2xl border border-white/10
                            bg-[#0f0f11] p-5">

                    <div class="flex items-center justify-between">

                        <h2 class="font-display text-lg font-bold text-white">

                            Tareas

                        </h2>

                        <button type="button"
                                class="flex items-center gap-1.5
                                       rounded-lg bg-[#d61f2c] px-3.5 py-2
                                       text-xs font-bold text-white">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-3.5 w-3.5"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2.4">

                                <path d="M12 5v14M5 12h14"/>

                            </svg>

                            Nueva tarea

                        </button>

                    </div>


                    @if($tareasLista->count())

                        <div class="mt-4 overflow-x-auto">

                            <table class="w-full min-w-[650px] text-left text-sm">

                                <thead>

                                <tr class="font-mono2 text-[11px]
                                           uppercase tracking-wide text-gray-600">

                                    <th class="pb-3 font-semibold">
                                        Tarea
                                    </th>

                                    <th class="pb-3 font-semibold">
                                        Prioridad
                                    </th>

                                    <th class="pb-3 font-semibold">
                                        Estado
                                    </th>

                                    <th class="pb-3 font-semibold">
                                        Fecha límite
                                    </th>

                                </tr>

                                </thead>


                                <tbody class="divide-y divide-white/5">

                                @foreach($tareasLista as $tarea)

                                    <tr>

                                        <td class="py-3 font-semibold text-white">

                                            {{ $tarea->titulo
                                                ?? $tarea->nombre
                                                ?? 'Sin título'
                                            }}

                                        </td>


                                        <td class="py-3">

                                            <span class="font-mono2 text-xs
                                                {{ ($tarea->prioridad ?? '') === 'Alta'
                                                    ? 'font-bold text-[#ff5b5b]'
                                                    : 'text-gray-500'
                                                }}">

                                                {{ $tarea->prioridad ?? 'Sin definir' }}

                                            </span>

                                        </td>


                                        <td class="py-3">

                                            <span class="inline-block rounded-full
                                                         border border-white/10
                                                         bg-white/5 px-2.5 py-1
                                                         font-mono2 text-[11px]
                                                         font-semibold text-gray-300">

                                                {{ $tarea->estado ?? 'Pendiente' }}

                                            </span>

                                        </td>


                                        <td class="py-3 text-gray-400">

                                            {{ $tarea->fecha_limite
                                                ? \Carbon\Carbon::parse($tarea->fecha_limite)->format('d M Y')
                                                : 'Sin fecha'
                                            }}

                                        </td>

                                    </tr>

                                @endforeach

                                </tbody>

                            </table>

                        </div>

                    @else

                        <div class="mt-4 rounded-xl border border-white/10
                                    bg-black/30 p-6 text-center">

                            <p class="text-sm text-gray-600">

                                No hay tareas registradas para este proyecto.

                            </p>

                        </div>

                    @endif

                </div>

            </div>


            {{-- ======================================================== --}}
            {{-- BUGS --}}
            {{-- ======================================================== --}}

            <div id="tab-bugs"
                 class="tab-panel mt-6 hidden">

                <div class="rounded-2xl border border-white/10
                            bg-[#0f0f11] p-5">

                    <div class="flex items-center justify-between">

                        <h2 class="font-display text-lg font-bold text-white">

                            Bugs

                        </h2>

                        <button type="button"
                                class="flex items-center gap-1.5
                                       rounded-lg bg-[#d61f2c] px-3.5 py-2
                                       text-xs font-bold text-white">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-3.5 w-3.5"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2.4">

                                <path d="M12 5v14M5 12h14"/>

                            </svg>

                            Reportar bug

                        </button>

                    </div>


                    @if($bugsLista->count())

                        <div class="mt-4 overflow-x-auto">

                            <table class="w-full min-w-[650px]
                                          text-left text-sm">

                                <thead>

                                <tr class="font-mono2 text-[11px]
                                           uppercase tracking-wide text-gray-600">

                                    <th class="pb-3 font-semibold">
                                        Folio
                                    </th>

                                    <th class="pb-3 font-semibold">
                                        Título
                                    </th>

                                    <th class="pb-3 font-semibold">
                                        Prioridad
                                    </th>

                                    <th class="pb-3 font-semibold">
                                        Estado
                                    </th>

                                    <th class="pb-3 font-semibold">
                                        Detectado
                                    </th>

                                </tr>

                                </thead>


                                <tbody class="divide-y divide-white/5">

                                @foreach($bugsLista as $bug)

                                    <tr>

                                        <td class="py-3 font-mono2 text-xs
                                                   font-bold text-[#ff5b5b]">

                                            {{ $bug->folio ?? 'BUG-'.$bug->id }}

                                        </td>


                                        <td class="py-3 font-semibold text-white">

                                            {{ $bug->titulo
                                                ?? $bug->nombre
                                                ?? 'Bug sin título'
                                            }}

                                        </td>


                                        <td class="py-3">

                                            <span class="font-mono2 text-xs
                                                {{ ($bug->prioridad ?? '') === 'Alta'
                                                    ? 'font-bold text-[#ff5b5b]'
                                                    : 'text-gray-500'
                                                }}">

                                                {{ $bug->prioridad ?? 'Sin definir' }}

                                            </span>

                                        </td>


                                        <td class="py-3">

                                            <span class="inline-block rounded-full
                                                         border border-white/10
                                                         bg-white/5 px-2.5 py-1
                                                         font-mono2 text-[11px]
                                                         font-semibold text-gray-300">

                                                {{ $bug->estado ?? 'Reportado' }}

                                            </span>

                                        </td>


                                        <td class="py-3 text-gray-400">

                                            {{ $bug->created_at
                                                ? $bug->created_at->format('d M Y')
                                                : 'Sin fecha'
                                            }}

                                        </td>

                                    </tr>

                                @endforeach

                                </tbody>

                            </table>

                        </div>

                    @else

                        <div class="mt-4 rounded-xl border border-white/10
                                    bg-black/30 p-6 text-center">

                            <p class="text-sm text-gray-600">

                                No hay bugs registrados para este proyecto.

                            </p>

                        </div>

                    @endif

                </div>

            </div>


            {{-- ======================================================== --}}
            {{-- ACTUALIZACIONES --}}
            {{-- ======================================================== --}}

            <div id="tab-actualizaciones"
                 class="tab-panel mt-6 hidden">

                <div class="rounded-2xl border border-white/10
                            bg-[#0f0f11] p-5">

                    <div class="flex items-center justify-between">

                        <h2 class="font-display text-lg font-bold text-white">

                            Actualizaciones

                        </h2>

                        <button type="button"
                                class="flex items-center gap-1.5
                                       rounded-lg bg-[#d61f2c] px-3.5 py-2
                                       text-xs font-bold text-white">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-3.5 w-3.5"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2.4">

                                <path d="M12 5v14M5 12h14"/>

                            </svg>

                            Nueva actualización

                        </button>

                    </div>


                    @if($actualizacionesLista->count())

                        <div class="mt-4 space-y-4">

                            @foreach($actualizacionesLista as $actualizacion)

                                <div class="rounded-xl border-l-2
                                            border-[#d61f2c]
                                            bg-black/40 p-4">

                                    <p class="font-mono2 text-[11px] text-gray-500">

                                        {{ $actualizacion->created_at
                                            ? $actualizacion->created_at->format('d/m/Y H:i')
                                            : ''
                                        }}

                                    </p>


                                    <p class="mt-1.5 font-semibold text-white">

                                        {{ $actualizacion->titulo
                                            ?? $actualizacion->nombre
                                            ?? 'Actualización'
                                        }}

                                    </p>


                                    @if(!empty($actualizacion->descripcion))

                                        <p class="mt-2 text-sm text-gray-400">

                                            {{ $actualizacion->descripcion }}

                                        </p>

                                    @endif

                                </div>

                            @endforeach

                        </div>

                    @else

                        <p class="mt-4 text-sm text-gray-600">

                            No hay actualizaciones registradas.

                        </p>

                    @endif

                </div>

            </div>


            {{-- ======================================================== --}}
            {{-- ARCHIVOS --}}
            {{-- ======================================================== --}}

            <div id="tab-archivos"
                 class="tab-panel mt-6 hidden">

                <div class="rounded-2xl border border-white/10
                            bg-[#0f0f11] p-5">

                    <div class="flex items-center justify-between">

                        <h2 class="font-display text-lg font-bold text-white">

                            Archivos

                        </h2>

                        <button type="button"
                                class="flex items-center gap-1.5
                                       rounded-lg bg-[#d61f2c] px-3.5 py-2
                                       text-xs font-bold text-white">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-3.5 w-3.5"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2.2">

                                <path d="M12 4v12M6 12l6 6 6-6"/>
                                <path d="M4 20h16"/>

                            </svg>

                            Subir archivo

                        </button>

                    </div>


                    @if($archivosLista->count())

                        <div class="mt-4 space-y-2">

                            @foreach($archivosLista as $archivo)

                                <div class="flex items-center justify-between
                                            rounded-lg border border-white/10
                                            bg-black/40 px-3.5 py-3">

                                    <div>

                                        <p class="font-medium text-white">

                                            {{ $archivo->nombre
                                                ?? $archivo->archivo
                                                ?? 'Archivo'
                                            }}

                                        </p>


                                        @if(!empty($archivo->descripcion))

                                            <p class="mt-1 text-xs text-gray-600">

                                                {{ $archivo->descripcion }}

                                            </p>

                                        @endif

                                    </div>


                                    <span class="font-mono2 text-xs text-gray-500">

                                        {{ $archivo->created_at
                                            ? $archivo->created_at->format('d/m/Y')
                                            : ''
                                        }}

                                    </span>

                                </div>

                            @endforeach

                        </div>

                    @else

                        <p class="mt-4 text-sm text-gray-600">

                            No hay archivos registrados.

                        </p>

                    @endif

                </div>

            </div>


            {{-- ======================================================== --}}
            {{-- NOTAS --}}
            {{-- ======================================================== --}}

            <div id="tab-notas"
                 class="tab-panel mt-6 hidden">

                <div class="rounded-2xl border border-white/10
                            bg-[#0f0f11] p-5">

                    <div class="flex items-center justify-between">

                        <h2 class="font-display text-lg font-bold text-white">

                            Notas

                        </h2>

                        <button type="button"
                                class="flex items-center gap-1.5
                                       rounded-lg bg-[#d61f2c] px-3.5 py-2
                                       text-xs font-bold text-white">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-3.5 w-3.5"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2.4">

                                <path d="M12 5v14M5 12h14"/>

                            </svg>

                            Nueva nota

                        </button>

                    </div>


                    @if($notasLista->count())

                        <ul class="mt-4 space-y-2.5">

                            @foreach($notasLista as $nota)

                                <li class="flex items-start gap-2.5
                                           rounded-lg border border-white/10
                                           bg-black/40 p-3 text-sm text-gray-300">

                                    <span class="mt-1.5 h-1.5 w-1.5
                                                 shrink-0 rounded-full
                                                 bg-gray-600">
                                    </span>

                                    {{ $nota->contenido
                                        ?? $nota->texto
                                        ?? $nota->descripcion
                                        ?? ''
                                    }}

                                </li>

                            @endforeach

                        </ul>

                    @else

                        <p class="mt-4 text-sm text-gray-600">

                            No hay notas registradas.

                        </p>

                    @endif

                </div>

            </div>

        @endif

    </main>

</div>


{{-- ================================================================ --}}
{{-- MODAL CREAR / EDITAR PROYECTO --}}
{{-- ================================================================ --}}

<div id="proyectoModal"
     class="fixed inset-0 z-[60] hidden items-center justify-center
            bg-black/70 px-4 py-8"
     onclick="closeProjectModalOnOverlay(event)">

    <div class="max-h-full w-full max-w-lg overflow-y-auto
                rounded-2xl border border-white/10
                bg-[#0f0f11] p-6"
         onclick="event.stopPropagation()">


        {{-- HEADER MODAL --}}

        <div class="flex items-center justify-between">

            <h2 id="proyectoModalTitle"
                class="font-display text-lg font-bold text-white">

                Crear proyecto

            </h2>


            <button type="button"
                    onclick="closeProjectModal()"
                    class="text-gray-500 transition hover:text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-5 w-5"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M18 6L6 18M6 6l12 12"/>

                </svg>

            </button>

        </div>


        {{-- FORMULARIO --}}

        <form id="proyectoForm"
              method="POST"
              action="{{ route('proyectos.store') }}"
              class="mt-5 space-y-4">

            @csrf

            <input type="hidden"
                   name="_method"
                   id="formMethod"
                   value="POST">


            {{-- NOMBRE --}}

            <div id="campoNombreProyecto">

                <label for="nombre"
                       class="mb-1.5 block text-sm
                              font-semibold text-gray-300">

                    Nombre del proyecto

                </label>


                <input type="text"
                       name="nombre"
                       id="nombre"
                       required
                       placeholder="Ej. TicketPro"
                       class="w-full rounded-xl border border-white/10
                              bg-black/40 px-3.5 py-2.5 text-sm
                              text-white placeholder-gray-600
                              outline-none transition
                              focus:border-[#d61f2c]">

            </div>


            {{-- DESCRIPCIÓN --}}

            <div id="campoDescripcionProyecto">

                <label for="descripcion"
                       class="mb-1.5 block text-sm
                              font-semibold text-gray-300">

                    Descripción

                </label>


                <textarea
                    name="descripcion"
                    id="descripcion"
                    rows="3"
                    placeholder="Breve descripción del proyecto..."
                    class="w-full resize-none rounded-xl
                           border border-white/10 bg-black/40
                           px-3.5 py-2.5 text-sm text-white
                           placeholder-gray-600 outline-none
                           transition focus:border-[#d61f2c]"></textarea>

            </div>

            <div id="campoContextoProyecto">
                <label for="contexto" class="mb-1.5 block text-sm font-semibold text-gray-300">
                    Contexto del proyecto
                </label>
                <textarea name="contexto" id="contexto" rows="3"
                          placeholder="¿Qué se construirá y qué problema resolverá?"
                          class="w-full resize-none rounded-xl border border-white/10 bg-black/40
                                 px-3.5 py-2.5 text-sm text-white placeholder-gray-600 outline-none
                                 transition focus:border-[#d61f2c]"></textarea>
            </div>

            <div id="campoObjetivoProyecto">
                <label for="objetivo" class="mb-1.5 block text-sm font-semibold text-gray-300">
                    Objetivo principal
                </label>
                <textarea name="objetivo" id="objetivo" rows="2"
                          placeholder="¿Cuál es el resultado esperado?"
                          class="w-full resize-none rounded-xl border border-white/10 bg-black/40
                                 px-3.5 py-2.5 text-sm text-white placeholder-gray-600 outline-none
                                 transition focus:border-[#d61f2c]"></textarea>
            </div>

            <div id="campoDetallesProyecto" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="tecnologias" class="mb-1.5 block text-sm font-semibold text-gray-300">
                        Tecnologías
                    </label>
                    <textarea name="tecnologias" id="tecnologias" rows="2"
                              placeholder="Laravel, PHP, MySQL..."
                              class="w-full resize-none rounded-xl border border-white/10 bg-black/40
                                     px-3.5 py-2.5 text-sm text-white placeholder-gray-600 outline-none
                                     transition focus:border-[#d61f2c]"></textarea>
                </div>
                <div>
                    <label for="reglas" class="mb-1.5 block text-sm font-semibold text-gray-300">
                        Reglas importantes
                    </label>
                    <textarea name="reglas" id="reglas" rows="2"
                              placeholder="Reglas que la IA debe respetar..."
                              class="w-full resize-none rounded-xl border border-white/10 bg-black/40
                                     px-3.5 py-2.5 text-sm text-white placeholder-gray-600 outline-none
                                     transition focus:border-[#d61f2c]"></textarea>
                </div>
            </div>

            <div>
                <label for="repositorio_url" class="mb-1.5 block text-sm font-semibold text-gray-300">
                    Repositorio del proyecto
                </label>
                <input type="url" name="repositorio_url" id="repositorio_url"
                       placeholder="https://github.com/usuario/repositorio"
                       class="w-full rounded-xl border border-white/10 bg-black/40 px-3.5 py-2.5 text-sm
                              text-white placeholder-gray-600 outline-none transition focus:border-[#d61f2c]">
            </div>

            <div id="campoSeccionesProyecto" class="border-t border-white/10 pt-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display font-bold text-white">Secciones y funcionalidades</h3>
                        <p class="mt-1 text-xs text-gray-600">Describe la estructura que DevControl deberá entender.</p>
                    </div>
                    <button type="button" onclick="addSection()" class="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-gray-300 hover:bg-white/5">
                        + Sección
                    </button>
                </div>
                <div id="seccionesContainer" class="mt-4 space-y-4"></div>
            </div>


            {{-- FECHAS --}}

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                <div>

                    <label for="fecha_inicio"
                           class="mb-1.5 block text-sm
                                  font-semibold text-gray-300">

                        Fecha de creación

                    </label>


                    <input type="date"
                           name="fecha_inicio"
                           id="fecha_inicio"
                           required
                           class="w-full rounded-xl border border-white/10
                                  bg-black/40 px-3.5 py-2.5 text-sm
                                  text-white outline-none transition
                                  focus:border-[#d61f2c]">

                </div>


                <div>

                    <label for="fecha_meta"
                           class="mb-1.5 block text-sm
                                  font-semibold text-gray-300">

                        Fecha meta

                        <span class="font-normal text-gray-600">

                            (opcional)

                        </span>

                    </label>


                    <input type="date"
                           name="fecha_meta"
                           id="fecha_meta"
                           class="w-full rounded-xl border border-white/10
                                  bg-black/40 px-3.5 py-2.5 text-sm
                                  text-white outline-none transition
                                  focus:border-[#d61f2c]">

                </div>

            </div>


            {{-- ESTADO / PROGRESO --}}

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                <div>

                    <label for="estado"
                           class="mb-1.5 block text-sm
                                  font-semibold text-gray-300">

                        Estado

                    </label>


                    <select name="estado"
                            id="estado"
                            required
                            class="w-full rounded-xl border border-white/10
                                   bg-black/40 px-3.5 py-2.5 text-sm
                                   text-white outline-none transition
                                   focus:border-[#d61f2c]">

                        <option value="Activo">
                            Activo
                        </option>

                        <option value="Pausado">
                            Pausado
                        </option>

                        <option value="Completado">
                            Completado
                        </option>

                        <option value="Cancelado">
                            Cancelado
                        </option>

                    </select>

                </div>


                <div>

                    <label for="progreso"
                           class="mb-1.5 block text-sm
                                  font-semibold text-gray-300">

                        Progreso (%)

                    </label>


                    <input type="number"
                           name="progreso"
                           id="progreso"
                           min="0"
                           max="100"
                           value="0"
                           required
                           class="w-full rounded-xl border border-white/10
                                  bg-black/40 px-3.5 py-2.5 text-sm
                                  text-white outline-none transition
                                  focus:border-[#d61f2c]">

                </div>

            </div>


            {{-- BOTONES --}}

            <div class="flex items-center justify-end gap-2.5
                        border-t border-white/10 pt-5">

                <button type="button"
                        onclick="closeProjectModal()"
                        class="rounded-xl border border-white/10
                               px-4 py-2.5 text-sm font-semibold
                               text-gray-300 transition hover:bg-white/5">

                    Cancelar

                </button>


                <button type="submit"
                        id="proyectoSubmitBtn"
                        class="flex items-center gap-2 rounded-xl
                               bg-[#d61f2c] px-4 py-2.5
                               text-sm font-bold text-white
                               transition hover:bg-[#b8161f]">

                    Crear proyecto

                </button>

            </div>

        </form>

    </div>

</div>


{{-- ================================================================ --}}
{{-- JAVASCRIPT --}}
{{-- ================================================================ --}}

<script>

    /*
    |--------------------------------------------------------------------------
    | SIDEBAR
    |--------------------------------------------------------------------------
    */

    function toggleSidebar() {

        const sidebar =
            document.getElementById('sidebar');

        const overlay =
            document.getElementById('sidebarOverlay');

        if (!sidebar || !overlay) {
            return;
        }

        sidebar.classList.toggle('-translate-x-full');

        overlay.classList.toggle('hidden');
    }


    /*
    |--------------------------------------------------------------------------
    | TABS
    |--------------------------------------------------------------------------
    */

    function showTab(id, btn = null) {

        document
            .querySelectorAll('.tab-panel')
            .forEach(panel => {

                panel.classList.add('hidden');

            });


        const panel =
            document.getElementById('tab-' + id);


        if (panel) {

            panel.classList.remove('hidden');

        }


        document
            .querySelectorAll('.tab-btn')
            .forEach(button => {

                button.classList.remove(
                    'text-white',
                    'border-[#d61f2c]'
                );

                button.classList.add(
                    'text-gray-500',
                    'border-transparent'
                );

            });


        if (btn) {

            btn.classList.add(
                'text-white',
                'border-[#d61f2c]'
            );

            btn.classList.remove(
                'text-gray-500',
                'border-transparent'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DATOS DEL PROYECTO
    |--------------------------------------------------------------------------
    */

    const proyectoActual =
        @json($proyectoActual);


    const proyectoStoreUrl =
        @json(route('proyectos.store'));

    const proyectoImportUrl =
        @json(route('proyectos.import'));


    const proyectoUpdateUrlTemplate =
        @json(route('proyectos.update', ['proyecto' => '__ID__']));

    let sectionIndex = 0;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function addFeature(sectionElement, sectionIndex, feature = {}) {
        const featuresContainer = sectionElement.querySelector('.features-container');
        const featureIndex = featuresContainer.children.length;
        const featureElement = document.createElement('div');

        featureElement.className = 'feature-row rounded-lg border border-white/10 bg-black/30 p-3';
        featureElement.innerHTML = `
            <div class="flex items-start gap-2">
                <div class="grid flex-1 gap-2 sm:grid-cols-2">
                    <input type="text" name="secciones[${sectionIndex}][funcionalidades][${featureIndex}][nombre]"
                           value="${escapeHtml(feature.nombre)}" required placeholder="Nombre de la funcionalidad"
                           class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]">
                    <select name="secciones[${sectionIndex}][funcionalidades][${featureIndex}][estado]"
                            class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-white outline-none focus:border-[#d61f2c]">
                        ${['Pendiente', 'En desarrollo', 'Parcial', 'Implementada', 'Desconocida', 'Requiere revisión']
                            .map(estado => `<option value="${estado}" ${feature.estado === estado ? 'selected' : ''}>${estado}</option>`).join('')}
                    </select>
                    <textarea name="secciones[${sectionIndex}][funcionalidades][${featureIndex}][descripcion]"
                              rows="2" placeholder="¿Qué hace esta funcionalidad?"
                              class="sm:col-span-2 w-full resize-none rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]">${escapeHtml(feature.descripcion)}</textarea>
                </div>
                <button type="button" onclick="this.closest('.feature-row').remove()" class="px-1 text-gray-600 hover:text-[#ff5b5b]" aria-label="Eliminar funcionalidad">×</button>
            </div>`;

        featuresContainer.appendChild(featureElement);
    }

    function addSection(section = {}) {
        const currentIndex = sectionIndex++;
        const sectionElement = document.createElement('div');

        sectionElement.className = 'section-row rounded-xl border border-white/10 bg-black/20 p-4';
        sectionElement.innerHTML = `
            <div class="flex items-start gap-2">
                <div class="grid flex-1 gap-2">
                    <input type="text" name="secciones[${currentIndex}][nombre]"
                           value="${escapeHtml(section.nombre)}" required placeholder="Ej. Usuarios, Tickets, Administración"
                           class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-sm text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]">
                    <textarea name="secciones[${currentIndex}][descripcion]" rows="2"
                              placeholder="¿Qué cubre esta sección?"
                              class="w-full resize-none rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]">${escapeHtml(section.descripcion)}</textarea>
                </div>
                <button type="button" onclick="this.closest('.section-row').remove()" class="px-1 text-gray-600 hover:text-[#ff5b5b]" aria-label="Eliminar sección">×</button>
            </div>
            <div class="mt-3">
                <div class="mb-2 flex items-center justify-between">
                    <span class="font-mono2 text-[10px] uppercase tracking-wide text-gray-600">Funcionalidades</span>
                    <button type="button" onclick="addFeature(this.closest('.section-row'), ${currentIndex})" class="text-xs font-semibold text-[#ff5b5b] hover:underline">+ Funcionalidad</button>
                </div>
                <div class="features-container space-y-2"></div>
            </div>`;

        document.getElementById('seccionesContainer').appendChild(sectionElement);

        (section.funcionalidades ?? []).forEach(feature => {
            addFeature(sectionElement, currentIndex, feature);
        });
    }

    function resetSections(secciones = []) {
        sectionIndex = 0;
        const container = document.getElementById('seccionesContainer');
        container.innerHTML = '';
        secciones.forEach(seccion => addSection(seccion));
    }


    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL
    |--------------------------------------------------------------------------
    */

    function openProjectModal(mode, proyecto = null) {

        const modal =
            document.getElementById('proyectoModal');

        const form =
            document.getElementById('proyectoForm');

        const title =
            document.getElementById('proyectoModalTitle');

        const methodInput =
            document.getElementById('formMethod');

        const submitBtn =
            document.getElementById('proyectoSubmitBtn');


        if (!modal || !form) {
            return;
        }


        form.reset();
        resetSections();
        document.getElementById('fecha_inicio').required = true;
        document.getElementById('repositorio_url').required = false;
        [
            'campoNombreProyecto',
            'campoDescripcionProyecto',
            'campoContextoProyecto',
            'campoObjetivoProyecto',
            'campoDetallesProyecto',
            'campoSeccionesProyecto',
        ].forEach(id => document.getElementById(id)?.classList.remove('hidden'));
        document.getElementById('fecha_inicio').closest('.grid')?.classList.remove('hidden');
        document.getElementById('nombre').required = true;

        /*
        |--------------------------------------------------------------------------
        | CREAR
        |--------------------------------------------------------------------------
        */

        if (mode === 'crear') {

            title.textContent =
                'Crear proyecto';


            form.action =
                proyectoStoreUrl;


            methodInput.value =
                'POST';


            submitBtn.textContent =
                'Crear proyecto';


            document.getElementById('estado').value =
                'Activo';


            document.getElementById('progreso').value =
                '0';

            addSection();

            /*
            | Fecha actual
            */

            const hoy =
                new Date()
                    .toISOString()
                    .split('T')[0];


            document.getElementById('fecha_inicio').value =
                hoy;

        } else if (mode === 'vincular') {
            title.textContent = 'Vincular o importar proyecto desde GitHub';
            form.action = proyectoImportUrl;
            methodInput.value = 'POST';
            submitBtn.textContent = 'Vincular e importar proyecto';
            document.getElementById('nombre').value = 'Proyecto importado';
            document.getElementById('nombre').required = false;
            [
                'campoNombreProyecto',
                'campoDescripcionProyecto',
                'campoContextoProyecto',
                'campoObjetivoProyecto',
                'campoDetallesProyecto',
                'campoSeccionesProyecto',
            ].forEach(id => document.getElementById(id)?.classList.add('hidden'));
            document.getElementById('fecha_inicio').closest('.grid')?.classList.add('hidden');
            document.getElementById('repositorio_url').required = true;
            document.getElementById('fecha_inicio').required = false;

        }


        /*
        |--------------------------------------------------------------------------
        | EDITAR
        |--------------------------------------------------------------------------
        */

        if (mode === 'editar') {

            proyecto =
                proyectoActual;


            if (!proyecto || !proyecto.id) {

                console.error(
                    'No se encontró el proyecto para editar.'
                );

                return;

            }


            title.textContent =
                'Editar proyecto';


            form.action =
                proyectoUpdateUrlTemplate.replace(
                    '__ID__',
                    proyecto.id
                );


            methodInput.value =
                'PUT';


            submitBtn.textContent =
                'Guardar cambios';


            document.getElementById('nombre').value =
                proyecto.nombre ?? '';


            document.getElementById('descripcion').value =
                proyecto.descripcion ?? '';

            document.getElementById('contexto').value =
                proyecto.contexto ?? '';

            document.getElementById('objetivo').value =
                proyecto.objetivo ?? '';

            document.getElementById('tecnologias').value =
                proyecto.tecnologias ?? '';

            document.getElementById('reglas').value =
                proyecto.reglas ?? '';

            document.getElementById('repositorio_url').value =
                proyecto.repositorio_url ?? '';

            resetSections(proyecto.secciones ?? []);

            document.getElementById('fecha_inicio').value =
                proyecto.fecha_inicio
                    ? String(proyecto.fecha_inicio).substring(0, 10)
                    : '';


            document.getElementById('fecha_meta').value =
                proyecto.fecha_meta
                    ? String(proyecto.fecha_meta).substring(0, 10)
                    : '';


            document.getElementById('estado').value =
                proyecto.estado ?? 'Activo';


            document.getElementById('progreso').value =
                proyecto.progreso ?? 0;

        }


        /*
        |--------------------------------------------------------------------------
        | MOSTRAR MODAL
        |--------------------------------------------------------------------------
        */

        modal.classList.remove('hidden');

        modal.classList.add('flex');

        document.body.classList.add('overflow-hidden');

    }


    /*
    |--------------------------------------------------------------------------
    | CERRAR MODAL
    |--------------------------------------------------------------------------
    */

    function closeProjectModal() {

        const modal =
            document.getElementById('proyectoModal');


        if (!modal) {
            return;
        }


        modal.classList.add('hidden');

        modal.classList.remove('flex');

        document.body.classList.remove('overflow-hidden');

    }


    /*
    |--------------------------------------------------------------------------
    | CERRAR AL TOCAR OVERLAY
    |--------------------------------------------------------------------------
    */

    function closeProjectModalOnOverlay(event) {

        if (event.target.id === 'proyectoModal') {

            closeProjectModal();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ESC
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function(event) {

            if (event.key === 'Escape') {

                closeProjectModal();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDAR PROGRESO
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            const progresoInput =
                document.getElementById('progreso');


            if (!progresoInput) {
                return;
            }


            progresoInput.addEventListener(
                'input',
                function() {

                    let valor =
                        parseInt(this.value);


                    if (isNaN(valor)) {

                        valor = 0;

                    }


                    if (valor < 0) {

                        valor = 0;

                    }


                    if (valor > 100) {

                        valor = 100;

                    }


                    this.value =
                        valor;

                }
            );

        }
    );

</script>

@include('admin.partials.asistente-flotante')
</body>
</html>