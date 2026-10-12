<template>
    <div class="card">
        <div class="card-body pt-2">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f3fa;">
                <h6 class="m-0" style="color: #6c757d; font-size:20px">Agents</h6>
                <i class="mdi mdi-account-multiple-outline text-info" style="font-size: 2rem;"></i>
            </div>
            <div class="row mt-2 mb-2">
                <div class="col-xl-8 col-lg-8 col-md-8 col-sm-8">
                    <button class="btn btn-sm btn-light me-2" @click="changeVisibility('all')">Show All</button>
                    <button class="btn btn-sm btn-light me-2" @click="changeVisibility('available')">Show Available</button>
                    <button class="btn btn-sm btn-danger me-2" :disabled="!isAnyAgentSelected" @click="changeVisibility('hide')">Hide selected</button>
                </div>
                <div class="text-xl-end text-lg-end text-md-end text-sm-end col-xl-4 col-lg-4 col-md-4 col-sm-4">
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="columnVisibility"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Show columns
                        </button>
                        <div class="dropdown-menu" aria-labelledby="columnVisibility">
                            <a class="dropdown-item" href="javascript:void(0);"
                               @click="showColumn('status')"
                               :class="{ 'selected': columnsVisibility.status }">Status</a>
                            <a class="dropdown-item" href="javascript:void(0);"
                               @click="showColumn('last_change')"
                               :class="{ 'selected': columnsVisibility.last_change }">Last Change</a>
                            <a class="dropdown-item" href="javascript:void(0);"
                               @click="showColumn('inbound_calls')"
                               :class="{ 'selected': columnsVisibility.inbound_calls }">Inbound Calls</a>
                            <a class="dropdown-item" href="javascript:void(0);"
                               @click="showColumn('minutes')"
                               :class="{ 'selected': columnsVisibility.minutes }">Minutes</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div v-if="filteredAgents.firstTable.length > 0" :class="{ 'col-lg-12': filteredAgents.secondTable.length === 0, 'col-lg-6': filteredAgents.secondTable.length > 0 }" class="col-md-12 col-sm-12">
                    <table class="table table-sm table-centered" id="firstTableParent">
                        <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectallFirstCheckbox" @click="event => selectAll('firstTable', event)">
                                            <label class="form-check-label" for="selectallFirstCheckbox">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th>Agent</th>
                                    <th v-if="columnsVisibility.status">Status</th>
                                    <th v-if="columnsVisibility.last_change">Last Change</th>
                                    <th v-if="columnsVisibility.inbound_calls">Inbound Calls</th>
                                    <th v-if="columnsVisibility.minutes">Minutes</th>
                                    <th></th>
                                </tr>
                            </thead>
                        <tbody>
                                <tr v-for="(agent, index) in filteredAgents.firstTable"
                                    :key="agent.call_center_agent_uuid"
                                    :id="agent.call_center_agent_uuid">
                                    <td>
                                        <div class="form-check">
                                            <input type="checkbox" name="action_box[]" :value="agent.call_center_agent_uuid"
                                                   v-model="checkboxState.firstTable[index]"
                                                   class="form-check-input action_checkbox">
                                            <label class="form-check-label">&nbsp;</label>
                                        </div>
                                    </td>
                                    <td>{{ agent.agent_name }}</td>
                                    <td v-if="columnsVisibility.status">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                                                aria-haspopup="true" aria-expanded="false">
                                                <span v-if="agent.status === 'Available'">
                                                    <span
                                                        v-if="agent.state === 'Receiving' || agent.state === 'In a queue call'"
                                                        class="badge bg-danger">
                                                        {{ agent.state === 'Receiving' ? 'Receiving a Call' : 'On a Call' }}
                                                    </span>
                                                    <span v-else class="badge bg-success">Available</span>
                                                </span>
                                                <span v-if="agent.status === 'On Break'" class="badge bg-warning">{{
                                                    agent.status
                                                }}</span>
                                                <span v-if="agent.status === 'Logged Out'" class="badge bg-secondary">{{
                                                    agent.status }}</span>
                                            </button>
                                            <div class="dropdown-menu">
                                                <span style="cursor: pointer;" class="dropdown-item"
                                                    @click="setAgentStatus(agent.call_center_agent_uuid, 'Available')">Available</span>
                                                <span style="cursor: pointer;" class="dropdown-item"
                                                    @click="setAgentStatus(agent.call_center_agent_uuid, 'Logged Out')">Logged
                                                    Out</span>
                                                <span style="cursor: pointer;" class="dropdown-item"
                                                    @click="setAgentStatus(agent.call_center_agent_uuid, 'On Break')">On
                                                    Break</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td v-if="columnsVisibility.last_change">{{ agentDurationToHuman(agent.last_status_change) }}</td>
                                    <td v-if="columnsVisibility.inbound_calls">{{ agent.calls_answered }}</td>
                                    <td v-if="columnsVisibility.minutes">{{ (agent.talk_time / 60).toFixed(1) }}</td>
                                    <td>
                                        <span style="cursor: pointer;" @click.prevent="updateRowVisibility('firstTable', index)">
                                            <i class="mdi mdi-eye-off"></i>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                    </table>
                </div>
                <div v-if="filteredAgents.secondTable.length > 0" :class="{ 'col-lg-12': filteredAgents.firstTable.length === 0, 'col-lg-6': filteredAgents.firstTable.length > 0 }" class="col-md-12 col-sm-12">
                    <table class="table table-sm table-centered" id="secondTableParent">
                        <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectallSecondCheckbox" @click="event => selectAll('secondTable', event)">
                                            <label class="form-check-label" for="selectallSecondCheckbox">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th>Agent</th>
                                    <th v-if="columnsVisibility.status">Status</th>
                                    <th v-if="columnsVisibility.last_change">Last Change</th>
                                    <th v-if="columnsVisibility.inbound_calls">Inbound Calls</th>
                                    <th v-if="columnsVisibility.minutes">Minutes</th>
                                    <th></th>
                                </tr>
                            </thead>
                        <tbody>
                                <tr v-for="(agent, index) in filteredAgents.secondTable"
                                    :key="agent.call_center_agent_uuid"
                                    :id="agent.call_center_agent_uuid">
                                    <td>
                                        <div class="form-check">
                                            <input type="checkbox" name="action_box[]" :value="agent.call_center_agent_uuid"
                                                   v-model="checkboxState.secondTable[index]"
                                                   class="form-check-input action_checkbox">
                                            <label class="form-check-label">&nbsp;</label>
                                        </div>
                                    </td>
                                    <td class="agent-column">{{ agent.agent_name }}
                                    </td>
                                    <td v-if="columnsVisibility.status">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                                                aria-haspopup="true" aria-expanded="false">
                                                <span v-if="agent.status === 'Available'">
                                                    <span
                                                        v-if="agent.state === 'Receiving' || agent.state === 'In a queue call'"
                                                        class="badge bg-danger">
                                                        {{ agent.state === 'Receiving' ? 'Receiving a Call' : 'On a Call' }}
                                                    </span>
                                                    <span v-else class="badge bg-success">Available</span>
                                                </span>
                                                <span v-if="agent.status === 'On Break'" class="badge bg-warning">{{
                                                    agent.status
                                                }}</span>
                                                <span v-if="agent.status === 'Logged Out'" class="badge bg-secondary">{{
                                                    agent.status }}</span>
                                            </button>
                                            <div class="dropdown-menu">
                                                <span style="cursor: pointer;" class="dropdown-item"
                                                    @click="setAgentStatus(agent.call_center_agent_uuid, 'Available')">Available</span>
                                                <span style="cursor: pointer;" class="dropdown-item"
                                                    @click="setAgentStatus(agent.call_center_agent_uuid, 'Logged Out')">Logged
                                                    Out</span>
                                                <span style="cursor: pointer;" class="dropdown-item"
                                                    @click="setAgentStatus(agent.call_center_agent_uuid, 'On Break')">On
                                                    Break</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td v-if="columnsVisibility.last_change">{{
                                        agentDurationToHuman(agent.last_status_change) }}</td>
                                    <td v-if="columnsVisibility.inbound_calls">{{ agent.calls_answered }}</td>
                                    <td v-if="columnsVisibility.minutes">{{ (agent.talk_time / 60).toFixed(1) }}</td>
                                    <td>
                                        <span style="cursor: pointer;" @click.prevent="updateRowVisibility('secondTable', index)">
                                            <i class="mdi mdi-eye-off"></i>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';

const props = defineProps({
    agents: Array,
});

const agents = ref(props.agents);

const emit = defineEmits(['update-agent-status']);

const columns = ['status', 'last_change', 'inbound_calls', 'minutes'];

const columnsVisibility = reactive(Object.fromEntries(columns.map(column => [column, true])));

const showColumn = column => {
    columnsVisibility[column] = !columnsVisibility[column];
}

const checkboxState = reactive({
    firstTable: Array(Math.ceil(agents.value.length / 2)).fill(false),
    secondTable: Array(Math.ceil(agents.value.length / 2)).fill(false),
});

const agentDurationToHuman = (seconds) => {
    const days = Math.floor(seconds / (3600*24));
    const hours = Math.floor((seconds % (3600*24)) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return (days > 0 ? days + 'd ' : '') + (hours > 0 ? hours + 'h ' : '') + (minutes > 0 ? minutes + 'm' : '');
};

const firstTableAgents = computed(() => {
    const halfIndex = Math.ceil(agents.value.length / 2);
    return agents.value.slice(0, halfIndex);
});

const secondTableAgents = computed(() => {
    const halfIndex = Math.ceil(agents.value.length / 2);
    return agents.value.slice(halfIndex);
});

const filterAgents = (table, state) =>
    table.value.filter((_, index) => !checkboxState[state][index]);

const filteredAgents = reactive({
    firstTable: filterAgents(firstTableAgents, 'firstTable'),
    secondTable: filterAgents(secondTableAgents, 'secondTable'),
});

const selectAll = (table, event) => {
    checkboxState[table] = checkboxState[table].map(() => event.target.checked);
};

const changeVisibility = (action) => {
    if(action === 'hide') {
        filteredAgents.firstTable = filteredAgents.firstTable.filter((_, index) => !checkboxState.firstTable[index]);
        filteredAgents.secondTable = filteredAgents.secondTable.filter((_, index) => !checkboxState.secondTable[index]);
        checkboxState.firstTable = checkboxState.firstTable.map(() => false);
        checkboxState.secondTable = checkboxState.secondTable.map(() => false);
    } else if (action === 'all') {
        filteredAgents.firstTable = firstTableAgents.value;
        filteredAgents.secondTable = secondTableAgents.value;
        checkboxState.firstTable = checkboxState.firstTable.map(() => false);
        checkboxState.secondTable = checkboxState.secondTable.map(() => false);
    } else if (action === 'available') {
        filteredAgents.firstTable = firstTableAgents.value.filter(agent => agent.status === 'Available');
        filteredAgents.secondTable = secondTableAgents.value.filter(agent => agent.status === 'Available');
        checkboxState.firstTable = checkboxState.firstTable.map(() => false);
        checkboxState.secondTable = checkboxState.secondTable.map(() => false);
    }

    recalculateAgentsColumn();
};

const setAgentStatus = (agentId, status) => {
    emit('update-agent-status', { agentId, status });
};

const isAnyAgentSelected = computed(() =>
    Object.values(checkboxState)
        .flatMap(checkbox => checkbox)
        .includes(true)
);

onMounted(() => {
    checkboxState.firstTable = Array(firstTableAgents.value.length).fill(false);
    checkboxState.secondTable = Array(secondTableAgents.value.length).fill(false);
});

const updateRowVisibility = (table, rowIndex) => {
    if (table === 'firstTable') {
        filteredAgents.firstTable = filteredAgents.firstTable.filter((_, index) => index !== rowIndex);
    } else if (table === 'secondTable') {
        filteredAgents.secondTable = filteredAgents.secondTable.filter((_, index) => index !== rowIndex);
    }

    recalculateAgentsColumn();
};

const recalculateAgentsColumn = () => {
    if (filteredAgents.firstTable.length !== filteredAgents.secondTable.length) {
        const totalAgents = [...filteredAgents.firstTable, ...filteredAgents.secondTable];
        const pivot = Math.ceil(totalAgents.length / 2);
        filteredAgents.firstTable = totalAgents.slice(0, pivot);
        filteredAgents.secondTable = totalAgents.slice(pivot);
    }
}
</script>

<style scoped>
.selected::before {
    content: '✔';
    margin-right: 8px;
    font-weight: bold;
}
</style>
