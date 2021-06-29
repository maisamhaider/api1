<?php

use JetBrains\PhpStorm\NoReturn;

require_once('db.php');
require_once('../model/Response.php');

#[NoReturn] function responseFun($httpStatusCode, $success, $message = null, $toCache = false, $data = null)
{
    $response = new Response();
    $response->setHttpStatusCode($httpStatusCode);
    $response->setSuccess($success);
    if ($message !== null) {
        $response->setMessages($message);
    }
    $response->setToCache($toCache);
    if ($data !== null) {
        $response->setData($data);
    }
    $response->send();
    exit();
}

try {

    $writeDb = DB::connectWriteDb();

} catch (PDOException $ex) {
    error_log("Connection error-" . $ex, 0);
    responseFun(500, false, "Database connection error");

}
if (array_key_exists("sessionid", $_GET)) {

    $sessionId = $_GET['sessionid'];
    if ($sessionId === '' || !is_numeric($sessionId)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $sessionId === '' ? $response->setMessages('Session id can\'t be empty') : false;
        !is_numeric($sessionId) ? $response->setMessages('Session id can\'t be non numeric') : false;
        $response->send();
        exit();
    }
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        !isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->setMessages('Access token is missing from header') : false;
        strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->setMessages('Access token can\'t be black') : false;
        $response->send();
        exit();
    }

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    //log out
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        try {
            $query = $writeDb->prepare('DELETE FROM table_sessions WHERE id = :sessionId 
                             and accesstoken = :accessToken');
            $query->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
            $query->bindParam(':accessToken', $accessToken);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessages("Failed to logout of this session using provided access token");
                $response->send();
                exit();
            }

            $returnData = array();

            $returnData['session_id'] = $sessionId;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setMessages("Logged out");
            $response->setData($returnData);
            $response->send();
            exit();


        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages("Failed to logout of this session using provided access token");
            $response->send();
            exit();
        }


    } elseif ($_SERVER['REQUEST_METHOD'] === 'FETCH') {
        // refresh refreshToken

        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages("Content type is not a json");
            $response->send();
            exit();
        }

        $inputFETCHData = file_get_contents('php://input');
        if (!$jsonData = json_decode($inputFETCHData)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages("Request method not allowed");
            $response->send();
            exit();
        }
        if (!isset($jsonData->refreshtoken) || strlen($jsonData->refreshtoken) < 1) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            !isset($jsonData->refreshtoken) ? $response->setMessages("refresh token is not provided") : false;
            strlen($jsonData->refreshtoken) < 1 ? $response->setMessages("refresh token can't be empty") : false;
            $response->send();
            exit();
        }


        try {

            $refreshToken = $jsonData->refreshtoken;

            $query = $writeDb->prepare('SELECT table_sessions.id as sessionid, table_sessions.userid as userid,
             accesstoken, refreshtoken, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry 
            FROM table_sessions, table_users WHERE table_users.id = table_sessions.userid and table_sessions.id =
            :sessionid and table_sessions.accesstoken = :accesstoken and table_sessions.refreshtoken = :refreshtoken');
            $query->bindParam(':sessionid', $sessionId, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accessToken);
            $query->bindParam(':refreshtoken', $refreshToken);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages("Access token or refresh token is incorrect");
                $response->send();

            }
            $row = $query->fetch(PDO::FETCH_ASSOC);

            $return_sessionId = $row['sessionid'];
            $return_userId = $row['userid'];
            $return_accesstoken = $row['accesstoken'];
            $return_refreshtoken = $row['refreshtoken'];
            $return_useractive = $row['useractive'];
            $return_loginattempts = $row['loginattempts'];
            $return_accesstokenexpiry = $row['accesstokenexpiry'];
            $return_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if ($return_useractive !== 'Y') {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages("User is not active");
                $response->send();
                exit();
            }
            if ($return_loginattempts >= 3) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages("User account is currently locked");
                $response->send();
                exit();
            }
            if (strtotime($return_refreshtokenexpiry) < time()) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages("Refresh token is expired: please login again");
                $response->send();
                exit();
            }
            $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
            $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

            $accessTokenExpirySec = 1200;
            $refreshTokenExpirySec = 1209600;

            $query = $writeDb->prepare('UPDATE table_sessions SET accesstoken = :accesstoken, accesstokenexpiry =
            date_add(NOW(), INTERVAL :accesstokenexpirysec SECOND ), refreshtoken = :refreshtoken, refreshtokenexpiry = 
            date_add(NOW(), INTERVAL :refreshtokenexpirysec SECOND ) WHERE id = :sessionid and userid = :userid and 
            accesstoken = :return_accesstoken and refreshaccesstoken = :return_refreshaccesstoken');
            $query->bindParam(':userid', $return_userId, PDO::PARAM_INT);
            $query->bindParam(':sessionid', $return_sessionId, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accessToken);
            $query->bindParam(':accesstokenexpirysec', $accessTokenExpirySec, PDO::PARAM_INT);
            $query->bindParam(':refreshtoken', $refreshToken);
            $query->bindParam(':refreshtokenexpirysec', $refreshTokenExpirySec, PDO::PARAM_INT);
            $query->bindParam('return_accesstoken', $return_accesstoken);
            $query->bindParam('return_refreshaccesstoken', $return_refreshtoken);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                responseFun(401, false, "Refresh token couldn't updated - please try again later");

            }

            $returnData = array();
            $returnData['sessionid'] = $return_sessionId;
            $returnData['access_token'] = $accessToken;
            $returnData['access_token_expiry'] = $accessTokenExpirySec;
            $returnData['refresh_token'] = $refreshToken;
            $returnData['refresh_token_expiry'] = $refreshTokenExpirySec;

            responseFun(200, true, "Token updated", false, $returnData);

        } catch
        (PDOException $ex) {
            responseFun(500, false, "There was an issue refreshing token - please try again later ");
        }


    } else {
        responseFun(405, false, "Request method not allowed");
    }

} elseif
(empty($_GET)) {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessages("Request method not allowed");
        $response->send();
        exit();
    }
    sleep(1);

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
    if (!isset($jsonData->username) || !isset($jsonData->password)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        !isset($jsonData->username) ? $response->setMessages("username is not provided") : false;
        !isset($jsonData->password) ? $response->setMessages("password is not provided") : false;
        $response->send();
        exit();
    }
    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 ||
        strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {

        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (strlen($jsonData->username) < 1
            ? $response->setMessages("username can't be empty") : false);
        (strlen($jsonData->username) > 255
            ? $response->setMessages("username can't be greater than 255 characters") : false);
        (strlen($jsonData->password) < 1
            ? $response->setMessages("password can't be empty") : false);
        (strlen($jsonData->password) > 255
            ? $response->setMessages("full name can't be greater than 255 characters") : false);
        $response->send();
        exit();
    }

    try {

        $userName = $jsonData->username;
        $password = $jsonData->password;

        $query = $writeDb->prepare("SELECT * FROM table_users WHERE username = :username");
        $query->bindParam(':username', $userName);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessages("Username or password is incorrect");
            $response->send();
            exit();
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $user_id = $row['id'];
        $user_fullName = $row['fullname'];
        $user_userName = $row['username'];
        $user_password = $row['password'];
        $user_userActive = $row['useractive'];
        $user_loginattempts = $row['loginattempts'];

        if ($user_userActive !== 'Y') {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessages("User account is not active");
            $response->send();
            exit();
        }
        if ($user_loginattempts >= 3) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessages("Your account is temporarily locked");
            $response->send();
            exit();
        }

        if (!password_verify($password, $user_password)) {

            $query = $writeDb->prepare('UPDATE table_users SET loginattempts = loginattempts+1 WHERE id = :id');
            $query->bindParam(':id', $user_id, PDO::PARAM_INT);
            $query->execute();

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessages("Username or password is incorrect");
            $response->send();
            exit();
        }

        $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

        $accessToken_expiry = 1200;
        $refreshToken_expiry = 1209600;


    } catch (PDOException $ex) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessages("Logging issue");
        $response->send();
        exit();
    }

    try {

        $writeDb->beginTransaction();

        $query = $writeDb->prepare('UPDATE table_users SET loginattempts = 0 WHERE id = :id');
        $query->bindParam(":id", $user_id, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDb->prepare('INSERT INTO table_sessions(userid, accesstoken, refreshtoken, 
                          accesstokenexpiry, refreshtokenexpiry)VALUES (:userid,:accesstoken,:refreshtoken,
                        date_add(NOW(),INTERVAL :accesstokenexpiryseconds SECOND),date_add(NOW(),INTERVAL 
                              :refreshtokenexpiryseconds SECOND))');
        $query->bindParam(':userid', $user_id, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiryseconds', $accessToken_expiry, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refreshToken, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiryseconds', $refreshToken_expiry, PDO::PARAM_INT);
        $query->execute();

        $lastSessionId = $writeDb->lastInsertId();
        $writeDb->commit();

        $returnData = array();
        $returnData["sesssion_id"] = $lastSessionId;
        $returnData["access_token"] = $accessToken;
        $returnData["access_token_expiry"] = $accessToken_expiry;
        $returnData["refresh_token"] = $refreshToken;
        $returnData["refresh_token_expiry"] = $refreshToken_expiry;

        $response = new Response();
        $response->setHttpStatusCode(201);
        $response->setSuccess(true);
        $response->setData($returnData);
        $response->send();
        exit();


    } catch (PDOException $ex) {
        $writeDb->rollBack();
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessages("Logging issue");
        $response->send();
        exit();

    }


} else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->setMessages("Endpoint not found");
    $response->send();
    exit();
}