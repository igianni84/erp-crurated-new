<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Model Configuration
    |--------------------------------------------------------------------------
    |
    | The model used for the ERP Assistant agent. Temperature is kept low
    | for factual accuracy. Max steps limits tool calls per conversation turn.
    |
    */

    'provider' => env('AI_PROVIDER', 'anthropic'),

    'model' => env('AI_ASSISTANT_MODEL', 'claude-sonnet-4-5-20250929'),

    'max_steps' => (int) env('AI_ASSISTANT_MAX_STEPS', 10),

    'temperature' => (float) env('AI_ASSISTANT_TEMPERATURE', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Input Constraints
    |--------------------------------------------------------------------------
    */

    'max_input_length' => (int) env('AI_ASSISTANT_MAX_INPUT_LENGTH', 2000),

    'max_context_messages' => (int) env('AI_ASSISTANT_MAX_CONTEXT_MESSAGES', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Per-user rate limits. Checked via cache counters (primary) with
    | ai_audit_logs COUNT as fallback. super_admin is exempt.
    |
    */

    'rate_limit' => [
        'requests_per_hour' => (int) env('AI_RATE_LIMIT_PER_HOUR', 60),
        'requests_per_day' => (int) env('AI_RATE_LIMIT_PER_DAY', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Token prices in USD per 1K tokens (i.e. divide by 1000 before multiplying).
    | Formula: estimated_cost = (tokens_input / 1000 * input_token_price)
    |                         + (tokens_output / 1000 * output_token_price)
    |
    | Prices sourced from Anthropic's published pricing for Claude Sonnet 4.5
    | as of Feb 2026: $3.00 / $15.00 per 1M tokens.
    |
    */

    'cost_tracking' => [
        'input_token_price' => (float) env('AI_INPUT_TOKEN_PRICE', 0.003),
        'output_token_price' => (float) env('AI_OUTPUT_TOKEN_PRICE', 0.015),
    ],

];
