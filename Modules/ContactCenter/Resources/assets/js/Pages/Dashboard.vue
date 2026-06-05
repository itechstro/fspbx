<template>

    <MainLayout>

        <main>
            <div class="mx-auto max-w-8xl px-4 sm:px-6 lg:px-8">
                <div class="mt-6 text-lg font-semibold leading-6 text-gray-600">Contact Center Dashboard</div>

                <div class="mx-auto max-w-2xl  lg:mx-0 lg:max-w-none ">

                    <div
                        class="-mx-4 mt-3 px-4 py-4 shadow-sm bg-gray-50 ring-1 ring-gray-900/5 sm:mx-0 sm:rounded-lg sm:px-8 sm:py-12 xl:px-12 xl:py-6">

                        <div class="container mx-auto">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">

                                <div class="">
                                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">Filter by
                                        Contact
                                        Center</label>
                                    <ComboBox :options="queueOptions" :selectedItem="selectedQueues" :multiple="true"
                                        :placeholder="'Select ...'" @apply-selection="handleSelectedQueuesUpdate"
                                        :error="null" />
                                </div>

                                <div class="">
                                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">Filter by
                                        Agent</label>
                                    <ComboBox :options="agentOptions" :multiple="true"
                                        :disabled="multipleQueuesSelected" :placeholder="'Select ...'"
                                        @apply-selection="handleSelectedAgentsUpdate" :error="null" />
                                </div>
                                <div class="z-10 -mt-0.5 mb-2 shrink-0 sm:mr-4">
                                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">Filter by
                                        Date</label>
                                    <DatePicker :dateRange="filterData.dateRange" :timezone="timezone"
                                        @update:date-range="handleUpdateDateRange" />
                                </div>


                            </div>
                        </div>


                    </div>

                    <div
                        class="-mx-4 mt-4 px-4 py-6 shadow-sm bg-gray-50 ring-1 ring-gray-900/5 sm:mx-0 sm:rounded-lg sm:px-8 sm:pb-14 xl:px-12 xl:pb-16 xl:pt-6">

                        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <div v-for="card in cards" :key="card.slug">
                                <div class="h-full bg-white rounded-lg shadow">
                                    <DashboardTile :card="card" :data="stats[card.slug]" />
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                <div class="mx-auto max-w-2xl  lg:mx-0 lg:max-w-none ">
                    <div
                        class="-mx-4 mt-4 px-4 py-6 shadow-sm bg-gray-50 ring-1 ring-gray-900/5 sm:mx-0 sm:rounded-lg sm:px-8 sm:pb-14 xl:px-12 xl:pb-16 xl:pt-6">

                        <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-2">

                            <div class="divide-y divide-gray-200 overflow-hidden rounded-lg bg-white shadow">
                                <div class="px-4 py-5 sm:px-6">
                                    <div class="truncate text-md font-bold text-gray-500 group-hover:text-gray-700">Call
                                        Volume
                                    </div>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <Bar v-if="stats.callVolumeChartData" :data="stats.callVolumeChartData"
                                        :options="callVolumeChartOptions" />
                                </div>
                            </div>

                            <div class="divide-y divide-gray-200 overflow-hidden rounded-lg bg-white shadow">
                                <div class="px-4 py-5 sm:px-6">
                                    <div class="truncate text-md font-bold text-gray-500 group-hover:text-gray-700">
                                        Average
                                        Call Duration
                                    </div>
                                    <!-- We use less vertical padding on card headers on desktop than on body sections -->
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <Line v-if="stats.callDurationChartData" :data="stats.callDurationChartData"
                                        :options="callAvgCallDurationChartOptions" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mx-auto max-w-2xl  lg:mx-0 lg:max-w-none ">
                    <div
                        class="-mx-4 mt-4 px-4 py-6 shadow-sm bg-gray-50 ring-1 ring-gray-900/5 sm:mx-0 sm:rounded-lg sm:px-8 sm:pb-14 xl:px-12 xl:pb-16 xl:pt-6">

                        <div class="ml-2 truncate text-md font-bold text-gray-500">Agents</div>
                        <div class="my-2 doverflow-hidden rounded-lg bg-white shadow">
                            <div class="px-4 py-2 sm:px-6">
                                <div class="flex py-2">
                                    <div class="sm:flex-auto">
                                        <button
                                            class="rounded bg-white px-2 mr-2 py-1 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                            @click="handleShowAllAgents">Show All
                                        </button>
                                        <button
                                            class="rounded bg-white px-2 mr-2 py-1 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                            @click="handleShowAvailableAgents">Show Available
                                        </button>
                                        <button
                                            class="rounded bg-white px-2 mr-2 py-1 text-xs font-semibold text-amber-900 shadow-sm ring-1 ring-inset ring-amber-300 hover:bg-amber-50"
                                            @click="handleHideSelectedAgents">Hide Selected
                                        </button>
                                    </div>
                                    <div>
                                        <Menu as="div" class="relative">
                                            <div>
                                                <MenuButton
                                                    class="rounded bg-white px-2 py-1 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                                    Show Columns
                                                </MenuButton>
                                            </div>
                                            <transition enter-active-class="transition ease-out duration-100"
                                                enter-from-class="transform opacity-0 scale-95"
                                                enter-to-class="transform opacity-100 scale-100"
                                                leave-active-class="transition ease-in duration-75"
                                                leave-from-class="transform opacity-100 scale-100"
                                                leave-to-class="transform opacity-0 scale-95">
                                                <MenuItems
                                                    class="absolute right-0 z-30 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                    <MenuItem v-slot="{ active }">
                                                    <button
                                                        class="block px-4 py-2 text-sm text-gray-700 w-full text-left flex"
                                                        @click="handleShowColumn('status')">
                                                        <div class="w-8">
                                                            <CheckIcon v-if="columnsVisibility.status"
                                                                class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                aria-hidden="true" />
                                                        </div>
                                                        <div>Status</div>
                                                    </button>
                                                    </MenuItem>
                                                    <MenuItem v-slot="{ active }">
                                                    <button href="#"
                                                        class="block px-4 py-2 text-sm text-gray-700 w-full text-left flex"
                                                        @click="handleShowColumn('last_change')">
                                                        <div class="w-8">
                                                            <CheckIcon v-if="columnsVisibility.last_change"
                                                                class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                aria-hidden="true" />
                                                        </div>
                                                        <div>Last Change</div>
                                                    </button>
                                                    </MenuItem>
                                                    <MenuItem v-slot="{ active }">
                                                    <button href="#"
                                                        class="block px-4 py-2 text-sm text-gray-700 w-full text-left flex"
                                                        @click="handleShowColumn('inbound_calls')">
                                                        <div class="w-8">
                                                            <CheckIcon v-if="columnsVisibility.inbound_calls"
                                                                class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                aria-hidden="true" />
                                                        </div>
                                                        <div>Inbound Calls</div>
                                                    </button>
                                                    </MenuItem>
                                                    <MenuItem v-slot="{ active }">
                                                    <button href="#"
                                                        class="block px-4 py-2 text-sm text-gray-700 w-full text-left flex"
                                                        @click="handleShowColumn('minutes')">
                                                        <div class="w-8">
                                                            <CheckIcon v-if="columnsVisibility.minutes"
                                                                class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                aria-hidden="true" />
                                                        </div>
                                                        <div>Minutes</div>
                                                    </button>
                                                    </MenuItem>
                                                </MenuItems>
                                            </transition>
                                        </Menu>
                                    </div>
                                </div>














                                <div class="overflow-auto h-72">
                                    <!-- Large Screen Layout -->
                                    <div v-if="isExtraLarge" class="xl:grid xl:grid-cols-2 xl:gap-4">
                                        <!-- First Half Table -->
                                        <div class="w-full lg:px-2 align-middle">
                                            <table class="min-w-full border-separate border-spacing-0">
                                                <thead>
                                                    <tr>
                                                        <TableColumnHeader
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900">
                                                            <div class="flex items-center justify-start">
                                                                <input type="checkbox" v-model="selectAgents"
                                                                    @change="handleSelectAgents"
                                                                    class="ml-1 h-4 w-4 rounded border-gray-300 text-indigo-600">
                                                                <span class="pl-4">Agent</span>
                                                            </div>
                                                        </TableColumnHeader>

                                                        <!-- Other Table Headers -->
                                                        <TableColumnHeader v-if="columnsVisibility.status"
                                                            header="Status"
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900" />
                                                        <TableColumnHeader v-if="columnsVisibility.last_change"
                                                            header="Last Change"
                                                            class="sticky whitespace-nowrap top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 sm:table-cell" />
                                                        <TableColumnHeader v-if="columnsVisibility.inbound_calls"
                                                            header="Inbound Calls"
                                                            class="sticky whitespace-nowrap top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 lg:table-cell xl:hidden 2xl:table-cell" />
                                                        <TableColumnHeader v-if="columnsVisibility.minutes"
                                                            header="Minutes"
                                                            class="sticky top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 lg:table-cell xl:hidden 2xl:table-cell" />
                                                        <TableColumnHeader header=""
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900" />
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="agent in firstHalfAgents"
                                                        :key="agent.call_center_agent_uuid"
                                                        v-show="agentsRowVisible[agent.call_center_agent_uuid]">
                                                        <td
                                                            class="whitespace-nowrap py-2 px-2 text-sm font-medium text-gray-900 border-b border-gray-200">
                                                            <div class="flex items-center">
                                                                <input v-if="agent.call_center_agent_uuid"
                                                                    v-model="selectedAgents" type="checkbox"
                                                                    name="action_box[]"
                                                                    :value="agent.call_center_agent_uuid"
                                                                    class="ml-1 h-4 w-4 rounded border-gray-300 text-indigo-600">
                                                                <div class="pl-4">{{ agent.agent_name }}</div>
                                                            </div>
                                                        </td>
                                                        <!-- Status Column -->
                                                        <td v-if="columnsVisibility.status"
                                                            class="whitespace-nowrap p2-3 py-2 text-sm text-gray-500 border-b border-gray-200">
                                                            <!-- Status dropdown here -->
                                                            <Menu as="div" class="relative">
                                                                <div>
                                                                    <MenuButton
                                                                        class="relative flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500  focus:ring-offset-1 lg:rounded-md lg:hover:bg-gray-50">
                                                                        <Badge :text="agent.status"
                                                                            :backgroundColor="getAgentBadgeColors(agent.status).background"
                                                                            :textColor="getAgentBadgeColors(agent.status).text"
                                                                            :ringColor="getAgentBadgeColors(agent.status).ring" />
                                                                        <ChevronDownIcon
                                                                            class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                            aria-hidden="true" />
                                                                    </MenuButton>
                                                                </div>
                                                                <transition
                                                                    enter-active-class="transition ease-out duration-100"
                                                                    enter-from-class="transform opacity-0 scale-95"
                                                                    enter-to-class="transform opacity-100 scale-100"
                                                                    leave-active-class="transition ease-in duration-75"
                                                                    leave-from-class="transform opacity-100 scale-100"
                                                                    leave-to-class="transform opacity-0 scale-95">
                                                                    <MenuItems
                                                                        class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'Available')">
                                                                            Available
                                                                        </button>
                                                                        </MenuItem>
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button href="#"
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'On Break')">
                                                                            On
                                                                            Break
                                                                        </button>
                                                                        </MenuItem>
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button href="#"
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'Logged Out')">
                                                                            Logged
                                                                            Out
                                                                        </button>
                                                                        </MenuItem>
                                                                    </MenuItems>
                                                                </transition>
                                                            </Menu>
                                                        </td>
                                                        <!-- Last Change Column -->
                                                        <td v-if="columnsVisibility.last_change"
                                                            class="hidden whitespace-nowrap px-2 py-2 text-sm text-gray-500 border-b border-gray-200 sm:table-cell">
                                                            {{ agent.last_status_change ?
                                                                formatDuration(agent.last_status_change) : 'N/A' }}
                                                        </td>
                                                        <!-- Inbound Calls Column -->
                                                        <td v-if="columnsVisibility.inbound_calls"
                                                            class="hidden whitespace-nowrap py-2 px-2 text-sm text-gray-500 border-b border-gray-200 lg:table-cell xl:hidden 2xl:table-cell">
                                                            {{ agent.calls_answered || 0 }}
                                                        </td>
                                                        <!-- Minutes Column -->
                                                        <td v-if="columnsVisibility.minutes"
                                                            class="hidden whitespace-nowrap py-2 px-2 pr-3 text-sm text-gray-500 border-b border-gray-200 lg:table-cell xl:hidden 2xl:table-cell">
                                                            {{ agent.talk_time ? agentDurationToHuman(agent.talk_time) :
                                                                'N/A' }}
                                                        </td>
                                                        <!-- Visibility Icon Column -->
                                                        <td
                                                            class="py-2 px-2 text-right text-sm font-medium border-b border-gray-200">
                                                            <VisibilityOffIcon
                                                                @click="toggleRowVisibility(agent.call_center_agent_uuid)"
                                                                class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Second Half Table -->
                                        <div class="w-full lg:px-2 align-middle">
                                            <table class="min-w-full border-separate border-spacing-0">
                                                <thead>
                                                    <tr>
                                                        <TableColumnHeader
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900">
                                                            <div class="flex items-center justify-start">
                                                                <span class="pl-4">Agent</span>
                                                            </div>
                                                        </TableColumnHeader>

                                                        <!-- Other Table Headers -->
                                                        <TableColumnHeader v-if="columnsVisibility.status"
                                                            header="Status"
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900" />
                                                        <TableColumnHeader v-if="columnsVisibility.last_change"
                                                            header="Last Change"
                                                            class="sticky whitespace-nowrap top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 sm:table-cell" />
                                                        <TableColumnHeader v-if="columnsVisibility.inbound_calls"
                                                            header="Inbound Calls"
                                                            class="sticky whitespace-nowrap top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 lg:table-cell xl:hidden 2xl:table-cell" />
                                                        <TableColumnHeader v-if="columnsVisibility.minutes"
                                                            header="Minutes"
                                                            class="sticky top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 lg:table-cell xl:hidden 2xl:table-cell" />
                                                        <TableColumnHeader header=""
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900" />
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="agent in secondHalfAgents"
                                                        :key="agent.call_center_agent_uuid"
                                                        v-show="agentsRowVisible[agent.call_center_agent_uuid]">
                                                        <td
                                                            class="whitespace-nowrap py-2 px-2 text-sm font-medium text-gray-900 border-b border-gray-200">
                                                            <div class="flex items-center">
                                                                <input v-if="agent.call_center_agent_uuid"
                                                                    v-model="selectedAgents" type="checkbox"
                                                                    name="action_box[]"
                                                                    :value="agent.call_center_agent_uuid"
                                                                    class="ml-1 h-4 w-4 rounded border-gray-300 text-indigo-600">
                                                                <div class="pl-4">{{ agent.agent_name }}</div>
                                                            </div>
                                                        </td>
                                                        <!-- Status Column -->
                                                        <td v-if="columnsVisibility.status"
                                                            class="whitespace-nowrap p2-3 py-2 text-sm text-gray-500 border-b border-gray-200">
                                                            <!-- Status dropdown here -->
                                                            <Menu as="div" class="relative">
                                                                <div>
                                                                    <MenuButton
                                                                        class="relative flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500  focus:ring-offset-1 lg:rounded-md lg:hover:bg-gray-50">
                                                                        <Badge :text="agent.status"
                                                                            :backgroundColor="getAgentBadgeColors(agent.status).background"
                                                                            :textColor="getAgentBadgeColors(agent.status).text"
                                                                            :ringColor="getAgentBadgeColors(agent.status).ring" />
                                                                        <ChevronDownIcon
                                                                            class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                            aria-hidden="true" />
                                                                    </MenuButton>
                                                                </div>
                                                                <transition
                                                                    enter-active-class="transition ease-out duration-100"
                                                                    enter-from-class="transform opacity-0 scale-95"
                                                                    enter-to-class="transform opacity-100 scale-100"
                                                                    leave-active-class="transition ease-in duration-75"
                                                                    leave-from-class="transform opacity-100 scale-100"
                                                                    leave-to-class="transform opacity-0 scale-95">
                                                                    <MenuItems
                                                                        class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'Available')">
                                                                            Available
                                                                        </button>
                                                                        </MenuItem>
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button href="#"
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'On Break')">
                                                                            On
                                                                            Break
                                                                        </button>
                                                                        </MenuItem>
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button href="#"
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'Logged Out')">
                                                                            Logged
                                                                            Out
                                                                        </button>
                                                                        </MenuItem>
                                                                    </MenuItems>
                                                                </transition>
                                                            </Menu>
                                                        </td>
                                                        <!-- Last Change Column -->
                                                        <td v-if="columnsVisibility.last_change"
                                                            class="hidden whitespace-nowrap px-2 py-2 text-sm text-gray-500 border-b border-gray-200 sm:table-cell">
                                                            {{ agent.last_status_change ?
                                                                formatDuration(agent.last_status_change) : 'N/A' }}
                                                        </td>
                                                        <!-- Inbound Calls Column -->
                                                        <td v-if="columnsVisibility.inbound_calls"
                                                            class="hidden whitespace-nowrap py-2 px-2 text-sm text-gray-500 border-b border-gray-200 lg:table-cell xl:hidden 2xl:table-cell">
                                                            {{ agent.calls_answered || 0 }}
                                                        </td>
                                                        <!-- Minutes Column -->
                                                        <td v-if="columnsVisibility.minutes"
                                                            class="hidden whitespace-nowrap py-2 px-2 pr-3 text-sm text-gray-500 border-b border-gray-200 lg:table-cell xl:hidden 2xl:table-cell">
                                                            {{ agent.talk_time ? agentDurationToHuman(agent.talk_time) :
                                                                'N/A' }}
                                                        </td>
                                                        <!-- Visibility Icon Column -->
                                                        <td
                                                            class="py-2 px-2 text-right text-sm font-medium border-b border-gray-200">
                                                            <VisibilityOffIcon
                                                                @click="toggleRowVisibility(agent.call_center_agent_uuid)"
                                                                class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Small Screen Layout -->
                                    <div v-else>
                                        <table class="min-w-full border-separate border-spacing-0">
                                            <thead>
                                                <tr>
                                                    <TableColumnHeader
                                                        class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900">
                                                        <div class="flex items-center justify-start">
                                                            <input type="checkbox" v-model="selectAgents"
                                                                @change="handleSelectAgents"
                                                                class="ml-1 h-4 w-4 rounded border-gray-300 text-indigo-600">
                                                            <span class="pl-4">Agent</span>
                                                        </div>
                                                    </TableColumnHeader>

                                                    <!-- Other Table Headers -->
                                                    <TableColumnHeader v-if="columnsVisibility.status" header="Status"
                                                        class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900" />
                                                    <TableColumnHeader v-if="columnsVisibility.last_change"
                                                        header="Last Change"
                                                        class="sticky whitespace-nowrap top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 sm:table-cell" />
                                                    <TableColumnHeader v-if="columnsVisibility.inbound_calls"
                                                        header="Inbound Calls"
                                                        class="sticky whitespace-nowrap top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 lg:table-cell" />
                                                    <TableColumnHeader v-if="columnsVisibility.minutes" header="Minutes"
                                                        class="sticky top-0 z-10 border-b hidden border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900 lg:table-cell" />
                                                    <TableColumnHeader header=""
                                                        class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-2 text-left text-sm font-semibold text-gray-900" />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="agent in visibleAgents" :key="agent.call_center_agent_uuid"
                                                    v-show="agentsRowVisible[agent.call_center_agent_uuid]">
                                                    <td
                                                        class="whitespace-nowrap py-2 px-2 text-sm font-medium text-gray-900 border-b border-gray-200">
                                                        <div class="flex items-center">
                                                            <input v-if="agent.call_center_agent_uuid"
                                                                v-model="selectedAgents" type="checkbox"
                                                                name="action_box[]"
                                                                :value="agent.call_center_agent_uuid"
                                                                class="ml-1 h-4 w-4 rounded border-gray-300 text-indigo-600">
                                                            <div class="pl-4">{{ agent.agent_name }}</div>
                                                        </div>
                                                    </td>
                                                    <!-- Status Column -->
                                                    <td v-if="columnsVisibility.status"
                                                        class="whitespace-nowrap p2-3 py-2 text-sm text-gray-500 border-b border-gray-200">
                                                        <!-- Status dropdown here -->
                                                        <Menu as="div" class="relative">
                                                                <div>
                                                                    <MenuButton
                                                                        class="relative flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500  focus:ring-offset-1 lg:rounded-md lg:hover:bg-gray-50">
                                                                        <Badge :text="agent.status"
                                                                            :backgroundColor="getAgentBadgeColors(agent.status).background"
                                                                            :textColor="getAgentBadgeColors(agent.status).text"
                                                                            :ringColor="getAgentBadgeColors(agent.status).ring" />
                                                                        <ChevronDownIcon
                                                                            class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                            aria-hidden="true" />
                                                                    </MenuButton>
                                                                </div>
                                                                <transition
                                                                    enter-active-class="transition ease-out duration-100"
                                                                    enter-from-class="transform opacity-0 scale-95"
                                                                    enter-to-class="transform opacity-100 scale-100"
                                                                    leave-active-class="transition ease-in duration-75"
                                                                    leave-from-class="transform opacity-100 scale-100"
                                                                    leave-to-class="transform opacity-0 scale-95">
                                                                    <MenuItems
                                                                        class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'Available')">
                                                                            Available
                                                                        </button>
                                                                        </MenuItem>
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button href="#"
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'On Break')">
                                                                            On
                                                                            Break
                                                                        </button>
                                                                        </MenuItem>
                                                                        <MenuItem v-slot="{ active }">
                                                                        <button href="#"
                                                                            :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
                                                                            @click="handleAgentStatusUpdate(agent, 'Logged Out')">
                                                                            Logged
                                                                            Out
                                                                        </button>
                                                                        </MenuItem>
                                                                    </MenuItems>
                                                                </transition>
                                                            </Menu>
                                                    </td>
                                                    <!-- Last Change Column -->
                                                    <td v-if="columnsVisibility.last_change"
                                                        class="hidden whitespace-nowrap px-2 py-2 text-sm text-gray-500 border-b border-gray-200 sm:table-cell">
                                                        {{ agent.last_status_change ?
                                                        formatDuration(agent.last_status_change) : 'N/A' }}
                                                    </td>
                                                    <!-- Inbound Calls Column -->
                                                    <td v-if="columnsVisibility.inbound_calls"
                                                        class="hidden whitespace-nowrap py-2 px-2 text-sm text-gray-500 border-b border-gray-200 lg:table-cell">
                                                        {{ agent.calls_answered || 0 }}
                                                    </td>
                                                    <!-- Minutes Column -->
                                                    <td v-if="columnsVisibility.minutes"
                                                        class="hidden whitespace-nowrap py-2 px-2 pr-3 text-sm text-gray-500 border-b border-gray-200 lg:table-cell">
                                                        {{ agent.talk_time ? agentDurationToHuman(agent.talk_time) :
                                                        'N/A' }}
                                                    </td>
                                                    <!-- Visibility Icon Column -->
                                                    <td
                                                        class="py-2 px-2 text-right text-sm font-medium border-b border-gray-200">
                                                        <VisibilityOffIcon
                                                            @click="toggleRowVisibility(agent.call_center_agent_uuid)"
                                                            class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>






                            </div>
                        </div>
                    </div>
                </div>

                <div class="mx-auto max-w-2xl  lg:mx-0 lg:max-w-none ">
                    <div
                        class="-mx-4 mt-4 px-4 py-6 shadow-sm bg-gray-50 ring-1 ring-gray-900/5 sm:mx-0 sm:rounded-lg sm:px-8 sm:pb-14 xl:px-12 xl:pb-16 xl:pt-6">

                        <div class="flex flex-1 justify-between">
                            <div class="ml-2 truncate text-md font-bold text-gray-500">Live Calls</div>
                            <div class="truncate text-md font-bold text-green-500">{{ callsInProgress }} In Progress
                            </div>
                            <div class="mr-2 truncate text-md font-bold text-indigo-500">{{ callsQueued }} Queued
                            </div>

                        </div>

                        <div class="my-2 overflow-hidden rounded-lg bg-white shadow">
                            <div class="px-4 py-2 sm:px-6">
                                <div class="overflow-auto h-72">
                                    <div class="-mx-4 -my-2 sm:-mx-6 lg:-mx-8">
                                        <div class="inline-block min-w-full py-2 align-middle">
                                            <table class="min-w-full border-separate border-spacing-0">
                                                <thead>
                                                    <tr>
                                                        <!-- <th scope="col"
                                                        class="sticky top-0 z-10 border-b border-gray-300 bg-white bg-opacity-75 py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                                        Customer</th> -->
                                                        <!-- <th scope="col"
                                                        class="sticky top-0 z-10 hidden border-b border-gray-300 bg-white bg-opacity-75 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:table-cell">
                                                        Joined</th> -->
                                                        <!-- <th scope="col"
                                                        class="sticky top-0 z-10 hidden border-b border-gray-300 bg-white bg-opacity-75 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter lg:table-cell">
                                                        Status</th> -->
                                                        <!-- <th scope="col"
                                                        class="sticky top-0 z-10 border-b border-gray-300 bg-white bg-opacity-75 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter">
                                                        Agent</th> -->
                                                        <TableColumnHeader @click="handleSortLiveCall('cid_number')"
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-3.5 text-left text-sm font-semibold text-gray-900 sm:pl-6 lg:pl-8">
                                                            <div class="flex">
                                                                Customer
                                                                <span v-if="liveCallSorting.sortKey === 'cid_number'">
                                                                    <ChevronUpIcon v-if="liveCallSorting.isAsc"
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                    <ChevronDownIcon v-else
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                </span>
                                                            </div>
                                                        </TableColumnHeader>
                                                        <TableColumnHeader @click="handleSortLiveCall('joined_epoch')"
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                                                            <div class="flex">
                                                                Joined
                                                                <span v-if="liveCallSorting.sortKey === 'joined_epoch'">
                                                                    <ChevronUpIcon v-if="liveCallSorting.isAsc"
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                    <ChevronDownIcon v-else
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                </span>
                                                            </div>
                                                        </TableColumnHeader>
                                                        <TableColumnHeader @click="handleSortLiveCall('state')"
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white backdrop-blur backdrop-filter px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                                                            <div class="flex">
                                                                Status
                                                                <span v-if="liveCallSorting.sortKey === 'state'">
                                                                    <ChevronUpIcon v-if="liveCallSorting.isAsc"
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                    <ChevronDownIcon v-else
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                </span>
                                                            </div>
                                                        </TableColumnHeader>
                                                        <TableColumnHeader
                                                            @click="handleSortLiveCall('serving_agent_name')"
                                                            class="sticky top-0 z-10 border-b border-gray-300 bg-white bg-opacity-75 backdrop-blur backdrop-filter px-2 py-3.5 text-left text-sm font-semibold text-gray-900 sm:pr-6 lg:pr-8">
                                                            <div class="flex">
                                                                Agent
                                                                <span
                                                                    v-if="liveCallSorting.sortKey === 'serving_agent_name'">
                                                                    <ChevronUpIcon v-if="liveCallSorting.isAsc"
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                    <ChevronDownIcon v-else
                                                                        class="ml-1 hidden h-5 w-5 flex-shrink-0 text-gray-400 lg:block"
                                                                        aria-hidden="true" />
                                                                </span>
                                                            </div>
                                                        </TableColumnHeader>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="call in liveCalls" :key="call.uuid">
                                                        <td
                                                            class="whitespace-nowrap px-3 py-2 text-sm text-gray-500 border-b border-gray-200 sm:table-cell sm:pl-6 lg:pl-8">
                                                            {{ call.cid_number }}
                                                        </td>
                                                        <td
                                                            class=" whitespace-nowrap px-3 py-2 text-sm text-gray-500 border-b border-gray-200 sm:table-cell">
                                                            {{ callFormatDuration(call.joined_epoch) }} ago
                                                        </td>
                                                        <td
                                                            class=" whitespace-nowrap px-3 py-1 text-sm text-gray-500 border-b border-gray-200 sm:table-cell">
                                                            <Badge :text="call.state"
                                                                :backgroundColor="getCallBadgeColors(call.state).background"
                                                                :textColor="getCallBadgeColors(call.state).text"
                                                                :ringColor="getCallBadgeColors(call.state).ring" />

                                                        </td>
                                                        <td
                                                            class=" whitespace-nowrap px-3 py-2 text-sm text-gray-500 border-b border-gray-200 sm:table-cell sm:pr-8 lg:pr-8">
                                                            {{ call.serving_agent_name }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

    </MainLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3'
import MainLayout from '@layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import ComboBox from "@generalComponents/ComboBox.vue";
import DatePicker from "@generalComponents/DatePicker.vue";
import TableColumnHeader from "@generalComponents/TableColumnHeader.vue";
import Badge from "@generalComponents/Badge.vue";
import VisibilityOffIcon from "@icons/VisibilityOffIcon.vue";
import moment from 'moment-timezone';

import { Menu, MenuButton, MenuItem, MenuItems, } from '@headlessui/vue'
import { ChevronDownIcon, ChevronUpIcon, CheckIcon } from '@heroicons/vue/20/solid'

import { Bar, Line } from 'vue-chartjs'
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip
} from 'chart.js'
import { Logger } from 'sass';


ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement)


// Define the structure of the props
const props = defineProps({
    cards: Object,
    queueOptions: Object,
    agentOptions: Object,
    timezone: String,
    routes: Object,
    agents: Array,
    calls: Array,
    totalProgress: Number,
    totalQueued: Number,
    availableAgentCount: Number,

});

const filterData = ref({
    queues: null,
    agents: null,
    dateRange: [moment.tz(moment(), props.timezone).startOf('day').format(), moment.tz(moment(), props.timezone).endOf('day').format()],
});

const selectedQueues = ref([]);
const stats = ref({});
const queueStatus = ref({});
const multipleQueuesSelected = ref(false);
const selectAgents = ref(false);
const selectedAgents = ref([]);
// const mergedAgents = ref([]);
const callsInProgress = ref(0);
const callsQueued = ref(0);
const firstHalfAgents = ref([]);
const secondHalfAgents = ref([]);
const agentsRowVisible = ref({});

const columns = ['status', 'last_change', 'inbound_calls', 'minutes'];
const columnsVisibility = ref(Object.fromEntries(columns.map(column => [column, true])));
const handleShowColumn = (column) => {
    columnsVisibility.value[column] = !columnsVisibility.value[column];
}

const liveCallSorting = ref({
    sortKey: 'joined_epoch',
    isAsc: false,
})

const handleSortLiveCall = (colName) => {
    if (liveCallSorting.value.sortKey === colName) {
        liveCallSorting.value.isAsc = !liveCallSorting.value.isAsc;
    } else {
        liveCallSorting.value.sortKey = colName;
        liveCallSorting.value.isAsc = true;
    }
}

const handleSelectedQueuesUpdate = (updatedQueues) => {
    filterData.value.queues = updatedQueues;
    multipleQueuesSelected.value = updatedQueues.length > 1;
    refreshStatistics();
    refreshAgentOptions();
};

const handleSelectedAgentsUpdate = (updatedAgents) => {
    filterData.value.agents = updatedAgents;
    refreshStatistics();
};

const handleShowAllAgents = () => {
    Object.keys(agentsRowVisible.value).forEach(agentUuid => {
        agentsRowVisible.value[agentUuid] = true;
    });
};

const handleShowAvailableAgents = () => {
    // Reset all to invisible
    Object.keys(agentsRowVisible.value).forEach(agentUuid => {
        agentsRowVisible.value[agentUuid] = false;
    });

    // Set only available agents to visible
    mergedAgents.value.filter(agent => agent.status !== 'Logged Out' && agent.status !== 'On Break').forEach(agent => {
        agentsRowVisible.value[agent.call_center_agent_uuid] = true;
    });
};


// const handleDateRangeUpdate = (dateRange) => {
//     filterData.value.dateRange = dateRange;
//     router.visit('/contact-center', {
//         data: {
//             filterData: filterData._rawValue,
//         },
//     })
// };

const handleHideSelectedAgents = () => {
    selectedAgents.value.forEach(agentUuid => {
        if (agentsRowVisible.value[agentUuid] !== undefined) {
            agentsRowVisible.value[agentUuid] = false;
        }
    });
};


const handleAgentStatusUpdate = (agent, status) => {
    axios.post(props.routes.agent_status_update,
        {
            agentUuid: agent.call_center_agent_uuid,
            status: status,
        },
    )
        .then((response) => {
            // console.log(response.data);
            // Update the status of the agent in queueStatus
            updateQueueStatusAgents(agent.call_center_agent_uuid, status);

        }).catch((error) => {
            console.log(error);
        });
};

const updateQueueStatusAgents = (agentUuid, status) => {
    const agentIndex = queueStatus.value.agents.findIndex(agent => agent.name === agentUuid);
    if (agentIndex !== -1) {
        queueStatus.value.agents[agentIndex].status = status;
    }
};


// Define a ref to store the interval ID
const intervalForAgentsAndCalls = ref(null);
const intervalForStatistics = ref(null);

// Method to refresh the component
const refreshAgentsAndCalls = () => {
    axios.post(props.routes.queue_info_refresh, filterData._rawValue)
        .then((response) => {
            // console.log(response.data);
            queueStatus.value = response.data;

            // Reset callsInProgress and callsQueued to 0 if calls are not present
            callsInProgress.value = 0;
            callsQueued.value = 0;

            if (response.data.calls && response.data.calls.length > 0) {
                callsInProgress.value = response.data.calls.filter(call => call.bridge_epoch !== '0').length;
                callsQueued.value = response.data.calls.filter(call => call.bridge_epoch === '0').length;
            }

            // Update the stats variable
            if (stats.value && stats.value.agents_to_callers_ratio) {
                stats.value.agents_to_callers_ratio.key_metric = agentsToCallersRatio.value;
                stats.value.agents_to_callers_ratio.details = [`${callsQueued.value} Queue length`];
            }

        }).catch((error) => {
            console.log(error);
        });
};

// Method to refresh statistics data
const refreshStatistics = () => {
    axios.post(props.routes.stats_refresh, filterData._rawValue)
        .then((response) => {
            // console.log(response.data);
            stats.value = response.data;

            // Update the stats variable
            if (stats.value && stats.value.agents_to_callers_ratio) {
                stats.value.agents_to_callers_ratio.key_metric = agentsToCallersRatio.value;
                stats.value.agents_to_callers_ratio.details = [`${callsQueued.value} Queue length`];
            }

        }).catch((error) => {
            console.log(error);
        });
};

// Method to refresh the agent options
const refreshAgentOptions = () => {
    router.post(props.routes.current_page,
        filterData._rawValue,
        {
            preserveScroll: true,
            preserveState: true,
            only: [
                'agentOptions',
                'agents',
            ],

            onSuccess: (page) => {
                // console.log(props.agents);
                props.agents.forEach(agent => {
                    agentsRowVisible.value[agent.call_center_agent_uuid] = true;
                });
            }
        }
    );

};

onMounted(() => {
    // console.log(props.agents);
    if (props.queueOptions) {
        filterData.value.queues = props.queueOptions[0];
        selectedQueues.value = [props.queueOptions[0]];
        handleSelectedQueuesUpdate([props.queueOptions[0]]);
        refreshAgentsAndCalls();
    }

    // Initialize the visibility status
    props.agents.forEach(agent => {
        if (agentsRowVisible.value[agent.call_center_agent_uuid] === undefined) {
            agentsRowVisible.value[agent.call_center_agent_uuid] = true;
        }
    });

    // refreshStatistics();
    // Set up the interval to refresh the components
    intervalForAgentsAndCalls.value = setInterval(refreshAgentsAndCalls, 15000);
    intervalForStatistics.value = setInterval(refreshStatistics, 90000);

    handleResize();  // Initial check
    window.addEventListener('resize', handleResize);  // Listen for window resizing
});

onBeforeUnmount(() => {
    // Clear the interval when the component is unmounted
    if (intervalForAgentsAndCalls.value) {
        clearInterval(intervalForAgentsAndCalls.value);
    }

    if (intervalForStatistics.value) {
        clearInterval(intervalForStatistics.value);
    }
});

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
    refreshStatistics();
}


// This function converts 24-hour format to 12-hour format
const usTimeFormat = hour => {
    if (hour == 0 || hour == 12) {
        return '12 AM';
    } else if (hour < 12) {
        return `${hour} AM`;
    } else {
        return `${hour - 12} PM`;
    }
};

const callVolumeChartOptions = ref(
    {
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Hour of the Day'
                },
            },
            y: {
                ticks: {
                    callback: (yValue) => {
                        if (Number.isInteger(yValue)) {
                            return yValue; // Return the integer value as-is
                        } else {
                            return null; // Return null if yValue is not an integer
                        }
                    },
                },
                beginAtZero: true
            },
        },
        responsive: true,
        maintainAspectRatio: false,

    }
)

const callAvgCallDurationChartOptions = ref(
    {
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Hour of the Day'
                },
            },
            y: {
                ticks: {
                    callback: (yValue) => {
                        const minutes = Math.floor(yValue / 60);
                        const seconds = yValue % 60;

                        // Format the value as "mm'm' ss's'"
                        return `${minutes < 10 ? '0' : ''}${minutes}m ${seconds < 10 ? '0' : ''}${seconds}s`;
                    },
                },
                beginAtZero: true
            }
        },
        responsive: true,
        maintainAspectRatio: false,
    }
)

const handleSelectAgents = () => {
    if (selectAgents.value) {
        selectedAgents.value = props.agents.map(item => item.call_center_agent_uuid);
    } else {
        selectedAgents.value = [];
    }
};

const agentDurationToHuman = (seconds) => {
    const days = Math.floor(seconds / (3600 * 24));
    const hours = Math.floor((seconds % (3600 * 24)) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return (days > 0 ? days + 'd ' : '') + (hours > 0 ? hours + 'h ' : '') + (minutes > 0 ? minutes + 'm' : '');
};

const callFormatDuration = (timestamp) => {
    if (timestamp === '0') {
        return 'N/A';
    }
    const now = moment();
    const duration = moment.duration(now.diff(moment.unix(timestamp)));
    const hours = Math.floor(duration.asHours());
    const minutes = duration.minutes();
    const seconds = duration.seconds();

    let result = '';
    if (hours > 0) {
        result += `${hours}h `;
    }
    if (minutes > 0 || hours > 0) { // include minutes if there are hours or if minutes are more than 0
        result += `${minutes}m `;
    }
    result += `${seconds}s`;

    return result.trim();
};

// Function to format duration to '15h 52m'
const formatDuration = (timestamp) => {
    if (timestamp === '0') {
        return '';
    }
    const now = moment();
    const duration = moment.duration(now.diff(moment.unix(timestamp)));
    const hours = Math.floor(duration.asHours());
    const minutes = duration.minutes();
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
};

const mergedAgents = computed(() => {
    if (!queueStatus.value.agents || !props.agents) return [];
    const merged = props.agents.map(agent => {
        const agentData = queueStatus.value.agents.find(a => a.name === agent.call_center_agent_uuid) || {};
        return { ...agent, ...agentData };
    });
    // console.log("Merged Agents:", merged);  // Log merged agents
    return merged;
});

const normalizeCalls = (raw) => {
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw); // <- handles {"5": {...}}
    return [];
};

const liveCalls = computed(() => {
    const calls = normalizeCalls(queueStatus.value?.calls);

    if (!calls.length) return [];

    if (liveCallSorting.value.sortKey) {
        const key = liveCallSorting.value.sortKey;

        calls.sort((a, b) => {
            const valA = a?.[key];
            const valB = b?.[key];

            // Your FS data is mostly strings ("1768..."), so sort numerically when possible
            const numA = Number(valA);
            const numB = Number(valB);
            const bothNumeric = Number.isFinite(numA) && Number.isFinite(numB);

            let comparison = bothNumeric
                ? (numB - numA)
                : String(valA ?? '').localeCompare(String(valB ?? ''));

            return liveCallSorting.value.isAsc ? comparison : -comparison;
        });
    }

    return calls;
});

// filtered array with visible agents
const visibleAgents = computed(() => {
    // Ensure that both mergedAgents and agentsRowVisible are reactive dependencies
    const merged = mergedAgents.value;
    const visibility = agentsRowVisible.value;

    // Apply the filter based on visibility
    return merged.filter(agent => visibility[agent.call_center_agent_uuid]);
});

// recompute the two halves whenever the visible agents change
watch(visibleAgents, (newValue) => {
    // console.log(newValue);
    const half = Math.ceil(newValue.length / 2);
    firstHalfAgents.value = newValue.slice(0, half);
    secondHalfAgents.value = newValue.slice(half);
});

const toggleRowVisibility = (agentUuid) => {
    agentsRowVisible.value[agentUuid] = !agentsRowVisible.value[agentUuid]
}

const getCallBadgeColors = (state) => {
    switch (state) {
        case 'Trying':
            return {
                background: 'bg-indigo-50',
                text: 'text-indigo-700',
                ring: 'ring-indigo-600/20'
            };
        case 'Waiting':
            return {
                background: 'bg-amber-50',
                text: 'text-amber-700',
                ring: 'ring-amber-600/20'
            };
        case 'Answered':
            return {
                background: 'bg-green-50',
                text: 'text-green-700',
                ring: 'ring-green-600/20'
            };
        default:
            return {
                background: 'bg-gray-50',
                text: 'text-gray-700',
                ring: 'ring-gray-600/20'
            };
    }
};

const getAgentBadgeColors = (status) => {
    switch (status) {
        case 'Available':
            return {
                background: 'bg-green-50',
                text: 'text-green-700',
                ring: 'ring-green-600/20'
            };
        case 'Logged Out':
            return {
                background: 'bg-gray-50',
                text: 'text-gray-700',
                ring: 'ring-gray-600/20'
            };
        case 'On Break':
            return {
                background: 'bg-amber-50',
                text: 'text-amber-700',
                ring: 'ring-amber-600/20'
            };
        case 'Receiving':
            return {
                background: 'bg-blue-50',
                text: 'text-blue-700',
                ring: 'ring-blue-600/20'
            };
        case 'On a Call':
            return {
                background: 'bg-rose-50',
                text: 'text-rose-700',
                ring: 'ring-rose-600/20'
            };
        default:
            return {
                background: 'bg-gray-50',
                text: 'text-gray-700',
                ring: 'ring-gray-600/20'
            };
    }
};

const availableAgents = computed(() => mergedAgents.value.filter(agent => agent.status !== 'Logged Out' && agent.status !== 'On Break').length);
const agentsToCallersRatio = computed(() => `${availableAgents.value}:${callsInProgress.value}`);


// Responsive state
const isExtraLarge = ref(false);

const handleResize = () => {
    isExtraLarge.value = window.innerWidth >= 1280;  // Extra-large screens start from 1280px
};

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);  // Cleanup listener
});

</script>
