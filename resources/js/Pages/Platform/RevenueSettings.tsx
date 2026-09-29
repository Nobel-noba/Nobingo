import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props extends PageProps {
    settings: {
        winner_share_percentage: number;
        platform_fee_percentage: number;
    };
}

export default function PlatformRevenueSettings({ settings }: Props) {
    const form = useForm({
        winner_share_percentage: settings.winner_share_percentage.toString(),
        platform_fee_percentage: settings.platform_fee_percentage.toString(),
    });

    // Interactive Pot Simulation
    const [simPlayers, setSimPlayers] = useState<number>(10);
    const [simFee, setSimFee] = useState<number>(10); // $10 per card

    const winnerPct = parseFloat(form.data.winner_share_percentage) || 0;
    const platformPct = parseFloat(form.data.platform_fee_percentage) || 0;

    const totalPot = simPlayers * simFee;
    const winnerPayout = (totalPot * winnerPct) / 100;
    const companyGross = totalPot - winnerPayout;
    const platformFee = (companyGross * platformPct) / 100;
    const companyNet = companyGross - platformFee;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/platform/settings/revenue', {
            preserveScroll: true,
        });
    };

    return (
        <PlatformOwnerLayout
            header={
                <div>
                    <h1 className="text-xl font-bold text-white tracking-tight">Platform Pot & Revenue Sharing</h1>
                    <p className="text-xs text-slate-400 mt-0.5">
                        Configure global defaults for pot splits, winner payouts, and platform commission debited from company credits.
                    </p>
                </div>
            }
        >
            <Head title="Revenue Sharing Settings" />

            <div className="max-w-4xl space-y-6">
                {/* Form Card */}
                <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-6">
                    <div className="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div>
                            <h2 className="text-base font-bold text-white">Global Revenue Formula</h2>
                            <p className="text-xs text-slate-400 mt-0.5">
                                These percentages automatically apply when any company starts a game round.
                            </p>
                        </div>
                        <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            Automatic Debit Engine
                        </span>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Winner Share */}
                            <div className="p-5 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                                <label className="block text-xs font-bold uppercase text-slate-300 tracking-wider">
                                    Winner Share Percentage (%)
                                </label>
                                <p className="text-xs text-slate-400">
                                    Percentage of total pot paid out directly to bingo winners.
                                </p>
                                <div className="relative">
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="10"
                                        max="99"
                                        required
                                        value={form.data.winner_share_percentage}
                                        onChange={(e) => form.setData('winner_share_percentage', e.target.value)}
                                        className="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-lg font-bold text-emerald-400 font-mono focus:border-indigo-500 focus:outline-none"
                                    />
                                    <span className="absolute right-4 top-3 text-slate-400 font-bold">%</span>
                                </div>
                                {form.errors.winner_share_percentage && (
                                    <p className="text-xs text-rose-400">{form.errors.winner_share_percentage}</p>
                                )}
                                <div className="text-[11px] text-slate-500">
                                    Recommended: 70% - 80% to incentivize high player participation.
                                </div>
                            </div>

                            {/* Platform Fee Percentage */}
                            <div className="p-5 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                                <label className="block text-xs font-bold uppercase text-slate-300 tracking-wider">
                                    Platform Fee from Company Gross (%)
                                </label>
                                <p className="text-xs text-slate-400">
                                    Percentage of the company's gross cut debited from their platform credit balance upon game completion.
                                </p>
                                <div className="relative">
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        max="50"
                                        required
                                        value={form.data.platform_fee_percentage}
                                        onChange={(e) => form.setData('platform_fee_percentage', e.target.value)}
                                        className="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-lg font-bold text-rose-400 font-mono focus:border-rose-500 focus:outline-none"
                                    />
                                    <span className="absolute right-4 top-3 text-slate-400 font-bold">%</span>
                                </div>
                                {form.errors.platform_fee_percentage && (
                                    <p className="text-xs text-rose-400">{form.errors.platform_fee_percentage}</p>
                                )}
                                <div className="text-[11px] text-slate-500">
                                    Example: At 20% on a 25% gross cut, the platform fee equals 5% of the total pot.
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end pt-4 border-t border-slate-800">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs disabled:opacity-50 transition shadow-sm"
                            >
                                {form.processing ? 'Saving...' : 'Save Revenue Settings'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Real-time Math Simulator */}
                <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-4">
                    <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                        <div className="flex items-center gap-2">
                            <span className="text-lg">🧮</span>
                            <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                                Live Pot & Commission Simulator
                            </h3>
                        </div>
                        <span className="text-xs text-slate-400">Preview breakdown for any match size</span>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">
                                Number of Cards / Players in Game
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="1000"
                                value={simPlayers}
                                onChange={(e) => setSimPlayers(Math.max(1, parseInt(e.target.value) || 0))}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white font-mono focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">
                                Card Entry Fee ($ USD)
                            </label>
                            <input
                                type="number"
                                min="0.5"
                                step="0.5"
                                value={simFee}
                                onChange={(e) => setSimFee(Math.max(0.1, parseFloat(e.target.value) || 0))}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white font-mono focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    {/* Breakdown Cards */}
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-3 pt-2">
                        <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-center">
                            <div className="text-[10px] uppercase font-bold text-slate-400">Total Pot</div>
                            <div className="text-lg font-black text-white font-mono mt-1">
                                ${totalPot.toFixed(2)}
                            </div>
                            <div className="text-[10px] text-slate-500">100% collected</div>
                        </div>

                        <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-center">
                            <div className="text-[10px] uppercase font-bold text-emerald-400">Winner Payout</div>
                            <div className="text-lg font-black text-emerald-400 font-mono mt-1">
                                ${winnerPayout.toFixed(2)}
                            </div>
                            <div className="text-[10px] text-slate-500">{winnerPct}% of pot</div>
                        </div>

                        <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-center">
                            <div className="text-[10px] uppercase font-bold text-amber-400">Company Gross</div>
                            <div className="text-lg font-black text-amber-400 font-mono mt-1">
                                ${companyGross.toFixed(2)}
                            </div>
                            <div className="text-[10px] text-slate-500">{(100 - winnerPct).toFixed(1)}% remaining</div>
                        </div>

                        <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-center">
                            <div className="text-[10px] uppercase font-bold text-rose-400">Platform Fee</div>
                            <div className="text-lg font-black text-rose-400 font-mono mt-1">
                                -${platformFee.toFixed(2)}
                            </div>
                            <div className="text-[10px] text-slate-500">{platformPct}% of company gross</div>
                        </div>

                        <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-center col-span-2 md:col-span-1">
                            <div className="text-[10px] uppercase font-bold text-indigo-400">Company Net Gain</div>
                            <div className="text-lg font-black text-indigo-400 font-mono mt-1">
                                ${companyNet.toFixed(2)}
                            </div>
                            <div className="text-[10px] text-slate-500">Net house profit</div>
                        </div>
                    </div>
                </div>
            </div>
        </PlatformOwnerLayout>
    );
}
