<?php

use Illuminate\Support\Facades\Route;
use InterNACHI\Modular\Support\FinderCollection;
use Symfony\Component\Finder\SplFileInfo;

// Dynamically load all examples into their own prefixes
FinderCollection::forDirectories()
    ->depth(0)
    ->inOrEmpty(__DIR__.'/../../examples')
    ->each(function (SplFileInfo $file) {
        if (file_exists($routes = "{$file->getRealPath()}/routes/web.php")) {
            Route::prefix(str($file->getBasename())->kebab()->toString())
                ->group(fn () => require $routes);
        }
    });
