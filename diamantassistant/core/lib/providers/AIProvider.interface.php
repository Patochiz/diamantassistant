<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Interface contract pour tous les providers d'IA.
 * Permet de changer de provider (Mistral, Gemini, Groq...) sans toucher au reste du code.
 */
interface AIProvider
{
    /**
     * Envoie une liste de messages à l'API et retourne la réponse texte.
     *
     * @param array $messages Tableau de messages au format OpenAI-compatible :
     *                        [['role' => 'system', 'content' => '...'],
     *                         ['role' => 'user',   'content' => '...'],
     *                         ['role' => 'assistant', 'content' => '...']]
     * @param array $options  Options provider-specific (temperature, max_tokens...)
     * @return string         Contenu texte de la réponse de l'assistant
     * @throws Exception      Si l'appel API échoue
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Nom humain du provider (pour logs / UI).
     */
    public function getName(): string;

    /**
     * Nombre de tokens utilisés par le dernier appel (input + output).
     * Retourne 0 si non disponible.
     */
    public function getLastTokensUsed(): int;
}
