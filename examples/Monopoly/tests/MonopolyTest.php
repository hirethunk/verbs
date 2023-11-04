<?php

use Glhd\Bits\Snowflake;
use Illuminate\Support\Collection;
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
    Mediterranean Avenue,Property,Brown,1,60,50,2,10,30,90,160,250,2
    Community Chest,Chest,None,2,0,0,0,0,0,0,0,0,0
    Baltic Avenue,Property,Brown,3,60,50,4,20,60,180,320,450,2
    Income Tax,Tax,None,4,200,0,200,0,0,0,0,0,0
    Reading Railroad,Railroad,None,5,200,0,25,0,0,0,0,0,0
    Oriental Avenue,Property,LightBlue,6,100,50,6,30,90,270,400,550,3
    Chance,Chance,None,7,0,0,0,0,0,0,0,0,0
    Vermont Avenue,Property,LightBlue,8,100,50,6,30,90,270,400,550,3
    Connecticut Avenue,Property,LightBlue,9,120,50,8,40,100,300,450,600,3
    Jail,Jail,None,10,0,0,0,0,0,0,0,0,0
    St. Charles Place,Property,Pink,11,140,100,10,50,150,450,625,750,3
    Electric Company,Utility,None,12,150,0,4,0,0,0,0,0,0
    States Avenue,Property,Pink,13,140,100,10,50,150,450,625,750,3
    Virginia Avenue,Property,Pink,14,160,100,12,60,180,500,700,900,3
    Pennsylvania Railroad,Railroad,None,15,200,0,25,0,0,0,0,0,0
    St. James Place,Property,Orange,16,180,100,14,70,200,550,750,950,3
    Community Chest,Chest,None,17,0,0,0,0,0,0,0,0,0
    Tennessee Avenue,Property,Orange,18,180,100,14,70,200,550,750,950,3
    New York Avenue,Property,Orange,19,200,100,16,80,220,600,800,1000,3
    Free Parking,Parking,None,20,0,0,0,0,0,0,0,0,0
    Kentucky Avenue,Property,Red,21,220,150,18,90,250,700,875,1050,3
    Chance,Chance,None,22,0,0,0,0,0,0,0,0,0
    Indiana Avenue,Property,Red,23,220,150,18,90,250,700,875,1050,3
    Illinois Avenue,Property,Red,24,240,150,20,100,300,750,925,1100,3
    B. & O. Railroad,Railroad,None,25,200,0,25,0,0,0,0,0,0
    Atlantic Avenue,Property,Yellow,26,260,150,22,110,330,800,975,1150,3
    Ventnor Avenue,Property,Yellow,27,260,150,22,110,330,800,975,1150,3
    Water Works,Utility,None,28,150,0,4,0,0,0,0,0,0
    Marvin Gardens,Property,Yellow,29,280,150,24,120,360,850,1025,1200,3
    Go To Jail,GoToJail,None,30,0,0,0,0,0,0,0,0,0
    Pacific Avenue,Property,Green,31,300,200,26,130,390,900,1100,1275,3
    North Carolina Avenue,Property,Green,32,300,200,26,130,390,900,1100,1275,3
    Community Chest,Chest,None,33,0,0,0,0,0,0,0,0,0
    Pennsylvania Avenue,Property,Green,34,320,200,28,150,450,1000,1200,1400,3
    Short Line,Railroad,None,35,200,0,25,0,0,0,0,0,0
    Chance,Chance,None,36,0,0,0,0,0,0,0,0,0
    Park Place,Property,Blue,37,350,200,35,175,500,1100,1300,1500,2
    Luxury Tax,Tax,None,38,100,0,75,0,0,0,0,0,0
    Boardwalk,Property,Blue,39,400,200,50,200,600,1400,1700,2000,2
    CSV;

    $fs = new Illuminate\Filesystem\Filesystem();

    $stream = fopen(sprintf('data://text/plain,%s', $csv), 'r');
    $headings = null;

    $spaces = new Collection();

    while ($line = fgetcsv($stream)) {
        if (! $headings) {
            $headings = $line;

            continue;
        }

        $data = array_combine($headings, $line);
        $data['class'] = (string) str($data['Name'])->slug()->studly();
        $data['namespace'] = (string) str($data['Space'])->plural()->studly();

        $spaces->push($data);
    }

    foreach ($spaces->groupBy('Space') as $group => $spaces) {
        foreach ($spaces as $space) {
            $parent = $spaces->count() > 1 ? $space['Space'] : 'Space';
            $namespace = $spaces->count() > 1 ? 'Spaces\\'.$space['namespace'] : 'Spaces';
            $directory = str_replace('\\', '//', $namespace);

            if ($space['Space'] === 'Property') {
                $code = <<<PHP
                <?php

                namespace Thunk\Verbs\Examples\Monopoly\Game\{$namespace};

                use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
                use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

                class {$space['class']} extends Property
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
                $code = <<<PHP
                <?php

                namespace Thunk\Verbs\Examples\Monopoly\Game\{$namespace};

                use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
                use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;

                class {$space['class']} extends {$parent}
                {
                    protected string \$name = '{$data['Name']}';

                    protected int \$position = {$data['Position']};
                }
                PHP;
            }

            $fs->ensureDirectoryExists(__DIR__.'/../src/Game/'.$directory);
            $fs->put(__DIR__.'/../src/Game/'.$directory.'/'.$space['class'].'.php', $code);
        }
    }
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
