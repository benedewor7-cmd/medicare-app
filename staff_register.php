<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/staff_security.php';


/*
|--------------------------------------------------------------------------
| REDIRECT AUTHENTICATED USERS
|--------------------------------------------------------------------------
|
| Staff registration should not be performed while already logged in.
|--------------------------------------------------------------------------
*/

if (is_logged_in()) {

    redirect(
        'dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$fullname = '';

$email = '';

$role = 'Doctor';

$error = '';

$success = '';


/*
|--------------------------------------------------------------------------
| HANDLE STAFF REGISTRATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    try {

        verify_csrf(
            $_POST['_csrf'] ?? null
        );

    } catch (Throwable $e) {

        $error =
            'Your security session has expired. Please refresh the page and try again.';
    }


    /*
    |--------------------------------------------------------------------------
    | READ FORM
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        $fullname =
            trim(
                post_string(
                    'fullname',
                    150
                )
            );


        $email =
            strtolower(
                trim(
                    post_string(
                        'email',
                        150
                    )
                )
            );


        $role =
            trim(
                post_string(
                    'role',
                    20
                )
            );


        $password =
            (string) (
                $_POST['password'] ?? ''
            );


        $confirmPassword =
            (string) (
                $_POST['confirm_password'] ?? ''
            );


        $securityKey =
            (string) (
                $_POST['security_key'] ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $fullname === '' ||
            $email === '' ||
            $password === '' ||
            $confirmPassword === '' ||
            $securityKey === ''
        ) {

            $error =
                'Please complete all required fields.';

        } elseif (
            !in_array(
                $role,
                [
                    'Doctor',
                    'Admin'
                ],
                true
            )
        ) {

            $error =
                'Invalid staff account type selected.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Please enter a valid email address.';

        } elseif (
            mb_strlen(
                $fullname
            ) > 150
        ) {

            $error =
                'Full name is too long.';

        } elseif (
            strlen(
                $password
            ) < 8
        ) {

            $error =
                'Password must contain at least 8 characters.';

        } elseif (
            $password !==
            $confirmPassword
        ) {

            $error =
                'Passwords do not match.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | SELECT CORRECT SECURITY KEY
            |--------------------------------------------------------------------------
            */

            $securityKeyHash =
                $role === 'Doctor'
                    ? MEDICARE_DOCTOR_REGISTRATION_KEY_HASH
                    : MEDICARE_ADMIN_REGISTRATION_KEY_HASH;


            /*
            |--------------------------------------------------------------------------
            | VERIFY SECURITY KEY
            |--------------------------------------------------------------------------
            */

            if (
                !password_verify(
                    $securityKey,
                    $securityKeyHash
                )
            ) {

                $error =
                    'The security key is invalid or unauthorized.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | DATABASE OPERATIONS
                |--------------------------------------------------------------------------
                */

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK EXISTING EMAIL
                    |--------------------------------------------------------------------------
                    */

                    $checkStmt =
                        $pdo->prepare(
                            "SELECT
                                id
                             FROM users
                             WHERE email = ?
                             LIMIT 1"
                        );


                    $checkStmt->execute([
                        $email
                    ]);


                    $existingUser =
                        $checkStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        $existingUser
                    ) {

                        $error =
                            'An account with this email address already exists.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | HASH PASSWORD
                        |--------------------------------------------------------------------------
                        */

                        $passwordHash =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | CREATE STAFF ACCOUNT
                        |--------------------------------------------------------------------------
                        */

                        $insertStmt =
                            $pdo->prepare(
                                "INSERT INTO users
                                (
                                    fullname,
                                    email,
                                    password,
                                    role
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    ?,
                                    ?
                                )"
                            );


                        $insertStmt->execute([
                            $fullname,
                            $email,
                            $passwordHash,
                            $role
                        ]);


                        /*
                        |--------------------------------------------------------------------------
                        | SUCCESS
                        |--------------------------------------------------------------------------
                        */

                        $success =
                            $role .
                            ' account created successfully. You can now sign in.';


                        /*
                        |--------------------------------------------------------------------------
                        | CLEAR FORM VALUES
                        |--------------------------------------------------------------------------
                        */

                        $fullname = '';

                        $email = '';

                        $role = 'Doctor';

                    }

                } catch (PDOException $e) {

                    error_log(
                        'MediCare staff registration database error: ' .
                        $e->getMessage()
                    );


                    $error =
                        'Unable to create the account right now. Please try again.';
                }
            }
        }
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
        content="Secure MediCare Doctor and Administrator registration."
    >


    <title>
        Staff Registration | MediCare
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


    <style>

        /* =========================================================
           THEME VARIABLES
        ========================================================== */

        :root {

            /* DARK THEME - DEFAULT */

            --bg:
                #031b2d;

            --bg-deep:
                #021522;

            --bg-secondary:
                #05283f;

            --panel:
                #082f49;

            --panel-dark:
                #06283f;

            --primary:
                #10c7b0;

            --primary-dark:
                #0a9e90;

            --blue:
                #2c8cff;

            --green:
                #55d890;

            --red:
                #ff7279;

            --orange:
                #ffb84d;

            --white:
                #ffffff;

            --text:
                #e1edf3;

            --text-strong:
                #edf7fa;

            --text-soft:
                #cadbe2;

            --muted:
                #8fa8b8;

            --muted-dark:
                #678392;

            --muted-darker:
                #607b8a;

            --border:
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            --border-soft:
                rgba(
                    255,
                    255,
                    255,
                    .055
                );

            --intro-top:
                #062a41;

            --intro-bottom:
                #031b2d;

            --card-start:
                rgba(
                    8,
                    47,
                    70,
                    .97
                );

            --card-end:
                rgba(
                    5,
                    31,
                    48,
                    .98
                );

            --input-bg:
                rgba(
                    2,
                    22,
                    36,
                    .65
                );

            --input-focus-bg:
                rgba(
                    2,
                    22,
                    36,
                    .82
                );

            --soft-bg:
                rgba(
                    255,
                    255,
                    255,
                    .025
                );

            --soft-bg-strong:
                rgba(
                    255,
                    255,
                    255,
                    .045
                );

            --hero-line:
                #10c7b0;

            --hero-text:
                #b5cbd5;

            --footer-text:
                #6e8896;

            --overlay:
                rgba(
                    0,
                    8,
                    16,
                    .65
                );
        }


        /* =========================================================
           LIGHT THEME
        ========================================================== */

        html[data-theme="light"] {

            --bg:
                #edf4f7;

            --bg-deep:
                #e5eef2;

            --bg-secondary:
                #ffffff;

            --panel:
                #ffffff;

            --panel-dark:
                #f4f8fa;

            --primary:
                #079f8d;

            --primary-dark:
                #087c70;

            --blue:
                #2478d9;

            --green:
                #24995d;

            --red:
                #c9434b;

            --orange:
                #bf7900;

            --white:
                #ffffff;

            --text:
                #173042;

            --text-strong:
                #173042;

            --text-soft:
                #365366;

            --muted:
                #607784;

            --muted-dark:
                #738b98;

            --muted-darker:
                #8297a3;

            --border:
                rgba(
                    20,
                    54,
                    70,
                    .12
                );

            --border-soft:
                rgba(
                    20,
                    54,
                    70,
                    .075
                );

            --intro-top:
                #f6fbfc;

            --intro-bottom:
                #e9f1f4;

            --card-start:
                rgba(
                    255,
                    255,
                    255,
                    .98
                );

            --card-end:
                rgba(
                    246,
                    250,
                    251,
                    .98
                );

            --input-bg:
                #f7fafb;

            --input-focus-bg:
                #ffffff;

            --soft-bg:
                rgba(
                    20,
                    54,
                    70,
                    .035
                );

            --soft-bg-strong:
                rgba(
                    20,
                    54,
                    70,
                    .055
                );

            --hero-line:
                #079f8d;

            --hero-text:
                #536c7b;

            --footer-text:
                #708693;

            --overlay:
                rgba(
                    14,
                    34,
                    45,
                    .38
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

            min-height:
                100%;

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
                    circle at 15% 15%,
                    rgba(
                        16,
                        199,
                        176,
                        .10
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 85% 80%,
                    rgba(
                        44,
                        140,
                        255,
                        .07
                    ),
                    transparent 28%
                ),

                linear-gradient(
                    145deg,
                    var(--bg),
                    var(--bg-deep)
                );

            overflow-x:
                hidden;

            transition:
                background .25s ease,
                color .25s ease;
        }


        html[data-theme="light"] body {

            background:

                radial-gradient(
                    circle at 15% 15%,
                    rgba(
                        7,
                        159,
                        141,
                        .08
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 85% 80%,
                    rgba(
                        36,
                        120,
                        217,
                        .06
                    ),
                    transparent 28%
                ),

                linear-gradient(
                    145deg,
                    var(--bg),
                    var(--bg-deep)
                );
        }


        a {

            color:
                inherit;

            text-decoration:
                none;
        }


        button,
        input,
        select {

            font:
                inherit;
        }


        /* =========================================================
           PAGE
        ========================================================== */

        .staff-page {

            min-height:
                100vh;

            display:
                grid;

            grid-template-columns:
                1fr
                minmax(
                    430px,
                    560px
                );
        }


        /* =========================================================
           INTRO
        ========================================================== */

        .staff-intro {

            position:
                relative;

            overflow:
                hidden;

            display:
                flex;

            flex-direction:
                column;

            justify-content:
                space-between;

            padding:
                55px 7vw 45px;

            background:

                radial-gradient(
                    circle at 25% 25%,
                    rgba(
                        16,
                        199,
                        176,
                        .09
                    ),
                    transparent 30%
                ),

                linear-gradient(
                    160deg,
                    var(--intro-top),
                    var(--intro-bottom)
                );

            border-right:
                1px solid
                var(--border);

            transition:
                background .25s ease,
                border-color .25s ease;
        }


        .staff-intro::before {

            content:
                "";

            position:
                absolute;

            width:
                420px;

            height:
                420px;

            right:
                -200px;

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
                    .12
                );
        }


        /* =========================================================
           THEME TOGGLE
        ========================================================== */

        .theme-toggle {

            position:
                absolute;

            right:
                25px;

            top:
                25px;

            z-index:
                10;

            width:
                40px;

            height:
                40px;

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
                var(--soft-bg);

            color:
                var(--muted);

            cursor:
                pointer;

            transition:
                .2s ease;
        }


        .theme-toggle:hover {

            color:
                var(--primary);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    .28
                );

            transform:
                translateY(-1px);
        }


        /* =========================================================
           LOGO
        ========================================================== */

        .brand {

            position:
                relative;

            z-index:
                2;

            display:
                inline-flex;

            width:
                180px;
        }


        .brand-logo {

            display:
                block;

            width:
                180px;

            max-width:
                100%;

            height:
                auto;

            max-height:
                65px;

            object-fit:
                contain;

            border-radius:
                8px;
        }


        /* =========================================================
           INTRO CONTENT
        ========================================================== */

        .staff-intro-content {

            position:
                relative;

            z-index:
                2;

            max-width:
                650px;
        }


        .eyebrow {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            margin-bottom:
                14px;

            color:
                var(--primary);

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                1.5px;

            text-transform:
                uppercase;
        }


        .eyebrow::before {

            content:
                "";

            width:
                28px;

            height:
                2px;

            background:
                var(--hero-line);
        }


        .staff-intro h1 {

            color:
                var(--text-strong);

            font-size:
                clamp(
                    38px,
                    4vw,
                    62px
                );

            line-height:
                1;

            letter-spacing:
                -2.5px;
        }


        .intro-description {

            max-width:
                580px;

            margin-top:
                18px;

            color:
                var(--hero-text);

            font-size:
                13px;

            line-height:
                1.8;
        }


        /* =========================================================
           SECURITY CARDS
        ========================================================== */

        .security-cards {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap:
                12px;

            margin-top:
                30px;
        }


        .security-card {

            padding:
                17px;

            border:
                1px solid
                var(--border);

            border-radius:
                13px;

            background:
                var(--soft-bg);

            transition:
                background .25s ease,
                border-color .25s ease;
        }


        .security-icon {

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
                    .08
                );
        }


        .security-card strong {

            display:
                block;

            color:
                var(--text-strong);

            font-size:
                10px;
        }


        .security-card span {

            display:
                block;

            margin-top:
                5px;

            color:
                var(--muted-dark);

            font-size:
                8px;

            line-height:
                1.55;
        }


        /* =========================================================
           FOOTER
        ========================================================== */

        .staff-footer {

            position:
                relative;

            z-index:
                2;

            color:
                var(--footer-text);

            font-size:
                9px;
        }


        .staff-footer i {

            margin-right:
                5px;

            color:
                var(--primary);
        }


        /* =========================================================
           REGISTRATION AREA
        ========================================================== */

        .staff-area {

            position:
                relative;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            min-height:
                100vh;

            padding:
                35px;

            background:
                transparent;
        }


        /* =========================================================
           CARD
        ========================================================== */

        .staff-card {

            width:
                min(
                    100%,
                    475px
                );

            padding:
                32px;

            border:
                1px solid
                var(--border);

            border-radius:
                20px;

            background:

                linear-gradient(
                    145deg,
                    var(--card-start),
                    var(--card-end)
                );

            box-shadow:

                0 30px 80px
                rgba(
                    0,
                    0,
                    0,
                    .18
                );

            transition:
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        html[data-theme="light"]
        .staff-card {

            box-shadow:

                0 25px 55px
                rgba(
                    20,
                    54,
                    70,
                    .10
                );
        }


        /* =========================================================
           HOME BUTTON
        ========================================================== */

        .home-button {

            width:
                100%;

            min-height:
                44px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            margin-bottom:
                22px;

            padding:
                0 18px;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .35
                );

            border-radius:
                9px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    .08
                );

            color:
                var(--primary);

            font-size:
                11px;

            font-weight:
                800;

            text-transform:
                uppercase;

            transition:
                background .2s ease,
                border-color .2s ease,
                color .2s ease,
                transform .2s ease;
        }


        .home-button:hover {

            background:
                rgba(
                    16,
                    199,
                    176,
                    .16
                );

            border-color:
                var(--primary);

            color:
                var(--text-strong);

            transform:
                translateY(-1px);
        }


        /* =========================================================
           HEADER
        ========================================================== */

        .card-header {

            margin-bottom:
                25px;
        }


        .card-header small {

            display:
                block;

            margin-bottom:
                7px;

            color:
                var(--primary);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.3px;

            text-transform:
                uppercase;
        }


        .card-header h2 {

            color:
                var(--text-strong);

            font-size:
                27px;

            letter-spacing:
                -.8px;
        }


        .card-header p {

            margin-top:
                7px;

            color:
                var(--muted);

            font-size:
                10px;

            line-height:
                1.6;
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
                10px;

            margin-bottom:
                18px;

            padding:
                13px 14px;

            border-radius:
                10px;

            font-size:
                10px;

            line-height:
                1.55;
        }


        .alert-error {

            color:
                var(--red);

            background:
                rgba(
                    255,
                    114,
                    121,
                    .07
                );

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    .14
                );
        }


        .alert-success {

            color:
                var(--green);

            background:
                rgba(
                    85,
                    216,
                    144,
                    .07
                );

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    .14
                );
        }


        /* =========================================================
           FORM
        ========================================================== */

        .form-group {

            margin-bottom:
                16px;
        }


        .form-group label {

            display:
                block;

            margin-bottom:
                7px;

            color:
                var(--text-soft);

            font-size:
                9px;

            font-weight:
                800;
        }


        .input-wrap {

            position:
                relative;
        }


        .input-icon {

            position:
                absolute;

            left:
                13px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                var(--muted-darker);

            font-size:
                11px;

            pointer-events:
                none;
        }


        .form-control {

            width:
                100%;

            height:
                48px;

            padding:
                0 44px 0 38px;

            border:
                1px solid
                var(--border);

            border-radius:
                9px;

            outline:
                none;

            background:
                var(--input-bg);

            color:
                var(--text);

            font-size:
                10px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                background-color .25s ease,
                color .25s ease;
        }


        .form-control::placeholder {

            color:
                var(--muted-darker);
        }


        .form-control:focus {

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    .50
                );

            box-shadow:
                0 0 0 3px
                rgba(
                    16,
                    199,
                    176,
                    .07
                );

            background:
                var(--input-focus-bg);
        }


        select.form-control {

            cursor:
                pointer;
        }


        select.form-control option {

            background:
                var(--panel);

            color:
                var(--text-strong);
        }


        /* =========================================================
           SECURITY KEY
        ========================================================== */

        .security-wrap {

            position:
                relative;
        }


        .security-key-help {

            margin-top:
                6px;

            color:
                var(--muted-dark);

            font-size:
                8px;

            line-height:
                1.5;
        }


        /* =========================================================
           PASSWORD TOGGLE
        ========================================================== */

        .toggle-password {

            position:
                absolute;

            right:
                7px;

            top:
                50%;

            width:
                34px;

            height:
                34px;

            transform:
                translateY(-50%);

            display:
                grid;

            place-items:
                center;

            border:
                none;

            background:
                transparent;

            color:
                var(--muted-darker);

            cursor:
                pointer;

            border-radius:
                8px;

            transition:
                .2s ease;
        }


        .toggle-password:hover {

            color:
                var(--primary);

            background:
                var(--soft-bg);
        }


        /* =========================================================
           SUBMIT
        ========================================================== */

        .submit-button {

            width:
                100%;

            height:
                48px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            margin-top:
                3px;

            border:
                none;

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

            box-shadow:

                0 12px 28px
                rgba(
                    16,
                    199,
                    176,
                    .13
                );

            font-size:
                10px;

            font-weight:
                800;

            cursor:
                pointer;

            transition:
                transform .2s ease,
                box-shadow .2s ease,
                opacity .2s ease;
        }


        .submit-button:hover {

            transform:
                translateY(-1px);

            box-shadow:

                0 16px 32px
                rgba(
                    16,
                    199,
                    176,
                    .20
                );
        }


        .submit-button:disabled {

            opacity:
                .60;

            cursor:
                not-allowed;

            transform:
                none;
        }


        /* =========================================================
           BOTTOM LINKS
        ========================================================== */

        .bottom-links {

            display:
                flex;

            justify-content:
                space-between;

            gap:
                10px;

            margin-top:
                20px;

            padding-top:
                18px;

            border-top:
                1px solid
                var(--border);

            font-size:
                9px;

            color:
                var(--muted);
        }


        .bottom-links a {

            color:
                var(--primary);

            font-weight:
                800;
        }


        .bottom-links a:hover {

            text-decoration:
                underline;
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
                11px 12px;

            border:
                1px solid
                rgba(
                    255,
                    184,
                    77,
                    .10
                );

            border-radius:
                9px;

            background:
                rgba(
                    255,
                    184,
                    77,
                    .025
                );

            color:
                var(--muted);

            font-size:
                8px;

            line-height:
                1.6;
        }


        .security-note i {

            color:
                var(--orange);
        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (
            max-width: 1050px
        ) {

            .staff-page {

                grid-template-columns:
                    1fr;
            }


            .staff-intro {

                display:
                    none;
            }


            .staff-area {

                min-height:
                    100vh;

                padding:
                    25px;
            }


            .theme-toggle {

                position:
                    fixed;

                right:
                    18px;

                top:
                    18px;
            }

        }


        @media (
            max-width: 560px
        ) {

            .staff-area {

                padding:
                    14px;
            }


            .staff-card {

                padding:
                    24px 18px;

                border-radius:
                    16px;
            }


            .card-header h2 {

                font-size:
                    24px;
            }


            .bottom-links {

                flex-direction:
                    column;

                align-items:
                    center;

                text-align:
                    center;
            }

        }

    </style>


    <!-- =========================================================
         THEME INITIALIZATION
         Dark mode is the default.
    ========================================================== -->

    <script>

        (function () {

            const savedTheme =
                localStorage.getItem(
                    'medicare-theme'
                );


            const theme =
                savedTheme === 'light'
                    ? 'light'
                    : 'dark';


            document.documentElement.setAttribute(
                'data-theme',
                theme
            );

        })();

    </script>

</head>


<body>


<div class="staff-page">


    <!-- =====================================================
         LEFT INTRO
    ====================================================== -->

    <section class="staff-intro">


        <!-- THEME TOGGLE -->

        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="Switch theme"
            title="Switch theme"
        >

            <i
                id="themeIcon"
                class="fa-solid fa-sun"
            ></i>

        </button>


        <!-- LOGO -->

        <a
            href="index.php"
            class="brand"
            aria-label="MediCare Home"
        >

            <img
                src="images/logo.jpg"
                alt="MediCare Logo"
                class="brand-logo"
            >

        </a>


        <!-- INTRO -->

        <div class="staff-intro-content">


            <div class="eyebrow">

                Authorized Staff Portal

            </div>


            <h1>

                Secure staff
                registration.

            </h1>


            <p class="intro-description">

                Create authorized MediCare Doctor and
                Administrator accounts using the appropriate
                staff security key.

            </p>


            <div class="security-cards">


                <!-- DOCTOR -->

                <div class="security-card">


                    <div class="security-icon">

                        <i
                            class="fa-solid fa-user-doctor"
                        ></i>

                    </div>


                    <strong>

                        Doctor Accounts

                    </strong>


                    <span>

                        Protected registration for authorized
                        medical professionals.

                    </span>


                </div>


                <!-- ADMIN -->

                <div class="security-card">


                    <div class="security-icon">

                        <i
                            class="fa-solid fa-user-shield"
                        ></i>

                    </div>


                    <strong>

                        Administrator Accounts

                    </strong>


                    <span>

                        Restricted registration for authorized
                        MediCare administrators.

                    </span>


                </div>


            </div>


        </div>


        <!-- FOOTER -->

        <div class="staff-footer">

            <i class="fa-solid fa-lock"></i>

            Staff registration requires an authorized
            MediCare security key.

        </div>


    </section>


    <!-- =====================================================
         STAFF REGISTRATION AREA
    ====================================================== -->

    <main class="staff-area">


        <div class="staff-card">


            <!-- HOME -->

            <a
                href="index.php"
                class="home-button"
            >

                <i class="fa-solid fa-house"></i>

                Home

            </a>


            <!-- HEADER -->

            <header class="card-header">


                <small>

                    Protected Registration

                </small>


                <h2>

                    Create Staff Account

                </h2>


                <p>

                    This page is restricted to authorized
                    MediCare Doctors and Administrators.

                </p>


            </header>


            <!-- SUCCESS -->

            <?php if (
                $success !== ''
            ): ?>


                <div
                    class="alert alert-success"
                    role="status"
                >

                    <i
                        class="fa-solid fa-circle-check"
                    ></i>


                    <span>

                        <?= e(
                            $success
                        ) ?>

                    </span>

                </div>


            <?php endif; ?>


            <!-- ERROR -->

            <?php if (
                $error !== ''
            ): ?>


                <div
                    class="alert alert-error"
                    role="alert"
                >

                    <i
                        class="fa-solid fa-circle-exclamation"
                    ></i>


                    <span>

                        <?= e(
                            $error
                        ) ?>

                    </span>

                </div>


            <?php endif; ?>


            <!-- FORM -->

            <form
                method="POST"
                action="staff_register.php"
                autocomplete="off"
                id="staffRegistrationForm"
            >


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= e(
                        $csrfToken
                    ) ?>"
                >


                <!-- FULL NAME -->

                <div class="form-group">


                    <label for="fullname">

                        Full Name

                    </label>


                    <div class="input-wrap">


                        <i
                            class="
                                input-icon
                                fa-regular
                                fa-user
                            "
                        ></i>


                        <input
                            type="text"
                            id="fullname"
                            name="fullname"
                            class="form-control"
                            value="<?= e(
                                $fullname
                            ) ?>"
                            placeholder="Enter full name"
                            maxlength="150"
                            autocomplete="name"
                            required
                        >


                    </div>


                </div>


                <!-- EMAIL -->

                <div class="form-group">


                    <label for="email">

                        Email Address

                    </label>


                    <div class="input-wrap">


                        <i
                            class="
                                input-icon
                                fa-regular
                                fa-envelope
                            "
                        ></i>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="<?= e(
                                $email
                            ) ?>"
                            placeholder="staff@example.com"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >


                    </div>


                </div>


                <!-- ACCOUNT TYPE -->

                <div class="form-group">


                    <label for="role">

                        Account Type

                    </label>


                    <div class="input-wrap">


                        <i
                            class="
                                input-icon
                                fa-solid
                                fa-user-tag
                            "
                        ></i>


                        <select
                            id="role"
                            name="role"
                            class="form-control"
                            required
                        >


                            <option
                                value="Doctor"
                                <?= $role === 'Doctor'
                                    ? 'selected'
                                    : '' ?>
                            >

                                Doctor

                            </option>


                            <option
                                value="Admin"
                                <?= $role === 'Admin'
                                    ? 'selected'
                                    : '' ?>
                            >

                                Administrator

                            </option>


                        </select>


                    </div>


                </div>


                <!-- PASSWORD -->

                <div class="form-group">


                    <label for="password">

                        Password

                    </label>


                    <div class="input-wrap">


                        <i
                            class="
                                input-icon
                                fa-solid
                                fa-lock
                            "
                        ></i>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Create a password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            data-target="password"
                            aria-label="Show password"
                            title="Show password"
                        >

                            <i
                                class="fa-regular fa-eye"
                            ></i>

                        </button>


                    </div>


                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group">


                    <label for="confirm_password">

                        Confirm Password

                    </label>


                    <div class="input-wrap">


                        <i
                            class="
                                input-icon
                                fa-solid
                                fa-lock
                            "
                        ></i>


                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            data-target="confirm_password"
                            aria-label="Show password"
                            title="Show password"
                        >

                            <i
                                class="fa-regular fa-eye"
                            ></i>

                        </button>


                    </div>


                </div>


                <!-- SECURITY KEY -->

                <div class="form-group">


                    <label for="security_key">

                        Staff Security Key

                    </label>


                    <div class="security-wrap">


                        <div class="input-wrap">


                            <i
                                class="
                                    input-icon
                                    fa-solid
                                    fa-key
                                "
                            ></i>


                            <input
                                type="password"
                                id="security_key"
                                name="security_key"
                                class="form-control"
                                placeholder="Enter authorized security key"
                                autocomplete="off"
                                required
                            >


                            <button
                                type="button"
                                class="toggle-password"
                                data-target="security_key"
                                aria-label="Show security key"
                                title="Show security key"
                            >

                                <i
                                    class="fa-regular fa-eye"
                                ></i>

                            </button>


                        </div>


                        <div class="security-key-help">

                            Use the security key assigned for
                            the selected staff account type.

                        </div>


                    </div>


                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="submit-button"
                    id="submitButton"
                >

                    <i class="fa-solid fa-user-plus"></i>

                    Create Staff Account

                </button>


            </form>


            <!-- LINKS -->

            <div class="bottom-links">


                <span>

                    Already have an account?

                    <a href="login.php">

                        Sign In

                    </a>

                </span>


                <span>

                    Patient?

                    <a href="register.php">

                        Patient Registration

                    </a>

                </span>


            </div>


            <!-- SECURITY NOTE -->

            <div class="security-note">


                <i class="fa-solid fa-shield-halved"></i>


                <span>

                    Staff accounts are created with the
                    selected role and protected using CSRF
                    validation, password hashing and a separate
                    registration security key.

                </span>


            </div>


        </div>


    </main>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | THEME
        |--------------------------------------------------------------------------
        */

        const themeToggle =
            document.getElementById(
                'themeToggle'
            );


        const themeIcon =
            document.getElementById(
                'themeIcon'
            );


        function getCurrentTheme() {

            return document.documentElement.getAttribute(
                'data-theme'
            ) === 'light'
                ? 'light'
                : 'dark';
        }


        function updateThemeIcon() {

            if (
                !themeIcon ||
                !themeToggle
            ) {

                return;
            }


            const theme =
                getCurrentTheme();


            if (
                theme === 'light'
            ) {

                themeIcon.classList.remove(
                    'fa-sun'
                );


                themeIcon.classList.add(
                    'fa-moon'
                );


                themeToggle.setAttribute(
                    'aria-label',
                    'Switch to dark mode'
                );


                themeToggle.setAttribute(
                    'title',
                    'Switch to dark mode'
                );

            } else {

                themeIcon.classList.remove(
                    'fa-moon'
                );


                themeIcon.classList.add(
                    'fa-sun'
                );


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


        function setTheme(theme) {

            const safeTheme =
                theme === 'light'
                    ? 'light'
                    : 'dark';


            document.documentElement.setAttribute(
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

                // Ignore unavailable localStorage.
            }


            updateThemeIcon();
        }


        updateThemeIcon();


        if (
            themeToggle
        ) {

            themeToggle.addEventListener(
                'click',
                function () {

                    const currentTheme =
                        getCurrentTheme();


                    setTheme(
                        currentTheme === 'dark'
                            ? 'light'
                            : 'dark'
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD / SECURITY KEY TOGGLE
        |--------------------------------------------------------------------------
        */

        const toggleButtons =
            document.querySelectorAll(
                '.toggle-password'
            );


        toggleButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const targetId =
                            button.getAttribute(
                                'data-target'
                            );


                        const input =
                            document.getElementById(
                                targetId
                            );


                        const icon =
                            button.querySelector(
                                'i'
                            );


                        if (
                            !input
                        ) {

                            return;
                        }


                        if (
                            input.type === 'password'
                        ) {

                            input.type =
                                'text';


                            button.setAttribute(
                                'aria-label',
                                'Hide password'
                            );


                            button.setAttribute(
                                'title',
                                'Hide password'
                            );


                            if (
                                icon
                            ) {

                                icon.classList.remove(
                                    'fa-eye'
                                );


                                icon.classList.add(
                                    'fa-eye-slash'
                                );
                            }

                        } else {

                            input.type =
                                'password';


                            button.setAttribute(
                                'aria-label',
                                'Show password'
                            );


                            button.setAttribute(
                                'title',
                                'Show password'
                            );


                            if (
                                icon
                            ) {

                                icon.classList.remove(
                                    'fa-eye-slash'
                                );


                                icon.classList.add(
                                    'fa-eye'
                                );
                            }
                        }

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE SUBMISSION
        |--------------------------------------------------------------------------
        */

        const form =
            document.getElementById(
                'staffRegistrationForm'
            );


        const submitButton =
            document.getElementById(
                'submitButton'
            );


        if (
            form &&
            submitButton
        ) {

            form.addEventListener(
                'submit',
                function () {

                    if (
                        submitButton.disabled
                    ) {

                        return;
                    }


                    submitButton.disabled =
                        true;


                    submitButton.style.opacity =
                        '0.65';


                    submitButton.style.cursor =
                        'not-allowed';


                    submitButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Creating Account...';

                }
            );

        }


    }
);

</script>


</body>

</html>