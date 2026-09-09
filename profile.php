<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

require_auth();


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId = current_user_id();

$error = '';

$success = '';

$fullname = '';

$email = '';

$role = '';



/*
|--------------------------------------------------------------------------
| LOAD CURRENT USER
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "SELECT
            id,
            fullname,
            email,
            role
         FROM users
         WHERE id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $userId
    ]);


    $user = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$user) {

        redirect(
            'login.php'
        );

        exit;
    }


    $fullname = trim(
        (string) (
            $user['fullname'] ?? ''
        )
    );


    $email = trim(
        (string) (
            $user['email'] ?? ''
        )
    );


    /*
    |--------------------------------------------------------------------------
    | CENTRAL ROLE
    |--------------------------------------------------------------------------
    */

    $role = current_role();


} catch (PDOException $e) {

    error_log(
        'MediCare profile loading error: ' .
        $e->getMessage()
    );


    $error =
        'Unable to load your profile right now.';
}


/*
|--------------------------------------------------------------------------
| HANDLE PROFILE UPDATE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    try {

        verify_csrf(
            $_POST['_csrf'] ?? null
        );


    } catch (Throwable $e) {

        $error =
            'Your security session has expired. Please refresh the page and try again.';
    }


    if (
        $error === ''
    ) {

        /*
        |--------------------------------------------------------------------------
        | FORM VALUES
        |--------------------------------------------------------------------------
        */

        $fullname = trim(
            post_string(
                'fullname',
                150
            )
        );


        $email = strtolower(
            trim(
                post_string(
                    'email',
                    150
                )
            )
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $fullname === ''
        ) {

            $error =
                'Full name is required.';


        } elseif (
            mb_strlen(
                $fullname
            ) < 2
        ) {

            $error =
                'Please enter a valid full name.';


        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Please enter a valid email address.';


        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | CHECK EMAIL DUPLICATE
                |--------------------------------------------------------------------------
                */

                $checkStmt =
                    $pdo->prepare(
                        "SELECT
                            id
                         FROM users
                         WHERE LOWER(email) = LOWER(?)
                           AND id <> ?
                         LIMIT 1"
                    );


                $checkStmt->execute([
                    $email,
                    $userId
                ]);


                $existingUser =
                    $checkStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    $existingUser
                ) {

                    $error =
                        'That email address is already being used by another account.';


                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE USER
                    |--------------------------------------------------------------------------
                    */

                    $updateStmt =
                        $pdo->prepare(
                            "UPDATE users
                             SET
                                fullname = ?,
                                email = ?
                             WHERE id = ?"
                        );


                    $updateStmt->execute([
                        $fullname,
                        $email,
                        $userId
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE SESSION
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['fullname'] =
                        $fullname;


                    $_SESSION['email'] =
                        $email;


                    /*
                    |--------------------------------------------------------------------------
                    | DO NOT ALLOW ROLE CHANGES
                    |--------------------------------------------------------------------------
                    |
                    | Role remains controlled by the database and
                    | authorized administration.
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['role'] =
                        $role;


                    $success =
                        'Your profile has been updated successfully.';
                }


            } catch (PDOException $e) {

                error_log(
                    'MediCare profile update error: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to update your profile. Please try again.';
            }
        }
    }
}



/*
|--------------------------------------------------------------------------
| USER DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$displayName =
    $fullname !== ''
        ? $fullname
        : 'User';


$emailDisplay =
    $email;



/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$initials = '';


$nameParts =
    preg_split(
        '/\s+/u',
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

            $initials .=
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
    $initials === ''
) {

    $initials =
        'U';
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
        content="MediCare user profile and account settings."
    >


    <title>
        My Profile | MediCare
    </title>


    <!-- =========================================================
         THEME RESTORE
         Dark mode is the default.
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
                | Dark mode remains the default if localStorage
                | is unavailable.
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
           DARK MODE DEFAULT
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

            --muted-dark:
                #678392;

            --border:
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            --sidebar-width:
                255px;


            /* =====================================================
               THEME SYSTEM
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

            --heading:
                #edf6f9;

            --white-text:
                #ffffff;

            --soft-text:
                #b7ced8;

            --muted-text:
                #718b99;

            --label-text:
                #c8d9e1;

            --input-text:
                #dceaf0;

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

            --input-placeholder:
                #587483;

            --input-focus-bg:
                rgba(
                    2,
                    22,
                    36,
                    0.80
                );

            --nav-text:
                #9db4c1;

            --nav-muted:
                #76919f;

            --nav-title:
                #648191;

            --surface:
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

            --hero-start:
                #073f51;

            --hero-middle:
                #072e42;

            --hero-end:
                #082940;

            --shadow:
                rgba(
                    0,
                    0,
                    0,
                    0.18
                );
        }


        /* =========================================================
           LIGHT MODE
        ========================================================== */

        html[data-theme="light"] {

            --bg:
                #f4f8fb;

            --sidebar:
                #ffffff;

            --panel:
                #ffffff;

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
                #78909d;

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

            --heading:
                #183241;

            --white-text:
                #19313f;

            --soft-text:
                #56707e;

            --muted-text:
                #6d8290;

            --label-text:
                #536c79;

            --input-text:
                #294454;

            --input-bg:
                #ffffff;

            --input-border:
                rgba(
                    24,
                    55,
                    72,
                    0.14
                );

            --input-placeholder:
                #7d929e;

            --input-focus-bg:
                #ffffff;

            --nav-text:
                #536c79;

            --nav-muted:
                #708795;

            --nav-title:
                #78909d;

            --surface:
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
                #ffffff;

            --panel-end:
                #f7fafc;

            --hero-start:
                #e5f7f4;

            --hero-middle:
                #edf8f8;

            --hero-end:
                #f1f7fb;

            --shadow:
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


        input,
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
                var(--shadow);

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

            color:
                var(--white-text);

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
                var(--surface);

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

            display:
                grid;

            place-items:
                center;

            flex-shrink:
                0;

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


        .page-title small {

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


        .page-title h2 {

            margin-top:
                3px;

            color:
                var(--white-text);

            font-size:
                17px;
        }


        .topbar-actions {

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
                var(--surface);

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

            appearance:
                none;

            -webkit-appearance:
                none;

            cursor:
                pointer;
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
                    1100px
                );

            margin:
                0 auto;

            padding:
                30px;
        }


        /* =========================================================
           PAGE HERO
        ========================================================== */

        .hero {

            position:
                relative;

            overflow:
                hidden;

            margin-bottom:
                18px;

            padding:
                28px 30px;

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

            top:
                -145px;

            right:
                -110px;

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
                var(--white-text);

            font-size:
                clamp(
                    28px,
                    3vw,
                    39px
                );

            line-height:
                1.05;

            letter-spacing:
                -1.8px;
        }


        .hero p {

            max-width:
                700px;

            margin-top:
                10px;

            color:
                var(--soft-text);

            font-size:
                10px;

            line-height:
                1.7;
        }


        /* =========================================================
           ALERTS
        ========================================================== */

        .alert {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                9px;

            margin-bottom:
                18px;

            padding:
                13px 15px;

            border-radius:
                10px;

            font-size:
                10px;

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


        .alert i {

            margin-top:
                1px;
        }


        /* =========================================================
           PROFILE LAYOUT
        ========================================================== */

        .profile-grid {

            display:
                grid;

            grid-template-columns:

                minmax(
                    280px,
                    .75fr
                )

                minmax(
                    0,
                    1.5fr
                );

            gap:
                18px;

            margin-bottom:
                18px;
        }


        /* =========================================================
           ACCOUNT CARD
        ========================================================== */

        .account-card {

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
                border-color .25s ease;
        }


        .account-header {

            padding:
                24px;

            border-bottom:
                1px solid
                var(--border);
        }


        .account-avatar {

            width:
                74px;

            height:
                74px;

            display:
                grid;

            place-items:
                center;

            margin-bottom:
                16px;

            border-radius:
                20px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.10
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.22
                );

            color:
                var(--primary);

            font-size:
                25px;

            font-weight:
                800;
        }


        .account-header h2 {

            color:
                var(--heading);

            font-size:
                18px;

            line-height:
                1.25;
        }


        .account-header p {

            margin-top:
                6px;

            color:
                var(--muted-text);

            font-size:
                9px;

            line-height:
                1.6;
        }


        .account-body {

            padding:
                20px 24px 24px;
        }


        .account-item {

            padding:
                13px 0;

            border-bottom:
                1px solid
                var(--border);
        }


        .account-item:last-child {

            border-bottom:
                none;
        }


        .account-label {

            display:
                block;

            margin-bottom:
                5px;

            color:
                var(--muted-text);

            font-size:
                7px;

            font-weight:
                800;

            letter-spacing:
                .7px;

            text-transform:
                uppercase;
        }


        .account-value {

            color:
                var(--input-text);

            font-size:
                10px;

            line-height:
                1.5;

            word-break:
                break-word;
        }


        .role-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                6px 9px;

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
                    0.13
                );

            font-size:
                7px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        /* =========================================================
           FORM CARD
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
                border-color .25s ease;
        }


        .panel-header {

            min-height:
                74px;

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


        .panel-header span {

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
                var(--heading);

            font-size:
                16px;
        }


        .panel-header p {

            margin-top:
                4px;

            color:
                var(--muted-text);

            font-size:
                9px;
        }


        .form-body {

            padding:
                22px;
        }


        /* =========================================================
           FORM
        ========================================================== */

        .profile-form {

            display:
                grid;

            grid-template-columns:

                repeat(
                    2,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                18px;
        }


        .form-group {

            min-width:
                0;
        }


        .full {

            grid-column:
                1 / -1;
        }


        .form-group label {

            display:
                block;

            margin-bottom:
                7px;

            color:
                var(--label-text);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                .2px;
        }


        .form-control {

            width:
                100%;

            min-height:
                47px;

            padding:
                0 13px;

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
                box-shadow .2s ease,
                background .2s ease,
                color .2s ease;
        }


        .form-control::placeholder {

            color:
                var(--input-placeholder);
        }


        .form-control:focus {

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.50
                );

            box-shadow:
                0 0 0 3px
                rgba(
                    16,
                    199,
                    176,
                    0.07
                );

            background:
                var(--input-focus-bg);
        }


        .readonly {

            color:
                var(--muted);

            background:
                var(--surface);

            cursor:
                not-allowed;
        }


        .field-help {

            margin-top:
                6px;

            color:
                var(--muted-text);

            font-size:
                8px;

            line-height:
                1.5;
        }


        /* =========================================================
           ACTIONS
        ========================================================== */

        .form-actions {

            grid-column:
                1 / -1;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            margin-top:
                4px;

            padding-top:
                20px;

            border-top:
                1px solid
                var(--border);
        }


        .back-button,
        .save-button {

            min-height:
                43px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                0 15px;

            border-radius:
                9px;

            font-size:
                9px;

            font-weight:
                800;
        }


        .back-button {

            color:
                var(--muted);

            background:
                var(--surface);

            border:
                1px solid
                var(--border);

            transition:
                .2s ease;
        }


        .back-button:hover {

            color:
                var(--white-text);
        }


        .save-button {

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

            box-shadow:

                0 10px 25px
                rgba(
                    16,
                    199,
                    176,
                    0.10
                );

            cursor:
                pointer;

            transition:
                transform .2s ease,
                box-shadow .2s ease,
                opacity .2s ease;
        }


        .save-button:hover {

            transform:
                translateY(-1px);

            box-shadow:

                0 15px 30px
                rgba(
                    16,
                    199,
                    176,
                    0.16
                );
        }


        .save-button:disabled {

            opacity:
                .55;

            cursor:
                not-allowed;

            transform:
                none;
        }


        /* =========================================================
           SECURITY NOTE
        ========================================================== */

        .security-note {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                8px;

            margin-top:
                18px;

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
                var(--muted-text);

            font-size:
                8px;

            line-height:
                1.7;

            transition:
                background .25s ease,
                border-color .25s ease,
                color .25s ease;
        }


        .security-note i {

            color:
                var(--primary);
        }


        /* =========================================================
           OVERLAY
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
           RESPONSIVE
        ========================================================== */

        @media (max-width: 1050px) {

            .profile-grid {

                grid-template-columns:
                    1fr;
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

                padding:
                    25px 20px;
            }


            .form-body {

                padding:
                    18px;
            }


            .profile-form {

                grid-template-columns:
                    1fr;
            }


            .full {

                grid-column:
                    auto;
            }


            .form-actions {

                grid-column:
                    auto;

                flex-direction:
                    column;

                align-items:
                    stretch;
            }


            .back-button,
            .save-button {

                width:
                    100%;
            }

        }


        @media (max-width: 460px) {

            .top-icon {

                display:
                    none;
            }


            .theme-toggle {

                display:
                    grid;
            }


            .panel-header {

                padding:
                    0 16px;
            }


            .account-header {

                padding:
                    20px;
            }


            .account-body {

                padding:
                    18px 20px 20px;
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


        <?php if (
            $role === 'Patient'
        ): ?>

            <a href="book_appointment.php">

                <i class="fa-solid fa-calendar-plus"></i>

                <span>
                    Book Appointment
                </span>

            </a>

        <?php endif; ?>


        <?php if (
            $role === 'Admin' ||
            $role === 'Doctor'
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
            $role === 'Admin'
        ): ?>

            <a href="manage_users.php">

                <i class="fa-solid fa-user-gear"></i>

                <span>
                    Manage Users
                </span>

            </a>

        <?php endif; ?>


        <a
            href="profile.php"
            class="active"
        >

            <i class="fa-solid fa-user-doctor"></i>

            <span>
                My Profile
            </span>

        </a>


    </nav>


    <div class="sidebar-spacer"></div>


    <!-- USER -->

    <div class="user-box">


        <div class="user-info">


            <div class="avatar">

                <?= e(
                    $initials
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
                        $emailDisplay
                    ) ?>

                </span>

            </div>


        </div>


        <span class="role-label">

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
                    csrf_token()
                ) ?>"
            >


            <button
                type="submit"
                class="logout-button"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

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


        <div class="page-title">

            <small>

                MediCare Portal

            </small>


            <h2>

                My Profile

            </h2>

        </div>


        <div class="topbar-actions">


            <!-- MOBILE MENU -->

            <button
                type="button"
                class="menu-button"
                id="menuButton"
                aria-label="Open menu"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <!-- THEME TOGGLE -->

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
                    class="fa-solid fa-sun"
                    id="themeIcon"
                ></i>

            </button>


            <!-- DASHBOARD -->

            <a
                href="dashboard.php"
                class="top-icon"
                title="Dashboard"
            >

                <i class="fa-solid fa-house"></i>

            </a>


            <!-- APPOINTMENTS -->

            <a
                href="appointments.php"
                class="top-icon"
                title="Appointments"
            >

                <i class="fa-regular fa-calendar-check"></i>

            </a>


        </div>

    </header>


    <!-- CONTENT -->

    <div class="content">


        <!-- =====================================================
             HERO
        ====================================================== -->

        <section class="hero">


            <div class="hero-copy">


                <div class="eyebrow">

                    Account Settings

                </div>


                <h1>

                    My Profile

                </h1>


                <p>

                    View and update your personal MediCare
                    account information securely.

                </p>


            </div>


        </section>


        <!-- =====================================================
             ALERTS
        ====================================================== -->

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

                <i class="fa-solid fa-circle-check"></i>

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

                <i class="fa-solid fa-circle-exclamation"></i>

                <span>

                    <?= e(
                        $error
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             PROFILE GRID
        ====================================================== -->

        <section class="profile-grid">


            <!-- =================================================
                 ACCOUNT SUMMARY
            ================================================== -->

            <article class="account-card">


                <header class="account-header">


                    <div class="account-avatar">

                        <?= e(
                            $initials
                        ) ?>

                    </div>


                    <h2>

                        <?= e(
                            $displayName
                        ) ?>

                    </h2>


                    <p>

                        Your MediCare account information and
                        current access level.

                    </p>


                </header>


                <div class="account-body">


                    <div class="account-item">

                        <span class="account-label">

                            Account ID

                        </span>


                        <strong class="account-value">

                            #<?= (int) $userId ?>

                        </strong>

                    </div>


                    <div class="account-item">

                        <span class="account-label">

                            Email Address

                        </span>


                        <strong class="account-value">

                            <?= e(
                                $emailDisplay
                            ) ?>

                        </strong>

                    </div>


                    <div class="account-item">

                        <span class="account-label">

                            Access Role

                        </span>


                        <span class="role-badge">

                            <i class="fa-solid fa-shield-heart"></i>

                            <?= e(
                                $role
                            ) ?>

                        </span>

                    </div>


                    <div class="account-item">

                        <span class="account-label">

                            Account Status

                        </span>


                        <span class="role-badge">

                            <i class="fa-solid fa-circle-check"></i>

                            Active

                        </span>

                    </div>


                </div>


            </article>


            <!-- =================================================
                 EDIT PROFILE
            ================================================== -->

            <article class="panel">


                <header class="panel-header">


                    <div>

                        <span>

                            Profile Management

                        </span>


                        <h2>

                            Update Account

                        </h2>


                        <p>

                            Change your name or email address.

                        </p>

                    </div>


                </header>


                <div class="form-body">


                    <form
                        method="POST"
                        action="profile.php"
                        class="profile-form"
                        autocomplete="on"
                    >


                        <input
                            type="hidden"
                            name="_csrf"
                            value="<?= e(
                                csrf_token()
                            ) ?>"
                        >


                        <!-- FULL NAME -->

                        <div class="form-group">


                            <label for="fullname">

                                Full Name

                            </label>


                            <input
                                type="text"
                                id="fullname"
                                name="fullname"
                                class="form-control"
                                value="<?= e(
                                    $fullname
                                ) ?>"
                                maxlength="150"
                                autocomplete="name"
                                required
                            >


                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">


                            <label for="email">

                                Email Address

                            </label>


                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                value="<?= e(
                                    $email
                                ) ?>"
                                maxlength="150"
                                autocomplete="email"
                                required
                            >


                        </div>


                        <!-- ROLE -->

                        <div class="form-group">


                            <label for="role">

                                Role

                            </label>


                            <input
                                type="text"
                                id="role"
                                class="
                                    form-control
                                    readonly
                                "
                                value="<?= e(
                                    $role
                                ) ?>"
                                readonly
                            >


                            <p class="field-help">

                                Your role is controlled by
                                authorized MediCare administration.

                            </p>


                        </div>


                        <!-- ACCOUNT ID -->

                        <div class="form-group">


                            <label for="account_id">

                                Account ID

                            </label>


                            <input
                                type="text"
                                id="account_id"
                                class="
                                    form-control
                                    readonly
                                "
                                value="#<?= (int) $userId ?>"
                                readonly
                            >


                        </div>


                        <!-- ACTIONS -->

                        <div class="form-actions">


                            <a
                                href="dashboard.php"
                                class="back-button"
                            >

                                <i class="fa-solid fa-arrow-left"></i>

                                Back to Dashboard

                            </a>


                            <button
                                type="submit"
                                class="save-button"
                                id="saveProfileButton"
                            >

                                <i class="fa-solid fa-floppy-disk"></i>

                                Save Changes

                            </button>


                        </div>


                    </form>


                </div>


            </article>


        </section>


        <!-- =====================================================
             SECURITY
        ====================================================== -->

        <section class="security-note">

            <i class="fa-solid fa-shield-heart"></i>

            <span>

                Your MediCare account is protected by
                authenticated access and CSRF security.
                Your role cannot be changed from this page.
                Contact an authorized administrator for
                account-level changes.

            </span>

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


        /*
        |--------------------------------------------------------------------------
        | THEME SYSTEM
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


        /*
        |--------------------------------------------------------------------------
        | OVERLAY
        |--------------------------------------------------------------------------
        */

        if (
            overlay
        ) {

            overlay.addEventListener(
                'click',
                closeMenu
            );
        }


        /*
        |--------------------------------------------------------------------------
        | RESIZE
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE SUBMISSION
        |--------------------------------------------------------------------------
        */

        const profileForm =
            document.querySelector(
                '.profile-form'
            );


        const saveProfileButton =
            document.getElementById(
                'saveProfileButton'
            );


        if (
            profileForm &&
            saveProfileButton
        ) {

            profileForm.addEventListener(
                'submit',
                function () {

                    if (
                        saveProfileButton.disabled
                    ) {

                        return;
                    }


                    saveProfileButton.disabled =
                        true;


                    saveProfileButton.style.opacity =
                        '0.65';


                    saveProfileButton.style.cursor =
                        'not-allowed';


                    saveProfileButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

                }
            );
        }

    }

);

</script>


</body>

</html>