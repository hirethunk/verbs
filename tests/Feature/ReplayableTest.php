<?php

use Carbon\CarbonImmutable;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Subscriptions\Models\Subscription;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Support\ModelFinder;
use Thunk\Verbs\Tests\TestModels\Concert;
use Thunk\Verbs\Tests\TestModels\Ticket;

use function Pest\Laravel\artisan;

it('finds all models that are replayable', function () {
    $models = ModelFinder::create()
        ->withBasePaths([
            realpath(__DIR__.'/../../examples/Bank/src'),
            realpath(__DIR__.'/../../examples/Subscriptions/src'),
        ])
        ->withRootNamespaces([
            'Thunk\Verbs\Examples\Bank\\',
            'Thunk\Verbs\Examples\Subscriptions\\',
        ])
        ->withPaths([__DIR__.'/../../examples'])
        ->withBaseModels([Model::class])
        ->replayable();

    expect($models)->toHaveCount(2)
        ->toMatchArray([
            Account::class,
            Subscription::class,
        ]);
});

it('truncates replayable models before replaying events', function () {
    setUpTables();
    config()->set('verbs.replay.base_directories', [realpath(__DIR__.'/../')]);
    config()->set('verbs.replay.root_namespaces', ['Thunk\Verbs\Tests\\']);
    config()->set('verbs.replay.paths', [realpath(__DIR__.'/../TestModels')]);

    $user = Concert::create(['name' => 'VerbsCon']);

    verb(new TicketPurchased(
        account_id: Snowflake::make()->id(),
        concert_id: $user->getKey(),
    ));

    verb(new TicketPurchased(
        account_id: Snowflake::make()->id(),
        concert_id: $user->getKey(),
    ));

    Verbs::commit();

    expect(Concert::count())->toBe(1)
        ->and(Ticket::count())->toBe(2);

    artisan('verbs:replay')
        ->expectsQuestion('Which models would you like to truncate?', [Ticket::class])
        ->expectsQuestion('Are you sure you want to truncate the following model before replaying events: Ticket', true)
        ->assertSuccessful();

    expect(Concert::count())->toBe(1)
        ->and(Ticket::count())->toBe(2);
});

function setUpTables()
{
    Schema::dropIfExists('concerts');
    Schema::create('concerts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::dropIfExists('tickets');
    Schema::create('tickets', function (Blueprint $table) {
        $table->snowflakeId();
        $table->foreignIdFor(Concert::class);
        $table->timestamps();
    });
}

class TicketPurchased extends Event
{
    public function __construct(
        public string $account_id,
        public int $concert_id,
        public CarbonImmutable $created_at = new CarbonImmutable()
    ) {
    }

    public function handle(): void
    {
        Ticket::create([
            'id' => $this->account_id,
            'concert_id' => $this->concert_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->created_at,
        ]);
    }
}
