<?php

namespace Tests\Feature;

use App\Models\MCPMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MCPMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    // -- Index --

    public function test_index_returns_messages_grouped_by_project(): void
    {
        MCPMessage::create(['project' => 'HavunCore', 'content' => 'Core message', 'tags' => []]);
        MCPMessage::create(['project' => 'HavunAdmin', 'content' => 'Admin message', 'tags' => []]);

        $response = $this->getJson('/api/mcp/messages');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'messages' => [
                    'HavunCore',
                    'HavunAdmin',
                    'Herdenkingsportaal',
                    'Studieplanner',
                ],
            ]);

        $this->assertCount(1, $response->json('messages.HavunCore'));
        $this->assertCount(1, $response->json('messages.HavunAdmin'));
        $this->assertCount(0, $response->json('messages.Herdenkingsportaal'));
    }

    // -- Store --

    public function test_store_creates_message(): void
    {
        $response = $this->postJson('/api/mcp/messages', [
            'project' => 'HavunCore',
            'content' => 'Test message content',
            'tags' => ['bug', 'urgent'],
            'external_id' => 'ext-123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Message stored');

        $this->assertDatabaseHas('mcp_messages', [
            'project' => 'HavunCore',
            'content' => 'Test message content',
            'external_id' => 'ext-123',
        ]);
    }

    public function test_store_deduplicates_by_external_id(): void
    {
        MCPMessage::create([
            'project' => 'HavunCore',
            'content' => 'Original message',
            'tags' => [],
            'external_id' => 'ext-duplicate',
        ]);

        $response = $this->postJson('/api/mcp/messages', [
            'project' => 'HavunCore',
            'content' => 'Duplicate message',
            'external_id' => 'ext-duplicate',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Message already exists');

        $this->assertDatabaseCount('mcp_messages', 1);
    }

    public function test_store_validation_rejects_missing_project(): void
    {
        $response = $this->postJson('/api/mcp/messages', [
            'content' => 'No project given',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_validation_rejects_missing_content(): void
    {
        $response = $this->postJson('/api/mcp/messages', [
            'project' => 'HavunCore',
        ]);

        $response->assertStatus(422);
    }

    // -- Show --

    public function test_show_filters_by_project(): void
    {
        MCPMessage::create(['project' => 'HavunCore', 'content' => 'Core msg', 'tags' => []]);
        MCPMessage::create(['project' => 'HavunAdmin', 'content' => 'Admin msg', 'tags' => []]);

        $response = $this->getJson('/api/mcp/messages/HavunCore');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('project', 'HavunCore')
            ->assertJsonCount(1, 'messages');

        $this->assertEquals('Core msg', $response->json('messages.0.content'));
    }

    // -- Destroy --

    public function test_destroy_by_id(): void
    {
        $message = MCPMessage::create([
            'project' => 'HavunCore',
            'content' => 'Delete me',
            'tags' => [],
        ]);

        $response = $this->deleteJson("/api/mcp/messages/{$message->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Message deleted');

        $this->assertDatabaseMissing('mcp_messages', ['id' => $message->id]);
    }

    public function test_destroy_by_external_id(): void
    {
        MCPMessage::create([
            'project' => 'HavunCore',
            'content' => 'Delete by ext id',
            'tags' => [],
            'external_id' => 'ext-delete-me',
        ]);

        $response = $this->deleteJson('/api/mcp/messages/ext-delete-me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('mcp_messages', ['external_id' => 'ext-delete-me']);
    }

    public function test_destroy_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/mcp/messages/99999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // -- Sync --

    public function test_sync_bulk_imports_messages(): void
    {
        $response = $this->postJson('/api/mcp/messages/sync', [
            'messages' => [
                ['project' => 'HavunCore', 'content' => 'Sync msg 1', 'id' => 'sync-1', 'tags' => ['info']],
                ['project' => 'HavunAdmin', 'content' => 'Sync msg 2', 'id' => 'sync-2'],
                ['project' => 'HavunCore', 'content' => 'Sync msg 3', 'id' => 'sync-3'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('imported', 3)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseCount('mcp_messages', 3);
    }

    public function test_sync_skips_existing_messages(): void
    {
        MCPMessage::create([
            'project' => 'HavunCore',
            'content' => 'Already here',
            'tags' => [],
            'external_id' => 'sync-existing',
        ]);

        $response = $this->postJson('/api/mcp/messages/sync', [
            'messages' => [
                ['project' => 'HavunCore', 'content' => 'Already here', 'id' => 'sync-existing'],
                ['project' => 'HavunCore', 'content' => 'New one', 'id' => 'sync-new'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 1);

        $this->assertDatabaseCount('mcp_messages', 2);
    }

    public function test_sync_validation_rejects_missing_messages(): void
    {
        $response = $this->postJson('/api/mcp/messages/sync', []);

        $response->assertStatus(422);
    }
}
