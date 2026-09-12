<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Editar bug</title>
</head>
<body class="min-h-screen bg-black text-white">
    <main class="mx-auto max-w-3xl px-5 py-8 sm:px-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <p class="font-mono2 text-[11px] uppercase tracking-widest text-[#ff5b5b]">DevControl / Bugs</p>
                <h1 class="mt-2 font-display text-3xl font-bold">Editar bug {{ $bug->folio }}</h1>
            </div>
            <a href="{{ route('bugs.index') }}" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                Volver
            </a>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('bugs.update', $bug) }}" class="space-y-5 rounded-2xl border border-white/10 bg-[#0f0f11] p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="proyecto_id" class="mb-2 block text-sm text-gray-400">Proyecto</label>
                <select id="proyecto_id" name="proyecto_id" required class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-white">
                    @foreach($proyectos as $proyecto)
                        <option value="{{ $proyecto->id }}" @selected(old('proyecto_id', $bug->proyecto_id) == $proyecto->id)>
                            {{ $proyecto->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="titulo" class="mb-2 block text-sm text-gray-400">Título</label>
                <input id="titulo" name="titulo" value="{{ old('titulo', $bug->titulo) }}" required maxlength="255"
                       class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-white">
            </div>

            <div>
                <label for="descripcion" class="mb-2 block text-sm text-gray-400">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="5"
                          class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-white">{{ old('descripcion', $bug->descripcion) }}</textarea>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="estado" class="mb-2 block text-sm text-gray-400">Estado</label>
                    <select id="estado" name="estado" required class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-white">
                        @foreach(['Reportado', 'Investigando', 'En desarrollo', 'En pruebas', 'Solucionado', 'Cerrado'] as $estado)
                            <option value="{{ $estado }}" @selected(old('estado', $bug->estado) === $estado)>{{ $estado }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="prioridad" class="mb-2 block text-sm text-gray-400">Prioridad</label>
                    <select id="prioridad" name="prioridad" required class="w-full rounded-lg border border-white/10 bg-black px-3 py-2.5 text-white">
                        @foreach(['Baja', 'Media', 'Alta'] as $prioridad)
                            <option value="{{ $prioridad }}" @selected(old('prioridad', $bug->prioridad) === $prioridad)>{{ $prioridad }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="fecha_detectado" class="mb-2 block text-sm text-gray-400">Fecha detectado</label>
                <input id="fecha_detectado" type="date" name="fecha_detectado"
                       value="{{ old('fecha_detectado', optional($bug->fecha_detectado)->format('Y-m-d')) }}"
                       class="rounded-lg border border-white/10 bg-black px-3 py-2.5 text-white">
            </div>

            <button type="submit" class="rounded-xl bg-[#d61f2c] px-5 py-3 text-sm font-bold text-white hover:bg-[#b8161f]">
                Guardar cambios
            </button>
        </form>
    </main>
</body>
</html>
