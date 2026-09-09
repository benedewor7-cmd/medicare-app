<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only authenticated Patient accounts may create appointments.
|--------------------------------------------------------------------------
*/

require_role('Patient');


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId = current_user_id();

$displayName = trim(
    (string) (
        $_SESSION['fullname'] ?? 'Patient'
    )
);

if ($displayName === '') {

    $displayName = 'Patient';
}

$role = current_role();


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';

$doctorId = '';

$appointmentDate = '';

$appointmentTime = '';

$reason = '';

$doctors = [];


/*
|--------------------------------------------------------------------------
| LOAD DOCTORS
|--------------------------------------------------------------------------
|
| Only accounts whose database role is Doctor are displayed.
| The submitted doctor ID is still independently verified on POST.
|--------------------------------------------------------------------------
*/

try {

    $doctorListStmt = $pdo->query(
        "SELECT
            id,
            fullname
         FROM users
         WHERE role = 'Doctor'
         ORDER BY fullname ASC"
    );


    $doctors =
        $doctorListStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    error_log(
        'MediCare doctor list lookup failed: ' .
        $e->getMessage()
    );


    $error =
        'Unable to load doctors right now. Please try again later.';
}


/*
|--------------------------------------------------------------------------
| HANDLE APPOINTMENT BOOKING
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {


    /*
    |--------------------------------------------------------------------------
    | CSRF PROTECTION
    |--------------------------------------------------------------------------
    */

    verify_csrf(
        $_POST['_csrf'] ?? null
    );


    /*
    |--------------------------------------------------------------------------
    | READ FORM VALUES
    |--------------------------------------------------------------------------
    */

    $doctorId =
        post_string(
            'doctor_id',
            20
        );


    $appointmentDate =
        post_string(
            'appointment_date',
            10
        );


    $appointmentTime =
        post_string(
            'appointment_time',
            5
        );


    $reason =
        post_string(
            'reason',
            1000
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATE DOCTOR ID
    |--------------------------------------------------------------------------
    */

    $doctorIdInt =
        filter_var(
            $doctorId,
            FILTER_VALIDATE_INT
        );


    if (
        $doctorIdInt === false ||
        $doctorIdInt === null ||
        $doctorIdInt < 1
    ) {

        $doctorIdInt = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE DATE
    |--------------------------------------------------------------------------
    */

    $date =
        safe_date(
            $appointmentDate
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATE TIME
    |--------------------------------------------------------------------------
    */

    $time =
        safe_time(
            $appointmentTime
        );


    if (
        $doctorIdInt < 1 ||
        $date === null ||
        $time === null
    ) {

        $error =
            'Please provide a valid doctor, date and time.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | BUILD APPOINTMENT DATETIME
        |--------------------------------------------------------------------------
        */

        $appointmentDateTime =
            DateTimeImmutable::createFromFormat(
                'Y-m-d H:i',
                $date->format('Y-m-d') .
                ' ' .
                $time->format('H:i')
            );


        if (
            $appointmentDateTime === false
        ) {

            $error =
                'The selected appointment date or time is invalid.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | PREVENT PAST APPOINTMENTS
            |--------------------------------------------------------------------------
            */

            $now =
                new DateTimeImmutable('now');


            if (
                $appointmentDateTime <= $now
            ) {

                $error =
                    'You cannot book an appointment in the past.';

            } else {


                /*
                |--------------------------------------------------------------------------
                | DATABASE OPERATION
                |--------------------------------------------------------------------------
                */

                try {


                    /*
                    |--------------------------------------------------------------------------
                    | START TRANSACTION
                    |--------------------------------------------------------------------------
                    */

                    $pdo->beginTransaction();


                    /*
                    |--------------------------------------------------------------------------
                    | VERIFY SELECTED DOCTOR
                    |--------------------------------------------------------------------------
                    |
                    | Never trust the doctor ID supplied by the browser.
                    | The database is checked again inside the transaction.
                    |--------------------------------------------------------------------------
                    */

                    $doctorCheckStmt =
                        $pdo->prepare(
                            "SELECT
                                id,
                                fullname
                             FROM users
                             WHERE
                                id = ?
                                AND role = 'Doctor'
                             LIMIT 1
                             FOR UPDATE"
                        );


                    $doctorCheckStmt->execute([
                        $doctorIdInt
                    ]);


                    $doctor =
                        $doctorCheckStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        !$doctor
                    ) {

                        throw new RuntimeException(
                            'The selected doctor is not available.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | VERIFY AUTHENTICATED PATIENT
                    |--------------------------------------------------------------------------
                    |
                    | The patient ID always comes from the authenticated
                    | session. It is never accepted from POST data.
                    |--------------------------------------------------------------------------
                    */

                    $patientCheckStmt =
                        $pdo->prepare(
                            "SELECT
                                id
                             FROM users
                             WHERE
                                id = ?
                                AND role = 'Patient'
                             LIMIT 1
                             FOR UPDATE"
                        );


                    $patientCheckStmt->execute([
                        $userId
                    ]);


                    $patientExists =
                        $patientCheckStmt->fetchColumn();


                    if (
                        $patientExists === false
                    ) {

                        throw new RuntimeException(
                            'Your patient account could not be verified.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK ACTIVE APPOINTMENT CONFLICT
                    |--------------------------------------------------------------------------
                    |
                    | Pending and Confirmed appointments occupy the slot.
                    | Completed and Cancelled appointments do not.
                    |--------------------------------------------------------------------------
                    */

                    $conflictStmt =
                        $pdo->prepare(
                            "SELECT
                                id
                             FROM appointments
                             WHERE
                                doctor_id = ?
                                AND appointment_date = ?
                                AND appointment_time = ?
                                AND status IN (
                                    'Pending',
                                    'Confirmed'
                                )
                             LIMIT 1
                             FOR UPDATE"
                        );


                    $conflictStmt->execute([
                        $doctorIdInt,
                        $date->format('Y-m-d'),
                        $time->format('H:i')
                    ]);


                    $conflict =
                        $conflictStmt->fetchColumn();


                    if (
                        $conflict !== false
                    ) {

                        throw new RuntimeException(
                            'That doctor is already booked for the selected time. Please choose another time.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CREATE APPOINTMENT
                    |--------------------------------------------------------------------------
                    */

                    $insertStmt =
                        $pdo->prepare(
                            "INSERT INTO appointments
                            (
                                user_id,
                                doctor_id,
                                appointment_date,
                                appointment_time,
                                reason,
                                status,
                                created_at
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                'Pending',
                                NOW()
                            )"
                        );


                    $insertStmt->execute([
                        $userId,
                        $doctorIdInt,
                        $date->format('Y-m-d'),
                        $time->format('H:i'),
                        $reason !== ''
                            ? $reason
                            : null
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | COMMIT
                    |--------------------------------------------------------------------------
                    */

                    $pdo->commit();


                    $success =
                        'Appointment successfully booked. It is now awaiting confirmation.';


                    /*
                    |--------------------------------------------------------------------------
                    | CLEAR FORM
                    |--------------------------------------------------------------------------
                    */

                    $doctorId = '';

                    $appointmentDate = '';

                    $appointmentTime = '';

                    $reason = '';


                } catch (
                    RuntimeException $e
                ) {


                    /*
                    |--------------------------------------------------------------------------
                    | ROLLBACK
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();
                    }


                    $error =
                        $e->getMessage();


                } catch (
                    PDOException $e
                ) {


                    /*
                    |--------------------------------------------------------------------------
                    | ROLLBACK
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | DATABASE-LEVEL DOUBLE BOOKING PROTECTION
                    |--------------------------------------------------------------------------
                    |
                    | Your uq_appointments_active_slot unique key remains
                    | the final protection against concurrent duplicate
                    | bookings.
                    |--------------------------------------------------------------------------
                    */

                    $duplicateKey =
                        isset(
                            $e->errorInfo[1]
                        ) &&
                        (string) (
                            $e->errorInfo[1]
                        ) === '1062';


                    if (
                        $duplicateKey
                    ) {

                        $error =
                            'That doctor is already booked for the selected time. Please choose another time.';

                    } else {

                        error_log(
                            'MediCare appointment booking failed: ' .
                            $e->getMessage()
                        );


                        $error =
                            'Unable to book the appointment right now. Please try again.';
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| USER INITIAL
|--------------------------------------------------------------------------
*/

$userInitial =
    mb_strtoupper(
        mb_substr(
            trim(
                $displayName
            ),
            0,
            1
        )
    );


if (
    $userInitial === ''
) {

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
        content="Book a healthcare appointment with MediCare."
    >


    <title>
        Book Appointment | MediCare
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

            --secondary-text:
                #9db7c3;

            --heading-text:
                #edf6f9;

            --card-text:
                #dceaf0;

            --label-text:
                #cadbe2;

            --caption-text:
                #718b99;

            --muted-text:
                #6d8896;

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

            --input-bg-focus:
                rgba(
                    2,
                    22,
                    36,
                    0.80
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

            --secondary-text:
                #607886;

            --heading-text:
                #183241;

            --card-text:
                #294454;

            --label-text:
                #536c79;

            --caption-text:
                #718792;

            --muted-text:
                #6d8290;

            --nav-text:
                #536c79;

            --nav-muted:
                #708795;

            --nav-title:
                #78909d;

            --input-bg:
                #ffffff;

            --input-bg-focus:
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
                var(--surface-strong);

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
                    1120px
                );

            margin:
                0 auto;

            padding:
                30px;
        }


        /* =========================================================
           ACTION BAR
        ========================================================== */

        .page-actions {

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
                var(--surface-soft);

            border:
                1px solid
                var(--border);

            transition:
                .2s ease;
        }


        .back-link:hover {

            color:
                var(--primary);
        }


        .appointments-link {

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
                    0.13
                );

            transition:
                .2s ease;
        }


        .appointments-link:hover {

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

        .booking-hero {

            position:
                relative;

            overflow:
                hidden;

            margin-bottom:
                18px;

            padding:
                29px 30px;

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


        .booking-hero::after {

            content:
                "";

            position:
                absolute;

            width:
                330px;

            height:
                330px;

            top:
                -155px;

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

            box-shadow:

                0 0 0 45px
                rgba(
                    16,
                    199,
                    176,
                    0.015
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


        .booking-hero h1 {

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


        .booking-hero p {

            max-width:
                730px;

            margin-top:
                10px;

            color:
                var(--soft-text);

            font-size:
                10px;

            line-height:
                1.75;
        }


        .patient-badge {

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

            border:
                1px solid
                var(--border);

            border-radius:
                999px;

            color:
                var(--card-text);

            background:
                var(--surface-strong);

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .patient-badge i {

            color:
                var(--primary);
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


        .alert a {

            margin-left:
                5px;

            color:
                var(--primary);

            font-weight:
                800;
        }


        /* =========================================================
           BOOKING PANEL
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
                72px;

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
           FORM GRID
        ========================================================== */

        .booking-form {

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
                flex;

            align-items:
                center;

            gap:
                4px;

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
                #617f8e;

            font-size:
                11px;

            pointer-events:
                none;

            z-index:
                2;
        }


        .field input,
        .field select,
        .field textarea {

            width:
                100%;

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


        .field input,
        .field select {

            height:
                47px;

            padding:
                0 13px;
        }


        .input-wrap input,
        .input-wrap select {

            padding-left:
                37px;
        }


        .field textarea {

            min-height:
                125px;

            padding:
                12px 13px;

            resize:
                vertical;

            line-height:
                1.65;
        }


        .field input::placeholder,
        .field textarea::placeholder {

            color:
                var(--input-placeholder);
        }


        .field input:focus,
        .field select:focus,
        .field textarea:focus {

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
                var(--input-bg-focus);
        }


        .field select {

            cursor:
                pointer;
        }


        /* =========================================================
           AVAILABILITY
        ========================================================== */

        .availability-note {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                9px;

            margin-top:
                8px;

            padding:
                12px 13px;

            border:
                1px solid
                rgba(
                    44,
                    140,
                    255,
                    0.12
                );

            border-radius:
                9px;

            background:
                rgba(
                    44,
                    140,
                    255,
                    0.035
                );

            color:
                #7894a3;

            font-size:
                8px;

            line-height:
                1.7;
        }


        .availability-note i {

            color:
                #6cb8ff;
        }


        .availability-note strong {

            color:
                var(--card-text);
        }


        /* =========================================================
           FIELD HELP
        ========================================================== */

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


        .security-note {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                8px;

            max-width:
                580px;

            color:
                var(--muted-text);

            font-size:
                8px;

            line-height:
                1.65;
        }


        .security-note i {

            color:
                var(--primary);
        }


        .security-note strong {

            color:
                var(--heading-text);
        }


        .action-buttons {

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
                opacity .2s ease,
                background .2s ease;
        }


        .button:hover {

            transform:
                translateY(-1px);
        }


        .cancel-button {

            color:
                var(--muted);

            background:
                var(--surface-soft);

            border:
                1px solid
                var(--border);
        }


        .cancel-button:hover {

            color:
                var(--white-text);
        }


        .book-button {

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


        .book-button:hover {

            box-shadow:
                0 14px 30px
                rgba(
                    16,
                    199,
                    176,
                    0.16
                );
        }


        .book-button:disabled {

            opacity:
                .45;

            cursor:
                not-allowed;

            transform:
                none;

            box-shadow:
                none;
        }


        /* =========================================================
           QUICK INFORMATION
        ========================================================== */

        .info-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
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

            min-height:
                100px;

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
                7px;

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
        .booking-hero,
        .panel,
        .user-box,
        .top-icon,
        .input,
        .select,
        .field textarea,
        .info-card,
        .page-actions a {

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


        @media (max-width: 750px) {

            .content {

                padding:
                    20px 14px 30px;
            }


            .page-actions {

                flex-direction:
                    column;

                align-items:
                    stretch;
            }


            .action-link {

                width:
                    100%;
            }


            .booking-hero {

                padding:
                    25px 20px;
            }


            .booking-form {

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


            .action-buttons {

                width:
                    100%;
            }


            .button {

                flex:
                    1;
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
            | Keep theme toggle visible on small screens.
            |--------------------------------------------------------------------------
            */

            .theme-toggle {

                display:
                    grid;
            }


            .form-body {

                padding:
                    17px;
            }


            .action-buttons {

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


    <div class="nav-title">
        Main Menu
    </div>


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


        <a
            href="book_appointment.php"
            class="active"
        >

            <i class="fa-solid fa-calendar-plus"></i>

            <span>
                Book Appointment
            </span>

        </a>


        <a href="patient_profile.php">

            <i class="fa-solid fa-id-card"></i>

            <span>
                My Profile
            </span>

        </a>


    </nav>


    <div class="sidebar-spacer"></div>


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


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">


        <div class="top-title">

            <small>
                MediCare Patient Portal
            </small>

            <h2>
                Book Appointment
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


        <!-- =================================================
             ACTION BAR
        ================================================== -->

        <div class="page-actions">


            <a
                href="dashboard.php"
                class="
                    action-link
                    back-link
                "
            >

                <i class="fa-solid fa-arrow-left"></i>

                Back to Dashboard

            </a>


            <a
                href="appointments.php"
                class="
                    action-link
                    appointments-link
                "
            >

                <i
                    class="
                        fa-regular
                        fa-calendar-check
                    "
                ></i>

                My Appointments

            </a>


        </div>


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="booking-hero">


            <div class="hero-content">


                <div class="eyebrow">

                    Patient Portal

                </div>


                <h1>
                    Book an Appointment
                </h1>


                <p>

                    Choose your preferred doctor, date and time,
                    then provide a brief reason for your visit.
                    Your request will be submitted for confirmation.

                </p>


                <span class="patient-badge">

                    <i class="fa-solid fa-calendar-plus"></i>

                    Patient Booking

                </span>


            </div>


        </section>


        <!-- =================================================
             SUCCESS MESSAGE
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


                <div>

                    <?= e(
                        $success
                    ) ?>


                    <a
                        href="appointments.php"
                    >

                        View Appointments

                        <i
                            class="
                                fa-solid
                                fa-arrow-right
                            "
                        ></i>

                    </a>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ERROR MESSAGE
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
             BOOKING PANEL
        ================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Healthcare Scheduling
                    </span>

                    <h2>
                        Appointment Details
                    </h2>

                    <p>
                        Fields marked with * are required.
                    </p>

                </div>


            </header>


            <div class="form-body">


                <form
                    method="POST"
                    action="book_appointment.php"
                    class="booking-form"
                    autocomplete="off"
                >


                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?= e(
                            csrf_token()
                        ) ?>"
                    >


                    <!-- =================================================
                         DOCTOR
                    ================================================== -->

                    <div class="field full">


                        <label for="doctor_id">

                            Doctor

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="input-wrap">


                            <i
                                class="
                                    input-icon
                                    fa-solid
                                    fa-user-doctor
                                "
                            ></i>


                            <select
                                id="doctor_id"
                                name="doctor_id"
                                required
                            >


                                <option value="">
                                    Choose a doctor
                                </option>


                                <?php foreach (
                                    $doctors as $doctor
                                ): ?>


                                    <option
                                        value="<?= (int) $doctor['id'] ?>"
                                        <?= (
                                            (int) $doctorId ===
                                            (int) $doctor['id']
                                        )
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        Dr.

                                        <?= e(
                                            (string)
                                            $doctor['fullname']
                                        ) ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>


                        <?php if (
                            !empty($doctors)
                        ): ?>


                            <div class="availability-note">


                                <i
                                    class="
                                        fa-solid
                                        fa-circle-info
                                    "
                                ></i>


                                <span>

                                    <strong>
                                        Doctor availability:
                                    </strong>

                                    Select your preferred date and
                                    time. MediCare will prevent
                                    conflicting active appointments
                                    for the same doctor.

                                </span>


                            </div>


                        <?php else: ?>


                            <div class="availability-note">


                                <i
                                    class="
                                        fa-solid
                                        fa-triangle-exclamation
                                    "
                                ></i>


                                <span>

                                    No doctors are currently
                                    available for booking.
                                    Please try again later.

                                </span>


                            </div>


                        <?php endif; ?>


                    </div>


                    <!-- =================================================
                         DATE
                    ================================================== -->

                    <div class="field">


                        <label for="appointment_date">

                            Appointment Date

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="input-wrap">


                            <i
                                class="
                                    input-icon
                                    fa-regular
                                    fa-calendar
                                "
                            ></i>


                            <input
                                id="appointment_date"
                                type="date"
                                name="appointment_date"
                                min="<?= e(
                                    date('Y-m-d')
                                ) ?>"
                                value="<?= e(
                                    $appointmentDate
                                ) ?>"
                                required
                            >


                        </div>


                    </div>


                    <!-- =================================================
                         TIME
                    ================================================== -->

                    <div class="field">


                        <label for="appointment_time">

                            Appointment Time

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="input-wrap">


                            <i
                                class="
                                    input-icon
                                    fa-regular
                                    fa-clock
                                "
                            ></i>


                            <input
                                id="appointment_time"
                                type="time"
                                name="appointment_time"
                                value="<?= e(
                                    $appointmentTime
                                ) ?>"
                                required
                            >


                        </div>


                    </div>


                    <!-- =================================================
                         REASON
                    ================================================== -->

                    <div class="field full">


                        <label for="reason">

                            Reason for Visit

                            <span class="optional">
                                Optional
                            </span>

                        </label>


                        <textarea
                            id="reason"
                            name="reason"
                            maxlength="1000"
                            rows="6"
                            placeholder="Briefly describe why you need the appointment..."
                        ><?= e(
                            $reason
                        ) ?></textarea>


                        <div class="field-help">

                            Keep this brief and relevant to your
                            appointment. Do not enter passwords or
                            other unnecessary confidential information.

                        </div>


                    </div>


                    <!-- =================================================
                         ACTIONS
                    ================================================== -->

                    <div class="form-actions">


                        <div class="security-note">


                            <i
                                class="
                                    fa-solid
                                    fa-shield-heart
                                "
                            ></i>


                            <span>

                                Your appointment request will be
                                securely submitted to MediCare.
                                Booking remains <strong>Pending</strong>
                                until it is confirmed by authorized
                                staff.

                            </span>


                        </div>


                        <div class="action-buttons">


                            <a
                                href="appointments.php"
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
                                    book-button
                                "
                                <?= empty($doctors)
                                    ? 'disabled'
                                    : '' ?>
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-calendar-plus
                                    "
                                ></i>

                                Book Appointment

                            </button>


                        </div>


                    </div>


                </form>


            </div>


            <!-- =================================================
                 INFORMATION CARDS
            ================================================== -->

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
                        Secure Request
                    </strong>


                    <span>

                        Your appointment details are submitted
                        through the protected MediCare portal.

                    </span>


                </div>


                <div class="info-card">


                    <div class="info-icon">

                        <i
                            class="
                                fa-regular
                                fa-clock
                            "
                        ></i>

                    </div>


                    <strong>
                        Confirmation Required
                    </strong>


                    <span>

                        New appointments begin as Pending until
                        authorized staff confirm them.

                    </span>


                </div>


                <div class="info-card">


                    <div class="info-icon">

                        <i
                            class="
                                fa-solid
                                fa-calendar-check
                            "
                        ></i>

                    </div>


                    <strong>
                        No Double Booking
                    </strong>


                    <span>

                        MediCare checks the doctor's schedule
                        before creating your appointment request.

                    </span>


                </div>


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


        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE SUBMISSION
        |--------------------------------------------------------------------------
        */

        const form =
            document.querySelector(
                'form.booking-form'
            );


        const submitButton =
            form
                ? form.querySelector(
                    '.book-button'
                )
                : null;


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
                        '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DATE MINIMUM
        |--------------------------------------------------------------------------
        */

        const dateInput =
            document.getElementById(
                'appointment_date'
            );


        if (
            dateInput
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


            dateInput.min =
                `${year}-${month}-${day}`;
        }

    }
);

</script>


</body>

</html>