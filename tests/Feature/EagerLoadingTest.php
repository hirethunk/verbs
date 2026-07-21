<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Attributes\Projection\EagerLoad;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\EagerLoader;

it('identifies the correct data when calling the attribute', function () {
    $event = new TestEagerLoadingEvent(1337);

    $attrs = collect((new ReflectionClass($event))->getProperties())
        ->map(function (ReflectionProperty $property) use ($event) {
            $attribute = Arr::first($property->getAttributes(EagerLoad::class));

            return $attribute?->newInstance()->handle($property, $event);
        })
        ->filter()
        ->values();

    expect($attrs->all())->toBe([[TestEagerLoadingModel::class, $event, 'test_model_id', 'test_model']]);
});

it('eager-loads models for events', function () {
    TestEagerLoadingModel::migrate();

    $model1 = TestEagerLoadingModel::create(['id' => 1337, 'name' => 'test 1']);
    $model2 = TestEagerLoadingModel::create(['id' => 9876, 'name' => 'test 2']);

    $event1 = new TestEagerLoadingEvent(1337);
    $event2 = new TestEagerLoadingEvent(9876);

    EagerLoader::load($event1, $event2);

    expect($model1->is($event1->getTestModel()))->toBeTrue()
        ->and($model2->is($event2->getTestModel()))->toBeTrue();
});

class TestEagerLoadingEvent extends Event
{
    public function __construct(
        public int $test_model_id,
    ) {}

    #[EagerLoad]
    protected ?TestEagerLoadingModel $test_model = null;

    public function getTestModel(): ?TestEagerLoadingModel
    {
        return $this->test_model;
    }
}

class TestEagerLoadingModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'test_eager_loading';

    public static function migrate()
    {
        Schema::create('test_eager_loading', function (Blueprint $table) {
            $table->snowflakeId();
            $table->string('name')->nullable();
        });
    }
}
