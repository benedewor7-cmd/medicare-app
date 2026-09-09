<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
*/

require_auth();


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId = current_user_id();

$dashboardError = '';

$user = null;

$stats = [
    'total'     => 0,
    'pending'   => 0,
    'confirmed' => 0,
    'completed' => 0,
];


/*
|--------------------------------------------------------------------------
| SESSION TERMINATION HELPER
|--------------------------------------------------------------------------
| Used when a logged-in account no longer exists or has been deactivated.
|--------------------------------------------------------------------------
*/

function terminate_dashboard_session(): never
{
    $_SESSION = [];


    if (
        ini_get('session.use_cookies')
    ) {

        $params =
            session_get_cookie_params();


        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }


    session_destroy();


    redirect(
        'login.php'
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD CURRENT USER
|--------------------------------------------------------------------------
*/

try {

    $userStmt = $pdo->prepare(
        "SELECT
            id,
            fullname,
            email,
            role,
            is_active
         FROM users
         WHERE id = ?
         LIMIT 1"
    );


    $userStmt->execute([
        $userId
    ]);


    $user =
        $userStmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |----------------------------------------------------------------------
    | USER NO LONGER EXISTS
    |----------------------------------------------------------------------
    */

    if (!$user) {

        error_log(
            'MediCare Security Warning: Logged-in user ID ' .
            $userId .
            ' no longer exists.'
        );


        terminate_dashboard_session();
    }


    /*
    |----------------------------------------------------------------------
    | ACCOUNT STATUS
    |----------------------------------------------------------------------
    */

    $isActive =
        (int) (
            $user['is_active']
            ?? 1
        ) === 1;


    if (!$isActive) {

        error_log(
            'MediCare Security Warning: Deactivated user ID ' .
            $userId .
            ' attempted to access dashboard.'
        );


        terminate_dashboard_session();
    }


    /*
    |----------------------------------------------------------------------
    | NORMALIZE ROLE
    |----------------------------------------------------------------------
    */

    $databaseRole =
        strtolower(
            trim(
                (string) (
                    $user['role'] ?? ''
                )
            )
        );


    $role =
        match (
            $databaseRole
        ) {

            'admin' =>
                'Admin',

            'doctor' =>
                'Doctor',

            'patient' =>
                'Patient',

            default =>
                '',
        };


    /*
    |----------------------------------------------------------------------
    | INVALID ROLE
    |----------------------------------------------------------------------
    */

    if (
        $role === ''
    ) {

        error_log(
            'MediCare Security Warning: Invalid role for user ID ' .
            $userId .
            ': ' .
            (string) (
                $user['role'] ?? ''
            )
        );


        terminate_dashboard_session();
    }


    /*
    |----------------------------------------------------------------------
    | APPOINTMENT STATISTICS
    |----------------------------------------------------------------------
    */

    if (
        $role === 'Doctor'
    ) {

        $statsStmt =
            $pdo->prepare(
                "SELECT
                    COUNT(*) AS total,

                    COALESCE(
                        SUM(status = 'Pending'),
                        0
                    ) AS pending,

                    COALESCE(
                        SUM(status = 'Confirmed'),
                        0
                    ) AS confirmed,

                    COALESCE(
                        SUM(status = 'Completed'),
                        0
                    ) AS completed

                 FROM appointments

                 WHERE doctor_id = ?"
            );


        $statsStmt->execute([
            $userId
        ]);


    } elseif (
        $role === 'Patient'
    ) {

        $statsStmt =
            $pdo->prepare(
                "SELECT
                    COUNT(*) AS total,

                    COALESCE(
                        SUM(status = 'Pending'),
                        0
                    ) AS pending,

                    COALESCE(
                        SUM(status = 'Confirmed'),
                        0
                    ) AS confirmed,

                    COALESCE(
                        SUM(status = 'Completed'),
                        0
                    ) AS completed

                 FROM appointments

                 WHERE user_id = ?"
            );


        $statsStmt->execute([
            $userId
        ]);


    } else {

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        $statsStmt =
            $pdo->query(
                "SELECT
                    COUNT(*) AS total,

                    COALESCE(
                        SUM(status = 'Pending'),
                        0
                    ) AS pending,

                    COALESCE(
                        SUM(status = 'Confirmed'),
                        0
                    ) AS confirmed,

                    COALESCE(
                        SUM(status = 'Completed'),
                        0
                    ) AS completed

                 FROM appointments"
            );
    }


    $stats =
        $statsStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$stats
    ) {

        $stats = [
            'total'     => 0,
            'pending'   => 0,
            'confirmed' => 0,
            'completed' => 0,
        ];
    }

} catch (
    PDOException $e
) {

    error_log(
        'MediCare Dashboard Error: ' .
        $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | DATABASE FAILURE
    |--------------------------------------------------------------------------
    | Do not destroy an otherwise valid authenticated session merely
    | because the statistics query temporarily failed.
    |--------------------------------------------------------------------------
    */

    $user = [
        'id'       => $userId,
        'fullname' =>
            $_SESSION['fullname']
            ?? 'User',
        'email'    =>
            $_SESSION['email']
            ?? '',
        'role'     =>
            $_SESSION['role']
            ?? 'Patient',
    ];


    $role =
        ucfirst(
            strtolower(
                trim(
                    (string) (
                        $user['role']
                        ?? 'Patient'
                    )
                )
            )
        );


    $stats = [
        'total'     => 0,
        'pending'   => 0,
        'confirmed' => 0,
        'completed' => 0,
    ];


    $dashboardError =
        'Some dashboard information is temporarily unavailable.';
}


/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$displayName =
    trim(
        (string) (
            $user['fullname']
            ?? 'User'
        )
    );


if (
    $displayName === ''
) {

    $displayName =
        'User';
}


$email =
    trim(
        (string) (
            $user['email']
            ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$userInitials = '';


$nameParts =
    preg_split(
        '/\s+/',
        $displayName
    );


if (
    is_array(
        $nameParts
    )
) {

    foreach (
        array_slice(
            $nameParts,
            0,
            2
        ) as $part
    ) {

        if (
            $part !== ''
        ) {

            $userInitials .=
                mb_strtoupper(
                    mb_substr(
                        $part,
                        0,
                        1
                    )
                );
        }
    }
}


if (
    $userInitials === ''
) {

    $userInitials =
        'U';
}


/*
|--------------------------------------------------------------------------
| ROLE-SPECIFIC VALUES
|--------------------------------------------------------------------------
*/

$isPatient =
    $role === 'Patient';

$isDoctor =
    $role === 'Doctor';

$isAdmin =
    $role === 'Admin';


/*
|--------------------------------------------------------------------------
| GREETING
|--------------------------------------------------------------------------
*/

$firstName =
    $displayName;


$namePartsForGreeting =
    preg_split(
        '/\s+/',
        $displayName
    );


if (
    is_array(
        $namePartsForGreeting
    ) &&
    isset(
        $namePartsForGreeting[0]
    ) &&
    trim(
        $namePartsForGreeting[0]
    ) !== ''
) {

    $firstName =
        trim(
            $namePartsForGreeting[0]
        );
}


/*
|--------------------------------------------------------------------------
| ROLE DESCRIPTION
|--------------------------------------------------------------------------
*/

$roleDescription =
    match (
        $role
    ) {

        'Admin' =>
            'Monitor the MediCare system, manage users and oversee healthcare operations.',

        'Doctor' =>
            'Review appointments, manage patients and maintain clinical records.',

        'Patient' =>
            'Manage your appointments, patient information and healthcare activity.',

        default =>
            'Access your MediCare healthcare portal securely.',
    };


/*
|--------------------------------------------------------------------------
| ROLE ICON
|--------------------------------------------------------------------------
*/

$roleIcon =
    match (
        $role
    ) {

        'Admin' =>
            'fa-shield-halved',

        'Doctor' =>
            'fa-user-doctor',

        'Patient' =>
            'fa-user',

        default =>
            'fa-user',
    };


/*
|--------------------------------------------------------------------------
| STAT CAPTIONS
|--------------------------------------------------------------------------
*/

$totalCaption =
    $isPatient
        ? 'Your recorded appointments'
        : (
            $isDoctor
                ? 'Your assigned appointments'
                : 'All system appointments'
        );


$pendingCaption =
    $isPatient
        ? 'Awaiting confirmation'
        : 'Awaiting action';


$confirmedCaption =
    $isPatient
        ? 'Your scheduled visits'
        : 'Scheduled visits';


$completedCaption =
    $isPatient
        ? 'Your completed visits'
        : 'Completed visits';


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfToken =
    csrf_token();

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
        content="MediCare secure healthcare management dashboard."
    >


    <title>
        Dashboard | MediCare
    </title>


    <!-- =========================================================
         SAVED THEME
         Dark mode remains the default.
    ========================================================== -->

    <script>

        (function () {

            try {

                const savedTheme =
                    localStorage.getItem(
                        'medicare-theme'
                    );


                if (
                    savedTheme === 'light'
                ) {

                    document.documentElement.setAttribute(
                        'data-theme',
                        'light'
                    );

                } else {

                    document.documentElement.removeAttribute(
                        'data-theme'
                    );
                }

            } catch (
                error
            ) {

                /*
                |--------------------------------------------------------------------------
                | Keep dark mode as the default if localStorage is unavailable.
                |--------------------------------------------------------------------------
                */
            }

        })();

    </script>


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


    <style>

        /* =========================================================
           ROOT — DARK MODE DEFAULT
        ========================================================= */

        :root {

            --bg:
                #031b2d;

            --sidebar:
                #05273e;

            --panel:
                #082f49;

            --panel-dark:
                #06283f;

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

            --muted-dark:
                #678392;

            --border:
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            /* =====================================================
               THEME SUPPORT VARIABLES
            ====================================================== */

            --body-text:
                #e1edf3;

            --body-bg:
                #031b2d;

            --sidebar-top:
                #05283f;

            --sidebar-bottom:
                #031b2d;

            --topbar-bg:
                rgba(
                    3,
                    27,
                    44,
                    0.86
                );

            --white-text:
                #ffffff;

            --soft-text:
                #b7ced8;

            --secondary-text:
                #9db7c3;

            --heading-text:
                #edf6f9;

            --card-text:
                #dceaf0;

            --label-text:
                #809ba9;

            --caption-text:
                #66808f;

            --panel-description:
                #718b99;

            --nav-text:
                #9db4c1;

            --nav-muted:
                #76919f;

            --nav-title:
                #648191;

            --hero-start:
                #073f51;

            --hero-middle:
                #072e42;

            --hero-end:
                #082940;

            --surface-soft:
                rgba(
                    255,
                    255,
                    255,
                    0.025
                );

            --surface-hover:
                rgba(
                    255,
                    255,
                    255,
                    0.045
                );

            --surface-strong:
                rgba(
                    255,
                    255,
                    255,
                    0.05
                );

            --shadow-color:
                rgba(
                    0,
                    0,
                    0,
                    0.18
                );

            --sidebar-width:
                255px;
        }


        /* =========================================================
           LIGHT THEME
           SAME UI / SAME STRUCTURE / COLOUR CHANGES ONLY
        ========================================================== */

        html[data-theme="light"] {

            --bg:
                #f4f8fb;

            --sidebar:
                #ffffff;

            --panel:
                #ffffff;

            --panel-dark:
                #f1f6f9;

            --primary:
                #0aa992;

            --blue:
                #237bd6;

            --green:
                #2fae68;

            --orange:
                #d98a18;

            --red:
                #d94d56;

            --purple:
                #7654d6;

            --white:
                #172b3a;

            --text:
                #233847;

            --muted:
                #627887;

            --muted-dark:
                #7f95a1;

            --border:
                rgba(
                    24,
                    55,
                    72,
                    0.12
                );

            --body-text:
                #233847;

            --body-bg:
                #f4f8fb;

            --sidebar-top:
                #ffffff;

            --sidebar-bottom:
                #f3f7fa;

            --topbar-bg:
                rgba(
                    255,
                    255,
                    255,
                    0.92
                );

            --white-text:
                #19313f;

            --soft-text:
                #56707e;

            --secondary-text:
                #607886;

            --heading-text:
                #183241;

            --card-text:
                #294454;

            --label-text:
                #607986;

            --caption-text:
                #718b97;

            --panel-description:
                #718792;

            --nav-text:
                #536c79;

            --nav-muted:
                #708795;

            --nav-title:
                #78909d;

            --hero-start:
                #e5f7f4;

            --hero-middle:
                #edf8f8;

            --hero-end:
                #f1f7fb;

            --surface-soft:
                rgba(
                    18,
                    49,
                    67,
                    0.035
                );

            --surface-hover:
                rgba(
                    18,
                    49,
                    67,
                    0.06
                );

            --surface-strong:
                rgba(
                    18,
                    49,
                    67,
                    0.055
                );

            --shadow-color:
                rgba(
                    31,
                    61,
                    78,
                    0.10
                );
        }


        /* =========================================================
           RESET
        ========================================================== */

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
                var(--body-text);

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

                var(--body-bg);

            overflow-x:
                hidden;

            transition:
                background-color .25s ease,
                color .25s ease;
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


        button {

            font:
                inherit;
        }


        /* =========================================================
           SIDEBAR
        ========================================================== */

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
                    var(--sidebar-top),
                    var(--sidebar-bottom)
                );

            border-right:
                1px solid
                var(--border);

            box-shadow:

                15px 0 45px
                var(--shadow-color);

            transition:
                transform .25s ease,
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        /* =========================================================
           BRAND
        ========================================================== */

        .brand {

            display:
                flex;

            align-items:
                center;

            width:
                100%;

            padding:
                0 10px;

            margin-bottom:
                34px;
        }


        .brand-logo {

            display:
                block;

            width:
                145px;

            max-width:
                100%;

            height:
                auto;

            max-height:
                58px;

            object-fit:
                contain;

            border-radius:
                8px;
        }


        /* =========================================================
           NAVIGATION
        ========================================================== */

        .nav-title {

            padding:
                0 11px;

            margin-bottom:
                10px;

            color:
                var(--nav-title);

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
                var(--nav-text);

            font-size:
                12px;

            font-weight:
                600;

            transition:
                .2s ease;
        }


        .nav a:hover {

            color:
                var(--white-text);

            background:
                var(--surface-hover);

            transform:
                translateX(2px);
        }


        .nav a.active {

            color:
                var(--white-text);

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
                var(--nav-muted);

            text-align:
                center;
        }


        .nav a.active i {

            color:
                var(--primary);
        }


        /* =========================================================
           SIDEBAR USER
        ========================================================== */

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
                var(--surface-soft);

            transition:
                background .25s ease,
                border-color .25s ease;
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

            flex-shrink:
                0;

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

            color:
                var(--white-text);

            font-size:
                11px;
        }


        .user-copy span {

            display:
                block;

            margin-top:
                2px;

            overflow:
                hidden;

            white-space:
                nowrap;

            text-overflow:
                ellipsis;

            color:
                var(--muted);

            font-size:
                8px;
        }


        .role-label {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

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

            min-height:
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

            transition:
                .2s ease;
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
        ========================================================== */

        .main {

            min-height:
                100vh;

            margin-left:
                var(--sidebar-width);
        }


        /* =========================================================
           TOPBAR
        ========================================================== */

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
                var(--topbar-bg);

            border-bottom:
                1px solid
                var(--border);

            backdrop-filter:
                blur(16px);

            transition:
                background .25s ease,
                border-color .25s ease;
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

            color:
                var(--white-text);

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
                var(--surface-soft);

            color:
                var(--muted);

            transition:
                color .2s ease,
                background .2s ease,
                border-color .2s ease;
        }


        .top-icon:hover {

            color:
                var(--primary);
        }


        .theme-toggle {

            border:
                1px solid
                var(--border);

            cursor:
                pointer;

            transition:
                color .2s ease,
                background .2s ease,
                border-color .2s ease;
        }


        .theme-toggle:hover {

            color:
                var(--primary);

            background:
                var(--surface-hover);
        }


        .theme-toggle i {

            pointer-events:
                none;
        }


        .menu-button {

            display:
                none;

            cursor:
                pointer;
        }


        /* =========================================================
           CONTENT
        ========================================================== */

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
           DASHBOARD ALERT
        ========================================================== */

        .dashboard-alert {

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

            border-radius:
                10px;

            color:
                #ffca70;

            background:
                rgba(
                    255,
                    184,
                    77,
                    0.07
                );

            border:
                1px solid
                rgba(
                    255,
                    184,
                    77,
                    0.14
                );

            font-size:
                9px;

            line-height:
                1.6;
        }


        /* =========================================================
           HERO
        ========================================================== */

        .hero {

            position:
                relative;

            overflow:
                hidden;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            min-height:
                175px;

            margin-bottom:
                18px;

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
                19px;

            background:

                linear-gradient(
                    135deg,
                    var(--hero-start),
                    var(--hero-middle) 55%,
                    var(--hero-end)
                );

            transition:
                background .25s ease,
                border-color .25s ease;
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
                -130px;

            top:
                -150px;

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

            box-shadow:

                0 0 0 45px
                rgba(
                    16,
                    199,
                    176,
                    0.015
                ),

                0 0 0 90px
                rgba(
                    16,
                    199,
                    176,
                    0.008
                );
        }


        .hero-content {

            position:
                relative;

            z-index:
                2;

            max-width:
                800px;
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
                var(--white-text);

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
                730px;

            margin-top:
                10px;

            color:
                var(--soft-text);

            font-size:
                11px;

            line-height:
                1.7;
        }


        .hero-user {

            position:
                relative;

            z-index:
                2;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            padding:
                10px 13px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            background:
                var(--surface-strong);

            transition:
                background .25s ease,
                border-color .25s ease;
        }


        .hero-avatar {

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

            color:
                white;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    #0ba8c5
                );

            font-size:
                11px;

            font-weight:
                800;
        }


        .hero-user-copy strong {

            display:
                block;

            color:
                var(--white-text);

            font-size:
                10px;

            max-width:
                180px;

            overflow:
                hidden;

            white-space:
                nowrap;

            text-overflow:
                ellipsis;
        }


        .hero-user-copy span {

            display:
                block;

            margin-top:
                3px;

            color:
                var(--secondary-text);

            font-size:
                8px;
        }


        /* =========================================================
           STATS
        ========================================================== */

        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                12px;

            margin-bottom:
                18px;
        }


        .stat-card {

            position:
                relative;

            min-height:
                115px;

            padding:
                19px;

            overflow:
                hidden;

            border:
                1px solid
                var(--border);

            border-radius:
                15px;

            background:

                linear-gradient(
                    145deg,
                    var(--panel),
                    var(--panel-dark)
                );

            transition:
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        .stat-icon {

            position:
                absolute;

            top:
                15px;

            right:
                15px;

            width:
                35px;

            height:
                35px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                9px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );
        }


        .stat-card.pending .stat-icon {

            color:
                var(--orange);

            background:
                rgba(
                    255,
                    184,
                    77,
                    0.08
                );
        }


        .stat-card.confirmed .stat-icon {

            color:
                var(--green);

            background:
                rgba(
                    85,
                    216,
                    144,
                    0.08
                );
        }


        .stat-card.completed .stat-icon {

            color:
                var(--blue);

            background:
                rgba(
                    44,
                    140,
                    255,
                    0.08
                );
        }


        .stat-label {

            display:
                block;

            color:
                var(--label-text);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .7px;

            text-transform:
                uppercase;
        }


        .stat-number {

            display:
                block;

            margin-top:
                9px;

            color:
                var(--white-text);

            font-size:
                28px;

            font-weight:
                800;
        }


        .stat-card.pending .stat-number {

            color:
                #ffca70;
        }


        .stat-card.confirmed .stat-number {

            color:
                #72e5ac;
        }


        .stat-card.completed .stat-number {

            color:
                #78bcff;
        }


        .stat-caption {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--caption-text);

            font-size:
                8px;
        }


        /* =========================================================
           QUICK ACTIONS PANEL
        ========================================================== */

        .panel {

            overflow:
                hidden;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            background:

                linear-gradient(
                    145deg,
                    var(--panel),
                    var(--panel-dark)
                );

            transition:
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        .panel-header {

            min-height:
                78px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                0 21px;

            border-bottom:
                1px solid
                var(--border);
        }


        .panel-header small {

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


        .panel-header h2 {

            margin-top:
                3px;

            color:
                var(--heading-text);

            font-size:
                17px;
        }


        .panel-header p {

            margin-top:
                4px;

            color:
                var(--panel-description);

            font-size:
                9px;
        }


        .panel-header-icon {

            width:
                34px;

            height:
                34px;

            display:
                grid;

            place-items:
                center;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.12
                );

            border-radius:
                9px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );
        }


        .action-grid {

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
                11px;

            padding:
                18px;
        }


        .action-card {

            min-height:
                125px;

            display:
                block;

            padding:
                16px;

            border:
                1px solid
                var(--border);

            border-radius:
                13px;

            background:
                var(--surface-soft);

            transition:
                transform .2s ease,
                border-color .2s ease,
                background .2s ease;
        }


        .action-card:hover {

            transform:
                translateY(-2px);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.18
                );

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.035
                );
        }


        .action-icon {

            width:
                38px;

            height:
                38px;

            display:
                grid;

            place-items:
                center;

            margin-bottom:
                11px;

            border-radius:
                10px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );
        }


        .action-card strong {

            display:
                block;

            margin-bottom:
                5px;

            color:
                var(--card-text);

            font-size:
                10px;

            font-weight:
                800;
        }


        .action-card span {

            color:
                var(--panel-description);

            font-size:
                8px;

            line-height:
                1.6;
        }


        /* =========================================================
           ACTION COLOR VARIANTS
        ========================================================== */

        .action-card.blue .action-icon {

            color:
                #78bcff;

            background:
                rgba(
                    44,
                    140,
                    255,
                    0.08
                );
        }


        .action-card.green .action-icon {

            color:
                #72e5ac;

            background:
                rgba(
                    85,
                    216,
                    144,
                    0.08
                );
        }


        .action-card.orange .action-icon {

            color:
                #ffca70;

            background:
                rgba(
                    255,
                    184,
                    77,
                    0.08
                );
        }


        .action-card.purple .action-icon {

            color:
                #c1a9ff;

            background:
                rgba(
                    169,
                    132,
                    255,
                    0.08
                );
        }


        /* =========================================================
           SECURITY / ROLE NOTE
        ========================================================== */

        .portal-note {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                8px;

            margin:
                0 18px 18px;

            padding:
                12px 13px;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );

            border-radius:
                10px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.025
                );

            color:
                var(--caption-text);

            font-size:
                8px;

            line-height:
                1.7;

            transition:
                background .25s ease,
                border-color .25s ease,
                color .25s ease;
        }


        .portal-note i {

            flex-shrink:
                0;

            margin-top:
                1px;

            color:
                var(--primary);
        }


        .portal-note strong {

            color:
                var(--heading-text);
        }


        /* =========================================================
           THEME TRANSITIONS
        ========================================================== */

        .sidebar,
        .topbar,
        .panel,
        .stat-card,
        .action-card,
        .user-box,
        .top-icon,
        .hero,
        .hero-user,
        .portal-note {

            transition:
                background-color .25s ease,
                background .25s ease,
                color .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 1100px) {

            :root {

                --sidebar-width:
                    235px;
            }


            .content {

                padding:
                    25px;
            }


            .action-grid {

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


            .stats {

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

                align-items:
                    flex-start;

                flex-direction:
                    column;

                padding:
                    25px 20px;
            }


            .hero-user {

                width:
                    100%;
            }


            .action-grid {

                grid-template-columns:
                    1fr;
            }


            .panel-header {

                min-height:
                    74px;
            }

        }


        @media (max-width: 460px) {

            .top-icon {

                display:
                    none;
            }


            /*
            |--------------------------------------------------------------------------
            | Keep the theme toggle visible on small screens.
            |--------------------------------------------------------------------------
            */

            .theme-toggle {

                display:
                    grid;
            }


            .stats {

                grid-template-columns:
                    1fr;
            }


            .hero {

                padding:
                    23px 18px;
            }


            .hero-user {

                padding:
                    9px 11px;
            }


            .action-grid {

                padding:
                    15px;
            }


            .portal-note {

                margin:
                    0 15px 15px;
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
        aria-label="MediCare Dashboard"
    >

        <img
            src="images/logo.jpg"
            alt="MediCare Logo"
            class="brand-logo"
        >

    </a>


    <!-- NAV TITLE -->

    <div class="nav-title">
        Main Menu
    </div>


    <!-- NAVIGATION -->

    <nav class="nav">


        <a
            href="dashboard.php"
            class="active"
        >

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


        <?php if (
            $isPatient
        ): ?>

            <a href="book_appointment.php">

                <i class="fa-solid fa-calendar-plus"></i>

                <span>
                    Book Appointment
                </span>

            </a>

        <?php endif; ?>


        <?php if (
            $isAdmin ||
            $isDoctor
        ): ?>

            <a href="patients.php">

                <i class="fa-solid fa-users"></i>

                <span>
                    Patients
                </span>

            </a>


            <a href="add_patient.php">

                <i class="fa-solid fa-user-plus"></i>

                <span>
                    Register Patient
                </span>

            </a>

        <?php endif; ?>


        <?php if (
            $isAdmin
        ): ?>

            <a href="manage_users.php">

                <i class="fa-solid fa-user-gear"></i>

                <span>
                    Manage Users
                </span>

            </a>

        <?php endif; ?>


        <?php if (
            $isPatient
        ): ?>

            <a href="patient_profile.php">

                <i class="fa-solid fa-id-card"></i>

                <span>
                    My Profile
                </span>

            </a>

        <?php else: ?>

            <a href="profile.php">

                <i class="fa-solid fa-user-doctor"></i>

                <span>
                    My Profile
                </span>

            </a>

        <?php endif; ?>


    </nav>


    <!-- SPACER -->

    <div class="sidebar-spacer"></div>


    <!-- USER BOX -->

    <div class="user-box">


        <div class="user-info">


            <div class="avatar">

                <?= e(
                    $userInitials
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
                        $email
                    ) ?>

                </span>

            </div>


        </div>


        <span class="role-label">

            <i
                class="
                    fa-solid
                    <?= e($roleIcon) ?>
                "
            ></i>

            <?= e(
                $role
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
                    $csrfToken
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


    <!-- =======================================================
         TOPBAR
    ======================================================== -->

    <header class="topbar">


        <div class="top-title">

            <small>
                MediCare Portal
            </small>

            <h2>
                Dashboard
            </h2>

        </div>


        <div class="top-actions">


            <button
                type="button"
                class="menu-button"
                id="menuButton"
                aria-label="Open menu"
            >

                <i
                    class="
                        fa-solid
                        fa-bars
                    "
                ></i>

            </button>


            <!-- =================================================
                 THEME TOGGLE
            ================================================== -->

            <button
                type="button"
                class="top-icon theme-toggle"
                id="themeToggle"
                aria-label="Switch to light mode"
                title="Switch to light mode"
            >

                <i
                    class="
                        fa-solid
                        fa-sun
                    "
                    id="themeIcon"
                ></i>

            </button>


            <a
                href="appointments.php"
                class="top-icon"
                title="Appointments"
            >

                <i
                    class="
                        fa-regular
                        fa-calendar-check
                    "
                ></i>

            </a>


            <?php if (
                $isPatient
            ): ?>

                <a
                    href="book_appointment.php"
                    class="top-icon"
                    title="Book appointment"
                >

                    <i
                        class="
                            fa-solid
                            fa-calendar-plus
                        "
                    ></i>

                </a>

            <?php endif; ?>


            <?php if (
                $isAdmin
            ): ?>

                <a
                    href="manage_users.php"
                    class="top-icon"
                    title="Manage users"
                >

                    <i
                        class="
                            fa-solid
                            fa-user-gear
                        "
                    ></i>

                </a>

            <?php endif; ?>


        </div>


    </header>


    <!-- =======================================================
         CONTENT
    ======================================================== -->

    <div class="content">


        <!-- =================================================
             DASHBOARD ERROR
        ================================================== -->

        <?php if (
            $dashboardError !== ''
        ): ?>

            <div
                class="dashboard-alert"
                role="status"
            >

                <i
                    class="
                        fa-solid
                        fa-triangle-exclamation
                    "
                ></i>

                <span>

                    <?= e(
                        $dashboardError
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="hero">


            <div class="hero-content">


                <div class="eyebrow">

                    MediCare Portal

                </div>


                <h1>

                    Welcome back,
                    <?= e(
                        $firstName
                    ) ?>.

                </h1>


                <p>

                    <?= e(
                        $roleDescription
                    ) ?>

                </p>


            </div>


            <div class="hero-user">


                <div class="hero-avatar">

                    <?= e(
                        $userInitials
                    ) ?>

                </div>


                <div class="hero-user-copy">

                    <strong>

                        <?= e(
                            $displayName
                        ) ?>

                    </strong>


                    <span>

                        <?= e(
                            $role
                        ) ?>

                    </span>

                </div>


            </div>


        </section>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section
            class="stats"
            aria-label="Appointment statistics"
        >


            <!-- TOTAL -->

            <article class="stat-card">


                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-calendar-days
                        "
                    ></i>

                </div>


                <span class="stat-label">

                    Total Appointments

                </span>


                <strong class="stat-number">

                    <?= (int) (
                        $stats['total']
                        ?? 0
                    ) ?>

                </strong>


                <span class="stat-caption">

                    <?= e(
                        $totalCaption
                    ) ?>

                </span>


            </article>


            <!-- PENDING -->

            <article
                class="
                    stat-card
                    pending
                "
            >


                <div class="stat-icon">

                    <i
                        class="
                            fa-regular
                            fa-clock
                        "
                    ></i>

                </div>


                <span class="stat-label">

                    Pending

                </span>


                <strong class="stat-number">

                    <?= (int) (
                        $stats['pending']
                        ?? 0
                    ) ?>

                </strong>


                <span class="stat-caption">

                    <?= e(
                        $pendingCaption
                    ) ?>

                </span>


            </article>


            <!-- CONFIRMED -->

            <article
                class="
                    stat-card
                    confirmed
                "
            >


                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-circle-check
                        "
                    ></i>

                </div>


                <span class="stat-label">

                    Confirmed

                </span>


                <strong class="stat-number">

                    <?= (int) (
                        $stats['confirmed']
                        ?? 0
                    ) ?>

                </strong>


                <span class="stat-caption">

                    <?= e(
                        $confirmedCaption
                    ) ?>

                </span>


            </article>


            <!-- COMPLETED -->

            <article
                class="
                    stat-card
                    completed
                "
            >


                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-check-double
                        "
                    ></i>

                </div>


                <span class="stat-label">

                    Completed

                </span>


                <strong class="stat-number">

                    <?= (int) (
                        $stats['completed']
                        ?? 0
                    ) ?>

                </strong>


                <span class="stat-caption">

                    <?= e(
                        $completedCaption
                    ) ?>

                </span>


            </article>


        </section>


        <!-- =================================================
             QUICK ACTIONS
        ================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <small>
                        Quick Access
                    </small>

                    <h2>
                        What would you like to do?
                    </h2>

                    <p>
                        Access the MediCare functions available
                        for your current account.
                    </p>

                </div>


                <div class="panel-header-icon">

                    <i
                        class="
                            fa-solid
                            fa-bolt
                        "
                    ></i>

                </div>


            </header>


            <div class="action-grid">


                <!-- APPOINTMENTS -->

                <a
                    href="appointments.php"
                    class="action-card"
                >


                    <div class="action-icon">

                        <i
                            class="
                                fa-regular
                                fa-calendar-check
                            "
                        ></i>

                    </div>


                    <strong>

                        Appointments

                    </strong>


                    <span>


                        <?php if (
                            $isPatient
                        ): ?>

                            View and manage your
                            appointment requests.

                        <?php elseif (
                            $isDoctor
                        ): ?>

                            Review and manage
                            scheduled patient visits.

                        <?php else: ?>

                            Review and manage all
                            system appointments.

                        <?php endif; ?>


                    </span>


                </a>


                <!-- BOOK APPOINTMENT -->

                <?php if (
                    $isPatient
                ): ?>

                    <a
                        href="book_appointment.php"
                        class="
                            action-card
                            blue
                        "
                    >


                        <div class="action-icon">

                            <i
                                class="
                                    fa-solid
                                    fa-calendar-plus
                                "
                            ></i>

                        </div>


                        <strong>

                            Book Appointment

                        </strong>


                        <span>

                            Request a new consultation
                            with an available doctor.

                        </span>


                    </a>

                <?php endif; ?>


                <!-- PATIENTS -->

                <?php if (
                    $isAdmin ||
                    $isDoctor
                ): ?>

                    <a
                        href="patients.php"
                        class="
                            action-card
                            green
                        "
                    >


                        <div class="action-icon">

                            <i
                                class="
                                    fa-solid
                                    fa-users
                                "
                            ></i>

                        </div>


                        <strong>

                            Patient List

                        </strong>


                        <span>

                            View and manage registered
                            patient records.

                        </span>


                    </a>

                <?php endif; ?>


                <!-- REGISTER PATIENT -->

                <?php if (
                    $isAdmin ||
                    $isDoctor
                ): ?>

                    <a
                        href="add_patient.php"
                        class="
                            action-card
                            green
                        "
                    >


                        <div class="action-icon">

                            <i
                                class="
                                    fa-solid
                                    fa-user-plus
                                "
                            ></i>

                        </div>


                        <strong>

                            Register Patient

                        </strong>


                        <span>

                            Create a new patient
                            healthcare record.

                        </span>


                    </a>

                <?php endif; ?>


                <!-- MANAGE USERS -->

                <?php if (
                    $isAdmin
                ): ?>

                    <a
                        href="manage_users.php"
                        class="
                            action-card
                            orange
                        "
                    >


                        <div class="action-icon">

                            <i
                                class="
                                    fa-solid
                                    fa-user-gear
                                "
                            ></i>

                        </div>


                        <strong>

                            Manage Users

                        </strong>


                        <span>

                            Manage accounts and
                            controlled system access.

                        </span>


                    </a>

                <?php endif; ?>


                <!-- PROFILE -->

                <a
                    href="<?= e(
                        $isPatient
                            ? 'patient_profile.php'
                            : 'profile.php'
                    ) ?>"
                    class="
                        action-card
                        purple
                    "
                >


                    <div class="action-icon">

                        <i
                            class="
                                fa-solid
                                fa-user-shield
                            "
                        ></i>

                    </div>


                    <strong>

                        My Profile

                    </strong>


                    <span>

                        View and update your
                        account information.

                    </span>


                </a>


            </div>


            <!-- SECURITY NOTE -->

            <div class="portal-note">


                <i
                    class="
                        fa-solid
                        fa-shield-heart
                    "
                ></i>


                <span>

                    You are securely signed in as

                    <strong>
                        <?= e(
                            $role
                        ) ?>
                    </strong>.

                    The dashboard options shown above are
                    controlled by your account permissions.

                </span>


            </div>


        </section>


    </div>


</main>


<!-- =========================================================
     OVERLAY
========================================================= -->

<div
    class="overlay"
    id="overlay"
></div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const body =
            document.body;


        /* =====================================================
           THEME SYSTEM
        ====================================================== */

        const root =
            document.documentElement;


        const themeToggle =
            document.getElementById(
                'themeToggle'
            );


        const themeIcon =
            document.getElementById(
                'themeIcon'
            );


        function applyTheme(
            theme
        ) {

            if (
                theme === 'light'
            ) {

                root.setAttribute(
                    'data-theme',
                    'light'
                );


                if (
                    themeIcon
                ) {

                    themeIcon.classList.remove(
                        'fa-sun'
                    );


                    themeIcon.classList.add(
                        'fa-moon'
                    );
                }


                if (
                    themeToggle
                ) {

                    themeToggle.setAttribute(
                        'aria-label',
                        'Switch to dark mode'
                    );


                    themeToggle.setAttribute(
                        'title',
                        'Switch to dark mode'
                    );
                }

            } else {

                root.removeAttribute(
                    'data-theme'
                );


                if (
                    themeIcon
                ) {

                    themeIcon.classList.remove(
                        'fa-moon'
                    );


                    themeIcon.classList.add(
                        'fa-sun'
                    );
                }


                if (
                    themeToggle
                ) {

                    themeToggle.setAttribute(
                        'aria-label',
                        'Switch to light mode'
                    );


                    themeToggle.setAttribute(
                        'title',
                        'Switch to light mode'
                    );
                }
            }
        }


        let currentTheme =
            'dark';


        try {

            currentTheme =
                localStorage.getItem(
                    'medicare-theme'
                ) || 'dark';

        } catch (
            error
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

                    const isLight =
                        root.getAttribute(
                            'data-theme'
                        ) === 'light';


                    const newTheme =
                        isLight
                            ? 'dark'
                            : 'light';


                    applyTheme(
                        newTheme
                    );


                    try {

                        localStorage.setItem(
                            'medicare-theme',
                            newTheme
                        );

                    } catch (
                        error
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | Ignore localStorage failures.
                        |--------------------------------------------------------------------------
                        */
                    }

                }
            );
        }


        /* =====================================================
           MOBILE MENU
        ====================================================== */

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


            if (
                menuButton
            ) {

                const icon =
                    menuButton.querySelector(
                        'i'
                    );


                if (
                    icon
                ) {

                    icon.classList.remove(
                        'fa-xmark'
                    );


                    icon.classList.add(
                        'fa-bars'
                    );
                }
            }
        }


        if (
            menuButton
        ) {

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


                    if (
                        icon
                    ) {

                        if (
                            isOpen
                        ) {

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


        if (
            overlay
        ) {

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