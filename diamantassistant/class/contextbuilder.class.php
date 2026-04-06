<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Construit le prompt système envoyé à l'IA.
 * Assemble : identité du bot + base de connaissance + contexte utilisateur.
 */
class ContextBuilder
{
    private string $knowledgeDir;

    public function __construct(?string $knowledgeDir = null)
    {
        $this->knowledgeDir = $knowledgeDir ?? DOL_DOCUMENT_ROOT.'/custom/diamantassistant/knowledge';
    }

    /**
     * Construit la liste de messages à envoyer au provider.
     *
     * @param User   $user     Utilisateur Dolibarr courant
     * @param string $question Question posée par l'utilisateur
     * @param array  $history  Historique récent [['role'=>'user|assistant','content'=>'...']]
     * @param string $pageCtx  Contexte optionnel de la page courante (URL, fiche en cours...)
     * @return array           Liste de messages OpenAI-compatible
     */
    public function build($user, string $question, array $history = [], string $pageCtx = ''): array
    {
        $systemPrompt = $this->buildSystemPrompt($user, $pageCtx);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Historique récent (limité aux 6 derniers échanges pour maîtriser les tokens)
        $recentHistory = array_slice($history, -6);
        foreach ($recentHistory as $msg) {
            if (isset($msg['role'], $msg['content']) && in_array($msg['role'], ['user', 'assistant'], true)) {
                $messages[] = ['role' => $msg['role'], 'content' => (string) $msg['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        return $messages;
    }

    /**
     * Assemble le prompt système complet.
     */
    private function buildSystemPrompt($user, string $pageCtx): string
    {
        $parts = [];

        // 1. Identité et ton du bot
        $parts[] = $this->getIdentitySection();

        // 2. Base de connaissance (fichiers .md)
        $knowledge = $this->loadKnowledgeBase();
        if (!empty($knowledge)) {
            $parts[] = "## BASE DE CONNAISSANCE\n\n".$knowledge;
        }

        // 3. Contexte utilisateur
        $userCtx = $this->getUserContext($user);
        if (!empty($userCtx)) {
            $parts[] = "## UTILISATEUR COURANT\n\n".$userCtx;
        }

        // 4. Contexte de la page (si fourni par le widget JS)
        if (!empty($pageCtx)) {
            $parts[] = "## PAGE COURANTE\n\n".$pageCtx;
        }

        // 5. Règles de réponse
        $parts[] = $this->getRulesSection();

        return implode("\n\n---\n\n", $parts);
    }

    private function getIdentitySection(): string
    {
        return <<<TXT
## IDENTITÉ

Tu es l'assistant IA interne de **DIAMANT INDUSTRIE**, une entreprise spécialisée dans les plafonds métalliques suspendus et la sous-traitance métallique. Tu aides les 6 collaborateurs de l'entreprise à utiliser Dolibarr (ERP, version 20) au quotidien.

Tu t'adresses à des utilisateurs de **niveaux informatiques très variés**. Sois toujours :
- **Pédagogue** : explique simplement, sans jargon inutile.
- **Concret** : donne les étapes précises dans l'interface Dolibarr (menus, boutons, onglets).
- **Bienveillant** : jamais condescendant, même pour les questions basiques.
- **Bref** : va droit au but. Pour les réponses longues, structure avec des listes courtes.

Tu réponds **toujours en français**.
TXT;
    }

    private function getRulesSection(): string
    {
        return <<<TXT
## RÈGLES DE RÉPONSE IMPORTANTES

1. **Rappelle systématiquement les bons réflexes DIAMANT** quand c'est pertinent :
   - Avant de créer un nouveau produit, **toujours vérifier** qu'il n'existe pas déjà (recherche par référence ou libellé).
   - Pour un nouvel article acheté : créer d'abord le **produit à la vente** (fiche produit complète), puis ajouter le **produit fournisseur** dans l'onglet Fournisseurs. Ne jamais créer juste un produit fournisseur isolé.
   - Renseigner la **description** du produit (pas seulement la référence), elle apparaîtra sur les documents.
   - Vérifier les **catégories** pour un bon classement et les statistiques.

2. **Si tu ne sais pas**, dis-le clairement. Ne jamais inventer un menu, un bouton ou une option qui n'existe pas dans Dolibarr.

3. **Si la question sort de ton périmètre** (RH, juridique, compta complexe, fiscalité...), oriente la personne vers Patrice (administrateur Dolibarr) ou le bon interlocuteur.

4. **Pour les actions sensibles** (suppression, validation définitive, clôture d'exercice...), rappelle systématiquement de faire une vérification ou une sauvegarde au préalable.

5. Si l'utilisateur semble bloqué, propose-lui de **reformuler** ou de décrire précisément ce qu'il voit à l'écran.
TXT;
    }

    /**
     * Charge tous les fichiers .md du dossier knowledge/ et les concatène.
     */
    private function loadKnowledgeBase(): string
    {
        if (!is_dir($this->knowledgeDir)) {
            return '';
        }

        $files = glob($this->knowledgeDir.'/*.md');
        if (empty($files)) {
            return '';
        }

        sort($files);
        $content = '';
        foreach ($files as $file) {
            $name = basename($file, '.md');
            $fileContent = @file_get_contents($file);
            if ($fileContent !== false) {
                $content .= "### ".$name."\n\n".trim($fileContent)."\n\n";
            }
        }

        return trim($content);
    }

    /**
     * Retourne un résumé du contexte utilisateur Dolibarr.
     */
    private function getUserContext($user): string
    {
        if (!is_object($user) || empty($user->id)) {
            return '';
        }

        $lines = [];
        $lines[] = "- Nom : ".trim(($user->firstname ?? '').' '.($user->lastname ?? ''));
        if (!empty($user->job)) {
            $lines[] = "- Poste : ".$user->job;
        }
        if (!empty($user->admin)) {
            $lines[] = "- Rôle : administrateur Dolibarr";
        }

        return implode("\n", $lines);
    }
}
