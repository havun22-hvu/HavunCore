<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Output\BufferedOutput;

class HavunGeminiCommand extends Command
{
    protected $signature = 'havun:gemini
                            {prompt : De opdracht voor Gemini}
                            {--project= : Project om in te pakken via havun:pack (optioneel)}
                            {--model=gemini-2.5-flash : Gemini model}
                            {--out= : Schrijf output naar bestand}';

    protected $description = 'Stuur een prompt naar Gemini, optioneel met havun:pack context';

    public function handle(): int
    {
        $apiKey = config('services.gemini.api_key');
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

        // withoutVerifying() is required: Windows TLS cert revocation check fails for googleapis.com
        $response = Http::withoutVerifying()
            ->timeout(300)
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                ['contents' => [['parts' => [['text' => $fullPrompt]]]]]
            );

        if (! $response->successful()) {
            $this->error('Gemini API fout: ' . $response->body());
            return Command::FAILURE;
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        $out = $this->option('out');
        if ($out) {
            if (file_put_contents($out, $text) === false) {
                $this->error("Schrijven naar {$out} mislukt.");
                return Command::FAILURE;
            }
            $this->line("Geschreven naar: {$out}");
        } else {
            $this->line($text);
        }

        return Command::SUCCESS;
    }

    private function packProject(string $project): string
    {
        $this->line("Context inpakken voor project: {$project}...", null, 'v');

        $buffer = new BufferedOutput();
        $this->call('havun:pack', ['--project' => $project], $buffer);
        return $buffer->fetch();
    }
}
