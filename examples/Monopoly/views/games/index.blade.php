<x-monopoly::layout>
    <div x-data="{ game_id: '' }" class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">
                Join an existing game
            </h3>
            <form
                class="mt-5 sm:flex sm:items-center"
                @submit.prevent="if (game_id.trim() !== '') { window.location.href = `./games/${game_id}` }"
            >
                <div class="w-full sm:max-w-xs">
                    <label
                        for="game_id"
                        class="sr-only"
                    >
                        Game ID
                    </label>
                    <input
                        type="number"
                        id="game_id"
                        x-model="game_id"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        placeholder="eg. 123000456001"
                    />
                </div>
                <button
                    type="submit"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:ml-3 sm:mt-0 sm:w-auto"
                    :disabled="game_id.trim() === ''"
                    :class="{ 'opacity-50': game_id.trim() === '' }"
                >
                    Join game
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white shadow mt-6 sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">
                Start a new game
            </h3>
            <div class="mt-2 max-w-xl text-sm text-gray-500">
                <p>First to get here? Start a new game and invite your friends!</p>
            </div>
            <form class="mt-5" action="./games" method="post">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                >
                    Start new game
                </button>
            </form>
        </div>
    </div>

</x-monopoly::layout>
