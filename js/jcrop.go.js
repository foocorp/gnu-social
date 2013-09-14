/** Init for Jcrop library and page setup
 *
 * @package   StatusNet
 * @author Sarven Capadisli <csarven@status.net>
 * @author Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

$(function(){
    var x = ($('#avatar_crop_x').val()) ? $('#avatar_crop_x').val() : 0;
    var y = ($('#avatar_crop_y').val()) ? $('#avatar_crop_y').val() : 0;
    var w = ($('#avatar_crop_w').val()) ? $('#avatar_crop_w').val() : $("#avatar_original img").attr("width");
    var h = ($('#avatar_crop_h').val()) ? $('#avatar_crop_h').val() : $("#avatar_original img").attr("height");

    $("#avatar_original img").Jcrop({
        onChange: showPreview,
        setSelect: [ x, y, w, h ],
        onSelect: updateCoords,
        aspectRatio: 1,
        boxWidth: 480,
        boxHeight: 480,
        bgColor: '#000',
        bgOpacity: .4
    });
});

function showPreview(coords) {
    var rx = 96 / coords.w;
    var ry = 96 / coords.h;

    var img_width = $("#avatar_original img").attr("width");
    var img_height = $("#avatar_original img").attr("height");

    $('#avatar_preview img').css({
        width: Math.round(rx *img_width) + 'px',
        height: Math.round(ry * img_height) + 'px',
        marginLeft: '-' + Math.round(rx * coords.x) + 'px',
        marginTop: '-' + Math.round(ry * coords.y) + 'px'
    });
};

function updateCoords(c) {
    $('#avatar_crop_x').val(c.x);
    $('#avatar_crop_y').val(c.y);
    $('#avatar_crop_w').val(c.w);
    $('#avatar_crop_h').val(c.h);
};
