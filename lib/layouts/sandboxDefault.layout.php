<?php

class sandboxDefaultLayout extends waLayout
{
    public function execute(): void
    {
        $this->view->assign([
            'contact_id' => wa()->getUser()->getId(),
        ]);
    }
}
