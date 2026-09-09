<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|-------------------------------------------------------------------------- 
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Admins and Doctors can register patient records.
|--------------------------------------------------------------------------
*/

require_role('Admin', 'Doctor');


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserId = current_user_id();

$currentRole = current_role();

$displayName = (string) (
    $_SESSION['fullname'] ?? 'User'
);

$email = (string) (
    $_SESSION['email'] ?? ''
);


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';

$firstName = '';

$lastName = '';

$dob = '';

$gender = '';

$phone = '';

$address = '';

$medicalHistory = '';


/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
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

        $firstName =
            post_string(
                'first_name',
                100
            );


        $lastName =
            post_string(
                'last_name',
                100
            );


        $dob =
            post_string(
                'dob',
                10
            );


        $gender =
            post_string(
                'gender',
                20
            );


        $phone =
            post_string(
                'phone',
                20
            );


        $address =
            post_string(
                'address',
                1000
            );


        $medicalHistory =
            post_string(
                'medical_history',
                5000
            );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        if (
            $firstName === '' ||
            $lastName === '' ||
            $dob === '' ||
            $gender === '' ||
            $phone === ''
        ) {

            $error =
                'Please complete all required fields.';

        } elseif (
            mb_strlen(
                $firstName
            ) < 2
        ) {

            $error =
                'Please enter a valid first name.';

        } elseif (
            mb_strlen(
                $lastName
            ) < 2
        ) {

            $error =
                'Please enter a valid last name.';

        } elseif (
            !preg_match(
                '/^[\p{L}\p{M}\s\'\-]+$/u',
                $firstName
            )
        ) {

            $error =
                'Please enter a valid first name.';

        } elseif (
            !preg_match(
                '/^[\p{L}\p{M}\s\'\-]+$/u',
                $lastName
            )
        ) {

            $error =
                'Please enter a valid last name.';

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

        } else {

            /*
            |--------------------------------------------------------------------------
            | DATE VALIDATION
            |--------------------------------------------------------------------------
            */

            $dobObject =
                safe_date(
                    $dob
                );


            if (
                $dobObject === null
            ) {

                $error =
                    'Please enter a valid date of birth.';

            } elseif (
                $dobObject >
                new DateTimeImmutable(
                    'today'
                )
            ) {

                $error =
                    'Date of birth cannot be in the future.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | PHONE VALIDATION
                |--------------------------------------------------------------------------
                */

                $phoneDigits =
                    preg_replace(
                        '/\D/',
                        '',
                        $phone
                    );


                if (
                    $phoneDigits === null ||
                    strlen($phoneDigits) < 7
                ) {

                    $error =
                        'Please enter a valid phone number.';

                } elseif (
                    strlen($phoneDigits) > 15
                ) {

                    $error =
                        'Please enter a valid phone number.';
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE PATIENT
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        try {

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CHECK FOR DUPLICATE PATIENT
            |--------------------------------------------------------------------------
            |
            | We normalize the phone number by removing formatting characters
            | before comparing it with existing patient records.
            |--------------------------------------------------------------------------
            */

            $duplicateStmt =
                $pdo->prepare(
                    "SELECT
                        id,
                        first_name,
                        last_name,
                        dob,
                        phone
                     FROM patients
                     WHERE
                        LOWER(TRIM(first_name)) = LOWER(TRIM(?))
                        AND LOWER(TRIM(last_name)) = LOWER(TRIM(?))
                        AND dob = ?
                     ORDER BY id ASC
                     LIMIT 50
                     FOR UPDATE"
                );


            $duplicateStmt->execute([
                $firstName,
                $lastName,
                $dob
            ]);


            $possibleDuplicates =
                $duplicateStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $existingPatientId = null;


            /*
            |--------------------------------------------------------------------------
            | COMPARE NORMALIZED PHONE NUMBERS
            |--------------------------------------------------------------------------
            */

            if (
                !empty(
                    $possibleDuplicates
                )
            ) {

                foreach (
                    $possibleDuplicates
                    as $possiblePatient
                ) {

                    $existingPhone =
                        (string) (
                            $possiblePatient['phone']
                            ?? ''
                        );


                    $existingPhoneDigits =
                        preg_replace(
                            '/\D/',
                            '',
                            $existingPhone
                        );


                    if (
                        $existingPhoneDigits !== null &&
                        $existingPhoneDigits !== '' &&
                        $existingPhoneDigits === $phoneDigits
                    ) {

                        $existingPatientId =
                            (int) (
                                $possiblePatient['id']
                                ?? 0
                            );

                        break;
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | DUPLICATE FOUND
            |--------------------------------------------------------------------------
            */

            if (
                $existingPatientId !== null &&
                $existingPatientId > 0
            ) {

                $pdo->rollBack();


                $error =
                    'A patient with the same name, date of birth and phone number already exists.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | INSERT PATIENT
                |--------------------------------------------------------------------------
                */

                $insertStmt =
                    $pdo->prepare(
                        "INSERT INTO patients
                        (
                            user_id,
                            first_name,
                            last_name,
                            dob,
                            gender,
                            phone,
                            address,
                            medical_history,
                            is_active
                        )
                        VALUES
                        (
                            NULL,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            1
                        )"
                    );


                $insertStmt->execute([
                    $firstName,
                    $lastName,
                    $dob,
                    $gender,
                    $phone,
                    $address !== ''
                        ? $address
                        : null,
                    $medicalHistory !== ''
                        ? $medicalHistory
                        : null
                ]);


                /*
                |--------------------------------------------------------------------------
                | GET NEW PATIENT ID
                |--------------------------------------------------------------------------
                */

                $newPatientId =
                    (int) $pdo->lastInsertId();


                if (
                    $newPatientId < 1
                ) {

                    throw new RuntimeException(
                        'Patient record was not created correctly.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | VERIFY CREATED RECORD
                |--------------------------------------------------------------------------
                */

                $verifyStmt =
                    $pdo->prepare(
                        "SELECT
                            id,
                            first_name,
                            last_name,
                            is_active
                         FROM patients
                         WHERE id = ?
                         LIMIT 1"
                    );


                $verifyStmt->execute([
                    $newPatientId
                ]);


                $createdPatient =
                    $verifyStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$createdPatient
                ) {

                    throw new RuntimeException(
                        'The patient record could not be verified after creation.'
                    );
                }


                if (
                    (int) (
                        $createdPatient['is_active']
                        ?? 0
                    ) !== 1
                ) {

                    throw new RuntimeException(
                        'The new patient record was not activated correctly.'
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
                | REDIRECT AFTER SUCCESS
                |--------------------------------------------------------------------------
                |
                | PRG pattern prevents duplicate registration after refresh.
                |--------------------------------------------------------------------------
                */

                redirect(
                    'view_patient.php?' .
                    http_build_query([
                        'id' =>
                            $newPatientId,
                        'created' =>
                            '1',
                    ])
                );


                exit;
            }


        } catch (
            RuntimeException $e
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            error_log(
                'MediCare patient registration runtime error: ' .
                $e->getMessage()
            );


            $error =
                'Unable to register the patient right now. Please try again.';

        } catch (
            PDOException $e
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | DATABASE DUPLICATE KEY
            |--------------------------------------------------------------------------
            */

            $duplicateKey =
                isset(
                    $e->errorInfo[1]
                ) &&
                (string) $e->errorInfo[1] === '1062';


            if (
                $duplicateKey
            ) {

                $error =
                    'A matching patient record already exists. Please review the patient information.';

            } else {

                error_log(
                    'MediCare patient registration database error: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to register the patient right now. Please try again.';
            }
        }
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
        content="Register a new patient in the MediCare healthcare system."
    >


    <title>
        Register Patient | MediCare
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

            --heading-text:
                #edf6f9;

            --card-text:
                #dceaf0;

            --soft-text:
                #b7ced8;

            --muted-text:
                #6d8896;

            --label-text:
                #cadbe2;

            --caption-text:
                #718b99;

            --nav-text:
                #9db4c1;

            --nav-muted:
                #76919f;

            --nav-title:
                #648191;

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

            --input-focus-bg:
                rgba(
                    2,
                    22,
                    36,
                    0.80
                );

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

            --white-text:
                #19313f;

            --heading-text:
                #183241;

            --card-text:
                #294454;

            --soft-text:
                #56707e;

            --muted-text:
                #6d8290;

            --label-text:
                #536c79;

            --caption-text:
                #718792;

            --nav-text:
                #536c79;

            --nav-muted:
                #708795;

            --nav-title:
                #78909d;

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

            --input-focus-bg:
                #ffffff;

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
                var(--shadow-color);

            transition:
                transform .25s ease,
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        .brand {

            display:
                flex;

            align-items:
                center;

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


        .sidebar-spacer {

            flex:
                1;
        }


        /* =========================================================
           USER BOX
        ========================================================== */

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
                    1120px
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

            margin-bottom:
                18px;

            padding:
                30px;

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
                320px;

            height:
                320px;

            top:
                -145px;

            right:
                -120px;

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
                720px;

            margin-top:
                10px;

            color:
                var(--soft-text);

            font-size:
                10px;

            line-height:
                1.75;
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
                74px;

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
                var(--heading-text);

            font-size:
                16px;
        }


        .panel-header p {

            margin-top:
                4px;

            color:
                var(--caption-text);

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

        .patient-form {

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


        .field {

            min-width:
                0;
        }


        .field.full {

            grid-column:
                1 / -1;
        }


        .field label {

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
        }


        .required {

            color:
                var(--red);
        }


        .optional {

            color:
                var(--muted);

            font-weight:
                500;
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
                125px;

            padding:
                12px 13px;

            resize:
                vertical;

            line-height:
                1.65;
        }


        .field-help {

            margin-top:
                6px;

            color:
                var(--muted-text);

            font-size:
                8px;

            line-height:
                1.6;
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
        }


        .security-note i {

            color:
                var(--primary);
        }


        .security-note strong {

            color:
                var(--heading-text);
        }


        /* =========================================================
           FORM ACTIONS
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


        .button-group {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;
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
                7px;

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
                background .2s ease,
                box-shadow .2s ease;
        }


        .cancel-button {

            border:
                1px solid
                var(--border);

            color:
                var(--muted);

            background:
                var(--surface-soft);
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

            transform:
                translateY(-1px);

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
           INFORMATION CARDS
        ========================================================== */

        .info-grid {

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
                10px;

            padding:
                17px;

            border-top:
                1px solid
                var(--border);
        }


        .info-card {

            padding:
                14px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            background:
                var(--surface-soft);

            transition:
                background .25s ease,
                border-color .25s ease;
        }


        .info-icon {

            width:
                34px;

            height:
                34px;

            display:
                grid;

            place-items:
                center;

            margin-bottom:
                9px;

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


        .info-card strong {

            display:
                block;

            color:
                var(--card-text);

            font-size:
                9px;
        }


        .info-card span {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--caption-text);

            font-size:
                8px;

            line-height:
                1.55;
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
        .hero,
        .panel,
        .user-box,
        .top-icon,
        .form-control,
        .info-card {

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


            .patient-form {

                grid-template-columns:
                    1fr;
            }


            .field.full {

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


            .security-note {

                max-width:
                    none;
            }


            .button-group {

                width:
                    100%;

                flex-direction:
                    column;
            }


            .button {

                width:
                    100%;
            }


            .info-grid {

                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 460px) {

            .top-icon {

                display:
                    none;
            }


            /*
            |--------------------------------------------------------------------------
            | Keep the theme toggle visible on mobile.
            |--------------------------------------------------------------------------
            */

            .theme-toggle {

                display:
                    grid;
            }


            .panel-header {

                padding:
                    0 16px;
            }


            .form-body {

                padding:
                    15px;
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


    <div class="nav-title">
        Main Menu
    </div>


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


        <a href="patients.php">

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


        <a
            href="add_patient.php"
            class="active"
        >

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
            $currentRole === 'Admin'
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


    <!-- USER -->

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
                    fa-shield-heart
                "
            ></i>

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


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="top-title">

            <small>
                MediCare Portal
            </small>

            <h2>
                Register Patient
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
                href="patients.php"
                class="top-icon"
                title="Patients"
            >

                <i
                    class="
                        fa-solid
                        fa-users
                    "
                ></i>

            </a>


        </div>


    </header>


    <!-- CONTENT -->

    <div class="content">


        <!-- HERO -->

        <section class="hero">


            <div class="hero-content">


                <div class="eyebrow">
                    Patient Management
                </div>


                <h1>
                    Register New Patient
                </h1>


                <p>

                    Create a new patient healthcare record.
                    Required information is validated before
                    the record is saved to MediCare.

                </p>


            </div>


        </section>


        <!-- SUCCESS -->

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

                <?= e(
                    $success
                ) ?>

            </div>

        <?php endif; ?>


        <!-- ERROR -->

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

                <?= e(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <!-- FORM PANEL -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Patient Registration
                    </span>

                    <h2>
                        Patient Information
                    </h2>

                    <p>
                        Fields marked with * are required.
                    </p>

                </div>


            </header>


            <div class="form-body">


                <form
                    method="POST"
                    action="add_patient.php"
                    class="patient-form"
                    autocomplete="off"
                >


                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?= e(
                            $csrfToken
                        ) ?>"
                    >


                    <!-- FIRST NAME -->

                    <div class="field">


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
                                $firstName
                            ) ?>"
                            maxlength="100"
                            autocomplete="given-name"
                            placeholder="Enter first name"
                            required
                        >


                    </div>


                    <!-- LAST NAME -->

                    <div class="field">


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
                                $lastName
                            ) ?>"
                            maxlength="100"
                            autocomplete="family-name"
                            placeholder="Enter last name"
                            required
                        >


                    </div>


                    <!-- DATE OF BIRTH -->

                    <div class="field">


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
                                (
                                    new DateTimeImmutable(
                                        'today'
                                    )
                                )->format(
                                    'Y-m-d'
                                )
                            ) ?>"
                            required
                        >


                    </div>


                    <!-- GENDER -->

                    <div class="field">


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

                    <div class="field">


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
                            maxlength="20"
                            autocomplete="tel"
                            placeholder="Enter phone number"
                            required
                        >


                        <div class="field-help">

                            Enter the patient's current
                            contact number.

                        </div>


                    </div>


                    <!-- ADDRESS -->

                    <div class="field">


                        <label for="address">

                            Residential Address

                            <span class="optional">
                                Optional
                            </span>

                        </label>


                        <input
                            type="text"
                            id="address"
                            name="address"
                            class="form-control"
                            value="<?= e(
                                $address
                            ) ?>"
                            maxlength="1000"
                            autocomplete="street-address"
                            placeholder="Enter residential address"
                        >


                    </div>


                    <!-- MEDICAL HISTORY -->

                    <div class="field full">


                        <label for="medical_history">

                            Medical History

                            <span class="optional">
                                Optional
                            </span>

                        </label>


                        <textarea
                            id="medical_history"
                            name="medical_history"
                            class="form-control"
                            maxlength="5000"
                            rows="7"
                            placeholder="Enter relevant medical history, allergies, previous conditions or other clinically relevant information..."
                        ><?= e(
                            $medicalHistory
                        ) ?></textarea>


                        <div class="field-help">

                            Only enter information relevant to
                            the patient's healthcare record.
                            Do not enter passwords or security keys.

                        </div>


                    </div>


                    <!-- ACTIONS -->

                    <div class="form-actions">


                        <div class="security-note">


                            <i
                                class="
                                    fa-solid
                                    fa-shield-heart
                                "
                            ></i>


                            <span>

                                This record will be created as an
                                <strong>active patient record</strong>.
                                It will not automatically create a
                                patient login account.

                            </span>


                        </div>


                        <div class="button-group">


                            <a
                                href="patients.php"
                                class="
                                    button
                                    cancel-button
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-left
                                    "
                                ></i>

                                Cancel

                            </a>


                            <button
                                type="submit"
                                class="
                                    button
                                    save-button
                                "
                                id="savePatientButton"
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-user-plus
                                    "
                                ></i>

                                Register Patient

                            </button>


                        </div>


                    </div>


                </form>


            </div>


            <!-- INFORMATION -->

            <div class="info-grid">


                <div class="info-card">


                    <div class="info-icon">

                        <i
                            class="
                                fa-solid
                                fa-shield-heart
                            "
                        ></i>

                    </div>


                    <strong>
                        Secure Registration
                    </strong>


                    <span>

                        Registration is protected by
                        authenticated access and CSRF
                        validation.

                    </span>


                </div>


                <div class="info-card">


                    <div class="info-icon">

                        <i
                            class="
                                fa-solid
                                fa-user-check
                            "
                        ></i>

                    </div>


                    <strong>
                        Active Record
                    </strong>


                    <span>

                        Newly registered patient records
                        are active by default.

                    </span>


                </div>


                <div class="info-card">


                    <div class="info-icon">

                        <i
                            class="
                                fa-solid
                                fa-link
                            "
                        ></i>

                    </div>


                    <strong>
                        Login Account
                    </strong>


                    <span>

                        Registration creates the healthcare
                        record only; login accounts remain
                        separately controlled.

                    </span>


                </div>


            </div>


        </section>


    </div>


</main>


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
        | PREVENT DOUBLE SUBMISSION
        |--------------------------------------------------------------------------
        */

        const form =
            document.querySelector(
                '.patient-form'
            );


        const saveButton =
            document.getElementById(
                'savePatientButton'
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


                    saveButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Registering...';

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PHONE INPUT CLEANUP
        |--------------------------------------------------------------------------
        */

        const phoneInput =
            document.getElementById(
                'phone'
            );


        if (
            phoneInput
        ) {

            phoneInput.addEventListener(
                'input',
                function () {

                    this.value =
                        this.value
                            .replace(
                                /[^\d+\-\s()]/g,
                                ''
                            )
                            .slice(
                                0,
                                20
                            );

                }
            );
        }

    }
);

</script>


</body>

</html>