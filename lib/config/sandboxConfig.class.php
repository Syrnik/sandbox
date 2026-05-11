<?php

declare(strict_types=1);

class sandboxConfig extends waAppConfig
{
    private ?sandboxHelper $helper = null;

    /**
     * @param string $name
     * @return waModel
     * @throws waException
     */
    public function getModel(string $name): waModel
    {
        if(waConfig::get('is_template')) {
            throw new waException('You can not use sandbox models in template');
        }
        $class = 'sandbox' . $name . 'Model';
        return new $class($this->getDbType());
    }

    public function getDbType(): ?string
    {
        if(waConfig::get('is_template')) {
            throw new waException('You can not use sandbox models in template');
        }

        $type = $this->options['db_connection'] ?? null;
        if ($type !== null && !array_key_exists($type, wa()->getConfig()->getDatabase())) {
            $type = null;
        }
        return $type;
    }

    private function getHelper(): sandboxHelper
    {
        if ($this->helper === null) {
            $this->helper = new sandboxHelper((int) wa()->getUser()->getId());
        }
        return $this->helper;
    }

    public function setVar(string $name, mixed $value): void
    {
        $old_is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        $this->getHelper()->set($name, $value);
        waConfig::set('is_template', $old_is_template);
    }

    public function getVar(string $name, mixed $default = null): mixed
    {
        $old_is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        $result = $this->getHelper()->get($name, $default);
        waConfig::set('is_template', $old_is_template);
        return $result;
    }

    public function deleteVar(string $name): void
    {
        $old_is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        $this->getHelper()->delete($name);
        waConfig::set('is_template', $old_is_template);
    }

    public function getAllVars(): array
    {
        $old_is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        $result = $this->getHelper()->getAll();
        waConfig::set('is_template', $old_is_template);
        return $result;
    }
}

function sandbox(): sandboxConfig
{
    return wa('sandbox')->getConfig();
}
