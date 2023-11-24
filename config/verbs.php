<?php

return [
    'replay' => [
        /**
         * When your models don't reside in the default laravel structure you can set a
         * different base directories to check.
         */
        'base_directories' => [base_path()],

        /**
         * When your models don't reside in the default App namespace you can set different
         * namespaces to check.
         */
        'root_namespaces' => ['App\\'],

        /*
        * Within these paths, the package will search for models that are replayable.
        */
        'paths' => [
            app_path(),
        ],

        /*
        * Only models that extend from one of the base models defined here will
        * be included replay discovery.
        */
        'base_models' => [
            Illuminate\Database\Eloquent\Model::class,
        ],
    ],
];
