$(document).ready(function() {

    // get current time from server
    var today = new Date($('now').val());

    $("#event-startdate").datepicker({
        // Don't let the user set a start date < before today
        minDate: today,
        onClose: onStartDateSelected
    });

    $("#event-enddate").datepicker({
        minDate: today,
        onClose: onEndDateSelected
    });

    $("#event-starttime").change(function(e) {
        var tz = $("#tz").val();

        var startDate = $("#event-startdate").val();
        var startTime = $("#event-starttime option:selected").val().replace(/(pm|am)/, ' $1');
        var startStr = startDate + ' ' + startTime + ' ' + tz;

        var endDate =  $("#event-enddate").val();
        var endTime = $("#event-endtime option:selected").val();
        var endStr = endDate + ' ' + endTime.replace(/(pm|am)/, ' $1') + ' ' + tz;

        // just need to compare hours
        var start = new Date(startStr);
        var end = new Date(endStr);

        updateTimes(startStr, (startDate === endDate), function (data) {
            var times = [];
            $.each(data, function(key, val) {
                times.push('<option value="' + key + '">' + val + '</option>');
            });
            $("#event-endtime").html(times.join(''));

            if (start > end) {
                $("#event-endtime").val(startTime).attr("selected", "selected");
            } else {
                $("#event-endtime").val(endTime).attr("selected", "selected");
            }
        });

    });

    $("#event-endtime").change(function(e) {
        var HOUR = 60 * 60 * 1000;
        var tz = $("#tz").val();
        var startDate = $("#event-startdate").val();
        var endDate = $("#event-enddate").val();
        var starttime = $("#event-starttime option:selected").val();
        var endtime = $("#event-endtime option:selected").val();
        var endtimeText = $("#event-endtime option:selected").text();

        // If the end time is in the next day then update the start date
        if (startDate === endDate) {
            var startstr = startDate + ' ' + starttime.replace(/(pm|am)/, ' $1') + ' ' + tz;
            var start = new Date(startstr);
            var matches = endtimeText.match(/\(.*\)/);
            var hours;
            if (matches) {
                hours = matches[0].substr(1).split(' ')[0]; // get x from (x hours)
                if (hours) {
                    if (hours == 30) {
                        hours = .5; // special case: x == 30 from (30 mins)
                    }
                    var end = new Date(start.getTime() + (hours * HOUR));
                    if (end.getDate() > start.getDate()) {
                        $("#event-enddate").datepicker('setDate', end);
                        var endstr = endDate + ' 12:00 am ' +  tz;
                        updateTimes(endstr, false, function(data) {
                            var times = [];
                            $.each(data, function(key, val) {
                                times.push('<option value="' + key + '">' + val + '</option>');
                            });
                            $("#event-endtime").html(times.join(''));

                            if (start > end) {
                                $("#event-endtime").val(starttime).attr("selected", "selected");
                            } else {
                                $("#event-endtime").val(endtime).attr("selected", "selected");
                            }
                        });
                    }
                }
            }
        }
    });

    function onStartDateSelected(dateText, inst) {
        var tz = $("#tz").val();
        var startTime = $("#event-starttime option:selected").val();
        var startDateTime = new Date(dateText + ' ' + startTime.replace(/(pm|am)/, ' $1') + ' ' + tz);

        // When we update the start date and time, we need to update the end date and time
        // to make sure they are equal or in the future
        $("#event-enddate").datepicker('option', 'minDate', startDateTime);

        recalculateTimes();
    }

    function onEndDateSelected(dateText, inst) {
        recalculateTimes();
    }

    function recalculateTimes(showDuration) {
        var tz = $("#tz").val();

        var startDate = $("#event-startdate").val();
        var startTime = $("#event-starttime option:selected").val();
        var startStr = startDate + ' ' + startTime.replace(/(pm|am)/, ' $1') + ' ' + tz;
        var startDateTime = new Date(startStr);

        var endDate = $("#event-enddate").val();
        var endTime = $("#event-endtime option:selected").val();
        var endDateTime = new Date(endDate + ' ' + endTime.replace(/(pm|am)/, ' $1') + ' ' + tz);
        var showDuration = true;

        if (endDateTime.getDate() !== startDateTime.getDate()) {
            starStr = endDate + ' 12:00 am ' +  tz;
            showDuration = false;
        }

        updateTimes(startStr, showDuration, function(data) {
            var times = [];
            $.each(data, function(key, val) {
                times.push('<option value="' + key + '">' + val + '</option>');
            });
            $("#event-endtime").html(times.join(''));
            if (startDateTime > endDateTime) {
                $("#event-endtime").val(startTime).attr("selected", "selected");
            } else {
                $("#event-endtime").val(endTime).attr("selected", "selected");
            }
        });
    }

    function updateTimes(start, duration, onSuccess) {
        $.getJSON($('#timelist_action_url').val(), {start: start, ajax: true, duration: duration}, onSuccess);
    }

});
