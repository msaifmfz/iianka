<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>ただいま工事中 | {{ config('app.name') }}</title>

    <style>
        :root {
            color-scheme: light;
            --asphalt: #15171d;
            --asphalt-soft: #242936;
            --chalk: #f9fbff;
            --concrete: #dce5e8;
            --sky: #6fd3dc;
            --sky-deep: #178c9a;
            --safety: #ffd23f;
            --signal: #ec4b3e;
            --fresh: #22a06b;
            --ink: #101217;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--sky);
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
            color: var(--chalk);
            background:
                linear-gradient(180deg, #66d9e8 0 34%, #9ee7e4 34.1% 48%, #67bb7d 48.1% 59%, #2a7a57 59.1% 66%, #262a31 66.1% 100%);
        }

        .maintenance-page {
            position: relative;
            min-height: 100vh;
            isolation: isolate;
            overflow: hidden;
        }

        .maintenance-page::before,
        .maintenance-page::after {
            position: absolute;
            inset-inline: 0;
            z-index: -1;
            content: "";
        }

        .maintenance-page::before {
            top: 0;
            height: 42%;
            background:
                linear-gradient(90deg, transparent 0 10%, rgb(255 255 255 / 32%) 10% 10.5%, transparent 10.5% 100%),
                linear-gradient(180deg, rgb(255 255 255 / 20%), transparent 72%);
            background-size: 8rem 100%, auto;
            animation: blueprint-slide 28s linear infinite;
        }

        .maintenance-page::after {
            bottom: 0;
            height: 35%;
            background:
                repeating-linear-gradient(90deg, transparent 0 2.5rem, rgb(255 210 63 / 82%) 2.5rem 6.5rem, transparent 6.5rem 9rem),
                linear-gradient(180deg, transparent, rgb(0 0 0 / 36%));
            clip-path: polygon(0 42%, 100% 8%, 100% 100%, 0 100%);
            opacity: 0.72;
        }

        .content {
            position: relative;
            display: grid;
            min-height: 100vh;
            grid-template-columns: minmax(0, 0.82fr) minmax(20rem, 1.18fr);
            align-items: center;
            gap: 2rem;
            width: min(78rem, calc(100% - 2rem));
            margin: 0 auto;
            padding: 2rem 0;
        }

        .copy {
            z-index: 2;
            max-width: 38rem;
            padding: 1.25rem;
            text-shadow: 0 0.25rem 0.9rem rgb(0 0 0 / 26%);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            border: 1px solid rgb(16 18 23 / 20%);
            border-radius: 8px;
            padding: 0.55rem 0.75rem;
            color: var(--ink);
            background: var(--safety);
            box-shadow: 0 0.75rem 0 rgb(16 18 23 / 18%);
            font-size: 0.88rem;
            font-weight: 900;
            line-height: 1;
        }

        .badge-light {
            width: 0.7rem;
            aspect-ratio: 1;
            border-radius: 50%;
            background: var(--signal);
            box-shadow: 0 0 0 0.22rem rgb(236 75 62 / 20%), 0 0 1rem var(--signal);
            animation: beacon 1s steps(2, end) infinite;
        }

        h1 {
            max-width: 9ch;
            margin: 1rem 0 0;
            font-size: 4.25rem;
            line-height: 0.95;
            letter-spacing: 0;
        }

        .lead {
            max-width: 32rem;
            margin: 1rem 0 0;
            color: rgb(249 251 255 / 92%);
            font-size: 1.12rem;
            line-height: 1.85;
        }

        .humour {
            display: inline-flex;
            max-width: 34rem;
            align-items: center;
            gap: 0.65rem;
            margin-top: 1.15rem;
            border: 1px solid rgb(255 255 255 / 24%);
            border-radius: 8px;
            padding: 0.75rem 0.85rem;
            color: var(--chalk);
            background: rgb(16 18 23 / 44%);
            backdrop-filter: blur(12px);
            font-size: 0.95rem;
            font-weight: 750;
        }

        .humour span {
            display: inline-grid;
            flex: 0 0 auto;
            width: 2rem;
            aspect-ratio: 1;
            place-items: center;
            border-radius: 8px;
            color: var(--ink);
            background: var(--safety);
            font-weight: 950;
            transform: rotate(-7deg);
            animation: tiny-wobble 2.3s ease-in-out infinite;
        }

        .status-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin-top: 1.1rem;
            padding: 0;
            list-style: none;
        }

        .status-list li {
            border: 1px solid rgb(255 255 255 / 22%);
            border-radius: 8px;
            padding: 0.55rem 0.75rem;
            background: rgb(16 18 23 / 34%);
            color: rgb(249 251 255 / 92%);
            font-size: 0.9rem;
            font-weight: 800;
        }

        .scene-wrap {
            position: relative;
            min-height: 37rem;
        }

        .scene-shadow {
            position: absolute;
            right: 4%;
            bottom: 4%;
            width: 72%;
            height: 1.5rem;
            border-radius: 50%;
            background: rgb(0 0 0 / 36%);
            filter: blur(0.8rem);
            animation: shadow-breathe 4.5s ease-in-out infinite;
        }

        .construction-scene {
            position: absolute;
            inset: auto -3rem -1rem auto;
            width: min(54rem, 116%);
            height: auto;
            filter: drop-shadow(0 2rem 1.5rem rgb(0 0 0 / 26%));
        }

        .cloud {
            animation: cloud-drift 24s linear infinite;
        }

        .cloud.second {
            animation-duration: 31s;
            animation-delay: -13s;
        }

        .sun {
            transform-origin: center;
            animation: sun-pulse 5s ease-in-out infinite;
        }

        .crane-cab,
        .crane-arm {
            transform-origin: 688px 172px;
            animation: crane-swing 6.5s ease-in-out infinite;
        }

        .cable {
            transform-origin: 798px 166px;
            animation: cable-sway 6.5s ease-in-out infinite;
        }

        .payload {
            transform-origin: center;
            animation: payload-lift 6.5s ease-in-out infinite;
        }

        .truck {
            animation: truck-drive 8.8s cubic-bezier(.66, 0, .28, 1) infinite;
        }

        .wheel {
            transform-box: fill-box;
            transform-origin: center;
            animation: wheel-spin 0.75s linear infinite;
        }

        .helmet {
            transform-origin: center;
            animation: helmet-nod 1.7s ease-in-out infinite;
        }

        .worker-arm {
            transform-origin: 487px 508px;
            animation: hammer 0.95s ease-in-out infinite;
        }

        .spark {
            transform-box: fill-box;
            transform-origin: center;
            animation: spark-pop 1.2s ease-out infinite;
        }

        .spark:nth-child(2) {
            animation-delay: 0.2s;
        }

        .spark:nth-child(3) {
            animation-delay: 0.45s;
        }

        .stripe {
            animation: stripe-flash 1.1s steps(2, end) infinite;
        }

        .road-mark {
            stroke-dasharray: 44 36;
            animation: road-run 1.5s linear infinite;
        }

        .bubble {
            transform-origin: center;
            animation: bubble-bob 3.4s ease-in-out infinite;
        }

        @keyframes blueprint-slide {
            to {
                background-position: 24rem 0, 0 0;
            }
        }

        @keyframes beacon {
            50% {
                opacity: 0.28;
            }
        }

        @keyframes tiny-wobble {
            0%, 100% {
                transform: rotate(-7deg) translateY(0);
            }

            50% {
                transform: rotate(7deg) translateY(-0.18rem);
            }
        }

        @keyframes shadow-breathe {
            50% {
                transform: scaleX(0.86);
                opacity: 0.72;
            }
        }

        @keyframes cloud-drift {
            from {
                transform: translateX(-18rem);
            }

            to {
                transform: translateX(78rem);
            }
        }

        @keyframes sun-pulse {
            50% {
                transform: scale(1.05);
                opacity: 0.9;
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

        @keyframes cable-sway {
            0%, 100% {
                transform: rotate(3deg);
            }

            50% {
                transform: rotate(-5deg);
            }
        }

        @keyframes payload-lift {
            0%, 100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-4.6rem);
            }
        }

        @keyframes truck-drive {
            0% {
                transform: translateX(-19rem);
            }

            46%, 58% {
                transform: translateX(7rem);
            }

            100% {
                transform: translateX(78rem);
            }
        }

        @keyframes wheel-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes helmet-nod {
            50% {
                transform: translateY(0.28rem) rotate(-2deg);
            }
        }

        @keyframes hammer {
            0%, 100% {
                transform: rotate(15deg);
            }

            50% {
                transform: rotate(-18deg);
            }
        }

        @keyframes spark-pop {
            0% {
                opacity: 0;
                transform: scale(0.2) translateY(0);
            }

            35% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                transform: scale(1.35) translateY(-1rem);
            }
        }

        @keyframes stripe-flash {
            50% {
                fill: #f9fbff;
            }
        }

        @keyframes road-run {
            to {
                stroke-dashoffset: -80;
            }
        }

        @keyframes bubble-bob {
            50% {
                transform: translateY(-0.45rem);
            }
        }

        @media (max-width: 960px) {
            .content {
                min-height: auto;
                grid-template-columns: 1fr;
                gap: 0;
                padding-top: 1.5rem;
            }

            .copy {
                padding: 0.75rem 0.25rem;
            }

            h1 {
                max-width: 10ch;
                font-size: 3.35rem;
            }

            .scene-wrap {
                min-height: 31rem;
            }

            .construction-scene {
                right: 50%;
                width: min(47rem, 128%);
                transform: translateX(50%);
            }
        }

        @media (max-width: 540px) {
            .content {
                width: min(100% - 1rem, 78rem);
                padding-top: 0.75rem;
            }

            h1 {
                font-size: 2.65rem;
            }

            .lead {
                font-size: 1rem;
            }

            .humour {
                align-items: flex-start;
            }

            .scene-wrap {
                min-height: 25rem;
            }

            .construction-scene {
                width: 43rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
    <main class="maintenance-page" aria-labelledby="maintenance-title">
        <div class="content">
            <section class="copy">
                <div class="badge">
                    <span class="badge-light" aria-hidden="true"></span>
                    503 Maintenance
                </div>

                <h1 id="maintenance-title">ただいま工事中です</h1>

                <p class="lead">
                    現在システムを整備しています。<br>
                    作業が完了し次第、まもなく再開します。
                </p>

                <p class="humour">
                    <span aria-hidden="true">!</span>
                    ネジを一本だけ探しています。見つかり次第、現場監督もサイトも戻ります。
                </p>

                <ul class="status-list" aria-label="メンテナンス状況">
                    <li>足場: 固定済み</li>
                    <li>コーヒー: 稼働中</li>
                    <li>復旧: 全力作業中</li>
                </ul>
            </section>

            <section class="scene-wrap" aria-label="工事中のアニメーション">
                <div class="scene-shadow" aria-hidden="true"></div>

                <svg class="construction-scene" viewBox="0 0 1120 720" role="img" aria-labelledby="construction-scene-title">
                    <title id="construction-scene-title">工事中のアニメーション</title>
                    <defs>
                        <linearGradient id="sunGlow" x1="0" x2="1">
                            <stop offset="0" stop-color="#fff4a8"/>
                            <stop offset="1" stop-color="#ffd23f"/>
                        </linearGradient>
                        <linearGradient id="beamPaint" x1="0" x2="1">
                            <stop offset="0" stop-color="#ffd23f"/>
                            <stop offset="1" stop-color="#f08a24"/>
                        </linearGradient>
                        <linearGradient id="steelPaint" x1="0" x2="1">
                            <stop offset="0" stop-color="#f9fbff"/>
                            <stop offset="1" stop-color="#8fa7ad"/>
                        </linearGradient>
                        <pattern id="hazardPaint" width="42" height="42" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                            <rect width="21" height="42" fill="#101217"/>
                            <rect x="21" width="21" height="42" fill="#ffd23f"/>
                        </pattern>
                    </defs>

                    <g class="sun">
                        <circle cx="140" cy="116" r="58" fill="url(#sunGlow)"/>
                        <circle cx="140" cy="116" r="86" fill="none" stroke="#fff4a8" stroke-width="6" opacity=".35"/>
                    </g>

                    <g class="cloud" opacity=".9">
                        <ellipse cx="70" cy="166" rx="65" ry="24" fill="#f9fbff"/>
                        <ellipse cx="124" cy="151" rx="45" ry="34" fill="#f9fbff"/>
                        <ellipse cx="177" cy="168" rx="67" ry="25" fill="#f9fbff"/>
                    </g>
                    <g class="cloud second" opacity=".78">
                        <ellipse cx="22" cy="88" rx="48" ry="18" fill="#f9fbff"/>
                        <ellipse cx="62" cy="76" rx="38" ry="29" fill="#f9fbff"/>
                        <ellipse cx="105" cy="90" rx="56" ry="20" fill="#f9fbff"/>
                    </g>

                    <path d="M0 422 C138 354 254 407 391 363 C551 312 663 385 805 344 C949 302 1002 328 1120 285 V720 H0 Z" fill="#36a874"/>
                    <path d="M0 486 C188 427 340 489 526 433 C738 369 887 430 1120 365 V720 H0 Z" fill="#267a57"/>
                    <path d="M0 520 H1120 V720 H0 Z" fill="#20242c"/>
                    <path class="road-mark" d="M48 641 H1080" fill="none" stroke="#f9fbff" stroke-linecap="round" stroke-width="12" opacity=".86"/>

                    <g transform="translate(664 166)">
                        <rect x="-24" y="-10" width="50" height="438" rx="8" fill="url(#beamPaint)"/>
                        <path d="M1 0 L-76 417 M1 0 L82 417 M-24 78 H26 M-24 158 H26 M-24 238 H26 M-24 318 H26" fill="none" stroke="#8b5418" stroke-linecap="round" stroke-width="9"/>
                        <circle cx="1" cy="0" r="31" fill="#101217"/>
                        <circle cx="1" cy="0" r="14" fill="#ffd23f"/>
                    </g>

                    <g class="crane-arm">
                        <path d="M642 160 L910 106 L1054 151 L672 202 Z" fill="url(#beamPaint)"/>
                        <path d="M674 199 L910 107 L1051 150 M700 192 L738 145 M761 181 L802 132 M826 168 L868 120 M894 156 L936 114 M963 154 L994 132" fill="none" stroke="#8b5418" stroke-linecap="round" stroke-width="8"/>
                    </g>
                    <g class="crane-cab">
                        <rect x="604" y="126" width="82" height="58" rx="8" fill="#101217"/>
                        <rect x="622" y="138" width="30" height="21" rx="4" fill="#6fd3dc"/>
                        <rect x="657" y="138" width="13" height="21" rx="3" fill="#6fd3dc"/>
                    </g>

                    <g class="cable">
                        <path d="M798 163 V324" fill="none" stroke="#101217" stroke-linecap="round" stroke-width="7"/>
                        <path d="M798 316 c-27 15 -8 54 23 33" fill="none" stroke="#101217" stroke-linecap="round" stroke-width="9"/>
                        <g class="payload">
                            <rect x="726" y="345" width="144" height="76" rx="8" fill="url(#hazardPaint)"/>
                            <rect x="717" y="335" width="162" height="20" rx="8" fill="#101217"/>
                            <path d="M758 345 L798 316 L839 345" fill="none" stroke="#101217" stroke-linecap="round" stroke-width="7"/>
                        </g>
                    </g>

                    <g transform="translate(124 480)">
                        <path d="M34 92 L138 13 H330 L423 92 Z" fill="#101217"/>
                        <path d="M58 82 L148 31 H314 L394 82" fill="none" stroke="url(#steelPaint)" stroke-linecap="round" stroke-width="15"/>
                        <circle cx="85" cy="88" r="20" fill="#8fa7ad"/>
                        <circle cx="354" cy="88" r="20" fill="#8fa7ad"/>
                        <rect x="164" y="1" width="56" height="33" rx="6" fill="#ffd23f"/>
                        <rect x="246" y="1" width="56" height="33" rx="6" fill="#ec4b3e"/>
                    </g>

                    <g transform="translate(445 437)">
                        <g class="bubble">
                            <path d="M53 0 H205 a18 18 0 0 1 18 18 v62 a18 18 0 0 1 -18 18 H91 l-48 37 14 -37 H53 a18 18 0 0 1 -18 -18 V18 A18 18 0 0 1 53 0Z" fill="#f9fbff"/>
                            <text x="68" y="40" fill="#101217" font-size="24" font-weight="900">ネジ</text>
                            <text x="68" y="70" fill="#101217" font-size="19" font-weight="800">一本足りない</text>
                        </g>
                    </g>

                    <g transform="translate(440 509)">
                        <circle class="helmet" cx="44" cy="17" r="17" fill="#ffd23f"/>
                        <path d="M37 36 H62 L78 112 H23 Z" fill="#22a06b"/>
                        <path class="worker-arm" d="M64 54 L104 30 L119 45" fill="none" stroke="#f9fbff" stroke-linecap="round" stroke-width="12"/>
                        <path d="M33 112 L17 164 M62 112 L84 164" fill="none" stroke="#f9fbff" stroke-linecap="round" stroke-width="13"/>
                        <path d="M22 18 H67 L58 0 H31 Z" fill="#ffd23f"/>
                        <g fill="#ffd23f">
                            <path class="spark" d="M121 57 l14 -18 l5 19 z"/>
                            <path class="spark" d="M102 72 l-16 13 l3 -19 z"/>
                            <path class="spark" d="M131 82 l18 8 l-16 9 z"/>
                        </g>
                    </g>

                    <g class="truck">
                        <g transform="translate(70 542)">
                            <path d="M30 58 H302 L278 8 H147 L104 40 H30 Z" fill="#ec4b3e"/>
                            <path d="M148 8 H278 L302 58 H148 Z" fill="#ffd23f"/>
                            <rect x="168" y="18" width="56" height="28" rx="4" fill="#6fd3dc"/>
                            <rect x="14" y="55" width="312" height="40" rx="8" fill="#101217"/>
                            <circle class="wheel" cx="78" cy="98" r="31" fill="#101217"/>
                            <circle class="wheel" cx="78" cy="98" r="14" fill="#8fa7ad"/>
                            <circle class="wheel" cx="254" cy="98" r="31" fill="#101217"/>
                            <circle class="wheel" cx="254" cy="98" r="14" fill="#8fa7ad"/>
                        </g>
                    </g>

                    <g transform="translate(816 535)">
                        <rect x="0" y="70" width="250" height="36" rx="8" fill="#101217"/>
                        <rect class="stripe" x="19" y="17" width="212" height="45" rx="8" fill="url(#hazardPaint)"/>
                        <path d="M42 62 V118 M207 62 V118" stroke="#ffd23f" stroke-linecap="round" stroke-width="18"/>
                        <path d="M34 118 H91 M184 118 H241" stroke="#ffd23f" stroke-linecap="round" stroke-width="18"/>
                    </g>
                </svg>
            </section>
        </div>
    </main>
</body>
</html>
