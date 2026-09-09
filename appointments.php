<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| REQUIRE VALID ROLE
|--------------------------------------------------------------------------
*/

require_role(
    'Admin',
    'Doctor',
    'Patient'
);


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId =
    current_user_id();

$role =
    current_role();

$displayName =
    (string) (
        $_SESSION['fullname']
        ?? 'User'
    );


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$success = '';

$error = '';


/*
|--------------------------------------------------------------------------
| APPOINTMENT STATUS UPDATE
|--------------------------------------------------------------------------
| All status changes are POST-only, CSRF-protected, ownership-aware
| and transaction-safe.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['appointment_action'])
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
    | APPOINTMENT ID
    |--------------------------------------------------------------------------
    */

    $appointmentId =
        filter_var(
            $_POST['appointment_id'] ?? null,
            FILTER_VALIDATE_INT
        );


    /*
    |--------------------------------------------------------------------------
    | ACTION
    |--------------------------------------------------------------------------
    */

    $action =
        trim(
            (string) (
                $_POST['appointment_action']
                ?? ''
            )
        );


    $allowedActions = [
        'Confirm'  => 'Confirmed',
        'Complete' => 'Completed',
        'Cancel'   => 'Cancelled',
    ];


    /*
    |--------------------------------------------------------------------------
    | VALIDATE REQUEST
    |--------------------------------------------------------------------------
    */

    if (
        $appointmentId === false ||
        $appointmentId === null ||
        $appointmentId < 1 ||
        !isset(
            $allowedActions[$action]
        )
    ) {

        $error =
            'Invalid appointment action.';

    } else {

        $newStatus =
            $allowedActions[$action];


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LOCK APPOINTMENT
            |--------------------------------------------------------------------------
            */

            $checkStmt =
                $pdo->prepare(
                    "SELECT
                        a.id,
                        a.user_id,
                        a.doctor_id,
                        a.appointment_date,
                        a.appointment_time,
                        a.status,

                        p.fullname AS patient_name,
                        d.fullname AS doctor_name

                     FROM appointments a

                     LEFT JOIN users p
                        ON a.user_id = p.id

                     LEFT JOIN users d
                        ON a.doctor_id = d.id

                     WHERE a.id = ?

                     LIMIT 1

                     FOR UPDATE"
                );


            $checkStmt->execute([
                $appointmentId
            ]);


            $appointment =
                $checkStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
            |--------------------------------------------------------------------------
            | NOT FOUND
            |--------------------------------------------------------------------------
            */

            if (!$appointment) {

                throw new RuntimeException(
                    'Appointment not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CURRENT STATUS
            |--------------------------------------------------------------------------
            */

            $currentStatus =
                trim(
                    (string) (
                        $appointment['status']
                        ?? ''
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | AUTHORIZATION
            |--------------------------------------------------------------------------
            */

            $authorized = false;


            if (
                $role === 'Admin'
            ) {

                /*
                |------------------------------------------------------------------
                | Admin may manage all appointments.
                |------------------------------------------------------------------
                */

                $authorized =
                    true;

            } elseif (
                $role === 'Doctor'
            ) {

                /*
                |------------------------------------------------------------------
                | Doctor may manage only assigned appointments.
                |------------------------------------------------------------------
                */

                $authorized =
                    (int) (
                        $appointment['doctor_id']
                        ?? 0
                    ) === $userId;

            } elseif (
                $role === 'Patient'
            ) {

                /*
                |------------------------------------------------------------------
                | Patient may only cancel their own pending appointment.
                |------------------------------------------------------------------
                */

                $authorized =
                    $action === 'Cancel' &&
                    (int) (
                        $appointment['user_id']
                        ?? 0
                    ) === $userId;
            }


            if (!$authorized) {

                throw new RuntimeException(
                    'You are not authorized to update this appointment.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE STATUS TRANSITION
            |--------------------------------------------------------------------------
            */

            $transitionAllowed =
                false;


            if (
                $action === 'Confirm' &&
                $currentStatus === 'Pending'
            ) {

                $transitionAllowed =
                    true;

            } elseif (
                $action === 'Complete' &&
                $currentStatus === 'Confirmed'
            ) {

                $transitionAllowed =
                    true;

            } elseif (
                $action === 'Cancel' &&
                (
                    $currentStatus === 'Pending' ||
                    $currentStatus === 'Confirmed'
                )
            ) {

                $transitionAllowed =
                    true;
            }


            if (!$transitionAllowed) {

                throw new RuntimeException(
                    'This appointment cannot be changed from its current status.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CONFIRMATION VALIDATION
            |--------------------------------------------------------------------------
            */

            if (
                $action === 'Confirm'
            ) {

                /*
                |------------------------------------------------------------------
                | A doctor must be assigned before confirmation.
                |------------------------------------------------------------------
                */

                $assignedDoctorId =
                    (int) (
                        $appointment['doctor_id']
                        ?? 0
                    );


                if (
                    $assignedDoctorId < 1
                ) {

                    throw new RuntimeException(
                        'This appointment cannot be confirmed because no doctor is assigned.'
                    );
                }


                /*
                |------------------------------------------------------------------
                | DOUBLE-BOOKING CHECK
                |------------------------------------------------------------------
                | Check for another active appointment occupying the same
                | doctor/date/time slot.
                |------------------------------------------------------------------
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
                            AND id <> ?
                         LIMIT 1
                         FOR UPDATE"
                    );


                $conflictStmt->execute([
                    $assignedDoctorId,
                    $appointment['appointment_date'],
                    $appointment['appointment_time'],
                    $appointmentId
                ]);


                $conflictId =
                    $conflictStmt->fetchColumn();


                if (
                    $conflictId !== false
                ) {

                    throw new RuntimeException(
                        'This doctor already has another active appointment at the selected date and time.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE APPOINTMENT
            |--------------------------------------------------------------------------
            */

            if (
                $role === 'Admin'
            ) {

                $updateStmt =
                    $pdo->prepare(
                        "UPDATE appointments
                         SET status = ?
                         WHERE
                            id = ?
                            AND status = ?"
                    );


                $updateStmt->execute([
                    $newStatus,
                    $appointmentId,
                    $currentStatus
                ]);

            } elseif (
                $role === 'Doctor'
            ) {

                $updateStmt =
                    $pdo->prepare(
                        "UPDATE appointments
                         SET status = ?
                         WHERE
                            id = ?
                            AND doctor_id = ?
                            AND status = ?"
                    );


                $updateStmt->execute([
                    $newStatus,
                    $appointmentId,
                    $userId,
                    $currentStatus
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | PATIENT
                |--------------------------------------------------------------------------
                */

                $updateStmt =
                    $pdo->prepare(
                        "UPDATE appointments
                         SET status = 'Cancelled'
                         WHERE
                            id = ?
                            AND user_id = ?
                            AND status = 'Pending'"
                    );


                $updateStmt->execute([
                    $appointmentId,
                    $userId
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY UPDATE
            |--------------------------------------------------------------------------
            */

            if (
                $updateStmt->rowCount() !== 1
            ) {

                throw new RuntimeException(
                    'The appointment could not be updated because its status may have changed.'
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

            if (
                $role === 'Patient'
            ) {

                $success =
                    'Appointment cancelled successfully.';

            } else {

                $success =
                    "Appointment marked as {$newStatus}.";
            }


        } catch (
            RuntimeException $e
        ) {

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

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | MYSQL UNIQUE CONSTRAINT
            |--------------------------------------------------------------------------
            */

            $mysqlErrorCode =
                (int) (
                    $e->errorInfo[1]
                    ?? $e->getCode()
                );


            if (
                $mysqlErrorCode === 1062
            ) {

                $error =
                    'This appointment cannot be confirmed because the selected doctor already has an active appointment at this date and time.';

            } else {

                error_log(
                    'MediCare appointment update error: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to update the appointment right now.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$searchQuery =
    trim(
        (string) (
            $_GET['search'] ?? ''
        )
    );


$searchQuery =
    mb_substr(
        $searchQuery,
        0,
        100
    );


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

$statusFilter =
    trim(
        (string) (
            $_GET['status_filter'] ?? ''
        )
    );


$allowedStatuses = [
    '',
    'Pending',
    'Confirmed',
    'Completed',
    'Cancelled',
];


if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $statusFilter =
        '';
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$limit =
    10;


$pageValue =
    filter_input(
        INPUT_GET,
        'page',
        FILTER_VALIDATE_INT
    );


$page =
    (
        $pageValue !== false &&
        $pageValue !== null &&
        $pageValue > 0
    )
        ? $pageValue
        : 1;


$offset =
    ($page - 1) *
    $limit;


/*
|--------------------------------------------------------------------------
| WHERE CLAUSES
|--------------------------------------------------------------------------
*/

$whereClauses = [];

$params = [];


/*
|--------------------------------------------------------------------------
| ROLE SCOPE
|--------------------------------------------------------------------------
*/

if (
    $role === 'Doctor'
) {

    $whereClauses[] =
        'a.doctor_id = ?';

    $params[] =
        $userId;

} elseif (
    $role === 'Patient'
) {

    $whereClauses[] =
        'a.user_id = ?';

    $params[] =
        $userId;
}


/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
*/

if (
    $searchQuery !== ''
) {

    $whereClauses[] =
        '(
            p.fullname LIKE ?
            OR d.fullname LIKE ?
            OR a.reason LIKE ?
        )';


    $searchValue =
        '%' .
        $searchQuery .
        '%';


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if (
    $statusFilter !== ''
) {

    $whereClauses[] =
        'a.status = ?';

    $params[] =
        $statusFilter;
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql =
    '';


if (
    !empty($whereClauses)
) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $whereClauses
        );
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$totalRecords =
    0;

$totalPages =
    1;

$appointments =
    [];

$pendingCount =
    0;

$confirmedCount =
    0;

$completedCount =
    0;

$cancelledCount =
    0;


/*
|--------------------------------------------------------------------------
| COUNT APPOINTMENTS
|--------------------------------------------------------------------------
*/

try {

    $countSql = "
        SELECT
            COUNT(*) AS total,

            COALESCE(
                SUM(
                    a.status = 'Pending'
                ),
                0
            ) AS pending,

            COALESCE(
                SUM(
                    a.status = 'Confirmed'
                ),
                0
            ) AS confirmed,

            COALESCE(
                SUM(
                    a.status = 'Completed'
                ),
                0
            ) AS completed,

            COALESCE(
                SUM(
                    a.status = 'Cancelled'
                ),
                0
            ) AS cancelled

        FROM appointments a

        LEFT JOIN users p
            ON a.user_id = p.id

        LEFT JOIN users d
            ON a.doctor_id = d.id

        {$whereSql}
    ";


    $countStmt =
        $pdo->prepare(
            $countSql
        );


    $countStmt->execute(
        $params
    );


    $countData =
        $countStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        $countData
    ) {

        $totalRecords =
            (int) (
                $countData['total']
                ?? 0
            );


        $pendingCount =
            (int) (
                $countData['pending']
                ?? 0
            );


        $confirmedCount =
            (int) (
                $countData['confirmed']
                ?? 0
            );


        $completedCount =
            (int) (
                $countData['completed']
                ?? 0
            );


        $cancelledCount =
            (int) (
                $countData['cancelled']
                ?? 0
            );
    }


    $totalPages =
        max(
            1,
            (int) ceil(
                $totalRecords /
                $limit
            )
        );


} catch (
    PDOException $e
) {

    error_log(
        'MediCare appointment count error: ' .
        $e->getMessage()
    );


    $error =
        'Unable to calculate appointment records.';


    $totalRecords =
        0;


    $totalPages =
        1;
}


/*
|--------------------------------------------------------------------------
| FIX PAGE
|--------------------------------------------------------------------------
*/

if (
    $page > $totalPages
) {

    $page =
        $totalPages;


    $offset =
        ($page - 1) *
        $limit;
}


/*
|--------------------------------------------------------------------------
| LOAD APPOINTMENTS
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            a.*,

            p.fullname AS patient_name,

            d.fullname AS doctor_name

        FROM appointments a

        LEFT JOIN users p
            ON a.user_id = p.id

        LEFT JOIN users d
            ON a.doctor_id = d.id

        {$whereSql}

        ORDER BY
            a.appointment_date DESC,
            a.appointment_time DESC,
            a.id DESC

        LIMIT {$limit}
        OFFSET {$offset}
    ";


    $stmt =
        $pdo->prepare(
            $sql
        );


    $stmt->execute(
        $params
    );


    $appointments =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    PDOException $e
) {

    error_log(
        'MediCare appointment loading error: ' .
        $e->getMessage()
    );


    $error =
        'Unable to load appointments.';
}


/*
|--------------------------------------------------------------------------
| PAGE URL HELPER
|--------------------------------------------------------------------------
*/

function appointmentPageUrl(
    int $pageNumber
): string {

    $query =
        $_GET;


    $query['page'] =
        max(
            1,
            $pageNumber
        );


    return
        'appointments.php?' .
        http_build_query(
            $query
        );
}


/*
|--------------------------------------------------------------------------
| STATUS CLASS HELPER
|--------------------------------------------------------------------------
*/

function appointmentStatusClass(
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
            'status-pending',

        'confirmed' =>
            'status-confirmed',

        'completed' =>
            'status-completed',

        'cancelled' =>
            'status-cancelled',

        default =>
            'status-default',
    };
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

    $userInitial =
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
        content="MediCare appointment management system."
    >


    <title>
        Appointments | MediCare
    </title>


    <!-- =========================================================
         MEDICARE THEME RESTORE
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
        href="https://fonts.googleapis.com"
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

            --panel-2:
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

            --heading-text:
                #edf6f9;

            --card-text:
                #dceaf0;

            --label-text:
                #809ba9;

            --caption-text:
                #66808f;

            --secondary-text:
                #9db7c3;

            --panel-description:
                #718b99;

            --nav-text:
                #9db4c1;

            --nav-muted:
                #76919f;

            --nav-title:
                #648191;

            --table-heading:
                #7993a1;

            --table-text:
                #a9bec8;

            --table-strong:
                #d8e6ec;

            --date-text:
                #deebf0;

            --date-muted:
                #66808f;

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

            --surface-soft:
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

            --surface-strong:
                rgba(
                    255,
                    255,
                    255,
                    0.03
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

            --stat-start:
                rgba(
                    8,
                    48,
                    71,
                    0.95
                );

            --stat-end:
                rgba(
                    5,
                    31,
                    49,
                    0.96
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

            --table-row-border:
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
           SAME UI / SAME STRUCTURE
        ========================================================== */

        html[data-theme="light"] {

            --bg:
                #f4f8fb;

            --sidebar:
                #ffffff;

            --panel:
                #ffffff;

            --panel-2:
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

            --heading-text:
                #183241;

            --card-text:
                #294454;

            --label-text:
                #607986;

            --caption-text:
                #718b97;

            --secondary-text:
                #607886;

            --panel-description:
                #718792;

            --nav-text:
                #536c79;

            --nav-muted:
                #708795;

            --nav-title:
                #78909d;

            --table-heading:
                #607987;

            --table-text:
                #617783;

            --table-strong:
                #294454;

            --date-text:
                #294454;

            --date-muted:
                #718b97;

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

            --surface-soft:
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

            --surface-strong:
                rgba(
                    18,
                    49,
                    67,
                    0.055
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

            --stat-start:
                rgba(
                    255,
                    255,
                    255,
                    1
                );

            --stat-end:
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

            --table-row-border:
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

            box-sizing:
                border-box;

            margin:
                0;

            padding:
                0;
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
        select {

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

            width:
                100%;

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
                    1280px,
                    100%
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

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            min-height:
                160px;

            margin-bottom:
                18px;

            padding:
                28px 32px;

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
                280px;

            height:
                280px;

            right:
                -100px;

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
                700px;

            margin-top:
                10px;

            color:
                var(--soft-text);

            font-size:
                11px;

            line-height:
                1.7;
        }


        .book-button {

            position:
                relative;

            z-index:
                2;

            flex-shrink:
                0;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            min-height:
                48px;

            padding:
                0 18px;

            border-radius:
                10px;

            color:
                white;

            background:

                linear-gradient(
                    135deg,
                    var(--primary),
                    #10b4d3
                );

            font-size:
                10px;

            font-weight:
                800;

            transition:
                transform .2s ease,
                opacity .2s ease;
        }


        .book-button:hover {

            transform:
                translateY(-1px);
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


        .alert-danger {

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
           STATS
        ========================================================== */

        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap:
                12px;

            margin-bottom:
                18px;
        }


        .stat-card {

            position:
                relative;

            min-height:
                112px;

            padding:
                19px;

            border:
                1px solid
                var(--border);

            border-radius:
                15px;

            background:

                linear-gradient(
                    145deg,
                    var(--stat-start),
                    var(--stat-end)
                );

            transition:
                background .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
        }


        .stat-icon {

            position:
                absolute;

            right:
                15px;

            top:
                15px;

            width:
                35px;

            height:
                35px;

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
        }


        .stat-card.pending
        .stat-icon {

            color:
                var(--orange);

            background:
                rgba(
                    255,
                    184,
                    77,
                    0.08
                );
        }


        .stat-card.confirmed
        .stat-icon {

            color:
                var(--green);

            background:
                rgba(
                    85,
                    216,
                    144,
                    0.08
                );
        }


        .stat-card.cancelled
        .stat-icon {

            color:
                var(--red);

            background:
                rgba(
                    255,
                    114,
                    121,
                    0.08
                );
        }


        .stat-label {

            color:
                var(--label-text);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .6px;

            text-transform:
                uppercase;
        }


        .stat-number {

            display:
                block;

            margin-top:
                9px;

            color:
                var(--white-text);

            font-size:
                27px;

            font-weight:
                800;
        }


        .stat-info {

            color:
                var(--caption-text);

            font-size:
                8px;
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


        /* =========================================================
           FILTER
        ========================================================== */

        .filter {

            padding:
                18px;

            border-bottom:
                1px solid
                var(--border);
        }


        .filter-form {

            display:
                grid;

            grid-template-columns:
                minmax(
                    240px,
                    1fr
                )
                180px
                95px
                75px;

            gap:
                9px;
        }


        .input,
        .select {

            width:
                100%;

            height:
                43px;

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
                background .25s ease,
                color .25s ease,
                box-shadow .2s ease;
        }


        .input::placeholder {

            color:
                var(--input-placeholder);
        }


        .input:focus,
        .select:focus {

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


        .search-button,
        .clear-button {

            height:
                43px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                9px;

            font-size:
                10px;

            font-weight:
                800;
        }


        .search-button {

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
        }


        .clear-button {

            border:
                1px solid
                var(--border);

            background:
                var(--surface-soft);

            color:
                var(--muted);
        }


        .clear-button:hover {

            color:
                var(--primary);
        }


        /* =========================================================
           TABLE
        ========================================================== */

        .table-wrapper {

            width:
                100%;

            overflow-x:
                auto;
        }


        .appointments-table {

            width:
                100%;

            min-width:
                920px;

            border-collapse:
                collapse;
        }


        .appointments-table th {

            padding:
                14px 15px;

            text-align:
                left;

            white-space:
                nowrap;

            color:
                var(--table-heading);

            background:
                var(--surface-soft);

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


        .appointments-table td {

            padding:
                15px;

            border-bottom:
                1px solid
                var(--table-row-border);

            color:
                var(--table-text);

            font-size:
                10px;

            vertical-align:
                middle;
        }


        .appointments-table tbody tr {

            transition:
                background .2s ease;
        }


        .appointments-table tbody tr:hover {

            background:
                rgba(
                    16,
                    199,
                    176,
                    0.025
                );
        }


        .appointments-table tbody tr:last-child td {

            border-bottom:
                none;
        }


        .date-main {

            display:
                block;

            color:
                var(--date-text);

            font-size:
                10px;

            font-weight:
                700;
        }


        .date-time {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--date-muted);

            font-size:
                8px;
        }


        .person {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;
        }


        .person-avatar {

            width:
                31px;

            height:
                31px;

            flex-shrink:
                0;

            display:
                grid;

            place-items:
                center;

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
                9px;
        }


        .person-name {

            color:
                var(--table-strong);

            font-size:
                10px;

            font-weight:
                600;
        }


        .reason {

            max-width:
                220px;

            color:
                var(--table-text);

            line-height:
                1.5;
        }


        .status {

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

            font-size:
                8px;

            font-weight:
                800;
        }


        .status::before {

            content:
                "";

            width:
                5px;

            height:
                5px;

            border-radius:
                50%;

            background:
                currentColor;
        }


        .status-pending {

            color:
                #ffca70;

            background:
                rgba(
                    255,
                    184,
                    77,
                    0.08
                );

            border:
                1px solid
                rgba(
                    255,
                    184,
                    77,
                    0.13
                );
        }


        .status-confirmed {

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
                    0.13
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
        }


        .status-cancelled {

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
                    0.13
                );
        }


        .status-default {

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


        .actions {

            display:
                flex;

            flex-wrap:
                wrap;

            align-items:
                center;

            gap:
                5px;
        }


        .action-form {

            margin:
                0;
        }


        .action-button {

            min-height:
                31px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                5px;

            padding:
                0 9px;

            border:
                none;

            border-radius:
                7px;

            color:
                white;

            font-size:
                8px;

            font-weight:
                800;

            cursor:
                pointer;

            transition:
                opacity .2s ease,
                transform .15s ease;
        }


        .action-button:hover {

            opacity:
                .86;

            transform:
                translateY(-1px);
        }


        .confirm {

            background:
                #17885b;
        }


        .complete {

            background:
                #236cb5;
        }


        .cancel {

            background:
                #b92d34;
        }


        .notes {

            background:
                #7650a9;
        }


        .no-action {

            color:
                #5f7886;

            font-size:
                8px;
        }


        .empty-state {

            padding:
                65px 20px !important;

            text-align:
                center;

            color:
                #67818f !important;
        }


        .empty-state i {

            display:
                block;

            margin-bottom:
                13px;

            color:
                #4e6b79;

            font-size:
                29px;
        }


        .empty-state strong {

            display:
                block;

            color:
                var(--table-strong);

            font-size:
                12px;

            margin-bottom:
                5px;
        }


        .empty-state span {

            font-size:
                9px;
        }


        /* =========================================================
           PAGINATION
        ========================================================== */

        .pagination {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                17px 18px;

            border-top:
                1px solid
                var(--border);
        }


        .pagination-info {

            color:
                var(--caption-text);

            font-size:
                9px;
        }


        .pagination-info strong {

            color:
                var(--table-text);
        }


        .pagination-links {

            display:
                flex;

            align-items:
                center;

            gap:
                5px;

            flex-wrap:
                wrap;
        }


        .page-link {

            min-width:
                32px;

            height:
                32px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                0 8px;

            border:
                1px solid
                var(--border);

            border-radius:
                7px;

            background:
                var(--surface-soft);

            color:
                var(--muted);

            font-size:
                8px;

            font-weight:
                800;

            transition:
                color .2s ease,
                border-color .2s ease,
                background .2s ease;
        }


        .page-link:hover {

            color:
                var(--primary);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    0.20
                );
        }


        .page-link.active {

            color:
                white;

            background:
                var(--primary);

            border-color:
                var(--primary);
        }


        .page-link.disabled {

            opacity:
                .35;

            pointer-events:
                none;
        }


        /* =========================================================
           MOBILE OVERLAY
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
        .stat-card,
        .panel,
        .user-box,
        .top-icon,
        .input,
        .select,
        .appointments-table th,
        .appointments-table td,
        .page-link {

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


            .filter-form {

                grid-template-columns:
                    minmax(
                        190px,
                        1fr
                    )
                    150px
                    90px
                    70px;
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


            .stats {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
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

                flex-direction:
                    column;

                align-items:
                    flex-start;

                padding:
                    25px 20px;
            }


            .book-button {

                width:
                    100%;
            }


            .filter-form {

                grid-template-columns:
                    1fr;
            }


            .search-button,
            .clear-button {

                width:
                    100%;
            }


            .pagination {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .pagination-links {

                width:
                    100%;
            }
        }


        @media (max-width: 460px) {

            .stats {

                grid-template-columns:
                    1fr;
            }


            /*
            |--------------------------------------------------------------------------
            | Keep theme toggle visible on small screens.
            |--------------------------------------------------------------------------
            */

            .top-icon {

                display:
                    none;
            }


            .theme-toggle {

                display:
                    grid;
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


        <?php if (
            $role === 'Patient'
        ): ?>

            <a href="book_appointment.php">

                <i
                    class="
                        fa-solid
                        fa-calendar-plus
                    "
                ></i>

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


            <a href="team.php">

                <i
                    class="
                        fa-solid
                        fa-user-doctor
                    "
                ></i>

                <span>
                    Medical Team
                </span>

            </a>

        <?php endif; ?>


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


        <?php if (
            $role === 'Patient'
        ): ?>

            <a href="patient_profile.php">

                <i class="fa-solid fa-id-card"></i>

                <span>
                    My Profile
                </span>

            </a>

        <?php else: ?>

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

        <?php endif; ?>


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

                &nbsp; Logout

            </button>

        </form>


    </div>


</aside>


<div
    class="overlay"
    id="overlay"
></div>


<main class="main">


    <header class="topbar">


        <div class="top-title">

            <small>
                MediCare Portal
            </small>

            <h2>
                Appointments
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


            <?php if (
                $role === 'Patient'
            ): ?>

                <a
                    href="book_appointment.php"
                    class="top-icon"
                    title="Book appointment"
                >

                    <i
                        class="
                            fa-solid
                            fa-calendar-plus
                        "
                    ></i>

                </a>

            <?php endif; ?>


        </div>


    </header>


    <div class="content">


        <section class="hero">


            <div class="hero-content">


                <div class="eyebrow">
                    Healthcare Scheduling
                </div>


                <h1>
                    Appointments
                </h1>


                <p>

                    Manage appointments, track visit status,
                    review schedules and keep your healthcare
                    activities organized.

                </p>


            </div>


            <?php if (
                $role === 'Patient'
            ): ?>

                <a
                    href="book_appointment.php"
                    class="book-button"
                >

                    <i
                        class="
                            fa-solid
                            fa-calendar-plus
                        "
                    ></i>

                    Book Appointment

                </a>

            <?php endif; ?>


        </section>


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
                    alert-danger
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


        <section class="stats">


            <article class="stat-card">

                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-calendar-days
                        "
                    ></i>

                </div>


                <span class="stat-label">
                    Total Records
                </span>


                <strong class="stat-number">
                    <?= $totalRecords ?>
                </strong>


                <span class="stat-info">
                    Matching appointments
                </span>

            </article>


            <article
                class="
                    stat-card
                    pending
                "
            >

                <div class="stat-icon">

                    <i
                        class="
                            fa-regular
                            fa-clock
                        "
                    ></i>

                </div>


                <span class="stat-label">
                    Pending
                </span>


                <strong class="stat-number">
                    <?= $pendingCount ?>
                </strong>


                <span class="stat-info">
                    Awaiting action
                </span>

            </article>


            <article
                class="
                    stat-card
                    confirmed
                "
            >

                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-circle-check
                        "
                    ></i>

                </div>


                <span class="stat-label">
                    Confirmed
                </span>


                <strong class="stat-number">
                    <?= $confirmedCount ?>
                </strong>


                <span class="stat-info">
                    Scheduled visits
                </span>

            </article>


            <article
                class="
                    stat-card
                    cancelled
                "
            >

                <div class="stat-icon">

                    <i
                        class="
                            fa-solid
                            fa-circle-xmark
                        "
                    ></i>

                </div>


                <span class="stat-label">
                    Cancelled
                </span>


                <strong class="stat-number">
                    <?= $cancelledCount ?>
                </strong>


                <span class="stat-info">
                    Cancelled requests
                </span>

            </article>


        </section>


        <section class="panel">


            <div class="filter">


                <form
                    method="GET"
                    action="appointments.php"
                    class="filter-form"
                >


                    <input
                        type="text"
                        name="search"
                        class="input"
                        placeholder="Search patient, doctor or reason..."
                        value="<?= e(
                            $searchQuery
                        ) ?>"
                        autocomplete="off"
                    >


                    <select
                        name="status_filter"
                        class="select"
                    >

                        <option value="">
                            All Statuses
                        </option>


                        <option
                            value="Pending"
                            <?= $statusFilter === 'Pending'
                                ? 'selected'
                                : '' ?>
                        >
                            Pending
                        </option>


                        <option
                            value="Confirmed"
                            <?= $statusFilter === 'Confirmed'
                                ? 'selected'
                                : '' ?>
                        >
                            Confirmed
                        </option>


                        <option
                            value="Completed"
                            <?= $statusFilter === 'Completed'
                                ? 'selected'
                                : '' ?>
                        >
                            Completed
                        </option>


                        <option
                            value="Cancelled"
                            <?= $statusFilter === 'Cancelled'
                                ? 'selected'
                                : '' ?>
                        >
                            Cancelled
                        </option>

                    </select>


                    <button
                        type="submit"
                        class="search-button"
                    >

                        <i
                            class="
                                fa-solid
                                fa-magnifying-glass
                            "
                        ></i>

                        &nbsp; Search

                    </button>


                    <?php if (
                        $searchQuery !== '' ||
                        $statusFilter !== ''
                    ): ?>

                        <a
                            href="appointments.php"
                            class="clear-button"
                        >
                            Clear
                        </a>

                    <?php else: ?>

                        <span></span>

                    <?php endif; ?>


                </form>


            </div>


            <div class="table-wrapper">


                <table class="appointments-table">


                    <thead>

                        <tr>

                            <th>
                                Date &amp; Time
                            </th>


                            <?php if (
                                $role !== 'Patient'
                            ): ?>

                                <th>
                                    Patient
                                </th>

                            <?php endif; ?>


                            <?php if (
                                $role !== 'Doctor'
                            ): ?>

                                <th>
                                    Doctor
                                </th>

                            <?php endif; ?>


                            <th>
                                Reason
                            </th>


                            <th>
                                Status
                            </th>


                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        !empty(
                            $appointments
                        )
                    ): ?>


                        <?php foreach (
                            $appointments
                            as $appointment
                        ): ?>


                            <?php

                            $status =
                                (string) (
                                    $appointment[
                                        'status'
                                    ]
                                    ?? 'Pending'
                                );


                            $appointmentDate =
                                (string) (
                                    $appointment[
                                        'appointment_date'
                                    ]
                                    ?? ''
                                );


                            $appointmentTime =
                                (string) (
                                    $appointment[
                                        'appointment_time'
                                    ]
                                    ?? ''
                                );


                            $patientName =
                                (string) (
                                    $appointment[
                                        'patient_name'
                                    ]
                                    ?? 'N/A'
                                );


                            $doctorName =
                                (string) (
                                    $appointment[
                                        'doctor_name'
                                    ]
                                    ?? 'Unassigned'
                                );


                            $reason =
                                trim(
                                    (string) (
                                        $appointment[
                                            'reason'
                                        ]
                                        ?? ''
                                    )
                                );


                            if (
                                $reason === ''
                            ) {

                                $reason =
                                    'Routine Checkup';
                            }


                            $dateObject =
                                safe_date(
                                    $appointmentDate
                                );


                            $timeObject =
                                safe_time(
                                    $appointmentTime
                                );


                            $formattedDate =
                                $dateObject !== null
                                    ? $dateObject->format(
                                        'M d, Y'
                                    )
                                    : 'Date not set';


                            $formattedTime =
                                $timeObject !== null
                                    ? $timeObject->format(
                                        'h:i A'
                                    )
                                    : 'Time not set';


                            $statusClass =
                                appointmentStatusClass(
                                    $status
                                );


                            $patientInitial =
                                mb_strtoupper(
                                    mb_substr(
                                        trim(
                                            $patientName
                                        ),
                                        0,
                                        1
                                    )
                                );


                            if (
                                $patientInitial === ''
                            ) {

                                $patientInitial =
                                    'P';
                            }

                            ?>


                            <tr>


                                <td>

                                    <span class="date-main">
                                        <?= e(
                                            $formattedDate
                                        ) ?>
                                    </span>


                                    <span class="date-time">
                                        <?= e(
                                            $formattedTime
                                        ) ?>
                                    </span>

                                </td>


                                <?php if (
                                    $role !== 'Patient'
                                ): ?>

                                    <td>

                                        <div class="person">

                                            <div
                                                class="
                                                    person-avatar
                                                "
                                            >

                                                <?= e(
                                                    $patientInitial
                                                ) ?>

                                            </div>


                                            <span
                                                class="person-name"
                                            >

                                                <?= e(
                                                    $patientName
                                                ) ?>

                                            </span>

                                        </div>

                                    </td>

                                <?php endif; ?>


                                <?php if (
                                    $role !== 'Doctor'
                                ): ?>

                                    <td>

                                        <div class="person">

                                            <div
                                                class="
                                                    person-avatar
                                                "
                                            >

                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-user-doctor
                                                    "
                                                ></i>

                                            </div>


                                            <span
                                                class="person-name"
                                            >

                                                <?php if (
                                                    $doctorName ===
                                                    'Unassigned'
                                                ): ?>

                                                    <?= e(
                                                        $doctorName
                                                    ) ?>

                                                <?php else: ?>

                                                    Dr.
                                                    <?= e(
                                                        $doctorName
                                                    ) ?>

                                                <?php endif; ?>

                                            </span>

                                        </div>

                                    </td>

                                <?php endif; ?>


                                <td>

                                    <div class="reason">

                                        <?= e(
                                            $reason
                                        ) ?>

                                    </div>

                                </td>


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
                                            $status
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <div class="actions">


                                        <?php if (
                                            $role === 'Admin' ||
                                            $role === 'Doctor'
                                        ): ?>


                                            <?php if (
                                                $status ===
                                                'Pending'
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="appointments.php"
                                                    class="action-form"
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
                                                        value="<?= (int) (
                                                            $appointment[
                                                                'id'
                                                            ]
                                                        ) ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="appointment_action"
                                                        value="Confirm"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="
                                                            action-button
                                                            confirm
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                fa-solid
                                                                fa-check
                                                            "
                                                        ></i>

                                                        Confirm

                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if (
                                                $status ===
                                                'Confirmed'
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="appointments.php"
                                                    class="action-form"
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
                                                        value="<?= (int) (
                                                            $appointment[
                                                                'id'
                                                            ]
                                                        ) ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="appointment_action"
                                                        value="Complete"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="
                                                            action-button
                                                            complete
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                fa-solid
                                                                fa-check-double
                                                            "
                                                        ></i>

                                                        Complete

                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if (
                                                $status !== 'Cancelled' &&
                                                $status !== 'Completed'
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="appointments.php"
                                                    class="action-form"
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
                                                        value="<?= (int) (
                                                            $appointment[
                                                                'id'
                                                            ]
                                                        ) ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="appointment_action"
                                                        value="Cancel"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="
                                                            action-button
                                                            cancel
                                                        "
                                                        onclick="
                                                            return confirm(
                                                                'Cancel this appointment?'
                                                            );
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                fa-solid
                                                                fa-xmark
                                                            "
                                                        ></i>

                                                        Cancel

                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if (
                                                $role === 'Doctor' &&
                                                (
                                                    $status === 'Confirmed' ||
                                                    $status === 'Completed'
                                                )
                                            ): ?>

                                                <a
                                                    href="add_record.php?appointment_id=<?= (int) (
                                                        $appointment[
                                                            'id'
                                                        ]
                                                    ) ?>"
                                                    class="
                                                        action-button
                                                        notes
                                                    "
                                                >

                                                    <i
                                                        class="
                                                            fa-solid
                                                            fa-file-medical
                                                        "
                                                    ></i>

                                                    Notes

                                                </a>

                                            <?php endif; ?>


                                        <?php elseif (
                                            $role === 'Patient'
                                        ): ?>


                                            <?php if (
                                                $status === 'Pending'
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="appointments.php"
                                                    class="action-form"
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
                                                        value="<?= (int) (
                                                            $appointment[
                                                                'id'
                                                            ]
                                                        ) ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="appointment_action"
                                                        value="Cancel"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="
                                                            action-button
                                                            cancel
                                                        "
                                                        onclick="
                                                            return confirm(
                                                                'Are you sure you want to cancel this appointment request?'
                                                            );
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                fa-solid
                                                                fa-xmark
                                                            "
                                                        ></i>

                                                        Cancel

                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span
                                                    class="no-action"
                                                >
                                                    No action
                                                </span>

                                            <?php endif; ?>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="<?= $role === 'Admin'
                                    ? 6
                                    : 5 ?>"
                                class="empty-state"
                            >

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

                                    <?php if (
                                        $searchQuery !== ''
                                    ): ?>

                                        No appointments
                                        matched your search.

                                    <?php elseif (
                                        $statusFilter !== ''
                                    ): ?>

                                        No appointments have
                                        the selected status.

                                    <?php else: ?>

                                        There are no
                                        appointments to display.

                                    <?php endif; ?>

                                </span>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


            <?php if (
                $totalPages > 1
            ): ?>


                <div class="pagination">


                    <div class="pagination-info">

                        Showing page

                        <strong>
                            <?= $page ?>
                        </strong>

                        of

                        <strong>
                            <?= $totalPages ?>
                        </strong>

                        · Total

                        <strong>
                            <?= $totalRecords ?>
                        </strong>

                        records

                    </div>


                    <div class="pagination-links">


                        <a
                            href="<?= e(
                                appointmentPageUrl(
                                    $page - 1
                                )
                            ) ?>"
                            class="
                                page-link
                                <?= $page <= 1
                                    ? 'disabled'
                                    : '' ?>"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-chevron-left
                                "
                            ></i>

                        </a>


                        <?php for (
                            $i = 1;
                            $i <= $totalPages;
                            $i++
                        ): ?>

                            <a
                                href="<?= e(
                                    appointmentPageUrl(
                                        $i
                                    )
                                ) ?>"
                                class="
                                    page-link
                                    <?= $page === $i
                                        ? 'active'
                                        : '' ?>"
                            >

                                <?= $i ?>

                            </a>

                        <?php endfor; ?>


                        <a
                            href="<?= e(
                                appointmentPageUrl(
                                    $page + 1
                                )
                            ) ?>"
                            class="
                                page-link
                                <?= $page >= $totalPages
                                    ? 'disabled'
                                    : '' ?>"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-chevron-right
                                "
                            ></i>

                        </a>


                    </div>


                </div>


            <?php endif; ?>


        </section>


    </div>


</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const body =
            document.body;


        /* =====================================================
           MEDICARE THEME SYSTEM
        ====================================================== */

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


        /* =====================================================
           MOBILE MENU
        ====================================================== */

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