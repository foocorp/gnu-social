(function(SN, $) {

var origInit = SN.Init.NoticeFormSetup;
SN.Init.NoticeFormSetup = function(form) {
    origInit(form);

    // Only attach to traditional-style forms
    var textarea = form.find('.notice_data-text:first');
    if (textarea.length == 0) {
        return;
    }

    function fullName(row) {
        if (typeof row.fullname == "string" && row.fullname != '') {
            return row.nickname + ' (' + row.fullname + ')';
        } else {
            return row.nickname;
        }
    }

    var apiUrl = $('#autocomplete-api').attr('data-url');
    textarea.autocomplete(apiUrl, {
        multiple: true,
        multipleSeparator: " ",
        minChars: 1,
        formatItem: function(row, i, max){
            row = eval("(" + row + ")");
            // the display:inline is because our INSANE stylesheets
            // override the standard display of all img tags for no
            // good reason.
            var div = $('<div><img style="display:inline; vertical-align: middle"> <span></span></div>')
                .find('img').attr('src', row.avatar).end()
                .find('span').text(fullName(row)).end()
            return div.html();
        },
        formatMatch: function(row, i, max){
            row = eval("(" + row + ")");
            return row.nickname;
        },
        formatResult: function(row){
            row = eval("(" + row + ")");
            switch(row.type)
            {
                case 'user':
                    return '@' + row.nickname;
                case 'group':
                    return '!' + row.nickname;
            }
        }
    });
};

})(SN, jQuery);
