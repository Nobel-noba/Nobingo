import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface VersionItem {
    id: number;
    version_number: number;
    card_hash: string;
    grid: number[][];
    created_at: string;
    notes: string | null;
    creator?: {
        name: string;
    };
}

interface CardDetail {
    id: number;
    card_number: number;
    status: string;
    card_hash: string;
    created_at: string;
    current_version: VersionItem;
    versions: VersionItem[];
}

interface Props extends PageProps {
    card: CardDetail;
    formatted_number: string;
}

export default function CardShow({ card, formatted_number, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const grid = card.current_version?.grid || [];

    const handleStatusChange = (newStatus: string) => {
        if (confirm(`Are you sure you want to change card status to ${newStatus}?`)) {
            router.patch(`/c/${companySlug}/admin/cards/${card.id}/status`, {
                status: newStatus,
            });
        }
    };

    const columnHeaders = ['B', 'I', 'N', 'G', 'O'];
    const columnColors = [
        'bg-rose-500/20 text-rose-400 border-rose-500/30',
        'bg-amber-500/20 text-amber-400 border-amber-500/30',
        'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
        'bg-sky-500/20 text-sky-400 border-sky-500/30',
        'bg-purple-500/20 text-purple-400 border-purple-500/30',
    ];

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={`/c/${companySlug}/admin/cards`}
                            className="text-xs text-neutral-400 hover:text-white"
                        >
                            &larr; Back to Inventory
                        </Link>
                        <span className="text-neutral-600">/</span>
                        <h1 className="text-xl font-bold text-white tracking-tight">
                            Fixed Card {formatted_number}
                        </h1>
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${
                            card.status === 'available'
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                : card.status === 'in_use'
                                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                                : 'bg-neutral-500/10 text-neutral-400 border border-neutral-500/20'
                        }`}>
                            {card.status.replace('_', ' ')}
                        </span>
                    </div>

                    <div className="flex items-center space-x-2">
                        {card.status !== 'available' && (
                            <button
                                onClick={() => handleStatusChange('available')}
                                className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                            >
                                Mark Available
                            </button>
                        )}
                        {card.status !== 'retired' && (
                            <button
                                onClick={() => handleStatusChange('retired')}
                                className="bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                            >
                                Retire Card
                            </button>
                        )}
                        {card.status !== 'disabled' && (
                            <button
                                onClick={() => handleStatusChange('disabled')}
                                className="bg-rose-900/60 hover:bg-rose-800 text-rose-300 text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                            >
                                Disable Card
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Card ${formatted_number}`} />

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                {/* Visual Bingo Card Representation */}
                <div className="lg:col-span-6 flex flex-col items-center">
                    <div className="w-full max-w-md bg-neutral-900 border-2 border-neutral-800 rounded-3xl p-6 shadow-2xl">
                        <div className="text-center mb-4">
                            <span className="text-xs uppercase font-extrabold tracking-widest text-indigo-400">
                                75-Ball Fixed Layout
                            </span>
                            <h2 className="text-2xl font-black text-white">{formatted_number}</h2>
                        </div>

                        {/* 5x5 Grid Display */}
                        <div className="grid grid-cols-5 gap-2.5">
                            {columnHeaders.map((col, idx) => (
                                <div
                                    key={col}
                                    className={`h-11 rounded-xl flex items-center justify-center font-black text-lg border shadow-sm ${columnColors[idx]}`}
                                >
                                    {col}
                                </div>
                            ))}

                            {grid.map((row, rIdx) =>
                                row.map((cell, cIdx) => {
                                    const isCenter = rIdx === 2 && cIdx === 2;
                                    return (
                                        <div
                                            key={`${rIdx}-${cIdx}`}
                                            className={`aspect-square rounded-xl flex items-center justify-center font-bold text-base border transition ${
                                                isCenter
                                                    ? 'bg-gradient-to-tr from-amber-500 to-rose-500 text-white border-amber-400 shadow-md shadow-rose-500/20 font-black text-xs'
                                                    : 'bg-neutral-950/80 border-neutral-800 text-white hover:border-neutral-700'
                                            }`}
                                        >
                                            {isCenter ? 'FREE' : cell}
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        <div className="mt-5 text-center text-[10px] text-neutral-500 font-mono">
                            Deterministic Hash: {card.card_hash}
                        </div>
                    </div>
                </div>

                {/* Card Details & Version History */}
                <div className="lg:col-span-6 space-y-6">
                    {/* Invariant Note */}
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <h3 className="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-2">
                            Immutability Invariant
                        </h3>
                        <p className="text-xs text-neutral-300 leading-relaxed">
                            Card #{String(card.card_number).padStart(6, '0')} has permanent numbers. When this card is played in
                            multiple games (e.g. Game 101, Game 204), it retains the exact same numbers. Any administrative update
                            creates a new version and never alters historical game records.
                        </p>
                    </div>

                    {/* Version History Table */}
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <h3 className="text-sm font-bold text-white mb-4">Version History ({card.versions.length})</h3>

                        <div className="space-y-3">
                            {card.versions.map((ver) => (
                                <div
                                    key={ver.id}
                                    className="p-3.5 rounded-xl bg-neutral-950/60 border border-neutral-800 flex items-center justify-between"
                                >
                                    <div>
                                        <div className="flex items-center space-x-2">
                                            <span className="text-xs font-bold text-indigo-400">Version {ver.version_number}</span>
                                            {ver.id === card.current_version?.id && (
                                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
                                                    Current
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-[11px] text-neutral-400 mt-1">
                                            {ver.notes || 'No change notes recorded'}
                                        </div>
                                        <div className="text-[10px] font-mono text-neutral-500 mt-0.5">
                                            Hash: {ver.card_hash.substring(0, 24)}...
                                        </div>
                                    </div>

                                    <div className="text-right text-[10px] text-neutral-500">
                                        {new Date(ver.created_at).toLocaleDateString()}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </CompanyAdminLayout>
    );
}
