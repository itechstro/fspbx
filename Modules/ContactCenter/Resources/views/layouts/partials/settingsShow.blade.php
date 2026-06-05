<form method="POST" id="contactCenterForm" action="{{ route('contactcenter.settings.update', ['callCenterQueues' => $queue]) }}" class="form">
    <input type="hidden" name="call_center_queue_uuid" value="{{ $queue->call_center_queue_uuid }}" />
    @method('put')
    @csrf
    <div class="text-sm-end" id="tooltip-container-actions">
        <a href="javascript:confirmDeleteAction('{{ route('contactcenter.destroy', ':id') }}','{{ $queue->call_center_queue_uuid }}');"
           class="btn btn-danger me-2">
            <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions"
               data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i> Delete
        </a>
        <a href="{{ route('contactcenter.settings.list') }}" class="btn btn-light me-2">Close</a>
        <button id="submitFormButton" class="btn btn-success" type="submit"><i class="uil uil-down-arrow me-2"></i>Save</button>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <h3 class="mt-2">Contact Center Info</h3>
            <form id="ContactCenterSettings" autocomplete="off" class="mt-3">
                <input type="hidden" id="contactCenter_entity_id" name="contactCenter_entity_id"
                       value="{{ $queue->call_center_queue_uuid ?? '' }}"/>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input class="form-control" type="search" placeholder="Enter name" name="queue_name"
                                   value="{{ $queue->queue_name ?? '' }}" autocomplete="off" id="name">
                            <div id="queue_name_err" class="queue_name_err text-danger error_message"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="extension" class="form-label">Extension</label>
                            <input class="form-control" type="text" placeholder="Enter extension"
                                   name="queue_extension" value="{{ $queue->queue_extension }}" id="extension">
                            <div id="queue_extension_err" class="queue_extension_err text-danger error_message"></div>
                        </div>
                    </div>
                </div> <!-- end row -->

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            @include(
                                'layouts.partials.greetingSelector',
                                 [
                                     'id' => 'queue_greeting',
                                     'allRecordings' => $recordings,
                                     'value' => $queue->queue_greeting ?? null,
                                     'entity' => 'contactCenter',
                                     'entityid' => $queue->call_center_queue_uuid,
                                     'hint' => 'Select the greeting callers hear when they enter the Contact Center queue. This greeting plays once before the hold music begins.',
                                     'showUseRecordingAction' => (bool)$queue->queue_greeting
                                 ]
                            )
                        </div>
                    </div>
                </div> <!-- end row -->

                <div class="row">
                    <div class="col-6">
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="queue_description" name="queue_description"
                                      rows="3">{{ $queue->queue_description }}</textarea>
                            <div id="queue_description_err"
                                 class="queue_description_err text-danger error_message"></div>
                        </div>
                    </div>
                </div> <!-- end row -->

                <div class="row">
                    <div class="col-6">
                        <div class="mb-3">
                            <label for="queue_moh_sound" class="form-label">Music on Hold</label>
                            <select class="select2 form-control" data-toggle="select2"
                                    data-placeholder="Choose ..." id="queue_moh_sound"
                                    name="queue_moh_sound">
                                <option
                                    value="null"
                                    @if (empty($queue->queue_moh_sound)) selected @endif>
                                    Don't use music
                                </option>
                                @if (!$moh->isEmpty())
                                    <optgroup label="Music on Hold">
                                        @foreach ($moh as $music)
                                            <option
                                                value="local_stream://{{ $music->music_on_hold_name }}"
                                                @if ('local_stream://' . $music->music_on_hold_name == $queue->queue_moh_sound) selected @endif>
                                                {{ $music->music_on_hold_name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if (!$recordings->isEmpty())
                                    <optgroup label="Recordings">
                                        @foreach ($recordings as $recording)
                                            <option
                                                value="{{ $recording->recording_filename }}"
                                                @if (getDefaultSetting('switch','recordings'). "/" . Session::get('domain_name') . "/" .$recording->recording_filename == $queue->queue_moh_sound) selected @endif>
                                                {{ $recording->recording_name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                <optgroup label="Ringtones">
                                    <option value="${us-ring}"
                                            @if ($queue->queue_moh_sound == '${us-ring}') selected="selected" @endif>
                                        ${us-ring}
                                    </option>
                                </optgroup>
                            </select>
                            <span class="help-block"><small>Music that the caller hears when calling the contact center</small></span>
                            <div id="queue_moh_sound_err" class="text-danger error_message"></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="accordion accordion-flush" id="accordionCC">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="agents-and-admins-heading">
                                    <button
                                        class="accordion-button @if($destinationTimeoutCategorySelected) collapsed @endif"
                                        type="button" data-bs-toggle="collapse"
                                        data-bs-target="#agents-and-admins-collapse" aria-expanded="false"
                                        aria-controls="agents-and-admins-collapse">
                                        <h3>Agents</h3>
                                    </button>
                                </h2>
                                <div id="agents-and-admins-collapse"
                                     class="accordion-collapse collapse @if(!$destinationTimeoutCategorySelected) in show @endif"
                                     aria-labelledby="agents-and-admins-heading" data-bs-parent="#accordionCC">
                                    <div class="accordion-body">
                                        @include(
                                            'contactcenter::layouts.partials.settingsShowAgents',
                                             [
                                                 'queue' => $queue,
                                                 'notAssignedAgents' => $notAssignedAgents,
                                                 'assignedAgents' => $assignedAgents
                                             ]
                                        )
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="routing-header">
                                    <button
                                        class="accordion-button @if(!$destinationTimeoutCategorySelected) collapsed @endif"
                                        type="button"
                                        data-bs-toggle="collapse" data-bs-target="#routing-collapse"
                                        aria-expanded="false" aria-controls="routing-collapse">
                                        <h3>Call Routing Options</h3>
                                        <span class="mx-2 float-end text-end
                                                queue_max_wait_time_err_badge
                                                queue_max_wait_time_with_no_agent_err_badge
                                                queue_cc_exit_keys_err_badge
                                                queue_timeout_action_err_badge
                                                queue_timeout_action_err_badge
                                                queue_strategy_err_badge
                                                " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </button>
                                </h2>
                                <div id="routing-collapse"
                                     class="accordion-collapse collapse @if($destinationTimeoutCategorySelected) in show @endif"
                                     aria-labelledby="routing-header" data-bs-parent="#accordionCC">
                                    <div class="accordion-body">

                                        {{-- Routing Method --}}
                                        <div class="row border border-dark-subtle p-3 mb-3">
                                            <h4 class="mt-2">Routing Method</h4>

                                            <p class="text-muted mb-3">
                                                Ensure calls are routed to the right agent every time.
                                                Select the routing option below to fit your business needs.
                                                Remember,
                                                you
                                                can
                                                always come back and readjust anytime.

                                            </p>
                                            <div id="queue_strategy_err"
                                                 class="queue_strategy_err text-danger error_message"></div>
                                            <div class="mt-3">
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio1"
                                                           name="queue_strategy" value="ring-all"
                                                           class="form-check-input"
                                                           @if ($queue->queue_strategy == 'ring-all') checked @endif>
                                                    <label class="form-check-label" for="callRoutingRadio1">Ring
                                                        All</label>
                                                    <p class="text-muted mb-3">
                                                        Incoming calls ring all agents in the queue.
                                                    </p>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio2"
                                                           name="queue_strategy" value="longest-idle-agent"
                                                           @if ($queue->queue_strategy == 'longest-idle-agent') checked
                                                           @endif
                                                           class="form-check-input">
                                                    <label class="form-check-label"
                                                           for="callRoutingRadio2">Longest
                                                        Idle
                                                        Agent</label>
                                                    <p class="text-muted mb-3">
                                                        The agents are contacted one by one, depending on who has
                                                        been
                                                        free
                                                        for the longest time. This setting is global across all
                                                        Contact
                                                        Centers and is determined by the agent's last call across
                                                        all
                                                        their
                                                        assigned Contact Centers.
                                                    </p>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio3"
                                                           name="queue_strategy" value="round-robin"
                                                           @if ($queue->queue_strategy == 'round-robin') checked @endif
                                                           class="form-check-input">
                                                    <label class="form-check-label" for="callRoutingRadio3">Round
                                                        Robin</label>
                                                    <p class="text-muted mb-3">
                                                        Agents are listed in a prioritized order for which they
                                                        should
                                                        be
                                                        rung. When a call comes in, the first agent who answers is
                                                        moved
                                                        to
                                                        the end of that list. That way, the same agent isn't always
                                                        taking
                                                        the calls.
                                                    </p>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio4"
                                                           name="queue_strategy" value="top-down"
                                                           @if ($queue->queue_strategy == 'top-down') checked @endif
                                                           class="form-check-input">
                                                    <label class="form-check-label" for="callRoutingRadio4">Top
                                                        Down</label>
                                                    <p class="text-muted mb-3">
                                                        Agents are organized in sequential order, and the first
                                                        available
                                                        agent is rung. The algorithm begins at the top of the list
                                                        and
                                                        works
                                                        its way down to the bottom until an agent is found. There is
                                                        no
                                                        priority, and the assignment is determined solely by the
                                                        agent's
                                                        position in the linear group.
                                                    </p>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio5"
                                                           name="queue_strategy" value="agent-with-least-talk-time"
                                                           @if ($queue->queue_strategy == 'agent-with-least-talk-time') checked
                                                           @endif
                                                           class="form-check-input">
                                                    <label class="form-check-label" for="callRoutingRadio5">Agent
                                                        With
                                                        Least Talk Time</label>
                                                    <p class="text-muted mb-3">
                                                        The call is directed to the agent with the shortest total
                                                        talk
                                                        time.
                                                    </p>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio6"
                                                           name="queue_strategy" value="agent-with-fewest-calls"
                                                           @if ($queue->queue_strategy == 'agent-with-fewest-calls') checked
                                                           @endif
                                                           class="form-check-input">
                                                    <label class="form-check-label" for="callRoutingRadio6">Agent
                                                        With
                                                        Fewest Calls</label>
                                                    <p class="text-muted mb-3">
                                                        The call is directed to the agent who has answered the
                                                        fewest
                                                        number
                                                        of calls.
                                                    </p>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio7"
                                                           name="queue_strategy" value="sequentially-by-agent-order"
                                                           @if ($queue->queue_strategy == 'sequentially-by-agent-order') checked
                                                           @endif
                                                           class="form-check-input">
                                                    <label class="form-check-label"
                                                           for="callRoutingRadio7">Sequentially
                                                        By Agent Order</label>
                                                    <p class="text-muted mb-3">
                                                        Rings agents sequentially by tier & order. The priority
                                                        never
                                                        changes.
                                                    </p>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input type="radio" id="callRoutingRadio8"
                                                           name="queue_strategy" value="random"
                                                           @if ($queue->queue_strategy == 'random') checked @endif
                                                           class="form-check-input">
                                                    <label class="form-check-label"
                                                           for="callRoutingRadio8">Random</label>
                                                    <p class="text-muted mb-3">
                                                        The Agents are contacted one after the other, following a
                                                        randomized
                                                        list generated every time the previous list is completed.
                                                    </p>
                                                </div>


                                            </div>
                                        </div>
                                        {{-- Timeout Settings --}}
                                        <div class="row border border-dark-subtle p-3 mb-3">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Timeout Settings</h4>

                                                <p class="text-muted mb-3">
                                                    During business hours, select the maximum wait time allowed in
                                                    the
                                                    queue
                                                    or the maximum wait time if no agents have signed in yet.
                                                </p>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="queue_max_wait_time"
                                                                   class="form-label">Maximum
                                                                queue wait time (seconds)</label>
                                                            <input class="form-control" type="text"
                                                                   name="queue_max_wait_time"
                                                                   placeholder="Enter maximum queue wait time"
                                                                   value="{{ $queue->queue_max_wait_time }}"
                                                                   id="queue_max_wait_time">
                                                            <span class="help-block"><small>If you want to disable
                                                                            it,
                                                                            set
                                                                            it to 0. When the maximum is exceeded, callers
                                                                            will
                                                                            be
                                                                            routed based on the fallback options.
                                                                        </small></span>

                                                            <div id="queue_max_wait_time_err"
                                                                 class="queue_max_wait_time_err text-danger error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="queue_max_wait_time_with_no_agent"
                                                                   class="form-label">Maximum wait time when agents
                                                                are
                                                                not
                                                                logged in (seconds)</label>
                                                            <input class="form-control" type="text"
                                                                   placeholder=""
                                                                   value="{{ $queue->queue_max_wait_time_with_no_agent }}"
                                                                   name="queue_max_wait_time_with_no_agent"
                                                                   id="queue_max_wait_time_with_no_agent">
                                                            <span class="help-block"><small>If you want to disable
                                                                            it,
                                                                            set
                                                                            it to 0. Define the amount of time the queue
                                                                            must be
                                                                            empty (without logged agents, on a call or not)
                                                                            before
                                                                            disconnecting all members. This principle
                                                                            protects
                                                                            kicking all members waiting if all agents are
                                                                            logged
                                                                            off
                                                                            by accident.
                                                                        </small></span>
                                                            <div id="queue_max_wait_time_with_no_agent_err"
                                                                 class="queue_max_wait_time_with_no_agent_err text-danger error_message"></div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="queue_cc_exit_keys"
                                                                   class="form-label">Allow
                                                                callers to exit the Contact Center queue by
                                                                pressing:</label>
                                                            <select class="select2 form-control"
                                                                    data-toggle="select2" name="queue_cc_exit_keys"
                                                                    data-placeholder="Choose ..."
                                                                    id="queue_cc_exit_keys">
                                                                <option value="10">Select</option>
                                                                @for ($i = 0; $i <= 9; $i++)
                                                                    <option value="{{ $i }}"
                                                                            @if ($queue->queue_cc_exit_keys == $i) selected @endif>
                                                                        {{ $i }}</option>
                                                                @endfor
                                                            </select>
                                                            <div id="queue_cc_exit_keys_err"
                                                                 class="queue_cc_exit_keys_err text-danger error_message"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        {{-- Fallback Options --}}
                                        <div class="row border border-dark-subtle p-3 mb-3">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Fallback Options</h4>

                                                <p class="text-muted mb-3">
                                                    Decide how to handle calls when timeout occurs or no agents are
                                                    logged
                                                    in during business hours.
                                                </p>

                                                @include('layouts.partials.timeoutDestinations', ['entityUuid' => $queue->queue_timeout_action])

                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="advanced-settings-heading">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#advanced-settings-collapse"
                                            aria-expanded="false" aria-controls="advanced-settings-collapse">
                                        <h3>Advanced Settings</h3>
                                        <span class="mx-2 float-end text-end
                                                queue_cid_prefix_err_badge
                                                queue_announce_sound_err_badge
                                                queue_announce_frequency_err_badge
                                                queue_announce_position_err_badge
                                                queue_time_base_score_err_badge
                                                queue_time_base_score_sec_err_badge
                                                queue_tier_rules_apply_err_badge
                                                queue_tier_rule_wait_second_err_badge
                                                queue_tier_rule_wait_multiply_level_err_badge
                                                queue_tier_rule_no_agent_no_wait_err_badge
                                                queue_discard_abandoned_after_err_badge
                                                queue_email_address_err_badge
                                                queue_email_address_err_badge
                                                queue_record_template_err_badge
                                                " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </button>
                                </h2>
                                <div id="advanced-settings-collapse" class="accordion-collapse collapse"
                                     aria-labelledby="advanced-settings-heading" data-bs-parent="#accordionCC">
                                    <div class="accordion-body">

                                        {{-- Periodic Announcements Settings --}}
                                        <div class="row border border-dark-subtle p-3 mb-3">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Periodic Announcements Settings</h4>

                                                <p class="text-muted mb-3">
                                                    This optional announcement is played periodically while the caller
                                                    is waiting to be connected to an agent.
                                                </p>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="queue_announce_sound" class="form-label">Periodic
                                                                Greeting</label>
                                                            <select class="select2 form-control" data-toggle="select2"
                                                                    data-placeholder="No Message"
                                                                    id="queue_announce_sound"
                                                                    name="queue_announce_sound">
                                                                <option value="null">No Message</option>
                                                                @if (!$recordings->isEmpty())
                                                                    @foreach ($recordings as $recording)
                                                                        <option
                                                                            value="{{ $recording->recording_filename }}"
                                                                            @if (strpos($queue->queue_announce_sound,$recording->recording_filename) != 0) selected @endif>
                                                                            {{ $recording->recording_name }}
                                                                        </option>
                                                                    @endforeach
                                                                @endif
                                                            </select>
                                                            <span class="help-block"><small>A greeting that is played every configured minutes while callers are in the queue.
                                                                        </small></span>
                                                            <div id="queue_announce_sound_err"
                                                                 class="queue_announce_sound_err text-danger error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="queue_announce_frequency"
                                                                   class="form-label">Periodic Announcement Frequency
                                                                (seconds)</label>
                                                            <input class="form-control" type="text"
                                                                   placeholder="" name="queue_announce_frequency"
                                                                   value="{{ $queue->queue_announce_frequency ?? '' }}"
                                                                   id="queue_announce_frequency">
                                                            <div id="queue_announce_frequency_err"
                                                                 class="queue_announce_frequency_err text-danger error_message"></div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">Announce Caller Position In
                                                                Queue</label><br>
                                                            <span class="help-block"><small>
                                                                        A pre-recorded audio message that the customer hears during the wait time when they are actually on hold, informing them of how many callers are ahead of them on the queue and/or the estimated time to wait before connecting with a live agent.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="mb-3 text-sm-end">
                                                            <input type="hidden" name="queue_announce_position"
                                                                   value="false">
                                                            <input type="checkbox" id="queue_announce_position"
                                                                   name="queue_announce_position" value="true"
                                                                   @if ($queue->queue_announce_position == 'true') checked
                                                                   @endif
                                                                   data-switch="primary"/>
                                                            <label for="queue_announce_position" data-on-label="On"
                                                                   data-off-label="Off"></label>
                                                            <div id="queue_announce_position_err"
                                                                 class="queue_announce_position_err text-danger error_message">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                            </div>
                                        </div>

                                        {{-- Time based Score --}}
                                        <div class="row border border-dark-subtle p-3 mb-3">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Time Based Score</h4>

                                                <p class="text-muted mb-3">
                                                    When a caller goes into a queue, we can add to their base score
                                                    the
                                                    total number of seconds they have been in the system. This
                                                    enables
                                                    the
                                                    caller to get in front of other callers by the amount of time
                                                    they
                                                    have
                                                    already spent waiting elsewhere.

                                                    The time-base-score param in a queue can be set as 'queue' (base
                                                    score
                                                    counts only the time the caller is in this queue) or 'system'
                                                    (base
                                                    score accounts for the total time of the call).
                                                </p>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="queue_time_base_score"
                                                                   class="form-label">Time
                                                                Based Score</label>
                                                            <select class="select2 form-control"
                                                                    data-toggle="select2"
                                                                    data-placeholder="Choose ..."
                                                                    id="queue_time_base_score"
                                                                    name="queue_time_base_score">
                                                                <option value=""></option>
                                                                @if (count($time_based_score_options) > 0)
                                                                    @foreach ($time_based_score_options as $option)
                                                                        <option value="{{ $option }}"
                                                                                @if ($option == $queue->queue_time_base_score) selected @endif>
                                                                            {{ $option }}
                                                                        </option>
                                                                    @endforeach
                                                                @endif
                                                            </select>
                                                            <div id="queue_time_base_score_err"
                                                                 class="queue_time_base_score_err text-danger error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="queue_time_base_score_sec"
                                                                   class="form-label">Set
                                                                Time Based Score</label>
                                                            <input class="form-control" type="text"
                                                                   placeholder="" name="queue_time_base_score_sec"
                                                                   id="queue_time_base_score_sec"
                                                                   value="{{ $queue->queue_time_base_score_sec ?? '' }}">
                                                            <span class="help-block"><small>Set the time base score
                                                                            in
                                                                            seconds. Higher numbers mean higher priority
                                                                            over
                                                                            other
                                                                            contact centers.
                                                                        </small></span>
                                                            <div id="queue_time_base_score_sec_err"
                                                                 class="queue_time_base_score_sec_err text-danger error_message"></div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                            </div>
                                        </div>


                                        {{-- Advanced Tier Rules --}}
                                        <div class="row border border-dark-subtle p-3 mb-3">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Advanced Tier Rules</h4>

                                                <p class="text-muted mb-3">
                                                    Fine-tune and optimize the performance of your contact center by
                                                    customizing various rules that govern agent assignments and
                                                    waiting
                                                    times based on tier levels.
                                                    With these advanced settings, you can take full control of call
                                                    routing
                                                    and prioritize the allocation of calls to the most qualified
                                                    agents,
                                                    enhancing both efficiency and customer satisfaction.
                                                </p>

                                                <div class="row">
                                                    <div class="col-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">Enable Tier Rules</label><br>
                                                            <span class="help-block"><small>
                                                                            This setting determines whether the tier rules
                                                                            should be
                                                                            applied as a caller progresses through a queue's
                                                                            tiers.
                                                                            If disabled, they will be able to access all
                                                                            tiers
                                                                            without any waiting.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="mb-3 text-sm-end">
                                                            <input type="hidden" name="queue_tier_rules_apply"
                                                                   value="false">
                                                            <input type="checkbox" id="queue_tier_rules_apply"
                                                                   name="queue_tier_rules_apply" value="true"
                                                                   @if ($queue->queue_tier_rules_apply == 'true') checked
                                                                   @endif
                                                                   data-switch="primary"/>
                                                            <label for="queue_tier_rules_apply" data-on-label="On"
                                                                   data-off-label="Off"></label>
                                                            <div id="queue_tier_rules_apply_err"
                                                                 class="text-danger queue_tier_rules_apply_err error_message"></div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="name" class="form-label">Delay Before
                                                                Progressing To Next Tier Level (seconds)
                                                            </label>
                                                            <input class="form-control" type="search"
                                                                   placeholder=""
                                                                   value="{{ $queue->queue_tier_rule_wait_second ?? '' }}"
                                                                   name="queue_tier_rule_wait_second"
                                                                   autocomplete="off"
                                                                   id="queue_tier_rule_wait_second">
                                                            <div id="queue_tier_rule_wait_second_err"
                                                                 class="text-danger queue_tier_rule_wait_second_err error_message"></div>
                                                            <span class="help-block"><small>
                                                                            The amount of time a caller must wait before
                                                                            moving
                                                                            to
                                                                            the next tier level. If the option to
                                                                            <code>Multiply Wait Time By Tier Level</code> is
                                                                            enabled, the wait
                                                                            time will be multiplied by the tier level. If
                                                                            the
                                                                            option
                                                                            is
                                                                            disabled, after the specified wait time has
                                                                            passed,
                                                                            all
                                                                            tier levels will be open for calls in the order
                                                                            specified,
                                                                            and no advancement to another level will be made
                                                                            based
                                                                            on wait time.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">Multiply Wait Time By Tier
                                                                Level</label><br>
                                                            <span class="help-block"><small>
                                                                            If this setting is disabled, the call will be
                                                                            offered to
                                                                            all tier levels once the
                                                                            delay is up. If enabled the <code>Delay Before
                                                                                Progressing To Next Tier Level</code> will
                                                                            be
                                                                            multiplied by the tier level.
                                                                            This means the caller will have to wait on each
                                                                            tier
                                                                            for
                                                                            the designated time before advancing to the next
                                                                            tier.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="mb-3 text-sm-end">
                                                            <input type="hidden"
                                                                   name="queue_tier_rule_wait_multiply_level"
                                                                   value="false">
                                                            <input type="checkbox"
                                                                   id="queue_tier_rule_wait_multiply_level"
                                                                   name="queue_tier_rule_wait_multiply_level"
                                                                   value="true"
                                                                   @if ($queue->queue_tier_rule_wait_multiply_level == 'true') checked
                                                                   @endif
                                                                   data-switch="primary"/>
                                                            <label for="queue_tier_rule_wait_multiply_level"
                                                                   data-on-label="On" data-off-label="Off"></label>
                                                            <div id="queue_tier_rule_wait_multiply_level_err"
                                                                 class="text-danger queue_tier_rule_wait_multiply_level_err error_message">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">Skip Tiers With No Available
                                                                Agents</label><br>
                                                            <span class="help-block"><small>If enabled, callers
                                                                            will be
                                                                            able to skip tiers that do not have available
                                                                            agents.
                                                                            Otherwise, they will have to wait before
                                                                            advancing.
                                                                            Agents will only be considered unavailable if
                                                                            they
                                                                            have
                                                                            logged off.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="mb-3 text-sm-end">
                                                            <input type="hidden"
                                                                   name="queue_tier_rule_no_agent_no_wait"
                                                                   value="false">
                                                            <input type="checkbox"
                                                                   id="queue_tier_rule_no_agent_no_wait"
                                                                   name="queue_tier_rule_no_agent_no_wait"
                                                                   value="true"
                                                                   @if ($queue->queue_tier_rule_no_agent_no_wait == 'true') checked
                                                                   @endif
                                                                   data-switch="primary"/>
                                                            <label for="queue_tier_rule_no_agent_no_wait"
                                                                   data-on-label="On" data-off-label="Off"></label>
                                                            <div id="queue_tier_rule_no_agent_no_wait_err"
                                                                 class="text-danger queue_tier_rule_no_agent_no_wait_err error_message">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->
                                            </div>
                                        </div>


                                        {{-- Abandoned Call Settings --}}
                                        <div class="row border border-dark-subtle p-3 mb-3">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Abandoned Call Settings</h4>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="extension" class="form-label">Delay Before
                                                                Removing
                                                                Abandoned Calls (seconds)</label>
                                                            <input class="form-control" type="text"
                                                                   placeholder=""
                                                                   value="{{ $queue->queue_discard_abandoned_after }}"
                                                                   name="queue_discard_abandoned_after"
                                                                   id="queue_discard_abandoned_after">
                                                            <div id="queue_discard_abandoned_after_err"
                                                                 class="text-danger queue_discard_abandoned_after_err error_message"></div>
                                                            <span class="help-block"><small>
                                                                            Duration of time before we permanently remove a
                                                                            caller
                                                                            who
                                                                            has abandoned their position in the queue.
                                                                            Callers can
                                                                            return to the queue and continue from where they
                                                                            left
                                                                            off if
                                                                            <code>Allow Caller To Retaining Their
                                                                                Position</code> is
                                                                            enabled.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="extension" class="form-label">Send
                                                                Abandoned Call
                                                                Notification To</label>
                                                            <input class="form-control" type="text"
                                                                   placeholder=""
                                                                   value="{{ $queue->queue_email_address }}"
                                                                   name="queue_email_address"
                                                                   id="queue_email_address">
                                                            <div id="queue_email_address_err"
                                                                 class="text-danger queue_email_address_err error_message"></div>
                                                            <span class="help-block"><small>
                                                                            If an individual departs from the queue prior to
                                                                            the
                                                                            agent answering, an email notification will be
                                                                            sent to
                                                                            the provided email address.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">Allow Caller To Retaining
                                                                Their
                                                                Position</label><br>
                                                            <span class="help-block"><small>
                                                                            If the feature is enabled, a caller who has left
                                                                            the
                                                                            queue
                                                                            can return and continue from their previous
                                                                            position in
                                                                            the
                                                                            same queue. However, to retain their position,
                                                                            they must
                                                                            not
                                                                            leave the queue for a duration longer than the
                                                                            specified
                                                                            number of seconds in <code>Delay Before Removing
                                                                                Abandoned
                                                                                Calls</code>.
                                                                        </small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="mb-3 text-sm-end">
                                                            <input type="hidden"
                                                                   name="queue_abandoned_resume_allowed"
                                                                   value="false">
                                                            <input type="checkbox"
                                                                   id="queue_abandoned_resume_allowed"
                                                                   name="queue_abandoned_resume_allowed"
                                                                   value="true"
                                                                   @if ($queue->queue_abandoned_resume_allowed == 'true') checked
                                                                   @endif
                                                                   data-switch="primary"/>
                                                            <label for="queue_abandoned_resume_allowed"
                                                                   data-on-label="On" data-off-label="Off"></label>
                                                            <div id="queue_abandoned_resume_allowed_err"
                                                                 class="text-danger queue_abandoned_resume_allowed_err error_message">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="queue_cid_prefix" class="form-label">Caller ID
                                                        Prefix
                                                    </label>
                                                    <input class="form-control" type="search" placeholder=""
                                                           name="queue_cid_prefix"
                                                           value="{{ $queue->queue_cid_prefix ?? '' }}"
                                                           autocomplete="off" id="queue_cid_prefix">
                                                    <div id="queue_cid_prefix_err"
                                                         class="text-danger queue_cid_prefix_err error_message">
                                                    </div>
                                                    <span class="help-block"><small>
                                                                    By using the Caller ID Prefix field, you can add
                                                                    characters
                                                                    to the Caller's ID. This feature comes in handy when
                                                                    your
                                                                    agents handle calls from various Contact Centers, and
                                                                    you
                                                                    want to determine which one was dialed..
                                                                </small></span>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <div class="row">
                                            <div class="col-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Record calls</label>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="mb-3 text-sm-end">
                                                    <input type="hidden" name="queue_record_template"
                                                           value="false">
                                                    <input type="checkbox" id="queue_record_template"
                                                           name="queue_record_template" value="true"
                                                           @if ($queue->queue_record_template != '') checked @endif
                                                           data-switch="primary"/>
                                                    <label for="queue_record_template" data-on-label="On"
                                                           data-off-label="Off"></label>
                                                    <div id="queue_record_template_err"
                                                         class="text-danger queue_record_template_err error_message">
                                                    </div>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <input type="hidden"
                                               name="queue_max_wait_time_with_no_agent_time_reached" value="5">

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- end row -->
            </form>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = $('#contactCenterForm');

            $('#submitFormButton').on('click', function(e) {
                e.preventDefault();
                $('.loading').show();

                //Reset error messages
                $('.error_message').text("");

                var url = form.attr('action');

                $.ajax({
                    type: "POST",
                    url: url,
                    cache: false,
                    data: form.serialize(),
                    beforeSend: function() {
                        //Reset error messages
                        form.find('.error').text('');
                        $('.error_message').text("");
                        $('.btn').attr('disabled', true);
                        $('.loading').show();
                    },
                    complete: function(xhr, status) {
                        $('.btn').attr('disabled', false);
                        $('.loading').hide();
                    },
                    success: function(result) {
                        $.NotificationApp.send("Success", result.message, "top-right",
                            "#10c469", "success");
                        if(result.redirect_url){
                            window.location=result.redirect_url;
                        } else {
                            $('.loading').hide();
                        }
                    },
                    error: function(error) {
                        $('.loading').hide();
                        $('.btn').attr('disabled', false);
                        if (error.status == 422) {
                            if (error.responseJSON.errors) {
                                $.each(error.responseJSON.errors, function(key, value) {
                                    if (value != '') {
                                        form.find('#' + key + '_err').text(value);
                                        printErrorMsg(value);
                                    }
                                });
                            } else {
                                printErrorMsg(error.responseJSON.message);
                            }
                        } else {
                            printErrorMsg(error.responseJSON.message);
                        }
                    }
                })
            });
        });
    </script>
@endpush
