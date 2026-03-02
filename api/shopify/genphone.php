<?php
$areaCodes = [
    202, 
    212, 
    213, 
    312, 
    305, 
    415, 
    602, 
    404, 
    503, 
    617, 
    702, 
    214, 
    303, 
    313, 
    512, 
    615, 
];

function generatePhoneNumber() {
    global $areaCodes;
    $areaCode = $areaCodes[array_rand($areaCodes)];
    return sprintf("+1%d%03d%04d", $areaCode, rand(200, 999), rand(1000, 9999));
}

$phoneNumber = generatePhoneNumber();
?>
