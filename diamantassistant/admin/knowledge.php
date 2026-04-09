<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Page de gestion des fichiers de la base de connaissance.
 * Permet aux administrateurs d'éditer et créer des fichiers .md
 * dans diamantassistant/knowledge/ qui sont injectés dans le prompt
 * système à chaque conversation (cf. ContextBuilder::loadKnowledgeBase).
 *
 * La suppression n'est volontairement pas supportée : pour retirer un
 * fichier, passer par FTP/SSH.
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

$langs->loadLangs(array("admin", "diamantassistant@diamantassistant"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

$knowledgeDir = dol_buildpath('/diamantassistant/knowledge', 0);

/**
 * Normalise et valide un nom de fichier .md pour la base de connaissance.
 *
 * Règles :
 *  - Retire une éventuelle extension .md (insensible à la casse)
 *  - Autorise uniquement [A-Za-z0-9_-]
 *  - Longueur du basename : 1 à 64 caractères
 *  - Rajoute systématiquement .md à la fin
 *
 * @param string $raw Valeur brute saisie par l'utilisateur
 * @return string|null Nom de fichier validé (ex: "foo.md") ou null si invalide
 */
function diamantAssistantSanitizeKnowledgeFilename(string $raw): ?string
{
    $name = trim($raw);
    if ($name === '') {
        return null;
    }
    // Retirer .md final éventuel
    if (preg_match('/\.md$/i', $name)) {
        $name = substr($name, 0, -3);
    }
    // Interdire tout séparateur / caractère hors whitelist
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) {
        return null;
    }
    return $name.'.md';
}

/**
 * Vérifie qu'un chemin cible reste bien à l'intérieur du dossier knowledge.
 * Protection défense-en-profondeur contre la path traversal.
 */
function diamantAssistantPathInsideKnowledge(string $fullPath, string $knowledgeDir): bool
{
    $realDir = realpath($knowledgeDir);
    if ($realDir === false) {
        return false;
    }
    // Le fichier peut ne pas encore exister (création) : on valide le parent
    $parent = realpath(dirname($fullPath));
    if ($parent === false) {
        return false;
    }
    return $parent === $realDir;
}

// ============================================================
// Actions
// ============================================================

if ($action === 'save') {
    if (!is_dir($knowledgeDir)) {
        setEventMessages("Le dossier knowledge/ est introuvable : ".$knowledgeDir, null, 'errors');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    $isCreate = (GETPOST('mode', 'alpha') === 'new');
    $rawName  = GETPOST('file', 'alphanohtml');
    $content  = GETPOST('content', 'restricthtml');

    $filename = diamantAssistantSanitizeKnowledgeFilename((string) $rawName);
    if ($filename === null) {
        setEventMessages("Nom de fichier invalide. Utilisez uniquement lettres, chiffres, tirets et underscores (max 64 caractères).", null, 'errors');
        header('Location: '.$_SERVER['PHP_SELF'].'?action='.($isCreate ? 'new' : 'edit').'&file='.urlencode((string) $rawName));
        exit;
    }

    $fullPath = $knowledgeDir.'/'.$filename;

    if (!diamantAssistantPathInsideKnowledge($fullPath, $knowledgeDir)) {
        setEventMessages("Chemin de fichier refusé (hors dossier knowledge).", null, 'errors');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    if ($isCreate && file_exists($fullPath)) {
        setEventMessages("Un fichier <code>".dol_escape_htmltag($filename)."</code> existe déjà. Utilisez « Éditer » pour le modifier.", null, 'errors');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    // Normalisation des fins de ligne : les navigateurs envoient CRLF, on stocke en LF.
    $content = str_replace("\r\n", "\n", (string) $content);

    $written = @file_put_contents($fullPath, $content);
    if ($written === false) {
        setEventMessages("Échec de l'écriture du fichier. Vérifiez les permissions du dossier <code>".dol_escape_htmltag($knowledgeDir)."</code> (le serveur web doit pouvoir y écrire).", null, 'errors');
    } else {
        setEventMessages($isCreate ? "Fichier <code>".dol_escape_htmltag($filename)."</code> créé." : "Fichier <code>".dol_escape_htmltag($filename)."</code> enregistré.", null, 'mesgs');
    }

    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ============================================================
// Chargement du fichier pour l'édition
// ============================================================

$currentFile    = null;
$currentContent = '';

if ($action === 'edit') {
    $filename = diamantAssistantSanitizeKnowledgeFilename((string) GETPOST('file', 'alphanohtml'));
    if ($filename === null) {
        setEventMessages("Nom de fichier invalide.", null, 'errors');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
    $fullPath = $knowledgeDir.'/'.$filename;
    if (!diamantAssistantPathInsideKnowledge($fullPath, $knowledgeDir) || !is_file($fullPath)) {
        setEventMessages("Fichier introuvable : ".dol_escape_htmltag($filename), null, 'errors');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
    $currentFile    = $filename;
    $loaded         = @file_get_contents($fullPath);
    $currentContent = ($loaded === false) ? '' : $loaded;
}

// ============================================================
// Affichage
// ============================================================

llxHeader('', 'DiamantAssistant — Base de connaissance');

$linkback = '<a href="setup.php">← Configuration</a>';
print load_fiche_titre('Base de connaissance (fichiers .md)', $linkback, 'title_setup');
print dol_get_fiche_head(array(), '', '', -1);

print '<div class="opacitymedium" style="margin-bottom:12px">';
print 'Ces fichiers Markdown sont concaténés et injectés dans le prompt système à <strong>chaque conversation</strong>. Soyez concis — un volume total trop important ralentit l\'IA et augmente le coût. Pensez à < 20&nbsp;000 caractères au total.';
print '</div>';

if (!is_dir($knowledgeDir)) {
    print '<div class="error">';
    print 'Le dossier <code>'.dol_escape_htmltag($knowledgeDir).'</code> n\'existe pas. Créez-le manuellement sur le serveur.';
    print '</div>';
    print dol_get_fiche_end();
    llxFooter();
    $db->close();
    exit;
}

if ($action === 'new' || $action === 'edit') {
    // ----------------------------------------------------------------
    // FORMULAIRE (édition ou création)
    // ----------------------------------------------------------------
    $isEdit = ($action === 'edit' && $currentFile !== null);

    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save">';
    print '<input type="hidden" name="mode" value="'.($isEdit ? 'edit' : 'new').'">';

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">'.($isEdit ? 'Éditer '.dol_escape_htmltag((string) $currentFile) : 'Nouveau fichier .md').'</td></tr>';

    // --- Nom de fichier
    print '<tr class="oddeven">';
    print '<td style="width:220px;vertical-align:top"><strong>Nom du fichier</strong><br>';
    print '<small>Lettres, chiffres, <code>-</code> et <code>_</code> uniquement.<br>L\'extension <code>.md</code> est ajoutée automatiquement.</small></td>';
    print '<td>';
    if ($isEdit) {
        print '<input type="text" name="file" value="'.dol_escape_htmltag((string) $currentFile).'" size="40" readonly style="background:#eee;cursor:not-allowed">';
    } else {
        print '<input type="text" name="file" value="" size="40" placeholder="ex: procedure-achats" autofocus>';
    }
    print '</td></tr>';

    // --- Contenu
    print '<tr class="oddeven">';
    print '<td style="vertical-align:top"><strong>Contenu Markdown</strong><br>';
    print '<small>Texte qui sera intégré au prompt système. Le nom du fichier (sans extension) sera utilisé comme titre de section.</small></td>';
    print '<td><textarea name="content" rows="25" style="width:100%;font-family:monospace;font-size:13px">'.dol_escape_htmltag($currentContent, 0, 1).'</textarea></td></tr>';

    print '</table>';

    print '<div class="center" style="margin-top:15px">';
    print '<input type="submit" class="button" value="'.($isEdit ? 'Enregistrer les modifications' : 'Créer le fichier').'">';
    print '&nbsp;&nbsp;<a href="'.$_SERVER['PHP_SELF'].'" class="button button-cancel">Annuler</a>';
    print '</div>';
    print '</form>';

} else {
    // ----------------------------------------------------------------
    // LISTE
    // ----------------------------------------------------------------
    print '<div style="margin-bottom:12px">';
    print '<a href="'.$_SERVER['PHP_SELF'].'?action=new" class="button">+ Nouveau fichier</a>';
    print '</div>';

    $files = glob($knowledgeDir.'/*.md');
    if ($files === false) {
        $files = [];
    }
    sort($files);

    if (empty($files)) {
        print '<div class="opacitymedium" style="padding:12px">';
        print 'Aucun fichier .md pour l\'instant. ';
        print '<a href="'.$_SERVER['PHP_SELF'].'?action=new">Créer le premier fichier →</a>';
        print '</div>';
    } else {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>Fichier</td>';
        print '<td style="text-align:right">Taille</td>';
        print '<td>Dernière modification</td>';
        print '<td>Actions</td>';
        print '</tr>';

        foreach ($files as $file) {
            $name  = basename($file);
            $size  = @filesize($file);
            $mtime = @filemtime($file);

            print '<tr class="oddeven">';
            print '<td><code>'.dol_escape_htmltag($name).'</code></td>';
            print '<td style="text-align:right"><small>'.($size !== false ? number_format($size, 0, ',', ' ').' o' : '?').'</small></td>';
            print '<td><small>'.($mtime !== false ? dol_print_date($mtime, 'dayhour') : '?').'</small></td>';
            print '<td><a href="'.$_SERVER['PHP_SELF'].'?action=edit&file='.urlencode($name).'" class="button">Éditer</a></td>';
            print '</tr>';
        }
        print '</table>';
    }

    // Alerte sur les permissions si le dossier n'est pas writable
    if (!is_writable($knowledgeDir)) {
        print '<br><div class="error">';
        print '<strong>⚠ Attention :</strong> le dossier <code>'.dol_escape_htmltag($knowledgeDir).'</code> n\'est pas inscriptible par le serveur web. L\'édition et la création échoueront. Corrigez les permissions en ligne de commande (ex: <code>chmod g+w</code>).';
        print '</div>';
    }
}

print dol_get_fiche_end();

print '<br><div class="opacitymedium" style="padding:10px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px">';
print '<strong>À propos de la base de connaissance</strong><br>';
print 'Chaque fichier <code>.md</code> est lu à chaque requête (pas de cache) puis concaténé avec son nom comme titre de section <code>### nom-du-fichier</code>. Pour <em>supprimer</em> un fichier, passer par FTP/SSH et retirer le fichier du dossier <code>'.dol_escape_htmltag($knowledgeDir).'</code>.';
print '</div>';

llxFooter();
$db->close();
