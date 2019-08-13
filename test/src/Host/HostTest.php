<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Host;

use PHPUnit\Framework\TestCase;

class HostTest extends TestCase
{
    public function testHost()
    {
        $host = new Host('host');
        $host
            ->hostname('hostname')
            ->user('user')
            ->port(22)
            ->configFile('~/.ssh/config')
            ->identityFile('~/.ssh/id_rsa')
            ->forwardAgent(true)
            ->multiplexing(true)
            ->sshOptions(['BatchMode' => 'yes'])
            ->addSshOption('Compression', 'yes');

        self::assertEquals('host', "$host");
        self::assertEquals('host', $host->getAlias());
        self::assertEquals('hostname', $host->getHostname());
        self::assertEquals('user@hostname', $host->getUserHostname());
        self::assertEquals('user', $host->getUser());
        self::assertEquals(22, $host->getPort());
        self::assertEquals('~/.ssh/config', $host->getConfigFile());
        self::assertEquals('~/.ssh/id_rsa', $host->getIdentityFile());
        self::assertEquals(true, $host->isForwardAgent());
        self::assertEquals(true, $host->isMultiplexing());
        self::assertStringContainsString(
            '-A -p 22 -F ~/.ssh/config -i ~/.ssh/id_rsa -o BatchMode=yes -o Compression=yes',
            $host->getSshArguments()->getCliArguments()
        );
    }

    public function testHostWithCustomPort()
    {
        $host = new Host('host');
        $host
            ->hostname('host')
            ->user('user')
            ->port(2222);

        self::assertEquals('-A -p 2222', $host->getSshArguments()->getCliArguments());
    }

    public function testConfigurationAccessor()
    {
        $host = new Host('host');
        $host
            ->roles('db', 'app')
            ->set('key', 'value')
            ->set('array', [1])
            ->add('array', [2]);

        self::assertEquals(['db', 'app'], $host->get('roles'));
        self::assertEquals('value', $host->get('key'));
        self::assertEquals([1, 2], $host->get('array'));
    }

    public function testHostAlias()
    {
        $host = new Host('host/alias');
        self::assertEquals('host/alias', "$host");
        self::assertEquals('host/alias', $host->getAlias());
        self::assertEquals('host', $host->getHostname());
    }

    public function testHostWithParams()
    {
        $host = new Host('host');
        $value = 'new_value';
        $host
            ->set('env', $value)
            ->identityFile('{{env}}');

        self::assertEquals($value, $host->getIdentityFile());
    }
}
