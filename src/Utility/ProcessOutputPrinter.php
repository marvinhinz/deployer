<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Utility;

use Deployer\Logger\Logger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use function Deployer\hostTag;

class ProcessOutputPrinter
{
    private $output;
    private $logger;

    public function __construct(OutputInterface $output, Logger $logger)
    {
        $this->output = $output;
        $this->logger = $logger;
    }

    /**
     * Returns a callable for use with the symfony Process->run($callable) method.
     *
     * @param string $hostAlias
     * @return callable A function expecting a int $type (e.g. Process::OUT or Process::ERR) and string $buffer parameters.
     */
    public function callback(string $hostAlias)
    {
        return function ($type, $buffer) use ($hostAlias) {
            $this->printBuffer($type, $hostAlias, $buffer);
        };
    }

    /**
     * @param string $type Process::OUT or Process::ERR
     * @param string $hostname
     * @param string $buffer
     */
    public function printBuffer($type, $hostname, $buffer)
    {
        foreach (explode("\n", rtrim($buffer)) as $line) {
            $this->writeln($type, $hostname, $line);
        }
    }

    public function command(string $hostAlias, string $command)
    {
        $this->logger->log("[$hostAlias] run $command");
        $this->output->writeln(hostTag($hostAlias) . "<fg=cyan>run</> $command");
    }

    /**
     * @param string $type Process::OUT or Process::ERR
     * @param string $hostAlias
     * @param string $line
     */
    public function writeln($type, $hostAlias, $line)
    {
        $line = $this->filterOutput($line);

        // Omit empty lines
        if (empty($line)) {
            return;
        }

        if ($type === Process::ERR) {
            $this->logger->log("[$hostAlias] [error] $line");
        } else {
            $this->logger->log("[$hostAlias] $line");
        }

        $prefix = hostTag($hostAlias);
        if ($type === Process::ERR) {
            $line = "$prefix<fg=red>err</> $line";
        } else {
            $line = "$prefix$line";
        }

        $this->output->writeln($line);
    }

    /**
     * This filtering used only in Ssh\Client, but for simplify putted here.
     *
     * @param string $output
     * @return string
     */
    public function filterOutput($output)
    {
        return preg_replace('/\[exit_code:(.*?)\]/', '', $output);
    }
}
