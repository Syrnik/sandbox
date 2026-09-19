<?php

class sandboxHelper
{
    private int $contactId;
    private ?waModel $model = null;

    public function __construct(int $contactId)
    {
        $this->contactId = $contactId;
    }

    private function getModel(): waModel
    {
        if ($this->model === null) {
            $this->model = sandbox()->getModel('Variable');
        }
        return $this->model;
    }

    public function set(string $name, mixed $value): void
    {
        if (sandbox()->isCloud()) {
            return;
        }
        $serialized = is_string($value) ? $value : serialize($value);
        $this->getModel()->setValue($this->contactId, $name, $serialized);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        if (sandbox()->isCloud()) {
            return $default;
        }
        $value = $this->getModel()->getValue($this->contactId, $name);
        if ($value === null) {
            return $default;
        }
        $unserialized = @unserialize($value);
        return $unserialized !== false ? $unserialized : $value;
    }

    public function delete(string $name): void
    {
        if (sandbox()->isCloud()) {
            return;
        }
        $this->getModel()->deleteByName($this->contactId, $name);
    }

    public function getAll(): array
    {
        if (sandbox()->isCloud()) {
            return [];
        }
        return $this->getModel()->getAllByContact($this->contactId);
    }
}
