<?php

class sandboxVariableModel extends waModel
{
    protected $table = 'sandbox_variable';

    public function getValue(int $contactId, string $name): ?string
    {
        $row = $this->getByField(['contact_id' => $contactId, 'name' => $name]);
        return $row ? $row['value'] : null;
    }

    public function setValue(int $contactId, string $name, mixed $value): void
    {
        $existing = $this->getByField(['contact_id' => $contactId, 'name' => $name]);
        $now = date('Y-m-d H:i:s');
        if ($existing) {
            $this->updateByField(
                ['contact_id' => $contactId, 'name' => $name],
                ['value' => $value, 'update_datetime' => $now]
            );
        } else {
            $this->insert([
                'contact_id'      => $contactId,
                'name'            => $name,
                'value'           => $value,
                'create_datetime' => $now,
            ]);
        }
    }

    public function getAllByContact(int $contactId): array
    {
        return $this->getByField('contact_id', $contactId, true);
    }

    public function deleteByName(int $contactId, string $name): void
    {
        $this->deleteByField(['contact_id' => $contactId, 'name' => $name]);
    }
}
