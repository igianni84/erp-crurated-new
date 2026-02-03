# **Module D — Procurement & Inbound**

**Admin Panel: UX/UI Narrative Walkthrough (LOCKED)**

---

## **1\. Purpose of the Module (Operator Mental Model)**

Module D is where **commercial commitments become physical wine**.

It is used to:

* decide *when* and *how* wine is sourced,

* translate allocation \+ voucher demand into procurement actions,

* manage bottling decisions for liquid products,

* orchestrate inbound flows into warehouses,

* prepare wine for serialization and downstream operations.

This module is **execution-oriented**, but **never commercial**.

If Module A answers *“what have we promised?”*  
 Module D answers *“how do we make that wine exist?”*

---

## **2\. Entry Point & Navigation**

### **Main Navigation**

**Admin Panel → Procurement & Inbound (Module D)**

Landing view is a **dashboard-style assurance page**, not a raw list.

Primary sub-sections (left navigation or top tabs):

1. **Overview**

2. **Procurement Intents**

3. **Purchase Orders**

4. **Bottling (Liquid Products)**

5. **Inbound**

6. **Suppliers & Producers** (read-focused, light CRUD)

---

## **3\. Overview Tab (Control Tower View)**

### **Purpose**

Give Ops, Supply, and Finance a **single glance** view of sourcing health.

This is not where actions happen — it’s where **priorities are identified**.

### **Key Panels**

**A. Demand → Execution Knowability**

* Vouchers issued awaiting sourcing

* Allocation-driven procurement pending

* Bottling-required liquid demand

* Inbound overdue vs expected

**B. Bottling Risk**

* Upcoming bottling deadlines (next 30 / 60 / 90 days)

* % of customer preferences collected

* Default bottling fallback count

**C. Inbound Status**

* Expected inbound (next 30 days)

* Delayed inbound

* Inbound awaiting serialization routing

**D. Exceptions**

* Inbound without ownership clarity

* Inbound blocked by missing procurement intent

* Bottling instructions past deadline

Each tile links directly to a **filtered list** in the relevant tab.

---

## **4\. Procurement Intents Tab (Canonical Object)**

### **Purpose**

This is the **heart of Module D**.

A Procurement Intent represents:

“We have decided to source X wine for reason Y.”

Everything else (POs, inbound, bottling) hangs off this.

---

### **Procurement Intent List (Lo-Fi Table)**

Columns:

* Intent ID

* Product (Wine \+ Vintage \+ Format / Liquid)

* Quantity (bottles or bottle-equivalents)

* Trigger Type  
   *(Voucher-driven / Allocation-driven / Strategic / Contractual)*

* Sourcing Model  
   *(Purchase / Passive Consignment / Third-party Custody)*

* Preferred Inbound Location

* Status *(Draft / Approved / Executed / Closed)*

* Linked Objects count (POs, Inbound, Bottling)

Primary actions:

* View

* Approve

* Create PO

* Create Bottling Instruction

* Link Inbound (exceptional)

---

### **Procurement Intent Detail View (Tabs)**

**Tab 1 – Summary**

* Demand source (voucher batch, allocation, manual)

* Rationale (auto \+ operator note)

* Quantities and units

* Sourcing model

* Status & approvals

**Tab 2 – Downstream Execution**

* Linked Purchase Orders

* Linked Bottling Instructions

* Linked Inbound Batches

**Tab 3 – Allocation & Voucher Context (Read-only)**

* Allocation IDs driving this intent

* Voucher count (if voucher-driven)

* No editing allowed here

**Tab 4 – Audit & History**

* Creation reason

* Approvals

* Changes

**Invariant enforced in UX**  
 A Procurement Intent can exist without a PO,  
 but a PO cannot exist without a Procurement Intent.

---

## **5\. Purchase Orders Tab**

### **Purpose**

Manage **contractual sourcing**, not physical arrival.

POs exist **only when ownership or purchase is involved**.

---

### **PO List View**

Columns:

* PO ID

* Supplier / Producer

* Product

* Quantity

* Unit Cost & Currency

* Ownership Transfer (Yes / No)

* Expected Delivery Window

* Status *(Draft / Sent / Confirmed / Closed)*

---

### **PO Detail View**

**Tab 1 – Commercial Terms**

* Supplier

* Product

* Quantity

* Pricing

* Incoterms

* Ownership transfer flag

**Tab 2 – Linked Procurement Intent**

* Read-only link back

* Quantity coverage vs intent

**Tab 3 – Delivery Expectations**

* Expected delivery window

* Destination warehouse

* Serialization routing note (e.g. “France required”)

**Tab 4 – Inbound Matching**

* Inbound batches linked (may be empty)

* Variance flags (qty / timing)

**Tab 5 – Audit**

* Approval trail

* Status changes

---

## **6\. Bottling Tab (Liquid Products)**

### **Purpose**

Handle **post-sale bottling decisions** safely and visibly.

This is one of the most sensitive UX areas in the system.

---

### **Bottling Instructions List**

Columns:

* Bottling Instruction ID

* Wine \+ Vintage

* Bottle-equivalents

* Allowed Formats

* Bottling Deadline

* Customer Preference Status  
   *(Pending / Partial / Complete / Defaulted)*

* Status *(Draft / Active / Executed)*

---

### **Bottling Instruction Detail View**

**Tab 1 – Bottling Rules**

* Allowed final formats

* Allowed case configurations

* Default bottling rule

* Delivery location for serialization

**Tab 2 – Customer Preferences**

* Voucher count

* Preferences collected

* Missing preferences

* Countdown to deadline

**Tab 3 – Allocation / Voucher Linkage**

* Source allocations

* Voucher batch(es)

* Read-only, traceable

**Tab 4 – Personalisation Flags**

* Personalised bottling required (Yes / No)

* Early binding required (Yes / No)

* Binding instruction preview

**Tab 5 – Audit**

* Deadline enforcement

* Default application event

UX must clearly show:  
 **“After this date, defaults will be applied automatically.”**

---

## **7\. Inbound Tab**

### **Purpose**

Record **physical reality** entering the system — without mutating meaning.

Inbound is a **fact**, not a decision.

---

### **Inbound List View**

Columns:

* Inbound ID

* Warehouse

* Product

* Quantity

* Packaging (cases / loose)

* Ownership Flag

* Received Date

* Serialization Required (Yes / No)

* Status *(Recorded / Routed / Completed)*

---

### **Inbound Detail View**

**Tab 1 – Physical Receipt**

* Warehouse

* Date

* Quantity

* Packaging

* Condition notes

**Tab 2 – Sourcing Context**

* Linked Procurement Intent(s)

* Linked PO (if any)

* Ownership status *(Owned / In Custody / Pending)*

**Tab 3 – Serialization Routing**

* Authorized location

* Current rule (e.g. “France only”)

* Blockers if misrouted

**Tab 4 – Downstream Hand-off**

* Sent to Module B (Yes / No)

* Serialized quantity (once available)

**Tab 5 – Audit**

* WMS event references

* Manual adjustments (if any)

---

## **8\. Suppliers & Producers Tab**

### **Purpose**

Lightweight reference view — **not a CRM**.

Used to:

* standardize producer constraints,

* store bottling deadlines,

* store routing constraints.

Fields:

* Producer

* Default bottling deadlines

* Allowed formats

* Serialization constraints

* Notes

Minimal editing, heavy governance.

---

## **9\. Key Operator Flows (End-to-End)**

### **Flow 1 — Voucher-Driven Procurement**

1. Voucher sale confirmed (Mod A)

2. Procurement Intent auto-created (Draft)

3. Ops reviews → Approves

4. PO created (if required)

5. Inbound recorded

6. Stock handed to Module B

---

### **Flow 2 — Liquid Sale with Bottling**

1. Liquid vouchers sold

2. Bottling Instruction created

3. Customer preferences collected

4. Deadline reached → defaults applied if needed

5. Bottling executed

6. Inbound → Serialization

---

### **Flow 3 — Strategic Stock Purchase**

1. Manual Procurement Intent created

2. Approved by Ops / Finance

3. PO issued

4. Inbound recorded

5. Allocation created downstream (Mod A)

---

## **10\. Non-Negotiable UX Invariants (for Filament)**

* Procurement Intent exists **before** PO, Bottling, or Inbound

* Inbound does **not** imply ownership

* Bottling deadlines are **visible and enforced**

* Serialization routing rules are **hard blockers**

* No module here can:

  * issue vouchers,

  * expose products commercially,

  * move inventory for fulfillment.

---

## **11\. Why This UX Matters**

This Admin Panel design:

* prevents silent sourcing mistakes,

* makes liquid sales operationally safe,

* preserves allocation lineage,

* supports consignment cleanly,

* and gives Finance confidence in ownership timing.

