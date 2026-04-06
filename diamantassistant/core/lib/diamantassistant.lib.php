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
    if (empty($user->rights->diamantassistant->use) && empty($user->admin)) {
        return '';
    }

    $cssUrl = DOL_URL_ROOT.'/custom/diamantassistant/css/chatbot.css';
    $jsUrl = DOL_URL_ROOT.'/custom/diamantassistant/js/chatbot.js';
    $ajaxUrl = DOL_URL_ROOT.'/custom/diamantassistant/ajax/chat.php';

    $html = '<link rel="stylesheet" href="'.$cssUrl.'">';
    $html .= '<script>window.DIAMANTASSISTANT_AJAX_URL = '.json_encode($ajaxUrl).';</script>';
    $html .= '<script src="'.$jsUrl.'" defer></script>';

    return $html;
}
