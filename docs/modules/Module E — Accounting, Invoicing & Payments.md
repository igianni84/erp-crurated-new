## **Module E — Accounting, Invoicing & Payments**

**Admin Panel: UX/UI Narrative Walkthrough (LOCKED)**

---

## **1\. Entry point & operator mental model**

### **Entry point**

**Admin Panel → Finance**

Module E appears as a **top-level “Finance” section** in the ERP navigation, clearly separated from:

* Commercial (Mod S),

* Operations (Allocations, Fulfillment),

* Inventory.

This separation reinforces the core principle:

**Finance follows ERP events — it does not create them.**

### **Primary users**

* Finance & Accounting

* Operations Finance

* Customer Support (read / limited actions)

* Admins

* Auditors (read-only)

### **What operators come here to do**

Operators do **not** come here to “sell” or “ship”.  
 They come to:

* Inspect and issue invoices,

* Track payment truth,

* Resolve financial exceptions,

* Enforce eligibility blocks via invoice status,

* Reconcile Stripe / bank transfers,

* Maintain a clean audit trail.

---

## **2\. Top-level structure (Finance)**

`Finance`  
`├── Invoices`  
`├── Payments`  
`├── Credit Notes & Refunds`  
`├── Customers (Financial View)`  
`├── Subscriptions & Storage Billing`  
`├── Integrations`  
`└── Reporting & Audit`

Each section maps to **one financial responsibility**, not one invoice type.

---

## **3\. Tabs & sub-tabs**

---

## **3.1 Invoices (core operational screen)**

### **Purpose**

The **Invoices** section is the authoritative ledger of all ERP invoices (INV0–INV4).

This is the **default landing page** for Finance.

---

### **Invoice List (table view)**

**Primary filters**

* Invoice ID

* Invoice Type (INV0–INV4)

* Customer

* Status (draft / issued / paid / partially paid / credited / cancelled)

* Currency

* Date issued

* Linked ERP event (membership / voucher / shipment / storage period)

**Table columns**

* Invoice ID

* Type

* Customer

* Amount (currency)

* Status

* Issue date

* Due date (if applicable)

* Accounting ref (Xero)

* ⚠ Flags (disputes, overdue, blocked ops)

**UX principles**

* No mixing of invoice types

* Clear color-coding by status (not by type)

* Clicking a row opens **Invoice Detail**

---

### **Invoice Detail (single invoice view)**

This screen is **read-heavy, action-light**.

**Header**

* Invoice ID

* Invoice Type (hard-locked, non-editable)

* Status

* Customer

* Currency

**Tabs inside Invoice Detail**

`Invoice`  
`├── Lines`  
`├── Payments`  
`├── Linked ERP Events`  
`├── Accounting`  
`└── Audit Log`

#### **Invoice → Lines**

* Read-only list of invoice lines

* Explicit descriptions (never inventory or serials)

* Tax breakdown per line

* Totals

#### **Payments**

* Applied payments

* Payment method (Stripe / bank)

* Partial payments visible

* Outstanding balance

#### **Linked ERP Events**

Read-only references, e.g.:

* Membership ID (INV0)

* Voucher batch (INV1)

* Shipping Order ID (INV2)

* Storage period \+ warehouse (INV3)

* Event ID (INV4)

This reinforces **causality**, not inference.

#### **Accounting**

* Xero sync status

* Statutory invoice number

* GL posting references

* FX rate at issuance

#### **Audit Log**

* Status changes

* Credits issued

* Manual actions

* External events (Stripe webhooks)

---

### **Allowed actions (contextual)**

Actions depend strictly on **invoice status**:

* Draft → Issue

* Issued → Record payment (bank)

* Issued → Create credit note

* Paid → Refund (partial / full)

* Never: change invoice type, amount, or lines after issuance

---

## **3.2 Payments**

### **Purpose**

Payments are **evidence**, not authority.

This section exists to:

* Monitor incoming payments,

* Resolve mismatches,

* Handle delayed or partial settlements.

---

### **Payment List**

* Payment ID

* Source (Stripe / Bank)

* Amount

* Currency

* Status

* Linked invoice(s)

* Date received

Stripe payments often auto-reconcile; bank transfers may require manual matching.

---

### **Payment Detail**

* Raw payment data (from Stripe or bank)

* Applied invoice(s)

* Reconciliation status

* FX differences (read-only)

**Key UX rule**

Payments never create invoices and never modify invoice structure.

---

## **3.3 Credit Notes & Refunds**

### **Purpose**

Explicit financial reversals and adjustments.

---

### **Credit Notes**

* Linked original invoice

* Reason (required)

* Amount

* Status

* Xero reference

Credit notes:

* Preserve original invoice type,

* Never mutate historical data.

---

### **Refunds**

* Linked invoice \+ payment

* Refund method (Stripe / bank)

* Partial vs full

* Status

**UX safeguard**  
 Refund creation always shows:

“Operational consequences are not automatic.”

---

## **3.4 Customers — Financial View**

This is a **read-only financial dashboard per customer**, complementary to Module K.

**Tabs**

`Customer Finance`  
`├── Open Invoices`  
`├── Payment History`  
`├── Credits & Refunds`  
`├── Exposure & Limits`  
`└── Eligibility Signals`

Used by:

* Finance,

* Customer Support,

* Ops escalation.

This view explains *why* a customer may be blocked elsewhere.

---

## **3.5 Subscriptions & Storage Billing**

### **Subscriptions (INV0)**

* Membership plans

* Billing cycles

* Next invoice date

* Status (active / suspended due to non-payment)

### **Storage Billing (INV3)**

* Billing periods

* Usage calculation snapshots

* Generated invoices

* Block indicators (unpaid storage)

This area is **batch-oriented**, not transactional.

---

## **3.6 Integrations**

### **Stripe**

* Webhook health

* Failed events

* Pending reconciliations

### **Xero**

* Sync status

* Failed postings

* Retry queue

No business logic lives here — this is **ops plumbing**.

---

## **3.7 Reporting & Audit**

### **Standard views**

* Invoice aging

* Revenue by invoice type

* Outstanding exposure

* FX impact summary

### **Audit**

* Immutable logs

* Export for auditors

* Event-to-invoice traceability

---

## **4\. Key operator flows**

---

### **Flow 1 — Voucher sale → INV1 → Voucher issuance**

1. Checkout/order approved (outside Mod E)

2. INV1 appears as **issued**

3. Payment confirmed (Stripe / bank)

4. Invoice marked **paid**

5. Module A creates vouchers

**UX note**  
 Operators cannot issue vouchers manually from Finance.

---

### **Flow 2 — Shipment → INV2**

1. Shipping Order executed (Mod C)

2. INV2 auto-created (draft or issued, per policy)

3. VAT / duties applied

4. Invoice issued and posted to Xero

5. Payment collected or tracked

---

### **Flow 3 — Storage billing cycle (INV3)**

1. Billing period closes

2. Usage calculated retrospectively

3. INV3 batch generated

4. Issued invoices may:

   * trigger reminders,

   * enforce custody blocks if unpaid.

---

### **Flow 4 — Refund or credit**

1. Operator opens invoice

2. Chooses **Create Credit Note** or **Refund**

3. Explicit reason required

4. Financial effect applied

5. Any operational change handled elsewhere

---

## **5\. Edge-case & governance UX notes**

* Invoice type is **immutable**

* No invoice merges or splits

* No manual VAT overrides post-issuance

* Stripe delays and duplicates handled silently (idempotent UX)

* Event inventory costs never appear here (expenses only)

