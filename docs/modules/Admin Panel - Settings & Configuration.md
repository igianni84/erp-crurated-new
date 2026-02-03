## **Settings & Configuration**

### **3.1 Settings as a first-class navigation item**

**Settings** should be a **top-level section**, not hidden inside modules.

Audience:

* Admins

* Ops leads

* Finance leads

* Tech / integration owners

Most operators never touch it.

---

### **3.2 What lives in Settings (proposed structure)**

#### **1\) Rules & Policies**

* Allocation constraint rules

* Voucher eligibility rules

* Pricing policies

* Blocking rules (shipment, payment, compliance)

* Alert thresholds

These are **declarative**, not code.

---

#### **2\) Financial Configuration**

* Invoice types (INV0 / INV1 / INV2)

* Revenue categorization

* Tax logic (where applicable)

* Service fee definitions

* Refund behavior

---

#### **3\) Integrations**

* Stripe (payment flows, webhooks)

* Xero (posting rules, account mapping)

* WMS (inventory sync rules)

* External data feeds (e.g. market pricing)

Includes:

* Status

* Sync health

* Error logs

* Mapping tables

---

#### **4\) Reference Data**

* Locations

* Warehouses

* Custody types

* Case formats

* Bottle formats

* Legal entities

Stable, slow-changing data.

---

#### **5\) Roles & Permissions**

* Role definitions

* Module access

* Action-level permissions

* Audit visibility

Critical for safety and compliance.

---

#### **6\) Alert & Notification Configuration**

* Which alerts exist

* Thresholds

* Recipients

* Escalation logic

---

### **3.3 UX principle for Settings**

Settings are:

* Explicit

* Slow-changing

* Heavily audited

* Hard to misuse

If something affects **system behavior**, it belongs here — not hidden in a module.

