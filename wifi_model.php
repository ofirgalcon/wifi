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
        } else { // Else process with new XML handler

            // Process incoming wifi.plist
            $parser = new CFPropertyList();
            $parser->parse($data, CFPropertyList::FORMAT_XML);
            $plist = $parser->toArray();

            // Process each of the items
            foreach (array('agrctlrssi', 'agrextrssi', 'agrctlnoise', 'agrextnoise', 'state', 'op_mode', 'lasttxrate', 'lastassocstatus', 'maxrate', 'x802_11_auth', 'link_auth', 'bssid', 'ssid', 'mcs', 'channel', 'snr', 'known_networks', 'phy_mode', 'country_code', 'private_mac_address', 'private_mac_mode_user') as $item) {

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
