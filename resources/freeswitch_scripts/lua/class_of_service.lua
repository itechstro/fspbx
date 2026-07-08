-- class_of_service.lua
-- Enforces Class of Service destination allow/deny rules on outbound calls.
-- toll_allow route matching is handled separately by outbound dialplan conditions.
--
-- If Redis and Postgres are both unavailable, this script fails open.

DEBUG_MODE = false

local json = require "resources.functions.lunajson"
require "resources.functions.base64"

local cache_ttl = 43200
local redis_profile = "default"
local api = freeswitch.API()

local function log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, "[class_of_service.lua] " .. tostring(message) .. "\n")
    end
end

local function blank(value)
    return value == nil or tostring(value) == "" or tostring(value) == "_undef_"
end

local function redis(command)
    local ok, response = pcall(function()
        return api:execute("hiredis_raw", redis_profile .. " " .. command)
    end)

    if not ok then
        return nil
    end

    if blank(response) then
        return nil
    end

    return response
end

local function current_version(domain_uuid)
    local version = redis("GET class_of_service:version:" .. domain_uuid)
    version = blank(version) and "1" or tostring(version)
    return version
end

local function cache_key(version, domain_uuid, cos_uuid)
    return "class_of_service:profile:v" .. version .. ":" .. domain_uuid .. ":" .. cos_uuid
end

local function decode_profile(encoded)
    if blank(encoded) then
        return nil
    end

    local ok_decode, decoded = pcall(function()
        return base64.decode(encoded)
    end)
    if not ok_decode or blank(decoded) then
        return nil
    end

    local ok_json, profile = pcall(function()
        return json.decode(decoded)
    end)
    if not ok_json or type(profile) ~= "table" then
        return nil
    end

    return profile
end

local function encode_profile(profile)
    local ok_json, encoded_json = pcall(function()
        return json.encode(profile)
    end)
    if not ok_json or blank(encoded_json) then
        return nil
    end

    local ok_base64, encoded = pcall(function()
        return base64.encode(encoded_json)
    end)
    if not ok_base64 or blank(encoded) then
        return nil
    end

    return encoded
end

local function load_profile_from_database(domain_uuid, cos_uuid)
    local ok, profile = pcall(function()
        local Database = require "resources.functions.database"
        local dbh = Database.new("system")
        assert(dbh:connected())

        local row = nil
        local sql = "select class_of_service_uuid, default_action, enabled "
            .. "from v_class_of_service "
            .. "where domain_uuid = :domain_uuid "
            .. "and class_of_service_uuid = :class_of_service_uuid "
            .. "and enabled = 'true' "
            .. "limit 1"
        local params = {
            domain_uuid = domain_uuid,
            class_of_service_uuid = cos_uuid,
        }

        dbh:query(sql, params, function(result)
            row = result
        end)

        if row == nil then
            dbh:release()
            return nil
        end

        local destinations = {}
        local destination_sql = "select destination_prefix, destination_action, destination_order "
            .. "from v_class_of_service_destinations "
            .. "where class_of_service_uuid = :class_of_service_uuid "
            .. "and enabled = 'true' "
            .. "order by destination_order asc, destination_prefix asc"
        local destination_params = {
            class_of_service_uuid = cos_uuid,
        }

        dbh:query(destination_sql, destination_params, function(destination_row)
            table.insert(destinations, {
                destination_prefix = destination_row.destination_prefix,
                destination_action = destination_row.destination_action,
                destination_order = tonumber(destination_row.destination_order) or 100,
            })
        end)

        dbh:release()

        return {
            class_of_service_uuid = row.class_of_service_uuid,
            default_action = row.default_action or "allow",
            destinations = destinations,
        }
    end)

    if not ok then
        log("ERR", "Database lookup failed: " .. tostring(profile))
        return nil
    end

    return profile
end

local function load_profile(domain_uuid, cos_uuid, version)
    local key = cache_key(version, domain_uuid, cos_uuid)
    local cached = redis("GET " .. key)
    local profile = decode_profile(cached)

    if profile ~= nil then
        return profile
    end

    profile = load_profile_from_database(domain_uuid, cos_uuid)
    if profile == nil then
        return nil
    end

    local encoded = encode_profile(profile)
    if encoded ~= nil then
        redis("SETEX " .. key .. " " .. cache_ttl .. " " .. encoded)
    end

    return profile
end

local function digits_only(value)
    return tostring(value or ""):gsub("%D+", "")
end

local function prefix_matches(number, prefix)
    prefix = tostring(prefix or "")

    if prefix == "" then
        return false
    end

    if string.sub(prefix, -1) == "*" then
        local stem = string.sub(prefix, 1, -2)
        return stem == "" or string.sub(number, 1, string.len(stem)) == stem
    end

    return string.sub(number, 1, string.len(prefix)) == prefix
end

local function evaluate_profile(profile, number)
    if profile == nil then
        return true
    end

    local destinations = profile.destinations or {}
    for _, destination in ipairs(destinations) do
        if prefix_matches(number, destination.destination_prefix) then
            return destination.destination_action == "allow"
        end
    end

    return (profile.default_action or "allow") == "allow"
end

if not session or not session:ready() then
    return
end

local direction = session:getVariable("call_direction")
if direction ~= "outbound" then
    return
end

local domain_uuid = session:getVariable("domain_uuid")
local cos_uuid = session:getVariable("class_of_service_uuid")

if blank(domain_uuid) or blank(cos_uuid) then
    return
end

local destination_number = session:getVariable("destination_number")
if blank(destination_number) then
    destination_number = session:getVariable("sip_to_user")
end

local number = digits_only(destination_number)
if number == "" then
    return
end

local version = current_version(domain_uuid)
local profile = load_profile(domain_uuid, cos_uuid, version)

if profile == nil then
    log("WARNING", "No Class of Service profile found for extension. Failing open.")
    return
end

if not evaluate_profile(profile, number) then
    log("NOTICE", "Blocking outbound call to " .. number .. " for Class of Service " .. tostring(cos_uuid) .. ".")
    session:execute("playback", "ivr/ivr-call_cannot_be_completed_as_dialed.wav")
    session:hangup("OUTGOING_CALL_BARRED")
end
