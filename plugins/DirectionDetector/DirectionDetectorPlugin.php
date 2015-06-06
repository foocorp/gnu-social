<?php
/**
 * DirectionDetector plugin, detects notices with RTL content & sets RTL
 * style for them.
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

if (!defined('STATUSNET')) {
    exit(1);
}

define('DIRECTIONDETECTORPLUGIN_VERSION', '0.2.0');

class DirectionDetectorPlugin extends Plugin {
    /**
     * SN plugin API, here we will make changes on rendered column
     *
     * @param object $notice notice is going to be saved
     */
    public function onStartNoticeSave($notice){
        if(!preg_match('/<span class="rtl">/', $notice->rendered) && self::isRTL($notice->content))
            $notice->rendered = '<span class="rtl">'.$notice->rendered.'</span>';
        return true;
    }

    /**
     * is passed string a rtl content or not
     *
     * @param string $content
     * @return boolean
     */
    public static function isRTL($content){
        $content = self::getClearText($content);
        $words = explode(' ', $content);
        $rtl = 0;
        foreach($words as $str)
            if(self::startsWithRTLCharacter($str))
                $rtl++;
            else
                $rtl--;
        if($rtl>0)// if number of rtl words is more than ltr words so it's a rtl content
            return true;
        elseif($rtl==0)
            // check first word again
            return self::startsWithRTLCharacter($words[0]);
        return false;
    }

    /**
     * checks that passed string starts with a RTL language or not
     *
     * @param string $str
     * @return boolean
     */
    public static function startsWithRTLCharacter($str){
        if (strlen($str) < 1) {
            return false;
        }
        if( is_array($cc = self::utf8ToUnicode(mb_substr($str, 0, 1, 'utf-8'))) )
            $cc = $cc[0];
        else
            return false;
        if($cc>=1536 && $cc<=1791) // arabic, persian, urdu, kurdish, ...
            return true;
        if($cc>=65136 && $cc<=65279) // arabic peresent 2
            return true;
        if($cc>=64336 && $cc<=65023) // arabic peresent 1
            return true;
        if($cc>=1424 && $cc<=1535) // hebrew
            return true;
        if($cc>=64256 && $cc<=64335) // hebrew peresent
            return true;
        if($cc>=1792 && $cc<=1871) // Syriac
            return true;
        if($cc>=1920 && $cc<=1983) // Thaana
            return true;
        if($cc>=1984 && $cc<=2047) // NKo
            return true;
        if($cc>=11568 && $cc<=11647) // Tifinagh
            return true;
        return false;
    }

    /**
     * clears text from replys, tags, groups, reteets & whitespaces
     *
     * @param string $str
     * @return string
     */
    private static function getClearText($str){
        $str = preg_replace('/@[^ ]+|![^ ]+|#[^ ]+/u', '', $str); // reply, tag, group
        $str = preg_replace('/^RT[: ]{1}| RT | RT: |^RD[: ]{1}| RD | RD: |[♺♻:]/u', '', $str); // redent, retweet
        $str = preg_replace("/[ \r\t\n]+/", ' ', trim($str)); // remove spaces
        return $str;
    }

    /**
     * adds javascript to do same thing on input textarea
     *
     * @param Action $action
     */
    function onEndShowScripts($action){
        if (common_logged_in()) {
            $action->script($this->path('jquery.DirectionDetector.js'));
        }
    }

    /**
     * Takes an UTF-8 string and returns an array of ints representing the
     * Unicode characters. Astral planes are supported ie. the ints in the
     * output can be > 0xFFFF. O$ccurrances of the BOM are ignored. Surrogates
     * are not allowed.
     *
     * @param string $str
     * @return mixed array of ints, or false on invalid input
     */
    private static function utf8ToUnicode($str){
        $mState = 0;	   // cached expected number of octets after the current octet
                   // until the beginning of the next UTF8 character sequence
        $mUcs4	= 0;     // cached Unicode character
        $mBytes = 1;	   // cached expected number of octets in the current sequence
        $out = array();
        $len = strlen($str);

        for($i = 0; $i < $len; $i++) {
            $in = ord($str{$i});
            if (0 == $mState) {
                // When mState is zero we expect either a US-ASCII character or a
                // multi-octet sequence.
                if (0 == (0x80 & ($in))) {
                    // US-ASCII, pass straight through.
                    $out[] = $in;
                    $mBytes = 1;
                } elseif (0xC0 == (0xE0 & ($in))) {
                    // First octet of 2 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x1F) << 6;
                    $mState = 1;
                    $mBytes = 2;
                } elseif (0xE0 == (0xF0 & ($in))) {
                    // First octet of 3 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x0F) << 12;
                    $mState = 2;
                    $mBytes = 3;
                } elseif (0xF0 == (0xF8 & ($in))) {
                    // First octet of 4 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x07) << 18;
                    $mState = 3;
                    $mBytes = 4;
                } elseif (0xF8 == (0xFC & ($in))) {
                    /* First octet of 5 octet sequence.
                     *
                     * This is illegal because the encoded codepoint must be either
                     * (a) not the shortest form or
                     * (b) outside the Unicode range of 0-0x10FFFF.
                     * Rather than trying to resynchronize, we will carry on until the end
                     * of the sequence and let the later error handling code catch it.
                     */
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x03) << 24;
                    $mState = 4;
                    $mBytes = 5;
                } elseif (0xFC == (0xFE & ($in))) {
                    // First octet of 6 octet sequence, see comments for 5 octet sequence.
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 1) << 30;
                    $mState = 5;
                    $mBytes = 6;
                } else {
                    /* Current octet is neither in the US-ASCII range nor a legal first
                     * octet of a multi-octet sequence.
                     */
                    return false;
                }
            } else {
                // When mState is non-zero, we expect a continuation of the multi-octet
                // sequence
                if (0x80 == (0xC0 & ($in))) {
                    // Legal continuation.
                    $shift = ($mState - 1) * 6;
                    $tmp = $in;
                    $tmp = ($tmp & 0x0000003F) << $shift;
                    $mUcs4 |= $tmp;
                    if (0 == --$mState) {
                        /* End of the multi-octet sequence. mUcs4 now contains the final
                         * Unicode codepoint to be output
                         *
                         * Check for illegal sequences and codepoints.
                         */
                        // From Unicode 3.1, non-shortest form is illegal
                        if	(
                                ((2 == $mBytes) && ($mUcs4 < 0x0080)) ||
                                ((3 == $mBytes) && ($mUcs4 < 0x0800)) ||
                                ((4 == $mBytes) && ($mUcs4 < 0x10000)) ||
                                (4 < $mBytes) ||
                                // From Unicode 3.2, surrogate characters are illegal
                                (($mUcs4 & 0xFFFFF800) == 0xD800) ||
                                // Codepoints outside the Unicode range are illegal
                                ($mUcs4 > 0x10FFFF)
                            ){
                            return false;
                        }
                        if (0xFEFF != $mUcs4) {
                            $out[] = $mUcs4;
                        }
                        //initialize UTF8 cache
                        $mState = 0;
                        $mUcs4  = 0;
                        $mBytes = 1;
                    }
                } else {
                    /* ((0xC0 & (*in) != 0x80) && (mState != 0))
                     *
                     * Incomplete multi-octet sequence.
                     */
                    return false;
                }
            }
        }
        return $out;
    }

    /**
     * plugin details
     */
    function onPluginVersion(array &$versions){
        $url = 'http://status.net/wiki/Plugin:DirectionDetector';

        $versions[] = array(
            'name' => 'Direction detector',
            'version' => DIRECTIONDETECTORPLUGIN_VERSION,
            'author' => 'Behrooz Shabani',
            'homepage' => $url,
            // TRANS: Plugin description.
            'rawdescription' => _m('Shows notices with right-to-left content in correct direction.')
        );
        return true;
    }
}

/*
// Example:
var_dump(DirectionDetectorPlugin::isRTL('RT @everplays ♺: دادگاه به دليل عدم حضور وکلای متهمان بنا بر اصل ١٣٥ قانون اساسی غير قانونی است')); // true
*/
