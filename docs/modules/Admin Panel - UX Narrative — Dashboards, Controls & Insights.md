## **1\) Admin Panel UX Narrative — Dashboards, Controls & Insights**

### **Entry point & positioning in the Admin Panel**

When an operator logs into the ERP Admin Panel, the **Dashboard** is the default landing area.

This is not a “welcome page”.  
 It is the **control tower** of the system.

From a navigation standpoint:

* **Dashboards** are a **top-level navigation item**, alongside Modules (PIM, Allocations, Inventory, etc.)

* Clicking **Dashboard** opens a **System Health overview**

* From there, users can drill down into:

  * Cross-module dashboards

  * Module-specific dashboards

  * Alert-driven exception lists

The dashboard area is **read-heavy, scan-first, action-light** by design.

---

### **1.1 System Health Dashboard (primary landing view)**

**Purpose**  
 Provide immediate visibility into whether the system is safe to operate *right now*.

**Audience**

* Leadership

* Ops managers

* Senior operators

* Finance (read-only)

**Layout (UX intent)**

* KPI tiles on top (status-driven, not vanity metrics)

* Below: grouped exception panels

* No raw tables

* Every metric is clickable → drill-down view

**Top status indicators**  
 Examples:

* Allocation exhaustion risk (⚠️ / 🔴)

* Vouchers outstanding vs redeemable stock

* Blocked shipments count

* Unpaid invoices impacting ops

* Inbound mismatch (expected vs received)

Each tile answers:

“Is something wrong, or about to be wrong?”

**Interaction**

* Clicking a tile opens a **filtered exception list**, not a chart

* From that list, users jump directly into the owning module (read-only or action-enabled, depending on role)

---

### **1.2 Risk & Exceptions Dashboard (escalation-oriented)**

**Purpose**  
 Surface *things that will break later if ignored now*.

This is the “what should I worry about this week?” view.

**Structure**  
 Grouped exception panels, such as:

* Allocation constraint violations

* Bottling deadlines approaching

* Unpaid storage fees blocking shipment

* Inventory anomalies

* Consignment mismatches

**Key UX principle**  
 This dashboard:

* does **not** allow execution

* does **not** show history

* is **forward-looking and actionable**

Each row answers:

* What is at risk?

* Why?

* Who owns it?

* What happens if we do nothing?

---

### **1.3 Embedded controls (operator layer)**

Most controls **do not live in dashboards**.

They live **inside the modules**, exactly where the action happens.

Examples:

* Allocation views show real-time consumption, constraint pressure, and exhaustion warnings

* Shipping Orders show blockers inline (unpaid fees, missing inventory, compliance holds)

* Procurement flows surface bottling deadlines at decision points

* Invoicing views show payment dependency before allowing shipment

**UX behavior**

* Warnings are visible *before* the action

* Blocking rules are explicit

* “Why is this blocked?” is always answerable without leaving the screen

Dashboards **never replace** these controls — they complement them.

---

### **1.4 Module-level dashboards**

Each operational module exposes a **dashboard tab** as its first sub-view.

These dashboards answer:

“Is this module healthy?”

They are:

* Scoped to the module

* Read-only

* Drill-down enabled

#### **Examples**

**Module A — Allocations & Vouchers**

* Allocation consumption vs limits

* Voucher aging

* Constraint-bound allocations

**Module B — Inventory**

* Stock by custody & location

* Serialized vs unserialized

* Case integrity issues

**Module C — Fulfillment**

* Open Shipping Orders

* Blocked shipments (by cause)

* Warehouse workload pressure

**Module D — Procurement**

* Procurement intent vs execution

* Inbound delays

* Bottling deadline risk

**Module E — Accounting**

* Unpaid invoices by type

* Aging balances

* Refunds & credit notes

* Revenue mix (services vs product)

**UX rule**  
 If a module has execution screens, it must also have:

* a **health view**

* an **exceptions view**

---

### **1.5 Alerts & notifications**

Alerts are not UI decorations. They are **state transitions made visible**.

**Alert center**

* Accessible globally (top bar)

* Also surfaced contextually inside modules

**Alert properties**

* Rule-based

* Configurable

* Auditable

* Always linked to:

  * the rule

  * the triggering event

  * the affected object

Alerts never:

* auto-execute

* hide logic

* disappear without trace

---

### **1.6 Auditability & traceability (UX implication)**

Every metric, alert, and warning supports:

* “Why am I seeing this?”

* “What changed?”

* “Who or what caused it?”

From a UX standpoint:

* Tooltips explain logic

* “View rule” or “View history” links are available for privileged users

* Dashboards are explainable, not magical

