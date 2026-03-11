<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'doc_intelligence';

    public function up(): void
    {
        Schema::connection('doc_intelligence')->table('doc_embeddings', function (Blueprint $table) {
            $table->string('embedding_model', 100)->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::connection('doc_intelligence')->table('doc_embeddings', function (Blueprint $table) {
            $table->dropColumn('embedding_model');
        });
    }
};
