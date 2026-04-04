<?php

class sandboxEnvironmentModel extends waModel
{
    protected $table = 'sandbox_environment';

    public function getAccessible(int $contactId): array
    {
        return $this->select('*')
            ->where('is_shared = 1 OR contact_id = :cid', ['cid' => $contactId])
            ->order('name ASC')
            ->fetchAll();
    }

    public function getVariables(int $id): array
    {
        $env = $this->getById($id);
        if (!$env || !$env['variables']) {
            return [];
        }
        return json_decode($env['variables'], true) ?: [];
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
