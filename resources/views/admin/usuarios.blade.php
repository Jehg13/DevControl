<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Usuarios</title>
    <style>.font-display { font-family: 'Space Grotesk', sans-serif; }.font-mono2 { font-family: 'JetBrains Mono', monospace; }</style>
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
                <img src="{{ asset('favicon.ico') }}" alt="Perfil" class="h-10 w-10 shrink-0 rounded-full border border-white/10 object-cover">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate font-mono2 text-[11px] text-gray-500">{{ auth()->user()->rol }}</p>
                </div>
            </div>
            @include('admin.partials.menu-principal')
            <nav class="space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('asistente.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <span class="flex h-5 w-5 items-center justify-center rounded border border-[#d61f2c]/60 font-mono2 text-[10px] text-[#ff5b5b]">&gt;_</span>
                    Asistente IA
                </a>
                <a href="{{ route('proyectos.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                    Proyectos
                </a>
                <a href="{{ route('tareas.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                    Tareas
                </a>
                <a href="{{ route('bugs.index') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="7" width="8" height="11" rx="4"/><path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 0 1 4 0v2"/></svg>
                    Bugs
                </a>
                <a href="{{ route('actualizaciones') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="6" cy="12" r="2.3"/><circle cx="18" cy="6" r="2.3"/><circle cx="18" cy="18" r="2.3"/><path d="m8.1 11 7.8-4M8.1 13l7.8 4"/></svg>
                    Actualizaciones
                </a>
                <a href="{{ route('archivos') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7l4-3h5l4 3h3v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7Z"/><path d="M9 12h6"/></svg>
                    Archivos
                </a>
                @include('admin.partials.navegacion-modulos')
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>
                    Cerrar sesión
                </button>
            </form>
        </aside>

    <main class="min-w-0 flex-1 px-5 py-6 sm:px-8 lg:px-10">
        <button type="button" onclick="toggleSidebar()" class="mb-5 flex h-10 w-10 items-center justify-center rounded-full border border-white/10 text-white hover:bg-white/5 lg:hidden" aria-label="Abrir navegación">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <header class="flex flex-col gap-4 border-b border-white/10 pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-mono2 text-xs text-gray-500">~ / usuarios</p>
                <h1 class="mt-2 font-display text-3xl font-bold">Usuarios</h1>
                <p class="mt-2 text-sm text-gray-500">Administra las cuentas con acceso a DevControl.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-white/10 px-4 py-2.5 font-mono2 text-xs text-gray-400 hover:bg-white/5 hover:text-white">Volver al dashboard</a>
        </header>

        @if(session('success'))
            <div class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if(session('error') || $errors->any())
            <div class="mt-6 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm text-red-300">{{ session('error') ?: $errors->first() }}</div>
        @endif

        <section class="mt-6 grid gap-6 lg:grid-cols-[320px_1fr]">
            <form method="POST" action="{{ route('usuarios.store') }}" class="h-fit rounded-2xl border border-white/10 bg-[#0f0f11] p-5">
                @csrf
                <h2 class="font-display text-lg font-bold">Nuevo usuario</h2>
                <div class="mt-5 space-y-4">
                    <input name="name" required value="{{ old('name') }}" placeholder="Nombre completo" class="w-full rounded-xl border border-white/10 bg-black px-3 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">
                    <input name="email" required type="email" value="{{ old('email') }}" placeholder="correo@ejemplo.com" class="w-full rounded-xl border border-white/10 bg-black px-3 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">
                    <select name="rol" required class="w-full rounded-xl border border-white/10 bg-black px-3 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">
                        <option value="admin">Administrador</option>
                        <option value="desarrollador">Desarrollador</option>
                        <option value="observador">Observador</option>
                    </select>
                    <input name="password" required type="password" placeholder="Contraseña (mínimo 8)" class="w-full rounded-xl border border-white/10 bg-black px-3 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">
                    <input name="password_confirmation" required type="password" placeholder="Confirmar contraseña" class="w-full rounded-xl border border-white/10 bg-black px-3 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">
                    <button class="w-full rounded-xl bg-[#d61f2c] px-4 py-3 text-sm font-bold text-white hover:bg-[#ef2937]">Crear usuario</button>
                </div>
            </form>

            <div>
                <form method="GET" action="{{ route('usuarios') }}" class="mb-4 flex flex-col gap-3 sm:flex-row">
                    <input name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Buscar por nombre o correo..." class="min-w-0 flex-1 rounded-xl border border-white/10 bg-[#0f0f11] px-4 py-3 text-sm text-white outline-none focus:border-[#d61f2c]/50">
                    <select name="rol" class="rounded-xl border border-white/10 bg-[#0f0f11] px-4 py-3 text-sm text-white outline-none">
                        <option value="">Todos los roles</option>
                        @foreach(['admin' => 'Administrador', 'desarrollador' => 'Desarrollador', 'observador' => 'Observador'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filtros['rol'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl border border-white/10 px-5 py-3 text-sm font-bold text-gray-300 hover:bg-white/5">Filtrar</button>
                </form>
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#0f0f11]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px] text-left">
                            <thead class="border-b border-white/10 font-mono2 text-[10px] uppercase tracking-widest text-gray-600">
                                <tr><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Rol</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Actividad</th><th class="px-5 py-4 text-right">Acciones</th></tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($usuarios as $usuario)
                                    <tr class="align-top">
                                        <td class="px-5 py-4"><p class="font-semibold text-white">{{ $usuario->name }}</p><p class="mt-1 text-xs text-gray-500">{{ $usuario->email }}</p></td>
                                        <td class="px-5 py-4"><span class="rounded-full bg-white/5 px-2.5 py-1 font-mono2 text-[10px] text-gray-300">{{ $usuario->rol }}</span></td>
                                        <td class="px-5 py-4"><span class="rounded-full bg-emerald-400/10 px-2.5 py-1 font-mono2 text-[10px] text-emerald-300">Activo</span><p class="mt-2 font-mono2 text-[10px] text-gray-600">Cuenta habilitada</p></td>
                                        <td class="px-5 py-4 font-mono2 text-xs text-gray-500"><p>Registro: {{ $usuario->created_at?->format('d/m/Y H:i') }}</p><p class="mt-1 text-gray-600">Actualizado: {{ $usuario->updated_at?->format('d/m/Y H:i') }}</p></td>
                                        <td class="px-5 py-4">
                                            <details class="text-right"><summary class="cursor-pointer font-mono2 text-xs text-[#ff5b5b]">Editar</summary>
                                                <form method="POST" action="{{ route('usuarios.update', $usuario) }}" class="mt-3 space-y-2 text-left">
                                                    @csrf @method('PUT')
                                                    <input name="name" required value="{{ $usuario->name }}" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2 text-xs text-white">
                                                    <input name="email" required type="email" value="{{ $usuario->email }}" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2 text-xs text-white">
                                                    <select name="rol" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2 text-xs text-white"><option value="admin" @selected($usuario->rol === 'admin')>Administrador</option><option value="desarrollador" @selected($usuario->rol === 'desarrollador')>Desarrollador</option><option value="observador" @selected($usuario->rol === 'observador')>Observador</option></select>
                                                    <input name="password" type="password" placeholder="Nueva contraseña opcional" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2 text-xs text-white">
                                                    <input name="password_confirmation" type="password" placeholder="Confirmar contraseña" class="w-full rounded-lg border border-white/10 bg-black px-3 py-2 text-xs text-white">
                                                    <button class="w-full rounded-lg bg-white/10 px-3 py-2 text-xs font-bold text-white hover:bg-white/15">Guardar cambios</button>
                                                </form>
                                                @if(!$usuario->is(auth()->user()))
                                                    <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" class="mt-2 text-left" onsubmit="return confirm('¿Eliminar este usuario?')">@csrf @method('DELETE')<button class="w-full rounded-lg border border-red-400/20 px-3 py-2 text-xs text-red-300 hover:bg-red-400/10">Eliminar usuario</button></form>
                                                @endif
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">No hay usuarios que coincidan con el filtro.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
    </div>
    @include('admin.partials.asistente-flotante')
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>
