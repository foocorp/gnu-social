$(function() {
    $.ajax({url:'check-fancy',
        type:'GET',
        success:function(data, textStatus) {
            $('#fancy-enable').prop('checked', true);
            $('#fancy-disable').prop('checked', false);
            $('#fancy-form_guide').text(data);
        },
        error:function(XMLHttpRequest, textStatus, errorThrown) {
            $('#fancy-enable').prop('checked', false);
            $('#fancy-disable').prop('checked', true);
            $('#fancy-enable').prop('disabled', true);
            $('#fancy-disable').prop('disabled', true);
            $('#fancy-form_guide').text("Fancy URL support detection failed, disabling this option. Make sure you renamed htaccess.sample to .htaccess.");
        }
    });
});

