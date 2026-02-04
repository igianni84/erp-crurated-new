<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
        }
        .header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 2px solid #e5e5e5;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }
        .invoice-title {
            font-size: 18px;
            color: #666666;
        }
        .content {
            margin-bottom: 30px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .custom-message {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        .invoice-summary {
            background-color: #f0f9ff;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .invoice-summary h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1e40af;
            font-size: 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e7ff;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-label {
            color: #4b5563;
            font-size: 14px;
        }
        .summary-value {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }
        .total-row {
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #3b82f6;
        }
        .total-row .summary-label,
        .total-row .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
        }
        .attachment-notice {
            background-color: #f0fdf4;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #22c55e;
            margin-bottom: 25px;
        }
        .attachment-notice p {
            margin: 0;
            color: #166534;
            font-size: 14px;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #e5e5e5;
            color: #6b7280;
            font-size: 13px;
        }
        .footer p {
            margin: 5px 0;
        }
        .payment-info {
            background-color: #fefce8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #eab308;
        }
        .payment-info h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #854d0e;
            font-size: 15px;
        }
        .payment-info p {
            margin: 0;
            color: #713f12;
            font-size: 14px;
        }
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <div class="company-name">{{ config('app.name', 'ERP4') }}</div>
            <div class="invoice-title">Invoice {{ $invoice->invoice_number }}</div>
        </div>

        <div class="content">
            <p class="greeting">Dear {{ $invoice->customer?->name ?? 'Valued Customer' }},</p>

            @if($customMessage)
                <div class="custom-message">
                    {!! nl2br(e($customMessage)) !!}
                </div>
            @else
                <p>Please find attached your invoice for your records.</p>
            @endif

            <div class="invoice-summary">
                <h3>Invoice Summary</h3>
                <div class="summary-row">
                    <span class="summary-label">Invoice Number</span>
                    <span class="summary-value">{{ $invoice->invoice_number }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Invoice Date</span>
                    <span class="summary-value">{{ $invoice->issued_at?->format('d M Y') ?? 'N/A' }}</span>
                </div>
                @if($invoice->due_date)
                <div class="summary-row">
                    <span class="summary-label">Due Date</span>
                    <span class="summary-value">{{ $invoice->due_date->format('d M Y') }}</span>
                </div>
                @endif
                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Tax</span>
                    <span class="summary-value">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</span>
                </div>
                <div class="summary-row total-row">
                    <span class="summary-label">Total Amount</span>
                    <span class="summary-value">{{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</span>
                </div>
            </div>

            <div class="attachment-notice">
                <p><strong>📎 Attachment:</strong> The full invoice PDF is attached to this email for your records.</p>
            </div>

            @if($invoice->due_date && $invoice->status->value === 'issued')
            <div class="payment-info">
                <h4>Payment Information</h4>
                <p>Please ensure payment is made by {{ $invoice->due_date->format('d M Y') }} to avoid any late fees.</p>
            </div>
            @endif

            <p>If you have any questions regarding this invoice, please don't hesitate to contact us.</p>

            <p>Thank you for your business.</p>

            <p>Best regards,<br>
            The {{ config('app.name', 'ERP4') }} Team</p>
        </div>

        <div class="footer">
            <p>This is an automated email sent from {{ config('app.name', 'ERP4') }}.</p>
            <p>Invoice Reference: {{ $invoice->invoice_number }} | Type: {{ $invoice->invoice_type->label() }}</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'ERP4') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
