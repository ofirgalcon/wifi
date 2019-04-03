WiFi module
==============

Provides connected WiFi network information gathered by `/System/Library/PrivateFrameworks/Apple80211.framework/Versions/Current/Resources/airport -I` and various AirPort preferences

Client Preferences
---

It is possible to disable the collection of known networks on clients using the preference domain `org.munkireport.wifi` with boolean key `known_networks_disabled` set to `true`. Alternatively, you can run `sudo defaults write /Library/Preferences/org.munkireport.wifi.plist known_networks_disabled -bool true` on the client.

Remarks
---

* 'init' state indicates that WiFi is on, but not connected to any networks


Table Schema
---

* agrctlrssi (integer) Aggregate RSSI (decibels) 
* agrextrssi (integer) Aggregate external RSSI (decibels)
* agrctlnoise (integer) Aggregate noise (decibels)
* agrextnoise (integer) Aggregate external noise (decibels)
* state (string) WiFi state (running, init, off)
* op_mode (string) Access point mode
* lasttxrate (integer) Last transmit rate (Mbps)
* lastassocstatus (string) Last association status
* maxrate (integer) Maximum supported transmit rate (Mbps)
* x802_11_auth (string) Type of authentication
* link_auth (string) Link authentication type
* bssid (string) Access point's BSSID
* ssid (string) SSID or name of the connected wireless network
* mcs (string) Modulation and Coding Scheme
* channel (string) Channel of wireless network
* snr (integer) Signal to noise ratio
* known_networks (medium text) JSON string detailing known wireless networks