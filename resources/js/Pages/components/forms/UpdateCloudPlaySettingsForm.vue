<template>
    <form @submit.prevent="submitForm">
        <div v-if="errors?.server" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            <p v-for="(message, index) in errors.server" :key="index">{{ message }}</p>
        </div>

        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired target="api_url" label="API URL" />
                <div class="mt-2">
                    <InputField v-model="form.api_url" type="text" name="api_url"
                        placeholder="https://vgate.cloudplay.cloud:8091/v1.58.5/api"
                        :error="errors?.api_url && errors.api_url.length > 0" />
                </div>
                <div v-if="errors?.api_url" class="mt-2 text-sm text-red-600">
                    {{ errors.api_url[0] }}
                </div>
            </div>

            <div class="sm:col-span-6">
                <LabelInputRequired target="admin_username" label="CloudPLAY Admin Username" />
                <div class="mt-2">
                    <InputField v-model="form.admin_username" type="text" name="admin_username"
                        :error="errors?.admin_username && errors.admin_username.length > 0" />
                </div>
            </div>

            <div class="sm:col-span-6">
                <label class="block text-sm font-medium text-gray-700">CloudPLAY Admin Password</label>
                <div class="mt-2">
                    <InputField v-model="form.admin_password" type="password" name="admin_password"
                        :placeholder="settings?.has_admin_password ? 'Leave blank to keep current password' : 'Enter admin password'"
                        :error="!!errors?.admin_password" />
                </div>
                <div v-if="errors?.admin_password" class="mt-2 text-sm text-red-600">
                    {{ errors.admin_password[0] }}
                </div>
            </div>
        </div>

        <div class="border-t mt-4 sm:mt-4">
            <div class="mt-4 sm:mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:col-start-2"
                    :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Save
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
import InputField from '../general/InputField.vue';
import LabelInputRequired from '../general/LabelInputRequired.vue';
import Spinner from '../general/Spinner.vue';

const props = defineProps({
    settings: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();
const emits = defineEmits(['submit', 'cancel']);

const form = reactive({
    api_url: props.settings?.api_url ?? '',
    admin_username: props.settings?.admin_username ?? '',
    admin_password: '',
    _token: page.props.csrf_token,
});

const submitForm = () => {
    emits('submit', form);
};
</script>
