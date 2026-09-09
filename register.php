<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| REDIRECT ALREADY AUTHENTICATED USERS
|--------------------------------------------------------------------------
| Only redirect when a real authenticated session exists.
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

$success = '';

$error = '';


/*
|--------------------------------------------------------------------------
| HANDLE REGISTRATION
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
            'Your session security token is invalid or has expired. Please refresh the page and try again.';
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
            post_string(
                'fullname',
                150
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


        $password =
            (string) (
                $_POST['password'] ?? ''
            );


        $passwordConfirmation =
            (string) (
                $_POST['password_confirmation'] ?? ''
            );


        $termsAccepted =
            isset(
                $_POST['terms']
            ) &&
            $_POST['terms'] === '1';


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $fullname === '' ||
            $email === '' ||
            $password === '' ||
            $passwordConfirmation === ''
        ) {

            $error =
                'Please complete all required fields.';

        } elseif (
            mb_strlen(
                $fullname
            ) < 2
        ) {

            $error =
                'Please enter your full name.';

        } elseif (
            mb_strlen(
                $fullname
            ) > 150
        ) {

            $error =
                'Your full name is too long.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Please enter a valid email address.';

        } elseif (
            strlen(
                $password
            ) < 8
        ) {

            $error =
                'Your password must contain at least 8 characters.';

        } elseif (
            $password !==
            $passwordConfirmation
        ) {

            $error =
                'The password confirmation does not match.';

        } elseif (
            !$termsAccepted
        ) {

            $error =
                'Please acknowledge the account and privacy information before continuing.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK EXISTING ACCOUNT
            |--------------------------------------------------------------------------
            */

            try {

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
                        'An account with this email address already exists. Please sign in instead.';

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


                    if (
                        $passwordHash === false
                    ) {

                        throw new RuntimeException(
                            'Unable to securely process the password.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CREATE PATIENT ACCOUNT
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
                                'Patient'
                            )"
                        );


                    $insertStmt->execute([
                        $fullname,
                        $email,
                        $passwordHash
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS
                    |--------------------------------------------------------------------------
                    */

                    $success =
                        'Your MediCare account has been created successfully. You can now sign in.';


                    /*
                    |--------------------------------------------------------------------------
                    | CLEAR FORM
                    |--------------------------------------------------------------------------
                    */

                    $fullname = '';

                    $email = '';
                }


            } catch (
                PDOException $e
            ) {

                error_log(
                    'MediCare registration database error: ' .
                    $e->getMessage()
                );


                /*
                |--------------------------------------------------------------------------
                | HANDLE DUPLICATE EMAIL SAFELY
                |--------------------------------------------------------------------------
                */

                $duplicateKey =
                    isset(
                        $e->errorInfo[1]
                    ) &&
                    (int) $e->errorInfo[1] === 1062;


                if (
                    $duplicateKey
                ) {

                    $error =
                        'An account with this email address already exists. Please sign in instead.';

                } else {

                    $error =
                        'Unable to create your account right now. Please try again.';
                }


            } catch (
                RuntimeException $e
            ) {

                error_log(
                    'MediCare registration runtime error: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to create your account right now. Please try again.';
            }
        }
    }
}


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
        content="Create your secure MediCare healthcare account."
    >


    <meta
        name="theme-color"
        content="#031b2d"
        id="themeColorMeta"
    >


    <title>
        Create Account | MediCare
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
           SHARED THEME SYSTEM
        ========================================================== */

        :root {

            /* DARK THEME — DEFAULT */

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
                    0.05
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

            --intro-background:
                linear-gradient(
                    160deg,
                    #062a41,
                    #031b2d
                );

            --page-background:
                linear-gradient(
                    145deg,
                    #031b2d,
                    #021522
                );

            --card-background:
                linear-gradient(
                    145deg,
                    rgba(
                        8,
                        47,
                        70,
                        0.97
                    ),
                    rgba(
                        5,
                        31,
                        48,
                        0.98
                    )
                );

            --shadow:
                0 30px 80px
                rgba(
                    0,
                    0,
                    0,
                    0.18
                );

            --select-option-bg:
                #082f49;

            --select-option-text:
                #ffffff;
        }


        /*
        |--------------------------------------------------------------------------
        | LIGHT THEME
        |--------------------------------------------------------------------------
        */

        html[data-theme="light"] {

            --bg:
                #eef5f8;

            --bg-deep:
                #f7fbfc;

            --panel:
                #ffffff;

            --panel-dark:
                #f4f8fa;

            --primary:
                #0b9e8d;

            --primary-light:
                #0b897b;

            --blue:
                #2677d8;

            --green:
                #1d9a67;

            --red:
                #d84f5a;

            --orange:
                #c88716;

            --white:
                #102330;

            --text:
                #203744;

            --muted:
                #627985;

            --muted-dark:
                #748b95;

            --surface:
                rgba(
                    15,
                    55,
                    70,
                    0.035
                );

            --surface-strong:
                rgba(
                    15,
                    55,
                    70,
                    0.06
                );

            --input-bg:
                #f8fbfc;

            --border:
                rgba(
                    32,
                    55,
                    68,
                    0.14
                );

            --border-soft:
                rgba(
                    32,
                    55,
                    68,
                    0.08
                );

            --intro-background:
                linear-gradient(
                    160deg,
                    #e8f5f4,
                    #f7fbfc
                );

            --page-background:
                linear-gradient(
                    145deg,
                    #edf6f8,
                    #f9fcfd
                );

            --card-background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #f5fafb
                );

            --shadow:
                0 30px 80px
                rgba(
                    30,
                    64,
                    80,
                    0.10
                );

            --select-option-bg:
                #ffffff;

            --select-option-text:
                #203744;
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

                var(--page-background);

            overflow-x:
                hidden;

            transition:
                background .25s ease,
                color .25s ease;
        }


        body,
        .intro,
        .register-card,
        .benefit,
        .form-control,
        .home-button,
        .security-note,
        .theme-toggle {

            transition:
                background-color .25s ease,
                background .25s ease,
                color .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
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
           THEME TOGGLE
        ========================================================== */

        .theme-toggle-wrap {

            position:
                fixed;

            top:
                20px;

            right:
                20px;

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
                var(--surface-strong);

            color:
                var(--text);

            box-shadow:
                0 10px 28px
                rgba(
                    0,
                    0,
                    0,
                    0.10
                );

            cursor:
                pointer;

            backdrop-filter:
                blur(10px);
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


        /* =========================================================
           PAGE
        ========================================================== */

        .page {

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

            align-items:
                stretch;
        }


        /* =========================================================
           LEFT SIDE
        ========================================================== */

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

                var(--intro-background);

            border-right:
                1px solid
                var(--border);
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
                    0.010
                );

            pointer-events:
                none;
        }


        .intro::after {

            content:
                "";

            position:
                absolute;

            width:
                280px;

            height:
                280px;

            left:
                -160px;

            bottom:
                -140px;

            border-radius:
                50%;

            border:
                1px solid
                rgba(
                    44,
                    140,
                    255,
                    0.10
                );

            pointer-events:
                none;
        }


        /* =========================================================
           BRAND
        ========================================================== */

        .brand {

            position:
                relative;

            z-index:
                2;

            display:
                inline-flex;

            align-items:
                center;

            gap:
                11px;

            width:
                fit-content;
        }


        .brand-icon {

            width:
                48px;

            height:
                48px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                13px;

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
                21px;
        }


        .brand-text {

            display:
                flex;

            flex-direction:
                column;
        }


        .brand-text strong {

            color:
                var(--white);

            font-size:
                23px;

            line-height:
                1;
        }


        .brand-text strong span {

            color:
                var(--primary);
        }


        .brand-text small {

            margin-top:
                5px;

            color:
                var(--muted);

            font-size:
                8px;
        }


        /* =========================================================
           INTRO CONTENT
        ========================================================== */

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

            max-width:
                580px;

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
                var(--muted);

            font-size:
                13px;

            line-height:
                1.8;
        }


        /* =========================================================
           BENEFITS
        ========================================================== */

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

            max-width:
                610px;
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
                var(--surface);
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


        /* =========================================================
           FOOTER
        ========================================================== */

        .intro-footer {

            position:
                relative;

            z-index:
                2;

            color:
                var(--muted-dark);

            font-size:
                9px;

            line-height:
                1.6;
        }


        .intro-footer i {

            margin-right:
                5px;

            color:
                var(--primary);
        }


        /* =========================================================
           REGISTER AREA
        ========================================================== */

        .register-area {

            position:
                relative;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                35px;
        }


        /* =========================================================
           REGISTER CARD
        ========================================================== */

        .register-card {

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
                var(--card-background);

            box-shadow:
                var(--shadow);
        }


        /* =========================================================
           MOBILE BRAND
        ========================================================== */

        .mobile-brand {

            display:
                none;
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
                #ff9ca2;

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
                    0.14
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
                    0.07
                );

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    0.14
                );
        }


        /* =========================================================
           FORM
        ========================================================== */

        .form-group {

            margin-bottom:
                17px;
        }


        .form-group label {

            display:
                flex;

            align-items:
                center;

            gap:
                4px;

            margin-bottom:
                7px;

            color:
                var(--text);

            font-size:
                9px;

            font-weight:
                800;
        }


        .required {

            color:
                var(--red);
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

            z-index:
                2;
        }


        .form-control {

            width:
                100%;

            height:
                48px;

            padding:
                0 13px 0 38px;

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
                background .2s ease,
                color .2s ease;
        }


        .form-control::placeholder {

            color:
                var(--muted-dark);

            opacity:
                .85;
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


        .password-wrap
        .form-control {

            padding-right:
                42px;
        }


        select.form-control {

            cursor:
                pointer;

            appearance:
                auto;
        }


        select.form-control option {

            background:
                var(--select-option-bg);

            color:
                var(--select-option-text);
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

            border-radius:
                8px;

            background:
                transparent;

            color:
                var(--muted-dark);

            cursor:
                pointer;

            z-index:
                3;
        }


        .toggle-password:hover {

            color:
                var(--primary);
        }


        .toggle-password:focus-visible {

            outline:
                2px solid
                var(--primary);

            outline-offset:
                2px;
        }


        .field-help {

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
           PASSWORD STRENGTH
        ========================================================== */

        .strength {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap:
                4px;

            margin-top:
                8px;
        }


        .strength-bar {

            height:
                3px;

            border-radius:
                999px;

            background:
                rgba(
                    127,
                    157,
                    170,
                    0.18
                );

            transition:
                background .2s ease;
        }


        .strength-text {

            margin-top:
                5px;

            color:
                var(--muted-dark);

            font-size:
                8px;
        }


        /* =========================================================
           TERMS
        ========================================================== */

        .terms {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                8px;

            margin:
                19px 0;
        }


        .terms input {

            width:
                15px;

            height:
                15px;

            margin-top:
                1px;

            accent-color:
                var(--primary);

            flex-shrink:
                0;
        }


        .terms label {

            color:
                var(--muted);

            font-size:
                8px;

            line-height:
                1.6;
        }


        .terms a {

            color:
                var(--primary);

            font-weight:
                700;
        }


        .terms a:hover {

            text-decoration:
                underline;
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
                    0.13
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
                    0.18
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
           LOGIN
        ========================================================== */

        .login-link {

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


        .login-link a {

            color:
                var(--primary);

            font-weight:
                800;
        }


        .login-link a:hover {

            text-decoration:
                underline;
        }


        /* =========================================================
           SECURITY
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
                    16,
                    199,
                    176,
                    0.08
                );

            border-radius:
                9px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.025
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


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (
            max-width: 1050px
        ) {

            .page {

                grid-template-columns:
                    1fr;
            }


            .intro {

                display:
                    none;
            }


            .register-area {

                min-height:
                    100vh;

                padding:
                    25px;
            }


            .mobile-brand {

                display:
                    flex;

                align-items:
                    center;

                justify-content:
                    center;

                gap:
                    9px;

                margin-bottom:
                    20px;
            }


            .mobile-brand-icon {

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
            }


            .mobile-brand strong {

                color:
                    var(--text);

                font-size:
                    20px;
            }


            .mobile-brand strong span {

                color:
                    var(--primary);
            }


            .theme-toggle-wrap {

                top:
                    14px;

                right:
                    14px;
            }

        }


        @media (
            max-width: 560px
        ) {

            .register-area {

                padding:
                    14px;
            }


            .register-card {

                padding:
                    24px 18px;

                border-radius:
                    16px;
            }


            .card-header h2 {

                font-size:
                    24px;
            }


            .benefits {

                grid-template-columns:
                    1fr;
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


        @media (
            max-width: 400px
        ) {

            .register-card {

                padding:
                    21px 15px;
            }


            .card-header h2 {

                font-size:
                    22px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     THEME TOGGLE
========================================================= -->

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


<div class="page">


    <!-- =========================================================
         INTRO
    ========================================================== -->

    <section class="intro">


        <a
            href="login.php"
            class="brand"
            aria-label="MediCare Login"
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


        <div class="intro-content">


            <div class="eyebrow">

                Secure Healthcare Portal

            </div>


            <h1>

                Your healthcare,
                connected.

            </h1>


            <p class="intro-description">

                Create your MediCare account and gain
                secure access to appointments, healthcare
                information and your personal patient portal.

            </p>


            <div class="benefits">


                <div class="benefit">


                    <div class="benefit-icon">

                        <i class="fa-solid fa-shield-heart"></i>

                    </div>


                    <strong>

                        Secure Access

                    </strong>


                    <span>

                        Your account is protected with
                        secure authentication.

                    </span>

                </div>


                <div class="benefit">


                    <div class="benefit-icon">

                        <i class="fa-regular fa-calendar-check"></i>

                    </div>


                    <strong>

                        Easy Appointments

                    </strong>


                    <span>

                        Request and manage healthcare
                        appointments easily.

                    </span>

                </div>


                <div class="benefit">


                    <div class="benefit-icon">

                        <i class="fa-solid fa-user-doctor"></i>

                    </div>


                    <strong>

                        Connected Care

                    </strong>


                    <span>

                        Keep your healthcare journey
                        organized in one place.

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


    <!-- =========================================================
         REGISTER AREA
    ========================================================== -->

    <main class="register-area">


        <div class="register-card">


            <!-- =================================================
                 MOBILE BRAND
            ================================================== -->

            <div class="mobile-brand">


                <span class="mobile-brand-icon">

                    <i class="fa-solid fa-plus"></i>

                </span>


                <strong>

                    Medi<span>Care</span>

                </strong>


            </div>


            <!-- =================================================
                 HEADER
            ================================================== -->

            <header class="card-header">


                <small>

                    Patient Registration

                </small>


                <h2>

                    Create your account

                </h2>


                <p>

                    Enter your information below to create
                    your secure MediCare patient account.

                </p>


            </header>


            <!-- =================================================
                 ERROR
            ================================================== -->

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
                 SUCCESS
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


            <!-- =================================================
                 FORM
            ================================================== -->

            <form
                method="POST"
                action="register.php"
                autocomplete="on"
                id="registerForm"
            >


                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= e(
                        $csrfToken
                    ) ?>"
                >


                <!-- =================================================
                     FULL NAME
                ================================================== -->

                <div class="form-group">


                    <label for="fullname">

                        Full Name

                        <span class="required">

                            *

                        </span>

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
                            placeholder="Enter your full name"
                            maxlength="150"
                            autocomplete="name"
                            required
                        >


                    </div>


                </div>


                <!-- =================================================
                     EMAIL
                ================================================== -->

                <div class="form-group">


                    <label for="email">

                        Email Address

                        <span class="required">

                            *

                        </span>

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
                            placeholder="you@example.com"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >


                    </div>


                </div>


                <!-- =================================================
                     PASSWORD
                ================================================== -->

                <div class="form-group">


                    <label for="password">

                        Password

                        <span class="required">

                            *

                        </span>

                    </label>


                    <div
                        class="
                            input-wrap
                            password-wrap
                        "
                    >


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
                            placeholder="Create a strong password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            data-target="password"
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


                    <div
                        class="strength"
                        id="strengthBars"
                    >

                        <span class="strength-bar"></span>

                        <span class="strength-bar"></span>

                        <span class="strength-bar"></span>

                        <span class="strength-bar"></span>

                    </div>


                    <div
                        class="strength-text"
                        id="strengthText"
                    >

                        Use at least 8 characters.

                    </div>


                </div>


                <!-- =================================================
                     CONFIRM PASSWORD
                ================================================== -->

                <div class="form-group">


                    <label for="password_confirmation">

                        Confirm Password

                        <span class="required">

                            *

                        </span>

                    </label>


                    <div
                        class="
                            input-wrap
                            password-wrap
                        "
                    >


                        <i
                            class="
                                input-icon
                                fa-solid
                                fa-shield-halved
                            "
                        ></i>


                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            placeholder="Re-enter your password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            data-target="password_confirmation"
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


                    <div class="field-help">

                        Your password must contain at least
                        8 characters.

                    </div>


                </div>


                <!-- =================================================
                     TERMS
                ================================================== -->

                <div class="terms">


                    <input
                        type="checkbox"
                        id="terms"
                        name="terms"
                        value="1"
                        required
                    >


                    <label for="terms">

                        I understand that MediCare will use my
                        account information to provide secure
                        healthcare portal services.


                        <a href="privacy.php">

                            Privacy information

                        </a>


                    </label>


                </div>


                <!-- =================================================
                     SUBMIT
                ================================================== -->

                <button
                    type="submit"
                    class="submit-button"
                    id="submitButton"
                >

                    <i class="fa-solid fa-user-plus"></i>


                    Create MediCare Account

                </button>


            </form>


            <!-- =================================================
                 LOGIN
            ================================================== -->

            <div class="login-link">


                Already have a MediCare account?


                <a href="login.php">

                    Sign in

                </a>


            </div>


            <!-- =================================================
                 SECURITY
            ================================================== -->

            <div class="security-note">


                <i class="fa-solid fa-shield-heart"></i>


                <span>

                    Registration creates a Patient account.
                    Administrative and clinical staff accounts
                    should be created through authorized
                    MediCare administration.

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

        const root =
            document.documentElement;


        const themeToggle =
            document.getElementById(
                'themeToggle'
            );


        const themeMeta =
            document.getElementById(
                'themeColorMeta'
            );


        function getStoredTheme() {

            try {

                return localStorage.getItem(
                    'medicare-theme'
                );

            } catch (
                error
            ) {

                return null;
            }
        }


        function saveTheme(
            theme
        ) {

            try {

                localStorage.setItem(
                    'medicare-theme',
                    theme
                );

            } catch (
                error
            ) {

                /*
                |--------------------------------------------------------------------------
                | Ignore storage failures.
                |--------------------------------------------------------------------------
                */
            }
        }


        function applyTheme(
            theme
        ) {

            const selectedTheme =
                theme === 'light'
                    ? 'light'
                    : 'dark';


            root.setAttribute(
                'data-theme',
                selectedTheme
            );


            if (
                themeToggle
            ) {

                themeToggle.setAttribute(
                    'aria-label',
                    selectedTheme === 'dark'
                        ? 'Switch to light mode'
                        : 'Switch to dark mode'
                );


                themeToggle.setAttribute(
                    'title',
                    selectedTheme === 'dark'
                        ? 'Switch to light mode'
                        : 'Switch to dark mode'
                );
            }


            if (
                themeMeta
            ) {

                themeMeta.setAttribute(
                    'content',
                    selectedTheme === 'dark'
                        ? '#031b2d'
                        : '#eef5f8'
                );
            }
        }


        const storedTheme =
            getStoredTheme();


        if (
            storedTheme === 'light' ||
            storedTheme === 'dark'
        ) {

            applyTheme(
                storedTheme
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | Dark mode remains the default.
            |--------------------------------------------------------------------------
            */

            applyTheme(
                'dark'
            );
        }


        if (
            themeToggle
        ) {

            themeToggle.addEventListener(
                'click',
                function () {

                    const currentTheme =
                        root.getAttribute(
                            'data-theme'
                        ) ||
                        'dark';


                    const nextTheme =
                        currentTheme === 'dark'
                            ? 'light'
                            : 'dark';


                    applyTheme(
                        nextTheme
                    );


                    saveTheme(
                        nextTheme
                    );

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD TOGGLE
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.toggle-password'
            )
            .forEach(
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
                                input.type ===
                                'password'
                            ) {

                                input.type =
                                    'text';


                                button.setAttribute(
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

                                input.type =
                                    'password';


                                button.setAttribute(
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
            );


        /*
        |--------------------------------------------------------------------------
        | PASSWORD STRENGTH
        |--------------------------------------------------------------------------
        */

        const passwordInput =
            document.getElementById(
                'password'
            );


        const strengthBars =
            document.querySelectorAll(
                '.strength-bar'
            );


        const strengthText =
            document.getElementById(
                'strengthText'
            );


        function updatePasswordStrength() {

            if (
                !passwordInput ||
                !strengthText
            ) {

                return;
            }


            const password =
                passwordInput.value;


            let score =
                0;


            if (
                password.length >= 8
            ) {

                score++;
            }


            if (
                /[a-z]/.test(
                    password
                )
            ) {

                score++;
            }


            if (
                /[A-Z]/.test(
                    password
                )
            ) {

                score++;
            }


            if (
                /[0-9\W]/.test(
                    password
                )
            ) {

                score++;
            }


            strengthBars.forEach(
                function (
                    bar,
                    index
                ) {

                    if (
                        index < score
                    ) {

                        if (
                            score <= 1
                        ) {

                            bar.style.background =
                                '#ff7279';

                        } else if (
                            score === 2
                        ) {

                            bar.style.background =
                                '#ffb84d';

                        } else {

                            bar.style.background =
                                '#55d890';
                        }

                    } else {

                        bar.style.background =
                            'rgba(127,157,170,0.18)';
                    }

                }
            );


            if (
                password === ''
            ) {

                strengthText.textContent =
                    'Use at least 8 characters.';

            } else if (
                score <= 1
            ) {

                strengthText.textContent =
                    'Weak password. Add more characters and variety.';

            } else if (
                score === 2
            ) {

                strengthText.textContent =
                    'Fair password. Try adding uppercase letters or numbers.';

            } else if (
                score === 3
            ) {

                strengthText.textContent =
                    'Good password.';

            } else {

                strengthText.textContent =
                    'Strong password.';
            }

        }


        if (
            passwordInput
        ) {

            passwordInput.addEventListener(
                'input',
                updatePasswordStrength
            );


            updatePasswordStrength();
        }


        /*
        |--------------------------------------------------------------------------
        | CLIENT-SIDE FORM VALIDATION
        |--------------------------------------------------------------------------
        */

        const form =
            document.getElementById(
                'registerForm'
            );


        const terms =
            document.getElementById(
                'terms'
            );


        const confirmation =
            document.getElementById(
                'password_confirmation'
            );


        const submitButton =
            document.getElementById(
                'submitButton'
            );


        if (
            form
        ) {

            form.addEventListener(
                'submit',
                function (
                    event
                ) {

                    if (
                        terms &&
                        !terms.checked
                    ) {

                        event.preventDefault();


                        alert(
                            'Please acknowledge the account and privacy information before continuing.'
                        );


                        terms.focus();


                        return;
                    }


                    if (
                        passwordInput &&
                        confirmation &&
                        passwordInput.value !==
                        confirmation.value
                    ) {

                        event.preventDefault();


                        alert(
                            'The password confirmation does not match.'
                        );


                        confirmation.focus();


                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | PREVENT DOUBLE SUBMISSION
                    |--------------------------------------------------------------------------
                    */

                    if (
                        submitButton &&
                        !submitButton.disabled
                    ) {

                        submitButton.disabled =
                            true;


                        submitButton.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin"></i> Creating Account...';
                    }

                }
            );
        }


    }
);

</script>


</body>

</html>