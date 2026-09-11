<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| REDIRECT AUTHENTICATED USERS
|--------------------------------------------------------------------------
*/

if (is_logged_in()) {
    redirect('dashboard.php');
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$email = '';

$error = '';

$success = '';


/*
|--------------------------------------------------------------------------
| HANDLE LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    if ($error === '') {

        $email = strtolower(
            trim(
                post_string(
                    'email',
                    150
                )
            )
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $email === '' ||
            $password === ''
        ) {

            $error =
                'Please enter your email address and password.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Please enter a valid email address.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | FIND USER
            |--------------------------------------------------------------------------
            */

            try {

                $stmt = $pdo->prepare(
                    "SELECT
                        id,
                        fullname,
                        email,
                        password,
                        role,
                        is_active
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $email
                ]);

                $user = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


                /*
                |--------------------------------------------------------------------------
                | VERIFY PASSWORD
                |--------------------------------------------------------------------------
                */

                if (
                    !$user ||
                    empty($user['password']) ||
                    !password_verify(
                        $password,
                        (string) $user['password']
                    )
                ) {

                    $error =
                        'Invalid email address or password.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK ACCOUNT STATUS
                    |--------------------------------------------------------------------------
                    */

                    $isActive =
                        (int) (
                            $user['is_active']
                            ?? 1
                        ) === 1;


                    if (!$isActive) {

                        error_log(
                            'MediCare login blocked inactive account. User ID: ' .
                            (int) $user['id']
                        );

                        $error =
                            'This account has been deactivated. Please contact an administrator.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | NORMALIZE ROLE
                        |--------------------------------------------------------------------------
                        */

                        $databaseRole =
                            strtolower(
                                trim(
                                    (string) (
                                        $user['role'] ?? ''
                                    )
                                )
                            );


                        $normalizedRole = match (
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


                        if (
                            $normalizedRole === ''
                        ) {

                            error_log(
                                'MediCare login rejected invalid role for user ID ' .
                                (int) $user['id']
                            );

                            $error =
                                'This account has an invalid role. Please contact the administrator.';

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | REGENERATE SESSION
                            |--------------------------------------------------------------------------
                            */

                            session_regenerate_id(
                                true
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | CREATE AUTHENTICATED SESSION
                            |--------------------------------------------------------------------------
                            */

                            $_SESSION['user_id'] =
                                (int) $user['id'];

                            $_SESSION['fullname'] =
                                (string) $user['fullname'];

                            $_SESSION['email'] =
                                (string) $user['email'];

                            $_SESSION['role'] =
                                $normalizedRole;


                            /*
                            |--------------------------------------------------------------------------
                            | REFRESH CSRF
                            |--------------------------------------------------------------------------
                            */

                            $_SESSION['_csrf'] =
                                bin2hex(
                                    random_bytes(32)
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | LOGIN SUCCESS
                            |--------------------------------------------------------------------------
                            */

                            redirect(
                                'dashboard.php'
                            );

                            exit;
                        }
                    }
                }

            } catch (PDOException $e) {

                error_log(
                    'MediCare login database error: ' .
                    $e->getMessage()
                );

                $error =
                    'Unable to sign you in right now. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| REGISTRATION SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['registered']) &&
    $_GET['registered'] === '1'
) {

    $success =
        'Your MediCare account was created successfully. You can now sign in.';
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
        content="Sign in securely to your MediCare healthcare account."
    >


    <meta
        name="theme-color"
        content="#031b2d"
        id="themeColorMeta"
    >


    <title>
        Login | MediCare
    </title>


    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->

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


    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- =====================================================
         MEDICARE MAIN CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="style.css?v=20260817"
    >


    <style>

        /* =====================================================
           ROOT / DARK THEME DEFAULT
        ====================================================== */

        :root {

            --bg:
                #031b2d;

            --bg-deep:
                #021522;

            --panel:
                #082f49;

            --panel-dark:
                #06283f;

            --primary:
                #10c7b0;

            --primary-light:
                #63e7d8;

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

            --muted:
                #8fa8b8;

            --muted-dark:
                #678392;

            --surface:
                rgba(
                    8,
                    47,
                    70,
                    0.97
                );

            --surface-soft:
                rgba(
                    255,
                    255,
                    255,
                    0.025
                );

            --input-bg:
                rgba(
                    2,
                    22,
                    36,
                    0.65
                );

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
                    0.06
                );

            --shadow:
                rgba(
                    0,
                    0,
                    0,
                    0.18
                );
        }


        /* =====================================================
           LIGHT THEME
        ====================================================== */

        html[data-theme="light"] {

            --bg:
                #eef4f8;

            --bg-deep:
                #e3edf3;

            --panel:
                #ffffff;

            --panel-dark:
                #f3f7fa;

            --primary:
                #079f8f;

            --primary-light:
                #0b8378;

            --blue:
                #2476d3;

            --green:
                #189b63;

            --red:
                #d94f5a;

            --orange:
                #c47b14;

            --white:
                #10212b;

            --text:
                #24343e;

            --muted:
                #607581;

            --muted-dark:
                #71858f;

            --surface:
                #ffffff;

            --surface-soft:
                rgba(
                    15,
                    45,
                    62,
                    0.035
                );

            --input-bg:
                #f8fbfc;

            --border:
                rgba(
                    30,
                    58,
                    74,
                    0.14
                );

            --border-soft:
                rgba(
                    30,
                    58,
                    74,
                    0.08
                );

            --shadow:
                rgba(
                    31,
                    60,
                    78,
                    0.12
                );
        }


        /* =====================================================
           RESET
        ====================================================== */

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

            transition:
                background-color .25s ease;
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
                        0.10
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 85% 80%,
                    rgba(
                        44,
                        140,
                        255,
                        0.07
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
                        16,
                        199,
                        176,
                        0.08
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
        input {

            font:
                inherit;
        }


        /* =====================================================
           THEME TOGGLE
        ====================================================== */

        .theme-toggle-wrap {

            position:
                fixed;

            top:
                22px;

            right:
                28px;

            z-index:
                3000;
        }


        .theme-toggle {

            width:
                44px;

            height:
                44px;

            display:
                grid;

            place-items:
                center;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            background:
                var(--surface);

            color:
                var(--text);

            box-shadow:
                0 10px 28px
                var(--shadow);

            cursor:
                pointer;

            backdrop-filter:
                blur(10px);

            transition:
                background .25s ease,
                color .25s ease,
                border-color .25s ease,
                transform .2s ease,
                box-shadow .25s ease;
        }


        .theme-toggle:hover {

            color:
                var(--primary);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.35
                );

            transform:
                translateY(-1px);
        }


        .theme-toggle:focus-visible {

            outline:
                2px solid
                var(--primary);

            outline-offset:
                3px;
        }


        .theme-icon-moon {

            display:
                inline-block;
        }


        .theme-icon-sun {

            display:
                none;
        }


        html[data-theme="light"]
        .theme-icon-moon {

            display:
                none;
        }


        html[data-theme="light"]
        .theme-icon-sun {

            display:
                inline-block;
        }


        /* =====================================================
           PAGE
        ====================================================== */

        .login-page {

            min-height:
                100vh;

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1fr
                )
                minmax(
                    420px,
                    560px
                );
        }


        /* =====================================================
           LEFT INTRO
        ====================================================== */

        .intro {

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
                        0.09
                    ),
                    transparent 30%
                ),

                linear-gradient(
                    160deg,
                    var(--panel-dark),
                    var(--bg)
                );

            border-right:
                1px solid
                var(--border);

            transition:
                background .25s ease,
                border-color .25s ease;
        }


        .intro::before {

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
                    0.12
                );
        }


        /* =====================================================
           REAL LOGO
        ====================================================== */

        .brand {

            position:
                relative;

            z-index:
                2;

            display:
                inline-flex;

            align-items:
                center;

            width:
                180px;

            padding:
                0;
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

            border:
                none;

            border-radius:
                0;

            background:
                transparent;
        }


        /* =====================================================
           INTRO CONTENT
        ====================================================== */

        .intro-content {

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
                var(--primary);
        }


        .intro h1 {

            color:
                var(--white);

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
                var(--text);

            opacity:
                .80;

            font-size:
                13px;

            line-height:
                1.8;
        }


        /* =====================================================
           BENEFITS
        ====================================================== */

        .benefits {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                11px;

            margin-top:
                30px;
        }


        .benefit {

            padding:
                15px;

            border:
                1px solid
                var(--border);

            border-radius:
                13px;

            background:
                var(--surface-soft);

            transition:
                background .25s ease,
                border-color .25s ease;
        }


        .benefit-icon {

            width:
                34px;

            height:
                34px;

            display:
                grid;

            place-items:
                center;

            margin-bottom:
                10px;

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


        .benefit strong {

            display:
                block;

            color:
                var(--text);

            font-size:
                10px;
        }


        .benefit span {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--muted-dark);

            font-size:
                8px;

            line-height:
                1.5;
        }


        /* =====================================================
           INTRO FOOTER
        ====================================================== */

        .intro-footer {

            position:
                relative;

            z-index:
                2;

            color:
                var(--muted-dark);

            font-size:
                9px;
        }


        .intro-footer i {

            margin-right:
                5px;

            color:
                var(--primary);
        }


        /* =====================================================
           LOGIN AREA
        ====================================================== */

        .login-area {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                35px;
        }


        /* =====================================================
           LOGIN CARD
        ====================================================== */

        .login-card {

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
                var(--surface);

            box-shadow:
                0 30px 80px
                var(--shadow);

            transition:
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        /* =====================================================
           VISIBLE HOME BUTTON
        ====================================================== */

        .login-home-button {

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
                    0.35
                );

            border-radius:
                9px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );

            color:
                var(--primary);

            font-size:
                11px;

            font-weight:
                800;

            letter-spacing:
                .5px;

            text-transform:
                uppercase;

            transition:
                background .2s ease,
                border-color .2s ease,
                color .2s ease,
                transform .2s ease;
        }


        .login-home-button:hover {

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.16
                );

            border-color:
                var(--primary);

            color:
                var(--white);

            transform:
                translateY(-1px);
        }


        .login-home-button i {

            font-size:
                12px;
        }


        /* =====================================================
           MOBILE BRAND
        ====================================================== */

        .mobile-brand {

            display:
                none;

            align-items:
                center;

            justify-content:
                center;

            margin-bottom:
                20px;
        }


        .mobile-brand-logo {

            width:
                155px;

            max-width:
                100%;

            height:
                auto;

            max-height:
                58px;

            object-fit:
                contain;
        }


        /* =====================================================
           CARD HEADER
        ====================================================== */

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
                var(--text);

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


        /* =====================================================
           ALERTS
        ====================================================== */

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
                #ff9ca2;

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
                #79e8ad;

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


        /* =====================================================
           FORM
        ====================================================== */

        .form-group {

            margin-bottom:
                17px;
        }


        .form-group label {

            display:
                block;

            margin-bottom:
                7px;

            color:
                var(--text);

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
                var(--muted-dark);

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
                0 42px 0 38px;

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
                background .25s ease,
                color .25s ease;
        }


        .form-control::placeholder {

            color:
                var(--muted-dark);
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
        }


        /* =====================================================
           PASSWORD TOGGLE
        ====================================================== */

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
                var(--muted-dark);

            cursor:
                pointer;
        }


        .toggle-password:hover {

            color:
                var(--primary);
        }


        /* =====================================================
           SUBMIT
        ====================================================== */

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
                box-shadow .2s ease;
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


        /* =====================================================
           REGISTER LINK
        ====================================================== */

        .register-link {

            margin-top:
                20px;

            padding-top:
                18px;

            border-top:
                1px solid
                var(--border);

            text-align:
                center;

            color:
                var(--muted);

            font-size:
                9px;
        }


        .register-link a {

            color:
                var(--primary);

            font-weight:
                800;
        }


        .register-link a:hover {

            text-decoration:
                underline;
        }


        /* =====================================================
           SECURITY NOTE
        ====================================================== */

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
                    16,
                    199,
                    176,
                    .08
                );

            border-radius:
                9px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    .025
                );

            color:
                var(--muted-dark);

            font-size:
                8px;

            line-height:
                1.6;
        }


        .security-note i {

            color:
                var(--primary);
        }


        /* =====================================================
           LIGHT THEME SPECIFIC DETAILS
        ====================================================== */

        html[data-theme="light"] .intro {

            background:
                linear-gradient(
                    160deg,
                    #f7fbfd,
                    #e7f0f5
                );
        }


        html[data-theme="light"] .intro-description {

            color:
                #526874;

            opacity:
                1;
        }


        html[data-theme="light"] .benefit {

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.72
                );
        }


        html[data-theme="light"] .card-header h2 {

            color:
                #1d303a;
        }


        html[data-theme="light"] .login-home-button {

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.07
                );
        }


        html[data-theme="light"] .form-control {

            background:
                #f8fbfc;
        }


        html[data-theme="light"] .theme-toggle {

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.92
                );
        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1050px) {

            .login-page {

                grid-template-columns:
                    1fr;
            }


            .intro {

                display:
                    none;
            }


            .login-area {

                min-height:
                    100vh;

                padding:
                    25px;
            }


            .mobile-brand {

                display:
                    flex;
            }


            .login-home-button {

                margin-bottom:
                    18px;
            }


            .theme-toggle-wrap {

                top:
                    18px;

                right:
                    20px;
            }

        }


        @media (max-width: 560px) {

            .login-area {

                padding:
                    14px;
            }


            .login-card {

                padding:
                    24px 18px;

                border-radius:
                    16px;
            }


            .card-header h2 {

                font-size:
                    24px;
            }


            .mobile-brand-logo {

                width:
                    140px;
            }


            .theme-toggle-wrap {

                top:
                    14px;

                right:
                    14px;
            }


            .theme-toggle {

                width:
                    40px;

                height:
                    40px;

                border-radius:
                    10px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     THEME TOGGLE
====================================================== -->

<div class="theme-toggle-wrap">

    <button
        type="button"
        class="theme-toggle"
        id="themeToggle"
        aria-label="Switch to light mode"
        title="Switch theme"
    >

        <i
            class="
                fa-solid
                fa-moon
                theme-icon-moon
            "
        ></i>


        <i
            class="
                fa-solid
                fa-sun
                theme-icon-sun
            "
        ></i>

    </button>

</div>


<div class="login-page">


    <!-- =====================================================
         LEFT INTRODUCTION
    ====================================================== -->

    <section class="intro">


        <!-- REAL LOGO -->

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


        <!-- INTRO CONTENT -->

        <div class="intro-content">


            <div class="eyebrow">
                Secure Healthcare Portal
            </div>


            <h1>

                Welcome back
                to MediCare.

            </h1>


            <p class="intro-description">

                Sign in to securely manage your appointments,
                healthcare information and MediCare account.

            </p>


            <div class="benefits">


                <div class="benefit">

                    <div class="benefit-icon">

                        <i
                            class="fa-solid fa-shield-heart"
                        ></i>

                    </div>

                    <strong>
                        Secure Access
                    </strong>

                    <span>
                        Protected authentication for your account.
                    </span>

                </div>


                <div class="benefit">

                    <div class="benefit-icon">

                        <i
                            class="fa-regular fa-calendar-check"
                        ></i>

                    </div>

                    <strong>
                        Appointments
                    </strong>

                    <span>
                        Manage your healthcare appointments.
                    </span>

                </div>


                <div class="benefit">

                    <div class="benefit-icon">

                        <i
                            class="fa-solid fa-user-doctor"
                        ></i>

                    </div>

                    <strong>
                        Connected Care
                    </strong>

                    <span>
                        Keep your healthcare information organized.
                    </span>

                </div>


            </div>


        </div>


        <div class="intro-footer">

            <i class="fa-solid fa-lock"></i>

            MediCare protects your account information
            and limits access to authorized users.

        </div>


    </section>


    <!-- =====================================================
         LOGIN AREA
    ====================================================== -->

    <main class="login-area">


        <div class="login-card">


            <!-- =================================================
                 VISIBLE HOME BUTTON
            ================================================== -->

            <a
                href="index.php"
                class="login-home-button"
                aria-label="Return to MediCare homepage"
            >

                <i class="fa-solid fa-house"></i>

                Home

            </a>


            <!-- =================================================
                 MOBILE LOGO
            ================================================== -->

            <div class="mobile-brand">

                <img
                    src="images/logo.jpg"
                    alt="MediCare Logo"
                    class="mobile-brand-logo"
                >

            </div>


            <!-- =================================================
                 HEADER
            ================================================== -->

            <header class="card-header">


                <small>
                    Secure Sign In
                </small>


                <h2>
                    Sign in to MediCare
                </h2>


                <p>

                    Enter your email address and password
                    to continue to your account.

                </p>


            </header>


            <!-- =================================================
                 SUCCESS MESSAGE
            ================================================== -->

            <?php if ($success !== ''): ?>

                <div
                    class="alert alert-success"
                    role="status"
                >

                    <i
                        class="fa-solid fa-circle-check"
                    ></i>

                    <span>

                        <?= e($success) ?>

                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 ERROR MESSAGE
            ================================================== -->

            <?php if ($error !== ''): ?>

                <div
                    class="alert alert-error"
                    role="alert"
                >

                    <i
                        class="fa-solid fa-circle-exclamation"
                    ></i>

                    <span>

                        <?= e($error) ?>

                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 LOGIN FORM
            ================================================== -->

            <form
                method="POST"
                action="login.php"
                autocomplete="on"
            >


                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= e($csrfToken) ?>"
                >


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
                            value="<?= e($email) ?>"
                            placeholder="you@example.com"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >


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
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-label="Show password"
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-eye
                                "
                            ></i>

                        </button>


                    </div>


                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="submit-button"
                >

                    <i
                        class="fa-solid fa-right-to-bracket"
                    ></i>

                    Sign In

                </button>


            </form>


            <!-- =================================================
                 REGISTER LINK
            ================================================== -->

            <div class="register-link">

                Don't have a MediCare account?

                <a href="register.php">

                    Create an account

                </a>

            </div>


            <!-- =================================================
                 SECURITY NOTE
            ================================================== -->

            <div class="security-note">

                <i
                    class="fa-solid fa-shield-heart"
                ></i>

                <span>

                    Your login is protected by secure session
                    authentication. Patient accounts can be
                    created through the registration page.

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
        | THEME TOGGLE
        |--------------------------------------------------------------------------
        */

        const themeToggle =
            document.getElementById(
                'themeToggle'
            );


        const themeColorMeta =
            document.getElementById(
                'themeColorMeta'
            );


        const root =
            document.documentElement;


        function applyTheme(
            theme
        ) {

            root.setAttribute(
                'data-theme',
                theme
            );


            if (
                themeColorMeta
            ) {

                themeColorMeta.setAttribute(
                    'content',
                    theme === 'light'
                        ? '#eef4f8'
                        : '#031b2d'
                );
            }


            if (
                themeToggle
            ) {

                themeToggle.setAttribute(
                    'aria-label',
                    theme === 'light'
                        ? 'Switch to dark mode'
                        : 'Switch to light mode'
                );

                themeToggle.setAttribute(
                    'title',
                    theme === 'light'
                        ? 'Switch to dark mode'
                        : 'Switch to light mode'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LOAD SAVED THEME
        |--------------------------------------------------------------------------
        */

        let savedTheme = null;


        try {

            savedTheme =
                localStorage.getItem(
                    'medicare-theme'
                );

        } catch (
            error
        ) {

            savedTheme =
                null;
        }


        const initialTheme =
            savedTheme === 'light' ||
            savedTheme === 'dark'
                ? savedTheme
                : 'dark';


        applyTheme(
            initialTheme
        );


        /*
        |--------------------------------------------------------------------------
        | TOGGLE THEME
        |--------------------------------------------------------------------------
        */

        if (
            themeToggle
        ) {

            themeToggle.addEventListener(
                'click',
                function () {

                    const currentTheme =
                        root.getAttribute(
                            'data-theme'
                        ) || 'dark';


                    const nextTheme =
                        currentTheme === 'dark'
                            ? 'light'
                            : 'dark';


                    applyTheme(
                        nextTheme
                    );


                    try {

                        localStorage.setItem(
                            'medicare-theme',
                            nextTheme
                        );

                    } catch (
                        error
                    ) {

                        /*
                        | Ignore localStorage failure.
                        | Theme still changes for this page load.
                        */

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD TOGGLE
        |--------------------------------------------------------------------------
        */

        const toggleButton =
            document.getElementById(
                'togglePassword'
            );


        const passwordInput =
            document.getElementById(
                'password'
            );


        if (
            toggleButton &&
            passwordInput
        ) {

            toggleButton.addEventListener(
                'click',
                function () {

                    const icon =
                        toggleButton.querySelector(
                            'i'
                        );


                    if (
                        passwordInput.type ===
                        'password'
                    ) {

                        passwordInput.type =
                            'text';


                        toggleButton.setAttribute(
                            'aria-label',
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

                        passwordInput.type =
                            'password';


                        toggleButton.setAttribute(
                            'aria-label',
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

    }
);

</script>


</body>

</html>
