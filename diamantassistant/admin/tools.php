<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Page de gestion des outils SQL personnalisés pour l'IA.
 * Permet aux administrateurs de définir de nouvelles requêtes SELECT
 * que l'IA peut appeler via le function calling Mistral.
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

$langs->loadLangs(array("admin"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id     = (int) GETPOST('id', 'int');

// --- Vérifier si la table existe, la créer si besoin ---
$daToolTableSql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."diamantassistant_tool (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(100) NOT NULL,
    label             VARCHAR(255) NOT NULL,
    description       TEXT NOT NULL,
    sql_query         TEXT NOT NULL,
    parameters        TEXT NOT NULL DEFAULT '[]',
    active            TINYINT DEFAULT 1 NOT NULL,
    date_creation     DATETIME NOT NULL,
    date_modification DATETIME DEFAULT NULL,
    fk_user_creat     INTEGER DEFAULT NULL
) ENGINE=innodb";

$daToolTableExists = false;
$daToolCreateError = '';
$chk = $db->query("SHOW TABLES LIKE '".MAIN_DB_PREFIX."diamantassistant_tool'");
if ($chk && $db->fetch_object($chk)) {
    $daToolTableExists = true;
} else {
    // Tentative de création automatique
    $db->begin();
    $resCreate = $db->query($daToolTableSql);
    if ($resCreate) {
        // Ignore error on ADD INDEX (may already exist)
        $db->query("ALTER TABLE ".MAIN_DB_PREFIX."diamantassistant_tool ADD UNIQUE INDEX uk_da_tool_name (name)");
        $db->commit();
        $daToolTableExists = true;
    } else {
        $daToolCreateError = $db->lasterror();
        $db->rollback();
    }
}

if ($action === 'create_table') {
    if ($db->query($daToolTableSql)) {
        $db->query("ALTER TABLE ".MAIN_DB_PREFIX."diamantassistant_tool ADD UNIQUE INDEX uk_da_tool_name (name)");
        setEventMessages("Table créée avec succès.", null, 'mesgs');
    } else {
        setEventMessages("Échec : ".$db->lasterror(), null, 'errors');
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ============================================================
// Actions
// ============================================================

if ($action === 'save') {
    $editId      = (int) GETPOST('edit_id', 'int');
    $name        = GETPOST('tool_name', 'alphanohtml');
    $label       = GETPOST('tool_label', 'alphanohtml');
    $description = GETPOST('tool_description', 'none');
    $sqlQuery    = GETPOST('sql_query', 'none');
    $parameters  = GETPOST('parameters', 'none');
    $active      = GETPOST('active', 'int') ? 1 : 0;

    $errors = [];

    if (!preg_match('/^[a-z][a-z0-9_]{0,98}$/', (string) $name)) {
        $errors[] = 'Nom invalide : minuscules, chiffres et _ uniquement, doit commencer par une lettre (ex: search_products).';
    }
    if (empty(trim((string) $label))) {
        $errors[] = 'Le libellé est obligatoire.';
    }
    if (empty(trim((string) $description))) {
        $errors[] = 'La description est obligatoire — l\'IA en a besoin pour savoir quand utiliser l\'outil.';
    }
    if (!preg_match('/^\s*SELECT\b/i', (string) $sqlQuery)) {
        $errors[] = 'La requête SQL doit commencer par SELECT.';
    }
    if (strpos((string) $sqlQuery, ';') !== false) {
        $errors[] = 'La requête SQL ne doit pas contenir de point-virgule (instruction multiple interdite).';
    }
    if (preg_match('/\b(INTO\s+OUTFILE|INTO\s+DUMPFILE|LOAD_FILE|LOAD\s+DATA)\b/i', (string) $sqlQuery)) {
        $errors[] = 'Opération SQL non autorisée.';
    }

    $paramsArray = json_decode((string) $parameters, true);
    if (!is_array($paramsArray)) {
        $paramsArray = [];
    }

    if (empty($errors)) {
        $now = $db->idate(dol_now());
        if ($editId > 0) {
            $sql  = "UPDATE ".MAIN_DB_PREFIX."diamantassistant_tool SET";
            $sql .= " label = '".$db->escape($label)."',";
            $sql .= " description = '".$db->escape($description)."',";
            $sql .= " sql_query = '".$db->escape($sqlQuery)."',";
            $sql .= " parameters = '".$db->escape(json_encode($paramsArray))."',";
            $sql .= " active = ".$active.",";
            $sql .= " date_modification = '".$now."'";
            $sql .= " WHERE rowid = ".$editId;
        } else {
            $sql  = "INSERT INTO ".MAIN_DB_PREFIX."diamantassistant_tool";
            $sql .= " (name, label, description, sql_query, parameters, active, date_creation, fk_user_creat) VALUES (";
            $sql .= "'".$db->escape($name)."',";
            $sql .= "'".$db->escape($label)."',";
            $sql .= "'".$db->escape($description)."',";
            $sql .= "'".$db->escape($sqlQuery)."',";
            $sql .= "'".$db->escape(json_encode($paramsArray))."',";
            $sql .= $active.",";
            $sql .= "'".$now."',";
            $sql .= (int) $user->id.")";
        }
        $resql = $db->query($sql);
        if ($resql) {
            setEventMessages($editId > 0 ? "Outil mis à jour." : "Outil créé.", null, 'mesgs');
            header('Location: '.$_SERVER['PHP_SELF']);
            exit;
        } else {
            setEventMessages("Erreur SQL : ".$db->lasterror(), null, 'errors');
        }
        $action = $editId > 0 ? 'edit' : 'create';
        $id     = $editId;
    } else {
        foreach ($errors as $err) {
            setEventMessages($err, null, 'errors');
        }
        $action = $editId > 0 ? 'edit' : 'create';
        $id     = $editId;
    }
}

if ($action === 'delete' && $id > 0) {
    $sql   = "DELETE FROM ".MAIN_DB_PREFIX."diamantassistant_tool WHERE rowid = ".$id;
    $resql = $db->query($sql);
    if ($resql) {
        setEventMessages("Outil supprimé.", null, 'mesgs');
    } else {
        setEventMessages("Erreur : ".$db->lasterror(), null, 'errors');
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $db->query("UPDATE ".MAIN_DB_PREFIX."diamantassistant_tool SET active = 1 - active WHERE rowid = ".$id);
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ============================================================
// Chargement de l'outil pour l'édition
// ============================================================

$currentTool = null;
if (in_array($action, ['edit', 'save']) && $id > 0) {
    $resql = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."diamantassistant_tool WHERE rowid = ".$id);
    if ($resql) {
        $currentTool = $db->fetch_object($resql);
        $db->free($resql);
    }
}

// ============================================================
// Affichage
// ============================================================

llxHeader('', 'DiamantAssistant — Outils personnalisés');

$linkback = '<a href="setup.php">← Configuration</a>';
print load_fiche_titre('Outils de recherche personnalisés', $linkback, 'title_setup');
print dol_get_fiche_head(array(), '', '', -1);

if ($action === 'create' || ($action === 'edit' && $currentTool)) {
    // ----------------------------------------------------------------
    // FORMULAIRE
    // ----------------------------------------------------------------
    $isEdit = ($action === 'edit' && $currentTool);
    $t      = $currentTool;

    print '<form id="da-tool-form" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save">';
    print '<input type="hidden" name="edit_id" value="'.($isEdit ? (int) $t->rowid : 0).'">';

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">'.($isEdit ? 'Modifier l\'outil' : 'Nouvel outil').'</td></tr>';

    // --- Nom
    $nameVal = $isEdit ? dol_escape_htmltag($t->name) : '';
    print '<tr class="oddeven">';
    print '<td style="width:240px;vertical-align:top"><strong>Nom machine</strong><br>';
    print '<small>Minuscules, chiffres et _ uniquement.<br>Ex&nbsp;: <code>search_products</code></small></td>';
    print '<td><input type="text" name="tool_name" value="'.$nameVal.'" size="40" placeholder="search_products"';
    if ($isEdit) print ' readonly style="background:#eee;cursor:not-allowed"';
    print '></td></tr>';

    // --- Libellé
    $labelVal = $isEdit ? dol_escape_htmltag($t->label) : '';
    print '<tr class="oddeven">';
    print '<td style="vertical-align:top"><strong>Libellé</strong><br><small>Nom affiché dans l\'admin</small></td>';
    print '<td><input type="text" name="tool_label" value="'.$labelVal.'" size="60" placeholder="Recherche de produits"></td></tr>';

    // --- Description
    $descVal = $isEdit ? dol_escape_htmltag($t->description) : '';
    print '<tr class="oddeven">';
    print '<td style="vertical-align:top"><strong>Description pour l\'IA</strong><br>';
    print '<small>L\'IA utilise ce texte pour décider quand appeler l\'outil.<br><strong>Soyez précis et explicite.</strong></small></td>';
    print '<td><textarea name="tool_description" rows="3" style="width:100%">'.$descVal.'</textarea></td></tr>';

    // --- Requête SQL
    $sqlVal = $isEdit ? dol_escape_htmltag($t->sql_query) : '';
    print '<tr class="oddeven">';
    print '<td style="vertical-align:top"><strong>Requête SQL</strong><br>';
    print '<small>Uniquement <code>SELECT</code>.<br>';
    print 'Placeholders : <code>{param}</code> pour les paramètres,<br><code>{entity}</code> pour l\'entité Dolibarr.<br>';
    print 'Les valeurs string s\'insèrent <em>sans</em> guillemets&nbsp;: écrire <code>\'%{ref}%\'</code>.<br>';
    print 'Les valeurs integer s\'insèrent directement&nbsp;: <code>= {year}</code>.</small></td>';
    print '<td><textarea name="sql_query" rows="7" style="width:100%;font-family:monospace;font-size:12px">'.$sqlVal.'</textarea></td></tr>';

    // --- Paramètres
    $paramsJson = $isEdit ? dol_escape_htmltag($t->parameters) : '[]';
    print '<tr class="oddeven">';
    print '<td style="vertical-align:top"><strong>Paramètres</strong><br>';
    print '<small>Correspondent aux <code>{placeholders}</code><br>dans la requête SQL.<br>';
    print 'Le placeholder <code>{entity}</code> est réservé<br>(toujours substitué automatiquement).</small></td>';
    print '<td>';
    print '<table class="noborder" style="width:100%">';
    print '<thead><tr style="background:#f0f0f0">';
    print '<th style="text-align:left;padding:4px 8px">Nom du paramètre</th>';
    print '<th style="text-align:left;padding:4px 8px">Type</th>';
    print '<th style="text-align:left;padding:4px 8px">Description pour l\'IA</th>';
    print '<th style="text-align:center;padding:4px 8px">Requis</th>';
    print '<th></th></tr></thead>';
    print '<tbody id="da-params-tbody"></tbody>';
    print '</table>';
    print '<button type="button" onclick="daAddParam()" class="button" style="margin-top:6px">+ Ajouter un paramètre</button>';
    print '<input type="hidden" name="parameters" id="da-parameters" value="">';
    print '<input type="hidden" id="da-params-init" value="'.$paramsJson.'">';
    print '</td></tr>';

    // --- Actif
    $activeChecked = $isEdit ? (int) $t->active : 1;
    print '<tr class="oddeven">';
    print '<td><strong>Actif</strong></td>';
    print '<td><input type="checkbox" name="active" value="1"'.($activeChecked ? ' checked' : '').'></td></tr>';

    print '</table>';
    print '<div class="center" style="margin-top:15px">';
    print '<input type="submit" class="button" value="'.($isEdit ? 'Mettre à jour' : 'Créer l\'outil').'">';
    print '&nbsp;&nbsp;<a href="'.$_SERVER['PHP_SELF'].'" class="button button-cancel">Annuler</a>';
    print '</div>';
    print '</form>';

    // --- JavaScript dynamique pour les paramètres ---
    ?>
<script>
(function () {
    var idx = 0;

    window.daAddParam = function (name, type, desc, req) {
        var i = idx++;
        var id = 'dapr' + i;
        var tr = document.createElement('tr');
        tr.id = id;
        tr.style.borderBottom = '1px solid #ddd';
        tr.innerHTML =
            '<td style="padding:4px 8px"><input type="text" class="pname" placeholder="reference" value="' + (name || '') + '" style="width:130px"></td>' +
            '<td style="padding:4px 8px"><select class="ptype" style="width:90px">' +
                '<option value="string"'  + (type !== 'integer' ? ' selected' : '') + '>string</option>' +
                '<option value="integer"' + (type === 'integer' ? ' selected' : '') + '>integer</option>' +
            '</select></td>' +
            '<td style="padding:4px 8px"><input type="text" class="pdesc" placeholder="Description pour l\'IA" value="' + (desc || '') + '" style="width:280px"></td>' +
            '<td style="padding:4px 8px;text-align:center"><input type="checkbox" class="preq"' + (req ? ' checked' : '') + '></td>' +
            '<td style="padding:4px 8px"><a href="#" onclick="document.getElementById(\'' + id + '\').remove();return false;" style="color:#cc0000;font-size:16px;text-decoration:none" title="Supprimer">✕</a></td>';
        document.getElementById('da-params-tbody').appendChild(tr);
    };

    // Initialisation depuis JSON existant
    try {
        var init = JSON.parse(document.getElementById('da-params-init').value || '[]');
        init.forEach(function (p) { daAddParam(p.name, p.type, p.description, p.required); });
    } catch (e) {}

    // Sérialisation au submit
    document.getElementById('da-tool-form').addEventListener('submit', function () {
        var params = [];
        document.querySelectorAll('#da-params-tbody tr').forEach(function (row) {
            var n = row.querySelector('.pname').value.trim();
            if (n) {
                params.push({
                    name: n,
                    type: row.querySelector('.ptype').value,
                    description: row.querySelector('.pdesc').value.trim(),
                    required: row.querySelector('.preq').checked
                });
            }
        });
        document.getElementById('da-parameters').value = JSON.stringify(params);
    });
})();
</script>
    <?php

} else {
    // ----------------------------------------------------------------
    // LISTE
    // ----------------------------------------------------------------

    if (!$daToolTableExists) {
        print '<div class="error" style="margin-bottom:12px">';
        print '<strong>La table des outils n\'existe pas encore.</strong><br>';
        if ($daToolCreateError) {
            print 'La création automatique a échoué : <code>'.dol_escape_htmltag($daToolCreateError).'</code><br>';
        }
        print 'Créez-la manuellement via <em>Accueil → Outils → SQL</em> en exécutant&nbsp;:';
        print '<pre style="background:#fff;padding:8px;border:1px solid #ddd;margin:8px 0;font-size:12px">CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX.'diamantassistant_tool (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(100) NOT NULL,
    label             VARCHAR(255) NOT NULL,
    description       TEXT NOT NULL,
    sql_query         TEXT NOT NULL,
    parameters        TEXT NOT NULL DEFAULT \'[]\',
    active            TINYINT DEFAULT 1 NOT NULL,
    date_creation     DATETIME NOT NULL,
    date_modification DATETIME DEFAULT NULL,
    fk_user_creat     INTEGER DEFAULT NULL
) ENGINE=innodb;
ALTER TABLE '.MAIN_DB_PREFIX.'diamantassistant_tool ADD UNIQUE INDEX uk_da_tool_name (name);</pre>';
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="create_table">';
        print '<input type="submit" class="button" value="Réessayer la création automatique">';
        print '</form>';
        print '</div>';
    } else {

    print '<div style="margin-bottom:12px">';
    print '<a href="'.$_SERVER['PHP_SELF'].'?action=create" class="button">+ Nouvel outil</a>';
    print '</div>';

    $resql = $db->query("SELECT rowid, name, label, description, active, date_creation FROM ".MAIN_DB_PREFIX."diamantassistant_tool ORDER BY name");

    if (!$resql) {
        print '<div class="error">Erreur SQL : '.dol_escape_htmltag($db->lasterror()).'</div>';
    } elseif ($db->num_rows($resql) === 0) {
        print '<div class="opacitymedium" style="padding:12px">';
        print 'Aucun outil personnalisé pour l\'instant. ';
        print '<a href="'.$_SERVER['PHP_SELF'].'?action=create">Créer le premier outil →</a>';
        print '</div>';
        $db->free($resql);
    } else {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>Nom</td><td>Libellé</td><td>Description</td>';
        print '<td style="text-align:center">Statut</td><td>Créé le</td><td>Actions</td>';
        print '</tr>';
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';
            print '<td><code>'.dol_escape_htmltag($obj->name).'</code></td>';
            print '<td>'.dol_escape_htmltag($obj->label).'</td>';
            print '<td><small>'.dol_escape_htmltag(dol_trunc($obj->description, 90)).'</small></td>';
            if ($obj->active) {
                print '<td style="text-align:center"><span style="color:#2a7a2a">● Actif</span></td>';
            } else {
                print '<td style="text-align:center"><span style="color:#999">● Inactif</span></td>';
            }
            print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
            print '<td>';
            print '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.(int) $obj->rowid.'" class="button">Modifier</a> ';
            $toggleLabel = $obj->active ? 'Désactiver' : 'Activer';
            print '<a href="'.$_SERVER['PHP_SELF'].'?action=toggle&id='.(int) $obj->rowid.'" class="button">'.$toggleLabel.'</a> ';
            print '<a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.(int) $obj->rowid.'" class="button button-delete"';
            print ' onclick="return confirm(\'Supprimer l\\\'outil '.dol_escape_js($obj->name).' ?\')">Supprimer</a>';
            print '</td>';
            print '</tr>';
        }
        print '</table>';
        $db->free($resql);
    }

    } // end if ($daToolTableExists)
}

print dol_get_fiche_end();

// --- Documentation des placeholders ---
print '<br>';
print '<div class="opacitymedium" style="padding:10px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px">';
print '<strong>Aide — Utilisation des placeholders dans la requête SQL</strong>';
print '<ul style="margin:8px 0">';
print '<li><code>{entity}</code> — remplacé automatiquement par l\'ID d\'entité Dolibarr (filtrage multi-entité)</li>';
print '<li><code>{mon_param}</code> avec type <strong>string</strong> — la valeur est échappée SQL ; à entourer de guillemets dans la requête : <code>\'%{mon_param}%\'</code></li>';
print '<li><code>{mon_param}</code> avec type <strong>integer</strong> — la valeur est passée en <code>intval()</code> ; pas de guillemets : <code>= {annee}</code></li>';
print '</ul>';
print '<strong>Exemple complet :</strong>';
print '<pre style="background:#fff;padding:8px;border:1px solid #ddd;border-radius:3px;font-size:12px;margin:4px 0">SELECT p.rowid, p.ref, p.label, p.price
FROM '.MAIN_DB_PREFIX.'product p
WHERE (p.ref LIKE \'%{reference}%\' OR p.label LIKE \'%{reference}%\')
AND p.entity = {entity}
ORDER BY p.ref
LIMIT 10</pre>';
print '<small>Paramètre requis : <code>reference</code> (string) — "Référence ou libellé partiel du produit à rechercher"</small>';
print '</div>';

llxFooter();
$db->close();
