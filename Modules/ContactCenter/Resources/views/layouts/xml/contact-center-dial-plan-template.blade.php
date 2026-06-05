<extension name="{{ $queue->queue_name }}" continue="" uuid="{{ $queue->dialplan_uuid }}">
	<condition field="destination_number" expression="^([^#]+#)(.*)$" break="never">
		<action application="set" data="caller_id_name=$2"/>
	</condition>
	<condition field="destination_number" expression="^(callcenter\+)?{{ $queue->queue_extension }}$">
		<action application="answer" data=""/>
		<action application="set" data="call_center_queue_uuid={{ $queue->call_center_queue_uuid }}"/>
		<action application="set" data="queue_extension={{ $queue->call_center_queue_uuid }}"/>
		<action application="set" data="cc_export_vars=${cc_export_vars},call_center_queue_uuid,sip_h_Alert-Info,absolute_codec_string"/>
		<action application="set" data="hangup_after_bridge=true"/>
		@if ($queue->queue_time_base_score_sec != '')<action application="set" data="cc_base_score={{ $queue->queue_time_base_score_sec }}"/>@endif

		@if (isset($recording_path) && $recording_path != '')<action application="playback" data="{{ $recording_path }}"/>@endif

		@if (strlen($queue->queue_cid_prefix) > 0)<action application="set" data="effective_caller_id_name={{ $queue->queue_cid_prefix }}#${caller_id_name}"/>@endif

		@if (strlen($queue->queue_cc_exit_keys) != '')<action application="set" data="cc_exit_keys={{ $queue->queue_cc_exit_keys }}"/>@endif

		<action application="callcenter" data="{{ $queue->queue_extension }}{{ '@' }}{{ Session::get('domain_name') }}"/>
		@if (!empty($queue->queue_timeout_action) && str_contains($queue->queue_timeout_action, ':'))
@php [$timeoutApp, $timeoutData] = explode(':', $queue->queue_timeout_action, 2); @endphp
		<action application="{{ $timeoutApp }}" data="{{ $timeoutData }}"/>
		@endif
	</condition>
</extension>
