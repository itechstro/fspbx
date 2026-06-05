<template>
    <div class="card" @mouseover="applyHoverStyle" @mouseout="removeHoverStyle" @click="openCallDetailRecords" style="transition: all 0.3s ease; cursor: pointer;">
        <div class="card-body pt-2">
            <div class="d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f3fa;">
                <h6 class="m-0" style="color: #6c757d;font-size:20px">Missed calls</h6>
                <i class="mdi mdi-phone-missed text-info" style="font-size: 2rem;"></i>
            </div>
            <div class="mt-3">
                <div id="mcTgt" class="d-flex justify-content-between align-items-center">
                    <h2 class="my-2 text-info">{{voicemails +  missedCalls }}</h2>
                    <div>
                        <div class="mb-0 text-muted">
                            <span class="me-1">{{  missedCalls }}</span>
                            <span class="text-nowrap">Missed Calls</span>
                        </div>
                        <div class="mb-0 text-muted">
                            <span class="me-1">{{ voicemails }}</span>
                            <span class="text-nowrap">Voicemails</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    missedCalls: Number,
    voicemails: Number,
  });

// Function to simulate Livewire's wire:poll functionality
const updateMissedCalls = () => {
    // Update logic for missed calls and voicemails
};

// Simulating wire:click behavior
const openCallDetailRecords = () => {
    // Logic to handle click event
};

// Hover style handling
const applyHoverStyle = (event) => {
    event.currentTarget.style.boxShadow = '0px 5px 10px 3px rgba(0, 0, 0, 0.2)';
    event.currentTarget.style.transform = 'translateY(-5px)';
};

const removeHoverStyle = (event) => {
    event.currentTarget.style.boxShadow = 'none';
    event.currentTarget.style.transform = 'translateY(0px)';
};

// Simulating Livewire's wire:poll.90s
setInterval(updateMissedCalls, 90000);
</script>
