<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base Facebook Action
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
 * @category  Faceboook
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/Facebook/facebookutil.php';
require_once INSTALLDIR . '/plugins/Facebook/facebooknoticeform.php';

class FacebookAction extends Action
{
    var $facebook = null;
    var $fbuid    = null;
    var $flink    = null;
    var $action   = null;
    var $app_uri  = null;
    var $app_name = null;

    function __construct($output='php://output', $indent=null, $facebook=null, $flink=null)
    {
        parent::__construct($output, $indent);

        $this->facebook = $facebook;
        $this->flink = $flink;

        if ($this->flink) {
            $this->fbuid = $flink->foreign_id;
            $this->user = $flink->getUser();
        }

        $this->args = array();
    }

    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->facebook = getFacebook();
        $this->fbuid = $this->facebook->require_login();

        $this->action = $this->trimmed('action');

        $app_props = $this->facebook->api_client->Admin_getAppProperties(
                array('canvas_name', 'application_name'));

        $this->app_uri = 'http://apps.facebook.com/' . $app_props['canvas_name'];
        $this->app_name = $app_props['application_name'];

        $this->flink = Foreign_link::getByForeignID($this->fbuid, FACEBOOK_SERVICE);

        return true;

    }

    function showStylesheets()
    {
        $this->cssLink('css/display.css', 'base');
        $this->cssLink('css/display.css', null, 'screen, projection, tv');
        $this->cssLink('plugins/Facebook/facebookapp.css');
    }

    function showScripts()
    {
        $this->script('plugins/Facebook/facebookapp.js');
    }

    /**
     * Start an Facebook ready HTML document
     *
     * For Facebook we don't want to actually output any headers,
     * DTD info, etc.  Just Stylesheet and JavaScript links.
     *
     * @param string $type MIME type to use; default is to do negotation.
     *
     * @return void
     */
    function startHTML($type=null)
    {
        $this->showStylesheets();
        $this->showScripts();

        $this->elementStart('div', array('class' => 'facebook-page'));
    }

    /**
    *  Ends a Facebook ready HTML document
    *
    *  @return void
    */
    function endHTML()
    {
        $this->elementEnd('div');
        $this->endXML();
    }

    /**
     * Show notice form.
     *
     * @return nothing
     */
    function showNoticeForm()
    {
        // don't do it for most of the Facebook pages
    }

    function showBody()
    {
        $this->elementStart('div', array('id' => 'wrap'));
        $this->showHeader();
        $this->showCore();
        $this->showFooter();
        $this->elementEnd('div');
    }

    function showHead($error, $success)
    {
        if ($error) {
            $this->element("h1", null, $error);
        }

        if ($success) {
            $this->element("h1", null, $success);
        }

        $this->elementStart('fb:if-section-not-added', array('section' => 'profile'));
        $this->elementStart('span', array('id' => 'add_to_profile'));
        $this->element('fb:add-section-button', array('section' => 'profile'));
        $this->elementEnd('span');
        $this->elementEnd('fb:if-section-not-added');

    }

    // Make this into a widget later
    function showLocalNav()
    {
        $this->elementStart('ul', array('class' => 'nav'));

        $this->elementStart('li', array('class' =>
            ($this->action == 'facebookhome') ? 'current' : 'facebook_home'));
        $this->element('a',
            // TRANS: Link description for 'Home' link that leads to a start page.
            array('href' => 'index.php', 'title' => _m('MENU','Home')),
            // TRANS: Tooltip for 'Home' link that leads to a start page.
            _m('Home'));
        $this->elementEnd('li');

        if (common_config('invite', 'enabled')) {
            $this->elementStart('li',
                array('class' =>
                    ($this->action == 'facebookinvite') ? 'current' : 'facebook_invite'));
            $this->element('a',
                // TRANS: Link description for 'Invite' link that leads to a page where friends can be invited.
                array('href' => 'invite.php', 'title' => _m('MENU','Invite')),
                // TRANS: Tooltip for 'Invite' link that leads to a page where friends can be invited.
                _m('Invite'));
            $this->elementEnd('li');
        }

        $this->elementStart('li',
            array('class' =>
                ($this->action == 'facebooksettings') ? 'current' : 'facebook_settings'));
        $this->element('a',
            array('href' => 'settings.php',
                // TRANS: Link description for 'Settings' link that leads to a page user preferences can be set.
                'title' => _m('MENU','Settings')),
                // TRANS: Tooltip for 'Settings' link that leads to a page user preferences can be set.
                _m('Settings'));
        $this->elementEnd('li');

        $this->elementEnd('ul');
    }

    /**
     * Show header of the page.
     *
     * @return nothing
     */
    function showHeader()
    {
        $this->elementStart('div', array('id' => 'header'));
        $this->showLogo();
        $this->showNoticeForm();
        $this->elementEnd('div');
    }

    /**
     * Show page, a template method.
     *
     * @return nothing
     */
    function showPage($error = null, $success = null)
    {
        $this->startHTML();
        $this->showHead($error, $success);
        $this->showBody();
        $this->endHTML();
    }

    function showInstructions()
    {
        $this->elementStart('div', array('class' => 'facebook_guide'));

        $this->elementStart('dl', array('class' => 'system_notice'));
        $this->element('dt', null, 'Page Notice');

        $loginmsg_part1 = _m('To use the %s Facebook Application you need to login ' .
            'with your username and password. Don\'t have a username yet?');
        $loginmsg_part2 = _m(' a new account.');

        $this->elementStart('dd');
        $this->elementStart('p');
        $this->text(sprintf($loginmsg_part1, common_config('site', 'name')));
        // @todo FIXME: Bad i18n. Patchwork message in two parts.
        $this->element('a',
            array('href' => common_local_url('register')), _m('Register'));
        $this->text( " " . $loginmsg_part2);
        $this->elementEnd('p');
        $this->elementEnd('dd');

        $this->elementEnd('dl');
        $this->elementEnd('div');
    }

    function showLoginForm($msg = null)
    {

        $this->elementStart('div', array('id' => 'content'));
        $this->element('h1', null, _m('Login'));

        if ($msg) {
             $this->element('fb:error', array('message' => $msg));
        }

        $this->showInstructions();

        $this->elementStart('div', array('id' => 'content_inner'));

        $this->elementStart('form', array('method' => 'post',
                                               'class' => 'form_settings',
                                               'id' => 'login',
                                               'action' => 'index.php'));

        $this->elementStart('fieldset');

        $this->elementStart('ul', array('class' => 'form_datas'));
        $this->elementStart('li');
        $this->input('nickname', _m('Nickname'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->password('password', _m('Password'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        // TRANS: Login button.
        $this->submit('submit', _m('BUTTON','Login'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');

        $this->elementStart('p');
        $this->element('a', array('href' => common_local_url('recoverpassword')),
                       _m('Lost or forgotten password?'));
        $this->elementEnd('p');

        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    function updateProfileBox($notice)
    {

        // Need to include inline CSS for styling the Profile box

        $app_props = $this->facebook->api_client->Admin_getAppProperties(array('icon_url'));
        $icon_url = $app_props['icon_url'];

        $style = '<style> .entry-title *, .entry-content * { font-size:14px; font-family:"Lucida Sans Unicode", "Lucida Grande", sans-serif; } .entry-title a, .entry-content a { color:#002E6E; } .entry-title .vcard .photo { float:left; display:inline; margin-right:11px; margin-bottom:11px } .entry-title { margin-bottom:11px; } .entry-title p.entry-content { display:inline; margin-left:5px; } div.entry-content { clear:both; } div.entry-content dl, div.entry-content dt, div.entry-content dd { display:inline; text-transform:lowercase; } div.entry-content dd, div.entry-content .device dt { margin-left:0; margin-right:5px; } div.entry-content dl.timestamp dt, div.entry-content dl.response dt { display:none; } div.entry-content dd a { display:inline-block; } #facebook_statusnet_app { text-indent:-9999px; height:16px; width:16px; display:block; background:url('.$icon_url.') no-repeat 0 0; float:right; } </style>';

        $this->xw->openMemory();

        $item = new FacebookProfileBoxNotice($notice, $this);
        $item->show();

        $fbml = "<fb:wide>$style " . $this->xw->outputMemory(false) . "</fb:wide>";
        $fbml .= "<fb:narrow>$style " . $this->xw->outputMemory(false) . "</fb:narrow>";

        $fbml_main = "<fb:narrow>$style " . $this->xw->outputMemory(false) . "</fb:narrow>";

        $this->facebook->api_client->profile_setFBML(null, $this->fbuid, $fbml, null, null, $fbml_main);

        $this->xw->openURI('php://output');
    }

    /**
     * Generate pagination links
     *
     * @param boolean $have_before is there something before?
     * @param boolean $have_after  is there something after?
     * @param integer $page        current page
     * @param string  $action      current action
     * @param array   $args        rest of query arguments
     *
     * @return nothing
     */
    function pagination($have_before, $have_after, $page, $action, $args=null)
    {
        // Does a little before-after block for next/prev page
        if ($have_before || $have_after) {
            $this->elementStart('dl', 'pagination');
            $this->element('dt', null, _m('Pagination'));
            $this->elementStart('dd', null);
            $this->elementStart('ul', array('class' => 'nav'));
        }
        if ($have_before) {
            $pargs   = array('page' => $page-1);
            $newargs = $args ? array_merge($args, $pargs) : $pargs;
            $this->elementStart('li', array('class' => 'nav_prev'));
            $this->element('a', array('href' => "$this->app_uri/$action?page=$newargs[page]", 'rel' => 'prev'),
                           _m('After'));
            $this->elementEnd('li');
        }
        if ($have_after) {
            $pargs   = array('page' => $page+1);
            $newargs = $args ? array_merge($args, $pargs) : $pargs;
            $this->elementStart('li', array('class' => 'nav_next'));
            $this->element('a', array('href' => "$this->app_uri/$action?page=$newargs[page]", 'rel' => 'next'),
                           _m('Before'));
            $this->elementEnd('li');
        }
        if ($have_before || $have_after) {
            $this->elementEnd('ul');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
    }

    function saveNewNotice()
    {
        $user = $this->flink->getUser();

        $content = $this->trimmed('status_textarea');

        if (!$content) {
            $this->showPage(_m('No notice content!'));
            return;
        } else {
            $content_shortened = $user->shortenLinks($content);

            if (Notice::contentTooLong($content_shortened)) {
                // @todo FIXME: i18n: Needs plural.
                $this->showPage(sprintf(_m('That\'s too long. Max notice size is %d chars.'),
                                        Notice::maxContent()));
                return;
            }
        }

        $inter = new CommandInterpreter();

        $cmd = $inter->handle_command($user, $content_shortened);

        if ($cmd) {
            // XXX fix this
            $cmd->execute(new WebChannel());
            return;
        }

        $replyto = $this->trimmed('inreplyto');

        try {
            $notice = Notice::saveNew($user->id, $content, 'web',
                                      array('reply_to' => ($replyto == 'false') ? null : $replyto));

        } catch (Exception $e) {
            $this->showPage($e->getMessage());
            return;
        }

    }
}

class FacebookNoticeList extends NoticeList
{
    /**
     * constructor
     *
     * @param Notice $notice stream of notices from DB_DataObject
     */

    function __construct($notice, $out=null)
    {
        parent::__construct($notice, $out);
    }

    /**
     * show the list of notices
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @return int count of notices listed.
     */
    function show()
    {
        $this->out->elementStart('div', array('id' =>'notices_primary'));
        $this->out->element('h2', null, _m('Notices'));
        $this->out->elementStart('ul', array('class' => 'notices'));

        $cnt = 0;

        while ($this->notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
            $cnt++;

            if ($cnt > NOTICES_PER_PAGE) {
                break;
            }

            $item = $this->newListItem($this->notice);
            $item->show();
        }

        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');

        return $cnt;
    }

    /**
     * returns a new list item for the current notice
     *
     * Overridden to return a Facebook specific list item.
     *
     * @param Notice $notice the current notice
     *
     * @return FacebookNoticeListItem a list item for displaying the notice
     * formatted for display in the Facebook App.
     */
    function newListItem($notice)
    {
        return new FacebookNoticeListItem($notice, $this);
    }
}

class FacebookNoticeListItem extends NoticeListItem
{
    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */
    function __construct($notice, $out=null)
    {
        parent::__construct($notice, $out);
    }

    /**
     * recipe function for displaying a single notice in the Facebook App.
     *
     * Overridden to strip out some of the controls that we don't
     * want to be available.
     *
     * @return void
     */
    function show()
    {
        $this->showStart();
        $this->showNotice();
        $this->showNoticeInfo();

        // XXX: Need to update to show attachements and controls

        $this->showEnd();
    }
}

class FacebookProfileBoxNotice extends FacebookNoticeListItem
{
    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */
    function __construct($notice, $out=null)
    {
        parent::__construct($notice, $out);
    }

    /**
     * Recipe function for displaying a single notice in the
     * Facebook App profile notice box
     *
     * @return void
     */
    function show()
    {
        $this->showNotice();
        $this->showNoticeInfo();
        $this->showAppLink();
    }

    function showAppLink()
    {
        $this->facebook = getFacebook();

        $app_props = $this->facebook->api_client->Admin_getAppProperties(
                array('canvas_name', 'application_name'));

        $this->app_uri = 'http://apps.facebook.com/' . $app_props['canvas_name'];
        $this->app_name = $app_props['application_name'];

        $this->out->elementStart('a', array('id' => 'facebook_statusnet_app',
                                            'href' => $this->app_uri));
        $this->out->text($this->app_name);
        $this->out->elementEnd('a');
    }
}
