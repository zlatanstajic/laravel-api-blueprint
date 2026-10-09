<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('docs:build {--path=build/docs : The directory to build the documentation into}', function (Generator $generator): void {
    $config = Scramble::getGeneratorConfig('default');
    $specification = $generator($config);

    $path = base_path((string) $this->option('path'));

    File::ensureDirectoryExists($path);
    File::put($path.'/api.json', json_encode($specification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    File::copy(base_path('assets/img/docs-favicon.svg'), $path.'/favicon.svg');

    $html = view('scramble::docs', [
        'spec' => $specification,
        'config' => $config,
    ])->render();

    File::put($path.'/index.html', str_replace('</head>', '<link rel="icon" type="image/svg+xml" href="./favicon.svg">'.PHP_EOL.'</head>', $html));

    $this->info("Static API documentation built in {$path}.");
})->purpose('Build the static, deployable API documentation');
