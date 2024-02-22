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

        $factory->configure();

        return $data ? $factory->state($data) : $factory;
    }

    /** @param  class-string<TStateType>  $state_class */
    public function __construct(
        protected string $state_class,
        protected Collection $transformations = new Collection(),
        protected ?int $count = null,
        protected int|string|null $id = null,
        protected bool $singleton = false,
        protected ?Generator $faker = null,
        protected string $initial_event = VerbsStateInitialized::class,
    ) {
    }

    public function definition(): array
    {
        return [];
    }

    public function configure(): void
    {
        //
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
            return new StateCollection();
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
        $initialized = $this->initial_event::fire(
            state_id: $this->id ?? Id::make(),
            state_class: $this->state_class,
            state_data: $this->getRawData(),
            singleton: $this->singleton,
        );

        return $initialized->state($this->state_class);
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
        return new static(
            state_class: $with['state_class'] ?? $this->state_class,
            transformations: $with['transformations'] ?? $this->transformations,
            count: $with['count'] ?? $this->count,
            id: $with['id'] ?? $this->id,
            singleton: $with['singleton'] ?? $this->singleton,
            faker: $with['faker'] ?? $this->faker,
        );
    }

    protected function faker(): Generator
    {
        return $this->faker ??= app(Generator::class);
    }
}
