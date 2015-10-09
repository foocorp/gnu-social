<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class DeletenoticeForm extends Form
{
    protected $notice = null;

    function __construct(HTMLOutputter $out=null, array $formOpts=array())
    {
        if (!array_key_exists('notice', $formOpts) || !$formOpts['notice'] instanceof Notice) {
            throw new ServerException('No notice provided to DeletenoticeForm');
        }

        parent::__construct($out);

        $this->notice = $formOpts['notice'];
    }

    function id()
    {
        return 'form_notice_delete-' . $this->notice->getID();
    }

    function formClass()
    {
        return 'form_settings';
    }

    function action()
    {
        return common_local_url('deletenotice', array('notice' => $this->notice->getID()));
    }

    function formLegend()
    {
        $this->out->element('legend', null, _('Delete notice'));
    }

    function formData()
    {
        $this->out->element('p', null, _('Are you sure you want to delete this notice?'));
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('form_action-no',
                      // TRANS: Button label on the delete notice form.
                      _m('BUTTON','No'),
                      'submit form_action-primary',
                      'no',
                      // TRANS: Submit button title for 'No' when deleting a notice.
                      _('Do not delete this notice.'));
        $this->out->submit('form_action-yes',
                      // TRANS: Button label on the delete notice form.
                      _m('BUTTON','Yes'),
                      'submit form_action-secondary',
                      'yes',
                      // TRANS: Submit button title for 'Yes' when deleting a notice.
                      _('Delete this notice.'));
    }
}
