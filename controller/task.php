<?php

require_once('db.php');
require_once('../model/Task.php');
require_once('../model/Response.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - ".$ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error!");
    $response->send();
    exit;
}

if(array_key_exists("taskid",$_GET)) {
    $taskid = $_GET['taskid'];

    if($taskid == '' || !is_numeric($taskid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank and must be numeric!");
        $response->send();
        exit;
    }

    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Task not found!");
                $response->send();
                exit;
            }

            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;

        } catch(TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch(PODException $ex) {
            error_log("Database query error - ".$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get task!");
            $response->send();
            exit;
        } 

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            $query = $writeDB->prepare('DELETE FROM tbltasks WHERE id = :taskid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if($rowCount == 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Task not found!");
                $response->send();
                exit;
            }

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Task deleted!");
            $response->send();
            exit;

        } catch(PDOExcpetion $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to delete task!");
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        try {
            // echo($_SERVER['CONTENT_TYPE']);
            // echo '<pre>';
            // print_r(getallheaders());
            // $allHeaders = getallheaders();
            // $contentType = $allHeaders['Content-Type'];
            if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content Type header not set to JSON!");
                $response->send();
                exit;
            }
    
            $rawPatchData = file_get_contents('php://input');
            if(!$jsonData = json_decode($rawPatchData)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON!");
                $response->send();
                exit;
            }
    
            $title_updated = false;
            $description_updated = false;
            $deadline_updated = false;
            $completed_updated = false;
    
            $queryFields = "";
            
            if(isset($jsonData->title)) {
                $title_updated = true;
                $queryFields .= "title = :title, ";
            }
    
            if(isset($jsonData->description)) {
                $description_updated = true;
                $queryFields .= "description = :description, ";
            }
    
            if(isset($jsonData->deadline)) {
                $deadline_updated = true;
                $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
            }
    
            if(isset($jsonData->completed)) {
                $completed_updated = true;
                $queryFields .= "completed = :completed, ";
            }
    
            $queryFields = rtrim($queryFields, ", ");
            
            if($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No task fields provided!");
                $response->send();
                exit;
            }
    
            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->execute();
    
            $rowCount = $query->rowCount();
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No task found to update!");
                $response->send();
                exit;
            }
    
            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
            }
    
            $queryString = "UPDATE tbltasks set ".$queryFields." WHERE id = :taskid";
            $query = $writeDB->prepare($queryString);
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
    
            if($title_updated === true) {
                $task->setTitle($jsonData->title);
                $up_title = $task->getTitle();
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }
            if($description_updated === true) {
                $task->setDescription($jsonData->description);
                $up_description = $task->getDescription();
                $query->bindParam(':description', $up_description, PDO::PARAM_STR);
            }
            if($deadline_updated === true) {
                $task->setDeadline($jsonData->deadline);
                $up_deadline = $task->getDeadline();
                $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
            }
            if($completed_updated === true) {
                $task->setCompleted($jsonData->completed);
                $up_completed = $task->getCompleted();
                $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
            }
            $query->execute();
    
            $rowCount = $query->rowCount();
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Task not updated!");
                $response->send();
                exit;
            }
    
            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->execute();
    
            $rowCount = $query->rowCount();
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No task found after update!");
                $response->send();
                exit;
            }
    
            $taskArray = array();
    
            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }
    
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;
    
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(false);
            $response->addMessage("Task updated!");
            $response->setData($returnData);
            $response->send();
            exit;
    
        } catch(TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
            
        } catch(PDOException $ex) {
            error_log("Database query error - ".$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to update task!");
            $response->send();
            echo($ex);
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed!");
        $response->send();
        exit;
    }
} elseif(array_key_exists("completed",$_GET)) {
    $completed = $_GET['completed'];
    if($completed !== 'Y' && $completed !== 'N') {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Completed filter must be Y or N!");
        $response->send();
        exit;
    }

    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks WHERE completed = :completed');
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;

        } catch(TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex-getMessage());
            $response->send();
            exit;
        } catch(PDOException $ex) {
            error_log("Database query error - ".$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get tasks!");
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} elseif(array_key_exists("page",$_GET)) {
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        $page = $_GET['page'];
        if($page == '' || !is_numeric($page)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Page number cannot be blank and must be numeric!");
            $response->send();
            exit;
        }

        $limitPerPage = 20;
        try {
            $query = $readDB->prepare('SELECT count(id) AS totalNoOfTasks FROM tbltasks');
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $tasksCount = intval($row['totalNoOfTasks']);

            $numOfPages = ceil($tasksCount/$limitPerPage);

            if($numOfPages == 0) {
                $numOfPages == 1;
            }

            if($page > $numOfPages || $page == 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Page not found!");
                $response->send();
                exit;
            }

            $offset = ($page == 1 ? 0 : ($limitPerPage*($page-1)));
            
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks LIMIT :pglimit offset :offset');
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();
            
            $rowCount = $query->rowCount();

            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $tasksCount;
            $returnData['total_pages'] = $numOfPages;
            ($page < $numOfPages ? $returnData['hasNextPage'] = true : $returnData['hasNextPage'] = false);
            ($page > 1 ? $returnData['hasPreviousPage'] = true : $returnData['hasPreviousPage'] = false);
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch(TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch(PDOException $ex) {
            error_log("Database query error - ".$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get tasks!");
            $response->send();
            exit;
        }

    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowes!");
        $response->send();
        exit;
    }
} elseif(empty($_GET)) {
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks');
            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch(TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch(PDOException $ex) {
            error_log("Database query error - ".$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get tasks!");
            $response->send();
            exit;
        }
    } elseif($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content type header is not set to JSON!");
                $response->send();
                exit;
            }

            $rawPOSTData = file_get_contents('php://input');

            if(!$jsonData = json_decode($rawPOSTData)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON!");
                $response->send();
                exit;
            }

            if(!isset($jsonData->title) || !isset($jsonData->completed)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory!") : false);
                (!isset($jsonData->completed) ? $response->addMessage("Completed field is mandatory!") : false);
                $response->send();
                exit;
            }

            $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);
            $title = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline = $newTask->getDeadline();
            $completed = $newTask->getCompleted();
            $query = $writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed) VALUES (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed)');
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to create task!");
                $response->send();
                exit;
            }

            $lastTaskID = $writeDB->lastInsertId();

            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid');
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to retrieve task after creation!");
                $response->send();
                exit;
            }

            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("Task created!");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch(TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch(PDOException $ex) {
            error_log("Database query error - ".$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to insert task into database!");
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint not found!");
    $response->send();
    exit;
}