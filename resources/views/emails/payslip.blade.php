<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 0; background: #f5f5f5; }
    .wrapper { max-width: 560px; margin: 0 auto; padding: 32px 16px; }
    .card { background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .brand { font-size: 20px; font-weight: 800; color: #006241; margin-bottom: 24px; }
    .greeting { font-size: 16px; margin-bottom: 16px; }
    .summary { background: #f9f9f7; border-radius: 8px; padding: 16px; margin: 20px 0; }
    .summary-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
    .summary-row .label { color: #666; }
    .summary-row .value { font-weight: 600; font-variant-numeric: tabular-nums; }
    .summary-row.net { border-top: 2px solid #006241; margin-top: 8px; padding-top: 8px; }
    .summary-row.net .value { color: #006241; font-size: 18px; font-weight: 800; }
    .note { font-size: 13px; color: #888; margin-top: 20px; }
    .footer { text-align: center; font-size: 12px; color: #aaa; margin-top: 24px; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="brand">HRIS</div>
        <p class="greeting">Hi {{ $employeeName }},</p>
        <p>Your payslip for <strong>{{ $periodName }}</strong> is attached to this email.</p>

        <div class="summary">
            <div class="summary-row">
                <span class="label">Gross Pay</span>
                <span class="value">&#8369;{{ $grossPay }}</span>
            </div>
            <div class="summary-row">
                <span class="label">Total Deductions</span>
                <span class="value">(&#8369;{{ $totalDeductions }})</span>
            </div>
            <div class="summary-row net">
                <span class="label">Net Pay</span>
                <span class="value">&#8369;{{ $netPay }}</span>
            </div>
        </div>

        <p class="note">This is a system-generated email. Please contact HR if you have questions about your payslip.</p>
    </div>
    <p class="footer">&copy; {{ date('Y') }} HRIS. All rights reserved.</p>
</div>
</body>
</html>
