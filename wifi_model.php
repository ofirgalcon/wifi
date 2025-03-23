<?php

use CFPropertyList\CFPropertyList;

class Wifi_model extends \Model
{
    public function __construct($serial = '')
    {
        parent::__construct('id', 'wifi'); // Primary key, tablename
        $this->rs['id'] = "";
        $this->rs['serial_number'] = $serial;
        $this->rs['agrctlrssi'] = 0;
        $this->rs['agrextrssi'] = 0;
        $this->rs['agrctlnoise'] = 0;
        $this->rs['agrextnoise'] = 0;
        $this->rs['state'] = '';
        $this->rs['op_mode'] = '';
        $this->rs['lasttxrate'] = 0;
        $this->rs['lastassocstatus'] = '';
        $this->rs['maxrate'] = 0;
        $this->rs['x802_11_auth'] = '';
        $this->rs['link_auth'] = '';
        $this->rs['bssid'] = '';
        $this->rs['bssid_alias'] = null;
        $this->rs['ssid'] = '';
        $this->rs['mcs'] = 0;
        $this->rs['channel'] = '';
        $this->rs['snr'] = 0;
        $this->rs['known_networks'] = "";
        $this->rs['phy_mode'] = null;
        $this->rs['country_code'] = null;
        $this->rs['private_mac_address'] = null;
        $this->rs['private_mac_mode_user'] = null;

        if ($serial) {
            $this->retrieve_record($serial);
        }

        $this->serial = $serial;
    }

    // Process incoming data
    public function process($data)
    {
        if (strpos($data, '<?xml') === false) {
            // old style text processing
            //Initialize variables
            $this->agrctlrssi = '';
            $this->agrextrssi = '';
            $this->agrctlnoise = '';
            $this->agrextnoise = '';
            $this->state = 'no wifi';
            $this->op_mode = '';
            $this->lasttxrate = '';
            $this->lastassocstatus = '';
            $this->maxrate = '';
            $this->x802_11_auth = '';
            $this->link_auth = '';
            $this->bssid = '';
            $this->bssid_alias = '';
            $this->ssid = '';
            $this->mcs = '';
            $this->channel = '';

            // Translate network strings to db fields
            $translate = array(
                '     agrCtlRSSI: ' => 'agrctlrssi',
                '     agrExtRSSI: ' => 'agrextrssi',
                '    agrCtlNoise: ' => 'agrctlnoise',
                '    agrExtNoise: ' => 'agrextnoise',
                '          state: ' => 'state',
                '        op mode: ' => 'op_mode',
                '     lastTxRate: ' => 'lasttxrate',
                '        maxRate: ' => 'maxrate',
                'lastAssocStatus: ' => 'lastassocstatus',
                '    802.11 auth: ' => 'x802_11_auth',
                '      link auth: ' => 'link_auth',
                '          BSSID: ' => 'bssid',
                '           SSID: ' => 'ssid',
                '            MCS: ' => 'mcs',
                '        channel: ' => 'channel');

            // Parse data
            foreach (explode("\n", $data) as $line) {
                // Translate standard entries
                foreach ($translate as $search => $field) {
                    if (strpos($line, $search) === 0) {
                        $value = substr($line, strlen($search));

                        $this->$field = $value;
                        break;
                    }
                }
            } // end foreach explode lines
            $this->save();
            
            // Save the data record first
            $this->save();
            
            // Only proceed with BSSID alias if we have a valid BSSID
            if (!empty($this->bssid)) {
                // Normalize the BSSID to match what is in the YAML file
                $bssid = strtolower(trim($this->bssid));
                $bssid = preg_replace('/[^a-f0-9:]/', '', $bssid);
                $bssid_no_colons = str_replace(':', '', $bssid);
                
                // First try to use the WiFi controller to look up the alias
                require_once(__DIR__ . '/wifi_controller.php');
                $wifi_controller = new Wifi_controller();
                $alias = $wifi_controller->lookup_bssid_alias($this->bssid);
                
                if ($alias !== null) {
                    $this->bssid_alias = $alias;
                    $this->save();
                } else {
                    // If controller couldn't find an alias, try direct YAML parsing
                    $yaml_file = APP_ROOT . '/local/module_configs/bssid_aliases.yml';
                    if (file_exists($yaml_file)) {
                        // Read the YAML file directly
                        $yaml_content = file_get_contents($yaml_file);
                        if ($yaml_content !== false) {
                            // Parse the YAML file manually to find the BSSID's alias
                            $current_location = null;
                            $bssid_found = false;
                            $line_count = 0;
                            
                            foreach (explode("\n", $yaml_content) as $line) {
                                $line_count++;
                                $line = trim($line);
                                
                                // Skip empty lines and comments
                                if (empty($line) || $line[0] === '#') {
                                    continue;
                                }
                                
                                // Check if this is a location line (ends with a colon)
                                if (substr($line, -1) === ':') {
                                    $current_location = trim(trim(substr($line, 0, -1)), "'\"");
                                    continue;
                                }
                                
                                // If this is a BSSID line (starts with dash and space)
                                if (strpos($line, '- ') === 0) {
                                    $entry_bssid = trim(substr($line, 2));
                                    $entry_bssid = trim($entry_bssid, "'\"");
                                    
                                    // Normalize for comparison
                                    $entry_bssid = strtolower(trim($entry_bssid));
                                    $entry_bssid = preg_replace('/[^a-f0-9:]/', '', $entry_bssid);
                                    $entry_bssid_no_colons = str_replace(':', '', $entry_bssid);
                                    
                                    // Compare with and without colons
                                    if ($entry_bssid === $bssid || $entry_bssid_no_colons === $bssid_no_colons) {
                                        if ($current_location !== null) {
                                            $this->bssid_alias = $current_location;
                                            $this->save();
                                            $bssid_found = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            if (!$bssid_found) {
                                // Set to null if we couldn't find a match
                                if ($this->bssid_alias !== null) {
                                    $this->bssid_alias = null;
                                    $this->save();
                                }
                            }
                        }
                    }
                }
            } else {
                // Clear the alias if the BSSID is empty
                if ($this->bssid_alias !== null) {
                    $this->bssid_alias = null;
                    $this->save();
                }
            }
        } else { // Else process with new XML handler

            // Process incoming wifi.plist
            $parser = new CFPropertyList();
            $parser->parse($data, CFPropertyList::FORMAT_XML);
            $plist = $parser->toArray();
            
            // Process each of the items
            foreach (array('agrctlrssi', 'agrextrssi', 'agrctlnoise', 'agrextnoise', 'state', 'op_mode', 'lasttxrate', 'lastassocstatus', 'maxrate', 'x802_11_auth', 'link_auth', 'bssid', 'ssid', 'mcs', 'channel', 'snr', 'known_networks', 'phy_mode', 'country_code', 'private_mac_address', 'private_mac_mode_user') as $item) {

                // If key exists and is zero, set it to zero
                if (array_key_exists($item, $plist) && $plist[$item] === 0) {
                    $this->$item = 0;
                // Else if key does not exist in $plist, null it
                } else if (!array_key_exists($item, $plist) || $plist[$item] == '' || $plist[$item] == "{}" || $plist[$item] == "[]") {
                    $this->$item = null;
                // Set the db fields to be the same as those in the preference file
                } else {
                    $this->$item = $plist[$item];
                }
            }
            
            // Save the data
            $this->save();
        }
        
        // After processing either XML or text format, perform BSSID alias lookup
        $this->process_bssid_alias();
        
        return $this;
    }
    
    /**
     * Process BSSID alias lookup
     * This handles the lookup of BSSID aliases from YAML file or config
     */
    private function process_bssid_alias()
    {
        // Check for a valid BSSID
        if ($this->bssid === null || trim($this->bssid) === '') {
            // Clear alias if BSSID is empty
            if ($this->bssid_alias !== null) {
                $this->bssid_alias = null;
                $this->save();
            }
            return;
        }
        
        // Normalize the BSSID - lowercase, no spaces, only valid hex chars and colons
        $normalized_bssid = strtolower(trim($this->bssid));
        $normalized_bssid = preg_replace('/[^a-f0-9:]/', '', $normalized_bssid);
        
        // Look up the alias from the YAML file
        $yaml_file = APP_ROOT . '/local/module_configs/bssid_aliases.yml';
        if (!file_exists($yaml_file)) {
            return;
        }
        
        $yaml_content = file_get_contents($yaml_file);
        if ($yaml_content === false) {
            return;
        }
        
        // Simple YAML parsing - focus on finding the BSSID
        $current_location = null;
        $found = false;
        
        // Also try without colons for extra matching
        $no_colons_bssid = str_replace(':', '', $normalized_bssid);
        
        foreach (explode("\n", $yaml_content) as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Location line (ends with colon)
            if (substr($line, -1) === ':') {
                $current_location = trim(trim(substr($line, 0, -1)), "'\"");
                continue;
            }
            
            // BSSID line (starts with dash)
            if (strpos($line, '- ') === 0 && $current_location !== null) {
                $entry_bssid = trim(substr($line, 2), "'\"");
                $entry_bssid = strtolower(trim($entry_bssid));
                $entry_bssid = preg_replace('/[^a-f0-9:]/', '', $entry_bssid);
                $entry_no_colons = str_replace(':', '', $entry_bssid);
                
                // Compare both with and without colons
                if ($entry_bssid === $normalized_bssid || $entry_no_colons === $no_colons_bssid) {
                    $this->bssid_alias = $current_location;
                    $this->save();
                    $found = true;
                    break;
                }
            }
        }
        
        // If we didn't find a match and there's a current alias, clear it
        if (!$found && $this->bssid_alias !== null) {
            $this->bssid_alias = null;
            $this->save();
        }
    }
}
