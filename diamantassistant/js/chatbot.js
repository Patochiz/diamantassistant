/* DiamantAssistant — widget chat flottant
 * Copyright (C) 2026 DIAMANT INDUSTRIE
 */
(function () {
    'use strict';

    if (window.DiamantAssistantLoaded) return;
    window.DiamantAssistantLoaded = true;

    // URL de l'endpoint AJAX — ajustée par le hook PHP
    var AJAX_URL = (window.DIAMANTASSISTANT_AJAX_URL || '/custom/diamantassistant/ajax/chat.php');

    var conversationId = 0;
    var isSending = false;

    // --- Construction du DOM ---
    function createWidget() {
        var container = document.createElement('div');
        container.id = 'da-chat-container';
        container.innerHTML = [
            '<button id="da-chat-toggle" title="Assistant DIAMANT" aria-label="Ouvrir l\'assistant">',
            '  <span>💬</span>',
            '</button>',
            '<div id="da-chat-window" class="da-hidden">',
            '  <div id="da-chat-header">',
            '    <span>Assistant DIAMANT</span>',
            '    <div id="da-chat-header-actions">',
            '      <button id="da-chat-maximize" aria-label="Agrandir" title="Agrandir">\u26F6</button>',
            '      <button id="da-chat-close" aria-label="Fermer">&times;</button>',
            '    </div>',
            '  </div>',
            '  <div id="da-chat-messages"></div>',
            '  <div id="da-chat-input-row">',
            '    <textarea id="da-chat-input" rows="2" placeholder="Posez votre question..."></textarea>',
            '    <button id="da-chat-send" title="Envoyer">➤</button>',
            '  </div>',
            '  <div id="da-chat-footer">IA Mistral — réponses à vérifier</div>',
            '</div>'
        ].join('');
        document.body.appendChild(container);

        document.getElementById('da-chat-toggle').addEventListener('click', toggleWindow);
        document.getElementById('da-chat-close').addEventListener('click', toggleWindow);
        document.getElementById('da-chat-maximize').addEventListener('click', toggleMaximize);
        document.getElementById('da-chat-send').addEventListener('click', sendMessage);
        document.getElementById('da-chat-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Message d'accueil
        addMessage('assistant', 'Bonjour ! Je suis l\'assistant DIAMANT. Posez-moi vos questions sur Dolibarr ou sur nos process internes.');
    }

    function toggleMaximize() {
        var win = document.getElementById('da-chat-window');
        var btn = document.getElementById('da-chat-maximize');
        win.classList.toggle('da-maximized');
        btn.textContent = win.classList.contains('da-maximized') ? '\u29C9' : '\u26F6';
        btn.title = win.classList.contains('da-maximized') ? 'R\u00e9duire' : 'Agrandir';
    }

    function toggleWindow() {
        var win = document.getElementById('da-chat-window');
        win.classList.toggle('da-hidden');
        if (!win.classList.contains('da-hidden')) {
            document.getElementById('da-chat-input').focus();
        }
    }

    function addMessage(role, text) {
        var container = document.getElementById('da-chat-messages');
        var msg = document.createElement('div');
        msg.className = 'da-msg da-msg-' + role;
        // Conversion basique markdown → HTML (gras, listes, retours ligne)
        msg.innerHTML = renderSimpleMarkdown(text);
        container.appendChild(msg);
        container.scrollTop = container.scrollHeight;
        return msg;
    }

    function renderSimpleMarkdown(text) {
        // Echapper HTML
        var html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        // **gras**
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // `code`
        html = html.replace(/`(.+?)`/g, '<code>$1</code>');
        // Liens [texte](url) — fonction pour pouvoir trimmer l'URL
        html = html.replace(/\[([^\]]+)\]\s*\(([^)]+)\)/g, function (match, linkText, url) {
            url = url.replace(/\s+/g, '');
            if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0) {
                return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + linkText + '</a>';
            }
            return match;
        });
        // Listes en tirets simples (ligne par ligne)
        html = html.replace(/(^|\n)[\-\*] (.+)/g, '$1• $2');
        // Retours ligne
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    function sendMessage() {
        if (isSending) return;
        var input = document.getElementById('da-chat-input');
        var question = input.value.trim();
        if (!question) return;

        isSending = true;
        addMessage('user', question);
        input.value = '';

        var thinkingMsg = addMessage('assistant', '...');
        thinkingMsg.classList.add('da-thinking');

        var payload = {
            question: question,
            conversation_id: conversationId,
            page_context: 'URL: ' + window.location.pathname + window.location.search
        };

        fetch(AJAX_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
            thinkingMsg.remove();
            if (!res.ok || res.data.error) {
                addMessage('assistant', '⚠️ ' + (res.data.error || 'Erreur inconnue.'));
            } else {
                conversationId = res.data.conversation_id || conversationId;
                addMessage('assistant', res.data.reply || '(réponse vide)');
            }
        })
        .catch(function (err) {
            thinkingMsg.remove();
            addMessage('assistant', '⚠️ Erreur de communication : ' + err.message);
        })
        .finally(function () {
            isSending = false;
            document.getElementById('da-chat-input').focus();
        });
    }

    // --- Init au chargement ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createWidget);
    } else {
        createWidget();
    }
})();
