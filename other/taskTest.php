<?php

require_once('../model/Task.php');

try {
    $task = new Task(1, "A", "Description here", "01/01/2019 12:00", "N");
    header('Content-type: application/json;charset=utf-8');
    echo json_encode($task->returnTaskAsArray());
} catch(TaskException $ex) {
    echo "Error: ".$ex->getMessage();
}