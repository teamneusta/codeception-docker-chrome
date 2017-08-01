![team neusta][logo]

# Docker Selenium Chrome for Codeception Extension #

[![Build Status](https://scrutinizer-ci.com/g/teamneusta/codeception-docker-chrome/badges/build.png?b=master)](https://scrutinizer-ci.com/g/teamneusta/codeception-docker-chrome/build-status/master)
[![Code Coverage](https://scrutinizer-ci.com/g/teamneusta/codeception-docker-chrome/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/teamneusta/codeception-docker-chrome/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/teamneusta/codeception-docker-chrome/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/teamneusta/codeception-docker-chrome/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/ab3e62a0-03dd-4f4b-8b82-39dd3d942f97/mini.png)](https://insight.sensiolabs.com/projects/ab3e62a0-03dd-4f4b-8b82-39dd3d942f97)
[![Latest Stable Version](https://img.shields.io/packagist/v/teamneusta/codeception-docker-chrome.svg?label=stable)](https://packagist.org/packages/teamneusta/codeception-docker-chrome)
[![Latest Stable Version](https://img.shields.io/packagist/l/teamneusta/codeception-docker-chrome.svg?label=stable)](https://packagist.org/packages/teamneusta/codeception-docker-chrome)


### What's Docker Selenium Chrome for Codeception? ###

**Docker Selenium Chrome for Codeception** is a extension to integrate automatic selenium with chrome in your codeception tests.

### Minimum Requirements ###

- Unix System
- [Codeception](http://codeception.com/) 2.2.0
- PHP 7.0 >
- [docker](https://docs.docker.com/engine/installation/linux/) 1.12.0
- [docker-compose](https://docs.docker.com/compose/install/) 1.11.0

### Installing ###

Simply add the following dependency to your project’s composer.json file:

```json
    "require": {
        "teamneusta/codeception-docker-chrome": "^1.0"
    }
```
Finally you can use **Docker Selenium Chrome for Codeception** in your codeception.yml

```yaml
extensions:
    enabled:
        - Codeception\Extension\DockerChrome
    config:
        Codeception\Extension\DockerChrome:
            suites: ['acceptance']
            debug: true
            extra_hosts: ['foo.loc:192.168.0.123']
```

#### Available options ####

##### Basic #####

- `path: {path}`
    - Full path to the docker-compose binary.
    - Default: `/usr/local/bin/docker-compose`
- `port: {port}`
    - Webdriver port to start chrome with.
    - Default: `4444`
- `debug: {true|false}`
    - Display debug output
    - Default: `false`
- `extra_hosts: ['domain:ip', 'domain:ip']`
    - set extra hosts for docker container to connect to local environment over network (not 127.0.0.1)
    - Default: `null`
- `suites: {array|string}`
    - If omitted, Chrome is started for all suites.
    - Specify an array of suites or a single suite name.
        - If you're using an environment (`--env`), Codeception appends the
          environment name to the suite name in brackets. You need to include
          each suite/environment combination separately in the array.
            - `suites: ['acceptance', 'acceptance (staging)', 'acceptance (prod)']`

##### Proxy Support #####

- `http_proxy: {address:port}`
    - Sets the http proxy server.
- `https_proxy: {address:port}`
    - Sets the https proxy server.
- `no_proxy: address1.local,adress2.de`
    - Sets the no proxy for specific domains.
        
##### Registry Support #####

- `private-registry: {address:port}`
    
#### Suite configuration example ####

**this configuration override the codeception.yml configuration**

```yaml
class_name: AcceptanceTester
modules:
    enabled:
        - WebDriver:
            port: 5555
            browser: chrome
            url: https://www.example.de/
            capabilities:
                proxyType: 'manual'
                httpProxy: 'http-proxy.example.de:3128'
                sslProxy: 'https-proxy.example.de:3128'
                noProxy: 'address1.local,adress2.de'
```
 
### Usage ###

Once installed and enabled, running your tests with `php codecept run` will
automatically start the chrome and wait for it to be accessible before
proceeding with the tests.

**be patient on first start. It could take a while**

```bash
Docker server now accessible
```

Once the tests are complete, Docker Server will be shut down.

```bash
Stopping Docker Server
```


[logo]: https://www.team-neusta.de/typo3temp/pics/t_0d7f868b56.png "team neusta logo"
