var SN_EXTENDED = SN_EXTENDED || {};

SN_EXTENDED.reorder = function (cls) {

    var divs = $('div[class=' + cls + ']');

    $(divs).each(function (i, div) {
        $(div).find('a.add_row').hide();
        $(div).find('a.remove_row').show();
        SN_EXTENDED.replaceIndex(SN_EXTENDED.rowIndex(div), i);
    });

    var lastDiv = $(divs).last().closest('tr');
    lastDiv.addClass('supersizeme');

    $(divs).last().find('a.add_row').show();

    if (divs.length == 1) {
        $(divs).find('a.remove_row').fadeOut("slow");
    }
};

SN_EXTENDED.rowIndex = function (div) {
    var idstr = $(div).attr('id');
    var id = idstr.match(/\d+/);
    return id;
};

SN_EXTENDED.rowCount = function (cls) {
    var divs = $.find('div[class=' + cls + ']');
    return divs.length;
};

SN_EXTENDED.replaceIndex = function (elem, oldIndex, newIndex) {
    $(elem).find('*').each(function () {
        $.each(this.attributes, function (i, attrib) {
            var regexp = /extprofile-.*-\d.*/;
            var value = attrib.value;
            var match = value.match(regexp);
            if (match !== null) {
                attrib.value = value.replace("-" + oldIndex, "-" + newIndex);
            }
        });
    });
}

SN_EXTENDED.resetRow = function (elem) {
    $(elem).find('input, textarea').attr('value', '');
    $(elem).find('input').removeAttr('disabled');
    $(elem).find("select option[value='office']").attr("selected", true);
    $(elem).find("input:checkbox").attr('checked', false);
    $(elem).find("input[name$=-start], input[name$=-end]").each(function () {
        $(this).removeClass('hasDatepicker');
        $(this).datepicker({ dateFormat: 'd M yy' });
    });
};

SN_EXTENDED.addRow = function () {
    var div = $(this).closest('div');
    var id = div.attr('id');
    var cls = div.attr('class');
    var index = id.match(/\d+/);
    var newIndex = parseInt(index) + 1;
    var newtr = $(div).closest('tr').removeClass('supersizeme').clone();
    SN_EXTENDED.replaceIndex(newtr, index, newIndex);
    SN_EXTENDED.resetRow(newtr);
    $(div).closest('tr').after(newtr);
    SN_EXTENDED.reorder(cls);
};

SN_EXTENDED.removeRow = function () {

    var div = $(this).closest('div');
    var id = $(div).attr('id');
    var cls = $(div).attr('class');
    var that = this;

    $("#confirm-dialog").dialog({
        buttons : {
            "Confirm" : function () {
                $(this).dialog("close");
                var target = $(that).closest('tr');
                target.fadeOut("slow", function () {
                    $(target).remove();
                    SN_EXTENDED.reorder(cls);
                });
            },
            "Cancel" : function () {
                $(this).dialog("close");
            }
        }
    });

    var cnt = SN_EXTENDED.rowCount(cls);

    if (cnt > 1) {
        $("#confirm-dialog").dialog("open");
    }
};

$(document).ready(function () {

    $("#confirm-dialog").dialog({
        autoOpen: false,
        modal: true
    });

    $("input#extprofile-manager").autocomplete({
        source: 'finduser',
        minLength: 2 });

    $("input[name$=-start], input[name$=-end], #extprofile-birthday").datepicker({ dateFormat: 'd M yy' });

    var multifields = ["phone-item", "experience-item", "education-item", "im-item", 'website-item'];

    for (f in multifields) {
        SN_EXTENDED.reorder(multifields[f]);
    }

    $("input#extprofile-manager").autocomplete({
        source: 'finduser',
        minLength: 2 });

    $(document).on('click', '.add_row', SN_EXTENDED.addRow);
    $(document).on('click', '.remove_row', SN_EXTENDED.removeRow);

    $('input:checkbox[name$=current]').each(function () {
        var input = $(this).parent().siblings('input[id$=-end]');
        if ($(this).is(':checked')) {
            $(input).attr('disabled', 'true');
        }
    });

    $(document).on('click', 'input:checkbox[name$=current]', function ()  {
        var input = $(this).parent().siblings('input[id$=-end]');
        if ($(this).is(':checked')) {
            $(input).val('');
            $(input).attr('disabled', 'true');
        } else {
            $(input).removeAttr('disabled');
        }
    });

});
