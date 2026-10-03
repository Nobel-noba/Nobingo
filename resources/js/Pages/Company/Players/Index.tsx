import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface PlayerItem {
    id: number;
    name: string;
    email: string;
    status: string;
    balance: number;
    formatted_balance: string;
    win_count: number;
    games_count: number;
    created_at: string;
}

interface AvailablePlayer {
    id: number;
    name: string;
    email: string;
    formatted_balance: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    players: {
        data: PlayerItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    available_players?: AvailablePlayer[];
    is_game_manager_view?: boolean;
    filters: {
        search: string;
        status: string;
    };
}

export default function PlayersIndex({ auth, company, players, available_players = [], is_game_manager_view = false, filters }: Props) {
    const isGameManagerOnly = Boolean(is_game_manager_view || (auth.user?.is_game_manager && !auth.user?.is_company_admin && !auth.user?.is_platform_owner));
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
    const [adjustingPlayer, setAdjustingPlayer] = useState<PlayerItem | null>(null);
    const [withdrawPlayer, setWithdrawPlayer] = useState<PlayerItem | null>(null);
    const [registerModalOpen, setRegisterModalOpen] = useState(false);
    const [authorizeModalOpen, setAuthorizeModalOpen] = useState(false);
    const [resetPassPlayer, setResetPassPlayer] = useState<PlayerItem | null>(null);

    const adjustForm = useForm({
        amount: 10,
        is_credit: true,
        reason: 'Administrative courtesy',
    });

    const withdrawForm = useForm({
        amount: 10,
        notes: 'Counter cash withdrawal',
    });

    const authorizeForm = useForm({
        player_id: '',
        initial_deposit: 0,
    });

    const registerForm = useForm({
        name: '',
        email: '',
        password: '',
        initial_deposit: 0,
    });

    const resetPassForm = useForm({
        password: '',
    });

    const generateRandomPassword = () => {
        const pass = 'Bingo' + Math.floor(1000 + Math.random() * 9000) + '!';
        return pass;
    };

    const handleFilter = (statusVal: string, searchVal: string) => {
        router.get(
            `/c/${company.slug}/admin/players`,
            {
                status: statusVal !== 'all' ? statusVal : undefined,
                search: searchVal || undefined,
            },
            { preserveState: true }
        );
    };

    const handleToggleStatus = (playerId: number) => {
        router.patch(`/c/${company.slug}/admin/players/${playerId}/toggle-status`, {}, {
            preserveScroll: true,
        });
    };

    const handleWithdrawSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!withdrawPlayer) return;

        withdrawForm.post(`/c/${company.slug}/admin/players/${withdrawPlayer.id}/withdraw`, {
            preserveScroll: true,
            onSuccess: () => {
                setWithdrawPlayer(null);
                withdrawForm.reset();
            },
        });
    };

    const handleAuthorizeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        authorizeForm.post(`/c/${company.slug}/admin/players/authorize-existing`, {
            preserveScroll: true,
            onSuccess: () => {
                setAuthorizeModalOpen(false);
                authorizeForm.reset();
            },
        });
    };

    const handleAdjustSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!adjustingPlayer) return;

        adjustForm.post(`/c/${company.slug}/admin/players/${adjustingPlayer.id}/adjust-balance`, {
            preserveScroll: true,
            onSuccess: () => {
                setAdjustingPlayer(null);
                adjustForm.reset();
            },
        });
    };

    const handleRegisterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        registerForm.post(`/c/${company.slug}/admin/players`, {
            preserveScroll: true,
            onSuccess: () => {
                setRegisterModalOpen(false);
                registerForm.reset();
            },
        });
    };

    const handleResetPassSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resetPassPlayer) return;
        resetPassForm.post(`/c/${company.slug}/admin/players/${resetPassPlayer.id}/reset-password`, {
            preserveScroll: true,
            onSuccess: () => {
                setResetPassPlayer(null);
                resetPassForm.reset();
            },
        });
    };

    return (
        <CompanyAdminLayout>
            <Head title={`Player Management - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-white tracking-tight">Player Directory & Moderation</h1>
                        <p className="text-sm text-neutral-400 mt-1">
                            {isGameManagerOnly
                                ? 'Authorize company players for your games, provision new accounts, and disburse cash deposits & withdrawals.'
                                : 'Register new players, issue temporary credentials, manage account status, and perform adjustments.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                        {isGameManagerOnly && (
                            <button
                                type="button"
                                onClick={() => {
                                    authorizeForm.reset();
                                    setAuthorizeModalOpen(true);
                                }}
                                className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md flex items-center gap-2 transition cursor-pointer"
                            >
                                <span>➕ Authorize Existing Player</span>
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => {
                                registerForm.setData({
                                    name: '',
                                    email: '',
                                    password: generateRandomPassword(),
                                    initial_deposit: 0,
                                });
                                setRegisterModalOpen(true);
                            }}
                            className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md flex items-center gap-2 transition cursor-pointer"
                        >
                            <span>+ Register New Player</span>
                        </button>
                    </div>
                </div>

                {isGameManagerOnly && (
                    <div className="bg-indigo-950/40 border border-indigo-500/30 rounded-xl p-4 flex items-start gap-3 text-xs text-indigo-200">
                        <span className="text-lg">🛡️</span>
                        <div>
                            <strong className="text-white block font-semibold mb-0.5">Isolated Game Manager Roster & Wallets</strong>
                            <p className="text-indigo-300">
                                You only see players authorized for your games. Wallet balances shown here are strictly isolated to your games. Deposits and cash withdrawals made here stay within your custody and audit trail.
                            </p>
                        </div>
                    </div>
                )}

                {/* Filter & Search Bar */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                    <div className="flex items-center space-x-3">
                        <span className="text-xs font-semibold text-neutral-400 uppercase">Status:</span>
                        <select
                            value={selectedStatus}
                            onChange={(e) => {
                                setSelectedStatus(e.target.value);
                                handleFilter(e.target.value, search);
                            }}
                            className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2"
                        >
                            <option value="all">All Accounts</option>
                            <option value="active">Active Only</option>
                            <option value="suspended">Suspended Only</option>
                        </select>
                    </div>

                    <div className="flex items-center space-x-2">
                        <input
                            type="text"
                            placeholder="Search player name or email..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleFilter(selectedStatus, search);
                                }
                            }}
                            className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2 w-72 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button
                            onClick={() => handleFilter(selectedStatus, search)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition"
                        >
                            Filter
                        </button>
                    </div>
                </div>

                {/* Players Table */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Player</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">{isGameManagerOnly ? 'Manager Wallet' : 'Wallet Balance'}</th>
                                    <th className="px-4 py-3">Games</th>
                                    <th className="px-4 py-3">Wins</th>
                                    <th className="px-4 py-3">Joined</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {players.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-8 text-center text-neutral-500">
                                            No players found matching your criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    players.data.map((player) => (
                                        <tr key={player.id} className="hover:bg-neutral-800/40 transition">
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <div className="font-semibold text-neutral-200">{player.name}</div>
                                                <div className="text-xs text-neutral-500">{player.email}</div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ${
                                                        player.status === 'active'
                                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                                                    }`}
                                                >
                                                    {player.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap font-mono font-bold text-emerald-400">
                                                {player.formatted_balance}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-neutral-300">
                                                {player.games_count}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs font-bold text-amber-400">
                                                {player.win_count}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-neutral-400">
                                                {player.created_at}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-right space-x-2">
                                                <Link
                                                    href={`/c/${company.slug}/admin/players/${player.id}`}
                                                    className="text-xs bg-neutral-800 hover:bg-neutral-700 text-neutral-200 px-2.5 py-1 rounded transition border border-neutral-700"
                                                >
                                                    Profile
                                                </Link>
                                                <button
                                                    onClick={() => setAdjustingPlayer(player)}
                                                    className="text-xs bg-indigo-950/60 hover:bg-indigo-900 text-indigo-300 border border-indigo-700/60 px-2.5 py-1 rounded transition"
                                                    title="Deposit / adjust funds"
                                                >
                                                    Deposit $
                                                </button>
                                                <button
                                                    onClick={() => {
                                                        setWithdrawPlayer(player);
                                                        withdrawForm.setData({ amount: 10, notes: 'Counter cash withdrawal' });
                                                    }}
                                                    className="text-xs bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-700/60 px-2.5 py-1 rounded transition"
                                                    title="Disburse counter cash withdrawal"
                                                >
                                                    Cash Out
                                                </button>
                                                <button
                                                    onClick={() => {
                                                        setResetPassPlayer(player);
                                                        resetPassForm.setData('password', generateRandomPassword());
                                                    }}
                                                    className="text-xs bg-amber-950/60 hover:bg-amber-900 text-amber-300 border border-amber-700/60 px-2.5 py-1 rounded transition"
                                                    title="Set temporary password"
                                                >
                                                    Reset Pass
                                                </button>
                                                {!isGameManagerOnly && (
                                                    <button
                                                        onClick={() => handleToggleStatus(player.id)}
                                                        className={`text-xs px-2.5 py-1 rounded transition border ${
                                                            player.status === 'active'
                                                                ? 'bg-rose-950/60 hover:bg-rose-900 text-rose-300 border-rose-700/60'
                                                                : 'bg-emerald-950/60 hover:bg-emerald-900 text-emerald-300 border-emerald-700/60'
                                                        }`}
                                                    >
                                                        {player.status === 'active' ? 'Suspend' : 'Activate'}
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {players.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-neutral-800 flex items-center justify-between">
                            <div className="text-xs text-neutral-500">
                                Showing {players.current_page} of {players.last_page} ({players.total} players)
                            </div>
                            <div className="flex space-x-1">
                                {players.links.map((link, idx) => (
                                    <button
                                        key={idx}
                                        disabled={!link.url}
                                        onClick={() => link.url && router.visit(link.url)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 text-xs rounded border transition ${
                                            link.active
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : link.url
                                                ? 'bg-neutral-950 text-neutral-300 border-neutral-800 hover:bg-neutral-800'
                                                : 'bg-neutral-950 text-neutral-600 border-neutral-900 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Adjust Balance Modal */}
            {adjustingPlayer && (
                <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center">
                            <h3 className="text-lg font-bold text-white">Adjust Player Balance</h3>
                            <button
                                onClick={() => setAdjustingPlayer(null)}
                                className="text-neutral-400 hover:text-white"
                            >
                                ✕
                            </button>
                        </div>
                        <p className="text-xs text-neutral-400">
                            Modifying balance for <span className="text-white font-semibold">{adjustingPlayer.name}</span>. Current balance: <span className="text-emerald-400 font-mono font-bold">{adjustingPlayer.formatted_balance}</span>.
                        </p>

                        <form onSubmit={handleAdjustSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs uppercase font-semibold text-neutral-400 mb-1">Adjustment Type</label>
                                <div className="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => adjustForm.setData('is_credit', true)}
                                        className={`py-2 text-xs font-bold rounded-lg border transition ${
                                            adjustForm.data.is_credit
                                                ? 'bg-emerald-600 text-white border-emerald-500'
                                                : 'bg-neutral-950 text-neutral-400 border-neutral-800'
                                        }`}
                                    >
                                        + Credit (Deposit)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => adjustForm.setData('is_credit', false)}
                                        className={`py-2 text-xs font-bold rounded-lg border transition ${
                                            !adjustForm.data.is_credit
                                                ? 'bg-rose-600 text-white border-rose-500'
                                                : 'bg-neutral-950 text-neutral-400 border-neutral-800'
                                        }`}
                                    >
                                        - Debit (Deduct)
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs uppercase font-semibold text-neutral-400 mb-1">Amount ($ USD)</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="50000"
                                    value={adjustForm.data.amount}
                                    onChange={(e) => adjustForm.setData('amount', parseInt(e.target.value) || 1)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono font-bold focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs uppercase font-semibold text-neutral-400 mb-1">Reason / Audit Note</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. VIP goodwill bonus, discrepancy correction"
                                    value={adjustForm.data.reason}
                                    onChange={(e) => adjustForm.setData('reason', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>

                            <div className="flex justify-end space-x-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setAdjustingPlayer(null)}
                                    className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={adjustForm.processing}
                                    className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 transition shadow"
                                >
                                    {adjustForm.processing ? 'Processing...' : 'Save Adjustment'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Register New Player Modal */}
            {registerModalOpen && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-neutral-800 pb-3">
                            <h3 className="font-bold text-white text-base flex items-center gap-2">
                                <span>👤</span>
                                <span>Register New Player Account</span>
                            </h3>
                            <button
                                onClick={() => setRegisterModalOpen(false)}
                                className="text-neutral-400 hover:text-white text-xs font-bold"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 text-xs text-amber-300">
                            <strong>Note:</strong> Public self-registration is closed. Players are provisioned here with temporary credentials and will be prompted to set a new personal password on first login.
                        </div>

                        <form onSubmit={handleRegisterSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Full Name</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. John Doe"
                                    value={registerForm.data.name}
                                    onChange={(e) => registerForm.setData('name', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {registerForm.errors.name && (
                                    <div className="text-rose-400 text-[11px] mt-1">{registerForm.errors.name}</div>
                                )}
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Email Address</label>
                                <input
                                    type="email"
                                    required
                                    placeholder="player@example.com"
                                    value={registerForm.data.email}
                                    onChange={(e) => registerForm.setData('email', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {registerForm.errors.email && (
                                    <div className="text-rose-400 text-[11px] mt-1">{registerForm.errors.email}</div>
                                )}
                            </div>

                            <div>
                                <div className="flex justify-between items-center mb-1">
                                    <label className="block uppercase font-bold text-neutral-400">Temporary Password</label>
                                    <button
                                        type="button"
                                        onClick={() => registerForm.setData('password', generateRandomPassword())}
                                        className="text-[11px] text-emerald-400 hover:text-emerald-300 underline font-semibold"
                                    >
                                        Generate Random
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    required
                                    value={registerForm.data.password}
                                    onChange={(e) => registerForm.setData('password', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {registerForm.errors.password && (
                                    <div className="text-rose-400 text-[11px] mt-1">{registerForm.errors.password}</div>
                                )}
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Initial Cash Deposit ($ USD, optional)</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="50000"
                                    step="0.01"
                                    value={registerForm.data.initial_deposit}
                                    onChange={(e) => registerForm.setData('initial_deposit', parseFloat(e.target.value) || 0)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="0.00"
                                />
                            </div>

                            <div className="flex justify-end space-x-2 pt-2 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setRegisterModalOpen(false)}
                                    className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={registerForm.processing}
                                    className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 transition shadow"
                                >
                                    {registerForm.processing ? 'Registering...' : 'Register Player'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reset Password Modal */}
            {resetPassPlayer && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-neutral-800 pb-3">
                            <h3 className="font-bold text-white text-base flex items-center gap-2">
                                <span>🔑</span>
                                <span>Reset Temporary Password</span>
                            </h3>
                            <button
                                onClick={() => setResetPassPlayer(null)}
                                className="text-neutral-400 hover:text-white text-xs font-bold"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="text-xs text-neutral-300">
                            Resetting password for: <strong className="text-white">{resetPassPlayer.name}</strong> ({resetPassPlayer.email}).
                            The player will be required to change this password upon their next login.
                        </div>

                        <form onSubmit={handleResetPassSubmit} className="space-y-4 text-xs">
                            <div>
                                <div className="flex justify-between items-center mb-1">
                                    <label className="block uppercase font-bold text-neutral-400">New Temporary Password</label>
                                    <button
                                        type="button"
                                        onClick={() => resetPassForm.setData('password', generateRandomPassword())}
                                        className="text-[11px] text-amber-400 hover:text-amber-300 underline font-semibold"
                                    >
                                        Generate
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    required
                                    value={resetPassForm.data.password}
                                    onChange={(e) => resetPassForm.setData('password', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono focus:ring-amber-500 focus:border-amber-500"
                                />
                                {resetPassForm.errors.password && (
                                    <div className="text-rose-400 text-[11px] mt-1">{resetPassForm.errors.password}</div>
                                )}
                            </div>

                            <div className="flex justify-end space-x-2 pt-2 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setResetPassPlayer(null)}
                                    className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={resetPassForm.processing}
                                    className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-amber-600 hover:bg-amber-500 transition shadow"
                                >
                                    {resetPassForm.processing ? 'Updating...' : 'Set Temporary Password'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Counter Cash Withdrawal Modal */}
            {withdrawPlayer && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-neutral-800 pb-3">
                            <h3 className="font-bold text-white text-base flex items-center gap-2">
                                <span>💵</span>
                                <span>Counter Cash Out / Withdrawal</span>
                            </h3>
                            <button
                                onClick={() => setWithdrawPlayer(null)}
                                className="text-neutral-400 hover:text-white text-xs font-bold"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="text-xs text-neutral-300">
                            Disbursing cash to <strong className="text-white">{withdrawPlayer.name}</strong>. Current isolated balance with you: <span className="text-emerald-400 font-mono font-bold">{withdrawPlayer.formatted_balance}</span>.
                        </div>

                        <div className="bg-rose-500/10 border border-rose-500/20 rounded-xl p-3 text-xs text-rose-300">
                            This cash withdrawal will deduct from the player's balance under your manager account, update your walk-in/counter cash records, and log an immutable financial audit transaction.
                        </div>

                        <form onSubmit={handleWithdrawSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Withdrawal Amount ($ USD)</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="50000"
                                    step="0.01"
                                    required
                                    value={withdrawForm.data.amount}
                                    onChange={(e) => withdrawForm.setData('amount', parseFloat(e.target.value) || 0)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono text-base font-bold focus:ring-rose-500 focus:border-rose-500"
                                />
                                {withdrawForm.errors.amount && (
                                    <div className="text-rose-400 text-[11px] mt-1">{withdrawForm.errors.amount}</div>
                                )}
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Notes / Disbursal Reason</label>
                                <input
                                    type="text"
                                    value={withdrawForm.data.notes}
                                    onChange={(e) => withdrawForm.setData('notes', e.target.value)}
                                    placeholder="e.g. In-person counter cash payout"
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-rose-500 focus:border-rose-500"
                                />
                            </div>

                            <div className="flex justify-end space-x-2 pt-2 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setWithdrawPlayer(null)}
                                    className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={withdrawForm.processing}
                                    className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 transition shadow"
                                >
                                    {withdrawForm.processing ? 'Processing...' : 'Disburse Cash'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Authorize Existing Player Modal */}
            {authorizeModalOpen && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-neutral-800 pb-3">
                            <h3 className="font-bold text-white text-base flex items-center gap-2">
                                <span>➕</span>
                                <span>Authorize Existing Company Player</span>
                            </h3>
                            <button
                                onClick={() => setAuthorizeModalOpen(false)}
                                className="text-neutral-400 hover:text-white text-xs font-bold"
                            >
                                ✕
                            </button>
                        </div>

                        <p className="text-xs text-neutral-400">
                            Add a player already registered in this company to your roster so they can view and join your games.
                        </p>

                        {available_players.length === 0 ? (
                            <div className="p-4 bg-neutral-950 border border-neutral-800 rounded-xl text-center text-xs text-neutral-400">
                                All registered company players are already on your game roster.
                            </div>
                        ) : (
                            <form onSubmit={handleAuthorizeSubmit} className="space-y-4 text-xs">
                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Select Player</label>
                                    <select
                                        required
                                        value={authorizeForm.data.player_id}
                                        onChange={(e) => authorizeForm.setData('player_id', e.target.value)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option value="">-- Choose a player --</option>
                                        {available_players.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} ({p.email})
                                            </option>
                                        ))}
                                    </select>
                                    {authorizeForm.errors.player_id && (
                                        <div className="text-rose-400 text-[11px] mt-1">{authorizeForm.errors.player_id}</div>
                                    )}
                                </div>

                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Initial Cash Deposit ($ USD, optional)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="50000"
                                        step="0.01"
                                        value={authorizeForm.data.initial_deposit}
                                        onChange={(e) => authorizeForm.setData('initial_deposit', parseFloat(e.target.value) || 0)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="0.00"
                                    />
                                    <p className="text-[11px] text-neutral-500 mt-1">
                                        Funds deposited here are credited directly to the player's wallet under your management.
                                    </p>
                                </div>

                                <div className="flex justify-end space-x-2 pt-2 border-t border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() => setAuthorizeModalOpen(false)}
                                        className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={authorizeForm.processing || !authorizeForm.data.player_id}
                                        className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 transition shadow disabled:opacity-50"
                                    >
                                        {authorizeForm.processing ? 'Authorizing...' : 'Authorize Player'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </CompanyAdminLayout>
    );
}
