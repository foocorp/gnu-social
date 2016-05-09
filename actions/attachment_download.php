<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Download notice attachment
 *
 * @category Personal
 * @package  GNUsocial
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     https:/gnu.io/social
 */
class Attachment_downloadAction extends AttachmentAction
{
    public function showPage()
    {
        common_redirect($this->attachment->getUrl(), 302);
    }
}
