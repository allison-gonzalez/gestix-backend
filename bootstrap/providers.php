<?php

use App\Providers\AppServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider;

return [
    MongoDBServiceProvider::class,
    AppServiceProvider::class,
];
