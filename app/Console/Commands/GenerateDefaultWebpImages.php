<?php

namespace App\Console\Commands;

use App\Support\DefaultImageGenerator;
use Illuminate\Console\Command;

class GenerateDefaultWebpImages extends Command
{
    protected $signature = 'images:generate-default-webp';

    protected $description = 'Generate default placeholder images as WebP files in public/';

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('PHP GD with WebP support is required.');

            return self::FAILURE;
        }

        $written = DefaultImageGenerator::generateAll();

        if ($written === []) {
            $this->error('No WebP files were generated.');

            return self::FAILURE;
        }

        foreach ($written as $path) {
            $this->line(str_replace(public_path(), 'public', $path));
        }

        $this->info('Generated '.count($written).' default WebP image(s).');

        return self::SUCCESS;
    }
}
