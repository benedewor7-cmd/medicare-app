<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Doctors can create clinical records.
|--------------------------------------------------------------------------
*/

require_role('Doctor');


/*
|--------------------------------------------------------------------------
| CURRENT DOCTOR
|--------------------------------------------------------------------------
*/

$doctorId = current_user_id();


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';

$diagnosis = '';

$prescription = '';

$notes = '';

$appointment = null;


/*
|--------------------------------------------------------------------------
| APPOINTMENT ID
|--------------------------------------------------------------------------
| Accept the appointment ID from either GET or POST.
| GET takes precedence when both are present.
|--------------------------------------------------------------------------
*/

$rawAppointmentId =
    $_GET['appointment_id']
    ?? $_POST['appointment_id']
    ?? null;


$appointmentId =
    filter_var(
        $rawAppointmentId,
        FILTER_VALIDATE_INT
    );


if (
    $appointmentId === false ||
    $appointmentId === null ||
    $appointmentId < 1
) {

    redirect(
        'appointments.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD APPOINTMENT
|--------------------------------------------------------------------------
|
| Security rule:
|
| The authenticated doctor can only access an appointment where
| appointments.doctor_id matches the current logged-in doctor.
|
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $pdo->prepare(
            "SELECT
                a.id,
                a.user_id AS patient_user_id,
                a.doctor_id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.status,
                p.fullname AS patient_name,
                d.fullname AS doctor_name
             FROM appointments a
             INNER JOIN users p
                ON p.id = a.user_id
             INNER JOIN users d
                ON d.id = a.doctor_id
             WHERE
                a.id = ?
                AND a.doctor_id = ?
             LIMIT 1"
        );


    $stmt->execute([
        $appointmentId,
        $doctorId
    ]);


    $appointment =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | APPOINTMENT NOT FOUND / ACCESS DENIED
    |--------------------------------------------------------------------------
    */

    if (
        !$appointment
    ) {

        http_response_code(404);

        exit(
            'Appointment not found or access denied.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXTRA OWNERSHIP VERIFICATION
    |--------------------------------------------------------------------------
    */

    if (
        (int) (
            $appointment['doctor_id']
            ?? 0
        ) !== $doctorId
    ) {

        error_log(
            'MediCare clinical record ownership mismatch. ' .
            'Doctor ID: ' . $doctorId .
            ', Appointment ID: ' . $appointmentId
        );


        http_response_code(403);

        exit(
            'You are not authorized to access this appointment.'
        );
    }


} catch (PDOException $e) {

    error_log(
        'MediCare clinical appointment lookup failed: ' .
        $e->getMessage()
    );


    http_response_code(503);

    exit(
        'Unable to load the appointment right now.'
    );
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT STATUS CHECK
|--------------------------------------------------------------------------
*/

$appointmentStatus =
    (string) (
        $appointment['status']
        ?? ''
    );


if (
    $appointmentStatus !== 'Confirmed'
) {

    $error =
        'Clinical notes can only be opened for a confirmed appointment.';
}


/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {

    /*
    |--------------------------------------------------------------------------
    | VERIFY CSRF
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

        $diagnosis =
            trim(
                post_string(
                    'diagnosis',
                    2000
                )
            );


        $prescription =
            trim(
                post_string(
                    'prescription',
                    5000
                )
            );


        $notes =
            trim(
                post_string(
                    'notes',
                    10000
                )
            );


        /*
        |--------------------------------------------------------------------------
        | VERIFY POST APPOINTMENT ID
        |--------------------------------------------------------------------------
        */

        $postedAppointmentId =
            filter_var(
                $_POST['appointment_id']
                ?? null,
                FILTER_VALIDATE_INT
            );


        if (
            $postedAppointmentId === false ||
            $postedAppointmentId === null ||
            $postedAppointmentId < 1
        ) {

            $error =
                'Invalid appointment request.';

        } elseif (
            $postedAppointmentId !== $appointmentId
        ) {

            $error =
                'The appointment request could not be verified.';
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $error === '' &&
            $diagnosis === ''
        ) {

            $error =
                'A primary diagnosis is required.';
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE CLINICAL RECORD
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            try {

                /*
                |--------------------------------------------------------------------------
                | START TRANSACTION
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | LOCK APPOINTMENT
                |--------------------------------------------------------------------------
                |
                | Re-check doctor ownership and status inside the
                | transaction before writing the clinical record.
                |--------------------------------------------------------------------------
                */

                $lock =
                    $pdo->prepare(
                        "SELECT
                            id,
                            user_id,
                            doctor_id,
                            status
                         FROM appointments
                         WHERE
                            id = ?
                            AND doctor_id = ?
                         LIMIT 1
                         FOR UPDATE"
                    );


                $lock->execute([
                    $appointmentId,
                    $doctorId
                ]);


                $lockedAppointment =
                    $lock->fetch(
                        PDO::FETCH_ASSOC
                    );


                /*
                |--------------------------------------------------------------------------
                | RE-VERIFY APPOINTMENT
                |--------------------------------------------------------------------------
                */

                if (
                    !$lockedAppointment
                ) {

                    throw new RuntimeException(
                        'This appointment no longer belongs to the current doctor.'
                    );
                }


                if (
                    (int) (
                        $lockedAppointment['doctor_id']
                        ?? 0
                    ) !== $doctorId
                ) {

                    throw new RuntimeException(
                        'You are not authorized to create clinical notes for this appointment.'
                    );
                }


                if (
                    (string) (
                        $lockedAppointment['status']
                        ?? ''
                    ) !== 'Confirmed'
                ) {

                    throw new RuntimeException(
                        'This appointment is no longer available for clinical notes.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | VERIFY PATIENT LINK
                |--------------------------------------------------------------------------
                */

                $patientUserId =
                    (int) (
                        $lockedAppointment['user_id']
                        ?? 0
                    );


                if (
                    $patientUserId < 1
                ) {

                    throw new RuntimeException(
                        'This appointment is not linked to a valid patient account.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | PREVENT DUPLICATE CLINICAL RECORD
                |--------------------------------------------------------------------------
                */

                $existingRecordStmt =
                    $pdo->prepare(
                        "SELECT
                            id
                         FROM medical_records
                         WHERE appointment_id = ?
                         LIMIT 1
                         FOR UPDATE"
                    );


                $existingRecordStmt->execute([
                    $appointmentId
                ]);


                $existingRecord =
                    $existingRecordStmt->fetchColumn();


                if (
                    $existingRecord !== false &&
                    $existingRecord !== null
                ) {

                    throw new RuntimeException(
                        'A clinical record has already been created for this appointment.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | BUILD CLINICAL NOTES
                |--------------------------------------------------------------------------
                |
                | The medical_records table currently does not have
                | a separate diagnosis column, so the diagnosis is
                | stored inside the notes field.
                |--------------------------------------------------------------------------
                */

                $clinicalNotes =
                    "Diagnosis:\n" .
                    $diagnosis;


                if (
                    $notes !== ''
                ) {

                    $clinicalNotes .=
                        "\n\nClinical Notes:\n" .
                        $notes;
                }


                /*
                |--------------------------------------------------------------------------
                | INSERT MEDICAL RECORD
                |--------------------------------------------------------------------------
                */

                $insert =
                    $pdo->prepare(
                        "INSERT INTO medical_records
                        (
                            appointment_id,
                            doctor_id,
                            notes,
                            prescription
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?
                        )"
                    );


                $insert->execute([
                    $appointmentId,
                    $doctorId,
                    $clinicalNotes,
                    $prescription !== ''
                        ? $prescription
                        : null
                ]);


                /*
                |--------------------------------------------------------------------------
                | VERIFY INSERT
                |--------------------------------------------------------------------------
                */

                if (
                    $insert->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        'The clinical record could not be created.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | COMPLETE APPOINTMENT
                |--------------------------------------------------------------------------
                */

                $update =
                    $pdo->prepare(
                        "UPDATE appointments
                         SET status = 'Completed'
                         WHERE
                            id = ?
                            AND doctor_id = ?
                            AND status = 'Confirmed'"
                    );


                $update->execute([
                    $appointmentId,
                    $doctorId
                ]);


                if (
                    $update->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        'The appointment could not be completed.'
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
                | SUCCESS
                |--------------------------------------------------------------------------
                */

                $success =
                    'Clinical record saved and the appointment has been completed.';


                /*
                |--------------------------------------------------------------------------
                | CLEAR FORM
                |--------------------------------------------------------------------------
                */

                $diagnosis = '';

                $prescription = '';

                $notes = '';


                /*
                |--------------------------------------------------------------------------
                | REFRESH DISPLAY STATUS
                |--------------------------------------------------------------------------
                */

                $appointment['status'] =
                    'Completed';


            } catch (RuntimeException $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                error_log(
                    'MediCare clinical record runtime error: ' .
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
                    'MediCare clinical record save failed: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to save the clinical record right now.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$patientName =
    (string) (
        $appointment['patient_name']
        ?? 'Patient'
    );


$doctorName =
    (string) (
        $appointment['doctor_name']
        ?? 'Doctor'
    );


$appointmentDate =
    safe_date(
        (string) (
            $appointment['appointment_date']
            ?? ''
        )
    );


$appointmentTime =
    safe_time(
        (string) (
            $appointment['appointment_time']
            ?? ''
        )
    );


$currentDisplayStatus =
    (string) (
        $appointment['status']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| CURRENT DOCTOR DISPLAY
|--------------------------------------------------------------------------
*/

$displayName =
    (string) (
        $_SESSION['fullname']
        ?? $doctorName
        ?? 'Doctor'
    );


$email =
    (string) (
        $_SESSION['email']
        ?? ''
    );


$currentRole =
    current_role();


/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$userInitials = '';


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
        'D';
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
        content="Create a MediCare clinical record for a confirmed appointment."
    >


    <title>
        Clinical Notes | MediCare
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

            --bg-secondary:
                #05283f;

            --bg-tertiary:
                #06283f;

            --panel:
                #082f49;

            --panel-dark:
                #06283f;

            --panel-solid:
                #082f49;

            --primary:
                #10c7b0;

            --primary-dark:
                #0a9e90;

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

            --text-strong:
                #edf6f9;

            --text-soft:
                #c8d9e1;

            --muted:
                #8fa8b8;

            --muted-dark:
                #678392;

            --muted-darker:
                #607b8a;

            --sidebar-top:
                #05283f;

            --sidebar-bottom:
                #031b2d;

            --topbar-bg:
                rgba(
                    3,
                    27,
                    44,
                    .86
                );

            --input-bg:
                rgba(
                    2,
                    22,
                    36,
                    .62
                );

            --input-bg-focus:
                rgba(
                    2,
                    22,
                    36,
                    .80
                );

            --card-bg:
                rgba(
                    255,
                    255,
                    255,
                    .025
                );

            --hover-bg:
                rgba(
                    16,
                    199,
                    176,
                    .025
                );

            --soft-bg:
                rgba(
                    255,
                    255,
                    255,
                    .03
                );

            --soft-bg-strong:
                rgba(
                    255,
                    255,
                    255,
                    .045
                );

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

            --sidebar-width:
                255px;

            --shadow:
                15px 0 45px
                rgba(
                    0,
                    0,
                    0,
                    .18
                );

            --hero-start:
                #073f51;

            --hero-middle:
                #072e42;

            --hero-end:
                #082940;

            --hero-border:
                rgba(
                    16,
                    199,
                    176,
                    .15
                );

            --hero-accent:
                #10c7b0;

            --hero-line:
                #10c7b0;

            --hero-text:
                #b7ced8;

            --overlay:
                rgba(
                    0,
                    8,
                    16,
                    .65
                );

            --success-text:
                #74e4aa;

            --danger-text:
                #ff989e;

            --body-transition:
                background-color .25s ease,
                color .25s ease;
        }


        /* =========================================================
           LIGHT THEME
        ========================================================== */

        html[data-theme="light"] {

            --bg:
                #eef4f7;

            --bg-secondary:
                #ffffff;

            --bg-tertiary:
                #f3f8fa;

            --panel:
                #ffffff;

            --panel-dark:
                #f5f9fb;

            --panel-solid:
                #ffffff;

            --primary:
                #079f8d;

            --primary-dark:
                #087c70;

            --blue:
                #2478d9;

            --green:
                #24995d;

            --orange:
                #bf7900;

            --red:
                #c9434b;

            --purple:
                #7552ca;

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
                #78909d;

            --muted-darker:
                #8499a5;

            --sidebar-top:
                #ffffff;

            --sidebar-bottom:
                #eef4f7;

            --topbar-bg:
                rgba(
                    255,
                    255,
                    255,
                    .90
                );

            --input-bg:
                #f7fafb;

            --input-bg-focus:
                #ffffff;

            --card-bg:
                #f7fafb;

            --hover-bg:
                rgba(
                    7,
                    159,
                    141,
                    .045
                );

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

            --sidebar-width:
                255px;

            --shadow:
                12px 0 35px
                rgba(
                    20,
                    54,
                    70,
                    .10
                );

            --hero-start:
                #edf8f7;

            --hero-middle:
                #e7f0f8;

            --hero-end:
                #e6f4f2;

            --hero-border:
                rgba(
                    7,
                    159,
                    141,
                    .16
                );

            --hero-accent:
                #087c70;

            --hero-line:
                #079f8d;

            --hero-text:
                #536c7b;

            --overlay:
                rgba(
                    14,
                    34,
                    45,
                    .38
                );

            --success-text:
                #287d50;

            --danger-text:
                #bd414a;
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
                var(--text);

            background:

                radial-gradient(
                    circle at 12% 10%,
                    rgba(
                        16,
                        199,
                        176,
                        .08
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 88% 80%,
                    rgba(
                        44,
                        140,
                        255,
                        .06
                    ),
                    transparent 27%
                ),

                var(--bg);

            overflow-x:
                hidden;

            transition:
                var(--body-transition);
        }


        html[data-theme="light"] body {

            background:

                radial-gradient(
                    circle at 12% 10%,
                    rgba(
                        7,
                        159,
                        141,
                        .08
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 88% 80%,
                    rgba(
                        36,
                        120,
                        217,
                        .06
                    ),
                    transparent 27%
                ),

                var(--bg);
        }


        a {

            color:
                inherit;

            text-decoration:
                none;
        }


        button,
        input,
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
                var(--shadow);

            transition:
                transform .25s ease,
                background-color .25s ease,
                border-color .25s ease;
        }


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

            width:
                145px;

            max-width:
                100%;

            max-height:
                58px;

            height:
                auto;

            display:
                block;

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
                #648191;

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
                var(--text-strong);

            background:
                var(--soft-bg-strong);

            transform:
                translateX(2px);
        }


        .nav a.active {

            color:
                var(--text-strong);

            background:

                linear-gradient(
                    135deg,
                    rgba(
                        16,
                        199,
                        176,
                        .18
                    ),
                    rgba(
                        16,
                        199,
                        176,
                        .06
                    )
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .12
                );
        }


        html[data-theme="light"]
        .nav a.active {

            background:

                linear-gradient(
                    135deg,
                    rgba(
                        7,
                        159,
                        141,
                        .14
                    ),
                    rgba(
                        7,
                        159,
                        141,
                        .045
                    )
                );

            border-color:
                rgba(
                    7,
                    159,
                    141,
                    .14
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
                var(--soft-bg);
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
                11px;

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
                var(--text-strong);

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
                5px;

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
                    .07
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .15
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
                    .15
                );

            border-radius:
                10px;

            background:
                rgba(
                    255,
                    114,
                    121,
                    .04
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
                    .10
                );

            color:
                #ffb5b8;
        }


        /* =========================================================
           THEME TOGGLE
        ========================================================== */

        .theme-toggle {

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
                    .25
                );

            transform:
                translateY(-1px);
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
                var(--text-strong);

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
                var(--soft-bg);

            color:
                var(--muted);

            transition:
                .2s ease;
        }


        .top-icon:hover {

            color:
                var(--primary);

            transform:
                translateY(-1px);
        }


        .menu-button {

            display:
                none;

            cursor:
                pointer;
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
                29px 31px;

            border:
                1px solid
                var(--hero-border);

            border-radius:
                19px;

            background:

                linear-gradient(
                    135deg,
                    var(--hero-start),
                    var(--hero-middle) 55%,
                    var(--hero-end)
                );
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

            right:
                -120px;

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
                    .14
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
                var(--hero-accent);

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
                var(--hero-line);
        }


        .hero h1 {

            color:
                var(--text-strong);

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
                730px;

            margin-top:
                10px;

            color:
                var(--hero-text);

            font-size:
                10px;

            line-height:
                1.7;
        }


        /* =========================================================
           PATIENT BANNER
        ========================================================== */

        .patient-banner {

            display:
                grid;

            grid-template-columns:
                1fr
                auto;

            gap:
                7px 15px;

            padding:
                18px;

            margin-bottom:
                22px;

            border:
                1px solid
                var(--border);

            border-radius:
                13px;

            background:
                var(--card-bg);
        }


        .patient-banner strong {

            color:
                var(--text-strong);

            font-size:
                17px;
        }


        .patient-banner span {

            color:
                var(--muted);

            font-size:
                10px;

            line-height:
                1.5;
        }


        .patient-banner .appointment-status {

            grid-row:
                1 / 3;

            grid-column:
                2;

            align-self:
                center;
        }


        .status-confirmed,
        .status-completed {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                6px 10px;

            border-radius:
                999px;

            font-size:
                8px;

            font-weight:
                800;
        }


        .status-confirmed {

            color:
                #72e5ac;

            background:
                rgba(
                    85,
                    216,
                    144,
                    .08
                );

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    .12
                );
        }


        .status-completed {

            color:
                #78bcff;

            background:
                rgba(
                    44,
                    140,
                    255,
                    .08
                );

            border:
                1px solid
                rgba(
                    44,
                    140,
                    255,
                    .12
                );
        }


        html[data-theme="light"]
        .status-confirmed {

            color:
                #287d50;

            background:
                rgba(
                    36,
                    153,
                    93,
                    .08
                );

            border-color:
                rgba(
                    36,
                    153,
                    93,
                    .15
                );
        }


        html[data-theme="light"]
        .status-completed {

            color:
                #216ebd;

            background:
                rgba(
                    36,
                    120,
                    217,
                    .08
                );

            border-color:
                rgba(
                    36,
                    120,
                    217,
                    .15
                );
        }


        /* =========================================================
           ALERTS
        ========================================================== */

        .alert {

            padding:
                13px 15px;

            margin-bottom:
                20px;

            border-radius:
                10px;

            font-size:
                10px;

            line-height:
                1.6;
        }


        .alert-success {

            background:
                rgba(
                    85,
                    216,
                    144,
                    .07
                );

            color:
                var(--success-text);

            border:
                1px solid
                rgba(
                    85,
                    216,
                    144,
                    .13
                );
        }


        .alert-danger {

            background:
                rgba(
                    255,
                    114,
                    121,
                    .07
                );

            color:
                var(--danger-text);

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    .13
                );
        }


        /* =========================================================
           FORM
        ========================================================== */

        .clinical-form {

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
                20px 18px;
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
                var(--text-soft);

            font-size:
                10px;

            font-weight:
                800;
        }


        .required {

            color:
                var(--red);
        }


        .field input,
        .field textarea {

            width:
                100%;

            border:
                1px solid
                var(--border);

            border-radius:
                9px;

            background:
                var(--input-bg);

            color:
                var(--text);

            outline:
                none;

            font-size:
                10px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                background-color .25s ease,
                color .25s ease;
        }


        .field input {

            min-height:
                47px;

            padding:
                10px 13px;
        }


        .field textarea {

            min-height:
                120px;

            padding:
                12px 13px;

            resize:
                vertical;

            line-height:
                1.6;
        }


        .field input::placeholder,
        .field textarea::placeholder {

            color:
                var(--muted-darker);
        }


        .field input:focus,
        .field textarea:focus {

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
                var(--input-bg-focus);
        }


        .field-help {

            margin-top:
                6px;

            color:
                var(--muted-dark);

            font-size:
                8px;

            line-height:
                1.6;
        }


        /* =========================================================
           ACTIONS
        ========================================================== */

        .form-actions {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding-top:
                22px;

            margin-top:
                4px;

            border-top:
                1px solid
                var(--border);
        }


        .btn {

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


        .btn-secondary {

            background:
                var(--soft-bg);

            border:
                1px solid
                var(--border);

            color:
                var(--muted);
        }


        .btn-secondary:hover {

            color:
                var(--text-strong);

            background:
                var(--soft-bg-strong);
        }


        .btn-primary {

            border:
                none;

            background:

                linear-gradient(
                    135deg,
                    var(--primary),
                    #10b4d3
                );

            color:
                white;

            cursor:
                pointer;

            box-shadow:
                0 10px 25px
                rgba(
                    16,
                    199,
                    176,
                    .10
                );
        }


        .btn-primary:hover {

            background:

                linear-gradient(
                    135deg,
                    var(--primary),
                    #10b4d3
                );

            transform:
                translateY(-1px);

            box-shadow:
                0 14px 30px
                rgba(
                    16,
                    199,
                    176,
                    .16
                );
        }


        .security-note {

            margin-top:
                18px;

            padding:
                13px 15px;

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .10
                );

            border-radius:
                10px;

            background:
                rgba(
                    16,
                    199,
                    176,
                    .03
                );

            color:
                var(--muted-dark);

            font-size:
                8px;

            line-height:
                1.7;
        }


        .security-note strong {

            color:
                var(--text-soft);
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
                var(--overlay);

            backdrop-filter:
                blur(3px);
        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (
            max-width: 900px
        ) {

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


        @media (
            max-width: 700px
        ) {

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


            .patient-banner {

                grid-template-columns:
                    1fr;
            }


            .patient-banner .appointment-status {

                grid-row:
                    auto;

                grid-column:
                    auto;

                justify-self:
                    start;
            }


            .clinical-form {

                grid-template-columns:
                    1fr;
            }


            .field.full {

                grid-column:
                    auto;
            }


            .form-actions {

                flex-direction:
                    column;

                align-items:
                    stretch;
            }


            .btn {

                width:
                    100%;
            }

        }


        @media (
            max-width: 460px
        ) {

            .top-icon {

                display:
                    none;
            }


            .hero {

                padding:
                    24px 19px;
            }


            .patient-banner {

                padding:
                    16px;
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


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


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

            <i class="fa-solid fa-chart-pie"></i>

            <span>
                Dashboard
            </span>

        </a>


        <a
            href="appointments.php"
            class="active"
        >

            <i class="fa-regular fa-calendar-check"></i>

            <span>
                Appointments
            </span>

        </a>


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


        <?php if (
            $currentRole === 'Admin'
        ): ?>

            <a href="manage_users.php">

                <i class="fa-solid fa-user-gear"></i>

                <span>
                    Manage Users
                </span>

            </a>

        <?php endif; ?>


        <a href="profile.php">

            <i class="fa-solid fa-user-doctor"></i>

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

            <i class="fa-solid fa-user-doctor"></i>

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


    <!-- =======================================================
         TOPBAR
    ======================================================== -->

    <header class="topbar">


        <div class="page-title">

            <small>
                MediCare Doctor Portal
            </small>

            <h2>
                Clinical Notes
            </h2>

        </div>


        <div class="top-actions">


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


            <button
                type="button"
                class="menu-button"
                id="menuButton"
                aria-label="Open menu"
            >

                <i class="fa-solid fa-bars"></i>

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

                <i class="fa-regular fa-calendar-check"></i>

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


            <div class="hero-copy">


                <div class="eyebrow">

                    Clinical Documentation

                </div>


                <h1>

                    Clinical Notes

                </h1>


                <p>

                    Record the diagnosis, prescription and
                    relevant clinical findings for this patient's
                    confirmed appointment.

                </p>


            </div>


        </section>


        <!-- =================================================
             PATIENT / APPOINTMENT
        ================================================== -->

        <div class="patient-banner">


            <div>

                <strong>

                    <?= e(
                        $patientName
                    ) ?>

                </strong>

            </div>


            <span>

                Dr.
                <?= e(
                    $doctorName
                ) ?>

            </span>


            <span>


                <?php if (
                    $appointmentDate !== null
                ): ?>

                    <?= e(
                        $appointmentDate
                            ->format(
                                'F j, Y'
                            )
                    ) ?>

                <?php else: ?>

                    Date unavailable

                <?php endif; ?>


                &nbsp;•&nbsp;


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


            <div class="appointment-status">


                <?php if (
                    $currentDisplayStatus === 'Completed'
                ): ?>

                    <span class="status-completed">

                        <?= e(
                            $currentDisplayStatus
                        ) ?>

                    </span>


                <?php else: ?>

                    <span class="status-confirmed">

                        <?= e(
                            $currentDisplayStatus
                        ) ?>

                    </span>

                <?php endif; ?>


            </div>


        </div>


        <!-- =================================================
             SUCCESS
        ================================================== -->

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

                <?= e(
                    $success
                ) ?>

            </div>


            <div class="form-actions">


                <a
                    class="btn btn-secondary"
                    href="appointments.php"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    Back to Appointments

                </a>


            </div>


        <?php endif; ?>


        <!-- =================================================
             ERROR
        ================================================== -->

        <?php if (
            $error !== ''
        ): ?>


            <div
                class="alert alert-danger"
                role="alert"
            >

                <i
                    class="fa-solid fa-circle-exclamation"
                ></i>

                <?= e(
                    $error
                ) ?>

            </div>


        <?php endif; ?>


        <!-- =================================================
             CLINICAL FORM
        ================================================== -->

        <?php if (
            $success === '' &&
            $currentDisplayStatus === 'Confirmed'
        ): ?>


            <section class="patient-banner">


                <div style="grid-column: 1 / -1;">

                    <strong>
                        Clinical Record
                    </strong>

                    <span
                        style="
                            display:block;
                            margin-top:5px;
                        "
                    >
                        Fields marked with * are required.
                    </span>

                </div>


            </section>


            <form
                method="POST"
                action="add_record.php?appointment_id=<?= (int) $appointmentId ?>"
                class="clinical-form"
                autocomplete="off"
            >


                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= e(
                        csrf_token()
                    ) ?>"
                >


                <input
                    type="hidden"
                    name="appointment_id"
                    value="<?= (int) $appointmentId ?>"
                >


                <!-- =================================================
                     DIAGNOSIS
                ================================================== -->

                <div class="field full">


                    <label for="diagnosis">

                        Primary Diagnosis

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        id="diagnosis"
                        name="diagnosis"
                        value="<?= e(
                            $diagnosis
                        ) ?>"
                        maxlength="2000"
                        placeholder="Enter the primary diagnosis"
                        required
                    >


                    <div class="field-help">

                        The diagnosis is stored inside the clinical
                        notes because the current database does not
                        have a separate diagnosis column.

                    </div>


                </div>


                <!-- =================================================
                     PRESCRIPTION
                ================================================== -->

                <div class="field full">


                    <label for="prescription">

                        Prescription / Medication

                    </label>


                    <textarea
                        id="prescription"
                        name="prescription"
                        maxlength="5000"
                        rows="5"
                        placeholder="Enter prescribed medication, dosage and instructions..."
                    ><?= e(
                        $prescription
                    ) ?></textarea>


                </div>


                <!-- =================================================
                     CLINICAL NOTES
                ================================================== -->

                <div class="field full">


                    <label for="notes">

                        Additional Clinical Notes

                    </label>


                    <textarea
                        id="notes"
                        name="notes"
                        maxlength="10000"
                        rows="7"
                        placeholder="Enter examination findings, treatment details, observations and other relevant clinical notes..."
                    ><?= e(
                        $notes
                    ) ?></textarea>


                </div>


                <!-- =================================================
                     ACTIONS
                ================================================== -->

                <div class="form-actions field full">


                    <a
                        class="btn btn-secondary"
                        href="appointments.php"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        Cancel

                    </a>


                    <button
                        class="btn btn-primary"
                        type="submit"
                        id="saveClinicalRecordButton"
                    >

                        <i class="fa-solid fa-file-medical"></i>

                        Save Clinical Record

                    </button>


                </div>


            </form>


        <?php elseif (
            $success === ''
        ): ?>


            <div class="security-note">

                <i class="fa-solid fa-circle-info"></i>

                &nbsp;

                This appointment is no longer available for
                clinical notes because its status is:

                <strong>

                    <?= e(
                        $currentDisplayStatus
                    ) ?>

                </strong>

            </div>


        <?php endif; ?>


        <!-- =================================================
             SECURITY NOTE
        ================================================== -->

        <div class="security-note">

            <i class="fa-solid fa-shield-heart"></i>

            &nbsp;

            Clinical records can only be created by the assigned
            doctor for a confirmed appointment. Saving the record
            will complete the appointment and preserve the clinical
            history in MediCare.

        </div>


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


        const menuButton =
            document.getElementById(
                'menuButton'
            );


        const overlay =
            document.getElementById(
                'overlay'
            );


        const themeToggle =
            document.getElementById(
                'themeToggle'
            );


        const themeIcon =
            document.getElementById(
                'themeIcon'
            );


        const saveButton =
            document.getElementById(
                'saveClinicalRecordButton'
            );


        /*
        |--------------------------------------------------------------------------
        | THEME
        |--------------------------------------------------------------------------
        */

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

            } catch (error) {

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
        | MOBILE MENU
        |--------------------------------------------------------------------------
        */

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

        const clinicalForm =
            document.querySelector(
                '.clinical-form'
            );


        if (
            clinicalForm &&
            saveButton
        ) {

            clinicalForm.addEventListener(
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