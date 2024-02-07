local key = KEYS[1]
local cursor = ARGV[1]
local res = redis.call('HSCAN', key, cursor)
local rep = { res[1], {} }
for i=2, #res[2], 2 do
  table.insert(rep[2], res[2][i])
end
return rep
-- return cursor