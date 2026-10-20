-- push_wake.lua — wake a TalkPhone client via the TalkGateway push relay.
--
-- TalkGateway keeps a SIP registration alive after iOS suspends the app,
-- so sofia still shows the extension as registered. That contact is the
-- gateway (x-talk-gateway=1), not a phone. This script still pushes, then
-- waits until a real handset contact comes back before the dialplan bridges.
--
--   <action application="lua" data="push_wake.lua ${dialed_extension} ${domain_name} 20"/>
--
-- Globals (vars.xml):
--   push_relay_url    http://talkgateway.<namespace>.svc.cluster.local:8080/push
--   push_relay_token  AUTH_TOKEN

local user            = argv[1]
local domain          = argv[2]
local timeout_seconds = tonumber(argv[3]) or 20

if not user or user == "" or not domain or domain == "" then
    freeswitch.consoleLog("err", "[push_wake] missing user or domain\n")
    return
end

local target = user .. "@" .. domain
local api    = freeswitch.API()

local function sofia_contact()
    return api:execute("sofia_contact", "*/" .. target) or ""
end

local function is_registered(c)
    return c ~= "" and not c:find("error", 1, true)
end

local function is_websocket(c)
    local lower = (c or ""):lower()
    return lower:find("transport=wss", 1, true)
        or lower:find("transport=ws", 1, true)
        or lower:find(";wss", 1, true)
end

local function is_gateway_contact(c)
    local lower = (c or ""):lower()
    -- Channel names drop the contact params, so match the gateway host too.
    return lower:find("x-talk-gateway=1", 1, true) ~= nil
        or lower:find("talkgateway.cloudplay.cloud", 1, true) ~= nil
end

local function split_contacts(c)
    local out = {}
    if not is_registered(c) then
        return out
    end
    for part in string.gmatch(c, "[^,]+") do
        local trimmed = part:match("^%s*(.-)%s*$")
        if trimmed ~= "" then
            table.insert(out, trimmed)
        end
    end
    return out
end

local function has_stationary_phone(c)
    for _, part in ipairs(split_contacts(c)) do
        if not is_gateway_contact(part) and not is_websocket(part) then
            return true
        end
    end
    return false
end

local function has_handset(c)
    for _, part in ipairs(split_contacts(c)) do
        if not is_gateway_contact(part) then
            return true
        end
    end
    return false
end

local function profile_from_contact(c)
    return (c or ""):match("^sofia/([^/]+)/") or "internal"
end

local function pick(contact, key)
    return (contact or ""):match(";" .. key .. "=([^;>]+)")
end

local function urldecode(s)
    if not s then return s end
    return (s:gsub("%%(%x%x)", function(h)
        return string.char(tonumber(h, 16))
    end))
end

local function sql_escape(s)
    return (s or ""):gsub("'", "''")
end

local contact = sofia_contact()

-- A desk phone that is actually online does not need a push.
if has_stationary_phone(contact) then
    freeswitch.consoleLog("info",
        "[push_wake] " .. target .. " has a non-gateway phone online — skip push\n")
    return
end

local script_dir = freeswitch.getGlobalVariable("script_dir")
    or "/usr/share/freeswitch/scripts"

local pn_provider, pn_prid, pn_param, pn_env

if is_registered(contact) then
    pn_provider = pick(contact, "pn%-provider")
    pn_prid     = urldecode(pick(contact, "pn%-prid"))
    pn_param    = urldecode(pick(contact, "pn%-param"))
    pn_env      = urldecode(pick(contact, "pn%-env"))
end

if not pn_provider or not pn_prid then
    local ok, tokens = pcall(dofile, script_dir .. "/push_tokens.lua")
    if ok and tokens and tokens.lookup then
        local stored = tokens.lookup(user, domain)
        if stored then
            pn_provider = stored.provider
            pn_prid     = stored.prid
            pn_param    = stored.param
            pn_env      = stored.env
        end
    end
end

if not pn_provider or not pn_prid then
    freeswitch.consoleLog("info",
        "[push_wake] " .. target .. " has no pn-provider / pn-prid\n")
    return
end

-- Drop a suspended phone's WebSocket row without removing the gateway
-- contact. flush_inbound_reg on the user would delete both.
local function flush_handset_websockets()
    local dbh = freeswitch.Dbh("core")
    if not dbh or not dbh:connected() then
        return false
    end
    local flushed = false
    local q = string.format(
        "SELECT call_id, contact, profile_name FROM sip_registrations " ..
        "WHERE sip_user = '%s' AND sip_host = '%s'",
        sql_escape(user), sql_escape(domain))
    dbh:query(q, function(row)
        local row_contact = row.contact or ""
        local call_id = row.call_id or ""
        if call_id ~= "" and is_websocket(row_contact) and not is_gateway_contact(row_contact) then
            local profile = row.profile_name
            if not profile or profile == "" then
                profile = profile_from_contact(row_contact)
            end
            freeswitch.consoleLog("info",
                "[push_wake] flushing stale " .. profile .. " WSS reg " ..
                call_id .. " for " .. target .. "\n")
            api:execute("sofia", "profile " .. profile .. " flush_inbound_reg " .. call_id)
            flushed = true
        end
    end)
    dbh:release()
    return flushed
end

local flushed = false
local flush_ok, flush_result = pcall(flush_handset_websockets)
if flush_ok then
    flushed = flush_result
else
    freeswitch.consoleLog("warning",
        "[push_wake] could not list registrations: " .. tostring(flush_result) .. "\n")
end
contact = sofia_contact()
if not flushed and is_websocket(contact) and not is_gateway_contact(contact) then
    local profile = profile_from_contact(contact)
    freeswitch.consoleLog("info",
        "[push_wake] flushing stale " .. profile .. " WSS reg for " .. target .. "\n")
    api:execute("sofia", "profile " .. profile .. " flush_inbound_reg " .. target)
end

local function global_var(name)
    local v = api:execute("global_getvar", name) or ""
    v = v:gsub("%s+$", "")
    if v == "" or v == "_undef_" then
        return ""
    end
    return v
end

-- Dialplan Lua has no env:getHeader, and api:getVar does not exist.
-- Globals come from vars.xml through global_getvar.
local relay_url = global_var("push_relay_url")
if relay_url == "" then
    relay_url = "http://127.0.0.1:8080/push"
end
local relay_token = global_var("push_relay_token")

-- Dialplan Lua sessions have getVariable, not env:getHeader.
local call_id = ""
local caller = ""
if session then
    call_id = session:getVariable("sip_call_id") or ""
    caller = session:getVariable("caller_id_number")
        or session:getVariable("ani") or ""
end

local function json_escape(s)
    if not s then return "" end
    return (s:gsub("\\", "\\\\"):gsub('"', '\\"'))
end

local body = string.format(
    '{"provider":"%s","token":"%s","param":"%s","call_id":"%s",' ..
    '"caller":"%s","callee":"%s","env":"%s"}',
    json_escape(pn_provider),
    json_escape(pn_prid),
    json_escape(pn_param or ""),
    json_escape(call_id),
    json_escape(caller),
    json_escape(target),
    json_escape(pn_env or ""))

local post_result
-- mod_curl splits arguments on spaces and cannot send "Bearer <token>".
local function shell_quote(s)
    return "'" .. (s or ""):gsub("'", "'\\''") .. "'"
end
local hdr = relay_token ~= ""
    and ("-H " .. shell_quote("Authorization: Bearer " .. relay_token) .. " ")
    or ""
local cmd = string.format(
    "curl -sS -m 5 -o /dev/null -w '%%{http_code}' -X POST " ..
    "-H 'Content-Type: application/json' %s--data-binary %s %s",
    hdr, shell_quote(body), shell_quote(relay_url))
local pipe = io.popen(cmd, "r")
if pipe then
    post_result = "HTTP " .. (pipe:read("*a") or "")
    pipe:close()
else
    post_result = "curl pipe failed"
end

freeswitch.consoleLog("info",
    "[push_wake] POST " .. relay_url .. " for " .. target ..
    " (provider=" .. pn_provider .. ") → " .. tostring(post_result) .. "\n")

-- bridge(user/) dials every contact, including the gateway. That contact is
-- not reachable from this host, so the leg never answers and the caller
-- stays in ringback after the handset has already responded. Bridge only
-- the handset here and hang up so the later bridge(user/) does not run.
local function handset_dialstring()
    local parts = {}
    for _, part in ipairs(split_contacts(sofia_contact())) do
        if not is_gateway_contact(part) then
            table.insert(parts, part)
        end
    end
    return table.concat(parts, ",")
end

local function bridge_handset()
    if session == nil or not session:ready() then
        freeswitch.consoleLog("warning",
            "[push_wake] no live session; cannot bridge " .. target .. "\n")
        return false
    end
    local dest = handset_dialstring()
    if dest == "" then
        return false
    end
    freeswitch.consoleLog("info",
        "[push_wake] bridging handset only for " .. target .. ": " .. dest .. "\n")
    -- One bridge. A second originate leaves the caller held on an INVITE
    -- the app is no longer answering.
    session:setVariable("hangup_after_bridge", "true")
    session:execute("bridge", "{leg_timeout=60}" .. dest)
    if session:ready() then
        local cause = session:getVariable("originate_disposition")
            or session:getVariable("bridge_hangup_cause")
            or "NORMAL_CLEARING"
        if cause == "SUCCESS" then
            cause = "NORMAL_CLEARING"
        end
        freeswitch.consoleLog("info",
            "[push_wake] handset bridge ended (" .. cause ..
            "); hanging up so the gateway contact is not dialed\n")
        session:hangup(cause)
    end
    return true
end

local deadline = os.time() + timeout_seconds
while os.time() < deadline do
    freeswitch.msleep(500)
    if has_handset(sofia_contact()) then
        freeswitch.consoleLog("info",
            "[push_wake] " .. target .. " handset registered after push\n")
        bridge_handset()
        return
    end
end

freeswitch.consoleLog("warning",
    "[push_wake] " .. target .. " handset did not register within " ..
    timeout_seconds .. "s\n")
