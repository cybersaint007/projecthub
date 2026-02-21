<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'ProjectHub') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .js-bi { display: none; }
            /* Badge styles (Bootstrap-compatible when Bootstrap not loaded) */
            .badge { display: inline-block; padding: 0.25em 0.5em; font-size: 0.75em; font-weight: 500; line-height: 1; border-radius: 0.25rem; }
            .badge.bg-danger { background-color: #dc3545; color: #fff; }
            .badge.bg-warning.text-dark { background-color: #ffc107; color: #212529; }
            .badge.bg-success { background-color: #198754; color: #fff; }
            .badge.bg-primary { background-color: #0d6efd; color: #fff; }
            .badge.bg-info.text-dark { background-color: #0dcaf0; color: #212529; }
            .badge.bg-secondary { background-color: #6c757d; color: #fff; }
            .badge .me-1 { margin-right: 0.25rem; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var test = document.createElement('i');
            test.className = 'bi bi-lock-fill';
            test.style.position = 'absolute';
            test.style.left = '-9999px';
            document.body.appendChild(test);

            var fontFamily = (window.getComputedStyle(test).fontFamily || '').toLowerCase();
            var hasIcons = fontFamily.indexOf('bootstrap-icons') !== -1;

            document.body.removeChild(test);

            if (hasIcons) {
                document.querySelectorAll('.js-bi').forEach(function (el) {
                    el.style.display = 'inline-block';
                });
            }
        });
        </script>
    </body>
</html>
