@php use Illuminate\Support\Facades\Session; @endphp
<x-monopoly::layout>
    <div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-sm">
            <h2 class="mt-10 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900 dark:text-gray-400 dark:text-gray-400">
                Sign in to Monopoly
            </h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
            <form class="space-y-6" action="" method="POST">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900 dark:text-gray-400">
                        Enter your name:
                    </label>
                    <div class="mt-2">
                        <input
                            id="name"
                            name="name"
                            autocomplete="name"
                            value="{{ Session::get('user.name') }}"
                            required
                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-gray-400 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        >
                    </div>
                </div>

                <div>
                    <button
                        type="submit"
                        class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        Sign in
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-monopoly::layout>
