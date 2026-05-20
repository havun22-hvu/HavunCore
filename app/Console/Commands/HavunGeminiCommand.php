<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class HavunGeminiCommand extends Command
{
    protected $signature = 'havun:gemini
                            {prompt : De opdracht voor Gemini}
                            {--project= : Project om in te pakken via havun:pack (optioneel)}
                            {--model=gemini-3.1-flash-lite : Gemini model}
                            {--out= : Schrijf output naar bestand}';

    protected $description = 'Stuur een prompt naar Gemini, optioneel met havun:pack context';

    public function handle(): int
    {
        $apiKey = env('GEMINI_API_KEY');
        if (! $apiKey) {
            $this->error('GEMINI_API_KEY niet ingesteld.');
            return Command::FAILURE;
        }

        $prompt = $this->argument('prompt');
        $project = $this->option('project');
        $model = $this->option('model');

        $context = $project ? $this->packProject($project) : '';

        $fullPrompt = $context
            ? "PROJECTCONTEXT:\n{$context}\n\nOPDRACHT:\n{$prompt}"
            : $prompt;

        $this->line("Gemini ({$model}) wordt aangesproken...", null, 'v');

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                ['contents' => [['parts' => [['text' => $fullPrompt]]]]]
            );

        if (! $response->successful()) {
            $this->error('Gemini API fout: ' . $response->body());
            return Command::FAILURE;
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if ($out = $this->option('out')) {
            file_put_contents($out, $text);
            $this->line("Geschreven naar: {$out}");
        } else {
            $this->line($text);
        }

        return Command::SUCCESS;
    }

    private function packProject(string $project): string
    {
        $this->line("Context inpakken voor project: {$project}...", null, 'v');

        ob_start();
        $this->call('havun:pack', ['--project' => $project]);
        return ob_get_clean();
    }
}
