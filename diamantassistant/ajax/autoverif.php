<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Endpoint AJAX pour l'auto-verification IA avant validation.
 * Recoit un snapshot de la page, analyse le document selon les regles
 * definies dans autoverif.md, et retourne les alertes ou "RAS".
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', 1);
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK', 1);

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

dol_include_once('/diamantassistant/class/contextbuilder.class.php');
dol_include_once('/diamantassistant/core/lib/providers/ProviderFactory.class.php');

header('Content-Type: application/json; charset=utf-8');

// --- Securite
if (empty($user->id)) {
    http_response_code(401);
    echo json_encode(['error' => 'Utilisateur non authentifie.']);
    exit;
}
if (!$user->hasRight('diamantassistant', 'use') && empty($user->admin)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission refusee.']);
    exit;
}

// --- Verification que la fonctionnalite est activee
if ((int) getDolGlobalString('DIAMANTASSISTANT_AUTOVERIF_ENABLED', 0) !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Auto-verification desactivee.']);
    exit;
}

// --- Payload JSON
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload invalide.']);
    exit;
}

$module = trim((string) ($input['module'] ?? ''));
$pageSnapshot = null;
if (isset($input['page_snapshot']) && is_array($input['page_snapshot'])) {
    $snap = $input['page_snapshot'];
    $pageSnapshot = [
        'url'       => mb_substr((string) ($snap['url'] ?? ''), 0, 500),
        'title'     => mb_substr((string) ($snap['title'] ?? ''), 0, 300),
        'heading'   => mb_substr((string) ($snap['heading'] ?? ''), 0, 300),
        'text'      => mb_substr((string) ($snap['text'] ?? ''), 0, 8000),
        'truncated' => !empty($snap['truncated']),
    ];
}

if ($pageSnapshot === null || empty($pageSnapshot['text'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucun contenu de page a analyser.']);
    exit;
}

// --- Verification que le module est autorise
$allowedModules = explode(',', getDolGlobalString('DIAMANTASSISTANT_AUTOVERIF_MODULES', ''));
if (!empty($module) && !in_array($module, $allowedModules, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Module non autorise pour l\'auto-verification.']);
    exit;
}

// --- Charger les regles de verification
$builder = new ContextBuilder();
$rules = $builder->getAutoVerifRules();
if (empty($rules)) {
    $rules = "Verifier l'orthographe, la coherence des produits, les quantites aberrantes et les informations manquantes.";
}

// --- Noms lisibles des modules
$moduleLabels = [
    'commande'       => 'commande client',
    'propal'         => 'proposition commerciale',
    'supplier_order' => 'commande fournisseur',
    'expedition'     => 'expedition',
];
$moduleLabel = $moduleLabels[$module] ?? 'document';

// --- Construction du prompt de verification
$systemPrompt = <<<PROMPT
Tu es un assistant de verification de documents Dolibarr pour DIAMANT INDUSTRIE.
Tu analyses un document ({$moduleLabel}) AVANT sa validation pour detecter d'eventuels problemes.

REGLES DE VERIFICATION :
{$rules}

INSTRUCTIONS :
- Analyse le contenu du document ci-dessous selon les regles de verification.
- Si tu trouves des problemes ou des oublis, liste-les clairement avec des puces.
- Sois concis et actionnable : indique exactement quoi corriger.
- Si tout semble conforme et qu'il n'y a rien a signaler, reponds UNIQUEMENT par le mot : RAS
- Ne fais pas de commentaires positifs inutiles, va droit au but.
PROMPT;

$pageContent = '';
if (!empty($pageSnapshot['heading'])) {
    $pageContent .= "Titre : ".$pageSnapshot['heading']."\n";
}
$pageContent .= $pageSnapshot['text'];
if ($pageSnapshot['truncated']) {
    $pageContent .= "\n(contenu tronque)";
}

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user',   'content' => "Voici le contenu du document a verifier :\n\n".$pageContent],
];

// --- Appel IA (sans outils, analyse pure)
try {
    $provider = ProviderFactory::get();
    $reply = $provider->chat($messages, [
        'max_tokens'  => 600,
        'temperature' => 0.2,
    ]);
} catch (Exception $e) {
    dol_syslog('DiamantAssistant autoverif error: '.$e->getMessage(), LOG_ERR);
    http_response_code(500);
    echo json_encode(['error' => 'Erreur IA : '.$e->getMessage()]);
    exit;
}

// --- Detecter si RAS (rien a signaler)
$trimmedReply = trim($reply);
$isRas = (strcasecmp($trimmedReply, 'RAS') === 0 || strcasecmp($trimmedReply, 'R.A.S.') === 0);

echo json_encode([
    'ras'   => $isRas,
    'reply' => $isRas ? '' : $trimmedReply,
]);
