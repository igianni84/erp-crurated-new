# **Mod 0 — Product Information Management (PIM)**

## **Admin Panel UX/UI Narrative Walkthrough (v2 — ERP-aligned, LOCKED)**

---

## **1\. Purpose and operator mental model**

Module 0 (PIM) is the authoritative system for **product identity and structure**.

It answers one question only:

*What exists, and how is it structurally defined?*

PIM does **not** decide:

* whether a product can be sold  
* to whom it can be sold  
* at what price  
* whether inventory exists

Those decisions belong to downstream modules (Sales, Allocations, Inventory).

Publishing in PIM means **definition completeness and structural validity**, not commercial availability.

---

## **2\. Entry point and primary navigation**

When the user opens the admin panel, they see **PIM** as a main navigation section.

Clicking **PIM → Products** brings the user to the **Product List**, which is the operational entry point of the module.

This section is primarily used by:

* Product Managers  
* Operations  
* Content Editors  
* Reviewers / Approvers

---

## **3\. Product List**

### **Purpose**

The Product List provides a global view of all **product definitions** managed in PIM.

Each row represents **one Product**, defined as:

* one wine  
* one vintage

(i.e. a **Wine Variant** in ERP terms).

Liquid Products are listed separately but live in the same module.

### **Table columns**

Each row communicates at a glance:

* Product name (wine \+ vintage)  
* Product category:  
  * Bottle Product (Wine Variant)  
  * Liquid Product  
* Lifecycle status (Draft, In Review, Approved, Published, Archived)  
* Definition completeness (%)  
* Data source (Liv-ex / Manual)  
* Last updated date  
* Thumbnail image (or placeholder)

### **List actions**

From this screen, the user can:

* Search by name, internal code, LWIN  
* Filter by status, category, completeness, or source  
* Open a product to view or edit  
* Duplicate a product definition  
* Perform limited bulk actions (e.g. submit for review)

Sensitive actions (publish, archive) are intentionally **not available** from the list view.

**Note on Liquid vs Bottle Products**

Liquid Products are shown as separate rows in the Product List even when they reference the same wine and vintage as a Bottle Product. This is intentional: liquid represents a distinct commercial abstraction phase (pre-bottling) with different rules, validations, and downstream behavior. Separating them prevents SKU, inventory, and allocation ambiguity while preserving a single underlying wine identity.

**Terminology lock**

In the system, the term *SKU* refers exclusively to *Sellable SKUs*, which represent concrete commercial units (wine vintage × format × case configuration).

Wine identities and vintages have stable internal codes but are not SKUs and must never be treated as commercial units.

---

## **4\. Create Product flow**

Clicking **Create Product** starts a guided creation flow designed to avoid empty or ambiguous states.

### **Step 1: Choose product category**

The user must explicitly choose:

* **Bottle Product (Wine \+ Vintage)**  
* **Liquid Product (Pre-bottling)**

This choice is irreversible after creation and determines available tabs and validation rules.

---

### **4.1 Bottle Product creation**

#### **Step 2: Choose creation method**

The user selects:

* Import from Liv-ex (recommended)  
* Create manually (fallback)

#### **Import from Liv-ex**

* User searches by LWIN or wine name  
* Matching wine \+ vintage results are shown  
* User selects one exact match  
* A confirmation screen explains:  
  * which data will be imported  
  * which fields will be locked by default

After confirmation:

* A **Draft Bottle Product** is created  
* Core attributes and media are imported  
* User is redirected to the Product Detail screen

#### **Manual creation**

* User enters minimal required fields (wine name, vintage, producer)  
* A Draft Bottle Product is created  
* User is redirected to the Product Detail screen

---

### **4.2 Liquid Product creation**

Liquid Products represent wine **before bottling** and are a distinct product category.

Creation flow:

* User selects wine \+ vintage  
* User defines allowed bottle-equivalent units (e.g. 0.75L)  
* User defines allowed final formats and case configurations

No Sellable SKUs are created at this stage.

---

## **5\. Product Detail (Bottle Product)**

Opening a Bottle Product brings the user into a product-scoped workspace with sub-navigation tabs.

---

### **Tab 1: Overview**

Read-only control panel showing:

* Product identity (wine \+ vintage)  
* Current lifecycle status  
* Definition completeness percentage  
* Blocking issues (must be resolved to publish)  
* Warnings (informational)  
* Liv-ex integration status

Actions available depend on role and status:

* Validate  
* Submit for review  
* Approve / Reject  
* Publish

Clicking an issue navigates directly to the relevant tab.

---

### **Tab 2: Core Info**

Contains immutable and structural product information:

* Wine name and vintage  
* Producer and appellation  
* Internal references  
* Product descriptions

Liv-ex–sourced fields are:

* Clearly marked  
* Locked by default  
* Overridable only by Managers/Admins

Editing sensitive fields on a Published product automatically moves it back to **In Review**.

---

### **Tab 3: Attributes**

Primary data-entry area for structured attributes.

Attributes are:

* Loaded dynamically by attribute set  
* Grouped into logical sections (e.g. Wine Info, Compliance)  
* Marked as required or optional

For each attribute, users see:

* Current value  
* Source (Liv-ex / Manual)  
* Editability

Completeness updates in real time.

---

### **Tab 4: Media**

Manages all product-related assets.

Sections:

* Imported from Liv-ex (read-only)  
* Manual uploads (editable)

Actions:

* Upload images and documents  
* Reorder manual images  
* Set primary image (required for publishing)  
* Refresh Liv-ex assets

---

### **Tab 5: Sellable SKUs**

This tab defines **how this wine vintage can be sold**, without implying availability or pricing.

A **Sellable SKU** represents:

Wine Variant × Format × Case Configuration

#### **SKU list view**

Each row shows:

* SKU code  
* Format (e.g. 0.75L, 1.5L)  
* Case configuration (e.g. 6×0.75L OWC, loose)  
* Case integrity flags (original, breakable)  
* SKU lifecycle status (Draft / Active / Retired)

#### **Actions**

* Create Sellable SKU  
  * Select Format  
  * Select Case Configuration  
* Generate intrinsic SKUs from producer data  
* Retire obsolete SKUs

SKU lifecycle reflects **definition readiness only**.

---

### **Tab 6: Associations**

Captures optional, long-term product relationships.

Examples:

* Substitute or equivalent wines  
* Vintage continuity (replaces / replaced by)  
* Accessories or packaging references

Associations are:

* Non-blocking  
* Informational  
* Product-level (inherited by SKUs)

Composite SKUs (bundles) are **not managed here**.

---

### **Tab 7: Lifecycle**

Governance and status transitions.

Shows:

* Current status  
* Publish readiness checklist  
* Blocking issues vs warnings  
* Allowed transitions based on role

Only valid transitions are visible:

* Draft → In Review  
* In Review → Approved / Rejected  
* Approved → Published  
* Published → Archived

---

### **Tab 8: Audit**

Read-only activity log including:

* Status changes  
* Attribute edits and overrides  
* Media updates  
* SKU creation and retirement  
* Liv-ex imports and refreshes

---

## **6\. Product Detail (Liquid Product)**

Liquid Products have a reduced and specialized tab set:

* Overview  
* Core Info  
* Bottling Constraints  
* Lifecycle  
* Audit

They **do not** have Sellable SKUs.

Liquid Products:

* Are sold via vouchers only  
* Resolve into Bottle Products and Sellable SKUs post-bottling  
* Never exist in inventory

---

## **7\. Data Quality (cross-product view)**

The Data Quality section provides a global operational view.

Users can:

* Monitor catalog health  
* Identify blocked products  
* Work through validation issues

Each issue links directly to the exact product and tab where it can be resolved.

---

## **8\. Locked mental model summary**

* One Product \= one wine \+ one vintage  
* Bottle Products and Liquid Products are distinct categories  
* Sellable SKUs define commercial unit structure, not availability  
* Case configuration is explicit and first-class  
* Publishing in PIM ≠ selling  
* Liv-ex is the default source; manual input is explicit and governed

This mental model must not be violated by downstream modules.

