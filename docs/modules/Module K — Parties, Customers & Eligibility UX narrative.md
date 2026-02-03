# **Module K — Parties, Customers & Eligibility**

**Admin Panel: UX/UI Narrative Walkthrough (LOCKED)**

---

## **1\. Purpose of the Admin Panel experience**

Module K defines **who exists in the system and what they are allowed to do**.

The Admin Panel UX for Module K is designed to:

* act as the **authoritative gatekeeper** for all transactions

* make eligibility **explicit, explainable, and enforceable**

* prevent implicit access, privilege escalation, or manual workarounds

No sale, voucher issuance, redemption, trade, or shipment can proceed without passing Module K checks.

This module is deliberately:

* factual rather than commercial

* restrictive rather than permissive

* explicit rather than inferred

---

## **2\. Entry point and positioning**

In the Admin Panel main navigation, Module K appears as a top-level section:

**Parties & Customers**  
 (or **Parties & Eligibility**, depending on final naming)

This module is primarily used by:

* Operations

* Customer Operations / Membership

* Compliance

* Finance (read-only or scoped access)

* Admins

Commercial teams may have read-only access but **cannot modify eligibility or permissions**.

---

## **3\. High-level structure of the module**

Module K is structured around three core objects:

1. **Parties**  
    The authoritative registry of all legal and operational counterparties.

2. **Customers**  
    A specialization of Parties holding the Customer role.

3. **Eligibility & Controls**  
    Membership, channels, payment permissions, and operational blocks.

A key UX principle is enforced throughout the module:

Not all Parties are Customers.  
 Most Parties in the ERP will never transact.

---

## **4\. Parties**

### **4.1 Party List**

**Navigation:** Parties → Party List

This screen lists **all entities known to the ERP**, regardless of role.

**Typical columns**

* Party ID

* Legal name

* Party type (Individual / Legal entity)

* Assigned roles (Customer, Supplier, Producer, Partner)

* Status (Active / Inactive)

* Created at

The purpose of this list is to answer one question only:

“Does this entity exist in the ERP, and in which role(s)?”

No eligibility, membership, pricing, or commercial information appears here.

The list includes a **Create Party** action, used by Ops teams to onboard non-customer counterparties.

---

### **4.2 Party creation (Ops-driven flow)**

While customer registration is initiated from front-end systems, **Party creation is an ERP-owned operational flow**.

Authorized operators can create Parties to represent:

* suppliers

* producers

* partners

* club organizations

* other non-customer counterparties

Creating a Party:

* does not imply customer status

* does not grant transactional rights

* does not create eligibility of any kind

---

### **4.3 Party Detail view**

Clicking a Party opens a Party Detail page.

#### **Tabs**

* Overview

* Roles

* Legal & Compliance

* Linked Customers

* Audit

**Overview**

* Legal identity

* Jurisdiction

* Registration details

* Status

**Roles**

* Explicit role assignment:

  * Customer

  * Supplier

  * Producer

  * Partner

* Roles are explicit, additive, and auditable.

* No role is inferred based on activity in other modules.

Assigning the **Customer** role creates a Customer entity but does not grant eligibility.

---

## **5\. Customers**

### **5.1 Customer List**

**Navigation:** Customers → Customer List

This is the primary operational screen for customer governance.

**Typical columns**

* Customer ID

* Party name

* Customer type (B2C / B2B / Partner)

* Membership tier

* Membership status

* Channel eligibility indicators

* Active operational blocks (count)

* Status (prospect / active / suspended / closed)

Visual emphasis is placed on:

* membership tier

* presence of blocks

This makes risk and eligibility immediately visible.

---

### **5.2 Customer Detail view**

Clicking a customer opens a **multi-tab control surface**.

#### **Tabs**

1. Overview

2. Accounts

3. Membership

4. Eligibility & Channels

5. Payment & Credit

6. Clubs

7. Operational Blocks

8. Users & Access

9. Audit

This screen answers:

“Why can — or can’t — this customer act?”

---

### **5.3 Customer Overview**

Displays:

* Customer ID

* Linked Party

* Customer type

* Status

* Default billing address (if any)

An **Active** customer is one that exists operationally — not necessarily one that may transact.

---

## **6\. Accounts**

### **6.1 Accounts tab**

A Customer may operate through one or more Accounts.

Accounts model:

* different billing or invoicing contexts

* shipping defaults

* channel access

* account-scoped blocks

**Accounts list columns**

* Account ID

* Account name

* Channel scope

* Status

* Active blocks

---

### **6.2 Account Detail**

Within an Account, operators can view:

* billing defaults

* shipping defaults

* channel eligibility

* account-level operational blocks

Accounts may **restrict** eligibility further but can never override Customer-level restrictions.

---

## **7\. Membership**

### **7.1 Membership tab**

Membership is managed explicitly and independently from customer existence.

Displayed fields:

* Membership tier (Legacy / Member / Invitation Only)

* Membership status (applied, under\_review, approved, rejected, suspended)

* Effective dates

* Decision notes

* Full history timeline

Key UX rule:

* Temporary or operational issues must be handled through **Operational Blocks**, not by repeatedly changing membership state.

---

## **8\. Eligibility & Channels**

### **8.1 Eligibility tab**

This tab displays **computed eligibility**.

Shown per Customer and per Account:

* Allowed channels (B2C, B2B, Clubs)

* Restricted channels

* Derived eligibility flags

Eligibility:

* is deterministic

* is not directly editable

* is fully explainable via underlying facts (membership, tier, clubs, blocks)

Operators can see *why* a customer is eligible, but cannot override logic.

---

## **9\. Payment & Credit**

### **9.1 Payment & Credit tab**

Explicit control over payment permissions.

Displayed fields:

* Allowed payment methods (Card, Bank transfer)

* Bank transfer authorization

* Credit limit (if applicable)

* Outstanding balance (read-only)

* Payment-related warnings or flags

Payment permissions affect the **ability to transact or fulfill**, but never pricing or commercial terms.

---

## **10\. Clubs**

### **10.1 Clubs List**

**Navigation:** Clubs → Club List

Lists all club entities:

* Club ID

* Partner name

* Status

* Active member count

---

### **10.2 Customer → Clubs tab**

Shows explicit Customer–Club affiliations.

Displayed fields:

* Club

* Affiliation status

* Start date

* End date

Club affiliation:

* does not grant transactional rights

* contributes to eligibility computation

* is consumed by other modules

---

## **11\. Segments (read-only)**

### **11.1 Segments view**

Segments are shown for transparency only.

For a given Customer:

* list of computed segments

* explanation tooltips

Segments:

* are derived automatically

* are never manually assigned

* cannot be overridden

---

## **12\. Operational Blocks (critical)**

### **12.1 Blocks overview**

**Navigation:** Operational Blocks → Block List

Lists all active blocks across the system.

**Typical columns**

* Block type

* Scope (Customer / Account)

* Reason

* Applied by

* Timestamp

* Status

---

### **12.2 Customer → Operational Blocks tab**

This is the primary enforcement surface.

Operators can:

* add a block

* remove a block (with appropriate permissions)

Block characteristics:

* explicit

* reasoned

* timestamped

* fully auditable

Blocks override:

* membership

* eligibility

* commercial logic

* fulfillment logic

No operator can bypass a block without removing it.

---

## **13\. Role of Module K in system flows**

### **Checkout**

Before checkout completes, Module K validates:

* customer status

* membership approval

* tier eligibility

* channel access

* payment permissions

* absence of blocking holds

Failure at any step denies checkout.

### **Redemption, trading, and shipment**

Before execution:

* Module K clearance is mandatory

* shipment or compliance blocks stop the flow before physical actions occur

---

## **14\. Audit and governance**

All changes to:

* roles

* membership

* tiers

* eligibility

* payment permissions

* blocks

* club affiliations

are:

* permission-controlled

* versioned

* fully auditable

Audit is embedded across all views, not isolated in a single screen.

---

## **15\. UX intent summary**

The UX of Module K is designed to:

* remove ambiguity

* enforce curated access

* centralize eligibility logic

* eliminate manual checks and tribal knowledge

**Module K is not friendly by design.**  
 **It is precise, explicit, and final.**

Without it, Modules S, A, and C cannot operate safely.

