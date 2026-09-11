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
                <span class="rounded-full border border-yellow-400/20 bg-yellow-400/10 px-3 py-2 font-mono2 text-xs text-yellow-300">Vista previa</span>
            </header>

            <section class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach([
                    ['GitHub', 'Repositorios, ramas y sincronización de cambios.', '⌘', 'Conexión pendiente', 'text-gray-400', 'Conectar después'],
                    ['Conexiones', 'Entornos, servidores y fuentes externas de monitoreo.', '⌁', 'Sin conexiones', 'text-yellow-300', 'Configurar después'],
                    ['Notificaciones', 'Alertas de bugs, incidentes, despliegues y tareas.', '◌', 'Preferencias básicas', 'text-cyan-300', 'Editar preferencias'],
                    ['Inteligencia artificial', 'Comportamiento, contexto y capacidades del asistente.', '✦', 'Asistente activo', 'text-[#ff6b6b]', 'Ver capacidades'],
                    ['Preferencias', 'Personaliza la experiencia visual y operativa.', '⚙', 'Configuración inicial', 'text-purple-300', 'Personalizar'],
                    ['Seguridad', 'Sesión, permisos y controles de acceso.', '◈', 'Protección activa', 'text-emerald-300', 'Revisar seguridad'],
                ] as $setting)
                    <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5 transition hover:border-white/20">
                        <div class="flex items-start justify-between gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/5 text-2xl {{ $setting[4] }}">{{ $setting[2] }}</span><span class="rounded-full bg-white/5 px-2.5 py-1 font-mono2 text-[9px] text-gray-500">{{ $setting[3] }}</span></div>
                        <h2 class="mt-5 font-display text-lg font-bold">{{ $setting[0] }}</h2>
                        <p class="mt-2 min-h-10 text-sm leading-relaxed text-gray-500">{{ $setting[1] }}</p>
                        <button type="button" class="mt-5 w-full rounded-xl border border-white/10 px-4 py-3 text-xs font-bold text-gray-300 transition hover:bg-white/5 hover:text-white">{{ $setting[5] }} <span class="ml-1 text-gray-600">→</span></button>
                    </article>
                @endforeach
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                <article class="rounded-2xl border border-white/10 bg-[#0f0f11] p-5"><div class="flex items-center justify-between"><div><h2 class="font-display text-lg font-bold">Estado de configuración</h2><p class="mt-1 text-xs text-gray-500">Resumen visual de las áreas disponibles.</p></div><span class="font-mono2 text-xs text-yellow-300">4/6 revisadas</span></div><div class="mt-6 h-2 overflow-hidden rounded-full bg-white/5"><div class="h-full w-2/3 rounded-full bg-gradient-to-r from-[#d61f2c] to-[#ff5b5b]"></div></div><div class="mt-4 flex justify-between text-xs text-gray-600"><span>Configuración inicial</span><span>66%</span></div></article>
                <article class="rounded-2xl border border-dashed border-yellow-400/20 bg-yellow-400/5 p-5"><p class="font-mono2 text-[10px] uppercase tracking-widest text-yellow-300">Próximamente</p><h2 class="mt-3 font-display text-lg font-bold">Conecta tu ecosistema</h2><p class="mt-2 text-sm leading-relaxed text-yellow-100/60">Cuando conectes GitHub y los entornos de tus proyectos, esta sección permitirá administrar esas integraciones de forma segura.</p></article>
            </section>
        </main>
    </div>
    @include('admin.partials.asistente-flotante')
    <script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}</script>
</body>
</html>
