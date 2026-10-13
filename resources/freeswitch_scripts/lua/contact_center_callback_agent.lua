-- Runs on the agent leg after the agent answers a callback.
-- Usage: lua contact_center_callback_agent.lua <callback_uuid>

require "resources.functions.config"

local Database = require "resources.functions.database"

local SOUNDS = "/var/www/fspbx/resources/sounds/en/us/alloy/call_center/callbacks"
local api = freeswitch.API()
local callback_uuid = tostring(argv[1] or "")

local function log(message)
	freeswitch.consoleLog("NOTICE", "[contact_center_callback_agent] " .. tostring(message) .. "\n")
end

if callback_uuid == "" or not session or not session:ready() then return end

session:answer()
session:sleep(500)
if not session:ready() then return end

local dbh = Database.new("system")
if not (dbh and dbh:connected()) then
	session:hangup("NORMAL_CLEARING")
	return
end

local row = dbh:first_row([[
	SELECT c.number, c.name_path, c.attempts, c.domain_uuid, s.max_attempts, s.retry_seconds,
		d.domain_name, q.queue_extension
	FROM contact_center_callbacks c
	JOIN contact_center_callback_settings s ON s.queue_uuid = c.queue_uuid
	JOIN v_domains d ON d.domain_uuid = c.domain_uuid
	JOIN v_call_center_queues q ON q.call_center_queue_uuid = c.queue_uuid
	WHERE c.contact_center_callback_uuid = :callback_uuid
	LIMIT 1
]], { callback_uuid = callback_uuid })

if not row then
	log("Callback row was not found")
	session:hangup("NORMAL_CLEARING")
	return
end

dbh:query([[
	UPDATE contact_center_callbacks
	SET status = 'calling', updated_at = CURRENT_TIMESTAMP
	WHERE contact_center_callback_uuid = :callback_uuid
]], { callback_uuid = callback_uuid })

session:streamFile(SOUNDS .. "/agent-introduction.wav")
if not session:ready() then return end

local number = tostring(row.number or ""):gsub("%D", "")
local domain = tostring(row.domain_name or "")
local agent_uuid = session:get_uuid()
local destination = "loopback/" .. number .. "/" .. domain
local vars = "{ignore_early_media=true,originate_timeout=40,origination_caller_id_number="
	.. tostring(row.queue_extension or "callback") .. ",domain_name=" .. domain .. "}"

api:executeString("bgapi originate " .. vars .. destination
	.. " &lua(lua/contact_center_callback_customer.lua " .. agent_uuid .. " " .. callback_uuid .. ")")

session:streamFile("local_stream://default")
if session:ready() then
	session:hangup("NORMAL_CLEARING")
end
