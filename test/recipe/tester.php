<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Process\Process;

abstract class DepCase extends BaseTestCase
{
    /**
     * @var string
     */
    public static $pwd = '';

    public static function setUpBeforeClass(): void
    {
        // Prepare tmp.
        self::cleanUp();
        mkdir(DEPLOYER_TMP, 0777, true);

        // Init repository.
        $repository = DEPLOYER_FIXTURES . '/repository';
        \exec("cd $repository && git init");
        \exec("cd $repository && git add .");
        \exec("cd $repository && git config user.name 'John Smith'");
        \exec("cd $repository && git config user.email 'john.smith@example.com'");
        \exec("cd $repository && git commit -m 'init commit'");
    }

    public static function tearDownAfterClass(): void
    {
        self::cleanUp();
    }

    protected static function cleanUp()
    {
        if (is_dir(DEPLOYER_TMP)) {
            \exec('rm -rf ' . DEPLOYER_TMP);
        }
    }

    abstract protected function recipe();

    protected function start(string $command): string
    {
        clearstatcache(DEPLOYER_TMP);
        $process = new Process([
            DEPLOYER_BIN,
            '--file=' . $this->recipe(),
            $command
        ]);
        $process->mustRun();
        return $process->getOutput();
    }

    protected function exec(string $command): string
    {
        if (!empty(self::$pwd)) {
            $command = 'cd ' . self::$pwd . ' && ' . $command;
        }
        $process = new Process([$command]);
        $process->mustRun();
        return $process->getOutput();
    }
}
