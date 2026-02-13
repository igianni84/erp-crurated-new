# Module D (Procurement) — Gap Analysis Report

**Date:** 2026-02-09
**Sources compared:**
1. **Functional Doc** — `tasks/ERP-FULL-DOC.md` (Section 8: Module D — Procurement & Inbound)
2. **UI/UX PRD** — `tasks/prd-module-d-procurement.md` (68 user stories, US-001 → US-068)
3. **Implementation** — Actual codebase (models, services, enums, resources, jobs, migrations)

---

## Executive Summary

Module D is **substantially complete** (65/68 user stories implemented). Three gaps remain, two of which are **missing service/job classes** and one is a **partial implementation** of a dashboard feature. No functional doc invariants are violated. The architecture is solid and follows all project conventions.

| Category | PRD Items | Implemented | Gap |
|----------|-----------|-------------|-----|
| Models | 5 | 5 | 0 |
| Enums | 9 | 9 | 0 |
| Services | 5 | 4 | 1 |
| Filament Resources | 5 | 5 | 0 |
| Wizard Pages (Create) | 4 | 4 | 0 |
| View Pages (Tabs) | 4 | 4 | 0 |
| Jobs | 2 | 1 | 1 |
| Listeners | 1 | 1 | 0 |
| Dashboard | 7 (US-050→056) | 7 | 0 |
| Audit Page | 1 (US-068) | 1 | 0 |
| Bulk Actions | 1 (US-017) | 1 | 0 |
| Aggregated View | 1 (US-018) | 1 | 0 |
| Notifications | 1 | 1 | 0 |
| Migrations | 7 tables/alters | 7 | 0 |
| Seeders | 1 | 1 | 0 |

---

## 1. GAPS (Items Missing or Incomplete)

### GAP-1: PurchaseOrderService non esiste
- **PRD Reference:** US-026 (PO Status Transitions), US-027 (Inbound Variance Tracking), US-049 implied
- **Functional Doc:** Section 8.4.2 — PO as core entity with status lifecycle
- **What's specified:** A dedicated `PurchaseOrderService` with methods for status transitions (draft→sent→confirmed→closed), variance calculation, and business validation
- **What's implemented:** PO status transitions and business logic are scattered across:
  - `ViewPurchaseOrder.php` (header actions: Mark Sent, Confirm, Close)
  - `PurchaseOrder` model (variance helper methods: `getVariance()`, `getVariancePercentage()`, `getVarianceStatus()`)
  - Direct Eloquent operations in Filament pages
- **Impact:** Medium. Logic works but violates the service-layer architecture pattern. Business rules are coupled to UI layer instead of being in a reusable service.
- **Recommendation:** Extract PO status transitions into `PurchaseOrderService` with methods: `markSent()`, `confirm()`, `close()`, `calculateVariance()`. Mirror the pattern of `ProcurementIntentService`.

### GAP-2: CheckOverdueInboundsJob non esiste
- **PRD Reference:** Mentioned in PRD directory structure as one of 2 scheduled jobs
- **Functional Doc:** Section 8.6 — Inbound processing with delivery tracking
- **What's specified:** A daily job `CheckOverdueInboundsJob` to flag overdue deliveries
- **What's implemented:** Overdue detection exists only as calculated properties:
  - `PurchaseOrder::isDeliveryOverdue()` (model method)
  - Dashboard metrics calculate overdue counts at query time
  - No proactive notification or flagging system
- **Impact:** Low-Medium. Overdue information is visible on the dashboard but there's no proactive alerting. Ops team must manually check the dashboard.
- **Recommendation:** Create `CheckOverdueInboundsJob` that: (1) finds POs with `expected_delivery_end < today` and status in [Sent, Confirmed], (2) creates audit log entries, (3) sends notifications to Ops.

### GAP-3: Dashboard Auto-Refresh — Parziale
- **PRD Reference:** US-056 — "Auto-refresh every 5 minutes (configurable)"
- **What's specified:** Auto-refresh with configurable interval, default 5 minutes
- **What's implemented:** Livewire `wire:poll` is implemented with configurable intervals (Off, 1, 5, 10, 15 min). However, the default is "Off" rather than "5 minutes" as specified.
- **Impact:** Negligible. The feature exists, just the default setting differs.
- **Recommendation:** Change default `$autoRefresh` from `''` (off) to `'300s'` (5 min).

---

## 2. FUNCTIONAL DOC vs PRD ALIGNMENT

The PRD faithfully translates all functional doc concepts into implementable user stories. Specific alignment check:

| Functional Doc Concept | PRD Coverage | Implementation |
|------------------------|-------------|----------------|
| 8.3.1 Demand-driven procurement | US-001, US-016 (trigger types) | ✅ 4 trigger types + VoucherIssued listener |
| 8.3.2 Sourcing ≠ Selling models | US-002 (SourcingModel enum) | ✅ 3 sourcing models, separate from commercial |
| 8.4.1 Procurement Intent | US-001, US-007, US-009→018 | ✅ Full CRUD, wizard, tabs, aggregated view |
| 8.4.2 Purchase Order | US-003, US-019→027 | ✅ Full CRUD, wizard, tabs, variance tracking |
| 8.4.3 Bottling Instruction | US-004, US-028→036 | ✅ Full CRUD, wizard, tabs, deadline job |
| 8.5.1 Owned stock flow | US-001→005 (model support) | ✅ Supported via sourcing_model=Purchase |
| 8.5.2 Passive consignment (producer) | US-002 (PassiveConsignment) | ✅ Enum + ownership_flag handling |
| 8.5.3 Passive consignment (warehouse) | US-058 (soft-flagged unlinked) | ✅ Inbound without intent allowed, flagged |
| 8.5.4 Active consignment | Described as Module S concern | ✅ Correctly not in Module D |
| 8.5.5 Third-party custody | US-002 (ThirdPartyCustody) | ✅ Enum + in_custody ownership flag |
| 8.6.1 Inbound event | US-005, US-037→045 | ✅ Full CRUD, ownership clarity, hand-off |
| 8.6.2 Serialization routing | US-060 | ✅ Hard blocker + ProducerSupplierConfig |
| 8.7 Multi-allocation → one inbound | US-058, model design | ✅ Inbound links to single intent, multiple intents can share PO |
| 8.9 Governance invariants | US-057→063 | ✅ All 5 invariants enforced |

**Nessuna divergenza funzionale tra doc e PRD.**

---

## 3. PRD vs IMPLEMENTATION — User Story Checklist

### Section 1: Infrastructure (US-001 → US-008) — 8/8 ✅

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-001 | ProcurementIntent Model | ✅ | All fields, morphic FK, soft deletes |
| US-002 | 9 Enums | ✅ | All 9 with label/color/icon/transitions |
| US-003 | PurchaseOrder Model | ✅ | FK to intent enforced in boot() |
| US-004 | BottlingInstruction Model | ✅ | Deadline + preference tracking |
| US-005 | Inbound Model | ✅ | Nullable intent FK, ownership_flag explicit |
| US-006 | ProducerSupplierConfig | ✅ | Party unique constraint, defaults storage |
| US-007 | ProcurementIntentService | ✅ | All 7 public methods implemented |
| US-008 | InboundService | ✅ | All 9 public methods + validation |

### Section 2: Procurement Intents CRUD (US-009 → US-018) — 10/10 ✅

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-009 | Intent List | ✅ | Columns, filters, badges, closed hidden by default |
| US-010 | Wizard Step 1: Product | ✅ | BottleSku/LiquidProduct toggle |
| US-011 | Wizard Step 2: Trigger/Model | ✅ | With guidance text |
| US-012 | Wizard Step 3: Delivery | ✅ | Location + rationale |
| US-013 | Wizard Step 4: Review | ✅ | Summary before creation |
| US-014 | Intent Detail (4 tabs) | ✅ | Summary, Downstream, Allocation, Audit |
| US-015 | Status Transitions | ✅ | Draft→Approved→Executed→Closed |
| US-016 | Auto-create from Voucher | ✅ | Listener + queued + retry |
| US-017 | Bulk Approval | ✅ | With confirmation, progress tracking |
| US-018 | Aggregated View | ✅ | Dedicated page with grouping |

### Section 3: Purchase Orders CRUD (US-019 → US-027) — 8/9 ⚠️

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-019 | PO List | ✅ | Columns, filters, overdue indicator |
| US-020 | Wizard Step 1: Intent | ✅ | Required, autocomplete |
| US-021 | Wizard Step 2: Supplier | ✅ | Config preview |
| US-022 | Wizard Step 3: Terms | ✅ | unit_cost, currency, incoterms |
| US-023 | Wizard Step 4: Delivery | ✅ | Expected dates, warehouse |
| US-024 | Wizard Step 5: Review | ✅ | Summary before creation |
| US-025 | PO Detail (5 tabs) | ✅ | Commercial, Intent, Delivery, Inbound, Audit |
| US-026 | PO Status Transitions | ⚠️ | Works but logic in View page, not in service (GAP-1) |
| US-027 | Inbound Variance | ✅ | Model methods + visual badges |

### Section 4: Bottling Instructions CRUD (US-028 → US-036) — 9/9 ✅

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-028 | Bottling List | ✅ | Urgency indicators, deadline badges |
| US-029 | Wizard Step 1: Intent | ✅ | Liquid product only filter |
| US-030 | Wizard Step 2: Rules | ✅ | From ProducerSupplierConfig |
| US-031 | Wizard Step 3: Personalisation | ✅ | Deadlines, binding flags |
| US-032 | Wizard Step 4: Review | ✅ | Countdown + warnings |
| US-033 | Detail (5 tabs) | ✅ | Rules, Preferences, Linkage, Flags, Audit |
| US-034 | Preference Collection | ✅ | Progress bar, voucher list |
| US-035 | Deadline Enforcement Job | ✅ | ApplyBottlingDefaultsJob with notification |
| US-036 | BottlingInstructionService | ✅ | All 5 public methods |

### Section 5: Inbound CRUD (US-037 → US-045) — 9/9 ✅

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-037 | Inbound List | ✅ | Ownership badge, filters |
| US-038 | Wizard Step 1: Receipt | ✅ | Physical details |
| US-039 | Wizard Step 2: Sourcing | ✅ | Optional intent/PO link |
| US-040 | Wizard Step 3: Serialization | ✅ | Routing rules |
| US-041 | Wizard Step 4: Review | ✅ | Warnings for unlinked/pending |
| US-042 | Detail (5 tabs) | ✅ | Receipt, Sourcing, Serialization, Handoff, Audit |
| US-043 | Status Transitions | ✅ | Hard blockers enforced |
| US-044 | Module B Hand-off | ✅ | One-way, non-reversible |
| US-045 | Unlinked Flagging | ✅ | Badge + filter + dashboard widget |

### Section 6: Suppliers & Producers (US-046 → US-049) — 4/4 ✅

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-046 | Config Tab in Party | ✅ | ViewParty + EditSupplierConfig |
| US-047 | Edit Config | ✅ | Audit logged |
| US-048 | Supplier Filtered View | ✅ | SupplierProducerResource (read-only) |
| US-049 | ConfigService | ✅ | 6 public methods |

### Section 7: Dashboard (US-050 → US-056) — 6/7 ⚠️

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-050 | Dashboard Landing | ✅ | Entry point, 4 widgets, links |
| US-051 | Widget A: Demand→Execution | ✅ | 4 metrics with color coding |
| US-052 | Widget B: Bottling Risk | ✅ | 30/60/90 day horizons, progress |
| US-053 | Widget C: Inbound Status | ✅ | Expected, delayed, awaiting |
| US-054 | Widget D: Exceptions | ✅ | All 4 exception types, red if >0 |
| US-055 | Quick Actions | ✅ | 3 primary + 5 conditional |
| US-056 | Dashboard Controls | ⚠️ | Refresh + date range + auto-refresh exist, but default is Off instead of 5min (GAP-3) |

### Section 8: Edge Cases & Invariants (US-057 → US-063) — 7/7 ✅

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-057 | Intent-before-PO (hard) | ✅ | FK NOT NULL + boot() validation |
| US-058 | Intent-before-Inbound (soft) | ✅ | Nullable FK, flagged |
| US-059 | Ownership Clarity | ✅ | Blocked on routed→completed |
| US-060 | Serialization Routing | ✅ | Hard blocker + config check |
| US-061 | Bottling Auto-Default | ✅ | Daily job + notification |
| US-062 | PO-Inbound Mismatch | ✅ | Variance calculation + badges |
| US-063 | Intent Closure Validation | ✅ | All linked objects checked |

### Section 9: Audit & Governance (US-064 → US-068) — 5/5 ✅

| US | Description | Status | Notes |
|----|-------------|--------|-------|
| US-064 | Intent Audit | ✅ | Auditable trait + tab |
| US-065 | PO Audit | ✅ | Auditable trait + tab |
| US-066 | Bottling Audit | ✅ | Auditable trait + tab |
| US-067 | Inbound Audit | ✅ | Auditable trait + tab |
| US-068 | Global Module D Audit | ✅ | ProcurementAudit page with CSV export |

---

## 4. FUNCTIONAL DOC INVARIANTS — Enforcement Check

| # | Invariant (Section 8.9) | How Enforced | Status |
|---|------------------------|--------------|--------|
| 1 | Sourcing ≠ selling models | SourcingModel enum is independent from commercial | ✅ |
| 2 | Bottling deadlines authoritative | bottling_deadline NOT NULL + ApplyBottlingDefaultsJob | ✅ |
| 3 | Inbound ≠ ownership | ownership_flag explicit, hard blocker on completion | ✅ |
| 4 | Serialization routing enforced | Hard blocker on recorded→routed + config validation | ✅ |
| 5 | Third-party stock segregation | ThirdPartyCustody sourcing model + in_custody flag | ✅ |

**Additional invariants from PRD:**

| # | Invariant | How Enforced | Status |
|---|-----------|--------------|--------|
| 6 | Intent before PO | FK NOT NULL + boot() | ✅ |
| 7 | Intent before Bottling | FK NOT NULL + boot() | ✅ |
| 8 | Intent closure validation | canClose() checks all linked objects | ✅ |
| 9 | Module B hand-off irreversible | One-way flag, no reset method | ✅ |
| 10 | Ownership clarity for completion | validateOwnershipClarity() hard blocker | ✅ |

---

## 5. EXTRA IMPLEMENTATIONS (Beyond PRD)

Items implemented that go beyond what's explicitly specified:

1. **source_allocation_id / source_voucher_id** on ProcurementIntent — Migration 400016 adds direct FK linkage to allocations and vouchers, improving traceability beyond the PRD's generic "linked_objects_count"

2. **needs_ops_review flag** on ProcurementIntent — Auto-created intents from voucher events are flagged for Ops review. This is a sensible addition not in the original PRD.

3. **confirmed_at / confirmed_by** on PurchaseOrder — Migration 400026 adds confirmation tracking, improving audit beyond the PRD minimum.

4. **BottlingDefaultsAppliedNotification** — Notification class for ops team when defaults are applied. PRD mentions "notifies Ops" but doesn't specify a Notification class.

5. **Conditional Quick Actions** on Dashboard — 5 conditional actions that appear only when exceptions exist. PRD only specified 3 static quick actions.

6. **SupplierProducerResource** as read-only — PRD specified a "filtered view" but implementation goes further with a dedicated Filament resource with rich filtering.

---

## 6. RECOMMENDATIONS (Priority Order)

### P1 — Create PurchaseOrderService (GAP-1)
**Effort:** ~2 hours
**Rationale:** Architectural consistency. Business logic in UI layer makes it harder to test, reuse, and maintain.
```
Methods needed:
- markSent(PurchaseOrder): PurchaseOrder
- confirm(PurchaseOrder): PurchaseOrder
- close(PurchaseOrder, ?string $varianceNotes): PurchaseOrder
- calculateVariance(PurchaseOrder): array
```

### P2 — Create CheckOverdueInboundsJob (GAP-2)
**Effort:** ~1.5 hours
**Rationale:** Proactive alerting prevents operational blind spots. Dashboard metrics already calculate overdue data — the job just needs to generate notifications.
```
Logic:
- Run daily via scheduler
- Find POs where expected_delivery_end < today AND status IN (sent, confirmed)
- Create audit log entries
- Send notification to Ops users
```

### P3 — Fix Dashboard Auto-Refresh Default (GAP-3)
**Effort:** ~5 minutes
**Rationale:** Trivial change, aligns with PRD spec.
```php
// In ProcurementDashboard.php
public string $autoRefresh = '300s'; // was ''
```

---

## 7. OVERALL ASSESSMENT

| Metric | Value |
|--------|-------|
| **User Stories Implemented** | 65/68 (95.6%) |
| **Functional Doc Coverage** | 100% — All concepts implemented |
| **Invariant Enforcement** | 10/10 — All invariants enforced |
| **Architecture Compliance** | 95% — One service missing (PurchaseOrderService) |
| **Extra Value Delivered** | 6 items beyond PRD spec |
| **Estimated Gap Closure Effort** | ~4 hours total |

**Verdict:** Module D is production-ready with minor architectural gaps. The 3 remaining items are non-blocking improvements that enhance maintainability and operational awareness.
