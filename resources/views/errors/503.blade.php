<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#8ec9f5">
    <title>ただいま工事中｜{{ config('app.name', 'Laravel') }}</title>
    <style>
        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background: linear-gradient(180deg, #8ec9f5 0%, #c9e7fb 55%, #eef7ff 100%);
            color: #1f2937;
            font-family: 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', 'Noto Sans JP', 'Yu Gothic UI', Meiryo, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* ---- hazard tape borders ---- */
        .tape {
            height: 26px;
            flex: none;
            background: repeating-linear-gradient(-45deg, #ffce00 0 24px, #1a1a1f 24px 48px);
            animation: tape-scroll 1.6s linear infinite;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .25);
            position: relative;
            z-index: 3;
        }

        .tape--bottom { box-shadow: 0 -2px 6px rgba(0, 0, 0, .25); }

        @keyframes tape-scroll {
            to { background-position: 67.88px 0; }
        }

        /* ---- sun ---- */
        .sun {
            position: fixed;
            top: -70px;
            right: -70px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: radial-gradient(circle at 42% 42%, #fffbe0 0%, #ffe27a 38%, rgba(255, 226, 122, 0) 56%);
            animation: sun-pulse 7s ease-in-out infinite alternate;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes sun-pulse {
            from { transform: scale(1); opacity: .9; }
            to   { transform: scale(1.08); opacity: 1; }
        }

        /* ---- copy ---- */
        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 34px 20px 0;
            position: relative;
            z-index: 1;
        }

        .badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            letter-spacing: .14em;
            color: #e2e8f0;
            background: linear-gradient(180deg, #47566b, #334155);
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 7px 30px;
            position: relative;
            box-shadow: 0 2px 0 rgba(0, 0, 0, .25);
        }

        .badge::before,
        .badge::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 7px;
            height: 7px;
            margin-top: -3.5px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #cbd5e1, #64748b);
            box-shadow: inset 0 -1px 1px rgba(0, 0, 0, .4);
        }

        .badge::before { left: 9px; }
        .badge::after  { right: 9px; }

        h1 {
            margin: .55em 0 .25em;
            font-size: clamp(2.3rem, 7vw, 4.1rem);
            font-weight: 800;
            letter-spacing: .04em;
            color: #17324d;
            text-shadow: 0 2px 0 rgba(255, 255, 255, .8);
        }

        h1 .w {
            display: inline-block;
            animation: char-wave 3s ease-in-out infinite;
            animation-delay: calc(var(--i) * .13s);
        }

        @keyframes char-wave {
            0%, 100% { transform: translateY(0); }
            12%      { transform: translateY(-7px); }
            24%      { transform: translateY(0); }
        }

        .sub {
            max-width: 36em;
            margin: 0;
            line-height: 2;
            font-size: clamp(.92rem, 2.6vw, 1.05rem);
            color: #33475c;
        }

        /* ---- progress tape ---- */
        .progress {
            width: min(460px, 84vw);
            height: 24px;
            margin-top: 30px;
            border: 3px solid #2b2b31;
            border-radius: 999px;
            background: repeating-linear-gradient(-45deg, #ffce00 0 16px, #2b2b31 16px 32px);
            animation: stripe-scroll 1.1s linear infinite;
            box-shadow: 0 3px 0 rgba(0, 0, 0, .12);
        }

        @keyframes stripe-scroll {
            to { background-position: 45.25px 0; }
        }

        .progress-label {
            margin-top: 12px;
            font-size: .92rem;
            font-weight: 600;
            color: #46596e;
        }

        .progress-label .d {
            display: inline-block;
            animation: dot-blink 1.5s infinite;
        }

        .progress-label .d:nth-child(2) { animation-delay: .25s; }
        .progress-label .d:nth-child(3) { animation-delay: .5s; }

        @keyframes dot-blink {
            0%, 60%, 100% { opacity: .15; }
            30%           { opacity: 1; }
        }

        .note {
            margin-top: 6px;
            font-size: .78rem;
            color: #7b8ba0;
        }

        /* ---- scene ---- */
        .scene {
            width: 100%;
            height: clamp(200px, calc(100vh - 560px), 504px);
            display: block;
            margin-top: auto;
            position: relative;
            z-index: 1;
            overflow: visible;
        }

        /* clouds drift across the whole sky */
        .cloud { animation: cloud-drift linear infinite; }
        .cloud--1 { animation-duration: 80s; animation-delay: -30s; }
        .cloud--2 { animation-duration: 115s; animation-delay: -80s; }

        @keyframes cloud-drift {
            from { transform: translateX(-600px); }
            to   { transform: translateX(1800px); }
        }

        /* jackhammer shake */
        .jack-tool { animation: jitter-tool .12s linear infinite; }
        .jack-body { animation: jitter-body .16s linear infinite; }

        @keyframes jitter-tool {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(2.6px); }
        }

        @keyframes jitter-body {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(1.2px); }
        }

        /* dust puffs at the jackhammer bit */
        .puff {
            transform-box: fill-box;
            transform-origin: center;
            opacity: 0;
            animation: puff-rise 1.8s ease-out infinite;
        }

        .puff--2 { animation-delay: .6s; }
        .puff--3 { animation-delay: 1.2s; }

        @keyframes puff-rise {
            0%   { transform: translate(0, 0) scale(.35); opacity: 0; }
            12%  { opacity: .75; }
            100% { transform: translate(var(--dx, 0px), -44px) scale(1.9); opacity: 0; }
        }

        /* sparks synced to the 2.2s hammer cycle (impact ~42%) */
        .spark {
            transform-box: fill-box;
            transform-origin: center;
            opacity: 0;
            animation: spark-burst 2.2s linear infinite;
        }

        @keyframes spark-burst {
            0%, 40%   { transform: scale(.3); opacity: 0; }
            44%       { transform: scale(.9); opacity: 1; }
            58%       { transform: scale(1.3); opacity: .8; }
            64%, 100% { transform: scale(1.4); opacity: 0; }
        }

        /* beacon lamps */
        .beacon { animation: beacon-pulse 1.1s ease-in-out infinite; }

        @keyframes beacon-pulse {
            0%, 100% { opacity: .35; }
            50%      { opacity: 1; }
        }

        .halo {
            transform-box: fill-box;
            transform-origin: center;
            animation: halo-ping 1.1s ease-out infinite;
        }

        @keyframes halo-ping {
            0%   { transform: scale(.6); opacity: .5; }
            100% { transform: scale(2.1); opacity: 0; }
        }

        /* wheelbarrow gorilla crossing the site */
        .walker { animation: walker-cross 26s linear infinite; animation-delay: -8s; }

        @keyframes walker-cross {
            from { transform: translate(-900px, 396px) scale(1.08); }
            to   { transform: translate(2100px, 396px) scale(1.08); }
        }

        .walker-bob { animation: walker-bob .4s ease-in-out infinite alternate; }

        @keyframes walker-bob {
            from { transform: translateY(0); }
            to   { transform: translateY(-4px); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="tape" aria-hidden="true"></div>
    <div class="sun" aria-hidden="true"></div>

    <main>
        <p class="badge">503 SERVICE UNAVAILABLE</p>
        <h1 aria-label="ただいま工事中！"><span class="w" style="--i:0">た</span><span class="w" style="--i:1">だ</span><span class="w" style="--i:2">い</span><span class="w" style="--i:3">ま</span><span class="w" style="--i:4">工</span><span class="w" style="--i:5">事</span><span class="w" style="--i:6">中</span><span class="w" style="--i:7">！</span></h1>
        <p class="sub">
            ただいまシステムのメンテナンス作業を行っております。<br>
            ご不便をおかけして申し訳ございません。ゴリラたちが全力で作業中です。<br>
            しばらく経ってから、もう一度アクセスしてください。
        </p>
        <div class="progress" role="presentation"></div>
        <p class="progress-label">作業進行中<span class="d">・</span><span class="d">・</span><span class="d">・</span></p>
        <p class="note">復旧後は自動的に再読み込みされます</p>
    </main>

    <svg id="scene" class="scene" viewBox="0 0 1200 420" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
        <defs>
            <pattern id="hz" width="14" height="14" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
                <rect width="14" height="14" fill="#ffce00"/>
                <rect width="7" height="14" fill="#1a1a1f"/>
            </pattern>
            <g id="cone">
                <ellipse cx="0" cy="0" rx="14" ry="4.5" fill="#d95f00"/>
                <polygon points="-10,0 10,0 3.5,-32 -3.5,-32" fill="#ff6a00"/>
                <polygon points="-5.9,-20 5.9,-20 7.4,-13 -7.4,-13" fill="#ffffff"/>
            </g>
            <g id="cloud-shape" fill="#ffffff" opacity=".9">
                <ellipse cx="0" cy="0" rx="46" ry="16"/>
                <ellipse cx="-32" cy="6" rx="28" ry="12"/>
                <ellipse cx="34" cy="4" rx="30" ry="13"/>
            </g>
        </defs>

        <!-- clouds -->
        <g class="cloud cloud--1"><use href="#cloud-shape" transform="translate(0,64)"/></g>
        <g class="cloud cloud--2"><use href="#cloud-shape" transform="translate(0,120) scale(.7)"/></g>

        <!-- crane (background) -->
        <g>
            <rect x="98" y="348" width="44" height="8" fill="#b98850"/>
            <rect x="112" y="128" width="16" height="224" fill="#e9b26a"/>
            <path stroke="#ffffff" stroke-opacity=".45" stroke-width="2" fill="none" d="M112 152 L128 168 M128 152 L112 168 M112 184 L128 200 M128 184 L112 200 M112 216 L128 232 M128 216 L112 232 M112 248 L128 264 M128 248 L112 264 M112 280 L128 296 M128 280 L112 296 M112 312 L128 328 M128 312 L112 328"/>
            <!-- jib sways slowly around the tower top -->
            <g>
                <animateTransform attributeName="transform" type="rotate"
                    values="-2.5 120 132; 2.5 120 132; -2.5 120 132"
                    keyTimes="0; .5; 1" calcMode="spline"
                    keySplines=".45 0 .55 1; .45 0 .55 1"
                    dur="9s" repeatCount="indefinite"/>
                <line x1="120" y1="122" x2="120" y2="92" stroke="#e9b26a" stroke-width="5"/>
                <line x1="120" y1="94" x2="298" y2="124" stroke="#d9a05c" stroke-width="2"/>
                <line x1="120" y1="94" x2="60" y2="124" stroke="#d9a05c" stroke-width="2"/>
                <rect x="54" y="122" width="252" height="9" rx="2" fill="#e9b26a"/>
                <rect x="54" y="131" width="24" height="20" fill="#c99055"/>
                <!-- pendulum: cable + hook + the payload banana -->
                <g>
                    <animateTransform attributeName="transform" type="rotate"
                        values="-6 298 126; 6 298 126; -6 298 126"
                        keyTimes="0; .5; 1" calcMode="spline"
                        keySplines=".45 0 .55 1; .45 0 .55 1"
                        dur="3.4s" repeatCount="indefinite"/>
                    <line x1="298" y1="126" x2="298" y2="196" stroke="#6b7280" stroke-width="2"/>
                    <path d="M298 196 q0 10 -7 10" stroke="#4b5563" stroke-width="3" fill="none"/>
                    <path d="M284 212 A 22 22 0 0 0 312 230" stroke="#ffd23f" stroke-width="12" stroke-linecap="round" fill="none"/>
                    <circle cx="284" cy="212" r="3" fill="#8a5a2b"/>
                    <circle cx="312" cy="230" r="3" fill="#8a5a2b"/>
                </g>
            </g>
            <!-- operator cab, gorilla inside -->
            <rect x="132" y="128" width="24" height="20" rx="3" fill="#f4c27d"/>
            <rect x="136" y="132" width="16" height="12" rx="2" fill="#bfe0ef"/>
            <circle cx="144" cy="140" r="4.5" fill="#33333b"/>
            <path d="M139.5 138 A 4.5 3.4 0 0 1 148.5 138 Z" fill="#ffce00"/>
        </g>

        <!-- ground (extends past the viewBox so letterboxed edges stay filled) -->
        <path d="M-700 352 L0 352 Q150 344 300 350 T600 348 T900 352 T1200 348 L1900 348 L1900 420 L-700 420 Z" fill="#d7a15c"/>
        <rect x="-700" y="398" width="2600" height="22" fill="#c08c4b" opacity=".8"/>
        <ellipse cx="200" cy="380" rx="60" ry="8" fill="#c08c4b" opacity=".5"/>
        <ellipse cx="750" cy="390" rx="80" ry="9" fill="#c08c4b" opacity=".5"/>
        <ellipse cx="1000" cy="378" rx="50" ry="7" fill="#c08c4b" opacity=".5"/>
        <circle cx="330" cy="392" r="4" fill="#b98850"/>
        <circle cx="690" cy="368" r="3" fill="#b98850"/>
        <circle cx="905" cy="396" r="4" fill="#b98850"/>
        <circle cx="1120" cy="384" r="3" fill="#b98850"/>

        <!-- cones -->
        <use href="#cone" transform="translate(300,378)"/>
        <use href="#cone" transform="translate(660,382)"/>
        <use href="#cone" transform="translate(1040,374)"/>

        <!-- barricade 1 with beacon -->
        <g>
            <line x1="546" y1="388" x2="556" y2="316" stroke="#7c8794" stroke-width="5"/>
            <line x1="614" y1="388" x2="604" y2="316" stroke="#7c8794" stroke-width="5"/>
            <rect x="530" y="316" width="100" height="18" fill="url(#hz)" stroke="#1a1a1f" stroke-width="2"/>
            <rect x="573" y="308" width="14" height="8" rx="2" fill="#4b5563"/>
            <circle class="halo" cx="580" cy="303" r="7" fill="#ffa02e"/>
            <path class="beacon" d="M571 308 A 9 9 0 0 1 589 308 Z" fill="#ff9d2e"/>
        </g>

        <!-- barricade 2 with beacon -->
        <g>
            <line x1="956" y1="388" x2="966" y2="316" stroke="#7c8794" stroke-width="5"/>
            <line x1="1024" y1="388" x2="1014" y2="316" stroke="#7c8794" stroke-width="5"/>
            <rect x="940" y="316" width="100" height="18" fill="url(#hz)" stroke="#1a1a1f" stroke-width="2"/>
            <rect x="983" y="308" width="14" height="8" rx="2" fill="#4b5563"/>
            <circle class="halo" cx="990" cy="303" r="7" fill="#ffa02e" style="animation-delay:.55s"/>
            <path class="beacon" d="M981 308 A 9 9 0 0 1 999 308 Z" fill="#ff9d2e" style="animation-delay:.55s"/>
        </g>

        <!-- signboard -->
        <g>
            <rect x="1108" y="300" width="10" height="88" fill="#8a6a4a"/>
            <rect x="1052" y="238" width="122" height="72" rx="10" fill="#fffdf5" stroke="#ff7a1a" stroke-width="5"/>
            <text x="1113" y="279" text-anchor="middle" font-size="30" font-weight="800" fill="#e15200">工事中</text>
            <text x="1113" y="300" text-anchor="middle" font-size="13" fill="#666666">ご安全に！</text>
        </g>

        <!-- ===== gorilla 2: jackhammer (faces right) ===== -->
        <g>
            <!-- cracked ground + hole under the bit -->
            <ellipse cx="466" cy="388" rx="26" ry="7" fill="#8a6a42"/>
            <path d="M468 384 L446 380 M468 384 L488 378 M468 384 L462 394 M468 384 L482 392" stroke="#ab8050" stroke-width="2" fill="none"/>
            <polygon points="436,380 446,374 452,382" fill="#9ca3af"/>
            <polygon points="484,376 494,380 488,386" fill="#8b939e"/>

            <g class="jack-body">
                <!-- legs -->
                <path d="M382 330 L372 384" stroke="#33333b" stroke-width="18" stroke-linecap="round"/>
                <path d="M414 330 L424 384" stroke="#33333b" stroke-width="18" stroke-linecap="round"/>
                <ellipse cx="368" cy="387" rx="13" ry="6.5" fill="#26262d"/>
                <ellipse cx="428" cy="387" rx="13" ry="6.5" fill="#26262d"/>
                <!-- body + vest -->
                <ellipse cx="398" cy="296" rx="48" ry="54" fill="#33333b"/>
                <rect x="362" y="258" width="74" height="76" rx="18" fill="#ff7d1f" stroke="#e05e00" stroke-width="2"/>
                <rect x="376" y="258" width="9" height="76" fill="#ffe14d"/>
                <rect x="410" y="258" width="9" height="76" fill="#ffe14d"/>
                <!-- head -->
                <circle cx="395" cy="210" r="8" fill="#26262d"/>
                <circle cx="420" cy="216" r="27" fill="#33333b"/>
                <ellipse cx="428" cy="224" rx="18" ry="14" fill="#b99a77"/>
                <ellipse cx="433" cy="230" rx="12" ry="8.5" fill="#cbb08c"/>
                <ellipse cx="429" cy="229" rx="2" ry="1.5" fill="#5a4632"/>
                <ellipse cx="437" cy="228" rx="2" ry="1.5" fill="#5a4632"/>
                <ellipse cx="432" cy="240" rx="5.5" ry="4" fill="#4a3428"/>
                <rect x="406" y="204" width="42" height="12" rx="6" fill="#16161c"/>
                <rect x="436" y="206" width="8" height="3" rx="1.5" fill="#ffffff" opacity=".6"/>
                <path d="M396 204 Q420 176 444 204 Z" fill="#ffce00"/>
                <rect x="390" y="201" width="62" height="8" rx="4" fill="#eab308"/>
                <rect x="414" y="184" width="12" height="9" rx="3.5" fill="#ffce00"/>
            </g>

            <g class="jack-tool">
                <!-- jackhammer -->
                <rect x="460" y="294" width="12" height="58" fill="#4b5563"/>
                <polygon points="462,352 470,352 467,386 465,386" fill="#9aa3ad"/>
                <rect x="452" y="302" width="28" height="26" rx="5" fill="#ff7d1f" stroke="#e05e00" stroke-width="2"/>
                <rect x="440" y="286" width="52" height="9" rx="4.5" fill="#374151"/>
                <!-- arms gripping the handle -->
                <path d="M400 264 Q420 274 446 288" stroke="#26262d" stroke-width="16" stroke-linecap="round" fill="none"/>
                <path d="M430 258 Q462 268 486 288" stroke="#33333b" stroke-width="17" stroke-linecap="round" fill="none"/>
                <circle cx="446" cy="290" r="10" fill="#26262d"/>
                <circle cx="486" cy="290" r="10" fill="#33333b"/>
            </g>

            <!-- dust puffs -->
            <circle class="puff" cx="468" cy="380" r="7" fill="#d9c4a3" style="--dx:-12px"/>
            <circle class="puff puff--2" cx="468" cy="380" r="7" fill="#d9c4a3" style="--dx:3px"/>
            <circle class="puff puff--3" cx="468" cy="380" r="7" fill="#d9c4a3" style="--dx:13px"/>
        </g>

        <!-- ===== gorilla 1: sledgehammer onto the 503 plate (faces left) ===== -->
        <g>
            <!-- steel plate -->
            <g transform="rotate(-3 751 360)">
                <rect x="705" y="338" width="92" height="44" rx="6" fill="#64748b" stroke="#475569" stroke-width="2"/>
                <circle cx="713" cy="346" r="3" fill="#cbd5e1"/>
                <circle cx="789" cy="346" r="3" fill="#cbd5e1"/>
                <circle cx="713" cy="374" r="3" fill="#cbd5e1"/>
                <circle cx="789" cy="374" r="3" fill="#cbd5e1"/>
                <text x="751" y="370" text-anchor="middle" font-size="27" font-weight="800" letter-spacing="2" fill="#f1f5f9">503</text>
            </g>

            <!-- far arm + far leg -->
            <path d="M898 262 Q912 300 906 334" stroke="#26262d" stroke-width="19" stroke-linecap="round" fill="none"/>
            <circle cx="906" cy="336" r="10" fill="#26262d"/>
            <path d="M890 330 L896 382" stroke="#26262d" stroke-width="20" stroke-linecap="round"/>
            <ellipse cx="900" cy="386" rx="15" ry="8" fill="#26262d"/>
            <!-- near leg -->
            <path d="M850 330 L842 382" stroke="#33333b" stroke-width="20" stroke-linecap="round"/>
            <ellipse cx="838" cy="386" rx="15" ry="8" fill="#33333b"/>
            <!-- body + vest -->
            <ellipse cx="870" cy="295" rx="50" ry="56" fill="#33333b"/>
            <rect x="832" y="255" width="76" height="78" rx="18" fill="#ff7d1f" stroke="#e05e00" stroke-width="2"/>
            <rect x="846" y="255" width="9" height="78" fill="#ffe14d"/>
            <rect x="880" y="255" width="9" height="78" fill="#ffe14d"/>
            <!-- swinging arm + sledgehammer (pivot = shoulder 838,258) -->
            <g>
                <animateTransform attributeName="transform" type="rotate"
                    values="0 838 258; 14 838 258; -78 838 258; -78 838 258; 0 838 258"
                    keyTimes="0; .3; .42; .6; 1" calcMode="spline"
                    keySplines=".4 0 .6 1; .6 0 .9 .6; 0 0 1 1; .3 0 .4 1"
                    dur="2.2s" repeatCount="indefinite"/>
                <path d="M838 258 C820 244 804 232 792 224" stroke="#33333b" stroke-width="19" stroke-linecap="round" fill="none"/>
                <circle cx="792" cy="224" r="11" fill="#33333b"/>
                <line x1="792" y1="224" x2="766" y2="180" stroke="#9a6a33" stroke-width="7" stroke-linecap="round"/>
                <g transform="translate(766,180) rotate(-31)">
                    <rect x="-24" y="-13" width="48" height="26" rx="5" fill="#5a6270"/>
                    <rect x="-24" y="-13" width="10" height="26" rx="5" fill="#788292"/>
                </g>
            </g>
            <!-- head -->
            <circle cx="880" cy="204" r="9" fill="#26262d"/>
            <circle cx="852" cy="210" r="28" fill="#33333b"/>
            <ellipse cx="844" cy="218" rx="19" ry="15" fill="#b99a77"/>
            <ellipse cx="839" cy="225" rx="13" ry="9" fill="#cbb08c"/>
            <ellipse cx="835" cy="224" rx="2" ry="1.5" fill="#5a4632"/>
            <ellipse cx="843" cy="223" rx="2" ry="1.5" fill="#5a4632"/>
            <rect x="830" y="232" width="14" height="3" rx="1.5" fill="#5a4632"/>
            <rect x="822" y="198" width="42" height="12" rx="6" fill="#16161c"/>
            <rect x="826" y="200" width="8" height="3" rx="1.5" fill="#ffffff" opacity=".6"/>
            <path d="M826 198 Q852 170 878 198 Z" fill="#ffce00"/>
            <rect x="818" y="195" width="68" height="8" rx="4" fill="#eab308"/>
            <rect x="846" y="178" width="12" height="9" rx="3.5" fill="#ffce00"/>

            <!-- impact sparks -->
            <g class="spark" stroke="#ffd23f" stroke-width="3" stroke-linecap="round">
                <g transform="translate(752,330)">
                    <line x1="3" y1="-3" x2="12" y2="-12"/>
                    <line x1="0" y1="-5" x2="0" y2="-16"/>
                    <line x1="-3" y1="-3" x2="-13" y2="-11"/>
                    <line x1="5" y1="0" x2="16" y2="-2"/>
                    <line x1="-5" y1="0" x2="-16" y2="-1"/>
                    <circle cx="10" cy="-14" r="2" fill="#ffffff" stroke="none"/>
                    <circle cx="-12" cy="-13" r="2" fill="#ffffff" stroke="none"/>
                </g>
            </g>
        </g>

        <!-- ===== gorilla 3: wheelbarrow full of bananas, crossing the site ===== -->
        <g class="walker">
            <g class="walker-bob">
                <!-- back leg -->
                <g>
                    <animateTransform attributeName="transform" type="rotate"
                        values="-14 -44 -48; 14 -44 -48; -14 -44 -48"
                        keyTimes="0; .5; 1" calcMode="spline"
                        keySplines=".45 0 .55 1; .45 0 .55 1"
                        dur=".8s" repeatCount="indefinite"/>
                    <path d="M-44 -48 L-52 -6" stroke="#26262d" stroke-width="17" stroke-linecap="round"/>
                    <ellipse cx="-54" cy="-4" rx="12" ry="5.5" fill="#26262d"/>
                </g>
                <!-- body + vest -->
                <ellipse cx="-30" cy="-80" rx="44" ry="48" fill="#33333b"/>
                <rect x="-58" y="-114" width="62" height="60" rx="15" fill="#ff7d1f" stroke="#e05e00" stroke-width="2"/>
                <rect x="-46" y="-114" width="8" height="60" fill="#ffe14d"/>
                <rect x="-14" y="-114" width="8" height="60" fill="#ffe14d"/>
                <!-- front leg -->
                <g>
                    <animateTransform attributeName="transform" type="rotate"
                        values="14 -16 -48; -14 -16 -48; 14 -16 -48"
                        keyTimes="0; .5; 1" calcMode="spline"
                        keySplines=".45 0 .55 1; .45 0 .55 1"
                        dur=".8s" repeatCount="indefinite"/>
                    <path d="M-16 -48 L-8 -6" stroke="#33333b" stroke-width="17" stroke-linecap="round"/>
                    <ellipse cx="-6" cy="-4" rx="12" ry="5.5" fill="#33333b"/>
                </g>
                <!-- arms to the handles -->
                <path d="M-26 -92 Q-14 -70 0 -54" stroke="#26262d" stroke-width="15" stroke-linecap="round" fill="none"/>
                <path d="M-12 -98 Q0 -76 8 -60" stroke="#33333b" stroke-width="16" stroke-linecap="round" fill="none"/>
                <circle cx="1" cy="-55" r="9" fill="#26262d"/>
                <circle cx="9" cy="-61" r="9" fill="#33333b"/>
                <!-- head -->
                <circle cx="-14" cy="-130" r="8" fill="#26262d"/>
                <circle cx="6" cy="-126" r="24" fill="#33333b"/>
                <ellipse cx="14" cy="-120" rx="16" ry="13" fill="#b99a77"/>
                <ellipse cx="18" cy="-115" rx="11" ry="8" fill="#cbb08c"/>
                <ellipse cx="14" cy="-116" rx="2" ry="1.5" fill="#5a4632"/>
                <ellipse cx="22" cy="-115" rx="2" ry="1.5" fill="#5a4632"/>
                <path d="M10 -108 Q18 -102 26 -108" stroke="#4a3428" stroke-width="2" fill="none"/>
                <rect x="-2" y="-138" width="36" height="11" rx="5.5" fill="#16161c"/>
                <rect x="22" y="-136" width="7" height="3" rx="1.5" fill="#ffffff" opacity=".6"/>
                <path d="M-8 -136 Q6 -160 28 -136 Z" fill="#ffce00"/>
                <rect x="-14" y="-139" width="48" height="7" rx="3.5" fill="#eab308"/>
                <rect x="1" y="-153" width="11" height="9" rx="3" fill="#ffce00"/>
            </g>

            <!-- wheelbarrow -->
            <line x1="36" y1="-42" x2="4" y2="-58" stroke="#8a5a2b" stroke-width="6" stroke-linecap="round"/>
            <path d="M46 -50 A 12 12 0 0 1 64 -56" stroke="#ffd23f" stroke-width="8" stroke-linecap="round" fill="none"/>
            <path d="M66 -54 A 12 12 0 0 1 84 -58" stroke="#ffd23f" stroke-width="8" stroke-linecap="round" fill="none"/>
            <path d="M78 -48 A 12 12 0 0 1 96 -52" stroke="#ffd23f" stroke-width="8" stroke-linecap="round" fill="none"/>
            <polygon points="34,-46 102,-46 90,-16 46,-16" fill="#2f855a" stroke="#256b4e" stroke-width="2"/>
            <line x1="50" y1="-16" x2="46" y2="-2" stroke="#256b4e" stroke-width="5" stroke-linecap="round"/>
            <g>
                <animateTransform attributeName="transform" type="rotate"
                    from="0 96 -13" to="360 96 -13"
                    dur=".9s" repeatCount="indefinite"/>
                <circle cx="96" cy="-13" r="13" fill="#16161c"/>
                <line x1="96" y1="-24" x2="96" y2="-2" stroke="#9ca3af" stroke-width="2.5"/>
                <line x1="85" y1="-13" x2="107" y2="-13" stroke="#9ca3af" stroke-width="2.5"/>
                <circle cx="96" cy="-13" r="4" fill="#9ca3af"/>
            </g>
        </g>
    </svg>

    <div class="tape tape--bottom" aria-hidden="true"></div>

    <script>
        (function () {
            var scene = document.getElementById('scene');

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches && scene && scene.pauseAnimations) {
                scene.pauseAnimations();
            }

            function check() {
                fetch(window.location.href, { method: 'HEAD', cache: 'no-store' })
                    .then(function (response) { if (response.ok) { window.location.reload(); } })
                    .catch(function () {})
                    .finally(function () { setTimeout(check, 30000); });
            }

            setTimeout(check, 30000);
        })();
    </script>
</body>
</html>
