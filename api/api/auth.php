<?php
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    include_once '../config/database.php';
    include_once '../class/students.php';

    $database = new Database();
    $db = $database->getConnection();

    $item = new Students($db);

    $item->students_cell = isset($_GET['students_cell']) ? $_GET['students_cell'] : die();
  
    $item->getSingleStudent();

    if($item->full_name != null){
        // create array
        $emp_arr = array(
			"status" => 1,
            "students_cell" =>  $item->students_cell,
            "full_name" => $item->full_name
        );
      
        http_response_code(200);
        echo json_encode($emp_arr);
    }
      
    else{
        http_response_code(404);
		$emp_arr = array(
			"status" => 0,
            "message" =>  'Student not found.'
        );
        echo json_encode($emp_arr);
    }
?>