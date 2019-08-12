<?php declare(strict_types=1);
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Executor;

use Deployer\Collection\Collection;
use Deployer\Configuration\UserConfiguration;
use Deployer\Console\Application;
use Deployer\Console\Output\Informer;
use Deployer\Exception\Exception;
use Deployer\Exception\GracefulShutdownException;
use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\Host\Storage;
use Deployer\Ssh\Client;
use Deployer\Task\Context;
use Deployer\Task\Task;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use function Deployer\hostTag;

const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

function spinner()
{
    return FRAMES[(int)(microtime(true) * 10) % count(FRAMES)];
}

class ParallelExecutor implements ExecutorInterface
{
    private $input;
    private $output;
    private $informer;
    private $console;
    private $client;
    private $config;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        Informer $informer,
        Application $console,
        Client $client,
        Collection $config
    )
    {
        $this->input = $input;
        $this->output = $output;
        $this->informer = $informer;
        $this->console = $console;
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * @param Task[] $tasks
     * @param Host[] $hosts
     * @throws Exception
     * @throws GracefulShutdownException
     */
    public function run(array $tasks, array $hosts)
    {
        $this->setColors($hosts);

        // Connect to each host sequentially, to prevent getting locked.
        foreach ($hosts as $host) {
            if (!($host instanceof Localhost)) {
                $this->output->write(spinner() . "\r");
                $this->client->connect($host);
            }
        }

        $localhost = new Localhost();
        $limit = (int)$this->input->getOption('limit') ?: count($hosts);

        // We need contexts here for usage inside `on` function. Pass input/output to callback of it.
        // This allows to use code like this in parallel mode:
        //
        //     host('prod')
        //         ->set('branch', function () {
        //             return input()->getOption('branch') ?: 'production';
        //     })
        //
        // Otherwise `input()` wont be accessible (i.e. null)
        //
        Context::push(new Context($localhost, $this->input, $this->output));
        {
            Storage::persist(...$hosts);
        }
        Context::pop();

        foreach ($tasks as $task) {
            $success = true;
            $this->informer->startTask($task);

            if ($task->isLocal()) {
                Storage::load(...$hosts);
                {
                    $task->run(new Context($localhost, $this->input, $this->output));
                }
                Storage::flush(...$hosts);
            } else {
                foreach (array_chunk($hosts, $limit) as $chunk) {
                    $exitCode = $this->runTask($chunk, $task);

                    switch ($exitCode) {
                        case 1:
                            throw new GracefulShutdownException();
                        case 2:
                            $success = false;
                            break;
                        case 255:
                            throw new Exception();
                    }
                }
            }

            if ($success) {
                $this->informer->endTask($task);
            } else {
                $this->informer->taskError();
            }
        }
    }

    private function runTask(array $hosts, Task $task): int
    {
        $processes = [];

        foreach ($hosts as $host) {
            if ($task->shouldBePerformed($host)) {
                $processes[$host->getAlias()] = $this->getProcess($host, $task);
                if ($task->isOnce()) {
                    $task->setHasRun();
                }
            }
        }

        $callback = function (string $type, string $host, string $output) {
            $output = preg_replace('/\n$/', '', $output);
            if (strlen($output) !== 0) {
                $this->output->writeln($output);
            }
        };

        $this->startProcesses($processes);

        while ($this->areRunning($processes)) {
            $this->gatherOutput($processes, $callback);
            $this->output->write(spinner() . "\r");
            usleep(1000);
        }
        $this->gatherOutput($processes, $callback);

        return $this->gatherExitCodes($processes);
    }

    protected function getProcess(Host $host, Task $task): Process
    {
        $dep = PHP_BINARY . ' ' . DEPLOYER_BIN;
        $hostAlias = $host->getAlias();
        $taskName = $task->getName();
        $configFile = $host->get('host_config_file');
        $value = $this->input->getOption('file');
        $file = $value ? "--file='$value'" : '';

        $options = '';
        foreach ($this->config as $key => $value) {
            if (is_scalar($value)) {
                $options .= " -o $key=" . json_encode($value);
            }
        }

        $command = "$dep $file worker --host $hostAlias --task $taskName $options --config-file $configFile";
        if ($this->output->isDebug()) {
            $this->output->writeln(hostTag($host->getAlias()) . $command);
        }

        $process = new Process($command);
        if (!defined('DEPLOYER_PARALLEL_PTY')) {
            $process->setPty(true);
        }
        return $process;
    }

    /**
     * Start all of the processes.
     *
     * @param Process[] $processes
     * @return void
     */
    protected function startProcesses(array $processes)
    {
        foreach ($processes as $process) {
            $process->start();
        }
    }

    /**
     * Determine if any of the processes are running.
     *
     * @param Process[] $processes
     * @return bool
     */
    protected function areRunning(array $processes): bool
    {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gather the output from all of the processes.
     *
     * @param Process[] $processes
     * @param callable $callback
     * @return void
     */
    protected function gatherOutput(array $processes, callable $callback)
    {
        foreach ($processes as $host => $process) {
            $output = $process->getIncrementalOutput();
            if (strlen($output) !== 0) {
                $callback(Process::OUT, $host, $output);
            }

            $errorOutput = $process->getIncrementalErrorOutput();
            if (strlen($errorOutput) !== 0) {
                $callback(Process::ERR, $host, $errorOutput);
            }
        }
    }

    /**
     * Gather the cumulative exit code for the processes.
     */
    protected function gatherExitCodes(array $processes): int
    {
        foreach ($processes as $process) {
            if ($process->getExitCode() > 0) {
                return $process->getExitCode();
            }
        }

        return 0;
    }

    private function setColors(array $hosts)
    {
        $colors = $allColors = [
            'fg=cyan;options=bold',
            'fg=green;options=bold',
            'fg=yellow;options=bold',
            'fg=cyan',
            'fg=blue',
            'fg=yellow',
            'fg=magenta',
            'fg=blue;options=bold',
            'fg=green',
            'fg=magenta;options=bold',
            'fg=red;options=bold',
        ];

        // Set colors to all host.
        $hostnameColors = UserConfiguration::load(UserConfiguration::HOSTNAME_COLORS, []);
        foreach ($hosts as $host) {
            $hostname = $host->getAlias();
            if (array_key_exists($hostname, $hostnameColors)) {
                $tag = $hostnameColors[$hostname];
                $colors = array_values(array_filter($colors, function ($i) use ($tag) {
                    return $i !== $tag;
                }));
            }
        }
        $i = 0;
        foreach ($hosts as $host) {
            $hostname = $host->getAlias();
            if (!array_key_exists($hostname, $hostnameColors)) {
                $hostnameColors[$hostname] = count($colors) > 0
                    ? $colors[$i++ % count($colors)]
                    : $allColors[abs(crc32($hostname)) % count($allColors)];
            }
        }
        UserConfiguration::save(UserConfiguration::HOSTNAME_COLORS, $hostnameColors);
    }
}
