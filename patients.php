<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Admins and Doctors can access the patient list.
|--------------------------------------------------------------------------
*/

require_role('Admin', 'Doctor');


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId = current_user_id();

$displayName = (string) (
    $_SESSION['fullname'] ?? 'User'
);

$email = (string) (
    $_SESSION['email'] ?? ''
);

$role = ucfirst(
    strtolower(
        trim(
            (string) (
                $_SESSION['role'] ?? ''
            )
        )
    )
);


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
    150
);


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$patients = [];

$error = '';

$success = '';


/*
|--------------------------------------------------------------------------
| STATUS / MESSAGE
|--------------------------------------------------------------------------
*/

$statusMessage = trim(
    (string) (
        $_GET['status'] ?? ''
    )
);

$actionMessage = trim(
    (string) (
        $_GET['message'] ?? ''
    )
);


if (
    $actionMessage !== ''
) {

    $success =
        $actionMessage;

} elseif (
    $statusMessage === 'active'
) {

    $success =
        $actionMessage !== ''
            ? $actionMessage
            : 'Patient account is active.';

} elseif (
    $statusMessage === 'inactive'
) {

    $success =
        $actionMessage !== ''
            ? $actionMessage
            : 'Patient account is inactive.';
}


/*
|--------------------------------------------------------------------------
| LOAD PATIENTS
|--------------------------------------------------------------------------
*/

try {

    if (
        $search !== ''
    ) {

        $searchTerm =
            '%' .
            $search .
            '%';


        $stmt =
            $pdo->prepare(
                "SELECT
                    id,
                    user_id,
                    first_name,
                    last_name,
                    dob,
                    gender,
                    phone,
                    address,
                    medical_history,
                    is_active,
                    created_at
                 FROM patients
                 WHERE
                    first_name LIKE ?
                    OR last_name LIKE ?
                    OR phone LIKE ?
                 ORDER BY
                    id DESC"
            );


        $stmt->execute([
            $searchTerm,
            $searchTerm,
            $searchTerm
        ]);

    } else {

        $stmt =
            $pdo->query(
                "SELECT
                    id,
                    user_id,
                    first_name,
                    last_name,
                    dob,
                    gender,
                    phone,
                    address,
                    medical_history,
                    is_active,
                    created_at
                 FROM patients
                 ORDER BY
                    id DESC"
            );
    }


    $patients =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    PDOException $e
) {

    error_log(
        'MediCare patient list error: ' .
        $e->getMessage()
    );


    $error =
        'Unable to load the patient list. Please try again.';
}


/*
|--------------------------------------------------------------------------
| PATIENT COUNTS
|--------------------------------------------------------------------------
*/

$totalPatients =
    count(
        $patients
    );


$activePatients = 0;

$inactivePatients = 0;


foreach (
    $patients as $patient
) {

    $isActive =
        (int) (
            $patient['is_active'] ?? 1
        ) === 1;


    if (
        $isActive
    ) {

        $activePatients++;

    } else {

        $inactivePatients++;
    }
}


/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$userInitials = '';

$nameParts =
    preg_split(
        '/\s+/',
        trim(
            $displayName
        )
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
| HELPER: PATIENT NAME
|--------------------------------------------------------------------------
*/

function patientListFullName(
    array $patient
): string {

    $name =
        trim(
            (string) (
                $patient['first_name']
                ?? ''
            )
            .
            ' '
            .
            (string) (
                $patient['last_name']
                ?? ''
            )
        );


    return
        $name !== ''
            ? $name
            : 'Unnamed Patient';
}


/*
|--------------------------------------------------------------------------
| HELPER: PATIENT INITIAL
|--------------------------------------------------------------------------
*/

function patientListInitial(
    string $name
): string {

    $name =
        trim(
            $name
        );


    if (
        $name === ''
    ) {

        return 'P';
    }


    return mb_strtoupper(
        mb_substr(
            $name,
            0,
            1
        )
    );
}


/*
|--------------------------------------------------------------------------
| HELPER: SAFE DOB DISPLAY
|--------------------------------------------------------------------------
*/

function patientListDob(
    string $dob
): string {

    $dob =
        trim(
            $dob
        );


    if (
        $dob === ''
    ) {

        return 'Not provided';
    }


    try {

        return
            (new DateTimeImmutable(
                $dob
            ))->format(
                'M d, Y'
            );

    } catch (
        Throwable $e
    ) {

        return 'Date unavailable';
    }
}


/*
|--------------------------------------------------------------------------
| CSRF
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
        content="MediCare patient management and healthcare records."
    >


    <title>
        Patients | MediCare
    </title>


    <!-- =========================================================
         MEDICARE THEME RESTORE
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
                | Dark mode remains the default if localStorage is unavailable.
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
        ========================================================== */

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

            --body-bg:
                #031b2d;

            --body-text:
                #e1edf3;

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

            --heading-text:
                #edf6f9;

            --card-text:
                #dceaf0;

            --label-text:
                #809ba9;

            --caption-text:
                #66808f;

            --muted-text:
                #6d8896;

            --nav-text:
                #9db4c1;

            --nav-muted:
                #76919f;

            --nav-title:
                #648191;

            --table-heading:
                #7993a1;

            --table-text:
                #a9bec8;

            --table-strong:
                #d8e6ec;

            --table-muted:
                #829ba9;

            --input-bg:
                rgba(
                    2,
                    22,
                    36,
                    0.62
                );

            --input-border:
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            --input-text:
                #dceaf0;

            --input-placeholder:
                #607b8a;

            --surface-soft:
                rgba(
                    255,
                    255,
                    255,
                    0.025
                );

            --surface-strong:
                rgba(
                    255,
                    255,
                    255,
                    0.03
                );

            --surface-hover:
                rgba(
                    255,
                    255,
                    255,
                    0.045
                );

            --panel-start:
                rgba(
                    8,
                    47,
                    70,
                    0.95
                );

            --panel-end:
                rgba(
                    5,
                    31,
                    48,
                    0.97
                );

            --stat-start:
                rgba(
                    8,
                    48,
                    71,
                    0.95
                );

            --stat-end:
                rgba(
                    5,
                    31,
                    49,
                    0.96
                );

            --hero-start:
                #073f51;

            --hero-middle:
                #072e42;

            --hero-end:
                #082940;

            --shadow-color:
                rgba(
                    0,
                    0,
                    0,
                    0.18
                );

            --row-border:
                rgba(
                    255,
                    255,
                    255,
                    0.055
                );

            --sidebar-width:
                255px;
        }


        /* =========================================================
           LIGHT THEME
           SAME UI / SAME STRUCTURE
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

            --body-bg:
                #f4f8fb;

            --body-text:
                #233847;

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

            --heading-text:
                #183241;

            --card-text:
                #294454;

            --label-text:
                #607986;

            --caption-text:
                #718b97;

            --muted-text:
                #6d8290;

            --nav-text:
                #536c79;

            --nav-muted:
                #708795;

            --nav-title:
                #78909d;

            --table-heading:
                #607987;

            --table-text:
                #617783;

            --table-strong:
                #294454;

            --table-muted:
                #718792;

            --input-bg:
                #ffffff;

            --input-border:
                rgba(
                    24,
                    55,
                    72,
                    0.14
                );

            --input-text:
                #294454;

            --input-placeholder:
                #7d929e;

            --surface-soft:
                rgba(
                    18,
                    49,
                    67,
                    0.035
                );

            --surface-strong:
                rgba(
                    18,
                    49,
                    67,
                    0.055
                );

            --surface-hover:
                rgba(
                    18,
                    49,
                    67,
                    0.06
                );

            --panel-start:
                rgba(
                    255,
                    255,
                    255,
                    1
                );

            --panel-end:
                rgba(
                    247,
                    250,
                    252,
                    1
                );

            --stat-start:
                rgba(
                    255,
                    255,
                    255,
                    1
                );

            --stat-end:
                rgba(
                    247,
                    250,
                    252,
                    1
                );

            --hero-start:
                #e5f7f4;

            --hero-middle:
                #edf8f8;

            --hero-end:
                #f1f7fb;

            --shadow-color:
                rgba(
                    31,
                    61,
                    78,
                    0.10
                );

            --row-border:
                rgba(
                    24,
                    55,
                    72,
                    0.08
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


        button,
        input {

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

            background:
                var(--surface-hover);
        }


        .menu-button {

            display:
                none;

            cursor:
                pointer;
        }


        /* =========================================================
           THEME TOGGLE
        ========================================================== */

        .theme-toggle {

            border:
                1px solid
                var(--border);

            cursor:
                pointer;

            appearance:
                none;

            -webkit-appearance:
                none;
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
                160px;

            margin-bottom:
                18px;

            padding:
                28px 32px;

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
                300px;

            height:
                300px;

            right:
                -105px;

            top:
                -145px;

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


        .hero-content {

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


        .hero-action {

            position:
                relative;

            z-index:
                2;

            flex-shrink:
                0;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            min-height:
                46px;

            padding:
                0 17px;

            border-radius:
                9px;

            color:
                white;

            background:

                linear-gradient(
                    135deg,
                    var(--primary),
                    #10b4d3
                );

            font-size:
                9px;

            font-weight:
                800;

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }


        .hero-action:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 12px 28px
                rgba(
                    16,
                    199,
                    176,
                    0.14
                );
        }


        /* =========================================================
           ALERTS
        ========================================================== */

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

            border-radius:
                10px;

            font-size:
                9px;

            line-height:
                1.6;
        }


        .alert-success {

            color:
                #74e4aa;

            background:
                rgba(
                    85,
                    216,
                    144,
                    0.07
                );

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    0.13
                );
        }


        .alert-error {

            color:
                #ff989e;

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.07
                );

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    0.13
                );
        }


        /* =========================================================
           STATS
        ========================================================== */

        .stats {

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
                12px;

            margin-bottom:
                18px;
        }


        .stat-card {

            position:
                relative;

            min-height:
                110px;

            padding:
                19px;

            border:
                1px solid
                var(--border);

            border-radius:
                15px;

            background:

                linear-gradient(
                    145deg,
                    var(--stat-start),
                    var(--stat-end)
                );

            transition:
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        .stat-icon {

            position:
                absolute;

            right:
                15px;

            top:
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


        .stat-card.active .stat-icon {

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


        .stat-card.inactive .stat-icon {

            color:
                var(--red);

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.08
                );
        }


        .stat-label {

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


        .stat-card.active .stat-number {

            color:
                #72e5ac;
        }


        .stat-card.inactive .stat-number {

            color:
                #ff9298;
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
           PANEL
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
                    var(--panel-start),
                    var(--panel-end)
                );

            transition:
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        /* =========================================================
           FILTER HEADER
        ========================================================== */

        .filter-area {

            padding:
                18px;

            border-bottom:
                1px solid
                var(--border);
        }


        .filter-form {

            display:
                grid;

            grid-template-columns:
                minmax(
                    240px,
                    1fr
                )
                100px;

            gap:
                9px;
        }


        .filter-input {

            width:
                100%;

            height:
                43px;

            padding:
                0 12px;

            border:
                1px solid
                var(--input-border);

            border-radius:
                9px;

            outline:
                none;

            background:
                var(--input-bg);

            color:
                var(--input-text);

            font-size:
                10px;

            transition:
                border-color .2s ease,
                background .25s ease,
                color .25s ease,
                box-shadow .2s ease;
        }


        .filter-input::placeholder {

            color:
                var(--input-placeholder);
        }


        .filter-input:focus {

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.48
                );

            box-shadow:
                0 0 0 3px
                rgba(
                    16,
                    199,
                    176,
                    0.07
                );
        }


        .filter-button,
        .clear-button {

            height:
                43px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                9px;

            font-size:
                9px;

            font-weight:
                800;
        }


        .filter-button {

            border:
                none;

            color:
                white;

            background:

                linear-gradient(
                    135deg,
                    var(--primary),
                    #10b4d3
                );

            cursor:
                pointer;
        }


        .clear-button {

            margin-left:
                8px;

            min-width:
                80px;

            padding:
                0 12px;

            border:
                1px solid
                var(--border);

            color:
                var(--muted);

            background:
                var(--surface-soft);

            transition:
                .2s ease;
        }


        .clear-button:hover {

            color:
                var(--primary);
        }


        /* =========================================================
           RESULTS BAR
        ========================================================== */

        .results-bar {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                13px 18px;

            border-bottom:
                1px solid
                var(--row-border);

            transition:
                border-color .25s ease;
        }


        .results-text {

            color:
                var(--muted-text);

            font-size:
                8px;
        }


        .results-text strong {

            color:
                var(--table-text);
        }


        .results-filter {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

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
                    0.12
                );

            font-size:
                8px;

            font-weight:
                800;
        }


        /* =========================================================
           TABLE
        ========================================================== */

        .table-wrapper {

            width:
                100%;

            overflow-x:
                auto;
        }


        .patients-table {

            width:
                100%;

            min-width:
                1120px;

            border-collapse:
                collapse;
        }


        .patients-table th {

            padding:
                14px 15px;

            text-align:
                left;

            white-space:
                nowrap;

            color:
                var(--table-heading);

            background:
                var(--surface-soft);

            border-bottom:
                1px solid
                var(--border);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .7px;

            text-transform:
                uppercase;
        }


        .patients-table td {

            padding:
                15px;

            color:
                var(--table-text);

            border-bottom:
                1px solid
                var(--row-border);

            font-size:
                9px;

            vertical-align:
                middle;
        }


        .patients-table tbody tr {

            transition:
                background .2s ease;
        }


        .patients-table tbody tr:hover {

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.025
                );
        }


        .patients-table tbody tr:last-child td {

            border-bottom:
                none;
        }


        /* =========================================================
           PATIENT CELL
        ========================================================== */

        .patient-cell {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            min-width:
                195px;
        }


        .patient-avatar {

            width:
                37px;

            height:
                37px;

            flex-shrink:
                0;

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
                    0.10
                );

            font-size:
                9px;

            font-weight:
                800;
        }


        .patient-name {

            display:
                block;

            max-width:
                220px;

            overflow:
                hidden;

            white-space:
                nowrap;

            text-overflow:
                ellipsis;

            color:
                var(--card-text);

            font-size:
                10px;

            font-weight:
                700;
        }


        .patient-id {

            display:
                block;

            margin-top:
                3px;

            color:
                var(--caption-text);

            font-size:
                8px;
        }


        /* =========================================================
           INFO CELLS
        ========================================================== */

        .date-main {

            display:
                block;

            color:
                var(--table-strong);

            font-size:
                9px;

            font-weight:
                700;
        }


        .date-sub {

            display:
                block;

            margin-top:
                3px;

            color:
                var(--caption-text);

            font-size:
                7px;
        }


        .phone {

            color:
                var(--table-text);

            font-size:
                9px;
        }


        .address {

            max-width:
                180px;

            color:
                var(--table-muted);

            font-size:
                8px;

            line-height:
                1.6;
        }


        .history {

            max-width:
                230px;

            color:
                var(--table-muted);

            font-size:
                8px;

            line-height:
                1.6;
        }


        /* =========================================================
           GENDER BADGE
        ========================================================== */

        .gender-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                6px 9px;

            border-radius:
                999px;

            color:
                var(--table-muted);

            background:
                var(--surface-soft);

            border:
                1px solid
                var(--border);

            font-size:
                7px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        /* =========================================================
           STATUS
        ========================================================== */

        .status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                6px 9px;

            border-radius:
                999px;

            font-size:
                7px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .status::before {

            content:
                "";

            width:
                5px;

            height:
                5px;

            border-radius:
                50%;

            background:
                currentColor;
        }


        .status-active {

            color:
                #72e5ac;

            background:
                rgba(
                    85,
                    216,
                    144,
                    0.08
                );

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    0.13
                );
        }


        .status-inactive {

            color:
                #ff9298;

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.08
                );

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    0.13
                );
        }


        /* =========================================================
           ACTIONS
        ========================================================== */

        .actions {

            display:
                flex;

            align-items:
                center;

            flex-wrap:
                wrap;

            gap:
                5px;
        }


        .action-link {

            min-height:
                31px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                5px;

            padding:
                0 9px;

            border-radius:
                7px;

            font-size:
                7px;

            font-weight:
                800;

            transition:
                transform .15s ease,
                opacity .2s ease;
        }


        .action-link:hover {

            transform:
                translateY(-1px);

            opacity:
                .88;
        }


        .view-action {

            color:
                #78bcff;

            background:
                rgba(
                    44,
                    140,
                    255,
                    0.08
                );

            border:
                1px solid
                rgba(
                    44,
                    140,
                    255,
                    0.13
                );
        }


        .edit-action {

            color:
                #72e5ac;

            background:
                rgba(
                    85,
                    216,
                    144,
                    0.08
                );

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    0.13
                );
        }


        /* =========================================================
           EMPTY STATE
        ========================================================== */

        .empty-state {

            padding:
                65px 20px !important;

            text-align:
                center;

            color:
                var(--muted-text) !important;
        }


        .empty-state i {

            display:
                block;

            margin-bottom:
                12px;

            color:
                #4e6b79;

            font-size:
                30px;
        }


        .empty-state strong {

            display:
                block;

            margin-bottom:
                5px;

            color:
                var(--card-text);

            font-size:
                12px;
        }


        .empty-state span {

            display:
                block;

            font-size:
                9px;

            line-height:
                1.7;
        }


        /* =========================================================
           FOOTER
        ========================================================== */

        .table-footer {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

            padding:
                15px 18px;

            border-top:
                1px solid
                var(--border);

            color:
                var(--caption-text);

            font-size:
                8px;
        }


        /* =========================================================
           MOBILE OVERLAY
        ========================================================== */

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
                    0.65
                );

            backdrop-filter:
                blur(3px);
        }


        /* =========================================================
           THEME TRANSITIONS
        ========================================================== */

        .sidebar,
        .topbar,
        .hero,
        .stat-card,
        .panel,
        .user-box,
        .top-icon,
        .filter-input,
        .patients-table th,
        .patients-table td,
        .table-footer,
        .results-bar {

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


            .stats {

                grid-template-columns:
                    repeat(
                        3,
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

                align-items:
                    flex-start;

                flex-direction:
                    column;

                padding:
                    25px 20px;
            }


            .hero-action {

                width:
                    100%;
            }


            .stats {

                grid-template-columns:
                    1fr;
            }


            .filter-form {

                grid-template-columns:
                    1fr;
            }


            .filter-button {

                width:
                    100%;
            }


            .clear-button {

                width:
                    100%;

                margin:
                    0;
            }


            .results-bar {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .table-footer {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }

        }


        @media (max-width: 460px) {

            .top-icon {

                display:
                    none;
            }


            /*
            |--------------------------------------------------------------------------
            | Keep theme toggle visible on small screens.
            |--------------------------------------------------------------------------
            */

            .theme-toggle {

                display:
                    grid;
            }


            .hero {

                padding:
                    23px 18px;
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


        <a href="dashboard.php">

            <i
                class="
                    fa-solid
                    fa-chart-pie
                "
            ></i>

            <span>
                Dashboard
            </span>

        </a>


        <a href="appointments.php">

            <i
                class="
                    fa-regular
                    fa-calendar-check
                "
            ></i>

            <span>
                Appointments
            </span>

        </a>


        <a
            href="patients.php"
            class="active"
        >

            <i
                class="
                    fa-solid
                    fa-users
                "
            ></i>

            <span>
                Patients
            </span>

        </a>


        <a href="add_patient.php">

            <i
                class="
                    fa-solid
                    fa-user-plus
                "
            ></i>

            <span>
                Register Patient
            </span>

        </a>


        <?php if (
            $role === 'Admin'
        ): ?>

            <a href="manage_users.php">

                <i
                    class="
                        fa-solid
                        fa-user-gear
                    "
                ></i>

                <span>
                    Manage Users
                </span>

            </a>

        <?php endif; ?>


        <a href="profile.php">

            <i
                class="
                    fa-solid
                    fa-user-doctor
                "
            ></i>

            <span>
                My Profile
            </span>

        </a>


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
                    fa-user-doctor
                "
            ></i>

            <?= e(
                $role
            ) ?>

        </span>


        <!-- CSRF-PROTECTED LOGOUT -->

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
                Patients
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
                class="
                    top-icon
                    theme-toggle
                "
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
                href="dashboard.php"
                class="top-icon"
                title="Dashboard"
            >

                <i
                    class="
                        fa-solid
                        fa-house
                    "
                ></i>

            </a>


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


        </div>


    </header>


    <!-- =======================================================
         CONTENT
    ======================================================== -->

    <div class="content">


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="hero">


            <div class="hero-content">


                <div class="eyebrow">

                    Patient Management

                </div>


                <h1>
                    Patient List
                </h1>


                <p>

                    Review registered patient records, access
                    patient profiles and manage healthcare
                    information from one secure workspace.

                </p>


            </div>


            <a
                href="add_patient.php"
                class="hero-action"
            >

                <i
                    class="
                        fa-solid
                        fa-user-plus
                    "
                ></i>

                Register Patient

            </a>


        </section>


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if (
            $success !== ''
        ): ?>

            <div
                class="
                    alert
                    alert-success
                "
                role="status"
            >

                <i
                    class="
                        fa-solid
                        fa-circle-check
                    "
                ></i>

                <span>

                    <?= e(
                        $success
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <?php if (
            $error !== ''
        ): ?>

            <div
                class="
                    alert
                    alert-error
                "
                role="alert"
            >

                <i
                    class="
                        fa-solid
                        fa-circle-exclamation
                    "
                ></i>

                <span>

                    <?= e(
                        $error
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section
            class="stats"
            aria-label="Patient statistics"
        >


            <!-- TOTAL -->

            <article class="stat-card">


                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-users
                        "
                    ></i>

                </div>


                <span class="stat-label">
                    Total Patients
                </span>


                <strong class="stat-number">

                    <?= $totalPatients ?>

                </strong>


                <span class="stat-caption">

                    Currently displayed

                </span>


            </article>


            <!-- ACTIVE -->

            <article
                class="
                    stat-card
                    active
                "
            >


                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-user-check
                        "
                    ></i>

                </div>


                <span class="stat-label">
                    Active
                </span>


                <strong class="stat-number">

                    <?= $activePatients ?>

                </strong>


                <span class="stat-caption">

                    Active patient records

                </span>


            </article>


            <!-- INACTIVE -->

            <article
                class="
                    stat-card
                    inactive
                "
            >


                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-user-slash
                        "
                    ></i>

                </div>


                <span class="stat-label">
                    Inactive
                </span>


                <strong class="stat-number">

                    <?= $inactivePatients ?>

                </strong>


                <span class="stat-caption">

                    Deactivated records

                </span>


            </article>


        </section>


        <!-- =================================================
             PATIENT PANEL
        ================================================== -->

        <section class="panel">


            <!-- FILTER -->

            <div class="filter-area">


                <form
                    method="GET"
                    action="patients.php"
                    class="filter-form"
                >


                    <input
                        type="search"
                        name="search"
                        class="filter-input"
                        placeholder="Search patient name or phone number..."
                        value="<?= e(
                            $search
                        ) ?>"
                        autocomplete="off"
                    >


                    <button
                        type="submit"
                        class="filter-button"
                    >

                        <i
                            class="
                                fa-solid
                                fa-magnifying-glass
                            "
                        ></i>

                        &nbsp;

                        Search

                    </button>


                    <?php if (
                        $search !== ''
                    ): ?>

                        <a
                            href="patients.php"
                            class="clear-button"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-xmark
                                "
                            ></i>

                            &nbsp;

                            Clear

                        </a>

                    <?php endif; ?>


                </form>


            </div>


            <!-- RESULTS BAR -->

            <div class="results-bar">


                <div class="results-text">

                    Showing

                    <strong>
                        <?= $totalPatients ?>
                    </strong>

                    patient record(s)

                </div>


                <span class="results-filter">


                    <i
                        class="
                            fa-solid
                            fa-filter
                        "
                    ></i>


                    <?php if (
                        $search !== ''
                    ): ?>

                        Search:
                        <?= e(
                            $search
                        ) ?>

                    <?php else: ?>

                        All Patients

                    <?php endif; ?>


                </span>


            </div>


            <!-- TABLE -->

            <div class="table-wrapper">


                <table class="patients-table">


                    <thead>

                        <tr>

                            <th>
                                Patient
                            </th>

                            <th>
                                Date of Birth
                            </th>

                            <th>
                                Gender
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Address
                            </th>

                            <th>
                                Medical History
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        !empty(
                            $patients
                        )
                    ): ?>


                        <?php foreach (
                            $patients
                            as $patient
                        ): ?>


                            <?php

                            $fullName =
                                patientListFullName(
                                    $patient
                                );


                            $initial =
                                patientListInitial(
                                    $fullName
                                );


                            $isActive =
                                (int) (
                                    $patient['is_active']
                                    ?? 1
                                ) === 1;


                            $gender =
                                trim(
                                    (string) (
                                        $patient['gender']
                                        ?? ''
                                    )
                                );


                            $phone =
                                trim(
                                    (string) (
                                        $patient['phone']
                                        ?? ''
                                    )
                                );


                            $address =
                                trim(
                                    (string) (
                                        $patient['address']
                                        ?? ''
                                    )
                                );


                            $history =
                                trim(
                                    (string) (
                                        $patient[
                                            'medical_history'
                                        ]
                                        ?? ''
                                    )
                                );

                            ?>


                            <tr>


                                <!-- PATIENT -->

                                <td>


                                    <div
                                        class="
                                            patient-cell
                                        "
                                    >


                                        <div
                                            class="
                                                patient-avatar
                                            "
                                        >

                                            <?= e(
                                                $initial
                                            ) ?>

                                        </div>


                                        <div>


                                            <span
                                                class="
                                                    patient-name
                                                "
                                            >

                                                <?= e(
                                                    $fullName
                                                ) ?>

                                            </span>


                                            <span
                                                class="
                                                    patient-id
                                                "
                                            >

                                                Patient #

                                                <?= (int) (
                                                    $patient['id']
                                                ) ?>

                                            </span>


                                        </div>


                                    </div>


                                </td>


                                <!-- DOB -->

                                <td>


                                    <span
                                        class="date-main"
                                    >

                                        <?= e(
                                            patientListDob(
                                                (string) (
                                                    $patient['dob']
                                                    ?? ''
                                                )
                                            )
                                        ) ?>

                                    </span>


                                </td>


                                <!-- GENDER -->

                                <td>


                                    <span
                                        class="
                                            gender-badge
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-person
                                            "
                                        ></i>

                                        <?= e(
                                            $gender !== ''
                                                ? $gender
                                                : 'Not provided'
                                        ) ?>

                                    </span>


                                </td>


                                <!-- PHONE -->

                                <td>


                                    <span
                                        class="phone"
                                    >

                                        <?= e(
                                            $phone !== ''
                                                ? $phone
                                                : 'Not provided'
                                        ) ?>

                                    </span>


                                </td>


                                <!-- ADDRESS -->

                                <td>


                                    <div
                                        class="address"
                                    >

                                        <?= e(
                                            $address !== ''
                                                ? $address
                                                : 'Not provided'
                                        ) ?>

                                    </div>


                                </td>


                                <!-- MEDICAL HISTORY -->

                                <td>


                                    <div
                                        class="history"
                                    >

                                        <?= e(
                                            $history !== ''
                                                ? $history
                                                : 'No medical history recorded'
                                        ) ?>

                                    </div>


                                </td>


                                <!-- STATUS -->

                                <td>


                                    <?php if (
                                        $isActive
                                    ): ?>

                                        <span
                                            class="
                                                status
                                                status-active
                                            "
                                        >

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                status
                                                status-inactive
                                            "
                                        >

                                            Inactive

                                        </span>

                                    <?php endif; ?>


                                </td>


                                <!-- ACTIONS -->

                                <td>


                                    <div class="actions">


                                        <a
                                            href="view_patient.php?id=<?= (int) (
                                                $patient['id']
                                            ) ?>"
                                            class="
                                                action-link
                                                view-action
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-eye
                                                "
                                            ></i>

                                            View

                                        </a>


                                        <a
                                            href="edit_patient.php?id=<?= (int) (
                                                $patient['id']
                                            ) ?>"
                                            class="
                                                action-link
                                                edit-action
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-pen
                                                "
                                            ></i>

                                            Edit

                                        </a>


                                    </div>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>


                            <td
                                colspan="8"
                                class="
                                    empty-state
                                "
                            >


                                <i
                                    class="
                                        fa-solid
                                        fa-users-slash
                                    "
                                ></i>


                                <strong>

                                    No patients found

                                </strong>


                                <span>


                                    <?php if (
                                        $search !== ''
                                    ): ?>

                                        No patient records
                                        matched your search.

                                    <?php else: ?>

                                        No patient records have
                                        been registered yet.

                                    <?php endif; ?>


                                </span>


                            </td>


                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


            <!-- FOOTER -->

            <div class="table-footer">


                <span>

                    <i
                        class="
                            fa-solid
                            fa-shield-heart
                        "
                    ></i>

                    Patient records are restricted to
                    authorized MediCare personnel.

                </span>


                <span>

                    <?= $totalPatients ?>

                    record(s)

                </span>


            </div>


        </section>


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


        /*
        |--------------------------------------------------------------------------
        | MEDICARE THEME SYSTEM
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | MOBILE MENU
        |--------------------------------------------------------------------------
        */

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