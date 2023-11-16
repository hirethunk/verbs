<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;

class Board
{
    public SpaceCollection $spaces;

    protected int $max_position;

    public function __construct()
    {
        $this->spaces = $this->setUpAllSpaces()->keyBy(fn (Space $space) => $space->position());
        $this->max_position = $this->spaces->max(fn (Space $space) => $space->position());
    }

    public function findNextSpace(Space $current, int $move): Space
    {
        $next_position = $current->position() + $move;

        if ($next_position > $this->max_position) {
            $next_position -= $this->max_position;
        }

        return $this->spaces->get($next_position);
    }

    protected function setUpAllSpaces(): Collection
    {
        return SpaceCollection::make([
            Spaces\Go::instance(),
            Spaces\Properties\MediterraneanAvenue::instance(),
            Spaces\Chests\CommunityChest1::instance(),
            Spaces\Properties\BalticAvenue::instance(),
            Spaces\Taxes\IncomeTax::instance(),
            Spaces\Railroads\ReadingRailroad::instance(),
            Spaces\Properties\OrientalAvenue::instance(),
            Spaces\Chances\Chance1::instance(),
            Spaces\Properties\VermontAvenue::instance(),
            Spaces\Properties\ConnecticutAvenue::instance(),
            Spaces\Jail::instance(),
            Spaces\Properties\StCharlesPlace::instance(),
            Spaces\Utilities\ElectricCompany::instance(),
            Spaces\Properties\StatesAvenue::instance(),
            Spaces\Properties\VirginiaAvenue::instance(),
            Spaces\Railroads\PennsylvaniaRailroad::instance(),
            Spaces\Properties\StJamesPlace::instance(),
            Spaces\Chests\CommunityChest2::instance(),
            Spaces\Properties\TennesseeAvenue::instance(),
            Spaces\Properties\NewYorkAvenue::instance(),
            Spaces\FreeParking::instance(),
            Spaces\Properties\KentuckyAvenue::instance(),
            Spaces\Chances\Chance2::instance(),
            Spaces\Properties\IndianaAvenue::instance(),
            Spaces\Properties\IllinoisAvenue::instance(),
            Spaces\Railroads\BORailroad::instance(),
            Spaces\Properties\AtlanticAvenue::instance(),
            Spaces\Properties\VentnorAvenue::instance(),
            Spaces\Utilities\WaterWorks::instance(),
            Spaces\Properties\MarvinGardens::instance(),
            Spaces\GoToJail::instance(),
            Spaces\Properties\PacificAvenue::instance(),
            Spaces\Properties\NorthCarolinaAvenue::instance(),
            Spaces\Chests\CommunityChest3::instance(),
            Spaces\Properties\PennsylvaniaAvenue::instance(),
            Spaces\Railroads\ShortLine::instance(),
            Spaces\Chances\Chance3::instance(),
            Spaces\Properties\ParkPlace::instance(),
            Spaces\Taxes\LuxuryTax::instance(),
            Spaces\Properties\Boardwalk::instance(),
        ]);
    }
}
