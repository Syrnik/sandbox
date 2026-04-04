<?php

class sandboxExecutor
{
    private int $contactId;

    public function __construct(int $contactId)
    {
        $this->contactId = $contactId;
    }

    /**
     * @return array{output: string, error: string|null, execution_time: float}
     */
    public function execute(string $codePHP, string $codeSmarty, int $environmentId = 0): array
    {
        $startTime = microtime(true);
        $output = '';
        $error = null;

        $envVars = [];
        if ($environmentId > 0) {
            $envModel = new sandboxEnvironmentModel();
            $envVars = $envModel->getVariables($environmentId);
        }

        $sandbox = new sandboxHelper($this->contactId);

        $phpOutput = '';
        $phpVars = [];
        if (trim($codePHP) !== '') {
            $phpResult = $this->executePHP($codePHP, $envVars, $sandbox);
            $phpOutput = $phpResult['output'];
            $error = $phpResult['error'];
            $phpVars = $phpResult['vars'];
        }

        $smartyOutput = '';
        if (trim($codeSmarty) !== '' && $error === null) {
            $smartyResult = $this->renderSmarty($codeSmarty, array_merge($envVars, $phpVars));
            $smartyOutput = $smartyResult['output'];
            if ($smartyResult['error']) {
                $error = ($error ? $error . "\n" : '') . $smartyResult['error'];
            }
        }

        $output = $phpOutput;
        if ($smartyOutput) {
            $output .= ($output ? "\n" : '') . $smartyOutput;
        }

        return [
            'output'         => $output,
            'error'          => $error,
            'execution_time' => round(microtime(true) - $startTime, 4),
        ];
    }

    private function executePHP(string $code, array $envVars, sandboxHelper $sandbox): array
    {
        $output = '';
        $error = null;
        $vars = [];

        extract($envVars, EXTR_PREFIX_SAME, 'env_');

        ob_start();
        try {
            $code = preg_replace('/^\s*<\?php\s*/i', '', $code);
            $code = preg_replace('/\s*\?>\s*$/', '', $code);

            eval($code);

            $vars = get_defined_vars();
            unset($vars['code'], $vars['envVars'], $vars['sandbox'],
                  $vars['output'], $vars['error'], $vars['vars']);

        } catch (\Throwable $e) {
            $error = $e->getMessage() . ' in line ' . $e->getLine();
        }
        $output = ob_get_clean();

        return ['output' => $output, 'error' => $error, 'vars' => $vars];
    }

    private function renderSmarty(string $code, array $vars): array
    {
        $output = '';
        $error = null;

        try {
            $view = wa()->getView();
            foreach ($vars as $key => $value) {
                $view->assign($key, $value);
            }
            $output = $view->fetch('string:' . $code);
        } catch (\Throwable $e) {
            $error = 'Smarty error: ' . $e->getMessage();
        }

        return ['output' => $output, 'error' => $error];
    }
}
