# GSTR-1 — Phase 1 Audit

Full findings (verbatim from the audit conversation, kept here since the Phase 2 service/tests
reference this file). See `docs/GSTR1-PHASE2-REPORT.md` for the fix that followed.

## Files traced
- Controller: `app/Http/Controllers/GST/EInvoiceDashboardController.php` — `gstr1View`, `gstr1SectionView`, `gstr1Details`
- Views: `resources/views/gst/gstr-1.blade.php`, `gstr-1-section.blade.php`
- Authoritative tax source: `app/Services/Tax/TaxEngine.php`
- Schema: `sales_bills`, `sales_bill_items`, `sales_returns`, `sales_return_items`, `customers`, `items`, `gst_settings`, `document_sequences`

## Audit table

| Section | Current Query/Formula (before fix) | Expected Business Rule | Bug Found | Required Fix |
|---|---|---|---|---|
| HSN B2B | Reused B2B/B2C item query; hardcoded 20-row fake fallback if empty | Aggregate real posted B2B line items by HSN/rate | Fabricated fallback | Real SQL aggregation, no fallback |
| HSN B2C | Same query reused for every non-B2B section | Same aggregation, B2C filter | Fabrication + wrong-section reuse | Real SQL aggregation, no fallback |
| B2B Outward | Filter by `customer.gst_no`; flat 18/118 guess when total_gst=0; hardcoded fallback (count=64, taxable=607861.26, tax=93420.62) | Invoice-wise registered supplies, real stored tax split | Fabrication + guessed rate | Use real `total_cgst/sgst/igst`, no guessing, no fallback |
| B2CL | `total>=250000 AND (Interstate OR igst>0)` | Large-value inter-state B2C, real 2.5L threshold | Threshold correct; no dedicated section query | Own query; threshold kept as-is |
| Exported Supplies | Hardcoded zero always | Separate export transactions | No schema support at all | Report NOT SUPPORTED |
| B2CS | Same 18/118 guess + hardcoded fallback | Aggregate small B2C by rate/POS | Fabrication | Real aggregation, no fallback |
| CDNR | Filter by `customer.gst_no`; hardcoded fallback (count=2, value=2829.76, tax=364.50) | Real posted returns for registered customers | Fabrication; no debit-note concept exists | Real query; document debit-note limitation |
| CDNUR | Same table, unregistered filter | Same, unregistered | Detail page reused wrong (HSN) query | Own dedicated query |
| Nil Rated | `invoice_type==='Exempted'`; hardcoded fallback 73252.50 | Distinguish nil/exempt/non-GST | Schema has only ONE bucket; fabrication | Report combined figure, document limitation |
| Advance Received/Adjusted | Hardcoded 0 always | Track real advance payments | No advance table exists at all | Report NOT SUPPORTED |
| Document Issued | `$allBills->count() ?: 3513` | First/last/total/cancelled per series | Fabricated fallback; bills-only, ignores returns | Real series from bill+return numbers |

## Other structural findings
1. B2B/B2C classification read the customer's **current** `gst_no`, not a posting-time snapshot — historical GSTR-1 for a past period could silently reclassify old invoices if a GSTIN was added/changed/removed later.
2. `gstr1SectionView` only had one real query (gated on B2B-vs-not); every other section fell through to that same HSN query regardless of which was clicked.
3. `GstSetting::current()->gstin` defaults to a hardcoded `'24AAECU3183G1ZN'` with `gsp_provider='mock'`, `is_sandbox=true` — genuinely a sandbox default, not silently a real business GSTIN.
4. Cess: genuinely unsupported (no cess columns anywhere) — `0.00` is honest, not a bug.
5. `gstr3bDetails`/`gstr9Sync`/`uploadGstr2` have the same guess/mock patterns — out of this task's 12-section scope, flagged for awareness only.
