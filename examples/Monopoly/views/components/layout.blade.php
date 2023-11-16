<!doctype html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <title>Monopoly</title>
</head>
<body class="flex h-full bg-zinc-50 dark:bg-black">
<div class="flex w-full">
    <div class="mx-auto w-full max-w-7xl lg:px-8">
        <div class="relative p-4 sm:p-8 lg:p-12">
            <div class="mx-auto max-w-2xl lg:max-w-5xl">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
</body>
</html>
