<?php
include('connection.php');

$software_title = $_POST['software_title'];

$scolor = $_POST['scolor'];

$company_name = $_POST['company_name'];

$address = $_POST['address'];

$phone = $_POST['phone'];

$email = $_POST['email'];

$table_heading_font_size = $_POST['table_heading_font_size'];

$default_page_size = $_POST['default_page_size'];

$table_data_font_size = $_POST['table_data_font_size'];

$form_label_font_size = $_POST['form_label_font_size'];

$form_input_font_size = $_POST['form_input_font_size'];

$form_label_text_transform = $_POST['form_label_text_transform'];

$form_heading_text_transform = $_POST['form_heading_text_transform'];

$target_dir = "uploads/";

$login_logo = $target_dir . basename($_FILES["login_logo"]["name"]);

if (move_uploaded_file($_FILES["login_logo"]["tmp_name"], $login_logo)) {
        $loginLogo =  basename($_FILES["login_logo"]["name"]);
    } else {
        $loginLogo = $_POST['prev_login_logo'];
    }


//.................


$header_logo = $target_dir . basename($_FILES["header_logo"]["name"]);

if (move_uploaded_file($_FILES["header_logo"]["tmp_name"], $header_logo)) {
        $headerLogo = basename( $_FILES["header_logo"]["name"]);
    } else {
        $headerLogo = $_POST['prev_header_logo'];
    }

//.................


$sidebarbg = $target_dir . basename($_FILES["sidebarbg"]["name"]);

if (move_uploaded_file($_FILES["sidebarbg"]["tmp_name"], $sidebarbg)) {
        $sidebarbg = basename( $_FILES["sidebarbg"]["name"]);
    } else {
        $sidebarbg = $_POST['prev_sidebarbg'];
    }

//.................


$companyLogo = $target_dir . basename($_FILES["companyLogo"]["name"]);

if (move_uploaded_file($_FILES["companyLogo"]["tmp_name"], $companyLogo)) {
        $companyLogo = basename( $_FILES["companyLogo"]["name"]);
    } else {
        $companyLogo = $_POST['prev_companyLogo'];
    }

//.................

$checkForExistQ = mysqli_query($con, "select * from template_settings where dataID=1");
$checkForExistQNumRows = mysqli_num_rows($checkForExistQ);

if($checkForExistQNumRows>0)
{
$insertQ = mysqli_query($con, "update `template_settings` set `sidebar_color`='$scolor',`sidebar_bg_img`='$sidebarbg',`software_title`='$software_title',`login_logo`='$login_logo',`header_logo`='$headerLogo',`company_name`='$company_name',`address`='$address',`phone`='$phone',`email`='$email',`companyLogo`='$companyLogo',`table_heading_font_size`='$table_heading_font_size',`default_page_size`='$default_page_size',`table_data_font_size`='$table_data_font_size',`form_label_font_size`='$form_label_font_size',`form_input_font_size`='$form_input_font_size',`form_label_text_transform`='$form_label_text_transform',`form_heading_text_transform`='$form_heading_text_transform' where dataID=1");

}
else
{
$insertQ = mysqli_query($con, "insert into `template_settings`(`sidebar_color`,`sidebar_bg_img`,`software_title`,`login_logo`,`header_logo`,`company_name`,`address`,`phone`,`email`,`companyLogo`,`table_heading_font_size`,`default_page_size`,`table_data_font_size`,`form_label_font_size`,`form_input_font_size`,`form_label_text_transform`,`form_heading_text_transform`) values('$scolor','$sidebarbg','$software_title','$login_logo','$headerLogo','$company_name','$address','$phone','$email','$companyLogo','$table_heading_font_size','$default_page_size','$table_data_font_size','$form_label_font_size','$form_input_font_size','$form_label_text_transform','$form_heading_text_transform')");
}

if($insertQ==1)
{
echo 1;
}
else
{
echo 0;
}


?>