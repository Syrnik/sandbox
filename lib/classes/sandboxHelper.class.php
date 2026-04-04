<?php

class sandboxHelper
{
    private int $contactId;
    private sandboxVariableModel $model;

    public function __construct(int $contactId)
    {
        $this->contactId = $contactId;
        $this->model = new sandboxVariableModel();
    }

    public function set(string $name, mixed $value): void
    {
        $serialized = is_string($value) ? $value : serialize($value);
        $this->model->setValue($this->contactId, $name, $serialized);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        $value = $this->model->getValue($this->contactId, $name);
        if ($value === null) {
            return $default;
        }
        $unserialized = @unserialize($value);
        return $unserialized !== false ? $unserialized : $value;
    }

    public function delete(string $name): void
    {
        $this->model->deleteByName($this->contactId, $name);
    }

    public function getAll(): array
    {
        return $this->model->getAllByContact($this->contactId);
    }
}
