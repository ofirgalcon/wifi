<?php

use CFPropertyList\CFPropertyList;

class Wifi_model extends \Model
{
    public function __construct($serial = '')
    {
        parent::__construct('id', 'wifi'); //primary key, tablename
        $this->rs['id'] = 0;
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
        $this->rs['ssid'] = '';
        $this->rs['mcs'] = 0;
        $this->rs['channel'] = '';
        $this->rs['snr'] = 0;
        $this->rs['known_networks'] = "";
        
        if ($serial) {
            $this->retrieve_record($serial);
        }
        
        $this->serial = $serial;
    }

    
    /**
     * Get WiFi state for widget
     *
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
        return current($this->query($sql));
    }
    
    /**
     * Get WiFi names for widget
     *
     **/
    public function get_wifi_name()
    {
        $out = array();
        $sql = "SELECT COUNT(CASE WHEN ssid <> '' AND ssid IS NOT NULL THEN 1 END) AS count, ssid 
                FROM wifi
                LEFT JOIN reportdata USING (serial_number)
                ".get_machine_group_filter()."
                GROUP BY ssid
                ORDER BY count DESC";
        
        foreach ($this->query($sql) as $obj) {
            if ("$obj->count" !== "0") {
                $obj->ssid = $obj->ssid ? $obj->ssid : 'Unknown';
                $out[] = $obj;
            }
        }
        
        return $out;
    }
    
    
    public function process($data)
    {
        // Check if data has been passed to model
        if (! $data) {
            throw new Exception("Error Processing Request: No data found", 1);
        } else if (substr( $data, 0, 30 ) != '<?xml version="1.0" encoding="' ) { // Else if old style text, process with old text based handler
        
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

            // Delete previous entries

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
            } //end foreach explode lines
            $this->save();
        } else { // Else process with new XML handler

            // Process incoming wifi.plist
            $parser = new CFPropertyList();
            $parser->parse($data, CFPropertyList::FORMAT_XML);
            $plist = $parser->toArray();

            // Process each of the items
            foreach (array('agrctlrssi', 'agrextrssi', 'agrctlnoise', 'agrextnoise', 'state', 'op_mode', 'lasttxrate', 'lastassocstatus', 'maxrate', 'x802_11_auth', 'link_auth', 'bssid', 'ssid', 'mcs', 'channel', 'snr', 'known_networks') as $item) {

                // If key exists and is zero, set it to zero
                if ( array_key_exists($item, $plist) && $plist[$item] === 0) {
                    $this->$item = 0;
                // Else if key does not exist in $plist, null it
                } else if (! array_key_exists($item, $plist) || $plist[$item] == '' || $plist[$item] == "{}" || $plist[$item] == "[]") {
                    $this->$item = null;

                // Set the db fields to be the same as those in the preference file
                } else {
                    $this->$item = $plist[$item];
                }
            }

            // Save the data because bumblebees are fuzzy
            $this->save();
        }
    }
}
