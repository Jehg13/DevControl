<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>DevControl | Bugs</title>

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
        | DATOS
        |--------------------------------------------------------------------------
        */

        $usuarioActual = $usuario ?? [];

        /*
        |--------------------------------------------------------------------------
        | ESTADOS
        |--------------------------------------------------------------------------
        */

        $estados = [
            'Reportado',
            'Investigando',
            'En desarrollo',
            'En pruebas',
            'Solucionado',
            'Cerrado'
        ];

        /*
        |--------------------------------------------------------------------------
        | PRIORIDADES
        |--------------------------------------------------------------------------
        */

        $prioridades = [
            'Alta',
            'Media',
            'Baja'
        ];

        /*
        |--------------------------------------------------------------------------
        | CONTADORES
        |--------------------------------------------------------------------------
        */

        $totalReportados = $bugs->where('estado', 'Reportado')->count();
        $totalInvestigando = $bugs->where('estado', 'Investigando')->count();
        $totalDesarrollo = $bugs->where('estado', 'En desarrollo')->count();
        $totalPruebas = $bugs->where('estado', 'En pruebas')->count();
        $totalSolucionados = $bugs->where('estado', 'Solucionado')->count();
        $totalCerrados = $bugs->where('estado', 'Cerrado')->count();

        /*
        |--------------------------------------------------------------------------
        | CLASES
        |--------------------------------------------------------------------------
        */

        $estadoBadgeClases = [
            'Reportado' =>
                'border border-white/10 bg-white/5 text-gray-300',

            'Investigando' =>
                'border border-white/10 bg-white/5 text-gray-300',

            'En desarrollo' =>
                'border border-white/10 bg-white/5 text-gray-300',

            'En pruebas' =>
                'border border-white/10 bg-white/5 text-gray-300',

            'Solucionado' =>
                'bg-white text-black',

            'Cerrado' =>
                'border border-white/10 bg-black text-gray-500',
        ];

        $prioridadClases = [
            'Alta' =>
                'text-[#ff5b5b] font-bold',

            'Media' =>
                'text-gray-300 font-semibold',

            'Baja' =>
                'text-gray-500 font-semibold',
        ];
    @endphp


    <div class="flex min-h-screen">


        {{-- ================================================================
             OVERLAY MOBILE
        ================================================================= --}}

        <div
            id="sidebarOverlay"
            onclick="toggleSidebar()"
            class="fixed inset-0 z-40 hidden bg-black/70 lg:hidden">
        </div>


        {{-- ================================================================
             SIDEBAR
        ================================================================= --}}

        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-64
                   -translate-x-full flex-col overflow-y-auto
                   border-r border-white/10 bg-[#0a0a0a]
                   px-5 py-8 shadow-2xl
                   transition-transform duration-300
                   lg:static lg:z-auto lg:w-64 lg:shrink-0
                   lg:translate-x-0 lg:shadow-none">

            {{-- LOGO --}}

            <div class="mb-8 flex items-center gap-2.5 px-1">

                <span
                    class="flex h-8 w-8 items-center justify-center
                           rounded-md bg-[#d61f2c]
                           font-mono2 text-sm font-bold text-white">

                    &gt;_

                </span>

                <span
                    class="font-display text-lg font-bold tracking-tight text-white">

                    DEV<span class="text-[#d61f2c]">CONTROL</span>

                </span>

            </div>


            {{-- USUARIO --}}

            <div
                class="mb-6 flex items-center gap-3 rounded-xl
                       border border-white/10 bg-white/5
                       px-3 py-3">

                <img
                    src="{{ $usuarioActual['foto'] ?? asset('storage/images/jesus-guerra.jpg') }}"
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

            <nav class="space-y-1">

                <a
                    href="#"
                    class="flex items-center gap-3 rounded-lg
                           px-3.5 py-2.5 text-sm font-semibold
                           text-gray-400 transition
                           hover:bg-white/5 hover:text-white">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4.5 w-4.5"
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


                <a
                    href="#"
                    class="flex items-center gap-3 rounded-lg
                           px-3.5 py-2.5 text-sm font-semibold
                           text-gray-400 transition
                           hover:bg-white/5 hover:text-white">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4.5 w-4.5"
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


                <a
                    href="#"
                    class="flex items-center gap-3 rounded-lg
                           px-3.5 py-2.5 text-sm font-semibold
                           text-gray-400 transition
                           hover:bg-white/5 hover:text-white">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4.5 w-4.5"
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


                {{-- BUGS ACTIVO --}}

                <a
                    href="{{ route('bugs.index') }}"
                    class="flex items-center gap-3 rounded-lg
                           bg-[#d61f2c] px-3.5 py-2.5
                           text-sm font-bold text-white">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4.5 w-4.5"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.8"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <rect x="8" y="7" width="8" height="11" rx="4"/>
                        <path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 014 0v2M6 6l2 1.5M18 6l-2 1.5"/>

                    </svg>

                    Bugs

                </a>


                <a
                    href="#"
                    class="flex items-center gap-3 rounded-lg
                           px-3.5 py-2.5 text-sm font-semibold
                           text-gray-400 transition
                           hover:bg-white/5 hover:text-white">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4.5 w-4.5"
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


                <a
                    href="#"
                    class="flex items-center gap-3 rounded-lg
                           px-3.5 py-2.5 text-sm font-semibold
                           text-gray-400 transition
                           hover:bg-white/5 hover:text-white">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4.5 w-4.5"
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

            </nav>


            {{-- LOGOUT --}}

            <form
                method="POST"
                action="{{ route('logout') }}"
                class="mt-6">

                @csrf

                <button
                    type="submit"
                    class="flex w-full items-center gap-3
                           rounded-lg px-3.5 py-2.5
                           text-sm font-semibold text-gray-400
                           transition hover:bg-white/5 hover:text-white">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4.5 w-4.5"
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


        {{-- ================================================================
             CONTENIDO
        ================================================================= --}}

        <main class="flex-1 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">


            {{-- ============================================================
                 MENSAJES
            ============================================================= --}}

            @if(session('success'))

                <div
                    class="mb-5 flex items-center gap-3 rounded-xl
                           border border-green-500/20
                           bg-green-500/10 px-4 py-3
                           text-sm text-green-400">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-5 w-5 shrink-0"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path d="M5 13l4 4L19 7"/>

                    </svg>

                    {{ session('success') }}

                </div>

            @endif


            @if($errors->any())

                <div
                    class="mb-5 rounded-xl border
                           border-[#d61f2c]/30
                           bg-[#d61f2c]/10 p-4">

                    <p class="mb-2 text-sm font-bold text-[#ff5b5b]">
                        Hay algunos errores:
                    </p>

                    <ul class="space-y-1 text-sm text-gray-400">

                        @foreach($errors->all() as $error)

                            <li>
                                • {{ $error }}
                            </li>

                        @endforeach

                    </ul>

                </div>

            @endif


            {{-- ============================================================
                 ENCABEZADO
            ============================================================= --}}

            <div
                class="flex flex-col gap-4
                       sm:flex-row sm:items-start
                       sm:justify-between">

                <div class="flex items-center gap-3">

                    <button
                        type="button"
                        onclick="toggleSidebar()"
                        class="flex h-10 w-10 shrink-0
                               items-center justify-center
                               rounded-full border border-white/10
                               text-white transition
                               hover:bg-white/5 lg:hidden">

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


                    <div>

                        <p class="font-mono2 text-xs text-gray-500">

                            ~ /
                            <span class="font-semibold text-gray-300">
                                bugs
                            </span>

                        </p>

                        <h1
                            class="font-display mt-1 text-2xl
                                   font-bold tracking-tight text-white
                                   sm:text-3xl">

                            Bugs

                        </h1>

                    </div>

                </div>


                {{-- BOTÓN AGREGAR --}}

                <button
                    type="button"
                    onclick="toggleComposer()"
                    class="flex items-center gap-2
                           rounded-xl bg-[#d61f2c]
                           px-5 py-3 text-sm font-bold
                           text-white transition
                           hover:bg-[#b8161f]">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-5 w-5"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2.4"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path d="M12 5v14M5 12h14"/>

                    </svg>

                    Agregar bug

                </button>

            </div>


            {{-- ============================================================
                 FORMULARIO AGREGAR
            ============================================================= --}}

            <div
                id="composer"
                class="mt-6 hidden rounded-2xl
                       border border-[#d61f2c]/30
                       bg-[#0f0f11] p-5 sm:p-6">


                <div class="flex items-center justify-between">

                    <div class="flex items-center gap-2.5">

                        <div
                            class="flex h-8 w-8 items-center
                                   justify-center rounded-lg
                                   bg-[#d61f2c]/10
                                   text-[#ff5b5b]">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8"
                                 stroke-linecap="round"
                                 stroke-linejoin="round">

                                <rect x="8" y="7"
                                      width="8"
                                      height="11"
                                      rx="4"/>

                                <path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 014 0v2M6 6l2 1.5M18 6l-2 1.5"/>

                            </svg>

                        </div>


                        <h2
                            class="font-display text-lg
                                   font-bold text-white">

                            Agregar nuevo bug

                        </h2>

                    </div>


                    <button
                        type="button"
                        onclick="toggleComposer()"
                        class="text-gray-500 hover:text-gray-300">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-5 w-5"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <path d="M6 6l12 12M18 6L6 18"/>

                        </svg>

                    </button>

                </div>


                {{-- FORM --}}

                <form
                    method="POST"
                    action="{{ route('bugs.store') }}"
                    class="mt-5 space-y-4">

                    @csrf


                    {{-- PROYECTO / PRIORIDAD --}}

                    <div class="grid gap-4 sm:grid-cols-2">

                        <div>

                            <label
                                class="mb-1.5 block font-mono2
                                       text-[11px] uppercase
                                       tracking-widest text-gray-500">

                                Proyecto

                            </label>


                            <select
                                name="proyecto_id"
                                required
                                class="w-full rounded-xl
                                       border border-white/10
                                       bg-black/40 px-4 py-3
                                       text-sm font-semibold text-white
                                       outline-none transition
                                       focus:border-[#d61f2c]/50">

                                <option value="" class="bg-[#0f0f11]">
                                    Seleccionar proyecto
                                </option>

                                @foreach($proyectos as $proyecto)

                                    <option
                                        value="{{ $proyecto->id }}"
                                        class="bg-[#0f0f11]">

                                        {{ $proyecto->nombre }}

                                    </option>

                                @endforeach

                            </select>

                        </div>


                        <div>

                            <label
                                class="mb-1.5 block font-mono2
                                       text-[11px] uppercase
                                       tracking-widest text-gray-500">

                                Prioridad

                            </label>


                            <select
                                name="prioridad"
                                required
                                class="w-full rounded-xl
                                       border border-white/10
                                       bg-black/40 px-4 py-3
                                       text-sm font-semibold text-white
                                       outline-none transition
                                       focus:border-[#d61f2c]/50">

                                <option value="Alta"
                                        class="bg-[#0f0f11]">
                                    Alta
                                </option>

                                <option value="Media"
                                        class="bg-[#0f0f11]">
                                    Media
                                </option>

                                <option value="Baja"
                                        class="bg-[#0f0f11]">
                                    Baja
                                </option>

                            </select>

                        </div>

                    </div>


                    {{-- ESTADO / FECHA --}}

                    <div class="grid gap-4 sm:grid-cols-2">

                        <div>

                            <label
                                class="mb-1.5 block font-mono2
                                       text-[11px] uppercase
                                       tracking-widest text-gray-500">

                                Estado

                            </label>


                            <select
                                name="estado"
                                required
                                class="w-full rounded-xl
                                       border border-white/10
                                       bg-black/40 px-4 py-3
                                       text-sm font-semibold text-white
                                       outline-none transition
                                       focus:border-[#d61f2c]/50">

                                @foreach($estados as $estado)

                                    <option
                                        value="{{ $estado }}"
                                        class="bg-[#0f0f11]">

                                        {{ $estado }}

                                    </option>

                                @endforeach

                            </select>

                        </div>


                        <div>

                            <label
                                class="mb-1.5 block font-mono2
                                       text-[11px] uppercase
                                       tracking-widest text-gray-500">

                                Fecha detectado

                            </label>


                            <input
                                type="date"
                                name="fecha_detectado"
                                value="{{ old('fecha_detectado', now()->format('Y-m-d')) }}"
                                class="w-full rounded-xl
                                       border border-white/10
                                       bg-black/40 px-4 py-3
                                       text-sm text-white
                                       outline-none transition
                                       focus:border-[#d61f2c]/50">

                        </div>

                    </div>


                    {{-- TITULO --}}

                    <div>

                        <label
                            class="mb-1.5 block font-mono2
                                   text-[11px] uppercase
                                   tracking-widest text-gray-500">

                            Título

                        </label>

                        <input
                            type="text"
                            name="titulo"
                            value="{{ old('titulo') }}"
                            required
                            maxlength="255"
                            placeholder="Error al subir archivos grandes"
                            class="w-full rounded-xl
                                   border border-white/10
                                   bg-black/40 px-4 py-3
                                   text-sm text-white
                                   placeholder-gray-600
                                   outline-none transition
                                   focus:border-[#d61f2c]/50">

                    </div>


                    {{-- DESCRIPCIÓN --}}

                    <div>

                        <label
                            class="mb-1.5 block font-mono2
                                   text-[11px] uppercase
                                   tracking-widest text-gray-500">

                            Descripción

                        </label>

                        <textarea
                            name="descripcion"
                            rows="4"
                            placeholder="¿Qué pasó? ¿Cómo se reproduce? ¿Qué esperabas que pasara?"
                            class="w-full resize-none rounded-xl
                                   border border-white/10
                                   bg-black/40 px-4 py-3
                                   text-sm text-white
                                   placeholder-gray-600
                                   outline-none transition
                                   focus:border-[#d61f2c]/50">{{ old('descripcion') }}</textarea>

                    </div>


                    {{-- BOTONES --}}

                    <div
                        class="flex items-center justify-end gap-3
                               border-t border-white/10 pt-4">

                        <button
                            type="button"
                            onclick="toggleComposer()"
                            class="rounded-xl border border-white/10
                                   px-4 py-2.5 text-sm
                                   font-semibold text-gray-400
                                   transition hover:bg-white/5">

                            Cancelar

                        </button>


                        <button
                            type="submit"
                            class="flex items-center gap-2
                                   rounded-xl bg-[#d61f2c]
                                   px-5 py-2.5 text-sm
                                   font-bold text-white transition
                                   hover:bg-[#b8161f]">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2"
                                 stroke-linecap="round"
                                 stroke-linejoin="round">

                                <path d="M12 5v14M5 12h14"/>

                            </svg>

                            Agregar bug

                        </button>

                    </div>

                </form>

            </div>


            {{-- ============================================================
                 RESUMEN
            ============================================================= --}}

            <div
                class="mt-6 -mx-5 overflow-x-auto
                       px-5 sm:mx-0 sm:px-0">

                <div
                    class="flex min-w-max gap-3
                           sm:grid sm:min-w-0
                           sm:grid-cols-3
                           lg:grid-cols-6">


                    @php
                        $resumen = [
                            [
                                'nombre' => 'Reportado',
                                'total' => $totalReportados
                            ],
                            [
                                'nombre' => 'Investigando',
                                'total' => $totalInvestigando
                            ],
                            [
                                'nombre' => 'En desarrollo',
                                'total' => $totalDesarrollo
                            ],
                            [
                                'nombre' => 'En pruebas',
                                'total' => $totalPruebas
                            ],
                            [
                                'nombre' => 'Solucionado',
                                'total' => $totalSolucionados
                            ],
                            [
                                'nombre' => 'Cerrado',
                                'total' => $totalCerrados
                            ],
                        ];
                    @endphp


                    @foreach($resumen as $e)

                        <div
                            class="w-32 shrink-0 rounded-xl
                                   border border-white/10
                                   bg-[#0f0f11] p-3.5
                                   text-center sm:w-auto">

                            <p
                                class="font-display text-2xl
                                       font-bold text-white">

                                {{ $e['total'] }}

                            </p>

                            <p
                                class="mt-0.5 font-mono2
                                       text-[10px] uppercase
                                       tracking-wide text-gray-500">

                                {{ $e['nombre'] }}

                            </p>

                        </div>

                    @endforeach

                </div>

            </div>


            {{-- ============================================================
                 FILTROS
            ============================================================= --}}

            <div
                class="mt-6 flex flex-col gap-3
                       sm:flex-row sm:items-center
                       sm:justify-between">


                {{-- BUSCADOR --}}

                <div class="relative w-full sm:max-w-xs">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="pointer-events-none absolute
                               left-3.5 top-1/2 h-4 w-4
                               -translate-y-1/2 text-gray-500"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round">

                        <circle cx="11" cy="11" r="7"/>
                        <path d="M21 21l-4.3-4.3"/>

                    </svg>


                    <input
                        id="buscadorBug"
                        type="text"
                        placeholder="Buscar bug o folio..."
                        class="w-full rounded-xl
                               border border-white/10
                               bg-[#0f0f11] py-2.5 pl-10 pr-4
                               text-sm text-white
                               placeholder-gray-500
                               outline-none transition
                               focus:border-[#d61f2c]/50">

                </div>


                {{-- FILTROS --}}

                <div class="flex flex-wrap items-center gap-2">


                    <select
                        id="filtroProyecto"
                        class="rounded-lg border border-white/10
                               bg-[#0f0f11] px-3 py-2
                               font-mono2 text-xs font-semibold
                               text-gray-300 outline-none">

                        <option value="">
                            Proyecto: Todos
                        </option>

                        @foreach($proyectos as $proyecto)

                            <option value="{{ $proyecto->id }}">
                                {{ $proyecto->nombre }}
                            </option>

                        @endforeach

                    </select>


                    <select
                        id="filtroEstado"
                        class="rounded-lg border border-white/10
                               bg-[#0f0f11] px-3 py-2
                               font-mono2 text-xs font-semibold
                               text-gray-300 outline-none">

                        <option value="">
                            Estado: Todos
                        </option>

                        @foreach($estados as $estado)

                            <option value="{{ $estado }}">
                                {{ $estado }}
                            </option>

                        @endforeach

                    </select>

                </div>

            </div>


            {{-- ============================================================
                 LISTADO
            ============================================================= --}}

            <div
                id="listaBugs"
                class="mt-4 space-y-3">


                @forelse($bugs as $bug)

                    @php

                        $nombreProyecto =
                            $bug->proyecto->nombre
                            ?? 'Proyecto eliminado';

                        $estadoClase =
                            $estadoBadgeClases[$bug->estado]
                            ?? 'border border-white/10 bg-white/5 text-gray-300';

                        $prioridadClase =
                            $prioridadClases[$bug->prioridad]
                            ?? 'text-gray-400';

                    @endphp


                    <div
                        class="bug-card group rounded-xl
                               border border-white/10
                               bg-[#0f0f11] p-4
                               transition hover:border-white/20
                               sm:p-5"
                        data-titulo="{{ strtolower($bug->titulo) }}"
                        data-folio="{{ strtolower($bug->folio) }}"
                        data-proyecto="{{ $bug->proyecto_id }}"
                        data-estado="{{ $bug->estado }}">


                        <div
                            class="flex flex-wrap
                                   items-start justify-between gap-3">


                            {{-- INFORMACIÓN --}}

                            <div class="min-w-0 flex-1">

                                <div
                                    class="flex flex-wrap
                                           items-center gap-2">

                                    <span
                                        class="font-mono2 text-xs
                                               font-bold text-[#ff5b5b]">

                                        {{ $bug->folio }}

                                    </span>


                                    <span
                                        class="rounded-full
                                               bg-white/5 px-2 py-0.5
                                               font-mono2 text-[10px]
                                               text-gray-400">

                                        {{ $nombreProyecto }}

                                    </span>

                                </div>


                                <p
                                    class="mt-1.5 font-semibold text-white">

                                    {{ $bug->titulo }}

                                </p>


                                @if($bug->descripcion)

                                    <p
                                        class="mt-1 text-sm
                                               text-gray-500">

                                        {{ $bug->descripcion }}

                                    </p>

                                @endif

                            </div>


                            {{-- ESTADO / PRIORIDAD --}}

                            <div
                                class="flex shrink-0
                                       flex-col items-end gap-1.5">

                                <span
                                    class="font-mono2 text-xs
                                           {{ $prioridadClase }}">

                                    {{ $bug->prioridad }}

                                </span>


                                <span
                                    class="rounded-full px-2.5 py-1
                                           font-mono2 text-[11px]
                                           font-semibold
                                           {{ $estadoClase }}">

                                    {{ $bug->estado }}

                                </span>

                            </div>

                        </div>


                        {{-- FECHA --}}

                        <div
                            class="mt-3 flex flex-wrap
                                   gap-x-5 gap-y-1
                                   border-t border-white/10
                                   pt-3 font-mono2
                                   text-[11px] text-gray-500">

                            <span>

                                Detectado:

                                {{ $bug->fecha_detectado
                                    ? \Carbon\Carbon::parse($bug->fecha_detectado)->format('d M Y')
                                    : 'Sin fecha'
                                }}

                            </span>

                            @if($bug->updated_at && $bug->updated_at != $bug->created_at)

                                <span>

                                    Actualizado:

                                    {{ $bug->updated_at->format('d M Y') }}

                                </span>

                            @endif

                        </div>


                        {{-- ACCIONES --}}

                        <div
                            class="mt-4 flex flex-wrap
                                   items-center justify-end gap-2">


                            {{-- EDITAR --}}

                            <a
                                href="{{ route('bugs.edit', $bug->id) }}"
                                class="flex items-center gap-1.5
                                       rounded-lg border
                                       border-white/10
                                       px-3 py-2
                                       font-mono2 text-[11px]
                                       font-semibold text-gray-400
                                       transition
                                       hover:border-white/20
                                       hover:bg-white/5
                                       hover:text-white">

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round">

                                    <path
                                        d="M12 20h9"/>

                                    <path
                                        d="M16.5 3.5a2.1 2.1 0 013 3L8 18l-4 1 1-4z"/>

                                </svg>

                                Editar

                            </a>


                            {{-- ELIMINAR --}}

                            <form
                                method="POST"
                                action="{{ route('bugs.destroy', $bug->id) }}"
                                onsubmit="return confirmarEliminacion(event)">

                                @csrf

                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="flex items-center gap-1.5
                                           rounded-lg border
                                           border-[#d61f2c]/20
                                           px-3 py-2
                                           font-mono2 text-[11px]
                                           font-semibold
                                           text-[#ff5b5b]
                                           transition
                                           hover:bg-[#d61f2c]/10">

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round">

                                        <path d="M3 6h18"/>

                                        <path d="M8 6V4h8v2"/>

                                        <path d="M19 6l-1 14H6L5 6"/>

                                        <path d="M10 11v5M14 11v5"/>

                                    </svg>

                                    Eliminar

                                </button>

                            </form>

                        </div>

                    </div>


                @empty


                    {{-- SIN BUGS --}}

                    <div
                        class="rounded-2xl border
                               border-white/10
                               bg-[#0f0f11]
                               px-6 py-14 text-center">

                        <div
                            class="mx-auto flex h-14 w-14
                                   items-center justify-center
                                   rounded-2xl bg-white/5
                                   text-gray-500">

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-7 w-7"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round">

                                <rect x="8" y="7"
                                      width="8"
                                      height="11"
                                      rx="4"/>

                                <path
                                    d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 014 0v2"/>

                            </svg>

                        </div>


                        <h3
                            class="mt-4 font-display
                                   text-lg font-bold text-white">

                            No hay bugs registrados

                        </h3>


                        <p
                            class="mt-1 text-sm
                                   text-gray-500">

                            Agrega el primer bug para comenzar.

                        </p>


                        <button
                            type="button"
                            onclick="toggleComposer()"
                            class="mt-5 rounded-xl
                                   bg-[#d61f2c]
                                   px-5 py-2.5
                                   text-sm font-bold text-white
                                   transition
                                   hover:bg-[#b8161f]">

                            Agregar bug

                        </button>

                    </div>


                @endforelse


                {{-- RESULTADO VACÍO DE FILTROS --}}

                <div
                    id="sinResultados"
                    class="hidden rounded-2xl
                           border border-white/10
                           bg-[#0f0f11]
                           px-6 py-14 text-center">

                    <p
                        class="font-display text-lg
                               font-bold text-white">

                        No se encontraron bugs

                    </p>

                    <p
                        class="mt-1 text-sm
                               text-gray-500">

                        Intenta cambiar los filtros o la búsqueda.

                    </p>

                </div>

            </div>

        </main>

    </div>


    {{-- ================================================================
         JAVASCRIPT
    ================================================================= --}}

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

            sidebar.classList.toggle(
                '-translate-x-full'
            );

            overlay.classList.toggle(
                'hidden'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | COMPOSER
        |--------------------------------------------------------------------------
        */

        function toggleComposer() {

            const composer =
                document.getElementById('composer');

            composer.classList.toggle(
                'hidden'
            );

            if (!composer.classList.contains('hidden')) {

                setTimeout(() => {

                    composer.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                }, 50);

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CONFIRMAR ELIMINACIÓN
        |--------------------------------------------------------------------------
        */

        function confirmarEliminacion(event) {

            const confirmar =
                confirm(
                    '¿Estás seguro de que deseas eliminar este bug? Esta acción no se puede deshacer.'
                );

            if (!confirmar) {

                event.preventDefault();

                return false;
            }

            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | FILTROS
        |--------------------------------------------------------------------------
        */

        const buscador =
            document.getElementById('buscadorBug');

        const filtroProyecto =
            document.getElementById('filtroProyecto');

        const filtroEstado =
            document.getElementById('filtroEstado');

        const tarjetas =
            document.querySelectorAll('.bug-card');

        const sinResultados =
            document.getElementById('sinResultados');


        function filtrarBugs() {

            const texto =
                buscador.value
                    .toLowerCase()
                    .trim();

            const proyecto =
                filtroProyecto.value;

            const estado =
                filtroEstado.value;

            let visibles = 0;


            tarjetas.forEach(tarjeta => {

                const titulo =
                    tarjeta.dataset.titulo || '';

                const folio =
                    tarjeta.dataset.folio || '';

                const proyectoId =
                    tarjeta.dataset.proyecto || '';

                const estadoBug =
                    tarjeta.dataset.estado || '';


                const coincideTexto =
                    titulo.includes(texto) ||
                    folio.includes(texto);


                const coincideProyecto =
                    proyecto === '' ||
                    proyecto === proyectoId;


                const coincideEstado =
                    estado === '' ||
                    estado === estadoBug;


                if (
                    coincideTexto &&
                    coincideProyecto &&
                    coincideEstado
                ) {

                    tarjeta.classList.remove('hidden');

                    visibles++;

                } else {

                    tarjeta.classList.add('hidden');

                }

            });


            if (visibles === 0 && tarjetas.length > 0) {

                sinResultados.classList.remove('hidden');

            } else {

                sinResultados.classList.add('hidden');

            }

        }


        if (buscador) {

            buscador.addEventListener(
                'input',
                filtrarBugs
            );

        }


        if (filtroProyecto) {

            filtroProyecto.addEventListener(
                'change',
                filtrarBugs
            );

        }


        if (filtroEstado) {

            filtroEstado.addEventListener(
                'change',
                filtrarBugs
            );

        }

    </script>

</body>

</html>