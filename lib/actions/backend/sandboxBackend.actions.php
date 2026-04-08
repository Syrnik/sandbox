<?php

class sandboxBackendActions extends waJsonActions
{
    // ========================
    // СНИППЕТЫ
    // ========================

    public function snippetAction(): void
    {
        $id = waRequest::get('id', 0, 'int');
        $model = new sandboxSnippetModel();
        $snippet = $model->getById($id);
        if (!$snippet) {
            $this->errors = 'Сниппет не найден';
            return;
        }
        $this->checkAccess($snippet);
        $this->response = $snippet;
    }

    public function snippetsAction(): void
    {
        $contactId = $this->getUserId();
        $folderId = waRequest::get('folder_id', null, 'int');
        $model = new sandboxSnippetModel();

        if ($folderId !== null) {
            $this->response = $model->getAccessible($contactId, $folderId);
        } else {
            $this->response = $model->getRecent($contactId);
        }
    }

    public function snippetSaveAction(): void
    {
        $id = waRequest::post('id', 0, 'int');
        $data = [
            'name'        => waRequest::post('name', '', 'string_trim'),
            'description' => waRequest::post('description', '', 'string_trim'),
            'code_php'    => waRequest::post('code_php'),
            'code_smarty' => waRequest::post('code_smarty'),
            'is_shared'   => waRequest::post('is_shared', 0, 'int'),
            'folder_id'   => waRequest::post('folder_id', null, 'int'),
        ];

        if (empty($data['name'])) {
            $this->errors = 'Название обязательно';
            return;
        }

        $model = new sandboxSnippetModel();
        if ($id) {
            $existing = $model->getById($id);
            $this->checkOwnership($existing);
            $model->edit($id, $data);
            $this->response = $model->getById($id);
        } else {
            $data['contact_id'] = $this->getUserId();
            $newId = $model->add($data);
            $this->response = $model->getById($newId);
        }
    }

    public function snippetMoveAction(): void
    {
        $ids = waRequest::post('ids', [], 'array_int');
        $folderId = waRequest::post('folder_id', 0, 'int');
        if ($folderId === 0) {
            $folderId = null;
        }

        $model = new sandboxSnippetModel();
        foreach ($ids as $id) {
            $snippet = $model->getById($id);
            $this->checkOwnership($snippet);
            $model->edit($id, ['folder_id' => $folderId]);
        }
        $this->response = true;
    }

    public function snippetDuplicateAction(): void
    {
        $id = waRequest::post('id', 0, 'int');
        $model = new sandboxSnippetModel();
        $snippet = $model->getById($id);
        $this->checkAccess($snippet);
        $newId = $model->add([
            'contact_id'  => $this->getUserId(),
            'name'        => $snippet['name'] . ' (копия)',
            'description' => $snippet['description'] ?? '',
            'code_php'    => $snippet['code_php'] ?? '',
            'code_smarty' => $snippet['code_smarty'] ?? '',
            'is_shared'   => 0,
            'folder_id'   => null,
        ]);
        $this->response = $model->getById($newId);
    }

    public function bulkShareAction(): void
    {
        $snippetIds = waRequest::post('snippet_ids', [], 'array_int');
        $folderIds  = waRequest::post('folder_ids', [], 'array_int');
        $isShared   = waRequest::post('is_shared', 0, 'int');

        $snippetModel = new sandboxSnippetModel();
        $folderModel  = new sandboxFolderModel();

        foreach ($snippetIds as $id) {
            $snippet = $snippetModel->getById($id);
            $this->checkOwnership($snippet);
            $snippetModel->edit($id, ['is_shared' => $isShared]);
        }

        foreach ($folderIds as $id) {
            $this->shareFolderRecursive($folderModel, $snippetModel, $id, $isShared);
        }

        $this->response = true;
    }

    public function snippetDeleteAction(): void
    {
        $ids = waRequest::post('ids', [], 'array_int');
        $model = new sandboxSnippetModel();
        $contactId = $this->getUserId();
        foreach ($ids as $id) {
            $snippet = $model->getById($id);
            if ($snippet && $snippet['contact_id'] == $contactId) {
                $model->deleteById($id);
            }
        }
        $this->response = true;
    }

    public function snippetExecuteAction(): void
    {
        $codePHP = waRequest::post('code_php', '', 'string');
        $codeSmarty = waRequest::post('code_smarty', '', 'string');
        $envId = waRequest::post('environment_id', 0, 'int');

        $executor = new sandboxExecutor($this->getUserId());
        $result = $executor->execute($codePHP, $codeSmarty, $envId);

        $this->response = $result;
    }

    // ========================
    // ПАПКИ
    // ========================

    public function foldersAction(): void
    {
        $model = new sandboxFolderModel();
        $this->response = $model->getTree($this->getUserId());
    }

    public function folderSaveAction(): void
    {
        $id = waRequest::post('id', 0, 'int');
        $parentId = waRequest::post('parent_id', 0, 'int');
        $data = [
            'name'        => waRequest::post('name', '', 'string_trim'),
            'description' => waRequest::post('description', '', 'string_trim'),
            'parent_id'   => $parentId ?: null,
            'is_shared'   => waRequest::post('is_shared', 0, 'int'),
        ];

        if (empty($data['name'])) {
            $this->errors = 'Название обязательно';
            return;
        }

        $model = new sandboxFolderModel();
        if ($id) {
            $existing = $model->getById($id);
            $this->checkOwnership($existing);
            $data['update_datetime'] = date('Y-m-d H:i:s');
            $model->updateByField('id', $id, $data);
            $this->response = $model->getById($id);
        } else {
            $data['contact_id'] = $this->getUserId();
            $newId = $model->add($data);
            $this->response = $model->getById($newId);
        }
    }

    public function folderDeleteAction(): void
    {
        $id = waRequest::post('id', 0, 'int');
        $model = new sandboxFolderModel();
        $folder = $model->getById($id);
        $this->checkOwnership($folder);
        $this->deleteFolderRecursive($id);
        $this->response = true;
    }

    // ========================
    // ОКРУЖЕНИЯ
    // ========================

    public function environmentsAction(): void
    {
        $model = new sandboxEnvironmentModel();
        $this->response = $model->getAccessible($this->getUserId());
    }

    public function environmentAction(): void
    {
        $id = waRequest::get('id', 0, 'int');
        $model = new sandboxEnvironmentModel();
        $env = $model->getById($id);
        if (!$env) {
            $this->errors = 'Окружение не найдено';
            return;
        }
        $this->checkAccess($env);
        $env['variables_parsed'] = $model->getVariables($id);
        $this->response = $env;
    }

    public function environmentSaveAction(): void
    {
        $id = waRequest::post('id', 0, 'int');
        $data = [
            'name'      => waRequest::post('name', '', 'string_trim'),
            'is_shared' => waRequest::post('is_shared', 0, 'int'),
            'variables' => waRequest::post('variables', '{}', 'string'),
        ];

        $decoded = json_decode($data['variables'], true);
        if ($decoded === null && $data['variables'] !== '{}') {
            $this->errors = 'Невалидный JSON в переменных';
            return;
        }
        $data['variables'] = json_encode($decoded ?: [], JSON_UNESCAPED_UNICODE);

        if (empty($data['name'])) {
            $this->errors = 'Название обязательно';
            return;
        }

        $model = new sandboxEnvironmentModel();
        if ($id) {
            $existing = $model->getById($id);
            $this->checkOwnership($existing);
            $model->edit($id, $data);
            $this->response = $model->getById($id);
        } else {
            $data['contact_id'] = $this->getUserId();
            $newId = $model->add($data);
            $this->response = $model->getById($newId);
        }
    }

    public function environmentDeleteAction(): void
    {
        $id = waRequest::post('id', 0, 'int');
        $model = new sandboxEnvironmentModel();
        $env = $model->getById($id);
        $this->checkOwnership($env);
        $model->deleteById($id);
        $this->response = true;
    }

    // ========================
    // ЭКСПОРТ / ИМПОРТ
    // ========================

    public function exportAction(): void
    {
        $type = waRequest::get('type', '', 'string');
        $id = waRequest::get('id', 0, 'int');
        $contactId = $this->getUserId();

        $result = match($type) {
            'snippet'     => $this->exportSnippet($id, $contactId),
            'folder'      => $this->exportFolder($id, $contactId),
            'environment' => $this->exportEnvironment($id, $contactId),
            default       => null,
        };

        if ($result === null) {
            $this->errors = 'Неверный тип экспорта';
            return;
        }

        $this->response = $result;
    }

    public function importAction(): void
    {
        $json = waRequest::post('data', '', 'string');
        $import = json_decode($json, true);
        if (!$import || !isset($import['type'])) {
            $this->errors = 'Неверный формат импорта';
            return;
        }

        $contactId = $this->getUserId();

        $result = match($import['type']) {
            'snippet'     => $this->importSnippet($import, $contactId),
            'folder'      => $this->importFolder($import, $contactId),
            'environment' => $this->importEnvironment($import, $contactId),
            default       => null,
        };

        if ($result === null) {
            $this->errors = 'Неверный тип импорта';
            return;
        }
        $this->response = $result;
    }

    // ========================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ========================

    public function getUserId(): int
    {
        return (int) wa()->getUser()->getId();
    }

    private function checkOwnership(?array $record): void
    {
        if (!$record || (int)$record['contact_id'] !== $this->getUserId()) {
            throw new waRightsException();
        }
    }

    private function checkAccess(?array $record): void
    {
        if (!$record) {
            throw new waException(_w('Not found'), 404);
        }
        if (!$record['is_shared'] && (int)$record['contact_id'] !== $this->getUserId()) {
            throw new waRightsException();
        }
    }

    private function shareFolderRecursive(
        sandboxFolderModel $folderModel,
        sandboxSnippetModel $snippetModel,
        int $folderId,
        int $isShared
    ): void {
        $folder = $folderModel->getById($folderId);
        $this->checkOwnership($folder);
        $folderModel->updateByField('id', $folderId, [
            'is_shared'       => $isShared,
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);

        $snippets = $snippetModel->getByField('folder_id', $folderId, true);
        foreach ($snippets as $s) {
            $snippetModel->edit((int)$s['id'], ['is_shared' => $isShared]);
        }

        $children = $folderModel->getByField('parent_id', $folderId, true);
        foreach ($children as $child) {
            $this->shareFolderRecursive($folderModel, $snippetModel, (int)$child['id'], $isShared);
        }
    }

    private function deleteFolderRecursive(int $folderId): void
    {
        $folderModel = new sandboxFolderModel();
        $snippetModel = new sandboxSnippetModel();

        $snippetModel->deleteByField('folder_id', $folderId);

        $children = $folderModel->getByField('parent_id', $folderId, true);
        foreach ($children as $child) {
            $this->deleteFolderRecursive((int)$child['id']);
        }

        $folderModel->deleteById($folderId);
    }

    private function exportSnippet(int $id, int $contactId): array
    {
        $model = new sandboxSnippetModel();
        $snippet = $model->getById($id);
        $this->checkAccess($snippet);
        return [
            'type'        => 'snippet',
            'version'     => '1.0',
            'name'        => $snippet['name'],
            'description' => $snippet['description'],
            'code_php'    => $snippet['code_php'],
            'code_smarty' => $snippet['code_smarty'],
        ];
    }

    private function exportFolder(int $id, int $contactId): array
    {
        $folderModel = new sandboxFolderModel();
        $folder = $folderModel->getById($id);
        $this->checkAccess($folder);

        return [
            'type'        => 'folder',
            'version'     => '1.0',
            'name'        => $folder['name'],
            'description' => $folder['description'],
            'snippets'    => $this->exportFolderContents($id),
            'subfolders'  => $this->exportSubfolders($id),
        ];
    }

    private function exportFolderContents(int $folderId): array
    {
        $model = new sandboxSnippetModel();
        $snippets = $model->getByField('folder_id', $folderId, true);
        return array_map(fn($s) => [
            'name'        => $s['name'],
            'description' => $s['description'],
            'code_php'    => $s['code_php'],
            'code_smarty' => $s['code_smarty'],
        ], $snippets);
    }

    private function exportSubfolders(int $parentId): array
    {
        $model = new sandboxFolderModel();
        $children = $model->getByField('parent_id', $parentId, true);
        return array_map(fn($f) => [
            'name'        => $f['name'],
            'description' => $f['description'],
            'snippets'    => $this->exportFolderContents((int)$f['id']),
            'subfolders'  => $this->exportSubfolders((int)$f['id']),
        ], $children);
    }

    private function exportEnvironment(int $id, int $contactId): array
    {
        $model = new sandboxEnvironmentModel();
        $env = $model->getById($id);
        $this->checkAccess($env);
        return [
            'type'      => 'environment',
            'version'   => '1.0',
            'name'      => $env['name'],
            'variables' => json_decode($env['variables'], true) ?: [],
        ];
    }

    private function importSnippet(array $data, int $contactId): array
    {
        $model = new sandboxSnippetModel();
        $newId = $model->add([
            'contact_id'  => $contactId,
            'name'        => $data['name'] ?? _w('Imported snippet'),
            'description' => $data['description'] ?? '',
            'code_php'    => $data['code_php'] ?? '',
            'code_smarty' => $data['code_smarty'] ?? '',
            'is_shared'   => 0,
        ]);
        return $model->getById($newId);
    }

    private function importFolder(array $data, int $contactId, ?int $parentId = null): array
    {
        $folderModel = new sandboxFolderModel();
        $folderId = $folderModel->add([
            'contact_id'  => $contactId,
            'parent_id'   => $parentId,
            'name'        => $data['name'] ?? _w('Imported folder'),
            'description' => $data['description'] ?? '',
            'is_shared'   => 0,
        ]);

        $snippetModel = new sandboxSnippetModel();
        foreach (($data['snippets'] ?? []) as $sData) {
            $snippetModel->add([
                'contact_id'  => $contactId,
                'folder_id'   => $folderId,
                'name'        => $sData['name'] ?? _w('Snippet'),
                'description' => $sData['description'] ?? '',
                'code_php'    => $sData['code_php'] ?? '',
                'code_smarty' => $sData['code_smarty'] ?? '',
                'is_shared'   => 0,
            ]);
        }

        foreach (($data['subfolders'] ?? []) as $sfData) {
            $this->importFolder($sfData, $contactId, $folderId);
        }

        return $folderModel->getById($folderId);
    }

    private function importEnvironment(array $data, int $contactId): array
    {
        $model = new sandboxEnvironmentModel();
        $newId = $model->add([
            'contact_id' => $contactId,
            'name'       => $data['name'] ?? _w('Imported environment'),
            'is_shared'  => 0,
            'variables'  => json_encode($data['variables'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        return $model->getById($newId);
    }
}
