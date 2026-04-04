<?php

class sandboxBackendAction extends waViewAction
{
    public function execute(): void
    {
        $this->setLayout(new sandboxDefaultLayout());

        $contactId = (int) wa()->getUser()->getId();

        $snippetModel = new sandboxSnippetModel();
        $recentSnippets = $snippetModel->getRecent($contactId);

        $envModel = new sandboxEnvironmentModel();
        $environments = $envModel->getAccessible($contactId);

        wa()->getResponse()->addCss('css/sandbox.css', 'sandbox');

        $this->view->assign([
            'recent_snippets' => $recentSnippets,
            'environments'    => $environments,
            'contact_id'      => $contactId,
        ]);
    }
}
