<?php
/**
 * This file is part of the teamneusta/codeception-docker-chrome package.
 *
 * Copyright (c) 2017 neusta GmbH | Ein team neusta Unternehmen
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace Codeception\Extension;

use Codeception\Exception\ExtensionException;
use Codeception\Platform\Extension;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class DockerChrome extends Extension
{
    /**
     * events
     *
     * @var array
     */
    static public $events = [
        'module.init' => 'moduleInit',
    ];

    /**
     * process
     *
     * @var \Symfony\Component\Process\Process
     */
    private $process;

    /**
     * dockerStarted
     *
     * @var bool
     */
    private $dockerStarted = false;

    /**
     * dockerComposePath
     *
     * @var string
     */
    private $dockerComposePath;

    /**
     * DockerChrome constructor.
     *
     * @param array $config
     * @param array $options
     * @param \Symfony\Component\Process\Process|null $process
     * @param string $defaultDockerComposePath
     */
    public function __construct(
        array $config,
        array $options,
        Process $process = null,
        string $defaultDockerComposePath = '/usr/local/bin/docker-compose'
    ) {

        // Set default https proxy
        if (!isset($options['silent'])) {
            $options['silent'] = false;
        }

        $this->dockerComposePath = $defaultDockerComposePath;

        parent::__construct($config, $options);

        $this->initDefaultConfig();
        $command = $this->getCommand();
        $this->process = $process ?: new Process($command, realpath(__DIR__));
    }

    /**
     * initDefaultConfig
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     */
    protected function initDefaultConfig()
    {
        $this->config['path'] = $this->config['path'] ?? $this->dockerComposePath;

        // Set default WebDriver port
        $this->config['port'] = $this->config['port'] ?? 4444;

        // Set default debug mode
        $this->config['debug'] = $this->config['debug'] ?? false;

        // Set default http proxy
        $this->config['http_proxy'] = $this->config['http_proxy'] ?? '';

        // Set default https proxy
        $this->config['https_proxy'] = $this->config['https_proxy'] ?? '';

        if (!file_exists($this->config['path'])) {
            throw new ExtensionException($this, "File not found: {$this->config['path']}.");
        }
    }

    /**
     * getCommand
     *
     * @return string
     */
    private function getCommand(): string
    {
        return 'exec ' . escapeshellarg(realpath($this->config['path'])) . ' up';
    }

    /**
     * getConfig
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->stopServer();
    }

    /**
     * stopServer
     *
     * @return void
     */
    private function stopServer()
    {
        if ($this->process && $this->process->isRunning()) {
            $this->write('Stopping Docker Server');

            $this->process->signal(2);
            $this->process->wait();
        }
    }

    /**
     * moduleInit
     *
     * @param \Codeception\Event\SuiteEvent $e
     * @return void
     */
    public function moduleInit(\Codeception\Event\SuiteEvent $e)
    {
        if (!$this->suiteAllowed($e)) {
            return;
        }

        $this->overrideWithModuleConfig($e);
        $this->generateYaml();
        $this->startServer();
    }

    /**
     * suiteAllowed
     *
     * @param \Codeception\Event\SuiteEvent $e
     * @return bool
     */
    protected function suiteAllowed(\Codeception\Event\SuiteEvent $e): bool
    {
        $allowed = true;
        if (isset($this->config['suites'])) {
            $suites = (array)$this->config['suites'];

            $e->getSuite()->getBaseName();

            if (!in_array($e->getSuite()->getBaseName(), $suites)
                && !in_array($e->getSuite()->getName(), $suites)
            ) {
                $allowed = false;
            }
        }

        return $allowed;
    }

    /**
     * overrideWithModuleConfig
     *
     * @param \Codeception\Event\SuiteEvent $e
     * @return void
     */
    protected function overrideWithModuleConfig(\Codeception\Event\SuiteEvent $e)
    {
        $modules = array_filter($e->getSettings()['modules']['enabled']);
        foreach ($modules as $module) {
            if (is_array($module)) {
                $moduleSettings = current($module);
                $this->config['port'] = $moduleSettings['port'] ?? $this->config['port'];
                $this->config['http_proxy'] = $moduleSettings['capabilities']['httpProxy'] ?? $this->config['http_proxy'];
                $this->config['https_proxy'] = $moduleSettings['capabilities']['sslProxy'] ?? $this->config['https_proxy'];
            }
        }
    }

    /**
     * generateYaml
     *
     * @return void
     */
    protected function generateYaml()
    {
        $environment = [];

        if (!empty($this->config['http_proxy'])) {
            $environment[] = 'http_proxy=' . $this->config['http_proxy'];
        }
        if (!empty($this->config['https_proxy'])) {
            $environment[] = 'https_proxy=' . $this->config['https_proxy'];
        }

        $dockerYaml = [
            'hub'    => [
                'image'       => 'selenium/hub',
                'ports'       => [$this->config['port'] . ':4444'],
                'environment' => $environment
            ],
            'chrome' => [
                'volumes'     => ['/dev/shm:/dev/shm'],
                'image'       => 'selenium/node-chrome',
                'links'       => ['hub'],
                'environment' => $environment
            ]
        ];

        file_put_contents(__DIR__ . '/docker-compose.yml', Yaml::dump($dockerYaml));
    }

    /**
     * startServer
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     */
    private function startServer()
    {
        if ($this->config['debug']) {
            $this->writeln(['Generated Docker Command:', $this->process->getCommandLine()]);
        }
        $this->writeln('Starting Docker Server');
        $this->process->start(function($type, $buffer) {
            if (strpos($buffer, 'Registered a node') !== false) {
                $this->dockerStarted = true;
            }
        });

        // wait until docker is finished to start
        while ($this->process->isRunning() && !$this->dockerStarted) {
        }

        if (!$this->process->isRunning()) {
            throw new ExtensionException($this, 'Failed to start Docker server.');
        }
        $this->writeln(['', 'Docker server now accessible']);
    }
}
