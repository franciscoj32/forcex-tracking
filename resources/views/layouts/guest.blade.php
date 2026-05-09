<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\AppSetting::current()->program_name }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=forcex">
        <link rel="shortcut icon" href="{{ asset('favicon.svg') }}?v=forcex">
        <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}?v=forcex">

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
                width: min(1080px, 100%);
                margin: 0 auto;
                border-radius: 32px;
                background: #ffffff;
                box-shadow: 0 24px 50px rgba(15, 23, 42, 0.20);
                overflow: hidden;
            }

            .guest-auth-shell {
                display: grid;
                grid-template-columns: minmax(280px, 420px) minmax(0, 1fr);
                min-height: 720px;
            }

            .guest-brand-panel {
                position: relative;
                padding: 40px 34px;
                background:
                    radial-gradient(circle at top, rgba(103, 232, 249, 0.22), transparent 30%),
                    linear-gradient(160deg, #08233d 0%, #0b3552 38%, #0f766e 100%);
                color: #e6fbff;
            }

            .guest-brand-panel::after {
                content: "";
                position: absolute;
                inset: auto -90px -100px auto;
                width: 220px;
                height: 220px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.08);
                filter: blur(4px);
            }

            .guest-brand-copy {
                position: relative;
                z-index: 1;
                margin-top: 90px;
            }

            .guest-brand-copy p {
                margin: 0;
            }

            .guest-brand-eyebrow {
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.28em;
                text-transform: uppercase;
                color: #a5f3fc;
            }

            .guest-brand-title {
                margin-top: 18px;
                font-size: 34px;
                line-height: 1.05;
                font-weight: 700;
                color: #ffffff;
            }

            .guest-brand-text {
                margin-top: 18px;
                max-width: 28ch;
                font-size: 15px;
                line-height: 1.7;
                color: rgba(230, 251, 255, 0.82);
            }

            .guest-form-panel {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 52px 56px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }

            .guest-form-inner {
                width: min(100%, 520px);
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

            @media (max-width: 960px) {
                .guest-auth-shell {
                    grid-template-columns: 1fr;
                }

                .guest-brand-panel {
                    padding: 28px 24px;
                }

                .guest-brand-copy {
                    margin-top: 36px;
                }

                .guest-brand-title {
                    font-size: 28px;
                }

                .guest-form-panel {
                    padding: 32px 24px 36px;
                }
            }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="guest-body">
        <div class="guest-shell">
            <div class="guest-card">
                <div class="guest-auth-shell">
                    @php($appSettings = \App\Models\AppSetting::current())
                    <aside class="guest-brand-panel">
                        <x-application-logo />
                        <div class="guest-brand-copy">
                            <p class="guest-brand-eyebrow">{{ $appSettings->login_eyebrow }}</p>
                            <p class="guest-brand-title">{{ $appSettings->login_title }}</p>
                            <p class="guest-brand-text">
                                {{ $appSettings->login_text }}
                            </p>
                        </div>
                    </aside>

                    <main class="guest-form-panel">
                        <div class="guest-form-inner">
                            {{ $slot }}
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
