<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

require_once __DIR__.'/AIProvider.interface.php';

/**
 * Implémentation provider Mistral AI (La Plateforme).
 * Documentation : https://docs.mistral.ai/api/
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

    public function chat(array $messages, array $options = []): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? 800,
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.$this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erreur cURL Mistral : '.$curlError);
        }

        if ($httpCode === 429) {
            throw new Exception('Limite de requêtes Mistral atteinte. Réessayez dans quelques secondes.');
        }

        if ($httpCode !== 200) {
            $errMsg = 'HTTP '.$httpCode;
            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['message'])) {
                $errMsg .= ' — '.$decoded['message'];
            } elseif (is_array($decoded) && isset($decoded['error']['message'])) {
                $errMsg .= ' — '.$decoded['error']['message'];
            }
            throw new Exception('Erreur API Mistral : '.$errMsg);
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Réponse Mistral invalide.');
        }

        $this->lastTokensUsed = (int) ($data['usage']['total_tokens'] ?? 0);

        return (string) $data['choices'][0]['message']['content'];
    }

    public function getName(): string
    {
        return 'Mistral ('.$this->model.')';
    }

    public function getLastTokensUsed(): int
    {
        return $this->lastTokensUsed;
    }
}
