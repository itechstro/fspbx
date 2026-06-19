<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-indigo-600">Domain license</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ domain.domain_description || domain.domain_name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Usage dashboard and tenant limits for resources, AI services, and outbound calling.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="routes.domains" class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Domains
                </a>
                <a v-if="routes.domain_settings" :href="routes.domain_settings" class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Settings
                </a>
            </div>
        </header>

        <UsageLimitsPanel
            :limits="usageData?.usage?.limits || []"
            :ai-costs="usageData?.ai_costs"
            :period="selectedPeriod"
            :loading="loading"
            @period-change="handlePeriodChange"
        />

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a
                v-if="permissions.ai_usage_rates && routes.ai_usage_rates"
                :href="routes.ai_usage_rates"
                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
            >
                Edit AI usage rates
            </a>
            <a
                v-if="permissions.usage_details && routes.usage_details"
                :href="usageDetailsUrl"
                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-indigo-700 shadow-sm ring-1 ring-inset ring-indigo-200 hover:bg-indigo-50"
            >
                Per-call AI cost details
            </a>
        </div>

        <div v-if="permissions.edit" class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Tenant limits</h2>
                    <p class="mt-1 text-sm text-gray-500">Enable a limit to enforce it for this tenant. Disabled limits are unlimited.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="saving || !limitRows.length"
                    @click="saveLimits"
                >
                    Save limits
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Limit</th>
                            <th class="px-3 py-2">Usage</th>
                            <th class="px-3 py-2">Enable</th>
                            <th class="px-3 py-2">Value</th>
                            <th class="px-3 py-2">Default</th>
                            <th class="px-3 py-2">Revert</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template v-for="group in limitGroups" :key="group.name">
                            <tr class="bg-gray-50">
                                <td colspan="6" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    {{ group.name }}
                                </td>
                            </tr>
                            <tr v-for="row in group.rows" :key="row.key">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-900">{{ row.label }}</p>
                                    <p v-if="row.description" class="text-xs text-gray-500">{{ row.description }}</p>
                                    <p v-else class="text-xs text-gray-500">{{ row.unit }}</p>
                                </td>
                                <td class="px-3 py-3 text-gray-700">
                                    <p>{{ formatUsage(row) }}</p>
                                    <p v-if="!row.unlimited" class="text-xs text-gray-500">{{ formatRemaining(row) }} remaining</p>
                                </td>
                                <td class="px-3 py-3">
                                    <input v-model="row.enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                </td>
                                <td class="px-3 py-3">
                                    <input
                                        v-model="row.value"
                                        type="number"
                                        min="0"
                                        step="any"
                                        class="block w-32 rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600"
                                        :disabled="!row.enabled"
                                    />
                                </td>
                                <td class="px-3 py-3 text-gray-500">
                                    {{ row.default_enabled ? row.default_value : 'Unlimited' }}
                                    <p v-if="row.inherited_from_default && !row.revert" class="text-xs text-amber-700">
                                        Global default applies to this tenant.
                                    </p>
                                    <p v-if="row.tenant_unlimited_override && !row.revert" class="text-xs text-green-700">
                                        Tenant override: unlimited.
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    <input v-model="row.revert" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="notificationShow = false" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import MainLayout from '../Layouts/MainLayout.vue';
import UsageLimitsPanel from './components/UsageLimitsPanel.vue';
import Notification from './components/notifications/Notification.vue';

const props = defineProps({
    domain: { type: Object, required: true },
    routes: { type: Object, required: true },
    permissions: { type: Object, default: () => ({}) },
});

const loading = ref(false);
const saving = ref(false);
const usageData = ref(null);
const selectedPeriod = ref(new URLSearchParams(window.location.search).get('period') || new Date().toISOString().slice(0, 7));
const limitRows = ref([]);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref('');

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages ?? {};
    notificationShow.value = true;
};

const usageDetailsUrl = computed(() => {
    if (!props.routes.usage_details) {
        return '#';
    }

    const url = new URL(props.routes.usage_details, window.location.origin);
    url.searchParams.set('period', selectedPeriod.value);
    return url.pathname + url.search;
});

const limitGroups = computed(() => {
    const groups = {};

    for (const row of limitRows.value) {
        const name = row.group || 'Limits';
        if (!groups[name]) {
            groups[name] = [];
        }
        groups[name].push(row);
    }

    return Object.entries(groups).map(([name, rows]) => ({ name, rows }));
});

function formatUsage(row) {
    const suffix = row.monthly ? ' this month' : '';
    return `${row.usage ?? 0} ${row.unit || ''}${suffix}`.trim();
}

function formatRemaining(row) {
    if (row.unlimited) {
        return 'Unlimited';
    }

    return `${row.remaining ?? 0} ${row.unit || ''}`.trim();
}

onMounted(() => {
    loadUsage();
});

function syncLimitRows() {
    const rows = usageData.value?.limits || [];
    limitRows.value = rows.map((row) => {
        const enabled = row.override_enabled !== null && row.override_enabled !== undefined
            ? row.override_enabled
            : (row.default_enabled ?? false);

        return {
            key: row.key,
            label: row.label,
            unit: row.unit,
            group: row.group,
            monthly: row.monthly,
            description: row.description,
            usage: row.usage,
            remaining: row.remaining,
            unlimited: row.unlimited,
            enabled,
            value: enabled
                ? String(row.override_value ?? row.effective_limit ?? row.default_value ?? '')
                : '',
            default_value: row.default_value,
            default_enabled: row.default_enabled,
            inherited_from_default: row.override_enabled == null && row.default_enabled,
            tenant_unlimited_override: row.tenant_unlimited_override ?? false,
            revert: false,
        };
    });
}

function loadUsage() {
    loading.value = true;
    axios.get(props.routes.usage, {
        params: { period: selectedPeriod.value },
    })
        .then((response) => {
            usageData.value = response.data;
            syncLimitRows();
        })
        .catch((error) => {
            handleError(error);
        })
        .finally(() => {
            loading.value = false;
        });
}

function handlePeriodChange(period) {
    selectedPeriod.value = period;
    loadUsage();
}

function saveLimits() {
    saving.value = true;
    axios.put(props.routes.update, {
        limits: limitRows.value.map((row) => ({
            key: row.key,
            enabled: row.enabled,
            value: row.value,
            revert: row.revert,
        })),
    })
        .then((response) => {
            usageData.value = response.data.data;
            syncLimitRows();
            showNotification('success', response.data.messages ?? { success: ['Limits saved.'] });
        })
        .catch(handleError)
        .finally(() => {
            saving.value = false;
        });
}

function handleError(error) {
    if (error?.response?.data?.errors) {
        showNotification('error', error.response.data.errors);
        return;
    }

    showNotification('error', { error: ['Request failed.'] });
}
</script>
