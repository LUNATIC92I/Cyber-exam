<?php
declare(strict_types=1);

namespace App\Core;

/** Append-only audit trail for security-relevant and business events. */
final class Audit
{
    public static function log(string $action, ?string $entityType = null, int|string|null $entityId = null, array $details = [], ?string $actorType = null, ?int $actorId = null): void
    {
        if ($actorType === null) {
            if (AdminAuth::id()) {
                [$actorType, $actorId] = ['admin', AdminAuth::id()];
            } elseif (Auth::id()) {
                [$actorType, $actorId] = ['user', Auth::id()];
            } else {
                $actorType = PHP_SAPI === 'cli' ? 'system' : 'guest';
            }
        }
        try {
            Database::query(
                'INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, details, ip_address, user_agent)
                 VALUES (:t, :aid, :a, :et, :eid, :d, :ip, :ua)',
                [
                    't' => $actorType, 'aid' => $actorId, 'a' => $action, 'et' => $entityType,
                    'eid' => $entityId === null ? null : (string) $entityId,
                    'd' => $details ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'ip' => PHP_SAPI === 'cli' ? null : Request::ip(),
                    'ua' => PHP_SAPI === 'cli' ? 'cli' : Request::userAgent(),
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('Audit write failed: ' . $e->getMessage());
        }
    }
}
