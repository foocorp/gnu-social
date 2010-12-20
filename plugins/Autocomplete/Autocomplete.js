$(document).ready(function(){
    function fullName(row) {
        if (typeof row.fullname == "string" && row.fullname != '') {
            return row.nickname + ' (' + row.fullname + ')';
        } else {
            return row.nickname;
        }
    }
    $('#notice_data-text').autocomplete($('address .url')[0].href+'main/autocomplete/suggest', {
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
});
