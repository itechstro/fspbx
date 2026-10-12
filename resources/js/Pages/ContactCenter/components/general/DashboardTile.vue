<template>
    <div class="flex flex-col h-full divide-y divide-gray-200 overflow-hidden rounded-lg bg-white shadow">
        <div class="px-4 py-2 sm:px-6">
            <a :href="card.href" class="group">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span
                            :class="[iconStyles.bgColor, iconStyles.textColor, iconStyles.hoverTextColor, 'inline-flex rounded-lg p-2 ring-4 ring-white']">
                            <component :is="iconComponent" class="h-4 w-4" aria-hidden="true" />
                        </span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="truncate text-md font-bold text-gray-500 group-hover:text-gray-700">{{ card.name }}
                            </dt>
                        </dl>
                    </div>
                </div>
            </a>
        </div>
        <div class="bg-gray-50 px-4 py-2 sm:px-6 h-full">
            <div v-if="!data" class="animate-pulse flex space-x-4">
                <div class="flex-1 space-y-4 py-1">
                    <div class="h-2 bg-slate-200 rounded"></div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="h-2 bg-slate-200 rounded col-span-2"></div>
                            <div class="h-2 bg-slate-200 rounded col-span-1"></div>
                        </div>
                        <!-- <div class="h-2 bg-slate-200 rounded"></div> -->
                    </div>
                    <div class="space-y-3">
                        <!-- <div class="grid grid-cols-3 gap-4">
                            <div class="h-2 bg-slate-200 rounded col-span-2"></div>
                            <div class="h-2 bg-slate-200 rounded col-span-1"></div>
                        </div> -->
                        <div class="h-2 bg-slate-200 rounded"></div>
                    </div>
                </div>
            </div>

            <div v-else class="flex text-sm justify-between items-center h-full gap-6">
                <div class="text-2xl text-blue-700">{{ data.key_metric }}
                </div>
                <div class="">
                    <div v-for="metric in data.details">
                        {{ metric }}
                    </div>

                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

import SupportAgent from "@icons/SupportAgent.vue"
// import FaxIcon from "../icons/FaxIcon.vue"
// import CallHistoryIcon from "../icons/CallHistoryIcon.vue"
// import DevicesIcon from "../icons/DevicesIcon.vue"
// import DialpadIcon from "../icons/DialpadIcon.vue"
// import ContactPhoneIcon from "../icons/ContactPhoneIcon.vue"
// import AlternativeRouteIcon from "../icons/AlternativeRouteIcon.vue"
// import IvrIcon from "../icons/IvrIcon.vue"
// import SupportAgent from "../icons/SupportAgent.vue"

const props = defineProps({
    card: Object,
    data: Object,
    count: {
        type: Number,
        default: () => (null) // Providing an empty object as default
    }
})


// Map string keys to actual component objects
const iconMap = {
    SupportAgent: SupportAgent,
    // UserGroupIcon: UserGroupIcon,
    // VoicemailIcon: VoicemailIcon,
    // CalendarDaysIcon: CalendarDaysIcon,
    // FaxIcon: FaxIcon,
    // CallHistoryIcon: CallHistoryIcon,
    // DevicesIcon: DevicesIcon,
    // DialpadIcon: DialpadIcon,
    // ContactPhoneIcon: ContactPhoneIcon,
    // AlternativeRouteIcon: AlternativeRouteIcon,
    // IvrIcon: IvrIcon,
    // SupportAgent: SupportAgent,
}

const styleMap = {
    SupportAgent: { bgColor: 'bg-blue-50', textColor: 'text-blue-700', hoverTextColor: 'group-hover:text-blue-900' },
    UserGroupIcon: { bgColor: 'bg-purple-50', textColor: 'text-purple-700', hoverTextColor: 'group-hover:text-purple-900' },
    ContactPhoneIcon: { bgColor: 'bg-sky-50', textColor: 'text-sky-700', hoverTextColor: 'group-hover:text-sky-900' },
    VoicemailIcon: { bgColor: 'bg-fuchsia-50', textColor: 'text-fuchsia-700', hoverTextColor: 'group-hover:text-fuchsia-900' },
    CalendarDaysIcon: { bgColor: 'bg-rose-50', textColor: 'text-rose-700', hoverTextColor: 'group-hover:text-rose-900' },
    FaxIcon: { bgColor: 'bg-indigo-50', textColor: 'text-indigo-700', hoverTextColor: 'group-hover:text-indigo-900' },
    CallHistoryIcon: { bgColor: 'bg-red-50', textColor: 'text-red-700', hoverTextColor: 'group-hover:text-red-900' },
    DevicesIcon: { bgColor: 'bg-lime-50', textColor: 'text-lime-700', hoverTextColor: 'group-hover:text-lime-900' },
    DialpadIcon: { bgColor: 'bg-green-50', textColor: 'text-green-700', hoverTextColor: 'group-hover:text-green-900' },
    AlternativeRouteIcon: { bgColor: 'bg-cyan-50', textColor: 'text-cyan-700', hoverTextColor: 'group-hover:text-cyan-900' },
    IvrIcon: { bgColor: 'bg-blue-50', textColor: 'text-blue-700', hoverTextColor: 'group-hover:text-blue-900' },
};

// Computed property to get the correct icon component
const iconComponent = computed(() => iconMap[props.card.icon]);

const iconStyles = computed(() => styleMap[props.card.icon] || { bgColor: 'default-bg', textColor: 'default-text' });


</script>