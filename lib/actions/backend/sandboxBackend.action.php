<?php

class sandboxBackendAction extends waViewAction
{
    public function execute(): void
    {
        $this->setLayout(new sandboxDefaultLayout());

        $contactId = sandbox()->getContactId();

        $snippetModel = sandbox()->getModel('Snippet');
        $recentSnippets = $snippetModel->getRecent($contactId);

        $envModel = sandbox()->getModel('Environment');
        $environments = $envModel->getAccessible($contactId);

        $folderModel = sandbox()->getModel('Folder');
        $folders = $folderModel->getAccessible($contactId);

        wa()->getResponse()->addCss('css/sandbox.css', 'sandbox');
        wa()->getResponse()->addJs('js/sandbox-editor.js', 'sandbox');

        $this->view->assign([
            'recent_snippets' => $recentSnippets,
            'environments'    => $environments,
            'folders'         => $folders,
            'contact_id'      => $contactId,
        ]);
    }
}
