<?php

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Thunk\Verbs\Tests\Support\PatchedSQLiteGrammar;
use Thunk\Verbs\Tests\TestCase;

Model::unguard();

$examples = collect(Finder::create()->directories()->in(__DIR__.'/../examples/')->depth(1)->name('tests'))
    ->map(fn (SplFileInfo $file) => $file->getRealPath())
    ->values()
    ->all();

expect()->extend('toThrow', function (string|Throwable $expected, ?string $message = null) {
    if ($expected instanceof Throwable) {
        $message = $expected->getMessage();
        $expected = $expected::class;
    }

    if (! $this->value instanceof Closure) {
        throw new InvalidArgumentException('toHaveThrown must be passed a closure');
    }

    try {
        call_user_func($this->value);
    } catch (Throwable $e) {
        $message ??= $e->getMessage();
        if ($e instanceof $expected && $message === $e->getMessage()) {
            return true;
        }
    }

    return false;
});

expect()->extend('toBeMoney', function (Money|string|int|null $amount = null, ?string $currency = null) {
    $this->toBeInstanceOf(Money::class);

    if (isset($amount, $currency)) {
        $amount = Money::of($amount, $currency);
    }

    if ($amount) {
        expect($amount->isEqualTo($this->value))->toBeTrue(sprintf(
            'Expected %s but got %s instead', $amount->formatTo('en_US'), $this->value->formatTo('en_US')
        ));
    }
});

uses(TestCase::class)
    ->beforeEach(function () {
        $db = DB::connection();
        if (
            version_compare(App::version(), '10.38.0', '<')
            && $db->getQueryGrammar() instanceof SQLiteGrammar
        ) {
            $db->setQueryGrammar(new PatchedSQLiteGrammar);
        }
    })
    ->in(__DIR__, ...$examples);
