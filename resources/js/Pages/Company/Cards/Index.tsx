import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface CardItem {
    id: number;
    card_number: number;
    status: string;
    card_hash: string;
    created_at: string;
    current_version?: {
        version_number: number;
    };
}

interface Props extends PageProps {
    cards: {
        data: CardItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        status: string | null;
        search: string | null;
    };
    stats: {
        total: number;
        available: number;
        assigned: number;
        in_use: number;
        retired: number;
        disabled: number;
    };
}

export default function CardIndex({ cards, filters, stats, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const [search, setSearch] = useState(filters.search || '');
    const [showBatchModal, setShowBatchModal] = useState(false);

    const { data: batchData, setData: setBatchData, post: postBatch, processing: batchProcessing } = useForm({
        count: 50,
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/c/${companySlug}/admin/cards`, {
            search: search || undefined,
            status: filters.status || undefined,
        }, { preserveState: true });
    };

    const handleStatusFilter = (status: string | null) => {
        router.get(`/c/${companySlug}/admin/cards`, {
            search: filters.search || undefined,
            status: status || undefined,
        }, { preserveState: true });
    };

    const submitBatch: FormEventHandler = (e) => {
        e.preventDefault();
        postBatch(`/c/${companySlug}/admin/cards/generate-batch`, {
            onSuccess: () => setShowBatchModal(false),
        });
    };

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Fixed Card Inventory</h1>
                        <p className="text-xs text-neutral-400 mt-0.5">
                            Permanent 75-ball card assets assigned to players for live games
                        </p>
                    </div>

                    <button
                        onClick={() => setShowBatchModal(true)}
                        className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition"
                    >
                        + Generate Card Batch
                    </button>
                </div>
            }
        >
            <Head title="Card Inventory" />

            {/* Inventory Status Counter Bar */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                <button
                    onClick={() => handleStatusFilter(null)}
                    className={`p-3 rounded-xl border text-left transition ${
                        !filters.status ? 'bg-neutral-800 border-indigo-500/50' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700'
                    }`}
                >
                    <div className="text-[10px] text-neutral-400 uppercase font-semibold">Total Inventory</div>
                    <div className="text-xl font-extrabold text-white mt-1">{stats.total}</div>
                </button>

                <button
                    onClick={() => handleStatusFilter('available')}
                    className={`p-3 rounded-xl border text-left transition ${
                        filters.status === 'available' ? 'bg-neutral-800 border-emerald-500/50' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700'
                    }`}
                >
                    <div className="text-[10px] text-emerald-400 uppercase font-semibold">Available</div>
                    <div className="text-xl font-extrabold text-emerald-400 mt-1">{stats.available}</div>
                </button>

                <button
                    onClick={() => handleStatusFilter('assigned')}
                    className={`p-3 rounded-xl border text-left transition ${
                        filters.status === 'assigned' ? 'bg-neutral-800 border-indigo-500/50' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700'
                    }`}
                >
                    <div className="text-[10px] text-indigo-400 uppercase font-semibold">Assigned</div>
                    <div className="text-xl font-extrabold text-indigo-400 mt-1">{stats.assigned}</div>
                </button>

                <button
                    onClick={() => handleStatusFilter('in_use')}
                    className={`p-3 rounded-xl border text-left transition ${
                        filters.status === 'in_use' ? 'bg-neutral-800 border-amber-500/50' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700'
                    }`}
                >
                    <div className="text-[10px] text-amber-400 uppercase font-semibold">In Live Game</div>
                    <div className="text-xl font-extrabold text-amber-400 mt-1">{stats.in_use}</div>
                </button>

                <button
                    onClick={() => handleStatusFilter('retired')}
                    className={`p-3 rounded-xl border text-left transition ${
                        filters.status === 'retired' ? 'bg-neutral-800 border-neutral-500/50' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700'
                    }`}
                >
                    <div className="text-[10px] text-neutral-400 uppercase font-semibold">Retired</div>
                    <div className="text-xl font-extrabold text-neutral-400 mt-1">{stats.retired}</div>
                </button>

                <button
                    onClick={() => handleStatusFilter('disabled')}
                    className={`p-3 rounded-xl border text-left transition ${
                        filters.status === 'disabled' ? 'bg-neutral-800 border-rose-500/50' : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700'
                    }`}
                >
                    <div className="text-[10px] text-rose-400 uppercase font-semibold">Disabled</div>
                    <div className="text-xl font-extrabold text-rose-400 mt-1">{stats.disabled}</div>
                </button>
            </div>

            {/* Filter Bar */}
            <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3 items-center justify-between">
                <form onSubmit={handleSearch} className="flex gap-2 w-full sm:w-auto">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search Card # (e.g. 247)"
                        className="bg-neutral-950 border border-neutral-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-indigo-500"
                    />
                    <button
                        type="submit"
                        className="bg-neutral-800 hover:bg-neutral-700 text-white text-xs px-3 py-1.5 rounded-lg transition"
                    >
                        Search
                    </button>
                    {filters.search && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                router.get(`/c/${companySlug}/admin/cards`, { status: filters.status || undefined });
                            }}
                            className="text-xs text-neutral-400 hover:text-white px-2 py-1.5"
                        >
                            Clear
                        </button>
                    )}
                </form>

                <div className="text-xs text-neutral-400">
                    Showing <span className="font-bold text-white">{cards.data.length}</span> of <span className="font-bold text-white">{cards.total}</span> cards
                </div>
            </div>

            {/* Cards Table */}
            <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                <table className="min-w-full divide-y divide-neutral-800 text-sm">
                    <thead className="bg-neutral-950/60 text-neutral-400 text-xs uppercase tracking-wider text-left">
                        <tr>
                            <th className="px-6 py-3 font-semibold">Card Identifier</th>
                            <th className="px-6 py-3 font-semibold">Version</th>
                            <th className="px-6 py-3 font-semibold">Fingerprint Hash</th>
                            <th className="px-6 py-3 font-semibold">Inventory Status</th>
                            <th className="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-800/80 text-neutral-200">
                        {cards.data.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-6 py-12 text-center text-neutral-500 text-xs">
                                    No cards found in inventory. Generate your first batch using the button above.
                                </td>
                            </tr>
                        ) : (
                            cards.data.map((card) => (
                                <tr key={card.id} className="hover:bg-neutral-800/40 transition">
                                    <td className="px-6 py-4 font-mono font-bold text-white">
                                        #{String(card.card_number).padStart(6, '0')}
                                    </td>
                                    <td className="px-6 py-4 text-xs font-semibold text-indigo-400">
                                        v{card.current_version?.version_number ?? 1}
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-neutral-400">
                                        {card.card_hash.substring(0, 16)}...
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${
                                            card.status === 'available'
                                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                : card.status === 'in_use'
                                                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                                                : card.status === 'assigned'
                                                ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                                                : 'bg-neutral-500/10 text-neutral-400 border border-neutral-500/20'
                                        }`}>
                                            {card.status.replace('_', ' ')}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <Link
                                            href={`/c/${companySlug}/admin/cards/${card.id}`}
                                            className="text-xs text-indigo-400 hover:text-indigo-300 font-semibold hover:underline"
                                        >
                                            View Card &rarr;
                                        </Link>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {cards.last_page > 1 && (
                <div className="mt-6 flex justify-center space-x-1">
                    {cards.links.map((link, idx) => (
                        <Link
                            key={idx}
                            href={link.url || '#'}
                            className={`px-3 py-1.5 rounded text-xs font-medium transition ${
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : link.url
                                    ? 'bg-neutral-900 text-neutral-300 hover:bg-neutral-800'
                                    : 'text-neutral-600 cursor-not-allowed'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            {/* Generate Batch Modal */}
            {showBatchModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-bold text-white">Generate Card Inventory Batch</h2>
                            <button onClick={() => setShowBatchModal(false)} className="text-neutral-400 hover:text-white">
                                &times;
                            </button>
                        </div>

                        <p className="text-xs text-neutral-400 mb-4 leading-relaxed">
                            Generate persistent 75-ball bingo cards. Each card is uniquely verified and hashed to guarantee no duplicates within your company inventory.
                        </p>

                        <form onSubmit={submitBatch} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-neutral-300 uppercase mb-1">
                                    Number of Cards to Generate
                                </label>
                                <select
                                    value={batchData.count}
                                    onChange={(e) => setBatchData('count', parseInt(e.target.value, 10))}
                                    className="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value={10}>10 Cards</option>
                                    <option value={25}>25 Cards</option>
                                    <option value={50}>50 Cards</option>
                                    <option value={100}>100 Cards</option>
                                    <option value={250}>250 Cards</option>
                                </select>
                            </div>

                            <div className="pt-4 flex justify-end space-x-3 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setShowBatchModal(false)}
                                    className="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={batchProcessing}
                                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm"
                                >
                                    {batchProcessing ? 'Generating...' : 'Generate Batch'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </CompanyAdminLayout>
    );
}
