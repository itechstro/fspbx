<template>
    <form @submit.prevent="submitForm">
        <div v-if="errors?.server" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            <p v-for="(message, index) in errors.server" :key="index">{{ message }}</p>
        </div>

        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired target="cust_id" label="Customer" />
                <ComboBox :options="customers" :search="true" placeholder="Select a customer"
                    @update:model-value="handleCustomerSelected" />
            </div>
            <div class="sm:col-span-6">
                <LabelInputRequired target="cust_username" label="Tenant Customer Username" />
                <InputField v-model="form.cust_username" type="text" class="mt-2" :error="!!errors?.cust_username" />
                <div v-if="errors?.cust_username" class="mt-2 text-xs text-red-600">{{ errors.cust_username[0] }}</div>
            </div>
            <div class="sm:col-span-6">
                <LabelInputRequired target="cust_password" label="Tenant Customer Password" />
                <InputField v-model="form.cust_password" type="password" class="mt-2" :error="!!errors?.cust_password" />
                <div v-if="errors?.cust_password" class="mt-2 text-xs text-red-600">{{ errors.cust_password[0] }}</div>
            </div>
        </div>

        <div class="border-t mt-4">
            <div class="mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:col-start-2"
                    :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Connect
                </button>
                <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="emits('cancel')">Cancel</button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ComboBox from '../general/ComboBox.vue';
import InputField from '../general/InputField.vue';
import LabelInputRequired from '../general/LabelInputRequired.vue';
import Spinner from '../general/Spinner.vue';

const props = defineProps({
    customers: Array,
    selectedAccount: String,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();
const emits = defineEmits(['submit', 'cancel']);

const form = reactive({
    domain_uuid: props.selectedAccount,
    cust_id: null,
    cust_username: '',
    cust_password: '',
    _token: page.props.csrf_token,
});

const handleCustomerSelected = (customer) => {
    form.cust_id = customer?.value ?? null;
    form.cust_username = customer?.cust_username ?? '';
};

const submitForm = () => {
    emits('submit', form);
};
</script>
