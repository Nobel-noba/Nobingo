import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface MailSettingsData {
    driver: string;
    host: string;
    port: number;
    encryption: string;
    username: string;
    password: string;
    from_address: string;
    from_name: string;
    resend_api_key: string;
    has_password?: boolean;
}

interface Props extends PageProps {
    mail_settings: MailSettingsData;
    default_driver: string;
}

export default function EmailSettings({ mail_settings, default_driver, flash }: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const [testEmail, setTestEmail] = useState('');
    const [testingDelivery, setTestingDelivery] = useState(false);
    const [testFeedback, setTestFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        driver: mail_settings.driver || 'smtp',
        host: mail_settings.host || '',
        port: mail_settings.port || 587,
        encryption: mail_settings.encryption || 'tls',
        username: mail_settings.username || '',
        password: '',
        from_address: mail_settings.from_address || '',
        from_name: mail_settings.from_name || '',
        resend_api_key: mail_settings.resend_api_key || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/platform/settings/email', {
            preserveScroll: true,
        });
    };

    const handleSendTest = (e: React.FormEvent) => {
        e.preventDefault();
        if (!testEmail) return;

        setTestingDelivery(true);
        setTestFeedback(null);

        // Send via router.post with preserveScroll
        router.post('/platform/settings/email/test', {
            email: testEmail,
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flashSuccess = (page.props as any).flash?.success;
                const flashError = (page.props as any).flash?.error;
                if (flashSuccess) {
                    setTestFeedback({ type: 'success', message: flashSuccess });
                } else if (flashError) {
                    setTestFeedback({ type: 'error', message: flashError });
                }
                setTestingDelivery(false);
            },
            onError: (errs) => {
                setTestFeedback({
                    type: 'error',
                    message: Object.values(errs).join(', ') || 'Email delivery failed. Please check credentials.',
                });
                setTestingDelivery(false);
            },
            onFinish: () => {
                setTestingDelivery(false);
            },
        });
    };

    const driverBadgeColors = {
        smtp: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
        resend: 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
        log: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
    };

    return (
        <PlatformOwnerLayout>
            <Head title="Email Service Integration - Platform Owner" />

            <div className="max-w-5xl mx-auto space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-800">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-black tracking-tight text-white">
                                Email Service Integration
                            </h1>
                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-mono font-bold uppercase border ${
                                driverBadgeColors[data.driver as keyof typeof driverBadgeColors] || 'bg-slate-800 text-slate-300 border-slate-700'
                            }`}>
                                Active: {data.driver}
                            </span>
                        </div>
                        <p className="text-sm text-slate-400 mt-1">
                            Configure SMTP or API delivery for company onboarding, email verification OTPs, and password reset dispatches.
                        </p>
                    </div>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 rounded-2xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 text-sm flex items-center gap-3">
                        <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 rounded-2xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-sm flex items-center gap-3">
                        <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{flash.error}</span>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Main Settings Form */}
                    <div className="lg:col-span-2 space-y-6">
                        <form onSubmit={handleSubmit} className="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6">
                            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
                                <div>
                                    <h2 className="text-base font-bold text-white">Mail Transport Configuration</h2>
                                    <p className="text-xs text-slate-400 mt-0.5">Select and specify provider credentials</p>
                                </div>
                            </div>

                            {/* Driver Choice */}
                            <div>
                                <label className="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                                    Mail Transport Driver
                                </label>
                                <div className="grid grid-cols-3 gap-3">
                                    {[
                                        { id: 'smtp', title: 'SMTP Server', desc: 'Mailtrap, SES, SendGrid, etc.' },
                                        { id: 'resend', title: 'Resend API', desc: 'Modern email API service' },
                                        { id: 'log', title: 'Local Log', desc: 'Dev testing to laravel.log' },
                                    ].map((drv) => (
                                        <button
                                            key={drv.id}
                                            type="button"
                                            onClick={() => setData('driver', drv.id)}
                                            className={`p-3.5 rounded-2xl border text-left transition ${
                                                data.driver === drv.id
                                                    ? 'bg-rose-500/10 border-rose-500/60 ring-1 ring-rose-500'
                                                    : 'bg-slate-950/60 border-slate-800 hover:border-slate-700'
                                            }`}
                                        >
                                            <div className="text-xs font-bold text-white">{drv.title}</div>
                                            <div className="text-[10px] text-slate-400 mt-1">{drv.desc}</div>
                                        </button>
                                    ))}
                                </div>
                                {errors.driver && <p className="text-xs text-rose-400 mt-1">{errors.driver}</p>}
                            </div>

                            {/* SMTP Settings */}
                            {data.driver === 'smtp' && (
                                <div className="space-y-4 pt-4 border-t border-slate-800/80">
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div className="sm:col-span-2">
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                                SMTP Host <span className="text-rose-400">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                value={data.host}
                                                onChange={(e) => setData('host', e.target.value)}
                                                placeholder="e.g. smtp.resend.com or smtp.mailtrap.io"
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-600 focus:border-rose-500 focus:outline-none"
                                            />
                                            {errors.host && <p className="text-xs text-rose-400 mt-1">{errors.host}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                                Port <span className="text-rose-400">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                value={data.port}
                                                onChange={(e) => setData('port', parseInt(e.target.value) || 587)}
                                                placeholder="587"
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-600 focus:border-rose-500 focus:outline-none"
                                            />
                                            {errors.port && <p className="text-xs text-rose-400 mt-1">{errors.port}</p>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                                Encryption Scheme
                                            </label>
                                            <select
                                                value={data.encryption}
                                                onChange={(e) => setData('encryption', e.target.value)}
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-rose-500 focus:outline-none"
                                            >
                                                <option value="tls">TLS (Recommended - Port 587)</option>
                                                <option value="ssl">SSL (Port 465)</option>
                                                <option value="none">None (Plain SMTP)</option>
                                            </select>
                                            {errors.encryption && <p className="text-xs text-rose-400 mt-1">{errors.encryption}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                                SMTP Username
                                            </label>
                                            <input
                                                type="text"
                                                value={data.username}
                                                onChange={(e) => setData('username', e.target.value)}
                                                placeholder="API user or mailbox login"
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-600 focus:border-rose-500 focus:outline-none"
                                            />
                                            {errors.username && <p className="text-xs text-rose-400 mt-1">{errors.username}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <div className="flex items-center justify-between mb-1">
                                            <label className="text-xs font-semibold text-slate-300">
                                                SMTP Password / Secret
                                            </label>
                                            {mail_settings.has_password && (
                                                <span className="text-[10px] text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                                                    Password saved in database
                                                </span>
                                            )}
                                        </div>
                                        <div className="relative">
                                            <input
                                                type={showPassword ? 'text' : 'password'}
                                                value={data.password}
                                                onChange={(e) => setData('password', e.target.value)}
                                                placeholder={mail_settings.has_password ? '•••••••• (Leave blank to keep existing)' : 'Enter SMTP password or API token'}
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl pl-3.5 pr-20 py-2.5 text-xs text-white placeholder-slate-600 focus:border-rose-500 focus:outline-none"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword(!showPassword)}
                                                className="absolute right-3 top-2.5 text-[10px] font-bold text-slate-400 hover:text-white px-2 py-0.5 rounded bg-slate-800"
                                            >
                                                {showPassword ? 'Hide' : 'Show'}
                                            </button>
                                        </div>
                                        {errors.password && <p className="text-xs text-rose-400 mt-1">{errors.password}</p>}
                                    </div>
                                </div>
                            )}

                            {/* Resend API Key */}
                            {data.driver === 'resend' && (
                                <div className="space-y-4 pt-4 border-t border-slate-800/80">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">
                                            Resend API Key <span className="text-rose-400">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.resend_api_key}
                                            onChange={(e) => setData('resend_api_key', e.target.value)}
                                            placeholder="re_123456789_abcdef..."
                                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs font-mono text-white placeholder-slate-600 focus:border-rose-500 focus:outline-none"
                                        />
                                        <p className="text-[11px] text-slate-500 mt-1">
                                            Obtain your API key from <a href="https://resend.com/api-keys" target="_blank" rel="noreferrer" className="text-indigo-400 underline">resend.com/api-keys</a>
                                        </p>
                                        {errors.resend_api_key && <p className="text-xs text-rose-400 mt-1">{errors.resend_api_key}</p>}
                                    </div>
                                </div>
                            )}

                            {/* Global From Address & Name */}
                            <div className="space-y-4 pt-4 border-t border-slate-800/80">
                                <h3 className="text-xs font-bold text-slate-300 uppercase tracking-wider">
                                    Global Sender Profile
                                </h3>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">
                                            From Email Address <span className="text-rose-400">*</span>
                                        </label>
                                        <input
                                            type="email"
                                            value={data.from_address}
                                            onChange={(e) => setData('from_address', e.target.value)}
                                            placeholder="noreply@nobingo.live"
                                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-600 focus:border-rose-500 focus:outline-none"
                                        />
                                        {errors.from_address && <p className="text-xs text-rose-400 mt-1">{errors.from_address}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">
                                            From Sender Name <span className="text-rose-400">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.from_name}
                                            onChange={(e) => setData('from_name', e.target.value)}
                                            placeholder="Nobingo Platform"
                                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-600 focus:border-rose-500 focus:outline-none"
                                        />
                                        {errors.from_name && <p className="text-xs text-rose-400 mt-1">{errors.from_name}</p>}
                                    </div>
                                </div>
                            </div>

                            <div className="pt-4 border-t border-slate-800 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-6 py-2.5 rounded-xl bg-gradient-to-r from-rose-500 to-indigo-600 hover:from-rose-600 hover:to-indigo-700 text-white font-bold text-xs shadow-lg shadow-rose-500/20 disabled:opacity-50 transition"
                                >
                                    {processing ? 'Saving...' : 'Save Configuration'}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Right Column: Live Diagnostic Test Console */}
                    <div className="space-y-6">
                        <div className="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 space-y-4">
                            <div className="flex items-center gap-2">
                                <span className="text-xl">🚀</span>
                                <h3 className="text-sm font-bold text-white">Live Email Delivery Test</h3>
                            </div>
                            <p className="text-xs text-slate-400 leading-relaxed">
                                Verify outbound network connectivity, authentication credentials, and DKIM/SPF alignment by firing a test email immediately.
                            </p>

                            <form onSubmit={handleSendTest} className="space-y-3 pt-2">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                                        Test Recipient Email
                                    </label>
                                    <input
                                        type="email"
                                        required
                                        value={testEmail}
                                        onChange={(e) => setTestEmail(e.target.value)}
                                        placeholder="admin@example.com"
                                        className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={testingDelivery || !testEmail}
                                    className="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs disabled:opacity-50 transition shadow-md shadow-indigo-600/20"
                                >
                                    {testingDelivery ? 'Dispatched, awaiting handshake...' : 'Send Live Test Email'}
                                </button>
                            </form>

                            {/* Test Feedback */}
                            {testFeedback && (
                                <div className={`p-3.5 rounded-2xl text-xs border leading-relaxed ${
                                    testFeedback.type === 'success'
                                        ? 'bg-emerald-950/60 border-emerald-500/40 text-emerald-300'
                                        : 'bg-rose-950/60 border-rose-500/40 text-rose-300'
                                }`}>
                                    <div className="font-bold mb-1">
                                        {testFeedback.type === 'success' ? 'Delivery Successful' : 'Delivery Failed'}
                                    </div>
                                    <div className="break-words">{testFeedback.message}</div>
                                </div>
                            )}
                        </div>

                        {/* Provider Recommendations */}
                        <div className="bg-slate-900/50 border border-slate-800/80 rounded-3xl p-6 space-y-3 text-xs text-slate-400">
                            <h4 className="font-bold text-white text-xs uppercase tracking-wider">
                                Provider Setup Reference
                            </h4>
                            <ul className="space-y-2 leading-relaxed">
                                <li>
                                    <strong className="text-slate-200">Resend:</strong> Host: <code className="text-indigo-400">smtp.resend.com</code>, Port: <code className="text-indigo-400">587</code>, User: <code className="text-indigo-400">resend</code>, Pass: Your API Key.
                                </li>
                                <li>
                                    <strong className="text-slate-200">Mailtrap:</strong> Host: <code className="text-indigo-400">sandbox.smtp.mailtrap.io</code>, Port: <code className="text-indigo-400">2525</code> or <code className="text-indigo-400">587</code>.
                                </li>
                                <li>
                                    <strong className="text-slate-200">Amazon SES:</strong> Use regional endpoint (e.g. <code className="text-indigo-400">email-smtp.us-east-1.amazonaws.com</code>), Port <code className="text-indigo-400">587</code>.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </PlatformOwnerLayout>
    );
}
