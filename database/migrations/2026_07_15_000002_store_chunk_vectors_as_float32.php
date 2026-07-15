<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store chunk vectors as raw float32 instead of JSON text.
 *
 * A vector is 768 numbers the model already computed in float32. JSON wrote
 * each one out in full ("0.7872941493988037"), so one vector cost 15,222 bytes
 * against 3,072 packed — and every search had to parse ~200MB of that text back
 * into floats, which was nearly all of the 8 seconds an unfiltered search took.
 * Round-tripping through float32 was verified lossless across 300 vectors: max
 * deviation exactly 0.
 *
 * Converted in place — the numbers are already in the database, so this needs
 * no Ollama and no re-index. Rows carrying the word-frequency fallback (a
 * word => weight map, which cannot be packed) are deleted instead; DocIndexer
 * re-chunks them on the next run. See docs/kb/plans/kb-chunking-plan.md.
 */
return new class extends Migration
{
    protected $connection = 'doc_intelligence';

    public function up(): void
    {
        $db = DB::connection('doc_intelligence');

        $db->statement('ALTER TABLE doc_chunks RENAME COLUMN embedding TO embedding_json');
        $db->statement('ALTER TABLE doc_chunks ADD COLUMN embedding BLOB');

        $db->table('doc_chunks')->select(['id', 'embedding_json'])->orderBy('id')
            ->chunkById(500, function ($rijen) use ($db) {
                foreach ($rijen as $rij) {
                    $vector = json_decode($rij->embedding_json ?? '[]', true);

                    if (!is_array($vector) || $vector === [] || !array_is_list($vector)) {
                        $db->table('doc_chunks')->where('id', $rij->id)->delete();
                        continue;
                    }

                    $db->table('doc_chunks')->where('id', $rij->id)->update([
                        'embedding' => pack('f*', ...$vector),
                    ]);
                }
            });

        $db->statement('ALTER TABLE doc_chunks DROP COLUMN embedding_json');
    }

    public function down(): void
    {
        $db = DB::connection('doc_intelligence');

        $db->statement('ALTER TABLE doc_chunks RENAME COLUMN embedding TO embedding_blob');
        $db->statement('ALTER TABLE doc_chunks ADD COLUMN embedding TEXT');

        $db->table('doc_chunks')->select(['id', 'embedding_blob'])->orderBy('id')
            ->chunkById(500, function ($rijen) use ($db) {
                foreach ($rijen as $rij) {
                    if ($rij->embedding_blob === null) {
                        continue;
                    }

                    $db->table('doc_chunks')->where('id', $rij->id)->update([
                        'embedding' => json_encode(array_values(unpack('f*', $rij->embedding_blob))),
                    ]);
                }
            });

        $db->statement('ALTER TABLE doc_chunks DROP COLUMN embedding_blob');
    }
};
