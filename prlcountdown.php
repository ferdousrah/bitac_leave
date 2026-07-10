<?php
session_start();
include('connection.php');

$getUserInfoQ = mysqli_query($con, "select * from user_list where user_id='$_SESSION[username]'");
$getUserInfoQRW = mysqli_fetch_assoc($getUserInfoQ);

$employeeID = $getUserInfoQRW['employee_id'];

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);


function calculatePRLDate($dateOfBirth) {
    $dob = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
    
    if (!$dob) {
        return "Invalid date format. Please use 'Y-m-d' format.";
    }
    
    $dob->modify('+59 years');
    
    // Return the PRL date in 'Y/m/d' format
    return $dob->format('Y/m/d');
}

function calculateTimeRemainingToPRL($dateOfBirth, $obj) {
    $prlDate = new DateTime(calculatePRLDate($dateOfBirth));
    $currentDate = new DateTime();
    
    if ($currentDate > $prlDate) {
        return [
            'years' => 0,
            'months' => 0,
            'days' => 0,
            'prl_date' => $prlDate->format('Y/m/d')
        ];
    }
    
    $interval = $currentDate->diff($prlDate);
    $prlDateReturn = $prlDate->format('d/m/Y');
    return [
        'years' => $obj->engToBn($interval->y),
        'months' => $obj->engToBn($interval->m),
        'days' => $obj->engToBn($interval->d),
        'prl_date' => $obj->engToBn($prlDateReturn)
    ];
}

$dateOfBirth = $getEmployeeInfoQRW['date_of_birth']; // Replace with the actual date of birth
$timeRemaining = calculateTimeRemainingToPRL($dateOfBirth, $obj);

header('Content-Type: application/json');
echo json_encode($timeRemaining);
?>
