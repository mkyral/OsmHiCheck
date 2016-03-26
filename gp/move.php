<?php

if(isset($_POST['id'])){
  $id = substr($_POST['id'], 5);
  $val = $_POST['value'];

  if(is_numeric($val)){
    echo "moving $id to node number $val";
    exit;
  }

  if (strstr($val, ',')) {
    list($lat,$lon) = explode(',', $val);
  } else {
    list($lat,$lon) = explode(' ', $val);
  }
  echo " UPDATE `guidepost` SET `lat` = $lat, `lon` = $lon WHERE `id` = $id;";
}
?>

