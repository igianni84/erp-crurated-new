<?php

namespace App\Jobs\Commercial;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\ExecutionStatus;
use App\Enums\Commercial\ExecutionType;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Models\Commercial\PricingPolicy;
use App\Models\Commercial\PricingPolicyExecution;
use App\Services\Commercial\PricingPolicyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Job to execute scheduled pricing policies.
 *
 * This job should be scheduled to run periodically (e.g., every hour)
 * to check for pricing policies that need to be executed based on their schedule.
 *
 * Scheduled policies can have the following frequencies:
 * - daily: Execute once per day at the configured time
 * - weekly: Execute once per week on the configured day and time
 * - monthly: Execute once per month on the configured day and time
 */
class ExecuteScheduledPricingPoliciesJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PricingPolicyService $service): void
    {
        $executedCount = 0;
        $errorCount = 0;

        // Get all active scheduled pricing policies
        $policies = PricingPolicy::query()
            ->where('status', PricingPolicyStatus::Active)
            ->where('execution_cadence', ExecutionCadence::Scheduled)
            ->get();

        Log::info("Checking {$policies->count()} scheduled pricing policies for execution");

        foreach ($policies as $policy) {
            if ($this->shouldExecute($policy)) {
                try {
                    Log::info("Executing scheduled pricing policy: {$policy->name} (ID: {$policy->id})");

                    $result = $service->execute($policy, isDryRun: false);

                    // Create execution log with scheduled type
                    PricingPolicyExecution::create([
                        'pricing_policy_id' => $policy->id,
                        'executed_at' => now(),
                        'execution_type' => ExecutionType::Scheduled,
                        'skus_processed' => $result->skusProcessed,
                        'prices_generated' => $result->pricesGenerated,
                        'errors_count' => $result->errorsCount,
                        'status' => $result->status,
                        'log_summary' => $this->generateLogSummary($policy, $result),
                    ]);

                    $executedCount++;

                    Log::info("Successfully executed pricing policy: {$policy->name}, generated {$result->pricesGenerated} prices");
                } catch (\Exception $e) {
                    $errorCount++;

                    // Log the error
                    PricingPolicyExecution::create([
                        'pricing_policy_id' => $policy->id,
                        'executed_at' => now(),
                        'execution_type' => ExecutionType::Scheduled,
                        'skus_processed' => 0,
                        'prices_generated' => 0,
                        'errors_count' => 1,
                        'status' => ExecutionStatus::Failed,
                        'log_summary' => "Scheduled execution failed: {$e->getMessage()}",
                    ]);

                    Log::error("Failed to execute scheduled pricing policy: {$policy->name}", [
                        'policy_id' => $policy->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($executedCount > 0 || $errorCount > 0) {
            Log::info("Scheduled pricing policies execution complete: {$executedCount} executed, {$errorCount} errors");
        }
    }

    /**
     * Check if a policy should be executed based on its schedule.
     */
    protected function shouldExecute(PricingPolicy $policy): bool
    {
        $logicDefinition = $policy->logic_definition;
        $schedule = $logicDefinition['schedule'] ?? null;

        if ($schedule === null) {
            Log::warning("Scheduled policy {$policy->name} has no schedule configuration");

            return false;
        }

        $frequency = $schedule['frequency'] ?? 'daily';
        $scheduledTime = $schedule['time'] ?? '00:00';
        $dayOfWeek = $schedule['day_of_week'] ?? null;
        $dayOfMonth = $schedule['day_of_month'] ?? null;

        $now = Carbon::now();
        $lastExecuted = $policy->last_executed_at ? Carbon::parse($policy->last_executed_at) : null;

        // Parse scheduled time
        [$scheduledHour, $scheduledMinute] = array_map('intval', explode(':', $scheduledTime));

        // Check if we're within the execution window (within 30 minutes of scheduled time)
        $scheduledToday = $now->copy()->setTime($scheduledHour, $scheduledMinute, 0);
        $isWithinWindow = $now->between(
            $scheduledToday->copy()->subMinutes(30),
            $scheduledToday->copy()->addMinutes(30)
        );

        if (! $isWithinWindow) {
            return false;
        }

        // Check if already executed in current period
        switch ($frequency) {
            case 'daily':
                // Execute once per day
                if ($lastExecuted !== null && $lastExecuted->isToday()) {
                    return false;
                }

                return true;

            case 'weekly':
                // Execute on specified day of week
                $targetDay = (int) ($dayOfWeek ?? 1); // Default to Monday (1)
                if ($now->dayOfWeek !== $targetDay) {
                    return false;
                }

                // Check if already executed this week
                if ($lastExecuted !== null && $lastExecuted->isSameWeek($now)) {
                    return false;
                }

                return true;

            case 'monthly':
                // Execute on specified day of month
                $targetDay = (int) ($dayOfMonth ?? 1); // Default to 1st
                if ($now->day !== $targetDay) {
                    return false;
                }

                // Check if already executed this month
                if ($lastExecuted !== null && $lastExecuted->isSameMonth($now)) {
                    return false;
                }

                return true;

            default:
                return false;
        }
    }

    /**
     * Generate a log summary for the execution.
     *
     * @param  \App\Services\Commercial\ExecutionResult  $result
     */
    protected function generateLogSummary(PricingPolicy $policy, $result): string
    {
        $schedule = $policy->logic_definition['schedule'] ?? [];
        $frequency = $schedule['frequency'] ?? 'daily';

        $summary = "Scheduled {$frequency} execution completed: ";
        $summary .= "{$result->pricesGenerated} prices generated from {$result->skusProcessed} SKUs.";

        if ($result->errorsCount > 0) {
            $summary .= " {$result->errorsCount} errors occurred.";
        }

        return $summary;
    }
}
