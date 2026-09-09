<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MEDICARE HOMEPAGE
|--------------------------------------------------------------------------
| Public landing page.
|--------------------------------------------------------------------------
*/

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="MediCare healthcare management platform developed by Team B2."
    >

    <meta
        name="theme-color"
        content="#031b2d"
        id="themeColorMeta"
    >

    <title>
        MediCare | Your Health, Our Priority
    </title>


    <!-- =========================================================
         GOOGLE FONT
    ========================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- =========================================================
         FONT AWESOME
    ========================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- =========================================================
         MAIN CSS
    ========================================================== -->

    <link
        rel="stylesheet"
        href="style.css?v=20260817"
    >


    <!-- =========================================================
         HOMEPAGE-SPECIFIC CSS
    ========================================================== -->

    <style>

        /* =====================================================
           SHARED THEME SYSTEM
        ===================================================== */

        :root {

            color-scheme: dark;

            --bg-main:
                #031b2d;

            --bg-secondary:
                #05263d;

            --bg-card:
                #082f46;

            --bg-card-dark:
                #06283f;

            --bg-soft:
                rgba(
                    255,
                    255,
                    255,
                    0.025
                );

            --text-main:
                #e1edf3;

            --text-heading:
                #ffffff;

            --text-soft:
                #b7ced8;

            --text-muted:
                #8fa8b8;

            --text-dark-muted:
                #678392;

            --border:
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            --border-soft:
                rgba(
                    255,
                    255,
                    255,
                    0.055
                );

            --primary:
                #10c7b0;

            --primary-dark:
                #0a9e90;

            --blue:
                #2c8cff;

            --green:
                #55d890;

            --orange:
                #ffb84d;

            --red:
                #ff7279;

            --purple:
                #a984ff;

            --shadow:
                0 25px 70px
                rgba(
                    0,
                    0,
                    0,
                    0.18
                );

            --header-bg:
                rgba(
                    3,
                    27,
                    44,
                    0.86
                );

            --section-dark-bg:
                #041b2b;

            --footer-bg:
                #021522;

            --theme-toggle-bg:
                rgba(
                    255,
                    255,
                    255,
                    0.045
                );

            --theme-toggle-border:
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

        }


        /* =====================================================
           LIGHT THEME
        ===================================================== */

        html[data-theme="light"] {

            color-scheme: light;

            --bg-main:
                #f4f8fb;

            --bg-secondary:
                #eaf1f6;

            --bg-card:
                #ffffff;

            --bg-card-dark:
                #f0f5f8;

            --bg-soft:
                rgba(
                    4,
                    27,
                    45,
                    0.035
                );

            --text-main:
                #243447;

            --text-heading:
                #102a43;

            --text-soft:
                #587080;

            --text-muted:
                #647789;

            --text-dark-muted:
                #718391;

            --border:
                rgba(
                    14,
                    49,
                    71,
                    0.12
                );

            --border-soft:
                rgba(
                    14,
                    49,
                    71,
                    0.08
                );

            --primary:
                #0caa97;

            --primary-dark:
                #078c7d;

            --blue:
                #2075d7;

            --green:
                #299b63;

            --orange:
                #d58a14;

            --red:
                #d74750;

            --purple:
                #7653cf;

            --shadow:
                0 20px 55px
                rgba(
                    25,
                    62,
                    87,
                    0.10
                );

            --header-bg:
                rgba(
                    255,
                    255,
                    255,
                    0.92
                );

            --section-dark-bg:
                #edf4f7;

            --footer-bg:
                #eaf1f5;

            --theme-toggle-bg:
                rgba(
                    11,
                    57,
                    83,
                    0.055
                );

            --theme-toggle-border:
                rgba(
                    11,
                    57,
                    83,
                    0.12
                );

        }


        /* =====================================================
           THEME TOGGLE
        ===================================================== */

        .theme-toggle {

            width:
                42px;

            height:
                42px;

            flex-shrink:
                0;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                1px solid
                var(--theme-toggle-border);

            border-radius:
                10px;

            background:
                var(--theme-toggle-bg);

            color:
                var(--text-muted);

            cursor:
                pointer;

            transition:
                color .2s ease,
                background .2s ease,
                border-color .2s ease,
                transform .2s ease;
        }


        .theme-toggle:hover {

            color:
                var(--primary);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.30
                );

            transform:
                translateY(-1px);
        }


        .theme-toggle i {

            font-size:
                14px;
        }


        /* =====================================================
           MOBILE HEADER ACTIONS
        ===================================================== */

        .mobile-header-actions {

            display:
                none;

            align-items:
                center;

            justify-content:
                flex-end;

            gap:
                8px;

            flex-shrink:
                0;
        }


        /* =====================================================
           MOBILE MENU BUTTON
        ===================================================== */

        .mobile-menu-btn {

            display:
                none;

            width:
                42px;

            height:
                42px;

            border:
                1px solid
                var(--border);

            border-radius:
                10px;

            background:
                transparent;

            color:
                var(--text-heading);

            font-size:
                18px;

            cursor:
                pointer;

            place-items:
                center;

            flex-shrink:
                0;

            transition:
                color .2s ease,
                background .2s ease,
                border-color .2s ease,
                transform .2s ease;
        }


        .mobile-menu-btn:hover {

            color:
                var(--primary);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.30
                );

            transform:
                translateY(-1px);
        }


        /* =====================================================
           GLOBAL THEME TRANSITIONS
        ===================================================== */

        body,
        .site-header,
        .main-nav,
        .phone-link,
        .team-b2-section,
        .team-b2-card,
        .hero-feature-panel,
        .hero-feature-item,
        .section-dark,
        .appointment-box,
        .about-card,
        .about-image,
        .about-content,
        .cta-section,
        .footer,
        .footer-column,
        .footer-brand,
        .social-links a,
        .about-card .btn {

            transition:
                background .25s ease,
                background-color .25s ease,
                border-color .25s ease,
                color .25s ease,
                box-shadow .25s ease;
        }


        /* =====================================================
           LIGHT THEME - GLOBAL HOMEPAGE OVERRIDES
        ===================================================== */

        html[data-theme="light"] body {

            background:
                var(--bg-main);

            color:
                var(--text-main);
        }


        html[data-theme="light"]
        .site-header {

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.94
                );

            border-bottom-color:
                var(--border);
        }


        html[data-theme="light"]
        .main-nav a {

            color:
                #536474;
        }


        html[data-theme="light"]
        .main-nav a:hover,
        html[data-theme="light"]
        .main-nav a.active {

            color:
                var(--primary);
        }


        html[data-theme="light"]
        .phone-link {

            color:
                #526576;
        }


        html[data-theme="light"]
        .phone-link i {

            color:
                var(--primary);
        }


        html[data-theme="light"]
        .hero {

            background:
                linear-gradient(
                    135deg,
                    #dbeff1,
                    #eaf4f7 55%,
                    #f5f9fb
                );
        }


        html[data-theme="light"]
        .hero-overlay {

            opacity:
                0.15;
        }


        html[data-theme="light"]
        .hero-copy h1 {

            color:
                var(--text-heading);
        }


        html[data-theme="light"]
        .hero-copy h1 span {

            color:
                var(--primary);
        }


        html[data-theme="light"]
        .hero-copy p {

            color:
                #617482;
        }


        html[data-theme="light"]
        .hero-line span {

            background:
                rgba(
                    18,
                    59,
                    82,
                    0.16
                );
        }


        /* =====================================================
           LIGHT THEME - HERO FEATURE PANEL
        ===================================================== */

        html[data-theme="light"]
        .hero-feature-panel {

            background:
                linear-gradient(
                    145deg,
                    rgba(
                        255,
                        255,
                        255,
                        0.98
                    ),
                    rgba(
                        244,
                        249,
                        252,
                        0.99
                    )
                );

            border-color:
                rgba(
                    15,
                    58,
                    82,
                    0.12
                );

            box-shadow:
                var(--shadow);
        }


        html[data-theme="light"]
        .hero-feature-panel h2 {

            color:
                var(--text-heading);
        }


        html[data-theme="light"]
        .hero-feature-item {

            background:
                rgba(
                    6,
                    45,
                    68,
                    0.025
                );

            border-color:
                var(--border-soft);
        }


        html[data-theme="light"]
        .hero-feature-item h3 {

            color:
                #20384a;
        }


        html[data-theme="light"]
        .hero-feature-item p,
        html[data-theme="light"]
        .hero-feature-footer {

            color:
                #718392;
        }


        /* =====================================================
           TEAM LIGHT THEME
        ===================================================== */

        html[data-theme="light"]
        .team-b2-section {

            background:
                linear-gradient(
                    180deg,
                    #f0f6f8,
                    #e8f1f5
                );

            border-top-color:
                rgba(
                    10,
                    50,
                    70,
                    0.08
                );

            border-bottom-color:
                rgba(
                    10,
                    50,
                    70,
                    0.08
                );
        }


        html[data-theme="light"]
        .team-heading h2 {

            color:
                var(--text-heading);
        }


        html[data-theme="light"]
        .team-heading p {

            color:
                #6a7c8a;
        }


        html[data-theme="light"]
        .team-b2-card {

            background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #f4f8fa
                );

            border-color:
                rgba(
                    13,
                    54,
                    77,
                    0.11
                );

            box-shadow:
                0 12px 35px
                rgba(
                    25,
                    62,
                    87,
                    0.06
                );
        }


        html[data-theme="light"]
        .team-b2-card:hover {

            border-color:
                rgba(
                    12,
                    170,
                    151,
                    0.35
                );

            box-shadow:
                0 18px 45px
                rgba(
                    25,
                    62,
                    87,
                    0.10
                );
        }


        html[data-theme="light"]
        .team-b2-number {

            color:
                rgba(
                    20,
                    56,
                    76,
                    0.20
                );
        }


        html[data-theme="light"]
        .team-b2-card h3 {

            color:
                #20394a;
        }


        html[data-theme="light"]
        .team-b2-card p {

            color:
                #70818f;
        }


        /* =====================================================
           APPOINTMENT SECTION LIGHT THEME
        ===================================================== */

        html[data-theme="light"]
        .section-dark {

            background:
                var(--section-dark-bg);
        }


        html[data-theme="light"]
        .appointment-box {

            color:
                #243447;

            background:
                transparent !important;

            background-image:
                none !important;
        }


        html[data-theme="light"]
        .appointment-box h2 {

            color:
                var(--text-heading);
        }


        html[data-theme="light"]
        .appointment-box p {

            color:
                #687a88;
        }


        /* =====================================================
           BOOK A VISIT BUTTONS
        ===================================================== */

        html[data-theme="light"]
        .appointment-box .btn-dark {

            color:
                #ffffff !important;

            background:
                #123b52 !important;

            border:
                1px solid
                #123b52 !important;

            box-shadow:
                0 8px 20px
                rgba(
                    18,
                    59,
                    82,
                    0.14
                );
        }


        html[data-theme="light"]
        .appointment-box .btn-dark:hover {

            color:
                #ffffff !important;

            background:
                #0d2e42 !important;

            border-color:
                #0d2e42 !important;
        }


        html[data-theme="light"]
        .appointment-box .btn-outline-light {

            color:
                #123b52 !important;

            background:
                #ffffff !important;

            border:
                1px solid
                rgba(
                    18,
                    59,
                    82,
                    0.20
                ) !important;

            box-shadow:
                0 8px 20px
                rgba(
                    25,
                    62,
                    87,
                    0.06
                );
        }


        html[data-theme="light"]
        .appointment-box .btn-outline-light:hover {

            color:
                #ffffff !important;

            background:
                var(--primary) !important;

            border-color:
                var(--primary) !important;
        }


        /* =====================================================
           MEDICARE PLATFORM - COMPLETE WHITE CARD
        ===================================================== */

        html[data-theme="light"]
        .about-card {

            background:
                #ffffff !important;

            background-image:
                none !important;

            border:
                1px solid
                rgba(
                    15,
                    58,
                    82,
                    0.16
                ) !important;

            box-shadow:
                0 20px 50px
                rgba(
                    25,
                    62,
                    87,
                    0.12
                ) !important;

            color:
                #243447 !important;

            overflow:
                hidden !important;
        }


        /* =====================================================
           ENTIRE ABOUT CARD CHILDREN WHITE
        ===================================================== */

        html[data-theme="light"]
        .about-card > * {

            background-color:
                #ffffff !important;

            background-image:
                none !important;
        }


        /* =====================================================
           ABOUT IMAGE
        ===================================================== */

        html[data-theme="light"]
        .about-card .about-image {

            background:
                #ffffff !important;

            background-color:
                #ffffff !important;

            background-image:
                none !important;

            border:
                none !important;

            box-shadow:
                none !important;
        }


        /* =====================================================
           INLINE IMAGE DIV
        ===================================================== */

        html[data-theme="light"]
        .about-card .about-image > div {

            width:
                100% !important;

            height:
                100% !important;

            background:
                #ffffff !important;

            background-color:
                #ffffff !important;

            background-image:
                none !important;

            border:
                none !important;

            box-shadow:
                none !important;
        }


        /* =====================================================
           ABOUT IMAGE PSEUDO ELEMENTS
        ===================================================== */

        html[data-theme="light"]
        .about-card .about-image::before,
        html[data-theme="light"]
        .about-card .about-image::after,
        html[data-theme="light"]
        .about-card .about-image > div::before,
        html[data-theme="light"]
        .about-card .about-image > div::after {

            background:
                #ffffff !important;

            background-color:
                #ffffff !important;

            background-image:
                none !important;

            border-color:
                transparent !important;

            box-shadow:
                none !important;
        }


        /* =====================================================
           ABOUT CONTENT
        ===================================================== */

        html[data-theme="light"]
        .about-card .about-content {

            background:
                #ffffff !important;

            background-color:
                #ffffff !important;

            background-image:
                none !important;

            color:
                #243447 !important;

            border:
                none !important;

            box-shadow:
                none !important;
        }


        /* =====================================================
           PLATFORM KICKER
        ===================================================== */

        html[data-theme="light"]
        .about-card
        .about-content
        .section-kicker {

            color:
                #087c70 !important;
        }


        /* =====================================================
           PLATFORM HEADING
        ===================================================== */

        html[data-theme="light"]
        .about-card
        .about-content
        h2 {

            color:
                #102a43 !important;
        }


        /* =====================================================
           PLATFORM DESCRIPTION
        ===================================================== */

        html[data-theme="light"]
        .about-card
        .about-content
        p {

            color:
                #526878 !important;
        }


        /* =====================================================
           GET STARTED BUTTON
        ===================================================== */

        html[data-theme="light"]
        .about-card
        .about-content
        .btn-outline-light {

            color:
                #123b52 !important;

            background:
                #ffffff !important;

            background-color:
                #ffffff !important;

            background-image:
                none !important;

            border:
                1px solid
                rgba(
                    12,
                    124,
                    112,
                    0.30
                ) !important;

            box-shadow:
                0 8px 20px
                rgba(
                    25,
                    62,
                    87,
                    0.08
                ) !important;
        }


        /* =====================================================
           GET STARTED HOVER
        ===================================================== */

        html[data-theme="light"]
        .about-card
        .about-content
        .btn-outline-light:hover {

            color:
                #ffffff !important;

            background:
                #0caa97 !important;

            background-color:
                #0caa97 !important;

            background-image:
                none !important;

            border-color:
                #0caa97 !important;

            box-shadow:
                0 10px 25px
                rgba(
                    12,
                    170,
                    151,
                    0.18
                ) !important;
        }


        /* =====================================================
           BUTTON ICON
        ===================================================== */

        html[data-theme="light"]
        .about-card
        .about-content
        .btn-outline-light i {

            color:
                currentColor !important;
        }


        /* =====================================================
           CTA LIGHT THEME
        ===================================================== */

        html[data-theme="light"]
        .cta-section {

            background:
                linear-gradient(
                    135deg,
                    #d9eff0,
                    #e5f0f7
                );
        }


        html[data-theme="light"]
        .cta-section h2 {

            color:
                var(--text-heading);
        }


        html[data-theme="light"]
        .cta-section p {

            color:
                #627482;
        }


        /* =====================================================
           FOOTER LIGHT THEME
        ===================================================== */

        html[data-theme="light"]
        .footer {

            background:
                var(--footer-bg);
        }


        html[data-theme="light"]
        .footer-brand p,
        html[data-theme="light"]
        .footer-column a,
        html[data-theme="light"]
        .footer-column span {

            color:
                #687a88;
        }


        html[data-theme="light"]
        .footer-column a:hover {

            color:
                var(--primary);
        }


        html[data-theme="light"]
        .social-links a {

            background:
                rgba(
                    8,
                    47,
                    70,
                    0.05
                );

            border-color:
                var(--border);

            color:
                #617482;
        }


        html[data-theme="light"]
        .footer-bottom {

            border-top-color:
                var(--border);
        }


        html[data-theme="light"]
        .footer-bottom-inner p,
        html[data-theme="light"]
        .footer-bottom-inner a {

            color:
                #738391;
        }


        /* =====================================================
           SCHOOL AFFILIATION
        ===================================================== */

        .school-affiliation {

            position:
                relative;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                14px;

            padding:
                24px 20px;

            border-top:
                1px solid
                var(--border-soft);

            background:
                var(--bg-main);

            text-align:
                left;

            transition:
                background .25s ease,
                border-color .25s ease,
                color .25s ease;
        }


        .school-affiliation-logo {

            width:
                48px;

            height:
                48px;

            flex-shrink:
                0;

            display:
                block;

            object-fit:
                contain;

            border-radius:
                50%;

            background:
                #ffffff;

            border:
                1px solid
                rgba(14, 49, 71, .12);

            padding:
                2px;

            box-shadow:
                0 6px 18px
                rgba(25, 62, 87, .08);
        }


        .school-affiliation-copy {

            min-width:
                0;
        }


        .school-affiliation-label {

            display:
                block;

            margin-bottom:
                3px;

            color:
                var(--primary);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                1.1px;

            text-transform:
                uppercase;
        }


        .school-affiliation-name {

            display:
                block;

            color:
                var(--text-heading);

            font-size:
                13px;

            font-weight:
                800;

            line-height:
                1.35;
        }


        .school-affiliation-department {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--text-muted);

            font-size:
                9px;

            line-height:
                1.5;
        }


        html[data-theme="light"]
        .school-affiliation {

            background:
                #f4f8fb;

            border-top-color:
                rgba(14, 49, 71, .10);
        }


        html[data-theme="light"]
        .school-affiliation-logo {

            border-color:
                rgba(14, 49, 71, .10);

            box-shadow:
                0 6px 18px
                rgba(25, 62, 87, .06);
        }


        @media (max-width: 600px) {

            .school-affiliation {

                justify-content:
                    flex-start;

                gap:
                    11px;

                padding:
                    20px 14px;

            }


            .school-affiliation-logo {

                width:
                    42px;

                height:
                    42px;

            }


            .school-affiliation-name {

                font-size:
                    11px;

            }


            .school-affiliation-department {

                font-size:
                    8px;

            }

        }


        /* =====================================================
           TEAM B2 SECTION
        ===================================================== */

        .team-b2-section {

            position:
                relative;

            padding:
                100px 0;

            background:
                linear-gradient(
                    180deg,
                    rgba(
                        3,
                        20,
                        32,
                        0.55
                    ),
                    rgba(
                        4,
                        28,
                        43,
                        0.96
                    )
                );

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.04
                );

            border-bottom:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.04
                );

            overflow:
                hidden;
        }


        .team-b2-section::before {

            content:
                "";

            position:
                absolute;

            width:
                420px;

            height:
                420px;

            top:
                -210px;

            right:
                -160px;

            border-radius:
                50%;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );

            box-shadow:
                0 0 0 45px
                rgba(
                    16,
                    199,
                    176,
                    0.012
                ),
                0 0 0 90px
                rgba(
                    16,
                    199,
                    176,
                    0.008
                );

            pointer-events:
                none;
        }


        .team-heading {

            position:
                relative;

            z-index:
                2;

            max-width:
                720px;

            margin:
                0 auto 45px;

            text-align:
                center;
        }


        .team-heading .section-kicker {

            margin-bottom:
                10px;
        }


        .team-heading h2 {

            color:
                var(--white);

            font-size:
                clamp(
                    34px,
                    4vw,
                    48px
                );

            line-height:
                1.05;

            letter-spacing:
                -1.7px;
        }


        .team-heading p {

            margin-top:
                12px;

            color:
                var(--muted);

            font-size:
                14px;

            line-height:
                1.7;
        }


        .team-b2-grid {

            position:
                relative;

            z-index:
                2;

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                16px;
        }


        .team-b2-card {

            position:
                relative;

            min-height:
                205px;

            padding:
                24px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.09
                );

            border-radius:
                17px;

            background:
                linear-gradient(
                    145deg,
                    rgba(
                        8,
                        47,
                        70,
                        0.96
                    ),
                    rgba(
                        5,
                        31,
                        48,
                        0.98
                    )
                );

            transition:
                transform .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        .team-b2-card:hover {

            transform:
                translateY(-4px);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.30
                );

            box-shadow:
                0 18px 45px
                rgba(
                    0,
                    0,
                    0,
                    0.20
                );
        }


        .team-b2-number {

            position:
                absolute;

            top:
                19px;

            right:
                20px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    0.18
                );

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                1px;
        }


        .team-b2-icon {

            width:
                48px;

            height:
                48px;

            display:
                grid;

            place-items:
                center;

            margin-bottom:
                18px;

            border-radius:
                13px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.13
                );

            font-size:
                18px;
        }


        .team-member-photo {

            overflow:
                hidden;

            padding:
                0;
        }


        .team-member-photo img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;

            border-radius:
                12px;

            display:
                block;
        }


        .team-b2-card h3 {

            max-width:
                235px;

            color:
                #e8f4f7;

            font-size:
                15px;

            line-height:
                1.35;
        }


        .team-b2-role {

            display:
                block;

            margin-top:
                7px;

            color:
                var(--primary);

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                .7px;

            text-transform:
                uppercase;
        }


        .team-b2-card p {

            margin-top:
                10px;

            color:
                #7793a1;

            font-size:
                11px;

            line-height:
                1.6;
        }


        /* =====================================================
           HERO FEATURE PANEL
        ===================================================== */

        .hero-feature-panel {

            position:
                relative;

            z-index:
                2;

            width:
                100%;

            padding:
                24px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            border-radius:
                20px;

            background:
                linear-gradient(
                    145deg,
                    rgba(
                        8,
                        47,
                        70,
                        0.96
                    ),
                    rgba(
                        5,
                        31,
                        48,
                        0.98
                    )
                );

            box-shadow:
                0 30px 80px
                rgba(
                    0,
                    0,
                    0,
                    0.24
                );
        }


        .hero-feature-heading {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            margin-bottom:
                16px;

            color:
                var(--primary);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;
        }


        .hero-feature-heading::before {

            content:
                "";

            width:
                24px;

            height:
                2px;

            background:
                var(--primary);
        }


        .hero-feature-panel h2 {

            color:
                white;

            font-size:
                22px;

            line-height:
                1.2;

            margin-bottom:
                18px;
        }


        .hero-feature-list {

            display:
                flex;

            flex-direction:
                column;

            gap:
                10px;
        }


        .hero-feature-item {

            display:
                grid;

            grid-template-columns:
                42px
                minmax(
                    0,
                    1fr
                );

            gap:
                12px;

            padding:
                13px;

            border-radius:
                12px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.07
                );

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.025
                );
        }


        .hero-feature-number {

            width:
                42px;

            height:
                42px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                11px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.15
                );

            font-size:
                10px;

            font-weight:
                800;
        }


        .hero-feature-item h3 {

            color:
                #e8f4f7;

            font-size:
                13px;

            margin-bottom:
                4px;
        }


        .hero-feature-item p {

            color:
                #7893a1;

            font-size:
                10px;

            line-height:
                1.6;
        }


        .hero-feature-footer {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            margin-top:
                15px;

            color:
                #7893a1;

            font-size:
                9px;
        }


        .hero-feature-footer i {

            color:
                var(--primary);
        }


        /* =====================================================
           SMALL SECTION INTRO
        ===================================================== */

        .team-b2-topline {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                9px;

            margin-bottom:
                10px;

            color:
                var(--primary);

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;
        }


        .team-b2-topline span {

            width:
                25px;

            height:
                2px;

            background:
                var(--primary);
        }


        /* =====================================================
           LIGHT THEME SECTION KICKERS
        ===================================================== */

        html[data-theme="light"]
        .section-kicker {

            color:
                var(--primary);
        }


        html[data-theme="light"]
        .btn-outline-light {

            color:
                #214052;

            border-color:
                rgba(
                    19,
                    62,
                    83,
                    0.18
                );
        }


        html[data-theme="light"]
        .btn-outline-light:hover {

            color:
                #102a43;

            background:
                rgba(
                    19,
                    62,
                    83,
                    0.05
                );
        }


        /* =====================================================
           MOBILE TEAM
        ===================================================== */

        @media (max-width: 900px) {

            .mobile-header-actions {

                display:
                    flex;
            }


            .mobile-menu-btn {

                display:
                    grid;
            }


            .header-right {

                display:
                    flex;

                align-items:
                    center;

                gap:
                    10px;
            }


            .header-right .btn {

                display:
                    none;
            }


            .team-b2-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );
            }


            .main-nav {

                position:
                    absolute;

                top:
                    76px;

                left:
                    0;

                right:
                    0;

                padding:
                    15px 5% 22px;

                flex-direction:
                    column;

                align-items:
                    flex-start;

                background:
                    #031b2d;

                border-bottom:
                    1px solid
                    var(--border);

                opacity:
                    0;

                visibility:
                    hidden;

                transform:
                    translateY(-12px);

                transition:
                    opacity .25s ease,
                    visibility .25s ease,
                    transform .25s ease;
            }


            .main-nav.show {

                opacity:
                    1;

                visibility:
                    visible;

                transform:
                    translateY(0);
            }

        }


        @media (max-width: 600px) {

            .team-b2-section {

                padding:
                    70px 0;
            }


            .team-b2-grid {

                grid-template-columns:
                    1fr;
            }


            .team-b2-card {

                min-height:
                    auto;

                padding:
                    21px;
            }


            .hero-feature-panel {

                padding:
                    20px;
            }


            .hero-feature-panel h2 {

                font-size:
                    20px;
            }


            .mobile-header-actions {

                gap:
                    7px;
            }


            .mobile-menu-btn,
            .theme-toggle {

                width:
                    40px;

                height:
                    40px;

                border-radius:
                    10px;
            }


            .school-affiliation {

                justify-content:
                    flex-start;

                gap:
                    11px;

                padding:
                    20px 14px;

            }


            .school-affiliation-logo {

                width:
                    42px;

                height:
                    42px;

            }


            .school-affiliation-name {

                font-size:
                    11px;

            }


            .school-affiliation-department {

                font-size:
                    8px;

            }

        }


        /* =====================================================
           MOBILE HEADER THEME TOGGLE
        ===================================================== */

        @media (max-width: 900px) {

            .theme-toggle {

                width:
                    40px;

                height:
                    40px;
            }

        }

    </style>


    <!-- =========================================================
         THEME INITIALIZATION
         Prevents unnecessary flash between stored themes.
    ========================================================== -->

    <script>

        (function () {

            try {

                const savedTheme =
                    localStorage.getItem(
                        'medicare-theme'
                    );


                if (
                    savedTheme === 'light' ||
                    savedTheme === 'dark'
                ) {

                    document.documentElement
                        .setAttribute(
                            'data-theme',
                            savedTheme
                        );

                } else {

                    document.documentElement
                        .setAttribute(
                            'data-theme',
                            'dark'
                        );
                }

            } catch (
                error
            ) {

                document.documentElement
                    .setAttribute(
                        'data-theme',
                        'dark'
                    );
            }

        })();

    </script>

</head>


<body>


<!-- =========================================================
     NAVIGATION
========================================================= -->

<header class="site-header">

    <div class="container navbar">


        <a
            href="index.php"
            class="logo"
            aria-label="MediCare Home"
        >

            <img
                src="images/logo.jpg"
                alt="MediCare Logo"
            >

        </a>


        <nav
            class="main-nav"
            id="mainNav"
        >

            <a
                href="index.php"
                class="active"
            >
                Home
            </a>

            <a href="#team">
                Team B2
            </a>

            <a href="#appointment">
                Appointment
            </a>

            <a href="login.php">
                Login
            </a>

            <a href="register.php">
                Register
            </a>

            <a href="#contact">
                Contact
            </a>

        </nav>


        <div class="header-right">


            <a
                href="tel:+2348101234567"
                class="phone-link"
            >

                <i class="fa-solid fa-phone"></i>

                <span>
                    +234 810 123 4567
                </span>

            </a>


            <!-- =================================================
                 MOBILE HEADER ACTIONS
            ================================================== -->

            <div class="mobile-header-actions">


                <button
                    class="mobile-menu-btn"
                    id="mobileMenuBtn"
                    type="button"
                    aria-label="Open menu"
                    aria-expanded="false"
                >

                    <i class="fa-solid fa-bars"></i>

                </button>


                <!-- =============================================
                     THEME TOGGLE
                ============================================== -->

                <button
                    type="button"
                    class="theme-toggle"
                    id="themeToggle"
                    aria-label="Switch to light mode"
                    title="Switch to light mode"
                >

                    <i
                        class="fa-solid fa-sun"
                        id="themeToggleIcon"
                    ></i>

                </button>


            </div>


            <a
                href="login.php"
                class="btn btn-primary"
            >
                Patient Portal
            </a>


        </div>

    </div>

</header>


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="hero-overlay"></div>


    <div class="container hero-content">


        <!-- =================================================
             LEFT
        ================================================== -->

        <div class="hero-copy">


            <div class="eyebrow">

                <span></span>

                WELCOME TO MEDICARE

            </div>


            <h1>

                Your Health,

                <span>
                    Our Priority
                </span>

            </h1>


            <p>

                We provide a connected healthcare experience
                designed to make appointments, clinical
                information and patient care easier to manage.

            </p>


            <div class="hero-actions">


                <a
                    href="login.php"
                    class="btn btn-primary btn-lg"
                >

                    <i class="fa-solid fa-right-to-bracket"></i>

                    Patient Portal

                </a>


                <a
                    href="#team"
                    class="btn btn-outline-light btn-lg"
                >

                    Meet Team B2

                    <i class="fa-solid fa-arrow-right"></i>

                </a>


            </div>


            <div class="hero-line">

                <span></span>

                <i class="fa-solid fa-heart-pulse"></i>

                <span></span>

            </div>


        </div>


        <!-- =================================================
             RIGHT
        ================================================== -->

        <div class="hero-feature-panel">


            <div class="hero-feature-heading">

                MediCare Platform

            </div>


            <h2>
                Healthcare management built around the patient.
            </h2>


            <div class="hero-feature-list">


                <!-- 01 -->

                <article class="hero-feature-item">

                    <div class="hero-feature-number">
                        01
                    </div>


                    <div>

                        <h3>
                            Appointment Management
                        </h3>

                        <p>
                            Book, confirm, complete and track
                            healthcare visits with ease.
                        </p>

                    </div>

                </article>


                <!-- 02 -->

                <article class="hero-feature-item">

                    <div class="hero-feature-number">
                        02
                    </div>


                    <div>

                        <h3>
                            Clinical Records
                        </h3>

                        <p>
                            Doctors can securely document
                            diagnoses, treatment notes and
                            patient records.
                        </p>

                    </div>

                </article>


                <!-- 03 -->

                <article class="hero-feature-item">

                    <div class="hero-feature-number">
                        03
                    </div>


                    <div>

                        <h3>
                            Role-Based Access
                        </h3>

                        <p>
                            Separate workflows for administrators,
                            doctors and patients, with access
                            based on their responsibilities.
                        </p>

                    </div>

                </article>


            </div>


            <div class="hero-feature-footer">

                <i class="fa-solid fa-shield-heart"></i>

                Secure workflows for authorized MediCare users.

            </div>


        </div>

    </div>

</section>


<!-- =========================================================
     TEAM B2
========================================================= -->

<section
    class="team-b2-section"
    id="team"
>

    <div class="container">


        <div class="team-heading">


            <div class="team-b2-topline">

                <span></span>

                TEAM B2

                <span></span>

            </div>


            <h2>
                Meet Team B2
            </h2>


            <p>

                A dedicated team working together to design,
                develop and support the MediCare healthcare platform.

            </p>

        </div>


        <div class="team-b2-grid">


            <!-- =================================================
                 01
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    01
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/edafe-clever.jpg"
                        alt="Edafe Clever Ogheneruona"
                    >

                </div>


                <h3>
                    Edafe Clever Ogheneruona
                </h3>


                <span class="team-b2-role">
                    President
                </span>


                <p>
                    Providing leadership and strategic direction
                    for Team B2 and the MediCare project.
                </p>

            </article>


            <!-- =================================================
                 02
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    02
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/joshua-eboma.jpg"
                        alt="President Eboma Ifakachukwu Joshua"
                    >

                </div>


                <h3>
                    President Eboma Ifakachukwu Joshua
                </h3>


                <span class="team-b2-role">
                    Secretary
                </span>


                <p>
                    Coordinating documentation, communication
                    and team administration.
                </p>

            </article>


            <!-- =================================================
                 03
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    03
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/edewor-solomon.jpg"
                        alt="Edewor Solomon Uwomano"
                    >

                </div>


                <h3>
                    Edewor Solomon Uwomano
                </h3>


                <span class="team-b2-role">
                    Technical Director
                </span>


                <p>
                    Leading the technical development and
                    implementation of the MediCare platform.
                </p>

            </article>


            <!-- =================================================
                 04
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    04
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/edijana-faith.jpg"
                        alt="Edijana Faith Segbuyota"
                    >

                </div>


                <h3>
                    Edijana Faith Segbuyota
                </h3>


                <span class="team-b2-role">
                    Public Relation Officer (P.R.O)
                </span>


                <p>
                    Supporting communication, outreach and
                    public engagement for the team.
                </p>

            </article>


            <!-- =================================================
                 05
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    05
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/efezino-courage.jpg"
                        alt="Efezino Courage Odomero"
                    >

                </div>


                <h3>
                    Efezino Courage Odomero
                </h3>


                <span class="team-b2-role">
                    Product Manager
                </span>


                <p>
                    Helping define product direction and ensuring
                    the platform meets project requirements.
                </p>

            </article>


            <!-- =================================================
                 06
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    06
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/ejiro-bright.jpg"
                        alt="Ejiro Bright Avwerosuoghene"
                    >

                </div>


                <h3>
                    Ejiro Bright Avwerosuoghene
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


            <!-- =================================================
                 07
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    07
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/eki-christian.jpg"
                        alt="Eki Christian"
                    >

                </div>


                <h3>
                    Eki Christian
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


            <!-- =================================================
                 08
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    08
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/benard-justice.jpg"
                        alt="Benard Justice Oghenero"
                    >

                </div>


                <h3>
                    Benard Justice Oghenero
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


            <!-- =================================================
                 09
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    09
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/brown-anietie.jpg"
                        alt="Brown Anietie Friday"
                    >

                </div>


                <h3>
                    Brown Anietie Friday
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


            <!-- =================================================
                 10
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    10
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/benson-oghenero.jpg"
                        alt="Benson Oghenero"
                    >

                </div>


                <h3>
                    Benson Oghenero
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


            <!-- =================================================
                 11
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    11
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/daniel-prosper.jpg"
                        alt="Daniel Prosper Fegor"
                    >

                </div>


                <h3>
                    Daniel Prosper Fegor
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


            <!-- =================================================
                 12
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    12
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/chukwuekwu-emmanuel.jpg"
                        alt="Chukwuekwu Emmanuel"
                    >

                </div>


                <h3>
                    Chukwuekwu Emmanuel
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


            <!-- =================================================
                 13
            ================================================== -->

            <article class="team-b2-card">

                <span class="team-b2-number">
                    13
                </span>


                <div class="team-b2-icon team-member-photo">

                    <img
                        src="images/Edigbe-Favour.jpg"
                        alt="Edigbe Favour Oghenero"
                    >

                </div>


                <h3>
                    Edigbe Favour Oghenero
                </h3>


                <span class="team-b2-role">
                    Member
                </span>

            </article>


        </div>

    </div>

</section>


<!-- =========================================================
     APPOINTMENT SECTION
========================================================= -->

<section
    class="section section-dark"
    id="appointment"
>

    <div class="container two-column-section">


        <!-- APPOINTMENT -->

        <div class="appointment-box">

            <span class="section-kicker">
                BOOK A VISIT
            </span>


            <h2>
                Book an Appointment
            </h2>


            <p>

                Schedule your healthcare visit through
                the MediCare patient portal.

            </p>


            <div
                class="hero-actions"
                style="margin-top: 24px;"
            >

                <a
                    href="login.php"
                    class="btn btn-dark btn-lg"
                >

                    <i class="fa-solid fa-right-to-bracket"></i>

                    Sign In

                </a>


                <a
                    href="register.php"
                    class="btn btn-outline-light btn-lg"
                >

                    Create Account

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>

        </div>


        <!-- PLATFORM SUMMARY -->

        <div class="about-card">

            <div class="about-image">

                <div
                    style="
                        width:100%;
                        height:100%;
                    "
                ></div>

            </div>


            <div class="about-content">

                <span class="section-kicker">
                    MEDICARE PLATFORM
                </span>


                <h2>
                    Connected healthcare
                    in one place.
                </h2>


                <p>

                    MediCare brings patients, doctors and
                    administrators into one structured healthcare
                    workflow for appointment management, clinical
                    records and secure role-based access.

                </p>


                <a
                    href="register.php"
                    class="btn btn-outline-light"
                >

                    Get Started

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>

        </div>


    </div>

</section>


<!-- =========================================================
     CTA
========================================================= -->

<section
    class="cta-section"
    id="contact"
>

    <div class="container cta-content">


        <div>

            <span class="section-kicker">
                NEED MEDICAL HELP?
            </span>


            <h2>
                Your health deserves
                organized, reliable care.
            </h2>


            <p>
                Access the MediCare platform and manage
                your healthcare journey securely.
            </p>

        </div>


        <div class="cta-actions">


            <a
                href="login.php"
                class="btn btn-primary btn-lg"
            >

                Patient Portal

            </a>


            <a
                href="tel:+2348101234567"
                class="btn btn-outline-light btn-lg"
            >

                <i class="fa-solid fa-phone"></i>

                Call Us

            </a>


        </div>

    </div>

</section>


<!-- =========================================================
     SCHOOL AFFILIATION
========================================================= -->

<section
    class="school-affiliation"
    aria-label="School and department affiliation"
>

    <img
        src="images/sdu-logo.jpg"
        alt="Southern Delta University Logo"
        class="school-affiliation-logo"
    >

    <div class="school-affiliation-copy">


        <strong class="school-affiliation-name">
            Southern Delta University
        </strong>

        <span class="school-affiliation-department">
            Department: Information Systems and Technology
        </span>

    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <div class="container footer-grid">


        <!-- BRAND -->

        <div class="footer-brand">


            <a
                href="index.php"
                class="logo"
                aria-label="MediCare Home"
            >

                <img
                    src="images/logo.jpg"
                    alt="MediCare Logo"
                >

            </a>


            <p>

                MediCare provides a structured healthcare
                management platform for patients, doctors
                and administrators.

            </p>


            <div class="social-links">


                <a
                    href="#"
                    aria-label="Facebook"
                >

                    <i
                        class="fa-brands fa-facebook-f"
                    ></i>

                </a>


                <a
                    href="#"
                    aria-label="Instagram"
                >

                    <i
                        class="fa-brands fa-instagram"
                    ></i>

                </a>


                <a
                    href="#"
                    aria-label="X"
                >

                    <i
                        class="fa-brands fa-x-twitter"
                    ></i>

                </a>


                <a
                    href="#"
                    aria-label="LinkedIn"
                >

                    <i
                        class="fa-brands fa-linkedin-in"
                    ></i>

                </a>


            </div>

        </div>


        <!-- QUICK LINKS -->

        <div class="footer-column">

            <h3>
                Quick Links
            </h3>


            <a href="index.php">
                Home
            </a>


            <a href="#team">
                Team B2
            </a>


            <a href="#appointment">
                Appointment
            </a>


            <a href="#contact">
                Contact
            </a>

        </div>


        <!-- PATIENT CARE -->

        <div class="footer-column">

            <h3>
                Patient Care
            </h3>


            <a href="login.php">
                Patient Login
            </a>


            <a href="register.php">
                Create Account
            </a>


            <a href="appointments.php">
                Appointments
            </a>


            <a href="patients.php">
                Patients
            </a>

        </div>


        <!-- CONTACT -->

        <div class="footer-column">

            <h3>
                Contact
            </h3>


            <a href="tel:+2348101234567">

                <i class="fa-solid fa-phone"></i>

                +234 810 123 4567

            </a>


            <a href="mailto:medicareltd001@gmail.com">

                <i class="fa-solid fa-envelope"></i>

                medicareltd001@gmail.com

            </a>


            <span>

                <i class="fa-solid fa-location-dot"></i>

                Nigeria

            </span>


            <span>

                <i class="fa-solid fa-clock"></i>

                Open 24/7

            </span>

        </div>


    </div>


    <!-- FOOTER BOTTOM -->

    <div class="footer-bottom">

        <div class="container footer-bottom-inner">

            <p>
                © 2026 MediCare. All rights reserved.
            </p>


            <div>

                <a href="#">
                    Privacy Policy
                </a>


                <a href="#">
                    Terms of Service
                </a>

            </div>

        </div>

    </div>

</footer>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /* =====================================================
           MOBILE MENU
        ===================================================== */

        const mobileMenuBtn =
            document.getElementById(
                'mobileMenuBtn'
            );

        const mainNav =
            document.getElementById(
                'mainNav'
            );


        if (
            mobileMenuBtn &&
            mainNav
        ) {

            mobileMenuBtn.addEventListener(
                'click',
                function () {

                    const isOpen =
                        mainNav.classList.toggle(
                            'show'
                        );


                    mobileMenuBtn.setAttribute(
                        'aria-expanded',
                        isOpen
                            ? 'true'
                            : 'false'
                    );


                    const icon =
                        mobileMenuBtn.querySelector(
                            'i'
                        );


                    if (icon) {

                        if (isOpen) {

                            icon.classList.remove(
                                'fa-bars'
                            );

                            icon.classList.add(
                                'fa-xmark'
                            );

                        } else {

                            icon.classList.remove(
                                'fa-xmark'
                            );

                            icon.classList.add(
                                'fa-bars'
                            );

                        }

                    }

                }
            );


            mainNav
                .querySelectorAll('a')
                .forEach(
                    function (link) {

                        link.addEventListener(
                            'click',
                            function () {

                                mainNav.classList.remove(
                                    'show'
                                );


                                mobileMenuBtn.setAttribute(
                                    'aria-expanded',
                                    'false'
                                );


                                const icon =
                                    mobileMenuBtn.querySelector(
                                        'i'
                                    );


                                if (icon) {

                                    icon.classList.remove(
                                        'fa-xmark'
                                    );

                                    icon.classList.add(
                                        'fa-bars'
                                    );

                                }

                            }
                        );

                    }
                );

        }


        /* =====================================================
           THEME TOGGLE
        ===================================================== */

        const themeToggle =
            document.getElementById(
                'themeToggle'
            );

        const themeToggleIcon =
            document.getElementById(
                'themeToggleIcon'
            );

        const themeColorMeta =
            document.getElementById(
                'themeColorMeta'
            );


        function applyTheme(
            theme
        ) {

            const safeTheme =
                theme === 'light'
                    ? 'light'
                    : 'dark';


            document.documentElement
                .setAttribute(
                    'data-theme',
                    safeTheme
                );


            try {

                localStorage.setItem(
                    'medicare-theme',
                    safeTheme
                );

            } catch (
                error
            ) {
                // Ignore localStorage errors.
            }


            if (
                themeToggleIcon
            ) {

                if (
                    safeTheme === 'light'
                ) {

                    themeToggleIcon.classList.remove(
                        'fa-sun'
                    );

                    themeToggleIcon.classList.add(
                        'fa-moon'
                    );

                } else {

                    themeToggleIcon.classList.remove(
                        'fa-moon'
                    );

                    themeToggleIcon.classList.add(
                        'fa-sun'
                    );

                }

            }


            if (
                themeToggle
            ) {

                const nextTheme =
                    safeTheme === 'light'
                        ? 'dark'
                        : 'light';


                const nextLabel =
                    nextTheme === 'light'
                        ? 'Switch to light mode'
                        : 'Switch to dark mode';


                themeToggle.setAttribute(
                    'aria-label',
                    nextLabel
                );


                themeToggle.setAttribute(
                    'title',
                    nextLabel
                );

            }


            if (
                themeColorMeta
            ) {

                themeColorMeta.setAttribute(
                    'content',
                    safeTheme === 'light'
                        ? '#f4f8fb'
                        : '#031b2d'
                );

            }

        }


        let currentTheme =
            document.documentElement
                .getAttribute(
                    'data-theme'
                );


        if (
            currentTheme !== 'light' &&
            currentTheme !== 'dark'
        ) {

            currentTheme =
                'dark';
        }


        applyTheme(
            currentTheme
        );


        if (
            themeToggle
        ) {

            themeToggle.addEventListener(
                'click',
                function () {

                    const activeTheme =
                        document.documentElement
                            .getAttribute(
                                'data-theme'
                            ) || 'dark';


                    const nextTheme =
                        activeTheme === 'dark'
                            ? 'light'
                            : 'dark';


                    applyTheme(
                        nextTheme
                    );

                }
            );

        }


        /* =====================================================
           PREVENT PAST DATES
        ===================================================== */

        const today =
            new Date()
                .toISOString()
                .split('T')[0];


        document
            .querySelectorAll(
                'input[type="date"]'
            )
            .forEach(
                function (input) {

                    input.min =
                        today;

                }
            );

    }
);

</script>


</body>

</html>
