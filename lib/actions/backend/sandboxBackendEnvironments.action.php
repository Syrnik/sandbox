<?php

class sandboxBackendEnvironmentsAction extends waViewAction
{
    public function execute(): void
    {
        $this->setLayout(new sandboxDefaultLayout());

        $contactId = sandbox()->getContactId();
        $envModel = sandbox()->getModel('Environment');
        $environments = $envModel->getAccessible($contactId);

        wa()->getResponse()->addCss('css/sandbox.css', 'sandbox');
        wa()->getResponse()->addJs('js/sandbox-environments.js', 'sandbox');

        $this->view->assign([
            'environments' => $environments,
            'contact_id'   => $contactId,
        ]);
    }
}
