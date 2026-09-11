<a href="{{ route('monitoreo') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold transition hover:bg-white/5 hover:text-white {{ request()->routeIs('monitoreo') ? 'bg-[#d61f2c] font-bold text-white' : 'text-gray-400' }}">
    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V9M10 19V5M16 19v-8M22 19V3"/><path d="M2 19h21"/></svg>
    Monitoreo
</a>
<a href="{{ route('incidentes') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold transition hover:bg-white/5 hover:text-white {{ request()->routeIs('incidentes') ? 'bg-[#d61f2c] font-bold text-white' : 'text-gray-400' }}">
    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3.5 7.5v5c0 4.5 3.6 7.2 8.5 8.5 4.9-1.3 8.5-4 8.5-8.5v-5L12 3Z"/><path d="M12 8v4M12 16h.01"/></svg>
    Incidentes
</a>
<a href="{{ route('notificaciones') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-white/5 hover:text-white">
    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
    Notificaciones
</a>
<a href="{{ route('configuracion') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold transition hover:bg-white/5 hover:text-white {{ request()->routeIs('configuracion') ? 'bg-[#d61f2c] font-bold text-white' : 'text-gray-400' }}">
    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.7 1.7-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-2.4v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1L8 17l.1-.1A1.7 1.7 0 0 0 8.4 15a1.7 1.7 0 0 0-1.5-1H6v-2.4h.9a1.7 1.7 0 0 0 1.5-1A1.7 1.7 0 0 0 8.1 9L8 8.9l1.7-1.7.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5v-.2h2.4v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 9l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2v2.4h-.2a1.7 1.7 0 0 0-1.5.6Z"/></svg>
    Configuración
</a>
<a href="{{ route('usuarios') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold transition hover:bg-white/5 hover:text-white {{ request()->routeIs('usuarios') ? 'bg-[#d61f2c] font-bold text-white' : 'text-gray-400' }}">
    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 11a3 3 0 1 0 0-6M17 14a5 5 0 0 1 4 6"/></svg>
    Usuarios
</a>
<a href="{{ route('actividad') }}" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-semibold transition hover:bg-white/5 hover:text-white {{ request()->routeIs('actividad') ? 'bg-[#d61f2c] font-bold text-white' : 'text-gray-400' }}">
    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5M4 19h16"/><path d="m7 15 3-3 3 2 5-6"/></svg>
    Actividad
</a>
