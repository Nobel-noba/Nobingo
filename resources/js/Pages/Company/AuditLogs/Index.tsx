import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface AuditLogItem {
    id: number;
    action: string;
    description: string | null;
    auditable_type: string;
    auditable_id: number | null;
    details: Record<string, any> | null;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    created_at: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    logs: {
        data: AuditLogItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        action: string;
        search: string;
        manager_id?: string;
    };
    game_managers?: Array<{ id: number; name: string; email: string }>;
    is_game_manager_view?: boolean;
}

export default function AuditLogsIndex({ auth, company, logs, filters, game_managers = [], is_game_manager_view = false }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedAction, setSelectedAction] = useState(filters.action || 'ALL');
    const [selectedManager, setSelectedManager] = useState(filters.manager_id || '');
    const [expandedLogId, setExpandedLogId] = useState<number | null>(null);

    const handleFilter = (actionVal: string, searchVal: string, managerVal: string = selectedManager) => {
        router.get(
            `/c/${company.slug}/admin/audit-logs`,
            {
                action: actionVal !== 'ALL' ? actionVal : undefined,
                search: searchVal || undefined,
                manager_id: managerVal || undefined,
            },
            { preserveState: true }
        );
    };

    const getActionBadgeColor = (action: string) => {
        if (action.includes('WINNER') || action.includes('PRIZE')) {
            return 'bg-amber-500/10 text-amber-300 border-amber-500/20';
        }
        if (action.includes('BALANCE') || action.includes('FEE')) {
            return 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20';
        }
        if (action.includes('SUSPENDED')) {
            return 'bg-rose-500/10 text-rose-300 border-rose-500/20';
        }
        if (action.includes('BALL') || action.includes('GAME')) {
            return 'bg-blue-500/10 text-blue-300 border-blue-500/20';
        }
        return 'bg-neutral-800 text-neutral-300 border-neutral-700';
    };

    return (
        <CompanyAdminLayout>
            <Head title={`Audit Trail - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-white tracking-tight">System & Operator Audit Trail</h1>
                        <p className="text-sm text-neutral-400 mt-1">
                            Immutable, timestamped event log for regulatory compliance, security audits, and lifecycle verification.
                        </p>
                    </div>
                </div>

                {/* Filter & Search Bar */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center space-x-2">
                            <span className="text-xs font-semibold text-neutral-400 uppercase">Action:</span>
                            <select
                                value={selectedAction}
                                onChange={(e) => {
                                    setSelectedAction(e.target.value);
                                    handleFilter(e.target.value, search, selectedManager);
                                }}
                                className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2"
                            >
                                <option value="ALL">All Actions</option>
                                <option value="GAME_CREATED">Game Created</option>
                                <option value="GAME_STATUS_CHANGED">Game Status Changed</option>
                                <option value="BALL_CALLED">Ball Called</option>
                                <option value="BINGO_CLAIMED">Bingo Claimed</option>
                                <option value="WINNER_DECLARED">Winner Declared</option>
                                <option value="PRIZE_DISTRIBUTED">Prize Distributed</option>
                                <option value="BALANCE_ADJUSTED">Balance Adjusted</option>
                                <option value="PLAYER_SUSPENDED">Player Suspended</option>
                                <option value="PLAYER_ACTIVATED">Player Activated</option>
                                <option value="TEMPLATE_CREATED">Template Created</option>
                            </select>
                        </div>

                        {!is_game_manager_view && game_managers.length > 0 && (
                            <div className="flex items-center space-x-2">
                                <span className="text-xs font-semibold text-neutral-400 uppercase">Operator:</span>
                                <select
                                    value={selectedManager}
                                    onChange={(e) => {
                                        setSelectedManager(e.target.value);
                                        handleFilter(selectedAction, search, e.target.value);
                                    }}
                                    className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2"
                                >
                                    <option value="">All Operators</option>
                                    {game_managers.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center space-x-2">
                        <input
                            type="text"
                            placeholder="Search description, action, or actor..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleFilter(selectedAction, search, selectedManager);
                                }
                            }}
                            className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2 w-72 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button
                            onClick={() => handleFilter(selectedAction, search, selectedManager)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition"
                        >
                            Filter
                        </button>
                    </div>
                </div>

                {/* Audit Logs Table */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Timestamp</th>
                                    <th className="px-4 py-3">Actor / Operator</th>
                                    <th className="px-4 py-3">Action</th>
                                    <th className="px-4 py-3">Target Entity</th>
                                    <th className="px-4 py-3">Description</th>
                                    <th className="px-4 py-3 text-right">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {logs.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                            No audit log entries recorded matching criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    logs.data.map((log) => (
                                        <React.Fragment key={log.id}>
                                            <tr className="hover:bg-neutral-800/40 transition">
                                                <td className="px-4 py-3 whitespace-nowrap font-mono text-xs text-neutral-400">
                                                    {log.created_at}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {log.user ? (
                                                        <div>
                                                            <div className="font-medium text-neutral-200 text-xs">{log.user.name}</div>
                                                            <div className="text-[10px] text-neutral-500">{log.user.email}</div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-neutral-500 italic">System Engine</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <span className={`inline-flex px-2 py-0.5 rounded text-[11px] font-bold border ${getActionBadgeColor(log.action)}`}>
                                                        {log.action}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-xs text-neutral-400">
                                                    {log.auditable_type ? `${log.auditable_type} #${log.auditable_id}` : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-neutral-300 max-w-sm truncate">
                                                    {log.description ?? '-'}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-right">
                                                    {log.details && Object.keys(log.details).length > 0 && (
                                                        <button
                                                            onClick={() => setExpandedLogId(expandedLogId === log.id ? null : log.id)}
                                                            className="text-xs text-indigo-400 hover:text-indigo-300 font-semibold"
                                                        >
                                                            {expandedLogId === log.id ? 'Hide' : 'Inspect'}
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                            {expandedLogId === log.id && log.details && (
                                                <tr className="bg-neutral-950/80">
                                                    <td colSpan={6} className="px-6 py-4">
                                                        <div className="text-xs font-mono text-emerald-400 bg-neutral-900 p-3 rounded-lg border border-neutral-800 overflow-x-auto">
                                                            <pre>{JSON.stringify(log.details, null, 2)}</pre>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </React.Fragment>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {logs.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-neutral-800 flex items-center justify-between">
                            <div className="text-xs text-neutral-500">
                                Showing {logs.current_page} of {logs.last_page} ({logs.total} records)
                            </div>
                            <div className="flex space-x-1">
                                {logs.links.map((link, idx) => (
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
        </CompanyAdminLayout>
    );
}
