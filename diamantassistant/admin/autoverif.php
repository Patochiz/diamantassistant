<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Page de configuration de l'auto-verification IA avant validation.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/diamantassistant/class/contextbuilder.class.php');

$langs->loadLangs(array("admin", "diamantassistant@diamantassistant"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// Modules disponibles pour l'auto-verification
$availableModules = array(
    'commande'       => 'Commandes clients',
    'propal'         => 'Propositions commerciales',
    'supplier_order' => 'Commandes fournisseurs',
    'expedition'     => 'Expeditions',
);

// Chemin du fichier autoverif.md
$knowledgeDir = dol_buildpath('/diamantassistant/knowledge', 0);
$autoverifFile = $knowledgeDir.'/autoverif.md';

// --- Traitement du formulaire ---
if ($action == 'save') {
    $enabled = GETPOST('autoverif_enabled', 'int') ? 1 : 0;

    $selectedModules = array();
    foreach (array_keys($availableModules) as $mod) {
        if (GETPOST('mod_'.$mod, 'int')) {
            $selectedModules[] = $mod;
        }
    }
    $modulesStr = implode(',', $selectedModules);

    dolibarr_set_const($db, 'DIAMANTASSISTANT_AUTOVERIF_ENABLED', (string) $enabled, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DIAMANTASSISTANT_AUTOVERIF_MODULES', $modulesStr, 'chaine', 0, '', $conf->entity);

    // Sauvegarder le contenu du fichier autoverif.md
    $content = GETPOST('autoverif_content', 'restricthtml');
    if (!empty($content)) {
        @file_put_contents($autoverifFile, $content);
    }

    setEventMessages("Configuration de l'auto-verification enregistree.", null, 'mesgs');
}

// --- Lecture des valeurs courantes ---
$currentEnabled = (int) getDolGlobalString('DIAMANTASSISTANT_AUTOVERIF_ENABLED', 0);
$currentModules = getDolGlobalString('DIAMANTASSISTANT_AUTOVERIF_MODULES', '');
$currentModulesArr = !empty($currentModules) ? explode(',', $currentModules) : array();

$currentContent = '';
if (file_exists($autoverifFile)) {
    $currentContent = @file_get_contents($autoverifFile);
    if ($currentContent === false) {
        $currentContent = '';
    }
}

// --- Affichage ---
llxHeader('', 'DiamantAssistant - Auto-verification');

$linkback = '<a href="setup.php">&larr; Retour a la configuration</a>';
print load_fiche_titre('Auto-verification avant validation', $linkback, 'title_setup');

print dol_get_fiche_head(array(), '', '', -1);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">Parametres generaux</td></tr>';

// Activation
print '<tr class="oddeven"><td>Activer l\'auto-verification</td><td>';
print '<input type="checkbox" name="autoverif_enabled" value="1"'.($currentEnabled ? ' checked' : '').'>';
print ' <small>(L\'IA analysera le document avant chaque validation)</small>';
print '</td></tr>';

// Modules concernes
print '<tr class="oddeven"><td>Modules concernes</td><td>';
foreach ($availableModules as $mod => $label) {
    $checked = in_array($mod, $currentModulesArr) ? ' checked' : '';
    print '<label style="display:block;margin:4px 0;">';
    print '<input type="checkbox" name="mod_'.$mod.'" value="1"'.$checked.'> '.$label;
    print '</label>';
}
print '</td></tr>';

print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Regles de verification (autoverif.md)</td></tr>';
print '<tr class="oddeven"><td>';
print '<textarea name="autoverif_content" rows="20" cols="80" style="width:95%;font-family:monospace;">'.dol_escape_htmltag($currentContent, 0, 1).'</textarea>';
print '<br><small>Decrivez en langage naturel ce que l\'IA doit verifier avant chaque validation. Ce contenu est envoye a l\'IA comme instructions.</small>';
print '</td></tr>';
print '</table>';

print '<div class="center" style="margin-top:15px;">';
print '<input type="submit" class="button" value="Enregistrer">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
