<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Pingwise') }}</title>
        <style>
            html, body {
                margin: 0;
                width: 100%;
                height: 100%;
                background: #fff;
            }

            body {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .hero-image {
                width: min(92vw, 920px);
                height: min(92vh, 920px);
                object-fit: contain;
                object-position: center;
                display: block;
            }
        </style>
    </head>
    <body>
        <img src="{{ asset('images/balloon.png') }}" alt="Balloon" class="hero-image">
    </body>
</html>
