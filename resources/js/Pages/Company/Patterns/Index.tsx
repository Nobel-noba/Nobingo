import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface PatternItem {
    id: number;
    company_id: number | null;
    name: string;
    slug: string;
    description: string | null;
    type: string;
    coordinates: Array<[number, number]>;
    is_active: boolean;
}

interface Props extends PageProps {
    patterns: PatternItem[];
}

export default function PatternIndex({ patterns, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const [showCreateModal, setShowCreateModal] = useState(false);

    // Grid state for custom pattern creation (5x5 boolean)
    const [selectedCells, setSelectedCells] = useState<boolean[][]>(
        Array(5).fill(null).map(() => Array(5).fill(false))
    );

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        slug: '',
        description: '',
        type: 'special',
        coordinates: [] as Array<[number, number]>,
    });

    const toggleCell = (r: number, c: number) => {
        const next = selectedCells.map((row, rIdx) =>
            row.map((cell, cIdx) => (rIdx === r && cIdx === c ? !cell : cell))
        );
        setSelectedCells(next);

        // Convert to coordinates array
        const coords: Array<[number, number]> = [];
        for (let row = 0; row < 5; row++) {
            for (let col = 0; col < 5; col++) {
                if (next[row][col]) {
                    coords.push([row, col]);
                }
            }
        }
        setData('coordinates', coords);
    };

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/c/${companySlug}/admin/patterns`, {
            onSuccess: () => {
                reset();
                setSelectedCells(Array(5).fill(null).map(() => Array(5).fill(false)));
                setShowCreateModal(false);
            },
        });
    };

    const togglePatternStatus = (patternId: number) => {
        router.patch(`/c/${companySlug}/admin/patterns/${patternId}/toggle`);
    };

    const renderMiniGrid = (coordinates: Array<[number, number]>) => {
        const activeLookup = new Set(coordinates.map(([r, c]) => `${r},${c}`));

        return (
            <div className="grid grid-cols-5 gap-1 w-28 h-28 bg-neutral-950 p-1.5 rounded-lg border border-neutral-800">
                {Array.from({ length: 5 }).map((_, r) =>
                    Array.from({ length: 5 }).map((_, c) => {
                        const isSet = activeLookup.has(`${r},${c}`);
                        const isCenter = r === 2 && c === 2;
                        return (
                            <div
                                key={`${r}-${c}`}
                                className={`rounded-sm transition-all ${
                                    isSet
                                        ? isCenter
                                            ? 'bg-amber-400 shadow-sm shadow-amber-400/50'
                                            : 'bg-indigo-500 shadow-sm shadow-indigo-500/30'
                                        : isCenter
                                        ? 'bg-amber-500/20 border border-amber-500/30'
                                        : 'bg-neutral-900'
                                }`}
                            />
                        );
                    })
                )}
            </div>
        );
    };

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Winning Pattern Engine</h1>
                        <p className="text-xs text-neutral-400 mt-0.5">
                            Data-driven 5x5 coordinate patterns used to evaluate game winners
                        </p>
                    </div>

                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition"
                    >
                        + Create Custom Pattern
                    </button>
                </div>
            }
        >
            <Head title="Winning Patterns" />

            {/* Pattern Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                {patterns.map((pattern) => (
                    <div
                        key={pattern.id}
                        className={`bg-neutral-900 border rounded-2xl p-5 flex flex-col justify-between transition ${
                            pattern.is_active ? 'border-neutral-800' : 'border-neutral-800/50 opacity-60'
                        }`}
                    >
                        <div className="flex gap-4">
                            {/* Visual Pattern Thumbnail */}
                            {renderMiniGrid(pattern.coordinates)}

                            <div className="flex-1">
                                <div className="flex items-center space-x-2">
                                    <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        {pattern.type}
                                    </span>
                                    {pattern.company_id && (
                                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 font-medium">
                                            Custom
                                        </span>
                                    )}
                                </div>

                                <h3 className="text-base font-bold text-white mt-1.5">{pattern.name}</h3>
                                <p className="text-xs text-neutral-400 mt-1 line-clamp-2 leading-relaxed">
                                    {pattern.description || 'Configured 5x5 winning pattern'}
                                </p>
                                <div className="text-[11px] text-neutral-500 font-mono mt-1">
                                    {pattern.coordinates.length} squares required
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 pt-3 border-t border-neutral-800 flex justify-between items-center text-xs">
                            <span className="text-neutral-500 font-mono text-[10px]">
                                slug: {pattern.slug}
                            </span>
                            <button
                                onClick={() => togglePatternStatus(pattern.id)}
                                className={`text-xs font-semibold px-2.5 py-1 rounded transition ${
                                    pattern.is_active
                                        ? 'bg-neutral-800 text-neutral-300 hover:bg-neutral-700'
                                        : 'bg-emerald-950 text-emerald-300 hover:bg-emerald-900'
                                }`}
                            >
                                {pattern.is_active ? 'Disable' : 'Enable'}
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            {/* Create Custom Pattern Modal with Interactive 5x5 Editor */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <div>
                                <h2 className="text-lg font-bold text-white">Create Custom Winning Pattern</h2>
                                <p className="text-xs text-neutral-400">Click cells on the 5x5 board to toggle required squares</p>
                            </div>
                            <button onClick={() => setShowCreateModal(false)} className="text-neutral-400 hover:text-white">
                                &times;
                            </button>
                        </div>

                        <form onSubmit={submitCreate} className="space-y-4">
                            <div className="flex flex-col sm:flex-row gap-6 items-center">
                                {/* Interactive 5x5 Grid */}
                                <div className="flex flex-col items-center">
                                    <div className="grid grid-cols-5 gap-1.5 w-48 h-48 bg-neutral-950 p-2 rounded-2xl border border-neutral-800 shadow-inner">
                                        {selectedCells.map((row, r) =>
                                            row.map((isSelected, c) => {
                                                const isCenter = r === 2 && c === 2;
                                                return (
                                                    <button
                                                        key={`${r}-${c}`}
                                                        type="button"
                                                        onClick={() => toggleCell(r, c)}
                                                        className={`rounded-lg font-bold text-xs transition flex items-center justify-center ${
                                                            isSelected
                                                                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/30'
                                                                : isCenter
                                                                ? 'bg-amber-500/20 text-amber-400 border border-amber-500/40 hover:bg-amber-500/30'
                                                                : 'bg-neutral-900 text-neutral-500 hover:bg-neutral-800'
                                                        }`}
                                                    >
                                                        {isCenter ? 'FREE' : ''}
                                                    </button>
                                                );
                                            })
                                        )}
                                    </div>
                                    <span className="text-[10px] text-neutral-500 mt-2 font-mono">
                                        {data.coordinates.length} squares selected
                                    </span>
                                </div>

                                {/* Form Fields */}
                                <div className="flex-1 space-y-3 w-full">
                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-300 uppercase mb-1">
                                            Pattern Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setData({
                                                    ...data,
                                                    name: val,
                                                    slug: val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, ''),
                                                });
                                            }}
                                            placeholder="e.g. Plus Sign (+)"
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
                                            required
                                        />
                                        {errors.name && <div className="text-xs text-rose-400 mt-1">{errors.name}</div>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-300 uppercase mb-1">
                                            Pattern Slug
                                        </label>
                                        <input
                                            type="text"
                                            value={data.slug}
                                            onChange={(e) => setData('slug', e.target.value)}
                                            placeholder="plus_sign"
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"
                                            required
                                        />
                                        {errors.slug && <div className="text-xs text-rose-400 mt-1">{errors.slug}</div>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-300 uppercase mb-1">
                                            Pattern Type
                                        </label>
                                        <select
                                            value={data.type}
                                            onChange={(e) => setData('type', e.target.value)}
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
                                        >
                                            <option value="special">Special Custom</option>
                                            <option value="line">Line</option>
                                            <option value="column">Column</option>
                                            <option value="diagonal">Diagonal</option>
                                            <option value="full_card">Coverall</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-300 uppercase mb-1">
                                            Description
                                        </label>
                                        <input
                                            type="text"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="e.g. Center row and center column"
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
                                        />
                                    </div>
                                </div>
                            </div>

                            {errors.coordinates && (
                                <div className="text-xs text-rose-400">{errors.coordinates}</div>
                            )}

                            <div className="pt-4 flex justify-end space-x-3 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing || data.coordinates.length === 0}
                                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-semibold rounded-lg shadow-sm"
                                >
                                    {processing ? 'Saving...' : 'Save Custom Pattern'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </CompanyAdminLayout>
    );
}
