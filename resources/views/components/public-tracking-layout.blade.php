<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'FORCEX Tracking') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700&display=swap" rel="stylesheet" />
        <style>
            .guest-body {
                min-height: 100vh;
                margin: 0;
                background: radial-gradient(circle at top, rgba(8, 145, 178, 0.18), transparent 35%), linear-gradient(135deg, #0f172a 0%, #111827 45%, #0f766e 100%);
                font-family: "IBM Plex Sans", sans-serif;
                color: #0f172a;
            }

            .guest-shell {
                width: min(1200px, calc(100% - 32px));
                margin: 0 auto;
                padding: 32px 0 40px;
            }

            .guest-card {
                width: min(1040px, 100%);
                margin: 0 auto;
                border-radius: 32px;
                background: #ffffff;
                box-shadow: 0 24px 50px rgba(15, 23, 42, 0.20);
                overflow: hidden;
            }

            @media (max-width: 640px) {
                .guest-shell {
                    width: min(1200px, calc(100% - 20px));
                    padding: 20px 0 28px;
                }

                .guest-card {
                    border-radius: 24px;
                }
            }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('flags/flag-icons/css/flag-icons.min.css') }}">
    </head>
    <body class="guest-body">
        <div class="guest-shell">
            <div class="guest-card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
