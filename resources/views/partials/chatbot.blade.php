@php $isAuthenticated = auth()->check(); @endphp

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
                @if($isAuthenticated)
                    Olá, <strong>{{ auth()->user()->name }}</strong>! Sou o <strong>Drinky</strong>, seu assistente de drinks! 🥂<br>
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
        <textarea
            id="chatbot-input"
            class="chatbot-input"
            placeholder="{{ $isAuthenticated ? 'Digite sua mensagem...' : 'Faça login para usar o chat' }}"
            rows="1"
            aria-label="Digite sua mensagem"
            maxlength="500"
            {{ $isAuthenticated ? '' : 'disabled' }}
        ></textarea>
        <button id="chatbot-send" class="chatbot-send-btn" aria-label="Enviar mensagem" disabled>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
        </button>
    </div>
</div>

<style>
.chatbot-drink-card {
    background: linear-gradient(135deg, rgba(255,165,0,0.12) 0%, rgba(255,100,50,0.08) 100%);
    border: 1px solid rgba(255,165,0,0.35);
    border-radius: 12px;
    padding: 12px 14px;
    margin-top: 10px;
    font-size: 0.85rem;
}
.chatbot-drink-card-title {
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 8px;
    color: #ffb347;
    display: flex;
    align-items: center;
    gap: 6px;
}
.chatbot-drink-ingredients {
    list-style: none;
    padding: 0;
    margin: 0 0 10px 0;
}
.chatbot-drink-ingredients li {
    padding: 2px 0;
    display: flex;
    gap: 6px;
}
.chatbot-drink-ingredients li::before {
    content: "•";
    color: #ffb347;
    font-weight: bold;
    flex-shrink: 0;
}
.chatbot-drink-preparo-toggle {
    background: none;
    border: none;
    color: rgba(255,255,255,0.6);
    font-size: 0.78rem;
    padding: 0;
    cursor: pointer;
    text-decoration: underline;
    margin-bottom: 6px;
    display: block;
}
.chatbot-drink-preparo-text {
    display: none;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.75);
    line-height: 1.5;
    margin-bottom: 10px;
    padding: 8px;
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
}
.chatbot-drink-preparo-text.open {
    display: block;
}
.chatbot-save-btn {
    width: 100%;
    padding: 8px 14px;
    background: linear-gradient(135deg, #ff8c00, #ff5722);
    color: #fff;
    border: none;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.chatbot-save-btn:hover:not(:disabled) {
    opacity: 0.88;
    transform: translateY(-1px);
}
.chatbot-save-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.chatbot-save-btn.success {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
}
.chatbot-save-btn.error {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
}
</style>

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

    const isAuthenticated = {{ $isAuthenticated ? 'true' : 'false' }};
    const csrfToken       = '{{ csrf_token() }}';
    const messageUrl      = '{{ route("chatbot.message") }}';
    const saveDrinkUrl    = '{{ route("chatbot.salvar-bebida") }}';
    let isOpen      = false;
    let limitReached = false;

    function openChat() {
        isOpen = true;
        window_.classList.add('chatbot-window--open');
        window_.setAttribute('aria-hidden', 'false');
        iconOpen.style.display  = 'none';
        iconClose.style.display = 'flex';
        toggle.classList.add('chatbot-toggle--open');
        notifDot.style.display = 'none';
        if (isAuthenticated && !limitReached) input.focus();
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

    if (isAuthenticated) {
        input.addEventListener('input', () => {
            if (limitReached) return;
            sendBtn.disabled = input.value.trim().length === 0;
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
                e.target.closest('.chatbot-quick-replies')?.remove();
                appendUserMessage(msg);
                callBackend(msg);
            }
        });
    }

    function sendMessage() {
        const text = input.value.trim();
        if (!text || limitReached) return;

        appendUserMessage(text);
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;

        callBackend(text);
    }

    function callBackend(text) {
        showTyping();

        fetch(messageUrl, {
            method: 'POST',
            headers: {
                'Content-Type':  'application/json',
                'X-CSRF-TOKEN':  csrfToken,
                'Accept':        'application/json',
            },
            body: JSON.stringify({ message: text }),
        })
        .then(async (res) => {
            const data = await res.json();
            hideTyping();

            if (res.status === 429 || data.limit_reached) {
                limitReached = true;
                appendBotMessage(data.reply);
                disableInput('Limite diário atingido 🚫');
                return;
            }

            appendBotMessage(data.reply);

            // Show drink suggestion card if present
            if (data.drink_suggestion) {
                appendDrinkCard(data.drink_suggestion);
            }

            // Show remaining AI calls hint when using OpenAI
            if (data.source === 'openai' && typeof data.remaining !== 'undefined') {
                if (data.remaining <= 2 && data.remaining > 0) {
                    appendBotMessage(
                        `<small class="text-warning">⚠️ Você tem apenas <strong>${data.remaining}</strong> pergunta(s) à IA restante(s) hoje.</small>`
                    );
                } else if (data.remaining === 0) {
                    limitReached = true;
                    appendBotMessage('<small class="text-danger">🚫 Você usou todas as suas perguntas à IA por hoje. Volte amanhã!</small>');
                    disableInput('Limite diário atingido 🚫');
                }
            }
        })
        .catch(() => {
            hideTyping();
            appendBotMessage('😔 Ops! Não consegui me conectar ao servidor. Tente novamente em instantes.');
        });
    }

    function appendDrinkCard(drink) {
        const card = document.createElement('div');
        card.className = 'chatbot-msg chatbot-msg-bot chatbot-msg--new';

        const ingredientesHtml = drink.ingredientes.map(ing => {
            const medida = ing.ds_medida ? `<span style="opacity:.7">${escapeHtml(ing.ds_medida)}</span>` : '';
            return `<li><span>${escapeHtml(ing.nm_ingrediente)}</span>${medida ? ' — ' + medida : ''}</li>`;
        }).join('');

        const preparo = escapeHtml(drink.modo_preparo);
        const btnId = 'save-btn-' + Date.now();

        card.innerHTML = `
            <div class="chatbot-msg-avatar">🍹</div>
            <div class="chatbot-msg-bubble" style="width:100%">
                <div class="chatbot-drink-card">
                    <div class="chatbot-drink-card-title">
                        🍸 ${escapeHtml(drink.nome)}
                    </div>
                    <ul class="chatbot-drink-ingredients">${ingredientesHtml}</ul>
                    <button class="chatbot-drink-preparo-toggle" onclick="this.nextElementSibling.classList.toggle('open'); this.textContent = this.nextElementSibling.classList.contains('open') ? '▲ Esconder preparo' : '▼ Ver modo de preparo';">▼ Ver modo de preparo</button>
                    <div class="chatbot-drink-preparo-text">${preparo}</div>
                    <button class="chatbot-save-btn" id="${btnId}">
                        ➕ Salvar esta bebida
                    </button>
                </div>
            </div>`;

        messages.appendChild(card);
        requestAnimationFrame(scrollToBottom);
        if (!isOpen) notifDot.style.display = 'block';

        // Attach save handler
        document.getElementById(btnId).addEventListener('click', function () {
            saveDrink(drink, this);
        });
    }

    function saveDrink(drink, btn) {
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .7s linear infinite"></span> Salvando...';

        fetch(saveDrinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                nome:         drink.nome,
                modo_preparo: drink.modo_preparo,
                ingredientes: drink.ingredientes,
            }),
        })
        .then(async (res) => {
            const data = await res.json();
            if (data.success) {
                btn.classList.add('success');
                btn.innerHTML = '✅ Bebida salva! Ver no <a href="/profile" style="color:#fff;font-weight:700">perfil</a>';
            } else {
                throw new Error('fail');
            }
        })
        .catch(() => {
            btn.classList.add('error');
            btn.innerHTML = '❌ Erro ao salvar. Tente novamente.';
            btn.disabled = false;
        });
    }

    function disableInput(placeholder) {
        input.disabled    = true;
        sendBtn.disabled  = true;
        input.placeholder = placeholder;
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
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Spinner keyframe (injected once)
    if (!document.getElementById('chatbot-spin-style')) {
        const s = document.createElement('style');
        s.id = 'chatbot-spin-style';
        s.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
        document.head.appendChild(s);
    }
})();
</script>
