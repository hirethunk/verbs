<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class Bank
{
    public DeedCollection $deeds;

    public function __construct()
    {
        // TODO: Bank still needs to understand mortgages

        $this->deeds = $this->setUpDeeds();
    }

    public function hasDeed(Property $property): bool
    {
        return $this->deeds->contains($property);
    }

    public function purchaseDeed(Property $property): void
    {
        if (! $this->hasDeed($property)) {
            throw new InvalidArgumentException("The bank does not have the deed for {$property->name()}!");
        }

        $this->deeds = $this->deeds->reject(fn (Property $deeded) => $deeded === $property);
    }

    protected function setUpDeeds()
    {
        return DeedCollection::make([
            Spaces\Properties\MediterraneanAvenue::instance(),
            Spaces\Properties\BalticAvenue::instance(),
            Spaces\Properties\OrientalAvenue::instance(),
            Spaces\Properties\VermontAvenue::instance(),
            Spaces\Properties\ConnecticutAvenue::instance(),
            Spaces\Properties\StCharlesPlace::instance(),
            Spaces\Properties\StatesAvenue::instance(),
            Spaces\Properties\VirginiaAvenue::instance(),
            Spaces\Properties\StJamesPlace::instance(),
            Spaces\Properties\TennesseeAvenue::instance(),
            Spaces\Properties\NewYorkAvenue::instance(),
            Spaces\Properties\KentuckyAvenue::instance(),
            Spaces\Properties\IndianaAvenue::instance(),
            Spaces\Properties\IllinoisAvenue::instance(),
            Spaces\Properties\AtlanticAvenue::instance(),
            Spaces\Properties\VentnorAvenue::instance(),
            Spaces\Properties\MarvinGardens::instance(),
            Spaces\Properties\PacificAvenue::instance(),
            Spaces\Properties\NorthCarolinaAvenue::instance(),
            Spaces\Properties\PennsylvaniaAvenue::instance(),
            Spaces\Properties\ParkPlace::instance(),
            Spaces\Properties\Boardwalk::instance(),
        ]);
    }
}
