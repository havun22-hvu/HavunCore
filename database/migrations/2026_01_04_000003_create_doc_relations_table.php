<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'doc_intelligence';

    public function up(): void
    {
        Schema::connection('doc_intelligence')->create('doc_relations', function (Blueprint $table) {
            $table->id();
            $table->string('source_project', 50);
            $table->string('source_file', 500);
            $table->string('target_project', 50);
            $table->string('target_file', 500);
            $table->string('relation_type', 50);      // 'references', 'duplicates', 'contradicts', 'extends'
            $table->decimal('confidence', 3, 2)->default(1.00); // 0.00 - 1.00
            $table->boolean('auto_detected')->default(true);
            $table->text('details')->nullable();       // Additional context
            $table->timestamps();

            $table->index(['source_project', 'source_file']);
            $table->index(['target_project', 'target_file']);
            $table->index('relation_type');
        });
    }

    public function down(): void
    {
        Schema::connection('doc_intelligence')->dropIfExists('doc_relations');
    }
};
