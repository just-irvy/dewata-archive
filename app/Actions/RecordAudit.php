<?php

namespace App\Actions;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mencatat operasi admin yang penting. Hanya nilai atribut yang berubah yang
 * disimpan. IP disimpan sebagai hash berkunci (HMAC), bukan alamat mentah.
 */
final class RecordAudit
{
    public function __construct(private readonly Request $request) {}

    public function __invoke(string $action, Model $entity, ?array $before = null, ?array $after = null): AuditLog
    {
        $ip = $this->request->ip();

        return AuditLog::create([
            'actor_user_id' => $this->request->user()?->getKey(),
            'action' => $action,
            'entity_type' => Str::snake(class_basename($entity)),
            'entity_id' => $entity->getKey(),
            'before_json' => $before ?: null,
            'after_json' => $after ?: null,
            'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
        ]);
    }
}
