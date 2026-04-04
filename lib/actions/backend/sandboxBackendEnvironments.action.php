<?php

class sandboxBackendEnvironmentsAction extends waViewAction
{
    public function execute(): void
    {
        $this->setLayout(new sandboxDefaultLayout());

        $contactId = (int) wa()->getUser()->getId();
        $envModel = new sandboxEnvironmentModel();
        $environments = $envModel->getAccessible($contactId);

        wa()->getResponse()->addCss('css/sandbox.css', 'sandbox');

        $this->view->assign([
            'environments' => $environments,
            'contact_id'   => $contactId,
        ]);
    }
}
