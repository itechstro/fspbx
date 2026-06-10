<template>
    <form @submit.prevent="submitForm">
        <div v-if="errors?.server" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            <p v-for="(message, index) in errors.server" :key="index">{{ message }}</p>
        </div>

        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-gray-700">CloudPLAY Customer ID</label>
                <InputField :model-value="customer?.cust_id ?? ''" type="text" class="mt-2" disabled />
            </div>
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-gray-700">FSPBX Tenant</label>
                <InputField :model-value="customer?.domain_description || customer?.domain_name || ''" type="text" class="mt-2" disabled />
            </div>
            <div class="sm:col-span-6">
                <LabelInputRequired target="profile_id" label="CloudPLAY Profile" />
                <ComboBox :options="profiles" :search="true" placeholder="Select a profile"
                    :selectedItem="selectedProfile" @update:model-value="handleProfileSelected" />
                <div v-if="errors?.profile_id" class="mt-2 text-xs text-red-600">{{ errors.profile_id[0] }}</div>
            </div>
            <div class="sm:col-span-6">
                <InputField v-model="form.cust_username" type="text" class="mt-2" :error="!!errors?.cust_username" />
                <div v-if="errors?.cust_username" class="mt-2 text-xs text-red-600">{{ errors.cust_username[0] }}</div>
            </div>
            <div class="sm:col-span-6">
                <label class="block text-sm font-medium text-gray-700">Tenant Customer Password</label>
                <InputField v-model="form.cust_password" type="password" class="mt-2" :error="!!errors?.cust_password"
                    :placeholder="customer?.has_password ? 'Leave blank to keep current password' : 'Enter customer password'" />
                <div v-if="errors?.cust_password" class="mt-2 text-xs text-red-600">{{ errors.cust_password[0] }}</div>
            </div>
        </div>

        <div class="border-t mt-4">
            <div class="mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:col-start-2"
                    :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Save Connection
                </button>
                <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="emits('cancel')">Cancel</button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ComboBox from '../general/ComboBox.vue';
import InputField from '../general/InputField.vue';
import LabelInputRequired from '../general/LabelInputRequired.vue';
import Spinner from '../general/Spinner.vue';

const props = defineProps({
    customer: Object,
    profiles: Array,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();
const emits = defineEmits(['submit', 'cancel']);

const form = reactive({
    domain_uuid: '',
    cust_username: '',
    cust_password: '',
    profile_id: null,
    _token: page.props.csrf_token,
});

const selectedProfile = computed(() => {
    if (!form.profile_id) {
        return null;
    }

    return props.profiles?.find((profile) => String(profile.value) === String(form.profile_id)) ?? null;
});

watch(
    () => props.customer,
    (customer) => {
        if (!customer) {
            return;
        }

        form.domain_uuid = customer.domain_uuid ?? '';
        form.cust_username = customer.cust_username ?? '';
        form.cust_password = '';
        form.profile_id = customer.profile_id ? String(customer.profile_id) : null;
    },
    { immediate: true, deep: true },
);

const handleProfileSelected = (profile) => {
    form.profile_id = profile?.value ?? null;
};

const submitForm = () => {
    emits('submit', {
        ...form,
        profile_id: form.profile_id ? Number(form.profile_id) : null,
    });
};
</script>
