<?php

namespace Thunk\Verbs\Examples\Wingspan\Game;

use SplObjectStorage;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\BirdCollection;

class Board
{
    protected SplObjectStorage $habitats;

    public function __construct()
    {
        $this->habitats = new SplObjectStorage();

        $this->habitats[Habitat::Trees] = new BirdCollection();
        $this->habitats[Habitat::Grass] = new BirdCollection();
        $this->habitats[Habitat::Water] = new BirdCollection();
    }

    public function inAnyHabitat(): BirdCollection
    {
        return $this->inTrees()->merge($this->inGrass())->merge($this->inWater());
    }

    public function inTrees(): BirdCollection
    {
        return $this->habitat(Habitat::Trees);
    }

    public function inGrass(): BirdCollection
    {
        return $this->habitat(Habitat::Grass);
    }

    public function inWater(): BirdCollection
    {
        return $this->habitat(Habitat::Water);
    }

    public function habitat(Habitat $habitat)
    {
        return $this->habitats[$habitat];
    }
}
