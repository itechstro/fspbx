<template>
    <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ title }}</h2>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500">{{ subtitle }}</p>
            </div>
            <div v-if="showPeriod" class="min-w-40">
                <label class="block text-xs font-medium text-gray-500">Period</label>
                <input
                    v-model="localPeriod"
                    type="month"
                    class="mt-1 block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600"
                    @change="emitPeriodChange"
                />
            </div>
        </div>

        <div v-if="aiCosts" class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
            <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Est. AI spend</p>
                <p class="text-lg font-semibold text-gray-900">{{ formatUsd(aiCosts.total_cost_usd) }}</p>
            </div>
            <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Transcription</p>
                <p class="text-lg font-semibold text-gray-900">{{ formatUsd(aiCosts.transcription_cost_usd) }}</p>
            </div>
            <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Call summaries</p>
                <p class="text-lg font-semibold text-gray-900">{{ formatUsd(aiCosts.summary_cost_usd) }}</p>
            </div>
            <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Translations</p>
                <p class="text-lg font-semibold text-gray-900">{{ formatUsd(aiCosts.translation_cost_usd) }}</p>
            </div>
            <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Executive summaries</p>
                <p class="text-lg font-semibold text-gray-900">{{ formatUsd(aiCosts.executive_summary_cost_usd) }}</p>
                <p class="mt-0.5 text-xs text-gray-500">{{ aiCosts.executive_summary_count ?? 0 }} run(s)</p>
            </div>
            <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Transcribed calls</p>
                <p class="text-lg font-semibold text-gray-900">{{ aiCosts.transcription_count ?? 0 }}</p>
            </div>
        </div>

        <div v-if="limitGroups.length" class="mt-5 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2">Limit</th>
                        <th class="px-3 py-2">Usage</th>
                        <th class="px-3 py-2">Limit</th>
                        <th class="px-3 py-2">Remaining</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-for="group in limitGroups" :key="group.name">
                        <tr class="bg-gray-50">
                            <td colspan="5" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                {{ group.name }}
                            </td>
                        </tr>
                        <tr v-for="row in group.rows" :key="row.key">
                            <td class="px-3 py-3">
                                <p class="font-medium text-gray-900">{{ row.label }}</p>
                                <p v-if="row.description" class="text-xs text-gray-500">{{ row.description }}</p>
                            </td>
                            <td class="px-3 py-3 text-gray-700">{{ formatUsage(row) }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ row.unlimited ? 'Unlimited' : formatLimit(row) }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ row.unlimited ? '—' : formatRemaining(row) }}</td>
                            <td class="px-3 py-3">
                                <span :class="statusClass(row)">{{ statusLabel(row) }}</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p v-else-if="!loading" class="mt-4 text-sm text-gray-500">No usage data for this period.</p>
        <p v-if="loading" class="mt-4 text-sm text-gray-500">Loading usage...</p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, default: 'Usage & Limits' },
    subtitle: { type: String, default: '' },
    limits: { type: Array, default: () => [] },
    aiCosts: { type: Object, default: null },
    period: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    showPeriod: { type: Boolean, default: true },
});

const emit = defineEmits(['period-change']);

const localPeriod = ref(props.period || new Date().toISOString().slice(0, 7));

const limitGroups = computed(() => {
    const groups = {};

    for (const row of props.limits || []) {
        const name = row.group || 'Limits';
        if (!groups[name]) {
            groups[name] = [];
        }
        groups[name].push(row);
    }

    return Object.entries(groups).map(([name, rows]) => ({ name, rows }));
});

watch(() => props.period, (value) => {
    if (value) {
        localPeriod.value = value;
    }
});

function emitPeriodChange() {
    emit('period-change', localPeriod.value);
}

function formatUsd(value) {
    const amount = Number(value || 0);
    return `$${amount.toFixed(amount >= 1 ? 2 : 4)}`;
}

function formatUsage(row) {
    const suffix = row.monthly ? ' this month' : '';
    return `${row.usage ?? 0} ${row.unit || ''}${suffix}`.trim();
}

function formatLimit(row) {
    return `${row.effective_limit ?? row.limit ?? 0} ${row.unit || ''}`.trim();
}

function formatRemaining(row) {
    return `${row.remaining ?? 0} ${row.unit || ''}`.trim();
}

function usagePercent(row) {
    if (row.unlimited) return 0;
    const limit = Number(row.effective_limit ?? row.limit ?? 0);
    if (!limit) return 0;
    return Math.min(100, (Number(row.usage || 0) / limit) * 100);
}

function statusLabel(row) {
    if (row.unlimited) return 'Unlimited';
    if (usagePercent(row) >= 100) return 'At limit';
    if (usagePercent(row) >= 80) return 'High';
    return 'OK';
}

function statusClass(row) {
    const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium';
    if (row.unlimited) return `${base} bg-gray-100 text-gray-700`;
    if (usagePercent(row) >= 100) return `${base} bg-rose-100 text-rose-700`;
    if (usagePercent(row) >= 80) return `${base} bg-amber-100 text-amber-800`;
    return `${base} bg-green-100 text-green-700`;
}
</script>
