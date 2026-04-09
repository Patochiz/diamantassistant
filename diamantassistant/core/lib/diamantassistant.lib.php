<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Bibliothèque d'helpers pour le module DiamantAssistant.
 */

/**
 * Retourne le HTML à injecter dans toutes les pages Dolibarr pour afficher le widget.
 * À appeler depuis un hook (printTopRightMenu ou équivalent).
 */
function diamantassistant_get_widget_html(): string
{
    global $user, $conf;

    if (empty($user->id)) {
        return '';
    }
    if ((int) getDolGlobalString('DIAMANTASSISTANT_ENABLED_WIDGET', 1) !== 1) {
        return '';
    }
    if (!$user->hasRight('diamantassistant', 'use') && empty($user->admin)) {
        return '';
    }

    $cssUrl = dol_buildpath('/diamantassistant/css/chatbot.css', 1);
    $jsUrl = dol_buildpath('/diamantassistant/js/chatbot.js', 1);
    $ajaxUrl = dol_buildpath('/diamantassistant/ajax/chat.php', 1);

    // Cache-busting : append file mtime pour forcer le rechargement après update.
    $cssPath = dol_buildpath('/diamantassistant/css/chatbot.css', 0);
    $jsPath  = dol_buildpath('/diamantassistant/js/chatbot.js', 0);
    $cssVer  = @filemtime($cssPath) ?: time();
    $jsVer   = @filemtime($jsPath)  ?: time();

    $html = '<link rel="stylesheet" href="'.$cssUrl.'?v='.$cssVer.'">';
    $html .= '<script>window.DIAMANTASSISTANT_AJAX_URL = '.json_encode($ajaxUrl).';</script>';
    $html .= '<script src="'.$jsUrl.'?v='.$jsVer.'" defer></script>';

    return $html;
}
