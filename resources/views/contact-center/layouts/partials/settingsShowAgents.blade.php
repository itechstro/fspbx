<div>
    <div class="row mb-2">
        <div class="col-4">
            <select class="select2 form-control" data-toggle="select2"
                    data-placeholder="-- Select agent to add --" id="selectAgent">
                <option value=""></option>
                @foreach ($notAssignedAgents as $agent)
                    <option value="{{ $agent['call_center_agent_uuid'] }}">
                        {{ $agent['agent_name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-2">
            <button class="btn btn-success" id="addAgent">Add</button>
        </div>
    </div>
    <table class="table table-centered mb-3">
        <thead>
        <tr>
            <th>Name</th>
            <th>Extension</th>
            <th>Tier Level</th>
            <th>Tier Position</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @if (count($assignedAgents) == 0)
            <tr>
                <td colspan="3">No agents assigned.</td>
            </tr>
        @else
            @foreach ($assignedAgents as $agent)
                <tr>
                    <td>{{ $agent->agent_name }}</td>
                    <td>{{ $agent->agent_id }}</td>
                    <td>
                        <select class="select2 form-control" data-toggle="select2" name="tiers[{{ $agent->call_center_agent_uuid }}][level]">
                            @for ($i = 0; $i < 10; $i++)
                                <option value="{{ $i }}" @if($agent->tier_level == $i) selected @endif >{{ $i }}</option>
                            @endfor
                        </select>
                    </td>
                    <td>
                        <select class="select2 form-control" data-toggle="select2" name="tiers[{{ $agent->call_center_agent_uuid }}][position]">
                            @for ($i = 0; $i < 10; $i++)
                                <option value="{{ $i }}" @if($agent->tier_position == $i) selected @endif >{{ $i }}</option>
                            @endfor
                        </select>
                    </td>
                    <td>
                        <div class="float-end">
                            <a class="dropdown-toggle arrow-down" href="#"
                               id="options{{ $agent->call_center_agent_uuid }}"
                               data-bs-toggle="dropdown"
                               data-bs-auto-close="false" aria-haspopup="true"
                               aria-expanded="false">
                                Options
                            </a>
                            <div class="dropdown-menu dropdown-menu-end"
                                 aria-labelledby="options{{ $agent->call_center_agent_uuid }}">
                                @if (userCheckPermission('contact_center_agent_unassign'))
                                    <button data-agent-id="{{$agent->call_center_agent_uuid}}" class="btn-link dropdown-item removeAgent">Remove</button>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
</div>
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('#addAgent').on('click', function(e) {
                e.preventDefault();
                $('.loading').show();
                //Reset error messages
                $('.error_message').text("");
                let url = '{{route('contactcenter.settings.agents.assign', ['callCenterQueues'=>$queue->call_center_queue_uuid,'callCenterAgents'=>':callCenterAgents'])}}';
                url = url.replace(':callCenterAgents', $('#selectAgent').select2('data')[0].id);
                $.ajax({
                    type: "GET",
                    url: url,
                    cache: false,
                    beforeSend: function() {
                        //Reset error messages
                        $('.error_message').text("");
                        $('.btn').attr('disabled', true);
                        $('.loading').show();
                    },
                    complete: function(xhr, status) {
                        $('.btn').attr('disabled', false);
                        $('.loading').hide();
                    },
                    success: function(result) {
                        $.NotificationApp.send("Success", result.message, "top-right", "#10c469", "success");
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

            $('.removeAgent').on('click', function(e) {
                e.preventDefault();
                $('.loading').show();
                $('.error_message').text("");
                let url = '{{route('contactcenter.settings.agents.unassign', ['callCenterQueues'=>$queue->call_center_queue_uuid,'callCenterAgents'=>':callCenterAgents'])}}';
                url = url.replace(':callCenterAgents', $(this).data('agentId'));
                $.ajax({
                    type: "GET",
                    url: url,
                    cache: false,
                    beforeSend: function() {
                        //Reset error messages
                        $('.error_message').text("");
                        $('.btn').attr('disabled', true);
                        $('.loading').show();
                    },
                    complete: function(xhr, status) {
                        $('.btn').attr('disabled', false);
                        $('.loading').hide();
                    },
                    success: function(result) {
                        $.NotificationApp.send("Success", result.message, "top-right", "#10c469", "success");
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
