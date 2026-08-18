<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ConversationRepository
{
    /** Numero massimo di conversazioni mantenute nello storico (per la sidebar). */
    public const MAX_CONVERSATIONS = 30;

    public function __construct(private readonly PDO $db)
    {
    }

    // -----------------------------------------------------------------------
    // Conversazioni
    // -----------------------------------------------------------------------

    public function createConversation(?string $title = null): string
    {
        $id = self::uuidV4();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $stmt = $this->db->prepare(
            'INSERT INTO conversations (id, title, created_at, updated_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$id, $title, $now, $now]);

        // Storico limitato: oltre le più recenti, le conversazioni vecchie
        // vengono eliminate (i messaggi seguono via ON DELETE CASCADE).
        $this->pruneConversations(self::MAX_CONVERSATIONS);

        return $id;
    }

    public function conversationExists(string $conversationId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        return (bool) $stmt->fetchColumn();
    }

    public function touchConversation(string $conversationId): void
    {
        $stmt = $this->db->prepare('UPDATE conversations SET updated_at = ? WHERE id = ?');
        $stmt->execute([gmdate('Y-m-d\TH:i:s\Z'), $conversationId]);
    }

    /**
     * Elenco delle conversazioni più recenti per la sidebar (max 30).
     *
     * @return array<int, array{id: string, title: ?string, updated_at: string}>
     */
    public function listConversations(int $limit = self::MAX_CONVERSATIONS): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, updated_at FROM conversations ORDER BY updated_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function renameConversation(string $conversationId, string $title): void
    {
        $stmt = $this->db->prepare('UPDATE conversations SET title = ? WHERE id = ?');
        $stmt->execute([$title, $conversationId]);
    }

    public function deleteConversation(string $conversationId): void
    {
        // I messaggi vengono eliminati dal vincolo ON DELETE CASCADE.
        $stmt = $this->db->prepare('DELETE FROM conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
    }

    private function pruneConversations(int $keep): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM conversations WHERE id NOT IN (
                SELECT id FROM conversations ORDER BY updated_at DESC, id DESC LIMIT :keep
            )'
        );
        $stmt->bindValue('keep', $keep, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Messaggi
    // -----------------------------------------------------------------------

    public function addMessage(
        string $conversationId,
        string $role,
        ?string $content,
        ?string $toolCallId = null,
        ?array $toolCalls = null
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (conversation_id, role, content, tool_call_id, tool_calls_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $conversationId,
            $role,
            $content,
            $toolCallId,
            $toolCalls !== null ? json_encode($toolCalls) : null,
            gmdate('Y-m-d\TH:i:s\Z'),
        ]);
        $this->touchConversation($conversationId);
    }

    /**
     * Ritorna lo storico nel formato atteso dalla Chat Completions API di OpenAI.
     */
    public function getHistoryForLlm(string $conversationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT role, content, tool_call_id, tool_calls_json FROM messages
             WHERE conversation_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$conversationId]);

        $messages = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $message = ['role' => $row['role']];

            if ($row['content'] !== null) {
                $message['content'] = $row['content'];
            }
            if ($row['role'] === 'assistant' && $row['tool_calls_json'] !== null) {
                $message['tool_calls'] = json_decode($row['tool_calls_json'], true);
                if (!isset($message['content'])) {
                    $message['content'] = null;
                }
            }
            if ($row['role'] === 'tool' && $row['tool_call_id'] !== null) {
                $message['tool_call_id'] = $row['tool_call_id'];
            }

            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Ritorna lo storico in un formato semplice per il frontend (solo messaggi visibili).
     *
     * @return array<int, array{role: string, content: string, created_at: string}>
     */
    public function getHistoryForClient(string $conversationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT role, content, created_at FROM messages
             WHERE conversation_id = ? AND role IN ('user', 'assistant') AND content IS NOT NULL
             ORDER BY id ASC"
        );
        $stmt->execute([$conversationId]);

        return array_map(
            static fn (array $row) => [
                'role' => $row['role'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
