<?php

namespace Thunk\Verbs;

use Closure;
use Faker\Generator;
use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Events\VerbsStateInitialized;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Support\StateCollection;

/**
 * @template TStateType of State
 */
class StateFactory
{
    use Conditionable;
    use Macroable;

    /**
     * @param  class-string<TStateType>  $state_class
     * @return static<TStateType>
     */
    public static function new(string $state_class, array $data = []): static
    {
        $factory = new static($state_class);

        return $data ? $factory->state($data) : $factory;
    }

    protected string $initial_event = VerbsStateInitialized::class;

    /** @param  class-string<TStateType>  $state_class */
    public function __construct(
        protected string $state_class,
        protected Collection $transformations = new Collection,
        protected ?int $count = null,
        protected int|string|null $id = null,
        protected bool $singleton = false,
        protected ?Generator $faker = null,
        protected Collection $makeCallbacks = new Collection,
        protected Collection $createCallbacks = new Collection,
    ) {}

    public function definition(): array
    {
        return [];
    }

    public function configure(): void
    {
        //
    }

    public function afterMaking(Closure $callback): static
    {
        $this->makeCallbacks->push($callback);

        return $this;
    }

    public function afterCreating(Closure $callback): static
    {
        $this->createCallbacks->push($callback);

        return $this;
    }

    /** @return static<TStateType> */
    public function state(callable|array $data): static
    {
        return $this->clone([
            'transformations' => $this->transformations->concat([
                is_callable($data) ? $data : fn () => $data,
            ]),
        ]);
    }

    /** @return static<TStateType> */
    public function count(int $count): static
    {
        return $this->clone(['count' => $count]);
    }

    public function id(Bits|UuidInterface|AbstractUid|int|string $id): static
    {
        return $this->clone(['id' => Id::from($id)]);
    }

    public function singleton(bool $singleton = true): static
    {
        return $this->clone(['singleton' => $singleton]);
    }

    /** @return TStateType|StateCollection<TStateType> */
    public function create(array $data = [], Bits|UuidInterface|AbstractUid|int|string|null $id = null): State|StateCollection
    {
        if (! empty($data)) {
            return $this->state($data)->create(id: $id);
        }

        if ($id !== null) {
            return $this->id($id)->create($data);
        }

        if ($this->count === null) {
            return $this->createState();
        }

        if ($this->count < 1) {
            return new StateCollection;
        }

        if ($this->count === 1) {
            return StateCollection::make([$this->createState()]);
        }

        if ($this->singleton) {
            throw new RuntimeException('You cannot create multiple singleton states of the same type.');
        }

        if ($this->id) {
            throw new RuntimeException('You cannot create multiple states with the same ID.');
        }

        return StateCollection::range(1, $this->count)->map(fn () => $this->id(Id::make())->createState());
    }

    /** @return TStateType */
    protected function createState(): State
    {
        $this->makeCallbacks->each(
            fn (Closure $callback) => $callback->bindTo($this)()
        );

        $this->configure();

        $initialized = $this->initial_event === VerbsStateInitialized::class
            ? VerbsStateInitialized::fire(
                state_id: $this->id ?? Id::make(),
                state_class: $this->state_class,
                state_data: $this->getRawData(),
                singleton: $this->singleton,
            )
            : $this->initial_event::fire(
                ...$this->getRawData(),
                id: $this->id ?? Id::make(),
            );

        $state = $initialized->state($this->state_class);

        $this->createCallbacks->each(fn (Closure $callback) => $callback($state));

        return $state;
    }

    protected function getRawData(): array
    {
        return $this->transformations->reduce(function (array $raw, callable $transformation) {
            if ($transformation instanceof Closure) {
                $transformation = $transformation->bindTo($this);
            }

            return array_merge($raw, $transformation($raw));
        }, $this->definition());
    }

    /** @return static<TStateType> */
    protected function clone(array $with = []): static
    {
        $state = new static(
            state_class: $with['state_class'] ?? $this->state_class,
            transformations: $with['transformations'] ?? $this->transformations,
            count: $with['count'] ?? $this->count,
            id: $with['id'] ?? $this->id,
            singleton: $with['singleton'] ?? $this->singleton,
            faker: $with['faker'] ?? $this->faker,
            makeCallbacks: $with['makeCallbacks'] ?? $this->makeCallbacks,
            createCallbacks: $with['createCallbacks'] ?? $this->createCallbacks,
        );

        return $state;
    }

    protected function faker(): Generator
    {
        return $this->faker ??= app(Generator::class);
    }
}
