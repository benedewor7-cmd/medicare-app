<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Admins and Doctors can edit patient records.
|--------------------------------------------------------------------------
*/

require_role('Admin', 'Doctor');


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserId = current_user_id();

$role = current_role();

$displayName = (string) (
    $_SESSION['fullname'] ?? 'User'
);

$email = (string) (
    $_SESSION['email'] ?? ''
);


/*
|--------------------------------------------------------------------------
| PATIENT ID
|--------------------------------------------------------------------------
*/

$patientId = get_int('id');

if ($patientId === null) {

    http_response_code(400);

    exit('Invalid patient ID.');
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$patient = null;

$first_name = '';
$last_name = '';
$dob = '';
$gender = '';
$phone = '';
$address = '';
$medical_history = '';

$error = '';
$success = '';


/*
|--------------------------------------------------------------------------
| LOAD PATIENT
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "SELECT
            id,
            first_name,
            last_name,
            dob,
            gender,
            phone,
            address,
            medical_history
         FROM patients
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->execute([
        $patientId
    ]);

    $patient = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$patient) {

        http_response_code(404);

        exit('Patient not found.');
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD FORM VALUES
    |--------------------------------------------------------------------------
    */

    $first_name = trim(
        (string) (
            $patient['first_name'] ?? ''
        )
    );

    $last_name = trim(
        (string) (
            $patient['last_name'] ?? ''
        )
    );

    $dob = trim(
        (string) (
            $patient['dob'] ?? ''
        )
    );

    $gender = trim(
        (string) (
            $patient['gender'] ?? ''
        )
    );

    $phone = trim(
        (string) (
            $patient['phone'] ?? ''
        )
    );

    $address = trim(
        (string) (
            $patient['address'] ?? ''
        )
    );

    $medical_history = trim(
        (string) (
            $patient['medical_history'] ?? ''
        )
    );


} catch (PDOException $e) {

    error_log(
        'MediCare edit patient load error: ' .
        $e->getMessage()
    );

    http_response_code(503);

    exit(
        'Unable to load the patient record right now.'
    );
}


/*
|--------------------------------------------------------------------------
| HANDLE UPDATE
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
    | READ FORM VALUES
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        $first_name = post_string(
            'first_name',
            100
        );

        $last_name = post_string(
            'last_name',
            100
        );

        $dob = post_string(
            'dob',
            10
        );

        $gender = post_string(
            'gender',
            20
        );

        $phone = post_string(
            'phone',
            30
        );

        $address = post_string(
            'address',
            500
        );

        $medical_history = post_string(
            'medical_history',
            2000
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $first_name === '' ||
            $last_name === '' ||
            $dob === '' ||
            $gender === '' ||
            $phone === ''
        ) {

            $error =
                'Please complete all required fields.';

        } elseif (
            mb_strlen($first_name) < 2 ||
            mb_strlen($last_name) < 2
        ) {

            $error =
                'Please enter valid first and last names.';

        } elseif (
            !preg_match(
                '/^[\p{L}\p{M}\s\'\-]+$/u',
                $first_name
            ) ||
            !preg_match(
                '/^[\p{L}\p{M}\s\'\-]+$/u',
                $last_name
            )
        ) {

            $error =
                'Names contain invalid characters.';

        } elseif (
            !in_array(
                $gender,
                [
                    'Male',
                    'Female',
                    'Other'
                ],
                true
            )
        ) {

            $error =
                'Please select a valid gender.';

        } elseif (
            !preg_match(
                '/^[0-9+\-\s().]{7,30}$/',
                $phone
            )
        ) {

            $error =
                'Please enter a valid phone number.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | DATE VALIDATION
            |--------------------------------------------------------------------------
            */

            $dateObject =
                safe_date($dob);


            if (
                $dateObject === null
            ) {

                $error =
                    'Please enter a valid date of birth.';

            } elseif (
                $dateObject >
                new DateTimeImmutable('today')
            ) {

                $error =
                    'Date of birth cannot be in the future.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | UPDATE PATIENT
                |--------------------------------------------------------------------------
                */

                try {

                    $pdo->beginTransaction();


                    /*
                    |--------------------------------------------------------------------------
                    | LOCK PATIENT RECORD
                    |--------------------------------------------------------------------------
                    */

                    $lockStmt = $pdo->prepare(
                        "SELECT
                            id
                         FROM patients
                         WHERE id = ?
                         LIMIT 1
                         FOR UPDATE"
                    );

                    $lockStmt->execute([
                        $patientId
                    ]);


                    $lockedPatient =
                        $lockStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        !$lockedPatient
                    ) {

                        throw new RuntimeException(
                            'The patient record no longer exists.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE
                    |--------------------------------------------------------------------------
                    */

                    $updateStmt = $pdo->prepare(
                        "UPDATE patients
                         SET
                            first_name = ?,
                            last_name = ?,
                            dob = ?,
                            gender = ?,
                            phone = ?,
                            address = ?,
                            medical_history = ?
                         WHERE id = ?"
                    );


                    $updateStmt->execute([
                        $first_name,
                        $last_name,
                        $dob,
                        $gender,
                        $phone,
                        $address !== ''
                            ? $address
                            : null,
                        $medical_history !== ''
                            ? $medical_history
                            : null,
                        $patientId
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | VERIFY UPDATE
                    |--------------------------------------------------------------------------
                    */

                    $verifyStmt = $pdo->prepare(
                        "SELECT
                            first_name,
                            last_name,
                            dob,
                            gender,
                            phone,
                            address,
                            medical_history
                         FROM patients
                         WHERE id = ?
                         LIMIT 1"
                    );

                    $verifyStmt->execute([
                        $patientId
                    ]);


                    $verifiedPatient =
                        $verifyStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        !$verifiedPatient
                    ) {

                        throw new RuntimeException(
                            'The patient record could not be verified after updating.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | COMMIT
                    |--------------------------------------------------------------------------
                    */

                    $pdo->commit();


                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECT
                    |--------------------------------------------------------------------------
                    */

                    redirect(
                        'view_patient.php?id=' .
                        $patientId .
                        '&updated=1'
                    );

                    exit;


                } catch (RuntimeException $e) {

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();
                    }


                    error_log(
                        'MediCare edit patient runtime error: ' .
                        $e->getMessage()
                    );


                    $error =
                        $e->getMessage();


                } catch (PDOException $e) {

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();
                    }


                    error_log(
                        'MediCare edit patient update error: ' .
                        $e->getMessage()
                    );


                    $error =
                        'Unable to update the patient record right now. Please try again.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$patientFullName =
    trim(
        $first_name .
        ' ' .
        $last_name
    );


if (
    $patientFullName === ''
) {

    $patientFullName =
        'Patient #' . $patientId;
}


/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$userInitial = '';

$nameParts =
    preg_split(
        '/\s+/u',
        trim($displayName)
    );


if (
    is_array($nameParts)
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

            $userInitial .=
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
    $userInitial === ''
) {

    $userInitial =
        'U';
}


$today =
    new DateTimeImmutable('today');

$todayString =
    $today->format('Y-m-d');

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
        content="Edit MediCare patient information."
    >


    <title>
        Edit Patient | MediCare
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
                | Dark mode remains the default.
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
        ========================================================== */

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

            /* =====================================================
               SHARED THEME VARIABLES
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
                #607b8a;

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

            --sidebar-width:
                255px;
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
        select,
        textarea,
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
           USER BOX
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
           ACTION BAR
        ========================================================== */

        .action-bar {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                12px;

            margin-bottom:
                18px;
        }


        .action-link {

            min-height:
                40px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            padding:
                0 14px;

            border-radius:
                9px;

            font-size:
                9px;

            font-weight:
                800;
        }


        .back-link {

            color:
                var(--muted);

            background:
                var(--surface);

            border:
                1px solid
                var(--border);

            transition:
                color .2s ease,
                background .2s ease;
        }


        .back-link:hover {

            color:
                var(--primary);
        }


        .view-link {

            color:
                #8dbdff;

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
                    0.14
                );

            transition:
                .2s ease;
        }


        .view-link:hover {

            color:
                white;

            background:
                rgba(
                    44,
                    140,
                    255,
                    0.14
                );
        }


        /* =========================================================
           HERO
        ========================================================== */

        .edit-hero {

            position:
                relative;

            overflow:
                hidden;

            margin-bottom:
                18px;

            padding:
                27px 30px;

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


        .edit-hero::after {

            content:
                "";

            position:
                absolute;

            width:
                280px;

            height:
                280px;

            right:
                -105px;

            top:
                -135px;

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


        .hero-copy h1 {

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


        .hero-copy p {

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


        .patient-badge {

            position:
                relative;

            z-index:
                2;

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            margin-top:
                12px;

            padding:
                7px 10px;

            border-radius:
                999px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.06
                );

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            color:
                var(--white-text);

            font-size:
                8px;

            font-weight:
                800;
        }


        .patient-badge i {

            color:
                var(--primary);
        }


        /* =========================================================
           ALERT
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

            line-height:
                1.5;
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


        .panel-header {

            min-height:
                70px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

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

        .form-grid {

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


        .full-width {

            grid-column:
                1 / -1;
        }


        .form-group label {

            display:
                flex;

            align-items:
                center;

            gap:
                3px;

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


        .required {

            color:
                var(--red);
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


        textarea.form-control {

            min-height:
                120px;

            padding:
                12px 13px;

            resize:
                vertical;

            line-height:
                1.6;
        }


        select.form-control {

            cursor:
                pointer;
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
           FORM FOOTER
        ========================================================== */

        .form-footer {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            margin-top:
                24px;

            padding-top:
                20px;

            border-top:
                1px solid
                var(--border);
        }


        .security-note {

            max-width:
                500px;

            color:
                var(--muted-text);

            font-size:
                8px;

            line-height:
                1.6;
        }


        .security-note i {

            color:
                var(--primary);
        }


        .form-actions {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            flex-shrink:
                0;
        }


        .button {

            min-height:
                43px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            padding:
                0 15px;

            border-radius:
                9px;

            font-size:
                9px;

            font-weight:
                800;

            transition:
                transform .2s ease,
                opacity .2s ease;
        }


        .button:hover {

            transform:
                translateY(-1px);
        }


        .cancel-button {

            color:
                var(--muted);

            background:
                var(--surface);

            border:
                1px solid
                var(--border);
        }


        .cancel-button:hover {

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
        }


        .save-button:hover {

            box-shadow:

                0 14px 30px
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
           THEME TRANSITIONS
        ========================================================== */

        .sidebar,
        .topbar,
        .edit-hero,
        .panel,
        .user-box,
        .top-icon,
        .form-control {

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


        @media (max-width: 750px) {

            .content {

                padding:
                    20px 14px 30px;
            }


            .action-bar {

                flex-direction:
                    column;

                align-items:
                    stretch;
            }


            .action-link {

                width:
                    100%;
            }


            .edit-hero {

                padding:
                    24px 20px;
            }


            .form-grid {

                grid-template-columns:
                    1fr;
            }


            .full-width {

                grid-column:
                    auto;
            }


            .form-footer {

                flex-direction:
                    column;

                align-items:
                    stretch;
            }


            .security-note {

                max-width:
                    none;
            }


            .form-actions {

                width:
                    100%;
            }


            .button {

                flex:
                    1;
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


            .form-body {

                padding:
                    17px;
            }


            .form-actions {

                flex-direction:
                    column;
            }


            .button {

                width:
                    100%;
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


    <div class="sidebar-spacer"></div>


    <!-- USER BOX -->

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
                        $role
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
                Edit Patient
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

                <i class="fa-solid fa-house"></i>

            </a>


            <a
                href="patients.php"
                class="top-icon"
                title="Patients"
            >

                <i class="fa-solid fa-users"></i>

            </a>


        </div>


    </header>


    <!-- =======================================================
         CONTENT
    ======================================================== -->

    <div class="content">


        <!-- =================================================
             ACTION BAR
        ================================================== -->

        <div class="action-bar">


            <a
                href="view_patient.php?id=<?= (int) $patientId ?>"
                class="action-link back-link"
            >

                <i class="fa-solid fa-arrow-left"></i>

                Back to Patient Profile

            </a>


            <a
                href="patients.php"
                class="action-link view-link"
            >

                <i class="fa-solid fa-users"></i>

                Patient List

            </a>


        </div>


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="edit-hero">


            <div class="hero-copy">


                <div class="eyebrow">

                    Patient Management

                </div>


                <h1>
                    Edit Patient Information
                </h1>


                <p>

                    Update registered patient details while
                    keeping the existing healthcare record secure.

                </p>


                <span class="patient-badge">

                    <i class="fa-solid fa-id-card"></i>

                    Patient #

                    <?= (int) $patientId ?>

                    &nbsp;•&nbsp;

                    <?= e(
                        $patientFullName
                    ) ?>

                </span>


            </div>


        </section>


        <!-- =================================================
             ERROR
        ================================================== -->

        <?php if (
            $error !== ''
        ): ?>

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

                <span>

                    <?= e(
                        $error
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FORM PANEL
        ================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Patient Information
                    </span>

                    <h2>
                        Update Details
                    </h2>

                    <p>
                        Fields marked with * are required.
                    </p>

                </div>


            </header>


            <div class="form-body">


                <form
                    action="edit_patient.php?id=<?= (int) $patientId ?>"
                    method="POST"
                    autocomplete="off"
                    id="editPatientForm"
                >


                    <!-- CSRF -->

                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?= e(
                            csrf_token()
                        ) ?>"
                    >


                    <div class="form-grid">


                        <!-- FIRST NAME -->

                        <div class="form-group">


                            <label for="first_name">

                                First Name

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                class="form-control"
                                value="<?= e(
                                    $first_name
                                ) ?>"
                                maxlength="100"
                                autocomplete="given-name"
                                required
                            >


                        </div>


                        <!-- LAST NAME -->

                        <div class="form-group">


                            <label for="last_name">

                                Last Name

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                class="form-control"
                                value="<?= e(
                                    $last_name
                                ) ?>"
                                maxlength="100"
                                autocomplete="family-name"
                                required
                            >


                        </div>


                        <!-- DATE OF BIRTH -->

                        <div class="form-group">


                            <label for="dob">

                                Date of Birth

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="date"
                                id="dob"
                                name="dob"
                                class="form-control"
                                value="<?= e(
                                    $dob
                                ) ?>"
                                max="<?= e(
                                    $todayString
                                ) ?>"
                                required
                            >


                        </div>


                        <!-- GENDER -->

                        <div class="form-group">


                            <label for="gender">

                                Gender

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select
                                id="gender"
                                name="gender"
                                class="form-control"
                                required
                            >

                                <option value="">
                                    Select gender
                                </option>


                                <option
                                    value="Male"
                                    <?= $gender === 'Male'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Male
                                </option>


                                <option
                                    value="Female"
                                    <?= $gender === 'Female'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Female
                                </option>


                                <option
                                    value="Other"
                                    <?= $gender === 'Other'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Other
                                </option>


                            </select>


                        </div>


                        <!-- PHONE -->

                        <div class="form-group full-width">


                            <label for="phone">

                                Phone Number

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                class="form-control"
                                value="<?= e(
                                    $phone
                                ) ?>"
                                maxlength="30"
                                autocomplete="tel"
                                required
                            >


                            <p class="field-help">

                                Use the primary number MediCare
                                should use to contact the patient.

                            </p>


                        </div>


                        <!-- ADDRESS -->

                        <div class="form-group full-width">


                            <label for="address">

                                Residential Address

                            </label>


                            <textarea
                                id="address"
                                name="address"
                                class="form-control"
                                maxlength="500"
                                autocomplete="street-address"
                            ><?= e(
                                $address
                            ) ?></textarea>


                            <p class="field-help">

                                Keep the patient's current
                                residential/contact address.

                            </p>


                        </div>


                        <!-- MEDICAL HISTORY -->

                        <div class="form-group full-width">


                            <label for="medical_history">

                                Medical History / Diagnosis

                            </label>


                            <textarea
                                id="medical_history"
                                name="medical_history"
                                class="form-control"
                                maxlength="2000"
                            ><?= e(
                                $medical_history
                            ) ?></textarea>


                            <p class="field-help">

                                Only update information that
                                belongs in the patient's registered
                                medical history.

                            </p>


                        </div>


                    </div>


                    <!-- FORM FOOTER -->

                    <div class="form-footer">


                        <div class="security-note">

                            <i class="fa-solid fa-shield-heart"></i>

                            &nbsp;

                            Patient information can only be
                            modified by authorized MediCare
                            personnel. Changes are protected
                            by CSRF validation and a locked
                            database update.

                        </div>


                        <div class="form-actions">


                            <a
                                href="view_patient.php?id=<?= (int) $patientId ?>"
                                class="
                                    button
                                    cancel-button
                                "
                            >

                                Cancel

                            </a>


                            <button
                                type="submit"
                                class="
                                    button
                                    save-button
                                "
                                id="saveButton"
                            >

                                <i class="fa-solid fa-floppy-disk"></i>

                                Save Changes

                            </button>


                        </div>


                    </div>


                </form>


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

        const form =
            document.getElementById(
                'editPatientForm'
            );


        const saveButton =
            document.getElementById(
                'saveButton'
            );


        if (
            form &&
            saveButton
        ) {

            form.addEventListener(
                'submit',
                function () {

                    if (
                        saveButton.disabled
                    ) {

                        return;
                    }


                    saveButton.disabled =
                        true;


                    saveButton.style.opacity =
                        '0.65';


                    saveButton.style.cursor =
                        'not-allowed';


                    saveButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DATE SAFETY
        |--------------------------------------------------------------------------
        */

        const dobInput =
            document.getElementById(
                'dob'
            );


        if (
            dobInput
        ) {

            const today =
                new Date();


            const year =
                today.getFullYear();


            const month =
                String(
                    today.getMonth() + 1
                ).padStart(
                    2,
                    '0'
                );


            const day =
                String(
                    today.getDate()
                ).padStart(
                    2,
                    '0'
                );


            dobInput.max =
                `${year}-${month}-${day}`;
        }


    }
);

</script>


</body>

</html>