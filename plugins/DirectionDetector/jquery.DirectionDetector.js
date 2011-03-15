
/**
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 *
 * @category     Plugin
 * @package      StatusNet
 * @author		 Behrooz shabani (everplays) - <behrooz@rock.com>
 * @copyright    2009-2010 Behrooz shabani
 * @license      http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 *
 */

(function($){
	$.fn.isRTL = function(str){
		if(typeof str != typeof "" || str.length<1)
			return false;
		var cc = str.charCodeAt(0);
		if(cc>=1536 && cc<=1791) // arabic, persian, ...
			return true;
		if(cc>=65136 && cc<=65279) // arabic peresent 2
			return true;
		if(cc>=64336 && cc<=65023) // arabic peresent 1
			return true;
		if(cc>=1424 && cc<=1535) // hebrew
			return true;
		if(cc>=64256 && cc<=64335) // hebrew peresent
			return true;
		if(cc>=1792 && cc<=1871) // Syriac
			return true;
		if(cc>=1920 && cc<=1983) // Thaana
			return true;
		if(cc>=1984 && cc<=2047) // NKo
			return true;
		if(cc>=11568 && cc<=11647) // Tifinagh
			return true;
		return false;
	};
    var origInit = SN.Init.NoticeFormSetup;
    SN.Init.NoticeFormSetup = function(form) {
        origInit(form);
        var tArea = form.find(".notice_data-text:first");
        if (tArea.length > 0) {
            var tCleaner = new RegExp('@[^ ]+|![^ ]+|#[^ ]+|^RT[: ]{1}| RT | RT: |^RD[: ]{1}| RD | RD: |[♺♻:]+', 'g')
            var ping = function(){
                var cleaned = tArea.val().replace(tCleaner, '').replace(/^[ ]+/, '');
                if($().isRTL(cleaned))
                    tArea.css('direction', 'rtl');
                else
                    tArea.css('direction', 'ltr');
            };
            tArea.bind('keyup cut paste', function() {
                // cut/paste trigger before the change
                window.setTimeout(ping, 0);
            });
            form.bind('reset', function() {
                tArea.css('direction', 'ltr');
            });
        }
    };
})(jQuery);
