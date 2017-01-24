<?php

function show_nodes($where, $class){ //{{{

  $query="SELECT nodeid, ref, ST_AsText(geom) AS geom, img FROM hicheck.gp_analyze WHERE ".$where;
  $res = pg_query($query);

  echo "<p>".pg_num_rows($res)." entries in table</p>\n";

  echo "<table>";
  echo '<tr><th width="7%">node ID</th><th width="11%">node coord</th><th width="12%">node ref</th><th>images</th></tr>'."\n";

  while ($row = pg_fetch_object($res)) {
    //check for row class - OK (have ref and img)
    echo '<tr class="'.$class.'">'."\n";

    $geom = preg_replace('/POINT\(([-0-9.]{1,8})[0-9]* ([-0-9.]{1,8})[0-9]*\)/', '$2&nbsp$1', $row->geom);
    echo "<td><a href=\"http://openstreetmap.org/node/".$row->nodeid."\">".$row->nodeid."</a></td>\n";
    echo "<td><a target=\"hiddenIframe\"
    href=\"http://localhost:8111/load_object?objects=n".$row->nodeid."\">".$geom."</a></td>\n";
    echo "<td>".$row->ref."</td>\n";
    echo '<td>';
    foreach(explode('|', $row->img) as $i){
      if($i == "") continue;

      list($imgid,$dist,$imgref) = explode(':', $i);
      echo '<a href="http://api.openstreetmap.cz/table/id/'.$imgid.'">'.$imgid.'</a> ('.sprintf("%0.2f", $dist)."m, $imgref) ";
    }
    echo "</td>\n";

    echo "</tr>\n";
  }
  pg_free_result($res);

  echo "</table>";
} //}}}

function show_unused_img(){ //{{{
  global $img_file;

  $img_ids = rtrim(file_get_contents($img_file), ', ');
  //format is NUM, NUM, NUM, 
  if(!preg_match('/^[0-9, ]*$/', $img_ids) || strlen($img_ids) == 0) {
    //bad cache file found, run analyze
      echo '<p>Bad unused image info found, run analyze to generate from scratch!</p>';
    return;
  }

  $query="SELECT id, by, ref, ST_AsText(geom) AS geom FROM hicheck.guideposts WHERE id IN (".$img_ids.")";
  $res = pg_query($query);
  $total = pg_num_rows($res);

  echo "<p>".pg_num_rows($res)." entries in table</p>\n";
  echo "<table>";
  echo "<tr><th>img ID</th><th>by</th><th>ref</th><th>coords</th></tr>";

  while ($row = pg_fetch_object($res)) {
    $geom = preg_replace('/POINT\(([-0-9.]{1,9})[0-9]* ([-0-9.]{1,9})[0-9]*\)/', '$2 $1', $row->geom);
    echo "<tr>\n";
    echo '  <td><a href="http://api.openstreetmap.cz/table/id/'.$row->id.'">'.$row->id.'</a></td>';
    echo '  <td>'.$row->by.'</td>';
    echo '  <td>'.$row->ref.'</td>';
    echo '  <td id="gpimg'.$row->id.'">'.$geom.'</td>';
    echo "</tr>\n";
  }
  echo "</table>";

  pg_free_result($res);
} //}}}

//cached old result
if(isset($_GET['cache'])){ //{{{
  echo file_get_contents("/tmp/osm.gp2.html");
  exit;
} // }}}

$time_start = microtime(true);

$gpx_file="/tmp/guideposts.gpx";
$json_file="/tmp/guideposts.json";
$img_file="/tmp/unused_img.txt";

$gpx_time=file_exists($gpx_file) ? '(data from '.date('d.m.Y H:i', filemtime($gpx_file)).')' : '';
$json_time=file_exists($json_file) ? '(data from '.date('d.m.Y H:i', filemtime($json_file)).')' : '';
$db_time='(data from '.trim(file_get_contents("../last_update.txt")).')';

//output prepared GPX if any
if(isset($_GET['gpx']) || isset($_GET['get_gpx'])){ //{{{

  if(file_exists($gpx_file)){
    header('Content-Description: File Transfer');
    header('Content-Type: application/gpx+xml');
    header('Content-Disposition: attachment; filename="'.basename($gpx_file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: '.filesize($gpx_file));
    readfile($gpx_file);
    exit;
  } else {
    echo "Generate GPX by running analysis!\n";
  }
}
//}}}

//output prepared JSON if any
if(isset($_GET['json']) || isset($_GET['get_json'])){ //{{{
  
  //generate just part of json file
  if(isset($_GET['bbox'])){
    list($xmin,$ymin,$xmax,$ymax) = explode(',', $_GET['bbox']);
    $j = json_decode(file_get_contents($json_file));
    foreach($j->features as $key => $item){
      $coord = $item->geometry->coordinates;
      if ($xmin < $coord[0] && $xmax > $coord[0] && $ymin < $coord[1] && $ymax > $coord[1]) 
        ;//print_r($coord);
      else {
        //echo 'removing'.$item->properties->name;
        unset($j->features[$key]);
      }
    };
    $j->features = array_values($j->features);

    header('Content-Type: application/json');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    echo json_encode($j);
    exit;
  }

  //read the whole file of no bbox
  if(file_exists($json_file)){
    header('Content-Description: File Transfer');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="'.basename($json_file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: '.filesize($json_file));
    readfile($json_file);
    exit;
  } else {
    echo "Generate JSON by running analysis!\n";
  }
}
//}}}

function push_geojson(&$json, $id, $lon, $lat, $name, $class){ //{{{
 $feature = array(
        'id' => $id,
        'type' => 'Feature', 
        'geometry' => array(
            'type' => 'Point',
            # Pass Longitude and Latitude Columns here
            'coordinates' => array($lon, $lat)
        ),
        # Pass other attribute columns here
        'properties' => array(
            'name' => $name,
            'class' => $class,
            )
        );
    # Add feature arrays to feature collection array
    array_push($json['features'], $feature);
} //}}}

require_once dirname(__FILE__).'/../db_conf.php';
$db = pg_connect("host=".SERVER." dbname=".DATABASE." user=".USERNAME." password=".PASSWORD);

$max_ok_distance = 20;

echo <<<EOF
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<style>
td { 
  border: 1px solid black;
}
.ok { background-color:#b0ffa0; }
.bad { background-color:#ffa090; }
.cor { background-color:#ff5010; }
table { 
  border-collapse: collapse;
  font-size: 9pt;
}
/* iframe for josm and rawedit links */
iframe#hiddenIframe {
    display: none;
      position: absolute;
}
</style>
<body>
<iframe id="hiddenIframe" name="hiddenIframe"></iframe>

<p>Max node and img distance: $max_ok_distance m</p>

<ul>
<li><a href="./?fetch">Fetch</a> DB from api.osm.cz to osm.fit.vutbr.cz</li>
<li><a href="./?analyse">Analyse</a> current DB on osm.fit.vutbr.cz $db_time</li>
<li>
  <a href="./?all">Show</a> last cached analyzed table 
  (<a href="./?ok">OK</a>, <a href="./?bad">noref+noimg</a>,
  <a href="./?cor">noref+img</a>, <a href="./?img">unused img</a>)
</li>
<li><a href="./?get.gpx">Download GPX</a> with guideposts without correct photos $gpx_time</li>
<li><a href="./?get.json">Download JSON</a> with guideposts without correct photos $json_time</li>
<li><a href="stats.php">Show guideposts</a> statistics</li>
</ul>

EOF;

if(isset($_GET['fetch'])){ //{{{
  $query="WITH data AS (SELECT '";

  $response = file_get_contents('http://api.openstreetmap.cz/table/all?output=geojson');
  #for over 5000 photos, it should be much more then 10kB
  if(strlen($response) < 10000){
    echo 'Too short reponse from api.osm.cz, ignoring!';
    return;
  }
  $query .= $response;

  $query .= "'::json AS fc)
    INSERT INTO hicheck.guideposts (\"id\", \"by\", \"url\", \"ref\", \"geom\") (
    SELECT 
      CAST (feat->'properties'->>'id' AS int),
      feat->'properties'->>'attribution' AS by,
      feat->'properties'->>'url',
      feat->'properties'->>'ref',
      ST_SetSRID(ST_GeomFromGeoJSON(feat->>'geometry'), 4326)
    FROM (
      SELECT json_array_elements(fc->'features') AS feat
      FROM data
    ) AS f
    );";
  //echo $query;

  $res = pg_query($db, 'TRUNCATE TABLE "hicheck"."guideposts";');
  pg_free_result($res);
  $res = pg_query($query);
  echo "Proceesed and inserted ".pg_affected_rows($res)." entries.";
  pg_free_result($res);
} // }}}

if(isset($_GET['analyse'])){ //{{{
  
  //skip infotables from unused guideposts
  $infotab = json_decode(file_get_contents('http://api.openstreetmap.cz/table/hashtag/infotabule?output=json'));
  foreach($infotab as $i){
    $img_skip[$i[0]] = 1;
  }
  //skip maps from unused guideposts
  $maps = json_decode(file_get_contents('http://api.openstreetmap.cz/table/hashtag/mapa?output=json'));
  foreach($maps as $i){
    $img_skip[$i[0]] = 1;
  }
  //skip cyklo GPs from unused guideposts
  $cyklo = json_decode(file_get_contents('http://api.openstreetmap.cz/table/hashtag/cyklo?output=json'));
  foreach($cyklo as $i){
    $img_skip[$i[0]] = 1;
  }
  //skip unreadable GP images
  $unread = json_decode(file_get_contents('http://api.openstreetmap.cz/table/hashtag/necitelne?output=json'));
  foreach($unread as $i){
    $img_skip[$i[0]] = 1;
  }
  //skip marking with no GP on img
  $mark = json_decode(file_get_contents('http://api.openstreetmap.cz/table/hashtag/znaceni?output=json'));
  foreach($mark as $i){
    $img_skip[$i[0]] = 1;
  }
  //skip GP tagged zruseno
  $mark = json_decode(file_get_contents('http://api.openstreetmap.cz/table/hashtag/zruseno?output=json'));
  foreach($mark as $i){
    $img_skip[$i[0]] = 1;
  }

  $query="SELECT id, ref, by, ST_AsText(geom) AS geom FROM hicheck.guideposts
          WHERE geom && ST_MakeEnvelope(12.09, 51.06, 18.87, 48.55, 4326)";
  $res = pg_query($query);
  while ($data = pg_fetch_object($res)) {
    //skip all unusable images based on tags
    if(isset($img_skip[$data->id])) continue;

    $gp[$data->id] = $data;
  }
  pg_free_result($res);
  echo "Loaded ".count($gp)." guideposts from DB.<br/>\n";

  $query="SELECT id, ST_AsText(geom) AS geom, tags->'ref' AS ref, tags->'name' AS name FROM nodes WHERE tags @> '\"information\"=>\"guidepost\"'::hstore ORDER BY id";
  $res = pg_query($query);
  while ($data = pg_fetch_object($res)) {
    $nodes[$data->id] = $data;
  }
  pg_free_result($res);
  echo "Loaded ".count($nodes)." nodes from DB.<br/>\n";

  $query="SELECT n.id AS n_id, g.id AS g_id, ST_DistanceSphere(n.geom, g.geom) AS dist
    FROM hicheck.guideposts AS g, (
      SELECT id, geom FROM nodes WHERE tags @> '\"information\"=>\"guidepost\"'::hstore
    ) AS n
    WHERE ST_DistanceSphere(n.geom, g.geom) < $max_ok_distance;";
  $res = pg_query($query);
  while ($data = pg_fetch_object($res)) {
    $close[$data->n_id][] = $data;
  }
  pg_free_result($res);
  
  //prepare GPX header
  $gpx=fopen($gpx_file, 'w');
  fwrite($gpx, '<?xml version="1.0" encoding="utf-8" standalone="yes" '.'?'.">\n");
  fwrite($gpx, '<gpx version="1.1" creator="OsmHiCheck"'."\n");
  fwrite($gpx, '  xmlns="http://www.topografix.com/GPX/1/1"'."\n");
  fwrite($gpx, '  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n");
  fwrite($gpx, '  xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">'."\n");
  
  //build GeoJSON feature collection array
  $geojson = array(
    'type'      => 'FeatureCollection',
    'features'  => array()
  );

  pg_query("TRUNCATE TABLE hicheck.gp_analyze");

  $gp_class['ok']=0;
  $gp_class['cor']=0;
  $gp_class['bad']=0;

  echo "<table>";
  echo '<tr><th width="7%">node ID</th><th width="11%">node coord</th><th width="12%">node ref</th><th>images</th></tr>'."\n";
  foreach($nodes as $n){

    //check for row class - OK (have ref and img), BAD, CORRECT
    echo "<tr";
    if(!isset($n->ref)){
      if (isset($close[$n->id])){ $gp_class['cor']++; echo ' class="cor"'; } else { $gp_class['bad']++; echo ' class="bad"'; }
    } else {
      if (isset($close[$n->id])){ $gp_class['ok']++; echo ' class="ok"'; }
    }
    echo ">";

    //check if need to put to GPX and GeoJSON
    preg_match('/POINT\(([-0-9.]{1,8})[0-9]* ([-0-9.]{1,8})[0-9]*\)/', $n->geom, $matches);
    $lon = $matches[1];
    $lat = $matches[2];
    $name = isset($n->name) ? htmlspecialchars($n->name) : 'n'.$n->id;
    if(isset($close[$n->id])) {
      if (!isset($n->ref)){
        //gp with image but without REF
        fwrite($gpx, "<wpt lat=\"$lat\" lon=\"$lon\">"."\n");
        fwrite($gpx, "<name>$name</name>"."\n");
        fwrite($gpx, "<desc>n".$n->id."</desc>"."\n");
        fwrite($gpx, '<sym>transport-accident</sym>'."\n");
        fwrite($gpx, '</wpt>'."\n");

        push_geojson($geojson, $n->id, $lon, $lat, $name, 'noref');
      }
    } else {
      //gp without image
      fwrite($gpx, "<wpt lat=\"$lat\" lon=\"$lon\">"."\n");
      fwrite($gpx, "<name>$name</name>"."\n");
      fwrite($gpx, "<desc>n".$n->id."</desc>"."\n");
      fwrite($gpx, '<sym>misc-sunny</sym>'."\n");
      fwrite($gpx, '</wpt>'."\n");

      if (!isset($n->ref)){
        //GP without photo and ref
        push_geojson($geojson, $n->id, $lon, $lat, $name, 'missing');
      } else {
        //GP without image but with some ref
        push_geojson($geojson, $n->id, $lon, $lat, $name, 'noimg');
      }
    }

    //POINT(12.5956722222222 49.6313222222222)
    $geom = preg_replace('/POINT\(([-0-9.]{1,8})[0-9]* ([-0-9.]{1,8})[0-9]*\)/', '$2 $1', $n->geom);
    $img = '';
    echo "<td><a href=\"http://openstreetmap.org/node/".$n->id."\">".$n->id."</a></td>";
    echo "<td><a target=\"hiddenIframe\" href=\"http://localhost:8111/load_object?objects=n".$n->id."\">".$geom."</a></td><td>".$n->ref."</td>";
    if(isset($close[$n->id])) {
      echo '<td>';
      foreach($close[$n->id] as $d){
        $g_id = $d->g_id;
        $ref = isset($gp[$g_id]) ? $gp[$g_id]->ref : '';
        $dist = sprintf("%0.2f", $d->dist);
        $img .= "$g_id:".$d->dist.":$ref|";

        echo '<a href="http://api.openstreetmap.cz/table/id/'.$g_id.'">'.$g_id.'</a>';
        echo '('.$dist.'m, '.$ref.') ';

        if(!isset($img_skip[$g_id])) $gp_used[$g_id] = 1;
      }
      //$cnt = count($close[$n->id]);
      //echo '<td>'.$cnt.'</td>'."\n";
      echo "\n";
    } else {
      echo '<td></td>'."\n";
    }
    echo "</tr>";
    
    //put result into a table
    $query = "INSERT INTO hicheck.gp_analyze (nodeid, geom, ref, img) VALUES('"
              .$n->id."',ST_GeomFromText('".$n->geom."', 4326),'".$n->ref."','$img')";
    //error_log("Q: $query");
    $res = pg_query($query);
  }
  echo "</table>";

  //save current stats to DB
  $node_total = count($nodes);
  $node_ok = $gp_class['ok'];
  $node_cor = $gp_class['cor'];
  $node_bad = $gp_class['bad'];
  $img_total = count($gp);
  $img_used = count($gp_used);

  echo "<p>Guideposts nodes (total:".$node_total
             .', <span class="ok">OK: '.$node_ok.'</span>'
             .', <span class="cor">have photo but no ref: '.$node_cor.'</span>'
             .', <span class="bad">missing photo and ref: '.$node_bad.'</span>'
             .', have ref but no photo: '.($node_total - $node_ok - $node_cor - $node_bad)
             .")</p>\n";
  
  echo "<p>Guideposts photo entries (total:".$img_total.", used: ".$img_used.", unused: ".($img_total - $img_used).")</p>\n";

  $date = date('Ymd');
  $check_q = "SELECT id FROM hicheck.gp_stats WHERE date='$date'";
  if(pg_num_rows(pg_query($check_q))==0){
    pg_query("INSERT INTO hicheck.gp_stats (date, node_total, img_total, img_used, node_ok, node_bad, node_cor) 
    VALUES('$date','$node_total','$img_total','$img_used','$node_ok','$node_bad','$node_cor')");
  } else {
    pg_query("UPDATE hicheck.gp_stats SET node_total='$node_total',img_total='$img_total',img_used='$img_used',node_ok='$node_ok',node_bad='$node_bad',node_cor='$node_cor' WHERE date='$date'");
  }

  $uimg=fopen($img_file, 'w');

  echo "<table>";
  echo "<tr><th>img ID</th><th>by</th><th>ref</th><th>coords</th></tr>";
  foreach($gp as $p){
    //skip used images and only left unused ones
    if(isset($gp_used[$p->id])) continue;

    //save to a cache file
    fwrite($uimg, $p->id.", ");

    $geom = preg_replace('/POINT\(([-0-9.]{1,9})[0-9]* ([-0-9.]{1,9})[0-9]*\)/', '$2 $1', $p->geom);
    echo "<tr>\n";
    echo '  <td><a href="http://api.openstreetmap.cz/table/id/'.$p->id.'">'.$p->id.'</a></td>';
    echo '  <td>'.$p->by.'</td>';
    echo '  <td>'.$p->ref.'</td>';
    echo '  <td id="gpimg'.$p->id.'">'.$geom.'</td>';
    echo "</tr>\n";
  }
  echo "</table>";
  
  fclose($uimg);

  fwrite($gpx, '</gpx>'."\n");
  fclose($gpx);

  $json=fopen($json_file, 'w');
  fwrite($json, json_encode($geojson, JSON_NUMERIC_CHECK));
  fclose($json);

} // }}}

//show all OSM nodes
if(isset($_GET['all'])){ //{{{
  $gp_class['ok']=0;
  $gp_class['cor']=0;
  $gp_class['bad']=0;

  echo "<table>";
  echo '<tr><th width="7%">node ID</th><th width="11%">node coord</th><th width="12%">node ref</th><th>images</th></tr>'."\n";

  $query="SELECT nodeid, ref, ST_AsText(geom) AS geom, img FROM hicheck.gp_analyze";
  $res = pg_query($query);
  $total = pg_num_rows($res);
  while ($row = pg_fetch_object($res)) {
    //check for row class - OK (have ref and img)
    echo "<tr";
    if($row->ref != "" && $row->img != ""){
      $gp_class['ok']++; echo ' class="ok"';
    } 
    if ($row->ref == "" && $row->img == ""){
      $gp_class['bad']++; echo ' class="bad"';
    }
    if ($row->ref == "" && $row->img != ""){
      $gp_class['cor']++; echo ' class="cor"';
    }
    echo ">\n";

    $geom = preg_replace('/POINT\(([-0-9.]{1,8})[0-9]* ([-0-9.]{1,8})[0-9]*\)/', '$2&nbsp$1', $row->geom);
    echo "<td><a href=\"http://openstreetmap.org/node/".$row->nodeid."\">".$row->nodeid."</a></td>\n";
    echo "<td><a target=\"hiddenIframe\"
    href=\"http://localhost:8111/load_object?objects=n".$row->nodeid."\">".$geom."</a></td>\n";
    echo "<td>".$row->ref."</td>\n";
    echo '<td>';
    foreach(explode('|', $row->img) as $i){
      if($i == "") continue;

      list($imgid,$dist,$imgref) = explode(':', $i);
      echo '<a href="http://api.openstreetmap.cz/table/id/'.$imgid.'">'.$imgid.'</a> ('.sprintf("%0.2f", $dist)."m, $imgref) ";
    }
    echo "</td>\n";

    echo "</tr>\n";
  }
  pg_free_result($res);

  echo "</table>";

  echo "<p>Guideposts nodes (total:".$total
                             .', <span class="ok">OK: '.$gp_class['ok'].'</span>'
                             .', <span class="cor">have photo but no ref: '.$gp_class['cor'].'</span>'
                             .', <span class="bad">missing photo and ref: '.$gp_class['bad'].'</span>'
                             .', have ref but no photo: '.($total - $gp_class['ok'] - $gp_class['cor'] - $gp_class['bad'])
                             .")</p>\n";
  
  show_unused_img();
  exit;
} // }}}

//show OK OSM nodes
if(isset($_GET['ok'])){ //{{{
  show_nodes("ref != '' AND img != ''", "ok");
  exit;
} // }}}

//show BAD OSM nodes
if(isset($_GET['bad'])){ //{{{
  show_nodes("ref = '' AND img = ''", "bad");
  exit;
} // }}}

//show CHECK OSM nodes
if(isset($_GET['cor'])){ //{{{
  show_nodes("ref = '' AND img != ''", "cor");
  exit;
} // }}}

//show unused images
if(isset($_GET['img'])){ //{{{
  show_unused_img();
  exit;
} // }}}

$time_end = microtime(true);

printf("<p>Total execution time: %.04fs</p>\n",($time_end - $time_start));

echo <<<EOF
</body>
</html>
EOF;

pg_close($db);

