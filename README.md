# redcap-notifications-api
REDCap notifications API 


### Key structure 
 Notifications are stored in either the REDCap EM Log table, or an optional redis cache.
 The general key structure for both options is the following:  
- `<TYPE>_<SERVER>_<ROLE>_<NOTIFICATION_ID>`
  - Where `TYPE` is one of the following `REDCAP PID`, `global` 
  - Where `SERVER` is one of the following: `prod`, `dev`, `both`
  - Where `ROLE` is one of the following: `all`, `admin`, `dc`

Example of a project specific notification key: `16023_prod_all_1`


### Redis implementation

Keys in Redis are stored as hashes based on the above key structure with one slight variation

Each notification ID will correspond to a `field` with a given value in Redis

The structure will resemble the following:

```json
  // Key => notification id => value
  16023_prod_all => 1 => {data}
  16023_prod_all => 2 => {data2}
  16024_prod_all => 1 => {data3}
```

Each key will have (1, inf) notifications set as field value pairs using HSET
 

