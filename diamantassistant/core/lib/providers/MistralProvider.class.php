<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

require_once __DIR__.'/AIProvider.interface.php';

/**
 * Implémentation provider Mistral AI (La Plateforme).
 * Documentation : https://docs.mistral.ai/api/
 *
 * Supporte le function calling (tool_calls) via la boucle interne :
 * l'appelant passe un callable $toolExecutor dans les options pour exécuter
 * les outils demandés par l'IA avant de renvoyer la réponse finale.
 */
class MistralProvider implements AIProvider
{
    private string $apiKey;
    private string $model;
    private string $endpoint = 'https://api.mistral.ai/v1/chat/completions';
    private int $lastTokensUsed = 0;
    private int $timeout = 60;

    public function __construct(string $apiKey, string $model = 'mistral-small-latest')
    {
        if (empty($apiKey)) {
            throw new Exception('Clé API Mistral manquante. Configurez-la dans Accueil > Configuration > Modules > DiamantAssistant.');
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Envoie les messages au modèle et retourne la réponse textuelle.
     *
     * Options supportées :
     *   - temperature (float)       : défaut 0.3
     *   - max_tokens (int)          : défaut 800
     *   - tools (array)             : définitions d'outils JSON (function calling)
     *   - tool_executor (callable)  : callable(string $name, array $args): string
     *                                 Appelé pour chaque outil demandé par l'IA.
     *
     * @param array $messages  Messages au format OpenAI (role/content)
     * @param array $options   Options supplémentaires
     * @return string          Réponse textuelle finale de l'IA
     * @throws Exception
     */
    public function chat(array $messages, array $options = []): string
    {
        $tools        = $options['tools']         ?? [];
        $toolExecutor = $options['tool_executor']  ?? null;

        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens'  => $options['max_tokens']  ?? 800,
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        // Boucle tool-calling : l'IA peut demander plusieurs outils successifs.
        // On limite à 5 tours pour éviter toute boucle infinie.
        for ($i = 0; $i < 5; $i++) {
            $response = $this->callApi($payload);

            $choice       = $response['choices'][0];
            $finishReason = $choice['finish_reason'] ?? 'stop';

            // Accumuler les tokens (dernier appel API fait foi)
            $this->lastTokensUsed = (int) ($response['usage']['total_tokens'] ?? 0);

            if ($finishReason !== 'tool_calls') {
                // Réponse finale — contenu textuel
                return (string) ($choice['message']['content'] ?? '');
            }

            // L'IA demande un ou plusieurs appels d'outils
            $assistantMsg = $choice['message'];
            $payload['messages'][] = $assistantMsg;

            foreach ($assistantMsg['tool_calls'] as $toolCall) {
                $name   = $toolCall['function']['name'];
                $args   = json_decode($toolCall['function']['arguments'], true) ?? [];
                $result = $toolExecutor
                    ? call_user_func($toolExecutor, $name, $args)
                    : json_encode(['error' => 'Aucun exécuteur d\'outil configuré.']);

                dol_syslog('DiamantAssistant tool_call: '.$name.' -> '.mb_substr($result, 0, 200), LOG_DEBUG);

                $payload['messages'][] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content'      => $result,
                ];
            }
        }

        throw new Exception('Trop d\'appels d\'outils successifs (boucle infinie détectée).');
    }

    public function getName(): string
    {
        return 'Mistral ('.$this->model.')';
    }

    public function getLastTokensUsed(): int
    {
        return $this->lastTokensUsed;
    }

    /**
     * Effectue un appel cURL vers l'API Mistral et retourne le tableau décodé.
     *
     * @param array $payload
     * @return array
     * @throws Exception
     */
    private function callApi(array $payload): array
    {
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST          => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT       => $this->timeout,
            CURLOPT_POSTFIELDS    => json_encode($payload),
            CURLOPT_HTTPHEADER    => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.$this->apiKey,
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erreur cURL Mistral : '.$curlError);
        }

        if ($httpCode === 429) {
            throw new Exception('Limite de requêtes Mistral atteinte. Réessayez dans quelques secondes.');
        }

        if ($httpCode !== 200) {
            $errMsg  = 'HTTP '.$httpCode;
            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['message'])) {
                $errMsg .= ' — '.$decoded['message'];
            } elseif (is_array($decoded) && isset($decoded['error']['message'])) {
                $errMsg .= ' — '.$decoded['error']['message'];
            }
            throw new Exception('Erreur API Mistral : '.$errMsg);
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['choices'][0]['message'])) {
            throw new Exception('Réponse Mistral invalide.');
        }

        return $data;
    }
}
