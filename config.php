<?php

return [
/*
|===============================================
| WiFi - BSSID Aliases
|===============================================
|
| List of BSSID to location name mappings
| The list is processed using case-insensitive matching
| Both formats (with and without colons) are supported
|
| Note: These can also be defined in local/module_configs/bssid_aliases.yml 
| in YAML format for more flexibility
|
*/
'wifi_bssid_aliases' => env('WIFI_BSSID_ALIASES', [
    // Define aliases here, or preferably in local/module_configs/bssid_aliases
]),

/*
|===============================================
| WiFi - BSSID Fuzzy Matching Threshold
|===============================================
|
| Maximum number of character differences allowed for fuzzy matching BSSIDs
| A value of 1-2 means BSSIDs that differ by up to 1-2 characters will be considered the same AP
| A value of 0 disables fuzzy matching (requires exact match)
|
*/
'wifi_bssid_fuzzy_threshold' => env('WIFI_BSSID_FUZZY_THRESHOLD', 0),
]; 

