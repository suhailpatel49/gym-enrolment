@props([
    'title' => 'Incline Fitness',
    'skipLabel' => 'Skip to main content',
    'skipTarget' => 'main-content',
])

<!DOCTYPE html>
<html lang="en" data-visual-system="incline">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas font-sans text-tertiary antialiased">
    <a href="#{{ $skipTarget }}" class="sr-only fixed left-3 top-3 z-50 rounded-sm bg-tertiary px-4 py-3 font-bold text-white focus:not-sr-only">{{ $skipLabel }}</a>
    {{ $slot }}
    @livewireScripts
</body>
</html>
