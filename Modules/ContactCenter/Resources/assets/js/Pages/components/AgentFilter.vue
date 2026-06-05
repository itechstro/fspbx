<template>
    <div class="d-flex">
        <div class="form-control p-0">
            <div v-if="agents" class="dropdown">
                <button type="button" class="btn dropdown-toggle w-100 text-start" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside">
                    Filter by Agent <span v-if="selectedAgents.length">({{ selectedAgents.length }})</span>
                </button>
                <div class="dropdown-menu">
                    <div class="px-3 py-2">
                        <div v-for="agent in agents" :key="agent.call_center_agent_uuid" class="mb-3">
                            <div class="form-check">
                                <label class="form-check-label">
                                    <input type="checkbox" class="agent-check form-check-input" v-model="selectedAgents"
                                        :value="agent.call_center_agent_uuid">
                                    {{ agent.agent_name }}
                                </label>
                            </div>
                        </div>
                        <hr />
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input id="selectAllAgents" type="checkbox" class="form-check-input" v-model="isAllSelected"
                                    @change="selectAllAgents($event.target.checked)">
                                Select All
                            </label>
                        </div>
                        <button type="button" class="btn btn-primary" @click="applyAgentFilter">Apply</button>

                    </div>
                </div>
            </div>
            <input v-else type="text" disabled class="form-control border-0" value="Filter by Agent" />
        </div>
    </div>
</template>


<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    agents: Array,
    filterData: Object // 
});

const emit = defineEmits(['update:selectedAgents']);
const selectedAgents = ref([]);
selectedAgents.value = props.filterData.agents;

const selectAllAgents = (checked) => {
    if (checked) {
        selectedAgents.value = props.agents.map(agents => agents.call_center_agent_uuid);
    } else {
        selectedAgents.value = [];
    }
};

const applyAgentFilter = () => {
    emit('update:selectedAgents', selectedAgents.value);
};


// Watcher to update Select All checkbox based on individual agent selection changes
watch(selectedAgents, (newVal) => {
  if (newVal.length !== props.agents.length) {
    // This ensures the Select All checkbox is unchecked if not all agents are selected
    document.getElementById("selectAllAgents").checked = false;
  }
  if (newVal.length == props.agents.length) {
    // This ensures the Select All checkbox is unchecked if not all agents are selected
    document.getElementById("selectAllAgents").checked = true;
  }
});

// Computed property to manage the state of the Select All checkbox
const isAllSelected = computed(() => {
    return selectedAgents.value.length === props.agents.length;
});
</script>
