# **Module A – Vouchers**

Admin Panel: UX/UI Narrative Walkthrough (LOCKED)

---

## **Entry point: Vouchers module**

When the user opens the admin panel, they see **Vouchers** as a main navigation section, separate from **Allocations**.

Clicking **Vouchers → Voucher List** brings the user to the operational entry point of customer entitlements.

This section is used primarily by:

* Operations

* Customer Support

* Commercial Ops (read-only)

* Fulfillment Planning (read-only)

* Finance / Audit (read-only)

* Admins

The purpose of this section is to:

* represent **authoritative customer redemption rights**,

* make voucher **lineage, status, and holder explicit**,

* support post-sale lifecycle events (gifting, trading suspension, redemption),

* preserve strict separation between **entitlements** and **physical inventory**,

* provide a complete audit trail for customer-facing and operational inquiries.

The Vouchers module is **not** used to:

* manage supply,

* adjust allocation capacity,

* assign physical bottles,

* fix commercial errors.

Those concerns are intentionally handled elsewhere.

---

## **Voucher List**

The Voucher List shows vouchers in a global tabular view.

This list functions as the **entitlement ledger of the system**.

Each row represents **exactly one voucher**, corresponding to:

one bottle or one bottle-equivalent entitlement.

There is no aggregation and no quantity column by design.

### **Default behavior**

* All active vouchers are shown by default

* Redeemed and cancelled vouchers may be hidden or shown via filters

### **Each row immediately communicates:**

* Voucher ID (first-class identifier)

* Customer (current holder)

* Bottle SKU (wine, vintage, format)

* Sellable SKU reference (what was purchased)

* Allocation ID (lineage anchor)

* Lifecycle state (Issued, Locked, Redeemed, Cancelled)

* Behavioral flags (Tradable, Giftable, Suspended)

* Created at

### **From this screen, the user can:**

* search by voucher ID, customer, wine name, Bottle SKU, or allocation ID

* filter by lifecycle state, flags, allocation, sellable SKU

* quickly identify vouchers that are:

  * locked for fulfillment

  * suspended due to external trading

  * already redeemed or cancelled

### **Key actions from Voucher List**

* Open voucher detail

* Export (role-based, read-only)

There is **no Create Voucher action**.  
 Vouchers are created only as a result of explicit sale confirmation via commercial flows.

---

## **Voucher Detail**

Opening a voucher brings the user into a detailed, read-heavy view.

This screen is designed to act as the **single source of truth** for:

* what the customer owns,

* where it came from,

* what can and cannot be done with it.

The page is intentionally conservative:

* most fields are read-only,

* actions are limited and role-gated,

* invariants are explicitly reinforced.

---

### **Header: Voucher identity & status**

At the top of the page, the user sees:

* Voucher ID

* Current customer (entitlement holder)

* Lifecycle state (Issued / Locked / Redeemed / Cancelled)

Status is visually prominent.

If the voucher is:

* **Locked** → a banner explains it is reserved for fulfillment

* **Suspended** → a banner explains it is unavailable due to external trading

This prevents confusion during support or ops investigations.

---

### **Section 1: What was sold (commercial context)**

This section answers:

“What did the customer buy?”

Displayed fields include:

* Sellable SKU

* Bottle SKU (vintage \+ format)

* Quantity (fixed to 1 bottle or 1 bottle-equivalent)

* Case Entitlement (if applicable):

  * Entitlement ID

  * Status: INTACT or BROKEN

This section is descriptive only.  
 No commercial mutation is possible from the Voucher module.

---

### **Section 2: Allocation lineage (authoritative)**

This is a **non-negotiable section** and is always visible.

It shows:

* Allocation ID (linked, read-only)

* Allocation source type

* Allocation constraints snapshot (read-only)

* Serialization requirement

* Composition constraints (if any)

Clear system messaging reinforces:

This voucher must be fulfilled within its original allocation lineage.  
 Allocation lineage can never be modified or substituted.

This section explains downstream behavior without requiring tribal knowledge.

---

### **Section 3: Lifecycle state**

This section shows:

* Current lifecycle state

* A read-only state diagram indicating:

  * allowed transitions

  * terminal states

Rules reinforced in the UI:

* Lifecycle states are **not manually edited** by admins

* State transitions occur only through:

  * fulfillment operations,

  * transfer acceptance,

  * system callbacks (e.g. trading platforms)

Admins can observe but not override lifecycle correctness.

---

### **Section 4: Behavioral permissions (flags)**

This section shows and, where allowed, controls:

* Tradable

* Giftable

* Suspended

Key principles communicated inline:

* Flags control **what the customer may do**, not what they own

* Flags are independent of lifecycle state

Actions:

* Flags may be toggled only if the voucher is in a compatible lifecycle state

* Each change requires confirmation and generates an audit event

Suspension is explicitly described as:

a temporary operational lock, not a lifecycle transition.

---

### **Section 5: Transfer & trading context (if applicable)**

When relevant, this section displays:

* Current transfer status (pending / accepted)

* External trading reference (if suspended)

* Timestamps and counterparties

Rules reinforced:

* Transfers do not create new vouchers

* Transfers do not consume allocation

* No financial event occurs at transfer time

This clarifies frequent customer and support questions.

---

### **Section 6: Event history (audit)**

The bottom section shows a complete, append-only event log:

* Voucher issued (sale reference)

* Transfers initiated / accepted

* Suspended / reactivated

* Locked for fulfillment

* Redeemed or cancelled

Events are immutable and time-ordered.

This section is critical for:

* customer support

* dispute resolution

* internal audit

---

## **Relationship to Allocations (explicit UX boundary)**

From the Vouchers module:

* Allocations are visible and linkable

* Allocations are never editable

From the Allocations module:

* Voucher counts and lineage are visible

* Voucher operations are not performed

This enforces the system invariant:

Allocations define what can be sold.  
 Vouchers define what is owed.

---

## **Mental model summary (LOCKED)**

* Voucher \= authoritative customer entitlement

* One voucher always represents exactly one bottle or one bottle-equivalent

* Vouchers are created only after explicit sale confirmation

* Vouchers consume allocation and retain immutable allocation lineage

* Vouchers never reference physical bottles prior to fulfillment

* Lifecycle states reflect operational reality and are system-driven

* Behavioral flags control customer permissions, not ownership

* Transfers change the holder, not the entitlement

* Binding to physical inventory is always late (except explicit personalised bottling)

* The Vouchers section is entitlement- and customer-focused; supply lives under Allocations

