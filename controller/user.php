<?php

require_once('db.php');
require_once('../model/Response.php');

try {
    $writeDb = DB::connectWriteDb();

} catch (PDOException $ex) {
    error_log("Connection error-" . $ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessages("Database connection error");
    $response->send();
    exit();
}
//check if request method is not POST Display error message
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->setMessages("Request method not allowed");
    $response->send();
    exit();
}
if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->setMessages("Content type must be json");
    $response->send();
    exit();
}


$inputData = file_get_contents('php://input');

if (!$jsonData = json_decode($inputData)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->setMessages("input body is not a valid json");
    $response->send();
    exit();

}
if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    !isset($jsonData->fullname) ? $response->setMessages("fullname is not provided") : false;
    !isset($jsonData->username) ? $response->setMessages("username is not provided") : false;
    !isset($jsonData->password) ? $response->setMessages("password is not provided") : false;
    $response->send();
    exit();
}
if (strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 ||
    strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 ||
    strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {

    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    (strlen($jsonData->fullname) < 1 ? $response->setMessages("full name can't be empty") : false);
    (strlen($jsonData->fullname) > 255 ? $response->setMessages("full name can't be greater than 255 characters") : false);
    (strlen($jsonData->username) < 1 ? $response->setMessages("username can't be empty") : false);
    (strlen($jsonData->username) > 255 ? $response->setMessages("username can't be greater than 255 characters") : false);
    (strlen($jsonData->password) < 1 ? $response->setMessages("password can't be empty") : false);
    (strlen($jsonData->password) > 255 ? $response->setMessages("full name can't be greater than 255 characters") : false);
    $response->send();
    exit();
}

$fullName = trim($jsonData->fullname);
$userName = trim($jsonData->username);
$password = $jsonData->password;

try {

    // first we need to check if username is already exists then we should display error(username is taken or user
    // already exists)
    $checkQuery = $writeDb->prepare('SELECT id FROM table_users WHERE username = :username');
    $checkQuery->bindParam(':username', $userName);
    $checkQuery->execute();

    $rowCount = $checkQuery->rowCount();
    if ($rowCount !== 0) {
        $response = new Response();
        $response->setHttpStatusCode(409);
        $response->setSuccess(false);
        $response->setMessages("User already exists");
        $response->send();
        exit();
    }

    // change password to hash first then pass it for further procedure

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $createQuery = $writeDb->prepare("INSERT INTO table_users(fullname, username , password ) 
                VALUES (:fullname,:username,:password)");
    $createQuery->bindParam(':fullname', $fullName);
    $createQuery->bindParam(':username', $userName);
    $createQuery->bindParam(':password', $hashed_password);
    $createQuery->execute();

    $rowCount = $createQuery->rowCount();
    if ($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessages("there was an issue creating a user account - please try again");
        $response->send();
        exit();
    }

    $lastUserId = $writeDb->lastInsertId();

    $returnArray = array();
    $returnArray['User_id'] = $lastUserId;
    $returnArray['full_name'] = $fullName;
    $returnArray['username'] = $userName;

    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->setMessages("User Created");
    $response->setData($returnArray);
    $response->send();
    exit();

} catch (PDOException $ex) {

    error_log("Database query error-" . $ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessages("input body is not a valid json");
    $response->send();
    exit();
}










