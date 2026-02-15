# PRD: AI Assistant — Natural Language ERP Intelligence

## Introduction

The AI Assistant is an integrated conversational interface that enables Crurated ERP operators to query operational data using natural language. Instead of navigating through dashboards, filters, and reports, users can ask questions like "Who is our top customer this month?", "How many Fourrier bottles have we sold?", or "Are there pending shipments?" and receive immediate, accurate answers derived from live ERP data.

The AI Assistant operates on a **Tool-Calling Architecture**: the user's natural language question is sent to an LLM (Claude Sonnet 4.5 via the Anthropic API), which interprets the intent and calls one or more predefined Tools. Each Tool is a PHP class that executes a specific Eloquent query against the ERP database and returns structured data. The LLM then formats the results into a human-readable response. This architecture ensures:

- **Security**: The AI never accesses the database directly. Every query is mediated by a controlled Tool with explicit parameters and scoped queries.
- **Accuracy**: Tools use the same Eloquent models, relationships, and scopes as the rest of the ERP. No SQL generation, no hallucinated data.
- **Auditability**: Every interaction is logged with user identity, tool calls, token usage, and duration.
- **Extensibility**: Adding a new capability means adding a new Tool class — no model retraining or prompt engineering required.

The AI Assistant is accessible through two entry points:
1. **Dedicated page** in the Filament sidebar under the "System" navigation group — full chat interface with conversation history
2. **Quick-access icon** in the Filament top bar — opens a slide-over panel for rapid queries from any page

**What the AI Assistant governs:**
- Read-only querying of all ERP modules (PIM, Customers, Commercial, Allocations, Procurement, Inventory, Fulfillment, Finance)
- Cross-module aggregations (e.g., "customer X's total spend including all invoice types")
- Operational status checks (e.g., "pending shipments", "overdue invoices", "stock levels")
- Data quality monitoring (e.g., "products missing SKU codes")

**What the AI Assistant does NOT govern:**
- Any write operations (no creating, updating, or deleting records)
- Real-time alerting (covered by Admin Panel PRD's Alert Infrastructure)
- Report generation or PDF export
- Direct Xero/Stripe/WMS integration queries (only ERP-side data)

**Non-negotiable invariants:**
1. AI Assistant is strictly read-only — no mutations to any table, ever
2. Every tool call is authorized against the user's role before execution
3. All interactions are audit-logged with immutable records
4. Tool results come from Eloquent queries using existing Models — no raw SQL
5. Financial amounts always use `bcmath` functions for precision
6. The AI never fabricates data — if a tool returns no results, it says so

---

## Goals

- Provide instant natural language access to operational data across all 8 ERP modules
- Reduce time-to-insight for common queries from minutes (navigating UI) to seconds (asking a question)
- Implement 25 Tools covering the most common operational queries identified by stakeholders
- Ensure role-based access control so each user only accesses data appropriate to their permission level
- Maintain full audit trail of all AI interactions for compliance and cost tracking
- Keep per-query cost under EUR 0.01 average using Claude Sonnet 4.5
- Support streaming responses for a fluid conversational experience
- Achieve zero data accuracy discrepancies between AI responses and dashboard values

---

## User Stories

### Section 1: Infrastructure & Configuration

#### US-AI001: Laravel AI SDK Installation
**Description:** As a Developer, I want the Laravel AI SDK installed and configured so that the application can communicate with the Anthropic API.

**Acceptance Criteria:**
- [ ] **PHP version**: `laravel/ai` v0.1.5 requires PHP ^8.4. Update `composer.json` PHP requirement from `^8.2` to `^8.4` before installing (production server runs PHP 8.5 — OK; dev environment confirmed PHP 8.4+). Also update CLAUDE.md "Tech Stack" line from "PHP 8.2+" to "PHP 8.4+"
- [ ] Package `laravel/ai` installed via Composer with **exact version pin**: `composer require "laravel/ai:0.1.5"` — do NOT use `^0.1` as the SDK is pre-1.0 and API may change between minor releases
- [ ] Configuration published: `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"`
- [ ] SDK migrations run: `php artisan migrate` — verify exact table names after migration (SDK docs have minor inconsistency between `agent_conversations` and `conversations`; adapt migration 500002 accordingly)
- [ ] Environment variable `ANTHROPIC_API_KEY` added to `.env.example` with placeholder
- [ ] `config/ai.php` configured with Anthropic as default provider, model `claude-sonnet-4-5-20250929`
- [ ] **Smoke test (blocking):** Create `tests/Feature/AI/SdkSmokeTest.php` that verifies all SDK contracts and attributes exist at their expected namespaces: `Laravel\Ai\Contracts\{Agent, Conversational, HasTools, Tool}`, `Laravel\Ai\Attributes\{MaxSteps, Model, Provider, Temperature}`, `Laravel\Ai\Enums\Lab`, `Laravel\Ai\Concerns\RemembersConversations`, `Laravel\Ai\Promptable`, `Laravel\Ai\Tools\Request`, `Illuminate\Contracts\JsonSchema\JsonSchema`. If ANY namespace differs from the PRD, update ALL subsequent user stories before proceeding
- [ ] **Schema builder API smoke test (blocking):** The smoke test MUST also verify that the `JsonSchema` type builders support `.enum()` and `.default()` methods. Create a minimal test tool that calls `$schema->string()->enum(['a', 'b'])->default('a')` and `$schema->integer()->min(1)->max(50)`. If `.enum()` or `.default()` do not exist, ALL 25 tool schemas must be redesigned before proceeding. This is critical because the official docs only document `.required()`, `.min()`, `.max()` — the `.enum()` and `.default()` methods are assumed but not explicitly confirmed
- [ ] **Tool contract return types:** Verify that `Tool::description()` returns `Stringable|string` and `Tool::handle(Request $request)` returns `Stringable|string` (NOT just `string`). The code examples in this PRD use `string` for simplicity, but implementations must match the SDK interface exactly. PHPStan level 5 will catch mismatches
- [ ] **Token usage structure:** Verify the exact structure of `AgentResponse->usage` and `StreamedAgentResponse->usage` — confirm field names for input/output token counts (expected: `input_tokens`, `output_tokens` but may differ). Record actual field names for use in US-AI006 and US-AI122
- [ ] Verify SDK works: simple test prompt returns a response (requires `ANTHROPIC_API_KEY` in `.env`)
- [ ] Verify SDK migration table names: run `php artisan migrate` and confirm the exact table names created (docs show minor inconsistency between `agent_conversations` and `conversations`). Record actual table names in this US for reference by US-AI006, US-AI110, US-AI111, US-AI112
- [ ] Typecheck and lint pass

---

#### US-AI002: AI Configuration File
**Description:** As a Developer, I want a dedicated configuration section for the AI Assistant to centralize all tunable parameters.

**Acceptance Criteria:**
- [ ] Configuration in `config/ai.php` (extends SDK config) or separate `config/ai-assistant.php` with:
  - `model`: default `claude-sonnet-4-5-20250929`
  - `max_steps`: default 10 (max tool calls per conversation turn)
  - `temperature`: default 0.3 (low for factual accuracy)
  - `max_input_length`: default 2000 characters
  - `max_context_messages`: default 30 (conversation history window — see US-AI113 rationale for why 30 instead of 50)
  - `rate_limit.requests_per_hour`: default 60
  - `rate_limit.requests_per_day`: default 500
  - `cost_tracking.input_token_price`: default 0.003 (USD per 1K tokens — i.e. $3.00/1M input tokens, Sonnet 4.5 pricing as of Feb 2026 — sourced from `env('AI_INPUT_TOKEN_PRICE', 0.003)`). **VERIFY at implementation time**: check current Anthropic pricing at https://docs.anthropic.com/en/docs/about-claude/models and update default if changed
  - `cost_tracking.output_token_price`: default 0.015 (USD per 1K tokens — i.e. $15.00/1M output tokens, Sonnet 4.5 pricing as of Feb 2026 — sourced from `env('AI_OUTPUT_TOKEN_PRICE', 0.015)`). **VERIFY at implementation time**: same as above
  - Config comments must document the unit as "USD per 1K tokens" and the formula: `estimated_cost = (tokens_input / 1000 * input_price) + (tokens_output / 1000 * output_price)`
- [ ] All values sourced from `env()` with sensible defaults
- [ ] Typecheck and lint pass

---

#### US-AI003: ERP Assistant Agent Class
**Description:** As a Developer, I want the main Agent class that orchestrates the AI Assistant, loading the system prompt and registering all available tools.

**Acceptance Criteria:**
- [ ] Class `App\AI\Agents\ErpAssistantAgent` implementing `Agent`, `Conversational`, `HasTools`
- [ ] Uses `Promptable` and `RemembersConversations` traits from the SDK
- [ ] Attributes: `#[Provider(Lab::Anthropic)]`, `#[Model('claude-sonnet-4-5-20250929')]`, `#[MaxSteps(10)]`, `#[Temperature(0.3)]`
- [ ] `instructions()` method loads system prompt from `app/AI/Prompts/erp-system-prompt.md`
- [ ] `tools()` method returns all registered Tool instances, filtered by the authenticated user's role
- [ ] Agent is instantiated with the authenticated `User` for role-based filtering
- [ ] Typecheck and lint pass

---

#### US-AI004: System Prompt with Domain Knowledge
**Description:** As a Developer, I want a comprehensive system prompt that gives the AI model context about the Crurated ERP domain so it can interpret user queries correctly.

**Acceptance Criteria:**
- [ ] File `app/AI/Prompts/erp-system-prompt.md` containing:
  - Role definition: "You are the Crurated ERP Assistant, a read-only analytics tool for a fine wine trading platform"
  - Module overview: brief description of each module (PIM, Customers, Commercial, Allocations, Procurement, Inventory, Fulfillment, Finance)
  - Key domain concepts: voucher (1 voucher = 1 bottle), allocation (supply pool), shipping order vs shipment, invoice types — explicitly list all 5: INV0 MembershipService (subscription fees), INV1 VoucherSale (wine allocation sales), INV2 ShippingRedemption (shipping charges), INV3 StorageFee (storage billing), INV4 ServiceEvents (event services)
  - Response guidelines: use tables for tabular data, format currencies with EUR symbol, use bottle counts not case counts unless asked
  - Tool usage hints: which tool to call for which type of question
  - Language: respond in the same language as the user's question (Italian or English)
- [ ] Prompt under 4000 tokens to leave room for conversation context
- [ ] No hallucination-prone statements (no "I can do X" unless a tool exists for X)
- [ ] Typecheck and lint pass

---

#### US-AI005: Rate Limiting
**Description:** As an Admin, I want rate limiting on AI queries to control costs and prevent abuse.

**Acceptance Criteria:**
- [ ] Rate limiting checked before each agent prompt execution
- [ ] Limits read from `config/ai-assistant.php`: `requests_per_hour` (default 60), `requests_per_day` (default 500)
- [ ] **Cache-first rate limiting (primary):** Use `Cache::increment("ai_rate:{userId}:hour:{hourKey}")` with TTL 3600s for hourly, and `Cache::increment("ai_rate:{userId}:day:{dayKey}")` with TTL 86400s for daily. This avoids a COUNT query on `ai_audit_logs` on every request. The `ai_audit_logs` table remains the source of truth for reporting but is NOT queried for rate limiting in the hot path
- [ ] **Fallback:** If cache is unavailable (Redis down), fall back to COUNT on `ai_audit_logs` with index `(user_id, created_at)`. This should be rare and is acceptable as a degraded mode
- [ ] If limit exceeded: HTTP 429 response with message "Rate limit exceeded. You can make {remaining} more queries in {time_window}."
- [ ] Super_admin role exempt from rate limiting
- [ ] Typecheck and lint pass

---

#### US-AI006: AI Audit Log Model
**Description:** As a Developer, I want an immutable audit log model for tracking all AI interactions for compliance and cost analysis.

**Acceptance Criteria:**
- [ ] Migration `2026_02_XX_500001_create_ai_audit_logs_table.php` with columns:
  - `id` (UUID, PK), `user_id` (FK to users), `conversation_id` (nullable — type must match the PK type of the SDK's `agent_conversations` table, verified during US-AI001; use `string` if UUID/ULID, `unsignedBigInteger` if auto-increment), `message_text` (text — user's message only, not AI response), `tools_invoked` (JSON — array of tool names called), `tokens_input` (unsigned int, nullable), `tokens_output` (unsigned int, nullable), `estimated_cost_eur` (decimal 8,6, nullable), `duration_ms` (unsigned int, nullable), `error` (text, nullable), `metadata` (JSON, nullable), `created_at` (timestamp)
  - NO `updated_at`, NO `deleted_at` — immutable records
  - Index on `(user_id, created_at)` for rate limiting queries
- [ ] Model `App\Models\AI\AiAuditLog` with boot guard preventing update and delete (pattern from `AuditLog`)
- [ ] Model uses `HasUuid` trait
- [ ] `$timestamps = false` with manual `created_at` setting
- [ ] Typecheck and lint pass

---

### Section 2: Tool Definitions

#### US-AI010: Base Tool Abstract Class
**Description:** As a Developer, I want an abstract base class for all AI Tools that handles authorization, error handling, and response formatting.

**Acceptance Criteria:**
- [ ] Abstract class `App\AI\Tools\BaseTool` with:
  - Abstract method `requiredAccessLevel(): ToolAccessLevel`
  - Method `authorizeForUser(User $user): bool` that checks `ToolAccessLevel::forRole($user->role)` >= `$this->requiredAccessLevel()`
  - Method `formatCurrency(string $amount, string $currency = 'EUR'): string` using bcmath
  - Method `formatDate(Carbon $date): string` for consistent date formatting
  - Method `parsePeriod(string $period): array` returning `[Carbon $from, Carbon $to]` for period strings like 'this_month', 'last_week', 'this_quarter', 'this_year', 'today', 'last_7_days', 'last_30_days'
  - **Error handling wrapper:** Method `safeHandle(Request $request): string` that wraps the subclass `handle()` in a try/catch. On exception: log the error, return a structured JSON response `{"error": "An error occurred while executing {ToolName}: {message}", "tool": "{ToolName}"}`. The LLM will use this to inform the user gracefully instead of crashing. Specific catch for `ModelNotFoundException` → "No record found matching the given criteria"
  - **Name disambiguation helper:** Method `disambiguateResults(Collection $results, string $searchTerm, string $displayField): string` — when a name-based search returns multiple matches (>1), return a structured message listing all matches so the LLM can ask the user to clarify. When 0 matches, return "No results found for '{searchTerm}'". When exactly 1 match, return null (proceed with single result). Used by US-AI014, US-AI032, and any tool with fuzzy name search
- [ ] Enum `App\Enums\AI\ToolAccessLevel` with cases: Overview (10), Basic (20), Standard (40), Full (60)
  - With `label()`, `color()`, `icon()` methods (standard enum pattern)
  - Static method `forRole(UserRole $role): self` mapping: Viewer→Overview, Editor→Basic, Manager→Standard, Admin/SuperAdmin→Full
  - **Note:** ToolAccessLevel numeric values (10/20/40/60) are intentionally different from `UserRole::level()` values (20/40/60/80/100). The two systems are decoupled — `forRole()` is the only bridge between them. Do NOT compare numeric values across the two enums
- [ ] Typecheck and lint pass

---

#### US-AI011: Tool — Top Customers by Revenue
**Description:** As a Manager, I want to see the top customers ranked by revenue for a given period to identify key accounts.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Customer\TopCustomersByRevenueTool` implements `Tool`
- [ ] Schema parameters: `period` (string, default 'this_month', enum of period values), `limit` (integer, default 10, min 1, max 50)
- [ ] Handle: query on `Invoice` model WHERE `status` in [issued, partially_paid, paid] within period, SUM `total_amount` GROUP BY `customer_id`, with `customer` relationship, ORDER BY total DESC, LIMIT
- [ ] Returns: array of `[customer_name (via $customer->getName() — prefers Party.legal_name over legacy name field), email, total_revenue (formatted EUR), invoice_count, membership_tier]`
- [ ] Uses `bcadd()` for summing amounts
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI012: Tool — Customer Search
**Description:** As an Operator, I want to search for a customer by name or email to quickly access their information.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Customer\CustomerSearchTool` implements `Tool`
- [ ] Schema parameters: `query` (string, required, min length 2)
- [ ] Handle: search on `Customer` model with `party` relationship. Search across BOTH `Customer.name` (legacy) AND `Party.legal_name` (authoritative) using `->where(fn ($q) => $q->where('name', 'LIKE', ...)->orWhere('email', 'LIKE', ...)->orWhereHas('party', fn ($p) => $p->where('legal_name', 'LIKE', ...)))`. Also eager load `activeMembership`, withCount of `vouchers`, `shippingOrders`
- [ ] Returns max 10 results: `[customer_name (via $customer->getName() which prefers Party.legal_name), email, status (label), customer_type (label), membership_tier, voucher_count, shipping_order_count]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI013: Tool — Customer Status Summary
**Description:** As a Manager, I want a summary of customers by status and type to understand the customer base composition.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Customer\CustomerStatusSummaryTool` implements `Tool`
- [ ] No required parameters
- [ ] Handle: query on `Customer` model with COUNT GROUP BY `status` (from `CustomerStatus` enum) and GROUP BY `customer_type` (from `CustomerType` enum)
- [ ] Returns: `[total_customers, by_status: {active: N, suspended: N, ...}, by_type: {B2C: N, B2B: N, Partner: N}, with_active_blocks: N]`
- [ ] Requires `ToolAccessLevel::Overview`
- [ ] Typecheck and lint pass

---

#### US-AI014: Tool — Customer Voucher Count
**Description:** As an Operator, I want to know how many vouchers a specific customer has, grouped by lifecycle state.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Customer\CustomerVoucherCountTool` implements `Tool`
- [ ] Schema parameters: `customer_id` (string UUID, optional), `customer_name` (string, optional — fuzzy search if customer_id not provided)
- [ ] Handle: if `customer_id` provided, direct lookup. If `customer_name`, search by LIKE on BOTH `Customer.name` (legacy) AND `Party.legal_name` (authoritative) via `->where('name', 'LIKE', ...)->orWhereHas('party', fn ($p) => $p->where('legal_name', 'LIKE', ...))`. Then COUNT vouchers GROUP BY `lifecycle_state` (from `VoucherLifecycleState` enum)
- [ ] **Name disambiguation:** If `customer_name` search returns >1 match, use `BaseTool::disambiguateResults()` to return a list of matching customers (name, email, id) so the LLM can ask the user to clarify. If 0 matches, return "No customer found matching '{customer_name}'". Only proceed with voucher count when exactly 1 match is found
- [ ] Returns: `[customer_name (via $customer->getName()), total_vouchers, by_state: {issued: N, locked: N, redeemed: N, cancelled: N}]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI020: Tool — Revenue Summary
**Description:** As a Manager, I want a revenue summary for a given period, optionally grouped by invoice type, to understand financial performance.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Finance\RevenueSummaryTool` implements `Tool`
- [ ] Schema parameters: `period` (string, default 'this_month'), `group_by` (string, optional, values: 'invoice_type', 'currency', 'none', default 'invoice_type')
- [ ] Handle: query on `Invoice` model WHERE `status` in [issued, partially_paid, paid] AND `issued_at` within period, SUM `total_amount`, SUM `tax_amount`, SUM `amount_paid`
- [ ] If grouped by invoice_type: breakdown by INV0-INV4 using `InvoiceType` enum labels
- [ ] Returns: `[period_label, gross_revenue, tax_total, net_revenue, amount_collected, outstanding, breakdown_by_group]`
- [ ] All amounts formatted with `bcmath` and EUR symbol
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI021: Tool — Outstanding Invoices
**Description:** As a Manager, I want to see all invoices with outstanding balances to track receivables.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Finance\OutstandingInvoicesTool` implements `Tool`
- [ ] Schema parameters: `min_amount` (numeric, optional), `invoice_type` (string, optional, enum of InvoiceType values), `limit` (integer, default 20)
- [ ] Handle: query on `Invoice` WHERE `status` in [issued, partially_paid], with `customer`, ordered by outstanding amount DESC (using `getOutstandingAmount()` or raw `total_amount - amount_paid`)
- [ ] Returns: `[total_outstanding_amount, invoice_count, invoices: [{invoice_number, customer_name, invoice_type (label), total_amount, amount_paid, outstanding, issued_at, due_date, is_overdue}]]`
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI022: Tool — Overdue Invoices
**Description:** As a Manager, I want to identify overdue invoices to prioritize collection efforts.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Finance\OverdueInvoicesTool` implements `Tool`
- [ ] Schema parameters: `days_overdue_min` (integer, optional, default 0), `limit` (integer, default 20)
- [ ] Handle: query on `Invoice` WHERE `status` = issued AND `due_date` < today, with `customer`, ordered by days overdue DESC
- [ ] Returns: `[total_overdue_count, total_overdue_amount, invoices: [{invoice_number, customer_name, invoice_type (label), total_amount, due_date, days_overdue}]]`
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI023: Tool — Payment Reconciliation Status
**Description:** As a Finance Admin, I want to see the current state of payment reconciliation to identify mismatches.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Finance\PaymentReconciliationStatusTool` implements `Tool`
- [ ] Schema parameters: `status` (string, optional, values from `ReconciliationStatus` enum: 'pending', 'matched', 'mismatched')
- [ ] Handle: query on `Payment` model COUNT GROUP BY `reconciliation_status`, optionally filtered. For mismatched: include detail with `customer` relationship
- [ ] Returns: `[total_payments, by_reconciliation_status: {pending: N, matched: N, mismatched: N}, mismatched_details (if filtered): [{payment_reference, customer_name, amount, source (label), received_at}]]`
- [ ] Requires `ToolAccessLevel::Full`
- [ ] Typecheck and lint pass

---

#### US-AI024: Tool — Credit Note Summary
**Description:** As a Finance Admin, I want a summary of credit notes to monitor adjustment activity.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Finance\CreditNoteSummaryTool` implements `Tool`
- [ ] Schema parameters: `period` (string, default 'this_month'), `status` (string, optional, values from `CreditNoteStatus` enum)
- [ ] Handle: query on `CreditNote` model within period, COUNT and SUM `amount` GROUP BY `status`
- [ ] Returns: `[total_credit_notes, total_amount, by_status: {draft: {count, amount}, issued: {count, amount}, applied: {count, amount}}]`
- [ ] Requires `ToolAccessLevel::Full`
- [ ] Typecheck and lint pass

---

#### US-AI030: Tool — Allocation Status Overview
**Description:** As a Manager, I want an overview of allocations by status to understand supply availability.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Allocation\AllocationStatusOverviewTool` implements `Tool`
- [ ] No required parameters
- [ ] Handle: query on `Allocation` model COUNT GROUP BY `status` (from `AllocationStatus` enum: draft, active, exhausted, closed). For active allocations: SUM `total_quantity`, SUM `sold_quantity`, compute remaining
- [ ] Returns: `[total_allocations, by_status: {draft: N, active: N, exhausted: N, closed: N}, active_summary: {total_quantity, sold_quantity, remaining_quantity, utilization_percentage}]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI031: Tool — Voucher Counts by State
**Description:** As a Manager, I want to see the distribution of vouchers across lifecycle states to monitor the sales pipeline.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Allocation\VoucherCountsByStateTool` implements `Tool`
- [ ] Schema parameters: `customer_id` (string UUID, optional — filter to specific customer)
- [ ] Handle: query on `Voucher` model COUNT GROUP BY `lifecycle_state` (from `VoucherLifecycleState` enum: issued, locked, redeemed, cancelled)
- [ ] Returns: `[total_vouchers, by_state: {issued: N, locked: N, redeemed: N, cancelled: N}, active_vouchers (issued + locked)]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI032: Tool — Bottles Sold by Producer
**Description:** As a Manager, I want to know how many bottles of a specific producer (or all producers) have been sold to track commercial performance.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Allocation\BottlesSoldByProducerTool` implements `Tool`
- [ ] Schema parameters: `producer_name` (string, optional — if omitted, shows top producers), `period` (string, default 'this_year'), `limit` (integer, default 10)
- [ ] Handle: query on `Voucher` WHERE `lifecycle_state` in [issued, locked, redeemed] AND `created_at` within period. **Exact join chain:** `Voucher.allocation_id` → `Allocation.wine_variant_id` → `WineVariant.wine_master_id` → `WineMaster.producer_id` → `Producer.name`. Eager load: `allocation.wineVariant.wineMaster.producerRelation`. Access producer name via `$voucher->allocation->wineVariant->wineMaster->producer_name` (accessor that resolves `producerRelation->name`). COUNT GROUP BY producer name
- [ ] If `producer_name` provided: LIKE search filter on `Producer.name`, also show breakdown by wine name (`WineMaster.name`). **Name disambiguation:** If the LIKE search matches multiple distinct producers (e.g., "Domaine" matches 5 producers), use `BaseTool::disambiguateResults()` to return a list of matching producers so the LLM can ask the user to clarify. If 0 matches, return "No producer found matching '{producer_name}'"
- [ ] Returns: `[producers: [{producer_name, bottles_sold, top_wines: [{wine_name, count}]}]]`
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI040: Tool — Stock Levels by Location
**Description:** As a Manager, I want to see current stock levels per warehouse location.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Inventory\StockLevelsByLocationTool` implements `Tool`
- [ ] Schema parameters: `location_id` (string UUID, optional), `state` (string, optional, values from `BottleState` enum, default 'stored')
- [ ] Handle: query on `SerializedBottle` COUNT GROUP BY `current_location_id`, with `currentLocation` relationship, filtered by `state`
- [ ] Returns: `[total_bottles, by_location: [{location_name, location_type (label), country, bottle_count}]]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI041: Tool — Total Bottles Count
**Description:** As an Operator, I want a quick count of total bottles in inventory with breakdown by state.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Inventory\TotalBottlesCountTool` implements `Tool`
- [ ] No required parameters
- [ ] Handle: query on `SerializedBottle` COUNT total, COUNT GROUP BY `state` (from `BottleState` enum — all 7 values), COUNT GROUP BY `ownership_type` (from `OwnershipType` enum — note: enum case is `CururatedOwned` with typo, string value is `crurated_owned`)
- [ ] Returns: `[total_bottles, by_state: {stored: N, reserved_for_picking: N, shipped: N, consumed: N, destroyed: N, missing: N, mis_serialized: N}, by_ownership: {crurated_owned: N, in_custody: N, third_party_owned: N}]`
- [ ] Requires `ToolAccessLevel::Overview`
- [ ] Typecheck and lint pass

---

#### US-AI042: Tool — Case Integrity Status
**Description:** As a Manager, I want to know the integrity status of cases in inventory (intact vs broken).

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Inventory\CaseIntegrityStatusTool` implements `Tool`
- [ ] No required parameters
- [ ] Handle: query on `InventoryCase` (table `cases`) COUNT GROUP BY `integrity_status` (from `CaseIntegrityStatus` enum: intact, broken)
- [ ] Returns: `[total_cases, intact_count, broken_count, intact_percentage]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### ~~US-AI043: Tool — Expected Inbounds~~ MERGED INTO US-AI061
**Rationale:** US-AI043 and US-AI061 (Inbound Schedule) perform nearly identical queries on `PurchaseOrder` filtered by expected delivery dates. Keeping both would confuse the LLM in tool selection. The combined tool is US-AI061 (InboundScheduleTool) which covers both use cases with its parameters.

---

#### US-AI050: Tool — Pending Shipping Orders
**Description:** As a Manager, I want the list of active shipping orders to monitor the fulfillment pipeline.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Fulfillment\PendingShippingOrdersTool` implements `Tool`
- [ ] Schema parameters: `status` (string, optional, values from `ShippingOrderStatus` non-terminal cases: 'draft', 'planned', 'picking', 'shipped', 'on_hold'), `limit` (integer, default 20)
- [ ] Handle: query on `ShippingOrder` WHERE `status` is non-terminal (exclude `completed` and `cancelled` using `ShippingOrderStatus::isTerminal()`), with `customer`, COUNT via `lines()` relationship per SO
- [ ] Returns: `[total_pending, by_status: {draft: N, planned: N, picking: N, shipped: N, on_hold: N}, orders: [{id, customer_name, status (label), line_count, created_at, requested_ship_date}]]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI051: Tool — Shipment Status
**Description:** As an Operator, I want to check the status of a specific shipment or see an overview of recent shipments.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Fulfillment\ShipmentStatusTool` implements `Tool`
- [ ] Schema parameters: `tracking_number` (string, optional), `status` (string, optional, values from `ShipmentStatus` enum), `period` (string, default 'last_7_days')
- [ ] Handle: if `tracking_number` provided, direct lookup. Otherwise query on `Shipment` filtered by status/period, with `shippingOrder` → `customer`
- [ ] Returns: single shipment detail or list: `[{tracking_number, status (label), carrier, customer_name, shipped_at, delivered_at, shipping_order_reference}]`
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI052: Tool — Shipments In Transit
**Description:** As a Manager, I want to know how many shipments are currently in transit.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Fulfillment\ShipmentsInTransitTool` implements `Tool`
- [ ] No required parameters
- [ ] Handle: query on `Shipment` WHERE `status` in [`Preparing`, `Shipped`, `InTransit`] (the 3 non-terminal states — `Delivered` and `Failed` are terminal per `ShipmentStatus::isTerminal()`), with `shippingOrder` → `customer`
- [ ] Returns: `[count_in_transit, shipments: [{tracking_number, customer_name, carrier, shipped_at, days_since_dispatch}]]`
- [ ] Requires `ToolAccessLevel::Overview`
- [ ] Typecheck and lint pass

---

#### US-AI060: Tool — Pending Purchase Orders
**Description:** As a Manager, I want the list of open purchase orders for procurement monitoring.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Procurement\PendingPurchaseOrdersTool` implements `Tool`
- [ ] Schema parameters: `status` (string, optional, values: 'draft', 'sent', 'confirmed'), `limit` (integer, default 20)
- [ ] Handle: query on `PurchaseOrder` WHERE `status` is non-terminal (use `! PurchaseOrderStatus::isTerminal()` — terminal state is `Closed`), optionally filtered by `$status` param. With `procurementIntent`, `supplier`, ORDER BY `created_at` DESC
- [ ] Returns: `[total_pending, orders: [{id, status (label), supplier_name, quantity, unit_cost, currency, expected_delivery_start}]]` (note: PurchaseOrder has no dedicated `reference` field — use UUID `id` as identifier)
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI061: Tool — Inbound Schedule (absorbs US-AI043)
**Description:** As a Manager, I want to see the calendar of expected inbounds for logistics planning, including what bottles are expected to arrive.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Procurement\InboundScheduleTool` implements `Tool`
- [ ] Schema parameters: `days_ahead` (integer, default 30, max 90), `include_draft` (boolean, default false — if true, also includes POs in `draft` status)
- [ ] Handle: query on `PurchaseOrder` WHERE `status` in [sent, confirmed] (+ draft if `include_draft`), AND `expected_delivery_start` between now and now + days_ahead, with `inbounds`, with `supplier` (Party via `supplier_party_id`), with `procurementIntent` for product context, ORDER BY `expected_delivery_start` ASC. Note: expected delivery dates live on PurchaseOrder, not on Inbound (which only has `received_date`)
- [ ] Returns: `[total_expected, purchase_orders: [{purchase_order_id, expected_delivery_start, expected_delivery_end, supplier_name, quantity, unit_cost, currency, status (label), inbound_count, inbound_statuses}]]`
- [ ] Tool description must clearly state: "Check expected inbound deliveries and incoming stock schedule" — this guides the LLM to use this single tool for both "what's arriving?" and "inbound schedule" questions
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI062: Tool — Procurement Intents Status
**Description:** As a Manager, I want the distribution of procurement intents by status to monitor the sourcing pipeline.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Procurement\ProcurementIntentsStatusTool` implements `Tool`
- [ ] No required parameters
- [ ] Handle: query on `ProcurementIntent` COUNT GROUP BY `status` (from `ProcurementIntentStatus` enum)
- [ ] Also count intents without associated PurchaseOrder (orphaned demand)
- [ ] Returns: `[total_intents, by_status: {draft: N, approved: N, executed: N, closed: N}, without_purchase_order: N]`
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI070: Tool — Active Offers
**Description:** As a Manager, I want the list of currently active offers for commercial overview.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Commercial\ActiveOffersTool` implements `Tool`
- [ ] Schema parameters: `channel_id` (string UUID, optional), `limit` (integer, default 20)
- [ ] Handle: query on `Offer` WHERE `status` = active, with `sellableSku` → `wineVariant` → `wineMaster`, `channel`, `priceBook`
- [ ] Returns: `[total_active, offers: [{name, wine_name, channel_name, offer_type (label), valid_from, valid_to, visibility (label)}]]` (note: field is `name` on Offer model, not `offer_name`)
- [ ] Requires `ToolAccessLevel::Basic`
- [ ] Typecheck and lint pass

---

#### US-AI071: Tool — Price Book Coverage
**Description:** As a Manager, I want to know the coverage of price books (how many SKUs have prices vs total active SKUs) to identify pricing gaps.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Commercial\PriceBookCoverageTool` implements `Tool`
- [ ] Schema parameters: `price_book_id` (string UUID, optional — if omitted, overview of all active price books)
- [ ] Handle: query `PriceBookEntry` COUNT per `price_book_id`, compared to total active `SellableSku` count
- [ ] Returns: `[price_books: [{name, status (label), total_entries, total_active_skus, coverage_percentage, missing_count}]]`
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI072: Tool — EMP Alerts (Estimated Market Price)
**Description:** As a Manager, I want to identify products priced significantly above or below estimated market price.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Commercial\EmpAlertsTool` implements `Tool`
- [ ] Schema parameters: `threshold_percent` (float, default 20.0, min 5.0, max 100.0)
- [ ] Handle: query `EstimatedMarketPrice` JOIN `PriceBookEntry` via shared `sellable_sku_id` (no direct FK between EMP and PriceBookEntry — join through common sellable_sku_id), calculate deviation percentage using `$emp->emp_value` (the field name is `emp_value`, NOT `market_price`), filter by threshold
- [ ] Returns: `[alerts: [{wine_name, our_price, market_price (from emp_value), deviation_percent, direction ('above'/'below'), confidence_level (label), price_book_name}]]`
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

#### US-AI080: Tool — Product Catalog Search
**Description:** As an Operator, I want to search the product catalog by name, producer, or appellation.

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Pim\ProductCatalogSearchTool` implements `Tool`
- [ ] Schema parameters: `query` (string, required, min 2), `type` (string, optional, values: 'wine_master', 'sellable_sku', 'all', default 'all')
- [ ] Handle: LIKE search on `WineMaster` (name, producer, appellation) or `SellableSku` (sku_code), with variant info
- [ ] Returns max 20 results: `[{name, producer, appellation, vintage (if variant), format, sku_code (if SKU), lifecycle_status}]`
- [ ] Requires `ToolAccessLevel::Overview`
- [ ] Typecheck and lint pass

---

#### US-AI081: Tool — PIM Data Quality Issues
**Description:** As an Admin, I want to identify data quality issues in the PIM (products without SKUs, missing fields, etc.).

**Acceptance Criteria:**
- [ ] Class `App\AI\Tools\Pim\DataQualityIssuesTool` implements `Tool`
- [ ] No required parameters
- [ ] Handle: queries for: WineMaster without WineVariants, WineVariant without SellableSku, SellableSku without CaseConfiguration, WineMaster with null `producer_id` or `country_id` or `region_id`
- [ ] Returns: `[issues: [{type, severity ('high'/'medium'/'low'), count, sample_names (first 10)}]]`
- [ ] Requires `ToolAccessLevel::Standard`
- [ ] Typecheck and lint pass

---

### Section 3: Chat UI

#### US-AI100: Filament Custom Page for AI Assistant
**Description:** As an Operator, I want to access the AI Assistant through a dedicated page in the Filament panel.

**Acceptance Criteria:**
- [ ] Class `App\Filament\Pages\AiAssistant` extends `Filament\Pages\Page`
- [ ] `protected ?string $maxContentWidth = 'full'` (string type, not enum — per project convention)
- [ ] Navigation: `$navigationIcon = 'heroicon-o-chat-bubble-left-right'`, `$navigationGroup = 'System'`, `$navigationSort = 99`
- [ ] View: `protected static string $view = 'filament.pages.ai-assistant'`
- [ ] Page auto-discovered by `discoverPages()` in AdminPanelProvider
- [ ] Page visible only to authenticated users
- [ ] Typecheck and lint pass
- [ ] Verify in browser using dev-browser skill

---

#### US-AI101: Chat Interface with Input and Messages
**Description:** As an Operator, I want a chat interface with a message area and input field to communicate with the AI.

**Acceptance Criteria:**
- [ ] Two-column layout: left sidebar (conversation list, 280px) + main chat area
- [ ] Chat area: header with conversation title, scrollable message area, fixed input at bottom
- [ ] User messages aligned right with `bg-primary-50` background, AI messages aligned left with `bg-white` background
- [ ] Input field: auto-expanding textarea, placeholder "Ask anything about Crurated ERP...", submit with Enter (Shift+Enter for newline)
- [ ] Send button with `heroicon-o-paper-airplane` icon, disabled during send
- [ ] Design consistent with ERP density: `p-4`, `gap-3`, `fi-section rounded-xl`
- [ ] Dark mode support (pattern: `dark:bg-gray-900 dark:ring-white/10`)
- [ ] Typecheck and lint pass
- [ ] Verify in browser using dev-browser skill

---

#### US-AI102: Streaming Responses
**Description:** As an Operator, I want to see AI responses appear progressively (streaming) for a fluid UX.

**Acceptance Criteria:**
- [ ] Dedicated route for streaming: `POST /admin/ai/chat` returning SSE (Server-Sent Events). Route registered in `routes/web.php` with middleware `['web', 'auth']` (standard Filament auth middleware stack). **Note:** `routes/web.php` is currently almost empty (only the `/` welcome route) — all ERP routing is via Filament auto-routing. These AI API routes are the first custom routes in `web.php`. The `auth` middleware uses the default `web` guard (session-based, Eloquent provider) which is the same guard Filament uses — no custom guard configuration needed
- [ ] **CSRF handling:** Alpine.js `fetch()` must include `X-CSRF-TOKEN` header, read from `document.querySelector('meta[name="csrf-token"]').content` (Filament already renders this meta tag). **Session expiry handling:** If the server returns HTTP 419 (CSRF token mismatch — typically caused by expired session after prolonged inactivity), the frontend must show an inline message "Your session has expired. Please reload the page." with a reload button. Do NOT silently retry — the user needs to re-authenticate
- [ ] **Server timeout config:** The SSE route controller must call `set_time_limit(120)` at the start of the handler as a safety net for CLI/dev environments. **Note:** `set_time_limit()` has NO effect under PHP-FPM (production) — the actual timeout is controlled by `request_terminate_timeout` in the FPM pool config and `proxy_read_timeout` in Nginx. Document in deploy notes: Nginx `proxy_read_timeout 120s` and PHP-FPM `request_terminate_timeout = 120` are required for the `/admin/ai/chat` route. Add these to `CLAUDE.md` Known Gotchas section after deployment
- [ ] Agent uses `->stream()` method from the SDK for incremental responses
- [ ] Frontend built as standalone Alpine.js component (NOT Livewire) — consumes SSE via `fetch()` API and updates the DOM progressively. Use `fetch()` with `ReadableStream` reader (NOT `EventSource`, which doesn't support POST). **Note:** Alpine.js is already used in the project (e.g., `finance-overview.blade.php`, `inventory-overview.blade.php`) for x-data, x-show, x-transition, and Livewire integration via `$wire` — so this pattern is consistent with existing codebase
- [ ] "Typing" indicator (3 animated dots) visible while waiting for first chunk
- [ ] Auto-scroll to bottom during streaming
- [ ] On error during streaming: inline message "Something went wrong. Please try again." On 429 (rate limit): show "Rate limit exceeded. Try again in {minutes} minutes." On 419 (CSRF expired): show "Session expired. Please reload the page." with reload button
- [ ] Typecheck and lint pass
- [ ] Verify in browser using dev-browser skill

---

#### US-AI103: Advanced Message Rendering (Markdown, Tables, Tool Badges)
**Description:** As an Operator, I want AI responses rendered with rich formatting for readability.

**Acceptance Criteria:**
- [ ] Markdown rendering: headings, bold, italic, inline code, code blocks
- [ ] Markdown tables rendered as HTML tables with Filament-consistent styling
- [ ] Ordered and unordered lists rendered correctly
- [ ] Internal Filament URLs (`/admin/...`) rendered as clickable links
- [ ] Tool call badges visible: tool name, module icon, execution time
- [ ] Numbers and amounts formatted with monospace font
- [ ] Typecheck and lint pass
- [ ] Verify in browser using dev-browser skill

---

#### US-AI104: Conversation List and Navigation
**Description:** As an Operator, I want a sidebar with my conversation list to navigate between sessions.

**Acceptance Criteria:**
- [ ] **Backend API endpoints** (registered in `routes/web.php` with `['web', 'auth']` middleware). All 5 AI routes (including `POST /admin/ai/chat` from US-AI102) should be grouped together:
  ```php
  Route::middleware(['web', 'auth'])->prefix('admin/ai')->group(function () {
      Route::post('/chat', [ChatController::class, 'stream']);
      Route::get('/conversations', [ConversationController::class, 'index']);
      Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages']);
      Route::patch('/conversations/{id}', [ConversationController::class, 'update']);
      Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
  });
  ```
  - `GET /admin/ai/conversations` — returns paginated list of user's conversations (fields: id, title, updated_at, message_count). Controller: `App\Http\Controllers\AI\ConversationController@index`. Query: `agent_conversations` WHERE `user_id` = auth, `deleted_at` IS NULL, ORDER BY `updated_at` DESC, paginate 20
  - `GET /admin/ai/conversations/{id}/messages` — returns messages for a conversation. Controller: `App\Http\Controllers\AI\ConversationController@messages`. Query: `agent_conversation_messages` WHERE `conversation_id`, ORDER BY `created_at` ASC. Verify ownership (user_id matches auth)
  - `DELETE /admin/ai/conversations/{id}` — soft-deletes a conversation (see US-AI112)
  - `PATCH /admin/ai/conversations/{id}` — updates conversation title (see US-AI111)
- [ ] Left sidebar with conversations ordered by last message timestamp DESC
- [ ] Each conversation shows: title (truncated to 50 chars), last message timestamp, message count
- [ ] "New Conversation" button at top of sidebar with `heroicon-o-plus` icon
- [ ] Click on conversation loads its history into the chat area via `GET /admin/ai/conversations/{id}/messages`
- [ ] Active conversation highlighted with `bg-primary-50` background
- [ ] Lazy-load pagination (infinite scroll or "Load more" button) via cursor/offset on the API
- [ ] Typecheck and lint pass
- [ ] Verify in browser using dev-browser skill

---

#### US-AI105: Top Bar Quick-Access Icon
**Description:** As an Operator, I want a chat icon in the Filament top bar to quickly access the AI Assistant from any page.

**Acceptance Criteria:**
- [ ] Chat bubble icon (`heroicon-o-chat-bubble-left-right`) added to Filament top bar via `renderHook('panels::user-menu.before')`. **Note:** `renderHook()` is NOT currently used anywhere in the ERP — this will be the first instance. Registration goes in `AdminPanelProvider::panel()` method: `->renderHook('panels::user-menu.before', fn () => view('filament.hooks.ai-chat-icon'))`. Verify the hook renders in the correct position (to the left of the user menu) by inspecting in browser
- [ ] Click opens a slide-over panel (Filament Action modal or custom Blade) with a simplified chat interface
- [ ] Slide-over shows: last active conversation (or new), input field, streaming responses
- [ ] No sidebar in slide-over mode (compact view)
- [ ] Closing slide-over preserves conversation state. **Implementation:** Use `Alpine.store('aiChat', { conversationId: null, isOpen: false })` with `@persist` on `conversationId`. The conversation ID stored in localStorage is sufficient to reload the conversation from the server on next open. Messages are fetched from `GET /admin/ai/conversations/{id}/messages` (US-AI104 API)
- [ ] Navigating between Filament pages does NOT lose the slide-over state (Alpine store persisted in localStorage survives full page navigations)
- [ ] Navigating to the full page from slide-over is possible via "Open full view" link (passes `?conversation={id}` query param)
- [ ] Typecheck and lint pass
- [ ] Verify in browser using dev-browser skill

---

### Section 4: Conversation Management

#### US-AI110: Conversation Persistence
**Description:** As an Operator, I want my conversations saved automatically so I can resume later.

**Acceptance Criteria:**
- [ ] Conversation created automatically on first message if no active conversation exists
- [ ] Messages saved to `agent_conversation_messages` table by SDK's `RemembersConversations` trait
- [ ] Conversation associated with `user_id` (only the owner can access their conversations)
- [ ] Page reload preserves the active conversation
- [ ] Typecheck and lint pass

---

#### US-AI111: Auto-Generated Conversation Title
**Description:** As an Operator, I want conversation titles generated automatically from the first message for easy recognition in the list.

**Acceptance Criteria:**
- [ ] **Migration required:** The SDK's `agent_conversations` table does NOT include a `title` column. Add migration `2026_02_XX_500002_alter_agent_conversations_add_title.php` to add `title` (string, nullable) column
- [ ] After first user message, title is set to the first 60 characters of the message (truncated at word boundary with "...")
- [ ] If message is shorter than 60 chars, title is the full message
- [ ] Title is manually editable by the user (click-to-edit in sidebar)
- [ ] Fallback: "New Conversation" if title generation fails
- [ ] Typecheck and lint pass

---

#### US-AI112: Conversation Deletion
**Description:** As an Operator, I want to delete my conversations for cleanup.

**Acceptance Criteria:**
- [ ] **Migration required:** The SDK's `agent_conversations` table does NOT include `deleted_at`. Add `deleted_at` (timestamp, nullable) to the same migration as US-AI111 (`2026_02_XX_500002_alter_agent_conversations_add_title.php`)
- [ ] **SDK model handling:** The SDK's own `Conversation` model does NOT use `SoftDeletes`. Our `ConversationController` (US-AI104) must query the `agent_conversations` table directly with `whereNull('deleted_at')` filter, rather than relying on Eloquent SoftDeletes trait. The `DELETE` endpoint sets `deleted_at = now()` via raw update. This avoids needing to extend or override the SDK model. **Important — centralize the query filter:** Create a private helper in `ConversationController` to avoid forgetting the `whereNull('deleted_at')` filter:
  ```php
  private function conversationsQuery(): \Illuminate\Database\Query\Builder
  {
      return DB::table('agent_conversations')
          ->where('user_id', auth()->id())
          ->whereNull('deleted_at');
  }
  ```
  ALL conversation queries in the controller (index, messages ownership check, update, delete) MUST use this helper. A developer forgetting the `whereNull('deleted_at')` filter would cause "deleted" conversations to reappear
- [ ] Delete button on each conversation in sidebar (trash icon with confirmation dialog)
- [ ] Deletion is soft-delete (conversation and messages retained for audit)
- [ ] Only the owner can delete their own conversations (verify `user_id` matches authenticated user)
- [ ] super_admin can view (read-only) other users' conversations for audit purposes via a separate admin endpoint or Filament resource (NOT via the chat UI)
- [ ] Typecheck and lint pass

---

#### US-AI113: Conversation Context Window Management
**Description:** As a Developer, I want to manage the conversation context window to avoid exceeding AI model token limits.

**Acceptance Criteria:**
- [ ] Maximum last N messages sent as context to the API (configurable in `config/ai-assistant.php`, default **30** — reduced from original 50). **Rationale:** With 25 tools that return detailed structured data (tables, lists, aggregations), tool call results can be very large. 50 messages with tool results could approach 100K+ tokens. Sonnet 4.5 has 200K context but after system prompt (~4K tokens) + tool definitions (~25 tools × ~200 tokens = ~5K) + tool results, effective capacity is ~150K. Default 30 is conservative — monitor `tokens_input` in `ai_audit_logs` and adjust upward if needed
- [ ] Older messages excluded from API context but retained in database for UI display
- [ ] Visual indicator in UI when conversation is long: "Older messages not included in AI context" — show a subtle divider with this text above the oldest message in the context window
- [ ] System prompt counts toward token budget but is not visible to the user
- [ ] **Monitoring hook:** After each API call, log `tokens_input` in the audit log. If `tokens_input` exceeds 100K tokens for any single request, add a warning to the audit log metadata (`{"warning": "high_token_usage"}`). This helps identify if the context window needs further tuning
- [ ] Typecheck and lint pass

---

### Section 5: Security & Authorization

#### US-AI120: Role-Based Tool Access
**Description:** As an Admin, I want AI tools to respect the authenticated user's role to protect sensitive data.

**Acceptance Criteria:**
- [ ] Each tool declares its minimum `ToolAccessLevel` via `requiredAccessLevel()` method
- [ ] Before execution, agent verifies `ToolAccessLevel::forRole(auth()->user()->role)` >= tool's requirement
- [ ] If tool unauthorized: agent responds "You don't have permission to access this data. Required role: {minimum_role}."
- [ ] Mapping: super_admin/admin → Full, manager → Standard, editor → Basic, viewer → Overview
- [ ] Denied tool calls logged in `ai_audit_logs` with metadata
- [ ] Typecheck and lint pass

---

#### US-AI121: Data Scoping Architecture
**Description:** As a Developer, I want the tool architecture to support future data scoping per user/role.

**Acceptance Criteria:**
- [ ] BaseTool includes an overridable method `scopeQuery(Builder $query, User $user): Builder` where `Builder` is `Illuminate\Database\Eloquent\Builder` (NOT `Illuminate\Database\Query\Builder`). Use the Eloquent Builder since all tools query via Eloquent models. **PHPStan note:** The method signature should be `scopeQuery(\Illuminate\Database\Eloquent\Builder $query, User $user): \Illuminate\Database\Eloquent\Builder` — do not import the generic `Builder` alias to avoid ambiguity
- [ ] For v1, the default implementation returns `$query` unchanged (all data visible at user's access level)
- [ ] Architecture is ready for v2 where a manager might only see their own customers/orders
- [ ] Typecheck and lint pass

---

#### US-AI122: Audit Logging of AI Interactions
**Description:** As an Admin, I want a complete audit trail of all AI interactions for compliance and cost tracking.

**Acceptance Criteria:**
- [ ] Every user message generates a record in `ai_audit_logs` with: user_id, conversation_id, message_text (user's message only, NOT AI response), tools_invoked (JSON array of tool names), tokens_input, tokens_output, estimated_cost_eur, duration_ms
- [ ] Logs are immutable (boot guard preventing update/delete, same pattern as `AuditLog` model)
- [ ] Admin view for logs: Filament table page with filters by user, date range, tool used
- [ ] Cost report: estimated cost based on `tokens * price_per_token` (from config)
- [ ] Typecheck and lint pass

---

#### US-AI123: Input Sanitization
**Description:** As a Developer, I want user input sanitized before sending to the AI model.

**Acceptance Criteria:**
- [ ] Maximum input length: 2000 characters (configurable)
- [ ] HTML tags stripped from input
- [ ] Validation: input not empty, not whitespace-only
- [ ] Oversized input returns user-friendly error: "Message too long. Maximum {max_length} characters."
- [ ] Typecheck and lint pass

---

### Section 6: Testing & Quality

#### US-AI130: Unit Tests for Each Tool
**Description:** As a Developer, I want unit tests for every AI tool to ensure query correctness.

**Acceptance Criteria:**
- [ ] Test files organized by module: `tests/Unit/AI/Tools/{Module}/{ToolName}Test.php`
- [ ] Each test uses `RefreshDatabase` and seeds test data
- [ ] Verifications: correct output structure, filters work correctly, limits are respected, currency formatting is correct
- [ ] Authorization test: insufficient role returns error
- [ ] Minimum 3 tests per tool: happy path, filtering, authorization
- [ ] All tests pass with `php artisan test`
- [ ] Typecheck and lint pass

---

#### US-AI131: Integration Tests for Agent
**Description:** As a Developer, I want integration tests to verify the agent orchestrates tools correctly.

**Acceptance Criteria:**
- [ ] File: `tests/Feature/AI/ErpAssistantAgentTest.php`
- [ ] Uses `ErpAssistantAgent::fake()` from SDK to mock model responses (SDK uses per-class faking, not generic `Agent::fake()`)
- [ ] Verifies: agent responds, conversation is persisted, audit log is created
- [ ] Verifies: rate limiting works (exceeding limit returns 429)
- [ ] Verifies: role-based access (viewer cannot access finance tools)
- [ ] All tests pass
- [ ] Typecheck and lint pass

---

#### US-AI132: Feature Tests for UI
**Description:** As a Developer, I want feature tests for the Filament AI Assistant page.

**Acceptance Criteria:**
- [ ] File: `tests/Feature/Filament/AiAssistantPageTest.php`
- [ ] Verifies: page accessible for authenticated user
- [ ] Verifies: page not accessible without login (redirect)
- [ ] Verifies: role check works
- [ ] Verifies: conversation created on first message
- [ ] All tests pass
- [ ] Typecheck and lint pass

---

#### US-AI133: PHPStan and Pint Compliance
**Description:** As a Developer, I want all AI code compliant with PHPStan level 5 and Laravel Pint.

**Acceptance Criteria:**
- [ ] `composer analyse` reports no errors for files in `app/AI/`, `app/Filament/Pages/AiAssistant.php`, `app/Models/AI/`, `app/Enums/AI/`
- [ ] `composer lint:test` reports no errors
- [ ] All types explicit, no unnecessary `@var`, no avoidable `mixed` types
- [ ] Typecheck and lint pass

---

## Functional Requirements

- **FR-1:** The AI Assistant is strictly read-only. No tool may execute INSERT, UPDATE, or DELETE operations on any table.
- **FR-2:** All tool queries use Eloquent models with their existing soft-delete scopes, casts, and relationships. No raw SQL.
- **FR-3:** Financial amounts in tool responses must use `bcmath` functions (`bcadd`, `bcsub`, `bcmul`, `bcdiv`) for precision. Never use float arithmetic.
- **FR-4:** The system prompt must not exceed 4000 tokens to preserve context window for conversation history and tool results.
- **FR-5:** Every tool must declare its minimum `ToolAccessLevel`. The agent must verify authorization before executing any tool.
- **FR-6:** All AI interactions must be audit-logged with immutable records in the `ai_audit_logs` table.
- **FR-7:** Streaming responses must use SSE (Server-Sent Events) to deliver incremental text to the frontend.
- **FR-8:** The conversation context window must be limited to the last N messages (configurable, default 30) to stay within model token limits.
- **FR-9:** Rate limiting must be enforced per-user via cache counters (primary) with audit log fallback, based on configurable thresholds (default: 60/hour, 500/day).
- **FR-10:** The AI must respond in the same language as the user's question (Italian or English).
- **FR-11:** The top bar quick-access icon must be available on every Filament page via render hook.
- **FR-12:** Tool results must be returned as structured arrays. The AI model decides how to format them for the user.

---

## Non-Goals

- **Write operations**: The AI Assistant will never create, update, or delete records. It is purely analytical. Users needing to take action will be directed to the appropriate Filament resource page.
- **Direct SQL execution**: No text-to-SQL capability. All queries are predefined in Tool classes. This eliminates SQL injection risk and hallucinated query risk.
- **Real-time alerting**: Covered by the Admin Panel PRD's Alert Infrastructure (US-AP006 through US-AP012). The AI can report current status but does not push notifications.
- **Report generation / PDF export**: Out of scope for v1. The AI provides on-screen answers, not downloadable documents.
- **External API querying**: The AI queries ERP database only. It does not call Xero, Stripe, WMS, or Liv-ex APIs directly. It can report data already synced into the ERP.
- **Multi-tenant isolation**: The ERP is single-tenant. No tenant-level data scoping is needed.
- **Voice input**: Text-only interface for v1.
- **Automated actions based on AI suggestions**: The AI may suggest actions ("You have 15 overdue invoices, you might want to review them") but will never execute them.

---

## Technical Considerations

### Directory Structure

```
app/
  AI/
    Agents/
      ErpAssistantAgent.php
    Prompts/
      erp-system-prompt.md
    Tools/
      BaseTool.php
      Customer/
        TopCustomersByRevenueTool.php
        CustomerSearchTool.php
        CustomerStatusSummaryTool.php
        CustomerVoucherCountTool.php
      Finance/
        RevenueSummaryTool.php
        OutstandingInvoicesTool.php
        OverdueInvoicesTool.php
        PaymentReconciliationStatusTool.php
        CreditNoteSummaryTool.php
      Allocation/
        AllocationStatusOverviewTool.php
        VoucherCountsByStateTool.php
        BottlesSoldByProducerTool.php
      Inventory/
        StockLevelsByLocationTool.php
        TotalBottlesCountTool.php
        CaseIntegrityStatusTool.php
      Fulfillment/
        PendingShippingOrdersTool.php
        ShipmentStatusTool.php
        ShipmentsInTransitTool.php
      Procurement/
        PendingPurchaseOrdersTool.php
        InboundScheduleTool.php
        ProcurementIntentsStatusTool.php
      Commercial/
        ActiveOffersTool.php
        PriceBookCoverageTool.php
        EmpAlertsTool.php
      Pim/
        ProductCatalogSearchTool.php
        DataQualityIssuesTool.php
  Enums/
    AI/
      ToolAccessLevel.php
  Models/
    AI/
      AiAuditLog.php
  Http/
    Controllers/
      AI/
        ChatController.php          # POST /admin/ai/chat (SSE streaming)
        ConversationController.php  # GET/PATCH/DELETE /admin/ai/conversations
  Filament/
    Pages/
      AiAssistant.php
config/
  ai-assistant.php
database/
  migrations/
    2026_02_XX_500001_create_ai_audit_logs_table.php
    2026_02_XX_500002_alter_agent_conversations_add_title.php
resources/
  views/
    filament/
      pages/
        ai-assistant.blade.php
tests/
  Unit/
    AI/
      Tools/
        Customer/
        Finance/
        Allocation/
        Inventory/
        Fulfillment/
        Procurement/
        Commercial/
        Pim/
  Feature/
    AI/
      ErpAssistantAgentTest.php
    Filament/
      AiAssistantPageTest.php
```

### Tool Class Pattern

Each tool follows the Laravel AI SDK `Tool` contract:

```php
namespace App\AI\Tools\Finance;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RevenueSummaryTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Get revenue summary for a given period, optionally grouped by invoice type.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year'])
                ->default('this_month'),
            'group_by' => $schema->string()
                ->enum(['invoice_type', 'currency', 'none'])
                ->default('invoice_type'),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): \Stringable|string
    {
        [$from, $to] = $this->parsePeriod($request['period'] ?? 'this_month');

        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])
            ->whereBetween('issued_at', [$from, $to]);

        // ... aggregate with bcmath, format response
        return json_encode($result);
    }
}
```

### Agent Class Pattern

```php
namespace App\AI\Agents;

use App\AI\Tools;
use Laravel\Ai\Attributes\{MaxSteps, Model, Provider, Temperature};
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\{Agent, Conversational, HasTools};
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-5-20250929')]
#[MaxSteps(10)]
#[Temperature(0.3)]
class ErpAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return file_get_contents(app_path('AI/Prompts/erp-system-prompt.md'));
    }

    public function tools(): iterable
    {
        return [
            app(Tools\Customer\TopCustomersByRevenueTool::class),
            app(Tools\Customer\CustomerSearchTool::class),
            // ... all 25 tools
        ];
    }
}
```

### Migration Numbering

Following the project convention:
- Module AI: **500000+** (new module, after Module D at 400000+)
- First migration: `2026_02_XX_500001_create_ai_audit_logs_table.php`
- Second migration: `2026_02_XX_500002_alter_agent_conversations_add_title.php` (adds `title` and `deleted_at` to SDK table)

### Key Architectural Decisions

1. **Tools use Eloquent, not raw SQL**: All tools query via Eloquent models to benefit from soft-delete scoping, relationships, casts, and consistency with the rest of the codebase. **Type-safety rule**: all `whereIn('status', [...])` filters MUST use enum instances (e.g., `InvoiceStatus::Issued`) instead of raw strings (e.g., `'issued'`). This PRD uses string values in examples for readability, but the implementation must always use the PHP enum.

2. **Tools return structured data**: Each tool returns arrays/JSON. The AI model decides how to present the data (table, list, summary) based on the user's question.

3. **System prompt in separate file**: The system prompt lives in `app/AI/Prompts/erp-system-prompt.md` for easy editing without code changes.

4. **Rate limiting via audit logs**: Rate limits check `ai_audit_logs` count in the last hour. This leverages the audit trail and avoids cache-dependency. Index on `(user_id, created_at)` ensures fast lookups. **Performance note**: if the table grows large (>100K records) and COUNT queries slow down, add a cache-based counter as a secondary check (e.g., `Cache::increment("ai_rate:{user_id}:{hour}")` with TTL) to avoid hitting the DB on every request.

5. **SSE for streaming**: Server-Sent Events over HTTP for streaming responses. Simpler than WebSockets, compatible with all browsers, works with the SDK's `->stream()` method.

6. **Tool authorization is declarative**: Each tool declares its `ToolAccessLevel`. Authorization is checked in `BaseTool` before delegating to `handle()`, keeping individual tools clean.

7. **Customer namespace is singular**: The correct model namespace is `App\Models\Customer\Customer` (not `Customers` plural). All Customer module enums are at `App\Enums\Customer\{EnumName}`. This follows the project convention where module folders use singular form.

8. **Inbound has no expected date**: Expected delivery dates (`expected_delivery_start`, `expected_delivery_end`) live on `PurchaseOrder`, not on `Inbound`. The `Inbound` model only records `received_date` (the actual receipt fact). Tools querying future inbounds must JOIN through `PurchaseOrder`.

9. **PurchaseOrder has no reference field**: PurchaseOrder uses its UUID `id` as identifier. There is no dedicated `reference` or `po_number` column. Tools should display the truncated UUID or the related ProcurementIntent for context.

10. **SDK conversation tables need extension**: The SDK's `agent_conversations` table includes `user_id` but lacks `title` and `deleted_at`. A separate migration (`500002`) is required to add these columns for US-AI111 and US-AI112. **Important**: verify exact table names after running SDK migrations (docs show minor inconsistency between `agent_conversations` and `conversations`) — adapt migration 500002 accordingly.

11. **EMP ↔ PriceBookEntry has no direct FK**: `EstimatedMarketPrice` relates to `SellableSku` via `sellable_sku_id`, and `PriceBookEntry` also relates to `SellableSku` via `sellable_sku_id`. To compare EMP vs PriceBookEntry prices, JOIN through the shared `sellable_sku_id` — there is no direct FK between EMP and PriceBookEntry.

12. **Offer model field is `name`, not `offer_name`**: The `Offer` model uses a simple `name` field (not `offer_name`). Tools returning offer data should access `$offer->name`.

13. **Customer name resolution**: Customer has legacy `name` and `email` fields (deprecated). The authoritative source for customer name is `Party.legal_name` accessed via `$customer->getName()` method, which prefers `Party.legal_name` and falls back to legacy `Customer.name`. All tools displaying customer names MUST use `$customer->getName()` instead of `$customer->name`. For search queries, search across BOTH `Customer.name` AND `Party.legal_name` via `whereHas('party', ...)`.

14. **OwnershipType enum typo**: The enum case is `CururatedOwned` (typo: "Cururated" instead of "Crurated"), but the string value is `'crurated_owned'` (correct). Database queries using string values work correctly. When referencing the enum case in code, use `OwnershipType::CururatedOwned`. This is a known pre-existing typo in the codebase.

15. **EMP field name is `emp_value`**: The `EstimatedMarketPrice` model stores the price in `emp_value` (not `market_price` or `price`). Tools comparing EMP to PriceBookEntry prices must access `$emp->emp_value`.

16. **Tool error handling via `safeHandle()` wrapper**: Every tool's `handle()` is wrapped by `BaseTool::safeHandle()` which catches exceptions and returns a structured JSON error message. This prevents uncaught exceptions from crashing the agent mid-conversation. Specific handling for `ModelNotFoundException` (returns "No record found") and generic `\Throwable` (returns "An error occurred while executing {ToolName}"). The LLM receives the error message and can inform the user gracefully.

17. **Name disambiguation pattern**: Tools that search by name (US-AI014, US-AI032) must handle 0, 1, and >1 matches explicitly. On >1 match: return a list of candidates for the LLM to present to the user. On 0 matches: return a clear "not found" message. On exactly 1 match: proceed with the result. This is centralized in `BaseTool::disambiguateResults()` to ensure consistent behavior across all name-search tools.

18. **Rate limiting is cache-first, not DB-first**: Rate limits use `Cache::increment()` as the primary check (O(1) performance) with `ai_audit_logs` COUNT as fallback if cache is unavailable. This avoids a COUNT query on every request, which would degrade as the audit log grows. The audit log remains the source of truth for reporting and analytics, but the hot path never touches it.

19. **Conversation soft-delete query centralization**: Since the SDK's `Conversation` model doesn't use `SoftDeletes`, we add `deleted_at` via migration but filter manually with `whereNull('deleted_at')`. To prevent accidental data leaks (forgotten filter), all conversation queries in `ConversationController` go through a centralized `conversationsQuery()` helper method.

20. **Context window set to 30 messages by default**: Reduced from the original 50 to account for large tool results. With 25 tools returning structured data (tables, lists, aggregations), tool call results can be very large. Monitor `tokens_input` in audit logs and adjust upward if users report context loss.

21. **SDK Schema builder API must be smoke-tested**: The `.enum()` and `.default()` methods on `JsonSchema` type builders are used by ALL 25 tools but are not explicitly documented in the SDK. The US-AI001 smoke test MUST verify these methods exist before any tool implementation begins. If they don't exist, all tool schemas need redesign.

22. **Tool return types are `Stringable|string`**: The SDK's `Tool::description()` and `Tool::handle()` return `Stringable|string`, not just `string`. While returning `string` is compatible at runtime, PHPStan level 5 may flag mismatches. All tool implementations must match the interface exactly.

---

## Success Metrics

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Tool coverage | 25 tools across 8 modules | Count of Tool classes |
| Response latency (p95) | < 5 seconds | `duration_ms` in `ai_audit_logs` |
| Tool correctness | 100% match with dashboard data | Cross-validation tests |
| Test coverage | >= 3 tests per tool, all pass | PHPUnit test count |
| PHPStan compliance | 0 errors at level 5 | `composer analyse` |
| Rate limit effectiveness | 0 abuse incidents | Monitor `ai_audit_logs` |
| User adoption | > 80% of admin users try within 2 weeks | Unique `user_id` in `ai_audit_logs` |
| Average cost per query | < EUR 0.01 | `estimated_cost_eur` in `ai_audit_logs` |

---

## Open Questions

1. **~~Laravel AI SDK maturity~~** — RESOLVED: The SDK is officially released (Feb 2026, Laracon India). All contracts (`Tool`, `Agent`, `Conversational`, `HasTools`), traits (`RemembersConversations`, `Promptable`), attributes (`#[Provider]`, `#[Model]`, `#[MaxSteps]`, `#[Temperature]`), and streaming (`->stream()`) are confirmed to exist with the exact API surface described in this PRD. The SDK also offers `HasMiddleware` and `HasStructuredOutput` contracts not used here but available. Proceed with confidence — no fallback to `mozex/anthropic-laravel` needed.

2. **~~SSE vs Livewire integration~~** — RESOLVED: Use Alpine.js directly for the chat component within the Filament page. Do NOT attempt to integrate SSE streaming through Livewire's reactivity model — build the chat as a standalone Alpine.js component that communicates with the backend via `fetch()` API for SSE streaming. This avoids conflicts between Livewire's DOM diffing and SSE's incremental text updates. The SDK's `->stream()` returns SSE natively, which Alpine.js can consume directly. The SDK also offers `->broadcastOnQueue()` as a fallback pattern if needed.

3. **~~Conversation table extension~~** — RESOLVED: The SDK creates `agent_conversations` (with `conversation_id`, `agent_class`, `user_id`, `expires_at`, timestamps) and `agent_conversation_messages` (with `conversation_id`, `role`, `content`, timestamps). Confirmed: `user_id` is present, but `title` and `deleted_at` are NOT. Solution: add migration `2026_02_XX_500002_alter_agent_conversations_add_title.php` with `title` (string, nullable) and `deleted_at` (timestamp, nullable). See US-AI111 and US-AI112 acceptance criteria.

4. **~~Token usage tracking~~** — RESOLVED: The SDK exposes token usage via `AgentResponse->usage` and `StreamedAgentResponse->usage`. No estimation needed — exact token counts are available from the SDK response objects.

5. **~~GDPR hard-delete~~** — RESOLVED: Implement soft-delete for v1. Add a `purge` Artisan command for GDPR compliance in v2 if required by legal review.

6. **~~Slide-over state management~~** — RESOLVED: Use `Alpine.store('aiChat', { conversationId: null, isOpen: false })` with `@persist` on `conversationId`. The conversation ID persisted in localStorage is sufficient to reload the conversation from the server on page navigation. See US-AI105 acceptance criteria for details.

---

## Implementation Priority

### Phase 1 — MVP (Target: 1 week)
Core infrastructure + basic tools + minimal UI:
- US-AI001 through US-AI006 (Infrastructure)
- US-AI010 (Base tool class)
- 6 core tools: US-AI011 (Top Customers), US-AI020 (Revenue), US-AI030 (Allocation Status), US-AI041 (Bottles Count), US-AI050 (Pending SOs), US-AI080 (Product Search)
- US-AI100, US-AI101, US-AI102 (Basic chat UI with streaming)
- US-AI110 (Persistence)
- US-AI120 (Role-based access)

### Phase 2 — Full Tools + Advanced UI (Target: 1 week)
- Remaining 19 tools (US-AI012-US-AI081, excluding merged US-AI043)
- US-AI103, US-AI104, US-AI105 (Advanced UI: markdown, sidebar, top bar icon)
- US-AI111, US-AI112, US-AI113 (Conversation management)
- US-AI121, US-AI122, US-AI123 (Security hardening)

### Phase 3 — Testing & Polish (Target: 3-5 days)
- US-AI130, US-AI131, US-AI132, US-AI133 (Comprehensive testing)
- Performance optimization
- Cost monitoring review
- Cross-validation against dashboard data
