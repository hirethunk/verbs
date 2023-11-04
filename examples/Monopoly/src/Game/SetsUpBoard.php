<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;

trait SetsUpBoard
{
    protected function setUpAllSpaces(): Collection
    {
        return Collection::make([
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
