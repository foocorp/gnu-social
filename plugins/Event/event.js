$(document).ready(function() {

    var today = new Date();

    $("#event-startdate").datepicker({
        // Don't let the user set a crazy start date
        minDate: today,
        onClose: function(dateText, picker) {
            // Don't let the user set a crazy end date
            var newStartDate = new Date(dateText);
            var endDate = new Date($("#event-startdate").val());
            if (endDate < newStartDate) {
                $("#event-enddate").val(dateText);
            }
            if (dateText !== null) {
                $("#event-enddate").datepicker('option', 'minDate', new Date(dateText));
            }
        },
        onSelect: function() {
            var startd = $("#event-startdate").val();
            var endd = $("#event-enddate").val();
            var sdate = new Date(startd);
            var edate = new Date(endd);
            if (sdate !== edate) {
                updateTimes();
            }
        }
    });

    $("#event-enddate").datepicker({
        minDate: today,
        onSelect: function() {
            var startd = $("#event-startdate").val();
            var endd = $("#event-enddate").val();
            var sdate = new Date(startd);
            var edate = new Date(endd);
            if (sdate !== edate) {
                updateTimes();
            }
        }
    });

    function updateTimes() {
        var startd = $("#event-startdate").val();
        var endd = $("#event-enddate").val();

        var startt = $("#event-starttime option:selected").val();
        var endt = $("#event-endtime option:selected").val();

        var sdate = new Date(startd + " " + startt);
        var edate = new Date(endd + " " + endt);
        var duration = (startd === endd);

        $.getJSON($('#timelist_action_url').val(),
            { start: startt, ajax: true, duration: duration },
            function(data) {
                var times = [];
                $.each(data, function(key, val) {
                times.push('<option value="' + key + '">' + val + '</option>');
            });

            $("#event-endtime").html(times.join(''));
            if (startt < endt) {
                $("#event-endtime").val(endt).attr("selected", "selected");
            }
        })
    }

    $("#event-starttime").change(function(e) {
        updateTimes();
    });

});
