<?php

class sandboxBackendCatalogAction extends waViewAction
{
    public function execute(): void
    {
        $this->setLayout(new sandboxDefaultLayout());

        $contactId = (int) wa()->getUser()->getId();

        $folderModel = new sandboxFolderModel();
        $snippetModel = new sandboxSnippetModel();

        $parentId = waRequest::get('folder_id', null, 'int');

        $breadcrumbs = [];
        if ($parentId) {
            $breadcrumbs = $this->buildBreadcrumbs($folderModel, $parentId);
        }

        $folders = $folderModel->getChildren($parentId, $contactId);
        $snippets = $snippetModel->getAccessible($contactId, $parentId);

        wa()->getResponse()->addCss('css/sandbox.css', 'sandbox');

        $this->view->assign([
            'folders'     => $folders,
            'snippets'    => $snippets,
            'breadcrumbs' => $breadcrumbs,
            'parent_id'   => $parentId,
            'contact_id'  => $contactId,
        ]);
    }

    private function buildBreadcrumbs(sandboxFolderModel $model, int $folderId): array
    {
        $crumbs = [];
        $current = $model->getById($folderId);
        while ($current) {
            array_unshift($crumbs, $current);
            $current = $current['parent_id'] ? $model->getById($current['parent_id']) : null;
        }
        return $crumbs;
    }
}
