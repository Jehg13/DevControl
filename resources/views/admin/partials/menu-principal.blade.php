<style>
    aside > nav:not(.devcontrol-main-navigation),
    aside > form:not(.devcontrol-main-logout) {
        display: none !important;
    }
</style>

<nav class="devcontrol-main-navigation space-y-1">
    @php
        $menuItems = [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
            ['route' => 'asistente.index', 'label' => 'Asistente IA', 'icon' => '<path d="M4 12h16M12 4v16M7 7l10 10M17 7 7 17"/>'],
            ['route' => 'proyectos.index', 'label' => 'Proyectos', 'icon' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>'],
            ['route' => 'tareas.index', 'label' => 'Tareas', 'icon' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8 12 2.5 2.5L16 9"/>'],
            ['route' => 'bugs.index', 'label' => 'Bugs', 'icon' => '<rect x="8" y="7" width="8" height="11" rx="4"/><path d="M8 10H4M16 10h4M8 15H4M16 15h4M10 7V5a2 2 0 0 1 4 0v2"/>'],
            ['route' => 'actualizaciones', 'label' => 'Actualizaciones', 'icon' => '<circle cx="6" cy="12" r="2.3"/><circle cx="18" cy="6" r="2.3"/><circle cx="18" cy="18" r="2.3"/><path d="m8.1 11 7.8-4M8.1 13l7.8 4"/>'],
            ['route' => 'monitoreo', 'label' => 'Monitoreo', 'icon' => '<path d="M4 19V9M10 19V5M16 19v-8M22 19V3M2 19h21"/>'],
            ['route' => 'incidentes', 'label' => 'Incidentes', 'icon' => '<path d="m12 3-8.5 4.5v5c0 4.5 3.6 7.2 8.5 8.5 4.9-1.3 8.5-4 8.5-8.5v-5L12 3Z"/><path d="M12 8v4M12 16h.01"/>'],
            ['route' => 'usuarios', 'label' => 'Usuarios', 'icon' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 11a3 3 0 1 0 0-6M17 14a5 5 0 0 1 4 6"/>'],
            ['route' => 'actividad', 'label' => 'Actividad', 'icon' => '<path d="M4 19V5M4 19h16M7 15l3-3 3 2 5-6"/>'],
            ['route' => 'archivos', 'label' => 'Archivos', 'icon' => '<path d="M4 7l4-3h5l4 3h3v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7Z"/><path d="M9 12h6"/>'],
            ['route' => 'notificaciones', 'label' => 'Notificaciones', 'icon' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>'],
            ['route' => 'configuracion', 'label' => 'Configuración', 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.7 1.7-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-2.4v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1L8 17l.1-.1A1.7 1.7 0 0 0 8.4 15a1.7 1.7 0 0 0-1.5-1H6v-2.4h.9a1.7 1.7 0 0 0 1.5-1A1.7 1.7 0 0 0 8.1 9L8 8.9l1.7-1.7.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5v-.2h2.4v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 9l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2v2.4h-.2a1.7 1.7 0 0 0-1.5.6Z"/>'],
        ];
    @endphp

    @foreach($menuItems as $item)
        <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold transition hover:bg-white/5 hover:text-white {{ request()->routeIs($item['route']) ? 'bg-[#d61f2c] font-bold text-white' : 'text-gray-400' }}">
            <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>

<form method="POST" action="{{ route('logout') }}" class="devcontrol-main-logout mt-6">
    @csrf
    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
        <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M16 17l5-5-5-5M21 12H9"/></svg>
        Cerrar sesión
    </button>
</form>
