<div class="col-lg-4 col-md-6">
    <div class="panel panel-default" id="wifi-snr-widget">
            <div class="panel-heading" data-container="body" data-i18n="[title]wifi.snr_widget.tooltip">
                <h3 class="panel-title"><i class="fa fa-wifi"></i>
                    <span data-i18n="wifi.snr_widget.title">WiFi Signal Quality</span>
                    <list-link data-url="/show/listing/wifi/wifi"></list-link>
                </h3>
            </div>
        <div class="panel-body text-center"></div>
    </div><!-- /panel -->
</div><!-- /col -->

<script>
$(document).on('appReady appUpdate', function(e, lang) {

    $.getJSON( appUrl + '/module/wifi/get_snr_stats', function( data ) {

        if(data.error){
            //alert(data.error);
            return;
        }

        var panel = $('#wifi-snr-widget div.panel-body'),
            baseUrl = appUrl + '/show/listing/wifi/wifi';
        panel.empty();

        // Set statuses based on SNR values
        if(data.poor && data.poor != "0"){
            panel.append(' <a href="'+baseUrl+'#snr<20" class="btn btn-danger" data-toggle="tooltip" data-placement="bottom" title="'+i18n.t('wifi.snr_widget.poor')+' (SNR < 20)"><span class="bigger-150">'+data.poor+'</span><br>'+i18n.t('wifi.snr_widget.poor')+'</a>');
        }
        if(data.fair && data.fair != "0"){
            panel.append(' <a href="'+baseUrl+'#snr>=20,<25" class="btn btn-warning" data-toggle="tooltip" data-placement="bottom" title="'+i18n.t('wifi.snr_widget.fair')+' (SNR 20-24)"><span class="bigger-150">'+data.fair+'</span><br>'+i18n.t('wifi.snr_widget.fair')+'</a>');
        }
        if(data.good && data.good != "0"){
            panel.append(' <a href="'+baseUrl+'#snr>=25,<30" class="btn btn-info" data-toggle="tooltip" data-placement="bottom" title="'+i18n.t('wifi.snr_widget.good')+' (SNR 25-29)"><span class="bigger-150">'+data.good+'</span><br>'+i18n.t('wifi.snr_widget.good')+'</a>');
        }
        if(data.excellent && data.excellent != "0"){
            panel.append(' <a href="'+baseUrl+'#snr>=30" class="btn btn-success" data-toggle="tooltip" data-placement="bottom" title="'+i18n.t('wifi.snr_widget.excellent')+' (SNR ≥ 30)"><span class="bigger-150">'+data.excellent+'</span><br>'+i18n.t('wifi.snr_widget.excellent')+'</a>');
        }
        
        // Initialize tooltips
        $('#wifi-snr-widget .btn[data-toggle="tooltip"]').tooltip();
    });
});
</script> 