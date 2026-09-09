<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only logged-in Patients can use this page.
|--------------------------------------------------------------------------
*/

require_role('Patient');


$userId =
    current_user_id();

$error = '';

$success = '';


/*
|--------------------------------------------------------------------------
| DEFAULT FORM VALUES
|--------------------------------------------------------------------------
*/

$first_name = '';

$last_name = '';

$dob = '';

$gender = '';

$phone = '';

$address = '';

$profileExists = false;

$appointments = [];

$medicalRecords = [];


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
            role
         FROM users
         WHERE id = ?
         LIMIT 1"
    );


    $userStmt->execute([
        $userId
    ]);


    $user = $userStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$user) {

        http_response_code(404);

        exit(
            'User account not found.'
        );
    }


} catch (PDOException $e) {

    error_log(
        'MediCare patient profile user lookup failed: ' .
        $e->getMessage()
    );


    http_response_code(503);

    exit(
        'Unable to load your profile right now.'
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT ROLE
|--------------------------------------------------------------------------
*/

$accountRole =
    current_role();


/*
|--------------------------------------------------------------------------
| LOAD PATIENT PROFILE
|--------------------------------------------------------------------------
*/

try {

    $patientStmt = $pdo->prepare(
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
            created_at
         FROM patients
         WHERE user_id = ?
         LIMIT 1"
    );


    $patientStmt->execute([
        $userId
    ]);


    $patient = $patientStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if ($patient) {

        /*
        |--------------------------------------------------------------------------
        | VERIFY LINKED USER
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $patient['user_id']
            ) ||
            (int) $patient['user_id'] !== $userId
        ) {

            error_log(
                'MediCare patient profile ownership mismatch for user ID ' .
                $userId
            );


            http_response_code(403);

            exit(
                'You are not authorized to access this patient profile.'
            );
        }


        $profileExists = true;


        $first_name =
            (string) (
                $patient['first_name']
                ?? ''
            );


        $last_name =
            (string) (
                $patient['last_name']
                ?? ''
            );


        $dob =
            (string) (
                $patient['dob']
                ?? ''
            );


        $gender =
            (string) (
                $patient['gender']
                ?? ''
            );


        $phone =
            (string) (
                $patient['phone']
                ?? ''
            );


        $address =
            (string) (
                $patient['address']
                ?? ''
            );
    }


} catch (PDOException $e) {

    error_log(
        'MediCare patient profile lookup failed: ' .
        $e->getMessage()
    );


    $error =
        'Unable to load your patient profile right now.';
}


/*
|--------------------------------------------------------------------------
| HANDLE PROFILE SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    verify_csrf(
        $_POST['_csrf'] ?? null
    );


    /*
    |--------------------------------------------------------------------------
    | FORM VALUES
    |--------------------------------------------------------------------------
    */

    $first_name =
        trim(
            post_string(
                'first_name',
                100
            )
        );


    $last_name =
        trim(
            post_string(
                'last_name',
                100
            )
        );


    $dob =
        trim(
            post_string(
                'dob',
                10
            )
        );


    $gender =
        trim(
            post_string(
                'gender',
                20
            )
        );


    $phone =
        trim(
            post_string(
                'phone',
                20
            )
        );


    $address =
        trim(
            post_string(
                'address',
                1000
            )
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
        mb_strlen(
            $first_name
        ) < 2
    ) {

        $error =
            'Please enter a valid first name.';


    } elseif (
        mb_strlen(
            $last_name
        ) < 2
    ) {

        $error =
            'Please enter a valid last name.';


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
            'Please enter valid names.';


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

        $dateObject =
            safe_date(
                $dob
            );


        if (
            $dateObject === null
        ) {

            $error =
                'Please enter a valid date of birth.';


        } elseif (
            $dateObject >
            new DateTimeImmutable(
                'today'
            )
        ) {

            $error =
                'Date of birth cannot be in the future.';


        } else {

            /*
            |--------------------------------------------------------------------------
            | SAVE PROFILE
            |--------------------------------------------------------------------------
            */

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | CHECK PATIENT RECORD AGAIN
                |--------------------------------------------------------------------------
                */

                $checkStmt =
                    $pdo->prepare(
                        "SELECT
                            id,
                            user_id
                         FROM patients
                         WHERE user_id = ?
                         LIMIT 1
                         FOR UPDATE"
                    );


                $checkStmt->execute([
                    $userId
                ]);


                $existingPatient =
                    $checkStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    $existingPatient
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | VERIFY OWNERSHIP BEFORE UPDATE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !isset(
                            $existingPatient['user_id']
                        ) ||
                        (int) $existingPatient['user_id']
                        !==
                        $userId
                    ) {

                        throw new RuntimeException(
                            'Patient profile ownership verification failed.'
                        );
                    }


                    $existingPatientId =
                        (int) (
                            $existingPatient['id']
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE EXISTING PROFILE
                    |--------------------------------------------------------------------------
                    */

                    $updateStmt =
                        $pdo->prepare(
                            "UPDATE patients
                             SET
                                first_name = ?,
                                last_name = ?,
                                dob = ?,
                                gender = ?,
                                phone = ?,
                                address = ?
                             WHERE id = ?
                               AND user_id = ?"
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
                        $existingPatientId,
                        $userId
                    ]);


                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE NEW PATIENT PROFILE
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
                                medical_history
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                NULL
                            )"
                        );


                    $insertStmt->execute([
                        $userId,
                        $first_name,
                        $last_name,
                        $dob,
                        $gender,
                        $phone,
                        $address !== ''
                            ? $address
                            : null
                    ]);


                    $profileExists =
                        true;
                }


                /*
                |--------------------------------------------------------------------------
                | SYNCHRONIZE USER FULLNAME
                |--------------------------------------------------------------------------
                */

                $fullName =
                    trim(
                        $first_name .
                        ' ' .
                        $last_name
                    );


                $userUpdateStmt =
                    $pdo->prepare(
                        "UPDATE users
                         SET fullname = ?
                         WHERE id = ?"
                    );


                $userUpdateStmt->execute([
                    $fullName,
                    $userId
                ]);


                /*
                |--------------------------------------------------------------------------
                | UPDATE SESSION
                |--------------------------------------------------------------------------
                */

                $_SESSION['fullname'] =
                    $fullName;


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $pdo->commit();


                $success =
                    'Your patient profile has been saved successfully.';


            } catch (PDOException $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                error_log(
                    'MediCare patient profile save failed: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to save your profile right now. Please try again.';


            } catch (RuntimeException $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                error_log(
                    'MediCare patient profile runtime error: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to save your profile right now. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD APPOINTMENT HISTORY
|--------------------------------------------------------------------------
| Always scoped to authenticated user.
|--------------------------------------------------------------------------
*/

try {

    $appointmentStmt =
        $pdo->prepare(
            "SELECT
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.status,
                a.created_at,
                d.fullname AS doctor_name
             FROM appointments a
             LEFT JOIN users d
                ON d.id = a.doctor_id
             WHERE a.user_id = ?
             ORDER BY
                a.appointment_date DESC,
                a.appointment_time DESC,
                a.id DESC"
        );


    $appointmentStmt->execute([
        $userId
    ]);


    $appointments =
        $appointmentStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    error_log(
        'MediCare patient appointment history error: ' .
        $e->getMessage()
    );


    $appointments = [];
}


/*
|--------------------------------------------------------------------------
| LOAD CLINICAL RECORDS
|--------------------------------------------------------------------------
*/

try {

    $recordStmt =
        $pdo->prepare(
            "SELECT
                mr.id,
                mr.appointment_id,
                mr.doctor_id,
                mr.notes,
                mr.prescription,
                mr.created_at,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                d.fullname AS doctor_name
             FROM medical_records mr
             INNER JOIN appointments a
                ON a.id = mr.appointment_id
             LEFT JOIN users d
                ON d.id = mr.doctor_id
             WHERE a.user_id = ?
             ORDER BY
                mr.created_at DESC,
                mr.id DESC"
        );


    $recordStmt->execute([
        $userId
    ]);


    $medicalRecords =
        $recordStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    error_log(
        'MediCare patient clinical records error: ' .
        $e->getMessage()
    );


    $medicalRecords = [];
}


/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$dobObject =
    safe_date(
        $dob
    );


$accountName =
    (string) (
        $user['fullname'] ?? ''
    );


$accountEmail =
    (string) (
        $user['email'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| PATIENT FULL NAME
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
        'Complete Your Profile';
}


/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$initials = '';


$nameParts =
    preg_split(
        '/\s+/u',
        trim(
            $accountName !== ''
                ? $accountName
                : $patientFullName
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
        'P';
}


/*
|--------------------------------------------------------------------------
| PROFILE COMPLETION
|--------------------------------------------------------------------------
*/

$profileCompletionFields = [
    $first_name,
    $last_name,
    $dob,
    $gender,
    $phone,
    $address
];


$completedFields = 0;


foreach (
    $profileCompletionFields
    as $field
) {

    if (
        trim(
            (string) $field
        ) !== ''
    ) {

        $completedFields++;
    }
}


$profileCompletion =
    (int) round(
        (
            $completedFields /
            count(
                $profileCompletionFields
            )
        ) * 100
    );


/*
|--------------------------------------------------------------------------
| STATUS HELPER
|--------------------------------------------------------------------------
*/

function patient_profile_status_class(
    string $status
): string {

    return match (
        strtolower(
            trim(
                $status
            )
        )
    ) {

        'pending' =>
            'pending',

        'confirmed' =>
            'confirmed',

        'completed' =>
            'completed',

        'cancelled' =>
            'cancelled',

        default =>
            'default',
    };
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
        content="MediCare patient portal and health profile."
    >


    <title>
        My Patient Profile | MediCare
    </title>


    <!-- =====================================================
         RESTORE SAVED THEME
         DARK MODE IS DEFAULT
    ====================================================== -->

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

                }

            } catch (error) {

                /*
                |--------------------------------------------------------------------------
                | Keep dark mode as default.
                |--------------------------------------------------------------------------
                */

            }

        })();

    </script>


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

            --sidebar-width:
                255px;


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
                #abc6d0;

            --muted-text:
                #718b99;

            --label-text:
                #c7d8df;

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

            --surface:
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

            --panel-dark:
                #eef4f7;

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

            --surface:
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
                    circle at 12% 10%,
                    rgba(
                        16,
                        199,
                        176,
                        0.08
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 88% 80%,
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
        input,
        select,
        textarea {

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
                border-color .25s ease;
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
                var(--muted-dark);

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
                var(--muted);

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
                var(--muted-dark);

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
                9px;
        }


        .role-label {

            display:
                inline-block;

            margin-top:
                10px;

            padding:
                5px 9px;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.15
                );

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
                    0.045
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
           THEME TOGGLE
        ========================================================== */

        .theme-toggle {

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


        /* =========================================================
           PROFILE HERO
        ========================================================== */

        .profile-hero {

            position:
                relative;

            overflow:
                hidden;

            min-height:
                190px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

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
                20px;

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


        .profile-hero::after {

            content:
                "";

            position:
                absolute;

            width:
                340px;

            height:
                340px;

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
        }


        .profile-main {

            position:
                relative;

            z-index:
                2;

            display:
                flex;

            align-items:
                center;

            gap:
                18px;
        }


        .profile-avatar {

            width:
                78px;

            height:
                78px;

            flex-shrink:
                0;

            display:
                grid;

            place-items:
                center;

            border-radius:
                21px;

            color:
                var(--primary);

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

            font-size:
                25px;

            font-weight:
                800;
        }


        .profile-copy {

            min-width:
                0;
        }


        .eyebrow {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                8px;

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


        .profile-copy h1 {

            max-width:
                650px;

            overflow:
                hidden;

            text-overflow:
                ellipsis;

            white-space:
                nowrap;

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


        .profile-copy p {

            margin-top:
                8px;

            color:
                var(--soft-text);

            font-size:
                10px;
        }


        .profile-badge {

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
                8px;

            padding:
                10px 13px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.12
                );

            border-radius:
                999px;

            background:
                var(--surface);

            color:
                var(--white-text);

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .profile-badge i {

            color:
                var(--primary);
        }


        /* =========================================================
           COMPLETION
        ========================================================== */

        .completion-card {

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1fr
                )
                180px;

            gap:
                20px;

            align-items:
                center;

            margin-bottom:
                18px;

            padding:
                20px;

            border:
                1px solid
                var(--border);

            border-radius:
                16px;

            background:

                linear-gradient(
                    145deg,
                    var(--panel-start),
                    var(--panel-end)
                );
        }


        .completion-copy span {

            display:
                block;

            color:
                var(--primary);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                1px;

            text-transform:
                uppercase;
        }


        .completion-copy strong {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--heading);

            font-size:
                15px;
        }


        .completion-copy p {

            margin-top:
                5px;

            color:
                var(--muted-text);

            font-size:
                9px;

            line-height:
                1.6;
        }


        .completion-bar {

            height:
                9px;

            margin-top:
                11px;

            overflow:
                hidden;

            border-radius:
                999px;

            background:
                var(--surface);
        }


        .completion-progress {

            height:
                100%;

            border-radius:
                inherit;

            background:
                linear-gradient(
                    90deg,
                    var(--primary),
                    #10b4d3
                );
        }


        .completion-score {

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            min-height:
                100px;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.12
                );

            border-radius:
                15px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.035
                );
        }


        .completion-score strong {

            color:
                var(--white-text);

            font-size:
                30px;
        }


        .completion-score span {

            margin-top:
                3px;

            color:
                var(--muted-text);

            font-size:
                8px;

            text-transform:
                uppercase;

            letter-spacing:
                .8px;
        }


        /* =========================================================
           PANEL
        ========================================================== */

        .panel {

            overflow:
                hidden;

            margin-bottom:
                18px;

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
                73px;

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
                8px;
        }


        .panel-badge {

            min-width:
                30px;

            height:
                30px;

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

            font-size:
                9px;

            font-weight:
                800;
        }


        .panel-body {

            padding:
                20px;
        }


        /* =========================================================
           ACCOUNT GRID
        ========================================================== */

        .account-grid {

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
                10px;
        }


        .account-item {

            min-height:
                94px;

            padding:
                15px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            background:
                var(--surface);
        }


        .account-label {

            display:
                block;

            margin-bottom:
                8px;

            color:
                var(--muted-text);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .7px;

            text-transform:
                uppercase;
        }


        .account-value {

            display:
                block;

            color:
                var(--input-text);

            font-size:
                11px;

            font-weight:
                700;

            line-height:
                1.55;

            word-break:
                break-word;
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


        .form-control {

            width:
                100%;

            min-height:
                46px;

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
                box-shadow .2s ease,
                background .2s ease;
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


        textarea.form-control {

            min-height:
                105px;

            padding:
                12px;

            line-height:
                1.6;

            resize:
                vertical;
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


        .form-actions {

            display:
                flex;

            align-items:
                center;

            justify-content:
                flex-end;

            gap:
                9px;

            margin-top:
                22px;

            padding-top:
                19px;

            border-top:
                1px solid
                var(--border);
        }


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
                0 16px;

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

            font-size:
                9px;

            font-weight:
                800;

            cursor:
                pointer;

            transition:
                transform .2s ease,
                opacity .2s ease;
        }


        .save-button:hover {

            transform:
                translateY(-1px);
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
           APPOINTMENT TABLE
        ========================================================== */

        .table-wrapper {

            width:
                100%;

            overflow-x:
                auto;
        }


        .appointment-table {

            width:
                100%;

            min-width:
                780px;

            border-collapse:
                collapse;
        }


        .appointment-table th {

            padding:
                13px 14px;

            text-align:
                left;

            white-space:
                nowrap;

            color:
                var(--muted-text);

            background:
                var(--surface);

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


        .appointment-table td {

            padding:
                14px;

            color:
                var(--muted);

            border-bottom:
                1px solid
                var(--border);

            font-size:
                9px;

            vertical-align:
                middle;
        }


        .appointment-table tbody tr:hover {

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.025
                );
        }


        .appointment-table tbody tr:last-child td {

            border-bottom:
                none;
        }


        .date-main {

            display:
                block;

            color:
                var(--input-text);

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
                var(--muted-text);

            font-size:
                7px;
        }


        .doctor-name {

            color:
                var(--input-text);

            font-size:
                9px;

            font-weight:
                700;
        }


        .reason {

            max-width:
                250px;

            color:
                var(--muted);

            line-height:
                1.5;
        }


        /* =========================================================
           STATUS
        ========================================================== */

        .status {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            min-width:
                75px;

            padding:
                6px 8px;

            border-radius:
                999px;

            font-size:
                7px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .status.pending {

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


        .status.confirmed {

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


        .status.completed {

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


        .status.cancelled {

            color:
                #ff9298;

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.08
                );
        }


        .status.default {

            color:
                #9fb6c2;

            background:
                var(--surface);
        }


        /* =========================================================
           EMPTY STATE
        ========================================================== */

        .empty-state {

            min-height:
                190px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                30px 20px;

            text-align:
                center;
        }


        .empty-icon {

            width:
                54px;

            height:
                54px;

            display:
                grid;

            place-items:
                center;

            margin:
                0 auto 13px;

            border-radius:
                15px;

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
                21px;
        }


        .empty-state h3 {

            color:
                var(--heading);

            font-size:
                14px;
        }


        .empty-state p {

            max-width:
                420px;

            margin:
                7px auto 0;

            color:
                var(--muted-text);

            font-size:
                9px;

            line-height:
                1.7;
        }


        .empty-action {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            min-height:
                38px;

            margin-top:
                14px;

            padding:
                0 14px;

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
        }


        /* =========================================================
           RECORDS
        ========================================================== */

        .record-list {

            padding:
                20px;
        }


        .record {

            padding:
                18px;

            margin-bottom:
                11px;

            border:
                1px solid
                var(--border);

            border-radius:
                13px;

            background:
                var(--surface);
        }


        .record:last-child {

            margin-bottom:
                0;
        }


        .record-top {

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                15px;

            margin-bottom:
                13px;
        }


        .record-doctor {

            color:
                var(--input-text);

            font-size:
                12px;

            font-weight:
                800;
        }


        .record-date {

            margin-top:
                4px;

            color:
                var(--muted-text);

            font-size:
                8px;
        }


        .record-id {

            flex-shrink:
                0;

            padding:
                5px 8px;

            border-radius:
                999px;

            color:
                var(--primary);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.08
                );

            font-size:
                7px;

            font-weight:
                800;
        }


        .record-section {

            margin-top:
                13px;
        }


        .record-label {

            display:
                block;

            margin-bottom:
                5px;

            color:
                var(--muted-text);

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                .5px;
        }


        .record-value {

            color:
                var(--muted);

            font-size:
                9px;

            line-height:
                1.7;

            white-space:
                pre-wrap;
        }


        /* =========================================================
           PRIVACY NOTE
        ========================================================== */

        .privacy-note {

            margin:
                17px;

            padding:
                12px 14px;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.10
                );

            border-radius:
                10px;

            color:
                var(--muted);

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.03
                );

            font-size:
                8px;

            line-height:
                1.65;
        }


        .privacy-note i {

            color:
                var(--primary);
        }


        .privacy-note strong {

            color:
                var(--input-text);
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

        @media (max-width: 1100px) {

            :root {

                --sidebar-width:
                    235px;
            }


            .content {

                padding:
                    25px;
            }


            .account-grid {

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


            .profile-hero {

                min-height:
                    auto;
            }

        }


        @media (max-width: 760px) {

            .profile-hero {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                padding:
                    25px 21px;
            }


            .profile-main {

                align-items:
                    flex-start;

                width:
                    100%;

                flex-direction:
                    column;
            }


            .profile-copy h1 {

                max-width:
                    100%;

                white-space:
                    normal;
            }


            .profile-badge {

                align-self:
                    flex-start;
            }


            .completion-card {

                grid-template-columns:
                    1fr;
            }


            .form-grid {

                grid-template-columns:
                    1fr;
            }


            .full-width {

                grid-column:
                    auto;
            }


            .form-actions {

                justify-content:
                    stretch;
            }


            .save-button {

                width:
                    100%;
            }

        }


        @media (max-width: 650px) {

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


            .account-grid {

                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 460px) {

            .top-icon {

                display:
                    none;
            }


            .panel-body {

                padding:
                    15px;
            }


            .record-list {

                padding:
                    15px;
            }


            .profile-avatar {

                width:
                    67px;

                height:
                    67px;

                border-radius:
                    18px;

                font-size:
                    22px;
            }


            .profile-copy h1 {

                font-size:
                    28px;
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
                My Appointments
            </span>

        </a>


        <a href="book_appointment.php">

            <i class="fa-solid fa-calendar-plus"></i>

            <span>
                Book Appointment
            </span>

        </a>


        <a
            href="patient_profile.php"
            class="active"
        >

            <i class="fa-solid fa-id-card"></i>

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
                        $accountName !== ''
                            ? $accountName
                            : $patientFullName
                    ) ?>

                </strong>


                <span>

                    <?= e(
                        $accountEmail
                    ) ?>

                </span>

            </div>


        </div>


        <span class="role-label">

            <?= e(
                $accountRole
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


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">


        <div class="page-title">

            <small>

                MediCare Patient Portal

            </small>


            <h2>

                My Profile

            </h2>

        </div>


        <div class="top-actions">


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
                    class="
                        fa-solid
                        fa-sun
                    "
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

                <i
                    class="
                        fa-regular
                        fa-calendar-check
                    "
                ></i>

            </a>


        </div>


    </header>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content">


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


        <!-- =====================================================
             PROFILE HERO
        ====================================================== -->

        <section class="profile-hero">


            <div class="profile-main">


                <div class="profile-avatar">

                    <?= e(
                        $initials
                    ) ?>

                </div>


                <div class="profile-copy">


                    <div class="eyebrow">

                        Patient Portal

                    </div>


                    <h1>

                        <?= e(
                            $patientFullName
                        ) ?>

                    </h1>


                    <p>

                        Manage your personal information and
                        access your healthcare activity.

                    </p>


                </div>


            </div>


            <div class="profile-badge">

                <i
                    class="
                        fa-solid
                        fa-shield-heart
                    "
                ></i>

                Patient Account

            </div>


        </section>


        <!-- =====================================================
             PROFILE COMPLETION
        ====================================================== -->

        <section class="completion-card">


            <div class="completion-copy">

                <span>
                    Profile Completion
                </span>


                <?php if (
                    $profileCompletion < 100
                ): ?>

                    <strong>
                        Complete your patient profile
                    </strong>


                    <p>

                        Keep your personal and contact
                        information complete so MediCare
                        can maintain an accurate patient record.

                    </p>

                <?php else: ?>

                    <strong>
                        Your profile is complete
                    </strong>


                    <p>

                        Your essential patient information
                        has been completed successfully.

                    </p>

                <?php endif; ?>


                <div class="completion-bar">


                    <div
                        class="completion-progress"
                        style="
                            width:
                            <?= $profileCompletion ?>%;
                        "
                    ></div>


                </div>


            </div>


            <div class="completion-score">

                <strong>
                    <?= $profileCompletion ?>%
                </strong>


                <span>
                    Complete
                </span>

            </div>


        </section>


        <!-- =====================================================
             ACCOUNT INFORMATION
        ====================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Account
                    </span>


                    <h2>
                        Account Information
                    </h2>


                    <p>
                        Your MediCare login account details.
                    </p>

                </div>


                <span class="panel-badge">

                    <i
                        class="
                            fa-solid
                            fa-user-shield
                        "
                    ></i>

                </span>


            </header>


            <div class="panel-body">


                <div class="account-grid">


                    <div class="account-item">

                        <span class="account-label">
                            Account Name
                        </span>


                        <strong class="account-value">

                            <?= e(
                                $accountName !== ''
                                    ? $accountName
                                    : 'Not available'
                            ) ?>

                        </strong>

                    </div>


                    <div class="account-item">

                        <span class="account-label">
                            Email Address
                        </span>


                        <strong class="account-value">

                            <?= e(
                                $accountEmail !== ''
                                    ? $accountEmail
                                    : 'Not available'
                            ) ?>

                        </strong>

                    </div>


                    <div class="account-item">

                        <span class="account-label">
                            Role
                        </span>


                        <strong class="account-value">

                            <?= e(
                                $accountRole
                            ) ?>

                        </strong>

                    </div>


                    <div class="account-item">

                        <span class="account-label">
                            Account ID
                        </span>


                        <strong class="account-value">

                            #<?= (int) $userId ?>

                        </strong>

                    </div>


                </div>


            </div>


        </section>


        <!-- =====================================================
             PATIENT INFORMATION
        ====================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Personal Information
                    </span>


                    <h2>
                        Patient Details
                    </h2>


                    <p>
                        Keep your personal and contact information
                        accurate and current.
                    </p>

                </div>


                <span class="panel-badge">

                    <i
                        class="
                            fa-solid
                            fa-id-card
                        "
                    ></i>

                </span>


            </header>


            <div class="panel-body">


                <form
                    method="POST"
                    action="patient_profile.php"
                    autocomplete="off"
                    id="patientProfileForm"
                >


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

                            <label
                                for="first_name"
                            >

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
                                placeholder="Enter your first name"
                                required
                            >

                        </div>


                        <!-- LAST NAME -->

                        <div class="form-group">

                            <label
                                for="last_name"
                            >

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
                                placeholder="Enter your last name"
                                required
                            >

                        </div>


                        <!-- DOB -->

                        <div class="form-group">

                            <label
                                for="dob"
                            >

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

                        <div class="form-group">

                            <label
                                for="gender"
                            >

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

                            <label
                                for="phone"
                            >

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
                                placeholder="Enter your phone number"
                                required
                            >


                            <div class="field-help">

                                Use the phone number where
                                MediCare can contact you.

                            </div>

                        </div>


                        <!-- ADDRESS -->

                        <div
                            class="
                                form-group
                                full-width
                            "
                        >

                            <label
                                for="address"
                            >

                                Residential Address

                            </label>


                            <textarea
                                id="address"
                                name="address"
                                class="form-control"
                                maxlength="1000"
                                autocomplete="street-address"
                                placeholder="Enter your residential address"
                            ><?= e(
                                $address
                            ) ?></textarea>


                            <div class="field-help">

                                Please keep your contact
                                address current.

                            </div>

                        </div>


                    </div>


                    <div class="form-actions">


                        <button
                            type="submit"
                            class="save-button"
                            id="saveProfileButton"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-floppy-disk
                                "
                            ></i>


                            <?= $profileExists
                                ? 'Save Profile Changes'
                                : 'Create Patient Profile' ?>

                        </button>


                    </div>


                </form>


                <div class="privacy-note">

                    <i
                        class="
                            fa-solid
                            fa-lock
                        "
                    ></i>

                    &nbsp;

                    Your personal healthcare information is
                    protected and should only be accessed by
                    you and authorized MediCare personnel.

                </div>


            </div>


        </section>


        <!-- =====================================================
             APPOINTMENTS
        ====================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Appointment Activity
                    </span>


                    <h2>
                        My Appointments
                    </h2>


                    <p>
                        Your appointment history and current requests.
                    </p>

                </div>


                <span class="panel-badge">

                    <?= count(
                        $appointments
                    ) ?>

                </span>


            </header>


            <?php if (
                !empty(
                    $appointments
                )
            ): ?>


                <div class="table-wrapper">


                    <table class="appointment-table">


                        <thead>

                            <tr>

                                <th>
                                    Date &amp; Time
                                </th>


                                <th>
                                    Doctor
                                </th>


                                <th>
                                    Reason
                                </th>


                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $appointments
                            as $appointment
                        ): ?>


                            <?php

                            $appointmentDate =
                                safe_date(
                                    (string) (
                                        $appointment[
                                            'appointment_date'
                                        ] ?? ''
                                    )
                                );


                            $appointmentTime =
                                safe_time(
                                    (string) (
                                        $appointment[
                                            'appointment_time'
                                        ] ?? ''
                                    )
                                );


                            $appointmentStatus =
                                (string) (
                                    $appointment[
                                        'status'
                                    ] ?? 'Unknown'
                                );


                            $statusClass =
                                patient_profile_status_class(
                                    $appointmentStatus
                                );


                            $doctorName =
                                trim(
                                    (string) (
                                        $appointment[
                                            'doctor_name'
                                        ] ?? ''
                                    )
                                );


                            $reason =
                                trim(
                                    (string) (
                                        $appointment[
                                            'reason'
                                        ] ?? ''
                                    )
                                );


                            if (
                                $reason === ''
                            ) {

                                $reason =
                                    'Routine Checkup';
                            }

                            ?>


                            <tr>


                                <!-- DATE -->

                                <td>

                                    <span class="date-main">

                                        <?php if (
                                            $appointmentDate !== null
                                        ): ?>

                                            <?= e(
                                                $appointmentDate
                                                    ->format(
                                                        'M d, Y'
                                                    )
                                            ) ?>

                                        <?php else: ?>

                                            Date unavailable

                                        <?php endif; ?>

                                    </span>


                                    <span class="date-sub">

                                        <?php if (
                                            $appointmentTime !== null
                                        ): ?>

                                            <?= e(
                                                $appointmentTime
                                                    ->format(
                                                        'h:i A'
                                                    )
                                            ) ?>

                                        <?php else: ?>

                                            Time unavailable

                                        <?php endif; ?>

                                    </span>

                                </td>


                                <!-- DOCTOR -->

                                <td>

                                    <span
                                        class="doctor-name"
                                    >

                                        <?php if (
                                            $doctorName !== ''
                                        ): ?>

                                            Dr.
                                            <?= e(
                                                $doctorName
                                            ) ?>

                                        <?php else: ?>

                                            Doctor not assigned

                                        <?php endif; ?>

                                    </span>

                                </td>


                                <!-- REASON -->

                                <td>

                                    <div class="reason">

                                        <?= e(
                                            $reason
                                        ) ?>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="
                                            status
                                            <?= e(
                                                $statusClass
                                            ) ?>
                                        "
                                    >

                                        <?= e(
                                            $appointmentStatus
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="empty-state">


                    <div>


                        <div class="empty-icon">

                            <i
                                class="
                                    fa-regular
                                    fa-calendar-xmark
                                "
                            ></i>

                        </div>


                        <h3>

                            No appointments yet

                        </h3>


                        <p>

                            Your appointment history will
                            appear here after you book a visit.

                        </p>


                        <a
                            href="book_appointment.php"
                            class="empty-action"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-calendar-plus
                                "
                            ></i>

                            Book Appointment

                        </a>


                    </div>


                </div>


            <?php endif; ?>


        </section>


        <!-- =====================================================
             CLINICAL RECORDS
        ====================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Clinical Information
                    </span>


                    <h2>
                        My Clinical Records
                    </h2>


                    <p>

                        Doctor notes and prescriptions from
                        your consultations.

                    </p>

                </div>


                <span class="panel-badge">

                    <?= count(
                        $medicalRecords
                    ) ?>

                </span>


            </header>


            <?php if (
                !empty(
                    $medicalRecords
                )
            ): ?>


                <div class="record-list">


                    <?php foreach (
                        $medicalRecords
                        as $record
                    ): ?>


                        <?php

                        $recordDate =
                            safe_date(
                                (string) (
                                    $record[
                                        'appointment_date'
                                    ] ?? ''
                                )
                            );


                        $recordTime =
                            safe_time(
                                (string) (
                                    $record[
                                        'appointment_time'
                                    ] ?? ''
                                )
                            );


                        $recordDoctor =
                            trim(
                                (string) (
                                    $record[
                                        'doctor_name'
                                    ] ?? ''
                                )
                            );

                        ?>


                        <article class="record">


                            <div class="record-top">


                                <div>


                                    <div class="record-doctor">

                                        <?php if (
                                            $recordDoctor !== ''
                                        ): ?>

                                            Dr.
                                            <?= e(
                                                $recordDoctor
                                            ) ?>

                                        <?php else: ?>

                                            Doctor not available

                                        <?php endif; ?>

                                    </div>


                                    <div class="record-date">

                                        <?php if (
                                            $recordDate !== null
                                        ): ?>

                                            Consultation:

                                            <?= e(
                                                $recordDate
                                                    ->format(
                                                        'M d, Y'
                                                    )
                                            ) ?>

                                        <?php else: ?>

                                            Consultation date unavailable

                                        <?php endif; ?>


                                        <?php if (
                                            $recordTime !== null
                                        ): ?>

                                            &nbsp; • &nbsp;

                                            <?= e(
                                                $recordTime
                                                    ->format(
                                                        'h:i A'
                                                    )
                                            ) ?>

                                        <?php endif; ?>

                                    </div>


                                </div>


                                <span class="record-id">

                                    Record #

                                    <?= (int) (
                                        $record['id']
                                    ) ?>

                                </span>


                            </div>


                            <?php if (
                                !empty(
                                    $record['prescription']
                                )
                            ): ?>


                                <div class="record-section">


                                    <span class="record-label">

                                        Prescription

                                    </span>


                                    <div class="record-value">

                                        <?= e(
                                            (string) (
                                                $record[
                                                    'prescription'
                                                ]
                                            )
                                        ) ?>

                                    </div>


                                </div>


                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $record['notes']
                                )
                            ): ?>


                                <div class="record-section">


                                    <span class="record-label">

                                        Clinical Notes

                                    </span>


                                    <div class="record-value">

                                        <?= e(
                                            (string) (
                                                $record[
                                                    'notes'
                                                ]
                                            )
                                        ) ?>

                                    </div>


                                </div>


                            <?php endif; ?>


                            <div class="record-section">


                                <span class="record-label">

                                    Appointment

                                </span>


                                <div class="record-value">

                                    Appointment #

                                    <?= (int) (
                                        $record[
                                            'appointment_id'
                                        ]
                                    ) ?>


                                    <?php if (
                                        !empty(
                                            $record['reason']
                                        )
                                    ): ?>

                                        &nbsp; • &nbsp;

                                        <?= e(
                                            (string) (
                                                $record[
                                                    'reason'
                                                ]
                                            )
                                        ) ?>

                                    <?php endif; ?>

                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-state">


                    <div>


                        <div class="empty-icon">

                            <i
                                class="
                                    fa-solid
                                    fa-file-medical
                                "
                            ></i>

                        </div>


                        <h3>

                            No clinical records yet

                        </h3>


                        <p>

                            Clinical records created from your
                            consultations will appear here.

                        </p>


                    </div>


                </div>


            <?php endif; ?>


            <div class="privacy-note">

                <i
                    class="
                        fa-solid
                        fa-shield-heart
                    "
                ></i>

                &nbsp;

                Clinical records are maintained by authorized
                MediCare healthcare personnel and cannot be
                edited from the patient portal.

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


        /*
        |--------------------------------------------------------------------------
        | THEME TOGGLE
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


        let savedTheme =
            'dark';


        try {

            savedTheme =
                localStorage.getItem(
                    'medicare-theme'
                ) || 'dark';

        } catch (
            error
        ) {

            savedTheme =
                'dark';
        }


        applyTheme(
            savedTheme
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
                        | Ignore browser storage failure.
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
            document.getElementById(
                'patientProfileForm'
            );


        const saveButton =
            document.getElementById(
                'saveProfileButton'
            );


        if (
            profileForm &&
            saveButton
        ) {

            profileForm.addEventListener(
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


    }
);

</script>


</body>

</html>