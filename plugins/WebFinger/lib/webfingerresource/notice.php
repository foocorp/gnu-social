<?php
/**
 * WebFinger resource for Notice objects
 *
 * @package   GNUsocial
 * @author    Mikael Nordfeldth
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class WebFingerResource_Notice extends WebFingerResource
{
    public function __construct(Notice $object)
    {
        // The type argument above verifies that it's our class
        parent::__construct($object);
    }

    public function updateXRD(XML_XRD $xrd)
    {
        if (Event::handle('StartWebFingerNoticeLinks', array($xrd, $this->object))) {
            if ($this->object->isLocal()) {
                $xrd->links[] = new XML_XRD_Element_Link('alternate',
                                    common_local_url('ApiStatusesShow',
                                        array('id'=>$this->object->id,
                                              'format'=>'atom')),
                                    'application/atom+xml');

                $xrd->links[] = new XML_XRD_Element_Link('alternate',
                                    common_local_url('ApiStatusesShow',
                                        array('id'=>$this->object->id,
                                              'format'=>'json')),
                                    'application/json');
            } else {
                try {
                    $xrd->links[] = new XML_XRD_Element_Link('alternate',
                                        $this->object->getUrl(),
                                        'text/html');
                } catch (InvalidUrlException $e) {
                    // don't do a fallback in webfinger
                }
            }
            Event::handle('EndWebFingerNoticeLinks', array($xrd, $this->object));
        }
    }
}
