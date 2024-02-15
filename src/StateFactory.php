<?php

namespace Thunk\Verbs;

use Closure;
use Faker\Generator;
use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Events\VerbsStateInitialized;
use Thunk\Verbs\Support\IdManager;

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
    public static function new(string $state_class, array $data = [])
    {
        $factory = new static($state_class);

        $factory->configure();

        return $factory->state($data);
    }

    /** @param  class-string<TStateType>  $state_class */
    public function __construct(
        protected string $state_class,
        protected Collection $transformations = new Collection(),
        protected ?int $count = null,
        protected ?int $id = null,
        protected ?Generator $faker = null,
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
        return $this->clone(['id' => $id]);
    }

    /** @return TStateType|Collection<TStateType> */
    public function create(array $data = [], ?int $id = null): State|Collection
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
            return new Collection();
        }

        return Collection::range(1, $this->count)->map(fn () => $this->createState());
    }

    /** @return TStateType */
    protected function createState(): State
    {
        $initialized = VerbsStateInitialized::fire(
            state_id: $this->id ?? app(IdManager::class)->make(),
            state_class: $this->state_class,
            state_data: $this->getRawData(),
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
            faker: $with['faker'] ?? $this->faker,
        );
    }

    protected function faker(): Generator
    {
        return $this->faker ??= app(Generator::class);
    }
}
