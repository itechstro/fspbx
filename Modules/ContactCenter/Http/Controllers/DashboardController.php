<?php

namespace Modules\ContactCenter\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use App\Models\Recordings;
use App\Models\FusionCache;
use App\Models\MusicOnHold;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\CallCenterAgents;
use App\Models\CallCenterQueues;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\CallCenterQueueAgents;
use Illuminate\Support\Facades\Cache;
use App\Services\ContactCenterQueueService;
use App\Services\FreeswitchEslService;
use App\Services\CallRoutingOptionsService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Modules\ContactCenter\Http\Requests\UpdateSettingsRequest;

class DashboardController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Dashboard::ContactCenter';
    protected $searchable = ['source', 'destination', 'message'];

    protected $isMultipleQueues = false;

    public $inbound = 0;
    public $outbound = 0;
    public $handledCalls = 0;
    public $startPeriod;
    public $endPeriod;
    public $selectedQueues;
    public $selectedAgents;
    public $contactCenterAgents;

    public $calls;
    public $totalProgress = 0;
    public $totalQueued = 0;
    public $availableAgentCount = 0;
    public $contactCenters;

    public function __construct()
    {
        if (!empty(request('dateRange'))) {
            $this->startPeriod = Carbon::parse(request('dateRange')[0])->setTimeZone('UTC');
            $this->endPeriod = Carbon::parse(request('dateRange')[1])->setTimeZone('UTC');
        } else {
            $this->startPeriod = Carbon::now($this->getTimezone())->startOfDay()->setTimeZone('UTC');
            $this->endPeriod = Carbon::now($this->getTimezone())->endOfDay()->setTimeZone('UTC');
        }

        if (!empty(request('queues'))) {
            $this->selectedQueues = array_map(fn($queue) => $queue['value'], request('queues'));
            if (count($this->selectedQueues) > 1) {
                $this->isMultipleQueues = true;
            }
        }

        if (!empty(request('agents'))) {
            $this->selectedAgents = array_map(fn($queue) => $queue['value'], request('agents'));
        }
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->to('/login');
        }

        // Check permissions
        if (!userCheckPermission("contact_center_dashboard_view")) {
            return redirect('/');
        }

        if (empty($request->input()) && empty($request->query())) {
            Cache::forget(auth()->user()->user_uuid . '_inboundCdrs');
            Cache::forget(auth()->user()->user_uuid . '_outboundCdrs');
            Cache::forget(auth()->user()->user_uuid . '_missedCdrs');
            Cache::forget(auth()->user()->user_uuid . '_voicemailCdrs');
        }

        return Inertia::render(
            $this->viewName,
            [
                'cards' => function () {
                    return $this->getCards();
                },
                'queueOptions' => function () {
                    return $this->getContactCenterOptions();
                },
                'agentOptions' => function () {
                    return $this->getAgentOptions();
                },
                'timezone' => function () {
                    return $this->getTimezone();
                },
                'agents' => function () {
                    return $this->getContactCenterAgents();
                },

                'routes' => [
                    'current_page' => route('contactcenter.index'),
                    'stats_refresh' => route('contactcenter.stats.refresh'),
                    'queue_info_refresh' => route('contactcenter.info.refresh'),
                    'agent_status_update' => route('agent.status.update'),

                ]
            ]
        );
    }


    protected function getContactCenterOptions()
    {
        $contactCenters = $this->getAllContactCenters();

        if ($contactCenters->isEmpty()) {
            return null;
        }

        $queueOptions = $contactCenters->map(function ($item) {
            return [
                'value' => $item['call_center_queue_uuid'],
                'name' => $item['queue_name'],
            ];
        });

        return $queueOptions;
    }

    protected function getAgentOptions()
    {
        try {
            if ($this->isMultipleQueues) {
                return null;
            }

            $contactCenterAgents = $this->getContactCenterAgents();

            $agentOptions = $contactCenterAgents->map(function ($item) {
                return [
                    'value' => $item['call_center_agent_uuid'],
                    'name' => $item['agent_name'],
                ];
            });
            // logger($agentOptions);

            return $agentOptions;
        } catch (\Exception $e) {
            // Handle the exception
            logger("Error: " . $e->getMessage());
            // Log the file where the error occurred
            logger("File: " . $e->getFile() . ":" . $e->getLine());
            return null;
        }
    }

    public function getFsAgents()
    {
        try {

            $contactCenters = $this->getAllContactCenters();

            if (!$this->selectedQueues) {
                // Default to this logic if 'queues' is not provided in the request
                $firstQueue = $contactCenters->first();
                // Check if the first element exists and then create an array with 'call_center_queue_uuid'
                $this->selectedQueues = $firstQueue ? [$firstQueue->call_center_queue_uuid] : [];
            }

            // Get current selected queue details
            $queue = $contactCenters->where('call_center_queue_uuid', current($this->selectedQueues))->first();

            if (!$queue) {
                throw new \Exception('Queue is empty');
            }

            $freeSwitchService = new FreeswitchEslService();
            $command = sprintf(
                'callcenter_config queue list agents %s@%s',
                $queue->queue_extension,
                session('domain_name')
            );

            $fs_agents = $freeSwitchService->executeCommand($command);

            // FreeswitchEslService can return:
            // - array (parsed rows) -> good
            // - string "+OK ..."    -> treat as "no data" (normal)
            // - string "-ERR ..."   -> log as warning and return empty
            if (empty($fs_agents)) {
                return [];
            }

            if (is_string($fs_agents)) {
                // Only log actual errors; +OK is not an error.
                if (str_starts_with($fs_agents, '-ERR')) {
                    logger('ContactCenter:DashboardController@getFsAgents ESL error: ' . $fs_agents);
                }
                return [];
            }

            $agents = array_map(function ($agent) {
                $state  = $agent['state'] ?? '';
                $status = $agent['status'] ?? '';

                if ($state === 'Receiving') {
                    $status = 'Receiving';
                } elseif ($state === 'In a queue call') {
                    $status = 'On a Call';
                }

                return [
                    'name' => $agent['name'] ?? '',
                    'status' => $status,
                    'state' => $state,
                    'last_status_change' => $agent['last_status_change'] ?? null,
                    'talk_time' => $agent['talk_time'] ?? null,
                    'calls_answered' => $agent['calls_answered'] ?? null,
                ];
            }, $fs_agents);

            // logger($agents);

            return $agents;
        } catch (\Exception $e) {
            // Handle the exception
            logger('ContactCenter:DashboardController@getFsAgents error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return [];

            // throw new \Exception('Unable to retrieve Freeswitch agents');
        }
    }

    public function getLiveCalls()
    {
        try {

            if ($this->isMultipleQueues) {
                return null;
            }

            $contactCenters = $this->getAllContactCenters();

            if (!$this->selectedQueues) {
                // Default to this logic if 'queues' is not provided in the request
                $firstQueue = $contactCenters->first();
                // Check if the first element exists and then create an array with 'call_center_queue_uuid'
                $this->selectedQueues = $firstQueue ? [$firstQueue->call_center_queue_uuid] : [];
            }

            // Get current selected queue details
            $queue = $contactCenters->where('call_center_queue_uuid', current($this->selectedQueues))->first();

            if (!$queue) {
                throw new \Exception('Queue is empty');
            }

            $freeSwitchService = new FreeswitchEslService();
            $command = sprintf(
                'callcenter_config queue list members %s@%s',
                $queue->queue_extension,
                session('domain_name')
            );

            // Get a list of current active calls
            $active_calls = $freeSwitchService->executeCommand($command);

            // FreeswitchEslService can return string "+OK ..." / "-ERR ...".
            if (empty($active_calls) || !is_array($active_calls)) {
                return null;
            }

            //Unset some values
            foreach ($active_calls as $key => $call) {
                unset($active_calls[$key]['queue']);
                unset($active_calls[$key]['instance_id']);
                unset($active_calls[$key]['session_uuid']);
                unset($active_calls[$key]['rejoined_epoch']);
                unset($active_calls[$key]['abandoned_epoch']);
                unset($active_calls[$key]['base_score']);
                unset($active_calls[$key]['skill_score']);
                unset($active_calls[$key]['serving_system']);
                unset($active_calls[$key]['score']);

                // Check if call state is 'Abandoned' and remove it from the array if true
                if (($active_calls[$key]['state'] ?? null) === 'Abandoned') {
                    unset($active_calls[$key]);
                }
            }
            $agents = $this->getContactCenterAgents();

            // // Initializing the variables
            // $this->totalProgress = 0;
            // $this->totalQueued = 0;

            // logger($this->calls);
            // Loop through the array
            foreach ($active_calls as &$call) {
                // $call['joined_epoch'] = time() - $call['joined_epoch'];

                // // Check the 'state' field for each element
                // switch ($call['state']) {
                //     case 'Trying':
                //     case 'Waiting':
                //         // If state is 'Trying', increment the $this->totalQueued variable
                //         // or
                //         // If state is 'Trying', increment the $this->totalQueued variable
                //         $this->totalQueued++;
                //         break;
                //     case 'Answered':
                //         // If state is 'Answered', increment the $this->totalProgress variable
                //         $this->totalProgress++;
                //         break;
                //         // Add more cases for other possible states if needed
                // }

                $servingAgentUuid = $call['serving_agent'];

                // Find the corresponding agent in the $this->agents array
                $matchingAgent = collect($agents)
                    ->first(function ($agent) use ($servingAgentUuid) {
                        return $agent['call_center_agent_uuid'] === $servingAgentUuid;
                    });

                // If a matching agent is found, assign the agent's name to the call
                if ($matchingAgent) {
                    $call['serving_agent_name'] = $matchingAgent['agent_name'];
                } else {
                    $call['serving_agent_name'] = '';
                }
            }

            return ($active_calls);
        } catch (\Exception $e) {
            // Handle the exception
            logger("Error: " . $e->getMessage());
            // Log the file where the error occurred
            logger("File: " . $e->getFile() . ":" . $e->getLine());

            return [];
            throw new \Exception('Unable to retrieve live calls');
        }
    }


    public function getContactCenterAgents()
    {
        // logger('get agents');
        try {

            $contactCenters = $this->getAllContactCenters();

            if (!$this->selectedQueues) {
                // Default to this logic if 'queues' is not provided in the request
                $firstQueue = $contactCenters->first();
                // Check if the first element exists and then create an array with 'call_center_queue_uuid'
                $this->selectedQueues = $firstQueue ? [$firstQueue->call_center_queue_uuid] : [];
            }

            // Get current selected queue details
            $queue = $contactCenters->where('call_center_queue_uuid', current($this->selectedQueues))->first();

            if (!$queue) {
                throw new \Exception('Queue is empty');
            }

            if (!Cache::has($queue->call_center_queue_uuid . '_contactCenterAgents')) {
                $queueAgents = CallCenterQueueAgents::where('call_center_queue_uuid', $queue->call_center_queue_uuid)
                    ->with(['agent' => function ($query) {
                        $query->select('call_center_agent_uuid', 'agent_id', 'agent_name', 'agent_contact');
                    }, 'agent.extension' => function ($query) {
                        $query->select('extension_uuid', 'extension');
                    }])
                    ->get([
                        'call_center_tier_uuid',
                        'call_center_agent_uuid',
                    ]);

                $agents = $queueAgents->map(function ($item) {
                    return [
                        'call_center_agent_uuid' => $item->call_center_agent_uuid,
                        'agent_name' => $item->agent->agent_name,
                        'agent_contact' => $item->agent->agent_contact,
                        'extension_uuid' => $item->agent->extension->extension_uuid ?? null,
                    ];
                });

                // Store in cache
                Cache::put($queue->call_center_queue_uuid . '_contactCenterAgents', $agents, 600);
            } else {
                $agents = Cache::get($queue->call_center_queue_uuid . '_contactCenterAgents');
            }
            // logger($agents);
            return $agents;
        } catch (\Exception $e) {
            // Handle the exception
            logger("Error: " . $e->getMessage());
            // Log the file where the error occurred
            logger("File: " . $e->getFile() . ":" . $e->getLine());

            return collect([]);
            // throw new \Exception('Unable to retrieve Contact Center agents');
        }
    }

    protected function getStatsData()
    {
        try {
            if (!$this->selectedQueues) {
                throw new \Exception('Queues array is empty');
            }

            // write all inbound calls for the current filfer to cache
            $this->getInboundCalls();

            // write all outbound calls for the current filfer to cache
            if (!$this->isMultipleQueues) {
                $this->getOutboundCalls();
            }

            // Retrive the cache and start populating data
            $data = [];
            $inboundCount = $outboundCount = $inboundMinutes = $outboundMinutes = $missedCount = $shortMissed = $voicemailCount =
                $answeredWithin60SecondsCount = $missedCallRate = $totalMissedSpeed = $shortestMissedSpeed = $longestMissedSpeed = $averageMissedSpeed =
                $serviceLevel = $totalCalls = $shortestAnswerSpeed = $totalAnswerSpeed = $longestAnswerSpeed = $averageAnswerSpeed = 0;
            $userUuid = auth()->user()->user_uuid;

            // Handle inbound CDRs
            $inboundCdrs = Cache::get("{$userUuid}_inboundCdrs", null);
            if ($inboundCdrs !== null) {
                $inboundCount = $inboundCdrs->count();
                $inboundDuration = $inboundCdrs->sum('duration');
                $inboundMinutes = ceil($inboundDuration / 60);

                $answeredWithin60SecondsCount = $inboundCdrs->filter(function ($cdr) {
                    $answerTime = $cdr->cc_queue_answered_epoch - $cdr->cc_queue_joined_epoch;
                    return $answerTime <= 60;
                })->count();

                if ($inboundCount > 0) {
                    $shortestAnswerSpeed = PHP_INT_MAX;

                    foreach ($inboundCdrs as $cdr) {
                        $answerSpeed = $cdr->cc_queue_answered_epoch - $cdr->cc_queue_joined_epoch;
                        $totalAnswerSpeed += $answerSpeed;
                        $shortestAnswerSpeed = min($shortestAnswerSpeed, $answerSpeed);
                        $longestAnswerSpeed = max($longestAnswerSpeed, $answerSpeed);
                    }

                    $averageAnswerSpeed = $inboundCount > 0 ? $totalAnswerSpeed / $inboundCount : 0;

                    // Service level shows the percentage of calls answered is 60 seconds. Short Abandoned Calls are excluded
                    $serviceLevel = round($answeredWithin60SecondsCount / $inboundCount, 2) * 100;

                    // $totalCallsExcludingShortMissed = $totalCalls - $shortMissed;
                    // if ($totalCallsExcludingShortMissed > 0) {
                    //     $serviceLevel = round($answeredWithin60SecondsCount / $totalCallsExcludingShortMissed, 2) * 100;
                    // }
                }
            }

            // Handle outbound CDRs
            $outboundCdrs = Cache::get("{$userUuid}_outboundCdrs", null);
            if ($outboundCdrs !== null) {
                $outboundCount = $outboundCdrs->count();
                $outboundDuration = $outboundCdrs->sum('duration');
                $outboundMinutes = ceil($outboundDuration / 60);
            }


            $data['handled_calls']['key_metric'] = $inboundCount + $outboundCount;
            $data['handled_calls']['details'] = [
                vsprintf('%d Inbound (answered)', [$inboundCount]),
                vsprintf('%d Outbound (connected)', [$outboundCount])
            ];
            $data['total_minutes']['key_metric'] = $inboundMinutes + $outboundMinutes;
            $data['total_minutes']['details'] = [
                vsprintf('%d Inbound (answered)', [$inboundMinutes]),
                vsprintf('%d Outbound (connected)', [$outboundMinutes]),
            ];
            $data['speed_to_answer']['key_metric'] =  vsprintf('%s Avg', [$this->formatDuration($averageAnswerSpeed)]);
            $data['speed_to_answer']['details'] = [
                vsprintf('%s Shortest', [$this->formatDuration($shortestAnswerSpeed)]),
                vsprintf('%s Average', [$this->formatDuration($averageAnswerSpeed)]),
                vsprintf('%s Longest', [$this->formatDuration($longestAnswerSpeed)])
            ];

            unset($outboundCdrs);
            unset($inboundCdrs);

            // write all inbound calls for the current filfer to cache
            $this->getMissedCalls();

            // Handle missed CDRs
            $missedCdrs = Cache::get("{$userUuid}_missedCdrs", null);
            if ($missedCdrs !== null) {
                $missedCount = $missedCdrs->count();
                $shortMissedCdrs = $missedCdrs->filter(function ($cdr) {
                    return $cdr->duration < 10;
                });
                $shortMissed = $shortMissedCdrs->count();

                // $totalHandledCount = $inboundCount + $outboundCount;

                $totalCalls = $inboundCount + $missedCount;

                if ($totalCalls > 0) {
                    $missedCallRate = round($missedCount / $totalCalls, 2) * 100;
                }

                if ($missedCount > 0) {
                    $shortestMissedSpeed = PHP_INT_MAX;

                    foreach ($missedCdrs as $cdr) {
                        $missedSpeed = $cdr->cc_queue_canceled_epoch - $cdr->cc_queue_joined_epoch;
                        $totalMissedSpeed += $missedSpeed;
                        $shortestMissedSpeed = min($shortestMissedSpeed, $missedSpeed);
                        $longestMissedSpeed = max($longestMissedSpeed, $missedSpeed);
                    }

                    $averageMissedSpeed = $missedCount > 0 ? $totalMissedSpeed / $missedCount : 0;
                }
            }

            unset($missedCdrs);

            // Handle voicemail CDRs
            $voicemailCdrs = Cache::get("{$userUuid}_voicemailCdrs", null);
            if ($voicemailCdrs !== null) {
                $voicemailCount = $voicemailCdrs->count();
            }

            $data['missed_calls']['key_metric'] = $missedCount;
            $data['missed_calls']['details'] = [
                vsprintf('%d Missed Calls', [$missedCount - $shortMissed - $voicemailCount]),
                vsprintf('%d Short Missed', [$shortMissed]),
                vsprintf('%d Voicemails', [$voicemailCount])
            ];

            $data['missed_call_rate']['key_metric'] = $missedCallRate . "%";
            $data['missed_call_rate']['details'] = [
                vsprintf('%s Shortest', [$this->formatDuration($shortestMissedSpeed)]),
                vsprintf('%s Average', [$this->formatDuration($averageMissedSpeed)]),
                vsprintf('%s Longest', [$this->formatDuration($longestMissedSpeed)])
            ];

            $data['service_level']['key_metric'] = $serviceLevel . "%";
            $data['service_level']['details'] = ['% of calls answered within 60s of entering the queue'];

            unset($voicemailCdrs);

            $data['agents_to_callers_ratio']['key_metric'] = "0:0";
            $data['agents_to_callers_ratio']['details'] = [
                'Queue length',
            ];

            $data['callVolumeChartData'] = $this->getCallVolumeChartData();
            $data['callDurationChartData'] = $this->getAvgCallDurationChartData();

            // $this->handledCalls =  $this->inbound + $this->outbound;

            // logger($data);
            return $data;
        } catch (\Exception $e) {
            // Handle the exception
            logger("Error: " . $e->getMessage());
            // Log the file where the error occurred
            logger("File: " . $e->getFile() . ":" . $e->getLine());
        }
    }

    public function getQueueInfoData()
    {
        try {

            $data['agents'] = $this->getFsAgents();

            $data['calls'] = $this->getLiveCalls();

            // logger($data);

            return ($data);
        } catch (\Exception $e) {
            // Handle the exception
            logger("Error: " . $e->getMessage());
            // Log the file where the error occurred
            logger("File: " . $e->getFile() . ":" . $e->getLine());
            return null;
        }
    }


    protected function getAvgCallDurationChartData()
    {
        $hourlyData = array_fill(0, 24, ['totalDuration' => 0, 'callCount' => 0]);
        $userUuid = auth()->user()->user_uuid;
        $timeZone = new \DateTimeZone($this->getTimezone());

        if (Cache::has($userUuid . '_inboundCdrs')) {
            foreach (Cache::get($userUuid . '_inboundCdrs') as $call) {
                $dateTime = (new \DateTime('@' . $call->start_epoch))->setTimezone($timeZone);
                $hour = (int) $dateTime->format('H');
                $duration = $call->end_epoch - $call->cc_queue_answered_epoch;
                $hourlyData[$hour]['totalDuration'] += (int) $duration;
                $hourlyData[$hour]['callCount']++;
            }
        }

        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Average Call Duration',
                    'data' => [],
                    'borderWidth' => 1,
                    'barThickness' => 10,
                    'maxBarThickness' => 15,
                    'backgroundColor' => 'rgba(0, 123, 255, 0.7)',
                    'borderColor' => 'rgba(0, 102, 204, 1)',
                ]
            ]
        ];

        foreach ($hourlyData as $hour => $data) {
            if ($data['callCount'] > 0) {
                $averageDuration = ceil($data['totalDuration'] / $data['callCount']);
                $chartData['labels'][] = $this->convertToUSTimeFormat($hour);
                $chartData['datasets'][0]['data'][] = $averageDuration;
            }
        }

        return $chartData;
    }


    protected function getCallVolumeChartData()
    {
        $hourlyData = array_fill(0, 24, ['inbound' => 0, 'missed' => 0, 'voicemail' => 0]);
        $userUuid = auth()->user()->user_uuid;
        $timeZone = new \DateTimeZone($this->getTimezone());

        $cacheKeys = [
            'inboundCdrs' => 'inbound',
            'missedCdrs' => 'missed',
            'voicemailCdrs' => 'voicemail',
        ];

        foreach ($cacheKeys as $cacheKey => $callType) {
            if (Cache::has($userUuid . '_' . $cacheKey)) {
                foreach (Cache::get($userUuid . '_' . $cacheKey) as $call) {
                    $dateTime = (new \DateTime('@' . $call->start_epoch))->setTimezone($timeZone);
                    $hour = (int) $dateTime->format('H');
                    $hourlyData[$hour][$callType]++;
                }
            }
        }

        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Answered',
                    'data' => [],
                    'borderWidth' => 1,
                    'barThickness' => 10,
                    'maxBarThickness' => 15,
                    'backgroundColor' => 'rgba(40, 167, 69, 0.7)',
                    'borderColor' => 'rgba(33, 136, 56, 1)',
                ],
                [
                    'label' => 'Missed',
                    'data' => [],
                    'borderWidth' => 1,
                    'barThickness' => 10,
                    'maxBarThickness' => 15,
                    'backgroundColor' => 'rgba(255, 193, 7, 0.7)',
                    'borderColor' => 'rgba(204, 153, 0, 1)',
                ],
                [
                    'label' => 'Voicemail',
                    'data' => [],
                    'borderWidth' => 1,
                    'barThickness' => 10,
                    'maxBarThickness' => 15,
                    'backgroundColor' => 'rgba(0, 123, 255, 0.7)',
                    'borderColor' => 'rgba(0, 102, 204, 1)',
                ]
            ]
        ];

        foreach ($hourlyData as $hour => $counts) {
            if ($counts['inbound'] > 0 || $counts['missed'] > 0 || $counts['voicemail'] > 0) {
                $chartData['labels'][] = $this->convertToUSTimeFormat($hour);
                $chartData['datasets'][0]['data'][] = $counts['inbound'];
                $chartData['datasets'][1]['data'][] = $counts['missed'];
                $chartData['datasets'][2]['data'][] = $counts['voicemail'];
            }
        }

        return $chartData;
    }


    // PHP function to convert 24-hour format to 12-hour (US) format
    function convertToUSTimeFormat($hour)
    {
        if ($hour == 0) {
            return '12 AM';
        } else if ($hour < 12) {
            return $hour . ' AM';
        } else if ($hour == 12) {
            return '12 PM';
        } else {
            return ($hour - 12) . ' PM';
        }
    }

    public function getAllContactCenters()
    {

        if (!Cache::has(session('domain_uuid') . '_contactCenters')) {
            $contactCenters = CallCenterQueues::where('domain_uuid', session('domain_uuid'))
                ->orderBy('queue_name')
                ->get([
                    'call_center_queue_uuid',
                    'queue_name',
                    'queue_extension'
                ]);

            Cache::put(session('domain_uuid') . '_contactCenters', $contactCenters, 600);
        } else {
            $contactCenters = Cache::get(session('domain_uuid') . '_contactCenters');
        }
        return $contactCenters;
    }



    public function getMissedCalls()
    {
        try {
            $query = CDR::wherein(
                'call_center_queue_uuid',
                $this->selectedQueues
            )
                ->where('start_stamp', '>=', $this->startPeriod)
                ->where('start_stamp', '<=', $this->endPeriod)
                ->where('cc_cause', 'cancel')
                ->where('cc_side', 'member');

            //exclude legs that were not answered
            if (!userCheckPermission('xml_cdr_lose_race')) {
                $query->where('hangup_cause', '!=', 'LOSE_RACE');
            }

            $missedCdrs = $query->get([
                'xml_cdr_uuid',
                'start_epoch',
                'cc_queue_joined_epoch',
                'cc_queue_canceled_epoch',
                'cc_side',
                'voicemail_message',
                'duration',
                'status'
            ]);

            // Update cache
            Cache::forget(auth()->user()->user_uuid . '_missedCdrs');
            Cache::put(auth()->user()->user_uuid . '_missedCdrs', $missedCdrs, 600);

            $voicemailCdrs = $missedCdrs->filter(function ($cdr) {
                return $cdr->status == 'voicemail' && $cdr->voicemail_message == true;
            });

            // Update cache
            Cache::forget(auth()->user()->user_uuid . '_voicemailCdrs');
            Cache::put(auth()->user()->user_uuid . '_voicemailCdrs', $voicemailCdrs, 600);

            unset($missedCdrs);
            unset($voicemailCdrs);
        } catch (\Exception $e) {
            // Do nothing for now
            logger($e->getMessage());
        }
    }

    public function getInboundCalls()
    {
        $query = CDR::wherein(
            'call_center_queue_uuid',
            $this->selectedQueues
        )
            ->whereBetween('start_stamp', [$this->startPeriod, $this->endPeriod])
            ->where('cc_side', 'member')
            ->where('cc_agent_bridged', 'true');

        // Filter by agent
        if ($this->selectedAgents) {
            $query->whereIn('cc_agent', $this->selectedAgents);
        }

        $inboundCdrs = $query->get([
            'xml_cdr_uuid',
            'duration',
            'waitsec',
            'start_epoch',
            'cc_queue_joined_epoch',
            'cc_queue_answered_epoch',
            'cc_agent',
            'end_epoch',
        ]);

        // Invalidate the cache if new data is retrieved
        Cache::forget(auth()->user()->user_uuid . '_inboundCdrs');
        Cache::put(auth()->user()->user_uuid . '_inboundCdrs', $inboundCdrs, 600);

        // Free memory
        unset($inboundCdrs);
    }

    public function getOutboundCalls()
    {
        try {
            $contactCenterAgents = $this->getContactCenterAgents();

            // Filter by agent
            if (!empty($this->selectedAgents)) {
                $contactCenterAgents = $contactCenterAgents->whereIn('call_center_agent_uuid', $this->selectedAgents);
            }

            // Only keep non-empty extension_uuid values
            $extensionUuids = $contactCenterAgents
                ->pluck('extension_uuid')
                ->filter(fn($uuid) => !empty($uuid))
                ->unique()
                ->values()
                ->all();

            // No extensions -> no outbound calls (normal state). Cache empty and return.
            if (empty($extensionUuids)) {
                Cache::put(auth()->user()->user_uuid . '_outboundCdrs', collect(), 600);
                return;
            }

            $outboundCdrs = CDR::whereIn('extension_uuid', $extensionUuids)
                ->where('direction', 'outbound')
                ->where('hangup_cause', '<>', 'ORIGINATOR_CANCEL')
                ->whereBetween('start_stamp', [$this->startPeriod, $this->endPeriod])
                ->get(['xml_cdr_uuid', 'duration']);

            Cache::put(auth()->user()->user_uuid . '_outboundCdrs', $outboundCdrs, 600);

            unset($outboundCdrs);
        } catch (\Throwable $e) {
            // Rate-limit logs to avoid spam on polling dashboards
            $key = 'cc:getOutboundCalls:error:' . auth()->user()->user_uuid . ':' . session('domain_uuid');
            if (!Cache::has($key)) {
                logger('ContactCenter:DashboardController@getOutboundCalls error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                Cache::put($key, true, 60);
            }

            // Fail safe: keep cache consistent
            Cache::put(auth()->user()->user_uuid . '_outboundCdrs', collect(), 600);
            return;
        }
    }


    /**
     * Display settings page.
     * @return Renderable
     */
    public function showSettings(?CallCenterQueues $callCenterQueues = null)
    {
        if (!userCheckPermission("contact_center_settings_edit")) {
            return redirect('/');
        }

        return Inertia::render('Settings::ContactCenter', $this->buildSettingsPageProps($callCenterQueues));
    }

    public function getQueueItemOptions(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_center_settings_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if (! $itemUuid) {
            return response()->json(['messages' => ['error' => ['Queue is required.']]], 422);
        }

        $item = CallCenterQueues::query()
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->whereKey($itemUuid)
            ->with(['agents'])
            ->firstOrFail();

        $item->append([
            'timeout_action',
            'timeout_target_uuid',
            'timeout_target_name',
            'timeout_target_extension',
        ]);

        $mohSound = $item->queue_moh_sound ?: 'local_stream://default';
        if (str_contains((string) $mohSound, '/music/')) {
            if (preg_match('#/music/(?:global|[^/]+)/([^/]+)/#', (string) $mohSound, $matches)) {
                $mohSound = 'local_stream://' . session('domain_name') . '/' . $matches[1];
            }
        }
        $item->queue_moh_sound = $mohSound;

        $tiers = $item->agents
            ->map(fn (CallCenterAgents $agent) => [
                'call_center_agent_uuid' => $agent->call_center_agent_uuid,
                'agent_name' => $agent->agent_name,
                'tier_level' => (int) ($agent->pivot?->tier_level ?? 1),
                'tier_position' => (int) ($agent->pivot?->tier_position ?? 1),
            ])
            ->values();

        $item->queue_greeting = $item->queue_greeting ?: 'disabled';
        $item->queue_announce_sound = $this->recordingFilenameFromPath($item->queue_announce_sound);
        $item->queue_tier_rules_apply = $this->boolString($item->queue_tier_rules_apply);
        $item->queue_tier_rule_wait_multiply_level = $this->boolString($item->queue_tier_rule_wait_multiply_level);
        $item->queue_tier_rule_no_agent_no_wait = $this->boolString($item->queue_tier_rule_no_agent_no_wait);
        $item->queue_abandoned_resume_allowed = $this->boolString($item->queue_abandoned_resume_allowed);
        $item->queue_announce_position = $this->boolString($item->queue_announce_position);
        $item->queue_record_template = $this->enabledStringFromStoredValue($item->queue_record_template);

        $openAiService = app(\App\Services\OpenAIService::class);

        return response()->json([
            'item' => $item,
            'tiers' => $tiers,
            'agent_options' => $this->agentOptions(),
            'routing_types' => (new CallRoutingOptionsService)->routingTypes,
            'music_on_hold_options' => getMusicOnHoldCollection(Session::get('domain_uuid')),
            'voices' => $openAiService->getVoices(),
            'default_voice' => $openAiService->getDefaultVoice(),
            'speeds' => $openAiService->getSpeeds(),
            'phone_call_instructions' => [
                'Dial <strong>*732</strong> from your phone.',
                'Enter the contact center extension number when prompted and press <strong>#</strong>.',
                'Follow the prompts to record your greeting.',
            ],
            'sample_message' => 'Thank you for calling. Please hold while we connect you with the next available agent.',
            'routes' => [
                'update_route' => route('contactcenter.settings.update', ['callCenterQueues' => $item->call_center_queue_uuid]),
                'get_routing_options' => route('routing.options'),
                'greeting_route' => route('greetings.greetings'),
                'serve_greeting_route' => route('greeting.file.serve', ['file_name' => ':file_name']),
                'update_greeting_route' => route('greetings.file.update'),
                'delete_greeting_route' => route('greetings.file.delete'),
                'upload_greeting_route' => route('greetings.file.upload'),
                'text_to_speech_route' => route('greetings.textToSpeech'),
            ],
        ]);
    }

    protected function agentOptions(): array
    {
        return CallCenterAgents::query()
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('agent_name')
            ->get(['call_center_agent_uuid', 'agent_name', 'agent_id'])
            ->map(fn (CallCenterAgents $agent) => [
                'value' => $agent->call_center_agent_uuid,
                'label' => trim($agent->agent_name . ($agent->agent_id ? " ({$agent->agent_id})" : '')),
            ])
            ->values()
            ->all();
    }

    protected function buildSettingsPageProps(?CallCenterQueues $callCenterQueues = null): array
    {
        $domainUuid = Session::get('domain_uuid');

        $queues = CallCenterQueues::query()
            ->where('domain_uuid', $domainUuid)
            ->orderBy('queue_name')
            ->get(['call_center_queue_uuid', 'queue_name', 'queue_extension']);

        return [
            'queues' => $queues,
            'selectedQueueUuid' => $callCenterQueues?->call_center_queue_uuid,
            'routes' => [
                'dashboard' => route('contactcenter.index'),
                'settings_list' => route('contactcenter.settings.list'),
                'settings_show' => route('contactcenter.settings.show', ['callCenterQueues' => '__QUEUE__']),
                'create' => route('contactcenter.create'),
                'queue_item_options' => route('contactcenter.settings.queue-item-options'),
                'destroy' => $callCenterQueues
                    ? route('contactcenter.destroy', ['callCenterQueues' => $callCenterQueues])
                    : null,
            ],
        ];
    }

    protected function formatQueueForSettings(CallCenterQueues $queue): array
    {
        $queue->append([
            'timeout_action',
            'timeout_target_uuid',
            'timeout_target_name',
            'timeout_target_extension',
        ]);

        $mohSound = $queue->queue_moh_sound ?: 'local_stream://default';
        if (str_contains((string) $mohSound, '/music/')) {
            if (preg_match('#/music/(?:global|[^/]+)/([^/]+)/#', (string) $mohSound, $matches)) {
                $mohSound = 'local_stream://' . session('domain_name') . '/' . $matches[1];
            }
        }

        return [
            'call_center_queue_uuid' => $queue->call_center_queue_uuid,
            'queue_name' => $queue->queue_name,
            'queue_extension' => $queue->queue_extension,
            'queue_description' => $queue->queue_description,
            'queue_strategy' => $queue->queue_strategy ?: 'ring-all',
            'queue_greeting' => $queue->queue_greeting ?: 'disabled',
            'queue_moh_sound' => $mohSound,
            'queue_max_wait_time' => $queue->queue_max_wait_time ?? 0,
            'queue_max_wait_time_with_no_agent' => $queue->queue_max_wait_time_with_no_agent ?? 90,
            'queue_tier_rules_apply' => $this->boolString($queue->queue_tier_rules_apply),
            'queue_tier_rule_wait_second' => $queue->queue_tier_rule_wait_second ?? 30,
            'queue_tier_rule_wait_multiply_level' => $this->boolString($queue->queue_tier_rule_wait_multiply_level),
            'queue_tier_rule_no_agent_no_wait' => $this->boolString($queue->queue_tier_rule_no_agent_no_wait),
            'queue_abandoned_resume_allowed' => $this->boolString($queue->queue_abandoned_resume_allowed),
            'queue_announce_position' => $this->boolString($queue->queue_announce_position),
            'queue_time_base_score' => $queue->queue_time_base_score ?: 'system',
            'queue_time_base_score_sec' => $queue->queue_time_base_score_sec,
            'queue_cid_prefix' => $queue->queue_cid_prefix,
            'queue_email_address' => $queue->queue_email_address,
            'queue_cc_exit_keys' => $queue->queue_cc_exit_keys,
            'queue_discard_abandoned_after' => $queue->queue_discard_abandoned_after,
            'queue_announce_sound' => $this->recordingFilenameFromPath($queue->queue_announce_sound),
            'queue_announce_frequency' => $queue->queue_announce_frequency,
            'queue_record_template' => $this->enabledStringFromStoredValue($queue->queue_record_template),
            'queue_max_wait_time_with_no_agent_time_reached' => $queue->queue_max_wait_time_with_no_agent_time_reached ?: '5',
            'timeout_action' => $queue->timeout_action,
            'timeout_target_uuid' => $queue->timeout_target_uuid,
            'timeout_target_name' => $queue->timeout_target_name,
            'timeout_target_extension' => $queue->timeout_target_extension,
        ];
    }

    protected function boolString(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    protected function enabledStringFromStoredValue(mixed $value): string
    {
        return filled($value) ? 'true' : 'false';
    }

    protected function recordingFilenameFromPath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return basename((string) $path);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        try {
            $callCenterQueue = app(ContactCenterQueueService::class)->createShellQueue();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('contactcenter.settings.list')
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('contactcenter.settings.show', ['callCenterQueues' => $callCenterQueue]);
    }

    protected function getTimezone()
    {
        if (!Cache::has(session('domain_uuid') . '_timeZone')) {
            $timezone = get_local_time_zone(session('domain_uuid'));
            Cache::put(session('domain_uuid') .  '_timeZone', $timezone, 600);
        } else {
            $timezone = Cache::get(session('domain_uuid') . '_timeZone');
        }
        return $timezone;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('contactcenter::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('contactcenter::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param  UpdateSettingsRequest  $request
     * @param  CallCenterQueues  $callCenterQueues
     * @return JsonResponse
     */
    public function update(UpdateSettingsRequest $request, CallCenterQueues $callCenterQueues)
    {
        $queue = app(ContactCenterQueueService::class)->saveFromContactCenterSettings(
            $request->validated(),
            $callCenterQueues,
        );

        return response()->json([
            'status' => 'success',
            'call_center_queue' => $queue->call_center_queue_uuid,
            'message' => 'ContactCenter has been saved',
            'messages' => ['success' => ['Contact center saved.']],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * @param  CallCenterQueues  $callCenterQueues
     * @return JsonResponse
     */
    public function destroy(CallCenterQueues $callCenterQueues)
    {
        app(ContactCenterQueueService::class)->deleteQueue($callCenterQueues);

        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Contact Center has been deleted',
            ],
            'redirect_url' => route('contactcenter.settings.list'),
        ]);
    }

    public function assignAgent(CallCenterQueues $callCenterQueues, CallCenterAgents $callCenterAgents)
    {
        $queueAgent = new CallCenterQueueAgents();
        $queueAgent->domain_uuid = Session::get('domain_uuid');
        $queueAgent->call_center_queue_uuid = $callCenterQueues->call_center_queue_uuid;
        $queueAgent->call_center_agent_uuid = $callCenterAgents->call_center_agent_uuid;
        $queueAgent->tier_level = 0;
        $queueAgent->tier_position = 0;
        $queueAgent->insert_date = Date("Y-m-d H:i:s");
        $queueAgent->insert_user = Session::get('user_uuid');
        $queueAgent->save();

        // connect to Freeswitch
        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );

        //clear fusionpbx cache
        /* TODO: Do we need this?
        if (session_status() == PHP_SESSION_NONE || session_id() == '') {
            session_start();
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }
        }*/

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . Session::get("domain_name"));
        FusionCache::clear('configuration:callcenter.conf*');

        event_socket_request($fp, 'api reloadxml');
        event_socket_request($fp, sprintf(
            'bgapi callcenter_config queue reload %s@%s',
            $callCenterQueues->queue_extension,
            Session::get('domain_name')
        ));
        fclose($fp);

        return response()->json([
            'status' => 'success',
            'call_center_agent_uuid' => $callCenterAgents->call_center_agent_uuid,
            'message' => 'Agent assigned successfully',
            'redirect_url' => route('contactcenter.settings.show', $callCenterQueues),
        ]);
    }

    public function unassignAgent(CallCenterQueues $callCenterQueues, CallCenterAgents $callCenterAgents)
    {
        // Remove the agent from the queue's pivot table
        $callCenterQueues->agents()->detach($callCenterAgents);

        // Connect to freeswitch
        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );

        //clear fusionpbx cache
        FusionCache::clear('configuration:callcenter.conf*');

        //Delete agent from the queue
        event_socket_request($fp, sprintf(
            'bgapi callcenter_config tier del %s@%s %s',
            $callCenterQueues->queue_extension,
            Session::get('domain_name'),
            $callCenterAgents->call_center_agent_uuid
        ));

        //Reload the queue
        event_socket_request($fp, sprintf(
            'api callcenter_config queue reload %s@%s',
            $callCenterQueues->queue_extension,
            $callCenterQueues->domain->domain_name,
        ));
        fclose($fp);

        return response()->json([
            'status' => 'success',
            'call_center_agent_uuid' => $callCenterAgents->call_center_agent_uuid,
            'message' => 'Agent removed from this queue',
            'redirect_url' => route('contactcenter.settings.show', $callCenterQueues),
        ]);
    }


    public function getCards()
    {
        $apps = [];

        $apps[] = ['name' => 'Handled Calls', 'icon' => 'SupportAgent', 'slug' => 'handled_calls'];

        $apps[] = ['name' => 'Total Minutes', 'icon' => 'SupportAgent', 'slug' => 'total_minutes'];

        $apps[] = ['name' => 'Missed Calls', 'icon' => 'SupportAgent', 'slug' => 'missed_calls'];

        $apps[] = ['name' => 'Missed Call Rate', 'icon' => 'SupportAgent', 'slug' => 'missed_call_rate'];

        $apps[] = ['name' => 'Speed to answer', 'icon' => 'SupportAgent', 'slug' => 'speed_to_answer'];

        $apps[] = ['name' => 'Agents to callers ratio', 'icon' => 'SupportAgent', 'slug' => 'agents_to_callers_ratio'];

        $apps[] = ['name' => 'Service Level', 'icon' => 'SupportAgent', 'slug' => 'service_level'];


        return $apps;
    }


    public function formatDuration($durationInSeconds)
    {
        try {
            $minutes = floor($durationInSeconds / 60);
            $seconds = $durationInSeconds % 60;
            $durationFormatted = sprintf('%dm %02ds', $minutes, $seconds);
            return $durationFormatted;
        } catch (\Exception $e) {
            throw new \Exception("Unable to convert duration to human format: " . $e->getMessage());
        }
    }
}
