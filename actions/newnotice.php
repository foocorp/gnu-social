<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Handler for posting new notices
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
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Action for posting new notices
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class NewnoticeAction extends FormAction
{
    protected $form = 'Notice';

    /**
     * Title of the page
     *
     * Note that this usually doesn't get called unless something went wrong
     *
     * @return string page title
     */
    function title()
    {
        if ($this->getInfo() && $this->stored instanceof Notice) {
            // TRANS: Page title after sending a notice.
            return _('Notice posted');
        }
        if ($this->int('inreplyto')) {
            return _m('TITLE', 'New reply');
        }
        // TRANS: Page title for sending a new notice.
        return _m('TITLE','New notice');
    }

    protected function doPreparation()
    {
        foreach(array('inreplyto') as $opt) {
            if ($this->trimmed($opt)) {
                $this->formOpts[$opt] = $this->trimmed($opt);
            }
        }

        // Backwards compatibility for "share this" widget things.
        // If no 'content', use 'status_textarea'
        $this->formOpts['content'] = $this->trimmed('content') ?: $this->trimmed('status_textarea');
    }

    /**
     * This doPost saves a new notice, based on arguments
     *
     * If successful, will show the notice, or return an Ajax-y result.
     * If not, it will show an error message -- possibly Ajax-y.
     *
     * Also, if the notice input looks like a command, it will run the
     * command and show the results -- again, possibly ajaxy.
     *
     * @return void
     */
    protected function doPost()
    {
        assert($this->scoped instanceof Profile); // XXX: maybe an error instead...
        $user = $this->scoped->getUser();
        $content = $this->formOpts['content'];
        $options = array('source' => 'web');
        Event::handle('StartSaveNewNoticeWeb', array($this, $user, &$content, &$options));

        $upload = null;
        try {
            // throws exception on failure
            $upload = MediaFile::fromUpload('attach', $this->scoped);
            if (Event::handle('StartSaveNewNoticeAppendAttachment', array($this, $upload, &$content, &$options))) {
                $content .= ($content==='' ? '' : ' ') . $upload->shortUrl();
            }
            Event::handle('EndSaveNewNoticeAppendAttachment', array($this, $upload, &$content, &$options));

            // We could check content length here if the URL was added, but I'll just let it slide for now...

            $act->enclosures[] = $upload->getEnclosure();
        } catch (NoUploadedMediaException $e) {
            // simply no attached media to the new notice
            if (empty($content)) {
                // TRANS: Client error displayed trying to send a notice without content.
                $this->clientError(_('No content!'));
            }
        }

        $inter = new CommandInterpreter();

        $cmd = $inter->handle_command($user, $content);

        if ($cmd) {
            if (GNUsocial::isAjax()) {
                $cmd->execute(new AjaxWebChannel($this));
            } else {
                $cmd->execute(new WebChannel($this));
            }
            return;
        }

        if ($this->int('inreplyto')) {
            // Throws exception if the inreplyto Notice is given but not found.
            $parent = Notice::getByID($this->int('inreplyto'));
        } else {
            $parent = null;
        }

        $act = new Activity();
        $act->verb = ActivityVerb::POST;
        $act->time = time();
        $act->actor = $this->scoped->asActivityObject();

        // Reject notice if it is too long (without the HTML)
        // This is done after MediaFile::fromUpload etc. just to act the same as the ApiStatusesUpdateAction
        if (Notice::contentTooLong($content)) {
            // TRANS: Client error displayed when the parameter "status" is missing.
            // TRANS: %d is the maximum number of character for a notice.
            throw new ClientException(sprintf(_m('That\'s too long. Maximum notice size is %d character.',
                                                 'That\'s too long. Maximum notice size is %d characters.',
                                                 Notice::maxContent()),
                                              Notice::maxContent()));
        }

        $act->context = new ActivityContext();

        if ($parent instanceof Notice) {
            $act->context->replyToID = $parent->getUri();
            $act->context->replyToUrl = $parent->getUrl(true);  // maybe we don't have to send true here to force a URL?
        }

        if ($this->scoped->shareLocation()) {
            // use browser data if checked; otherwise profile data
            if ($this->arg('notice_data-geo')) {
                $locOptions = Notice::locationOptions($this->trimmed('lat'),
                                                      $this->trimmed('lon'),
                                                      $this->trimmed('location_id'),
                                                      $this->trimmed('location_ns'),
                                                      $this->scoped);
            } else {
                $locOptions = Notice::locationOptions(null,
                                                      null,
                                                      null,
                                                      null,
                                                      $this->scoped);
            }

            $act->context->location = Location::fromOptions($locOptions);
        }

        $content = $this->scoped->shortenLinks($content);

        // FIXME: Make sure NoticeTitle plugin gets a change to add the title to our activityobject!
        if (Event::handle('StartNoticeSaveWeb', array($this, $this->scoped, &$content, &$options))) {

            // FIXME: We should be able to get the attentions from common_render_content!
            // and maybe even directly save whether they're local or not!
            $act->context->attention = common_get_attentions($content, $this->scoped, $parent);

            // $options gets filled with possible scoping settings
            ToSelector::fillActivity($this, $act, $options);

            $actobj = new ActivityObject();
            $actobj->type = ActivityObject::NOTE;
            $actobj->content = common_render_content($content, $this->scoped, $parent);

            // Finally add the activity object to our activity
            $act->objects[] = $actobj;

            $this->stored = Notice::saveActivity($act, $this->scoped, $options);

            if ($upload instanceof MediaFile) {
                $upload->attachToNotice($this->stored);
            }

            Event::handle('EndNoticeSaveWeb', array($this, $this->stored));
        }

        Event::handle('EndSaveNewNoticeWeb', array($this, $user, &$content, &$options));

        if (!GNUsocial::isAjax()) {
            $url = common_local_url('shownotice', array('notice' => $this->stored->id));
            common_redirect($url, 303);
        }

        return _('Saved the notice!');
    }

    protected function showContent()
    {
        if ($this->getInfo() && $this->stored instanceof Notice) {
            $this->showNotice($this->stored);
        } elseif (!$this->getError()) {
            parent::showContent();
        }
    }

    /**
     * Output a notice
     *
     * Used to generate the notice code for Ajax results.
     *
     * @param Notice $notice Notice that was saved
     *
     * @return void
     */
    function showNotice(Notice $notice)
    {
        $nli = new NoticeListItem($notice, $this);
        $nli->show();
    }

    public function showNoticeForm()
    {
        // pass
    }
}
