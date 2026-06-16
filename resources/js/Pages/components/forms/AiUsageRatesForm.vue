<template>
    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6 text-gray-600">
        <div>
            <h4 class="text-base font-semibold text-gray-900">AI Usage Rates</h4>
            <p class="mt-1 text-sm text-gray-500">
                Estimated provider rates for per-call cost reporting and tenant spend limits. These are not live invoice amounts.
            </p>
        </div>

        <div v-if="loading" class="text-sm text-gray-500">Loading rates...</div>

        <template v-else>
            <div v-for="group in schema" :key="group.group" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h5 class="text-sm font-semibold text-gray-900">{{ group.group }}</h5>
                <p v-if="group.description" class="mt-1 text-sm text-gray-500">{{ group.description }}</p>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label
                        v-for="field in group.fields"
                        :key="field.key"
                        class="block text-sm"
                    >
                        <span class="font-medium text-gray-700">{{ field.label }}</span>
                        <input
                            v-model.number="rates[field.key]"
                            type="number"
                            min="0"
                            step="any"
                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 disabled:bg-gray-100"
                            :disabled="!canEdit || saving"
                        />
                    </label>
                </div>
            </div>

            <div v-if="canEdit" class="flex justify-end">
                <button
                    type="button"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="saving"
                    @click="saveRates"
                >
                    Save rates
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    routes: { type: Object, required: true },
});

const emit = defineEmits(['success', 'error']);

const loading = ref(true);
const saving = ref(false);
const schema = ref([]);
const rates = ref({});
const canEdit = ref(false);

onMounted(() => {
    loadRates();
});

async function loadRates() {
    loading.value = true;
    try {
        const response = await axios.get(props.routes.ai_usage_rates_show);
        schema.value = response.data.schema || [];
        rates.value = { ...(response.data.rates || {}) };
        canEdit.value = !!response.data.can_edit;
    } catch (error) {
        emit('error', error);
    } finally {
        loading.value = false;
    }
}

async function saveRates() {
    saving.value = true;
    try {
        const response = await axios.put(props.routes.ai_usage_rates_update, {
            rates: rates.value,
        });
        rates.value = { ...(response.data.rates || rates.value) };
        emit('success', response.data.messages ?? { success: ['AI usage rates saved.'] });
    } catch (error) {
        emit('error', error);
    } finally {
        saving.value = false;
    }
}
</script>
