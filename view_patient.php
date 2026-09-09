<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

require_role('Admin', 'Doctor');


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

$appointments = [];

$medicalRecords = [];

$error = '';

$success = '';

$linkedUserId = null;


/*
|--------------------------------------------------------------------------
| LOAD PATIENT
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
            created_at,
            is_active
         FROM patients
         WHERE id = ?
         LIMIT 1"
    );

    $patientStmt->execute([
        $patientId
    ]);

    $patient = $patientStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$patient) {

        http_response_code(404);

        exit('Patient not found.');
    }


    if (
        isset($patient['user_id']) &&
        $patient['user_id'] !== null &&
        (int) $patient['user_id'] > 0
    ) {

        $linkedUserId =
            (int) $patient['user_id'];
    }


} catch (PDOException $e) {

    error_log(
        'MediCare patient profile lookup failed: ' .
        $e->getMessage()
    );

    http_response_code(503);

    exit(
        'Unable to load this patient record right now.'
    );
}


/*
|--------------------------------------------------------------------------
| PATIENT STATUS
|--------------------------------------------------------------------------
*/

$isActive =
    (int) (
        $patient['is_active'] ?? 1
    ) === 1;


/*
|--------------------------------------------------------------------------
| SUCCESS / STATUS MESSAGE
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


if ($actionMessage !== '') {

    $success =
        $actionMessage;

} elseif ($statusMessage === 'activated') {

    $success =
        'Patient account has been reactivated successfully.';

} elseif ($statusMessage === 'deactivated') {

    $success =
        'Patient has been deactivated successfully.';

} elseif (
    isset($_GET['updated']) &&
    $_GET['updated'] === '1'
) {

    $success =
        'Patient information updated successfully.';
}


/*
|--------------------------------------------------------------------------
| LOAD APPOINTMENTS AND CLINICAL RECORDS
|--------------------------------------------------------------------------
*/

if ($linkedUserId !== null) {


    /*
    |--------------------------------------------------------------------------
    | APPOINTMENTS
    |--------------------------------------------------------------------------
    */

    try {

        $appointmentStmt = $pdo->prepare(
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
            $linkedUserId
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

        $error =
            'Appointment history is temporarily unavailable.';
    }


    /*
    |--------------------------------------------------------------------------
    | CLINICAL RECORDS
    |--------------------------------------------------------------------------
    */

    try {

        $recordStmt = $pdo->prepare(
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
            $linkedUserId
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

        if ($error === '') {

            $error =
                'Clinical records are temporarily unavailable.';

        } else {

            $error .=
                ' Clinical records are also temporarily unavailable.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string) (
        $patient['first_name'] ?? ''
    )
);

$lastName = trim(
    (string) (
        $patient['last_name'] ?? ''
    )
);

$fullName = trim(
    $firstName . ' ' . $lastName
);

if ($fullName === '') {
    $fullName = 'Unnamed Patient';
}


$dob = safe_date(
    (string) (
        $patient['dob'] ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| PATIENT INITIAL
|--------------------------------------------------------------------------
*/

$patientInitial = mb_strtoupper(
    mb_substr(
        $firstName !== ''
            ? $firstName
            : $fullName,
        0,
        1
    )
);

if ($patientInitial === '') {
    $patientInitial = 'P';
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$displayName = (string) (
    $_SESSION['fullname'] ?? 'User'
);

$role = current_role();

$userInitial = mb_strtoupper(
    mb_substr(
        trim(
            $displayName
        ),
        0,
        1
    )
);


if ($userInitial === '') {
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
        content="MediCare patient profile, appointments and clinical records."
    >


    <title>
        <?= e($fullName) ?> | MediCare
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
               THEME VARIABLES
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
                #718b99;

            --caption-text:
                #66808f;

            --table-text:
                #a7bcc6;

            --table-strong:
                #deebf0;

            --table-muted:
                #829ba9;

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
        ========================================================== */

        html[data-theme="light"] {

            --bg:
                #f4f8fb;

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

            --caption-text:
                #718792;

            --table-text:
                #617783;

            --table-strong:
                #294454;

            --table-muted:
                #718792;

            --nav-text:
                #536c79;

            --nav-muted:
                #708795;

            --nav-title:
                #78909d;

            --input-bg:
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
           USER AREA
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
           TOP BAR
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
           PAGE ACTIONS
        ========================================================== */

        .page-actions {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

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

            transition:
                transform .2s ease,
                background .2s ease,
                color .2s ease;
        }


        .back-link {

            color:
                var(--muted);

            background:
                var(--surface-soft);

            border:
                1px solid
                var(--border);
        }


        .back-link:hover {

            color:
                var(--primary);
        }


        .edit-link {

            color:
                white;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    #10b4d3
                );
        }


        .edit-link:hover {

            transform:
                translateY(-1px);
        }


        /* =========================================================
           STATUS AREA
        ========================================================== */

        .status-area {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            margin-bottom:
                18px;

            padding:
                13px 15px;

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


        .status-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                7px 10px;

            border-radius:
                999px;

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;
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
                    0.14
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
                    0.14
                );
        }


        .status-description {

            flex:
                1;

            color:
                var(--muted-text);

            font-size:
                8px;

            line-height:
                1.5;
        }


        .status-form {

            margin:
                0;
        }


        .status-button {

            min-height:
                34px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                6px;

            padding:
                0 11px;

            border-radius:
                8px;

            font-size:
                8px;

            font-weight:
                800;

            cursor:
                pointer;
        }


        .deactivate-button {

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    0.16
                );

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.06
                );

            color:
                #ff969c;
        }


        .activate-button {

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    0.16
                );

            background:
                rgba(
                    85,
                    216,
                    144,
                    0.06
                );

            color:
                #72e5ac;
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
           PATIENT HERO
        ========================================================== */

        .profile-hero {

            position:
                relative;

            overflow:
                hidden;

            margin-bottom:
                18px;

            padding:
                28px;

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


        .profile-hero.inactive {

            border-color:
                rgba(
                    255,
                    114,
                    121,
                    0.16
                );

            background:
                linear-gradient(
                    135deg,
                    #3a2733,
                    #29212f
                );
        }


        html[data-theme="light"]
        .profile-hero.inactive {

            background:
                linear-gradient(
                    135deg,
                    #f9e8ea,
                    #f7f3f5
                );
        }


        .profile-hero::after {

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
        }


        .profile-content {

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


        .patient-avatar {

            width:
                76px;

            height:
                76px;

            flex-shrink:
                0;

            display:
                grid;

            place-items:
                center;

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
                28px;

            font-weight:
                800;
        }


        .profile-hero.inactive
        .patient-avatar {

            color:
                #ff9298;

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.08
                );

            border-color:
                rgba(
                    255,
                    114,
                    121,
                    0.16
                );
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

            color:
                var(--white-text);

            font-size:
                clamp(
                    27px,
                    3vw,
                    38px
                );

            line-height:
                1.05;

            letter-spacing:
                -1.6px;
        }


        .patient-number {

            display:
                block;

            margin-top:
                7px;

            color:
                var(--soft-text);

            font-size:
                10px;
        }


        /* =========================================================
           PANELS
        ========================================================== */

        .panel {

            margin-bottom:
                18px;

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


        .panel-description {

            margin-top:
                5px;

            color:
                var(--muted-text);

            font-size:
                9px;
        }


        .count-badge {

            min-width:
                28px;

            height:
                28px;

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

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    0.12
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
           INFO GRID
        ========================================================== */

        .info-grid {

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


        .info-item {

            min-height:
                96px;

            padding:
                16px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            background:
                var(--surface-soft);
        }


        .info-item label {

            display:
                block;

            margin-bottom:
                7px;

            color:
                var(--muted-text);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .6px;

            text-transform:
                uppercase;
        }


        .info-item strong {

            display:
                block;

            color:
                var(--card-text);

            font-size:
                11px;

            line-height:
                1.55;
        }


        /* =========================================================
           HISTORY
        ========================================================== */

        .history-box {

            padding:
                17px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            background:
                var(--surface-soft);

            color:
                var(--table-text);

            font-size:
                10px;

            line-height:
                1.75;

            white-space:
                pre-wrap;
        }


        .empty-text {

            color:
                var(--muted-text);

            font-style:
                italic;
        }


        /* =========================================================
           APPOINTMENTS
        ========================================================== */

        .appointment-list {

            padding:
                0 20px;
        }


        .appointment-item {

            display:
                grid;

            grid-template-columns:
                150px
                minmax(
                    160px,
                    1fr
                )
                minmax(
                    180px,
                    1.5fr
                )
                95px;

            align-items:
                center;

            gap:
                15px;

            padding:
                17px 0;

            border-bottom:
                1px solid
                var(--row-border);
        }


        .appointment-item:last-child {

            border-bottom:
                none;
        }


        .appointment-date strong {

            display:
                block;

            margin-bottom:
                4px;

            color:
                var(--table-strong);

            font-size:
                10px;
        }


        .appointment-date span,
        .appointment-doctor span {

            color:
                var(--caption-text);

            font-size:
                8px;
        }


        .appointment-doctor strong {

            display:
                block;

            margin-bottom:
                4px;

            color:
                var(--card-text);

            font-size:
                10px;
        }


        .appointment-reason {

            color:
                var(--table-muted);

            font-size:
                9px;

            line-height:
                1.6;
        }


        .status {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            min-width:
                78px;

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
                rgba(
                    255,
                    255,
                    255,
                    0.04
                );
        }


        /* =========================================================
           MEDICAL RECORDS
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
                var(--surface-soft);
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
                14px;
        }


        .record-title {

            color:
                var(--card-text);

            font-size:
                12px;

            font-weight:
                800;
        }


        .record-date {

            margin-top:
                4px;

            color:
                var(--caption-text);

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

            letter-spacing:
                .6px;

            text-transform:
                uppercase;
        }


        .record-value {

            color:
                var(--table-text);

            font-size:
                9px;

            line-height:
                1.7;

            white-space:
                pre-wrap;
        }


        /* =========================================================
           EMPTY STATE
        ========================================================== */

        .empty-state {

            padding:
                55px 20px;

            text-align:
                center;

            color:
                var(--muted-text);
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
                11px;
        }


        .empty-state span {

            display:
                block;

            max-width:
                460px;

            margin:
                0 auto;

            color:
                var(--caption-text);

            font-size:
                9px;

            line-height:
                1.7;
        }


        /* =========================================================
           ACCOUNT NOTE
        ========================================================== */

        .account-note {

            margin-top:
                12px;

            padding:
                12px 13px;

            border:
                1px solid
                rgba(
                    255,
                    184,
                    77,
                    0.12
                );

            border-radius:
                10px;

            background:
                rgba(
                    255,
                    184,
                    77,
                    0.045
                );

            color:
                #9a8a67;

            font-size:
                8px;

            line-height:
                1.7;
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
        .profile-hero,
        .status-area,
        .panel,
        .user-box,
        .top-icon,
        .info-item,
        .history-box,
        .record,
        .account-note {

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


            .info-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );
            }


            .appointment-item {

                grid-template-columns:
                    130px
                    1fr
                    1.3fr
                    80px;
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


            .status-area {

                align-items:
                    stretch;

                flex-direction:
                    column;
            }


            .status-form {

                width:
                    100%;
            }


            .status-button {

                width:
                    100%;
            }


            .profile-content {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .profile-hero {

                padding:
                    24px 20px;
            }


            .info-grid {

                grid-template-columns:
                    1fr;
            }


            .appointment-item {

                grid-template-columns:
                    1fr;

                gap:
                    9px;
            }


            .record-top {

                flex-direction:
                    column;
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


            .panel-body {

                padding:
                    15px;
            }


            .appointment-list,
            .record-list {

                padding:
                    0 15px;
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
                Patient Profile
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


    <!-- =========================================================
         CONTENT
    ========================================================== -->

    <div class="content">


        <!-- =====================================================
             ACTIONS
        ====================================================== -->

        <div class="page-actions">


            <a
                href="patients.php"
                class="action-link back-link"
            >

                <i
                    class="
                        fa-solid
                        fa-arrow-left
                    "
                ></i>

                Back to Patients

            </a>


            <a
                href="edit_patient.php?id=<?= (int) $patientId ?>"
                class="action-link edit-link"
            >

                <i class="fa-solid fa-pen"></i>

                Edit Patient

            </a>


        </div>


        <!-- =====================================================
             SUCCESS
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


        <!-- =====================================================
             ERROR
        ====================================================== -->

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
             PATIENT STATUS
        ====================================================== -->

        <div class="status-area">


            <?php if (
                $isActive
            ): ?>


                <span
                    class="
                        status-badge
                        status-active
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-circle-check
                        "
                    ></i>

                    Active

                </span>


                <span class="status-description">

                    This patient is currently active in
                    the MediCare system.

                </span>


                <form
                    method="POST"
                    action="deactivate_patient.php?action=deactivate&id=<?= (int) $patientId ?>"
                    class="status-form"
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
                        class="
                            status-button
                            deactivate-button
                        "
                        onclick="
                            return confirm(
                                'Are you sure you want to deactivate this patient? The patient records will be preserved.'
                            );
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-user-slash
                            "
                        ></i>

                        Deactivate

                    </button>


                </form>


            <?php else: ?>


                <span
                    class="
                        status-badge
                        status-inactive
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-circle-xmark
                        "
                    ></i>

                    Inactive

                </span>


                <span class="status-description">

                    This patient has been deactivated.
                    Existing records remain preserved.

                </span>


                <form
                    method="POST"
                    action="deactivate_patient.php?action=activate&id=<?= (int) $patientId ?>"
                    class="status-form"
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
                        class="
                            status-button
                            activate-button
                        "
                        onclick="
                            return confirm(
                                'Reactivate this patient?'
                            );
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-user-check
                            "
                        ></i>

                        Reactivate

                    </button>


                </form>


            <?php endif; ?>


        </div>


        <!-- =====================================================
             PATIENT HERO
        ====================================================== -->

        <section
            class="
                profile-hero
                <?= !$isActive
                    ? 'inactive'
                    : '' ?>
            "
        >


            <div class="profile-content">


                <div class="patient-avatar">

                    <?= e(
                        $patientInitial
                    ) ?>

                </div>


                <div class="profile-copy">


                    <div class="eyebrow">
                        Patient Profile
                    </div>


                    <h1>
                        <?= e(
                            $fullName
                        ) ?>
                    </h1>


                    <span class="patient-number">

                        Patient ID #

                        <?= (int) $patient['id'] ?>

                    </span>


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
                        Patient Information
                    </span>

                    <h2>
                        Personal Details
                    </h2>

                    <p class="panel-description">
                        Registered patient information.
                    </p>

                </div>


            </header>


            <div class="panel-body">


                <div class="info-grid">


                    <div class="info-item">

                        <label>
                            Date of Birth
                        </label>


                        <strong>


                            <?php if (
                                $dob !== null
                            ): ?>

                                <?= e(
                                    $dob->format(
                                        'F j, Y'
                                    )
                                ) ?>


                            <?php else: ?>

                                Not provided

                            <?php endif; ?>


                        </strong>


                    </div>


                    <div class="info-item">

                        <label>
                            Gender
                        </label>


                        <strong>

                            <?= e(
                                !empty(
                                    $patient['gender']
                                )
                                    ? (string)
                                        $patient['gender']
                                    : 'Not provided'
                            ) ?>

                        </strong>


                    </div>


                    <div class="info-item">

                        <label>
                            Phone
                        </label>


                        <strong>

                            <?= e(
                                !empty(
                                    $patient['phone']
                                )
                                    ? (string)
                                        $patient['phone']
                                    : 'Not provided'
                            ) ?>

                        </strong>


                    </div>


                    <div class="info-item">

                        <label>
                            Address
                        </label>


                        <strong>

                            <?= e(
                                !empty(
                                    $patient['address']
                                )
                                    ? (string)
                                        $patient['address']
                                    : 'Not provided'
                            ) ?>

                        </strong>


                    </div>


                </div>


                <?php if (
                    $linkedUserId === null
                ): ?>

                    <div class="account-note">

                        <i
                            class="
                                fa-solid
                                fa-link
                            "
                        ></i>

                        &nbsp;

                        This patient record is not linked to
                        a MediCare Patient login account.

                    </div>

                <?php endif; ?>


            </div>


        </section>


        <!-- =====================================================
             MEDICAL HISTORY
        ====================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Medical Information
                    </span>

                    <h2>
                        Medical History
                    </h2>

                    <p class="panel-description">
                        Information recorded during registration.
                    </p>

                </div>


            </header>


            <div class="panel-body">


                <div class="history-box">


                    <?php if (
                        !empty(
                            $patient['medical_history']
                        )
                    ): ?>

                        <?= e(
                            (string)
                                $patient[
                                    'medical_history'
                                ]
                        ) ?>

                    <?php else: ?>

                        <span class="empty-text">

                            No medical history has been
                            recorded for this patient.

                        </span>

                    <?php endif; ?>


                </div>


            </div>


        </section>


        <!-- =====================================================
             APPOINTMENT HISTORY
        ====================================================== -->

        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Appointment Activity
                    </span>

                    <h2>
                        Appointment History
                    </h2>

                    <p class="panel-description">
                        Appointments associated with this patient.
                    </p>

                </div>


                <?php if (
                    $linkedUserId !== null
                ): ?>

                    <span class="count-badge">
                        <?= count(
                            $appointments
                        ) ?>
                    </span>

                <?php endif; ?>


            </header>


            <?php if (
                $linkedUserId === null
            ): ?>


                <div class="empty-state">


                    <i class="fa-solid fa-link"></i>


                    <strong>
                        Patient account not linked
                    </strong>


                    <span>

                        Appointment history will appear here
                        once this patient is linked to a
                        MediCare Patient account.

                    </span>


                </div>


            <?php elseif (
                !empty($appointments)
            ): ?>


                <div class="appointment-list">


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
                            strtolower(
                                trim(
                                    (string) (
                                        $appointment[
                                            'status'
                                        ] ?? ''
                                    )
                                )
                            );


                        $statusClass =
                            match (
                                $appointmentStatus
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


                        ?>


                        <div class="appointment-item">


                            <div class="appointment-date">


                                <strong>


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


                                </strong>


                                <span>


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


                            </div>


                            <div class="appointment-doctor">


                                <strong>


                                    <?php if (
                                        !empty(
                                            $appointment[
                                                'doctor_name'
                                            ]
                                        )
                                    ): ?>

                                        Dr.

                                        <?= e(
                                            (string)
                                                $appointment[
                                                    'doctor_name'
                                                ]
                                        ) ?>


                                    <?php else: ?>

                                        Doctor not assigned

                                    <?php endif; ?>


                                </strong>


                                <span>

                                    Appointment #

                                    <?= (int) (
                                        $appointment['id']
                                    ) ?>

                                </span>


                            </div>


                            <div class="appointment-reason">


                                <?= e(
                                    !empty(
                                        $appointment['reason']
                                    )
                                        ? (string)
                                            $appointment['reason']
                                        : 'Routine Checkup'
                                ) ?>


                            </div>


                            <div>


                                <span
                                    class="
                                        status
                                        <?= e(
                                            $statusClass
                                        ) ?>
                                    "
                                >

                                    <?= e(
                                        (string) (
                                            $appointment[
                                                'status'
                                            ] ?? 'Unknown'
                                        )
                                    ) ?>

                                </span>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-state">


                    <i
                        class="
                            fa-regular
                            fa-calendar-xmark
                        "
                    ></i>


                    <strong>
                        No appointments found
                    </strong>


                    <span>

                        This patient does not have any
                        recorded appointments yet.

                    </span>


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
                        Medical Records
                    </h2>

                    <p class="panel-description">
                        Doctor notes and prescriptions linked
                        to this patient's appointments.
                    </p>

                </div>


                <?php if (
                    $linkedUserId !== null
                ): ?>

                    <span class="count-badge">
                        <?= count(
                            $medicalRecords
                        ) ?>
                    </span>

                <?php endif; ?>


            </header>


            <?php if (
                $linkedUserId === null
            ): ?>


                <div class="empty-state">


                    <i class="fa-solid fa-link"></i>


                    <strong>
                        Patient account not linked
                    </strong>


                    <span>

                        Clinical records can be connected after
                        the patient is linked to a MediCare account.

                    </span>


                </div>


            <?php elseif (
                !empty($medicalRecords)
            ): ?>


                <div class="record-list">


                    <?php foreach (
                        $medicalRecords as $record
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

                        ?>


                        <article class="record">


                            <div class="record-top">


                                <div>


                                    <div class="record-title">


                                        <?php if (
                                            !empty(
                                                $record[
                                                    'doctor_name'
                                                ]
                                            )
                                        ): ?>

                                            Dr.

                                            <?= e(
                                                (string)
                                                    $record[
                                                        'doctor_name'
                                                    ]
                                            ) ?>


                                        <?php else: ?>

                                            Clinical Record

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
                                    $record['notes']
                                )
                            ): ?>


                                <div class="record-section">


                                    <span class="record-label">
                                        Clinical Notes
                                    </span>


                                    <div class="record-value">

                                        <?= e(
                                            (string)
                                                $record['notes']
                                        ) ?>

                                    </div>


                                </div>


                            <?php endif; ?>


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
                                            (string)
                                                $record[
                                                    'prescription'
                                                ]
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
                                            (string)
                                                $record[
                                                    'reason'
                                                ]
                                        ) ?>

                                    <?php endif; ?>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-state">


                    <i class="fa-solid fa-file-medical"></i>


                    <strong>
                        No clinical records yet
                    </strong>


                    <span>

                        No doctor clinical record has been
                        created from this patient's appointments.

                    </span>


                </div>


            <?php endif; ?>


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