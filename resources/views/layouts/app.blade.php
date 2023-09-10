<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="api-token" content="{{ auth()->user()->api_token }}">
    @endauth

    <title>{{ config('app.name', 'Laravel') }}</title>

    @vite([
        'resources/sass/app.scss',
        'resources/js/app.js'
    ])
    @stack('inline-scripts')
</head>

<body class="bg-light">
    @include('shared/navbar')

    <div class="container">
        @include('shared/alerts')

        <main>
            @yield('content')
        </main>
    </div>

    @include('shared/footer')
</body>
</html>
