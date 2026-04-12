<?php

declare(strict_types=1);

class sandboxConfig extends waAppConfig
{
    private ?sandboxHelper $helper = null;

    private function getHelper(): sandboxHelper
    {
        if ($this->helper === null) {
            $this->helper = new sandboxHelper((int) wa()->getUser()->getId());
        }
        return $this->helper;
    }

    public function setVar(string $name, mixed $value): void
    {
        $this->getHelper()->set($name, $value);
    }

    public function getVar(string $name, mixed $default = null): mixed
    {
        return $this->getHelper()->get($name, $default);
    }

    public function deleteVar(string $name): void
    {
        $this->getHelper()->delete($name);
    }

    public function getAllVars(): array
    {
        return $this->getHelper()->getAll();
    }
}

function sandbox(): sandboxConfig
{
    return wa('sandbox')->getConfig();
}
