<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

use Illuminate\Support\Traits\ForwardsCalls;

/**
 * @mixin SpaceDetails
 * @mixin PropertyDetails
 */
enum Space: string
{
    use ForwardsCalls;
    use HasDetails;

    case Go = Details\Go::class;
    case MediterraneanAvenue = Details\MediterraneanAvenue::class;
    case CommunityChest = Details\CommunityChest::class;
    case BalticAvenue = Details\BalticAvenue::class;
    case IncomeTax = Details\IncomeTax::class;
    case ReadingRailroad = Details\ReadingRailroad::class;
    case OrientalAvenue = Details\OrientalAvenue::class;
    case Chance = Details\Chance::class;
    case VermontAvenue = Details\VermontAvenue::class;
    case ConnecticutAvenue = Details\ConnecticutAvenue::class;
    case Jail = Details\Jail::class;
    case StCharlesPlace = Details\StCharlesPlace::class;
    case ElectricCompany = Details\ElectricCompany::class;
    case StatesAvenue = Details\StatesAvenue::class;
    case VirginiaAvenue = Details\VirginiaAvenue::class;
    case PennsylvaniaRailroad = Details\PennsylvaniaRailroad::class;
    case StJamesPlace = Details\StJamesPlace::class;
    case TennesseeAvenue = Details\TennesseeAvenue::class;
    case NewYorkAvenue = Details\NewYorkAvenue::class;
    case FreeParking = Details\FreeParking::class;
    case KentuckyAvenue = Details\KentuckyAvenue::class;
    case IndianaAvenue = Details\IndianaAvenue::class;
    case IllinoisAvenue = Details\IllinoisAvenue::class;
    case BORailroad = Details\BORailroad::class;
    case AtlanticAvenue = Details\AtlanticAvenue::class;
    case VentnorAvenue = Details\VentnorAvenue::class;
    case WaterWorks = Details\WaterWorks::class;
    case MarvinGardens = Details\MarvinGardens::class;
    case GoToJail = Details\GoToJail::class;
    case PacificAvenue = Details\PacificAvenue::class;
    case NorthCarolinaAvenue = Details\NorthCarolinaAvenue::class;
    case PennsylvaniaAvenue = Details\PennsylvaniaAvenue::class;
    case ShortLine = Details\ShortLine::class;
    case ParkPlace = Details\ParkPlace::class;
    case LuxuryTax = Details\LuxuryTax::class;
    case Boardwalk = Details\Boardwalk::class;
}
