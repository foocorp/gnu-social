/** Init for Autocomplete (requires jquery-ui)
 *
 * @package   Plugin
 * @author Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

var origInit = SN.Init.NoticeFormSetup;
SN.Init.NoticeFormSetup = function(form) {
    origInit(form);

    // Only attach to traditional-style forms
    var textarea = form.find('.notice_data-text:first');
    if (textarea.length === 0) {
        return;
    }

    function termSplit(val) {
        return val.split(/ \s*/);
    }
    function extractLast( term ) {
        return termSplit(term).pop();
    }
    var apiUrl = $('#autocomplete-api').attr('data-url');
    // migrated "multiple" and "multipleSeparator" from
    // http://www.learningjquery.com/2010/06/autocomplete-migration-guide
    textarea
        .bind('keydown', function( event ) {
            if ( event.keyCode === $.ui.keyCode.TAB &&
                $( this ).data( "ui-autocomplete" ).menu.active ) {
                event.preventDefault();
            }
        })
        .autocomplete({
            minLength: 1,   // 1 is default
            source: function (request, response) {
                $.getJSON( apiUrl, {
                    term: extractLast(request.term)
                }, response );
            },
            search: function () {
                // custom minLength, we though we match the 1 below
                var term = extractLast(this.value);
                if (term.length <= 1) {
                    return false;
                }
            },
            focus: function () {
                // prevent value inserted on focus
                return false;
            },
            select: function (event, ui) {
                var terms = termSplit(this.value);
                terms.pop();    // remove latest term
                terms.push(ui.item.value);  // insert
                terms.push(''); // empty element, for the join()
                this.value = terms.join(' ');
                return false;
            },
        })
        .data('ui-autocomplete')._renderItem = function (ul, item) {
            return $('<li></li>')
                .data('ui-autocomplete-item', item)
                .append('<a><img style="display:inline; vertical-align: middle"><span /></a>')
                .find('img').attr('src', item.avatar).end()
                .find('span').text(item.label).end()
                .appendTo(ul);
        };
};

/**
 * Called when a people tag edit box is shown in the interface
 *
 * - loads the jQuery UI autocomplete plugin
 * - sets event handlers for tag completion
 *
 */
SN.Init.PeopletagAutocomplete = function(txtBox) {
    var split,
        extractLast;

    split = function(val) {
        return val.split( /\s+/ );
    };

     extractLast = function(term) {
        return split(term).pop();
    };

    // don't navigate away from the field on tab when selecting an item
    txtBox
        .on('keydown', function(event) {
            if (event.keyCode === $.ui.keyCode.TAB &&
                    $(this).data('ui-autocomplete').menu.active) {
                event.preventDefault();
            }
        })
        .autocomplete({
            minLength: 0,
            source: function(request, response) {
                // delegate back to autocomplete, but extract the last term
                response($.ui.autocomplete.filter(
                    SN.C.PtagACData, extractLast(request.term)));
            },
            focus: function () {
                return false;
            },
            select: function(event, ui) {
                var terms = split(this.value);
                terms.pop();
                terms.push(ui.item.value);
                terms.push('');
                this.value = terms.join(' ');
                return false;
            }
        })
        .data('ui-autocomplete')
        ._renderItem = function (ul, item) {
            // FIXME: with jQuery UI you cannot have it highlight the match
            var _l = '<a class="ptag-ac-line-tag">' + item.tag +
                     ' <em class="privacy_mode">' + item.mode + '</em>' +
                     '<span class="freq">' + item.freq + '</span></a>';

            return $('<li/>')
                    .addClass('mode-' + item.mode)
                    .addClass('ptag-ac-line')
                    .data('item.autocomplete', item)
                    .append(_l)
                    .appendTo(ul);
        };
};

$(document).on('click', '.peopletags_edit_button', function () {
    SN.Init.PeopletagAutocomplete($(this).closest('dd').find('[name="tags"]'));
});
