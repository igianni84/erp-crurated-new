# **1\. Why the Current Platform Must Be Rethought (Problem Statement)** {#1.-why-the-current-platform-must-be-rethought-(problem-statement)}

## **1.1 Context: a platform built for a different Crurated**

The current platform was built over four years ago to support an early version of Crurated’s business: an innovative but relatively contained model centered on allocations, vouchers, delayed fulfillment, and bottle-level provenance.

Since then, the business has evolved materially. Crurated now operates multiple commercial, ownership, and distribution models simultaneously, with long-lived customer entitlements and monetization unfolding over time rather than per transaction.

The platform has not evolved with the same structural discipline. The result is a growing mismatch between how the business operates and how the system represents it. This is not a matter of missing features or UI quality, but of foundational abstractions no longer aligned with the operating model.

---

## **1.2 The platform is incomplete and relies on heavy manual intervention**

Several core operational processes are only partially supported by the platform and require substantial manual input.

In practice:

* allocations are defined and managed outside the system (e.g. Coda) and then manually entered;

* pricing logic is prepared externally (e.g. spreadsheets) and manually applied;

* commercial and operational decisions depend on coordination across multiple tools.

Reconciliation *does* occur in the system, but because key inputs are manual and fragmented:

* system stock levels often diverge from WMS reality,

* discrepancies emerge between commercial commitments, inventory, and financial data,

* issues are detected late and corrected manually.

**Impact**

* high operational overhead and error correction cost;

* low confidence in data accuracy;

* growing fragility as volumes and models increase.

---

## **1.3 Missing or incorrect core business abstractions**

Some of the platform’s core abstractions do not reflect how the business actually operates today, or are only implied rather than explicitly modeled.

Most critically:

* **Sellable SKUs are not clearly separated from physical SKUs**, creating ambiguity around cases, mixed cases, breakability, and parallel selling.

* **Vouchers — the core selling instrument (1 voucher \= 1 bottle right) — are not modeled** as first-class objects with explicit lifecycle states.

* **Ownership, custody, and physical control are only implicitly represented**, rather than explicitly modeled as separate dimensions. The platform does not clearly distinguish:

  * who owns the wine,

  * who has physical custody,

  * whether the asset exists physically or only as a future entitlement. This makes it fragile to support consignment, third-party inventory, custody-only services, and deferred ownership transfer.

* **Current allocation / sub-allocation structure is confusing**, not grounded in business logic, and difficult to reason about. Constraints are poorly structured, and allocations are unnecessarily coupled to commercial logic (e.g. requiring selling prices at allocation level), creating rigidity and confusion \- ops team rely on Coda to manage all relevant information.

**Impact**

* commercial commitments are hard to interpret and enforce consistently;

* ownership and liability are at risk of being misrepresented;

* selling logic is constrained earlier than necessary;

* operational complexity increases without adding real control.

---

## **1.4 Early binding between customers and bottles creates operational errors**

The platform binds customers to specific (virtual) bottles too early, before:

* bottles physically exist,

* case composition is known,

* warehouse constraints are relevant.

Virtual serials are used simultaneously as:

* placeholders for future physical bottles, and

* customer-specific entitlements.

This premature binding generates significant operational friction.  
 In practice, it leads to frequent exceptions that must be resolved manually, such as:

* reassigning serials,

* correcting allocations,

* fixing fulfillment errors post-factum.

**Impact**

* increased error rates and manual intervention;

* inefficient warehouse operations;

* reduced flexibility across warehouses and channels.

---

## **1.5 Fragmented commercial and customer governance**

Commercial policy (pricing, eligibility, channel exposure, allocation usage) is fragmented across multiple system areas and partially managed outside the platform.

There is also no single authoritative customer model:

* B2C and B2B customers are managed separately;

* clubs and partners are forced into generic membership constructs;

* rules and pricing logic are duplicated and inconsistently enforced.

**Impact**

* low confidence in pricing accuracy and margins;

* difficulty reallocating inventory across channels;

* operational duplication and limited scalability for partner-driven models.

---

## **1.6 Limited modularity, weak documentation, and unclear system responsibilities**

The current platform lacks clear modularity and a well-defined division of responsibilities between system components.

Business domains such as:

* product definition,

* commercial policy,

* selling instruments,

* allocations,

* inventory and custody,

* fulfillment,

* and financial events

are tightly coupled and spread across unrelated parts of the system, without a clearly documented reference model.

As a result:

* it is difficult to understand where decisions are made and enforced;

* changes in one area often produce unexpected side effects in others;

* operators rely on manual cross-checks and corrections;

* product and business teams lack visibility into dependencies and implications;

* obsolete concepts and logic persist even after business decisions have changed.

The system has evolved organically and incrementally, without an explicit target architecture, resulting in a structure that is hard to reason about end-to-end and increasingly prone to error as complexity grows.

**Impact**

* high regression risk and fear of change;

* operational fragility and manual error correction;

* slow delivery of new initiatives;

* growing dependence on tribal knowledge rather than system discipline.

---

## **1.7 Summary and Conclusion**

**The issues outlined above are systemic and interconnected**. They stem from missing or incorrect core abstractions, early binding of concepts that should remain decoupled, and insufficient domain discipline.

While the current platform supported earlier stages of Crurated’s growth, it is no longer adequate for the business’s current and future complexity. Incremental fixes would perpetuate the same structural weaknesses.

A new architecture is required to:

* clearly separate physical SKUs from sellable SKUs,

* model vouchers as first-class commercial instruments,

* enforce late binding between entitlements and physical bottles,

* explicitly represent ownership, custody, and financial responsibility,

* provide modularity, auditability, and scalability aligned with how the business actually operates today and is expected to evolve.

In addition, the current state of the platform represents a **material risk in the context of a technical and operational due diligence**. The lack of clear core abstractions, modularity, documentation, and authoritative system ownership would significantly weaken the company’s position during fundraising, increasing perceived execution risk and potentially impacting valuation and deal timelines.

As a result, **incremental refactoring would perpetuate the same architectural weaknesses**, while increasing complexity and risk.

---

# **2\. Target ERP Architecture and Design Principles** {#2.-target-erp-architecture-and-design-principles}

## **2.1 Objectives of the new ERP**

The new ERP is not a technical rewrite of the existing platform, nor a consolidation of scattered logic.

Its purpose is to establish a **single, authoritative system of record** that:

* correctly models Crurated’s core business abstractions,

* enforces discipline through explicit structure and states,

* supports multiple commercial and operational models without fragmentation,

* integrates cleanly with best-in-class external systems (ecommerce, WMS, CRM, accounting).

The ERP is designed to be the **operational brain of the business**, not another channel-driven application.

This directly addresses the issues identified in Section 1: fragmented logic, implicit rules, unsafe coupling between commerce and execution, and lack of authoritative models.

---

## **2.2 Core architectural philosophy**

### **2.2.1 ERP as authority, not interface**

The ERP is the authoritative system for:

* commercial policy and pricing

* customer eligibility and operational rules

* selling instruments and obligations

* allocations and procurement commitments

* inventory, custody, and provenance

* fulfillment state

* financial events

Customer-facing platforms (B2C, B2B, club portals, trading interfaces) and external operational systems (ecommerce engines, WMS, accounting systems such as Xero, payment providers) are **execution arms**, not decision-makers.They execute actions and reflect outcomes, but do not own business logic, commercial rules, eligibility, state transitions, or authoritative records.

---

### **2.2.2 Explicit domain modeling over implicit logic**

All critical business concepts must be modeled **explicitly**, not inferred:

* vouchers are first-class objects where applicable

* sellable SKUs are distinct from wine identity

* cases are physical containers, not attributes

* allocations define sellable supply independently of inventory

* procurement is a controlled response to supply requirements

* custody and ownership are separate concepts

* lifecycle states are explicit and enforced

Any logic that is “implied” rather than modeled is treated as a design failure.

---

### **2.2.3 Separation of business functions**

The ERP enforces a strict separation between layers that are currently entangled:

* **Commercial policy**: can we sell this, to whom, and on what terms?

* **Selling instruments**: what right or obligation is created?

* **Allocation & procurement**: how much supply is committed and how it will be sourced.

* **Inventory & custody**: where physical assets are and who controls them.

* **Fulfillment**: how and when obligations are executed.

* **Accounting**: financial impact and timing.

---

## **2.3 Canonical business abstractions**

The ERP is built around a small, stable set of canonical abstractions that remain valid as business models evolve:

* **Product vs Sellable SKU**:  
  Wine identity is not sellable by itself.  
  A sellable SKU represents a concrete commercial unit (wine, format, packaging, case configuration).

* **Voucher (**where applicable):  
  A voucher represents a deferred fulfillment obligation and a financial liability until redemption. It exists independently of physical bottles and follows an explicit lifecycle.

* **Allocation**  
  Allocations define how much can be sold, independently of inventory timing or format. They cap exposure and drive procurement without assuming stock exists.

* **Bottle**  
  A bottle is a physical asset that exists only after bottling, is serialized by default, and carries custody and provenance.

* **Case**  
  A case is a physical container with integrity and logistical meaning; its state affects fulfillment rights and must be enforced.

These abstractions are foundational and are not redefined by channels or use cases.

---

## **2.4 Supported commercial and operational models (clarified)**

The ERP supports multiple business models that differ along two fundamental dimensions:

* whether a **deferred customer obligation** is created,

* whether **Crurated owns** the physical asset.

Vouchers are used **only** in models where Crurated sells a deferred right to future delivery.  
All models share the same canonical abstractions and enforcement rules.

---

### **2.4.1 Voucher-based sales (deferred obligations)**

In these models, Crurated sells a right to future delivery rather than an immediate physical transfer.  
A voucher is always created and represents a deferred fulfillment obligation and a financial liability until redemption.  
Physical bottles may exist already or be sourced or produced later; binding occurs only at fulfillment.

---

### **2.4.2 Active consignment (sell-through, no vouchers)**

In active consignment, Crurated owns the wine and places physical stock with restaurants or third parties for sell-through.  
Sales are recorded after they occur, with no deferred customer obligation and no customer-held right.  
No vouchers are created, as fulfillment happens at the point of sale.

The ERP tracks custody transfer, serial-level inventory depletion, and periodic invoicing to the consignee.

---

### **2.4.3 Third-party owned stock (custody and agency)**

In this model, wine is owned by a third party while Crurated acts as custodian and, optionally, as selling agent.  
Crurated may sell the wine on behalf of the owner (e.g. “verified by Crurated” bottles), or provide custody only.  
A voucher is created **only if** the sale creates a deferred delivery obligation; ownership remains third-party until sale.

The ERP enforces ownership vs custody separation, inventory segregation, provenance, and agency sale tracking.

---

### **2.4.4 Services & experiences (non-inventory, non-entitlement)**

In this model, Crurated sells access to a time-bound service or experience rather than a delivery obligation.  
Customers receive participation or attendance rights, not entitlements to future physical delivery.  
No vouchers are created.

Wine used during services or events is consumed operationally as part of execution and is never associated with customer ownership, fulfillment rights, or post-event delivery.

---

### **2.4.5 Model summary**

| Model | Voucher | Deferred obligation | Crurated owns asset |
| :---- | :---- | :---- | :---- |
| Standard sale | ✅ | ✅ | ✅ |
| Liquid sale | ✅ | ✅ | ✅ |
| Active consignment | ❌ | ❌ | ✅ |
| Third-party stock | ❌ | ❌ | ❌ |

This framework ensures conceptual clarity, correct accounting treatment, and safe extensibility.

---

## **2.5 Late binding as a design principle**

Late binding is the default and enforced behavior:

* customer rights remain abstract,

* physical assets remain fungible,

* binding between customer and specific bottles happens only at fulfillment.

Early binding is an explicit exception, not the norm.

This reduces warehouse complexity, improves scalability, and aligns with best practices in comparable asset-backed industries.

---

## **2.6 One ERP, multiple frontends**

The ERP is **single and unified**, regardless of the number of customer-facing channels.

* One ERP, one source of truth

* Multiple frontends:

  * B2C (Members)

  * B2B (Professionals)

  * Club portals

  * Trading platform

This replaces the current dual-admin-panel approach and eliminates operational divergence.

---

## **2.7 Integration-first, but ERP-led**

The ERP integrates with ecommerce, WMS, CRM, payments, accounting, and blockchain systems.  
Integration does not imply delegation of authority:

External systems execute actions; the ERP decides and records outcomes.

---

## **2.8 Operational discipline by design**

The ERP enforces discipline through:

* explicit states and transitions

* confirmation and locking semantics

* approval workflows where required

* immutable audit logs

* deterministic logic over manual overrides

The system is designed to **prevent mistakes**, not merely record them.

---

## **2.9 Strategic and due-diligence value**

A robust ERP is also a **strategic asset**:

* demonstrates architectural maturity

* reduces operational and key-person risk

* provides clarity on liabilities, inventory, and obligations

* materially improves technical and operational due diligence during fundraising, partnerships, or M\&A

A strong ERP increases confidence for investors and external stakeholders.

---

# **3\. Global System Architecture & High-Level Data Flow** {#3.-global-system-architecture-&-high-level-data-flow}

## **3.1 Architectural overview**

The target architecture is built around a **single, unified ERP core** that acts as the **system of record and decision authority**, surrounded by specialized external systems that execute specific functions such as ecommerce, logistics, payments, accounting, CRM, and blockchain recording.

The ERP is intentionally central in terms of **truth and governance**, while execution is distributed across systems that are best suited for specific tasks.

**Core principle**

One operational brain, many execution arms.

This directly addresses the fragmentation and duplication observed in the current platform.

---

## **3.2 Logical system architecture (visual overview)**

The diagram below represents the **logical architecture** of the target system, highlighting authority boundaries, system responsibilities, and data flow.

The diagram is authoritative with respect to system boundaries, ownership of decisions, and direction of data flow. Any integration or implementation must conform to the authority model expressed in this diagram.

![][image1]

---

## **3.3 Authority and responsibility boundaries**

To prevent fragmentation and ambiguity, **authority boundaries are explicit**.

**ERP responsibilities**

The ERP:

* decides what can and cannot be sold, to whom, and under which conditions

* validates customer eligibility and operational permissions

* governs allocations and obligations

* records authoritative inventory, custody, and provenance

* controls fulfillment eligibility and sequencing

* generates financial events and invoice lifecycles

**External systems**

External systems:

* present ERP-approved options

* execute ERP-authorized actions

* report outcomes back to the ERP

The ERP remains the system of record for all business, commercial, and financial state.

**For physical inventory and warehouse execution, the WMS is the system of record for physical reality (existence, location, and movements).**

In case of discrepancies between expected inventory (ERP) and physical inventory (WMS), the WMS prevails on physical facts, and the ERP state is reconciled through explicit adjustment events.

---

## **3.4 ERP modules and data ownership**

The ERP is explicitly structured around the following modules, each owning a clear domain and lifecycle:

* **Module 0 — Product Information Management (PIM)**  
  Defines wine identity, formats, packaging, cases, and liquid product definitions.
  Doc reference in /docs/modules/MOD 0 - PIM UX narrative.md

* **Module S — Sales & Commercial Management**  
  Governs channels, pricing logic, campaigns, currency rules, and eligibility.
  Doc reference in /docs/modules/Mod S – Commercial_ UX_UI Narrative.md

* **Module K — Customers & Eligibility**  
  Manages customer identity, tiers, permissions, operational holds, and billing rules.
  Doc reference in /docs/modules/Module K — Parties, Customers & Eligibility UX narrative.md

* **Module A — Allocations & Vouchers**  
  Controls sellable supply, voucher issuance (where applicable), liabilities, and trading constraints.
  Doc reference in /docs/modules/Mod A – Allocations narrative.md, /docs/modules/Mod A – Vouchers narrative.md, /docs/modules/Mod A - Vouchers - edge cases UX.md

* **Module D — Procurement & Inbound**  
  Converts supply commitments into purchase orders and bottling instructions.
  Doc reference in /docs/modules/Module D — Procurement & Inbound UX narrative.md

* **Module B — Physical Inventory & Provenance**  
  Tracks warehouses, cases, bottles, serialization, custody, and provenance events.
  Doc reference in /docs/modules/Module B – Inventory UX narrative & edge cases.md

* **Module C — Fulfillment & Shipping**  
  Orchestrates redemption, late binding, shipment planning, and WMS execution.
  Doc reference in /docs/modules/Mod C - Fulfillment UX narrative.md

* **Module E — Accounting & Financial Events**  
  Represents and governs invoices, credits, refunds, and financial events as the authoritative financial layer, and synchronizes them with downstream accounting systems.
  Doc reference in /docs/modules/Module E — Accounting, Invoicing & Payments.md

* **Admin Panel**
  Define the main structure, settings and access of the Admin panel
  Doc reference in /docs/modules/Admin Panel - UX Narrative — Dashboards, Controls & Insights.md, /docs/modules/Admin Panel - Overall Architecture.md, /docs/modules/Admin Panel - Settings & Configuration.md

Each module is authoritative for its own data and exposes controlled interfaces to others.

---

## **3.5 Canonical high-level flows**

### **3.5.1 Voucher-based sales**

*(standard, liquid, passive consignment)*

1. Products defined in Module 0

2. Allocation and constraints created in Mod A

3. Sale conditions and pricing evaluated in Module S

4. Customer validated in Module K

5. Allocation consumed and voucher issued in Module A

6. INV1 generated in Module E

7. Procurement and inventory follow as required

8. Redemption triggers fulfillment via Module C

9. Shipment confirmed and INV2 generated

---

### **3.5.2 Active consignment (sell-through)**

1. Stock procured and serialized (Module B)

2. Inventory placed with consignee (custody transfer)

3. Consignee sells to end customers

4. Sales reported to ERP

5. Inventory decremented at serial level

6. Periodic invoice issued (Module E)

No vouchers are involved in this flow.

---

### **3.5.3 Services & experiences**

Service offerings are defined and sold without creating product entitlements or fulfillment obligations.

Customer bookings trigger service invoices (INV0) and operational execution records.

Any wine used is consumed internally via inventory consumption flows, without vouchers, fulfillment, or ownership transfer.

---

## **3.6 Late binding as a system-wide rule**

Where vouchers apply:

* customer rights remain abstract

* inventory remains fungible

* binding between voucher and bottle occurs only at fulfillment planning, unless an explicit early-binding exception applies (e.g. personalized bottling).

Late binding is enforced **consistently and centrally**, not ad hoc.

---

## **3.7 Multi-warehouse and custody model**

All physical locations (internal warehouses, satellite warehouses, consignees) are modeled uniformly.

Every bottle or case always has:

* an owner

* a custody location

* a custody history

Ownership and custody are independent dimensions and may change at different times under different business models.

This supports transfers, consignment, rebalancing, and full auditability.

---

## **3.8 Event-aware, state-authoritative behavior**

External systems emit events (payment success, pick confirmation, sale report).  
The ERP:

* validates events against current state

* applies or rejects transitions

* updates authoritative records

External events may be duplicated or delayed; the ERP must handle them idempotently and deterministically. Events never override state.

---

## **3.9 Security, auditability, and traceability**

All critical actions:

* require explicit permissions

* follow defined state transitions

* generate immutable audit records

This applies to pricing changes, allocations, vouchers, inventory movements, and invoicing.

# **4\. Module 0 — Product Information Management (PIM)** {#4.-module-0-—-product-information-management-(pim)}

## **4.1 Purpose and scope**

Module 0 (PIM) is responsible for defining **what exists** in the Crurated ecosystem and **how it can be described**, independently of:

* availability

* pricing

* customer eligibility

* inventory

* fulfillment

It is the **single source of truth for product identity and structure**, but it does **not** decide:

* whether something can be sold

* to whom it can be sold

* at what price

Those decisions belong to **Module S (Sales)** and **Module A (Allocations)**.

---

## **4.2 Design principles**

### **4.2.1 Identity before commerce**

The PIM models:

* wines

* formats

* packaging

* cases

* pre-bottling liquid products

**without assuming**:

* that they are sellable

* that they are available

* that they will ever be sold

This separation avoids the current SKU confusion.

**Terminology lock**

In the system, the term *SKU* refers exclusively to *Sellable SKUs*, which represent concrete commercial units (wine vintage × format × case configuration).

Wine identities and vintages have stable internal codes but are not SKUs and must never be treated as commercial units.

---

### **4.2.2 Clear separation between wine identity and sellable unit**

A fine wine has:

* a **wine identity** (what it is)

* one or more **sellable expressions** (how it can be sold)

The PIM explicitly separates these layers.

---

### **4.2.3 Explicit modeling of cases and packaging**

Cases are **not attributes**.  
They are **physical and commercial constructs** that must be modeled explicitly.

This is critical to support:

* OWC vs OC vs loose

* intact vs broken cases

* case-level customer expectations

---

## **4.3 Core entities and relationships**

The PIM is structured around the following core entities.

---

### **4.3.1 Wine Master**

Represents the **identity of a wine**, independent of vintage or format.

**Examples**

* Château Margaux

* Sassicaia

* Domaine de la Romanée-Conti – Romanée-Conti

**Key fields (non exhaustive list)**

* producer

* wine name / cuvée

* appellation

* classification

* country / region

* producer metadata (story, estate info)

* regulatory attributes

* Liv-ex reference (if applicable)

A Wine Master is **never sold directly**. A Wine master can have a *code*, but is not a SKU

---

### **4.3.2 Wine Variant (Vintage)**

Represents a **specific vintage** of a Wine Master.

**Examples**

* Château Margaux 2016

* Sassicaia 2018

**Key fields (illustrative, non exhaustive)**

* wine\_master\_id

* vintage year

* alcohol % (if known)

* critic scores (multiple sources)

* drinking window

* production notes

* Liv-ex vintage reference

A Wine Variant is still **not sellable**. A wine Variant can have a *code* but it’s not an SKU

---

### **4.3.3 Format**

Represents the **physical bottle size**.

**Examples**

* 0.75L

* 1.5L

* 3.0L

* 0.375L (exceptional)

**Key fields**

* volume\_ml

* standard flag (yes/no)

* allowed\_for\_liquid\_conversion (yes/no)

Formats are reusable across wines.

---

### **4.3.4 Case Configuration**

Represents a **physical container configuration**.

This is a first-class entity.

**Examples**

* 6 × 0.75L OWC

* 3 × 0.75L OC

* 1 × 1.5L OWC

* loose (no case)

**Key fields**

* format\_id

* bottles\_per\_case

* case\_type (OWC, OC, none)

* original\_from\_producer (yes/no)

* breakable (yes/no)

Case configuration is **commercially meaningful**.

---

### **4.3.5 Sellable SKU**

A Sellable SKU represents a concrete commercial unit definition that may be sold if and when sales and allocation allow it

Sellable SKUs represent how a product variant can be sold commercially. The system distinguishes between two categories of sellable SKUs:

* **Intrinsic sellable SKUs**, which correspond to physically existing configurations received from the producer (e.g. loose bottles, original cases, original wooden cases, or producer-created verticals). These configurations may carry physical integrity constraints.

* **Composite sellable SKUs**, which represent commercially defined bundles (e.g. mixed cases curated by Crurated). Composite sellable SKUs never correspond to pre-existing physical inventory and must always be resolved at fulfillment time through entitlement aggregation.

Both categories are first-class sellable SKUs with their own identifiers, pricing, and eligibility rules, but only intrinsic sellable SKUs correspond to pre-existing physical configurations.

**Examples**

* “Sassicaia 2018 – 6×0.75L – OWC”

* “Sassicaia 2018 – 0.75L – loose”

**Key fields**

* wine\_variant\_id

* format\_id

* case\_configuration\_id

* sku\_code (internal)

* barcode (optional)

* lifecycle status (draft / active / retired)

Important clarifications:

* SKUs exist **before inventory**

* SKUs do **not** imply availability

* SKUs do **not** imply pricing

* SKU lifecycle status reflects definition readiness, not commercial availability. Availability and exposure are governed exclusively by Offers and Allocations.

**Composite Sellable SKUs (bundles)**

A composite Sellable SKU represents a commercially atomic bundle composed of multiple Bottle SKUs in defined quantities.

Composite Sellable SKUs are the **only valid representation of bundles** in the system. They encapsulate all bundle semantics, including composition, atomicity, and fulfillment constraints.

Downstream modules (Sales, Allocation, Fulfillment) treat composite Sellable SKUs as indivisible commercial units. No downstream module may create, alter, or reinterpret bundle composition.

---

## **4.4 Liquid (pre-bottling) product definitions**

Liquid sales are **not modeled as another SKU**.

They require a separate construct. It’s a different Product (vs Bottles)

---

### **4.4.1 Liquid Product**

A Liquid Product is a temporary commercial abstraction used only before bottling. It allows the sale of bottle-equivalent rights without creating Bottle SKUs or Sellable SKUs. Once bottling formats are decided, liquid allocations and vouchers are resolved into Bottle SKUs and Sellable SKUs, after which the Liquid Product no longer participates in operational flows.”

Represents wine **before bottling**, sold as bottle-equivalent units.

**Key principles**

* Units are discrete (0.75L or 0.375L)

* No free-form liters

* Pricing is per bottle-equivalent

* Bottling happens later

**Key fields**

* wine\_variant\_id

* allowed\_equivalent\_units (0.75L, optionally 0.375L)

* allowed\_final\_formats (e.g. 0.75L, 1.5L)

* allowed\_case\_configurations

* bottling\_constraints (producer-specific)

* serialization\_required (true by default post-bottling; case-level non-serialized inventory allowed only where explicitly configured)

Liquid Products:

* Are separate Product category (e.g. liquid\_product\_id: LP\_DX\_GC\_2019)

* are sellable only via vouchers directly (no sellable SKU at time of sale)

* resolve into physical bottles and case configurations that correspond to Sellable SKU definitions

* never exist in inventory

---

## **4.5 Media, content, and enrichment**

PIM is also responsible for **content integrity**, not presentation.

**Supported content**

* product descriptions

* producer stories

* tasting notes

* 3D bottle assets

* label images

* regulatory documents

**External enrichment**

* Liv-ex data (prices, references, indices)

* critic scores

* market metadata

All enrichment:

* is versioned

* has a source

* can be audited

Market price references (e.g. Liv-ex) are stored in PIM strictly as external, observational enrichment. They are not commercial prices and must never be interpreted as sell prices, offer prices, or valuation rules.

---

## **4.6 Governance, lifecycle, and controls**

### **4.6.1 Lifecycle states**

All PIM entities support lifecycle states:

* draft

* reviewed

* active

* retired

Only **active** entities can be referenced by downstream modules.

---

### **4.6.2 Roles and permissions**

Typical roles:

* Product Manager (create/edit)

* Content Editor (text/media)

* Reviewer / Approver

* Admin

Changes to:

* Wine identity

* Format definitions

* Case configurations

require explicit approval and are audited.

---

## **4.7 What PIM explicitly does not do**

To avoid repeating current mistakes, Module 0 does **not**:

* manage pricing

* manage availability

* manage inventory

* manage customer eligibility

* manage sales logic

* PIM does not model commercial offers, channel exposure, or campaign logic; these belong exclusively to Module S.

It provides **clean, stable building blocks** for other modules.

---

## **4.8 Key invariants (must never be violated)**

1. Wine identity ≠ sellable SKU

2. Case configuration is explicit and first-class

3. Liquid products are not SKUs

4. No SKU implies availability or price

5. PIM never encodes business policy

6. A sellable SKU is not a customer entitlement and never represents a customer-held right.

These invariants are critical for long-term correctness.

---

## **4.9 Outputs and integrations**

Module 0 provides read-only product definitions to downstream modules.:

* product definitions to Module S (Sales)

* SKU references to Module A (Allocations)

* format and case rules to Module B (Inventory)

* descriptive data to Bottle Page (read-only)

It does **not** consume data from downstream modules.

---

## **4.10 Why this module matters**

A correct PIM:

* removes ambiguity across the entire ERP

* enables liquid sales without hacks

* makes case logic enforceable

* simplifies fulfillment and inventory

* reduces operational mistakes

Most of the structural issues in the current platform originate from a weak PIM.

# **5\. Module S — Sales & Commercial Management** {#5.-module-s-—-sales-&-commercial-management}

## **5.1 Purpose and scope**

Module S is responsible for defining **commercial exposure and intent**.

It answers the questions:

* *Which products or allocations can be sold right now?*

* *Through which channels (B2C, B2B, clubs)?*

* *To which customers or segments?*

* *At what price and under which commercial rules?*

Module S is the **commercial brain** of the ERP.  
It governs *how supply is exposed to the market*, but it does **not** execute sales, manage inventory, or move stock.

---

## **5.2 What Module S is — and is not**

**What Module S does**

* Decides **whether** something is commercially eligible to be sold, within constraints set in Mod A (Allocation)

* Decides **where** it can be sold (channels)

* Decides **to whom** it can be sold (segments, tiers, allowlists)

* Decides **at what price** and under which rules

* Governs **multi-channel exposure of the same supply**

* Provides deterministic, auditable commercial decisions

**What Module S does not**

* Reserve or decrement physical inventory

* Create vouchers

* Trigger procurement

* Plan fulfillment

* Issue invoices

* Move stock between warehouses

* Module S never consumes or reserves allocation capacity; it relies on Module A for all capacity enforcement.

Those responsibilities belong to Modules A, B, C, D, and E.

---

## **5.3 Core design principles**

### **5.3.1 One supply, many commercial projections**

The same underlying supply (allocation) may be exposed:

* on B2C

* on B2B

* on one or more private clubs

* simultaneously

* at different prices

Module S governs the commercial exposure of **allocations defined at Bottle SKU level** across channels.

The same underlying supply (Bottle SKU allocation) may be offered simultaneously on multiple channels at different prices, with allocation acting as the single constraint

This principle is foundational.

---

### **5.3.2 Channels are stable; exposure is flexible**

A **Channel** represents a stable commercial relationship:

* B2C (Members)

* B2B (Professionals)

* Club X, Club Y

Temporary or tactical constructs such as:

* weekly offers

* shop vs private sales

* launch windows

* opportunistic B2B selling

are **not channels**.  
They are expressed through **Offers, Campaigns, and Eligibility rules** within the same channel.

This avoids fragmentation and preserves a single commercial brain.

Multiple sellable SKUs may coexist over the same underlying **Bottle SKU allocation pool**.

For example, the same set of bottles may be offered simultaneously as loose bottles, as fixed cases (OWC/OC), or as part of composite bundles. Some sellable SKUs (e.g. vertical cases) require atomic consumption of multiple Bottle SKU allocations and may only be exposed when all required constrained allocations are available.

Sellable SKUs represent **commercial promises and entitlement definitions** (including packaging rights), not units of inventory or allocation

Availability must therefore be evaluated across sellable SKUs, ensuring that sales of one sellable SKU correctly consume bottle-equivalents from the same allocation pool and prevent overselling. 

Commercial rules (pricing, channel exposure, eligibility) apply at the **Offer** level, while inventory consumption is reconciled centrally against the allocation pool.

---

### **5.3.3 Commercial intent is decoupled from physical reality**

Module S operates **independently of inventory location or physical stock movements**.

* Supply constraints are enforced via **allocations** (Module A)

* Physical availability is handled later by **inventory and fulfillment**

This decoupling allows:

* selling before stock exists

* selling across regions

* reallocating commercial focus without operational disruption

---

## **5.4 Core commercial entities**

### **5.4.1 Commercial abstraction**

Module S intentionally separates commercial intent, pricing decisions, and supply constraints into distinct abstractions.

An **Offer** represents a commercial invitation to transact under a specific context (channel, eligibility, commercial model), but it does not intrinsically define pricing or consume supply.

**Prices are not embedded in Offers**. Instead, Offers resolve to explicit prices via Price Books, which represent governed pricing decisions scoped by channel, segment, currency, and validity period.

This separation is a deliberate design choice to ensure pricing consistency, auditability, multi-channel scalability, and controlled evolution of commercial rules over time.

### **5.4.2 Channel**

A **Channel** defines the commercial context of a sale.

**Examples**

* B2C (Members)

* B2B (Professionals)

* Private Club

**Key fields**

* channel\_id

* channel\_type

* default currency

* allowed commercial models (voucher-based, sell-through)

Channels do not define pricing or availability.

---

### **5.4.3 Offer**

An **Offer** represents a commercial projection of supply into a channel.

It answers:

“This product can be sold *here*, *now*, *under these conditions*.” 

An Offer may only reference **SKUs that are commercially available under allocation-derived constraints** for the Offer’s channel and market. Offers cannot override or relax allocation availability.

**Offer references**

* a Sellable SKU

* a Liquid Product

**Key fields**

* offer\_id

* product\_reference

* channel\_id

* commercial\_model (voucher-based / sell-through)

* offer\_type (e.g. standard, weekly offer, private sale)

* visibility (public / restricted)

* status (draft / active / paused / expired)

* validity window

Offers must be **explicitly mapped** to one or more allocations in Module A; allocation sourcing is never implicit or automatic. Multiple offers may point to the **same underlying allocation**. Allocations referenced by an Offer are always defined at **Bottle SKU (vintage \+ format)** level; the Sellable SKU referenced by the Offer determines how that allocation may be consumed.

Offers for **atomic composite sellable SKUs** (such as verticals) must reference all required underlying Bottle SKU allocations and validate composition constraints before exposure.

**Offer pricing resolutions**: An Offer does not carry a price directly. At runtime, an Offer resolves to a concrete, explicit unit price through the application of an **active Price Book** whose scope matches the Offer’s channel, customer eligibility, currency, and validity window.

This ensures that the same Offer may be priced differently across customer segments, time periods, or commercial contexts without duplicating or fragmenting commercial definitions.

Offers may optionally carry a `campaign_tag` or `campaign_id` for grouping and governance, without changing Offer execution semantics.

**Offer–Sellable SKU-Bundle relationship (non-negotiable)**

An Offer always references **exactly one Sellable SKU**.

The referenced Sellable SKU may be:  
 – **simple**, representing a single bottle or homogeneous case configuration, or  
 – **composite**, representing a bundle composed of multiple Bottle SKUs (e.g. mixed cases, verticals, discovery sets).

Bundles are modeled exclusively as **composite Sellable SKUs** in the PIM, with explicit component definitions and composition constraints.

Commercial logic must never bundle products at the Offer level. All bundle semantics are expressed at the Sellable SKU level.

Offers apply pricing, eligibility, activation, and validity logic to the referenced Sellable SKU **as a whole**, without inspecting or modifying its internal composition.

---

### **5.4.4 Price Book**

A Price Book represents a **pricing decision**, not a simple price list.

Price Books are expected to contain a large number of Offer price entries corresponding to all sellable Offers within their defined scope. This is intentional and reflects a coherent commercial pricing stance toward a given channel and customer segment.

Price Books are versioned, time-bound, and approved as a whole, enabling controlled bulk pricing changes, historical traceability, and deterministic price resolution at checkout.

This replaces Excel-based pricing entirely.

**Key fields**

* price\_book\_id

* currency

* channel scope

* customer segment (optional)

* validity period

* approval status

Each price line includes:

* offer\_id

* unit\_price (per bottle or bottle-equivalent)

Price Books are versioned, auditable, and time-bound.

Pricing is always defined per Offer, not per SKU, to allow channel-, segment-, and campaign-specific pricing over the same underlying product.

**Membership- and segment-based pricing**

Differences in pricing based on customer membership, club affiliation, or eligibility tier are typically expressed through Offers and Discounts, not through separate Price Books.

A single Price Book represents a coherent base price reality for a channel, market, and currency. Membership- or club-specific benefits are applied by Offers that reference the same Price Book while enforcing different eligibility rules or discounts.

Separate Price Books are appropriate only when the underlying price reality itself differs (e.g. B2B vs B2C, different tax regimes, currencies, or fundamentally different commercial positioning).

---

### **5.4.5 Campaign**

A Campaign is not a first-class executable object in the ERP. It represents a business coordination concept used to group and manage multiple Offers that share a common intent, validity window, eligibility, or discount logic.

All commercial execution occurs at the Offer level. Campaigns do not introduce pricing logic, availability, or allocation consumption independently.

In practice, campaigns are created and managed by bulk creation and management of Offers with shared attributes, ensuring that the ERP models only what is operationally executable and auditable.

A **Campaign** modifies exposure or pricing for a limited time.

**Examples**

* weekly offer

* private sale

* launch window

* club-exclusive access

**Key fields**

* `Campaign_id`

*  `name` / `campaign_tag`

* `campaign_kind` (e.g. launch, private sale, weekly offer, club-exclusive)

*  `start_date`, `end_date`

*  `approval_state` (draft / approved / active / archived)

*  `applicable_offers` (explicit list or query-based association)

A Campaign does not define executable eligibility, pricing, or stacking logic. Those are defined and executed by Offers (and referenced Discounts/Rules). Campaigns exist to coordinate, approve, audit, and manage groups of Offers.

Campaigns never mutate base pricing permanently.

---

### **5.4.6 Pricing Policy (price generation support)**

A Pricing Policy is an optional, non-authoritative construct used to assist in the generation of draft prices for Price Books.

Pricing Policies may express price derivation logic (e.g. cost-plus markup, market-anchored pricing) and validation constraints (e.g. margin floors, market price caps), but they **never apply prices at runtime** and never override approved prices implicitly.

When applied, a Pricing Policy produces proposed prices that are reviewed, adjusted if necessary, and explicitly approved within a Price Book.

Once a Price Book is active, it remains the sole authoritative source of pricing for Offers within its scope. Pricing Policies do not execute during checkout or commercial resolution.

Pricing Policies operate exclusively on SKUs that are commercially available according to **allocation-derived constraints**.

Each Pricing Policy defines a scope (e.g. channel, market, currency) and applies only to Bottle SKUs whose allocation availability matches that scope.

As a result, Price Books are populated deterministically:  
 a SKU appears in a Price Book if and only if it is commercially available for that channel and market at the time of pricing evaluation.

Pricing Policies must never introduce, re-enable, or override commercial availability. They react to allocation truth; they do not define it.

### ---

**5.4.7 Discounts and pricing rules (reusable building blocks)**

Module S supports a library of reusable **Discounts and Pricing Rules**, which define atomic pricing logic such as percentage discounts, fixed-amount reductions, conditional eligibility, stacking behavior, and rounding rules.

Discounts and Rules are **definitions only**. They are not executable by themselves and never apply prices directly.

Discounts and Rules may be referenced by Offers (and, where applicable, by Pricing Policies during price generation), but they must never:  
 – mutate base prices stored in Price Books,  
 – activate or expose selling on their own,  
 – introduce new commercial availability.

This separation ensures that pricing benefits remain reusable, auditable, and reversible, while execution logic remains centralized in Offers.

---

## **5.5 Multi-channel exposure of a single allocation**

Allocations (Module A) are **channel-agnostic by default**.

Module S creates one or more Offers that:

* reference the same product

* pull from the same allocation

* expose it to different channels at different prices

**Example**

Allocation: Wine X 2019 – 750ml (Bottle SKU) – 1,000 bottle-equivalents

Offers:

* B2C Offer → €320

* B2B Offer → €260

* Club Offer → €300 (time-limited)

Sales on any channel reduce the **same allocation balance**.

No stock is duplicated.  
No channel owns inventory.

---

## **5.6 Liquid (pre-bottling) sales in Module S**

Liquid sales are handled as a **commercial variant**, not a product variant.

Module S:

* exposes liquid offers

* enforces allowed quantities (bottle-equivalents only)

* prices per bottle-equivalent

* defers format and case decisions

All conversion logic happens later in Modules A and D.

---

## **5.7 Market price as a reference signal (EMP)**

Module S may maintain one or more **Estimated Market Prices (EMP)** for a given wine or sellable unit, derived from external market sources such as Liv-ex or other reference feeds.

EMP represents a normalized, time-aware reference signal reflecting observed market behavior. It is strictly **non-authoritative** and **non-executable**.

EMP may be used by Module S to:  
 – validate pricing constraints and guardrails,  
 – support pricing review and approval workflows,  
 – provide transparency and context to downstream UX,  
 – power analytics and future AI-assisted decision support.

EMP must never:  
 – resolve to an actual sell price,  
 – override an approved Price Book,  
 – block a sale on its own without an explicit commercial rule.

Market reference data remains observational. All executable pricing decisions are defined exclusively by approved Price Books.

---

## **5.8 Governance, lifecycle, and auditability**

All commercial objects (Offers, Price Books, Campaigns) support:

* draft

* submitted

* approved

* active

* expired

* cancelled

Every change is:

* versioned

* attributed to a user

* timestamped

* auditable

This is critical for:

* operational trust

* error prevention

* investor and auditor confidence

All pricing used by Module S is explicit, versioned, and auditable.

Although pricing may be assisted by Pricing Policies during preparation, all prices applied to Offers are frozen within approved Price Books prior to activation.

This guarantees deterministic commercial outcomes, reproducible historical pricing, and full traceability of pricing decisions for operational, financial, and audit purposes.

---

## **5.9 Outputs and downstream dependencies**

Module S provides:

* sellable offers to ecommerce and club portals

* pricing decisions to checkout

* commercial context to Module A (allocation consumption)

* pricing metadata to Module E (invoicing)

Module S consumes:

* product definitions from Module 0

* customer segmentation from Module K

* market price signals (read-only)

  ### **5.9.1 Commercial handshake**

During checkout, Module S determines the applicable Offer and price context, Module K validates customer eligibility and blocks, and Module A validates and (optionally) reserves **Bottle SKU allocation capacity**. A voucher is issued only after explicit sale confirmation, at which point allocation is consumed and the financial obligation is created.

---

## **5.10 Key invariants**

1. One allocation may feed multiple channels

2. Channels never own inventory

3. No sale happens without an Offer

4. Pricing is explicit, versioned, and auditable

5. Commercial changes never move physical stock

6. An Offer does not represent a sale or commitment; it is an invitation to transact subject to allocation and confirmation.

These invariants are non-negotiable.

---

## **5.11 Why this module is critical for Crurated**

Module S enables Crurated to:

* dynamically arbitrate between B2C and B2B

* react to market opportunities

* protect high-value channels

* sell before stock exists

* avoid operational chaos

This flexibility — without compromising control — is one of the **core strategic advantages** of the new ERP.

# **6\. Module K — Parties, Customers & Eligibility** {#6.-module-k-—-parties,-customers-&-eligibility}

## **6.1 Purpose and scope**

Module K is the **authoritative registry of all parties** operating within the ERP.

It defines:

* who exists in the Crurated ecosystem,

* in which role(s) each party operates,

* and—where applicable—what actions they are allowed to perform.

Module K answers the questions:

* Who is this party (legally and operationally)?

* In which role does this party act (e.g. customer, supplier, producer, partner)?

* If acting as a customer:

  * which channels and actions are they eligible for?  
  * what is their membership status and tier?  
  * which payment methods and credit conditions are allowed?  
* Are there any operational restrictions or holds in place?

Module K is the **system-wide gatekeeper**: no sale, voucher issuance, redemption, trading, or shipment can proceed without passing Module K checks.

---

## **6.2 What Module K is — and is not**

**What Module K does**

* Acts as the ERP system of record for all parties

* Defines party identity and legal representation

* Manages party roles (customer, supplier, producer, partner)

* Manages customer membership, tiers, and eligibility

* Computes customer segments and affiliations (e.g. clubs)

* Enforces channel eligibility and access rights

* Controls payment permissions and credit eligibility

* Applies and enforces operational blocks and holds

**What Module K does not**

* Manage pricing, discounts, or commercial conditions (Module S)

* Decide offer composition or commercial exposure (Module S)

* Create or manage vouchers (Module A)

* Manage inventory, custody, or fulfillment execution (Modules B, C)

* Replace CRM, marketing automation, or sales tooling

---

## **6.3 ERP Customer vs CRM Contact (clear boundary)**

Module K is **not a CRM**. Module K and CRM systems such as HubSpot serve complementary but strictly separated roles.

**HubSpot (CRM)** is used for:

* lead and prospect management

* sales pipelines and deal tracking

* marketing engagement and communications

* relationship history and notes

**Module K (ERP)** is authoritative for:

* customer existence as a transacting party

* legal and billing identity

* membership status and tier

* eligibility to access channels, offers, and actions

* operational permissions and blocks

Data may flow from HubSpot into Module K (e.g. approved customer creation, tier changes), but:

* CRM status never implies transactional eligibility

* no action in HubSpot can grant access, remove blocks, or override ERP rules

* in case of discrepancy, Module K state always prevails

A prospect or contact may exist and be actively managed in HubSpot without being eligible to transact in the ERP.

---

## **6.4 Party roles and customer structures**

---

### **6.4.1 Customer role and customer profile**

When a Party holds the **Customer** role, it becomes a transacting party in the ERP and is subject to eligibility, membership, and payment rules.

A Customer represents a party that may:

* purchase offers,

* receive vouchers,

* redeem, trade, or ship inventory.

Customer categories include:

* B2C individuals,

* B2B legal entities,

* partner organizations acting as buyers.

Clubs are **not customers by default**.  
A club organization may act as a customer only if it explicitly holds the Customer role.

**Key fields (Customer profile)**

* `customer_id`  
* `party_id` (reference to Party)  
* `customer_type` (B2C / B2B / Partner)  
* `status` (prospect / active / suspended / closed)  
* `default_billing_address_id` (optional)

Only customers in **active** status may transact.

Customer existence does not imply eligibility to buy.

---

### **6.4.2 Customer Account(s)**

A Customer may operate through one or more **Accounts**, representing distinct operational or commercial contexts under the same legal entity.

Accounts are used to model differences in:

* billing and invoicing,

* shipping defaults,

* channel access,

* eligibility rules,

* operational blocks.

Examples:

* an individual with a personal account and a club-affiliated account,

* a restaurant group with multiple venue accounts,

* an enterprise customer with regional operational accounts.

Accounts do **not** represent separate legal entities and do not exist independently of a Customer.

---

### **6.4.3 Users and Access**

User identities (login accounts) are distinct from Customers and Accounts.

A User:

* represents a login identity (email, authentication, permissions),

* may access multiple customer accounts,

* multiple users may access the same account.

Emails are not required to be unique per customer or per account.

Authorization to act on an account is governed by access rules, not by party identity.

---

### **6.4.4 Address Management**

Addresses are managed as first-class entities in Module K.

Address types include:

* billing addresses,

* shipping addresses,

* temporary or event-specific delivery addresses.

Addresses may be:

* associated with a Customer,

* associated with a specific Account,

* time-bound and versioned.

Fulfillment and logistics modules consume addresses but do not own or manage them.

---

### **6.4.5 Non-customer roles (suppliers, producers, partners)**

Parties acting as suppliers, producers, or partners are governed by Module K only for identity, role validation, and legal legitimacy.

Module K ensures that such parties:

* exist as authoritative counterparties,  
* hold the appropriate role(s),  
* are active and compliant,  
* can be safely referenced by allocations, procurement, and financial modules.

No eligibility, membership, channel access, or payment permission logic applies to non-customer roles.

Commercial, operational, and financial behavior for these roles is governed by Modules A, D, and E.

---

### **6.4.6 Party creation and role assignment**

Module K is the authoritative system of record for the existence of all parties in the ERP.

Parties may be created through two distinct paths:

* **Front-end customer registration**, which may create a Party holding the Customer role only

* **ERP / Ops–initiated party creation**, used to create suppliers, producers, partners, clubs, or other operational counterparties

ERP-initiated party creation:

* does not imply customer status

* does not grant eligibility or transactional rights

* requires explicit role assignment

Party roles are explicit, additive, and auditable.  
 No role is inferred based on usage in other modules.

Assigning the **Customer** role creates a Customer entity, initialized in a non-eligible state and subject to membership, eligibility, payment, and block rules defined in Module K.

Not all Parties are Customers. Most Parties in the ERP will never transact.

---

## **6.5 Memberships and tiers (Customer Role only)**

Membership is a first-class concept in Module K, applicable **only** to parties acting in the Customer role. Membership governs whether and how a customer may access curated offers and channels.

### **6.5.1 Membership tiers**

Supported membership tiers include:

* **Legacy**

  * registered but not approved member

  * limited visibility

  * no or restricted purchasing rights

* **Member**

  * approved member

  * access to standard B2C offers

  * eligible for private sales depending on rules

* **Invitation Only (IO)**

  * explicitly invited or approved

  * access to exclusive offers

  * may have preferential pricing or limits

Tiers are:

* explicit

* time-bound

* auditable

The system is designed to support additional tiers (including B2B-specific tiers) if enabled in the future.

---

### **6.5.2 Membership lifecycle and enforcement**

Membership follows a **controlled lifecycle**, separate from party or customer existence.

**States**

* applied

* under\_review

* approved

* rejected

* suspended

**Key principles**

* A customer may exist before being eligible to buy

* Eligibility depends on **approved membership status**

* Tier changes propagate automatically to downstream modules

Time-bound restrictions (e.g. temporary suspension, compliance review, payment issues) should be enforced through **operational blocks**, not by repeatedly changing membership tiers or states, unless the membership decision itself is being altered.

This separation allows:

* curated membership growth

* operational oversight

* consistent enforcement across channels

---

## **6.6 Club as customer affiliation**

A **Club** is a first-class affiliation entity used to group customers for eligibility, visibility, and experience purposes.

A Club is **not a customer by default** and does not represent a transacting party unless explicitly linked to a Party holding the Customer role.

---

### **6.6.1 Club entity**

A Club represents an external partner organization or curated group whose members receive differentiated access to the platform.

**Key attributes**

* `club_id`  
* `partner_name`  
* `status` (active / suspended / ended)  
* `branding_metadata` (used by front-end systems; not used for ERP enforcement logic)

---

### **6.6.2 Customer-Club relationship**

Customers may be affiliated with one or more Clubs.

The Customer ↔ Club relationship is:

* many-to-many,

* explicit,

* time-bound.

Affiliation metadata may include:

* membership status (active / suspended),

* start and end dates,

* privacy or data-sharing flags (if applicable).

Club affiliation:

* does not grant transactional rights by itself,

* does not override membership approval,

* contributes to eligibility computation and segmentation.

---

### **6.6.3 Use of Clubs across modules**

Club affiliation is:

* **managed in Module K** as an eligibility fact,  
* **consumed by Module S** for offer eligibility, pricing, and promotions,  
* **consumed by Module A** for allocation visibility or reservation rules,  
* **used by Events modules** for participation rules,  
* **used by front-end systems** for routing and theming.

Module K defines *who belongs to which club*; it does not decide *what that means commercially*.

---

## **6.7 Segments and eligibility**

### **6.7.1 Customer segments**

A **Segment** is a deterministic classification computed in Module K and primarily consumed by Module S.

**Examples**

* B2C Guest

* B2C Collector

* B2C Invitation Only

* B2B Restaurant

* B2B Distributor

* Club Member

* Member of Club X (optional, if granular segmentation is needed)

Segments are:

* computed automatically in Module K

* Derived from:

  * Customer type

  * Membership status & tier

  * Account context

  * Club affiliation

* consumed by Module S for pricing, offers, and campaigns

Segments are never independently assigned or manually overridden.

This ensures deterministic eligibility, prevents privilege escalation, and guarantees consistent pricing and access rules across channels.

---

### **6.7.2 Channel eligibility**

For each customer or account, Module K defines:

* allowed channels (B2C, B2B, clubs)

* restricted channels (if any)

A customer failing channel eligibility:

* cannot see offers

* cannot checkout

* cannot redeem vouchers

Channel eligibility is enforced independently of pricing and offer logic.

---

## **6.8 Payment permissions and credit control**

Payment permissions are **explicit**, not inferred.

**Supported payment modes**

* credit / debit card

* bank transfer (approved customers only)

**Key rules**

* bank transfer requires explicit authorization

* authorization may be scoped by:

  * customer

  * account

  * credit limit

* outstanding balances are tracked

* Payment permissions and credit controls affect eligibility to transact and fulfill, but never modify pricing or commercial terms.

Customers with overdue balances may be:

* blocked from new purchases

* blocked from redemption or shipment

---

## **6.9 Stripe integration (clear boundary)**

Stripe may be used for:

* collecting membership fees

* recurring payments

* payment execution and settlement

**However:**

* Stripe is **not** the system of record for membership

* Stripe does **not** define eligibility or access rights

**Authoritative model**

* Module K owns:

  * membership status

  * tier

  * eligibility

  * operational permissions

* Stripe reflects **payment state only**

Payment events from Stripe may:

* trigger warnings

* trigger operational blocks (e.g. payment holds)

* never override ERP authority

Payment events from Stripe may be delayed or duplicated and must be handled idempotently; authoritative customer status always derives from Module K state.

*Membership rights are defined in the ERP; payment platforms execute economics only.*

---

## **6.10 Operational blocks and holds (critical)**

Module K introduces **first-class operational blocks**, enforced system-wide.

**Examples**

* payment hold

* shipment hold

* redemption hold

* trading hold

* compliance review hold

**Characteristics**

* blocks are explicit and reasoned

* blocks are timestamped and auditable

* blocks override all commercial and fulfillment logic

No operator can bypass a block without removing it.

---

## **6.11 Role of Module K in flows**

**During checkout**

Module K validates:

* customer and account status

* membership approval

* tier eligibility

* channel access

* payment permissions

* operational blocks

Failure at any step denies checkout.

---

**During redemption, trading, or shipment**

Module K validates:

* eligibility to perform the required action

* absence of shipment blocks

* compliance status

* financial standing

Fulfillment (Module C) cannot proceed without Module K clearance.

---

## **6.12 Governance and auditability**

All changes to:

* membership status

* tiers

* eligibility

* payment permissions

* operational blocks

* Party roles

* Club affiliations

are:

* permission-controlled

* versioned

* fully auditable

---

## **6.13 Key invariants**

1. Party existence ≠ eligibility

2. Membership approval is explicit

3. Operational blocks override all logic

4. Payment platforms do not define rights

5. Club affiliation ≠ transactional rights

6. CRM status does not imply ERP access

---

## **6.14 Why this module is critical**

Module K:

* makes curated membership enforceable

* prevents unauthorized access and sales

* protects against payment and compliance risk

* supports private sales and IO logic

* removes manual checks and tribal knowledge

Without Module K, Modules S, A, and C cannot operate safely.

# **7\. Module A — Allocations & Vouchers** {#7.-module-a-—-allocations-&-vouchers}

## **7.1 Purpose and scope**

Module A governs **sellable supply and customer obligations (entitlements)**.

It answers the questions:

* *How much can we sell, independently of physical stock and location?*  
* *Under which constraints (producer, geography, channel, customer type) can we sell it?*  
* *What obligation do we create when a customer buys?*  
* *How do we prevent overselling across channels and business models?*  
* *How do we delay the assignment of physical bottles until the optimal moment?*

Module A is the control layer between:

* commercial exposure (Module S),  
* physical inventory and containers (Module B),  
* financial obligation and settlement (Module E).

---

## **7.2 Core design principles**

### **7.2.1 Allocation before inventory**

In this system, selling does **not** require physical inventory.

Wine may be sold when it is:

* still with the producer,  
* not yet bottled,  
* not yet serialized,  
* not yet physically inbounded.

What matters is whether Crurated holds a **sellable commitment**. That commitment is modeled as an **Allocation**.

### **7.2.2 Vouchers represent customer rights, not physical assets**

A **Voucher** represents the customer’s right to redeem **exactly one bottle or one bottle-equivalent** at a future point in time.

Important clarifications:

* vouchers are not bottles,  
* vouchers are not inventory,  
* vouchers are not NFTs,  
* vouchers are ERP-native, authoritative constructs.

Buying multiple bottles always results in multiple vouchers:

* 1 bottle → 1 voucher  
* 12 bottles → 12 vouchers  
* liquid sale → 1 voucher per bottle-equivalent

**Voucher generation from sellable SKUs**

* When a *sellable SKU* is sold, the system generates vouchers representing the associated entitlements.  
* Each voucher corresponds to exactly one bottle or bottle-equivalent.  
* For composite sellable SKUs (e.g., mixed cases), the system generates multiple vouchers according to the bundle composition, issuing one voucher per bottle-equivalent defined in the sellable SKU structure.  
* Vouchers are independent of physical bottles and are later resolved to specific serialized bottles at fulfillment time through **late binding**.  
* Vouchers may have one or more customer-facing symbolic representations used for customer experience, trading, or presentation purposes.  
  * These representations do not imply physical bottle assignment,  
  * do not reference serialized inventory,  
  * and have no operational impact.

### **7.2.3 Late binding is mandatory**

The system must not assign:

* a voucher,  
* a customer,  
* or a trade

…to a specific physical bottle until fulfillment planning.

Late binding:

* reduces warehouse complexity,  
* avoids unnecessary bottle handling,  
* allows optimal picking,  
* follows best-in-class fine wine logistics practice.

Binding vouchers to serial numbers happens only during fulfillment (Module C).

---

## **7.3 Core entities**

**Important alignment with Appendix (SKU Strategy):**

* Allocation happens at **Bottle SKU** level (vintage \+ format).  
* Sellable SKUs encode packaging promises and *consume* allocations.  
* Vouchers reference what was sold (sellable SKU and/or its bottle SKU mapping) but remain unbound from physical bottles until fulfillment.

### **7.3.1 Bottle SKU (Variant reference)**

A **Bottle SKU** identifies a fungible physical unit type: **vintage \+ format** (e.g., 2019–750ml, 2019–1.5L).

Bottle SKU is the level at which:

* supply commitments are expressed (allocations),  
* physical fungibility is defined,  
* later procurement/bottling and WMS flows resolve into bottles.

### **7.3.2 Allocation**

An **Allocation** represents a sellable supply commitment at the **Bottle SKU (variant)** level.

Allocations are always quantified in **bottles** or **bottle-equivalents**.

Allocations are **not** created per sellable SKU (packaging must not fragment supply).  
Sellable SKUs consume allocations by applying rules (bottle count, packaging constraints, eligibility).

Allocation statement:

“We are allowed to sell up to X bottles (or bottle-equivalents) of this Bottle SKU under specific conditions.”

**Key fields**

* allocation\_id  
* bottle\_sku\_reference (vintage \+ format)  
* source\_type:  
  * producer allocation  
  * owned stock  
  * passive consignment  
  * third-party stock (custody only)  
* total\_quantity (in bottles or bottle-equivalents)  
* sold\_quantity  
* remaining\_quantity  
* expected availability window  
* serialization\_required (yes / no)  
* commercial constraints (authoritative):  
  * allowed channels  
  * allowed geographies  
  * allowed customer types  
* status (draft / active / exhausted / closed)

### **7.3.3 Allocation constraints (important)**

Allocations are channel-agnostic by default, but may carry hard constraints imposed by the source.

Examples:

* producer allows sale only on B2C  
* producer restricts sale to specific geographies  
* third-party or club stock restricted to specific customers

These constraints:

* are defined in Module A,  
* are authoritative,  
* must be enforced by Module S.

Module S may not expose an offer that violates allocation constraints.

**Liquid allocations constraints**

Liquid allocations must define allowed bottling formats and (where applicable) allowed case configurations upfront as conditioning constraints. These constraints govern later bottling choices but do not affect sellability or pricing.

**Serialization and fungibility constraint**

Allocations may specify whether resulting inventory is:

* serializable at bottle level, or  
* managed as fungible items by the case (exception path).

This constraint is authoritative for fulfillment and binding behavior.

**Composition constraints (verticals and atomic bundles)**

Allocations may carry composition constraints that restrict how they may be consumed.

A composition constraint indicates that **one or more Bottle SKU allocations must be consumed together as an atomic group** and may only be used by sellable SKUs that explicitly declare a matching composition.

This mechanism is used for **vertical cases or other non-fungible groupings** where individual bottles must not be sold independently.

### **7.3.4 Allocation-derived commercial availability**

Allocations are the sole source of truth for determining where a Bottle SKU may exist commercially.

From each active Allocation, the ERP derives a set of **commercial availability facts**, expressing:  
 – allowed channels,  
 – allowed markets / geographies,  
 – validity windows,  
 – quantity caps.

These availability facts are **derived, read-only projections** of allocation constraints. They are not manually edited and cannot be overridden by commercial configuration.

If a Bottle SKU is not covered by an active allocation for a given channel or market, it is **not commercially available** in that context and must not be priced, offered, or sold.

### **7.3.5 Temporary allocation reservation**

To prevent overselling during checkout, negotiation, or manual deal workflows, Module A supports temporary allocation reservations.

Characteristics:

* reserve allocation quantity (not inventory),  
* are time-bound and explicitly reversible,  
* may be created during checkout or commercial negotiation,  
* are automatically released if the reservation expires or is cancelled,  
* do not create customer entitlements and do not consume allocation.

Allocation is consumed only when vouchers are issued following an explicit sale confirmation.

Temporary reservations must never be treated as commitments and must not trigger downstream financial or fulfillment processes.

### **7.3.6 Voucher**

**Voucher — authoritative customer entitlement**

A Voucher represents a confirmed customer right to redeem exactly one bottle or one bottle-equivalent at a future point in time.

Vouchers:

* are ERP-native, authoritative constructs  
* are atomic (one voucher \= one bottle or bottle-equivalent)  
* are independent of physical bottles and inventory  
* never reference serial numbers prior to fulfillment

Vouchers represent customer rights, not physical assets, and are resolved to specific serialized bottles only during fulfillment through late binding.

**Voucher issuance and lifecycle**

Vouchers are created only upon explicit sale confirmation, according to the applicable commercial and payment model.

Voucher creation:

* is explicit and auditable  
* consumes allocation  
* creates a financial obligation  
* never occurs as a result of draft orders or temporary reservations

Voucher lifecycle states include: **issued, locked, redeemed, cancelled**.

Behavioral permissions such as tradability or giftability are modeled separately and do not represent lifecycle transitions.

**Allocation lineage (non-negotiable)**

Each voucher is issued from exactly one allocation and permanently retains its allocation lineage.

Allocation lineage defines the contractual, economic, and provenance context under which the entitlement was sold.

Allocation lineage:

* must never be modified or substituted  
* must be enforced during fulfillment  
* prevents interchangeable fulfillment across different allocations, even for identical Bottle SKUs

Allocation lineage remains intact across gifting, trading, locking, and redemption.

**Key fields**

* voucher\_id (explicit, first-class identifier)  
* customer\_id  
* allocation\_id  
* bottle\_sku\_reference (vintage \+ format)  
* sellable\_sku\_reference (what was purchased / promised)  
* quantity \= 1 bottle OR 1 bottle-equivalent  
* lifecycle state: issued → locked → redeemed / cancelled  
* behavioral flags: tradable, giftable, suspended  
* creation timestamp

**Rules**

* vouchers are atomic  
* vouchers are fungible until redemption (within the constraints of allocation lineage and any packaging entitlements)  
* vouchers may be traded subject to rules  
* vouchers do not reference serial numbers

---

## **7.4 Case entitlements and breakability**

Allocations are tracked in bottles, but fixed-case sellable SKUs introduce **case-level fulfilment rights**.

**Case Entitlement**

When a customer buys a *fixed case* sellable SKU (OWC/OC), the system creates a **Case Entitlement** that groups the resulting vouchers.

Rules:

* allocations remain bottle-quantified (no inventory fragmentation)  
* buying a fixed case sellable SKU creates a Case Entitlement grouping vouchers  
* while the entitlement is **INTACT**, the customer has the right to ship as the promised case packaging  
* any trade or redemption of a bottle breaks the entitlement irreversibly  
* once **BROKEN**, the right to ship the case is removed  
* remaining vouchers remain valid and behave as loose bottles

Enforcement happens at fulfillment (Module C) using entitlement status \+ operational rules; the sellable SKU itself does not mutate.

In addition to standard fixed cases (OWC/OC), Module A supports atomic composite groupings (e.g. **vertical cases**) composed of different Bottle SKUs.

These groupings are enforced via allocation composition constraints rather than packaging breakability rules.

---

## **7.5 Multiple allocations, identical bottles (important nuance)**

Over time, the same Bottle SKU may be sourced through multiple allocations:

* different producers,  
* different contracts,  
* different pricing,  
* different moments in time.

From a customer perspective:

* bottles are indistinguishable.

From an ERP perspective:

* allocations remain distinct for:  
  * cost tracking,  
  * margin analysis,  
  * supplier reporting,  
  * contractual compliance.

Late binding allows:

* physical bottles to be treated as fungible operationally,  
* while preserving allocation lineage for reporting and finance.

---

## **7.6 Voucher transfer (gifting and trading)**

A voucher may be transferred from one customer to another without creating a new commercial transaction.

This transfer represents a change in entitlement holder only and does not constitute a sale, refund, or resale.

Key principles:

* the voucher remains the same authoritative entitlement object  
* no new voucher is created and no allocation is consumed  
* the original commercial and fiscal provenance of the voucher is preserved  
* no financial event (revenue, refund, VAT) occurs at transfer time

Rules:

* a voucher may be transferred only if it is not redeemed, cancelled, or locked for fulfillment  
* during transfer, the voucher is temporarily unavailable to the sender until the recipient explicitly accepts it  
* upon acceptance, the recipient becomes the new voucher holder  
* the transfer is recorded as an explicit, immutable event linked to the voucher’s history

At fulfillment time:

* the voucher is redeemed by the current holder  
* shipment, ownership transfer, and late binding occur normally  
* applicable VAT and shipping costs are charged to the redeemer, based on the voucher’s original economic value

Voucher transfer must never:

* create a new sale or zero-price order  
* modify allocation balances  
* alter the voucher’s original invoice or tax base  
* imply physical bottle assignment prior to fulfillment

**External Trading and Voucher Suspension**

Vouchers may be traded on external platforms that are not part of the ERP. During external trading, a voucher remains an authoritative entitlement in the ERP but is temporarily suspended.

While suspended, the voucher cannot be redeemed, gifted, or transferred, but continues to count toward committed inventory protection.

Upon notification of trade completion, the ERP updates the voucher holder and reactivates the voucher. Allocation lineage, unit economic value, and voucher history remain unchanged.

---

## **7.7 Vouchers vs NFTs (clarified)**

**Provenance NFT**

* minted at serialization  
* represents chain of custody  
* owned by Crurated  
* linked to a physical bottle  
* never traded by customers

**Trading (wrapper) NFT**

* created only when customers trade  
* lives on the trading platform  
* represents an economic wrapper  
* burned when brought back for redemption

**Voucher**

* exists only in the ERP  
* represents redemption rights  
* authoritative for fulfillment  
* independent from blockchain

Voucher ↔ NFT relationships are integrations, not identity.

---

## **7.8 Allocation consumption by business model**

### **7.8.1 Standard sales (owned or producer allocation)**

Flow:

* Allocation exists and is active (Bottle SKU level)  
* Sale approved by Module S (sellable SKU chosen)  
* Allocation temporarily reserved  
* Sale is explicitly confirmed (according to commercial and payment terms)  
* Voucher(s) issued (1 per bottle)  
* Allocation balance decremented

### **7.8.2 Liquid (pre-bottling) sales**

Characteristics:

* allocation measured in bottle-equivalents  
* no physical format yet  
* bottling occurs later

Rules:

* vouchers always represent bottle-equivalents  
* conversion to formats happens in procurement (Module D)  
* serialization occurs after bottling

### **7.8.3 Passive consignment**

Two variants:

* stock still with producer  
* stock already in warehouse

In both cases:

* allocation exists  
* vouchers are issued at sale  
* procurement and ownership may occur later

Module A behavior is identical.

### **7.8.4 Active consignment (sell-through)**

Key distinction:

* no vouchers are created  
* stock moves before sale  
* sales are reported after they happen  
* invoices are issued periodically

Module A may track quantity limits but does not create customer obligations.

### ---

### **7.8.5 Third-party stock (custody only)**

In custody-only models, allocations are used solely as control and segmentation constructs and must never be consumed or generate vouchers unless Crurated is explicitly authorized to sell.

In this case:

* allocation exists for control  
* sales may be restricted to specific channels  
* vouchers are not issued  
* Crurated does not own the asset

### ---

**7.8.6 Member consignment (agency resale)**

**7.8.6 Member-owned stock resale (agency model)**

In this model, a customer who already owns bottles consigns them to Crurated for resale under an agency arrangement.

Key characteristics:  
 – ownership remains with the consigning customer until sale,  
 – Crurated acts solely as selling agent,  
 – bottles are tracked as third-party owned inventory under Crurated custody,  
 – Offers may expose this stock across selected channels (e.g. B2B, restaurants).

When a sale occurs:  
 – ownership transfers directly from the consigning customer to the buyer,  
 – custody may remain with Crurated or move at shipment,  
 – no vouchers are issued, as no deferred delivery obligation is created by Crurated.

This model supports secondary liquidity for members while preserving clear ownership, custody, and accounting boundaries.

---

## **7.9 Voucher suspension during external trading**

Vouchers may be traded on external platforms that are not part of the ERP.

During external trading, a voucher remains an authoritative entitlement in the ERP but is temporarily **suspended**.

While suspended:

* the voucher cannot be redeemed, gifted, or transferred  
* it continues to count toward committed inventory protection

Upon notification of trade completion:

* the ERP updates the voucher holder and reactivates the voucher  
* allocation lineage, unit economic value, and voucher history remain unchanged

---

## **7.10 Voucher binding mode**

Vouchers represent bottle-equivalent entitlements and are unbound from physical inventory by default.

A voucher may optionally enter an **early binding** mode when the customer requests personalised bottling.

In this case:

* the voucher is bound to a specific physical unit at serialization time

Early binding is permitted only for personalised bottling and does not occur at sale time.

---

## **7.11 Interaction with other modules**

* **Module S**: requests allocation capacity and constraint validation  
* **Module E**: confirms payment and triggers voucher issuance  
* **Module D**: uses allocation consumption to generate POs and bottling instructions  
* **Module B**: manages physical stock, containers, and serialization  
* **Module C**: redeems vouchers and performs late binding

---

## **7.12 Governance and invariants**

Non-negotiable rules:

* No voucher without explicit sale confirmation  
* Temporary reservations do not create commitments  
* No overselling beyond allocation  
* One voucher \= one bottle OR one bottle-equivalent  
* Allocation happens at Bottle SKU level  
* Allocation constraints are authoritative  
* Voucher-to-bottle binding is always late  
* Early binding is permitted only for personalised bottling and is otherwise forbidden by design  
* Allocation constraints must never be silently relaxed; changes in commercial intent (e.g. releasing vertical-only stock for loose sale) require closing existing allocations and issuing new allocations with updated constraints.

---

## **7.13 Why this module is critical**

Module A:

* prevents overselling across channels  
* enables selling before stock exists  
* supports liquid and consignment models  
* enables trading without operational chaos  
* preserves financial and contractual correctness

Most of the structural complexity of the business belongs here.

# **8\. Module D — Procurement & Inbound** {#8.-module-d-—-procurement-&-inbound}

## **8.1 Purpose and scope**

Module D governs how **commercial commitments turn into physical supply**.

It answers the questions:

* When and why do we source wine from producers or suppliers?

* How do vouchers and allocations translate into procurement actions?

* How do we record physical inbound events correctly?

* How do we prepare stock for serialization and downstream operations?

* How are liquid sales converted into bottled inventory under controlled rules?

Module D is the **execution layer for sourcing and inbound**, sitting between:

* demand signals (allocations and vouchers — Module A),

* physical inventory and serialization (Module B),

* and financial ownership and accounting (Module E).

**Customer-driven bottling & personalisation (liquid products)**

For liquid (pre-bottling) sales, Module D:

* collects post-sale customer bottling and conditioning preferences,

* validates them against producer and allocation constraints,

* generates binding bottling instructions.

When personalisation is requested, Module D generates **explicit bottling and labelling instructions** that require Module B to bind the resulting serialized bottle(s) to the originating voucher(s) at serialization time.

---

## **8.2 What Module D is — and is not**

**What Module D does**

* Manages procurement intents and sourcing decisions

* Generates purchase orders when required

* Manages bottling instructions for liquid sales

* Tracks producer bottling deadlines and customer choices

* Orchestrates inbound flows into warehouses

* Records sourcing context and ownership flags

**What Module D does not**

* Decide how stock is sold (Module S)

* Decide commercial models (Module S)

* Issue vouchers or manages allocations (Module A)

* Serialize bottles or manage inventory (Module B)

* Plan shipments (Module C)

---

## **8.3 Core design principles**

### **8.3.1 Procurement is demand-driven, not inventory-driven**

Procurement actions in the ERP are driven by explicit demand signals, not by inventory availability.

Module D initiates sourcing activities only in response to one of the following **procurement triggers**:

* **Voucher-driven demand**  
  Procurement initiated as a direct consequence of voucher issuance following a confirmed sale.

* **Allocation-driven demand**  
  Procurement initiated based on allocation planning thresholds, forecasted depletion, or planned replenishment of sellable supply.

* **Strategic or manual demand**  
  Procurement initiated through an explicitly approved stock decision, independent of immediate sales.

* **Contractual demand**  
  Procurement initiated to satisfy pre-agreed contractual obligations with roducers or partners.

Each procurement intent records **why** sourcing occurred, ensuring traceability between:

* what was promised,

* why it was sourced,

* and under which commercial conditions.

---

### **8.3.2 Sourcing and selling models are distinct**

**How wine is sourced** and **how wine is sold** are different decisions.

Sourcing models include:

* owned purchase

* passive consignment (stock with producer or warehouse)

* third-party custody

Selling models include:

* voucher-based deferred fulfillment

* active consignment (sell-through)

* direct shipment

**Active consignment is not a sourcing model**.  
It is a commercial and fulfillment decision applied to stock already sourced via one of the above methods. In active consignment models, procurement intents are strategic or replenishment-driven and never voucher-driven.

---

## **8.4 Core entities**

---

### **8.4.1 Procurement Intent**

A **Procurement Intent** is the canonical object that links demand (allocations/vouchers) to sourcing actions (POs, inbound, bottling instructions). It represents the decision to source wine.

Procurement Intents may be created as a result of:

* **voucher-driven demand** (post-sale sourcing),

* **allocation-driven demand** (planned or threshold-based replenishment),

* **strategic or manual demand** (approved stocking decisions),

* **contractual demand** (obligations defined outside of individual sales).

A Procurement Intent exists **before** any purchase order, bottling instruction, or physical movement is created, and acts as the canonical link between demand and execution.

**Key fields**

* procurement\_intent\_id

* product\_reference

* quantity (bottles or bottle-equivalents)

* trigger\_type (voucher-driven / strategic / manual)

* sourcing\_model (purchase / passive consignment / third-party custody)

* preferred inbound location

* status (draft / approved / executed)

Procurement intent exists **before** any PO or physical movement.

---

### **8.4.2 Purchase Order (PO)**

A **Purchase Order** is created when sourcing requires a contractual purchase or ownership transfer.

Multiple procurement intents may be consolidated into a single PO, while preserving intent-level traceability.

**Key fields**

* po\_id

* supplier / producer

* product\_reference

* quantity

* unit cost

* currency

* incoterms / logistics terms

* ownership\_transfer (yes/no)

* expected delivery window

* linked procurement intent(s)

* status (draft / sent / confirmed / closed)

Not all procurement intents require a PO.

---

### **8.4.3 Bottling Instruction (Liquid Sales)**

A **Bottling Instruction** defines how liquid wine is converted into bottles.

Important clarification:

Bottling instructions may be issued **well after vouchers are sold**, based on producer-defined deadlines.

**Key fields**

* wine\_variant

* total bottle-equivalents

* allowed formats

* allowed case configurations

* bottling deadline (authoritative)

* label customization deadline (authoritative)

* customer preference status (pending / confirmed / defaulted)

* default bottling rule

* delivery location (for serialization)

* `source_allocation_ids` (one or many) and/or `voucher_batch_id` / `voucher_ids` (aggregated)

**Bottling logic**

* Vouchers are sold without format commitment

* Producer defines a bottling deadline

* Customers must express preferences before that deadline

* If no preference is received, a predefined default bottling applies

* Bottling instructions may aggregate demand across customers

This makes liquid sales operationally safe and enforceable.

---

## **8.5 Supported sourcing scenarios**

---

### **8.5.1 Owned stock (classic purchase)**

Flow:

1. Allocation exists

2. Vouchers sold or stock decision approved

3. Procurement intent created

4. PO issued

5. Wine delivered

6. Inbound recorded

7. Stock prepared for serialization

---

### **8.5.2 Passive consignment — stock with producer**

Flow:

1. Allocation exists

2. Vouchers sold

3. Procurement intent created

4. PO issued after sale (if required)

5. Ownership transfers later

6. Wine inbounded when available

---

### **8.5.3 Passive consignment — stock already in warehouse**

Flow:

1. Wine inbounded without ownership transfer

2. Allocation exists

3. Vouchers sold

4. PO issued later or never

Module D supports inbound **before** procurement finalization.

In such cases, inbound stock must be recorded with explicit ownership and sourcing status (e.g. “in custody, ownership pending”) and must not be treated as Crurated-owned inventory until ownership transfer is confirmed.

---

### **8.5.4 Selling stock under active consignment**

Active consignment applies to stock that has already been:

* purchased, or

* passively consigned, or

* otherwise sourced.

Flow:

1. Stock is procured and inbounded

2. Stock is placed with a consignee (restaurant, partner)

3. Ownership remains with Crurated

4. No vouchers are issued

5. Sales are reported after they occur

6. Periodic invoicing follows

Module D handles sourcing decisions and inbound preparation; custody transfers and placement movements are executed and recorded in Module B.

---

### **8.5.5 Third-party custody**

Flow:

1. Wine inbounded on behalf of third party

2. Ownership never transfers

3. Stock is segregated

4. Sales (if any) are restricted

Procurement may not occur at all in this scenario.

---

## **8.6 Inbound processing**

### **8.6.1 Inbound event**

An **Inbound Event** records the physical arrival of wine into a warehouse.

Inbound data is **originated and operationally confirmed by the WMS** and reflected in Module D as the authoritative inbound record.

**Key fields**

* inbound\_id

* warehouse

* product\_reference

* quantity

* packaging (cases / loose)

* sourcing context

* ownership flag

* received date

Inbound is a **physical fact**, not a commercial or financial decision.

**Relationship with WMS**

* The WMS executes and confirms physical receipts  
* Module D records inbound events, sourcing context, and ownership intent  
* Module B consumes confirmed inbound events to create inventory and serialization records

---

### **8.6.2 Serialization routing (configurable rule)**

Current rule:

**All bottles requiring serialization must transit through France.**

Important nuance:

* This rule is **configurable**, not hard-coded.

* France is the only serialization hub today.

* The model allows future authorization of other locations.

Module D must:

* enforce current serialization routing rules,

* prevent accidental bypass,

* allow future configuration without redesign.

---

## **8.7 Multiple allocations converging into one inbound**

It is common that:

* multiple allocations,

* from different moments,

* result in a single physical delivery.

Module D:

* records the inbound batch once,

* preserves linkage to procurement intents and sourcing context,

* allows downstream allocation-aware reporting.

Physical fungibility does not erase commercial traceability.

---

## **8.8 Interaction with other modules**

* **Module A**  
  Drives procurement demand and constraints.

* **Module B**  
  Receives inbound stock for serialization and inventory creation.

* **Module C**  
  Uses prepared inventory later for fulfillment.

* **Module E**  
  Uses PO and ownership data for accounting treatment.

---

## **8.9 Governance and invariants**

**Non-negotiable rules**

1. Sourcing and selling models are distinct

2. Bottling deadlines are authoritative

3. Inbound does not imply ownership

4. Serialization routing rules are enforced

5. Third-party stock is never mixed

---

## **8.10 Why this module matters**

Module D:

* connects promises to physical reality,

* enables liquid sales safely,

* supports consignment without hacks,

* preserves sourcing traceability,

* prevents operational and financial errors.

Without a strong procurement and inbound module, the entire ERP collapses under real-world complexity.

# **9\. Module B — Inventory, Serialization & Provenance** {#9.-module-b-—-inventory,-serialization-&-provenance}

## **9.1 Purpose and scope**

Module B is responsible for managing **physical reality** inside the ERP.

It answers the questions:

* *What physical wine exists, where is it, and in what form?*

* *When does wine become an individually identifiable bottle?*

* *How do we track custody, movements, and provenance over time?*

* *How do we preserve operational fungibility while enabling full traceability?*

Module B is the **physical system of record**.  
It represents **what exists in the real world**, independently from:

* commercial promises (Module A),

* customer rights (Module K),

* or fulfillment intent (Module C).

Inventory is tracked exclusively at the level of physical entities, namely bottles and intrinsic cases. **Composite sellable SKUs (such as mixed cases)** do not exist as inventory objects and do not have physical representation prior to fulfillment.

Serialized bottles and intrinsic cases are the only objects that carry custody, location, and provenance information. Commercial bundling is resolved downstream during fulfillment without introducing additional inventory abstractions.

---

## **9.2 What Module B is — and is not**

**What Module B does**

* Records physical inbound events

* Tracks stock across all locations

* Manages bottles, cases, and packaging

* Performs and records serialization

* Maintains chain of custody and provenance

* Integrates with warehouse management systems (WMS)

**What Module B does not**

* Decide what can be sold (Module S)

* Represent sellable rights (Module A)

* Bind bottles to customers early

* Plan or execute shipments (Module C)

* Issue invoices or financial records (Module E)

* Decide which specific bottles are assigned to which customer entitlements; bottle selection is orchestrated by Module C.

---

## **9.3 Core design principles**

### **9.3.1 Physical truth is separate from commercial truth**

The system deliberately separates:

* **commercial units** (allocations, vouchers),

* **physical quantities** (inbound wine),

* **individual physical objects** (serialized bottles).

A bottle-equivalent sold is **not** a bottle in inventory.  
A bottle in inventory may **not yet** be sold.

This separation is essential for:

* late binding,

* operational efficiency,

* correct provenance.

**Committed inventory protection**

For each allocation lineage, the system maintains a committed quantity equal to the number of unredeemed vouchers.  
Physical inventory belonging to that lineage is split into:

* committed quantity (reserved for fulfillment), and  
* free quantity (available for non-fulfillment consumption).

Any operation that would reduce free quantity below zero must be blocked or require explicit exception handling.

---

### **9.3.2 Individual identity begins at serialization**

Before serialization:

* wine exists physically,

* but bottles are **not individually identifiable**.

After serialization:

* each bottle becomes a first-class object,

* with a unique serial and digital identity.

The ERP must not pretend individuality exists before it does physically.

---

### **9.3.3 Event Inventory Consumption**

Wine used for events is treated as **internal inventory consumption** and not as customer fulfillment. Bottles and cases may be shipped internally from Crurated warehouses to event locations while **ownership remains with Crurated** and custody is temporarily transferred to the event location.

Inventory used at events:

* is selected from owned stock only,  
* is never associated with vouchers or customer entitlements,  
* is never bound to a customer or shipment for delivery,  
* is marked as **consumed** at the moment it is opened or used during the event.

Consumption for events results in:

* a reduction of physical inventory,  
* enforcement of case integrity rules (cases opened for events are considered broken),  
* recording of an explicit consumption reason (e.g. `EVENT_CONSUMPTION`) for traceability and reporting.

Event-related inventory consumption is irreversible and must be recorded as an immutable historical fact.

---

## **9.4 Core physical entities**

---

### **9.4.1 Warehouse / Location**

Represents any physical location where wine may be stored.

**Examples**

* France main warehouse

* Satellite warehouses (IT, HK, UAE)

* Consignee locations (restaurants)

* Third-party storage

**Key fields**

* location\_id

* location\_type (main WH / satellite / consignee / third party)

* serialization\_authorized (yes / no)

* country

* linked WMS (if applicable)

---

### **9.4.2 Inbound Batch**

An **Inbound Batch** represents a physical receipt of wine.

Important clarification: Inbound batches are typically **initiated by WMS events**,  
but are **authoritative ERP records**, enriched with business context.

Inbound batches must retain references to their originating allocations or procurement intents so that allocation lineage can be preserved and propagated to serialized bottles.

**Key fields**

* inbound\_batch\_id

* source (producer / supplier / transfer)

* product\_reference

* quantity (bottles or cases)

* packaging details

* sourcing context (from Module D)

* ownership flag

* received\_date

* receiving location

Inbound batches:

* may aggregate multiple procurement intents,

* may relate to multiple allocations,

* do **not** create individual bottles.

They represent **physical quantities without individual identity**.

---

### **9.4.3 Serialized Bottle**

A **Serialized Bottle** is an individually identifiable physical object.

**Key fields**

* bottle\_id (internal)

* serial\_number (unique, immutable)

* wine\_variant

* format

* inbound\_batch\_id

* current\_location

* ownership (Crurated / third party)

* custody holder

* serialized\_at

* current\_state (stored / reserved for picking / shipped)

**Serialized bottles exist in the ERP only after serialization.**

Before serialization, wine is tracked only as inbound quantities.

**Allocation Lineage on Physical Inventory**

Each serialized bottle (and any case or container representation) must carry an immutable reference to its allocation lineage (`allocation_id`). Bottles originating from different allocations must remain distinguishable throughout their lifecycle, even when they reference the same product.

Allocation lineage on physical inventory is used to:

* preserve provenance and contractual correctness,  
* ensure accurate supplier and margin reporting,  
* enforce correct voucher-to-bottle binding during fulfillment.

Physical units from different allocations must never be substituted during fulfillment.

---

### **9.4.4 Case**

A **Case** is a physical container grouping serialized bottles.

**Key fields**

* case\_id

* case\_configuration

* bottle\_ids

* is\_original (yes/no)

* is\_breakable (yes/no)

* integrity\_status (intact / broken)

* current\_location

Cases never replace bottle-level tracking.

**Special cases/containers from producers (e.g. verticals)**

Physical containers provided by producers (including vertical cases) are tracked for warehouse handling, integrity, and fulfillment preferences.

Physical container structure or integrity does not define or override commercial sellability rules, which remain governed by allocation constraints and sellable SKU definitions.

---

## **9.5 Serialization process**

### **9.5.1 Serialization event**

Serialization is the moment when:

* a physical label is applied,

* a serial number is assigned,

* a digital identity is created.

Characteristics:

* performed only at authorized locations,

* irreversible,

* audited.

Serialization converts:

* inbound batch quantities  
  into

* serialized bottle records.

During serialization, if a bottling assignment exists for a physical unit (personalized bottling), the serialized bottle must be bound to the corresponding voucher. If no assignment exists, serialized bottles remain unbound and eligible for late binding during fulfillment. Allocation lineage must be preserved in both cases.

---

### **9.5.2 Provenance NFT**

For each serialized bottle:

* a provenance NFT is minted,

* owned by Crurated,

* recording:

  * custody with producer,

  * transfer to warehouse,

  * future shipment events.

This NFT:

* supports transparency,

* backs the bottle page,

* does **not** represent ownership or redemption rights.

NFT minting is a separate process from serialization and can happen in a different moment in time

---

### **9.5.3 Non-Serialized (Fungible) Case Inventory**

While serialization is the default model, certain products may be intentionally managed as non-serialized inventory. In this case, inventory is tracked at case level only, with bottles treated as fungible within an intact case. It is an **explicit exception to the default serialization model** and must be deliberately configured.

Non-serialized inventory:

* carries immutable allocation lineage at case level,  
* is eligible only for case-based selling and fulfillment,  
* does not support per-bottle provenance, trading, or personalization,  
* is subject to committed inventory protection at case level.

The decision to serialize or not serialize inventory is determined by product and allocation configuration and must be explicit.

---

## **9.6 Bottle Page integration**

Each serialized bottle:

* carries a QR/NFC tag,

* links to a **public bottle page**.

The bottle page:

* is read-only,

* shows producer and wine data,

* links to blockchain provenance,

* does not expose customer identity.

Module B is the authoritative data source.

---

## **9.7 Multi-warehouse operations and movements**

### **9.7.1 Internal transfers**

Serialized bottles and cases may be transferred:

* between warehouses,

* between custody holders.

Transfers:

* are recorded as events,

* update location and custody,

* do not affect vouchers or customer rights.

---

### **9.7.2 Consignment placements**

For active consignment:

* serialized bottles are moved to consignee locations,

* ownership remains with Crurated,

* custody changes.

Module B tracks:

* exact serials at each consignee,

* under which agreement.

---

## **9.8 Fungibility vs traceability**

Operationally:

* bottles of the same wine are **fungible until binding**.

Systemically:

* every bottle is **always traceable**.

This duality enables:

* efficient warehouse operations,

* late binding,

* strong provenance guarantees.

Fungibility applies only within the same allocation lineage; physical units from different allocations are never substitutable, even if identical in appearance.

---

## **9.9 Interaction with other modules**

* **Module D**  
  Provides inbound batches and sourcing context.

* **Module A**  
  Never assigns bottles; only tracks abstract rights.

* **Module C**  
  Selects specific serialized bottles during fulfillment.

* **Module E**  
  Uses shipment events for financial recognition.

---

## **9.10 Governance and invariants**

**Non-negotiable rules**

1. Individual bottles exist as first-class, individually identifiable system entities only after serialization

2. Serial numbers are immutable

3. Inbound ≠ serialization

4. Bottles are atomic; cases are containers

5. Provenance records are append-only

Violating these breaks trust and operational correctness.

---

## **9.11 Why this module is critical**

Module B:

* anchors the ERP in physical reality,

* enables chain-of-custody transparency,

* supports complex custody and consignment models,

* allows operational efficiency without losing control,

* underpins fulfillment, trading, and trust.

Without a rigorous inventory and serialization layer, the rest of the system cannot be safely executed.

# **10\. Module C — Fulfillment & Shipping** {#10.-module-c-—-fulfillment-&-shipping}

## **10.1 Purpose and scope**

Module C governs **how customer rights are converted into physical shipments**.

It answers the questions:

* *When and how does a customer request delivery?*

* *Which exact physical bottles are selected for shipment?*

* *How are shipments planned, executed, and tracked?*

* *When do vouchers get redeemed and ownership transfer occur?*

Module C is the **execution layer for delivery**, sitting between:

* abstract customer rights (Module A),

* physical inventory (Module B),

* and financial completion (Module E).

**Exclusions — Events and Internal Shipments**

The Fulfillment module governs **customer deliveries only** and applies exclusively to the redemption of vouchers resulting in shipment to customers.

Shipments related to events are **internal logistics movements** and must not be handled through the Fulfillment module. Event-related shipments:

* do not redeem vouchers,  
* do not trigger ownership transfer,  
* do not create customer delivery records,  
* do not generate fulfillment invoices.

Internal shipments to event locations are handled as inventory movements and are accounted for as operational or event-related expenses.

**OVERALL SUMMARY (MODELS)**

The ERP supports **three physical fulfillment models** under one coherent ERP:

1. **Serialized bottles** (default, high provenance)

2. **Early-personalised serialized bottles** (controlled early binding)

3. **Non-serialized fungible case inventory** (operational efficiency)

All three respect:

* allocation lineage  
* entitlement guarantees  
* committed inventory protection,  
* ERP authority.

---

## **10.2 What Module C is — and is not**

**What Module C does**

* Accepts customer shipping requests

* Creates and manages Shipping Orders (SO)

* Performs late binding (voucher → bottle)

* Plans and authorizes shipments

* Integrates with WMS for pick/pack/ship

* Tracks shipment execution

* Triggers voucher redemption, ownership transfer, and provenance updates

**What Module C does not**

* Decide what can be sold (Module S)

* Issue vouchers (Module A)

* Track physical stock existence (Module B)

* Decide pricing or take payments (Module E)

---

## **10.3 Core design principles**

### **10.3.1 Shipping is customer-initiated**

A shipment occurs **only when the customer explicitly requests it**.

Selling a voucher does **not** imply:

* immediate shipment,

* bottle assignment,

* logistics planning.

This supports:

* long-term storage,

* trading before shipment,

* inventory flexibility across locations.

---

### **10.3.2 Redemption happens at shipment**

**Redemption is not a standalone process.**

Key rule:

**Vouchers are redeemed only when a Shipping Order is shipped.**

Redemption occurs upon confirmed shipment execution (carrier handover), independent of payment status and prior to final delivery

Consequences:

* no voucher is redeemed when an SO is created

* no bottle is bound to a voucher before picking

* ownership transfer and financial recognition occur at shipment

This aligns legal, operational, and financial truth.

---

### **10.3.3 Late binding is enforced here and only here**

Module C is the **only module** allowed to bind:

* a voucher

* to a specific serialized bottle.

Binding:

* happens during picking / shipment execution,

* uses real-time warehouse availability,

* is irreversible once shipped.

**Early Binding exception**

Early binding is forbidden by design and permitted only in the specific case of personalized bottling initiated in Module D.

If a voucher is already bound to a serialized bottle due to personalised bottling, Module C must skip late binding and validate only integrity and eligibility before shipment.

---

### **10.3.4 Non serialized inventory**

For non-serialized inventory, fulfillment binds vouchers to cases rather than bottles and must respect case integrity constraints.

---

## **10.4 Core entities**

---

### **10.4.1 Shipping Order (SO)**

A **Shipping Order (SO)** represents:

* the customer’s request to receive wine, and	

* the operational authorization to execute shipment.

**Key fields**

* so\_id

* customer\_id

* vouchers (list)

* destination address

* shipping method

* packaging preferences (cases / loose)

* source warehouse (selected or resolved)

* status:

  * draft

  * planned

  * picking

  * shipped

  * completed

An SO may include:

* multiple vouchers,

* multiple wines,

* multiple cases.

**Voucher eligibility for fulfillment**

Prior to shipment creation and voucher locking, Module C must validate that vouchers are in an eligible state for fulfillment. Vouchers that are cancelled, redeemed, locked by another process, or suspended due to gifting or external trading must be treated as ineligible and must not be locked or redeemed.

When a Shipping Order enters the *picking* state, included vouchers are locked to prevent concurrent fulfillment or transfer; vouchers are redeemed only upon successful shipment.

---

### **10.4.2 Shipment Event**

A **Shipment Event** records the physical execution of a Shipping Order.

**Key fields**

* shipment\_id

* so\_id

* carrier

* tracking number

* shipped\_date

* origin location

* destination

* shipped bottle serials

The shipment event is the **point of no return**:

* vouchers are redeemed,

* ownership transfers,

* provenance is updated,

* accounting events are triggered.

Ownership transfer from Crurated to the customer occurs at the shipment event.

---

## **10.5 Bottle assignment (late binding)**

Bottle assignment occurs **during picking**, not earlier.

Rules:

* one voucher → one serialized bottle

* bottles must satisfy:

  * ownership constraints,

  * custody constraints,

  * consignment rules,

  * case integrity rules

* once assigned and shipped, binding is final

This creates:

* voucher → bottle linkage

* bottle → customer linkage

**Lineage-Constrained Late Binding**

During fulfillment planning and execution, vouchers must be bound only to physical inventory units that match the voucher’s allocation lineage. Module C must request eligible inventory from Module B using allocation lineage as a mandatory constraint.

Late binding allows operational flexibility only within the same allocation lineage. Substitution across allocations is not permitted under any circumstance.

Fulfillment assumes that committed inventory protection is enforced by Module B. If eligible physical inventory for the required lineage is not available, the shipment must not proceed and the situation must be surfaced as a supply exception rather than resolved through substitution.

---

## **10.6 Bottle selection logic**

Selection criteria may include:

* warehouse proximity to destination

* preservation of original cases when possible

* bottle accessibility

* allocation lineage (if required for reporting)

* regulatory or contractual constraints

The ERP:

* requests eligible inventory from Module B

* validates fulfillment constraints

* instructs the WMS.proposes eligible bottles,

The WMS executes; the ERP remains authoritative.

---

## **10.7 Case handling during fulfillment**

Rules:

* original cases are preserved when possible,

* cases may be broken if partial shipment is required,

* breaking a case permanently removes its integrity,

* customers lose the right to ship the case once broken.

Case handling decisions are:

* explicit,

* auditable,

* visible to operators and customers.

Even when fulfilling by case, bottle-level identity and lineage remain authoritative in the ERP.

**Fulfillment of composite sellable SKUs**

For composite sellable SKUs (mixed cases), fulfillment consists of selecting the required number and type of bottles according to the bundle definition and assembling the case during pick and pack. Bottle selection follows late binding principles and optimizes for warehouse efficiency and case integrity where applicable.

Composite cases do not impose long-term physical constraints: once assembled and shipped, they may be broken without affecting producer integrity, unlike intrinsic cases received from origin.

---

## **10.8 Multi-warehouse fulfillment**

### **10.8.1 Warehouse selection**

A Shipping Order may be fulfilled from:

* France main warehouse,

* satellite warehouses,

* consignee locations (active consignment).

Selection considers:

* stock availability,

* shipping cost and timing,

* customer expectations,

* regulatory constraints.

---

### **10.8.2 Transfers vs direct shipment**

If stock is not at the optimal location:

* an internal transfer may be executed first, or

* direct shipment may occur if allowed.

Module C orchestrates decisions; Module B records movements.

---

## **10.9 Integration with WMS (Logilize and others)**

**Authority model**

* ERP plans and authorizes

* WMS executes and reports

Flow:

1. ERP sends Shipping Order to WMS

2. WMS performs picking and packing

3. WMS confirms picked serials

4. ERP validates selections

5. Shipment is executed and confirmed

Discrepancies are explicitly handled.

---

## **10.10 Special fulfillment scenarios**

---

### **10.10.1 Active consignment shipments**

In active consignment scenarios, outbound movements represent authorized stock placement rather than customer delivery. These movements do not constitute fulfillment and therefore do not trigger voucher redemption, ownership transfer, or customer delivery records, regardless of whether the stock is Crurated-owned or customer-owned.

---

### **10.10.2 Third-party stock**

For third-party custody:

* shipping rights may be restricted,

* SO creation may be blocked or limited,

* accidental shipment is prevented by ownership checks.

---

### **10.11 Governance and invariants**

**Non-negotiable rules**

1. No shipment without a Shipping Order

2. Redemption occurs only at shipment

3. Late binding only in Module C

4. One voucher → one serialized bottle

5. ERP authorizes; WMS executes

---

### **10.12 Why this module is critical**

Module C:

* closes the loop between promise and delivery,

* enforces late binding operationally,

* protects warehouse efficiency,

* ensures legal and financial correctness,

* delivers the customer experience.

Without a strong fulfillment and shipping module, all upstream rigor collapses at the last mile.

# **11\. Module E — Accounting, Invoicing & Payments** {#11.-module-e-—-accounting,-invoicing-&-payments}

## **11.1 Purpose and scope**

Module E governs **financial truth** in the ERP.

It answers the questions:

* *What do we invoice, when, and for what reason?*

* *When does a customer payment create a right, a service, or revenue?*

* *How do financial events align with commercial and operational events?*

* *How do we integrate Stripe and Xero without losing ERP authority?*

Module E is the **financial system of record inside the ERP**, even though:

* payments are executed via Stripe or bank transfer,

* accounting entries are posted to Xero.

Mod E determines invoice existence and type, invoice timing and lifecycle, linkage between invoices and ERP events (membership, vouchers, shipment, custody). However, invoices can be rendered, numbered, and legally issued using Xero, which acts as: the statutory accounting system, the general ledger, the legal invoice document engine.

**Event-Related Inventory Costs**

Wine and logistics costs associated with events are treated as **internal expenses** and not as cost of goods sold for customer deliveries. Inventory reductions resulting from event consumption are posted to appropriate expense accounts (e.g. events, marketing, hospitality) and do not generate revenue or VAT events.

---

## **11.2 What Module E is — and is not**

**What Module E does**

* Issues and manages invoices

* Tracks payments, refunds, and credit notes

* Handles multiple invoice types

* Supports multi-currency transactions

* Calculates service-based charges (e.g. storage)

* Integrates with Stripe and Xero

* Enforces financial consistency with ERP events

**What Module E does not**

* Decide what can be sold (Module S)

* Create vouchers (Module A)

* Track inventory (Module B)

* Execute shipments (Module C)

---

## **11.3 Core financial principles**

### **11.3.1 Explicit separation of economic events**

Each major economic event in the Crurated business model generates a **distinct invoice type**. These events are conceptually independent and must never be merged or inferred from one another.

These events are:

* access to the platform services (membership, subscriptions),

* creation of a future delivery obligation (voucher sale),

* execution of a physical delivery (shipments, logistics, tax & duties),

* provision of custodial services (storage),

* provision of non-product services (events, tastings, hospitality, bespoke services).

Each event has its own timing, own tax treatment, own revenue recognition logic, and its own operational dependencies. They are **not interchangeable** and must be tracked independently.

---

### **11.3.2 Revenue follows real-world events, not UI actions**

* Selling a voucher ≠ shipping wine

* Paying an invoice ≠ revenue recognition

* Creating a Shipping Order ≠ redemption

Module E anchors finance to **authoritative ERP events** (Modules A, C, B, K), not front-end actions.

---

## **11.4 Invoice types (core model)**

Module E supports **five primary invoice types**, each tied to a specific and non-overlapping business event.

---

### **11.4.1 INV0 — Membership & Service Invoice**

**Purpose**  
INV0 represents payment for **access to platform services (membership)**, not wine.

It is used for:

* annual or periodic membership fees (B2C)

* subscription models (annual, quarterly, one-off) \- *if applicable*

INV0 never:

* creates vouchers,

* affects allocations,

* transfers ownership of wine.

---

### **11.4.2 INV1 — Voucher Sale Invoice**

**Purpose**  
INV1 represents the sale of **vouchers**, i.e. future redemption rights and delivery obligation.

It creates:

* a customer claim on future wine delivery,

* vouchers in Module A after payment.

INV1 does **not**:

* trigger shipment,

* bind bottles,

* include VAT and duties,

* transfer ownership.

---

### **11.4.3 INV2 — Shipping & Redemption Invoice**

**Purpose**  
INV2 represents:

* logistics services (shipping, handling, insurance),

* Application of VAT, excise and duties,

* and the moment of **voucher redemption** and ownership transfer.

INV2 exists **only if shipment occurs**, is linked to a specific Shipping Order and cannot be issued without an underlying voucher redemption or sell-through event.

---

### **11.4.4 INV3 — Storage Fee Invoice**

**Purpose**  
INV3 represents **custodial services**, i.e. the safekeeping of physically stored bottles over time.

It is used for:

* periodic storage fees (e.g. semi-annual),

* custody of serialized bottles held in Crurated warehouses.

INV3:

* is usage-based and retrospective,

* is independent from voucher sale or redemption,

* does not represent product revenue,

* may trigger operational blocks if unpaid.

---

### **11.4.5 INV4 — Service and Events Invoice**

**Purpose**  
INV4 represents **non-product, non-custodial services**, including:

* Events and tastings, 

* Hospitality and experiential services

* Other bespoke services.

INV4:

* Does not create product rights

* Does not affect inventory

* May consume inventory as internal cost

---

## **11.5 Core entities**

### **11.5.1 Invoice**

An **Invoice** is a financial and legal document **authoritatively created and managed by the ERP**.

It represents a claim for payment arising from a specific economic event and is synchronized to Xero for statutory accounting and reporting.

**Key fields**

* invoice\_id

* invoice\_type (INV0 / INV1 / INV2/ INV3/ INV4)

* customer\_id

* currency

* invoice\_lines

* billing\_period (if applicable)

* tax breakdown

* total\_amount

* status (draft / issued / paid / cancelled)

* accounting\_reference (Xero)

---

### **11.5.2 Invoice status and lifecycle**

Invoice status is **owned by the ERP** and reflects the financial truth of the invoice.

Supported states:  
 – `draft` (created but not legally issued)  
 – `issued` (legally issued; posted to Xero)  
 – `partially_paid`  
 – `paid`  
 – `cancelled`  
 – `credited`

Lifecycle rules:  
 – only `issued` invoices are posted to Xero,  
 – payments may only be applied to `issued` invoices,  
 – operational or eligibility blocks may depend on invoice status (via Module K),  
 – cancellation or crediting is always explicit and auditable.

---

### **11.5.3 Invoice line**

Invoice lines explicitly describe **what is being charged**,not how it is fulfilled

Examples:  
 – membership fee (INV0)  
 – voucher for wine X (INV1)  
 – shipment to country Y (INV2)  
 – storage service for period Z (INV3)  
 – event participation fee (INV4)

Invoice lines never reference:  
 – bottle serials,  
 – physical inventory,  
 – warehouse locations.

---

## **11.6 Invoice issuance triggers and timing**

Each invoice type is issued only in response to a **specific authoritative ERP event**.

– **INV0 (Subscription & Membership)**  
 Issued according to membership or subscription policy (e.g. annual or periodic).  
 Payment status may affect eligibility and access via Module K.

– **INV1 (Voucher Sale)**  
 Issued after successful checkout or approved order confirmation, according to applicable payment terms.  
 Voucher issuance in Module A occurs only after payment confirmation.

– **INV2 (Shipment & Taxes)**  
 Issued only after a Shipping Order is executed.  
 VAT, excise, and duties are applied at this stage, based on shipment characteristics.

– **INV3 (Storage Fees)**  
 Issued periodically (e.g. semi-annually), calculated retrospectively based on actual custody duration and quantities.

– **INV4 (Services & Events)**  
 Issued based on service delivery or event participation rules.

This section contains **no commercial or accounting logic**, which remains defined by the invoice type itself and by accounting policy.

---

## **11.7 Payments**

Module E records payment truth against ERP invoices. Payments may be executed through external rails (Stripe or bank transfer), but **invoice state and customer financial status remain authoritative in the ERP**.

### **11.7.1 Stripe payments**

Stripe is used for:

* card payments,

* payment intents,

* recurring subscription execution.

Flow:

1. ERP creates an invoice (`issued`) and a payment request (e.g. Stripe Payment Intent).

2. Stripe executes the payment.

3. Stripe emits webhook events.

4. ERP validates and applies the payment to the invoice and updates invoice status (`paid` / `partially_paid`).

**Authority and invariants**

– Stripe never defines invoice existence, invoice type, tax treatment, or customer eligibility.

– Stripe events may be delayed, duplicated, or arrive out of order and must be handled idempotently.

– ERP invoice status is updated only when payment evidence is confirmed and reconciled to the correct invoice.

---

### **11.7.2 Bank transfers (approved customers)**

For selected customers (typically B2B):  
 – invoices may be issued with payment due terms,  
 – outstanding balances are tracked in the ERP,  
 – credit limits and operational blocks are enforced via Module K.

Bank transfer payments are:  
 – recorded against a specific invoice,  
 – reconciled back to invoice state in the ERP,  
 – synchronized to Xero as part of standard accounting reconciliation.

---

## **11.8 Refunds and credit notes**

All financial reversals and adjustments are handled explicitly through **refunds and credit notes**, under the authority of the ERP.

**Refunds**

Refunds represent the **reversal of a received payment**.

Characteristics:  
 – always linked to an existing invoice,  
 – may be full or partial,  
 – update invoice payment state in the ERP,  
 – are synchronized to Xero for accounting reconciliation.

Refunds do **not**:  
 – cancel the original invoice,  
 – alter the original invoice type,  
 – implicitly reverse operational events.

Any operational consequence (e.g. voucher cancellation, access revocation) must be handled explicitly by the relevant module.

**Credit notes**

Credit notes represent **adjustments to issued invoices**.

They are used for:  
 – pricing corrections,  
 – service adjustments,  
 – goodwill gestures,  
 – returns or partial reversals.

Characteristics:  
 – explicitly reference the original invoice,  
 – preserve the original invoice type (INV0–INV4),  
 – are auditable and traceable,  
 – are synchronized to Xero as accounting documents.

Credit notes may:  
 – reduce or extinguish outstanding balances,  
 – generate refund eligibility,  
 – trigger downstream operational actions via explicit rules.

---

## **11.9 Multi-currency support**

Module E supports invoicing and payments in multiple currencies.

**Pricing authority**  
 – Selling prices, price books, and customer-specific currencies are defined in **Module S (Commercial)**.  
 – Module E does not calculate or optimize prices and does not perform commercial FX logic.

**Financial recording**  
 For each issued invoice, Module E:  
 – records the invoice currency,  
 – captures the applicable exchange rate at issuance time,  
 – stores the base-currency equivalent for accounting and reporting,  
 – synchronizes currency and FX data to Xero.

**Payments**  
 – Payments are accepted and reconciled in the invoice currency.  
 – Any FX differences are handled as accounting adjustments, not commercial logic.

This separation ensures:  
 – commercial consistency across channels,  
 – accurate accounting and reporting,  
 – no leakage of pricing logic into finance.

---

## **11.10 Accounting integration (Xero)**

**Authority model**

* ERP is the **financial authority**

* Xero is the **general ledger and statutory accounting system.**

The ERP:

* defines when an invoice exists and its type (INV0–INV4),

* controls invoice lifecycle and state,

* links invoices to ERP events and customers,

* determines tax applicability and timing.

Xero:

* renders legal invoice documents using compliant templates,

* assigns statutory invoice numbers,

* records accounting entries in the general ledger,

* produces financial statements and tax reports.

---

## **11.11 Special business models and finance**

**Passive consignment**

* INV1 issued on voucher sale

* ownership transfer may occur later

* COGS timing and revenue recognition handled in accounting

---

**Active consignment (sell-through)**

* no vouchers

* no INV1 generated

* sales reported periodically based on actual sell-through

* invoices issued based on agreed settlement cycles with consignor

---

**Third-party custody**

* no vouchers

* no product sales invoices

* Only service invoices (e.g. storage, handling) may be issued, if applicable.

---

## **11.12 Governance and invariants**

**Non-negotiable rules**

1. INV0 governs service access, not product rights

2. INV1 precedes voucher issuance

3. INV2 only after shipment

4. INV3 (storage fees) represents custodial service revenue.

5. ERP is the financial authority

---

## **11.13 Why this module is critical**

Module E:

* aligns finance with real operations,

* supports deferred and recurring revenue,

* enables complex timing models,

* reduces reconciliation risk,

* stands up to audit and due diligence.

Without a rigorous financial module, operational correctness cannot become financial truth.

# **12\. Dashboards, Controls & Insights** {#12.-dashboards,-controls-&-insights}

## **12.1 Purpose and scope**

Section 12 defines how the ERP:

* exposes system state,

* enforces controls,

* supports operators in daily execution,

* and enables management oversight.

Dashboards and controls are **not cosmetic UI elements**.  
They are a **core safety and scalability mechanism** in a complex, multi-model business.

The objective is:

*Make the system observable, controllable, and auditable at all times.*

---

## **12.2 Design principles**

## **12.2.1 Dashboards reflect state, not raw data**

Dashboards must answer operational questions such as:

* *What is blocked?*

* *What is at risk?*

* *What requires immediate action?*

They must not:

* expose raw tables without context,

* require operators to infer system logic.

---

## **12.2.2 Controls are explicit and centralized**

Every critical action must:

* have a clear owner,

* expose its current status,

* enforce eligibility and blocking rules,

* leave a complete audit trail.

Silent failures or hidden logic are explicitly avoided.

---

## **12.2.3 Insights are derived from ERP truth**

All insights:

* derive from authoritative ERP state,

* are explainable,

* are traceable to events and rules.

This is essential for:

* operational trust,

* financial accuracy,

* investor and auditor confidence.

---

## **12.3 UX/UI positioning of dashboards and controls**

From a UX/UI perspective, dashboards and controls are exposed through **two complementary layers**, each serving a different role.

### **12.3.1 Embedded (contextual) controls — operator layer**

Operational checks, warnings, and insights appear **directly within the functional modules** where actions are taken.

Examples:

* allocation health indicators shown inside Allocation views (Module A),

* shipment blockers shown directly on Shipping Orders (Module C),

* bottling deadline alerts shown in Procurement workflows (Module D),

* unpaid fees or compliance holds shown at the moment of action.

These embedded elements act as **guardrails**:

* they prevent mistakes at the point of execution,

* they reduce reliance on tribal knowledge,

* they make incorrect actions difficult or impossible.

This is where **operators spend most of their time**.

---

### **12.3.2 Centralized dashboards — oversight layer**

In addition to embedded controls, the ERP provides **dedicated, cross-module dashboards** for monitoring and escalation.

These views are:

* separate from transactional screens,

* optimized for scanning and prioritization,

* used by managers and leadership.

Their purpose is not to execute actions, but to:

* monitor system health,

* identify emerging risks,

* decide where to intervene.

---

### **12.3.3 Navigation principle**

At a high level:

* **controls live where actions happen**,

* **dashboards live where oversight happens**.

This dual-layer approach aligns with best-in-class ERP UX patterns and avoids overloading any single view with conflicting responsibilities.

---

## **12.4 Cross-module dashboards (executive & ops)**

### **12.4.1 System Health Dashboard**

High-level visibility on:

* allocation utilization and exhaustion risk,

* vouchers outstanding vs redeemable stock,

* inbound vs expected inbound,

* blocked shipments and reasons,

* unpaid invoices affecting operations.

Audience:

* leadership,

* operations managers.

  ### ---

  ### **12.4.2 Risk & Exceptions Dashboard**

Surfaces:

* allocation constraint violations,

* upcoming bottling deadlines,

* unpaid storage fees blocking shipment,

* consignment discrepancies,

* inventory anomalies.

Designed to enable **proactive intervention**, not post-mortems.

---

## **12.5 Module-level dashboards**

Each module exposes dashboards aligned with its responsibilities.

---

### **12.5.1 Module S — Sales & Commercial**

Views:

* allocation exposure by channel,

* price vs market benchmarks,

* campaign coverage by segment,

* channel overlap and cannibalization signals.

---

### **12.5.2 Module A — Allocations & Vouchers**

Views:

* allocation consumption vs limits,

* vouchers outstanding vs redeemed,

* constraint-bound allocations,

* voucher aging.

---

### **12.5.3 Module D — Procurement & Inbound**

Views:

* procurement intents vs execution,

* inbound delays,

* bottling deadlines and customer response status,

* sourcing mix by model.

---

### **12.5.4 Module B — Inventory & Serialization**

Views:

* stock by location, ownership, and custody,

* serialized vs unserialized quantities,

* case integrity status,

* consignment placements.

---

### **12.5.5 Module C — Fulfillment & Shipping**

Views:

* open Shipping Orders,

* blocked shipments and causes,

* warehouse workload,

* case breakage impact.

---

### **12.5.6 Module E — Accounting & Payments**

Views:

* unpaid invoices by type (INV0 / INV1 / INV2),

* aging balances,

* refunds and credit notes,

* revenue split by category (services vs product).

---

## **12.6 Alerts and notifications**

The ERP proactively notifies users when attention is required.

Examples:

* allocation nearing exhaustion,

* bottling deadlines approaching,

* unpaid storage fees blocking shipment,

* WMS discrepancies,

* consignment stock reconciliation gaps.

Alerts are:

* rule-based,

* configurable,

* auditable.

---

## **12.7 Auditability and traceability**

Every dashboard metric and alert can be traced back to:

* a specific rule,

* a specific event,

* a specific state transition.

This enables:

* internal audits,

* external audits,

* investor and partner due diligence.

---

## **12.8 Why this section matters**

Without strong dashboards and controls:

* complexity scales into chaos,

* operators rely on experience instead of systems,

* errors grow with volume.

With them:

* the ERP becomes a **true control tower**,

* not just a transactional backend.

# **13\. Role of AI & Decision Support** {#13.-role-of-ai-&-decision-support}

## **13.1 Why AI is feasible and relevant for this ERP**

The ERP described in this document is **AI-ready by construction**, not by retrofit.

This is a direct consequence of its architectural choices:

* modular domain boundaries,

* explicit business events,

* deterministic state machines,

* clear ownership of rules and authority,

* full auditability of actions and transitions.

These properties are precisely what modern AI systems require in order to be:

* effective,

* explainable,

* safe to operate in regulated, asset-intensive environments.

Unlike legacy platforms—where logic is implicit, state is ambiguous, and decisions are scattered—this ERP produces **high-quality, structured signals** that AI can reason over without guessing.

In this context, AI is not positioned as a replacement for business logic or human decision-making.  
It is positioned as an **augmentation layer** that improves:

* operational efficiency,

* forecasting accuracy,

* anomaly detection,

* and decision quality.

**The ERP remains the system of record and authority.**  
**AI acts as an advisory layer, never as a silent decision-maker.**

---

## **13.2 Positioning principle**

AI components:

* observe ERP state and events,

* analyze patterns and trajectories,

* suggest actions or highlight risks.

They **do not**:

* create legal obligations,

* issue vouchers,

* move inventory,

* bind bottles to customers,

* post accounting entries.

This separation preserves:

* legal correctness,

* auditability,

* trust by operators and regulators.

---

## **13.3 Why this ERP design enables AI safely**

### **13.3.1 Structured domains and explicit ownership**

Each module owns a clearly defined domain:

* Sales decides exposure and pricing,

* Allocations govern commitments,

* Inventory represents physical truth,

* Fulfillment executes delivery,

* Accounting records financial truth.

This clarity allows AI systems to:

* reason within bounded contexts,

* avoid cross-domain confusion,

* generate explainable recommendations.

---

### **13.3.2 Event-driven lifecycle**

Key business events are explicit and time-stamped:

* allocation consumption,

* voucher issuance,

* inbound arrival,

* serialization,

* shipment,

* invoicing,

* payment.

This enables AI to:

* learn from sequences, not snapshots,

* forecast future states,

* simulate “what-if” scenarios.

Without explicit events, AI becomes unreliable.

---

### **13.3.3 Deterministic core with probabilistic assistance**

The ERP enforces:

* hard constraints,

* eligibility rules,

* blocking conditions.

AI operates **around** these constraints:

* suggesting improvements,

* highlighting risks,

* optimizing choices.

This avoids the common failure mode of AI-driven systems: unexplainable outcomes.

---

### **13.3.4 High-quality, auditable data foundation**

Because:

* state transitions are explicit,

* overrides are reasoned,

* actions are logged,

the ERP generates **training and inference data that is trustworthy by design**.

This dramatically increases:

* AI accuracy,

* operator confidence,

* long-term value.

---

## **13.4 AI use cases by domain**

### **13.4.1 Sales & Commercial (Module S)**

AI can:

* suggest pricing ranges based on market data and sell-through history,

* flag prices outside market or margin bounds,

* simulate campaign impact across channels.

AI does **not** publish prices or override rules.

---

### **13.4.2 Allocations & Supply Planning (Modules A & D)**

AI can:

* forecast allocation depletion,

* suggest procurement timing,

* highlight risk of bottling deadline breaches,

* simulate demand scenarios.

AI does **not** issue vouchers or modify allocation limits.

---

### **13.4.3 Inventory & Operations (Modules B & C)**

AI can:

* suggest optimal warehouse selection,

* optimize picking to reduce fragmentation,

* detect anomalous movements or losses,

* forecast workload peaks.

AI does **not** bind bottles or ship stock.

---

### **13.4.4 Finance & Risk (Module E)**

AI can:

* flag reconciliation mismatches,

* detect unusual refund or credit patterns,

* assist audit preparation.

AI does **not** post entries or recognize revenue.

---

## **13.5 Operator Copilot (cross-module)**

A natural evolution of this ERP is an **Operator Copilot**.

The Copilot allows users to ask:

* “Why is this Shipping Order blocked?”

* “Which allocations are at risk of overselling?”

* “What happens if we sell 10 more vouchers of this wine?”

* “Which bottles are incurring storage fees?”

The Copilot:

* explains system state,

* references rules and events,

* suggests next actions.

It never executes actions autonomously.

---

## **13.6 Governance and safeguards**

AI components must be:

* explainable,

* permission-scoped,

* optional,

* fully logged.

Every AI suggestion must be:

* traceable to data,

* reviewable by a human,

* dismissible without consequence.

---

## **13.7 Strategic impact**

Positioned this way, AI:

* increases efficiency without increasing risk,

* scales operations without fragility,

* strengthens technical due diligence,

* reassures auditors and investors.

Most importantly:

**AI value compounds over time because the ERP foundation is clean.**

---

## **13.8 Final takeaway**

This ERP is not merely compatible with AI.  
It is **designed for it**.

By combining:

* deterministic authority,

* explicit structure,

* and advisory intelligence,

the system achieves a rare balance:  
**control without rigidity, intelligence without loss of trust.**

---