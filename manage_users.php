<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Admin users can manage system accounts.
|--------------------------------------------------------------------------
*/

require_role('Admin');


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserId = current_user_id();

$currentRole = current_role();


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$success = '';

$error = '';

$users = [];

$totalRecords = 0;

$totalPages = 1;

$limit = 10;

$page = 1;

$offset = 0;


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$searchQuery = trim(
    (string) (
        $_GET['search'] ?? ''
    )
);

$searchQuery = mb_substr(
    $searchQuery,
    0,
    150
);


/*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

$roleFilter = trim(
    (string) (
        $_GET['role_filter'] ?? ''
    )
);


$allowedRoles = [
    '',
    'Admin',
    'Doctor',
    'Patient',
];


if (
    !in_array(
        $roleFilter,
        $allowedRoles,
        true
    )
) {

    $roleFilter = '';
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

$statusFilter = trim(
    (string) (
        $_GET['status_filter'] ?? ''
    )
);


$allowedStatuses = [
    '',
    'Active',
    'Inactive',
];


if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $statusFilter = '';
}


/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    try {

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
        | ACTION
        |--------------------------------------------------------------------------
        */

        $action =
            strtolower(
                trim(
                    post_string(
                        'action',
                        30
                    )
                )
            );


        /*
        |--------------------------------------------------------------------------
        | TARGET USER ID
        |--------------------------------------------------------------------------
        */

        $targetUserId =
            filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );


        if (
            $targetUserId === false ||
            $targetUserId === null ||
            $targetUserId < 1
        ) {

            $error =
                'Invalid user account selected.';

        } elseif (
            (int) $targetUserId ===
            $currentUserId
        ) {

            $error =
                'Security restriction: you cannot deactivate your own account.';

        } elseif (
            !in_array(
                $action,
                [
                    'activate',
                    'deactivate',
                ],
                true
            )
        ) {

            $error =
                'Invalid account status action.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION
            |--------------------------------------------------------------------------
            */

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | LOCK TARGET ACCOUNT
                |--------------------------------------------------------------------------
                */

                $targetStmt =
                    $pdo->prepare(
                        "SELECT
                            id,
                            fullname,
                            email,
                            role,
                            is_active
                         FROM users
                         WHERE id = ?
                         LIMIT 1
                         FOR UPDATE"
                    );


                $targetStmt->execute([
                    (int) $targetUserId
                ]);


                $targetUser =
                    $targetStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$targetUser
                ) {

                    throw new RuntimeException(
                        'The selected user account could not be found.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | PREVENT ADMIN ACCOUNT STATUS CHANGES
                |--------------------------------------------------------------------------
                */

                $targetRole =
                    ucfirst(
                        strtolower(
                            trim(
                                (string) (
                                    $targetUser['role']
                                    ?? ''
                                )
                            )
                        )
                    );


                if (
                    $targetRole === 'Admin'
                ) {

                    throw new RuntimeException(
                        'Administrator accounts are protected and cannot be activated or deactivated from this page.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | TARGET STATUS
                |--------------------------------------------------------------------------
                */

                $newStatus =
                    $action === 'activate'
                        ? 1
                        : 0;


                $currentStatus =
                    (int) (
                        $targetUser['is_active']
                        ?? 1
                    );


                /*
                |--------------------------------------------------------------------------
                | ALREADY IN TARGET STATUS
                |--------------------------------------------------------------------------
                */

                if (
                    $currentStatus ===
                    $newStatus
                ) {

                    $pdo->commit();


                    $name =
                        trim(
                            (string) (
                                $targetUser['fullname']
                                ?? ''
                            )
                        );


                    if (
                        $name === ''
                    ) {

                        $name =
                            'User account';
                    }


                    $success =
                        $name .
                        (
                            $newStatus === 1
                                ? ' is already active.'
                                : ' is already inactive.'
                        );

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE ACCOUNT STATUS
                    |--------------------------------------------------------------------------
                    */

                    $updateStmt =
                        $pdo->prepare(
                            "UPDATE users
                             SET is_active = ?
                             WHERE id = ?"
                        );


                    $updateStmt->execute([
                        $newStatus,
                        (int) $targetUserId
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | VERIFY
                    |--------------------------------------------------------------------------
                    */

                    $verifyStmt =
                        $pdo->prepare(
                            "SELECT is_active
                             FROM users
                             WHERE id = ?
                             LIMIT 1"
                        );


                    $verifyStmt->execute([
                        (int) $targetUserId
                    ]);


                    $verifiedStatus =
                        $verifyStmt->fetchColumn();


                    if (
                        $verifiedStatus === false ||
                        (int) $verifiedStatus !==
                        $newStatus
                    ) {

                        throw new RuntimeException(
                            'The account status could not be updated.'
                        );
                    }


                    $pdo->commit();


                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS MESSAGE
                    |--------------------------------------------------------------------------
                    */

                    $name =
                        trim(
                            (string) (
                                $targetUser['fullname']
                                ?? ''
                            )
                        );


                    if (
                        $name === ''
                    ) {

                        $name =
                            'User account';
                    }


                    $success =
                        $name .
                        (
                            $newStatus === 1
                                ? ' has been activated successfully.'
                                : ' has been deactivated successfully.'
                        );
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
                    'MediCare user status runtime error: ' .
                    $e->getMessage()
                );


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


                error_log(
                    'MediCare user status database error: ' .
                    $e->getMessage()
                );


                $error =
                    'Unable to update the user account status right now.';
            }
        }

    } catch (Throwable $e) {

        error_log(
            'MediCare manage users security error: ' .
            $e->getMessage()
        );


        $error =
            'Unable to process the account action.';
    }
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$pageValue =
    filter_input(
        INPUT_GET,
        'page',
        FILTER_VALIDATE_INT
    );


if (
    $pageValue !== false &&
    $pageValue !== null &&
    $pageValue > 0
) {

    $page =
        $pageValue;
}


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
| SEARCH
|--------------------------------------------------------------------------
*/

if (
    $searchQuery !== ''
) {

    $whereClauses[] =
        "(
            fullname LIKE ?
            OR email LIKE ?
        )";


    $searchValue =
        '%' .
        $searchQuery .
        '%';


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;
}


/*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

if (
    $roleFilter !== ''
) {

    $whereClauses[] =
        'role = ?';


    $params[] =
        $roleFilter;
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if (
    $statusFilter === 'Active'
) {

    $whereClauses[] =
        'is_active = 1';

} elseif (
    $statusFilter === 'Inactive'
) {

    $whereClauses[] =
        'is_active = 0';
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';


if (
    !empty(
        $whereClauses
    )
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
| COUNT USERS
|--------------------------------------------------------------------------
*/

try {

    $countStmt =
        $pdo->prepare(
            "SELECT COUNT(*)
             FROM users
             {$whereSql}"
        );


    $countStmt->execute(
        $params
    );


    $totalRecords =
        (int) (
            $countStmt->fetchColumn()
            ?: 0
        );


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
        'MediCare user count error: ' .
        $e->getMessage()
    );


    $error =
        'Unable to calculate user records right now.';
}


/*
|--------------------------------------------------------------------------
| CORRECT PAGE
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
| LOAD USERS
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $pdo->prepare(
            "SELECT
                id,
                fullname,
                email,
                role,
                is_active,
                created_at
             FROM users
             {$whereSql}
             ORDER BY id DESC
             LIMIT {$limit}
             OFFSET {$offset}"
        );


    $stmt->execute(
        $params
    );


    $users =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    PDOException $e
) {

    error_log(
        'MediCare user loading error: ' .
        $e->getMessage()
    );


    $error =
        'Unable to load user accounts right now.';
}


/*
|--------------------------------------------------------------------------
| CURRENT USER DISPLAY
|--------------------------------------------------------------------------
*/

$displayName =
    (string) (
        $_SESSION['fullname'] ??
        'Administrator'
    );


$email =
    (string) (
        $_SESSION['email'] ??
        ''
    );


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
        'A';
}


/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function manageUsersPageUrl(
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
        'manage_users.php?' .
        http_build_query(
            $query
        );
}


/*
|--------------------------------------------------------------------------
| ROLE CLASS
|--------------------------------------------------------------------------
*/

function manageUsersRoleClass(
    string $role
): string {

    return match (
        strtolower(
            trim(
                $role
            )
        )
    ) {

        'admin' =>
            'role-admin',

        'doctor' =>
            'role-doctor',

        'patient' =>
            'role-patient',

        default =>
            'role-default',
    };
}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function manageUsersStatusClass(
    int $status
): string {

    return
        $status === 1
            ? 'status-active'
            : 'status-inactive';
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
        content="MediCare administrator user management."
    >


    <title>
        Manage Users | MediCare
    </title>


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
                #33255b;

            --hero-middle:
                #182c49;

            --hero-end:
                #082940;

            --hero-border:
                rgba(
                    169,
                    132,
                    255,
                    .15
                );

            --hero-accent:
                #c1a9ff;

            --hero-line:
                #a984ff;

            --hero-text:
                #c3c4d5;

            --overlay:
                rgba(
                    0,
                    8,
                    16,
                    .65
                );

            --table-header:
                rgba(
                    255,
                    255,
                    255,
                    .025
                );

            --icon-muted:
                #76919f;

            --nav-text:
                #9db4c1;

            --nav-title:
                #648191;

            --success-text:
                #74e4aa;

            --danger-text:
                #ff989e;

            --body-transition:
                background-color .25s ease,
                color .25s ease;
        }


        /*
        |--------------------------------------------------------------------------
        | LIGHT THEME
        |--------------------------------------------------------------------------
        */

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
                #eef2ff;

            --hero-middle:
                #e7f0f8;

            --hero-end:
                #e6f4f2;

            --hero-border:
                rgba(
                    117,
                    82,
                    202,
                    .15
                );

            --hero-accent:
                #6f52bb;

            --hero-line:
                #7552ca;

            --hero-text:
                #536c7b;

            --overlay:
                rgba(
                    14,
                    34,
                    45,
                    .38
                );

            --table-header:
                #f4f8fa;

            --icon-muted:
                #6f8794;

            --nav-text:
                #496473;

            --nav-title:
                #79909c;

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
                    circle at 15% 10%,
                    rgba(
                        16,
                        199,
                        176,
                        .07
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 85% 80%,
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
                    circle at 15% 10%,
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
                    transparent 27%
                ),

                var(--bg);
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
                var(--nav-title);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;

            transition:
                color .25s ease;
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
                var(--icon-muted);

            text-align:
                center;

            transition:
                color .25s ease;
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

            transition:
                background-color .25s ease,
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


        .theme-toggle i {

            font-size:
                13px;
        }


        /* =========================================================
           MAIN
        ========================================================== */

        .main {

            min-height:
                100vh;

            margin-left:
                var(--sidebar-width);

            transition:
                margin-left .25s ease;
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

            transition:
                background-color .25s ease,
                border-color .25s ease;
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
           CONTENT / HERO
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


        .hero {

            position:
                relative;

            overflow:
                hidden;

            min-height:
                175px;

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
                28px 32px;

            border:
                1px solid
                var(--hero-border);

            border-radius:
                19px;

            background:

                linear-gradient(
                    135deg,
                    var(--hero-start),
                    var(--hero-middle) 58%,
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
                300px;

            height:
                300px;

            right:
                -105px;

            top:
                -145px;

            border-radius:
                50%;

            border:
                1px solid
                rgba(
                    169,
                    132,
                    255,
                    .14
                );
        }


        .hero-copy {

            position:
                relative;

            z-index:
                2;

            max-width:
                760px;
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
                var(--hero-text);

            font-size:
                11px;

            line-height:
                1.7;
        }


        .hero-action {

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
                46px;

            padding:
                0 17px;

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
                10px;

            font-weight:
                800;

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }


        .hero-action:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 12px 28px
                rgba(
                    16,
                    199,
                    176,
                    .14
                );
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
                1.5;

            transition:
                background-color .25s ease,
                border-color .25s ease,
                color .25s ease;
        }


        .alert-success {

            color:
                var(--success-text);

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
                    .13
                );
        }


        .alert-error {

            color:
                var(--danger-text);

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
                    .13
                );
        }


        html[data-theme="light"]
        .alert-success {

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
                    .16
                );
        }


        html[data-theme="light"]
        .alert-error {

            background:
                rgba(
                    201,
                    67,
                    75,
                    .08
                );

            border-color:
                rgba(
                    201,
                    67,
                    75,
                    .16
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
                    color-mix(
                        in srgb,
                        var(--panel-solid) 95%,
                        transparent
                    ),
                    color-mix(
                        in srgb,
                        var(--panel-dark) 97%,
                        transparent
                    )
                );

            transition:
                background .25s ease,
                border-color .25s ease;
        }


        /*
        |------------------------------------------------------------------
        | Fallback for browsers without color-mix
        |------------------------------------------------------------------
        */

        @supports not (
            background:
                color-mix(
                    in srgb,
                    white 50%,
                    transparent
                )
        ) {

            .panel {

                background:
                    var(--panel-solid);
            }
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
                var(--text-strong);

            font-size:
                16px;
        }


        .panel-header p {

            margin-top:
                4px;

            color:
                var(--muted);

            font-size:
                9px;
        }


        .count-badge {

            min-width:
                30px;

            height:
                30px;

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
                    .08
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .12
                );

            font-size:
                9px;

            font-weight:
                800;
        }


        /* =========================================================
           FILTERS
        ========================================================== */

        .filter-area {

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
                    220px,
                    1fr
                )
                150px
                150px
                95px
                75px;

            gap:
                9px;
        }


        .filter-input,
        .filter-select {

            width:
                100%;

            height:
                43px;

            padding:
                0 12px;

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


        .filter-input::placeholder {

            color:
                var(--muted-darker);
        }


        .filter-input:focus,
        .filter-select:focus {

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    .48
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
                var(--soft-bg);

            color:
                var(--muted);
        }


        .clear-button:hover {

            color:
                var(--primary);
        }


        /* =========================================================
           RESULTS BAR
        ========================================================== */

        .results-bar {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                12px 18px;

            border-bottom:
                1px solid
                var(--border-soft);
        }


        .results-text {

            color:
                var(--muted);

            font-size:
                9px;
        }


        .results-text strong {

            color:
                var(--text-soft);
        }


        .current-filter {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

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
                    .12
                );

            font-size:
                8px;

            font-weight:
                800;
        }


        /* =========================================================
           TABLE
        ========================================================== */

        .table-wrapper {

            overflow-x:
                auto;
        }


        .users-table {

            width:
                100%;

            min-width:
                1080px;

            border-collapse:
                collapse;
        }


        .users-table th {

            padding:
                14px 15px;

            text-align:
                left;

            white-space:
                nowrap;

            color:
                var(--muted);

            background:
                var(--table-header);

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


        .users-table td {

            padding:
                15px;

            color:
                var(--text-soft);

            border-bottom:
                1px solid
                var(--border-soft);

            font-size:
                10px;

            vertical-align:
                middle;
        }


        .users-table tbody tr {

            transition:
                background-color .2s ease;
        }


        .users-table tbody tr:hover {

            background:
                var(--hover-bg);
        }


        .users-table tbody tr:last-child td {

            border-bottom:
                none;
        }


        .user-cell {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            min-width:
                190px;
        }


        .user-avatar {

            width:
                37px;

            height:
                37px;

            flex-shrink:
                0;

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
                    .08
                );

            border:
                1px solid
                rgba(
                    16,
                    199,
                    176,
                    .10
                );

            font-size:
                9px;

            font-weight:
                800;
        }


        .user-name {

            display:
                block;

            max-width:
                230px;

            overflow:
                hidden;

            white-space:
                nowrap;

            text-overflow:
                ellipsis;

            color:
                var(--text-strong);

            font-size:
                10px;

            font-weight:
                700;
        }


        .user-id {

            display:
                block;

            margin-top:
                3px;

            color:
                var(--muted-dark);

            font-size:
                8px;
        }


        .email {

            max-width:
                220px;

            overflow-wrap:
                anywhere;

            color:
                var(--muted);

            font-size:
                9px;

            line-height:
                1.5;
        }


        /* =========================================================
           ROLE
        ========================================================== */

        .role-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                6px 9px;

            border-radius:
                999px;

            font-size:
                7px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .role-admin {

            color:
                #ff9298;

            background:
                rgba(
                    255,
                    114,
                    121,
                    .08
                );

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    .12
                );
        }


        .role-doctor {

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


        .role-patient {

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


        .role-default {

            color:
                #9fb6c2;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .04
                );

            border:
                1px solid
                var(--border);
        }


        html[data-theme="light"]
        .role-admin {

            color:
                #b13d47;

            background:
                rgba(
                    201,
                    67,
                    75,
                    .08
                );
        }


        html[data-theme="light"]
        .role-doctor {

            color:
                #216ebd;

            background:
                rgba(
                    36,
                    120,
                    217,
                    .08
                );
        }


        html[data-theme="light"]
        .role-patient {

            color:
                #287d50;

            background:
                rgba(
                    36,
                    153,
                    93,
                    .08
                );
        }


        html[data-theme="light"]
        .role-default {

            color:
                #5e7480;

            background:
                rgba(
                    20,
                    54,
                    70,
                    .04
                );
        }


        /* =========================================================
           STATUS
        ========================================================== */

        .account-status {

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
                7px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .account-status::before {

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


        .status-active {

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


        .status-inactive {

            color:
                #ff9298;

            background:
                rgba(
                    255,
                    114,
                    121,
                    .08
                );

            border:
                1px solid
                rgba(
                    255,
                    114,
                    121,
                    .12
                );
        }


        html[data-theme="light"]
        .status-active {

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
        .status-inactive {

            color:
                #bd414a;

            background:
                rgba(
                    201,
                    67,
                    75,
                    .08
                );

            border-color:
                rgba(
                    201,
                    67,
                    75,
                    .15
                );
        }


        /* =========================================================
           ACTION BUTTONS
        ========================================================== */

        .action-form {

            margin:
                0;
        }


        .status-button {

            min-height:
                32px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                6px;

            padding:
                0 10px;

            border-radius:
                7px;

            font-size:
                8px;

            font-weight:
                800;

            cursor:
                pointer;

            transition:
                transform .2s ease,
                background .2s ease,
                border-color .2s ease;
        }


        .status-button:hover {

            transform:
                translateY(-1px);
        }


        .deactivate-button {

            color:
                #ff969c;

            background:
                rgba(
                    255,
                    114,
                    121,
                    .05
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


        .deactivate-button:hover {

            background:
                rgba(
                    255,
                    114,
                    121,
                    .10
                );
        }


        .activate-button {

            color:
                #72e5ac;

            background:
                rgba(
                    85,
                    216,
                    144,
                    .05
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


        .activate-button:hover {

            background:
                rgba(
                    85,
                    216,
                    144,
                    .10
                );
        }


        html[data-theme="light"]
        .deactivate-button {

            color:
                #b83d46;

            background:
                rgba(
                    201,
                    67,
                    75,
                    .06
                );

            border-color:
                rgba(
                    201,
                    67,
                    75,
                    .18
                );
        }


        html[data-theme="light"]
        .deactivate-button:hover {

            background:
                rgba(
                    201,
                    67,
                    75,
                    .11
                );
        }


        html[data-theme="light"]
        .activate-button {

            color:
                #287d50;

            background:
                rgba(
                    36,
                    153,
                    93,
                    .06
                );

            border-color:
                rgba(
                    36,
                    153,
                    93,
                    .18
                );
        }


        html[data-theme="light"]
        .activate-button:hover {

            background:
                rgba(
                    36,
                    153,
                    93,
                    .11
                );
        }


        .protected {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            color:
                var(--muted);

            font-size:
                8px;
        }


        /* =========================================================
           EMPTY
        ========================================================== */

        .empty-state {

            padding:
                65px 20px !important;

            text-align:
                center;

            color:
                var(--muted-dark) !important;
        }


        .empty-state i {

            display:
                block;

            margin-bottom:
                12px;

            color:
                var(--muted-dark);

            font-size:
                30px;
        }


        .empty-state strong {

            display:
                block;

            margin-bottom:
                5px;

            color:
                var(--text-soft);

            font-size:
                12px;
        }


        .empty-state span {

            display:
                block;

            color:
                var(--muted);

            font-size:
                9px;

            line-height:
                1.6;
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
                var(--muted-dark);

            font-size:
                9px;
        }


        .pagination-info strong {

            color:
                var(--text-soft);
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
                var(--soft-bg);

            color:
                var(--muted);

            font-size:
                8px;

            font-weight:
                800;

            transition:
                .2s ease;
        }


        .page-link:hover {

            color:
                var(--primary);

            border-color:
                rgba(
                    16,
                    199,
                    176,
                    .20
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
            max-width: 1150px
        ) {

            .filter-form {

                grid-template-columns:
                    minmax(
                        180px,
                        1fr
                    )
                    140px
                    140px
                    85px
                    70px;
            }

        }


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


            .hero {

                flex-direction:
                    column;

                align-items:
                    flex-start;

                padding:
                    25px 20px;
            }


            .hero-action {

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


            .results-bar {

                align-items:
                    flex-start;

                flex-direction:
                    column;
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


        @media (
            max-width: 460px
        ) {

            .top-icon {

                display:
                    none;
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


        <a
            href="manage_users.php"
            class="active"
        >

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
                    fa-shield-halved
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


    <header class="topbar">


        <div class="page-title">

            <small>
                MediCare Administration
            </small>

            <h2>
                Manage Users
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
                href="profile.php"
                class="top-icon"
                title="Profile"
            >

                <i class="fa-regular fa-user"></i>

            </a>


        </div>


    </header>


    <div class="content">


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="hero">


            <div class="hero-copy">


                <div class="eyebrow">
                    User Administration
                </div>


                <h1>
                    Manage MediCare Users
                </h1>


                <p>

                    Review accounts, control account
                    availability and preserve user records
                    without permanently deleting healthcare data.

                </p>


            </div>


            <a
                href="staff_register.php"
                class="hero-action"
            >

                <i class="fa-solid fa-user-plus"></i>

                Register Staff

            </a>


        </section>


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

                <?= e(
                    $success
                ) ?>

            </div>

        <?php endif; ?>


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

                <?= e(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <section class="panel">


            <header class="panel-header">


                <div>

                    <span>
                        Account Directory
                    </span>

                    <h2>
                        System Users
                    </h2>

                    <p>
                        Activate or deactivate non-administrator accounts.
                    </p>

                </div>


                <span class="count-badge">

                    <?= $totalRecords ?>

                </span>


            </header>


            <!-- =================================================
                 FILTERS
            ================================================== -->

            <div class="filter-area">


                <form
                    method="GET"
                    action="manage_users.php"
                    class="filter-form"
                >


                    <input
                        type="search"
                        name="search"
                        class="filter-input"
                        placeholder="Search name or email..."
                        value="<?= e(
                            $searchQuery
                        ) ?>"
                        autocomplete="off"
                    >


                    <select
                        name="role_filter"
                        class="filter-select"
                    >

                        <option value="">
                            All Roles
                        </option>


                        <option
                            value="Admin"
                            <?= $roleFilter === 'Admin'
                                ? 'selected'
                                : '' ?>
                        >
                            Admin
                        </option>


                        <option
                            value="Doctor"
                            <?= $roleFilter === 'Doctor'
                                ? 'selected'
                                : '' ?>
                        >
                            Doctor
                        </option>


                        <option
                            value="Patient"
                            <?= $roleFilter === 'Patient'
                                ? 'selected'
                                : '' ?>
                        >
                            Patient
                        </option>

                    </select>


                    <select
                        name="status_filter"
                        class="filter-select"
                    >

                        <option value="">
                            All Statuses
                        </option>


                        <option
                            value="Active"
                            <?= $statusFilter === 'Active'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>


                        <option
                            value="Inactive"
                            <?= $statusFilter === 'Inactive'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
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

                        &nbsp;

                        Search

                    </button>


                    <?php if (
                        $searchQuery !== '' ||
                        $roleFilter !== '' ||
                        $statusFilter !== ''
                    ): ?>

                        <a
                            href="manage_users.php"
                            class="clear-button"
                        >

                            Clear

                        </a>

                    <?php else: ?>

                        <span></span>

                    <?php endif; ?>


                </form>


            </div>


            <!-- =================================================
                 RESULTS
            ================================================== -->

            <div class="results-bar">


                <div class="results-text">

                    Showing

                    <strong>
                        <?= count(
                            $users
                        ) ?>
                    </strong>

                    account(s)

                </div>


                <span class="current-filter">

                    <i
                        class="
                            fa-solid
                            fa-filter
                        "
                    ></i>


                    <?php if (
                        $roleFilter !== '' &&
                        $statusFilter !== ''
                    ): ?>

                        <?= e(
                            $roleFilter
                        ) ?>

                        ·

                        <?= e(
                            $statusFilter
                        ) ?>

                    <?php elseif (
                        $roleFilter !== ''
                    ): ?>

                        <?= e(
                            $roleFilter
                        ) ?>

                    <?php elseif (
                        $statusFilter !== ''
                    ): ?>

                        <?= e(
                            $statusFilter
                        ) ?>

                    <?php else: ?>

                        All Accounts

                    <?php endif; ?>

                </span>


            </div>


            <!-- =================================================
                 TABLE
            ================================================== -->

            <div class="table-wrapper">


                <table class="users-table">


                    <thead>

                        <tr>

                            <th>
                                User
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Joined
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        !empty(
                            $users
                        )
                    ): ?>


                        <?php foreach (
                            $users
                            as $user
                        ): ?>


                            <?php

                            $accountId =
                                (int) (
                                    $user['id']
                                    ?? 0
                                );


                            $fullName =
                                trim(
                                    (string) (
                                        $user['fullname']
                                        ?? ''
                                    )
                                );


                            if (
                                $fullName === ''
                            ) {

                                $fullName =
                                    'Unnamed User';
                            }


                            $emailAddress =
                                trim(
                                    (string) (
                                        $user['email']
                                        ?? ''
                                    )
                                );


                            $userRole =
                                trim(
                                    (string) (
                                        $user['role']
                                        ?? ''
                                    )
                                );


                            $isActive =
                                (int) (
                                    $user['is_active']
                                    ?? 1
                                ) === 1;


                            $roleClass =
                                manageUsersRoleClass(
                                    $userRole
                                );


                            $statusClass =
                                manageUsersStatusClass(
                                    $isActive
                                        ? 1
                                        : 0
                                );


                            $nameParts =
                                preg_split(
                                    '/\s+/',
                                    $fullName
                                );


                            $initials =
                                'U';


                            if (
                                is_array(
                                    $nameParts
                                )
                            ) {

                                $initials =
                                    '';

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
                                    'U';
                            }


                            $createdAt =
                                (string) (
                                    $user['created_at']
                                    ?? ''
                                );


                            $createdDate =
                                null;


                            if (
                                $createdAt !== ''
                            ) {

                                try {

                                    $createdDate =
                                        new DateTimeImmutable(
                                            $createdAt
                                        );

                                } catch (
                                    Exception $e
                                ) {

                                    $createdDate =
                                        null;
                                }
                            }

                            ?>


                            <tr>


                                <!-- USER -->

                                <td>


                                    <div class="user-cell">


                                        <div class="user-avatar">

                                            <?= e(
                                                $initials
                                            ) ?>

                                        </div>


                                        <div>


                                            <span
                                                class="user-name"
                                            >

                                                <?= e(
                                                    $fullName
                                                ) ?>

                                            </span>


                                            <span
                                                class="user-id"
                                            >

                                                User #

                                                <?= $accountId ?>

                                            </span>


                                        </div>


                                    </div>


                                </td>


                                <!-- EMAIL -->

                                <td>


                                    <div class="email">

                                        <?= e(
                                            $emailAddress
                                        ) ?>

                                    </div>


                                </td>


                                <!-- ROLE -->

                                <td>


                                    <span
                                        class="
                                            role-badge
                                            <?= e(
                                                $roleClass
                                            ) ?>
                                        "
                                    >


                                        <?php if (
                                            strtolower(
                                                $userRole
                                            ) === 'admin'
                                        ): ?>

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-shield-halved
                                                "
                                            ></i>

                                        <?php elseif (
                                            strtolower(
                                                $userRole
                                            ) === 'doctor'
                                        ): ?>

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-user-doctor
                                                "
                                            ></i>

                                        <?php else: ?>

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-user
                                                "
                                            ></i>

                                        <?php endif; ?>


                                        <?= e(
                                            $userRole
                                        ) ?>

                                    </span>


                                </td>


                                <!-- JOINED -->

                                <td>


                                    <?php if (
                                        $createdDate !== null
                                    ): ?>

                                        <?= e(
                                            $createdDate
                                                ->format(
                                                    'M d, Y'
                                                )
                                        ) ?>

                                    <?php else: ?>

                                        Date unavailable

                                    <?php endif; ?>


                                </td>


                                <!-- STATUS -->

                                <td>


                                    <span
                                        class="
                                            account-status
                                            <?= e(
                                                $statusClass
                                            ) ?>
                                        "
                                    >

                                        <?= $isActive
                                            ? 'Active'
                                            : 'Inactive' ?>

                                    </span>


                                </td>


                                <!-- ACTION -->

                                <td>


                                    <?php if (
                                        $accountId ===
                                        $currentUserId
                                    ): ?>


                                        <span
                                            class="protected"
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-circle-check
                                                "
                                            ></i>

                                            Current account

                                        </span>


                                    <?php elseif (
                                        strtolower(
                                            $userRole
                                        ) === 'admin'
                                    ): ?>


                                        <span
                                            class="protected"
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-shield
                                                "
                                            ></i>

                                            Admin protected

                                        </span>


                                    <?php elseif (
                                        $isActive
                                    ): ?>


                                        <form
                                            method="POST"
                                            action="manage_users.php"
                                            class="action-form"
                                            onsubmit="
                                                return confirm(
                                                    'Deactivate this account? The user record and healthcare history will be preserved.'
                                                );
                                            "
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
                                                name="action"
                                                value="deactivate"
                                            >


                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $accountId ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    status-button
                                                    deactivate-button
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


                                        <form
                                            method="POST"
                                            action="manage_users.php"
                                            class="action-form"
                                            onsubmit="
                                                return confirm(
                                                    'Reactivate this account?'
                                                );
                                            "
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
                                                name="action"
                                                value="activate"
                                            >


                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $accountId ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    status-button
                                                    activate-button
                                                "
                                            >

                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-user-check
                                                    "
                                                ></i>

                                                Activate

                                            </button>


                                        </form>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>


                            <td
                                colspan="6"
                                class="empty-state"
                            >


                                <i
                                    class="
                                        fa-solid
                                        fa-users-slash
                                    "
                                ></i>


                                <strong>
                                    No users found
                                </strong>


                                <span>

                                    <?php if (
                                        $searchQuery !== '' ||
                                        $roleFilter !== '' ||
                                        $statusFilter !== ''
                                    ): ?>

                                        No accounts match
                                        your current filters.

                                    <?php else: ?>

                                        There are currently
                                        no user accounts
                                        to display.

                                    <?php endif; ?>


                                </span>


                            </td>


                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


            <!-- =================================================
                 PAGINATION
            ================================================== -->

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
                                manageUsersPageUrl(
                                    $page - 1
                                )
                            ) ?>"
                            class="
                                page-link
                                <?= $page <= 1
                                    ? 'disabled'
                                    : '' ?>
                            "
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
                                    manageUsersPageUrl(
                                        $i
                                    )
                                ) ?>"
                                class="
                                    page-link
                                    <?= $page === $i
                                        ? 'active'
                                        : '' ?>
                                "
                            >

                                <?= $i ?>

                            </a>


                        <?php endfor; ?>


                        <a
                            href="<?= e(
                                manageUsersPageUrl(
                                    $page + 1
                                )
                            ) ?>"
                            class="
                                page-link
                                <?= $page >= $totalPages
                                    ? 'disabled'
                                    : '' ?>
                            "
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

            if (!themeIcon) {

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

                /*
                |--------------------------------------------------------------------------
                | Local storage may be unavailable in some browser configurations.
                |--------------------------------------------------------------------------
                */

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

    }
);

</script>


</body>

</html>