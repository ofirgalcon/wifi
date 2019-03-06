<h2 data-i18n="wifi.wifiinfo"></h2>
<div id="wifi-tab"></div>

<div id="wifi-msg" data-i18n="listing.loading" class="col-lg-12 text-center"></div>

<script>
$(document).on('appReady', function(){
    // Set blank tab badge
    $('#wifi-cnt').text("");

    $.getJSON(appUrl + '/module/wifi/get_tab_data/' + serialNumber, function(data){
                
        if( ! data ){
            $('#wifi-msg').text(i18n.t('no_data'));
        } else if (data[0]['state'] == 'no wifi') {
			$('#wifi-msg').text(i18n.t('wifi.no_wifi_client_tab'));
		} else if (data[0]['state'] == 'off') {
			$('#wifi-msg').text(i18n.t('wifi.off_client_tab'));
            // Update the tab badge
            $('#wifi-cnt').text(i18n.t('off'));
        } else {
            
            // Hide
            $('#wifi-msg').text('');
            $('#wifi-count-view').removeClass('hide');
            // Update the tab badge
            $('#wifi-cnt').text(i18n.t('on'));
            
            var skipThese = ['id', 'serial_number', 'known_networks'];
            $.each(data, function(i,d){

                // Generate rows from data
                var rows = ''
                var known_networks = false
                for (var prop in d){
                    // Skip skipThese
                    if(skipThese.indexOf(prop) == -1){
                        if (d[prop] == '' || d[prop] == null || d[prop] == "{}"){
                        // Do nothing for empty values to blank them
                        }

                        else if(prop == 'snr' && d[prop] !== null){
                           rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td><span title="'+i18n.t('wifi.snr_detail')+'">'+d[prop]+' db</span></td></tr>';
                        }
                        else if(prop == 'snr'){
                            // Calculate signal to noise ratio
                            snr_value = d['agrctlrssi']-d['agrctlnoise']
                            rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td><span title="'+i18n.t('wifi.snr_detail')+'">'+snr_value+' db</span></td></tr>';
                        }

                        else if(prop == 'lasttxrate' || prop == 'maxrate'){
                           rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td><span title="'+(d[prop]*0.125)+' MB/sec">'+d[prop]+" Mbps</span></td></tr>";
                        }

                        else if(prop == 'agrctlrssi'){
                           rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td><span title="'+i18n.t('wifi.rssi_detail')+'">'+d[prop]+' db</span></td></tr>';
                        }

                        else if(prop == 'agrctlnoise'){
                           rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td><span title="'+i18n.t('wifi.noise_detail')+'">'+d[prop]+' db</span></td></tr>';
                        }

                        else if(prop == 'state' || prop == 'link_auth'){
                           rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td>'+i18n.t('wifi.'+d[prop])+'</td></tr>';
                        }

                        else if(prop == 'x802_11_auth' && d[prop] == 'open'){
                           rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td>'+i18n.t('wifi.open')+'</td></tr>';
                        }

                        else if(prop == 'op_mode' && d[prop].indexOf("station") !== -1 ){
                           rows = rows + '<tr><th>'+i18n.t('wifi.'+prop)+'</th><td>'+i18n.t('wifi.station')+'</td></tr>';
                        }

                        else {
                            rows = rows + '<tr><th style="width: 200px;">'+i18n.t('wifi.'+prop)+'</th><td style="max-width: 500px;">'+d[prop]+'</td></tr>';
                        }
                    }
                }
                
                $('#wifi-tab')
                    .append($('<div>')
                        .append($('<table style="width: 450px;">')
                            .addClass('table table-striped table-condensed')
                            .append($('<tbody>')
                                .append(rows))))

                // Only draw the known networks table if there is something in it
                if (d["known_networks"]){
                    $('#wifi-tab')
                        .append('<div id="wifi_known_networks-table-view" class="row" style="padding-left: 15px; padding-right: 15px;"><h4>'+i18n.t('wifi.known_networks')+'</h4><table class="table table-striped table-condensed table-bordered" id="wifi_known_networks-table"><thead><tr><th data-colname="wifi.ssid">'+i18n.t('wifi.ssid')+'</th><th data-colname="wifi.security_type">'+i18n.t('wifi.security_type')+'</th><th data-colname="wifi.preferred_order">'+i18n.t('wifi.preferred_order')+'</th><th data-colname="wifi.last_connected">'+i18n.t('wifi.last_connected')+'</th><th data-colname="wifi.personal_hotspot">'+i18n.t('wifi.personal_hotspot')+'</th><th data-colname="wifi.possibly_hidden_network">'+i18n.t('wifi.possibly_hidden_network')+'</th><th data-colname="wifi.captive">'+i18n.t('wifi.captive')+'</th><th data-colname="wifi.network_was_captive">'+i18n.t('wifi.network_was_captive')+'</th><th data-colname="wifi.closed">'+i18n.t('wifi.closed')+'</th><th data-colname="wifi.disabled">'+i18n.t('wifi.disabled')+'</th><th data-colname="wifi.passpoint">'+i18n.t('wifi.passpoint')+'</th><th data-colname="wifi.roaming_profile_type">'+i18n.t('wifi.roaming_profile_type')+'</th><th data-colname="wifi.temporarily_disabled">'+i18n.t('wifi.temporarily_disabled')+'</th><th data-colname="wifi.bssid_list">'+i18n.t('wifi.bssid_list')+'</th></tr></thead><tbody><tr><td data-i18n="listing.loading" colspan="14" class="dataTables_empty"></td></tr></tbody></table></div>')
                    
                    
                        // Parse the JSON string into vaiable
                        var table_data = JSON.parse(d["known_networks"]);
                        var known_networks = true;
                        $('#wifi_known_networks-table').DataTable({

                            data: table_data,
                            order: [[0,'asc']],
                            autoWidth: false,
                            columns: [
                                { data: 'ssid' },
                                { data: 'security_type' },
                                { data: 'preferred_order' },
                                { data: 'last_connected' },
                                { data: 'personal_hotspot' },
                                { data: 'possibly_hidden_network' },
                                { data: 'captive' },
                                { data: 'network_was_captive' },
                                { data: 'closed' },
                                { data: 'disabled' },
                                { data: 'passpoint' },
                                { data: 'roaming_profile_type' },
                                { data: 'temporarily_disabled' },
                                { data: 'bssid_list' }
                            ],
                            createdRow: function( nRow, aData, iDataIndex ) {
                                    // Format date
                                    var event = parseInt($('td:eq(3)', nRow).html());
                                    var date = new Date(event * 1000);
                                    $('td:eq(3)', nRow).html('<span title="' + moment(date).fromNow() + '">'+moment(date).format('llll')+'</span>');
                                
                                    var colvar=$('td:eq(4)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(4)', nRow).html(colvar)
                                
                                    var colvar=$('td:eq(5)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(5)', nRow).html(colvar)
                                
                                    var colvar=$('td:eq(6)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(6)', nRow).html(colvar)
                                
                                    var colvar=$('td:eq(7)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(7)', nRow).html(colvar)
                                
                                    var colvar=$('td:eq(8)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(8)', nRow).html(colvar)
                                
                                    var colvar=$('td:eq(9)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(9)', nRow).html(colvar)
                                                                
                                    var colvar=$('td:eq(10)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(10)', nRow).html(colvar)
                                
                                    var colvar=$('td:eq(12)', nRow).html();
                                    colvar = colvar == '1' ? i18n.t('yes') :
                                    (colvar === '0' ? i18n.t('no') : '')
                                    $('td:eq(12)', nRow).html(colvar)
                            }
                        });
                }

            })
        }
    });
});
</script>