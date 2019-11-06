<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer;

use Symfony\Component\Process\Exception\ProcessFailedException;

class DeployTest extends DepCase
{
    protected function setUp(): void
    {
        self::$pwd = DEPLOYER_TMP . '/localhost';
    }

    protected function recipe()
    {
        return DEPLOYER_FIXTURES . '/recipe/deploy.php';
    }

    public function testDeploy()
    {
        $output = $this->start('deploy');
        self::assertContains('Successfully deployed!', $output);
        self::assertDirectoryExists(self::$pwd . '/.dep');
        self::assertDirectoryExists(self::$pwd . '/releases');
        self::assertDirectoryExists(self::$pwd . '/shared');
        self::assertDirectoryExists(self::$pwd . '/current');
        self::assertFileExists(self::$pwd . '/current/composer.json');
        self::assertFileExists(self::$pwd . '/shared/public/media/.gitkeep');
        self::assertFileExists(self::$pwd . '/shared/app/config/parameters.yml');
        self::assertEquals(1, $this->exec("ls -1 releases | wc -l"));
    }

    public function testKeepReleases()
    {
        $this->start('deploy');
        $this->start('deploy');
        $this->start('deploy');
        $this->start('deploy');

        $this->start('deploy');
        $this->exec('touch current/ok.txt');

        $this->start('deploy');
        $this->exec('touch current/fail.txt');
        self::assertEquals(5, $this->exec("ls -1 releases | wc -l"));

        // Make sure what after cleanup task same amount of releases a kept.
        $this->start('cleanup');
        self::assertEquals(5, $this->exec("ls -1 releases | wc -l"));
    }

    /**
     * @depends testKeepReleases
     */
    public function testRollback()
    {
        $this->start('rollback');

        self::assertEquals(4, $this->exec("ls -1 releases | wc -l"));
        self::assertFileExists(self::$pwd . '/current/ok.txt');
        self::assertFileNotExists(self::$pwd . '/current/fail.txt');
    }

    /**
     * @depends testRollback
     */
    public function testFail()
    {
        self::expectException(ProcessFailedException::class);
        $this->start('deploy_fail');
    }

    /**
     * @depends testFail
     */
    public function testAfterFail()
    {
        self::assertFileExists(self::$pwd . '/current/ok.txt');
        self::assertFileNotExists(self::$pwd . '/.dep/deploy.lock');

        $this->start('cleanup');
        self::assertEquals(5, $this->exec("ls -1 releases | wc -l"));
        self::assertFileNotExists(self::$pwd . '/release');
    }
}
