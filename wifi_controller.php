<?php

/**
 * Wifi_controller class
 *
 * @package wifi
 * @author John Eberle
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
     * Retrieve data in json format
     *
     **/
    public function get_data($serial_number = '')
    {
        $obj = new View();

        if (! $this->authorized()) {
            $obj->view('json', array('msg' => 'Not authorized'));
            return;
        }

        $wifi = new wifi_model($serial_number);
        $obj->view('json', array('msg' => $wifi->rs));
    }
    
    /**
     * Get WiFi information for state widget
     *
     * @return void
     * @author John Eberle (tuxudo)
     **/
    public function get_wifi_state()
    {
        $obj = new View();

        if (! $this->authorized()) {
            $obj->view('json', array('msg' => array('error' => 'Not authenticated')));
            return;
        }
        
        $wifi = new wifi_model;
        $obj->view('json', array('msg' =>$wifi->get_wifi_state()));
    }
    
    /**
     * Get WiFi information for SSID widget
     *
     * @return void
     * @author John Eberle (tuxudo)
     **/
    public function get_wifi_name()
    {
        $obj = new View();

        if (! $this->authorized()) {
            $obj->view('json', array('msg' => array('error' => 'Not authenticated')));
            return;
        }
        
        $wifi = new wifi_model;
        $obj->view('json', array('msg' => $wifi->get_wifi_name()));
    }
    
    /**
    * Retrieve data in json format
    *
    * @return void
    * @author tuxudo
    **/
    public function get_tab_data($serial_number = '')
    {
        $obj = new View();

        if (! $this->authorized()) {
            $obj->view('json', array('msg' => 'Not authorized'));
            return;
        }
        
        $sql = "SELECT ssid, bssid, state, op_mode, x802_11_auth, link_auth, lasttxrate, maxrate, channel, agrctlrssi, agrctlnoise, snr, known_networks
                        FROM wifi 
                        WHERE serial_number = '$serial_number'";
        
        $queryobj = new Wifi_model();
        $wifi_tab = $queryobj->query($sql);
        $obj->view('json', array('msg' => current(array('msg' => $wifi_tab)))); 
    }
} // End class Wifi_controller
