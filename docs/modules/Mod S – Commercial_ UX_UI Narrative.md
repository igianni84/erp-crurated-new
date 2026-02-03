# **Mod S – Commercial: UX/UI Narrative Walkthrough (LOCKED)**

## **Terminology note (important)**

Throughout this module, the term **SKU** always refers to a **Sellable SKU** as defined in Mod 0 (PIM).

A **Sellable SKU** is a uniquely sellable commercial unit, including:

* product \+ vintage  
* **format** (e.g. 0.75, 1.5)  
* **packaging** (OC, OWC, case size, etc.)

All Commercial logic operates strictly at **Sellable SKU level**.

---

## **Entry point: Commercial module**

When the user opens the admin panel, they see **Commercial** as a main navigation section, alongside PIM and other modules.

Clicking **Commercial → Overview** brings them to the operational entry point of the module.

Commercial is where users define **how Sellable SKUs are priced and activated for sale**, through:

* Pricing Policies (automation)  
* Price Books (base prices at scale)  
* Offers (selling activation and conditional logic)  
* Discounts, rules, and bundles

Commercial does not decide what can be sold. It only governs **pricing and selling logic** for eligible Sellable SKUs.

---

## **Commercial prerequisites (critical)**

Before any pricing or offer can exist, three upstream conditions must be met:

1. A **Sellable SKU** exists in Mod 0 (PIM)  
2. An **Allocation** exists for that Sellable SKU (Mod A)  
3. The Allocation allows the relevant **market, channel, and customer type**

Commercial objects (Pricing Policies, Price Books, Offers) can only be created for Sellable SKUs that are **commercially available** under Allocation constraints.

Commercial never overrides Allocation rules.

---

## **Commercial Overview**

The Commercial Overview acts as a control panel for all commercial activity.  
It is primarily read-only and designed to give immediate clarity on the commercial state of the business.

From this screen, the user sees:

* Active Pricing Policies and their last execution status  
* Active Price Books by market and channel  
* Active and scheduled Offers

  ### **Estimated Market Price (EMP)**

For each Sellable SKU, Commercial displays an **Estimated Market Price (EMP)**.

EMP is:

* Calculated upstream using market data (e.g. Liv-ex) plus internal adjustments  
* Stored **per Sellable SKU**  
* Read-only in Commercial

EMP is used for:

* Optional Pricing Policy inputs  
* Sanity checks and dashboards  
* Alerting on abnormal prices

EMP never sets, activates, or overrides a selling price.

### **Alerts and warnings**

* Price Books with missing prices for commercially available Sellable SKUs  
* Failed or outdated Pricing Policy executions  
* Prices deviating materially from EMP  
* Overlapping or conflicting Offers  
* Upcoming expirations

  ### **Commercial calendar**

A visual calendar shows:

* Price Book validity periods  
* Offer schedules  
* Pricing Policy executions

  ### **Primary CTAs**

* Create Pricing Policy  
* Create Price Book  
* Create Offer  
  ---

  ## **Top-level navigation inside Commercial**

Once inside the module, users navigate through persistent tabs:

* Overview  
* Pricing Intelligence  
* Pricing Policies  
* Price Books  
* Offers  
* Discounts & Rules  
* Bundles  
* Simulation  
* Audit

Each tab corresponds to a single commercial responsibility, avoiding overlaps and hidden logic.

---

## **Pricing Intelligence**

Pricing Intelligence provides a read-only, analytical view of market pricing signals used to inform commercial decisions.

It is the authoritative UI home for **Estimated Market Price (EMP)** and other future pricing signals.

Pricing Intelligence is **non-operational**:

* No prices are activated or modified here  
* No commercial objects are created  
* No Allocation or Offer logic is overridden

Its purpose is to help users understand **market reality vs internal pricing intent** before taking action elsewhere in Commercial.

---

### **Pricing Intelligence – List View**

The list view shows one row per **Sellable SKU per market**.

Each row displays:

* Sellable SKU (format \+ packaging)  
* Market / region  
* EMP value  
* EMP freshness / confidence indicator  
* Active Price Book price (if any)  
* Active Offer price (if any)  
* Absolute and percentage delta vs EMP

Users can:

* Search and filter by SKU, market, or variance  
* Sort by deviation, freshness, or coverage  
* Drill into a specific Sellable SKU

There are no create or edit actions in this view.

---

### **Pricing Intelligence – Detail View**

Opening a Sellable SKU shows a detailed analytical view.

Tabs include:

1. **EMP Overview**  
   * Current EMP value  
   * Source breakdown (e.g. Liv-ex, internal adjustments)  
   * Last update timestamp  
   * Historical EMP trend  
2. **Comparisons**  
   * EMP vs Price Book prices  
   * EMP vs active Offer prices  
   * Highlighted variances and thresholds  
3. **Market Coverage**  
   * Markets with EMP coverage  
   * Missing or stale data warnings  
4. **Signals & Alerts**  
   * Outlier detection  
   * Significant deviations  
   * Informational warnings surfaced to Commercial Overview  
5. **Audit**  
   * Data refresh history  
   * Source updates

Pricing Intelligence surfaces insights but delegates all action to Pricing Policies, Price Books, and Offers.

---

## **Pricing Policies**

Pricing Policies automate how prices are generated, updated, and maintained.  
They operate **upstream of Price Books** and never apply at checkout time.

Pricing Policies only operate on **commercially available Sellable SKUs**.

### **Pricing Policy List**

Each row represents one Pricing Policy and shows:

* Policy name  
* Policy type (Cost \+ Margin, Reference Price Book, Index-based, Rounding)  
* Input source (Cost, EMP, Price Book, External index)  
* Target Price Book(s)  
* Status (Draft, Active, Paused, Archived)  
* Last execution date and result

Primary action:

* Create Pricing Policy  
  ---

  ### **Create Pricing Policy flow**

**Step 1: Choose policy type**

* Cost \+ Margin  
* Reference Price Book  
* External index (FX, Liv-ex)  
* Fixed adjustment  
* Rounding / normalization

**Step 2: Define inputs**

* Cost source  
* Reference Price Book  
* External index or EMP  
* FX conversion rules

**Step 3: Define pricing logic**

* Margins or markups  
* Tiered logic by product, category, or Sellable SKU  
* Rounding rules (.90, .95, .99)

Logic is displayed structurally and as a plain-language explanation.

**Step 4: Define scope & targets**

* Target Price Book(s)  
* Products, categories, or Sellable SKUs  
* Markets and channels

Policies always resolve to underlying **Sellable SKUs**.

**Step 5: Execution & cadence**

* Manual or scheduled execution  
* Frequency  
* Trigger events (cost change, FX update)

**Step 6: Review & create**  
A summary explains exactly which Sellable SKUs will be priced and where prices will be written.

---

### **Pricing Policy Detail**

Sub-navigation tabs:

1. **Overview** – policy summary and last execution result  
2. **Logic** – inputs, calculations, rounding, with live previews  
3. **Scope** – resolved Sellable SKUs, markets, channels  
4. **Execution** – manual run, scheduling, dry runs  
5. **Impact Preview** – old price vs new price, EMP delta, warnings  
6. **Lifecycle** – Draft → Active → Paused → Archived  
7. **Audit** – immutable log of changes and executions  
   ---

   ## **Price Books**

Price Books store **base prices** for Sellable SKUs.  
They are the authoritative price source for all downstream logic.

A single Price Book may contain **thousands of Sellable SKUs**, including historical SKUs.  
Coverage is expected to be broad; activation is handled by Offers.

### **Price Book List**

Each row shows:

* Name  
* Market  
* Channel  
* Currency  
* Validity  
* Status  
* Last updated

Primary action:

* Create Price Book  
  ---

  ### **Create Price Book flow**

The user defines:

* Market  
* Channel  
* Currency  
* Validity period

A Draft Price Book is created and opened for editing.

---

### **Price Book Detail**

**Tab 1: Overview**

* Scope and applicability  
* Status  
* Price coverage  
* Linked Pricing Policies and Offers

**Tab 2: Prices**

* Grid of Sellable SKU  
* Base price  
* Source (Manual / Policy-generated)  
* EMP reference

Manual overrides are explicitly flagged.

**Tab 3: Scope & Applicability**

* Market and channel applicability  
* Priority rules

**Tab 4: Lifecycle**

* Activation and expiration

**Tab 5: Audit**

* Full change history  
  ---

  ## **Offers**

Offers activate selling logic for Sellable SKUs under specific conditions.

An Offer:

* References a Price Book price  
* Optionally modifies it (discount, fixed price, benefit)  
* Defines where, when, and to whom the Sellable SKU is sold

In most cases:

* **1 Offer \= 1 Sellable SKU**

Bundles and composed products are explicit exceptions.

---

### **Offer List**

Each row shows:

* Name  
* Sellable SKU  
* Type  
* Status  
* Validity  
* Market / channel

Primary action:

* Create Offer

Bulk actions:

* Create offers for selected Sellable SKUs  
* Create offers from Allocation  
* Pause or archive offers in bulk  
  ---

  ### **Bulk Offer Creation**

Offers can be created at scale through bulk flows.

Entry points:

* Offer List (selected Sellable SKUs)  
* Allocation detail (all eligible Sellable SKUs)  
* Price Book (uncovered Sellable SKUs)

The bulk flow allows:

* Selecting a target Price Book  
* Defining shared eligibility  
* Defining benefit logic  
* Reviewing all generated offers before creation

Each Sellable SKU results in **one independent Offer**.

---

### **Offer Detail**

Tabs:

1. **Overview** – summary and status  
2. **Eligibility** – market, channel, customer type (Allocation-constrained)  
3. **Benefit** – discount, fixed price, or promotion  
4. **Products** – Sellable SKU reference  
5. **Priority & Conflicts** – resolution rules  
6. **Simulation** – price testing  
7. **Audit** – full traceability  
   ---

   ## **Discounts & Rules**

Reusable atomic logic shared across Pricing Policies and Offers.

This section is intended for advanced users and does not block core flows.

---

## **Bundles**

Bundles define commercial groupings of Sellable SKUs.  
They specify:

* Components  
* Pricing logic  
* Eligibility  
* Simulation  
  ---

  ## **Simulation (cross-commercial)**

Simulation explains every price decision end-to-end.

**Inputs**:

* Sellable SKU (format \+ packaging)  
* Customer  
* Channel  
* Date  
* Quantity

**Outputs**:

* Allocation lineage used  
* EMP reference  
* Applied Pricing Policy  
* Applied Price Book  
* Applied Offer  
* Final price with explanation

If a Sellable SKU is blocked by Allocation, this is explicitly shown.

---

## **Audit (cross-module)**

Global read-only log of:

* Pricing Policy executions  
* Price Book changes  
* Offer changes  
* Activations and expirations  
  ---

  ## **Mental model summary (LOCKED)**

* PIM defines Sellable SKUs  
* Allocations define where and how Sellable SKUs may be sold  
* EMP provides a market reference per Sellable SKU  
* Pricing Policies generate prices  
* Price Books store base prices at scale  
* Offers activate selling logic, usually one per Sellable SKU  
* Bulk tools enable operational scalability  
* Simulation explains every decision  
* Nothing activates silently  
* Commercial never overrides Allocation  
* 

