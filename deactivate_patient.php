<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Admins and Doctors can activate/deactivate patient records.
|--------------------------------------------------------------------------
*/

require_role(
    'Admin',
    'Doctor'
);


/*
|--------------------------------------------------------------------------
| ONLY POST REQUESTS
|--------------------------------------------------------------------------
| Patient status changes must never happen through a normal GET request.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    http_response_code(405);

    header(
        'Allow: POST'
    );

    exit(
        'Method Not Allowed.'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF SECURITY
|--------------------------------------------------------------------------
*/

verify_csrf(
    $_POST['_csrf'] ?? null
);


/*
|--------------------------------------------------------------------------
| PATIENT ID
|--------------------------------------------------------------------------
*/

$patientId =
    filter_var(
        $_GET['id'] ?? null,
        FILTER_VALIDATE_INT
    );


if (
    $patientId === false ||
    $patientId === null ||
    $patientId < 1
) {

    http_response_code(400);

    exit(
        'Invalid patient ID.'
    );
}


/*
|--------------------------------------------------------------------------
| ACTION
|--------------------------------------------------------------------------
*/

$action =
    strtolower(
        trim(
            (string) (
                $_GET['action'] ?? ''
            )
        )
    );


$allowedActions = [
    'activate',
    'deactivate',
];


if (
    !in_array(
        $action,
        $allowedActions,
        true
    )
) {

    http_response_code(400);

    exit(
        'Invalid patient status action.'
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


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserId =
    current_user_id();

$currentRole =
    current_role();


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
    | LOCK PATIENT RECORD
    |--------------------------------------------------------------------------
    */

    $patientStmt =
        $pdo->prepare(
            "SELECT
                id,
                first_name,
                last_name,
                is_active,
                user_id
             FROM patients
             WHERE id = ?
             LIMIT 1
             FOR UPDATE"
        );


    $patientStmt->execute([
        $patientId
    ]);


    $patient =
        $patientStmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | PATIENT NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (!$patient) {

        $pdo->rollBack();

        http_response_code(404);

        exit(
            'Patient not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT STATUS
    |--------------------------------------------------------------------------
    */

    $currentStatus =
        (int) (
            $patient['is_active'] ?? 1
        );


    /*
    |--------------------------------------------------------------------------
    | NO CHANGE NEEDED
    |--------------------------------------------------------------------------
    */

    if (
        $currentStatus ===
        $newStatus
    ) {

        $pdo->commit();


        $message =
            $newStatus === 1
                ? 'Patient is already active.'
                : 'Patient is already inactive.';


        redirect(
            'patients.php?' .
            http_build_query([
                'status' =>
                    $newStatus === 1
                        ? 'active'
                        : 'inactive',

                'message' =>
                    $message,
            ])
        );


        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PATIENT STATUS
    |--------------------------------------------------------------------------
    */

    $updateStmt =
        $pdo->prepare(
            "UPDATE patients
             SET is_active = ?
             WHERE id = ?"
        );


    $updateStmt->execute([
        $newStatus,
        $patientId
    ]);


    /*
    |--------------------------------------------------------------------------
    | VERIFY UPDATE
    |--------------------------------------------------------------------------
    */

    if (
        $updateStmt->rowCount() === 0
    ) {

        /*
        |--------------------------------------------------------------------------
        | It is possible for a database adapter to report zero affected
        | rows in edge cases. Re-check the record before failing.
        |--------------------------------------------------------------------------
        */

        $verifyStmt =
            $pdo->prepare(
                "SELECT is_active
                 FROM patients
                 WHERE id = ?
                 LIMIT 1"
            );


        $verifyStmt->execute([
            $patientId
        ]);


        $verifiedStatus =
            $verifyStmt->fetchColumn();


        if (
            $verifiedStatus === false ||
            (int) $verifiedStatus !==
            $newStatus
        ) {

            throw new RuntimeException(
                'The patient status could not be updated.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | PATIENT DISPLAY NAME
    |--------------------------------------------------------------------------
    */

    $patientName =
        trim(
            (string) (
                $patient['first_name'] ?? ''
            )
            .
            ' '
            .
            (string) (
                $patient['last_name'] ?? ''
            )
        );


    if (
        $patientName === ''
    ) {

        $patientName =
            'Patient #' .
            $patientId;
    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT MESSAGE
    |--------------------------------------------------------------------------
    */

    $message =
        $newStatus === 1
            ? $patientName .
              ' has been reactivated successfully.'
            : $patientName .
              ' has been deactivated successfully.';


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    redirect(
        'patients.php?' .
        http_build_query([
            'status' =>
                $newStatus === 1
                    ? 'active'
                    : 'inactive',

            'message' =>
                $message,
        ])
    );


    exit;


} catch (RuntimeException $e) {

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


    error_log(
        'MediCare patient status update error: ' .
        $e->getMessage()
    );


    redirect(
        'patients.php?' .
        http_build_query([
            'message' =>
                'Unable to update the patient status.',

            'error' =>
                '1',
        ])
    );


    exit;


} catch (PDOException $e) {

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


    error_log(
        'MediCare patient status database error: ' .
        $e->getMessage()
    );


    redirect(
        'patients.php?' .
        http_build_query([
            'message' =>
                'Unable to update the patient status right now.',

            'error' =>
                '1',
        ])
    );


    exit;
}