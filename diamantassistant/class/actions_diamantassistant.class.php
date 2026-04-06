<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Hook qui injecte le widget de chat DiamantAssistant dans toutes les pages Dolibarr
 * via le hook printTopRightMenu (ou équivalent selon version).
 */

require_once DOL_DOCUMENT_ROOT.'/custom/diamantassistant/core/lib/diamantassistant.lib.php';

class ActionsDiamantAssistant
{
    public array $results = [];
    public string $resprints = '';
    public int $error = 0;
    public array $errors = [];

    /**
     * Hook : ajoute le widget en haut à droite de toutes les pages.
     */
    public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
    {
        $this->resprints = diamantassistant_get_widget_html();
        return 0;
    }
}
