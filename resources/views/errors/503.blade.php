<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>ただいま工事中 | {{ config('app.name') }}</title>

    <style>
        :root {
            color-scheme: dark;
            --ink: #13151b;
            --paper: #f7f2e8;
            --signal: #f6c445;
            --signal-dark: #c98213;
            --steel: #8ba2a8;
            --sky: #8ed7df;
            --night: #1d2630;
            --leaf: #2f7d69;
            --danger: #e64539;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--night);
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow: hidden;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
            color: var(--paper);
            background:
                radial-gradient(circle at 18% 18%, rgb(246 196 69 / 24%), transparent 28rem),
                linear-gradient(180deg, #8ed7df 0%, #bce9e4 35%, #f0d28b 35.2%, #f2b35d 58%, #2a3036 58.2%, #11141a 100%);
        }

        .page {
            position: relative;
            display: grid;
            min-height: 100vh;
            place-items: center;
            padding: clamp(1rem, 3vw, 3rem);
            isolation: isolate;
        }

        .sun {
            position: absolute;
            top: 7vh;
            left: min(9vw, 7rem);
            width: clamp(5rem, 12vw, 9rem);
            aspect-ratio: 1;
            border-radius: 50%;
            background: radial-gradient(circle, #fff1aa 0 24%, #f6c445 25% 56%, rgb(246 196 69 / 0%) 58%);
            box-shadow: 0 0 4rem rgb(246 196 69 / 55%);
            animation: pulse 4s ease-in-out infinite;
        }

        .scene {
            position: relative;
            width: min(72rem, 100%);
            min-height: min(42rem, 82vh);
            border: 1px solid rgb(255 255 255 / 18%);
            border-radius: 8px;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgb(255 255 255 / 16%), transparent 44%),
                linear-gradient(165deg, rgb(255 255 255 / 20%) 0 8%, transparent 8.2%),
                rgb(19 21 27 / 72%);
            box-shadow: 0 2rem 6rem rgb(0 0 0 / 34%);
        }

        .copy {
            position: absolute;
            z-index: 6;
            top: clamp(1.25rem, 4vw, 3rem);
            left: clamp(1.25rem, 4vw, 3.5rem);
            max-width: min(35rem, calc(100% - 2.5rem));
            text-shadow: 0 0.35rem 1rem rgb(0 0 0 / 36%);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.45rem 0.7rem;
            border: 1px solid rgb(255 255 255 / 22%);
            border-radius: 8px;
            font-size: clamp(0.72rem, 1.6vw, 0.86rem);
            font-weight: 800;
            line-height: 1;
            color: #13151b;
            background: var(--signal);
            box-shadow: 0 0.8rem 2rem rgb(0 0 0 / 24%);
        }

        .beacon {
            width: 0.62rem;
            aspect-ratio: 1;
            border-radius: 50%;
            background: var(--danger);
            box-shadow: 0 0 0.8rem var(--danger);
            animation: blink 1s steps(2, end) infinite;
        }

        h1 {
            margin: 1.05rem 0 0;
            font-size: clamp(2.4rem, 7vw, 6rem);
            line-height: 0.95;
            letter-spacing: 0;
        }

        .lead {
            max-width: 31rem;
            margin: 1rem 0 0;
            font-size: clamp(1rem, 2.1vw, 1.25rem);
            line-height: 1.8;
            color: rgb(247 242 232 / 88%);
        }

        .status {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin-top: 1.35rem;
        }

        .chip {
            border: 1px solid rgb(255 255 255 / 18%);
            border-radius: 8px;
            padding: 0.5rem 0.72rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: rgb(247 242 232 / 90%);
            background: rgb(19 21 27 / 42%);
            backdrop-filter: blur(12px);
        }

        .construction-scene {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .cloud {
            transform-origin: center;
            animation: drift 18s linear infinite;
        }

        .cloud.two {
            animation-duration: 25s;
            animation-delay: -9s;
        }

        .crane-arm {
            transform-origin: 642px 169px;
            animation: crane-swing 7s ease-in-out infinite;
        }

        .cable {
            transform-origin: 730px 170px;
            animation: cable-swing 7s ease-in-out infinite;
        }

        .load {
            transform-origin: 730px 334px;
            animation: lift 7s ease-in-out infinite;
        }

        .hook {
            transform-origin: 730px 314px;
            animation: hook-swing 7s ease-in-out infinite;
        }

        .truck {
            animation: truck-roll 9s cubic-bezier(.65, 0, .35, 1) infinite;
        }

        .wheel {
            transform-box: fill-box;
            transform-origin: center;
            animation: spin 0.7s linear infinite;
        }

        .conveyor {
            stroke-dasharray: 18 14;
            animation: conveyor 0.8s linear infinite;
        }

        .spark {
            transform-box: fill-box;
            transform-origin: center;
            animation: spark 1.15s ease-out infinite;
        }

        .spark:nth-of-type(2n) {
            animation-delay: 0.35s;
        }

        .spark:nth-of-type(3n) {
            animation-delay: 0.7s;
        }

        .barrier-stripe {
            animation: stripe-alert 1.2s steps(2, end) infinite;
        }

        .ground-lines {
            stroke-dasharray: 38 34;
            animation: conveyor 1.8s linear infinite;
        }

        .worker-arm {
            transform-origin: 488px 434px;
            animation: hammer 1s ease-in-out infinite;
        }

        @keyframes pulse {
            50% {
                transform: scale(1.06);
                opacity: 0.84;
            }
        }

        @keyframes blink {
            50% {
                opacity: 0.3;
            }
        }

        @keyframes drift {
            from {
                transform: translateX(-16rem);
            }

            to {
                transform: translateX(82rem);
            }
        }

        @keyframes crane-swing {
            0%, 100% {
                transform: rotate(-2deg);
            }

            50% {
                transform: rotate(4deg);
            }
        }

        @keyframes cable-swing {
            0%, 100% {
                transform: rotate(2deg);
            }

            50% {
                transform: rotate(-4deg);
            }
        }

        @keyframes hook-swing {
            0%, 100% {
                transform: rotate(-5deg);
            }

            50% {
                transform: rotate(5deg);
            }
        }

        @keyframes lift {
            0%, 100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5.5rem);
            }
        }

        @keyframes truck-roll {
            0% {
                transform: translateX(-14rem);
            }

            45%, 58% {
                transform: translateX(4rem);
            }

            100% {
                transform: translateX(76rem);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes conveyor {
            to {
                stroke-dashoffset: -64;
            }
        }

        @keyframes spark {
            0% {
                opacity: 0;
                transform: scale(0.4) translateY(0);
            }

            35% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                transform: scale(1.3) translateY(-1rem);
            }
        }

        @keyframes stripe-alert {
            50% {
                fill: #fff5c4;
            }
        }

        @keyframes hammer {
            0%, 100% {
                transform: rotate(14deg);
            }

            50% {
                transform: rotate(-18deg);
            }
        }

        @media (max-width: 720px) {
            body {
                overflow: auto;
            }

            .page {
                padding: 0;
            }

            .scene {
                width: 100%;
                min-height: 100vh;
                border: 0;
                border-radius: 0;
            }

            .copy {
                top: 1rem;
                left: 1rem;
                right: 1rem;
            }

            .lead {
                max-width: 24rem;
            }

            .construction-scene {
                width: 145%;
                height: 100%;
                transform: translateX(-23%);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
    <main class="page" aria-labelledby="maintenance-title">
        <div class="sun" aria-hidden="true"></div>

        <section class="scene">
            <div class="copy">
                <div class="eyebrow"><span class="beacon" aria-hidden="true"></span>503 Maintenance</div>
                <h1 id="maintenance-title">ただいま工事中です</h1>
                <p class="lead">
                    現在システムを整備しています。<br />
                    作業が完了し次第、まもなく再開します。
                </p>
            </div>

            <svg class="construction-scene" viewBox="0 0 1120 720" role="img" aria-label="工事中のアニメーション">
                <defs>
                    <linearGradient id="beam" x1="0" x2="1">
                        <stop offset="0" stop-color="#f6c445"/>
                        <stop offset="1" stop-color="#d98f1d"/>
                    </linearGradient>
                    <linearGradient id="steel" x1="0" x2="1">
                        <stop offset="0" stop-color="#c5d1d2"/>
                        <stop offset="1" stop-color="#6f858b"/>
                    </linearGradient>
                    <pattern id="hazard" width="42" height="42" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                        <rect width="21" height="42" fill="#13151b"/>
                        <rect x="21" width="21" height="42" fill="#f6c445"/>
                    </pattern>
                </defs>

                <g class="cloud" opacity=".8">
                    <ellipse cx="80" cy="104" rx="66" ry="28" fill="#fff8e7"/>
                    <ellipse cx="132" cy="94" rx="48" ry="36" fill="#fff8e7"/>
                    <ellipse cx="177" cy="111" rx="68" ry="25" fill="#fff8e7"/>
                </g>
                <g class="cloud two" opacity=".74">
                    <ellipse cx="20" cy="178" rx="48" ry="20" fill="#fff8e7"/>
                    <ellipse cx="62" cy="167" rx="40" ry="29" fill="#fff8e7"/>
                    <ellipse cx="104" cy="181" rx="56" ry="19" fill="#fff8e7"/>
                </g>

                <path d="M0 412 C180 347 268 412 412 370 C573 323 695 390 810 349 C930 307 1012 334 1120 297 V720 H0 Z" fill="#486a62"/>
                <path d="M0 470 C190 422 338 494 522 432 C735 361 897 435 1120 366 V720 H0 Z" fill="#2f7d69"/>
                <path d="M0 502 H1120 V720 H0 Z" fill="#2b3036"/>
                <path class="ground-lines" d="M38 628 H1090" fill="none" stroke="#f7f2e8" stroke-width="11" stroke-linecap="round" opacity=".82"/>

                <g transform="translate(642 162)">
                    <rect x="-22" y="-10" width="48" height="424" rx="8" fill="url(#beam)"/>
                    <path d="M1 0 L-72 404 M1 0 L77 404 M-22 74 H26 M-22 154 H26 M-22 234 H26 M-22 314 H26" fill="none" stroke="#7a4f13" stroke-width="9" stroke-linecap="round"/>
                    <circle cx="1" cy="0" r="29" fill="#13151b"/>
                    <circle cx="1" cy="0" r="14" fill="#f6c445"/>
                </g>

                <g class="crane-arm">
                    <path d="M636 160 L882 110 L1011 150 L665 197 Z" fill="url(#beam)"/>
                    <path d="M668 194 L882 111 L1008 149 M696 187 L730 142 M753 176 L790 130 M811 165 L850 118 M873 153 L913 120 M933 154 L953 134" fill="none" stroke="#7a4f13" stroke-width="8" stroke-linecap="round"/>
                    <rect x="603" y="126" width="78" height="54" rx="8" fill="#13151b"/>
                    <rect x="620" y="137" width="28" height="20" rx="4" fill="#8ed7df"/>
                </g>

                <g class="cable">
                    <path d="M730 168 V314" fill="none" stroke="#20242a" stroke-width="7" stroke-linecap="round"/>
                    <g class="hook">
                        <path d="M730 306 c-27 16 -8 53 22 33" fill="none" stroke="#20242a" stroke-width="9" stroke-linecap="round"/>
                    </g>
                    <g class="load">
                        <rect x="666" y="334" width="128" height="74" rx="8" fill="url(#hazard)"/>
                        <rect x="658" y="325" width="144" height="18" rx="8" fill="#13151b"/>
                        <path d="M690 334 L730 306 L770 334" fill="none" stroke="#20242a" stroke-width="6" stroke-linecap="round"/>
                    </g>
                </g>

                <g transform="translate(104 470)">
                    <path d="M38 86 L136 12 H318 L406 86 Z" fill="#13151b"/>
                    <path class="conveyor" d="M58 78 L144 28 H305 L382 78" fill="none" stroke="#c5d1d2" stroke-width="14" stroke-linecap="round"/>
                    <circle cx="86" cy="84" r="20" fill="#6f858b"/>
                    <circle cx="342" cy="84" r="20" fill="#6f858b"/>
                    <rect x="164" y="0" width="55" height="32" rx="6" fill="#f6c445"/>
                    <rect x="244" y="0" width="55" height="32" rx="6" fill="#e64539"/>
                </g>

                <g transform="translate(460 412)">
                    <circle cx="32" cy="24" r="18" fill="#f6c445"/>
                    <path d="M26 43 H49 L64 116 H18 Z" fill="#2f7d69"/>
                    <path class="worker-arm" d="M52 60 L91 34 L106 48" fill="none" stroke="#f7f2e8" stroke-width="12" stroke-linecap="round"/>
                    <path d="M29 115 L14 165 M55 115 L74 165" fill="none" stroke="#f7f2e8" stroke-width="13" stroke-linecap="round"/>
                    <path d="M12 24 H57 L48 4 H21 Z" fill="#f6c445"/>
                    <g fill="#f6c445">
                        <path class="spark" d="M108 60 l12 -17 l6 18 z"/>
                        <path class="spark" d="M91 72 l-14 13 l2 -18 z"/>
                        <path class="spark" d="M116 81 l17 7 l-15 8 z"/>
                    </g>
                </g>

                <g class="truck" transform="translate(0 0)">
                    <g transform="translate(74 522)">
                        <path d="M28 64 H286 L266 8 H142 L102 42 H28 Z" fill="#e64539"/>
                        <path d="M142 8 H265 L285 64 H142 Z" fill="#f6c445"/>
                        <rect x="160" y="19" width="52" height="29" rx="4" fill="#8ed7df"/>
                        <rect x="14" y="60" width="292" height="38" rx="10" fill="#13151b"/>
                        <circle class="wheel" cx="74" cy="101" r="31" fill="#20242a"/>
                        <circle class="wheel" cx="74" cy="101" r="14" fill="#8ba2a8"/>
                        <circle class="wheel" cx="240" cy="101" r="31" fill="#20242a"/>
                        <circle class="wheel" cx="240" cy="101" r="14" fill="#8ba2a8"/>
                    </g>
                </g>

                <g transform="translate(792 514)">
                    <rect x="0" y="72" width="258" height="38" rx="8" fill="#13151b"/>
                    <rect class="barrier-stripe" x="20" y="20" width="218" height="44" rx="8" fill="url(#hazard)"/>
                    <path d="M42 64 V120 M216 64 V120" stroke="#f6c445" stroke-width="18" stroke-linecap="round"/>
                    <path d="M34 120 H92 M191 120 H249" stroke="#f6c445" stroke-width="18" stroke-linecap="round"/>
                </g>
            </svg>
        </section>
    </main>
</body>
</html>
