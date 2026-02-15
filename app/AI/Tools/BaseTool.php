<?php

namespace App\AI\Tools;

use App\Enums\AI\ToolAccessLevel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Laravel\Ai\Tools\Request;

abstract class BaseTool
{
    abstract protected function requiredAccessLevel(): ToolAccessLevel;

    public function authorizeForUser(User $user): bool
    {
        if ($user->role === null) {
            return false;
        }

        return ToolAccessLevel::forRole($user->role)->value >= $this->requiredAccessLevel()->value;
    }

    public function formatCurrency(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2, '.', ',');
    }

    public function formatDate(Carbon $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function parsePeriod(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_7_days' => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    public function safeHandle(Request $request): string
    {
        try {
            $result = $this->handle($request);

            return (string) $result;
        } catch (ModelNotFoundException) {
            return (string) json_encode([
                'error' => 'No record found matching the given criteria.',
                'tool' => class_basename(static::class),
            ]);
        } catch (\Throwable $e) {
            return (string) json_encode([
                'error' => 'An error occurred while executing '.class_basename(static::class).': '.$e->getMessage(),
                'tool' => class_basename(static::class),
            ]);
        }
    }

    /**
     * Disambiguate search results when multiple matches are found.
     *
     * Returns null on exactly 1 match (proceed), or a message string
     * listing matches (>1) or indicating not found (0).
     */
    public function disambiguateResults(Collection $results, string $searchTerm, string $displayField): ?string
    {
        if ($results->count() === 1) {
            return null;
        }

        if ($results->isEmpty()) {
            return "No results found for '{$searchTerm}'.";
        }

        $list = $results->map(function ($item) use ($displayField) {
            $name = $item->{$displayField} ?? 'Unknown';
            $id = $item->id ?? '';
            $email = $item->email ?? '';

            $parts = [$name];
            if ($email !== '') {
                $parts[] = $email;
            }
            if ($id !== '') {
                $parts[] = "ID: {$id}";
            }

            return implode(' — ', $parts);
        })->implode("\n");

        return "Multiple matches found for '{$searchTerm}'. Please specify:\n{$list}";
    }

    /**
     * Scope query for the given user (v2 hook for data isolation).
     * Default implementation returns the query unchanged.
     */
    public function scopeQuery(Builder $query, User $user): Builder
    {
        return $query;
    }

    /**
     * Execute the tool. Must be implemented by subclasses that implement Tool.
     */
    abstract public function handle(Request $request): \Stringable|string;
}
