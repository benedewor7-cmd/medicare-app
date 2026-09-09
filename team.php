<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Admins and Doctors can view the Medical Team.
|--------------------------------------------------------------------------
*/

require_role('Admin', 'Doctor');


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserId = current_user_id();

$currentRole = ucfirst(
    strtolower(
        trim(
            (string) (
                $_SESSION['role'] ?? 'Doctor'
            )
        )
    )
);

$displayName = (string) (
    $_SESSION['fullname'] ?? 'User'
);


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$doctors = [];

$error = '';

$search = '';


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) (
        $_GET['search'] ?? ''
    )
);

$search = mb_substr(
    $search,
    0,
    100
);


/*
|--------------------------------------------------------------------------
| LOAD MEDICAL TEAM
|--------------------------------------------------------------------------
*/

try {

    if ($search !== '') {

        $searchTerm =
            '%' . $search . '%';


        $stmt = $pdo->prepare(
            "SELECT
                id,
                fullname,
                email,
                role
             FROM users
             WHERE
                role = 'Doctor'
                AND (
                    fullname LIKE ?
                    OR email LIKE ?
                )
             ORDER BY
                fullname ASC"
        );


        $stmt->execute([
            $searchTerm,
            $searchTerm
        ]);


    } else {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                fullname,
                email,
                role
             FROM users
             WHERE
                role = 'Doctor'
             ORDER BY
                fullname ASC"
        );


        $stmt->execute();
    }


    $doctors =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    error_log(
        'MediCare Medical Team Error: ' .
        $e->getMessage()
    );

    $error =
        'Unable to load the medical team right now.';
}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalDoctors = 0;

try {

    $countStmt = $pdo->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role = 'Doctor'"
    );

    $totalDoctors =
        (int) (
            $countStmt->fetchColumn() ?: 0
        );


} catch (PDOException $e) {

    error_log(
        'MediCare Medical Team Count Error: ' .
        $e->getMessage()
    );

    $totalDoctors =
        count($doctors);
}


/*
|--------------------------------------------------------------------------
| USER INITIAL
|--------------------------------------------------------------------------
*/

$userInitial = mb_strtoupper(
    mb_substr(
        trim($displayName),
        0,
        1
    )
);

if ($userInitial === '') {
    $userInitial = 'U';
}

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
        content="MediCare medical team and doctors."
    >

    <title>
        Medical Team | MediCare
    </title>


    <!-- GOOGLE FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        /* =========================================================
           ROOT
        ========================================================= */

        :root {

            --bg:
                #031b2d;

            --sidebar:
                #05273e;

            --panel:
                #082f49;

            --primary:
                #10c7b0;

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

            --white:
                #ffffff;

            --text:
                #e1edf3;

            --muted:
                #8fa8b8;

            --border:
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            --sidebar-width:
                255px;
        }


        /* =========================================================
           RESET
        ========================================================= */

        * {

            margin:
                0;

            padding:
                0;

            box-sizing:
                border-box;
        }


        html {
            scroll-behavior:
                smooth;
        }


        body {

            min-height:
                100vh;

            font-family:
                "Inter",
                sans-serif;

            color:
                var(--text);

            background:
                radial-gradient(
                    circle at 15% 10%,
                    rgba(
                        16,
                        199,
                        176,
                        0.07
                    ),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 85% 80%,
                    rgba(
                        44,
                        140,
                        255,
                        0.06
                    ),
                    transparent 27%
                ),
                var(--bg);

            overflow-x:
                hidden;
        }


        body.menu-open {
            overflow:
                hidden;
        }


        a {

            color:
                inherit;

            text-decoration:
                none;
        }


        input,
        button {
            font:
                inherit;
        }


        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {

            position:
                fixed;

            inset:
                0 auto 0 0;

            width:
                var(--sidebar-width);

            z-index:
                1000;

            display:
                flex;

            flex-direction:
                column;

            padding:
                24px 15px;

            background:
                linear-gradient(
                    180deg,
                    #05283f,
                    #031b2d
                );

            border-right:
                1px solid
                var(--border);

            box-shadow:
                15px 0 45px
                rgba(
                    0,
                    0,
                    0,
                    0.18
                );

            transition:
                transform .25s ease;
        }


        /* =========================================================
           BRAND
        ========================================================= */

        .brand {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            padding:
                0 10px;

            margin-bottom:
                34px;
        }


        .brand-icon {

            width:
                43px;

            height:
                43px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                12px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.09
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.28
                );

            font-size:
                20px;
        }


        .brand-text {

            display:
                flex;

            flex-direction:
                column;
        }


        .brand-text strong {

            font-size:
                21px;

            line-height:
                1;
        }


        .brand-text strong span {

            color:
                var(--primary);
        }


        .brand-text small {

            margin-top:
                4px;

            color:
                var(--muted);

            font-size:
                8px;
        }


        /* =========================================================
           NAVIGATION
        ========================================================= */

        .nav-title {

            padding:
                0 11px;

            margin-bottom:
                10px;

            color:
                #648191;

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;
        }


        .nav {

            display:
                flex;

            flex-direction:
                column;

            gap:
                5px;
        }


        .nav a {

            min-height:
                47px;

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            padding:
                0 13px;

            border-radius:
                11px;

            color:
                #9db4c1;

            font-size:
                12px;

            font-weight:
                600;

            transition:
                .2s ease;
        }


        .nav a:hover {

            color:
                white;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.045
                );

            transform:
                translateX(2px);
        }


        .nav a.active {

            color:
                white;

            background:
                linear-gradient(
                    135deg,
                    rgba(
                        16,
                        199,
                        176,
                        0.18
                    ),
                    rgba(
                        16,
                        199,
                        176,
                        0.06
                    )
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.12
                );
        }


        .nav a i {

            width:
                18px;

            color:
                #76919f;

            text-align:
                center;
        }


        .nav a.active i {

            color:
                var(--primary);
        }


        /* =========================================================
           USER AREA
        ========================================================= */

        .sidebar-spacer {

            flex:
                1;
        }


        .user-box {

            padding:
                14px;

            border:
                1px solid
                var(--border);

            border-radius:
                14px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.03
                );
        }


        .user-info {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;
        }


        .avatar {

            width:
                38px;

            height:
                38px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                11px;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    #0ba8c5
                );

            color:
                white;

            font-size:
                12px;

            font-weight:
                800;
        }


        .user-copy {

            min-width:
                0;
        }


        .user-copy strong {

            display:
                block;

            overflow:
                hidden;

            white-space:
                nowrap;

            text-overflow:
                ellipsis;

            font-size:
                11px;
        }


        .user-copy span {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--muted);

            font-size:
                9px;
        }


        .role-label {

            display:
                inline-block;

            margin-top:
                10px;

            padding:
                5px 9px;

            border-radius:
                999px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.07
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
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .logout-form {

            margin-top:
                10px;
        }


        .logout-button {

            width:
                100%;

            height:
                40px;

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    0.15
                );

            border-radius:
                10px;

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.04
                );

            color:
                #ff969c;

            font-size:
                10px;

            font-weight:
                700;

            cursor:
                pointer;
        }


        .logout-button:hover {

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.10
                );

            color:
                #ffb5b8;
        }


        /* =========================================================
           MAIN
        ========================================================= */

        .main {

            min-height:
                100vh;

            margin-left:
                var(--sidebar-width);
        }


        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {

            height:
                75px;

            position:
                sticky;

            top:
                0;

            z-index:
                900;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                0 30px;

            background:
                rgba(
                    3,
                    27,
                    44,
                    0.86
                );

            border-bottom:
                1px solid
                var(--border);

            backdrop-filter:
                blur(16px);
        }


        .top-title small {

            display:
                block;

            color:
                var(--primary);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                1.3px;

            text-transform:
                uppercase;
        }


        .top-title h2 {

            margin-top:
                3px;

            font-size:
                17px;
        }


        .top-actions {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;
        }


        .top-icon,
        .menu-button {

            width:
                38px;

            height:
                38px;

            display:
                grid;

            place-items:
                center;

            border:
                1px solid
                var(--border);

            border-radius:
                10px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.025
                );

            color:
                #8fa8b6;
        }


        .top-icon:hover {

            color:
                var(--primary);
        }


        .menu-button {

            display:
                none;

            cursor:
                pointer;
        }


        /* =========================================================
           CONTENT
        ========================================================= */

        .content {

            width:
                min(
                    100%,
                    1280px
                );

            margin:
                0 auto;

            padding:
                30px;
        }


        /* =========================================================
           HERO
        ========================================================= */

        .hero {

            position:
                relative;

            overflow:
                hidden;

            min-height:
                180px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                25px;

            margin-bottom:
                20px;

            padding:
                30px 32px;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.15
                );

            border-radius:
                20px;

            background:
                linear-gradient(
                    135deg,
                    #073f51,
                    #072e42 55%,
                    #082940
                );
        }


        .hero::after {

            content:
                "";

            position:
                absolute;

            width:
                330px;

            height:
                330px;

            right:
                -125px;

            top:
                -160px;

            border-radius:
                50%;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.14
                );
        }


        .hero-copy {

            position:
                relative;

            z-index:
                2;
        }


        .eyebrow {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                9px;

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


        .eyebrow::before {

            content:
                "";

            width:
                25px;

            height:
                2px;

            background:
                var(--primary);
        }


        .hero h1 {

            color:
                white;

            font-size:
                clamp(
                    28px,
                    3vw,
                    40px
                );

            line-height:
                1.05;

            letter-spacing:
                -1.8px;
        }


        .hero p {

            max-width:
                720px;

            margin-top:
                11px;

            color:
                #b7ced8;

            font-size:
                11px;

            line-height:
                1.7;
        }


        .team-count {

            position:
                relative;

            z-index:
                2;

            flex-shrink:
                0;

            min-width:
                110px;

            padding:
                15px;

            text-align:
                center;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.12
                );

            border-radius:
                14px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.05
                );
        }


        .team-count strong {

            display:
                block;

            color:
                white;

            font-size:
                28px;

            line-height:
                1;
        }


        .team-count span {

            display:
                block;

            margin-top:
                6px;

            color:
                #8fa8b6;

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                .7px;
        }


        /* =========================================================
           ERROR
        ========================================================= */

        .alert {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            margin-bottom:
                18px;

            padding:
                13px 15px;

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    0.13
                );

            border-radius:
                10px;

            color:
                #ff989e;

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.07
                );

            font-size:
                10px;
        }


        /* =========================================================
           SEARCH
        ========================================================= */

        .search-panel {

            margin-bottom:
                20px;

            padding:
                18px;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            background:
                linear-gradient(
                    145deg,
                    rgba(
                        8,
                        47,
                        70,
                        .95
                    ),
                    rgba(
                        5,
                        31,
                        48,
                        .97
                    )
                );
        }


        .search-form {

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1fr
                )
                110px
                90px;

            gap:
                9px;
        }


        .search-input {

            width:
                100%;

            height:
                44px;

            padding:
                0 13px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            border-radius:
                9px;

            outline:
                none;

            background:
                rgba(
                    2,
                    22,
                    36,
                    .62
                );

            color:
                #dceaf0;

            font-size:
                10px;
        }


        .search-input::placeholder {

            color:
                #607b8a;
        }


        .search-input:focus {

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    .48
                );

            box-shadow:
                0 0 0 3px
                rgba(
                    16,
                    199,
                    176,
                    .07
                );
        }


        .search-button,
        .clear-button {

            height:
                44px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                6px;

            border-radius:
                9px;

            font-size:
                9px;

            font-weight:
                800;
        }


        .search-button {

            border:
                none;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    #10b4d3
                );

            color:
                white;

            cursor:
                pointer;
        }


        .clear-button {

            border:
                1px solid
                var(--border);

            background:
                rgba(
                    255,
                    255,
                    255,
                    .03
                );

            color:
                #8da5b2;
        }


        .clear-button:hover {

            color:
                var(--primary);
        }


        /* =========================================================
           TEAM GRID
        ========================================================= */

        .team-grid {

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


        /* =========================================================
           DOCTOR CARD
        ========================================================= */

        .doctor-card {

            position:
                relative;

            overflow:
                hidden;

            min-height:
                245px;

            padding:
                22px;

            border:
                1px solid
                var(--border);

            border-radius:
                17px;

            background:
                linear-gradient(
                    145deg,
                    rgba(
                        8,
                        47,
                        70,
                        .96
                    ),
                    rgba(
                        5,
                        31,
                        48,
                        .98
                    )
                );

            transition:
                transform .2s ease,
                border-color .2s ease;
        }


        .doctor-card::after {

            content:
                "";

            position:
                absolute;

            width:
                130px;

            height:
                130px;

            right:
                -65px;

            top:
                -65px;

            border-radius:
                50%;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .10
                );
        }


        .doctor-card:hover {

            transform:
                translateY(-4px);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    .22
                );
        }


        .doctor-top {

            position:
                relative;

            z-index:
                2;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

            margin-bottom:
                18px;
        }


        .doctor-avatar {

            width:
                62px;

            height:
                62px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                18px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    .08
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .16
                );

            color:
                var(--primary);

            font-size:
                20px;

            font-weight:
                800;
        }


        .doctor-role {

            padding:
                6px 9px;

            border-radius:
                999px;

            color:
                #72e5ac;

            background:
                rgba(
                    85,
                    216,
                    144,
                    .08
                );

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    .13
                );

            font-size:
                7px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .doctor-name {

            position:
                relative;

            z-index:
                2;

            color:
                #e2edf2;

            font-size:
                16px;

            font-weight:
                800;

            line-height:
                1.3;
        }


        .doctor-subtitle {

            margin-top:
                5px;

            color:
                var(--primary);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .5px;

            text-transform:
                uppercase;
        }


        .doctor-info {

            position:
                relative;

            z-index:
                2;

            margin-top:
                18px;

            padding-top:
                15px;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .055
                );
        }


        .doctor-info-row {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            margin-bottom:
                9px;

            color:
                #829ba9;

            font-size:
                9px;

            line-height:
                1.5;
        }


        .doctor-info-row:last-child {

            margin-bottom:
                0;
        }


        .doctor-info-row i {

            width:
                15px;

            color:
                #66808f;

            text-align:
                center;
        }


        .doctor-info-row span {

            overflow:
                hidden;

            white-space:
                nowrap;

            text-overflow:
                ellipsis;
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty-state {

            padding:
                70px 25px;

            text-align:
                center;

            border:
                1px dashed
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            border-radius:
                16px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .02
                );
        }


        .empty-state i {

            display:
                block;

            margin-bottom:
                14px;

            color:
                #4e6b79;

            font-size:
                32px;
        }


        .empty-state strong {

            display:
                block;

            margin-bottom:
                6px;

            color:
                #bdd0d8;

            font-size:
                13px;
        }


        .empty-state span {

            display:
                block;

            max-width:
                420px;

            margin:
                0 auto;

            color:
                #66808f;

            font-size:
                9px;

            line-height:
                1.7;
        }


        /* =========================================================
           OVERLAY
        ========================================================= */

        .overlay {

            display:
                none;

            position:
                fixed;

            inset:
                0;

            z-index:
                950;

            background:
                rgba(
                    0,
                    8,
                    16,
                    .65
                );

            backdrop-filter:
                blur(3px);
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

            :root {

                --sidebar-width:
                    235px;
            }


            .content {

                padding:
                    25px;
            }


            .team-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );
            }
        }


        @media (max-width: 900px) {

            .sidebar {

                transform:
                    translateX(-105%);
            }


            body.menu-open
            .sidebar {

                transform:
                    translateX(0);
            }


            .main {

                margin-left:
                    0;
            }


            .menu-button {

                display:
                    grid;

                place-items:
                    center;
            }


            .overlay {

                display:
                    block;

                visibility:
                    hidden;

                opacity:
                    0;

                transition:
                    opacity .25s ease,
                    visibility .25s ease;
            }


            body.menu-open
            .overlay {

                visibility:
                    visible;

                opacity:
                    1;
            }
        }


        @media (max-width: 700px) {

            .topbar {

                height:
                    68px;

                padding:
                    0 18px;
            }


            .content {

                padding:
                    20px 14px 30px;
            }


            .hero {

                flex-direction:
                    column;

                align-items:
                    flex-start;

                padding:
                    25px 20px;
            }


            .team-count {

                width:
                    100%;
            }


            .search-form {

                grid-template-columns:
                    1fr;
            }


            .search-button,
            .clear-button {

                width:
                    100%;
            }


            .team-grid {

                grid-template-columns:
                    1fr;
            }
        }


        @media (max-width: 460px) {

            .top-icon {

                display:
                    none;
            }


            .doctor-card {

                padding:
                    19px;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


    <!-- BRAND -->

    <a
        href="dashboard.php"
        class="brand"
    >

        <span class="brand-icon">

            <i class="fa-solid fa-plus"></i>

        </span>


        <span class="brand-text">

            <strong>
                Medi<span>Care</span>
            </strong>

            <small>
                Compassionate. Trusted. Reliable.
            </small>

        </span>

    </a>


    <!-- NAVIGATION TITLE -->

    <div class="nav-title">
        Main Menu
    </div>


    <!-- NAVIGATION -->

    <nav class="nav">


        <a href="dashboard.php">

            <i class="fa-solid fa-chart-pie"></i>

            <span>
                Dashboard
            </span>

        </a>


        <a href="appointments.php">

            <i class="fa-regular fa-calendar-check"></i>

            <span>
                Appointments
            </span>

        </a>


        <?php if ($currentRole === 'Patient'): ?>

            <a href="book_appointment.php">

                <i class="fa-solid fa-calendar-plus"></i>

                <span>
                    Book Appointment
                </span>

            </a>

        <?php endif; ?>


        <a href="patients.php">

            <i class="fa-solid fa-users"></i>

            <span>
                Patients
            </span>

        </a>


        <!-- MEDICAL TEAM -->

        <a
            href="team.php"
            class="active"
        >

            <i class="fa-solid fa-user-doctor"></i>

            <span>
                Medical Team
            </span>

        </a>


        <?php if ($currentRole === 'Admin'): ?>

            <a href="manage_users.php">

                <i class="fa-solid fa-user-gear"></i>

                <span>
                    Manage Users
                </span>

            </a>

        <?php endif; ?>


        <a href="profile.php">

            <i class="fa-solid fa-user-doctor"></i>

            <span>
                My Profile
            </span>

        </a>


    </nav>


    <!-- SPACER -->

    <div class="sidebar-spacer"></div>


    <!-- USER -->

    <div class="user-box">


        <div class="user-info">


            <div class="avatar">

                <?= e(
                    $userInitial
                ) ?>

            </div>


            <div class="user-copy">

                <strong>

                    <?= e(
                        $displayName
                    ) ?>

                </strong>


                <span>

                    <?= e(
                        $currentRole
                    ) ?>

                </span>

            </div>


        </div>


        <span class="role-label">

            <?= e(
                $currentRole
            ) ?>

        </span>


        <form
            method="POST"
            action="logout.php"
            class="logout-form"
        >

            <input
                type="hidden"
                name="_csrf"
                value="<?= e(
                    csrf_token()
                ) ?>"
            >


            <button
                type="submit"
                class="logout-button"
            >

                <i
                    class="
                        fa-solid
                        fa-right-from-bracket
                    "
                ></i>

                &nbsp;

                Logout

            </button>

        </form>


    </div>


</aside>


<!-- =========================================================
     OVERLAY
========================================================= -->

<div
    class="overlay"
    id="overlay"
></div>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="top-title">

            <small>
                MediCare Portal
            </small>

            <h2>
                Medical Team
            </h2>

        </div>


        <div class="top-actions">


            <button
                type="button"
                class="menu-button"
                id="menuButton"
                aria-label="Open menu"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <a
                href="dashboard.php"
                class="top-icon"
                title="Dashboard"
            >

                <i class="fa-solid fa-house"></i>

            </a>


            <a
                href="appointments.php"
                class="top-icon"
                title="Appointments"
            >

                <i class="fa-regular fa-calendar-check"></i>

            </a>


        </div>


    </header>


    <!-- =========================================================
         CONTENT
    ========================================================= -->

    <div class="content">


        <!-- HERO -->

        <section class="hero">


            <div class="hero-copy">


                <div class="eyebrow">
                    Healthcare Professionals
                </div>


                <h1>
                    Medical Team
                </h1>


                <p>

                    View the doctors currently registered
                    with the MediCare healthcare system and
                    access their professional account information.

                </p>


            </div>


            <div class="team-count">


                <strong>
                    <?= $totalDoctors ?>
                </strong>


                <span>
                    Doctors
                </span>


            </div>


        </section>


        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div
                class="alert"
                role="alert"
            >

                <i
                    class="
                        fa-solid
                        fa-circle-exclamation
                    "
                ></i>


                <?= e(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <!-- SEARCH -->

        <section class="search-panel">


            <form
                action="team.php"
                method="GET"
                class="search-form"
            >


                <input
                    type="search"
                    name="search"
                    class="search-input"
                    placeholder="Search doctor by name or email..."
                    value="<?= e(
                        $search
                    ) ?>"
                    autocomplete="off"
                >


                <button
                    type="submit"
                    class="search-button"
                >

                    <i class="fa-solid fa-magnifying-glass"></i>

                    Search

                </button>


                <?php if ($search !== ''): ?>

                    <a
                        href="team.php"
                        class="clear-button"
                    >

                        <i class="fa-solid fa-xmark"></i>

                        Clear

                    </a>

                <?php else: ?>

                    <span></span>

                <?php endif; ?>


            </form>


        </section>


        <!-- =====================================================
             TEAM GRID
        ====================================================== -->

        <?php if (!empty($doctors)): ?>


            <section class="team-grid">


                <?php foreach (
                    $doctors as $doctor
                ): ?>


                    <?php

                    $doctorId =
                        (int) (
                            $doctor['id'] ?? 0
                        );


                    $doctorName =
                        trim(
                            (string) (
                                $doctor['fullname']
                                ?? ''
                            )
                        );


                    if (
                        $doctorName === ''
                    ) {

                        $doctorName =
                            'Unnamed Doctor';
                    }


                    $doctorEmail =
                        trim(
                            (string) (
                                $doctor['email']
                                ?? ''
                            )
                        );


                    $doctorRole =
                        trim(
                            (string) (
                                $doctor['role']
                                ?? 'Doctor'
                            )
                        );


                    $doctorInitial =
                        mb_strtoupper(
                            mb_substr(
                                $doctorName,
                                0,
                                1
                            )
                        );


                    if (
                        $doctorInitial === ''
                    ) {

                        $doctorInitial =
                            'D';
                    }

                    ?>


                    <article class="doctor-card">


                        <div class="doctor-top">


                            <div class="doctor-avatar">

                                <?= e(
                                    $doctorInitial
                                ) ?>

                            </div>


                            <span class="doctor-role">

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-check
                                    "
                                ></i>

                                <?= e(
                                    $doctorRole
                                ) ?>

                            </span>


                        </div>


                        <h2 class="doctor-name">

                            Dr.
                            <?= e(
                                $doctorName
                            ) ?>

                        </h2>


                        <div class="doctor-subtitle">

                            Medical Professional

                        </div>


                        <div class="doctor-info">


                            <div
                                class="
                                    doctor-info-row
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-envelope
                                    "
                                ></i>


                                <span>

                                    <?= e(
                                        $doctorEmail !== ''
                                            ? $doctorEmail
                                            : 'Email not available'
                                    ) ?>

                                </span>

                            </div>


                            <div
                                class="
                                    doctor-info-row
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-user-doctor
                                    "
                                ></i>


                                <span>

                                    Doctor ID #

                                    <?= $doctorId ?>

                                </span>

                            </div>


                        </div>


                    </article>


                <?php endforeach; ?>


            </section>


        <?php else: ?>


            <div class="empty-state">


                <i
                    class="
                        fa-solid
                        fa-user-doctor
                    "
                ></i>


                <strong>

                    <?php if (
                        $search !== ''
                    ): ?>

                        No doctors found

                    <?php else: ?>

                        No medical team members yet

                    <?php endif; ?>

                </strong>


                <span>

                    <?php if (
                        $search !== ''
                    ): ?>

                        No doctor account matched your
                        current search.

                    <?php else: ?>

                        No users with the Doctor role
                        have been registered in MediCare yet.

                    <?php endif; ?>

                </span>


            </div>


        <?php endif; ?>


    </div>


</main>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const body =
            document.body;

        const menuButton =
            document.getElementById(
                'menuButton'
            );

        const overlay =
            document.getElementById(
                'overlay'
            );


        function closeMenu() {

            body.classList.remove(
                'menu-open'
            );


            if (menuButton) {

                const icon =
                    menuButton.querySelector(
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
        }


        if (menuButton) {

            menuButton.addEventListener(
                'click',
                function () {

                    const isOpen =
                        body.classList.toggle(
                            'menu-open'
                        );


                    const icon =
                        menuButton.querySelector(
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
        }


        if (overlay) {

            overlay.addEventListener(
                'click',
                closeMenu
            );
        }


        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth > 900
                ) {

                    closeMenu();
                }

            }
        );


        document
            .querySelectorAll(
                '.nav a'
            )
            .forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        closeMenu
                    );

                }
            );

    }
);

</script>


</body>

</html>