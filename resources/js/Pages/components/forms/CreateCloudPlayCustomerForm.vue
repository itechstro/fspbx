<template>
    <form @submit.prevent="submitForm">
        <div v-if="errors?.server" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            <p v-for="(message, index) in errors.server" :key="index">{{ message }}</p>
        </div>

        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-6">
                <LabelInputRequired target="cust_cmp_name" label="Company Name" />
                <InputField v-model="form.cust_cmp_name" type="text" class="mt-2" :error="!!errors?.cust_cmp_name" />
                <div v-if="errors?.cust_cmp_name" class="mt-2 text-xs text-red-600">{{ errors.cust_cmp_name[0] }}</div>
            </div>
            <div class="sm:col-span-3">
                <LabelInputRequired target="cust_firstname" label="First Name" />
                <InputField v-model="form.cust_firstname" type="text" class="mt-2" :error="!!errors?.cust_firstname" />
                <div v-if="errors?.cust_firstname" class="mt-2 text-xs text-red-600">{{ errors.cust_firstname[0] }}</div>
            </div>
            <div class="sm:col-span-3">
                <LabelInputRequired target="cust_lastname" label="Last Name" />
                <InputField v-model="form.cust_lastname" type="text" class="mt-2" :error="!!errors?.cust_lastname" />
                <div v-if="errors?.cust_lastname" class="mt-2 text-xs text-red-600">{{ errors.cust_lastname[0] }}</div>
            </div>
            <div class="sm:col-span-3">
                <LabelInputRequired target="cust_username" label="Tenant Customer Username" />
                <InputField v-model="form.cust_username" type="text" class="mt-2" :error="!!errors?.cust_username" />
                <div v-if="errors?.cust_username" class="mt-2 text-xs text-red-600">{{ errors.cust_username[0] }}</div>
            </div>
            <div class="sm:col-span-3">
                <LabelInputRequired target="cust_password" label="Tenant Customer Password" />
                <InputField v-model="form.cust_password" type="password" class="mt-2" :error="!!errors?.cust_password" />
                <div v-if="errors?.cust_password" class="mt-2 text-xs text-red-600">{{ errors.cust_password[0] }}</div>
            </div>
            <div class="sm:col-span-6">
                <LabelInputRequired target="cust_contact_email" label="Contact Email" />
                <InputField v-model="form.cust_contact_email" type="email" class="mt-2" :error="!!errors?.cust_contact_email" />
                <div v-if="errors?.cust_contact_email" class="mt-2 text-xs text-red-600">{{ errors.cust_contact_email[0] }}</div>
            </div>
            <div class="sm:col-span-6">
                <label class="block text-sm font-medium text-gray-700">Authorized IPs</label>
                <InputField v-model="form.cust_auth_ips" type="text" class="mt-2" placeholder="0.0.0.0/0" />
            </div>
        </div>

        <div class="border-t mt-4">
            <div class="mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:col-start-2"
                    :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Activate
                </button>
                <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="emits('cancel')">Cancel</button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import InputField from '../general/InputField.vue';
import LabelInputRequired from '../general/LabelInputRequired.vue';
import Spinner from '../general/Spinner.vue';

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();
const emits = defineEmits(['submit', 'cancel']);

const form = reactive({
    domain_uuid: '',
    cust_cmp_name: '',
    cust_firstname: '',
    cust_lastname: 'Customer',
    cust_username: '',
    cust_password: '',
    cust_contact_email: '',
    cust_auth_ips: '0.0.0.0/0',
    _token: page.props.csrf_token,
});

watch(
    () => props.options,
    (options) => {
        if (!options?.model) {
            return;
        }

        form.domain_uuid = options.model.domain_uuid ?? '';
        form.cust_cmp_name = options.settings?.suggested_cust_cmp_name ?? options.model.domain_description ?? options.model.domain_name ?? '';
        form.cust_firstname = options.settings?.suggested_cust_cmp_name ?? options.model.domain_description ?? options.model.domain_name ?? '';
        form.cust_username = options.settings?.suggested_cust_username ?? '';
    },
    { immediate: true, deep: true },
);

const submitForm = () => {
    emits('submit', form);
};
</script>
