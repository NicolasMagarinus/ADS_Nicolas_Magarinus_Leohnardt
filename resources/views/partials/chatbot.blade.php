@php $isAuthenticated = auth()->check(); @endphp

<button id="chatbot-toggle" class="chatbot-toggle" aria-label="Abrir assistente Drinkerito"
    title="Assistente Drinkerito">
    <span class="chatbot-toggle-icon chatbot-icon-open">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
    </span>
    <span class="chatbot-toggle-icon chatbot-icon-close" style="display:none;">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
    </span>
    <span class="chatbot-notif-dot" id="chatbot-notif-dot"></span>
</button>

<div id="chatbot-window" class="chatbot-window" role="dialog" aria-label="Chat com Drinkerito" aria-hidden="true">

    <div class="chatbot-header">
        <div class="chatbot-header-info">
            <div class="chatbot-avatar">🍹</div>
            <div>
                <div class="chatbot-header-name">Drinky</div>
                <div class="chatbot-header-status">
                    <span class="chatbot-status-dot"></span>Online
                </div>
            </div>
        </div>
        <button class="chatbot-close-btn" id="chatbot-close" aria-label="Fechar chat">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
    </div>

    <div class="chatbot-messages" id="chatbot-messages" aria-live="polite">
        <div class="chatbot-msg chatbot-msg-bot" id="chatbot-welcome">
            <div class="chatbot-msg-avatar">🍹</div>
            <div class="chatbot-msg-bubble">
                @if($isAuthenticated)
                    Olá, <strong>{{ auth()->user()->name }}</strong>! Sou o <strong>Drinky</strong>, seu assistente de
                    drinks! 🥂<br>
                    Posso te ajudar a descobrir receitas, ingredientes e dicas de bebidas. Como posso te ajudar?
                    <div class="chatbot-quick-replies" id="chatbot-quick-replies">
                        <button class="chatbot-quick-btn" data-msg="Quero um drink aleatório">🎲 Drink aleatório</button>
                        <button class="chatbot-quick-btn" data-msg="Drinks sem álcool">🥤 Sem álcool</button>
                        <button class="chatbot-quick-btn" data-msg="Como favoritar uma bebida?">❤️ Como favoritar?</button>
                    </div>
                @else
                    Olá! Sou o <strong>Drinky</strong>, seu assistente de drinks! 🥂<br><br>
                    Para conversar comigo, você precisa estar logado.<br><br>
                    <a href="{{ route('login') }}" class="btn btn-sm btn-warning fw-bold">🔑 Fazer login</a>
                    &nbsp;
                    <a href="{{ route('register') }}" class="btn btn-sm btn-outline-light">Criar conta</a>
                @endif
            </div>
        </div>
    </div>

    <div class="chatbot-typing" id="chatbot-typing" style="display:none;">
        <div class="chatbot-msg-avatar">🍹</div>
        <div class="chatbot-typing-bubble">
            <span></span><span></span><span></span>
        </div>
    </div>

    <div class="chatbot-input-area">
        <textarea id="chatbot-input" class="chatbot-input"
            placeholder="{{ $isAuthenticated ? 'Digite sua mensagem...' : 'Faça login para usar o chat' }}" rows="1"
            aria-label="Digite sua mensagem" maxlength="500" {{ $isAuthenticated ? '' : 'disabled' }}></textarea>
        <button id="chatbot-send" class="chatbot-send-btn" aria-label="Enviar mensagem" disabled>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
            </svg>
        </button>
    </div>
</div>

<script>
    window.DrinkeritoChatbot = {
        isAuthenticated: {{ $isAuthenticated ? 'true' : 'false' }},
        csrfToken: '{{ csrf_token() }}',
        messageUrl: '{{ route("chatbot.message") }}',
        saveDrinkUrl: '{{ route("chatbot.salvar-bebida") }}',
    };
</script>
@vite('resources/js/chatbot.js')