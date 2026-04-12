<?php

declare(strict_types=1);

class sandboxViewHelper extends waAppViewHelper
{
    public function setVar(string $name, mixed $value): void
    {
        sandbox()->setVar($name, $value);
    }

    public function getVar(string $name, mixed $default = null): mixed
    {
        return sandbox()->getVar($name, $default);
    }

    public function deleteVar(string $name): void
    {
        sandbox()->deleteVar($name);
    }

    public function getAllVars(): array
    {
        return sandbox()->getAllVars();
    }
}
