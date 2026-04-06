<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Hook qui injecte le widget de chat DiamantAssistant dans toutes les pages Dolibarr
 * via le hook printTopRightMenu (ou équivalent selon version).
 */

dol_include_once('/diamantassistant/core/lib/diamantassistant.lib.php');

class ActionsDiamantAssistant
{
    public $results = array();
    public $resprints = '';
    public $error = 0;
    public $errors = array();

    /**
     * Hook : ajoute le widget en haut à droite de toutes les pages.
     */
    public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
    {
        if (function_exists('diamantassistant_get_widget_html')) {
            $this->resprints = diamantassistant_get_widget_html();
        }
        return 0;
    }
}
