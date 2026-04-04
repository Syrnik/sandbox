<?php

class sandboxFolderModel extends waModel
{
    protected $table = 'sandbox_folder';

    public function getAccessible(int $contactId): array
    {
        return $this->select('*')
            ->where('is_shared = 1 OR contact_id = :cid', ['cid' => $contactId])
            ->order('sort ASC, name ASC')
            ->fetchAll();
    }

    public function getChildren(?int $parentId, int $contactId): array
    {
        if ($parentId === null) {
            return $this->select('*')
                ->where('parent_id IS NULL AND (is_shared = 1 OR contact_id = :cid)', ['cid' => $contactId])
                ->order('sort ASC, name ASC')
                ->fetchAll();
        }
        return $this->select('*')
            ->where('parent_id = :pid AND (is_shared = 1 OR contact_id = :cid)', [
                'pid' => $parentId,
                'cid' => $contactId,
            ])
            ->order('sort ASC, name ASC')
            ->fetchAll();
    }

    public function getTree(int $contactId): array
    {
        $all = $this->getAccessible($contactId);
        return $this->buildTree($all, null);
    }

    private function buildTree(array $all, ?int $parentId): array
    {
        $result = [];
        foreach ($all as $item) {
            $itemParent = $item['parent_id'] === null ? null : (int)$item['parent_id'];
            if ($itemParent === $parentId) {
                $item['children'] = $this->buildTree($all, (int)$item['id']);
                $result[] = $item;
            }
        }
        return $result;
    }

    public function add(array $data): int
    {
        $data['create_datetime'] = date('Y-m-d H:i:s');
        return $this->insert($data);
    }
}
