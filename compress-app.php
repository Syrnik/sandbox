<?php
/**
 * Standalone App Compress Script
 *
 * Checks and packages a Webasyst app into {app_id}.tar.gz.
 * No Webasyst framework required — works standalone in CI/CD.
 *
 * Usage:
 *   php compress-app.php <app_id> [options]
 *
 *   -style  true|false|no-vendors   Code checks (default: false — disabled)
 *   -skip   compress|test|all|none  Operations to skip (default: none)
 *   -php    /path/to/php            Custom PHP binary for syntax check
 *   -apps-dir /path/to/wa-apps/    apps directory (default: ./wa-apps/ beside script)
 *   -app-path /absolute/path/       Direct app path — overrides apps-dir+app_id
 *
 * Examples:
 *   php compress-app.php apicollection
 *   php compress-app.php apicollection -skip compress
 *   php compress-app.php apicollection -app-path /workspace/apicollection
 */

class AppCompressor
{
    /** @var string */
    private $appId;

    /** @var string */
    private $appPath;

    /** @var string[] */
    private $files = [];

    /** @var array */
    private $params = [];

    private static $defaults = [
        'style' => 'false',
        'skip'  => 'none',
    ];

    public function __construct(array $argv)
    {
        if (!extension_loaded('phar')) {
            throw new RuntimeException('PHP extension "phar" is required');
        }
        $this->parseArgs($argv);
    }

    // -------------------------------------------------------------------------
    // Main flow
    // -------------------------------------------------------------------------

    public function run(): void
    {
        $this->initPath();

        $skip  = $this->params['skip'] ?? 'none';
        $style = array_map('trim', explode(',', $this->params['style'] ?? 'false'));

        $ok = true;

        if (!in_array($skip, ['test', 'all'], true)) {
            $this->out('');
            $ok = $this->test($style);
            $this->out($ok ? 'Config check OK' : 'Config check FAILED');

            if (!in_array('false', $style, true)) {
                $codeOk = $this->checkCode();
                $this->out($codeOk ? 'Code check OK' : 'Code check FAILED');
                $ok = $ok && $codeOk;
            } else {
                $this->out('Code style check disabled');
            }
        } else {
            $this->out('Config check skipped');
        }

        if ($ok && !in_array($skip, ['compress', 'all'], true)) {
            $this->out('');
            $this->compress();
        } elseif ($ok) {
            $this->out('Test completed');
        } else {
            exit(1);
        }
    }

    // -------------------------------------------------------------------------
    // Argument parsing
    // -------------------------------------------------------------------------

    private function parseArgs(array $argv): void
    {
        if (count($argv) < 2 || in_array($argv[1], ['--help', '-h', 'help'], true)) {
            $this->printHelp();
            exit(0);
        }

        $this->appId = $argv[1];
        if (!preg_match('/^[a-z][a-z0-9_]+$/', $this->appId)) {
            throw new InvalidArgumentException(
                "Invalid app_id '{$this->appId}'. Must match [a-z][a-z0-9_]+"
            );
        }

        $params = self::$defaults;
        $i = 2;
        while ($i < count($argv)) {
            $key = ltrim($argv[$i], '-');
            $i++;
            if ($i < count($argv) && substr($argv[$i], 0, 1) !== '-') {
                $params[$key] = $argv[$i];
                $i++;
            } else {
                $params[$key] = true;
            }
        }
        $this->params = $params;
    }

    // -------------------------------------------------------------------------
    // Path initialization and file collection
    // -------------------------------------------------------------------------

    private function initPath(): void
    {
        // -app-path overrides apps-dir + app_id
        if (!empty($this->params['app-path'])) {
            $this->appPath = rtrim($this->params['app-path'], '/\\');
        } else {
            $scriptDir = dirname(realpath(__FILE__));
            $appsDir = isset($this->params['apps-dir'])
                ? rtrim($this->params['apps-dir'], '/\\')
                : $scriptDir . '/wa-apps';
            $this->appPath = $appsDir . '/' . $this->appId;
        }

        if (!is_dir($this->appPath)) {
            throw new RuntimeException("App directory not found: {$this->appPath}");
        }

        $this->outf('Check & compress app: %s', $this->appId);
        $this->outf('PHP version: %s', PHP_VERSION);
        $this->outf('App path: %s', $this->appPath);

        $this->files = $this->listFilesRecursive($this->appPath);
        sort($this->files);

        // App-specific excludes (plugins/themes/widgets are distributed separately)
        $blacklist = [
            '@^plugins/.+@'           => "application's plugins",
            '@^themes/(?!default).+@' => "application's non-default themes",
            '@^widgets/.+@'           => "application's widgets",
        ];
        $whitelist = [];

        // Merge rules from .gitignore
        $gitignore = $this->appPath . '/.gitignore';
        if (file_exists($gitignore)) {
            $rules = $this->parseGitignore($gitignore);
            $blacklist = array_merge($rules['blacklist'], $blacklist);
            $whitelist = array_merge($rules['whitelist'], $whitelist);
        }

        // Merge rules from lib/config/exclude.php
        $excludeFile = $this->appPath . '/lib/config/exclude.php';
        if (file_exists($excludeFile)) {
            $exclude = include($excludeFile);
            if (is_array($exclude)) {
                foreach (self::makePatterns($exclude) as $pattern) {
                    $blacklist[$pattern] = 'disabled at exclude.php';
                }
            }
        }

        $skipped = $this->filter($this->files, $blacklist, $whitelist);

        if ($skipped) {
            $this->out(str_repeat('-', 80));
            $this->outf('IGNORE %d FILE(S)', count($skipped));
            $this->out(str_repeat('-', 80));
            $n = 0;
            foreach ($skipped as $file => $description) {
                $this->outf('%3d | %-40s | %s', ++$n, $file, $description);
            }
            $this->out(str_repeat('-', 80));
        }
    }

    // -------------------------------------------------------------------------
    // File listing
    // -------------------------------------------------------------------------

    /**
     * @return string[]
     */
    private function listFilesRecursive(string $dir): array
    {
        $files   = [];
        $baseLen = strlen($dir) + 1;
        $iter    = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            if ($item->isFile()) {
                $files[] = str_replace('\\', '/', substr($item->getPathname(), $baseLen));
            }
        }
        return $files;
    }

    // -------------------------------------------------------------------------
    // File filtering
    // -------------------------------------------------------------------------

    /**
     * Remove blacklisted files (unless whitelisted) and return the skipped list.
     *
     * @param string[] $files
     * @param array    $blacklist  pattern => description
     * @param array    $whitelist  pattern => description
     * @return array   file => reason
     */
    private function filter(array &$files, array $blacklist, array $whitelist): array
    {
        // Standard blacklist — mirrors the original webasystCompress rules
        $blacklist = array_merge($blacklist, [
            '@^lib/updates/dev/.+@'                                => 'developer stage updates',
            '@^lib/config/exclude\.php@'                           => 'exclude files list',
            '@\.styl$@'                                            => 'CSS preprocessor files',
            '@\.(bak|old|user|te?mp|www)(\.(php|css|js|html))?$@' => 'temp file',
            '@(locale)\/.+\.(te?mp)(\.(po|mo))?$@'                => 'temp files in locale directory',
            '@(/|^)(\.DS_Store|\.desktop\.ini|thumbs\.db)$@i'      => 'system file',
            '@\b\.(svn|git|hg_archival\.txt)\b@'                   => 'CVS file',
            '@(/|^)\.git.*@'                                       => 'GIT file',
            '@(/|^)\.[^/]+/@'                                      => 'directory with leading dot',
            '@(/|^)\.(project|buildpath)@'                         => 'IDE file',
            '@\.(zip|rar|gz)$@'                                    => 'archive',
            '@\.log$@'                                             => 'log file',
            '@\.md5$@'                                             => 'checksum file',
            '@\.(exe|dll|sys)$@'                                   => 'executable file',
            '@(/|^)[^\.]*todo$@i'                                  => 'TODO file',
            '@(/|^)[^\.]+$@'                                       => 'unknown type file',
            '@(/|^)[^0-9a-z_\-\.]+$@'                              => 'invalid filename characters',
            '@\.fw_@'                                              => 'internal files',
        ]);

        $skipped = [];
        foreach ($files as $id => $file) {
            foreach ($blacklist as $pattern => $description) {
                if (!preg_match($pattern, $file)) {
                    continue;
                }
                // Check whitelist override
                $whitelisted = false;
                foreach ($whitelist as $wPattern => $wDesc) {
                    if (preg_match($wPattern, $file)) {
                        $whitelisted = true;
                        break;
                    }
                }
                if (!$whitelisted) {
                    $skipped[$file] = $description;
                    unset($files[$id]);
                }
                break;
            }
        }
        $files = array_values($files);
        return $skipped;
    }

    // -------------------------------------------------------------------------
    // Gitignore / exclude pattern helpers
    // -------------------------------------------------------------------------

    private function parseGitignore(string $file): array
    {
        $blacklist = [];
        $whitelist = [];
        foreach (file($file) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if ($line[0] === '!') {
                $sub = substr($line, 1);
                $whitelist[self::getGitPattern($sub)] = 'Gitignore rule !' . $sub;
            } else {
                $blacklist[self::getGitPattern($line)] = 'Gitignore rule ' . $line;
            }
        }
        return compact('blacklist', 'whitelist');
    }

    private static function getGitPattern(string $pattern): string
    {
        $pattern = preg_replace('@^/\*\*/@', '', $pattern);
        $pattern = preg_replace('@^/@', '^', $pattern);
        $pattern = preg_replace('@/\*\*/@', '([^/]+/){0,}', $pattern);
        $pattern = preg_replace('@\*@', '.*', $pattern);
        return "@{$pattern}@";
    }

    /**
     * Convert simple glob-style strings (from exclude.php) to regex patterns.
     *
     * @param string[] $patterns
     * @return string[]
     */
    private static function makePatterns(array $patterns, string $basePath = null): array
    {
        $metaChars = ['+', '.', '(', ')', '[', ']', '{', '}', '<', '>', '^', '$', '@'];
        foreach ($metaChars as &$char) {
            $char = "\\{$char}";
        }
        unset($char);

        $commandChars = ['?', '*'];
        foreach ($commandChars as &$char) {
            $char = "\\{$char}";
        }
        unset($char);

        $cleanupPattern = '@(' . implode('|', $metaChars) . ')@';
        $commandPattern = '@(' . implode('|', $commandChars) . ')@';

        if ($basePath) {
            $basePath = preg_replace('@([/\\\\]+)@', '/', $basePath . '/');
            $basePath = preg_replace($cleanupPattern, '\\\\$1', $basePath);
        }

        foreach ($patterns as &$pattern) {
            $pattern = preg_replace($cleanupPattern, '\\\\$1', $pattern);
            $pattern = preg_replace($commandPattern, '.$1', $pattern);
            $pattern = "@^{$basePath}({$pattern})@m";
        }
        unset($pattern);

        return $patterns;
    }

    // -------------------------------------------------------------------------
    // Config loading and token analysis
    // -------------------------------------------------------------------------

    /**
     * Safely include lib/config/{name}.php and return its value.
     *
     * @return array|null|false  array on success, null if file absent, false if invalid
     */
    private function loadConfig(string $name)
    {
        $path = $this->appPath . '/lib/config/' . $name . '.php';
        if (!file_exists($path)) {
            return null;
        }
        $config = include($path);
        return is_array($config) ? $config : false;
    }

    /**
     * Token-analyze a config file for forbidden constructs (no functions, classes, etc.).
     */
    private function checkConfig(string $name): bool
    {
        $path    = $this->appPath . '/lib/config/' . $name . '.php';
        $relName = '/lib/config/' . $name . '.php';

        if (!file_exists($path)) {
            return true;
        }

        $blacklistedTokens = $this->getConfigTokensBlacklist();
        $tokens = token_get_all(file_get_contents($path));
        $result = true;
        $skip   = [];

        foreach ($tokens as $id => $token) {
            if (isset($skip[$id]) || !is_array($token)) {
                continue;
            }

            if (in_array($token[0], $blacklistedTokens)) {
                if ($result) {
                    $this->outf("\nERROR encountered in config file %s:", $relName);
                }
                $result = false;
                $this->outf("\tUnexpected '%s' (%s) on line %d", $token[1], token_name($token[0]), $token[2]);

            } elseif ($token[0] === T_OPEN_TAG && $token[1] === '<?') {
                if ($result) {
                    $this->outf("\nERROR encountered in config file %s:", $relName);
                }
                $result = false;
                $this->outf("\tPHP short open tag not allowed on line %d", $token[2]);

            } elseif ($token[0] === T_STRING && !in_array($token[1], ['true', 'false', 'null'], true)) {
                $next  = $tokens[$id + 1] ?? null;
                $next2 = $tokens[$id + 2] ?? null;
                if (is_array($next) && $next[0] === T_DOUBLE_COLON) {
                    if (is_array($next2) && $next2[0] === T_STRING) {
                        $constant = "{$token[1]}::{$next2[1]}";
                        if (!defined($constant)) {
                            if ($result) {
                                $this->outf("\nERROR encountered in config file %s:", $relName);
                            }
                            $result = false;
                            $this->outf("\tUndefined constant '%s' on line %d", $constant, $token[2]);
                        }
                        $skip[$id + 1] = true;
                        $skip[$id + 2] = true;
                        continue;
                    }
                }
                if ($result) {
                    $this->outf("\nERROR encountered in config file %s:", $relName);
                }
                $result = false;
                $this->outf("\tUnexpected '%s' (%s) on line %d", $token[1], token_name($token[0]), $token[2]);
            }
        }

        if (!$result) {
            $this->out('');
        }

        return $result;
    }

    private function getConfigTokensBlacklist(): array
    {
        $list = [
            T_FUNC_C, T_FUNCTION, T_INTERFACE, T_CLASS, T_CLASS_C,
            T_CONST, T_DOUBLE_COLON, T_OPEN_TAG_WITH_ECHO, T_EXIT,
            T_NEW, T_PRINT, T_ECHO, T_DECLARE, T_GLOBAL, T_HALT_COMPILER,
        ];
        foreach (['T_NAMESPACE', 'T_TRAIT', 'T_TRAIT_C', 'T_USE', 'T_OPEN_SHORT_ARRAY', 'T_YIELD'] as $c) {
            if (defined($c)) {
                $list[] = constant($c);
            }
        }
        return $list;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * @param string[] $style
     */
    private function test(array $style): bool
    {
        $result = true;
        $result = $this->testConfig() && $result;
        $result = $this->testRequirements() && $result;
        $result = $this->testPhp($style) && $result;
        $result = $this->testDb() && $result;
        $result = $this->testInstall() && $result;
        return $result;
    }

    private function testConfig(): bool
    {
        $this->out('Start test item config.');
        $valid = true;

        $available = [
            'name', 'description', 'version', 'vendor', 'img', 'icon', 'logo',
            'frontend', 'license', 'critical', 'locale',
            'plugins', 'sms_plugins', 'shipping_plugins', 'payment_plugins',
            'routing_params', 'pages', 'themes', 'rights', 'csrf', 'auth',
            'my_account', 'mobile', 'sash_color', 'system', 'ui',
        ];

        if (!$this->checkConfig('app')) {
            $this->out('ERROR: Invalid item config. Config full test skipped.');
            return false;
        }

        $config = $this->loadConfig('app');
        if ($config === null) {
            $this->out('ERROR: lib/config/app.php not found');
            return false;
        }
        if ($config === false) {
            $this->out('ERROR: lib/config/app.php does not return an array');
            return false;
        }

        $unknown = array_diff(array_keys($config), $available);
        if ($unknown) {
            $this->outf("Unknown config options: %s", implode(', ', $unknown));
        }

        $hasImages = false;
        foreach (['icon', 'img', 'logo'] as $field) {
            if (!empty($config[$field])) {
                foreach ((array)$config[$field] as $imgFile) {
                    $imgFile = '/' . ltrim($imgFile, '/');
                    if (!file_exists($this->appPath . $imgFile)) {
                        $this->outf('WARNING: not found %s file %s', $field, $imgFile);
                        $valid = false;
                    } else {
                        $hasImages = true;
                    }
                }
            }
        }
        if (!$hasImages) {
            $this->out('WARNING: not found any of icon, img, logo in app config');
            $valid = false;
        }

        $valid = $this->testRouting($config) && $valid;

        if ($valid) {
            $this->out("\tOK");
        }
        return $valid;
    }

    private function testRouting(array $config): bool
    {
        if (!empty($config['frontend']) && !empty($config['themes'])) {
            $this->out("WARNING: themes option is ignored when frontend is enabled");
        }

        $routing = $this->loadConfig('routing');
        if (empty($routing) && !empty($config['frontend'])) {
            $this->out("ERROR: empty routing.php for frontend app");
            return false;
        }
        return true;
    }

    private function testRequirements(): bool
    {
        $result = true;
        $this->out('Start checking system requirements');

        $requirements = $this->loadConfig('requirements');
        if ($requirements === null) {
            $this->out("\tPASSED (no requirements.php)");
            return true;
        }
        if ($requirements === false) {
            $this->out('ERROR: Invalid requirements.php');
            return false;
        }
        if (!$this->checkConfig('requirements')) {
            return false;
        }

        foreach ($requirements as $key => $info) {
            if (preg_match('@^php\.(.+)$@', $key, $m) && !extension_loaded($m[1])) {
                $this->outf('NOTICE: extension "%s" not loaded on this machine (strict check skipped)', $m[1]);
            }
        }

        $this->outf("\t%s", $result ? 'PASSED' : 'FAILED');
        return $result;
    }

    private function testDb(): bool
    {
        $result    = true;
        $namespace = $this->appId;

        $this->out('Start test item database section.');
        $db = $this->loadConfig('db');

        if ($db) {
            $pattern = "@^{$namespace}(_.+)?$@";
            foreach ($db as $table => $info) {
                if (!preg_match($pattern, $table)) {
                    $result = false;
                    $this->outf('Invalid table name: "%s"', $table);
                }
            }
            if (!$result) {
                $this->outf('Valid table names: "%1$s" or "%1$s_*"', $namespace);
            } else {
                $this->out("\tPassed");
            }
        } elseif ($db === null && in_array('lib/config/app.sql', $this->files)) {
            $this->out('NOTICE: app.sql is deprecated — use db.php');
            $this->outf('Valid table names: "%1$s" or "%1$s_*"', $namespace);
        } elseif ($db === false) {
            $this->out('ERROR: Invalid db.php');
            $result = false;
        } else {
            $this->out("\tPassed (no database)");
        }

        return $result;
    }

    private function testInstall(): bool
    {
        $this->out('Start test item install/uninstall section.');
        $install   = in_array('lib/config/install.php', $this->files);
        $uninstall = in_array('lib/config/uninstall.php', $this->files);

        if (($install && !$uninstall) || (!$install && $uninstall)) {
            $this->out('NOTICE: only one of install.php & uninstall.php is present');
        } else {
            $this->out("\tPassed");
        }
        return true;
    }

    /**
     * @param string[] $style
     */
    private function testPhp(array $style): bool
    {
        $result = true;

        if (!$this->procOpenAvailable()) {
            $this->out("WARNING: PHP syntax check SKIPPED (proc_open not available)");
            return true;
        }

        $phpBins = $this->getPhpBinaries();
        if (empty($phpBins)) {
            $this->out("PHP binary not found, syntax check SKIPPED");
            return true;
        }

        foreach ($phpBins as $phpBin => $versionStr) {
            $this->out("\nStart checking PHP syntax");
            $matches = [];
            $version = preg_match('@PHP\s+(\d+(\.\d+)+)(\s|$)@', $versionStr, $matches)
                ? $matches[1]
                : $versionStr;
            $this->outf("PHP Version:\t%s\nBinary path:\t%s", $version, $phpBin);

            $versionResult = true;
            $errors        = ['ignored' => 0, 'strict' => 0];

            foreach ($this->files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                $strict = !in_array('no-vendors', $style, true)
                    || !preg_match('@^(lib/|js/)?vendors?/@', $file);

                $command  = sprintf('%s -l -f "%s/%s"', $phpBin, $this->appPath, $file);
                $exitCode = $this->execCommand($command, $stdout, $stderr);

                if ($exitCode !== 0) {
                    // php -l reports errors in stdout; fall back to stderr
                    $lines = array_filter(array_merge($stdout, preg_split("@[\r\n]+@", $stderr)), 'trim');
                    $lines = array_filter($lines, static function ($l) {
                        return strpos($l, 'No syntax errors') === false;
                    });
                    if ($lines) {
                        $format = $strict ? "\nERROR at [%s]:" : "\nIgnored ERROR at vendor [%s]:";
                        $this->outf($format, $file);
                        foreach ($lines as $line) {
                            $this->outf("\t%s", trim(str_replace($this->appPath . '/' . $file, 'file', $line)));
                        }
                    }
                    if ($strict) {
                        $versionResult = false;
                        $errors['strict']++;
                    } else {
                        $errors['ignored']++;
                    }
                }
            }

            foreach ($errors as $type => $count) {
                if ($count) {
                    $this->outf("Found %d %s error(s)", $count, $type);
                }
            }
            $this->outf("PHP %s file syntax check\t%s", $version, $versionResult ? 'PASSED' : 'FAILED');
            $result = $result && $versionResult;
        }

        if (count($phpBins) > 1) {
            $this->outf("PHP file syntax check totally %s", $result ? 'PASSED' : 'FAILED');
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // Code token analysis (replaces CodeSniffer for basic checks)
    // -------------------------------------------------------------------------

    private function checkCode(): bool
    {
        $result = true;

        $varBlacklist = [
            '@^\$_(POST|GET|REQUEST|COOKIE|SERVER)$@' => 'Use waRequest or waStorage instead',
        ];
        $funcBlacklist = [
            '@^mysqli?_@'          => 'Use waModel instead',
            '@^eregi?(_replace)?$@' => 'Deprecated — use preg functions',
            '@^spliti?$@'          => 'Deprecated — use explode',
        ];

        $classes   = [];
        $functions = [];

        foreach ($this->files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $path   = $this->appPath . '/' . $file;
            $tokens = token_get_all(file_get_contents($path));

            foreach ($tokens as $id => $token) {
                if (!is_array($token)) {
                    continue;
                }

                switch ($token[0]) {
                    case T_CLASS:
                        // Find the class name (next T_STRING)
                        $next_id = $id;
                        do {
                            $next = $tokens[++$next_id] ?? [];
                        } while (is_array($next) && $next[0] !== T_STRING && $next_id < $id + 10);
                        if (is_array($next) && isset($next[1])) {
                            $classes[$next[1]][] = $file;
                        }
                        break;

                    case T_VARIABLE:
                    case T_STRING_VARNAME:
                        foreach ($varBlacklist as $pattern => $hint) {
                            if (preg_match($pattern, $token[1])) {
                                $this->outf(
                                    "Not allowed variable %s at %s:%d\n\t%s",
                                    $token[1], $file, $token[2], $hint
                                );
                                $result = false;
                            }
                        }
                        break;

                    case T_STRING:
                        if (function_exists($token[1])) {
                            $functions[$token[1]][] = $file;
                        }
                        break;

                    case T_EVAL:
                    case T_EXIT:
                        $this->outf("Not allowed token '%s' at %s:%d", $token[1], $file, $token[2]);
                        $result = false;
                        break;

                    case T_OPEN_TAG:
                        if ($token[1] === '<?') {
                            $this->outf("PHP short open tag not allowed at %s:%d", $file, $token[2]);
                            $result = false;
                        }
                        break;

                    case T_CLOSE_TAG:
                        $this->outf("PHP closing tag not recommended at %s:%d", $file, $token[2]);
                        break;
                }
            }
        }

        // Class name prefix check
        $classPattern = sprintf('@^%s\w+$@', $this->appId);
        foreach ($classes as $class => $classFiles) {
            if (!preg_match($classPattern, $class)) {
                $this->outf("Invalid class name '%s' at %s", $class, implode(', ', array_unique($classFiles)));
                $result = false;
            }
        }

        // Forbidden functions
        foreach ($functions as $func => $funcFiles) {
            foreach ($funcBlacklist as $pattern => $hint) {
                if (preg_match($pattern, $func)) {
                    $this->outf("Function '%s' not allowed\n\tHint: %s", $func, $hint);
                    $result = false;
                }
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // PHP binary helpers
    // -------------------------------------------------------------------------

    private function procOpenAvailable(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = preg_split('@[,\s]+@', (string)ini_get('disable_functions'));
        return !in_array('proc_open', $disabled, true);
    }

    private function getPhpBinaries(): array
    {
        $paths  = [];
        $phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $ver    = $this->getPhpVersion($phpBin);
        if ($ver === false && $phpBin !== 'php') {
            $phpBin = 'php';
            $ver    = $this->getPhpVersion('php');
        }
        if ($ver !== false) {
            $paths[$phpBin] = $ver;
        }

        if (!empty($this->params['php'])) {
            foreach (preg_split('@[\s;,]+@', $this->params['php']) as $bin) {
                $v = $this->getPhpVersion(trim($bin));
                if ($v !== false) {
                    $paths[trim($bin)] = $v;
                }
            }
        }
        return $paths;
    }

    /**
     * @return string|false
     */
    private function getPhpVersion(string $bin)
    {
        $exitCode = $this->execCommand(sprintf('%s -v', $bin), $stdout, $stderr);
        if ($exitCode === 0) {
            return implode("\n\t", array_filter($stdout, 'trim'));
        }
        return false;
    }

    private function execCommand(string $command, &$stdout, &$stderr): int
    {
        $stdout = [];
        $stderr = '';
        $spec   = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes  = [];

        $process = proc_open($command, $spec, $pipes);
        if (!is_resource($process)) {
            return -1;
        }

        $out    = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);
        $stdout   = preg_split("@[\r\n]+@", $out);
        return $exitCode;
    }

    // -------------------------------------------------------------------------
    // Compression
    // -------------------------------------------------------------------------

    private function compress(): void
    {
        $archivePath = $this->appPath . '/' . $this->appId . '.tar.gz';

        if (file_exists($archivePath)) {
            $mtime = max(filemtime($archivePath), filectime($archivePath));
            $this->outf('Removing previous archive from %s', date('Y-m-d H:i:s', $mtime));
            if (!unlink($archivePath)) {
                throw new RuntimeException("Cannot remove existing archive: {$archivePath}");
            }
        }

        // Write .files.md5
        $md5Path = $this->appPath . '/.files.md5';
        $this->md5Files($this->files, $md5Path);

        // Build .tar (PharData works with uncompressed tar first)
        $tarPath = $this->appPath . '/' . $this->appId . '.tar';
        if (file_exists($tarPath)) {
            if (!unlink($tarPath)) {
                throw new RuntimeException("Cannot remove existing tar file: {$tarPath}");
            }
        }

        $time = microtime(true);
        $tar  = new PharData($tarPath);

        $tar->addFile($md5Path, $this->appId . '/.files.md5');
        foreach ($this->files as $file) {
            $tar->addFile($this->appPath . '/' . $file, $this->appId . '/' . $file);
        }

        // PharData::compress() creates {name}.tar.gz alongside {name}.tar
        $tar->compress(Phar::GZ);
        unset($tar);

        // Clean up temp files
        foreach ([$tarPath, $md5Path] as $tmp) {
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }

        // PharData names the gz as {name}.tar.gz — rename to final destination if needed
        $createdGz = $tarPath . '.gz';
        if ($createdGz !== $archivePath) {
            if (file_exists($archivePath)) {
                unlink($archivePath);
            }
            rename($createdGz, $archivePath);
        }

        $size = filesize($archivePath);
        $this->outf("\ntime\t%d ms\nsize\t%0.2f KByte", (int)((microtime(true) - $time) * 1000), $size / 1024);
        $this->outf("Archive: %s", $archivePath);
    }

    private function md5Files(array $files, string $outPath): int
    {
        $fp = fopen($outPath, 'w');
        if (!$fp) {
            throw new RuntimeException('Cannot create checksum file: ' . $outPath);
        }

        $count     = 0;
        $totalSize = 0;
        $time      = microtime(true);

        foreach ($files as $file) {
            $fullPath = $this->appPath . '/' . $file;
            if (file_exists($fullPath)) {
                $md5   = md5_file($fullPath);
                $size  = filesize($fullPath);
                $totalSize += $size;
                fprintf($fp, "%s *%s\n", $md5, $file);
                $this->outf("%5d\t%-10s\t%32s\t%s", ++$count, $size, $md5, $file);
            } else {
                $this->outf("%5d\t%-10s\t%32s\t%s", $count, '0', 'missed', $file);
            }
        }

        fclose($fp);
        $this->outf(
            "md5: %d ms\t%d files, %0.2f KBytes",
            (int)((microtime(true) - $time) * 1000),
            $count,
            $totalSize / 1024
        );

        return $count;
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    public function out(string $msg = ''): void
    {
        echo $msg . "\n";
    }

    public function outf(string $fmt, ...$args): void
    {
        $this->out(vsprintf($fmt, $args));
    }

    // -------------------------------------------------------------------------
    // Help
    // -------------------------------------------------------------------------

    private function printHelp(): void
    {
        echo <<<'HELP'
Standalone App Compress Script — packages a Webasyst app into {app_id}.tar.gz
No Webasyst framework required. Works standalone in CI/CD.

Usage:
  php compress-app.php <app_id> [options]

Arguments:
  app_id              App ID (must match [a-z][a-z0-9_]+, e.g. apicollection)

Options:
  -style  VALUE       Code check mode:
                        true        Check all PHP/JS/CSS code
                        false       Disabled (default)
                        no-vendors  Skip lib/vendors/ and js/vendors/
  -skip   VALUE       Operations to skip:
                        compress    Test only, no archive
                        test        Archive only, no checks
                        all         Skip everything (dry run listing)
                        none        Run everything (default)
  -php    PATH        Custom PHP binary for syntax check (e.g. /usr/bin/php8.2)
  -apps-dir DIR       Path to wa-apps/ directory (default: ./wa-apps/ beside script)
  -app-path DIR       Direct path to the app directory — overrides apps-dir + app_id
                      Useful in CI/CD where the app repo is checked out directly.

Examples:
  # Standard local run
  php compress-app.php apicollection

  # Test only (no archive)
  php compress-app.php apicollection -skip compress

  # Archive only (skip tests)
  php compress-app.php apicollection -skip test

  # CI/CD: app checked out at /workspace/apicollection/
  php compress-app.php apicollection -app-path /workspace/apicollection

  # CI/CD: parent dir contains several apps
  php compress-app.php apicollection -apps-dir /workspace/apps

  # Dry run: list files that would be included
  php compress-app.php apicollection -skip all

HELP;
    }
}

// Entry point
try {
    (new AppCompressor($argv))->run();
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n\n");
    exit(1);
}
