<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to Nobingo</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 20px; }
        .card { max-width: 580px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; padding: 36px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); }
        .logo { font-size: 24px; font-weight: 900; background: linear-gradient(135deg, #fbbf24, #f43f5e, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: 2px; }
        .badge { display: inline-block; background-color: rgba(99, 102, 241, 0.2); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 9999px; margin-top: 8px; }
        h1 { font-size: 22px; font-weight: 700; color: #ffffff; margin-top: 24px; margin-bottom: 12px; }
        p { font-size: 15px; line-height: 1.6; color: #cbd5e1; margin-bottom: 20px; }
        .otp-box { background-color: #0f172a; border: 1px dashed #6366f1; border-radius: 12px; padding: 20px; text-align: center; margin: 28px 0; }
        .otp-code { font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #fbbf24; font-family: monospace; }
        .credentials-box { background-color: #0f172a; border-radius: 8px; padding: 16px; margin: 20px 0; font-size: 14px; color: #94a3b8; }
        .btn { display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 14px; padding: 14px 28px; border-radius: 10px; text-align: center; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .footer { font-size: 12px; color: #64748b; text-align: center; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">NOBINGO</div>
        <div class="badge">Multi-Tenant Platform</div>

        <h1>Welcome to Nobingo, {{ $adminUser->name }}!</h1>
        <p>Your dedicated company installation <strong>{{ $company->name }}</strong> (slug: <code>{{ $company->slug }}</code>) has been successfully provisioned by the platform owner.</p>

        <p>To verify your email address and activate your administrator console, use the 6-digit verification code below or click the direct activation button:</p>

        <div class="otp-box">
            <div style="font-size: 12px; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px; font-weight: 600;">Your 6-Digit Activation Code</div>
            <div class="otp-code">{{ $otp }}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 8px;">Expires in 15 minutes</div>
        </div>

        @if ($temporaryPassword)
        <div class="credentials-box">
            <strong style="color: #f1f5f9;">Your Login Credentials:</strong><br>
            Email: <code>{{ $adminUser->email }}</code><br>
            Password: <code>{{ $temporaryPassword }}</code>
        </div>
        @endif

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $verificationUrl }}" class="btn">Verify & Activate Account &rarr;</a>
        </div>

        <p style="font-size: 13px; color: #94a3b8;">
            Direct URL: <a href="{{ $verificationUrl }}" style="color: #818cf8; word-break: break-all;">{{ $verificationUrl }}</a>
        </p>

        <div class="footer">
            &copy; {{ date('Y') }} Nobingo Platform. All rights reserved. If you did not request this, please disregard.
        </div>
    </div>
</body>
</html>
