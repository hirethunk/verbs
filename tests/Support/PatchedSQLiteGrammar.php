<?php

namespace Thunk\Verbs\Tests\Support;

use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Stolen from: @joecampo
// Original Laracasts Answer: https://laracasts.com/discuss/channels/laravel/wherejsoncontains-equivalent-for-sqlite-database?page=1&replyId=894087
// Modified slightly to not use strict mode
// Modified to use collection diff

class PatchedSQLiteGrammar extends SQLiteGrammar
{
    public function __construct()
    {
        DB::connection()
            ->getPdo()
            ->sqliteCreateFunction(
                'JSON_CONTAINS',
                function ($json, $val, $path = null) {
                    $decoded_needle = collect(json_decode(trim($val, '"'), true, 512, JSON_THROW_ON_ERROR));
                    $decoded_haystack = collect(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

                    if ($path) {
                        return $this->collectionContainsCollection(
                            $decoded_needle,
                            $decoded_haystack->pluck($path)
                        );
                    }

                    return $this->collectionContainsCollection(
                        $decoded_needle,
                        $decoded_haystack
                    );
                }
            );
    }

    protected function compileJsonContains($column, $value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_contains('.$field.', '.$value.$path.')';
    }

    protected function collectionContainsCollection(Collection $needle, Collection $haystack): bool
    {
        return $needle->intersect($haystack)->count() == $needle->count();
    }
}
