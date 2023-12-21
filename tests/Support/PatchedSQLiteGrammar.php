<?php

namespace Thunk\Verbs\Tests\Support;

use Illuminate\Database\Query\Grammars\SQLiteGrammar;

// This just brings in the 10.38.0 code:
// https://github.com/laravel/framework/pull/49401

class PatchedSQLiteGrammar extends SQLiteGrammar
{
    protected function compileJsonContains($column, $value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'exists (select 1 from json_each('.$field.$path.') where '.$this->wrap('json_each.value').' is '.$value.')';
    }

    public function prepareBindingForJsonContains($binding)
    {
        return $binding;
    }
}
