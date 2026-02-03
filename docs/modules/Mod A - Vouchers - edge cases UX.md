# **Voucher Module — Edge Case Test Pack (Admin UX)**

## **A. Lifecycle \+ Action gating**

### **1\) Transfer attempted while Locked**

**Scenario:** Support tries to gift/transfer a voucher that is already **Locked** for fulfillment.  
 **Expected behavior:** Transfer is blocked. No state change.  
 **Admin UI must:**

* Show prominent **LOCKED** banner (“Reserved for fulfillment planning”)

* Disable transfer action with tooltip: “Transfers are not allowed while locked.”

### **2\) Cancel attempted after Redeemed**

**Scenario:** Admin wants to “undo” a redeemed voucher.  
 **Expected behavior:** Not allowed. Redeemed is terminal.  
 **Admin UI must:**

* Hide/disable cancel action

* Explain: “Redeemed vouchers cannot be cancelled. Handle via finance/refund workflows.”

### **3\) Toggle Tradable/Giftable after Redeemed/Cancelled**

**Scenario:** Admin tries to toggle flags on terminal vouchers.  
 **Expected behavior:** Blocked.  
 **Admin UI must:** disable toggles \+ show rationale.

### **4\) Toggle flags while Suspended (external trading)**

**Scenario:** Voucher is **Suspended**; admin tries to make it giftable or tradable.  
 **Expected behavior:** Blocked (suspension overrides).  
 **Admin UI must:**

* Show SUSPENDED banner

* Lock all customer-permission toggles

---

## **B. Transfer flows (gifting / holder change)**

### **5\) Transfer initiated, recipient never accepts**

**Scenario:** Voucher is “sent” to another customer; recipient doesn’t accept.  
 **Expected behavior:** Voucher remains temporarily unavailable to sender until expiry/cancel.  
 **Admin UI must:**

* Show **Transfer Pending** status

* Show “Current holder” vs “Pending recipient”

* Provide only **Cancel transfer** action (role-based)

* Log events: initiated → (cancelled/expired)

### **6\) Transfer accepted while voucher became Locked in parallel**

**Scenario:** Fulfillment locks voucher while transfer is pending; recipient then tries to accept.  
 **Expected behavior:** Acceptance is blocked; transfer must fail or be reversed.  
 **Admin UI must:**

* Show conflict banner: “Locked during transfer — acceptance blocked”

* Show exact timestamps for lock vs transfer acceptance attempt

* Event history must make ordering obvious

### **7\) Transfer between same customer (self-transfer)**

**Scenario:** Customer accidentally sends to themselves.  
 **Expected behavior:** No-op or immediate acceptance without changing holder.  
 **Admin UI must:**

* Either prevent initiation (“recipient must differ”) or log as no-op clearly

* Must not duplicate events ambiguously

---

## **C. External trading suspension**

### **8\) Redemption attempted while Suspended**

**Scenario:** Customer tries to redeem a voucher currently trading externally.  
 **Expected behavior:** Blocked. Voucher remains suspended.  
 **Admin UI must:**

* Show “Redemption blocked due to suspension”

* Provide trading reference \+ last update timestamp

### **9\) Suspension lifted but voucher was already Locked elsewhere**

**Scenario:** Trading completes and ERP callback reactivates voucher, but fulfillment had already locked it earlier (shouldn’t happen, but test it).  
 **Expected behavior:** System resolves deterministically; locked state prevails.  
 **Admin UI must:**

* Show both events in history

* Show current effective restriction: LOCKED

* Provide exception warning: “Unexpected overlap: suspended \+ lock.”

### **10\) External trading callback changes holder**

**Scenario:** Trading completion updates voucher holder.  
 **Expected behavior:** Holder changes; lineage unchanged; no new voucher.  
 **Admin UI must:**

* Highlight “Holder changed via external trade”

* Preserve immutable fields (allocation ID, sellable SKU, creation time)

* Show old→new holder in event log

---

## **D. Case Entitlement breakability**

### **11\) Voucher in a Case Entitlement is transferred**

**Scenario:** A voucher belonging to an **INTACT** case is gifted/traded.  
 **Expected behavior:** Case entitlement becomes **BROKEN irreversibly**.  
 **Admin UI must:**

* On voucher detail: show “Case Entitlement: BROKEN”

* On entitlement view (if exists): update status \+ explain “broken by transfer/trade/redemption of a bottle”

* Event history must record the break cause

### **12\) Partial redemption breaks case rights**

**Scenario:** Customer redeems 1 bottle from an intact case.  
 **Expected behavior:** Case becomes BROKEN, remaining vouchers behave as loose.  
 **Admin UI must:**

* On redeemed voucher: show it was part of a case at issuance

* On remaining vouchers: show “formerly part of case entitlement — broken”

* Avoid confusing “case SKU” assumptions in fulfillment previews

### **13\) Attempt to “restore” broken case entitlement**

**Scenario:** Support asks to re-enable case shipment rights.  
 **Expected behavior:** Not allowed by invariant.  
 **Admin UI must:** no action; strong explanation.

---

## **E. Allocation lineage invariants (hard correctness)**

### **14\) Same Bottle SKU, different allocations — fulfillment swap requested**

**Scenario:** Ops wants to fulfill a voucher using a bottle sourced from another allocation with identical Bottle SKU.  
 **Expected behavior:** Not allowed (lineage non-negotiable), unless explicitly designed cross-lineage pooling exists (your doc says no).  
 **Admin UI must:**

* Show allocation lineage prominently

* Show warning: “Not fulfillable outside lineage”

* Provide link to allocation to explain “why”

### **15\) Allocation closed after vouchers issued**

**Scenario:** Allocation is closed/exhausted, but vouchers exist.  
 **Expected behavior:** Vouchers remain valid; allocation status doesn’t invalidate entitlements.  
 **Admin UI must:**

* Still show allocation ID even if closed

* Avoid showing “invalid” warnings unless a real operational exception exists

### **16\) Allocation constraints changed in draft vs voucher already issued (shouldn’t happen)**

**Scenario:** Allocation constraints edited while draft, but vouchers already exist due to bug/integration timing.  
 **Expected behavior:** System must preserve voucher snapshot and audit anomaly.  
 **Admin UI must:**

* Show “constraint snapshot at issuance” (if stored)

* Flag discrepancy as an exception

---

## **F. Liquid / bottle-equivalent nuance**

### **17\) Liquid voucher later mapped to final format**

**Scenario:** Voucher sold as bottle-equivalent; later procurement finalizes format.  
 **Expected behavior:** Voucher remains valid; bottle SKU reference may remain “equivalent” until mapped by procurement rules (depending on your data model).  
 **Admin UI must:**

* Display clearly: “Bottle-equivalent entitlement”

* If later mapped: show “Mapped to format on DATE via procurement”

* Still no serials until fulfillment/serialization

### **18\) Mixed case SKU generates many vouchers; one is liquid-equivalent**

**Scenario:** A composite SKU contains a mix of bottled and liquid entitlements (rare but test).  
 **Expected behavior:** Each voucher remains atomic; case entitlement behavior should be explicit if fixed-case.  
 **Admin UI must:**

* Not assume uniformity across vouchers in a group

* Show per-voucher entitlement type

---

## **G. Personalised bottling early binding (allowed exception)**

### **19\) Voucher enters early-binding mode**

**Scenario:** Customer requests personalised bottling.  
 **Expected behavior:** Voucher becomes bound at serialization time (exception path).  
 **Admin UI must:**

* Show “Binding mode: EARLY (personalised bottling)”

* Show serialization reference when available

* Still prevent general “manual binding” actions

### **20\) Attempt to early-bind without personalised bottling request**

**Scenario:** Ops tries to bind early “to simplify picking.”  
 **Expected behavior:** Forbidden.  
 **Admin UI must:** no action exists; explanation text reinforces invariant.

---

## **H. Data integrity \+ duplicates**

### **21\) Duplicate voucher creation for same sale line (idempotency failure)**

**Scenario:** Payment callback fires twice → voucher issued twice.  
 **Expected behavior:** System must prevent duplicates or flag as critical exception.  
 **Admin UI must:**

* Provide “Sale reference” field visible for diagnosis

* Show anomaly banner: “Potential duplicate issuance”

* Support audit/export for finance correction

### **22\) Voucher missing allocation\_id (should never happen)**

**Scenario:** Data import created vouchers without lineage.  
 **Expected behavior:** Voucher is invalid operationally; must be quarantined.  
 **Admin UI must:**

* Mark as “Invalid / Quarantined”

* Block redemption, transfer, fulfillment lock

* Provide admin-only remediation workflow (outside normal module) or escalation

