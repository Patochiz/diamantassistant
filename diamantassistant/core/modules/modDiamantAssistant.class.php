<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE
 *
 * Licensed under the GNU GPL v3 or later.
 */

/**
 * \defgroup   diamantassistant  Module DiamantAssistant
 * \brief      Assistant IA interne pour guider les utilisateurs sur Dolibarr
 * \file       htdocs/custom/diamantassistant/core/modules/modDiamantAssistant.class.php
 * \ingroup    diamantassistant
 * \brief      Description and activation file for module DiamantAssistant
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module DiamantAssistant
 */
class modDiamantAssistant extends DolibarrModules
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 500001; // ID interne — en dehors des ranges officiels
        $this->rights_class = 'diamantassistant';

        $this->family = "other";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Assistant IA interne DIAMANT pour guider les utilisateurs Dolibarr";
        $this->descriptionlong = "Chatbot basé sur Mistral AI, répond aux questions des utilisateurs sur Dolibarr et les process internes DIAMANT INDUSTRIE.";

        $this->editor_name = 'DIAMANT INDUSTRIE';
        $this->editor_url = '';

        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'generic';

        // Data directories to create when module is enabled
        $this->dirs = array("/diamantassistant/temp");

        // Config page
        $this->config_page_url = array("setup.php@diamantassistant");

        // Dependencies
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();

        $this->langfiles = array("diamantassistant@diamantassistant");

        // Enregistrement du hook manager pour injecter le widget dans toutes les pages
        $this->module_parts = array(
            'hooks' => array(
                'data' => array('main', 'toprightmenu'),
                'entity' => '0',
            ),
        );

        // Constants to add when module is enabled
        $this->const = array(
            1 => array('DIAMANTASSISTANT_PROVIDER', 'chaine', 'mistral', 'Provider IA actif', 0),
            2 => array('DIAMANTASSISTANT_MISTRAL_MODEL', 'chaine', 'mistral-small-latest', 'Modèle Mistral', 0),
            3 => array('DIAMANTASSISTANT_RATE_LIMIT_PER_MIN', 'chaine', '10', 'Messages max par minute et par utilisateur', 0),
            4 => array('DIAMANTASSISTANT_ENABLED_WIDGET', 'chaine', '1', 'Widget chat activé', 0),
            5 => array('DIAMANTASSISTANT_LOG_CONVERSATIONS', 'chaine', '1', 'Logger les conversations', 0),
        );

        // New pages on tabs
        $this->tabs = array();

        // Dictionaries
        $this->dictionaries = array();

        // Boxes / widgets
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();

        // Permissions provided by this module
        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = 5000101;
        $this->rights[$r][1] = 'Utiliser l\'assistant IA';
        $this->rights[$r][4] = 'use';
        $this->rights[$r][5] = '';
        $this->rights[$r][3] = 1; // Activé par défaut
        $r++;

        $this->rights[$r][0] = 5000102;
        $this->rights[$r][1] = 'Administrer l\'assistant IA';
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = '';
        $this->rights[$r][3] = 0;
        $r++;

        // Main menu entries — pas de menu principal pour ce module (widget flottant)
        $this->menu = array();
    }

    /**
     * Function called when module is enabled.
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int            1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $sql = array();

        $result = $this->_load_tables('/diamantassistant/sql/');
        if ($result < 0) {
            return -1;
        }

        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled.
     *
     * @param string $options Options when disabling module
     * @return int            1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
