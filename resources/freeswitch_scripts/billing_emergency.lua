local M = {}

function M.is_emergency_destination(domain_uuid, destination_number)
    if not domain_uuid or not destination_number or destination_number == '' then
        return false
    end

    local Database = require "resources.functions.database"
    local dbh = Database.new('system')

    local sql = [[
        SELECT COUNT(*) AS emergency_count
        FROM emergency_calls
        WHERE domain_uuid = :domain_uuid
          AND emergency_number = :destination_number
    ]]

    local row = dbh:first_row(sql, {
        domain_uuid = domain_uuid,
        destination_number = destination_number,
    })
    dbh:release()

    return row and tonumber(row.emergency_count or 0) > 0
end

return M
