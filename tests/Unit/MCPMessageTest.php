<?php

namespace Tests\Unit;

use App\Models\MCPMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MCPMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_for_project(): void
    {
        MCPMessage::create(['project' => 'havunadmin', 'content' => 'Admin msg']);
        MCPMessage::create(['project' => 'judotoernooi', 'content' => 'JT msg']);

        $results = MCPMessage::forProject('havunadmin')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Admin msg', $results->first()->content);
    }

    public function test_scope_recent(): void
    {
        $recent = MCPMessage::create(['project' => 'test', 'content' => 'Recent']);
        // Manually set old date
        MCPMessage::where('id', $recent->id)->update(['created_at' => now()->subDays(60)]);

        MCPMessage::create(['project' => 'test', 'content' => 'New']);

        $results = MCPMessage::recent(30)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('New', $results->first()->content);
    }

    public function test_scope_with_tag(): void
    {
        MCPMessage::create(['project' => 'test', 'content' => 'Tagged', 'tags' => ['deploy', 'urgent']]);
        MCPMessage::create(['project' => 'test', 'content' => 'Other', 'tags' => ['info']]);

        $results = MCPMessage::withTag('deploy')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Tagged', $results->first()->content);
    }

    public function test_tags_cast_as_array(): void
    {
        $msg = MCPMessage::create([
            'project' => 'test',
            'content' => 'Test',
            'tags' => ['a', 'b', 'c'],
        ]);

        $this->assertIsArray($msg->fresh()->tags);
        $this->assertCount(3, $msg->fresh()->tags);
    }
}
