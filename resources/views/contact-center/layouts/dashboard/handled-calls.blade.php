<div x-data="{ total: 0, inbound: 0, outbound: 0, multipleQueuesSelected: false }" x-init="() => {
    fetchCallData();
    setInterval(() => fetchCallData(), 30000);
}">

    <h2 class="my-2 text-info" x-text="total"></h2>

    <div>
        <div class="mb-0 text-muted">
            <span class="me-1" x-text="inbound"></span>
            <span class="text-nowrap">Inbound (answered)</span>
        </div>

        <template x-if="!multipleQueuesSelected">
            <div class="mb-0 text-muted">
                <span class="me-1" x-text="outbound"></span>
                <span class="text-nowrap">Outbound (connected)</span>
            </div>
        </template>
    </div>

    <script>
        function fetchCallData() {
            // Implement logic to fetch data using Alpine.js (e.g., Axios or Fetch API)
            axios.get('/contact-center/get-handled-calls', {
                    timeout: 5000, // Set the timeout to 5 seconds (adjust as needed)
                })
                .then(response => {
                    const data = response.data.data;
                    // console.log(Alpine.data['total']);
                    // Update x-data directly
                    Alpine.data['total'] = data.total;
                    Alpine.data['inbound'] = data.inbound;
                    Alpine.data['outbound'] = data.outbound;
                    Alpine.data['multipleQueuesSelected'] = data.multipleQueuesSelected;
                    

                    // Alpine.store('total', data.total);
                    // Alpine.store('inbound', data.inbound);
                    // Alpine.store('outbound', data.outbound);
                    // Alpine.store('multipleQueuesSelected', data.multipleQueuesSelected);
                })
                .catch(error => {
                    if (axios.isCancel(error)) {
                        console.error('Request canceled:', error.message);
                    } else {
                        console.error('Error fetching data:', error);
                    }
                });

        }
    </script>
</div>
