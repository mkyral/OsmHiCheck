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
  } else {
    echo 'Unknown ID!';
  }
}
?>

