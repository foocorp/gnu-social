// XXX: Should I do crazy SN.X.Y.Z.A namespace instead?
var SN_WHITELIST = SN_WHITELIST || {};

SN_WHITELIST.updateButtons = function () {
   $("ul > li > a.remove_row").show();
   $("ul > li > a.add_row").hide();

    var lis = $('ul > li > input[name^="username[]"]');
    if (lis.length === 1) {
        $("ul > li > a.remove_row").hide();
    } else {
        $("ul > li > a.remove_row:first").show();
    }
    $("ul > li > a.add_row:last").show();
};

SN_WHITELIST.resetRow = function (row) {
    $("input", row).val('');
    // Make sure the default domain is the first selection
    $("select option:first", row).val();
    $("a.remove_row", row).show();
};

SN_WHITELIST.addRow = function () {
    var row = $(this).closest("li");
    var newRow = row.clone();
    $(row).find('a.add_row').hide();
    SN_WHITELIST.resetRow(newRow);
        $(newRow).insertAfter(row).show("blind", "fast", function () {
            SN_WHITELIST.updateButtons();
        });
};

SN_WHITELIST.removeRow = function () {
    var that = this;

    $("#confirm-dialog").dialog({
        buttons : {
            "Confirm" : function () {
                $(this).dialog("close");
                $(that).closest("li").hide("blind", "fast", function () {
                    $(this).remove();
                    SN_WHITELIST.updateButtons();
                });
            },
            "Cancel" : function () {
                $(this).dialog("close");
            }
        }
    });

    if ($(this).closest('li').find(':input[name^=username]').val()) {
        $("#confirm-dialog").dialog("open");
    } else {
        $(that).closest("li").hide("blind", "fast", function () {
            $(this).remove();
            SN_WHITELIST.updateButtons();
        });
    }
};

$(document).ready(function () {
    $("#confirm-dialog").dialog({
        autoOpen: false,
        modal: true
    });

    $(document).on('click', '.add_row', SN_WHITELIST.addRow);
    $(document).on('click', '.remove_row', SN_WHITELIST.removeRow);

    SN_WHITELIST.updateButtons();
});
