<?php

function converter($val)
{
$letters = str_split($val);


for($i=0;$i<sizeof($letters);$i++)
{

if($letters[$i]=='1')
{
$letters[$i] = "১";
}

else if($letters[$i]=='2')
{
$letters[$i] = "২";
}

else if($letters[$i]=='3')
{
$letters[$i] = "৩";
}

else if($letters[$i]=='4')
{
$letters[$i] = "৪";
}

else if($letters[$i]=='5')
{
$letters[$i] = "৫";
}

else if($letters[$i]=='6')
{
$letters[$i] = "৬";
}

else if($letters[$i]=='7')
{
$letters[$i] = "৭";
}

else if($letters[$i]=='8')
{
$letters[$i] = "৮";
}

else if($letters[$i]=='9')
{
$letters[$i] = "৯";
}

else if($letters[$i]=='0')
{
$letters[$i] = "০";
}


echo $letters[$i];

}

/*

for($i=0;$i<sizeof($letters);$i++)
{

echo $letters[$i];

}


*/








}


function banglaNumber($val)
{
$letters = str_split($val);


for($i=0;$i<sizeof($letters);$i++)
{

if($letters[$i]=='1')
{
$letters[$i] = "১";
}

else if($letters[$i]=='2')
{
$letters[$i] = "২";
}

else if($letters[$i]=='3')
{
$letters[$i] = "৩";
}

else if($letters[$i]=='4')
{
$letters[$i] = "৪";
}

else if($letters[$i]=='5')
{
$letters[$i] = "৫";
}

else if($letters[$i]=='6')
{
$letters[$i] = "৬";
}

else if($letters[$i]=='7')
{
$letters[$i] = "৭";
}

else if($letters[$i]=='8')
{
$letters[$i] = "৮";
}

else if($letters[$i]=='9')
{
$letters[$i] = "৯";
}

else if($letters[$i]=='0')
{
$letters[$i] = "০";
}



}

$banglaNumber = "";

for($i=0;$i<sizeof($letters);$i++)
{

$banglaNumber = $banglaNumber.$letters[$i];

}





return $banglaNumber;





}




function bn2enNumber ($number){
    $search_array= array("১", "২", "৩", "৪", "৫", "৬", "৭", "৮", "৯", "০");
    $replace_array= array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
    $en_number = str_replace($search_array, $replace_array, $number);

    return $en_number;
}





?>

