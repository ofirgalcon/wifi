
<div id="lister" style="font-size: large; float: right;">
    <a href="/show/listing/wifi/wifi" title="List">
        <i class="btn btn-default tab-btn fa fa-list"></i>
    </a>
</div>
<div id="report_btn" style="font-size: large; float: right;">
    <a href="/show/report/wifi/wifi_report" title="Report">
        <i class="btn btn-default tab-btn fa fa-th"></i>
    </a>
</div>
<h2 data-i18n="wifi.wifiinfo"></h2>
<div id="wifi-tab"></div>

<div id="wifi-msg" data-i18n="listing.loading" class="col-lg-12 text-center"></div>

<script>
$(document).on('appReady', function(){
    // Cache jQuery selectors and i18n calls
    const $wifiMsg = $('#wifi-msg');
    const $wifiCnt = $('#wifi-cnt');
    const $wifiTab = $('#wifi-tab');
    const $machineHostname = $('.machine-hostname').parent().parent().parent();
    const t = i18n.t; // Cache translation function

    // Set blank tab badge once
    $wifiCnt.empty();

    // Create table templates once
    const tableTemplate = $('<table style="width: 450px;">').addClass('table table-striped table-condensed');
    const knownNetworksTemplate = `
        <div id="wifi_known_networks-table-view" class="row" style="padding: 0 15px;">
            <h4>${t('wifi.known_networks')}</h4>
            <table class="table table-striped table-condensed table-bordered" id="wifi_known_networks-table">
                <thead>
                    <tr>
                        ${['ssid', 'security_type', 'private_mac_address', 'private_mac_mode_user', 
                           'last_connected_system', 'last_connected_user', 'last_discovered_at', 'channel',
                           'personal_hotspot', 'autojoin_disabled', 'possibly_hidden_network', 'captive',
                           'passpoint', 'roaming_profile_type', 'add_reason', 'temporarily_disabled',
                           'standalone_6g', 'bssid_list']
                            .map(col => `<th data-colname="wifi.${col}">${t('wifi.' + col)}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
                    <tr><td data-i18n="listing.loading" colspan="18" class="dataTables_empty"></td></tr>
                </tbody>
            </table>
        </div>`;

    $.getJSON(appUrl + '/module/wifi/get_tab_data/' + serialNumber, function(data){
        if (!data) {
            $wifiMsg.text(t('no_data'));
            return;
        }

        const state = data[0].state;
        if (state === 'no wifi') {
            $wifiMsg.text(t('wifi.no_wifi_client_tab'));
            return;
        } 
        if (state === 'off') {
            $wifiMsg.text(t('wifi.off_client_tab'));
            $wifiCnt.text(t('off'));
            return;
        }

        // Process main wifi data
        $wifiMsg.empty();
        $wifiCnt.text(t('on'));

        const skipThese = new Set(['id', 'serial_number', 'known_networks']);
        const rows = [];
        const hasWiFi = $machineHostname.text().includes("Wi-Fi");
        const hostnameRows = [];

        $.each(data, function(i, d){
            for (const [prop, value] of Object.entries(d)) {
                if (skipThese.has(prop) || ((value === '' || value === null || value === "{}") && value !== "0")) continue;

                let row;
                switch(prop) {
                    case 'snr':
                        const snrValue = value !== null ? value : d.agrctlrssi - d.agrctlnoise;
                        row = `<tr><th>${t('wifi.' + prop)}</th><td><span title="${t('wifi.snr_detail')}">${snrValue} db</span></td></tr>`;
                        break;
                    case 'lasttxrate':
                    case 'maxrate':
                        row = `<tr><th>${t('wifi.' + prop)}</th><td><span title="${value * 0.125} MB/sec">${value} Mbps</span></td></tr>`;
                        break;
                    case 'agrctlrssi':
                    case 'agrctlnoise':
                        row = `<tr><th>${t('wifi.' + prop)}</th><td><span title="${t('wifi.' + prop + '_detail')}">${value} db</span></td></tr>`;
                        break;
                    case 'state':
                    case 'link_auth':
                        if (prop === 'state' || value.includes("-")) {
                            row = `<tr><th>${t('wifi.' + prop)}</th><td>${t('wifi.' + value)}</td></tr>`;
                        }
                        break;
                    case 'x802_11_auth':
                        if (value === 'open') {
                            row = `<tr><th>${t('wifi.' + prop)}</th><td>${t('wifi.open')}</td></tr>`;
                        }
                        break;
                    case 'op_mode':
                        if (value.includes("station")) {
                            row = `<tr><th>${t('wifi.' + prop)}</th><td>${t('wifi.station')}</td></tr>`;
                        }
                        break;
                    case 'ssid':
                    case 'bssid':
                        row = `<tr><th>${t('wifi.' + prop)}</th><td>${value}</td></tr>`;
                        if (hasWiFi) {
                            hostnameRows.push(`<tr><th>Wi-Fi ${t('wifi.' + prop)}</th><td>${prop === 'bssid' ? value.toUpperCase() : value}</td></tr>`);
                        }
                        break;
                    default:
                        row = `<tr><th style="width: 200px;">${t('wifi.' + prop)}</th><td style="max-width: 500px;">${value}</td></tr>`;
                }
                if (row) rows.push(row);
            }
        });

        // Append all rows at once
        $wifiTab.append(
            $('<div>').append(
                tableTemplate.clone().append($('<tbody>').html(rows.join('')))
            )
        );

        if (hostnameRows.length) {
            $machineHostname.append(hostnameRows.join(''));
        }

        // Process known networks
        const knownNetworks = data[0].known_networks;
        if (knownNetworks && (knownNetworks.includes(', "last_connected_user": "') || 
            knownNetworks.includes(', "private_mac_address": "'))) {
            
            $wifiTab.append(knownNetworksTemplate);
            
            const dateFormatter = date => `<span title="${moment(date).fromNow()}">${moment(date).format('llll')}</span>`;
            const boolFormatter = val => val == '1' ? t('yes') : (val === '0' ? t('no') : '');

            $('#wifi_known_networks-table').DataTable({
                data: JSON.parse(knownNetworks),
                order: [[0, 'asc']],
                autoWidth: false,
                columns: ['ssid', 'security_type', 'private_mac_address', 'private_mac_mode_user',
                         'last_connected_system', 'last_connected_user', 'last_discovered_at', 'channel',
                         'personal_hotspot', 'autojoin_disabled', 'possibly_hidden_network', 'captive',
                         'passpoint', 'roaming_profile_type', 'add_reason', 'temporarily_disabled',
                         'standalone_6g', 'bssid_list'].map(col => ({data: col})),
                createdRow: function(nRow, aData, iDataIndex) {
                    // Format dates
                    [4, 5, 6].forEach(index => {
                        const $cell = $('td:eq(' + index + ')', nRow);
                        const timestamp = parseInt($cell.html());
                        $cell.html(!isNaN(timestamp) ? dateFormatter(new Date(timestamp * 1000)) : '');
                    });

                    // Format booleans
                    [8, 9, 10, 11, 12, 15, 16].forEach(index => {
                        const $cell = $('td:eq(' + index + ')', nRow);
                        $cell.text(boolFormatter($cell.html()));
                    });
                }
            });
        }
    });
});
</script>