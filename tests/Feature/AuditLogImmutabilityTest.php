<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function entry(): AuditLog
    {
        return AuditLog::create([
            'action' => 'place.published',
            'entity_type' => 'place',
            'entity_id' => 1,
            'after_json' => ['status' => 'published'],
        ]);
    }

    public function test_entry_cannot_be_updated(): void
    {
        $entry = $this->entry();
        $this->expectException(LogicException::class);
        $entry->update(['action' => 'diubah']);
    }

    public function test_entry_cannot_be_deleted(): void
    {
        $entry = $this->entry();
        $this->expectException(LogicException::class);
        $entry->delete();
    }
}
