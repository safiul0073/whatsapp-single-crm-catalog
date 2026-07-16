<?php

require 'components/session.php';
require 'components/helper.php';

checkIfAlreadyInstalled();

$fields = [
    'purchase_code' => 'required',
    'app_name' => 'required',
    'app_url' => 'required|url',
    'db_name' => 'required',
    'db_user' => 'required',
    'db_pass' => 'nullable',
    'db_host' => 'required',
    'first_name' => 'required',
    'last_name' => 'required',
    'email' => 'required|email',
    'password' => 'required',
];

$data = validate($fields);

if (! $data) {
    putSession('admin-info-error', 'One or more fields are not valid');
    header('Location: '.appUrl().'installer/admin-info.php');
    exit;
}

// check the purchased code is valid
checkLicense($data);

// Database connection setup
try {
    $dsn = 'mysql:host='.$data['db_host'].';dbname='.$data['db_name'];
    $conn = new PDO($dsn, $data['db_user'], $data['db_pass']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    putSession('admin-info-error', 'Database connection failed: '.$e->getMessage());
    header('Location: '.appUrl().'installer/admin-info.php');
    exit;
}

// Database import
try {
    dropAllTables($conn, $data['db_name']);
    // check app_db.sql exists
    if (! file_exists(__DIR__.'/app_db.sql')) {
        putSession('admin-info-error', 'Script app_db.sql not found!');

        return header('Location: '.appUrl().'installer/admin-info.php');
    }

    $query = file_get_contents('app_db.sql');
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->closeCursor();

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    dropAllTables($conn, $data['db_name']);

    putSession('admin-info-error', 'Database operation failed: '.$e->getMessage());
    header('Location: '.appUrl().'installer/admin-info.php');
    exit;
}

function dropAllTables($conn, $dbName)
{
    try {
        $conn->exec('SET FOREIGN_KEY_CHECKS = 0');

        $stmt = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbName'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $conn->exec("DROP TABLE IF EXISTS `$table`");
        }

        $conn->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $e) {
        error_log('Error dropping tables: '.$e->getMessage());
    }
}

// Admin user creation
try {

    $stmt = $conn->prepare('INSERT INTO `admins` (`first_name`, `last_name`, `email`, `admin_role_id`, `password`, `created_at`, `updated_at`)
        VALUES (:first_name, :last_name, :email, :admin_role_id, :password, :created_at, :updated_at)');
    $stmt->execute([
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'email' => $data['email'],
        'admin_role_id' => 1,
        'password' => password_hash($data['password'], PASSWORD_BCRYPT),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
} catch (PDOException $e) {
    putSession('admin-info-error', 'Database connection failed: '.$e->getMessage());
    dropAllTables($conn, $data['db_name']);
    header('Location: '.appUrl().'installer/admin-info.php');
    exit;
}

// Rewrite .env file with new values
try {
    file_put_contents('../../.env', getEnvContent($data['app_url'], $data['app_name'], $data['db_name'], $data['db_user'], $data['db_pass'], $data['db_host']));
} catch (Exception $e) {
    putSession('admin-info-error', 'Error writing .env file: '.$e->getMessage());
    dropAllTables($conn, $data['db_name']);
    header('Location: '.appUrl().'installer/admin-info.php');
    exit;
}

// Finish the installation
file_put_contents('../../storage/installed', date('Y-m-d H:i:s'));

// complete any post installation steps
postInstallation();

putSession('admin-info-success', 'Installation completed successfully');

header('Location: '.appUrl());
exit;
