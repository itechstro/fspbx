<configuration name="callcenter.conf" description="CallCenter">
	<settings>
		<!--<param name="odbc-dsn" value="dsn:user:pass"/>-->
		<!--<param name="dbname" value="/dev/shm/callcenter.db"/>-->
		<!--<param name="reserve-agents" value="true"/>-->
		<!--<param name="cc-instance-id" value="single_box"/>-->
	</settings>
	<queues>
		@foreach($queues as $queue)
			@php $queue_name = $queue->queue_name.'@'.$queue->domain()->first()->domain_name; @endphp;
			<queue name="{{$queue_name}}">
				<param name="strategy" value="{{$queue->queue_strategy}}">
				<param name="moh-sound" value="{{$queue->queue_moh_sound}}">
				<param name="record-template" value="{{$queue->queue_record_template}}">
				<param name="time-base-score" value="{{$queue->queue_time_base_score}}">
				<param name="max-wait-time" value="{{$queue->queue_max_wait_time}}">
				<param name="max-wait-time-with-no-agent" value="{{$queue->queue_max_wait_time_with_no_agent}}">
				<param name="max-wait-time-with-no-agent-time-reached" value="{{$queue->queue_max_wait_time_with_no_agent_time_reached}}">
				<param name="tier-rules-apply" value="{{$queue->queue_tier_rules_apply}}">
				<param name="tier-rule-wait-second" value="{{$queue->queue_tier_rule_wait_second}}">
				<param name="tier-rule-wait-multiply-level" value="{{$queue->queue_tier_rule_wait_multiply_level}}">
				<param name="tier-rule-no-agent-no-wait" value="true">
				<param name="discard-abandoned-after" value="{{$queue->queue_discard_abandoned_after}}">
				<param name="abandoned-resume-allowed" value="{{$queue->queue_abandoned_resume_allowed}}">
				<param name="announce-sound" value="{{$queue->queue_announce_sound}}">
				<param name="announce-frequency" value="{{$queue->queue_announce_frequency}}">
			</queue>
		@endforeach
	</queues>

	<!-- WARNING: Configuration of XML Agents will be updated into the DB upon restart. -->
	<!-- WARNING: Configuration of XML Tiers will reset the level and position if those were supplied. -->
	<!-- WARNING: Agents and Tiers XML config shouldn't be used in a multi FS shared DB setup (Not currently supported anyway) -->
	<agents>
		@foreach($agents as $agent)
			@php
				//if($agent->queue()->first()) {
				  print '<agent
							name="'.$agent->agent_id.'@'.$agent->domain()->first()->domain_name.'"
							type="'.$agent->agent_type.'"
							contact="{call_timeout='.$agent->agent_call_timeout.'}'.$agent->agent_contact.'"
							status="'.$agent->agent_status.'"
							no-answer-delay-time="'.$agent->agent_no_answer_delay_time.'"
							max-no-answer="'.$agent->agent_max_no_answer.'"
							wrap-up-time="'.$agent->agent_wrap_up_time.'"
							reject-delay-time="'.$agent->agent_reject_delay_time.'"
							busy-delay-time="'.$agent->agent_busy_delay_time.'"
					></agent>';
				//}
        	@endphp
		@endforeach
		<!--<agent name="1000@default" type="callback" contact="[leg_timeout=10]user/1000@default" status="Available" max-no-answer="3" wrap-up-time="10" reject-delay-time="10" busy-delay-time="60" />-->
	</agents>
	<tiers>
		@foreach($agents as $agent)
			@php
				$tier = $agent->tier()->first();
				if($tier && $queue = $tier->queue()->first()) {
            		if($tier->tier_level == 0) $tier->tier_level = 1;
                	if($tier->tier_position == 0) $tier->tier_position = 1;
                  	print '<tier
								agent="'.$agent->agent_id.'@'.$agent->domain()->first()->domain_name.'"
								queue="'.$queue->queue_extension.'@'.$agent->domain()->first()->domain_name.'"
								level="'.$tier->tier_level.'"
								position="'.$tier->tier_position.'">
					</tier>';
				}
			@endphp
		@endforeach
		<!-- If no level or position is provided, they will default to 1.  You should do this to keep db value on restart. -->
		<!-- <tier agent="1000@default" queue="support@default" level="1" position="1"/> -->
	</tiers>
</configuration>
