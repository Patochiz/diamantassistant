<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Page de configuration du module DiamantAssistant.
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
dol_include_once('/diamantassistant/core/lib/providers/ProviderFactory.class.php');

$langs->loadLangs(array("admin", "diamantassistant@diamantassistant"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// --- Traitement du formulaire ---
if ($action == 'save') {
    $provider = GETPOST('provider', 'alpha');
    $apiKey = GETPOST('mistral_api_key', 'none'); // none = pas d'échappement pour une clé
    $model = GETPOST('mistral_model', 'alpha');
    $rateLimit = (int) GETPOST('rate_limit', 'int');
    $logConv = GETPOST('log_conv', 'int') ? 1 : 0;
    $widgetEnabled = GETPOST('widget_enabled', 'int') ? 1 : 0;

    dolibarr_set_const($db, 'DIAMANTASSISTANT_PROVIDER', $provider, 'chaine', 0, '', $conf->entity);

    // Stockage chiffré de la clé API si modifiée (sinon on laisse l'ancienne)
    if (!empty($apiKey) && $apiKey !== '********') {
        $encrypted = function_exists('dolEncrypt') ? dolEncrypt($apiKey) : $apiKey;
        dolibarr_set_const($db, 'DIAMANTASSISTANT_MISTRAL_API_KEY', $encrypted, 'chaine', 0, '', $conf->entity);
    }

    dolibarr_set_const($db, 'DIAMANTASSISTANT_MISTRAL_MODEL', $model, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DIAMANTASSISTANT_RATE_LIMIT_PER_MIN', (string) $rateLimit, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DIAMANTASSISTANT_LOG_CONVERSATIONS', (string) $logConv, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DIAMANTASSISTANT_ENABLED_WIDGET', (string) $widgetEnabled, 'chaine', 0, '', $conf->entity);

    setEventMessages("Configuration enregistrée.", null, 'mesgs');
}

// --- Test de connexion ---
$testResult = null;
if ($action == 'test') {
    try {
        $provider = ProviderFactory::get();
        $reply = $provider->chat([
            ['role' => 'system', 'content' => 'Tu es un assistant de test. Réponds très brièvement.'],
            ['role' => 'user', 'content' => 'Dis "Connexion OK" et rien d\'autre.'],
        ], ['max_tokens' => 20]);
        $testResult = ['ok' => true, 'msg' => 'Provider : '.$provider->getName().' — Réponse : '.$reply];
    } catch (Exception $e) {
        $testResult = ['ok' => false, 'msg' => $e->getMessage()];
    }
}

// --- Affichage ---
llxHeader('', 'DiamantAssistant — Configuration');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre('Configuration DiamantAssistant', $linkback, 'title_setup');

print dol_get_fiche_head(array(), '', '', -1);

if ($testResult !== null) {
    if ($testResult['ok']) {
        print '<div class="ok">✅ '.dol_escape_htmltag($testResult['msg']).'</div>';
    } else {
        print '<div class="error">❌ '.dol_escape_htmltag($testResult['msg']).'</div>';
    }
}

$currentProvider = getDolGlobalString('DIAMANTASSISTANT_PROVIDER', 'mistral');
$currentModel = getDolGlobalString('DIAMANTASSISTANT_MISTRAL_MODEL', 'mistral-small-latest');
$currentRateLimit = getDolGlobalString('DIAMANTASSISTANT_RATE_LIMIT_PER_MIN', '10');
$currentLog = (int) getDolGlobalString('DIAMANTASSISTANT_LOG_CONVERSATIONS', 1);
$currentWidget = (int) getDolGlobalString('DIAMANTASSISTANT_ENABLED_WIDGET', 1);
$hasKey = !empty(getDolGlobalString('DIAMANTASSISTANT_MISTRAL_API_KEY', ''));

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Paramètre</td><td>Valeur</td></tr>';

print '<tr class="oddeven"><td>Provider IA</td><td>';
print '<select name="provider"><option value="mistral"'.($currentProvider == 'mistral' ? ' selected' : '').'>Mistral AI (La Plateforme)</option></select>';
print '</td></tr>';

print '<tr class="oddeven"><td>Clé API Mistral</td><td>';
print '<input type="password" name="mistral_api_key" size="60" value="'.($hasKey ? '********' : '').'" autocomplete="off">';
print ' <small>'.($hasKey ? '(clé enregistrée — laisser ******** pour ne pas changer)' : '(obligatoire)').'</small>';
print '</td></tr>';

print '<tr class="oddeven"><td>Modèle Mistral</td><td>';
print '<select name="mistral_model">';
$models = [
    'mistral-small-latest' => 'Mistral Small (recommandé, rapide, économique)',
    'mistral-medium-latest' => 'Mistral Medium (équilibré)',
    'mistral-large-latest' => 'Mistral Large (plus précis, plus lent)',
];
foreach ($models as $m => $label) {
    print '<option value="'.$m.'"'.($currentModel == $m ? ' selected' : '').'>'.$label.'</option>';
}
print '</select>';
print '</td></tr>';

print '<tr class="oddeven"><td>Messages max / minute / utilisateur</td><td>';
print '<input type="number" name="rate_limit" value="'.dol_escape_htmltag($currentRateLimit).'" min="0" max="100"> <small>(0 = illimité)</small>';
print '</td></tr>';

print '<tr class="oddeven"><td>Logger les conversations en base</td><td>';
print '<input type="checkbox" name="log_conv" value="1"'.($currentLog ? ' checked' : '').'>';
print '</td></tr>';

print '<tr class="oddeven"><td>Afficher le widget de chat</td><td>';
print '<input type="checkbox" name="widget_enabled" value="1"'.($currentWidget ? ' checked' : '').'>';
print '</td></tr>';

print '</table>';

print '<div class="center" style="margin-top:15px;">';
print '<input type="submit" class="button" value="Enregistrer">';
print ' &nbsp; ';
print '<a href="'.$_SERVER["PHP_SELF"].'?action=test" class="button">Tester la connexion</a>';
print '</div>';

print '</form>';

print dol_get_fiche_end();

print '<br><div class="opacitymedium">';
print '<strong>Base de connaissance :</strong> déposez vos fichiers <code>.md</code> dans <code>htdocs/custom/diamantassistant/knowledge/</code>. Ils seront automatiquement chargés dans le prompt système à chaque conversation.';
print '</div>';

print '<br>';
print '<div style="padding:12px;background:#f0f4ff;border:1px solid #c0cfe8;border-radius:4px">';
print '<strong>Outils de recherche personnalisés</strong><br>';
print 'Définissez vos propres requêtes SQL en lecture seule que l\'IA pourra utiliser.';
print ' <a href="tools.php" class="button" style="margin-left:10px">Gérer les outils →</a>';
print '</div>';

llxFooter();
$db->close();
