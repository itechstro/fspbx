-- Speaks the caller's queue position at each position step of a ready audio sequence.
-- The announcement repeats on every loop. An unknown position is skipped.

local uuid = argv[1]
local queue_uuid = argv[2]
local extension = argv[3]
local domain = argv[4]

if not uuid or uuid == "" or not queue_uuid or queue_uuid == "" then
	return
end

local api = freeswitch.API()
local dbh = freeswitch.Dbh("system")
if not dbh:connected() then
	return
end

local row = dbh:first_row([[
	SELECT steps::text AS steps
	FROM contact_center_audio_configurations
	WHERE queue_uuid = :queue_uuid AND mode = 'sequence' AND status = 'ready'
	LIMIT 1
]], { queue_uuid = queue_uuid })

if not row or not row.steps then
	return
end

local period = 0
for duration in row.steps:gmatch('"duration_seconds"%s*:%s*(%d+)') do
	period = period + tonumber(duration)
end
if period < 1 then
	period = 60
end

local offsets = {}
local function add_offset(value)
	value = tonumber(value)
	if not value then
		return
	end
	for _, existing in ipairs(offsets) do
		if existing == value then
			return
		end
	end
	offsets[#offsets + 1] = value
end

for offset in row.steps:gmatch('"type"%s*:%s*"position".-"offset_seconds"%s*:%s*(%d+)') do
	add_offset(offset)
end
for offset in row.steps:gmatch('"offset_seconds"%s*:%s*(%d+).-"type"%s*:%s*"position"') do
	add_offset(offset)
end

if #offsets == 0 then
	return
end

local function alive()
	local exists = api:executeString("uuid_exists " .. uuid)
	return exists and exists:find("true")
end

local function position()
	local raw = api:executeString("callcenter_config queue list members " .. extension .. "@" .. domain)
	if not raw or raw == "" or raw:find("^-ERR") or raw:find("^+OK") then
		return nil
	end

	local mine = nil
	local members = {}
	for line in raw:gmatch("[^\r\n]+") do
		if line:find("|") and not line:find("^uuid|") then
			local fields = {}
			for field in (line .. "|"):gmatch("(.-)|") do
				fields[#fields + 1] = field
			end
			if #fields >= 14 then
				local item = {
					session = fields[2],
					state = fields[14],
					score = tonumber(fields[15]) or tonumber(fields[10]) or 0,
				}
				if item.session == uuid then
					mine = item
				end
				if item.state == "Waiting" or item.state == "Trying" then
					members[#members + 1] = item
				end
			end
		end
	end

	if not mine or (mine.state ~= "Waiting" and mine.state ~= "Trying") then
		return nil
	end

	local place = 1
	for _, item in ipairs(members) do
		if item.session ~= uuid and item.score < mine.score then
			place = place + 1
		end
	end

	return place
end

local started = os.time()
local next_at = {}
for index, offset in ipairs(offsets) do
	next_at[index] = offset
end

while alive() and os.time() - started < 1800 do
	local elapsed = os.time() - started
	for index, _ in ipairs(offsets) do
		if elapsed >= next_at[index] then
			local place = position()
			if place then
				api:executeString("uuid_broadcast " .. uuid .. " say::en\\snumber\\siterated\\s" .. tostring(place) .. " aleg")
			end
			next_at[index] = next_at[index] + period
		end
	end
	freeswitch.msleep(1000)
end
