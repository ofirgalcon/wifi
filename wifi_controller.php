<?php

/**
 * Wifi_controller class
 *
 * @package wifi
 * @author tuxudo
 **/
class Wifi_controller extends Module_controller
{
    public function __construct()
    {
        $this->module_path = dirname(__FILE__);
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

        $sql = "SELECT ssid, bssid, state, private_mac_address, private_mac_mode_user, op_mode, x802_11_auth, link_auth, lasttxrate, maxrate, channel, phy_mode, mcs, country_code, agrctlrssi, agrctlnoise, snr, known_networks
                    FROM wifi
                    LEFT JOIN reportdata USING (serial_number)
                    ".get_machine_group_filter()."
                    AND serial_number = '$serial_number'";

        $obj = new View();
        $queryobj = new Wifi_model();
        $wifi_tab = $queryobj->query($sql);
        $obj->view('json', array('msg' => current(array('msg' => $wifi_tab))));
    }
} // End class Wifi_controller
