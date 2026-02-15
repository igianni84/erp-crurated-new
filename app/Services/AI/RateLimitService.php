<?php

namespace App\Services\AI;

use App\Enums\UserRole;
use App\Models\AI\AiAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RateLimitService
{
    /**
     * Check if the user has exceeded rate limits.
     *
     * @return array{allowed: bool, message: string|null}
     */
    public function check(User $user): array
    {
        // super_admin exempt from rate limiting
        if ($user->role === UserRole::SuperAdmin) {
            return ['allowed' => true, 'message' => null];
        }

        $hourlyLimit = (int) config('ai-assistant.rate_limit.requests_per_hour', 60);
        $dailyLimit = (int) config('ai-assistant.rate_limit.requests_per_day', 500);

        $hourlyCount = $this->getHourlyCount($user);
        $dailyCount = $this->getDailyCount($user);

        if ($hourlyCount >= $hourlyLimit) {
            $remaining = 0;

            return [
                'allowed' => false,
                'message' => "Rate limit exceeded. You have used all {$hourlyLimit} queries this hour. Try again in the next hour.",
            ];
        }

        if ($dailyCount >= $dailyLimit) {
            return [
                'allowed' => false,
                'message' => "Rate limit exceeded. You have used all {$dailyLimit} queries today. Try again tomorrow.",
            ];
        }

        return ['allowed' => true, 'message' => null];
    }

    /**
     * Increment the rate limit counters after a successful request.
     */
    public function increment(User $user): void
    {
        $hourKey = $this->hourKey($user);
        $dayKey = $this->dayKey($user);

        try {
            Cache::increment($hourKey);
            if (Cache::get($hourKey) === 1) {
                Cache::put($hourKey, 1, 3600);
            }

            Cache::increment($dayKey);
            if (Cache::get($dayKey) === 1) {
                Cache::put($dayKey, 1, 86400);
            }
        } catch (\Throwable) {
            // Cache unavailable — counters not incremented.
            // Fallback check() uses audit log COUNT.
        }
    }

    protected function getHourlyCount(User $user): int
    {
        try {
            $count = Cache::get($this->hourKey($user));

            if ($count !== null) {
                return (int) $count;
            }
        } catch (\Throwable) {
            // Cache unavailable — fall through to DB
        }

        return $this->countFromAuditLog($user, now()->subHour());
    }

    protected function getDailyCount(User $user): int
    {
        try {
            $count = Cache::get($this->dayKey($user));

            if ($count !== null) {
                return (int) $count;
            }
        } catch (\Throwable) {
            // Cache unavailable — fall through to DB
        }

        return $this->countFromAuditLog($user, now()->startOfDay());
    }

    protected function countFromAuditLog(User $user, \DateTimeInterface $since): int
    {
        return AiAuditLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->count();
    }

    protected function hourKey(User $user): string
    {
        return 'ai_rate:'.$user->id.':hour:'.now()->format('Y-m-d-H');
    }

    protected function dayKey(User $user): string
    {
        return 'ai_rate:'.$user->id.':day:'.now()->format('Y-m-d');
    }
}
