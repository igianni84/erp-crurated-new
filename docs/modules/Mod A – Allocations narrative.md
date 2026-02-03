# **Module A – Allocations**

## **Admin Panel: UX/UI Narrative Walkthrough (LOCKED)**

---

## **Entry point: Allocations module**

When the user opens the admin panel, they see **Allocations** as a main navigation section, separate from **Vouchers**.

Clicking **Allocations → Allocation List** brings the user to the operational entry point of the module.

This section is used primarily by:

* Operations

* Commercial Ops

* Supply / Procurement

* Finance (read-only)

* Admins

The purpose of this section is to:

* define **sellable capacity**, independently of physical inventory,

* enforce **authoritative selling constraints** (channel, geography, customer eligibility, composition),

* prevent overselling via **reservations and controlled consumption**,

* preserve **allocation lineage**, which is mandatory for late binding at fulfillment.

---

## **Allocation List**

The Allocation List shows allocations in a tabular view.  
 Closed allocations are hidden by default.

Each row represents **one Allocation at Bottle SKU level** (wine \+ vintage \+ format).  
 If the same wine exists in multiple formats or under multiple contracts, it will appear as multiple rows.

Each row immediately communicates:

* Allocation ID

* Bottle SKU (wine, vintage, format)

* Supply form (Bottled / Liquid)

* Source type (producer allocation, owned stock, passive consignment, third-party custody)

* Status (Draft, Active, Exhausted, Closed)

* Total quantity (bottles or bottle-equivalents)

* Sold quantity

* Remaining quantity

* Expected availability window

* Constraint summary (channels / geographies / customer types)

* Serialization mode (serial vs fungible exception)

* Last updated date

From this screen, the user can:

* search by wine name, producer, Bottle SKU, or allocation ID

* filter by status, source type, supply form, constraints, availability window

* quickly identify allocations that are nearing exhaustion or blocked from selling

### **Key actions from Allocation List**

* **Create allocation** (primary CTA)

* Open allocation detail

* Export or limited bulk actions (role-based)

Editing constraints or quantities is intentionally **not possible from the list**, to avoid mistakes.

---

## **Create Allocation flow**

Clicking **Create allocation** starts a guided creation flow that avoids empty or ambiguous forms.

### **Step 1: Select Bottle SKU**

The user selects:

* Wine \+ vintage

* Format (Bottle SKU)

This enforces the invariant:

Allocation always happens at Bottle SKU level.

No sellable SKU or packaging concepts appear at this stage.

---

### **Step 2: Define source & capacity**

The user defines:

* Source type (producer, owned, consignment, third-party)

* **Supply form**

  * Bottled (physical bottles exist or will exist in this format)

  * Liquid (pre-bottling; measured in bottle-equivalents)

* Total quantity (bottles or bottle-equivalents)

* Expected availability window

* Serialization requirement (yes / no)

Inline guidance explains the operational implications of:

* bottled vs liquid supply,

* serialization timing,

* downstream effects on procurement and fulfillment.

---

### **Step 3: Define commercial constraints (authoritative)**

The user defines **who and where this allocation may be sold to**:

* Allowed channels (e.g. B2C, Private Sales, Wholesale)

* Allowed geographies

* **Allowed customer types**

Customer types include, for example:

* Retail customer

* Trade / On-trade

* Private client

* **Club member (specific club)**

* Internal / staff

Clubs are explicitly modeled as **customer eligibility constraints**, not channels.

Constraints are clearly marked as:

Authoritative. Module S must enforce them and may not override them.

---

### **Step 4: Advanced constraints (optional / conditional)**

This section is collapsed by default and shown only when relevant.

It includes:

* Composition constraints (atomic groupings such as vertical cases)

* Fungibility exceptions (case-managed exception paths)

#### **Liquid-only constraints**

If **Supply form \= Liquid**, additional fields appear:

* Allowed bottling formats

* Allowed case configurations (if applicable)

* **Bottling confirmation deadline**

The bottling confirmation deadline defines the latest date by which bottling options must be finalized before procurement and serialization planning.

---

### **Step 5: Review & Activate**

Before activation, the system shows a **read-only summary** of:

* capacity

* supply form

* constraints

* warnings (informational only)

Allocations are created in **Draft** by default and become sellable only when explicitly activated.

Draft allocations:

* cannot be consumed,

* cannot issue vouchers,

* are not considered by sellability checks.

---

## **Allocation Detail**

Opening an allocation brings the user into a sub-navigation with tabs related to that allocation.

The detail view is designed to ensure:

* governance (constraints and lineage do not drift),

* operational clarity (capacity and reservations),

* traceability (voucher lineage and audit).

---

## **Tab 1: Overview (control panel)**

Read-only summary showing:

* Allocation ID and status

* Bottle SKU reference

* Supply form (Bottled / Liquid)

* Source type

* Total / sold / remaining quantities

* Availability window

* Constraint summary

* Serialization and fungibility mode

* **Allocation lineage rule (explicit):**

   Vouchers issued from this allocation must be fulfilled from the same allocation lineage.

Primary actions (role-based):

* Activate (Draft only)

* Close allocation (where allowed)

* Navigate to issued vouchers (read-only linkage)

---

## **Tab 2: Constraints (authoritative rules)**

Shows the complete constraint set:

* Channels

* Geographies

* Customer types (including club eligibility)

* Composition constraints

* Liquid conditioning rules (if applicable)

* Serialization and fungibility rules

Rules:

* Constraints are editable only while the allocation is **Draft**

* Once **Active**, constraints are strictly read-only

If commercial intent changes, the UI explicitly guides users to:

Close allocation → create a new allocation with updated constraints

This prevents silent relaxation and preserves contractual correctness.

---

## **Tab 3: Capacity & Consumption**

This tab explains how capacity is being used:

* total, sold, and remaining quantities

* consumption breakdown by:

  * sellable SKU

  * channel

  * time

This view supports operational planning and investigation, without exposing customer-level actions.

---

## **Tab 4: Reservations (oversell protection)**

Shows all temporary reservations placed against the allocation:

* Reservation ID

* Context (checkout / negotiation / manual hold)

* Quantity

* Created at

* Expires at

* Status (active / expired / cancelled / converted)

Actions:

* Cancel active reservations (role-based)

* Navigate to reservation context (order or deal)

Clear invariant messaging:

Reservations are temporary holds. They do not consume allocation and do not create entitlements.

---

## **Tab 5: Vouchers (read-only lineage view)**

Shows vouchers issued from this allocation for traceability:

* Voucher ID

* Customer

* Lifecycle state (issued / locked / redeemed / cancelled)

* Case entitlement membership (if any)

* Created at

This tab is strictly read-only.  
 All voucher operations are performed in the **Vouchers** main navigation section.

---

## **Tab 6: Audit**

Read-only activity log including:

* allocation creation

* draft-stage edits

* activation

* closure

* reservations created / expired / cancelled

* system events (e.g. exhaustion)

This supports audits, investigations, and internal trust.

---

## **Mental model summary (LOCKED)**

* **Allocation \= sellable supply commitment at Bottle SKU level**

* Supply form (bottled vs liquid) is a property of the allocation

* Allocations define **what can be sold**, under which constraints

* Constraints are authoritative and must not drift silently

* Clubs are modeled as customer eligibility constraints

* Liquid allocations require a bottling confirmation deadline

* Vouchers consume allocations and retain immutable **allocation lineage**

* Late binding at fulfillment is allowed only within lineage-safe pools

* The Allocations section is supply- and control-focused; customer entitlements live under Vouchers

