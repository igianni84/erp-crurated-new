# **Module B – Inventory, Serialization & Provenance**

**Admin Panel: UX/UI Narrative Walkthrough (LOCKED)**

## **1\. Entry point & mental model**

### **Entry point**

When the user opens the ERP Admin Panel, **Inventory** appears as a primary navigation section alongside Allocations, Commercial, Fulfillment, etc.

Clicking **Inventory** lands the user on the **Inventory Overview**.

### **Primary users**

This module is used primarily by:

* Warehouse Operations

* Supply / Logistics

* Inventory Control

* Compliance & Audit

* Admins

* Finance (read-only)

### **Mental model reinforced by the UI**

From the first screen, the UI reinforces that:

* Inventory is **physical**, not commercial

* Everything here represents **what exists in the real world**

* Objects are **bottles, cases, locations, movements**, not SKUs or offers

No pricing, customers, or vouchers are visible anywhere in Module B.

---

## **2\. Inventory Overview (Landing Screen)**

### **Purpose**

The Inventory Overview is a **control tower**, not a transaction screen.

It answers at a glance:

* Where stock is

* In what state (serialized / unserialized / committed / free)

* Where exceptions exist

### **Key components**

* **Global inventory KPIs**

  * Total bottles (serialized)

  * Unserialized inbound quantities

  * Bottles by state (stored / reserved / shipped)

  * Committed vs free quantities (by allocation lineage)

* **Inventory by location**

  * Warehouse / consignee breakdown

* **Alerts & exceptions**

  * Serialization pending

  * Committed inventory at risk

  * WMS sync errors

### **Navigation from here**

Primary action paths:

* View **Inbound Batches**

* View **Bottles**

* View **Cases**

* View **Locations**

* View **Movements**

---

## **3\. Locations & WMS Integration**

### **Inventory → Locations**

This tab lists all physical locations known to the system.

#### **Location list**

Each row shows:

* Location name

* Location type (main WH / satellite / consignee / third party)

* Country

* Serialization authorized (yes / no)

* Linked WMS (if any)

* Current stock summary

#### **Location detail page**

Clicking a location opens a **Location Detail** view with tabs:

##### **Tabs**

1. **Overview**

   * Stock summary

   * Serialized vs non-serialized quantities

   * Ownership and custody context

2. **Inventory**

   * Bottles currently stored here

   * Cases stored here

3. **Inbound / Outbound**

   * Recent receipts (from WMS)

   * Transfers in / out

4. **WMS Status**

   * Connection status

   * Last sync timestamp

   * Error logs (read-only)

### **WMS interaction principle (made explicit in UI)**

* WMS is **event-driven**

* ERP is **authoritative**

* All WMS messages appear as:

  * inbound events

  * movement confirmations

  * serialization confirmations

No direct editing of quantities received from WMS is allowed without an explicit exception flow.

---

## **4\. Inbound Batches**

### **Inventory → Inbound Batches**

This is the **entry point for physical reality entering the system**.

### **Inbound Batch List**

Each batch shows:

* inbound\_batch\_id

* source (producer / supplier / transfer)

* product reference

* quantity & packaging

* receiving location

* received date

* serialization status (not started / in progress / completed)

* ownership flag

### **Creating inbound batches**

Inbound batches are **normally created automatically** from WMS receipt events.

Manual creation is:

* restricted

* permission-gated

* fully audited

### **Inbound Batch Detail**

Tabs inside an inbound batch:

1. **Summary**

   * Source and sourcing context

   * Allocation lineage references

   * Ownership

2. **Quantities**

   * Received quantities

   * Remaining unserialized quantities

3. **Serialization**

   * Eligible for serialization (yes/no)

   * Serialization history

4. **Linked Physical Objects**

   * Serialized bottles created from this batch

   * Cases created from this batch

5. **Audit Log**

   * WMS events

   * Operator actions

Inbound batches never show customers, vouchers, or pricing.

---

## **5\. Serialization Flow**

### **Inventory → Serialization Queue**

This is an **operational workspace**, used only by authorized locations.

#### **Serialization Queue**

Shows inbound batches and quantities:

* eligible for serialization

* pending serialization

* partially serialized

### **Serialization action**

When the operator starts a serialization event:

* the system locks the selected quantity

* serial numbers are generated

* physical labels are applied

* serialized bottle records are created

### **Serialization confirmation**

Once completed:

* serialized bottles immediately appear in:

  * Bottle List

  * Location Inventory

* inbound batch quantities are reduced

* provenance NFT minting is triggered asynchronously

Serialization is:

* irreversible

* fully audited

* location-restricted

---

## **6\. Bottle Registry (Serialized Bottles)**

### **Inventory → Bottles**

This is the **canonical registry of all physical bottles**.

### **Bottle List**

Each row represents **one physical bottle**:

* serial number

* wine \+ format

* allocation lineage

* current location

* custody holder

* current state (stored / reserved / shipped)

Advanced filters:

* by allocation lineage

* by location

* by state

* by ownership

### **Bottle Detail Page**

This is a read-heavy, audit-grade page.

#### **Tabs**

1. **Overview**

   * Identity and physical attributes

   * Allocation lineage (immutable)

2. **Location & Custody**

   * Current location

   * Custody holder

3. **Provenance**

   * Inbound event

   * Transfers

   * NFT reference

4. **Movements**

   * Full movement history

5. **Fulfillment Status (read-only)**

   * Reserved / shipped (fed from Module C)

   * No customer identity shown

No bottle can ever be edited or merged.

---

## **7\. Cases & Containers**

### **Inventory → Cases**

Cases are treated as **containers**, never as inventory replacements.

### **Case List**

Shows:

* case\_id

* configuration

* is\_original / is\_breakable

* integrity status

* location

### **Case Detail**

Tabs:

1. **Summary**

2. **Contained Bottles**

3. **Integrity & Handling**

4. **Movements**

Breaking a case:

* requires an explicit action

* updates integrity status

* never deletes bottle identities

---

## **8\. Inventory Movements**

### **Inventory → Movements**

This is a **ledger of physical events**, not a planning tool.

### **Movement types**

* Internal transfer

* Consignment placement

* Return

* Event consumption

### **Movement Detail**

Each movement shows:

* source & destination

* affected bottle IDs

* custody change

* triggering system (WMS / ERP)

* timestamp

Movements never affect:

* allocations

* vouchers

* commercial availability

---

## **9\. Event Consumption Flow**

### **Inventory → Event Consumption**

This is a dedicated flow to avoid contamination with fulfillment.

Flow:

1. Operator selects location and event

2. Selects bottles or cases (owned stock only)

3. Confirms consumption reason \= EVENT\_CONSUMPTION

4. Bottles are marked as consumed at opening

Effects:

* physical inventory reduced

* cases marked broken if applicable

* immutable consumption record created

Consumed bottles:

* never reappear

* never bind to vouchers

* remain visible for audit

---

## **10\. Cross-module interactions (visible but constrained)**

From Module B:

* Module A is never referenced directly

* Module C appears only as:

  * read-only reservation state

* Module D appears as sourcing context

* Module E consumes shipment events downstream

The UI deliberately prevents:

* early bottle assignment

* commercial overrides

* silent substitutions

---

## **11\. UX invariants enforced everywhere**

The Admin Panel enforces the following visually and structurally:

* Bottles appear only after serialization

* Allocation lineage is immutable and always visible

* Physical movements are append-only

* WMS events are clearly distinguished from operator actions

* Commercial abstractions never leak into inventory views

## **12\. Edge cases & exception handling**

**(Inventory, Serialization & Provenance)**

This section defines **explicit, limited exception flows**.  
 Anything not listed here is intentionally unsupported.

The Admin Panel must make exceptions:

* rare,

* visible,

* auditable,

* uncomfortable to execute.

---

## **12.1 WMS vs ERP quantity mismatch**

### **Scenario**

The WMS reports:

* fewer bottles received than expected, or

* more bottles than the inbound batch specifies.

### **UX handling**

* The inbound batch is created in **“Discrepancy”** state.

* Inventory Overview shows a **red alert badge**.

* Serialization is **blocked** until resolution.

### **Resolution flow**

Inbound Batch → **Discrepancy Resolution** tab:

* Side-by-side view:

  * expected quantity

  * WMS reported quantity

* Operator must:

  * select a resolution reason (damage / shortage / overage)

  * attach evidence (WMS note, photo, document)

### **System behavior**

* Adjustments create:

  * an immutable correction event

  * a delta record (never overwriting original values)

* Full audit trail is preserved.

---

## **12.2 Serialization attempted at unauthorized location**

### **Scenario**

A warehouse without serialization authorization attempts to serialize.

### **UX handling**

* Serialization action is **not visible** in the UI.

* If triggered via WMS:

  * event is rejected

  * logged as a **blocked system event**

### **UX implication**

* Location detail page shows:

  * “Serialization not authorized”

  * last blocked attempt (if any)

No override exists in the Admin Panel.

---

## **12.3 Partial serialization of inbound batches**

### **Scenario**

Only part of an inbound batch is serialized.

### **UX handling**

Inbound Batch detail shows:

* remaining unserialized quantity

* serialized bottles created so far

### **UX implication**

* Inventory Overview highlights:

  * “Inbound wine pending serialization”

* Serialization Queue keeps the batch visible until completed.

This is a **normal state**, not an exception.

---

## **12.4 Broken bottles (warehouse damage)**

### **Scenario**

A serialized bottle breaks or becomes unsellable in storage.

### **UX flow**

Inventory → Bottles → Bottle Detail → **Mark as Damaged**

Operator must:

* confirm physical destruction

* select reason (breakage / leakage / contamination)

* attach optional evidence

### **System behavior**

* Bottle state changes to **DESTROYED**

* Bottle remains visible for audit

* Inventory quantity is reduced

* Provenance remains intact

### **UX implication**

* Destroyed bottles:

  * cannot be selected by Module C

  * remain queryable in reports

* No deletion is ever allowed.

---

## **12.5 Case broken outside fulfillment**

### **Scenario**

A case is opened for inspection, sampling, or events.

### **UX flow**

Inventory → Case → **Break Case**

Effects:

* integrity\_status → BROKEN

* bottles remain individually tracked

* case no longer eligible for case-based handling

### **UX implication**

* Case disappears from “intact case” filters

* Bottles immediately appear as loose stock

---

## **12.6 Event consumption mistakenly attempted on committed inventory**

### **Scenario**

Operator attempts to consume bottles reserved for vouchers.

### **UX handling**

* Selection step blocks committed bottles.

* Inline warning:

   “This bottle is reserved for customer fulfillment.”

### **Override (exceptional)**

If user has special permission:

* requires explicit justification

* creates an **Inventory Exception Record**

* flagged for finance & ops review

This is intentionally painful.

---

## **12.7 Consignment bottle not returned / missing**

### **Scenario**

Bottle sent to consignee cannot be accounted for.

### **UX flow**

Inventory → Movements → Consignment → **Mark as Missing**

Operator must:

* select reason

* record last known custody

* attach agreement reference

### **System behavior**

* Bottle state → MISSING

* Inventory reduced

* Bottle locked from fulfillment

### **UX implication**

* Missing bottles remain visible forever

* Used in loss & compliance reporting

---

## **12.8 WMS sends duplicate movement events**

### **Scenario**

WMS retries a movement event already processed.

### **UX handling**

* ERP detects duplicate event ID.

* Event is ignored.

* Logged in Movement Audit Log.

### **UX implication**

* No double movement

* Operators see clean, deduplicated history

---

## **12.9 Attempt to substitute bottles across allocation lineage**

### **Scenario**

Ops attempts to use a “similar” bottle from another allocation.

### **UX handling**

* System blocks the action.

* Error message:

   “Allocation lineage mismatch. Substitution not allowed.”

### **UX implication**

* Allocation lineage is always shown prominently in:

  * bottle list

  * bottle picker

* No hidden overrides exist in Module B.

---

## **12.10 Late discovery of mis-serialized bottle**

### **Scenario**

Bottle serialized with incorrect format or wine variant.

### **UX handling**

* Bottle is flagged as **MIS-SERIALIZED**

* Requires admin-only correction flow:

  * original record locked

  * corrective record created

  * both linked

### **UX implication**

* Corrections are additive

* Original error remains visible

* Provenance integrity preserved

---

## **13\. UX principles enforced by edge cases**

### **13.1 Exceptions are visible**

* Alerts surface at Overview level

* Exception states are never hidden in detail views

### **13.2 Exceptions are irreversible**

* No silent fixes

* No delete buttons

* Everything leaves a trail

### **13.3 Exceptions are role-gated**

* Warehouse ops can report

* Admins can resolve

* Finance sees everything (read-only)

---

## **14\. Why this matters for the Admin Panel**

By explicitly designing for edge cases, the UI:

* prevents informal “workarounds”

* protects allocation integrity

* preserves provenance trust

* reduces ops/tech escalations

Module B becomes:

a **ledger of physical truth**, not a suggestion.

