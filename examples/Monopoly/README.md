# Monopoly Example

This is an example of the game “Monopoly” built with Verbs. All the rules of Monopoly
have not been implemented, but many of the basic rules are in place. It’s a good way to
see how to use Verbs with a complex set of rules and multiple states interacting at once.

In this example, there are no controllers or Livewire components. Everything is purely in
event state. In a real-world application, you would need to implement a way for users to
interact with this state—either thru traditional HTTP requests, or a REST API, or with
something like Livewire.

## How to use this example

Because this example is much more complicated, it's probably best to start by looking
at the states first:

- `GameState` holds the current status of the game, with information like who is the
  current active player, and what's the current board and bank look like. There is
  only one `GameState` for each game.
- `PlayerState` holds the status of a given player. There will be a separate `PlayerState` 
  instance for each player in the game, and it is used to track things like how much
  money the player currently has, or which token they're playing with.

After reviewing the game states, the next step is to look at the events that happen when
a game is set up. This includes starting the game (setting the initial state of the board
and the bank), having players join the game, and then finally, selecting the first player.

Finally, browse through the gameplay events. These cover everything from rolling dice and
moving your token, to purchasing a property or paying rent.
