<?php

use Thunk\Verbs\Tests\TestCase;
use Symfony\Component\Finder\Finder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Tests\Support\PatchedSQLiteGrammar;

Model::unguard();

$examples = collect(Finder::create()->directories()->in(__DIR__.'/../examples/')->depth(1)->name('tests'))
    ->map(fn (SplFileInfo $file) => $file->getRealPath())
    ->values()
    ->all();

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => 
        DB::connection(DB::getDefaultConnection())
            ->setQueryGrammar(
                new PatchedSQLiteGrammar()
            )
    )
    ->in(__DIR__, ...$examples);
