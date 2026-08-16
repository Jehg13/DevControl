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

    <title>DevControl | Tareas</title>

    <style>
        .font-display {
            font-family: 'Space Grotesk', sans-serif;
        }

        .font-mono2 {
            font-family: 'JetBrains Mono', monospace;
        }

        .tarea-card {
            transition: all .2s ease;
        }

        .tarea-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, .18);
        }

        .modal-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .modal-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .modal-scroll::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 10px;
        }
    </style>
</head>


<body class="min-h-screen bg-black text-white">


@php

    $usuarioActual = auth()->user();

    $nombreUsuario = $usuarioActual->nombre
        ?? $usuarioActual->name
        ?? 'Jesús Guerra';

    $rolUsuario = $usuarioActual->rol
        ?? 'Desarrollador';

    $fotoUsuario = $usuarioActual->foto
        ?? asset('storage/images/jesus-guerra.jpg');

@endphp


<div class="flex min-h-screen">


{{-- ============================================================= --}}
{{-- OVERLAY --}}
{{-- ============================================================= --}}

<div
    id="sidebarOverlay"
    onclick="toggleSidebar()"
    class="fixed inset-0 z-40 hidden bg-black/70 lg:hidden">
</div>


{{-- ============================================================= --}}
{{-- SIDEBAR --}}
{{-- ============================================================= --}}

<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overflow-y-auto border-r border-white/10 bg-[#0a0a0a] px-5 py-8 shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:w-64 lg:shrink-0 lg:translate-x-0 lg:shadow-none">


    {{-- LOGO --}}
    <div class="mb-8 flex items-center gap-2.5 px-1">

        <span
            class="flex h-8 w-8 items-center justify-center rounded-md bg-[#d61f2c] font-mono2 text-sm font-bold text-white">

            &gt;_

        </span>

        <span class="font-display text-lg font-bold tracking-tight text-white">

            DEV<span class="text-[#d61f2c]">CONTROL</span>

        </span>

    </div>


    {{-- USUARIO --}}
    <div class="mb-6 flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3">

        <img
            src="{{ $fotoUsuario }}"
            alt="Foto de {{ $nombreUsuario }}"
            class="h-10 w-10 shrink-0 rounded-full border border-white/10 object-cover">

        <div class="min-w-0">

            <p class="truncate text-sm font-bold text-white">
                {{ $nombreUsuario }}
            </p>

            <p class="truncate font-mono2 text-[11px] text-gray-500">
                {{ $rolUsuario }}
            </p>

        </div>

    </div>


    {{-- NAVEGACIÓN --}}
    <nav class="space-y-1">


        {{-- DASHBOARD --}}
        <a
            href="#"
            class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8">

                <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                <rect x="3" y="16" width="7" height="5" rx="1.5"/>

            </svg>

            Dashboard

        </a>


        {{-- PROYECTOS --}}
        <a
            href="#"
            class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8">

                <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>

            </svg>

            Proyectos

        </a>


        {{-- TAREAS --}}
        <a
            href="{{ route('tareas.index') }}"
            class="flex items-center gap-3 rounded-lg bg-[#d61f2c] px-3.5 py-2.5 text-sm font-bold text-white">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8">

                <rect x="4" y="4" width="16" height="16" rx="2"/>
                <path d="M8 12l2.5 2.5L16 9"/>

            </svg>

            Tareas

        </a>


        {{-- BUGS --}}
        <a
            href="#"
            class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

            Bugs

        </a>


        {{-- ACTUALIZACIONES --}}
        <a
            href="#"
            class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

            Actualizaciones

        </a>


        {{-- ARCHIVOS --}}
        <a
            href="#"
            class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

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
            class="flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

            Cerrar sesión

        </button>

    </form>

</aside>


{{-- ============================================================= --}}
{{-- CONTENIDO --}}
{{-- ============================================================= --}}

<main class="flex-1 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">


{{-- ============================================================= --}}
{{-- HEADER --}}
{{-- ============================================================= --}}

<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

    <div class="flex items-center gap-3">

        <button
            type="button"
            onclick="toggleSidebar()"
            class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 lg:hidden">

            ☰

        </button>


        <div>

            <p class="font-mono2 text-xs text-gray-500">

                ~ / <span class="text-gray-300">tareas</span>

            </p>

            <h1 class="font-display mt-1 text-3xl font-bold text-white">

                Tareas

            </h1>

        </div>

    </div>


    {{-- NUEVA TAREA --}}
    <button
        type="button"
        onclick="abrirFormulario()"
        class="flex items-center justify-center gap-2 rounded-xl bg-[#d61f2c] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#b8161f]">

        <span class="text-xl">+</span>

        Nueva tarea

    </button>

</div>


{{-- ============================================================= --}}
{{-- MENSAJE SUCCESS --}}
{{-- ============================================================= --}}

@if(session('success'))

    <div class="mt-5 rounded-xl border border-green-500/20 bg-green-500/10 px-4 py-3 text-sm text-green-400">

        {{ session('success') }}

    </div>

@endif


{{-- ============================================================= --}}
{{-- ERRORES --}}
{{-- ============================================================= --}}

@if($errors->any())

    <div class="mt-5 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3">

        <ul class="space-y-1 text-sm text-red-400">

            @foreach($errors->all() as $error)

                <li>
                    {{ $error }}
                </li>

            @endforeach

        </ul>

    </div>

@endif


{{-- ============================================================= --}}
{{-- FORMULARIO NUEVA TAREA --}}
{{-- ============================================================= --}}

<div
    id="formularioTarea"
    class="mt-6 hidden rounded-2xl border border-white/10 bg-[#0f0f11] p-6">


    <div class="mb-6 flex items-center justify-between">

        <div>

            <p class="font-mono2 text-xs text-gray-600">
                / nueva
            </p>

            <h2 class="font-display text-xl font-bold text-white">
                Crear nueva tarea
            </h2>

        </div>


        <button
            type="button"
            onclick="cerrarFormulario()"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-400 hover:bg-white/5 hover:text-white">

            ✕

        </button>

    </div>


    <form
        method="POST"
        action="{{ route('tareas.store') }}"
        class="space-y-5">

        @csrf


        {{-- PROYECTO --}}
        <div>

            <label class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">
                Proyecto *
            </label>

            <select
                name="proyecto_id"
                required
                class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                <option value="">
                    Selecciona un proyecto
                </option>

                @foreach($proyectos as $proyecto)

                    <option
                        value="{{ $proyecto->id }}"
                        {{ old('proyecto_id') == $proyecto->id ? 'selected' : '' }}>

                        {{ $proyecto->nombre }}

                    </option>

                @endforeach

            </select>

        </div>


        {{-- TITULO --}}
        <div>

            <label class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">
                Título *
            </label>

            <input
                type="text"
                name="titulo"
                value="{{ old('titulo') }}"
                required
                maxlength="255"
                placeholder="Ej. Diseñar recuperación de contraseña"
                class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]/50">

        </div>


        {{-- DESCRIPCION --}}
        <div>

            <label class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">
                Descripción
            </label>

            <textarea
                name="descripcion"
                rows="4"
                placeholder="Describe la tarea..."
                class="w-full resize-none rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]/50">{{ old('descripcion') }}</textarea>

        </div>


        {{-- PRIORIDAD / ESTADO --}}
        <div class="grid gap-5 md:grid-cols-2">

            <div>

                <label class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">
                    Prioridad *
                </label>

                <select
                    name="prioridad"
                    required
                    class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                    <option value="Alta"
                        {{ old('prioridad') === 'Alta' ? 'selected' : '' }}>
                        Alta
                    </option>

                    <option value="Media"
                        {{ old('prioridad', 'Media') === 'Media' ? 'selected' : '' }}>
                        Media
                    </option>

                    <option value="Baja"
                        {{ old('prioridad') === 'Baja' ? 'selected' : '' }}>
                        Baja
                    </option>

                </select>

            </div>


            <div>

                <label class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">
                    Estado *
                </label>

                <select
                    name="estado"
                    required
                    class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                    <option value="Pendiente"
                        {{ old('estado', 'Pendiente') === 'Pendiente' ? 'selected' : '' }}>
                        Pendiente
                    </option>

                    <option value="En progreso"
                        {{ old('estado') === 'En progreso' ? 'selected' : '' }}>
                        En progreso
                    </option>

                    <option value="En revisión"
                        {{ old('estado') === 'En revisión' ? 'selected' : '' }}>
                        En revisión
                    </option>

                    <option value="Completado"
                        {{ old('estado') === 'Completado' ? 'selected' : '' }}>
                        Completado
                    </option>

                    <option value="Cancelado"
                        {{ old('estado') === 'Cancelado' ? 'selected' : '' }}>
                        Cancelado
                    </option>

                </select>

            </div>

        </div>


        {{-- FECHAS --}}
        <div class="grid gap-5 md:grid-cols-2">

            <div>

                <label class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">
                    Fecha de inicio
                </label>

                <input
                    type="date"
                    name="fecha_inicio"
                    value="{{ old('fecha_inicio') }}"
                    class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

            </div>


            <div>

                <label class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">
                    Fecha límite
                </label>

                <input
                    type="date"
                    name="fecha_limite"
                    value="{{ old('fecha_limite') }}"
                    class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

            </div>

        </div>


        <div class="flex justify-end gap-3 border-t border-white/10 pt-5">

            <button
                type="button"
                onclick="cerrarFormulario()"
                class="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-gray-400 hover:bg-white/5 hover:text-white">

                Cancelar

            </button>

            <button
                type="submit"
                class="rounded-xl bg-[#d61f2c] px-6 py-3 text-sm font-bold text-white hover:bg-[#b8161f]">

                Crear tarea

            </button>

        </div>

    </form>

</div>


{{-- ============================================================= --}}
{{-- HERRAMIENTAS --}}
{{-- ============================================================= --}}

<div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

    <div class="relative w-full sm:max-w-xs">

        <input
            id="buscador"
            type="text"
            placeholder="Buscar tarea..."
            oninput="filtrarTareas()"
            class="w-full rounded-xl border border-white/10 bg-[#0f0f11] py-2.5 px-4 text-sm text-white placeholder-gray-500 outline-none focus:border-[#d61f2c]/50">

    </div>


    <div class="flex flex-wrap gap-2">

        <select
            id="filtroProyecto"
            onchange="filtrarTareas()"
            class="rounded-lg border border-white/10 bg-[#0f0f11] px-3 py-2 font-mono2 text-xs text-gray-300 outline-none">

            <option value="">
                Proyecto: Todos
            </option>

            @foreach($proyectos as $proyecto)

                <option value="{{ strtolower($proyecto->nombre) }}">
                    {{ $proyecto->nombre }}
                </option>

            @endforeach

        </select>


        <select
            id="filtroPrioridad"
            onchange="filtrarTareas()"
            class="rounded-lg border border-white/10 bg-[#0f0f11] px-3 py-2 font-mono2 text-xs text-gray-300 outline-none">

            <option value="">
                Prioridad: Todas
            </option>

            <option value="Alta">
                Alta
            </option>

            <option value="Media">
                Media
            </option>

            <option value="Baja">
                Baja
            </option>

        </select>


        <div class="flex rounded-lg border border-white/10 bg-[#0f0f11] p-1">

            <button
                type="button"
                onclick="mostrarVista('kanban', this)"
                class="vista-btn rounded-md bg-[#d61f2c] px-3 py-1.5 font-mono2 text-xs font-bold text-white">

                Kanban

            </button>

            <button
                type="button"
                onclick="mostrarVista('lista', this)"
                class="vista-btn rounded-md px-3 py-1.5 font-mono2 text-xs text-gray-500">

                Lista

            </button>

        </div>

    </div>

</div>


{{-- ============================================================= --}}
{{-- KANBAN --}}
{{-- ============================================================= --}}

<div
    id="vista-kanban"
    class="vista-panel mt-6 overflow-x-auto pb-4">

    <div class="grid min-w-[1200px] grid-cols-5 gap-4">

        @php

            $estados = [
                'Pendiente' => 'bg-gray-500',
                'En progreso' => 'bg-white',
                'En revisión' => 'bg-gray-400',
                'Completado' => 'bg-white',
                'Cancelado' => 'bg-[#d61f2c]',
            ];

        @endphp


        @foreach($estados as $estado => $dot)

            @php
                $tareasEstado = $tareas->where('estado', $estado);
            @endphp

            <div
                data-columna-estado="{{ $estado }}"
                class="min-w-0">

                <div class="flex items-center justify-between">

                    <div class="flex items-center gap-2">

                        <span class="h-2 w-2 rounded-full {{ $dot }}"></span>

                        <span class="font-mono2 text-xs font-bold uppercase text-gray-300">
                            {{ $estado }}
                        </span>

                    </div>

                    <span
                        class="contador-estado rounded-full bg-white/5 px-2 py-1 font-mono2 text-[10px] text-gray-500">

                        {{ $tareasEstado->count() }}

                    </span>

                </div>


                <div class="mt-3 space-y-3">

                    @forelse($tareasEstado as $tarea)

                        @php

                            $nombreProyecto =
                                optional($tarea->proyecto)->nombre
                                ?? 'Sin proyecto';

                            $prioridadColor = match($tarea->prioridad) {

                                'Alta' => 'bg-[#ff5b5b]',
                                'Media' => 'bg-gray-400',
                                default => 'bg-gray-700',

                            };

                            $fecha = $tarea->fecha_limite
                                ? \Carbon\Carbon::parse($tarea->fecha_limite)->format('d/m/Y')
                                : 'Sin fecha';

                        @endphp


                        <div
                            class="tarea-item tarea-card rounded-xl border border-white/10 bg-[#0f0f11] p-4"
                            data-titulo="{{ strtolower($tarea->titulo) }}"
                            data-proyecto="{{ strtolower($nombreProyecto) }}"
                            data-prioridad="{{ $tarea->prioridad }}">

                            <div class="flex items-center justify-between">

                                <span
                                    class="max-w-[80%] truncate rounded-full bg-white/5 px-2 py-1 font-mono2 text-[10px] text-gray-400">

                                    {{ $nombreProyecto }}

                                </span>

                                <span
                                    class="h-2 w-2 rounded-full {{ $prioridadColor }}">
                                </span>

                            </div>


                            <p class="mt-3 text-sm font-bold text-white">
                                {{ $tarea->titulo }}
                            </p>


                            @if($tarea->descripcion)

                                <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-gray-500">
                                    {{ $tarea->descripcion }}
                                </p>

                            @endif


                            <div class="mt-4 flex items-center justify-between">

                                <span class="font-mono2 text-[10px] text-gray-500">
                                    {{ $fecha }}
                                </span>

                                <span class="font-mono2 text-[10px] text-gray-600">
                                    #{{ $tarea->id }}
                                </span>

                            </div>


                            {{-- ACCIONES --}}
                            <div class="mt-4 flex gap-2 border-t border-white/5 pt-3">

                                {{-- VER --}}
                                <button
                                    type="button"
                                    onclick="abrirDetalleTarea(this)"
                                    data-id="{{ $tarea->id }}"
                                    data-titulo="{{ $tarea->titulo }}"
                                    data-descripcion="{{ $tarea->descripcion ?? 'Sin descripción' }}"
                                    data-proyecto="{{ $nombreProyecto }}"
                                    data-prioridad="{{ $tarea->prioridad }}"
                                    data-estado="{{ $tarea->estado }}"
                                    data-fecha-inicio="{{ $tarea->fecha_inicio ? \Carbon\Carbon::parse($tarea->fecha_inicio)->format('d/m/Y') : 'Sin fecha' }}"
                                    data-fecha-limite="{{ $tarea->fecha_limite ? \Carbon\Carbon::parse($tarea->fecha_limite)->format('d/m/Y') : 'Sin fecha' }}"
                                    class="flex-1 rounded-lg border border-white/10 px-2 py-2 text-center font-mono2 text-[10px] text-gray-400 hover:bg-white/5 hover:text-white">

                                    Ver

                                </button>


                                {{-- EDITAR --}}
                                <button
                                    type="button"
                                    onclick="abrirEditarTarea(this)"
                                    data-id="{{ $tarea->id }}"
                                    data-proyecto-id="{{ $tarea->proyecto_id }}"
                                    data-titulo="{{ $tarea->titulo }}"
                                    data-descripcion="{{ $tarea->descripcion ?? '' }}"
                                    data-prioridad="{{ $tarea->prioridad }}"
                                    data-estado="{{ $tarea->estado }}"
                                    data-fecha-inicio="{{ $tarea->fecha_inicio ? \Carbon\Carbon::parse($tarea->fecha_inicio)->format('Y-m-d') : '' }}"
                                    data-fecha-limite="{{ $tarea->fecha_limite ? \Carbon\Carbon::parse($tarea->fecha_limite)->format('Y-m-d') : '' }}"
                                    class="flex-1 rounded-lg border border-white/10 px-2 py-2 text-center font-mono2 text-[10px] text-gray-400 hover:bg-white/5 hover:text-white">

                                    Editar

                                </button>


                                {{-- ELIMINAR --}}
                                <form
                                    method="POST"
                                    action="{{ route('tareas.destroy', $tarea) }}"
                                    class="flex-1"
                                    onsubmit="return confirmarEliminar()">

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="w-full rounded-lg border border-[#d61f2c]/20 bg-[#d61f2c]/10 px-2 py-2 font-mono2 text-[10px] text-[#ff5b5b] hover:bg-[#d61f2c]/20">

                                        Eliminar

                                    </button>

                                </form>

                            </div>

                        </div>

                    @empty

                        <div class="rounded-xl border border-dashed border-white/10 px-4 py-6 text-center">

                            <p class="font-mono2 text-[11px] text-gray-600">
                                Sin tareas
                            </p>

                        </div>

                    @endforelse

                </div>

            </div>

        @endforeach

    </div>

</div>


{{-- ============================================================= --}}
{{-- LISTA --}}
{{-- ============================================================= --}}

<div
    id="vista-lista"
    class="vista-panel mt-6 hidden">

    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-[#0f0f11] p-5">

        <table class="w-full min-w-[900px] text-left text-sm">

            <thead>

                <tr class="font-mono2 text-[11px] uppercase text-gray-600">

                    <th class="pb-4">Tarea</th>
                    <th class="pb-4">Proyecto</th>
                    <th class="pb-4">Prioridad</th>
                    <th class="pb-4">Estado</th>
                    <th class="pb-4">Fecha límite</th>
                    <th class="pb-4 text-right">Acciones</th>

                </tr>

            </thead>


            <tbody class="divide-y divide-white/5">

                @forelse($tareas as $tarea)

                    @php

                        $nombreProyecto =
                            optional($tarea->proyecto)->nombre
                            ?? 'Sin proyecto';

                        $estadoClases = [

                            'Pendiente' =>
                                'bg-white/5 text-gray-300 border border-white/10',

                            'En progreso' =>
                                'bg-white/5 text-gray-300 border border-white/10',

                            'En revisión' =>
                                'bg-white/5 text-gray-300 border border-white/10',

                            'Completado' =>
                                'bg-white text-black',

                            'Cancelado' =>
                                'bg-[#d61f2c]/10 text-[#ff5b5b] border border-[#d61f2c]/20',

                        ];

                    @endphp


                    <tr
                        class="tarea-item"
                        data-titulo="{{ strtolower($tarea->titulo) }}"
                        data-proyecto="{{ strtolower($nombreProyecto) }}"
                        data-prioridad="{{ $tarea->prioridad }}">

                        <td class="py-4">

                            <p class="font-semibold text-white">
                                {{ $tarea->titulo }}
                            </p>

                            @if($tarea->descripcion)

                                <p class="mt-1 max-w-md truncate text-xs text-gray-600">
                                    {{ $tarea->descripcion }}
                                </p>

                            @endif

                        </td>


                        <td class="py-4 text-gray-400">
                            {{ $nombreProyecto }}
                        </td>


                        <td class="py-4">

                            <span
                                class="font-mono2 text-xs
                                {{ $tarea->prioridad === 'Alta'
                                    ? 'font-bold text-[#ff5b5b]'
                                    : 'text-gray-400' }}">

                                {{ $tarea->prioridad }}

                            </span>

                        </td>


                        <td class="py-4">

                            <span
                                class="inline-block rounded-full px-2.5 py-1 font-mono2 text-[10px] font-semibold
                                {{ $estadoClases[$tarea->estado] ?? 'bg-white/5 text-gray-300' }}">

                                {{ $tarea->estado }}

                            </span>

                        </td>


                        <td class="py-4 text-gray-400">

                            {{ $tarea->fecha_limite
                                ? \Carbon\Carbon::parse($tarea->fecha_limite)->format('d/m/Y')
                                : 'Sin fecha' }}

                        </td>


                        <td class="py-4">

                            <div class="flex justify-end gap-2">

                                {{-- VER --}}
                                <button
                                    type="button"
                                    onclick="abrirDetalleTarea(this)"
                                    data-id="{{ $tarea->id }}"
                                    data-titulo="{{ $tarea->titulo }}"
                                    data-descripcion="{{ $tarea->descripcion ?? 'Sin descripción' }}"
                                    data-proyecto="{{ $nombreProyecto }}"
                                    data-prioridad="{{ $tarea->prioridad }}"
                                    data-estado="{{ $tarea->estado }}"
                                    data-fecha-inicio="{{ $tarea->fecha_inicio ? \Carbon\Carbon::parse($tarea->fecha_inicio)->format('d/m/Y') : 'Sin fecha' }}"
                                    data-fecha-limite="{{ $tarea->fecha_limite ? \Carbon\Carbon::parse($tarea->fecha_limite)->format('d/m/Y') : 'Sin fecha' }}"
                                    class="rounded-lg border border-white/10 px-3 py-2 font-mono2 text-[10px] text-gray-400 hover:bg-white/5 hover:text-white">

                                    Ver

                                </button>


                                {{-- EDITAR --}}
                                <button
                                    type="button"
                                    onclick="abrirEditarTarea(this)"
                                    data-id="{{ $tarea->id }}"
                                    data-proyecto-id="{{ $tarea->proyecto_id }}"
                                    data-titulo="{{ $tarea->titulo }}"
                                    data-descripcion="{{ $tarea->descripcion ?? '' }}"
                                    data-prioridad="{{ $tarea->prioridad }}"
                                    data-estado="{{ $tarea->estado }}"
                                    data-fecha-inicio="{{ $tarea->fecha_inicio ? \Carbon\Carbon::parse($tarea->fecha_inicio)->format('Y-m-d') : '' }}"
                                    data-fecha-limite="{{ $tarea->fecha_limite ? \Carbon\Carbon::parse($tarea->fecha_limite)->format('Y-m-d') : '' }}"
                                    class="rounded-lg border border-white/10 px-3 py-2 font-mono2 text-[10px] text-gray-400 hover:bg-white/5 hover:text-white">

                                    Editar

                                </button>


                                {{-- ELIMINAR --}}
                                <form
                                    method="POST"
                                    action="{{ route('tareas.destroy', $tarea) }}"
                                    onsubmit="return confirmarEliminar()">

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="rounded-lg border border-[#d61f2c]/20 bg-[#d61f2c]/10 px-3 py-2 font-mono2 text-[10px] text-[#ff5b5b] hover:bg-[#d61f2c]/20">

                                        Eliminar

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="py-12 text-center text-gray-600">

                            No hay tareas registradas.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>


{{-- SIN RESULTADOS --}}
<div
    id="sinResultados"
    class="mt-5 hidden rounded-xl border border-dashed border-white/10 bg-[#0f0f11] px-5 py-10 text-center">

    <p class="font-mono2 text-sm text-gray-500">
        No se encontraron tareas con esos filtros.
    </p>

</div>


</main>

</div>


{{-- ============================================================= --}}
{{-- MODAL DETALLE --}}
{{-- ============================================================= --}}

<div
    id="modalDetalle"
    class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/80 px-4 backdrop-blur-sm">

    <div
        class="relative max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-[#0f0f11] shadow-2xl">

        <div class="flex items-start justify-between border-b border-white/10 px-6 py-5">

            <div>

                <p class="font-mono2 text-[10px] uppercase tracking-wider text-gray-600">
                    / tarea
                </p>

                <h2
                    id="detalleTitulo"
                    class="font-display mt-1 text-xl font-bold text-white">

                    Tarea

                </h2>

            </div>

            <button
                type="button"
                onclick="cerrarDetalleTarea()"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-400 transition hover:bg-white/5 hover:text-white">

                ✕

            </button>

        </div>


        <div class="modal-scroll max-h-[calc(90vh-90px)] overflow-y-auto px-6 py-6">

            <div>

                <p class="font-mono2 text-[10px] uppercase text-gray-600">
                    Descripción
                </p>

                <p
                    id="detalleDescripcion"
                    class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-300">

                    Sin descripción

                </p>

            </div>


            <div class="mt-6 grid gap-4 sm:grid-cols-2">

                <div class="rounded-xl border border-white/10 bg-black/30 p-4">

                    <p class="font-mono2 text-[10px] uppercase text-gray-600">
                        Proyecto
                    </p>

                    <p
                        id="detalleProyecto"
                        class="mt-2 text-sm font-semibold text-white">
                        -
                    </p>

                </div>


                <div class="rounded-xl border border-white/10 bg-black/30 p-4">

                    <p class="font-mono2 text-[10px] uppercase text-gray-600">
                        Prioridad
                    </p>

                    <p
                        id="detallePrioridad"
                        class="mt-2 text-sm font-semibold text-white">
                        -
                    </p>

                </div>


                <div class="rounded-xl border border-white/10 bg-black/30 p-4">

                    <p class="font-mono2 text-[10px] uppercase text-gray-600">
                        Estado
                    </p>

                    <p
                        id="detalleEstado"
                        class="mt-2 text-sm font-semibold text-white">
                        -
                    </p>

                </div>


                <div class="rounded-xl border border-white/10 bg-black/30 p-4">

                    <p class="font-mono2 text-[10px] uppercase text-gray-600">
                        ID
                    </p>

                    <p
                        id="detalleId"
                        class="mt-2 font-mono2 text-sm font-semibold text-gray-300">
                        #-
                    </p>

                </div>


                <div class="rounded-xl border border-white/10 bg-black/30 p-4">

                    <p class="font-mono2 text-[10px] uppercase text-gray-600">
                        Fecha de inicio
                    </p>

                    <p
                        id="detalleFechaInicio"
                        class="mt-2 text-sm font-semibold text-gray-300">
                        -
                    </p>

                </div>


                <div class="rounded-xl border border-white/10 bg-black/30 p-4">

                    <p class="font-mono2 text-[10px] uppercase text-gray-600">
                        Fecha límite
                    </p>

                    <p
                        id="detalleFechaLimite"
                        class="mt-2 text-sm font-semibold text-gray-300">
                        -
                    </p>

                </div>

            </div>


            <div class="mt-6 flex justify-end border-t border-white/10 pt-5">

                <button
                    type="button"
                    onclick="cerrarDetalleTarea()"
                    class="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

                    Cerrar

                </button>

            </div>

        </div>

    </div>

</div>


{{-- ============================================================= --}}
{{-- MODAL EDITAR --}}
{{-- ============================================================= --}}

<div
    id="modalEditar"
    class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/80 px-4 py-6 backdrop-blur-sm">


    <div
        class="relative max-h-[92vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-[#0f0f11] shadow-2xl">


        {{-- HEADER --}}
        <div class="flex items-start justify-between border-b border-white/10 px-6 py-5">

            <div>

                <p class="font-mono2 text-[10px] uppercase tracking-wider text-gray-600">
                    / editar
                </p>

                <h2 class="font-display mt-1 text-xl font-bold text-white">
                    Editar tarea
                </h2>

                <p class="mt-1 font-mono2 text-[10px] text-gray-600">
                    Modifica la información de la tarea
                </p>

            </div>


            <button
                type="button"
                onclick="cerrarEditarTarea()"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-400 transition hover:bg-white/5 hover:text-white">

                ✕

            </button>

        </div>


        {{-- CONTENIDO --}}
        <div class="modal-scroll max-h-[calc(92vh-105px)] overflow-y-auto px-6 py-6">


            <form
                id="formEditarTarea"
                method="POST"
                class="space-y-5">

                @csrf

                @method('PUT')


                {{-- PROYECTO --}}
                <div>

                    <label
                        class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">

                        Proyecto *

                    </label>


                    <select
                        id="editarProyecto"
                        name="proyecto_id"
                        required
                        class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                        <option value="">
                            Selecciona un proyecto
                        </option>


                        @foreach($proyectos as $proyecto)

                            <option value="{{ $proyecto->id }}">

                                {{ $proyecto->nombre }}

                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- TITULO --}}
                <div>

                    <label
                        class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">

                        Título *

                    </label>


                    <input
                        id="editarTitulo"
                        type="text"
                        name="titulo"
                        required
                        maxlength="255"
                        class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]/50">

                </div>


                {{-- DESCRIPCION --}}
                <div>

                    <label
                        class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">

                        Descripción

                    </label>


                    <textarea
                        id="editarDescripcion"
                        name="descripcion"
                        rows="5"
                        placeholder="Describe la tarea..."
                        class="w-full resize-none rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white placeholder-gray-600 outline-none focus:border-[#d61f2c]/50"></textarea>

                </div>


                {{-- PRIORIDAD / ESTADO --}}
                <div class="grid gap-5 md:grid-cols-2">


                    {{-- PRIORIDAD --}}
                    <div>

                        <label
                            class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">

                            Prioridad *

                        </label>


                        <select
                            id="editarPrioridad"
                            name="prioridad"
                            required
                            class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                            <option value="Alta">
                                Alta
                            </option>

                            <option value="Media">
                                Media
                            </option>

                            <option value="Baja">
                                Baja
                            </option>

                        </select>

                    </div>


                    {{-- ESTADO --}}
                    <div>

                        <label
                            class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">

                            Estado *

                        </label>


                        <select
                            id="editarEstado"
                            name="estado"
                            required
                            class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                            <option value="Pendiente">
                                Pendiente
                            </option>

                            <option value="En progreso">
                                En progreso
                            </option>

                            <option value="En revisión">
                                En revisión
                            </option>

                            <option value="Completado">
                                Completado
                            </option>

                            <option value="Cancelado">
                                Cancelado
                            </option>

                        </select>

                    </div>

                </div>


                {{-- FECHAS --}}
                <div class="grid gap-5 md:grid-cols-2">


                    {{-- FECHA INICIO --}}
                    <div>

                        <label
                            class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">

                            Fecha de inicio

                        </label>


                        <input
                            id="editarFechaInicio"
                            type="date"
                            name="fecha_inicio"
                            class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                    </div>


                    {{-- FECHA LIMITE --}}
                    <div>

                        <label
                            class="mb-2 block font-mono2 text-xs font-semibold text-gray-400">

                            Fecha límite

                        </label>


                        <input
                            id="editarFechaLimite"
                            type="date"
                            name="fecha_limite"
                            class="w-full rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">

                    </div>

                </div>


                {{-- BOTONES --}}
                <div class="flex justify-end gap-3 border-t border-white/10 pt-5">


                    <button
                        type="button"
                        onclick="cerrarEditarTarea()"
                        class="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">

                        Cancelar

                    </button>


                    <button
                        type="submit"
                        class="rounded-xl bg-[#d61f2c] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#b8161f]">

                        Guardar cambios

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


{{-- ============================================================= --}}
{{-- JAVASCRIPT --}}
{{-- ============================================================= --}}

<script>


/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

function toggleSidebar()
{
    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.toggle('-translate-x-full');

    overlay.classList.toggle('hidden');
}



/*
|--------------------------------------------------------------------------
| FORMULARIO NUEVA TAREA
|--------------------------------------------------------------------------
*/

function abrirFormulario()
{
    const formulario =
        document.getElementById('formularioTarea');

    formulario.classList.remove('hidden');

    formulario.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}


function cerrarFormulario()
{
    document
        .getElementById('formularioTarea')
        .classList.add('hidden');
}



/*
|--------------------------------------------------------------------------
| CAMBIAR VISTA
|--------------------------------------------------------------------------
*/

function mostrarVista(id, btn)
{
    document
        .querySelectorAll('.vista-panel')
        .forEach(panel => {

            panel.classList.add('hidden');

        });


    document
        .getElementById('vista-' + id)
        .classList.remove('hidden');


    document
        .querySelectorAll('.vista-btn')
        .forEach(boton => {

            boton.classList.remove(
                'bg-[#d61f2c]',
                'text-white',
                'font-bold'
            );

            boton.classList.add(
                'text-gray-500'
            );

        });


    btn.classList.add(
        'bg-[#d61f2c]',
        'text-white',
        'font-bold'
    );


    btn.classList.remove(
        'text-gray-500'
    );
}



/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

function filtrarTareas()
{
    const buscador =
        document.getElementById('buscador');

    const filtroProyecto =
        document.getElementById('filtroProyecto');

    const filtroPrioridad =
        document.getElementById('filtroPrioridad');


    if (!buscador || !filtroProyecto || !filtroPrioridad) {
        return;
    }


    const texto =
        buscador.value
            .toLowerCase()
            .trim();


    const proyecto =
        filtroProyecto.value
            .toLowerCase()
            .trim();


    const prioridad =
        filtroPrioridad.value
            .trim();


    const tareas =
        document.querySelectorAll('.tarea-item');


    let visibles = 0;


    tareas.forEach(tarea => {

        const titulo =
            tarea.dataset.titulo || '';


        const proyectoTarea =
            tarea.dataset.proyecto || '';


        const prioridadTarea =
            tarea.dataset.prioridad || '';


        const coincideTexto =
            texto === '' ||
            titulo.includes(texto) ||
            proyectoTarea.includes(texto);


        const coincideProyecto =
            proyecto === '' ||
            proyectoTarea === proyecto;


        const coincidePrioridad =
            prioridad === '' ||
            prioridadTarea === prioridad;


        const mostrar =
            coincideTexto &&
            coincideProyecto &&
            coincidePrioridad;


        if (mostrar) {

            tarea.classList.remove('hidden');

            visibles++;

        } else {

            tarea.classList.add('hidden');

        }

    });


    document
        .querySelectorAll('[data-columna-estado]')
        .forEach(columna => {

            const tareasColumna =
                columna.querySelectorAll(
                    '.tarea-item:not(.hidden)'
                );


            const contador =
                columna.querySelector('.contador-estado');


            if (contador) {

                contador.textContent =
                    tareasColumna.length;

            }

        });


    const sinResultados =
        document.getElementById('sinResultados');


    if (sinResultados) {

        if (visibles === 0) {

            sinResultados.classList.remove('hidden');

        } else {

            sinResultados.classList.add('hidden');

        }

    }
}



/*
|--------------------------------------------------------------------------
| MODAL DETALLE
|--------------------------------------------------------------------------
*/

function abrirDetalleTarea(button)
{
    const modal =
        document.getElementById('modalDetalle');


    document.getElementById('detalleId').textContent =
        '#' + (button.dataset.id || '-');


    document.getElementById('detalleTitulo').textContent =
        button.dataset.titulo || 'Sin título';


    document.getElementById('detalleDescripcion').textContent =
        button.dataset.descripcion || 'Sin descripción';


    document.getElementById('detalleProyecto').textContent =
        button.dataset.proyecto || 'Sin proyecto';


    document.getElementById('detallePrioridad').textContent =
        button.dataset.prioridad || '-';


    document.getElementById('detalleEstado').textContent =
        button.dataset.estado || '-';


    document.getElementById('detalleFechaInicio').textContent =
        button.dataset.fechaInicio || 'Sin fecha';


    document.getElementById('detalleFechaLimite').textContent =
        button.dataset.fechaLimite || 'Sin fecha';


    modal.classList.remove('hidden');

    modal.classList.add('flex');

    document.body.classList.add('overflow-hidden');
}


function cerrarDetalleTarea()
{
    const modal =
        document.getElementById('modalDetalle');


    modal.classList.add('hidden');

    modal.classList.remove('flex');

    document.body.classList.remove('overflow-hidden');
}



/*
|--------------------------------------------------------------------------
| MODAL EDITAR
|--------------------------------------------------------------------------
*/

function abrirEditarTarea(button)
{
    const modal =
        document.getElementById('modalEditar');


    const formulario =
        document.getElementById('formEditarTarea');


    /*
    |--------------------------------------------------------------------------
    | URL DEL UPDATE
    |--------------------------------------------------------------------------
    |
    | Se genera una URL base utilizando una tarea ficticia.
    | Después reemplazamos el ID por el de la tarea seleccionada.
    |
    */

    let url =
        "{{ route('tareas.update', ['tarea' => '__ID__']) }}";


    url =
        url.replace(
            '__ID__',
            button.dataset.id
        );


    formulario.action = url;


    /*
    |--------------------------------------------------------------------------
    | CARGAR DATOS
    |--------------------------------------------------------------------------
    */

    document.getElementById('editarProyecto').value =
        button.dataset.proyectoId || '';


    document.getElementById('editarTitulo').value =
        button.dataset.titulo || '';


    document.getElementById('editarDescripcion').value =
        button.dataset.descripcion || '';


    document.getElementById('editarPrioridad').value =
        button.dataset.prioridad || 'Media';


    document.getElementById('editarEstado').value =
        button.dataset.estado || 'Pendiente';


    document.getElementById('editarFechaInicio').value =
        button.dataset.fechaInicio || '';


    document.getElementById('editarFechaLimite').value =
        button.dataset.fechaLimite || '';


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR MODAL
    |--------------------------------------------------------------------------
    */

    modal.classList.remove('hidden');

    modal.classList.add('flex');

    document.body.classList.add('overflow-hidden');
}



function cerrarEditarTarea()
{
    const modal =
        document.getElementById('modalEditar');


    modal.classList.add('hidden');

    modal.classList.remove('flex');

    document.body.classList.remove('overflow-hidden');
}



/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event)
    {
        if (event.key === 'Escape') {

            cerrarDetalleTarea();

            cerrarEditarTarea();

        }
    }
);



/*
|--------------------------------------------------------------------------
| CERRAR DETALLE HACIENDO CLICK EN FONDO
|--------------------------------------------------------------------------
*/

document
    .getElementById('modalDetalle')
    .addEventListener(
        'click',
        function(event)
        {
            if (event.target === this) {

                cerrarDetalleTarea();

            }
        }
    );



/*
|--------------------------------------------------------------------------
| CERRAR EDITAR HACIENDO CLICK EN FONDO
|--------------------------------------------------------------------------
*/

document
    .getElementById('modalEditar')
    .addEventListener(
        'click',
        function(event)
        {
            if (event.target === this) {

                cerrarEditarTarea();

            }
        }
    );



/*
|--------------------------------------------------------------------------
| ELIMINAR
|--------------------------------------------------------------------------
*/

function confirmarEliminar()
{
    return confirm(
        '¿Estás seguro de que deseas eliminar esta tarea? Esta acción no se puede deshacer.'
    );
}



/*
|--------------------------------------------------------------------------
| INICIO
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function()
    {
        filtrarTareas();


        @if($errors->any())

            abrirFormulario();

        @endif

    }
);

</script>


</body>

</html>