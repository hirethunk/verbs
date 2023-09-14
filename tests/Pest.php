<?php

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;
use Thunk\Verbs\Tests\TestCase;

Model::unguard();

$examples = collect(Finder::create()->directories()->in(__DIR__.'/../examples/')->depth(1)->name('tests'))
    ->map(fn (\Symfony\Component\Finder\SplFileInfo $file) => $file->getRealPath())
    ->values()
    ->all();

uses(TestCase::class)->in(__DIR__, ...$examples);
