<template>
    <TextElement :name="name" :label="label" :placeholder="placeholder" :floating="floating" :columns="columns"
        :conditions="conditions" :default="defaultValue" :input-type="visible ? 'text' : 'password'" autocomplete="off">
        <template #addon-after>
            <button type="button"
                class="inline-flex h-full items-center px-3 text-gray-500 hover:text-gray-700 focus:outline-none"
                :title="visible ? 'Hide password' : 'Show password'"
                :aria-label="visible ? 'Hide password' : 'Show password'" @click.prevent="visible = !visible">
                <EyeSlashIcon v-if="visible" class="h-5 w-5" />
                <EyeIcon v-else class="h-5 w-5" />
            </button>
        </template>
    </TextElement>
</template>

<script setup>
import { ref } from 'vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

defineProps({
    name: {
        type: String,
        default: 'password',
    },
    label: {
        type: String,
        default: 'SIP Password',
    },
    placeholder: {
        type: String,
        default: 'Enter SIP password',
    },
    floating: {
        type: Boolean,
        default: false,
    },
    columns: {
        type: Object,
        default: () => ({}),
    },
    conditions: {
        type: Array,
        default: () => [],
    },
    defaultValue: {
        type: [String, Number],
        default: null,
    },
})

const visible = ref(false)
</script>
