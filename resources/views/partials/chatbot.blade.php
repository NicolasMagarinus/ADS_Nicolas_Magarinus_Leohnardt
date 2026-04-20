<button id="chatbot-toggle" class="chatbot-toggle" aria-label="Abrir assistente Drinkerito" title="Assistente Drinkerito">
    <span class="chatbot-toggle-icon chatbot-icon-open">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
    </span>
    <span class="chatbot-toggle-icon chatbot-icon-close" style="display:none;">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
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
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <div class="chatbot-messages" id="chatbot-messages" aria-live="polite">
        <div class="chatbot-msg chatbot-msg-bot" id="chatbot-welcome">
            <div class="chatbot-msg-avatar">🍹</div>
            <div class="chatbot-msg-bubble">
                Olá! Sou o <strong>Drinky</strong>, seu assistente de drinks! 🥂<br>
                Posso te ajudar a descobrir receitas, ingredientes e dicas de bebidas. Como posso te ajudar?
                <div class="chatbot-quick-replies" id="chatbot-quick-replies">
                    <button class="chatbot-quick-btn" data-msg="Quero um drink aleatório">🎲 Drink aleatório</button>
                    <button class="chatbot-quick-btn" data-msg="Drinks sem álcool">🥤 Sem álcool</button>
                    <button class="chatbot-quick-btn" data-msg="Como favoritar uma bebida?">❤️ Como favoritar?</button>
                </div>
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
        <textarea
            id="chatbot-input"
            class="chatbot-input"
            placeholder="Digite sua mensagem..."
            rows="1"
            aria-label="Digite sua mensagem"
            maxlength="500"
        ></textarea>
        <button id="chatbot-send" class="chatbot-send-btn" aria-label="Enviar mensagem" disabled>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
        </button>
    </div>
</div>

<script>
(function () {
    const toggle     = document.getElementById('chatbot-toggle');
    const window_    = document.getElementById('chatbot-window');
    const closeBtn   = document.getElementById('chatbot-close');
    const messages   = document.getElementById('chatbot-messages');
    const input      = document.getElementById('chatbot-input');
    const sendBtn    = document.getElementById('chatbot-send');
    const typing     = document.getElementById('chatbot-typing');
    const notifDot   = document.getElementById('chatbot-notif-dot');
    const iconOpen   = toggle.querySelector('.chatbot-icon-open');
    const iconClose  = toggle.querySelector('.chatbot-icon-close');

    let isOpen = false;

    function openChat() {
        isOpen = true;
        window_.classList.add('chatbot-window--open');
        window_.setAttribute('aria-hidden', 'false');
        iconOpen.style.display  = 'none';
        iconClose.style.display = 'flex';
        toggle.classList.add('chatbot-toggle--open');
        notifDot.style.display = 'none';
        input.focus();
        scrollToBottom();
    }

    function closeChat() {
        isOpen = false;
        window_.classList.remove('chatbot-window--open');
        window_.setAttribute('aria-hidden', 'true');
        iconOpen.style.display  = 'flex';
        iconClose.style.display = 'none';
        toggle.classList.remove('chatbot-toggle--open');
    }

    toggle.addEventListener('click', () => isOpen ? closeChat() : openChat());
    closeBtn.addEventListener('click', closeChat);

    setTimeout(() => {
        if (!isOpen) notifDot.style.display = 'block';
    }, 3000);

    function scrollToBottom() {
        messages.scrollTo({ top: messages.scrollHeight, behavior: 'smooth' });
    }

    input.addEventListener('input', () => {
        sendBtn.disabled = input.value.trim().length === 0;
        // auto-resize textarea
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sendBtn.disabled) sendMessage();
        }
    });

    sendBtn.addEventListener('click', sendMessage);

    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('chatbot-quick-btn')) {
            const msg = e.target.getAttribute('data-msg');
            appendUserMessage(msg);
            e.target.closest('.chatbot-quick-replies')?.remove();
            showTyping();
            // TODO: substituir pela chamada backend
            setTimeout(() => {
                hideTyping();
                appendBotMessage(getFallbackReply(msg));
            }, 1200);
        }
    });

    function sendMessage() {
        const text = input.value.trim();
        if (!text) return;

        appendUserMessage(text);
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;

        showTyping();

        // TODO: substituir pela chamada backend (fetch/axios)
        // Exemplo:
        // fetch('/api/chatbot', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        //     body: JSON.stringify({ message: text })
        // })
        // .then(r => r.json())
        // .then(data => { hideTyping(); appendBotMessage(data.reply); })
        // .catch(() => { hideTyping(); appendBotMessage('Ops, algo deu errado. Tente novamente!'); });

        setTimeout(() => {
            hideTyping();
            appendBotMessage(getFallbackReply(text));
        }, 1200 + Math.random() * 600);
    }

    function appendUserMessage(text) {
        const div = document.createElement('div');
        div.className = 'chatbot-msg chatbot-msg-user';
        div.innerHTML = `<div class="chatbot-msg-bubble">${escapeHtml(text)}</div>`;
        messages.appendChild(div);
        requestAnimationFrame(scrollToBottom);
    }

    function appendBotMessage(html) {
        const div = document.createElement('div');
        div.className = 'chatbot-msg chatbot-msg-bot chatbot-msg--new';
        div.innerHTML = `<div class="chatbot-msg-avatar">🍹</div><div class="chatbot-msg-bubble">${html}</div>`;
        messages.appendChild(div);
        requestAnimationFrame(scrollToBottom);
        if (!isOpen) notifDot.style.display = 'block';
    }

    function showTyping() {
        typing.style.display = 'flex';
        scrollToBottom();
    }

    function hideTyping() {
        typing.style.display = 'none';
    }

    function escapeHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function getFallbackReply(msg) {
        const lower = msg.toLowerCase();
        if (lower.includes('aleatório') || lower.includes('aleatorio') || lower.includes('sortear'))
            return '🎲 Clique em <a href="/random" class="chatbot-link">Sortear Bebida</a> para descobrir um drink surpresa!';
        if (lower.includes('sem álcool') || lower.includes('sem alcool') || lower.includes('não alcoólico'))
            return '🥤 Temos várias opções sem álcool! Use o filtro de <a href="/bebidas" class="chatbot-link">busca</a> e selecione "Não alcoólico".';
        if (lower.includes('favorit'))
            return '❤️ Para favoritar, abra a página da bebida e clique no botão <strong>Favoritar</strong>. Você precisa estar logado!';
        if (lower.includes('ingrediente'))
            return '🧪 Você pode buscar drinks por ingrediente! Acesse <a href="/bebidas" class="chatbot-link">explorar bebidas</a> e use a barra de pesquisa.';
        if (lower.includes('oi') || lower.includes('olá') || lower.includes('ola') || lower.includes('hello'))
            return 'Olá! 😄 Estou aqui para te ajudar a encontrar o drink perfeito. O que você está procurando?';
        if (lower.includes('obrigad') || lower.includes('valeu') || lower.includes('thanks'))
            return 'Por nada! 🍸 Qualquer dúvida é só chamar. Saúde!';
        return '🤖 Em breve terei respostas completas! Por enquanto, explore nosso catálogo de bebidas ou use o campo de <a href="/bebidas" class="chatbot-link">busca</a>. 🥂';
    }
})();
</script>
