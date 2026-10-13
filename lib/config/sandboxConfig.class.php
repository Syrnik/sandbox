<?php

declare(strict_types=1);

class sandboxConfig extends waAppConfig
{
    private ?sandboxHelper $helper = null;
    private ?bool $is_cloud = null;

    /**
     * Whether the app must behave as disabled (Webasyst Cloud environment).
     *
     * @return bool
     * @throws waException
     */
    public function isCloud(): bool
    {
        if ($this->is_cloud === null) {
            // Дешёвый локальный флаг первым, чтобы не дёргать getApps() без необходимости.
            $this->is_cloud = !empty($this->getOption('force_cloud')) || wa()->appExists('cloud');
        }
        return $this->is_cloud;
    }

    /**
     * @param string $name
     * @return waModel
     * @throws waException
     */
    public function getModel(string $name): waModel
    {
        if ($this->isCloud()) {
            throw new waException(_w('Sandbox is not available in Webasyst Cloud'));
        }
        if(waConfig::get('is_template')) {
            throw new waException(_w('You can not use sandbox models in template'));
        }
        $class = 'sandbox' . $name . 'Model';
        return new $class($this->getDbType());
    }

    public function getDbType(): ?string
    {
        if(waConfig::get('is_template')) {
            throw new waException(_w('You can not use sandbox models in template'));
        }

        $type = $this->options['db_connection'] ?? null;
        if ($type !== null && !array_key_exists($type, wa()->getConfig()->getDatabase())) {
            $type = null;
        }
        return $type;
    }

    public function getContactId(): int
    {
        return (int) ($this->options['contact_id'] ?? wa()->getUser()->getId());
    }

    private function getHelper(): sandboxHelper
    {
        if ($this->helper === null) {
            $this->helper = new sandboxHelper($this->getContactId());
        }
        return $this->helper;
    }

    public function setVar(string $name, mixed $value): void
    {
        if ($this->isCloud()) {
            return;
        }
        $old_is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        $this->getHelper()->set($name, $value);
        waConfig::set('is_template', $old_is_template);
    }

    public function getVar(string $name, mixed $default = null): mixed
    {
        if ($this->isCloud()) {
            return $default;
        }
        $old_is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        $result = $this->getHelper()->get($name, $default);
        waConfig::set('is_template', $old_is_template);
        return $result;
    }

    public function deleteVar(string $name): void
    {
        if ($this->isCloud()) {
            return;
        }
        $old_is_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        $this->getHelper()->delete($name);
        waConfig::set('is_template', $old_is_template);
    }

    public function getAllVars(): array
    {
        if ($this->isCloud()) {
            return [];
        }
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
