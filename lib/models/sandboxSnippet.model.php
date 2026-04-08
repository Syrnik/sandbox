<?php

class sandboxSnippetModel extends waModel
{
    protected $table = 'sandbox_snippet';

    public function getAccessible(int $contactId, ?int $folderId = null): array
    {
        $where = '(is_shared = 1 OR contact_id = :cid)';
        $params = ['cid' => $contactId];

        if ($folderId !== null) {
            $where .= ' AND folder_id = :fid';
            $params['fid'] = $folderId;
        } else {
            $where .= ' AND folder_id IS NULL';
        }

        return $this->select('*')
            ->where($where, $params)
            ->order('sort ASC, name ASC')
            ->fetchAll();
    }

    public function getRecent(int $contactId, int $limit = 10): array
    {
        return $this->select('*')
            ->where('contact_id = :cid', ['cid' => $contactId])
            ->order('COALESCE(update_datetime, create_datetime) DESC')
            ->limit($limit)
            ->fetchAll();
    }

    public function add(array $data): int
    {
        $data['create_datetime'] = date('Y-m-d H:i:s');
        return $this->insert($data);
    }

    public function edit(int $id, array $data): void
    {
        $data['update_datetime'] = date('Y-m-d H:i:s');
        $this->updateById($id, $data);
    }
}
