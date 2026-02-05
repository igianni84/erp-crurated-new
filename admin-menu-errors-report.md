# Report Errori Menu Admin - Crurated ERP

**Data:** 2026-02-05
**URL Base:** http://127.0.0.1:8002/admin

## Riepilogo

| Tipo Errore | Conteggio |
|-------------|-----------|
| 500 Server Error | 18 |
| 404 Not Found | 12 |
| **Totale Errori** | **30** |

---

## Errori 500 - Server Error

Questi errori indicano problemi nel codice PHP/Laravel che devono essere investigati nei log.

| # | Sezione | Voce Menu | URL |
|---|---------|-----------|-----|
| 1 | Fulfillment | Fulfillment Overview | `/admin/fulfillment-dashboard` |
| 2 | Fulfillment | Shipping Orders | `/admin/fulfillment/shipping-orders` |
| 3 | Finance | Invoices | `/admin/finance/invoices` |
| 4 | Finance | Payments | `/admin/finance/payments` |
| 5 | Finance | Subscriptions | `/admin/finance/subscriptions` |
| 6 | Vouchers | Vouchers | `/admin/allocation/vouchers` |
| 7 | Commercial | Pricing Intelligence | `/admin/pricing-intelligence` |
| 8 | Commercial | Price Simulation | `/admin/price-simulation` |
| 9 | Procurement | Overview | `/admin/procurement-dashboard` |
| 10 | Procurement | Purchase Orders | `/admin/procurement/purchase-orders` |
| 11 | Procurement | Bottling Instructions | `/admin/procurement/bottling-instructions` |
| 12 | Procurement | Inbounds | `/admin/procurement/inbounds` |
| 13 | Customers | Parties | `/admin/customer/parties` |
| 14 | Customers | Customers | `/admin/customer/customers` |
| 15 | Customers | Clubs | `/admin/customer/clubs` |
| 16 | Customers | Operational Blocks | `/admin/customer/operational-blocks` |

---

## Errori 404 - Not Found

Questi errori indicano route mancanti o URL errati nel menu.

| # | Sezione | Voce Menu | URL |
|---|---------|-----------|-----|
| 1 | Finance | Overview | `/admin/finance-dashboard` |
| 2 | Finance | Storage Billing | `/admin/storage-billing` |
| 3 | Finance | Integration Settings | `/admin/integration-settings` |
| 4 | Vouchers | Transfers | `/admin/allocation/transfers` |
| 5 | Commercial | Overview | `/admin/commercial-dashboard` |
| 6 | Commercial | Channels | `/admin/commercial/channels` |
| 7 | Commercial | Calendar | `/admin/commercial/calendar` |
| 8 | Commercial | Price Books | `/admin/commercial/price-books` |
| 9 | Commercial | Pricing Policies | `/admin/commercial/pricing-policies` |
| 10 | Commercial | Offers | `/admin/commercial/offers` |
| 11 | Procurement | Procurement Intents | `/admin/procurement/procurement-intents` |
| 12 | Procurement | Suppliers & Producers | `/admin/procurement/suppliers` |

---

## Voci Menu Funzionanti (Status 200)

| Sezione | Voce Menu | URL |
|---------|-----------|-----|
| Dashboard | Dashboard | `/admin` |
| PIM | Data Quality | `/admin/pim-dashboard` |
| PIM | Products | `/admin/pim/products` |
| PIM | Wine Masters | `/admin/pim/wine-masters` |
| PIM | Wine Variants | `/admin/pim/wine-variants` |
| PIM | Formats | `/admin/pim/formats` |
| PIM | Case Configurations | `/admin/pim/case-configurations` |
| PIM | Sellable SKUs | `/admin/pim/sellable-skus` |
| PIM | Liquid Products | `/admin/pim/liquid-products` |
| Allocations | A&V Dashboard | `/admin/allocation-voucher-dashboard` |
| Allocations | Allocations | `/admin/allocation/allocations` |
| Fulfillment | Shipments | `/admin/fulfillment/shipments` |
| Fulfillment | Exceptions & Holds | `/admin/fulfillment/shipping-order-exceptions` |
| Inventory | Inventory Overview | `/admin/inventory-overview` |
| Inventory | Locations | `/admin/inventory/locations` |
| Inventory | Inbound Batches | `/admin/inventory/inbound-batches` |
| Inventory | Serialization Queue | `/admin/serialization-queue` |
| Inventory | Bottle Registry | `/admin/inventory/serialized-bottles` |
| Inventory | Cases | `/admin/inventory/cases` |
| Inventory | Movements | `/admin/inventory/inventory-movements` |
| Inventory | Create Transfer | `/admin/create-internal-transfer` |
| Inventory | Consignment Placement | `/admin/create-consignment-placement` |
| Inventory | Event Consumption | `/admin/event-consumption` |
| Inventory | Override Committed | `/admin/committed-inventory-override` |
| Inventory | Audit Log | `/admin/inventory-audit` |
| Finance | Credit Notes | `/admin/finance/credit-notes` |
| Finance | Refunds | `/admin/finance/refunds` |
| Finance | Customer Finance | `/admin/customer-finance` |
| Finance | Storage Billing Preview | `/admin/storage-billing-preview` |
| Finance | Integrations Health | `/admin/integrations-health` |
| Vouchers | Case Entitlements | `/admin/allocation/case-entitlements` |
| Commercial | Audit Trail | `/admin/commercial-audit` |
| Procurement | Audit Trail | `/admin/procurement-audit` |
| System | Users | `/admin/users` |

---

## Note per il Fix

### Errori 500 (Server Error)
Per questi errori, controllare:
1. I log Laravel in `storage/logs/laravel.log`
2. Possibili cause comuni:
   - Relazioni Eloquent mancanti o errate
   - Colonne database mancanti
   - Servizi non registrati nel container
   - Errori di sintassi nei controller/resource

### Errori 404 (Not Found)
Per questi errori, verificare:
1. Le route sono registrate in `routes/web.php` o nei file di route Filament
2. I resource Filament sono registrati nel panel provider
3. Gli URL nel menu di navigazione corrispondono alle route definite

---

*Report generato automaticamente il 2026-02-05*
