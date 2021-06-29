<?php

use JetBrains\PhpStorm\NoReturn;

require_once('db.php');
require_once('../model/Image.php');
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

//Auth script start
function authorizationFun($writeDb)
{
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $message = '';
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $message = 'Access token is missing from header';
        } else {
            if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
                $message = 'Access token can\'t be black';
            }
        }
        responseFun(401, false, $message);
    }

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];
    try {

        $query = $writeDb->prepare('SELECT userid,accesstokenexpiry,useractive,loginattempts from table_sessions,
    table_users WHERE table_sessions.userid = table_users.id and accesstoken = :accesstoken');
        $query->bindParam(':accesstoken', $accessToken);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            responseFun(401, false, 'Invalid access token');
        }
        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_userId = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];

        if ($returned_useractive !== 'Y') {
            responseFun(401, false, 'User Not active');

        }
        if ($returned_loginattempts >= 3) {
            responseFun(401, false, 'User account is locked');
        }

        if (strtotime($returned_accesstokenexpiry) < time()) {
            responseFun(401, false, 'Access Token expired');

        }
        return $returned_userId;
    } catch (PDOException $ex) {
        responseFun(500, false, "Authenticating error");
    }
}

//Auth script end

function getImage($readDb, $taskId, $imageId, $return_userid)
{
    $query = $readDb->prepare("SELECT table_images.id, table_images.title,table_images.filename,table_images.mimetype,
     table_images.taskid FROM table_images,table_tasks WHERE table_images.id = :imageid
     and table_tasks.id = :taskId and table_tasks.userid = :userid and table_images.taskid = table_tasks.id");

    $query->bindParam(":imageid", $imageId, PDO::PARAM_INT);
    $query->bindParam(":taskId", $taskId, PDO::PARAM_INT);
    $query->bindParam(":userid", $return_userid, PDO::PARAM_INT);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        responseFun(404, false, "Image not found");
    }
    $image = null;

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
     }
     if ($image == null)
     {
         responseFun(404,false,"Image not found");
     }
     $image->returnImageFile();

}

function getImageAttributes($readDb, $taskId, $imageId, $return_userid)
{
    $query = $readDb->prepare("SELECT table_images.id, table_images.title,table_images.filename,table_images.mimetype,
     table_images.taskid FROM table_images,table_tasks WHERE table_images.id = :imageid
     and table_tasks.id = :taskId and table_tasks.userid = :userid and table_images.taskid = table_tasks.id");

    $query->bindParam(":imageid", $imageId, PDO::PARAM_INT);
    $query->bindParam(":taskId", $taskId, PDO::PARAM_INT);
    $query->bindParam(":userid", $return_userid, PDO::PARAM_INT);
    $query->execute();

    $rowCount = $query->rowCount();

    $imageArray = array();

    if ($rowCount === 0) {
        responseFun(404, false, "Image not found");
    }

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
        $imageArray[] = $image->getImageArrayData();
    }
    responseFun(200, true, null, true, $imageArray);


}

//function to upload image
function uploadImageRoute($readDb, $writeDb, $taskId, $return_userid)
{
    try {
        // check request content type header if it is multipart/form-data with boundary or not
        if (!isset($_SERVER['CONTENT_TYPE']) ||
            !str_contains($_SERVER['CONTENT_TYPE'], "multipart/form-data; boundary=")) {
            responseFun(400, false, "Content type header is not set to multipart/form-data with boundary");


        }
        // check that the given task id and userid is valid mean this user and task exist in database
        $query = $readDb->prepare('SELECT id FROM table_tasks WHERE id = :taskId and userid = :userId');
        $query->bindParam(":taskId", $taskId, PDO::PARAM_INT);
        $query->bindParam(":userId", $return_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        //
        if ($rowCount === 0) {
            responseFun(404, false, "Task not found");
        }
        //now check the attributes field has been provided
        if (!isset($_POST['attributes'])) {
            responseFun(400, false, "Attributes must be provided");
        }
        //check in attributes (title and filename) are provided in JSON
        if (!$jsonImageAttr = json_decode($_POST['attributes'])) {
            responseFun(400, false, "Attributes field is not a valid JSON");

        }
        if (!isset($jsonImageAttr->title) ||
            !isset($jsonImageAttr->filename) ||
            $jsonImageAttr->title == '' ||
            $jsonImageAttr->filename == '') {
            responseFun(400, false, "Title and Filename are mandatory");
        }
        if (str_contains($jsonImageAttr->filename, ".")) {
            responseFun(400, false, "Filename mustn't contain file extension");
        }

        //check filename is provided and error free
        if (!isset($_FILES['imagefile']) || $_FILES['imagefile']['error'] !== 0) {
            responseFun(500, false, "Image file upload unsuccessfully - 
            make sure you have selected a file");
        }

        //check uploaded file if this is valid image or not
        //getImagesize will return false if file is not a valid file
        $imageFileDetail = getimagesize($_FILES['imagefile']["tmp_name"]);

        if ($imageFileDetail == false) {
            responseFun(400, false, "Not a valid image file");
        }

        //check if file size if set and set limit of the image
        if (isset($_FILES['imagefile']['size']) && $_FILES['imagefile']['size'] < 5242880/*=5MB = 1024x5x5*/) {
            responseFun(400, false, "File must be under 5MB");
        }

        //check mimetype for valid and allowed mime type
        $allowedImageMimeTypes = array('image/jpeg', 'image/gif', 'image/png');
        if (!in_array($imageFileDetail['mime'], $allowedImageMimeTypes)) {
            responseFun(400, false, "File not supported");
        }

        $fileExtension = "";
        switch ($imageFileDetail['mime']) {
            case "image/jpeg":
                $fileExtension = ".jpg";
                break;
            case "image/gif":
                $fileExtension = ".gif";
                break;
            case "image/png":
                $fileExtension = ".png";
                break;
            default:
                break;
        }
        if ($fileExtension == "") {
            responseFun(400, false, "No Valid file extension found from mineType");
        }
        // after validation checks then move uploaded file to the correct folder
        // and name it with the name provided in the provided attributes
        //in actual we do not keep image in db we keep reference of image.
        //we create path of the physical image location. we move uploaded file from tmp location to proper location

        $image = new Image(null, $jsonImageAttr->title,
            $jsonImageAttr->filename . $fileExtension, $imageFileDetail['mime'], $taskId);
        $title = $image->getTitle();
        $newFileName = $image->getFilename();
        $mimeType = $image->getMimetype();

        //check image for same name for given task id and user id. if exists then send response and exit
        $query = $readDb->prepare("SELECT table_images.id FROM table_images, table_tasks 
                                WHERE table_images.taskid = table_tasks.id and 
                                table_tasks.id = :tasksId and table_tasks.userid = :userId and
                                table_images.filename = :filename");
        $query->bindParam(":tasksId", $taskId, PDO::PARAM_INT);
        $query->bindParam(":userId", $return_userid, PDO::PARAM_INT);
        $query->bindParam(":filename", $newFileName);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount !== 0) {
            responseFun(409, false, "A file with that filename already exists");
        }

        //insert image attributes into image table
        //rollback on insert or function fails
        $writeDb->beginTransaction();

        $query = $writeDb->prepare('INSERT INTO table_images(title,filename,mimetype,taskid)
                                    VALUE(:title,:filename,:mimetype,:taskid)');
        $query->bindParam(':title', $title, PDO::PARAM_STR);
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR);
        $query->bindParam(':mimetype', $mimeType, PDO::PARAM_STR);
        $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            if ($writeDb->inTransaction()) {
                $writeDb->rollBacl();
            }
            responseFun(500, false, "Failed to upload image");
        }

        $lastImageId = $writeDb->lastInsertId();

        $query = $writeDb->prepare("SELECT table_images.id,table_images.title,table_images.filename,
        table_images.mimetype, table_images.taskid FROM table_images, table_tasks WHERE table_images.id = :imageId and 
        table_tasks.id = :taskId and table_tasks.userid = :userId and table_images.taskid = table_tasks.id");
        $query->bindParam(":imageId", $lastImageId, PDO::PARAM_INT);
        $query->bindParam(":taskId", $taskId, PDO::PARAM_INT);
        $query->bindParam(":userId", $return_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            if ($writeDb->inTransaction()) {
                $writeDb->rollBack();
            }
            responseFun(500, false, "Failed to retrieve image attributes after upload - try uploading the image again");
        }

        $imageArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
            $imageArray[] = $image->getImageArrayData();
        }
        //now move uploaded file from temp location to correct location
        $image->saveImageFile($_FILES['imagefile']['tmp_name']);

        //once file moved to correct location then commit the image  insert attribute query to database
        // if something failed then it should be caught in the ImageException handler below and roll back the DB transaction

        $writeDb->commit();

        responseFun(201, true, "Image uploaded successfully", false, $imageArray);


    } catch (ImageException $ex) {
        if ($writeDb->inTransaction()) {
            $writeDb->rollBack();
        }
        responseFun(500, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("Database Query Error: " . $ex, 0);
        if ($writeDb->inTransaction()) {
            $writeDb->rollBack();
        }
        responseFun(500, false, "Failed to upload image");
    }

}

/*Functions End*/

try {
    $writeDb = DB::connectWriteDb();
    $readDb = DB::connectReadDb();

} catch (PDOException $ex) {
    error_log("Database connection error-" . $ex, 0);
    responseFun(500, false, "Database connection error");
}

$return_userId = authorizationFun($writeDb);

//tasks/1/images/5/attributes
if (array_key_exists('taskid', $_GET) && array_key_exists('imageid', $_GET) && array_key_exists('attributes', $_GET)) {
    $taskId = $_GET['taskid'];
    $imageId = $_GET['imageid'];
    if ($imageId === '' || !is_numeric($imageId) || $taskId === '' || !is_numeric($taskId)) {
        responseFun(400, false, "Image id od Task id can't be empty and must be numeric");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getImageAttributes($readDb, $taskId, $imageId, $return_userId);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

    } else {
        responseFun(405, false, "Request method not allowed");

    }

}
//tasks/1/images/2
elseif (array_key_exists('taskid', $_GET) && array_key_exists('imageid', $_GET)) {
    $taskId = $_GET['taskid'];
    $imageId = $_GET['imageid'];
    if ($imageId === '' || !is_numeric($imageId) || $taskId === '' || !is_numeric($taskId)) {
        responseFun(400, false, "Image id od Task id can't be empty and must be numeric");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getImage($readDb,$taskId,$imageId,$return_userId);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    } else {
        responseFun(405, false, "Request method not allowed");

    }

} //tasks/1/images
elseif (array_key_exists('taskid', $_GET) && !array_key_exists('imageid', $_GET)) {
    $taskId = $_GET['taskid'];
    if ($taskId === '' || !is_numeric($taskId)) {
        responseFun(400, false, "Task id can't be empty and must be numeric");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        uploadImageRoute($readDb, $writeDb, $taskId, $return_userId);
    } else {
        responseFun(405, false, "Request method not allowed");
    }

} else {
    responseFun(404, false, "End point not found");
}