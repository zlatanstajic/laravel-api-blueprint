<?php

declare(strict_types=1);

return [
    // Schema inspection is a local development feature, never a public endpoint.
    'enabled' => env('APP_ENV', 'production') === 'local',
    'diff' => ['enabled' => false],
    'mcp' => ['enabled' => false],
];
