<?php


use Codeception\Event\SuiteEvent;
use Codeception\Exception\ExtensionException;
use Codeception\Suite;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class DockerChromeTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * dockerChrome
     *
     * @var Codeception\Extension\DockerChrome
     */
    protected $dockerChrome;

    /**
     * processProphecy
     *
     * @var Process | \Prophecy\Prophecy\ObjectProphecy
     */
    protected $processProphecy;

    /**
     * suiteEventProphecy
     *
     * @var SuiteEvent | \Prophecy\Prophecy\ObjectProphecy
     */
    protected $suiteEventProphecy;

    /**
     * dockerComposeFilePath
     *
     * @var string
     */
    protected $dockerComposeFilePath = __DIR__ . '/../../src/docker-compose.yml';

    /**
     * configDefaultsDataProvider
     *
     * @return array
     */
    public function configDefaultsDataProvider()
    {
        return [
            'path should return specific path'                    => [
                'config'         => ['path' => __FILE__],
                'expectedConfig' => ['path' => __FILE__]
            ],
            'debug should set to false by default'                => [
                'config'         => [],
                'expectedConfig' => ['debug' => false]
            ],
            'debug should set to true by config'                  => [
                'config'         => ['debug' => true],
                'expectedConfig' => ['debug' => true]
            ],
            'http_proxy should set to empty string by default'    => [
                'config'         => [],
                'expectedConfig' => ['http_proxy' => false]
            ],
            'http_proxy should set to specific string by config'  => [
                'config'         => ['http_proxy' => 'http-proxy.sample.de:3128'],
                'expectedConfig' => ['http_proxy' => 'http-proxy.sample.de:3128']
            ],
            'https_proxy should set to empty string by default'   => [
                'config'         => [],
                'expectedConfig' => ['https_proxy' => false]
            ],
            'https_proxy should set to specific string by config' => [
                'config'         => ['https_proxy' => 'https-proxy.sample.de:3128'],
                'expectedConfig' => ['https_proxy' => 'https-proxy.sample.de:3128']
            ]
        ];
    }

    /**
     * testInitConfigDefaults
     *
     * @dataProvider configDefaultsDataProvider
     * @param array $config
     * @param array $expectedConfig
     * @return void
     */
    public function testInitConfigDefaults(array $config, array $expectedConfig)
    {
        $this->dockerChrome = new Codeception\Extension\DockerChrome(array_merge(['path' => vfsStream::url('docker/usr/local/bin/docker-composes')], $config), [], $this->processProphecy->reveal());
        $this->assertArraySubset($expectedConfig, $this->dockerChrome->getConfig());
    }

    /**
     * testInitConfigPathDefaultShouldThrowExceptionIfPathNotExist
     *
     * @return void
     */
    public function testInitConfigPathDefaultShouldThrowExceptionIfPathNotExist()
    {
        $this->expectException(ExtensionException::class);
        $this->expectExceptionMessage('File not found: /some/missing/path');
        $this->dockerChrome = new Codeception\Extension\DockerChrome(['path' => '/some/missing/path'], [], $this->processProphecy->reveal());
    }

    /**
     * testInitConfigPathDefaultShouldThrowExceptionIfDefaultPathNotExist
     *
     * @return void
     */
    public function testInitConfigPathDefaultShouldThrowExceptionIfDefaultPathNotExist()
    {
        $this->expectException(ExtensionException::class);
        $this->expectExceptionMessage('File not found: /some/missing/path');
        $this->dockerChrome = new Codeception\Extension\DockerChrome([], [], $this->processProphecy->reveal(), '/some/missing/path');
    }

    /**
     * testModuleInitShouldNotCreateDockerComposeYamlIfSuiteAreNotAllowed
     *
     * @return void
     */
    public function testModuleInitShouldNotCreateDockerComposeYamlIfSuiteAreNotAllowed()
    {
        $this->dockerChrome = new Codeception\Extension\DockerChrome(['suites' => 'acceptance', 'path' => vfsStream::url('docker/usr/local/bin/docker-composes')], [], $this->processProphecy->reveal());
        $this->suiteEventProphecy->getSettings()->willReturn([
            'modules' => [
                'enabled' => []
            ]
        ]);
        $this->suiteEventProphecy->getSuite()->willReturn(new Suite());
        $this->dockerChrome->moduleInit($this->suiteEventProphecy->reveal());
        $this->assertFileNotExists($this->dockerComposeFilePath);
    }

    /**
     * testModuleInitShouldThrowAnExceptionIfProcessIsNotRunning
     *suites
     * @return void
     */
    public function testModuleInitShouldThrowAnExceptionIfProcessIsNotRunning()
    {
        $this->expectExceptionMessage('Failed to start Docker server.');
        $this->expectException(ExtensionException::class);

        $this->processProphecy->start(Argument::any())->shouldBeCalled();
        $this->processProphecy->getCommandLine(Argument::any())->shouldBeCalled();
        $this->processProphecy->isRunning()->shouldBeCalled()->willReturn(false);
        $this->processProphecy->signal(Argument::type('int'))->shouldNotBeCalled();
        $this->processProphecy->wait()->shouldNotBeCalled();

        $this->suiteEventProphecy->getSettings()->willReturn([
            'modules' => [
                'enabled' => []
            ]
        ]);
        $this->dockerChrome->moduleInit($this->suiteEventProphecy->reveal());
        $this->assertFileExists($this->dockerComposeFilePath);
    }

    /**
     * testModuleInitShouldCreateDockerComposeYamlIfNoSuiteAreSet
     *
     * @return void
     */
    public function testModuleInitShouldCreateDockerComposeYamlAndStartProcessIfNoSuiteAreSet()
    {
        $this->processProphecy->start(Argument::type('callable'))->shouldBeCalled()->will(function ($args) {
            $args[0]('info', 'Registered a node');
        });
        $this->processProphecy->getCommandLine(Argument::any())->shouldBeCalled();
        $this->processProphecy->isRunning()->shouldBeCalled()->willReturn(true);
        $this->processProphecy->signal(Argument::type('int'))->shouldNotBeCalled();
        $this->processProphecy->wait()->shouldNotBeCalled();

        $this->suiteEventProphecy->getSettings()->willReturn([
            'modules' => [
                'enabled' => []
            ]
        ]);
        $this->dockerChrome->moduleInit($this->suiteEventProphecy->reveal());
        $this->assertFileExists($this->dockerComposeFilePath);
    }

    /**
     * testModuleInitShouldCreateDockerComposeYamlAndStartProcessIfNoSuiteAreSetWithProxy
     *
     * @return void
     */
    public function testModuleInitShouldCreateDockerComposeYamlAndStartProcessIfNoSuiteAreSetWithProxy()
    {
        $this->processProphecy->start(Argument::type('callable'))->shouldBeCalled()->will(function ($args) {
            $args[0]('info', 'Registered a node');
        });
        $this->processProphecy->getCommandLine(Argument::any())->shouldBeCalled();
        $this->processProphecy->isRunning()->shouldBeCalled()->willReturn(true);
        $this->processProphecy->signal(Argument::type('int'))->shouldNotBeCalled();
        $this->processProphecy->wait()->shouldNotBeCalled();

        $this->suiteEventProphecy->getSettings()->willReturn([
            'modules' => [
                'enabled' => [
                    [
                        'WebDriver' => [
                            'port'         => 2222,
                            'capabilities' => [
                                'httpProxy' => 'http-proxy:3128',
                                'sslProxy'  => 'https-proxy:3128',
                            ]
                        ]
                    ]
                ]
            ]
        ])->shouldBeCalled();
        $this->dockerChrome->moduleInit($this->suiteEventProphecy->reveal());
        $this->assertFileExists($this->dockerComposeFilePath);
        $this->assertEquals([
            'hub'    => [
                'image'       => 'selenium/hub',
                'ports'       => ['2222:4444'],
                'environment' => [
                    'http_proxy=http-proxy:3128',
                    'https_proxy=https-proxy:3128',
                ],
            ],
            'chrome' => [
                'volumes'     => [
                    '/dev/shm:/dev/shm'
                ],
                'image'       => 'selenium/node-chrome',
                'links'       => ['hub'],
                'environment' => [
                    'http_proxy=http-proxy:3128',
                    'https_proxy=https-proxy:3128',
                ],
            ]
        ], Yaml::parse(file_get_contents($this->dockerComposeFilePath)));
    }

    /**
     * testModuleInitShouldCreateDockerComposeYamlAndStartProcessIfNoSuiteAreSetWithNoProxy
     *
     * @return void
     */
    public function testModuleInitShouldCreateDockerComposeYamlAndStartProcessIfNoSuiteAreSetWithNoProxy()
    {
        $this->processProphecy->start(Argument::type('callable'))->shouldBeCalled()->will(function ($args) {
            $args[0]('info', 'Registered a node');
        });
        $this->processProphecy->getCommandLine(Argument::any())->shouldBeCalled();
        $this->processProphecy->isRunning()->shouldBeCalled()->willReturn(true);
        $this->processProphecy->signal(Argument::type('int'))->shouldNotBeCalled();
        $this->processProphecy->wait()->shouldNotBeCalled();

        $this->suiteEventProphecy->getSettings()->willReturn([
            'modules' => [
                'enabled' => [
                    [
                        'WebDriver' => [
                            'port'         => 2222,
                            'capabilities' => [
                                'httpProxy' => 'http-proxy:3128',
                                'sslProxy'  => 'https-proxy:3128',
                                'noProxy'  => 'domain.loc',
                            ]
                        ]
                    ]
                ]
            ]
        ])->shouldBeCalled();
        $this->dockerChrome->moduleInit($this->suiteEventProphecy->reveal());
        $this->assertFileExists($this->dockerComposeFilePath);
        $this->assertEquals([
            'hub'    => [
                'image'       => 'selenium/hub',
                'ports'       => ['2222:4444'],
                'environment' => [
                    'http_proxy=http-proxy:3128',
                    'https_proxy=https-proxy:3128',
                    'no_proxy=domain.loc',
                ],
            ],
            'chrome' => [
                'volumes'     => [
                    '/dev/shm:/dev/shm'
                ],
                'image'       => 'selenium/node-chrome',
                'links'       => ['hub'],
                'environment' => [
                    'http_proxy=http-proxy:3128',
                    'https_proxy=https-proxy:3128',
                    'no_proxy=domain.loc',
                ],
            ]
        ], Yaml::parse(file_get_contents($this->dockerComposeFilePath)));
    }

    /**
     * testModuleInitShouldCreateDockerComposeYamlAndStartProcessIfNoSuiteAreSetWithExtraHosts
     *
     * @return void
     */
    public function testModuleInitShouldCreateDockerComposeYamlAndStartProcessIfNoSuiteAreSetWithExtraHosts()
    {
        $this->dockerChrome = new Codeception\Extension\DockerChrome(['extra_hosts' => ['someDomain.loc:127.0.0.1'], 'debug' => true, 'path' => vfsStream::url('docker/usr/local/bin/docker-composes')], [], $this->processProphecy->reveal());

        $this->processProphecy->start(Argument::type('callable'))->shouldBeCalled()->will(function ($args) {
            $args[0]('info', 'Registered a node');
        });
        $this->processProphecy->getCommandLine(Argument::any())->shouldBeCalled();
        $this->processProphecy->isRunning()->shouldBeCalled()->willReturn(true);
        $this->processProphecy->signal(Argument::type('int'))->shouldNotBeCalled();
        $this->processProphecy->wait()->shouldNotBeCalled();

        $this->suiteEventProphecy->getSettings()->willReturn([
            'modules' => [
                'enabled' => [
                    [
                        'WebDriver' => [
                            'port'         => 2222
                        ]
                    ]
                ]
            ]
        ])->shouldBeCalled();
        $this->dockerChrome->moduleInit($this->suiteEventProphecy->reveal());
        $this->assertFileExists($this->dockerComposeFilePath);
        $this->assertEquals([
            'hub'    => [
                'image'       => 'selenium/hub',
                'ports'       => ['2222:4444'],
                'environment' => [],
                'extra_hosts' => ['someDomain.loc:127.0.0.1']
            ],
            'chrome' => [
                'volumes'     => [
                    '/dev/shm:/dev/shm'
                ],
                'image'       => 'selenium/node-chrome',
                'links'       => ['hub'],
                'environment' => [],
                'extra_hosts' => ['someDomain.loc:127.0.0.1']
            ]
        ], Yaml::parse(file_get_contents($this->dockerComposeFilePath)));
    }

    /**
     * testDestructShouldStopServer
     *
     * @return void
     */
    public function testDestructShouldStopServer()
    {
        $this->processProphecy->isRunning()->shouldBeCalled()->willReturn(true);
        $this->processProphecy->signal(2)->shouldBeCalled();
        $this->processProphecy->wait()->shouldBeCalled();
        $this->dockerChrome->__destruct();
    }

    protected function initExtension()
    {
        vfsStream::setup('docker', null, [
            'usr' => [
                'local' => [
                    'bin' => [
                        'docker-composes' => '<?php'
                    ]
                ]
            ]
        ]);
        $config = ['debug' => true, 'path' => vfsStream::url('docker/usr/local/bin/docker-composes')];
        $options = [];
        $this->processProphecy = $this->prophesize(Process::class);
        $this->dockerChrome = new Codeception\Extension\DockerChrome($config, $options, $this->processProphecy->reveal());

        $this->suiteEventProphecy = $this->prophesize(SuiteEvent::class);

        //defaults for clean run
        $this->processProphecy->start(Argument::any());
        $this->processProphecy->isRunning();
        $this->processProphecy->signal(Argument::type('int'));
        $this->processProphecy->wait();
    }

    /**
     * _before
     *
     * @return void
     */
    protected function _before()
    {
        $this->initExtension();
    }

    /**
     * _after
     *
     * @return void
     */
    protected function _after()
    {
        if (file_exists($this->dockerComposeFilePath)) {
            unlink($this->dockerComposeFilePath);
        }
    }
}