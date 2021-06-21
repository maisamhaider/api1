<?php
require_once("db.php");
require_once("../model/Response.php");
require_once("../model/Task.php");

try {
    $writeDb = DB::ConnectWriteDb();
    $readDb = DB::ConnectReadDb();

} catch (PDOException $ex) {
    error_log("Connection error" . $ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessages("database connection error");
    $response->send();
    exit();

}


if (array_key_exists("taskid", $_GET)) {

    $taskId = $_GET['taskid'];
    if ($taskId == '' || !is_numeric($taskId)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessages("Task id can not be empty or non numeric");
        $response->send();
        exit();

    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = 'SELECT id,title,description,DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") 
                 as deadline,completed FROM table_tasks WHERE id = :taskId';
            $prepareStatement = $readDb->prepare($query);
            $prepareStatement->bindParam(':taskId', $taskId, PDO::PARAM_INT);
            $prepareStatement->execute();

            $rowCount = $prepareStatement->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessages("Task not found");
                $response->send();
                exit();

            }

            while ($row = $prepareStatement->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $tasksArray[] = $task->getTaskArray();
            }

            $returnArray = array();
            $returnArray['rowCount'] = $rowCount;
            $returnArray['tasks'] = $tasksArray;


            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setToCache(true);
            $response->setData($returnArray);
            $response->send();
            exit();

        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages($ex->getMessage());
            $response->send();
            exit();

        } catch (PDOException $ex) {
            error_log("database query error" . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages("Failed to get task");
            $response->send();
            exit();
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            $deleteQuery = 'DELETE FROM table_tasks WHERE id = :taskId';
            $prepareStatement = $writeDb->prepare($deleteQuery);
            $prepareStatement->bindParam(':taskId', $taskId, PDO::PARAM_INT);
            $prepareStatement->execute();

            $rowCount = $prepareStatement->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessages("Task not found");
                $response->send();
                exit();

            }

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setMessages("Task has been deleted");
            $response->send();
            exit();
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages("Failed to delete task");
            $response->send();
            exit();
        }


    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessages("Request method not allowed");
        $response->send();
        exit();
    }
} elseif (array_key_exists("completed", $_GET)) {

    $completed = $_GET['completed'];

    if ($completed !== 'Y' && $completed !== 'N') {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessages("Completed state must be Y or N");
        $response->send();
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $completedGetQuery = 'SELECT id,title,description,DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline,completed FROM table_tasks WHERE completed = :completed';
            $prepareStatement = $readDb->prepare($completedGetQuery);
            $prepareStatement->bindParam(':completed', $completed, PDO::PARAM_STR);
            $prepareStatement->execute();

            $rowCount = $prepareStatement->rowCount();

//            if ($rowCount === 0)
//            {
//                $response = new Response();
//                $response->setHttpStatusCode(404);
//                $response->setSuccess(false);
//                $response->setMessages("task not found");
//                $response->send();
//                exit();
//            }

            $tasksArray = array();

            while ($row = $prepareStatement->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $tasksArray[] = $task->getTaskArray();
            }

            $returnArray = array();
            $returnArray['rowCounts'] = $rowCount;
            $returnArray['tasks'] = $tasksArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setToCache(true);
            $response->setData($returnArray);
            $response->send();
            exit();

        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages($ex->getMessage());
            $response->send();
            exit();

        } catch (PDOException $ex) {
            error_log("database error" . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages("Failed to get task");
            $response->send();
            exit();
        }

    } else {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->setMessages("Request method not allowed");
        $response->send();
        exit();
    }
} elseif (array_key_exists("page", $_GET)) {

    $page = $_GET['page'];
    if ($page == '' || !is_numeric($page)) {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->setMessages("page mustn't be empty and must be numeric");
        $response->send();
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // want to show 20 task per page
        $tasksLimitPerPage = 20;

        try {
            //get total tasks to calculate pages based on these tasks
            $statement = $readDb->prepare('SELECT count(id) as totalNoOfTasks FROM table_tasks');
            $statement->execute();


            $rows = $statement->fetch(PDO::FETCH_ASSOC);

            $totalTasks = intval($rows['totalNoOfTasks']);

            // calculate pages based on task counts
            $noOfPages = ceil($totalTasks / $tasksLimitPerPage);
            echo $noOfPages;

            if ($noOfPages == 0) {
                $noOfPages = 1;
            }
            if ($page > $noOfPages) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessages("Page not found");
                $response->send();
                exit();
            }
            // this logic is start from 0 and then 20 and then 40, 60 , 80 so on based on pages,
            //per page has 20 tasks send page's tasks start from 20 and go to 40
            $offSet = ($page == 1 ? 0 : ($tasksLimitPerPage * ($page - 1)));

            $getTaskQuery = $readDb->prepare('SELECT id,title,description,DATE_FORMAT(deadline,"%d/%m/%Y %H:%i")
            as deadline,completed FROM table_tasks limit :tasksLimitPerPage offset :offset');
            $getTaskQuery->bindParam(":tasksLimitPerPage", $tasksLimitPerPage, PDO::PARAM_INT);
            $getTaskQuery->bindParam(":offset", $offSet, PDO::PARAM_INT);
            $getTaskQuery->execute();

            $rowCount = $getTaskQuery->rowCount();
//
            $tasksArray = array();

            while ($row = $getTaskQuery->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $tasksArray[] = $task->getTaskArray();
            }

            $returnArray = array();
            $returnArray['rows_per_page'] = $rowCount;
            $returnArray['total_tasks'] = $totalTasks;
            $returnArray['total_pages'] = $noOfPages;
            ($page < $noOfPages ? $returnArray['has_next_page'] = true : $returnArray['has_next_page'] = false);
            ($page > 1 ? $returnArray['has_previous_page'] = true : $returnArray['has_previous_page'] = false);
            $returnArray['tasks'] = $tasksArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setToCache(true);
            $response->setData($returnArray);
            $response->send();
            exit();


        } catch (PDOException $ex) {
            error_log("Query error-" . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages("Failed to load page");
            $response->send();
            exit();
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages($ex->getMessage());
            $response->send();
            exit();
        }


    } else {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->setMessages("Request method not allowed");
        $response->send();
        exit();

    }
} elseif (empty($_GET)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {


            $selectAll = 'SELECT id,title,description,DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline,completed FROM table_tasks';

            $prepareStatement = $readDb->prepare($selectAll);
            $prepareStatement->execute();

            $rowCount = $prepareStatement->rowCount();
            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessages("task not found");
                $response->send();
                exit();
            }

            $tasksArray = array();
            while ($row = $prepareStatement->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $tasksArray[] = $task->getTaskArray();
            }

            $returnArray = array();
            $returnArray['rowCount'] = $rowCount;
            $returnArray['tasks'] = $tasksArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setToCache(true);
            $response->setData($returnArray);
            $response->send();
            exit();


        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log("connection error-" . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages("unable to connect to database");
            $response->send();
            exit();
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        try {
            // first check if the content type is json or not
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessages("Content type must be in json format");
                $response->send();
                exit();
            }
            //get data or input that that passed in request body
            $rawPOSTData = file_get_contents('php://input');

            // check if the input is valid json and decoded_to_json successfully
            if (!$jsonData = json_decode($rawPOSTData)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessages("request body is not a valid json");
                $response->send();
                exit();
            }
            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->title) ? $response->setMessages("title field is mandatory and must be provided") : false);
                (!isset($jsonData->completed) ? $response->setMessages("complete state is mandatory and must be provided") : false);
                $response->send();
                exit();
            }

            $task = new Task(null
                , $jsonData->title
                , isset($jsonData->description) ? $jsonData->description : null
                , isset($jsonData->deadline) ? $jsonData->deadline : null
                , $jsonData->completed);

            $title = $task->getTitle();
            $description = $task->getDescription();
            $deadline = $task->getDeadline();
            $completed = $task->getCompleted();

            $insertQuery = $writeDb->prepare('INSERT INTO table_tasks(title,description,deadline,completed) 
                    VALUES (:title, :description, STR_TO_DATE(:deadline,\'%d/%m/%Y %H:%i\'),:completed)');
            $insertQuery->bindParam(":title", $title, PDO::PARAM_STR);
            $insertQuery->bindParam(":description", $description, PDO::PARAM_STR);
            $insertQuery->bindParam(":deadline", $deadline, PDO::PARAM_STR);
            $insertQuery->bindParam(":completed", $completed, PDO::PARAM_STR);
            $insertQuery->execute();

            $rowCount = $insertQuery->rowCount();
            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages("Failed to create task");
                $response->send();
                exit();
            }

            // now get last task id and send back response to client
            $lastTaskId = $writeDb->lastInsertId();

            $getTask = $readDb->prepare('SELECT id,title,description,DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline,
             completed FROM table_tasks WHERE id = :taskid');
            $getTask->bindParam(":taskid", $lastTaskId, PDO::PARAM_INT);
            $getTask->execute();

            $rowCount = $getTask->rowCount();
            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages("Failed to retrieve task after creation");
                $response->send();
                exit();
            }

            $tasksArray = array();
            while ($row = $getTask->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                $tasksArray[] = $task->getTaskArray();
            }

            $returnArray = array();
            $returnArray['rowCount'] = $rowCount;
            $returnArray['tasks'] = $tasksArray;

            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->setToCache(true);
            $response->setMessages("Task created");
            $response->setData($returnArray);
            $response->send();
            exit();


        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log("connection error-" . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages("unable to connect to database");
            $response->send();
            exit();

        }


    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessages("Request method not allowed");
        $response->send();
        exit();
    }


} else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->setMessages("End point not found");
    $response->send();
    exit();
}
