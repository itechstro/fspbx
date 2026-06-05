<template>
    <div class="card">
        <div class="card-body pt-2">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f3fa;">
                <h6 class="m-0" style="color: #6c757d; font-size:20px">Live calls</h6>
                <i class="mdi mdi-account-multiple-outline text-info" style="font-size: 2rem;"></i>
            </div>

            <!-- Content -->
            <div v-if="calls !== null">
                <!-- Calls Summary -->
                <div class="row text-center">
                    <div class="col-sm-6">
                        <p class="text-muted mb-0 mt-3">In Progress</p>
                        <h2 class="mb-3 text-info">{{ totalProgress }}</h2>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted mb-0 mt-3">Queued</p>
                        <h2 class="mb-3 text-secondary">{{ totalQueued }}</h2>
                    </div>
                </div>

                <!-- Calls Table -->
                <div class="row" v-if="calls.length">
                    <div class="table-wrapper">
                        <div class="header">
                            <table class="table table-sm mb-0 table-centered">
                                <thead>
                                <tr>
                                    <th class="col1"></th>
                                    <th class="col2" @click="sortBy('cid_name')">Customer<span v-if="sortKey === 'cid_name'" class="arrow" :class="{ 'arrow-asc': sortOrders['cid_name'] > 0, 'arrow-desc': sortOrders['cid_name'] < 0 }"></span></th>
                                    <th class="col3" @click="sortBy('joined_epoch')">Joined<span v-if="sortKey === 'joined_epoch'" class="arrow" :class="{ 'arrow-asc': sortOrders['joined_epoch'] > 0, 'arrow-desc': sortOrders['joined_epoch'] < 0 }"></span></th>
                                    <th class="col4" @click="sortBy('state')">Status<span v-if="sortKey === 'state'" class="arrow" :class="{ 'arrow-asc': sortOrders['state'] > 0, 'arrow-desc': sortOrders['state'] < 0 }"></span></th>
                                    <th class="col5" @click="sortBy('serving_agent_name')">Agent<span v-if="sortKey === 'serving_agent_name'" class="arrow" :class="{ 'arrow-asc': sortOrders['serving_agent_name'] > 0, 'arrow-desc': sortOrders['serving_agent_name'] < 0 }"></span></th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="body">
                            <table class="table table-sm mb-0 table-centered">
                                <tbody>
                                <tr v-for="call in sortedCalls" :key="call.uuid">
                                    <td class="col1">
                                        <div class="avatar-xs rounded">
                                        <span
                                            class="avatar-title bg-white text-info border border-info rounded-circle h4 my-0">
                                            <i class="uil uil-user"></i>
                                        </span>
                                        </div>
                                    </td>
                                    <td class="col2">
                                        <h4 class="mt-0 mb-1 font-16 fw-semibold">{{ call.cid_name }}</h4>
                                        <p class="mb-0 text-muted">{{ call.cid_number }}</p>
                                    </td>
                                    <td class="col3">{{ durationToHuman(call.joined_epoch) }} ago</td>
                                    <td class="col4">
                                    <span v-if="call.state === 'Trying' || call.state === 'Waiting'"
                                          class="badge bg-primary text-white">In Queue</span>
                                        <span v-else-if="call.state === 'Answered'"
                                              class="badge bg-success text-white">Answered</span>
                                    </td>
                                    <td class="col5">{{ call.serving_agent_name }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else>
                <div class="w-50 text-center mt-4 mb-3 m-auto">
                    Live calls are not available for multiple contact centers. To view this data, change your filters to
                    1
                    contact center.
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted, computed} from 'vue'

const props = defineProps({
    calls: Array,
    totalProgress: Number,
    totalQueued: Number,
    multipleQueuesSelected: Boolean,
});

onMounted(() => {
    sortBy('joined_epoch');
});

const sortKey = ref('');
const sortOrders = ref({'calls': 1, 'Customer': 1, 'Joined': 1, 'Status': 1, 'Agent': 1});

const durationToHuman = (seconds) => {
    var minutes = Math.floor(seconds / 60);
    seconds = (seconds % 60).toFixed(0); // Rounds to two decimal places

    return minutes + "m " + seconds + "s";
};

const sortBy = (key) => {
    if (sortKey.value == key) {
        sortOrders.value[key] = sortOrders.value[key] * -1;
    } else {
        sortKey.value = key;
        sortOrders.value[key] = 1;
    }

    props.calls.sort((a, b) => {
        a = a[key];
        b = b[key];
        return (a === b ? 0 : a > b ? 1 : -1) * sortOrders.value[key];
    });
}

const sortedCalls = computed(() => {
    let sortedCalls = [...props.calls];  // create a copy so we don't mutate props

    return sortedCalls.sort((a, b) => {
        a = a[sortKey.value];
        b = b[sortKey.value];
        return (a === b ? 0 : a > b ? 1 : -1) * sortOrders.value[sortKey.value];
    });
});
</script>
<style scoped>
.table-wrapper > .body {
    overflow-y: auto;
    height: 500px;
}

.table-wrapper .header th {
    cursor: pointer;
}

.table-wrapper .col1 {
    width: 50px;
}

.table-wrapper .col3, .table-wrapper .col4 {
    width: 13%;
    min-width: 100px;
}

.table-wrapper .col5 {
    width: 15%;
    min-width: 100px;
}
.arrow {
    display: inline-block;
    vertical-align: middle;
    width: 0;
    height: 0;
    margin-left: 2px;
    opacity: 0.66;
}
.arrow-asc {
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-bottom: 4px solid #000;
}
.arrow-desc {
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 4px solid #000;
}
</style>
