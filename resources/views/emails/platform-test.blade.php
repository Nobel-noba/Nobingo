<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nobingo Platform - Email Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 20px; }
        .card { max-width: 580px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; padding: 36px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); }
        .logo { font-size: 24px; font-weight: 900; background: linear-gradient(135deg, #fbbf24, #f43f5e, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: 2px; }
        .badge { display: inline-block; background-color: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 9999px; margin-top: 8px; }
        h1 { font-size: 22px; font-weight: 700; color: #ffffff; margin-top: 24px; margin-bottom: 12px; }
        p { font-size: 15px; line-height: 1.6; color: #cbd5e1; margin-bottom: 20px; }
        .config-box { background-color: #0f172a; border-radius: 8px; border: 1px solid #334155; padding: 18px; margin: 20px 0; font-size: 13px; color: #94a3b8; }
        .config-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #1e293b; }
        .config-item:last-child { border-bottom: none; }
        .label { color: #64748b; font-weight: 600; }
        .value { color: #f1f5f9; font-family: monospace; font-weight: 600; }
        .footer { font-size: 12px; color: #64748b; text-align: center; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">NOBINGO</div>
        <div class="badge">&#10003; Integration Verified</div>

        <h1>Live Email Service Test</h1>
        <p>This is a live test transmission dispatched from your <strong>Nobingo Platform Owner Console</strong>. If you are receiving this message, your mail transport is configured and operational.</p>

        <div class="config-box">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #38bdf8; margin-bottom: 10px;">Active Configuration Summary</div>
            <div class="config-item">
                <span class="label">Mail Driver</span>
                <span class="value">{{ strtoupper($configurationSummary['driver'] ?? 'UNKNOWN') }}</span>
            </div>
            @if(($configurationSummary['driver'] ?? '') === 'smtp')
            <div class="config-item">
                <span class="label">SMTP Host</span>
                <span class="value">{{ $configurationSummary['host'] ?? 'N/A' }}</span>
            </div>
            <div class="config-item">
                <span class="label">Port / Encryption</span>
                <span class="value">{{ $configurationSummary['port'] ?? 'N/A' }} / {{ strtoupper($configurationSummary['encryption'] ?? 'TLS') }}</span>
            </div>
            <div class="config-item">
                <span class="label">Username</span>
                <span class="value">{{ $configurationSummary['username'] ? $configurationSummary['username'] : '(Anonymous)' }}</span>
            </div>
            @endif
            <div class="config-item">
                <span class="label">From Address</span>
                <span class="value">{{ $configurationSummary['from_address'] ?? 'N/A' }}</span>
            </div>
            <div class="config-item">
                <span class="label">From Name</span>
                <span class="value">{{ $configurationSummary['from_name'] ?? 'N/A' }}</span>
            </div>
            <div class="config-item">
                <span class="label">Delivered To</span>
                <span class="value">{{ $recipientEmail }}</span>
            </div>
            <div class="config-item">
                <span class="label">Dispatched At</span>
                <span class="value">{{ $sentAt }}</span>
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Nobingo Platform. Production Test Delivery.
        </div>
    </div>
</body>
</html>
