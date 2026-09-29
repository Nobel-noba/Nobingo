import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps, Tenant } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    status: string;
    email_verified_at: string | null;
    created_at: string;
}

interface RecentGame {
    id: number;
    game_number: number;
    name: string;
    status: string;
    entry_fee: number;
    players_count: number;
    started_at: string | null;
}

interface Props extends PageProps {
    company: Tenant & {
        domain?: string | null;
        users_count?: number;
        cards_count?: number;
    };
    admins: AdminUser[];
    stats: {
        total_cards: number;
        available_cards: number;
        total_games: number;
        active_games: number;
        total_players: number;
        total_transactions_volume: number;
    };
    recent_games: RecentGame[];
}

export default function CompanyShow({ company, admins, stats, recent_games }: Props) {
    const [selectedAdmin, setSelectedAdmin] = useState<AdminUser | null>(null);
    const [showDirectPasswordModal, setShowDirectPasswordModal] = useState(false);
    const [showOtpResetModal, setShowOtpResetModal] = useState(false);
    const [otpSentMessage, setOtpSentMessage] = useState<string | null>(null);

    // Form for Settings Update
    const { data: settingsData, setData: setSettingsData, patch: patchSettings, processing: processingSettings } = useForm({
        name: company.name,
        domain: company.domain || '',
        tagline: company.settings?.tagline || '',
        brand_color: company.settings?.brand_color || '#4f46e5',
        currency: company.settings?.currency || 'USD',
    });

    // Form for Direct Password Override
    const directPasswordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    // Form for OTP Password Reset
    const otpResetForm = useForm({
        otp: '',
        password: '',
        password_confirmation: '',
    });

    const submitSettings: FormEventHandler = (e) => {
        e.preventDefault();
        patchSettings(`/platform/companies/${company.id}/settings`);
    };

    const toggleStatus = () => {
        const action = company.status === 'active' ? 'REVOKE ACCESS from and SUSPEND' : 'RESTORE ACCESS to';
        if (confirm(`Are you sure you want to ${action} ${company.name}?`)) {
            router.patch(`/platform/companies/${company.id}/status`);
        }
    };

    const openDirectPasswordModal = (admin: AdminUser) => {
        setSelectedAdmin(admin);
        directPasswordForm.reset();
        setShowDirectPasswordModal(true);
    };

    const submitDirectPassword: FormEventHandler = (e) => {
        e.preventDefault();
        if (!selectedAdmin) return;
        directPasswordForm.post(`/platform/companies/${company.id}/users/${selectedAdmin.id}/password-direct`, {
            onSuccess: () => {
                setShowDirectPasswordModal(false);
                directPasswordForm.reset();
            },
        });
    };

    const requestEmailOtp = (admin: AdminUser) => {
        setSelectedAdmin(admin);
        otpResetForm.reset();
        router.post(`/platform/companies/${company.id}/users/${admin.id}/request-password-reset`, {}, {
            onSuccess: () => {
                setOtpSentMessage(`A 6-digit verification code has been dispatched to ${admin.email}. Enter it below along with the new password.`);
                setShowOtpResetModal(true);
            },
        });
    };

    const submitOtpReset: FormEventHandler = (e) => {
        e.preventDefault();
        if (!selectedAdmin) return;
        otpResetForm.post(`/platform/companies/${company.id}/users/${selectedAdmin.id}/confirm-password-reset`, {
            onSuccess: () => {
                setShowOtpResetModal(false);
                otpResetForm.reset();
                setOtpSentMessage(null);
            },
        });
    };

    return (
        <PlatformOwnerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center space-x-3">
                        <Link href="/platform/companies" className="text-xs text-slate-400 hover:text-white">
                            &larr; Companies
                        </Link>
                        <span className="text-slate-600">/</span>
                        <div
                            className="h-8 w-8 rounded-lg flex items-center justify-center font-bold text-white text-sm"
                            style={{ backgroundColor: company.settings?.brand_color || '#4f46e5' }}
                        >
                            {company.name.charAt(0)}
                        </div>
                        <h1 className="text-xl font-bold text-white tracking-tight">{company.name}</h1>
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
                            company.status === 'active'
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                        }`}>
                            {company.status}
                        </span>
                    </div>

                    <div className="flex items-center space-x-2">
                        <button
                            onClick={toggleStatus}
                            className={`px-4 py-2 rounded-xl text-xs font-bold transition border ${
                                company.status === 'active'
                                    ? 'bg-rose-950/40 hover:bg-rose-900/60 text-rose-300 border-rose-700/60 shadow-sm'
                                    : 'bg-emerald-950/40 hover:bg-emerald-900/60 text-emerald-300 border-emerald-700/60 shadow-sm'
                            }`}
                        >
                            {company.status === 'active' ? 'Revoke Company Access (Suspend)' : 'Restore Company Access'}
                        </button>
                        <a
                            href={`/c/${company.slug}/admin`}
                            target="_blank"
                            rel="noreferrer"
                            className="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold px-3.5 py-2 rounded-xl border border-slate-700 transition"
                        >
                            View Company Console &nearr;
                        </a>
                    </div>
                </div>
            }
        >
            <Head title={`Manage ${company.name}`} />

            {/* Metrics Overview Cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
                    <p className="text-xs uppercase font-bold text-slate-500">Fixed Cards Buffer</p>
                    <p className="text-2xl font-black text-white mt-1">{stats.total_cards}</p>
                    <p className="text-[11px] text-slate-400 mt-1">
                        <span className="text-emerald-400 font-semibold">{stats.available_cards}</span> available unassigned
                    </p>
                </div>
                <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
                    <p className="text-xs uppercase font-bold text-slate-500">Registered Players</p>
                    <p className="text-2xl font-black text-white mt-1">{stats.total_players}</p>
                    <p className="text-[11px] text-slate-400 mt-1">Tenant player accounts</p>
                </div>
                <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
                    <p className="text-xs uppercase font-bold text-slate-500">Games Hosted</p>
                    <p className="text-2xl font-black text-white mt-1">{stats.total_games}</p>
                    <p className="text-[11px] text-slate-400 mt-1">
                        <span className="text-amber-400 font-semibold">{stats.active_games}</span> currently active
                    </p>
                </div>
                <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
                    <p className="text-xs uppercase font-bold text-slate-500">Ledger Volume</p>
                    <p className="text-2xl font-black text-white mt-1">${(stats.total_transactions_volume / 100).toFixed(2)}</p>
                    <p className="text-[11px] text-slate-400 mt-1">{company.settings?.currency || 'USD'} total gross</p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Left 2 Cols: Administrator Accounts & Password Controls */}
                <div className="lg:col-span-2 space-y-8">
                    {/* Admins Card */}
                    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm">
                        <div className="flex justify-between items-center mb-4">
                            <div>
                                <h3 className="text-base font-bold text-white">Company Administrators & Credentials</h3>
                                <p className="text-xs text-slate-400">Manage login credentials, password resets, and email verifications</p>
                            </div>
                        </div>

                        {admins.length === 0 ? (
                            <div className="text-center py-8 text-xs text-slate-500">
                                No administrators assigned to this company installation yet.
                            </div>
                        ) : (
                            <div className="divide-y divide-slate-800">
                                {admins.map((admin) => (
                                    <div key={admin.id} className="py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <div>
                                            <div className="flex items-center space-x-2">
                                                <span className="text-sm font-bold text-white">{admin.name}</span>
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold ${
                                                    admin.status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'
                                                }`}>
                                                    {admin.status}
                                                </span>
                                            </div>
                                            <p className="text-xs font-mono text-slate-400 mt-0.5">{admin.email}</p>
                                            <div className="mt-1 flex items-center space-x-3 text-[11px] text-slate-500">
                                                <span>Verification:</span>
                                                {admin.email_verified_at ? (
                                                    <span className="text-emerald-400 font-semibold">✓ Verified ({new Date(admin.email_verified_at).toLocaleDateString()})</span>
                                                ) : (
                                                    <span className="text-amber-400 font-semibold">◌ Pending Verification</span>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center space-x-2 self-start sm:self-auto">
                                            <button
                                                onClick={() => openDirectPasswordModal(admin)}
                                                className="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-700 transition"
                                            >
                                                Change Password
                                            </button>
                                            <button
                                                onClick={() => requestEmailOtp(admin)}
                                                className="bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                                            >
                                                Send Reset OTP
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Recent Games */}
                    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-base font-bold text-white">Recent Bingo Games</h3>
                            <a
                                href={`/c/${company.slug}/admin/games`}
                                target="_blank"
                                rel="noreferrer"
                                className="text-xs text-rose-400 hover:underline"
                            >
                                View All Games &rarr;
                            </a>
                        </div>

                        {recent_games.length === 0 ? (
                            <p className="text-xs text-slate-500 py-4">No bingo games hosted yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-slate-800 text-slate-400">
                                            <th className="pb-2 font-semibold"># Number</th>
                                            <th className="pb-2 font-semibold">Name</th>
                                            <th className="pb-2 font-semibold">Status</th>
                                            <th className="pb-2 font-semibold">Players</th>
                                            <th className="pb-2 font-semibold">Entry Fee</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/60">
                                        {recent_games.map((g) => (
                                            <tr key={g.id}>
                                                <td className="py-2.5 font-mono text-slate-300">#{g.game_number}</td>
                                                <td className="py-2.5 font-semibold text-white">{g.name}</td>
                                                <td className="py-2.5">
                                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                                                        g.status === 'active' ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-800 text-slate-300'
                                                    }`}>
                                                        {g.status}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 text-slate-300">{g.players_count}</td>
                                                <td className="py-2.5 text-slate-300">${(g.entry_fee / 100).toFixed(2)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>

                {/* Right 1 Col: Company Settings & Brand */}
                <div>
                    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm">
                        <h3 className="text-base font-bold text-white mb-1">Company Installation Settings</h3>
                        <p className="text-xs text-slate-400 mb-5">Customize tenant brand, domain, and currency</p>

                        <form onSubmit={submitSettings} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">Company Name</label>
                                <input
                                    type="text"
                                    required
                                    value={settingsData.name}
                                    onChange={(e) => setSettingsData('name', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">Custom Domain</label>
                                <input
                                    type="text"
                                    value={settingsData.domain}
                                    onChange={(e) => setSettingsData('domain', e.target.value)}
                                    placeholder="bingo.example.com"
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">Tagline</label>
                                <input
                                    type="text"
                                    value={settingsData.tagline}
                                    onChange={(e) => setSettingsData('tagline', e.target.value)}
                                    placeholder="Premier Bingo Room"
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">Brand Color</label>
                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="color"
                                            value={settingsData.brand_color}
                                            onChange={(e) => setSettingsData('brand_color', e.target.value)}
                                            className="h-8 w-10 bg-transparent cursor-pointer rounded border border-slate-700"
                                        />
                                        <input
                                            type="text"
                                            value={settingsData.brand_color}
                                            onChange={(e) => setSettingsData('brand_color', e.target.value)}
                                            className="w-full bg-slate-950 border border-slate-700 rounded-xl px-2.5 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-rose-500"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">Currency</label>
                                    <input
                                        type="text"
                                        maxLength={3}
                                        value={settingsData.currency}
                                        onChange={(e) => setSettingsData('currency', e.target.value.toUpperCase())}
                                        className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-rose-500 uppercase"
                                    />
                                </div>
                            </div>

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={processingSettings}
                                    className="w-full bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold py-2.5 rounded-xl border border-slate-700 transition"
                                >
                                    {processingSettings ? 'Saving Settings...' : 'Save Company Settings'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {/* Direct Password Modal */}
            {showDirectPasswordModal && selectedAdmin && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-base font-bold text-white">Change Admin Password</h3>
                            <button onClick={() => setShowDirectPasswordModal(false)} className="text-slate-400 hover:text-white">&times;</button>
                        </div>
                        <p className="text-xs text-slate-400 mb-4">
                            Directly override the password for <strong>{selectedAdmin.name}</strong> ({selectedAdmin.email}).
                        </p>

                        <form onSubmit={submitDirectPassword} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">New Password (min 8) *</label>
                                <input
                                    type="password"
                                    required
                                    value={directPasswordForm.data.password}
                                    onChange={(e) => directPasswordForm.setData('password', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                />
                                {directPasswordForm.errors.password && <span className="text-rose-400 text-[11px]">{directPasswordForm.errors.password}</span>}
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">Confirm New Password *</label>
                                <input
                                    type="password"
                                    required
                                    value={directPasswordForm.data.password_confirmation}
                                    onChange={(e) => directPasswordForm.setData('password_confirmation', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                />
                            </div>

                            <div className="flex justify-end space-x-2 pt-3 border-t border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setShowDirectPasswordModal(false)}
                                    className="px-3.5 py-2 text-xs text-slate-400 hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={directPasswordForm.processing}
                                    className="bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition"
                                >
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Email OTP Password Reset Modal */}
            {showOtpResetModal && selectedAdmin && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-base font-bold text-white">Email-Verified Password Reset</h3>
                            <button onClick={() => setShowOtpResetModal(false)} className="text-slate-400 hover:text-white">&times;</button>
                        </div>
                        {otpSentMessage && (
                            <div className="bg-emerald-950/40 border border-emerald-700/60 text-emerald-300 text-xs p-3 rounded-xl mb-4">
                                {otpSentMessage}
                            </div>
                        )}

                        <form onSubmit={submitOtpReset} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">6-Digit Security OTP Code *</label>
                                <input
                                    type="text"
                                    required
                                    maxLength={6}
                                    placeholder="123456"
                                    value={otpResetForm.data.otp}
                                    onChange={(e) => otpResetForm.setData('otp', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-center text-lg font-mono tracking-widest text-amber-300 focus:outline-none focus:border-rose-500"
                                />
                                {otpResetForm.errors.otp && <span className="text-rose-400 text-[11px]">{otpResetForm.errors.otp}</span>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">New Password (min 8) *</label>
                                <input
                                    type="password"
                                    required
                                    value={otpResetForm.data.password}
                                    onChange={(e) => otpResetForm.setData('password', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                />
                                {otpResetForm.errors.password && <span className="text-rose-400 text-[11px]">{otpResetForm.errors.password}</span>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">Confirm New Password *</label>
                                <input
                                    type="password"
                                    required
                                    value={otpResetForm.data.password_confirmation}
                                    onChange={(e) => otpResetForm.setData('password_confirmation', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                />
                            </div>

                            <div className="flex justify-end space-x-2 pt-3 border-t border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setShowOtpResetModal(false)}
                                    className="px-3.5 py-2 text-xs text-slate-400 hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={otpResetForm.processing}
                                    className="bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition"
                                >
                                    Verify OTP & Reset Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </PlatformOwnerLayout>
    );
}
