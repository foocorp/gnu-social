$(document).ready(
    function() {
	var form = $('#form_new_bookmark');
        form.append('<input type="hidden" name="ajax" value="1"/>');
        function doClose() {
            self.close();
            // If in popup blocker situation, we'll have to redirect back.
            setTimeout(function() {
                window.location = $('#url').val();
            }, 100);
        }
        form.ajaxForm({dataType: 'xml',
		       timeout: '60000',
                       beforeSend: function(formData) {
			   form.addClass('processing');
			   form.find('#submit').addClass('disabled');
		       },
                       error: function (xhr, textStatus, errorThrown) {
			   form.removeClass('processing');
			   form.find('#submit').removeClass('disabled');
               doClose();
		       },
                       success: function(data, textStatus) {
			   form.removeClass('processing');
			   form.find('#submit').removeClass('disabled');
                           doClose();
                       }});

    }
);