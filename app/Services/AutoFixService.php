<?php

namespace App\Services;

use App\Models\AutofixProposal;
use Illuminate\Support\Facades\Log;

/**
 * Central AutoFix Service
 *
 * Analyzes errors from any project and generates fix proposals.
 * The actual fix application happens in the project itself (local executor).
 */
class AutoFixService
{
    protected AIProxyService $aiProxy;

    public function __construct(AIProxyService $aiProxy)
    {
        $this->aiProxy = $aiProxy;
    }

    /**
     * Analyze an error and generate a fix proposal.
     */
    public function analyze(array $errorData): ?AutofixProposal
    {
        $project = $errorData['project'];
        $exceptionClass = $errorData['exception_class'];
        $message = $errorData['message'];
        $file = $errorData['file'] ?? null;
        $line = $errorData['line'] ?? null;
        $trace = $errorData['trace'] ?? '';
        $context = $errorData['context'] ?? [];

        // Rate limit check
        if (AutofixProposal::isRateLimited($project, $exceptionClass, $file, $line)) {
            return null;
        }

        // Build prompt for AI analysis
        $prompt = $this->buildPrompt($project, $exceptionClass, $message, $file, $line, $trace, $context);

        try {
            $result = $this->aiProxy->chat(
                tenant: 'havuncore',
                message: $prompt,
                systemPrompt: $this->getSystemPrompt(),
                maxTokens: 2048
            );

            $fixProposal = $result['response'] ?? '';
            $riskLevel = $this->assessRisk($fixProposal);
            $deliveryMode = $this->resolveDeliveryMode($riskLevel);
            $initialStatus = $deliveryMode === 'dry_run' ? 'dry_run' : 'branch_pending';

            $proposal = AutofixProposal::create([
                'project' => $project,
                'exception_class' => $exceptionClass,
                'message' => mb_substr($message, 0, 65535),
                'file' => $file,
                'line' => $line,
                'fix_proposal' => $fixProposal,
                'status' => $initialStatus,
                'risk_level' => $riskLevel,
                'source' => 'central',
                'context' => array_merge($context, [
                    'delivery_mode' => $deliveryMode,
                    'branch_name' => $deliveryMode === 'branch_pr'
                        ? config('autofix.branch_prefix') . $project . '-' . now()->format('Y-m-d-Hi')
                        : null,
                    'snapshot_required' => (bool) config('autofix.snapshot_enabled'),
                ]),
            ]);

            Log::info("AutoFix: proposal created for {$project}", [
                'proposal_id' => $proposal->id,
                'risk' => $riskLevel,
            ]);

            return $proposal;

        } catch (\Throwable $e) {
            Log::error("AutoFix: analysis failed for {$project}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Report the result of applying a fix.
     */
    public function reportResult(int $proposalId, string $status, ?string $resultMessage = null): void
    {
        $proposal = AutofixProposal::find($proposalId);
        if (! $proposal) {
            return;
        }

        $proposal->update([
            'status' => $status,
            'result_message' => $resultMessage,
        ]);

        $this->sendNotification($proposal);
    }

    /**
     * Record a local fallback fix (when HavunCore was unreachable).
     */
    public function recordFallback(array $data): AutofixProposal
    {
        return AutofixProposal::create([
            'project' => $data['project'],
            'exception_class' => $data['exception_class'],
            'message' => mb_substr($data['message'] ?? '', 0, 65535),
            'file' => $data['file'] ?? null,
            'line' => $data['line'] ?? null,
            'fix_proposal' => $data['fix_proposal'] ?? null,
            'status' => $data['status'] ?? 'applied',
            'risk_level' => $data['risk_level'] ?? 'unknown',
            'result_message' => $data['result_message'] ?? 'Applied via local fallback',
            'source' => 'local_fallback',
            'context' => $data['context'] ?? null,
        ]);
    }

    protected function buildPrompt(string $project, string $class, string $message, ?string $file, ?int $line, string $trace, array $context): string
    {
        $prompt = "Analyseer deze productie-error en geef een fix.\n\n";
        $prompt .= "Project: {$project}\n";
        $prompt .= "Exception: {$class}\n";
        $prompt .= "Message: {$message}\n";

        if ($file) {
            $prompt .= "File: {$file}\n";
        }
        if ($line) {
            $prompt .= "Line: {$line}\n";
        }
        if ($trace) {
            $prompt .= "\nStack trace (eerste 2000 tekens):\n" . mb_substr($trace, 0, 2000) . "\n";
        }
        if (! empty($context)) {
            $prompt .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }

        $prompt .= "\nGeef je antwoord in dit format:\n";
        $prompt .= "RISK: low|medium|high\n";
        $prompt .= "FILE: pad/naar/bestand.php\n";
        $prompt .= "FIX:\n```php\n// de fix code\n```\n";
        $prompt .= "UITLEG: korte uitleg van de oorzaak en fix\n";

        return $prompt;
    }

    protected function getSystemPrompt(): string
    {
        return 'Je bent een ervaren Laravel developer die productie-errors analyseert en fixes voorstelt. '
            . 'Geef alleen fixes voor duidelijke bugs, geen refactoring. '
            . 'Wees conservatief: liever geen fix dan een riskante fix. '
            . 'Markeer risk level: low (typo, null check), medium (logica wijziging), high (database/auth/betaling).';
    }

    /**
     * Resolve delivery mode based on risk + branch_model config.
     *
     * - branch_model OFF → legacy 'direct' (executor mag direct committen)
     * - risk in dry_run_on_risk → 'dry_run' (alleen notificatie)
     * - anders → 'branch_pr' (executor maakt branch + PR)
     */
    protected function resolveDeliveryMode(string $riskLevel): string
    {
        if (! config('autofix.branch_model', true)) {
            return 'direct';
        }

        $dryRunRisks = (array) config('autofix.dry_run_on_risk', ['medium', 'high']);

        if (in_array($riskLevel, $dryRunRisks, true)) {
            return 'dry_run';
        }

        return 'branch_pr';
    }

    protected function assessRisk(string $proposal): string
    {
        if (preg_match('/RISK:\s*(low|medium|high)/i', $proposal, $matches)) {
            return strtolower($matches[1]);
        }

        // Heuristic fallback
        $highRiskPatterns = ['migration', 'database', 'payment', 'auth', 'password', 'token', 'DELETE', 'DROP'];
        foreach ($highRiskPatterns as $pattern) {
            if (stripos($proposal, $pattern) !== false) {
                return 'high';
            }
        }

        return 'medium';
    }

    protected function sendNotification(AutofixProposal $proposal): void
    {
        // Notifications are visible in HavunAdmin dashboard (observability/autofix)
        Log::info("AutoFix [{$proposal->status}]: {$proposal->project} — {$proposal->exception_class}", [
            'proposal_id' => $proposal->id,
            'risk' => $proposal->risk_level,
            'source' => $proposal->source,
        ]);
    }
}
