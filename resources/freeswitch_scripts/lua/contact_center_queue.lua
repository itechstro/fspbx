-- Enter a Contact Center queue and capture a callback when the offer key is pressed.
-- Dialplan: lua contact_center_queue.lua

require "resources.functions.config"

local Database = require "resources.functions.database"

local SOUNDS = "/var/www/fspbx/resources/sounds/en/us/alloy/call_center/callbacks"
local api = freeswitch.API()

local function log(message)
	freeswitch.consoleLog("NOTICE", "[contact_center_queue] " .. tostring(message) .. "\n")
end

local function play(path)
	if session:ready() and path and path ~= "" then
		session:streamFile(path)
	end
end

local function sql_text(value)
	return tostring(value or ""):gsub("'", "''")
end

local function return_to_queue(waited)
	if not session:ready() then return end
	session:setVariable("cc_callback_used", "true")
	if waited and waited ~= "" then
		session:setVariable("cc_base_score", tostring(waited))
	end
	local extension = session:getVariable("queue_extension") or ""
	local domain = session:getVariable("domain_name") or ""
	session:transfer(extension, "XML", domain)
end

local function capture(dbh, queue_uuid, domain_uuid, offer_key)
	local caller = session:getVariable("caller_id_number") or ""
	caller = caller:gsub("%D", "")
	play(SOUNDS .. "/number.wav")
	if caller ~= "" then
		session:say(caller, "en", "number", "iterated")
	end
	play(SOUNDS .. "/accept-number.wav")

	local choice = ""
	if caller ~= "" then
		choice = session:playAndGetDigits(1, 1, 3, 8000, "#", SOUNDS .. "/accept-number.wav", "", "^[123]$")
	else
		choice = "2"
	end

	if choice == "3" or choice == "" then
		local joined = session:getVariable("cc_queue_joined_epoch")
		local waited = "0"
		if joined and joined ~= "" then
			waited = tostring(os.time() - tonumber(joined))
		end
		return_to_queue(waited)
		return
	end

	local number = caller
	if choice == "2" or caller == "" then
		play(SOUNDS .. "/callback-number.wav")
		number = session:playAndGetDigits(3, 20, 3, 15000, "#", SOUNDS .. "/callback-number.wav", "", "^[0-9]+$")
	end

	if not number or number == "" then
		return_to_queue(session:getVariable("cc_base_score"))
		return
	end

	local name_path = "/var/lib/freeswitch/recordings/contact-center-callbacks"
	os.execute("mkdir -p " .. name_path)
	local call_uuid = session:get_uuid()
	local recording = name_path .. "/" .. call_uuid .. ".wav"
	play(SOUNDS .. "/record-name.wav")
	session:execute("record", recording .. " 10 200 3")
	play(recording)
	play(SOUNDS .. "/review-name.wav")
	local accept = session:playAndGetDigits(1, 1, 2, 8000, "#", SOUNDS .. "/review-name.wav", "", "^[12]$")
	if accept == "2" then
		play(SOUNDS .. "/record-name.wav")
		session:execute("record", recording .. " 10 200 3")
	end

	local size = 0
	local handle = io.popen("wc -c < " .. recording)
	if handle then
		size = tonumber(handle:read("*a")) or 0
		handle:close()
	end
	local digest = call_uuid
	local sha = io.popen("sha256sum " .. recording)
	if sha then
		digest = (sha:read("*l") or call_uuid):match("^(%w+)") or call_uuid
		sha:close()
	end

	local joined = tonumber(session:getVariable("cc_queue_joined_epoch") or "") or os.time()
	local score = tonumber(session:getVariable("cc_base_score") or "") or math.max(0, os.time() - joined)
	local callback_uuid = (api:executeString("create_uuid") or ""):gsub("%s+", "")
	local node = api:executeString("hostname") or "local"
	node = tostring(node):sub(1, 32)

	dbh:query([[
		INSERT INTO contact_center_callbacks (
			contact_center_callback_uuid, domain_uuid, queue_uuid, original_call_uuid, source_node,
			number, name_path, name_sha256, name_bytes, media_ready_at, joined_epoch, base_score,
			status, attempts, next_attempt_at, expires_at, created_at, updated_at
		)
		SELECT :callback_uuid, :domain_uuid, :queue_uuid, :call_uuid, :source_node,
			:number, :name_path, :name_sha256, :name_bytes, CURRENT_TIMESTAMP, :joined_epoch, :base_score,
			'waiting', 0, CURRENT_TIMESTAMP,
			((CURRENT_TIMESTAMP AT TIME ZONE timezone)::date + closing_time::time) AT TIME ZONE timezone,
			CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
		FROM contact_center_callback_settings
		WHERE queue_uuid = :queue_uuid AND enabled = true
	]], {
		callback_uuid = callback_uuid,
		domain_uuid = domain_uuid,
		queue_uuid = queue_uuid,
		call_uuid = call_uuid,
		source_node = node,
		number = number,
		name_path = recording,
		name_sha256 = digest,
		name_bytes = size,
		joined_epoch = joined,
		base_score = score,
	})

	local saved = dbh:first_row([[
		SELECT contact_center_callback_uuid
		FROM contact_center_callbacks
		WHERE original_call_uuid = :call_uuid
		LIMIT 1
	]], { call_uuid = call_uuid })

	if not saved then
		log("Callback insert failed for " .. tostring(call_uuid))
		play(SOUNDS .. "/unavailable.wav")
		return_to_queue(tostring(score))
		return
	end

	play(SOUNDS .. "/accepted.wav")
	session:hangup("NORMAL_CLEARING")
end

if not session or not session:ready() then return end

local queue_uuid = session:getVariable("call_center_queue_uuid") or ""
local extension = session:getVariable("queue_extension") or ""
local domain = session:getVariable("domain_name") or ""
local domain_uuid = session:getVariable("domain_uuid") or ""

if queue_uuid == "" or extension == "" or domain == "" then
	log("Missing queue variables")
	return
end

local dbh = Database.new("system")
if not (dbh and dbh:connected()) then
	session:execute("callcenter", extension .. "@" .. domain)
	return
end

if domain_uuid == "" then
	local domain_row = dbh:first_row([[
		SELECT domain_uuid FROM v_domains WHERE domain_name = :domain_name LIMIT 1
	]], { domain_name = domain })
	domain_uuid = domain_row and tostring(domain_row.domain_uuid or "") or ""
end

local settings = dbh:first_row([[
	SELECT enabled, offer_key, offer_after_seconds,
		CASE
			WHEN ((CURRENT_TIMESTAMP AT TIME ZONE timezone)::date + closing_time::time) AT TIME ZONE timezone <= CURRENT_TIMESTAMP
			THEN 'true' ELSE 'false'
		END AS closed
	FROM contact_center_callback_settings
	WHERE queue_uuid = :queue_uuid
	LIMIT 1
]], { queue_uuid = queue_uuid })

local offer_key = settings and tostring(settings.offer_key or "") or ""
local already_used = session:getVariable("cc_callback_used") == "true"
local closed = settings and tostring(settings.closed) == "true"

local audio = dbh:first_row([[
	SELECT mode, steps::text AS steps
	FROM contact_center_audio_configurations
	WHERE queue_uuid = :queue_uuid AND mode = 'sequence' AND status = 'ready'
	LIMIT 1
]], { queue_uuid = queue_uuid })

local uuid = session:get_uuid()
if audio and audio.steps then
	api:executeString("luarun lua/contact_center_sequence.lua " .. uuid .. " " .. queue_uuid .. " " .. extension .. " " .. domain)
end

local sequence_callback = audio and audio.steps and audio.steps:find('"type"%s*:%s*"callback"')
local enabled = settings and tostring(settings.enabled or "") or ""
local offer_allowed = not audio or sequence_callback
if settings and (enabled == "true" or enabled == "t" or enabled == "1") and offer_key ~= "" and not already_used and not closed and offer_allowed then
	local existing = session:getVariable("cc_exit_keys") or ""
	if not existing:find(offer_key, 1, true) then
		existing = existing .. offer_key
	end
	session:setVariable("cc_exit_keys", existing)

	local delay = tonumber(settings.offer_after_seconds) or 90
	if sequence_callback then
		local offset = audio.steps:match('"type"%s*:%s*"callback".-"offset_seconds"%s*:%s*(%d+)')
			or audio.steps:match('"offset_seconds"%s*:%s*(%d+).-"type"%s*:%s*"callback"')
		if offset then delay = tonumber(offset) or delay end
	end

	api:executeString("sched_broadcast +" .. tostring(delay) .. " " .. uuid .. " " .. SOUNDS .. "/offer.wav aleg")
elseif closed and not already_used then
	-- The offer is not played after the daily cutoff. Callers stay in the queue.
end

local waited = session:getVariable("cc_base_score")
if waited and waited ~= "" then
	session:setVariable("cc_base_score", waited)
end

session:execute("callcenter", extension .. "@" .. domain)

if session:ready() and settings and tostring(session:getVariable("cc_exit_key") or "") == offer_key and not already_used then
	capture(dbh, queue_uuid, domain_uuid, offer_key)
end
