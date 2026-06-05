<template>
    <div v-if="inline" v-show="show">
        <BasicQueueFormFields ref="fieldsRef" :options="options" :loading="loading" :mode="mode"
            :extended-fields="extendedFields" @close="emit('close')" @error="(...args) => emit('error', ...args)"
            @success="(...args) => emit('success', ...args)" @refresh-data="emit('refresh-data')" />
    </div>

    <TransitionRoot v-else as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <button type="button"
                                class="absolute right-4 top-4 rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="emit('close')">
                                <span class="sr-only">Close</span>
                                <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                            </button>

                            <BasicQueueFormFields ref="fieldsRef" :options="options" :loading="loading" :mode="mode"
                                :extended-fields="extendedFields" @close="emit('close')" @error="(...args) => emit('error', ...args)"
                                @success="(...args) => emit('success', ...args)" @refresh-data="emit('refresh-data')" />
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>

    </TransitionRoot>
</template>

<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import BasicQueueFormFields from "./BasicQueueFormFields.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: String,
    inline: {
        type: Boolean,
        default: false,
    },
    extendedFields: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
</script>
