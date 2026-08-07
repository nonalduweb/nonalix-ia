(function() {
    // 1. Détection de la configuration
    const scriptTag = document.currentScript;
    if (!scriptTag) return;

    const tenantId = scriptTag.getAttribute('data-tenant');
    if (!tenantId) {
        console.error('Nonalix IA Widget : Attribut data-tenant manquant sur le script.');
        return;
    }

    // Domaines de base (relatif au script ou absolu)
    const baseUrl = new URL(scriptTag.src).origin;

    // 2. Gestion de la session visiteur
    const sessionKey = `nonalix_chat_session_${tenantId}`;
    let sessionId = localStorage.getItem(sessionKey);
    if (!sessionId) {
        sessionId = 'web_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        localStorage.setItem(sessionKey, sessionId);
    }

    // 3. Variables d'état
    let isOpen = false;
    let widgetConfig = {
        agent_name: 'Assistant IA',
        persona: 'Assistant Virtuel',
        greeting_message: 'Bonjour ! Comment puis-je vous aider ?',
        theme_color: '#2563eb', // Bleu par défaut
        messages: []
    };
    let messageCount = 0;
    let pollInterval = null;

    // 4. Injection des Styles CSS
    const style = document.createElement('style');
    style.innerHTML = `
        .nonalix-widget-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .nonalix-launcher {
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background-color: var(--theme-color, #2563eb);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease-in-out;
        }
        .nonalix-launcher:hover {
            transform: scale(1.05);
        }
        .nonalix-launcher svg {
            fill: #ffffff;
            width: 28px;
            height: 28px;
            transition: transform 0.3s ease;
        }
        .nonalix-launcher.open svg {
            transform: rotate(90deg);
        }
        .nonalix-chat-window {
            position: absolute;
            bottom: 75px;
            right: 0;
            width: 370px;
            height: 520px;
            border-radius: 16px;
            background-color: #ffffff;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px);
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
            border: 1px solid #f1f5f9;
        }
        .nonalix-chat-window.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .nonalix-header {
            background-color: var(--theme-color, #2563eb);
            color: #ffffff;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .nonalix-header-info {
            display: flex;
            flex-direction: column;
        }
        .nonalix-agent-name {
            font-weight: 700;
            font-size: 15px;
        }
        .nonalix-agent-status {
            font-size: 11px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }
        .nonalix-status-dot {
            width: 7px;
            height: 7px;
            background-color: #4ade80;
            border-radius: 50%;
            display: inline-block;
        }
        .nonalix-close-btn {
            background: none;
            border: none;
            color: #ffffff;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.8;
            padding: 0 4px;
        }
        .nonalix-close-btn:hover {
            opacity: 1;
        }
        .nonalix-messages {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background-color: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .nonalix-message-bubble {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13.5px;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .nonalix-message-bubble.out {
            background-color: #ffffff;
            color: #0f172a;
            align-self: flex-start;
            border-bottom-left-radius: 2px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .nonalix-message-bubble.in {
            background-color: var(--theme-color, #2563eb);
            color: #ffffff;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }
        .nonalix-notice {
            align-self: center;
            max-width: 90%;
            padding: 8px 12px;
            border-radius: 10px;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 11.5px;
            line-height: 1.4;
            text-align: center;
        }
        .nonalix-footer {
            padding: 12px;
            background-color: #ffffff;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 8px;
        }
        .nonalix-input {
            flex: 1;
            border: 1px solid #cbd5e1;
            border-radius: 24px;
            padding: 10px 16px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .nonalix-input:focus {
            border-color: var(--theme-color, #2563eb);
        }
        .nonalix-send-btn {
            background-color: var(--theme-color, #2563eb);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            flex-shrink: 0;
            transition: opacity 0.2s;
        }
        .nonalix-send-btn:hover {
            opacity: 0.9;
        }
        .nonalix-send-btn svg {
            fill: #ffffff;
            width: 16px;
            height: 16px;
            transform: translateX(1px);
        }
    `;
    document.head.appendChild(style);

    // 5. Création des Éléments HTML du widget
    const container = document.createElement('div');
    container.className = 'nonalix-widget-container';
    
    // Bouton flottant
    const launcher = document.createElement('div');
    launcher.className = 'nonalix-launcher';
    launcher.innerHTML = `
        <svg viewBox="0 0 24 24" id="nonalix-icon-chat">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
        </svg>
    `;

    // Fenêtre de chat
    const chatWindow = document.createElement('div');
    chatWindow.className = 'nonalix-chat-window';
    chatWindow.innerHTML = `
        <div class="nonalix-header">
            <div class="nonalix-header-info">
                <span class="nonalix-agent-name" id="nonalix-agent-name">Assistant IA</span>
                <span class="nonalix-agent-status">
                    <span class="nonalix-status-dot"></span> en ligne
                </span>
            </div>
            <button class="nonalix-close-btn">&times;</button>
        </div>
        <div class="nonalix-messages" id="nonalix-messages-box">
            <!-- Messages chargés dynamiquement -->
        </div>
        <div class="nonalix-footer">
            <input type="text" class="nonalix-input" placeholder="Écrivez votre message..." id="nonalix-input-field" autocomplete="off" />
            <button class="nonalix-send-btn" id="nonalix-send-button">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </div>
    `;

    container.appendChild(launcher);
    container.appendChild(chatWindow);
    document.body.appendChild(container);

    // Sélections DOM
    const nameEl = chatWindow.querySelector('#nonalix-agent-name');
    const messagesBox = chatWindow.querySelector('#nonalix-messages-box');
    const inputField = chatWindow.querySelector('#nonalix-input-field');
    const sendButton = chatWindow.querySelector('#nonalix-send-button');
    const closeBtn = chatWindow.querySelector('.nonalix-close-btn');

    // 6. Fonctions API
    const loadConfig = async () => {
        try {
            const res = await fetch(`${baseUrl}/widget/config/${tenantId}?session_id=${sessionId}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                widgetConfig = await res.json();
                
                // Appliquer la couleur de thème
                container.style.setProperty('--theme-color', widgetConfig.theme_color);
                nameEl.textContent = widgetConfig.agent_name;

                // Rendu des messages
                renderMessages();
            }
        } catch (err) {
            console.error('Erreur lors du chargement de la config Nonalix IA:', err);
        }
    };

    const sendMessage = async () => {
        const text = inputField.value.trim();
        if (!text) return;

        inputField.value = '';

        // Ajouter immédiatement le message envoyé à l'écran
        appendMessage(text, 'in');

        try {
            const res = await fetch(`${baseUrl}/widget/chat/${tenantId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // Sans cet en-tête, une erreur de validation répond par une
                    // redirection 302 que `fetch` suit en silence : on reçoit
                    // alors une page HTML en 200 et l'échec passe pour un
                    // succès, le message du visiteur disparaissant sans un mot.
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    body: text
                })
            });

            if (!res.ok) {
                notifyFailure();
            }
        } catch (err) {
            notifyFailure();
        }
    };

    /** Signale au visiteur que son message n'est pas parti. */
    const notifyFailure = () => {
        const notice = document.createElement('div');
        notice.className = 'nonalix-notice';
        notice.textContent = "Votre message n'a pas pu être envoyé. Vérifiez votre connexion et réessayez.";
        messagesBox.appendChild(notice);
        scrollToBottom();
    };

    const renderMessages = () => {
        // Si l'historique est vide, ajouter le message d'accueil.
        // textContent et non innerHTML : le message est saisi dans l'espace
        // client, et il s'affiche ici sur le site d'un tiers.
        if (widgetConfig.messages.length === 0) {
            messagesBox.innerHTML = '';
            const greeting = document.createElement('div');
            greeting.className = 'nonalix-message-bubble out';
            greeting.textContent = widgetConfig.greeting_message;
            messagesBox.appendChild(greeting);
            messageCount = 0;
            return;
        }

        // Sinon, restituer l'historique
        if (widgetConfig.messages.length !== messageCount) {
            messagesBox.innerHTML = '';
            widgetConfig.messages.forEach(msg => {
                // out = message de l'agent, in = message du visiteur
                const direction = msg.direction === 'outbound' ? 'out' : 'in';
                const bubble = document.createElement('div');
                bubble.className = `nonalix-message-bubble ${direction}`;
                bubble.textContent = msg.body;
                messagesBox.appendChild(bubble);
            });
            messageCount = widgetConfig.messages.length;
            scrollToBottom();
        }
    };

    const appendMessage = (text, direction) => {
        const bubble = document.createElement('div');
        bubble.className = `nonalix-message-bubble ${direction}`;
        bubble.textContent = text;
        messagesBox.appendChild(bubble);
        scrollToBottom();
    };

    const scrollToBottom = () => {
        messagesBox.scrollTop = messagesBox.scrollHeight;
    };

    // 7. Événements
    const toggleChat = () => {
        isOpen = !isOpen;
        if (isOpen) {
            launcher.classList.add('open');
            chatWindow.classList.add('open');
            loadConfig();
            
            // Démarrer le polling toutes les 3 secondes pour récupérer les réponses de l'IA
            pollInterval = setInterval(loadConfig, 3000);
            setTimeout(scrollToBottom, 100);
        } else {
            launcher.classList.remove('open');
            chatWindow.classList.remove('open');
            
            // Stopper le polling
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }
    };

    launcher.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    sendButton.addEventListener('click', sendMessage);
    inputField.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    // Chargement initial silencieux
    loadConfig();
})();
