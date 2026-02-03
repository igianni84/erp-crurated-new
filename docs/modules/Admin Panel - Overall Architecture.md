## **Overall Admin Panel UX/UI Architecture**

Here’s the **mental model** the Admin Panel follows.

### **Layered architecture**

#### **Layer 1 — Shell (global)**

* Top bar

* Global alerts

* User profile

* Environment indicator (prod / staging)

* Search (objects, not pages)

#### **Layer 2 — Dashboards (oversight)**

* System Health

* Risk & Exceptions

* Module dashboards

This layer answers:

“Is the system safe and under control?”

#### **Layer 3 — Operational Modules (execution)**

* Mod 0 PIM

* Mod A Allocations

* Mod B Inventory

* Mod C Fulfillment

* Mod D Procurement

* Mod E Accounting

* Mod S Commercial

* Mod K Parties

This layer answers:

“Do the work.”

#### **Layer 4 — Settings & Configuration (governance)**

* Rules

* Policies

* Integrations

* Permissions

* Reference data

This layer answers:

“Why does the system behave this way?”

---

### **Navigation philosophy**

* **Dashboards ≠ execution**

* **Modules ≠ configuration**

* **Settings ≠ daily work**

Each layer has a single responsibility.

