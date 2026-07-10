<?php
function lang($phrase){
    static $lang = array(
        'TITLE' => 'ACI Motors - Service Management',
		'LOGO_LOGIN' => 'uploads/logo.png',
		'LOGO_TOP' => 'uploads/logo-top.png',
		'LOGO_ONLY' => 'uploads/logo.png',
        'COPYRIGHT' => ''
    );
    return $lang[$phrase];
}

?>