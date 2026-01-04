<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'doc_intelligence';

    public function up(): void
    {
        Schema::connection('doc_intelligence')->create('doc_issues', function (Blueprint $table) {
            $table->id();
            $table->string('project', 50)->nullable(); // null = cross-project issue
            $table->string('issue_type', 50);          // 'inconsistent', 'duplicate', 'outdated', 'missing', 'broken_link'
            $table->string('severity', 20)->default('medium'); // 'low', 'medium', 'high'
            $table->string('title', 255);
            $table->text('details');                   // JSON with issue details
            $table->json('affected_files');            // Array of affected file paths
            $table->text('suggested_action')->nullable();
            $table->string('status', 20)->default('open'); // 'open', 'in_progress', 'resolved', 'ignored'
            $table->string('resolved_by', 100)->nullable(); // 'user' or 'claude'
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('project');
            $table->index('issue_type');
            $table->index('severity');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('doc_intelligence')->dropIfExists('doc_issues');
    }
};
