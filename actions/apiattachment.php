<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a notice's attachment
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  API
 * @package   GNUSocial
 * @author    Hannes Mannerheim <h@nnesmannerhe.im>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Show a notice's attachment
 *
 */
class ApiAttachmentAction extends ApiAuthAction
{
    const MAXCOUNT = 100;

    var $original = null;
    var $cnt      = self::MAXCOUNT;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        return true;
    }

    /**
     * Handle the request
     *
     * Make a new notice for the update, save it, and show it
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $file = new File();
        $file->selectAdd(); // clears it
        $file->selectAdd('url');
        $file->id = $this->trimmed('id');
        $url = $file->fetchAll('url');
        
		$file_txt = '';
		if(strstr($url[0],'.html')) {
			$file_txt['txt'] = file_get_contents(str_replace('://quitter.se','://127.0.0.1',$url[0]));
			$file_txt['body_start'] = strpos($file_txt['txt'],'<body>')+6;
			$file_txt['body_end'] = strpos($file_txt['txt'],'</body>');
			$file_txt = substr($file_txt['txt'],$file_txt['body_start'],$file_txt['body_end']-$file_txt['body_start']);
			}

		$this->initDocument('json');
		$this->showJsonObjects($file_txt);
		$this->endDocument('json');
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        return true;
    }
}
