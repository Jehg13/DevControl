<div id="assistantWidget" class="fixed bottom-5 right-5 z-[100] h-14 w-14 font-sans" style="position:fixed;right:20px;bottom:20px;width:56px;height:56px;z-index:2147483647;">
    <div id="assistantPanel" class="absolute bottom-0 right-[70px] hidden h-[330px] w-[min(280px,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0b0b0d] shadow-2xl shadow-black/70" style="position:absolute;right:70px;bottom:0;width:min(280px,calc(100vw - 2rem));height:330px;background:#0b0b0d;opacity:1;">
        <div class="flex items-center justify-between border-b border-white/10 bg-[#111114] px-4 py-3">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#d61f2c]/20 font-mono2 text-[10px] text-[#ff5b5b]">&gt;_</span>
                <div>
                    <p class="text-sm font-bold text-white">Nexus</p>
                    <p class="font-mono2 text-[9px] text-emerald-400">● disponible</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('asistente.index') }}" title="Abrir pantalla completa" class="rounded-lg px-2 py-1.5 font-mono2 text-[10px] text-gray-500 hover:bg-white/5 hover:text-white">⛶</a>
                <button id="assistantWidgetClose" type="button" class="rounded-lg px-2 py-1.5 text-gray-500 hover:bg-white/5 hover:text-white">×</button>
            </div>
        </div>
        <div id="assistantWidgetMessages" class="min-h-0 flex-1 space-y-3 overflow-y-scroll p-3" style="display:block;min-height:0;height:220px;overflow-y:scroll;scrollbar-width:thin;scrollbar-color:#d61f2c #17171a;">
            @foreach(array_slice(session('assistant_messages', []), -8) as $message)
                <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[88%] whitespace-pre-line rounded-xl {{ $message['role'] === 'user' ? 'bg-[#d61f2c] text-white' : 'border border-white/10 bg-white/5 text-gray-300' }} px-3 py-2 text-xs leading-relaxed">{{ $message['content'] }}</div>
                </div>
            @endforeach
            @if(count(session('assistant_messages', [])) === 0)
                <p id="assistantWidgetEmpty" class="py-10 text-center text-xs leading-relaxed text-gray-600">Escribe una instrucción para navegar o revisar DevControl.</p>
            @endif
        </div>
        <form id="assistantWidgetForm" class="border-t border-white/10 p-3">
            @csrf
            <div class="flex gap-2">
                <input id="assistantWidgetInput" maxlength="1000" autocomplete="off" placeholder="Habla con la IA..." class="min-w-0 flex-1 rounded-lg border border-white/10 bg-black px-3 py-2.5 text-xs text-white outline-none placeholder:text-gray-600 focus:border-[#d61f2c]/60">
                <button id="assistantWidgetSend" class="rounded-lg bg-[#d61f2c] px-3 py-2 font-mono2 text-[10px] font-bold text-white hover:bg-[#ef2937]">Enviar</button>
            </div>
        </form>
    </div>
    <button id="assistantWidgetToggle" type="button" class="flex h-14 w-14 items-center justify-center rounded-full border border-[#ff5b5b]/30 bg-[#d61f2c] text-white shadow-xl shadow-red-950/40 transition hover:scale-105 hover:bg-[#ef2937]" style="display:flex;width:56px;height:56px;border-radius:9999px;background:#d61f2c;color:#fff;cursor:pointer;" aria-label="Abrir asistente">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-4 2 1.2-3.6A7.5 7.5 0 1 1 20 11.5Z"/>
            <path d="M8 11.5h.01M12 11.5h.01M16 11.5h.01"/>
        </svg>
    </button>
</div>

<script>
(() => {
    const toggle = document.getElementById('assistantWidgetToggle');
    const panel = document.getElementById('assistantPanel');
    const close = document.getElementById('assistantWidgetClose');
    const form = document.getElementById('assistantWidgetForm');
    const input = document.getElementById('assistantWidgetInput');
    const send = document.getElementById('assistantWidgetSend');
    const messages = document.getElementById('assistantWidgetMessages');
    const empty = document.getElementById('assistantWidgetEmpty');

    const addMessage = (role, content) => {
        empty?.remove();
        const wrapper = document.createElement('div');
        wrapper.className = `flex ${role === 'user' ? 'justify-end' : 'justify-start'}`;
        const bubble = document.createElement('div');
        bubble.className = `max-w-[88%] whitespace-pre-line rounded-xl ${role === 'user' ? 'bg-[#d61f2c] text-white' : 'border border-white/10 bg-white/5 text-gray-300'} px-3 py-2 text-xs leading-relaxed`;
        bubble.textContent = content;
        wrapper.appendChild(bubble);
        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;
    };

    toggle.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        panel.classList.toggle('flex');
        if (!panel.classList.contains('hidden')) {
            input.focus();
            messages.scrollTop = messages.scrollHeight;
        }
    });
    close.addEventListener('click', () => panel.classList.add('hidden'));
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const command = input.value.trim();
        if (!command || send.disabled) return;
        addMessage('user', command);
        input.value = '';
        send.disabled = true;
        send.textContent = '...';
        try {
            const response = await fetch('{{ route('asistente.message') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                body: JSON.stringify({message: command})
            });
            if (!response.ok) throw new Error('assistant request failed');
            const data = await response.json();
            addMessage('assistant', data.message.content);
            if (data.navigation) window.location.href = data.navigation;
        } catch (error) {
            addMessage('assistant', 'No pude procesar la instrucción. Inténtalo nuevamente.');
        } finally {
            send.disabled = false;
            send.textContent = 'Enviar';
        }
    });
})();
</script>

<style>
    #assistantWidgetMessages::-webkit-scrollbar {
        width: 7px;
    }

    #assistantWidgetMessages::-webkit-scrollbar-track {
        background: #17171a;
    }

    #assistantWidgetMessages::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #d61f2c;
    }
</style>
