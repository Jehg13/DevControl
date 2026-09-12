<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Configuración</title>
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
                <div><p class="font-mono2 text-xs text-gray-500">~ / configuracion</p><h1 class="mt-2 font-display text-3xl font-bold">Configuración</h1><p class="mt-2 text-sm text-gray-500">Administra las conexiones, preferencias y comportamiento de DevControl.</p></div>
                <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 font-mono2 text-xs text-emerald-300">Configuración activa</span>
            </header>

            @if(session('success'))
                <div class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mt-6 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm text-red-300">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('configuracion.update') }}" class="mt-6 grid gap-6 xl:grid-cols-2">
                @csrf
                @method('PUT')
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">Notificaciones</h2>
                    <p class="mt-1 text-sm text-gray-500">Controla las alertas de bugs, incidentes, tareas, commits y actualizaciones.</p>
                    <label class="mt-6 flex items-center gap-3 text-sm text-gray-300">
                        <input type="checkbox" name="alertas_activas" value="1" @checked($configuracion['alertas_activas']) class="h-4 w-4 accent-[#d61f2c]">
                        Enviar alertas por correo
                    </label>
                    <label class="mt-5 block text-xs text-gray-500">Correo receptor</label>
                    <input type="email" name="correo_alertas" value="{{ old('correo_alertas', $configuracion['correo_alertas']) }}" class="mt-2 w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        @foreach(['bugs' => 'Bugs', 'incidentes' => 'Incidentes', 'tareas' => 'Tareas', 'commits' => 'Commits', 'actualizaciones' => 'Actualizaciones'] as $clave => $label)
                            <label class="flex items-center gap-2 text-xs text-gray-400">
                                <input type="checkbox" name="alertas_{{ $clave }}" value="1" @checked($configuracion["alertas_{$clave}"]) class="h-4 w-4 accent-[#d61f2c]">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <label class="mt-5 block text-xs text-gray-500">Prioridad mínima para alertas</label>
                    <select name="prioridad_minima" class="mt-2 w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                        @foreach(['Baja', 'Media', 'Alta'] as $prioridad)
                            <option value="{{ $prioridad }}" @selected($configuracion['prioridad_minima'] === $prioridad)>{{ $prioridad }} o superior</option>
                        @endforeach
                    </select>
                </article>

                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">Preferencias y seguridad</h2>
                    <p class="mt-1 text-sm text-gray-500">Ajustes generales de la sesión y visualización de fechas.</p>
                    <label class="mt-6 block text-xs text-gray-500">Zona horaria</label>
                    <select name="zona_horaria" class="mt-2 w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                        @foreach(['America/Mexico_City', 'America/Monterrey', 'America/Tijuana', 'UTC'] as $zona)
                            <option value="{{ $zona }}" @selected(old('zona_horaria', $configuracion['zona_horaria']) === $zona)>{{ $zona }}</option>
                        @endforeach
                    </select>
                    <label class="mt-5 block text-xs text-gray-500">Duración de sesión (minutos)</label>
                    <input type="number" name="sesion_minutos" min="15" max="1440" value="{{ old('sesion_minutos', $configuracion['sesion_minutos']) }}" class="mt-2 w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                    <label class="mt-5 block text-xs text-gray-500">Intentos máximos de acceso</label>
                    <input type="number" name="intentos_acceso" min="3" max="10" value="{{ old('intentos_acceso', $configuracion['intentos_acceso']) }}" class="mt-2 w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                </article>
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">GitHub</h2>
                    <p class="mt-1 text-sm text-gray-500">Preferencias para sincronización y ramas. El token permanece en .env.</p>
                    <label class="mt-6 block text-xs text-gray-500">Rama predeterminada</label>
                    <input name="github_rama" value="{{ old('github_rama', $configuracion['github_rama']) }}" class="mt-2 w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-sm text-white">
                    <label class="mt-5 flex items-center gap-3 text-sm text-gray-300">
                        <input type="checkbox" name="github_sincronizacion" value="1" @checked($configuracion['github_sincronizacion']) class="h-4 w-4 accent-[#d61f2c]">
                        Permitir sincronización automática
                    </label>
                </article>
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                    <h2 class="font-display text-lg font-bold">Nexus / IA</h2>
                    <p class="mt-1 text-sm text-gray-500">Controla el análisis y las acciones que requieren autorización.</p>
                    <label class="mt-6 flex items-center gap-3 text-sm text-gray-300">
                        <input type="checkbox" name="nexus_confirmacion" value="1" @checked($configuracion['nexus_confirmacion']) class="h-4 w-4 accent-[#d61f2c]">
                        Pedir confirmación antes de modificar datos
                    </label>
                    <label class="mt-4 flex items-center gap-3 text-sm text-gray-300">
                        <input type="checkbox" name="nexus_analisis" value="1" @checked($configuracion['nexus_analisis']) class="h-4 w-4 accent-[#d61f2c]">
                        Permitir análisis automático del proyecto
                    </label>
                </article>
                <button type="submit" class="w-fit rounded-xl bg-[#d61f2c] px-5 py-3 text-sm font-bold text-white hover:bg-[#b8161f]">Guardar configuración</button>
            </form>

            <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5"><div class="flex items-center justify-between"><div><h2 class="font-display text-lg font-bold">Estado de configuración</h2><p class="mt-1 text-xs text-gray-500">Preferencias persistidas en DevControl.</p></div><span class="font-mono2 text-xs text-emerald-300">Activa</span></div><div class="mt-6 h-2 overflow-hidden rounded-full bg-white/5"><div class="h-full w-full rounded-full bg-gradient-to-r from-[#d61f2c] to-[#ff5b5b]"></div></div><div class="mt-4 flex justify-between text-xs text-gray-600"><span>Alertas y preferencias</span><span>100%</span></div></article>
                <article class="rounded-2xl border border-dashed border-blue-400/20 bg-blue-400/5 p-5"><p class="font-mono2 text-[10px] uppercase tracking-widest text-blue-300">Integraciones</p><h2 class="mt-3 font-display text-lg font-bold">GitHub y correo protegidos</h2><p class="mt-2 text-sm leading-relaxed text-blue-100/60">Los tokens y contraseñas permanecen en .env. Esta pantalla solo administra preferencias operativas.</p></article>
            </section>
        </main>
    </div>
    @include('admin.partials.asistente-flotante')
    <script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}</script>
</body>
</html>
