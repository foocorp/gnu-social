<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class SubQueueListItem extends ProfileListItem
{
    public function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showApproveButtons();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    public function showApproveButtons()
    {
        $this->out->elementStart('li', 'entity_approval');
        $form = new ApproveSubForm($this->out, $this->profile);
        $form->show();
        $this->out->elementEnd('li');
    }
}
