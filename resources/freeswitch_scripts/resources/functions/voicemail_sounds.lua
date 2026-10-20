local M = {}

local function value(session, name, fallback)
    local selected = session:getVariable(name)
    if selected == nil or selected == '' then return fallback end
    return selected
end

local function expand_prefix(path, sounds_dir)
    if type(path) ~= 'string' or path == '' then return nil end
    path = path:gsub('%$%$%{sounds_dir%}', sounds_dir)
    path = path:gsub('%$%{sounds_dir%}', sounds_dir)
    return path:gsub('/+$', '')
end

-- A dialect such as "uk" selects say-module phrasing, but the Callie recordings
-- often remain installed under en/us. Treat the voice as installed only when a
-- password prompt is actually readable.
function M.voice_installed(path)
    if type(path) ~= 'string' or path == '' then return false end
    local probes = {
        '/voicemail/8000/vm-enter_pass.wav',
        '/voicemail/16000/vm-enter_pass.wav',
        '/voicemail/32000/vm-enter_pass.wav',
        '/voicemail/48000/vm-enter_pass.wav',
        '/voicemail/vm-enter_pass.wav',
    }
    for _, probe in ipairs(probes) do
        local handle = io.open(path .. probe, 'rb')
        if handle ~= nil then
            handle:close()
            return true
        end
    end
    return false
end

-- Phrase macros and native say otherwise replace the channel's sound_prefix
-- with the voice hardcoded in languages/<language>/<language>.xml. Use the
-- same language/dialect/voice as voicemail's direct file playback, for the
-- duration of voicemail only. When that directory has no voicemail prompts,
-- keep the call's existing sound prefix so a dialect with no sound pack
-- (for example en/uk while Callie lives in en/us) still plays. Restore the
-- caller's settings on errors too.
function M.with_voice(session, callback)
    local previous_prefix = session:getVariable('sound_prefix')
    local previous_enforced = session:getVariable('sound_prefix_enforced')
    local sounds_dir = value(session, 'sounds_dir', '/usr/share/freeswitch/sounds'):gsub('/+$', '')
    local selected = sounds_dir
        .. '/' .. value(session, 'default_language', 'en')
        .. '/' .. value(session, 'default_dialect', 'us')
        .. '/' .. value(session, 'default_voice', 'callie')
    local prefix = selected
    if not M.voice_installed(selected) then
        local current = expand_prefix(previous_prefix, sounds_dir)
        if M.voice_installed(current) then
            prefix = current
            if freeswitch ~= nil and freeswitch.consoleLog ~= nil then
                freeswitch.consoleLog('warning', '[voicemail] sound directory ' .. selected .. ' is not installed; using ' .. prefix .. '\n')
            end
        end
    end

    session:setVariable('sound_prefix', prefix)
    session:setVariable('sound_prefix_enforced', 'true')
    local ok, result = pcall(callback)
    session:setVariable('sound_prefix', previous_prefix)
    session:setVariable('sound_prefix_enforced', previous_enforced)
    if not ok then error(result, 0) end
    return result
end

return M
