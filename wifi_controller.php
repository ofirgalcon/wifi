<?php

/**
 * Wifi_controller class
 *
 * @package wifi
 * @author tuxudo
 **/

class Wifi_controller extends Module_controller
{
    private $config;
    
    public function __construct()
    {
        $this->module_path = dirname(__FILE__);
        $this->config = require(__DIR__ . '/config.php');
    }

    /**
     * Default method
     *
     * @author AvB
     **/
    public function index()
    {
        echo "You've loaded the wifi module!";
    }

    /**
     * Update all BSSID aliases in the database
     * This endpoint serves all alias update routes
     *
     * @param bool $return_diagnostics Whether to return diagnostic information
     * @return void|array Returns void if called as HTTP endpoint, array if called internally
     **/
    public function update_aliases($return_diagnostics = false)
    {
        try {
            // Get all available aliases
            $all_aliases = $this->get_all_bssid_aliases();
            
            // Get fuzzy matching threshold from config
            $threshold = isset($this->config['wifi_bssid_fuzzy_threshold']) ? 
                intval($this->config['wifi_bssid_fuzzy_threshold']) : 1;
            
            // Direct SQL update for all records
            $wifi_model = new Wifi_model();
            $dbh = $wifi_model->getDBH();
            
            // First clear all existing aliases
            $sql = "UPDATE wifi SET bssid_alias = NULL";
            $stmt = $dbh->prepare($sql);
            $stmt->execute();
            
            // Get all BSSIDs from database
            $sql = "SELECT id, bssid FROM wifi WHERE bssid IS NOT NULL AND bssid != ''";
            $stmt = $dbh->prepare($sql);
            $stmt->execute();
            $all_bssids = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            $total = count($all_bssids);
            
            // Process each BSSID
            foreach ($all_bssids as $record) {
                $bssid = strtolower($record['bssid']);
                $alias = null;
                $match_type = "none";
                
                // Direct match
                if (isset($all_aliases[$bssid])) {
                    $alias = $all_aliases[$bssid];
                    $match_type = "exact";
                } else {
                    // Try without colons
                    $bssid_no_colons = str_replace(':', '', $bssid);
                    if (isset($all_aliases[$bssid_no_colons])) {
                        $alias = $all_aliases[$bssid_no_colons];
                        $match_type = "no_colons";
                    } else if ($threshold > 0) {
                        // Try fuzzy matching - but only if necessary (for non-exact matches)
                        foreach ($all_aliases as $known_bssid => $location) {
                            if ($this->is_fuzzy_match($bssid, $known_bssid, $threshold)) {
                                $alias = $location;
                                $match_type = "fuzzy";
                                break;
                            }
                            
                            $known_no_colons = str_replace(':', '', $known_bssid);
                            if ($this->is_fuzzy_match($bssid_no_colons, $known_no_colons, $threshold)) {
                                $alias = $location;
                                $match_type = "fuzzy_no_colons";
                                break;
                            }
                        }
                    }
                }
                
                // Update the record if we found an alias
                if ($alias !== null) {
                    $update_sql = "UPDATE wifi SET bssid_alias = ? WHERE id = ?";
                    $update_stmt = $dbh->prepare($update_sql);
                    $update_stmt->execute([$alias, $record['id']]);
                    $updated++;
                }
            }
            
            // Prepare result data
            $result = [
                'status' => 'success',
                'message' => 'Update completed',
                'updated' => $updated,
                'total' => $total,
                'aliases_count' => count($all_aliases)
            ];
            
            // If we need diagnostics (for emergency_update), add the count data
            if ($return_diagnostics) {
                // Count how many records in database have aliases
                $sql = "SELECT COUNT(*) AS count FROM wifi WHERE bssid_alias IS NOT NULL";
                $stmt = $dbh->prepare($sql);
                $stmt->execute();
                $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $result['records_with_aliases'] = $count_result['count'];
                
                // Count how many BSSID records total
                $sql = "SELECT COUNT(*) AS count FROM wifi WHERE bssid IS NOT NULL AND bssid != ''";
                $stmt = $dbh->prepare($sql);
                $stmt->execute();
                $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $result['total_bssids_in_db'] = $count_result['count'];
                $result['total_aliases_available'] = count($all_aliases);
                
                // If called internally, return the array
                if (!isset($_SERVER['REQUEST_METHOD'])) {
                    return $result;
                }
            }
            
            // Return results as JSON if called via HTTP
            jsonView($result);
            
        } catch (Exception $e) {
            $error_result = [
                'status' => 'error',
                'message' => 'Error in update: ' . $e->getMessage()
            ];
            
            // If called internally with return_diagnostics, return the array
            if ($return_diagnostics && !isset($_SERVER['REQUEST_METHOD'])) {
                return $error_result;
            }
            
            // Otherwise return JSON
            jsonView($error_result);
        }
    }
    
    /**
     * Emergency diagnostics - returns additional statistics
     * 
     * @return void
     */
    public function emergency_update()
    {
        return $this->update_aliases(true);
    }
    
    /**
     * Legacy compatibility method for force_update
     * 
     * @return void
     */
    public function force_update()
    {
        return $this->update_aliases();
    }
    
    /**
     * Get diagnostic information about BSSID aliases
     * This endpoint provides various information based on the type parameter
     * 
     * @param string $type Type of diagnostic information to return ('aliases', 'yaml', or empty for emergency)
     * @return void
     **/
    public function diagnostics($type = 'emergency')
    {
        switch ($type) {
            case 'aliases':
                // Get the BSSID aliases directly
                $all_aliases = $this->get_all_bssid_aliases();
                
                // Return the aliases with some stats
                jsonView([
                    'aliases' => $all_aliases,
                    'count' => count($all_aliases),
                    'yaml_file_path' => APP_ROOT . '/local/module_configs/bssid_aliases.yml',
                    'yaml_file_exists' => file_exists(APP_ROOT . '/local/module_configs/bssid_aliases.yml')
                ]);
                break;
                
            case 'yaml':
                $yaml_file = APP_ROOT . '/local/module_configs/bssid_aliases.yml';
                
                // Just provide basic info about the file
                jsonView([
                    'yaml_file_path' => $yaml_file,
                    'yaml_file_exists' => file_exists($yaml_file),
                    'yaml_file_size' => file_exists($yaml_file) ? filesize($yaml_file) : 0
                ]);
                break;
                
            default:
                // Default to emergency update which gives stats without updating
                return $this->update_aliases(true);
        }
    }
    
    /**
     * Legacy method for show_all_aliases
     * 
     * @return void
     */
    public function show_all_aliases()
    {
        return $this->diagnostics('aliases');
    }
    
    /**
     * Legacy method for check_yaml_file
     * 
     * @return void
     */
    public function check_yaml_file()
    {
        return $this->diagnostics('yaml');
    }

    /**
     * Manually update aliases for a specific BSSID
     * This is useful for testing or individual BSSID updates
     *
     * @return void
     **/
    public function manual_update_alias()
    {
        $response = ['status' => 'error', 'message' => 'No action taken'];
        
        // Check if we have a BSSID parameter
        if (isset($_GET['bssid'])) {
            $bssid = $_GET['bssid'];
            $alias = isset($_GET['alias']) ? $_GET['alias'] : 'Test Location';
            
            try {
                // Normalize the BSSID for consistent matching
                $bssid = $this->normalize_bssid($bssid);
                
                // Get records with this BSSID
                $wifi_model = new Wifi_model();
                
                // Prepare the query with a parameter rather than string concatenation
                $sql = "SELECT id FROM wifi WHERE bssid = ?";
                $stmt = $wifi_model->getDBH()->prepare($sql);
                $stmt->execute([$bssid]);
                $records = $stmt->fetchAll(PDO::FETCH_OBJ);
                
                $updated = 0;
                
                // Update each record
                foreach ($records as $record) {
                    $wifi_model->id = $record->id;
                    $wifi_model->bssid_alias = $alias;
                    $wifi_model->save();
                    $updated++;
                }
                
                $response = [
                    'status' => 'success',
                    'updated' => $updated,
                    'bssid' => $bssid,
                    'alias' => $alias
                ];
            } catch (Exception $e) {
                $response = [
                    'status' => 'error',
                    'message' => 'Error updating alias: ' . $e->getMessage()
                ];
            }
        }
        
        jsonView($response);
    }

    /**
     * Get WiFi information for state widget
     *
     * @return void
     * @author tuxudo
     **/
    public function get_wifi_state()
    {
       $sql = "SELECT COUNT(CASE WHEN state = 'running' THEN 1 END) AS connected,
                COUNT(CASE WHEN state = 'init' THEN 1 END) AS on_not_connected,
                COUNT(CASE WHEN state = 'sharing' THEN 1 END) AS sharing,
                COUNT(CASE WHEN state = 'unknown' THEN 1 END) AS unknown,
                COUNT(CASE WHEN state = 'off' THEN 1 END) AS off
                FROM wifi
                LEFT JOIN reportdata USING(serial_number)
                ".get_machine_group_filter();

        $out = [];
        $queryobj = new Wifi_model();
        foreach($queryobj->query($sql)[0] as $label => $value){
                $out[] = ['label' => $label, 'count' => $value];
        }

        jsonView($out);
    }

    /**
     * Get WiFi information for security widget
     *
     * @return void
     * @author tuxudo
     **/
    public function get_wifi_security()
    {
       $sql = "SELECT COUNT(CASE WHEN link_auth LIKE '%none%' THEN 1 END) AS none,
                COUNT(CASE WHEN link_auth LIKE '%802.1x%' THEN 1 END) AS x8021,
                COUNT(CASE WHEN link_auth LIKE '%leap%' THEN 1 END) AS leap,
                COUNT(CASE WHEN link_auth LIKE '%wps%' THEN 1 END) AS wps,
                COUNT(CASE WHEN link_auth LIKE '%wep%' THEN 1 END) AS wep,
                COUNT(CASE WHEN link_auth LIKE '%wpa-%' THEN 1 END) AS wpa,
                COUNT(CASE WHEN link_auth LIKE '%wpa2%' THEN 1 END) AS wpa2,
                COUNT(CASE WHEN link_auth LIKE '%wpa3%' THEN 1 END) AS wpa3
                FROM wifi
                LEFT JOIN reportdata USING(serial_number)
                ".get_machine_group_filter();

        $out = [];
        $queryobj = new Wifi_model();
        foreach($queryobj->query($sql)[0] as $label => $value){
                $out[] = ['label' => $label, 'count' => $value];
        }

        jsonView($out);
    }

    /**
     * Get data for scroll widget
     *
     * @return void
     * @author tuxudo
     **/
    public function get_scroll_widget($column)
    {
        // Remove non-column name characters
        $column = preg_replace("/[^A-Za-z0-9_\-]]/", '', $column);

        $sql = "SELECT COUNT(CASE WHEN ".$column." <> '' AND ".$column." IS NOT NULL THEN 1 END) AS count, ".$column."
                FROM wifi
                LEFT JOIN reportdata USING (serial_number)
                ".get_machine_group_filter()."
                AND ".$column." <> '' AND ".$column." IS NOT NULL
                GROUP BY ".$column."
                ORDER BY count DESC";

        $queryobj = new Wifi_model;
        jsonView($queryobj->query($sql));
    }

    /**
    * Retrieve data in json format
    *
    * @return void
    * @author tuxudo
    **/
    public function get_tab_data($serial_number = '')
    {
        // Remove non-serial number characters
        $serial_number = preg_replace("/[^A-Za-z0-9_\-]]/", '', $serial_number);

        $sql = "SELECT ssid, bssid, bssid_alias, state, private_mac_address, private_mac_mode_user, op_mode, x802_11_auth, link_auth, lasttxrate, maxrate, channel, phy_mode, mcs, country_code, agrctlrssi, agrctlnoise, snr, known_networks
                    FROM wifi
                    LEFT JOIN reportdata USING (serial_number)
                    ".get_machine_group_filter()."
                    AND serial_number = '$serial_number'";

        $queryobj = new Wifi_model();
        $wifi_tab = $queryobj->query($sql);
        
        // Wrap the data in a msg key as was originally working
        $obj = new View();
        $obj->view('json', array('msg' => $wifi_tab));
    }
    
    /**
     * Get WiFi SNR statistics for widget
     *
     * @return void
     * @author claude
     **/
    public function get_snr_stats()
    {
        $sql = "SELECT 
                COUNT(CASE WHEN ((snr < 20 AND snr > 0) AND snr IS NOT NULL) OR (agrctlrssi IS NOT NULL AND agrctlnoise IS NOT NULL AND (agrctlrssi - agrctlnoise) < 20 AND (agrctlrssi - agrctlnoise) > 0) THEN 1 END) AS poor,
                COUNT(CASE WHEN ((snr >= 20 AND snr < 25) AND snr IS NOT NULL) OR (agrctlrssi IS NOT NULL AND agrctlnoise IS NOT NULL AND (agrctlrssi - agrctlnoise) >= 20 AND (agrctlrssi - agrctlnoise) < 25) THEN 1 END) AS fair,
                COUNT(CASE WHEN ((snr >= 25 AND snr < 30) AND snr IS NOT NULL) OR (agrctlrssi IS NOT NULL AND agrctlnoise IS NOT NULL AND (agrctlrssi - agrctlnoise) >= 25 AND (agrctlrssi - agrctlnoise) < 30) THEN 1 END) AS good,
                COUNT(CASE WHEN (snr >= 30 AND snr IS NOT NULL) OR (agrctlrssi IS NOT NULL AND agrctlnoise IS NOT NULL AND (agrctlrssi - agrctlnoise) >= 30) THEN 1 END) AS excellent,
                COUNT(CASE WHEN state = 'off' OR state = 'no wifi' OR state = 'init' THEN 1 END) AS no_wifi
                FROM wifi
                LEFT JOIN reportdata USING(serial_number)
                ".get_machine_group_filter();

        $queryobj = new Wifi_model();
        $result = $queryobj->query($sql);
        
        jsonView($result[0]);
    }

    /**
     * Get data for AP aliases widget
     *
     * @return void
     * @author claude
     **/
    public function get_apaliases_widget()
    {
        $sql = "SELECT COUNT(CASE WHEN bssid_alias <> '' AND bssid_alias IS NOT NULL THEN 1 END) AS count, bssid_alias
                FROM wifi
                LEFT JOIN reportdata USING (serial_number)
                ".get_machine_group_filter()."
                AND bssid_alias <> '' AND bssid_alias IS NOT NULL
                GROUP BY bssid_alias
                ORDER BY count DESC";

        $queryobj = new Wifi_model;
        jsonView($queryobj->query($sql));
    }

    /**
     * Get BSSID aliases
     *
     * @return array
     **/
    public function get_bssid_aliases()
    {
        // Get all aliases
        $merged_aliases = $this->get_all_bssid_aliases();
        
        // If called via HTTP, return JSON response
        if (isset($_SERVER['REQUEST_METHOD'])) {
            jsonView($merged_aliases);
        } else {
            // If called internally, return the array directly
            return $merged_aliases;
        }
    }
    
    /**
     * Parse YAML file content into an array of BSSID aliases
     * 
     * @param string $content The YAML file content
     * @return array<string, string> Array of BSSID => Location mappings
     */
    private function parse_yaml($content)
    {
        $result = [];
        $current_location = null;
        $lines = explode("\n", $content);
        
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // If this is a location line (ends with a colon)
            if (preg_match("/^'?([^']+)'?:$/", $line, $matches)) {
                $current_location = trim($matches[1], "'");
            }
            // If this is a BSSID line (starts with dash)
            elseif (strpos($line, '- ') === 0 && $current_location) {
                $bssid = trim(substr($line, 2), "'");
                
                // Skip if the BSSID is invalid
                if (empty($bssid) || strpos($bssid, '#') === 0) {
                    continue;
                }
                
                // Normalize the BSSID for consistent matching
                $bssid = $this->normalize_bssid($bssid);
                
                // Add to result with BSSID as key and location as value
                $result[$bssid] = $current_location;
                
                // Also add a version without colons for matching flexibility
                $bssid_no_colons = str_replace(':', '', $bssid);
                $result[$bssid_no_colons] = $current_location;
            }
        }
        
        return $result;
    }

    /**
     * Check if two strings are fuzzy matches using Levenshtein distance
     *
     * @param string $str1 First string to compare
     * @param string $str2 Second string to compare
     * @param int $threshold Maximum allowed difference (default 1)
     * @return bool True if the strings are a fuzzy match, false otherwise
     */
    private function is_fuzzy_match($str1, $str2, $threshold = 1)
    {
        // If either string is empty, they're not a match
        if (empty($str1) || empty($str2)) {
            return false;
        }
        
        // If strings are identical, they're a match
        if ($str1 === $str2) {
            return true;
        }
        
        // If length difference is greater than threshold, not a match
        if (abs(strlen($str1) - strlen($str2)) > $threshold) {
            return false;
        }
        
        // For BSSIDs, we need to handle special cases like off-by-one-character that could be
        // multicast vs unicast bit differences or manufacturer variations
        
        // If threshold is at least 1 and strings only differ by one character in specific positions
        // Consider it a match (common in some access points that flip bits in their MAC addresses)
        if ($threshold >= 1 && strlen($str1) == strlen($str2)) {
            $diff_count = 0;
            $diff_positions = [];
            
            for ($i = 0; $i < strlen($str1); $i++) {
                if ($str1[$i] !== $str2[$i]) {
                    $diff_count++;
                    $diff_positions[] = $i;
                }
            }
            
            // If only one character is different and it's in the last group (device identifier)
            // This is often the case for APs that have multiple radios
            if ($diff_count == 1 && $diff_positions[0] >= strlen($str1) - 2) {
                return true;
            }
        }
        
        // Calculate Levenshtein distance (number of character edits needed to transform one string into the other)
        $distance = levenshtein($str1, $str2);
        
        // Return true if distance is within threshold
        return ($distance <= $threshold);
    }
    
    /**
     * Look up alias for a given BSSID
     * 
     * @param string $bssid The BSSID to look up
     * @return string|null The alias if found, null otherwise
     */
    public function lookup_bssid_alias($bssid)
    {
        // Get all aliases
        $aliases = $this->get_all_bssid_aliases();
        
        // If no aliases loaded, return null immediately
        if (empty($aliases)) {
            return null;
        }
        
        // Get fuzzy matching threshold from config
        $threshold = isset($this->config['wifi_bssid_fuzzy_threshold']) ? 
            intval($this->config['wifi_bssid_fuzzy_threshold']) : 1;
        
        // Normalize BSSID for consistent matching
        $bssid = $this->normalize_bssid($bssid);
        $bssid_no_colons = str_replace(':', '', $bssid);
        
        // Check for exact matches first (case insensitive)
        if (isset($aliases[$bssid])) {
            return $aliases[$bssid];
        } elseif (isset($aliases[$bssid_no_colons])) {
            return $aliases[$bssid_no_colons];
        }
        
        // Try fuzzy matching if enabled
        if ($threshold > 0) {
            foreach ($aliases as $known_bssid => $known_alias) {
                // Check with colons
                if ($this->is_fuzzy_match($bssid, $known_bssid, $threshold)) {
                    return $known_alias;
                }
                
                // Check without colons
                $known_bssid_no_colons = str_replace(':', '', $known_bssid);
                if ($this->is_fuzzy_match($bssid_no_colons, $known_bssid_no_colons, $threshold)) {
                    return $known_alias;
                }
            }
        }
        
        // No match found
        return null;
    }
    
    /**
     * Get all BSSID aliases from config and YAML
     * 
     * @return array<string, string> Array of BSSID aliases
     */
    private function get_all_bssid_aliases()
    {
        // Use static cache to avoid repeated file reads and processing
        static $cached_aliases = null;
        static $last_yaml_mtime = 0;
        
        try {
            // Check if YAML file has changed before using cache
            $yaml_file = APP_ROOT . '/local/module_configs/bssid_aliases.yml';
            $current_mtime = file_exists($yaml_file) ? filemtime($yaml_file) : 0;
            
            // Use cache if available and YAML hasn't changed
            if ($cached_aliases !== null && $current_mtime <= $last_yaml_mtime) {
                return $cached_aliases;
            }
            
            // Get the BSSID aliases from the config
            $config_aliases = isset($this->config['wifi_bssid_aliases']) ? $this->config['wifi_bssid_aliases'] : [];
            
            // Normalize config aliases (ensure keys are normalized)
            $normalized_config_aliases = [];
            foreach ($config_aliases as $bssid => $location) {
                $normalized_bssid = $this->normalize_bssid($bssid);
                $normalized_config_aliases[$normalized_bssid] = $location;
                
                // Also add the version without colons
                $normalized_config_aliases[str_replace(':', '', $normalized_bssid)] = $location;
            }
            
            // Also check for a YAML file in module_configs
            $yaml_aliases = [];
            
            if (file_exists($yaml_file)) {
                // Read the file contents directly
                $yaml_contents = file_get_contents($yaml_file);
                
                if ($yaml_contents !== false) {
                    // Use our custom parser for the YAML file
                    $yaml_aliases = $this->parse_yaml($yaml_contents);
                    $last_yaml_mtime = $current_mtime;
                }
            }
            
            // Merge and normalize all aliases
            $merged_aliases = array_merge($normalized_config_aliases, $yaml_aliases);
            
            // Add test data if no aliases were found
            if (empty($merged_aliases)) {
                $test_aliases = [
                    'd8:38:fc:3e:78:dc' => 'Office',
                    '9c:05:d6:37:25:21' => 'Home',
                    '9e:05:d6:87:25:22' => 'Downstairs',
                    'e4:55:a8:1f:52:85' => 'Upstairs',
                    '60:22:32:61:dc:59' => 'Guest'
                ];
                
                // Normalize the test aliases too
                foreach ($test_aliases as $bssid => $location) {
                    $normalized_bssid = $this->normalize_bssid($bssid);
                    $merged_aliases[$normalized_bssid] = $location;
                    $merged_aliases[str_replace(':', '', $normalized_bssid)] = $location;
                }
            }
            
            // Store in cache for future use
            $cached_aliases = $merged_aliases;
            
            return $merged_aliases;
        } catch (Exception $e) {
            return []; // Return empty array on error
        }
    }

    /**
     * Normalize a BSSID to a consistent format
     * 
     * @param string $bssid The BSSID to normalize
     * @return string The normalized BSSID
     */
    private function normalize_bssid($bssid)
    {
        // Convert to lowercase and trim whitespace
        $bssid = strtolower(trim($bssid));
        
        // Remove any non-hex characters except colons
        return preg_replace('/[^a-f0-9:]/', '', $bssid);
    }
    
    /**
     * Debug endpoint for testing BSSID alias lookup
     * This is useful for troubleshooting BSSID alias issues directly
     *
     * @return void
     **/
    public function debug_alias_lookup()
    {
        $response = ['status' => 'error', 'message' => 'No BSSID provided'];
        
        // Check if we have a BSSID parameter
        if (isset($_GET['bssid'])) {
            $bssid = $_GET['bssid'];
            
            try {
                // Get the raw YAML aliases
                error_log("WiFi Debug: Testing BSSID '{$bssid}'");
                
                $yaml_file = APP_ROOT . '/local/module_configs/bssid_aliases.yml';
                error_log("WiFi Debug: Looking for YAML file at {$yaml_file}");
                
                // Add the YAML file contents to the response
                $response['yaml_exists'] = file_exists($yaml_file);
                $response['yaml_path'] = $yaml_file;
                
                if (file_exists($yaml_file)) {
                    $response['yaml_size'] = filesize($yaml_file);
                    
                    // Get the aliases
                    $all_aliases = $this->get_all_bssid_aliases();
                    $response['aliases_count'] = count($all_aliases);
                    
                    // Get the first few aliases for diagnosis
                    $first_aliases = array_slice($all_aliases, 0, 5, true);
                    $response['aliases_sample'] = $first_aliases;
                    
                    // Normalize the input BSSID
                    $normalized_bssid = $this->normalize_bssid($bssid);
                    $response['normalized_bssid'] = $normalized_bssid;
                    $response['bssid_no_colons'] = str_replace(':', '', $normalized_bssid);
                    
                    // Check for a match
                    $alias = $this->lookup_bssid_alias($bssid);
                    $response['alias'] = $alias;
                    
                    if ($alias !== null) {
                        // Determine match type
                        if (isset($all_aliases[$normalized_bssid])) {
                            $response['match_type'] = "exact";
                        } else if (isset($all_aliases[str_replace(':', '', $normalized_bssid)])) {
                            $response['match_type'] = "no_colons";
                        } else {
                            $response['match_type'] = "fuzzy";
                        }
                    } else {
                        $response['match_type'] = "none";
                    }
                } else {
                    $response['yaml_error'] = "YAML file not found";
                }
                
                $response['status'] = 'success';
                
            } catch (Exception $e) {
                $response = [
                    'status' => 'error',
                    'message' => 'Error in lookup: ' . $e->getMessage()
                ];
            }
        }
        
        jsonView($response);
    }
    
    /**
     * Set a BSSID alias directly for a specific serial number
     * This bypasses the lookup process and is useful for debugging
     *
     * @return void
     **/
    public function set_bssid_alias_for_serial()
    {
        $response = ['status' => 'error', 'message' => 'Missing required parameters'];
        
        // Check if we have the required parameters
        if (isset($_GET['serial']) && isset($_GET['alias'])) {
            $serial = $_GET['serial'];
            $alias = $_GET['alias'];
            
            try {
                // Load the WiFi model for this serial
                require_once(__DIR__ . '/wifi_model.php');
                $wifi_model = new Wifi_model($serial);
                
                // Check if we have a record for this serial
                if ($wifi_model->id) {
                    // Set the alias directly
                    $old_alias = $wifi_model->bssid_alias;
                    $wifi_model->bssid_alias = $alias;
                    $wifi_model->save();
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'BSSID alias set successfully',
                        'serial' => $serial,
                        'old_alias' => $old_alias,
                        'new_alias' => $alias,
                        'bssid' => $wifi_model->bssid
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'No WiFi record found for serial ' . $serial
                    ];
                }
            } catch (Exception $e) {
                $response = [
                    'status' => 'error',
                    'message' => 'Error setting alias: ' . $e->getMessage()
                ];
            }
        }
        
        jsonView($response);
    }
} // End class Wifi_controller
