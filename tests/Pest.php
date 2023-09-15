<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Thunk\Verbs\Tests\TestCase;

Model::unguard();

$examples = collect(Finder::create()->directories()->in(__DIR__.'/../examples/')->depth(1)->name('tests'))
    ->map(fn (SplFileInfo $file) => $file->getRealPath())
    ->values()
    ->all();

uses(TestCase::class, RefreshDatabase::class)
    ->in(__DIR__, ...$examples);
