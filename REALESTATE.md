# Ready Estate real estate and accounting

The real estate workspace manages independent parties, client property listings, brokerage deals, client money, company-owned property, deal timelines, follow-ups and documents. Ready Estate accounting remains usable on its own. Brokerage and company-property transactions post balanced entries into the same company books.

## Brokerage workflow

1. Add buyers, sellers and owners under **Parties**. These are independent people or organizations, not company members.
2. Add a **Property** and choose its independent owner. The listing does not become a company asset.
3. Create a **Deal** with separate buyer and seller parties, agreement and due dates, client funds, commission, and payer. The agreement automatically creates a sales invoice: debit accounts receivable and credit brokerage commission income. Unpaid commission remains receivable.
4. Receive commission from the invoice using **Receive payment**. This debits cash or bank and credits the receivable. It does not record income again.
5. Record token, biana or other client receipts separately. These debit cash or bank and credit client funds held, a liability. Seller payouts and client refunds reduce that liability. If the buyer pays the seller directly, record a **Direct buyer–seller payment** on the deal. It appears in settlement history without touching company cash or the ledger.
6. Open a deal for its timeline, tasks, documents, client money history and invoices. A deal can complete with unpaid commission still shown as receivable once client funds are settled. Cancellation reverses an unpaid commission invoice and requires all client funds to be settled.

The workspace does not record the buyer's purchase price as company revenue. A deal can have only one active brokerage record per property. Correct client-money mistakes with a reversing entry; use **Client refund** for an actual repayment. Posted deal amounts and parties are fixed, so cancel an unused incorrect deal and enter a replacement.

## Company-owned property

Use **Company assets → Buy property** only when the company acquires property for itself and pays from cash or bank. The purchase debits a company property asset account and credits cash or bank. The owned property is excluded from brokerage deals. Selling it debits cash or bank, credits the property's cost, and records the difference as a gain or loss. The asset detail retains purchase, sale and reversal journals.

## Follow-ups and documents

Deal activity includes notes, calls, meetings and milestones. Tasks have an assignee, due time, reminder time and priority. The **Reminders** page shows pending tasks, due settlements and commission receivables. Deal documents accept PDF, PNG, JPG and TXT files up to 2 MB. Downloads require company access.

## Accounting and controls

The real estate module uses the existing company permissions, transaction lock, duplicate-request protection, journal engine and audit log. The general accounting screens remain independent and can be used without creating a real estate deal. Company access to an independent party controls visibility but does not imply ownership or employment. Owner and Accountant roles can post; Viewer can read.

The migration is additive. It creates `re_parties`, `re_party_access`, `re_company_assets`, `re_asset_events` and `re_direct_settlements`, adds nullable party links to existing property and deal tables, and links historical property/deal names to independent party records. Existing invoice, payment, deal, message and journal records are retained. It does not create or reverse any historical financial entry.

Run `install-operations.ps1` from the development workspace to back up the WAMP application and database, apply the migration, copy reviewed application files, and verify file hashes while preserving the private WAMP configuration. Test work uses a separate database ending `_test`.

The release handles paid company-property purchases and sales, fixed brokerage commission to one payer, client funds physically handled by the office, and direct buyer-to-seller settlement records. Installment schedules, recurring rent, joint ownership, tax and split commissions require separate workflows.
