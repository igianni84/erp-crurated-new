<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }

        .container {
            padding: 20px 30px;
        }

        /* Header Section */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }

        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        .company-name {
            font-size: 24pt;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 9pt;
            color: #666;
        }

        .invoice-title {
            font-size: 28pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 10px;
        }

        .invoice-number {
            font-size: 12pt;
            font-weight: bold;
            color: #2563eb;
        }

        /* Invoice Info Section */
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .info-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .info-box-title {
            font-size: 8pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-box-content {
            font-size: 10pt;
        }

        .info-row {
            margin-bottom: 4px;
        }

        .info-label {
            font-weight: bold;
            color: #475569;
        }

        /* Invoice Type Badge */
        .invoice-type-badge {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .invoice-type-inv0 { background-color: #8b5cf6; }
        .invoice-type-inv1 { background-color: #10b981; }
        .invoice-type-inv2 { background-color: #f59e0b; }
        .invoice-type-inv3 { background-color: #6366f1; }
        .invoice-type-inv4 { background-color: #ec4899; }

        /* Lines Table */
        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .lines-table th {
            background-color: #1e3a5f;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .lines-table th.text-right {
            text-align: right;
        }

        .lines-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10pt;
        }

        .lines-table td.text-right {
            text-align: right;
        }

        .lines-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Totals Section */
        .totals-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .totals-spacer {
            display: table-cell;
            width: 60%;
        }

        .totals-box {
            display: table-cell;
            width: 40%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px 10px;
            font-size: 10pt;
        }

        .totals-table td.label {
            text-align: right;
            color: #64748b;
        }

        .totals-table td.value {
            text-align: right;
            font-weight: bold;
            width: 120px;
        }

        .totals-table tr.total-row {
            background-color: #1e3a5f;
            color: white;
        }

        .totals-table tr.total-row td {
            font-size: 12pt;
            padding: 12px 10px;
        }

        .totals-table tr.paid-row td {
            color: #16a34a;
        }

        .totals-table tr.outstanding-row td {
            color: #dc2626;
            border-top: 2px solid #e2e8f0;
        }

        /* Payment Info Section */
        .payment-info {
            background-color: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 30px;
        }

        .payment-info-title {
            font-size: 10pt;
            font-weight: bold;
            color: #166534;
            margin-bottom: 10px;
        }

        .payment-info-content {
            font-size: 9pt;
            color: #15803d;
        }

        /* Notes Section */
        .notes-section {
            background-color: #fefce8;
            border: 1px solid #fde047;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 30px;
        }

        .notes-title {
            font-size: 10pt;
            font-weight: bold;
            color: #854d0e;
            margin-bottom: 8px;
        }

        .notes-content {
            font-size: 9pt;
            color: #713f12;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8pt;
            color: #94a3b8;
        }

        .footer-legal {
            margin-top: 10px;
            font-size: 7pt;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: bold;
        }

        .status-issued { background-color: #dbeafe; color: #1d4ed8; }
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-partially_paid { background-color: #fef3c7; color: #92400e; }
        .status-credited { background-color: #fce7f3; color: #9d174d; }

        /* Due Date Warning */
        .due-date-warning {
            color: #dc2626;
            font-weight: bold;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ config('app.company_name', 'Crurated') }}</div>
                <div class="company-details">
                    {{ config('app.company_address', '123 Wine Street') }}<br>
                    {{ config('app.company_city', 'London, UK') }}<br>
                    {{ config('app.company_country', 'United Kingdom') }}<br>
                    VAT: {{ config('app.company_vat', 'GB123456789') }}<br>
                    {{ config('app.company_email', 'billing@crurated.com') }}
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                <div style="margin-top: 10px;">
                    <span class="invoice-type-badge invoice-type-{{ strtolower($invoice->invoice_type->code()) }}">
                        {{ $invoice->invoice_type->code() }} - {{ $invoice->invoice_type->label() }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Invoice Info Section -->
        <div class="invoice-info">
            <div class="info-left">
                <!-- Bill To -->
                <div class="info-box">
                    <div class="info-box-title">Bill To</div>
                    <div class="info-box-content">
                        @if($invoice->customer)
                            <strong>{{ $invoice->customer->name }}</strong><br>
                            @if($invoice->customer->billing_address)
                                {{ $invoice->customer->billing_address }}<br>
                            @endif
                            @if($invoice->customer->billing_city)
                                {{ $invoice->customer->billing_city }}@if($invoice->customer->billing_postal_code), {{ $invoice->customer->billing_postal_code }}@endif<br>
                            @endif
                            @if($invoice->customer->billing_country)
                                {{ $invoice->customer->billing_country }}<br>
                            @endif
                            @if($invoice->customer->vat_number)
                                VAT: {{ $invoice->customer->vat_number }}<br>
                            @endif
                            {{ $invoice->customer->email }}
                        @else
                            Customer information not available
                        @endif
                    </div>
                </div>
            </div>
            <div class="info-right">
                <!-- Invoice Details -->
                <div class="info-box">
                    <div class="info-box-title">Invoice Details</div>
                    <div class="info-box-content">
                        <div class="info-row">
                            <span class="info-label">Invoice Number:</span>
                            {{ $invoice->invoice_number }}
                        </div>
                        <div class="info-row">
                            <span class="info-label">Issue Date:</span>
                            {{ $invoice->issued_at?->format('d M Y') ?? 'Not issued' }}
                        </div>
                        <div class="info-row">
                            <span class="info-label">Due Date:</span>
                            @if($invoice->due_date)
                                <span class="{{ $invoice->isOverdue() ? 'due-date-warning' : '' }}">
                                    {{ $invoice->due_date->format('d M Y') }}
                                    @if($invoice->isOverdue())
                                        (OVERDUE)
                                    @endif
                                </span>
                            @else
                                Due on Receipt
                            @endif
                        </div>
                        <div class="info-row">
                            <span class="info-label">Currency:</span>
                            {{ $invoice->currency }}
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="status-badge status-{{ $invoice->status->value }}">
                                {{ $invoice->status->label() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Lines Table -->
        <table class="lines-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th class="text-right" style="width: 10%;">Qty</th>
                    <th class="text-right" style="width: 15%;">Unit Price</th>
                    <th class="text-right" style="width: 10%;">Tax Rate</th>
                    <th class="text-right" style="width: 15%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->invoiceLines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="text-right">{{ number_format((float)$line->quantity, 2) }}</td>
                        <td class="text-right">{{ $invoice->currency }} {{ number_format((float)$line->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format((float)$line->tax_rate, 2) }}%</td>
                        <td class="text-right">{{ $invoice->currency }} {{ number_format((float)$line->line_total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: #94a3b8;">No invoice lines</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="totals-section">
            <div class="totals-spacer"></div>
            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td class="label">Subtotal:</td>
                        <td class="value">{{ $invoice->currency }} {{ number_format((float)$invoice->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Tax:</td>
                        <td class="value">{{ $invoice->currency }} {{ number_format((float)$invoice->tax_amount, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td class="label">Total:</td>
                        <td class="value">{{ $invoice->currency }} {{ number_format((float)$invoice->total_amount, 2) }}</td>
                    </tr>
                    @if((float)$invoice->amount_paid > 0)
                        <tr class="paid-row">
                            <td class="label">Amount Paid:</td>
                            <td class="value">{{ $invoice->currency }} {{ number_format((float)$invoice->amount_paid, 2) }}</td>
                        </tr>
                        @php
                            $outstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
                        @endphp
                        @if(bccomp($outstanding, '0', 2) > 0)
                            <tr class="outstanding-row">
                                <td class="label">Amount Due:</td>
                                <td class="value">{{ $invoice->currency }} {{ number_format((float)$outstanding, 2) }}</td>
                            </tr>
                        @endif
                    @endif
                </table>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="payment-info">
            <div class="payment-info-title">Payment Information</div>
            <div class="payment-info-content">
                <strong>Bank Transfer Details:</strong><br>
                Bank: {{ config('app.bank_name', 'Example Bank') }}<br>
                Account Name: {{ config('app.bank_account_name', 'Crurated Ltd') }}<br>
                IBAN: {{ config('app.bank_iban', 'GB00 XXXX 0000 0000 0000 00') }}<br>
                BIC/SWIFT: {{ config('app.bank_bic', 'XXXXGB2L') }}<br>
                Reference: {{ $invoice->invoice_number }}
            </div>
        </div>

        <!-- Notes Section (if applicable) -->
        @if($invoice->notes)
            <div class="notes-section">
                <div class="notes-title">Notes</div>
                <div class="notes-content">
                    {{ $invoice->notes }}
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <strong>Thank you for your business!</strong><br>
            If you have any questions about this invoice, please contact us at {{ config('app.company_email', 'billing@crurated.com') }}
            <div class="footer-legal">
                {{ config('app.company_name', 'Crurated') }} | {{ config('app.company_registration', 'Registered in England and Wales') }} | Company No: {{ config('app.company_number', '12345678') }}<br>
                This invoice was generated on {{ now()->format('d M Y H:i') }} UTC
            </div>
        </div>
    </div>
</body>
</html>
