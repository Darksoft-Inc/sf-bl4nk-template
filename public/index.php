<?php

use App\Kernel;
use LogicException;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    $environment = $context['APP_ENV'] ?? null;

    if (!is_string($environment)) {
        throw new LogicException('The APP_ENV runtime context value must be a string.');
    }

    return new Kernel($environment, (bool) $context['APP_DEBUG']);
};
