-- push_wake.lua — wake an offline TalkPhone client via APNs / FCM.
--
-- Invoked from the dialplan before bridging to a user extension:
--
--   <action application="lua" data="push_wake.lua ${dialed_extension} ${domain_name} 20"/>
--
-- Behaviour:
--   1. If the user is already reachable on any registration, return
--      immediately — no push needed.
--   2. Otherwise look up the latest registration Contact URI, parse
--      RFC 8599 push params (pn-provider, pn-prid, pn-param, pn-env),
--      and POST them to the push relay (see server/push-relay).
--   3. Poll `sofia_contact` for up to `timeout_seconds` waiting for the
--      client to wake up and re-REGISTER. When it re-appears, return
--      so the dialplan can bridge the call.
--   4. If the timeout elapses, return anyway — the dialplan's bridge
--      will fall through to voicemail / its normal offline handling.
--
-- Required globals (set in freeswitch.xml / vars.xml or as chan vars):
--   push_relay_url     — e.g. http://127.0.0.1:8080/push
--   push_relay_token   — shared bearer token (matches AUTH_TOKEN)

local user             = argv[1]
local domain           = argv[2]
local timeout_seconds  = tonumber(argv[3]) or 20

if not user or user == "" or not domain or domain == "" then
    freeswitch.consoleLog("err",
        "[push_wake] missing user or domain (argv: user=" ..
        tostring(user) .. ", domain=" .. tostring(domain) .. ")\n")
    return
end

local target = user .. "@" .. domain
local api    = freeswitch.API()

-- ── 1. Already online? ─────────────────────────────────────────────
-- sofia_contact returns "error/user_not_registered" or an empty string
-- when the user is offline; otherwise it returns the contact URI.
local function is_online()
    local c = api:execute("sofia_contact", "*/" .. target) or ""
    return c ~= "" and not c:find("error", 1, true)
end

if is_online() then
    freeswitch.consoleLog("info",
        "[push_wake] " .. target .. " already online — skip push\n")
    return
end

-- ── 2. Look up push params from the last registration ─────────────
-- We read directly from the FreeSWITCH core DB so we can see the
-- Contact URI params even for a just-expired registration. On
-- FusionPBX / fspbx this uses the default core-db DSN.
--
-- Column layout for sip_registrations: sip_user, sip_host, contact,
-- expires, ... The `contact` field is the full URI with ;params.

local dbh = freeswitch.Dbh("core")
if not dbh:connected() then
    freeswitch.consoleLog("err", "[push_wake] cannot open core DB\n")
    return
end

local function sql_escape(s)
    return (s or ""):gsub("'", "''")
end

local last_contact
local q = string.format(
    "SELECT contact FROM sip_registrations " ..
    "WHERE sip_user = '%s' AND sip_host = '%s' " ..
    "ORDER BY expires DESC LIMIT 1",
    sql_escape(user), sql_escape(domain))

dbh:query(q, function(row)
    last_contact = row.contact
end)
dbh:release()

if not last_contact or last_contact == "" then
    freeswitch.consoleLog("info",
        "[push_wake] no registration on record for " .. target .. "\n")
    return
end

-- Extract a single URI param: value stops at ';', '>', or end-of-string.
local function pick(contact, key)
    return contact:match(";" .. key .. "=([^;>]+)")
end

local pn_provider = pick(last_contact, "pn%-provider")
local pn_prid     = pick(last_contact, "pn%-prid")
local pn_param    = pick(last_contact, "pn%-param")
local pn_env      = pick(last_contact, "pn%-env")

if not pn_provider or not pn_prid then
    freeswitch.consoleLog("info",
        "[push_wake] " .. target ..
        " has no pn-provider / pn-prid — client probably didn't register " ..
        "with push enabled (desktop?)\n")
    return
end

-- URI-decode percent escapes — the client percent-encodes param values
-- that aren't bare-safe per RFC 3261 §25.1.
local function urldecode(s)
    if not s then return s end
    return (s:gsub("%%(%x%x)", function(h)
        return string.char(tonumber(h, 16))
    end))
end

pn_prid  = urldecode(pn_prid)
pn_param = urldecode(pn_param)
pn_env   = urldecode(pn_env)

-- ── 3. POST to the push relay ─────────────────────────────────────
local relay_url   = env and env:getHeader("push_relay_url")
                    or api:getVar("push_relay_url")
                    or "http://127.0.0.1:8080/push"
local relay_token = env and env:getHeader("push_relay_token")
                    or api:getVar("push_relay_token")
                    or ""

local call_id = env and env:getHeader("variable_sip_call_id") or ""
local caller  = env and (env:getHeader("Caller-Caller-ID-Number") or
                         env:getHeader("Caller-ANI")) or ""

-- Build a JSON body. No external JSON lib needed — the payload is
-- flat and all values come from known-safe sources (we escape "
-- and \ just in case).
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

-- Prefer mod_curl's JSON-aware API if available, fall back to shelling
-- out to /usr/bin/curl otherwise.
local post_result
if api:execute("module_exists", "mod_curl") == "true" then
    local args = string.format(
        "%s post content-type:application/json" ..
        "%s %s",
        relay_url,
        relay_token ~= "" and (" authorization:Bearer\\ " .. relay_token) or "",
        body:gsub(" ", "\\ "))
    post_result = api:execute("curl", args)
else
    local hdr = relay_token ~= ""
        and ('-H "Authorization: Bearer ' .. relay_token .. '" ')
        or ''
    local cmd = string.format(
        'curl -fsS -m 5 -X POST ' ..
        '-H "Content-Type: application/json" %s-d @- "%s"',
        hdr, relay_url)
    local pipe = io.popen(cmd, "w")
    if pipe then
        pipe:write(body)
        pipe:close()
        post_result = "sent via /usr/bin/curl"
    else
        post_result = "curl pipe failed"
    end
end

freeswitch.consoleLog("info",
    "[push_wake] POST " .. relay_url .. " for " .. target ..
    " (provider=" .. pn_provider .. ") → " .. tostring(post_result) .. "\n")

-- ── 4. Wait for re-registration ───────────────────────────────────
local deadline = os.time() + timeout_seconds
while os.time() < deadline do
    freeswitch.msleep(500)
    if is_online() then
        freeswitch.consoleLog("info",
            "[push_wake] " .. target .. " re-registered after push\n")
        return
    end
end

freeswitch.consoleLog("warning",
    "[push_wake] " .. target .. " did not re-register within " ..
    timeout_seconds .. "s — bridge will likely fail over to voicemail\n")
