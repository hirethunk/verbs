<?php

namespace Thunk\Verbs\Helpers;

use Illuminate\Support\Arr;

class Stub
{
    CONST STUBS_PATH = __DIR__ . '/../../stubs/';

    public array $substitutions = [];
    public string $stub_name;
    public string $destination_path;

    public static function event(string $classname): string
    {
        $stub = new self();
        $stub->stub_name = 'Event';
        $stub->destination_path = app_path('Events/' . $classname . '.php');
        $stub->substitutions = [
            'classname' => $classname,
            'lyric' => LilWayneLyrics::random(),
        ];

        $stub->build();

        return $stub->destination_path;
    }

    public static function state(string $classname): string
    {
        $stub = new self();
        $stub->stub_name = 'State';
        $stub->destination_path = app_path('States/' . $classname . '.php');
        $stub->substitutions = [
            'classname' => $classname,
            'lyric' => LilWayneLyrics::random(),
        ];

        $stub->build();

        return $stub->destination_path;
    }

    public function build(): void {
        $stub = $this->replaceSubstitutions();
        $this->forceWrite( $this->destination_path, $stub);
    }

    protected function replaceSubstitutions(): string
    {
        return collect($this->substitutions)
            ->reduce(
                fn($stub, $value, $key) => str_replace(
                    '<< ' . strtoupper($key) . ' >>',
                    $value,
                    $stub
                ),
                $this->stubContents()
            );
    }

    protected function stubContents(): string
    {
        return file_get_contents(self::STUBS_PATH . $this->stub_name . '.stub');
    }

    protected function forceWrite($dir, $contents){
        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach($parts as $part)
            if(!is_dir($dir .= "/$part")) mkdir($dir);
        file_put_contents("$dir/$file", $contents);
    }
}