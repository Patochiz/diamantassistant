/* DiamantAssistant — Auto-verification IA avant validation
 * Copyright (C) 2026 DIAMANT INDUSTRIE
 *
 * Intercepte le clic sur "Valider" dans les fiches Dolibarr configurees,
 * envoie le contenu de la page a l'IA pour analyse, et affiche les alertes
 * dans le chat avant de laisser passer la validation.
 */
(function () {
    'use strict';

    var config = window.DIAMANTASSISTANT_AUTOVERIF;
    if (!config || !config.enabled) return;

    // Mapping module → pattern URL Dolibarr
    var MODULE_URLS = {
        commande:        '/commande/card.php',
        propal:          '/comm/propal/card.php',
        supplier_order:  '/fourn/commande/card.php',
        expedition:      '/expedition/card.php'
    };

    // Detecter si la page courante correspond a un module active
    var currentPath = window.location.pathname;
    var currentModule = null;
    var enabledModules = config.modules || [];

    for (var i = 0; i < enabledModules.length; i++) {
        var mod = enabledModules[i];
        if (MODULE_URLS[mod] && currentPath.indexOf(MODULE_URLS[mod]) !== -1) {
            currentModule = mod;
            break;
        }
    }
    if (!currentModule) return;

    // Actions de validation Dolibarr a intercepter
    var VALIDATE_ACTIONS = ['action=validate', 'action=approve'];

    var isRunning = false;

    function init() {
        var buttons = document.querySelectorAll('a.butAction');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var href = btn.getAttribute('href') || '';
            if (isValidateButton(href)) {
                btn.addEventListener('click', createInterceptor(btn));
            }
        }
    }

    function isValidateButton(href) {
        for (var i = 0; i < VALIDATE_ACTIONS.length; i++) {
            if (href.indexOf(VALIDATE_ACTIONS[i]) !== -1) {
                return true;
            }
        }
        return false;
    }

    function createInterceptor(btn) {
        return function (e) {
            if (isRunning) return;
            e.preventDefault();
            e.stopPropagation();
            runAutoVerif(btn.getAttribute('href'), btn);
        };
    }

    function runAutoVerif(originalHref, btn) {
        isRunning = true;

        // Indicateur visuel : bouton grise avec texte "Verification IA..."
        var originalText = btn.textContent;
        var originalClass = btn.className;
        btn.textContent = 'Verification IA...';
        btn.className = btn.className.replace('butAction', 'butActionRefused');

        var snapshot = capturePageSnapshot();

        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                page_snapshot: snapshot,
                module: currentModule
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            restoreButton(btn, originalText, originalClass);
            isRunning = false;

            if (data.error) {
                // Erreur serveur : laisser passer la validation
                window.location.href = originalHref;
                return;
            }

            if (data.ras) {
                // Aucun probleme detecte : laisser passer la validation
                window.location.href = originalHref;
            } else {
                // Problemes detectes : ouvrir le chat avec les alertes
                showWarnings(data.reply, originalHref);
            }
        })
        .catch(function () {
            // Erreur reseau : laisser passer la validation
            restoreButton(btn, originalText, originalClass);
            isRunning = false;
            window.location.href = originalHref;
        });
    }

    function restoreButton(btn, text, className) {
        btn.textContent = text;
        btn.className = className;
    }

    function showWarnings(reply, originalHref) {
        if (!window.DiamantAssistant) {
            // Widget non disponible : laisser passer
            window.location.href = originalHref;
            return;
        }

        window.DiamantAssistant.openChat();
        window.DiamantAssistant.addMessage(
            'assistant',
            '**Verification avant validation**\n\n'
            + reply
            + '\n\n[Ignorer et valider quand meme](' + originalHref + ')'
        );
    }

    /**
     * Capture un instantane de la page courante.
     * Replique la logique de chatbot.js pour etre independant.
     */
    function capturePageSnapshot() {
        var MAX_TEXT = 5000;

        var headingEl = document.querySelector('.fichecenter .titre')
            || document.querySelector('.titre_page')
            || document.querySelector('.titre')
            || document.querySelector('h1');
        var heading = headingEl ? (headingEl.innerText || headingEl.textContent || '').trim() : '';

        var mainEl = document.getElementById('mainbody')
            || document.querySelector('.fichecenter')
            || document.querySelector('main')
            || document.body;
        var text = mainEl ? (mainEl.innerText || mainEl.textContent || '') : '';
        text = text.replace(/[ \t]+/g, ' ').replace(/\n{2,}/g, '\n').trim();

        var truncated = false;
        if (text.length > MAX_TEXT) {
            text = text.substring(0, MAX_TEXT);
            truncated = true;
        }

        return {
            url: window.location.pathname + window.location.search,
            title: (document.title || '').trim(),
            heading: heading,
            text: text,
            truncated: truncated
        };
    }

    // --- Init au chargement ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
