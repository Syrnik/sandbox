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

        $folderModel = new sandboxFolderModel();
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
