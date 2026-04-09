<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Outils de consultation base de données en lecture seule pour l'IA.
 * Chaque outil correspond à une requête SQL pré-définie et sécurisée.
 * L'IA ne peut exécuter que ces outils (whitelist) — jamais de SQL libre.
 */
class DatabaseTools
{
    /**
     * Retourne les définitions d'outils au format JSON attendu par l'API Mistral (OpenAI-compatible).
     * Inclut les outils natifs + les outils personnalisés actifs chargés depuis la base de données.
     *
     * @param object|null $db  Objet DoliDB (pour charger les outils dynamiques)
     * @return array
     */
    public static function getToolDefinitions($db = null): array
    {
        $staticTools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'read_current_page',
                    'description' => 'Lit le contenu de la page Dolibarr actuellement affichée à l\'écran par l\'utilisateur (titre, entête de fiche, texte principal). Utilise cet outil dès que l\'utilisateur fait référence à ce qu\'il voit (« cette page », « cette fiche », « ici », « à l\'écran », « ce produit », etc.) ou quand la question est ambiguë sans ce contexte. Aucun paramètre.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'read_knowledge_file',
                    'description' => 'Lit le contenu intégral d\'un document de la base de connaissance interne DIAMANT (fichiers .md listés dans la section BASE DE CONNAISSANCE DISPONIBLE du prompt système). Utilise cet outil dès qu\'une question touche à un sujet couvert par l\'un de ces documents — par exemple process internes, conventions de nommage, procédures. Passe le nom du document (sans extension .md).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Nom du document à lire (ex: "diamant-workflows"), tel qu\'indiqué dans l\'index de la base de connaissance. Sans l\'extension .md.',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_orders',
                    'description' => 'Recherche des commandes clients dans Dolibarr par référence de commande ou nom de client. Retourne les 10 dernières correspondances avec date, montant HT/TTC et statut.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reference' => [
                                'type' => 'string',
                                'description' => 'Référence de commande (ex: CMD-2025-0042) ou nom partiel du client à rechercher.',
                            ],
                        ],
                        'required' => ['reference'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_invoices',
                    'description' => 'Recherche des factures clients dans Dolibarr par référence de facture ou nom de client. Retourne les 10 dernières correspondances avec date, montant HT/TTC et statut de paiement.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reference' => [
                                'type' => 'string',
                                'description' => 'Référence de facture (ex: FA-2025-0120) ou nom partiel du client à rechercher.',
                            ],
                        ],
                        'required' => ['reference'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_supplier_orders',
                    'description' => 'Recherche des commandes fournisseurs dans Dolibarr par référence ou nom de fournisseur. Retourne les 10 dernières correspondances avec date, montant HT/TTC et statut.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reference' => [
                                'type' => 'string',
                                'description' => 'Référence de commande fournisseur (ex: PF-2025-0015) ou nom partiel du fournisseur à rechercher.',
                            ],
                        ],
                        'required' => ['reference'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_monthly_revenue',
                    'description' => 'Calcule le chiffre d\'affaires (CA) d\'un mois donné à partir des factures clients validées et payées dans Dolibarr. Retourne le total HT, total TTC et le nombre de factures.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'year' => [
                                'type' => 'integer',
                                'description' => 'Année (ex: 2025).',
                            ],
                            'month' => [
                                'type' => 'integer',
                                'description' => 'Mois en chiffre de 1 (janvier) à 12 (décembre).',
                            ],
                        ],
                        'required' => ['year', 'month'],
                    ],
                ],
            ],
        ];

        if ($db !== null) {
            $dynamicTools = self::loadDynamicToolDefinitions($db);
            return array_merge($staticTools, $dynamicTools);
        }

        return $staticTools;
    }

    /**
     * Charge les définitions des outils personnalisés actifs depuis la base de données.
     *
     * @param object $db
     * @return array
     */
    private static function loadDynamicToolDefinitions($db): array
    {
        $tools = [];
        $sql   = "SELECT name, description, parameters FROM ".MAIN_DB_PREFIX."diamantassistant_tool WHERE active = 1 ORDER BY name";
        $resql = $db->query($sql);
        if (!$resql) {
            return [];  // Table inexistante ou erreur — ne pas bloquer le chat
        }
        while ($obj = $db->fetch_object($resql)) {
            $params     = json_decode($obj->parameters ?: '[]', true) ?? [];
            $properties = [];
            $required   = [];
            foreach ($params as $p) {
                $pName = (string) ($p['name'] ?? '');
                if (empty($pName)) continue;
                $properties[$pName] = [
                    'type'        => (($p['type'] ?? 'string') === 'integer') ? 'integer' : 'string',
                    'description' => (string) ($p['description'] ?? ''),
                ];
                if (!empty($p['required'])) {
                    $required[] = $pName;
                }
            }
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name'        => $obj->name,
                    'description' => $obj->description,
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => $properties,
                        'required'   => $required,
                    ],
                ],
            ];
        }
        $db->free($resql);
        return $tools;
    }

    /**
     * Exécute un outil par son nom avec les arguments fournis par l'IA.
     * Retourne le résultat encodé en JSON pour être renvoyé à l'API Mistral.
     *
     * @param string $toolName  Nom de l'outil (search_orders, search_invoices, etc.)
     * @param array  $args      Arguments passés par l'IA
     * @param object $db        Objet DoliDB Dolibarr
     * @param object $conf      Objet Conf Dolibarr (pour l'entité)
     * @return string           JSON du résultat
     */
    public static function execute(string $toolName, array $args, $db, $conf): string
    {
        try {
            switch ($toolName) {
                case 'search_orders':
                    return self::searchOrders((string) ($args['reference'] ?? ''), $db, $conf);
                case 'search_invoices':
                    return self::searchInvoices((string) ($args['reference'] ?? ''), $db, $conf);
                case 'search_supplier_orders':
                    return self::searchSupplierOrders((string) ($args['reference'] ?? ''), $db, $conf);
                case 'get_monthly_revenue':
                    return self::getMonthlyRevenue((int) ($args['year'] ?? 0), (int) ($args['month'] ?? 0), $db, $conf);
                default:
                    // Outil non natif : chercher dans les outils personnalisés
                    return self::executeDynamicTool($toolName, $args, $db, $conf);
            }
        } catch (Exception $e) {
            dol_syslog('DiamantAssistant DatabaseTools error ('.$toolName.'): '.$e->getMessage(), LOG_ERR);
            return json_encode(['error' => 'Erreur lors de la recherche : '.$e->getMessage()]);
        }
    }

    /**
     * Recherche des commandes clients par référence ou nom de client.
     */
    private static function searchOrders(string $reference, $db, $conf): string
    {
        if (empty($reference)) {
            return json_encode(['error' => 'Référence vide.']);
        }

        $entity  = (int) $conf->entity;
        $escaped = $db->escape(str_replace(['%', '_'], ['\\%', '\\_'], $reference));

        $sql = "SELECT c.rowid, c.ref,";
        $sql .= " DATE_FORMAT(c.date_commande, '%d/%m/%Y') AS date_cmd,";
        $sql .= " c.total_ht, c.total_ttc, c.fk_statut AS status,";
        $sql .= " s.nom AS client";
        $sql .= " FROM ".MAIN_DB_PREFIX."commande c";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
        $sql .= " WHERE (c.ref LIKE '%".$escaped."%' OR s.nom LIKE '%".$escaped."%')";
        $sql .= " AND c.entity = ".$entity;
        $sql .= " ORDER BY c.date_commande DESC";
        $sql .= " LIMIT 10";

        return self::runQuery($sql, $db, 'commandes clients', function ($obj) {
            $statuts = [
                -1 => 'Annulée',
                0  => 'Brouillon',
                1  => 'Validée',
                2  => 'Expédiée',
                3  => 'Livrée',
            ];
            return [
                'ref'      => $obj->ref,
                'date'     => $obj->date_cmd,
                'client'   => $obj->client,
                'total_ht' => number_format((float) $obj->total_ht, 2, ',', ' ').' €',
                'total_ttc'=> number_format((float) $obj->total_ttc, 2, ',', ' ').' €',
                'statut'   => $statuts[(int) $obj->status] ?? 'Statut '.$obj->status,
                'url'      => dol_buildpath('/commande/card.php', 2).'?id='.(int) $obj->rowid,
            ];
        });
    }

    /**
     * Recherche des factures clients par référence ou nom de client.
     */
    private static function searchInvoices(string $reference, $db, $conf): string
    {
        if (empty($reference)) {
            return json_encode(['error' => 'Référence vide.']);
        }

        $entity  = (int) $conf->entity;
        $escaped = $db->escape(str_replace(['%', '_'], ['\\%', '\\_'], $reference));

        $sql = "SELECT f.rowid, f.ref,";
        $sql .= " DATE_FORMAT(f.datef, '%d/%m/%Y') AS date_facture,";
        $sql .= " f.total_ht, f.total_ttc, f.fk_statut, f.paye,";
        $sql .= " s.nom AS client";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture f";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc";
        $sql .= " WHERE (f.ref LIKE '%".$escaped."%' OR s.nom LIKE '%".$escaped."%')";
        $sql .= " AND f.entity = ".$entity;
        $sql .= " ORDER BY f.datef DESC";
        $sql .= " LIMIT 10";

        return self::runQuery($sql, $db, 'factures clients', function ($obj) {
            $statuts = [
                0 => 'Brouillon',
                1 => 'Validée',
                2 => 'Abandonnée',
                3 => 'Annulée',
            ];
            $statut = $statuts[(int) $obj->fk_statut] ?? 'Statut '.$obj->fk_statut;
            if ((int) $obj->fk_statut === 1 && (int) $obj->paye === 1) {
                $statut = 'Payée';
            }
            return [
                'ref'       => $obj->ref,
                'date'      => $obj->date_facture,
                'client'    => $obj->client,
                'total_ht'  => number_format((float) $obj->total_ht, 2, ',', ' ').' €',
                'total_ttc' => number_format((float) $obj->total_ttc, 2, ',', ' ').' €',
                'statut'    => $statut,
                'url'       => dol_buildpath('/compta/facture/card.php', 2).'?id='.(int) $obj->rowid,
            ];
        });
    }

    /**
     * Recherche des commandes fournisseurs par référence ou nom de fournisseur.
     */
    private static function searchSupplierOrders(string $reference, $db, $conf): string
    {
        if (empty($reference)) {
            return json_encode(['error' => 'Référence vide.']);
        }

        $entity  = (int) $conf->entity;
        $escaped = $db->escape(str_replace(['%', '_'], ['\\%', '\\_'], $reference));

        $sql = "SELECT cf.rowid, cf.ref,";
        $sql .= " DATE_FORMAT(cf.date_commande, '%d/%m/%Y') AS date_cmd,";
        $sql .= " cf.total_ht, cf.total_ttc, cf.fk_statut AS status,";
        $sql .= " s.nom AS fournisseur";
        $sql .= " FROM ".MAIN_DB_PREFIX."commande_fournisseur cf";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = cf.fk_soc";
        $sql .= " WHERE (cf.ref LIKE '%".$escaped."%' OR s.nom LIKE '%".$escaped."%')";
        $sql .= " AND cf.entity = ".$entity;
        $sql .= " ORDER BY cf.date_commande DESC";
        $sql .= " LIMIT 10";

        return self::runQuery($sql, $db, 'commandes fournisseurs', function ($obj) {
            $statuts = [
                -1 => 'Annulée',
                0  => 'Brouillon',
                1  => 'Validée',
                2  => 'Approuvée',
                3  => 'Commandée',
                4  => 'Reçue partiellement',
                5  => 'Reçue',
                6  => 'Facturée',
                7  => 'Annulée (approuvée)',
                9  => 'Refusée',
            ];
            return [
                'ref'         => $obj->ref,
                'date'        => $obj->date_cmd,
                'fournisseur' => $obj->fournisseur,
                'total_ht'    => number_format((float) $obj->total_ht, 2, ',', ' ').' €',
                'total_ttc'   => number_format((float) $obj->total_ttc, 2, ',', ' ').' €',
                'statut'      => $statuts[(int) $obj->status] ?? 'Statut '.$obj->status,
                'url'         => dol_buildpath('/fourn/commande/card.php', 2).'?id='.(int) $obj->rowid,
            ];
        });
    }

    /**
     * Calcule le CA mensuel (factures validées + payées).
     */
    private static function getMonthlyRevenue(int $year, int $month, $db, $conf): string
    {
        if ($year < 2000 || $year > 2100) {
            return json_encode(['error' => 'Année invalide : '.$year]);
        }
        if ($month < 1 || $month > 12) {
            return json_encode(['error' => 'Mois invalide : '.$month.' (attendu 1-12)']);
        }

        $entity = (int) $conf->entity;

        $sql = "SELECT";
        $sql .= " SUM(f.total_ht) AS ca_ht,";
        $sql .= " SUM(f.total_ttc) AS ca_ttc,";
        $sql .= " COUNT(*) AS nb_factures";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture f";
        $sql .= " WHERE f.fk_statut = 1";
        $sql .= " AND YEAR(f.datef) = ".$year;
        $sql .= " AND MONTH(f.datef) = ".$month;
        $sql .= " AND f.entity = ".$entity;

        $resql = $db->query($sql);
        if (!$resql) {
            throw new Exception($db->lasterror());
        }

        $obj = $db->fetch_object($resql);
        $db->free($resql);

        $moisNoms = [
            1  => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5  => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9  => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        $caHt  = (float) ($obj->ca_ht  ?? 0);
        $caTtc = (float) ($obj->ca_ttc ?? 0);
        $nb    = (int)   ($obj->nb_factures ?? 0);

        return json_encode([
            'periode'      => $moisNoms[$month].' '.$year,
            'ca_ht'        => number_format($caHt, 2, ',', ' ').' €',
            'ca_ttc'       => number_format($caTtc, 2, ',', ' ').' €',
            'nb_factures'  => $nb,
            'note'         => 'Basé sur les factures validées et payées (brouillons et annulées exclus).',
        ]);
    }

    /**
     * Exécute un outil personnalisé défini en base de données.
     * Substitue les placeholders {param} et {entity} dans la requête SQL.
     *
     * @param string $toolName
     * @param array  $args
     * @param object $db
     * @param object $conf
     * @return string JSON
     * @throws Exception
     */
    private static function executeDynamicTool(string $toolName, array $args, $db, $conf): string
    {
        $escaped = $db->escape($toolName);
        $resql   = $db->query("SELECT sql_query, parameters FROM ".MAIN_DB_PREFIX."diamantassistant_tool WHERE name = '".$escaped."' AND active = 1");
        if (!$resql || !($obj = $db->fetch_object($resql))) {
            return json_encode(['error' => 'Outil inconnu ou inactif : '.$toolName]);
        }
        $db->free($resql);

        $sqlQuery = (string) $obj->sql_query;
        $params   = json_decode((string) $obj->parameters, true) ?? [];

        // Vérification de sécurité (normalement déjà validé à la saisie)
        if (!preg_match('/^\s*SELECT\b/i', $sqlQuery) || strpos($sqlQuery, ';') !== false) {
            return json_encode(['error' => 'Requête non autorisée.']);
        }

        // Substituer {entity}
        $sqlQuery = str_replace('{entity}', (int) $conf->entity, $sqlQuery);

        // Substituer les paramètres déclarés
        foreach ($params as $p) {
            $pName = (string) ($p['name'] ?? '');
            if (empty($pName)) continue;
            $pType  = ($p['type'] ?? 'string') === 'integer' ? 'integer' : 'string';
            $pValue = $args[$pName] ?? '';
            if ($pType === 'integer') {
                $sqlQuery = str_replace('{'.$pName.'}', intval($pValue), $sqlQuery);
            } else {
                $safeVal  = $db->escape(str_replace(['%', '_'], ['\\%', '\\_'], (string) $pValue));
                $sqlQuery = str_replace('{'.$pName.'}', $safeVal, $sqlQuery);
            }
        }

        // Ajouter LIMIT si absent
        if (!preg_match('/\bLIMIT\s+\d+/i', $sqlQuery)) {
            $sqlQuery .= ' LIMIT 20';
        }

        return self::runQuery($sqlQuery, $db, 'résultats', function ($row) {
            return (array) $row;
        });
    }

    /**
     * Exécute une requête SELECT et transforme les résultats via un callable.
     */
    private static function runQuery(string $sql, $db, string $label, callable $mapper): string
    {
        $resql = $db->query($sql);
        if (!$resql) {
            throw new Exception($db->lasterror());
        }

        $results = [];
        while ($obj = $db->fetch_object($resql)) {
            $results[] = $mapper($obj);
        }
        $db->free($resql);

        if (empty($results)) {
            return json_encode(['message' => 'Aucun résultat trouvé parmi les '.$label.'.', 'resultats' => []]);
        }

        return json_encode(['nb_resultats' => count($results), 'resultats' => $results]);
    }
}
