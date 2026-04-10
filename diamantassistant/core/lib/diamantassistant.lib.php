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

    // Auto-verification : injecter la config et le script si active
    $autoverifEnabled = (int) getDolGlobalString('DIAMANTASSISTANT_AUTOVERIF_ENABLED', 0);
    if ($autoverifEnabled) {
        $autoverifModules = getDolGlobalString('DIAMANTASSISTANT_AUTOVERIF_MODULES', '');
        $modulesArray = !empty($autoverifModules) ? explode(',', $autoverifModules) : [];

        if (!empty($modulesArray)) {
            $autoverifAjaxUrl = dol_buildpath('/diamantassistant/ajax/autoverif.php', 1);
            $autoverifJsUrl   = dol_buildpath('/diamantassistant/js/autoverif.js', 1);
            $autoverifJsPath  = dol_buildpath('/diamantassistant/js/autoverif.js', 0);
            $autoverifJsVer   = @filemtime($autoverifJsPath) ?: time();

            $html .= '<script>window.DIAMANTASSISTANT_AUTOVERIF = '.json_encode([
                'enabled' => true,
                'modules' => $modulesArray,
                'ajaxUrl' => $autoverifAjaxUrl,
            ]).';</script>';
            $html .= '<script src="'.$autoverifJsUrl.'?v='.$autoverifJsVer.'" defer></script>';
        }
    }

    return $html;
}
