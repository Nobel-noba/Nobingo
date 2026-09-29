<?php

namespace App\Http\Controllers\Platform;

use App\Domains\Platform\Models\PlatformSetting;
use App\Http\Controllers\Controller;
use App\Mail\PlatformTestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsController extends Controller
{
    /**
     * Display email service integration console.
     */
    public function index(): Response
    {
        $settings = PlatformSetting::getMailSettings();

        return Inertia::render('Platform/EmailSettings', [
            'mail_settings' => $settings,
            'default_driver' => config('mail.default'),
        ]);
    }

    /**
     * Save updated email configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:smtp,resend,log'],
            'host' => ['required_if:driver,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:driver,smtp', 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'resend_api_key' => ['required_if:driver,resend', 'nullable', 'string', 'max:255'],
        ]);

        $existing = PlatformSetting::get('mail_settings', []);

        // Preserve existing password if user didn't enter a new one
        if (empty($validated['password']) && ! empty($existing['password'])) {
            $validated['password'] = $existing['password'];
        }

        // Preserve existing resend_api_key if left blank
        if (empty($validated['resend_api_key']) && ! empty($existing['resend_api_key'])) {
            $validated['resend_api_key'] = $existing['resend_api_key'];
        }

        PlatformSetting::set('mail_settings', $validated);
        PlatformSetting::applyMailSettings();

        return redirect()->back()->with('success', 'Email integration settings saved successfully.');
    }

    /**
     * Send a live test email to verify configuration.
     */
    public function sendTest(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        PlatformSetting::applyMailSettings();

        try {
            $recipient = $validated['email'];
            $driver = config('mail.default');

            Mail::to($recipient)->send(new PlatformTestMail(
                recipientEmail: $recipient,
                sentAt: now()->toDateTimeString(),
                configurationSummary: [
                    'driver' => $driver,
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.scheme'),
                    'username' => config('mail.mailers.smtp.username'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                ]
            ));

            $message = "Test email successfully sent to {$recipient} via [".strtoupper($driver).'].';

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Test email delivery failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            $error = 'Email delivery failed: '.$e->getMessage();

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }

            return redirect()->back()->with('error', $error);
        }
    }
}
