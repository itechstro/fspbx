local billing_emergency = require "billing_emergency"

domain_uuid = session:getVariable("domain_uuid")
destination_number = session:getVariable("destination_number")
call_direction = session:getVariable("call_direction")
is_internal_call = session:getVariable("from_user_exists")

if not domain_uuid then
    freeswitch.consoleLog("error", "[check-outbound-minutes] Missing domain_uuid")
    return
end

if call_direction ~= "outbound" then
    return
end

if billing_emergency.is_emergency_destination(domain_uuid, destination_number) then
    freeswitch.consoleLog("info", "[check-outbound-minutes] Emergency destination allowed: " .. tostring(destination_number))
    return
end

local Database = require "resources.functions.database"
local dbh = Database.new('system')

local function fetch_limit()
    local sql = [[
        SELECT domain_setting_value
        FROM v_domain_settings
        WHERE domain_uuid = :domain_uuid
          AND domain_setting_category = 'limit'
          AND domain_setting_subcategory = 'outbound_minutes_monthly'
          AND domain_setting_enabled = 'true'
        LIMIT 1
    ]]

    local row = dbh:first_row(sql, { domain_uuid = domain_uuid })
    if row and row.domain_setting_value and tonumber(row.domain_setting_value) then
        return tonumber(row.domain_setting_value)
    end

    sql = [[
        SELECT default_setting_value
        FROM v_default_settings
        WHERE default_setting_category = 'limit'
          AND default_setting_subcategory = 'outbound_minutes_monthly'
          AND default_setting_enabled = 'true'
        LIMIT 1
    ]]

    row = dbh:first_row(sql, {})
    if row and row.default_setting_value and tonumber(row.default_setting_value) then
        return tonumber(row.default_setting_value)
    end

    return nil
end

local function fetch_timezone()
    local sql = [[
        SELECT domain_setting_value
        FROM v_domain_settings
        WHERE domain_uuid = :domain_uuid
          AND domain_setting_subcategory = 'time_zone'
          AND domain_setting_enabled = 'true'
        LIMIT 1
    ]]

    local row = dbh:first_row(sql, { domain_uuid = domain_uuid })
    if row and row.domain_setting_value and row.domain_setting_value ~= '' then
        return row.domain_setting_value
    end

    sql = [[
        SELECT default_setting_value
        FROM v_default_settings
        WHERE default_setting_subcategory = 'time_zone'
          AND default_setting_enabled = 'true'
        LIMIT 1
    ]]

    row = dbh:first_row(sql, {})
    if row and row.default_setting_value and row.default_setting_value ~= '' then
        return row.default_setting_value
    end

    return 'UTC'
end

local limit_minutes = fetch_limit()
if not limit_minutes or limit_minutes <= 0 then
    dbh:release()
    return
end

local timezone = fetch_timezone()
local sql = [[
    SELECT COALESCE(SUM(
        CASE
            WHEN NULLIF(billsec, '') ~ '^[0-9]+$' THEN NULLIF(billsec, '')::bigint
            WHEN NULLIF(duration, '') ~ '^[0-9]+$' THEN NULLIF(duration, '')::bigint
            ELSE 0
        END
    ), 0) AS total_seconds
    FROM v_xml_cdr
    WHERE domain_uuid = :domain_uuid
      AND direction IN ('outbound', 'out')
      AND to_char(to_timestamp(NULLIF(start_epoch, '')::bigint) AT TIME ZONE :timezone, 'YYYY-MM') =
          to_char(timezone(:timezone, now()), 'YYYY-MM')
]]

local usage_row = dbh:first_row(sql, {
    domain_uuid = domain_uuid,
    timezone = timezone,
})
dbh:release()

local used_seconds = 0
if usage_row and usage_row.total_seconds then
    used_seconds = tonumber(usage_row.total_seconds) or 0
end

local used_minutes = used_seconds / 60
if used_minutes < limit_minutes then
    return
end

freeswitch.consoleLog(
    "warning",
    string.format(
        "[check-outbound-minutes] Monthly outbound limit reached for %s: %.2f / %d minutes",
        domain_uuid,
        used_minutes,
        limit_minutes
    )
)

if is_internal_call == "true" then
    if session:ready() then
        session:execute("playback", "silence_stream://1000")
    end
    if session:ready() then
        session:streamFile("ivr/ivr-phone_not_make_external_calls.wav")
    end
else
    if session:ready() then
        session:execute("playback", "silence_stream://1000")
    end
    if session:ready() then
        session:streamFile("ivr/ivr-no_route_destination.wav")
    end
end

if session:ready() then
    session:sleep(1000)
    session:hangup("CALL_REJECTED")
end
