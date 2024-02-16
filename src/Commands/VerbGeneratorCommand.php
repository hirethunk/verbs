<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\GeneratorCommand;
use Thunk\Verbs\Support\LilWayneLyrics;

abstract class VerbGeneratorCommand extends GeneratorCommand
{
    protected function resolveStubPath($stub): string
    {
        return file_exists($customPath = $this->laravel->basePath('/stubs/verbs/'.$stub))
            ? $customPath
            : dirname(__DIR__, 2).'/stubs/'.$stub;
    }

    protected function buildClass($name): string
    {
        return $this->replaceLyrics(parent::buildClass($name));
    }

    protected function replaceLyrics($stub): string
    {
        return str_replace(['DummyLyric', '{{ lyric }}', '{{lyric}}'], LilWayneLyrics::random(), $stub);
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => [
                'What should the '.strtolower($this->type).' be named?',
                match ($this->type) {
                    'Event' => 'e.g. ApplicationSubmitted',
                    'State' => 'e.g. ApplicationState',
                    default => '',
                },
            ],
        ];
    }
}
