<?php
/* Copyright (C) 2026 DIAMANT INDUSTRIE */

/**
 * Classe métier : gère la persistance des conversations et messages.
 */
class DiamantAssistant
{
    public DoliDB $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle conversation et retourne son ID.
     */
    public function createConversation(int $userId, string $pageContext = '', string $title = ''): int
    {
        $now = dol_now();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."diamantassistant_conversation";
        $sql .= " (fk_user, date_creation, context_page, title)";
        $sql .= " VALUES (";
        $sql .= " ".((int) $userId).",";
        $sql .= " '".$this->db->idate($now)."',";
        $sql .= " '".$this->db->escape(dol_trunc($pageContext, 255, 'right', 'UTF-8', 1))."',";
        $sql .= " '".$this->db->escape(dol_trunc($title, 255, 'right', 'UTF-8', 1))."'";
        $sql .= ")";

        $res = $this->db->query($sql);
        if (!$res) {
            dol_syslog("DiamantAssistant::createConversation error ".$this->db->lasterror(), LOG_ERR);
            return 0;
        }

        return (int) $this->db->last_insert_id(MAIN_DB_PREFIX."diamantassistant_conversation");
    }

    /**
     * Ajoute un message à une conversation.
     */
    public function addMessage(int $conversationId, string $role, string $content, int $tokens = 0, string $provider = ''): bool
    {
        $now = dol_now();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."diamantassistant_message";
        $sql .= " (fk_conversation, role, content, tokens_used, provider_used, date_creation)";
        $sql .= " VALUES (";
        $sql .= " ".((int) $conversationId).",";
        $sql .= " '".$this->db->escape($role)."',";
        $sql .= " '".$this->db->escape($content)."',";
        $sql .= " ".((int) $tokens).",";
        $sql .= " '".$this->db->escape($provider)."',";
        $sql .= " '".$this->db->idate($now)."'";
        $sql .= ")";

        $res = $this->db->query($sql);
        if (!$res) {
            dol_syslog("DiamantAssistant::addMessage error ".$this->db->lasterror(), LOG_ERR);
            return false;
        }

        return true;
    }

    /**
     * Récupère l'historique d'une conversation (ordre chronologique).
     * Retourne un tableau [['role'=>'user|assistant','content'=>'...']]
     */
    public function getHistory(int $conversationId, int $limit = 20): array
    {
        $history = [];
        $sql = "SELECT role, content FROM ".MAIN_DB_PREFIX."diamantassistant_message";
        $sql .= " WHERE fk_conversation = ".((int) $conversationId);
        $sql .= " AND role IN ('user','assistant')";
        $sql .= " ORDER BY rowid ASC";
        $sql .= " LIMIT ".((int) $limit);

        $res = $this->db->query($sql);
        if (!$res) {
            return $history;
        }
        while ($row = $this->db->fetch_object($res)) {
            $history[] = ['role' => $row->role, 'content' => $row->content];
        }
        return $history;
    }

    /**
     * Vérification simple de rate-limit : compte les messages user envoyés
     * dans la dernière minute par cet utilisateur, toutes conversations confondues.
     */
    public function countRecentMessages(int $userId, int $secondsWindow = 60): int
    {
        $since = dol_now() - $secondsWindow;
        $sql = "SELECT COUNT(m.rowid) as nb";
        $sql .= " FROM ".MAIN_DB_PREFIX."diamantassistant_message m";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."diamantassistant_conversation c ON c.rowid = m.fk_conversation";
        $sql .= " WHERE c.fk_user = ".((int) $userId);
        $sql .= " AND m.role = 'user'";
        $sql .= " AND m.date_creation >= '".$this->db->idate($since)."'";

        $res = $this->db->query($sql);
        if (!$res) {
            return 0;
        }
        $row = $this->db->fetch_object($res);
        return (int) ($row->nb ?? 0);
    }
}
