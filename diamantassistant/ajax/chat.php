<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Endpoint AJAX pour le widget de chat.
 * Reçoit une question, appelle le provider IA, renvoie la réponse en JSON.
 */

// Chargement de Dolibarr (remonte jusqu'à trouver main.inc.php)
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}

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

dol_include_once('/diamantassistant/class/diamantassistant.class.php');
dol_include_once('/diamantassistant/class/contextbuilder.class.php');
dol_include_once('/diamantassistant/core/lib/providers/ProviderFactory.class.php');

header('Content-Type: application/json; charset=utf-8');

// --- Sécurité : utilisateur connecté + droit d'usage
if (empty($user->id)) {
    http_response_code(401);
    echo json_encode(['error' => 'Utilisateur non authentifié.']);
    exit;
}
if (!$user->hasRight('diamantassistant', 'use') && empty($user->admin)) {
    http_response_code(403);
    echo json_encode(['error' => 'Vous n\'avez pas la permission d\'utiliser l\'assistant.']);
    exit;
}

// --- Récupération du payload JSON
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload invalide.']);
    exit;
}

$question = trim((string) ($input['question'] ?? ''));
$conversationId = (int) ($input['conversation_id'] ?? 0);
$pageContext = trim((string) ($input['page_context'] ?? ''));

if (empty($question)) {
    http_response_code(400);
    echo json_encode(['error' => 'Question vide.']);
    exit;
}
if (mb_strlen($question) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Question trop longue (2000 caractères max).']);
    exit;
}

$assistant = new DiamantAssistant($db);

// --- Rate-limit basique
$rateLimit = (int) getDolGlobalString('DIAMANTASSISTANT_RATE_LIMIT_PER_MIN', 10);
if ($rateLimit > 0) {
    $recent = $assistant->countRecentMessages((int) $user->id, 60);
    if ($recent >= $rateLimit) {
        http_response_code(429);
        echo json_encode(['error' => 'Trop de messages envoyés. Patientez une minute.']);
        exit;
    }
}

// --- Gestion conversation
$logEnabled = (int) getDolGlobalString('DIAMANTASSISTANT_LOG_CONVERSATIONS', 1) === 1;

if ($conversationId <= 0 && $logEnabled) {
    $conversationId = $assistant->createConversation(
        (int) $user->id,
        $pageContext,
        dol_trunc($question, 80)
    );
}

$history = [];
if ($conversationId > 0 && $logEnabled) {
    $history = $assistant->getHistory($conversationId);
}

// --- Construction du prompt
$builder = new ContextBuilder();
$messages = $builder->build($user, $question, $history, $pageContext);

// --- Appel au provider
try {
    $provider = ProviderFactory::get();
    $reply = $provider->chat($messages);
    $tokensUsed = $provider->getLastTokensUsed();
    $providerName = $provider->getName();
} catch (Exception $e) {
    dol_syslog("DiamantAssistant chat error: ".$e->getMessage(), LOG_ERR);
    http_response_code(500);
    echo json_encode(['error' => 'Erreur assistant IA : '.$e->getMessage()]);
    exit;
}

// --- Logging
if ($logEnabled && $conversationId > 0) {
    $assistant->addMessage($conversationId, 'user', $question, 0, $providerName);
    $assistant->addMessage($conversationId, 'assistant', $reply, $tokensUsed, $providerName);
}

// --- Réponse
echo json_encode([
    'reply' => $reply,
    'conversation_id' => $conversationId,
    'tokens' => $tokensUsed,
    'provider' => $providerName,
]);
