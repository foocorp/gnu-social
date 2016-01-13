/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  OStatus UI interaction
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 * @note      Everything in here should eventually migrate over to /js/util.js's SN.
 */

SN.U.DialogBox = {
    Subscribe: function (a) {
        var f = a.parent().find('.form_settings');
        if (f.length > 0) {
            f.show();
        }
        else {
            $.ajax({
                type: 'GET',
                dataType: 'xml',
                url: a[0].href + ((a[0].href.match(/[\\?]/) === null)?'?':'&') + 'ajax=1',
                beforeSend: function (formData) {
                    a.addClass('processing');
                },
                error: function (xhr, textStatus, errorThrown) {
                    alert(errorThrown || textStatus);
                },
                success: function (data, textStatus, xhr) {
                    if (typeof($('form', data)[0]) != 'undefined') {
                        a.after(document._importNode($('form', data)[0], true));

                        var form = a.parent().find('.form_settings');

                        form
                            .addClass('dialogbox')
                            .append('<button class="close">&#215;</button>');

                        form
                            .find('.submit')
                                .addClass('submit_dialogbox')
                                .removeClass('submit')
                                .bind('click', function () {
                                    form.addClass('processing');
                                });

                        form.find('button.close').click(function (){
                            form.hide();

                            return false;
                        });

                        form.find('#profile').focus();
                    }

                    a.removeClass('processing');
                }
            });
        }
    }
};

SN.Init.Subscribe = function () {
    $(document).on('click',
        '.entity_subscribe .entity_remote_subscribe, .entity_tag .entity_remote_tag',
        function () { SN.U.DialogBox.Subscribe($(this)); return false; });
};

$(document).ready(function () {
    SN.Init.Subscribe();

    $('.form_remote_authorize').bind('submit', function () {
            $(this).addClass(SN.C.S.Processing); return true;
        });
});
