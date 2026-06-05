<template>
    <div class="card">
        <div class="card-body pt-2">
            <div class="d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f3fa;">
                <h6 class="m-0" style="color: #6c757d; font-size:20px">Missed call rate</h6>
                <i class="mdi mdi-chart-pie text-info" style="font-size: 2rem;"></i>
            </div>
            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="my-2 text-info">{{ rate.rate.toFixed(0) }}%</h2>
                    <div>
                        <div class="mb-0 text-muted">
                            <span class="me-1">{{ durationToHuman(rate.shortest) }}</span>
                            <span class="text-nowrap">Shortest</span>
                        </div>
                        <div class="mb-0 text-muted">
                            <span class="me-1">{{ durationToHuman(rate.avg) }}</span>
                            <span class="text-nowrap">Avg</span>
                        </div>
                        <div class="mb-0 text-muted">
                            <span class="me-1">{{ durationToHuman(rate.longest) }}</span>
                            <span class="text-nowrap">Longest</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>

const props = defineProps({
    rate: Array,
});

// Function to convert duration to human-readable format
const durationToHuman = (seconds) => {
    var minutes = Math.floor(seconds / 60);
    seconds = (seconds % 60).toFixed(0); // Rounds to two decimal places

    return minutes + "m " + seconds + "s";
};

// Here you would have the logic to fetch or update the rate data
</script>
  