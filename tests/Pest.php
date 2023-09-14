<?php

use Illuminate\Database\Eloquent\Model;
use InterNACHI\Modular\Support\ModuleRegistry;
use Symfony\Component\Finder\Finder;
use Thunk\Verbs\Tests\TestCase;

Model::unguard();

$examples = collect(Finder::create()->directories()->in(__DIR__.'/../examples/')->depth(1)->name('tests'))
	->map(fn(\Symfony\Component\Finder\SplFileInfo $file) => $file->getRealPath())
	->values()
	->all();

uses(TestCase::class)
	->beforeEach(function() {
		$registry = app(ModuleRegistry::class);
		$reflection = new \ReflectionClass($registry);
		$property = $reflection->getProperty('modules_path');
		$property->setAccessible(true);
		$property->setValue($registry, realpath(__DIR__.'/../examples'));
	})
	->in(__DIR__, ...$examples);
