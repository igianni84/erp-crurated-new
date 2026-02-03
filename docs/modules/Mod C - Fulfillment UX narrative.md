# **Module C — Fulfillment & Shipping**

**Admin Panel: UX/UI Narrative Walkthrough (LOCKED)**

## **Entry point: Fulfillment module**

When the user opens the ERP Admin Panel, they see **Fulfillment** as a top-level navigation item.

Clicking **Fulfillment** opens the operational execution layer where customer delivery requests are managed and executed.

This module is primarily used by:

* Operations / Fulfillment teams

* Warehouse coordinators

* Customer Support (read-only / exception handling)

* Finance (read-only, shipment confirmation & audit)

* Admins

The purpose of this module is to:

* convert **customer shipping requests** into executable Shipping Orders

* orchestrate **late binding** between vouchers and physical inventory

* authorize and track **shipment execution** via WMS

* trigger **voucher redemption, ownership transfer, and provenance updates**

This module does **not** decide commercial availability, pricing, or inventory existence.

---

## **High-level navigation structure**

**Fulfillment**

* Shipping Orders

* Shipments

* Exceptions & Holds

* Configuration (admin-only, limited)

---

## **1\. Shipping Orders**

### **Shipping Orders → List**

This is the primary operational workspace.

The list shows all Shipping Orders (SO) across their lifecycle.

**Lo-fi table columns**

* SO ID

* Customer

* Destination country

* **Vouchers**

* Source warehouse

* Status (draft / planned / picking / shipped / completed)

* Created date

* Requested ship date

Status color coding is prominent, as SO status drives all downstream actions.

---

### **Shipping Orders → SO Detail View**

Clicking an SO opens a **tabbed detail view**.

This screen represents:

* the customer’s shipping request, and

* the ERP’s authorization to execute fulfillment.

#### **Header (always visible)**

* SO ID

* Status

* Customer name (link to customer profile)

* Source warehouse (resolved or selected)

---

### **Tab 1 — Overview**

This tab answers: *“What is being shipped, for whom, and from where?”*

**Sections**

* Customer & destination address

* Shipping method (carrier, speed, incoterms if applicable)

* Packaging preferences (cases / loose / preserve cases when possible)

**Voucher summary**

* List of vouchers included

* Voucher state (eligible / locked / redeemed)

* Allocation lineage per voucher

At this stage:

* vouchers are **not redeemed**

* bottles are **not assigned**

---

### **Tab 2 — Vouchers & Eligibility**

This tab is critical before planning.

For each voucher, the system displays:

* voucher ID

* wine / SKU

* allocation lineage

* eligibility status

**System validations shown explicitly**

* cancelled / redeemed vouchers are blocked

* vouchers locked by other processes are excluded

* gifted or externally traded vouchers are marked ineligible

The operator cannot override eligibility here.  
 If a voucher is ineligible, it must be resolved upstream.

---

### **Tab 3 — Planning**

This tab is where the SO transitions from intent to execution readiness.

**Key actions**

* Resolve or confirm source warehouse

* Validate stock availability (via Module B)

* Prepare SO for WMS execution

The ERP:

* requests **eligible inventory** from Module B using **allocation lineage as a hard constraint**

* does *not* assign bottles yet

If eligible inventory is missing:

* the SO cannot proceed

* the issue is surfaced as a **supply exception**, not resolved by substitution

Once validated, the operator moves the SO to **Planned**.

### **Packaging intent: intrinsic cases (OWC / OC)**

Within the **Planning** tab, the Admin Panel makes a clear distinction between:

* shipment of *loose bottles*, and

* shipment of bottles **preserved in an intrinsic case** (e.g. 6 bottles in OWC).

When a Shipping Order includes a request to ship bottles in an intrinsic case:

* the request is displayed as a **packaging intent**, not a different entitlement,

* vouchers remain bottle-based and unbound,

* the system introduces an additional **case integrity constraint** for planning.

#### **Planning behavior in the Admin Panel**

During planning, the ERP evaluates inventory availability with the following constraints:

Mandatory (always):

* allocation lineage match,

* voucher eligibility.

Additional (only if intrinsic case is requested):

* availability of a **fully intact intrinsic case** containing the required number of bottles.

The Planning tab surfaces one of the following states:

* **Intact case available**

   “1 eligible intact 6-bottle OWC available.”  
   The Shipping Order can proceed to Picking.

* **Bottles available, case unavailable**

   “Eligible bottles available, but no intact intrinsic case exists.”  
   The Shipping Order cannot proceed as requested.

* **Insufficient inventory**  
   Standard supply exception.

The system must not automatically downgrade an intrinsic-case request to loose bottles.

---

### **Tab 4 — Picking & Binding (Read-only until picking)**

This tab becomes active once the SO enters **Picking**.

It visualizes **late binding in action**.

**For serialized inventory**

* voucher → bottle serial mapping appears here

* binding happens based on WMS pick confirmation

**For non-serialized inventory**

* voucher → case assignment is shown

* case integrity rules are displayed

If early binding exists (personalised bottling from Module D):

* the system shows the pre-bound serial

* ERP validates integrity but skips selection

Operators cannot manually choose bottles here.  
 They can only **accept or reject WMS feedback**.

---

### **Tab 5 — Audit & Timeline**

A chronological log of:

* SO creation

* planning validation

* WMS messages

* picking confirmation

* shipment execution

This tab exists for:

* audit

* customer support

* dispute resolution

---

## **2\. Shipments**

### **Shipments → List**

This section represents **physical execution events**, not requests.

Each row corresponds to a **Shipment Event**.

**Columns**

* Shipment ID

* SO ID

* Carrier

* Tracking number

* Ship date

* Status

---

### **Shipments → Shipment Detail**

This screen is the **point of no return**.

It records:

* carrier and tracking

* origin and destination

* shipped bottle serials

At this moment:

* vouchers are redeemed

* ownership transfers to the customer

* provenance records are updated

* accounting events are triggered

No edits are allowed after shipment confirmation.

---

## **3\. Exceptions & Holds**

This section aggregates fulfillment blockers.

Examples:

* insufficient eligible inventory

* WMS discrepancies (picked serial not eligible)

* ownership or custody violations

Each exception links back to:

* the SO

* the affected voucher(s)

Resolution paths are informational; fixes happen upstream (Modules A or B).

---

## **4\. Integration with WMS (implicit UX behavior)**

There is **no WMS UI** in the ERP.

Instead:

* SO status changes reflect WMS state

* picked serials are shown once confirmed

* discrepancies surface as blocking alerts

Authority is always clear:

* ERP authorizes

* WMS executes

---

## **Key end-to-end flows**

### **Flow 1 — Standard customer shipment (serialized bottles)**

1. Customer requests delivery (outside ERP)

2. SO is created in **Draft**

3. Vouchers validated (no redemption)

4. Inventory availability confirmed (no binding)

5. SO sent to WMS → status **Picking**

6. WMS picks bottles → late binding occurs

7. Shipment confirmed → vouchers redeemed

8. Ownership and provenance updated

---

### **Flow 2 — Personalized bottling (early bound)**

1. Voucher already bound in Module D

2. SO skips bottle selection

3. ERP validates integrity only

4. Shipment proceeds normally

---

### **Flow 3 — Non-serialized case fulfillment**

1. Voucher bound to case, not bottle

2. Case integrity rules enforced

3. Breaking cases is explicit and auditable

---

## **UX principles enforced in the Admin Panel**

* No shipment without an SO

* No redemption before shipment

* No manual bottle choice

* No cross-allocation substitution

* Full auditability at every step

# **Module C — Fulfillment & Shipping**

## **Edge Cases & UX Implications (LOCKED)**

This section documents exceptional scenarios that may occur during fulfillment and how they are surfaced, constrained, and resolved in the Admin Panel.

The guiding principle is that **exceptions are made visible, not silently corrected**.  
 Resolution typically happens upstream (Modules A, B, or D), not through manual overrides in Module C.

---

## **1\. Voucher becomes ineligible after SO creation**

### **Scenario**

A Shipping Order is created in *Draft* with eligible vouchers, but before it enters Picking:

* a voucher is cancelled,

* transferred,

* gifted,

* locked by another process.

### **System behavior**

* The SO cannot transition to **Planned** or **Picking**.

* The affected voucher is flagged as **Ineligible**.

* No partial silent fulfillment is allowed.

### **UX implications**

* In **Vouchers & Eligibility**, the voucher row is marked in error state.

* A blocking banner appears:

   “One or more vouchers are no longer eligible for fulfillment.”

* Operator actions:

  * remove the voucher from the SO, or

  * abandon the SO.

No override or forced shipment is possible.

---

## **2\. Insufficient eligible inventory at planning time**

### **Scenario**

Vouchers are eligible, but Module B reports:

* no available inventory matching the required allocation lineage.

### **System behavior**

* The SO cannot be planned.

* Late binding is not attempted.

* Substitution across allocations is forbidden.

### **UX implications**

* In **Planning**, stock availability is shown as **0 eligible units**.

* The SO enters a **Supply Exception** state.

* A clear explanation is displayed:

   “No eligible physical inventory available for the required allocation lineage.”

Operators cannot:

* choose another allocation,

* force a shipment,

* reserve future inventory.

---

## **3\. Inventory available at wrong warehouse**

### **Scenario**

Eligible inventory exists, but not at the initially selected warehouse.

### **System behavior**

* The ERP evaluates:

  * internal transfer first, or

  * direct shipment if allowed.

* No bottle binding occurs yet.

### **UX implications**

* In **Planning**, the system suggests:

  * alternate source warehouse, or

  * transfer requirement.

The operator explicitly confirms the fulfillment path.  
 Transfers are executed and recorded in Module B, not in Module C.

---

## **4\. WMS picks an ineligible bottle**

### **Scenario**

The WMS reports a picked bottle that:

* does not match the voucher’s allocation lineage,

* violates ownership or custody constraints,

* breaks case integrity rules.

### **System behavior**

* The ERP rejects the pick confirmation.

* Late binding does not complete.

* Shipment cannot proceed.

### **UX implications**

* In **Picking & Binding**, the picked serial is shown with an error state.

* A discrepancy message is displayed:

   “Picked bottle does not satisfy eligibility constraints.”

Operator options:

* request WMS re-pick,

* cancel the SO.

Manual acceptance is not allowed.

---

## **5\. Bottle breaks during picking or packing**

### **Scenario**

A bottle selected for binding is broken before shipment.

### **System behavior**

* The SO cannot proceed with that binding.

* The broken bottle is removed from eligible inventory by Module B.

* Late binding is retried **within the same allocation lineage only**.

### **UX implications**

* The incident is logged in **Audit & Timeline**.

* The ERP requests a replacement bottle automatically.

* If no replacement exists:

  * the SO enters **Supply Exception**.

No cross-allocation substitution is permitted.

---

## **6\. Case must be broken for partial shipment**

### **Scenario**

A customer ships only part of an intrinsic case.

### **System behavior**

* The case is explicitly broken.

* Case integrity is permanently removed.

* Future rights to ship the case as a whole are lost.

### **UX implications**

* In **Picking & Binding**, the operator sees:

   “This action will permanently break the original case.”

* The decision is:

  * explicit,

  * auditable,

  * irreversible.

Bottle-level provenance remains intact.

---

## **7\. Composite (mixed) case fulfillment**

### **Scenario**

The SO includes a composite sellable SKU (mixed case).

### **System behavior**

* Bottles are selected individually via late binding.

* Case is assembled during pick & pack.

* Composite cases do not create long-term physical constraints.

### **UX implications**

* In **Picking & Binding**, the case is shown as:

  * “Assembled during fulfillment.”

* No intrinsic case preservation warnings are shown.

---

## **8\. Early-bound personalized bottles fail validation**

### **Scenario**

A voucher is early-bound (Module D), but:

* the serialized bottle is missing,

* custody is invalid,

* ownership is incorrect.

### **System behavior**

* Shipment is blocked.

* Late binding is not attempted as a fallback.

### **UX implications**

* In **Picking & Binding**, the bound serial is marked invalid.

* Error message:

   “Pre-bound bottle failed fulfillment validation.”

Resolution must occur in Module D or B.

---

## **9\. Attempted shipment of third-party or consigned stock**

### **Scenario**

A Shipping Order includes stock:

* not owned by Crurated,

* under restricted third-party custody.

### **System behavior**

* Shipment is blocked.

* No voucher redemption occurs.

### **UX implications**

* In **Planning**, ownership constraints are clearly shown.

* The SO cannot move forward.

---

## **10\. Shipment confirmation fails after WMS execution**

### **Scenario**

WMS reports shipment, but:

* ERP validation fails,

* data is incomplete or inconsistent.

### **System behavior**

* Shipment is not confirmed.

* Redemption does not occur.

* The SO remains in an unresolved execution state.

### **UX implications**

* A critical alert is shown in **Shipments**.

* Manual correction requires admin-level intervention.

* Full audit trail is preserved.

---

## **UX invariants reinforced by edge cases**

Across all edge cases, the Admin Panel enforces:

* No silent substitution

* No manual bottle selection

* No early redemption

* No cross-allocation flexibility

* No hiding of failures

Operators are guided to **see, understand, and escalate**, not to override.

