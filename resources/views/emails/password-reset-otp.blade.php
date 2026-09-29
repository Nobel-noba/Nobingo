<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset Verification Code</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 20px; }
        .card { max-width: 580px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; padding: 36px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); }
        .logo { font-size: 24px; font-weight: 900; background: linear-gradient(135deg, #fbbf24, #f43f5e, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: 2px; }
        .badge { display: inline-block; background-color: rgba(244, 63, 94, 0.2); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 9999px; margin-top: 8px; }
        h1 { font-size: 22px; font-weight: 700; color: #ffffff; margin-top: 24px; margin-bottom: 12px; }
        p { font-size: 15px; line-height: 1.6; color: #cbd5e1; margin-bottom: 20px; }
        .otp-box { background-color: #0f172a; border: 2px dashed #f43f5e; border-radius: 12px; padding: 24px; text-align: center; margin: 28px 0; }
        .otp-code { font-size: 36px; font-weight: 900; letter-spacing: 10px; color: #fbbf24; font-family: monospace; }
        .btn { display: inline-block; background-color: #e11d48; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 14px; padding: 14px 28px; border-radius: 10px; text-align: center; box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3); }
        .footer { font-size: 12px; color: #64748b; text-align: center; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">NOBINGO</div>
        <div class="badge">Security Verification</div>

        <h1>Password Reset Authorization</h1>
        <p>A password reset has been initiated for the administrator account of <strong>{{ $company->name }}</strong> ({{ $adminUser->email }}).</p>

        <p>To proceed with changing your password, please provide the 6-digit security code below in your verification modal:</p>

        <div class="otp-box">
            <div style="font-size: 12px; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px; font-weight: 600;">One-Time Security Code</div>
            <div class="otp-code">{{ $otp }}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 8px;">Valid for 15 minutes • Do not share this code with anyone</div>
        </div>

        <p style="font-size: 13px; color: #94a3b8;">
            If you did not request this password reset or believe this to be an error, please contact the Nobingo platform owner immediately.
        </p>

        <div class="footer">
            &copy; {{ date('Y') }} Nobingo Security Operations. All rights reserved.
        </div>
    </div>
</body>
</html>
