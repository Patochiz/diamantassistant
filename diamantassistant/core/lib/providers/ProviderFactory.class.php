<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

require_once __DIR__.'/AIProvider.interface.php';
require_once __DIR__.'/MistralProvider.class.php';

/**
 * Factory qui instancie le bon provider selon la configuration Dolibarr.
 * Permet d'ajouter d'autres providers (Gemini, Groq...) sans toucher au reste.
 */
class ProviderFactory
{
    /**
     * Retourne une instance du provider configuré.
     *
     * @return AIProvider
     * @throws Exception
     */
    public static function get(): AIProvider
    {
        global $conf;

        $providerName = getDolGlobalString('DIAMANTASSISTANT_PROVIDER', 'mistral');

        switch ($providerName) {
            case 'mistral':
                $apiKey = getDolGlobalString('DIAMANTASSISTANT_MISTRAL_API_KEY', '');
                // Déchiffrement si la clé a été stockée avec dolEncrypt()
                if (!empty($apiKey) && function_exists('dolDecrypt')) {
                    $decrypted = dolDecrypt($apiKey);
                    if (!empty($decrypted)) {
                        $apiKey = $decrypted;
                    }
                }
                $model = getDolGlobalString('DIAMANTASSISTANT_MISTRAL_MODEL', 'mistral-small-latest');
                return new MistralProvider($apiKey, $model);

            default:
                throw new Exception('Provider IA inconnu : '.$providerName);
        }
    }
}
