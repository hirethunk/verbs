<x-monopoly::layout>

    <div>
        <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-400">
            Current game
        </h3>

        @if($player_id && $game->hasPlayer($player_id))
            <div class="rounded-md bg-green-50 p-4 my-2.5">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                  clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">You are in this game!</p>
                    </div>
                </div>
            </div>
        @endif

        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">
                    Total players
                </dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
                    {{ count($game->player_ids) }}
                </dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">
                    Unsold properties
                </dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
                    {{ $game->bank->deeds->count() }}
                </dd>
            </div>
        </dl>
    </div>

    @if(! $player_id || ! $game->hasPlayer($player_id))
        <form action="./{{ $game->id }}/players" method="post" class="mt-5">
            @csrf
            <div class="flex items-end space-x-2">
                <div>
                    <label for="token" class="sr-only">
                        Choose token
                    </label>
                    <select
                        id="token"
                        name="token"
                        class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                    >
                        @foreach($tokens as $token)
                            <option value="{{ $token->value }}">
                                {{ str($token->name)->headline() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto"
                >
                    Join this game
                </button>
            </div>
        </form>
    @endif

</x-monopoly::layout>
