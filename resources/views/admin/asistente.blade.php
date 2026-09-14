<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>DevControl | Asistente</title>
    <style>
        .font-display { font-family: 'Space Grotesk', sans-serif; }
        .font-mono2 { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen bg-black text-white">
    <main class="mx-auto flex min-h-screen max-w-7xl flex-col px-5 py-6 sm:px-8 lg:px-10">
        <header class="flex flex-col gap-4 border-b border-white/10 pb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-mono2 text-[11px] uppercase tracking-[0.25em] text-[#ff5b5b]">DevControl / Nexus</p>
                <h1 class="mt-2 font-display text-3xl font-bold">Habla con Nexus sobre tu proyecto</h1>
                <p class="mt-2 max-w-2xl text-sm text-gray-500">Nexus entiende instrucciones naturales, conversa contigo y te avisa antes de tocar una sección incompleta.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-white/10 px-4 py-2 font-mono2 text-xs text-gray-300 transition hover:bg-white/5 hover:text-white">Volver al dashboard</a>
        </header>

        <section class="grid flex-1 gap-6 py-6 lg:grid-cols-[1fr_280px]">
            <div class="flex h-[calc(100vh-190px)] min-h-[560px] flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0b0b0d]">
                <div class="flex shrink-0 items-center justify-between border-b border-white/10 px-5 py-4">
                    <div>
                        <p class="font-display text-sm font-bold text-white">Conversación</p>
                        <p class="mt-1 font-mono2 text-[10px] text-gray-600">Las instrucciones se guardan durante esta sesión</p>
                    </div>
                    <button id="clearChat" type="button" class="rounded-lg border border-white/10 px-3 py-2 font-mono2 text-[10px] text-gray-500 transition hover:border-red-400/40 hover:text-red-300">
                        Limpiar chat
                    </button>
                </div>
                <div id="messages" class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                    @foreach($messages as $message)
                        <div class="flex items-end gap-2 {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            @if($message['role'] !== 'user')
                                <span class="mb-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#d61f2c]/15 font-mono2 text-[10px] text-[#ff5b5b]">&gt;_</span>
                            @endif
                            <div class="max-w-[85%] whitespace-pre-line rounded-2xl {{ $message['role'] === 'user' ? 'rounded-br-md bg-[#d61f2c] text-white' : 'rounded-bl-md border border-white/10 bg-white/5 text-gray-300' }} px-4 py-3 text-sm leading-relaxed shadow-lg shadow-black/10">
                                {{ $message['content'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div id="researchProgress" class="hidden border-t border-white/10 bg-black/20 px-5 py-4" aria-live="polite">
                    <div class="flex items-center justify-between">
                        <p id="researchProgressMessage" class="font-mono2 text-[10px] text-gray-400">Preparando investigación...</p>
                        <span id="researchProgressPercent" class="font-mono2 text-[10px] text-[#ff5b5b]">0%</span>
                    </div>
                    <div id="researchProgressSteps" class="mt-3 grid gap-1 text-[11px] text-gray-500 sm:grid-cols-5"></div>
                </div>
                <form id="assistantForm" class="border-t border-white/10 p-4">
                    @csrf
                    <div class="flex gap-3">
                        <textarea id="assistantInput" rows="1" maxlength="20000" autocomplete="off" placeholder="Escribe una instrucción..." class="max-h-32 min-w-0 flex-1 resize-none rounded-xl border border-white/10 bg-black px-4 py-3 text-sm text-white outline-none placeholder:text-gray-600 focus:border-[#d61f2c]/60"></textarea>
                        <button id="sendButton" class="self-end rounded-xl bg-[#d61f2c] px-5 py-3 font-mono2 text-xs font-bold text-white transition hover:bg-[#ef2937] disabled:cursor-not-allowed disabled:opacity-50">Enviar</button>
                    </div>
                    <p class="mt-2 font-mono2 text-[10px] text-gray-600">Enter para enviar · Shift + Enter para nueva línea</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" data-command="revisa errores" class="command rounded-full border border-white/10 px-3 py-1.5 font-mono2 text-[10px] text-gray-400 hover:bg-white/5 hover:text-white">Revisar errores</button>
                        <button type="button" data-command="ir a proyectos" class="command rounded-full border border-white/10 px-3 py-1.5 font-mono2 text-[10px] text-gray-400 hover:bg-white/5 hover:text-white">Abrir proyectos</button>
                        <button type="button" data-command="ir a tareas" class="command rounded-full border border-white/10 px-3 py-1.5 font-mono2 text-[10px] text-gray-400 hover:bg-white/5 hover:text-white">Abrir tareas</button>
                    </div>
                </form>
            </div>

            <aside class="rounded-2xl border border-white/10 bg-[#0b0b0d] p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold">Módulos</h2>
                    <span class="font-mono2 text-[10px] text-gray-600">estado</span>
                </div>
                <div class="mt-5 space-y-3">
                    @foreach($modules as $module)
                        <div class="rounded-xl border border-white/5 bg-white/[0.03] p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-gray-300">{{ $module['label'] }}</span>
                                <span class="font-mono2 text-[10px] {{ $module['ready'] ? 'text-emerald-400' : 'text-red-400' }}">{{ $module['ready'] ? 'OPERATIVO' : 'BLOQUEADO' }}</span>
                            </div>
                            @if(!$module['ready'])
                                <p class="mt-2 text-xs leading-relaxed text-gray-600">{{ $module['reason'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </aside>
        </section>
    </main>
    @include('admin.partials.asistente-flotante')
    <script>
        const form = document.getElementById('assistantForm');
        const input = document.getElementById('assistantInput');
        const messages = document.getElementById('messages');
        const sendButton = document.getElementById('sendButton');
        const researchProgress = document.getElementById('researchProgress');
        const researchProgressMessage = document.getElementById('researchProgressMessage');
        const researchProgressPercent = document.getElementById('researchProgressPercent');
        const researchProgressSteps = document.getElementById('researchProgressSteps');
        let progressPoll = null;

        const stepLabels = {
            intent: 'Intención',
            evidence: 'Evidencia',
            analysis: 'Análisis',
            synthesis: 'Síntesis',
            verification: 'Verificación',
        };

        function renderResearchProgress(progress) {
            researchProgress.classList.remove('hidden');
            researchProgressMessage.textContent = progress.message || 'Nexus está procesando la consulta.';
            researchProgressPercent.textContent = `${progress.progress || 0}%`;
            researchProgressSteps.innerHTML = Object.entries(stepLabels).map(([key, label]) => {
                const completed = (progress.completed_steps || []).includes(key);
                const current = progress.current_step === key;
                const icon = completed ? '✓' : (current ? '◉' : '○');
                const color = completed ? 'text-emerald-400' : (current ? 'text-[#ff5b5b]' : 'text-gray-600');
                return `<span class="${color}">${icon} ${label}</span>`;
            }).join('');
        }

        async function pollResearchProgress(token) {
            try {
                const response = await fetch(`{{ route('asistente.progress') }}?progress_token=${encodeURIComponent(token)}`, {
                    headers: {'Accept': 'application/json'}
                });
                if (response.ok) renderResearchProgress(await response.json());
            } catch (error) {
                // The final assistant request remains the source of truth if polling is unavailable.
            }
        }

        function addMessage(role, content) {
            const wrapper = document.createElement('div');
            wrapper.className = `flex items-end gap-2 ${role === 'user' ? 'justify-end' : 'justify-start'}`;
            if (role !== 'user') {
                const avatar = document.createElement('span');
                avatar.className = 'mb-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#d61f2c]/15 font-mono2 text-[10px] text-[#ff5b5b]';
                avatar.textContent = '>_';
                wrapper.appendChild(avatar);
            }
            const bubble = document.createElement('div');
            bubble.className = `max-w-[85%] whitespace-pre-line rounded-2xl ${role === 'user' ? 'rounded-br-md bg-[#d61f2c] text-white' : 'rounded-bl-md border border-white/10 bg-white/5 text-gray-300'} px-4 py-3 text-sm leading-relaxed shadow-lg shadow-black/10`;
            bubble.textContent = content;
            wrapper.appendChild(bubble);
            messages.appendChild(wrapper);
            messages.scrollTop = messages.scrollHeight;
        }

        async function sendMessage(command) {
            if (!command.trim() || sendButton.disabled) return;
            addMessage('user', command);
            input.value = '';
            input.style.height = 'auto';
            sendButton.disabled = true;
            sendButton.textContent = '...';
            const progressToken = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            renderResearchProgress({message: 'Consulta recibida.', progress: 0, current_step: 'intent', completed_steps: []});
            await pollResearchProgress(progressToken);
            progressPoll = window.setInterval(() => pollResearchProgress(progressToken), 500);
            try {
                const response = await fetch('{{ route('asistente.message') }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                    body: JSON.stringify({message: command, progress_token: progressToken})
                });
                if (!response.ok) {
                    addMessage('assistant', 'No pude procesar la instrucción. Revisa la conexión y vuelve a intentarlo.');
                    return;
                }
                const data = await response.json();
                if (data.research) renderResearchProgress(data.research);
                addMessage('assistant', data.message.content);
                if (data.navigation) window.location.href = data.navigation;
            } catch (error) {
                renderResearchProgress({
                    status: 'failed',
                    message: 'La investigación falló antes de completar la respuesta.',
                    progress: 0,
                    current_step: null,
                    completed_steps: []
                });
                addMessage('assistant', 'No pude conectarme con DevControl. Vuelve a intentarlo.');
            } finally {
                window.clearInterval(progressPoll);
                progressPoll = null;
                sendButton.disabled = false;
                sendButton.textContent = 'Enviar';
            }
        }

        form.addEventListener('submit', event => {
            event.preventDefault();
            sendMessage(input.value);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = `${Math.min(input.scrollHeight, 128)}px`;
        });
        document.getElementById('clearChat').addEventListener('click', async () => {
            if (!confirm('¿Limpiar toda la conversación?')) return;
            await fetch('{{ route('asistente.clear') }}', {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
            });
            messages.innerHTML = '';
            addMessage('assistant', 'Conversación limpiada. ¿Qué quieres hacer en DevControl?');
            input.focus();
        });
        document.querySelectorAll('.command').forEach(button => {
            button.addEventListener('click', () => sendMessage(button.dataset.command));
        });
        messages.scrollTop = messages.scrollHeight;
    </script>
</body>
</html>
