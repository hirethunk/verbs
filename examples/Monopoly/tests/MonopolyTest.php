<?php

use Brick\Money\Money;
use Glhd\Bits\Snowflake;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\GameStarted;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\PlayerJoinedGame;
use Thunk\Verbs\Examples\Monopoly\Game\Token;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;

it('can generate data', function () {
    $csv = <<<'CSV'
    Name,Space,Color,Position,Price,PriceBuild,Rent,RentBuild1,RentBuild2,RentBuild3,RentBuild4,RentBuild5,Number
    Go,Go,None,0,0,0,0,0,0,0,0,0,0
    Mediterranean Avenue,Street,Brown,1,60,50,2,10,30,90,160,250,2
    Community Chest,Chest,None,2,0,0,0,0,0,0,0,0,0
    Baltic Avenue,Street,Brown,3,60,50,4,20,60,180,320,450,2
    Income Tax,Tax,None,4,200,0,200,0,0,0,0,0,0
    Reading Railroad,Railroad,None,5,200,0,25,0,0,0,0,0,0
    Oriental Avenue,Street,LightBlue,6,100,50,6,30,90,270,400,550,3
    Chance,Chance,None,7,0,0,0,0,0,0,0,0,0
    Vermont Avenue,Street,LightBlue,8,100,50,6,30,90,270,400,550,3
    Connecticut Avenue,Street,LightBlue,9,120,50,8,40,100,300,450,600,3
    Jail,Jail,None,10,0,0,0,0,0,0,0,0,0
    St. Charles Place,Street,Pink,11,140,100,10,50,150,450,625,750,3
    Electric Company,Utility,None,12,150,0,4,0,0,0,0,0,0
    States Avenue,Street,Pink,13,140,100,10,50,150,450,625,750,3
    Virginia Avenue,Street,Pink,14,160,100,12,60,180,500,700,900,3
    Pennsylvania Railroad,Railroad,None,15,200,0,25,0,0,0,0,0,0
    St. James Place,Street,Orange,16,180,100,14,70,200,550,750,950,3
    Community Chest,Chest,None,17,0,0,0,0,0,0,0,0,0
    Tennessee Avenue,Street,Orange,18,180,100,14,70,200,550,750,950,3
    New York Avenue,Street,Orange,19,200,100,16,80,220,600,800,1000,3
    Free Parking,Parking,None,20,0,0,0,0,0,0,0,0,0
    Kentucky Avenue,Street,Red,21,220,150,18,90,250,700,875,1050,3
    Chance,Chance,None,22,0,0,0,0,0,0,0,0,0
    Indiana Avenue,Street,Red,23,220,150,18,90,250,700,875,1050,3
    Illinois Avenue,Street,Red,24,240,150,20,100,300,750,925,1100,3
    B. & O. Railroad,Railroad,None,25,200,0,25,0,0,0,0,0,0
    Atlantic Avenue,Street,Yellow,26,260,150,22,110,330,800,975,1150,3
    Ventnor Avenue,Street,Yellow,27,260,150,22,110,330,800,975,1150,3
    Water Works,Utility,None,28,150,0,4,0,0,0,0,0,0
    Marvin Gardens,Street,Yellow,29,280,150,24,120,360,850,1025,1200,3
    Go To Jail,GoToJail,None,30,0,0,0,0,0,0,0,0,0
    Pacific Avenue,Street,Green,31,300,200,26,130,390,900,1100,1275,3
    North Carolina Avenue,Street,Green,32,300,200,26,130,390,900,1100,1275,3
    Community Chest,Chest,None,33,0,0,0,0,0,0,0,0,0
    Pennsylvania Avenue,Street,Green,34,320,200,28,150,450,1000,1200,1400,3
    Short Line,Railroad,None,35,200,0,25,0,0,0,0,0,0
    Chance,Chance,None,36,0,0,0,0,0,0,0,0,0
    Park Place,Street,Blue,37,350,200,35,175,500,1100,1300,1500,2
    Luxury Tax,Tax,None,38,100,0,75,0,0,0,0,0,0
    Boardwalk,Street,Blue,39,400,200,50,200,600,1400,1700,2000,2
    CSV;

    $stream = fopen(sprintf('data://text/plain,%s', $csv), 'r');
    $headings = null;

    $colors = [];
    $spaces = [];

    while ($line = fgetcsv($stream)) {
        if (! $headings) {
            $headings = $line;

            continue;
        }

        $data = array_combine($headings, $line);

        $class_name = str($data['Name'])->slug()->studly()->toString();

        $colors[$data['Color']] ??= $data['Number'];
        $spaces[$class_name] = true;

        //Name,Space,Color,Position,Price,PriceBuild,Rent,RentBuild1,RentBuild2,RentBuild3,RentBuild4,RentBuild5,Number

        if ($data['Space'] === 'Street') {
            $impl = <<<PHP
            <?php

            namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

            use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
            use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

            class {$class_name} extends PropertyDetails
            {
                protected string \$name = '{$data['Name']}';

                protected PropertyColor \$color = PropertyColor::{$data['Color']};

                protected int \$position = {$data['Position']};

                protected int \$price = {$data['Price']};

                /** @var int[] */
                protected array \$rent = [{$data['Rent']}, {$data['RentBuild1']}, {$data['RentBuild2']}, {$data['RentBuild3']}, {$data['RentBuild4']}, {$data['RentBuild5']}];

                protected int \$building_cost = {$data['PriceBuild']};
            }
            PHP;
        } else {
            $impl = <<<PHP
            <?php

            namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

            use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
            use Thunk\Verbs\Examples\Monopoly\Game\Spaces\SpaceDetails;

            class {$class_name} extends SpaceDetails
            {
                protected string \$name = '{$data['Name']}';

                protected int \$position = {$data['Position']};
            }
            PHP;
        }

        file_put_contents(__DIR__.'/../src/Game/Spaces/Details/'.$class_name.'.php', $impl);
    }

    $color_cases = collect($colors)
        ->keys()
        ->map(fn ($case) => str($case))
        ->map(fn (\Illuminate\Support\Stringable $case) => "case {$case} = '{$case->kebab()}';")
        ->implode("\n    ");

    $color_set_match = collect($colors)
        ->map(fn ($number, $color) => "self::{$color} => $number,")
        ->implode("\n                ");

    $property_color_enum = <<<PHP
    <?php

    namespace Thunk\Verbs\Examples\Monopoly\Game;

    enum PropertyColor: string
    {
        {$color_cases}

        public function totalSpaces(): int
        {
            return match(\$this) {
                {$color_set_match}
                default => throw new \UnexpectedValueException('Unknown color.'),
            };
        }
    }
    PHP;

    file_put_contents(__DIR__.'/../src/Game/PropertyColor.php', $property_color_enum);

    $space_cases = collect($spaces)
        ->keys()
        ->map(fn ($case) => str($case))
        ->map(fn (\Illuminate\Support\Stringable $case) => "case {$case} = Details\\{$case}::class;")
        ->implode("\n    ");

    $spaces_enum = <<<PHP
    <?php

    namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

    use Illuminate\Support\Traits\ForwardsCalls;

    /**
     * @mixin SpaceDetails
     * @mixin PropertyDetails
     */
    enum Space: string
    {
        use HasDetails;
        use ForwardsCalls;

        {$space_cases}
    }
    PHP;

    file_put_contents(__DIR__.'/../src/Game/Spaces/Space.php', $spaces_enum);

});

it('can play a game of Monopoly', function () {

    // Game setup
    // ---------------------------------------------------------------------------------------------------------------------------

    $player1_id = Snowflake::make()->id();
    $player2_id = Snowflake::make()->id();

    $game_state = verb(new GameStarted())->state(GameState::class);

    expect($game_state->started)->toBeTrue()
        ->and(fn () => GameStarted::fire(game_id: $game_state->id))->toThrow(EventNotValidForCurrentState::class);

    verb(new PlayerJoinedGame(
        game_id: $game_state->id,
        player_id: $player1_id,
        token: Token::Battleship,
    ));

    $player1 = PlayerState::load($player1_id);

    expect($player1->token)->toBe(Token::Battleship)
        ->and($player1->money)->toBeMoney(1500, 'USD')
        ->and($player1->deeds)->toBeEmpty();

    verb(new PlayerJoinedGame(
        game_id: $game_state->id,
        player_id: $player2_id,
        token: Token::TopHat,
    ));

    $player2 = PlayerState::load($player2_id);

    expect($player2->token)->toBe(Token::TopHat)
        ->and($player2->money)->toBeMoney(1500, 'USD')
        ->and($player2->deeds)->toBeEmpty();
});
