<?php

if(isset($_POST['id'])){
  $type = substr($_POST['id'], 0, 5);
  $id = substr($_POST['id'], 5);
  $val = $_POST['value'];

  if (strstr($val, ',')) {
    list($lat,$lon) = explode(',', $val);
  } else {
    list($lat,$lon) = explode(' ', $val);
  }

  if(!is_numeric($lat)){
    echo "Bad LAT given, not a number: $lat";
    exit;
  }
  if(!is_numeric($lon)){
    echo "Bad LAT given, not a number: $lon";
    exit;
  }

  if ($type == 'gpimg') {
    echo " UPDATE `guidepost` SET `lat` = $lat, `lon` = $lon WHERE `id` = $id;";
  } else if ($type == 'gpapi') {
    echo " POST /table/move/$id/$lat/$lon";

    $url = 'http://api.openstreetmap.cz/table/move';
    $fields = "id=$id&lat=$lat&lon=$lon";

    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_POST, 1);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields);

    $result = curl_exec($ch);
    curl_close($ch);

    echo ":$result";

  } else {
    echo 'Unknown ID!';
  }
}
?>

