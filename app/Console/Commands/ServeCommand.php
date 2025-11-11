<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Carbon\Carbon;

class ServeCommand extends BaseServeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'serve:fixed
                            {--host=127.0.0.1 : The host address to serve the application on}
                            {--port=8000 : The port to serve the application on}
                            {--tries=10 : The max number of ports to attempt to serve from}
                            {--no-reload : Do not reload the development server on .env file changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the application on the PHP development server (Windows fixed)';

    /**
     * Get the date from the given PHP server output.
     *
     * @param  string  $line
     * @return \Carbon\Carbon
     */
    protected function getDateFromLine($line)
    {
        $regex = env('PHP_CLI_SERVER_WORKERS', 1) > 1
            ? '/^\[\d+]\s\[([a-zA-Z0-9: ]+)\]/'
            : '/^\[([^\]]+)\]/';

        $line = str_replace('  ', ' ', $line);

        preg_match($regex, $line, $matches);

        // Fix for Windows: Check if matches[1] exists
        if (!isset($matches[1])) {
            return now();
        }

        return Carbon::createFromFormat('D M d H:i:s Y', $matches[1]);
    }
}

