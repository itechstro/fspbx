-- Runs on the customer leg of a callback. Connects only after the customer presses 1.
-- Usage: lua contact_center_callback_customer.lua <agent_uuid> <callback_uuid>

require "resources.functions.config"

local Database = require "resources.functions.database"

local SOUNDS = "/var/www/fspbx/resources/sounds/en/us/alloy/call_center/callbacks"
local api = freeswitch.API()
local agent_uuid = tostring(argv[1] or "")
local callback_uuid = tostring(argv[2] or "")

local function log(message)
	freeswitch.consoleLog("NOTICE", "[contact_center_callback_customer] " .. tostring(message) .. "\n")
end

local function finish(dbh, status, error_message, retry_seconds, attempts, max_attempts)
	local next_status = status
	local next_attempt = "NULL"
	if status == "retry" then
		local used = (tonumber(attempts) or 0) + 1
		if used >= (tonumber(max_attempts) or 1) then
			next_status = "failed"
		else
			next_status = "waiting"
			next_attempt = "CURRENT_TIMESTAMP + interval '" .. tostring(tonumber(retry_seconds) or 600) .. " seconds'"
		end
		dbh:query([[
			UPDATE contact_center_callbacks
			SET status = :status,
				attempts = attempts + 1,
				next_attempt_at = ]] .. next_attempt .. [[,
				last_error = :last_error,
				updated_at = CURRENT_TIMESTAMP
			WHERE contact_center_callback_uuid = :callback_uuid
		]], {
			status = next_status,
			last_error = error_message,
			callback_uuid = callback_uuid,
		})
		return
	end

	dbh:query([[
		UPDATE contact_center_callbacks
		SET status = :status, last_error = :last_error, updated_at = CURRENT_TIMESTAMP
		WHERE contact_center_callback_uuid = :callback_uuid
	]], {
		status = status,
		last_error = error_message,
		callback_uuid = callback_uuid,
	})
end

if agent_uuid == "" or callback_uuid == "" or not session or not session:ready() then return end

session:answer()
local dbh = Database.new("system")
if not (dbh and dbh:connected()) then
	session:hangup("NORMAL_CLEARING")
	return
end

local row = dbh:first_row([[
	SELECT c.name_path, c.attempts, s.max_attempts, s.retry_seconds, s.introduction, s.instruction
	FROM contact_center_callbacks c
	JOIN contact_center_callback_settings s ON s.queue_uuid = c.queue_uuid
	WHERE c.contact_center_callback_uuid = :callback_uuid
	LIMIT 1
]], { callback_uuid = callback_uuid })

if not row then
	api:executeString("uuid_kill " .. agent_uuid)
	session:hangup("NORMAL_CLEARING")
	return
end

if row.introduction and row.introduction ~= "" and row.introduction:sub(1, 1) == "/" then
	session:streamFile(row.introduction)
else
	session:streamFile(SOUNDS .. "/agent-introduction.wav")
end
if row.name_path and row.name_path ~= "" then
	session:streamFile(row.name_path)
end
if row.instruction and row.instruction ~= "" and row.instruction:sub(1, 1) == "/" then
	session:streamFile(row.instruction)
else
	session:streamFile(SOUNDS .. "/connection-instruction.wav")
end

local digit = session:playAndGetDigits(1, 1, 1, 8000, "#", SOUNDS .. "/connection-instruction.wav", "", "^[1]$")
if digit == "1" and session:ready() then
	dbh:query([[
		UPDATE contact_center_callbacks
		SET status = 'completed', updated_at = CURRENT_TIMESTAMP
		WHERE contact_center_callback_uuid = :callback_uuid
	]], { callback_uuid = callback_uuid })
	api:executeString("uuid_bridge " .. agent_uuid .. " " .. session:get_uuid())
	return
end

api:executeString("uuid_broadcast " .. agent_uuid .. " playback::" .. SOUNDS .. "/agent-no-answer.wav aleg")
api:executeString("sched_hangup +3 " .. agent_uuid)
finish(dbh, "retry", "The customer did not press 1.", row.retry_seconds, row.attempts, row.max_attempts)
log("Customer did not confirm callback " .. callback_uuid)
session:hangup("NORMAL_CLEARING")
