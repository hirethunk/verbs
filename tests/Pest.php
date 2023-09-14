<?php

use Thunk\Verbs\Tests\TestCase;
use Symfony\Component\Finder\Finder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

Model::unguard();

$examples = collect(Finder::create()->directories()->in(__DIR__.'/../examples/')->depth(1)->name('tests'))
    ->map(fn (\Symfony\Component\Finder\SplFileInfo $file) => $file->getRealPath())
    ->values()
    ->all();

uses(TestCase::class, RefreshDatabase::class)
    ->in(__DIR__, ...$examples);
