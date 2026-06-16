<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-indigo-600">Per-call AI costs</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ domain.domain_description || domain.domain_name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Estimated transcription, summary, translation, and executive-summary cost for AI usage.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="licenseUrl" class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    License &amp; usage
                </a>
                <a :href="routes.domains" class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Domains
                </a>
            </div>
        </header>

        <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Period summary</h2>
                    <p class="mt-1 text-sm text-gray-500">Costs are estimates from configured provider rates, not invoices.</p>
                </div>
                <div class="min-w-40">
                    <label class="block text-xs font-medium text-gray-500">Period</label>
                    <input
                        v-model="selectedPeriod"
                        type="month"
                        class="mt-1 block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600"
                        @change="loadData(1)"
                    />
                </div>
            </div>

            <div v-if="summary" class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
                <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                    <p class="text-xs text-gray-500">Total est. spend</p>
                    <p class="text-lg font-semibold text-gray-900">{{ formatUsd(summary.total_cost_usd) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                    <p class="text-xs text-gray-500">Transcription</p>
                    <p class="text-lg font-semibold text-gray-900">{{ formatUsd(summary.transcription_cost_usd) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                    <p class="text-xs text-gray-500">Call summaries</p>
                    <p class="text-lg font-semibold text-gray-900">{{ formatUsd(summary.summary_cost_usd) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                    <p class="text-xs text-gray-500">Translations</p>
                    <p class="text-lg font-semibold text-gray-900">{{ formatUsd(summary.translation_cost_usd) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                    <p class="text-xs text-gray-500">Executive summaries</p>
                    <p class="text-lg font-semibold text-gray-900">{{ formatUsd(summary.executive_summary_cost_usd) }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ summary.executive_summary_count ?? 0 }} run(s)</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                    <p class="text-xs text-gray-500">Transcribed calls</p>
                    <p class="text-lg font-semibold text-gray-900">{{ summary.transcription_count ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">AI executive summaries</h2>
                    <p class="mt-1 text-sm text-gray-500">Leadership briefs generated from Recorder Analytics, Call History Analytics, or scheduled reports.</p>
                </div>
                <p v-if="executiveSummaries.length" class="text-sm text-gray-500">{{ executiveSummaries.length }} run(s)</p>
            </div>

            <div v-if="loading && !executiveSummaries.length" class="py-6 text-center text-sm text-gray-500">Loading...</div>
            <div v-else-if="!executiveSummaries.length" class="py-6 text-center text-sm text-gray-500">
                No executive summary runs for this period.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Date</th>
                            <th class="px-2 py-2">Source</th>
                            <th class="px-2 py-2">Model</th>
                            <th class="px-2 py-2">Tokens</th>
                            <th class="px-2 py-2">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in executiveSummaries" :key="row.uuid" class="border-b border-gray-100 align-top">
                            <td class="px-2 py-3 whitespace-nowrap">
                                <div>{{ row.date || '—' }}</div>
                                <div class="text-xs text-gray-500">{{ row.time || '' }}</div>
                            </td>
                            <td class="px-2 py-3">{{ row.source_label || '—' }}</td>
                            <td class="px-2 py-3 text-xs text-gray-700">{{ row.model || '—' }}</td>
                            <td class="px-2 py-3 whitespace-nowrap text-xs text-gray-500">
                                <span v-if="row.total_tokens">{{ row.total_tokens }} total</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-2 py-3 whitespace-nowrap font-medium text-gray-900">{{ formatUsd(row.estimated_cost_usd) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900">Calls</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <p v-if="rows?.total" class="text-sm text-gray-500">{{ rows.total }} call(s)</p>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="loading || exporting || (!rows?.total && !executiveSummaries.length)"
                        @click="exportCsv"
                    >
                        Export CSV
                    </button>
                </div>
            </div>

            <div v-if="loading" class="py-10 text-center text-sm text-gray-500">Loading...</div>
            <div v-else-if="!rows?.data?.length" class="py-10 text-center text-sm text-gray-500">
                No completed AI calls with cost data for this period.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Date</th>
                            <th class="px-2 py-2">Caller</th>
                            <th class="px-2 py-2">Destination</th>
                            <th class="px-2 py-2">Duration</th>
                            <th class="px-2 py-2">Transcription</th>
                            <th class="px-2 py-2">Summary</th>
                            <th class="px-2 py-2">Translation</th>
                            <th class="px-2 py-2">Total</th>
                            <th class="px-2 py-2">Models</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows.data" :key="row.uuid" class="border-b border-gray-100 align-top">
                            <td class="px-2 py-3 whitespace-nowrap">
                                <div>{{ row.date || '—' }}</div>
                                <div class="text-xs text-gray-500">{{ row.time || '' }}</div>
                            </td>
                            <td class="px-2 py-3">{{ row.caller || '—' }}</td>
                            <td class="px-2 py-3">{{ row.destination || '—' }}</td>
                            <td class="px-2 py-3 whitespace-nowrap">{{ row.duration || '—' }}</td>
                            <td class="px-2 py-3 whitespace-nowrap">{{ formatUsd(row.transcription_cost_usd) }}</td>
                            <td class="px-2 py-3 whitespace-nowrap">{{ formatUsd(row.summary_cost_usd) }}</td>
                            <td class="px-2 py-3 whitespace-nowrap">{{ formatUsd(row.translation_cost_usd) }}</td>
                            <td class="px-2 py-3 whitespace-nowrap font-medium text-gray-900">{{ formatUsd(row.total_ai_cost_usd) }}</td>
                            <td class="px-2 py-3 text-xs text-gray-500">
                                <div v-if="row.speech_model">T: {{ row.speech_model }}</div>
                                <div v-if="row.summary_model">S: {{ row.summary_model }}</div>
                                <div v-if="row.translation_model">X: {{ row.translation_model }}</div>
                                <span v-if="!row.speech_model && !row.summary_model && !row.translation_model">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="rows?.last_page > 1" class="mt-4 flex items-center justify-between gap-3 text-sm text-gray-600">
                <p>Page {{ rows.current_page }} of {{ rows.last_page }}</p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="rows.current_page <= 1 || loading"
                        @click="loadData(rows.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="rows.current_page >= rows.last_page || loading"
                        @click="loadData(rows.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="notificationShow = false" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import MainLayout from '../Layouts/MainLayout.vue';
import Notification from './components/notifications/Notification.vue';

const props = defineProps({
    domain: { type: Object, required: true },
    routes: { type: Object, required: true },
});

const loading = ref(false);
const exporting = ref(false);
const selectedPeriod = ref(new URLSearchParams(window.location.search).get('period') || new Date().toISOString().slice(0, 7));
const summary = ref(null);
const rows = ref(null);
const executiveSummaries = ref([]);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref('');

const licenseUrl = computed(() => {
    const url = new URL(props.routes.license, window.location.origin);
    url.searchParams.set('period', selectedPeriod.value);
    return url.pathname + url.search;
});

onMounted(() => {
    loadData(1);
});

function loadData(page = 1) {
    loading.value = true;
    axios.get(props.routes.data, {
        params: {
            period: selectedPeriod.value,
            page,
            per_page: 25,
        },
    })
        .then((response) => {
            summary.value = response.data.summary;
            rows.value = response.data.rows;
            executiveSummaries.value = response.data.executive_summaries ?? [];
            if (response.data.period) {
                selectedPeriod.value = response.data.period;
            }
        })
        .catch((error) => {
            if (error?.response?.data?.errors) {
                showNotification('error', error.response.data.errors);
                return;
            }
            showNotification('error', { error: ['Failed to load per-call usage details.'] });
        })
        .finally(() => {
            loading.value = false;
        });
}

function exportCsv() {
    exporting.value = true;
    axios.get(props.routes.export_csv, {
        params: {
            period: selectedPeriod.value,
        },
        responseType: 'blob',
    })
        .then((response) => {
            const fileUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }));
            const link = document.createElement('a');
            link.href = fileUrl;
            link.setAttribute('download', `ai-usage-details-${selectedPeriod.value}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        })
        .catch(() => {
            showNotification('error', { error: ['Failed to export AI usage details.'] });
        })
        .finally(() => {
            exporting.value = false;
        });
}

function formatUsd(value) {
    const amount = Number(value || 0);
    if (amount <= 0) {
        return '—';
    }
    return `$${amount.toFixed(amount >= 1 ? 4 : 6)}`;
}

function showNotification(type, messages = null) {
    notificationType.value = type;
    notificationMessages.value = messages ?? {};
    notificationShow.value = true;
}
</script>
