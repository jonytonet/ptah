<?php

namespace Ptah\Commands\Config\Formatters;

use Symfony\Component\Console\Output\OutputInterface;

class JsonFormatter
{
    protected OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Format configuration as pretty JSON
     */
    public function format(array $config, ?string $model = null): void
    {
        $output = [
            'model' => $model,
            'config' => $config,
        ];

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $this->output->writeln($json);
    }
}
