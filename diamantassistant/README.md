# DiamantAssistant

Module Dolibarr interne pour DIAMANT INDUSTRIE : assistant IA (chatbot) qui guide les utilisateurs sur Dolibarr et rappelle les process maison.

- **Provider IA** : Mistral AI (La Plateforme)
- **Cible** : Dolibarr 20.x sur PHP 8.2
- **Hébergement** : OVH mutualisé (pas de SSH requis, pur PHP + cURL)

---

## Installation

1. **Déposer le dossier** `diamantassistant/` dans `htdocs/custom/` de votre Dolibarr.
   ```
   htdocs/custom/diamantassistant/
   ```

2. **Activer les modules externes** dans Dolibarr si ce n'est pas déjà fait :
   - `htdocs/conf/conf.php` doit contenir :
     ```php
     $dolibarr_main_url_root_alt = '/custom';
     $dolibarr_main_document_root_alt = '/var/www/.../htdocs/custom';
     ```

3. **Activer le module** dans Dolibarr :
   - Accueil → Configuration → Modules → Autres → **DiamantAssistant** → activer.
   - Les tables SQL (`llx_diamantassistant_conversation`, `llx_diamantassistant_message`) sont créées automatiquement à l'activation.

4. **Configurer le module** :
   - Cliquer sur l'icône de configuration du module.
   - Coller la **clé API Mistral** (obtenue sur https://console.mistral.ai/).
   - Choisir le modèle (`mistral-small-latest` recommandé pour démarrer).
   - Régler le rate-limit (10 messages/min par utilisateur par défaut).
   - Cliquer sur **Tester la connexion** pour vérifier.

5. **Attribuer les droits** :
   - Accueil → Utilisateurs & Groupes → choisir un utilisateur → Permissions.
   - Cocher « Utiliser l'assistant IA » (`diamantassistant → use`) pour chaque utilisateur autorisé.

6. **Vérifier le widget** : une bulle 💬 doit apparaître en bas à droite de toutes les pages Dolibarr.

---

## Architecture

```
diamantassistant/
├── core/
│   ├── modules/modDiamantAssistant.class.php   ← descripteur du module
│   └── lib/
│       ├── diamantassistant.lib.php            ← helper d'injection widget
│       └── providers/
│           ├── AIProvider.interface.php        ← contrat commun
│           ├── MistralProvider.class.php       ← implémentation Mistral
│           └── ProviderFactory.class.php       ← sélection du provider actif
├── class/
│   ├── diamantassistant.class.php              ← persistance (conversations, messages)
│   ├── contextbuilder.class.php                ← assemblage du prompt système
│   └── actions_diamantassistant.class.php      ← hook printTopRightMenu
├── ajax/chat.php                               ← endpoint AJAX principal
├── js/chatbot.js                               ← widget vanilla JS
├── css/chatbot.css
├── sql/                                         ← 2 tables + clés
├── admin/setup.php                             ← page de configuration
├── langs/{fr_FR,en_US}/diamantassistant.lang
└── knowledge/                                  ← base de connaissance (.md)
    ├── dolibarr-general.md
    └── diamant-workflows.md
```

### Flux d'une requête

1. L'utilisateur tape une question dans le widget.
2. `chatbot.js` envoie en POST JSON à `ajax/chat.php` avec la question, l'URL courante et l'ID de conversation.
3. `chat.php` vérifie auth + droits + rate-limit, puis charge l'historique en base.
4. `ContextBuilder` assemble le prompt système : identité + base de connaissance (tous les `.md` de `knowledge/`) + contexte user + règles.
5. `ProviderFactory::get()` retourne le provider configuré (Mistral).
6. `MistralProvider::chat()` appelle l'API via cURL, récupère la réponse.
7. L'échange est loggé en base (conversation + messages).
8. JSON renvoyé au widget qui l'affiche.

---

## Enrichir la base de connaissance

C'est le levier principal pour améliorer les réponses. Il suffit d'éditer ou d'ajouter des fichiers `.md` dans `knowledge/` :

- Aucun redémarrage nécessaire, les fichiers sont relus à chaque requête.
- Structure libre, mais privilégier un titre `#` par section et du texte en prose courte.
- Attention à la taille totale : viser < 20 000 caractères au total pour rester économique en tokens.
- Chaque fichier est préfixé automatiquement par son nom de fichier dans le prompt.

---

## Ajouter un autre provider (Gemini, Groq, etc.)

1. Créer `core/lib/providers/GeminiProvider.class.php` qui implémente `AIProvider`.
2. Ajouter le `case` correspondant dans `ProviderFactory::get()`.
3. Ajouter l'option dans le `<select>` de `admin/setup.php`.

Le reste du code n'a pas à changer.

---

## Debug

### Logs Dolibarr
Les erreurs du provider et de la persistance sont loggées via `dol_syslog` — visibles dans `documents/dolibarr.log` ou l'onglet Logs de la barre de débogage.

### Widget n'apparaît pas
- Vérifier que le module est activé.
- Vérifier que l'utilisateur a le droit `diamantassistant → use`.
- Vérifier que `DIAMANTASSISTANT_ENABLED_WIDGET` vaut `1`.
- Inspecter l'onglet Hooks de la barre de débogage : le hook `printTopRightMenu` doit lister le contexte `toprightmenu` du module.
- Ouvrir la console navigateur : `window.DIAMANTASSISTANT_AJAX_URL` doit être défini.

### Erreur 401/403 sur l'endpoint AJAX
- L'utilisateur n'a pas le droit `diamantassistant → use` → le donner dans l'admin des permissions.

### Erreur 429 depuis Mistral
- Limite de requêtes atteinte côté Mistral (plan gratuit restrictif).
- Patienter ou passer sur un modèle plus rapide (Small au lieu de Large).

### Réponses de mauvaise qualité
- Enrichir les fichiers `knowledge/*.md` avec des cas concrets et des instructions explicites.
- Augmenter `max_tokens` dans `MistralProvider::chat()` si les réponses sont coupées.
- Tester avec `mistral-medium-latest` ou `mistral-large-latest` pour comparer.

---

## Évolutions possibles (v2+)

- **Streaming SSE** des réponses (si l'hébergement le permet).
- **Contexte de page enrichi** : détecter automatiquement la fiche courante (commande, produit, client) et l'injecter dans le prompt.
- **RAG vectoriel** quand la base de connaissance dépassera 50+ pages (embeddings Mistral + recherche cosinus en SQL).
- **Function calling** pour permettre au bot d'interroger Dolibarr en lecture seule (liste devis, stocks, etc.).
- **Fallback multi-provider** automatique si Mistral est indisponible.
- **Historique utilisateur** : page listant les conversations passées.

---

## Licence

Usage interne DIAMANT INDUSTRIE. Non destiné à la distribution publique.
