<?php

//cached old result
if(isset($_GET['cache'])){ //{{{
  echo file_get_contents("/tmp/osm.gp2.html");
  exit;
} // }}}

$time_start = microtime(true);

$gpx_file="/tmp/guideposts.gpx";
$json_file="/tmp/guideposts.json";

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

function push_geojson(&$json, $id, $lon, $lat, $name, $desc){ //{{{
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
            'description' => $desc,
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
<header>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <script type="text/javascript" src="../js/jquery-1.11.2.min.js"></script>
  <script type="text/javascript" src="../js/jquery.jeditable.js"></script>
  <script type="text/javascript" charset="utf-8">
    $(function() {
      $(".click").editable("move.php",
        { tooltip : "Click to edit...",
          style   : "inherit"
        });
    });
  </script>
</header>
<style>
td { 
  border: 1px solid black;
}
tr.ok { background-color:#b0ffa0; }
tr.bad { background-color:#ffa090; }
tr.cor { background-color:#ff5010; }
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
<li><a href="./?cache">Show</a> last cached analyzed table</li>
<li><a href="./?get.gpx">Download GPX</a> with guideposts without correct photos $gpx_time</li>
<li><a href="./?get.json">Download JSON</a> with guideposts without correct photos $json_time</li>
<li><a href="stats.php">Show guideposts</a> statistics</li>
</ul>

EOF;

if(isset($_GET['fetch'])){ //{{{
  $query="WITH data AS (SELECT '";

  $response = file_get_contents('http://api.openstreetmap.cz/table/all?output=geojson');
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

  $query="SELECT id, ref, by, ST_AsText(geom) AS geom FROM hicheck.guideposts";
  $res = pg_query($query);
  while ($data = pg_fetch_object($res)) {
    //skip all unusable images based on tags
    if(isset($img_skip[$data->id])) continue;

    $gp[$data->id] = $data;
  }
  pg_free_result($res);
  //echo "Loaded guideposts from DB.<br/>\n";
  //ob_flush();

  $query="SELECT id, ST_AsText(geom) AS geom, tags->'ref' AS ref, tags->'name' AS name FROM nodes WHERE tags @> '\"information\"=>\"guidepost\"'::hstore ORDER BY id";
  $res = pg_query($query);
  while ($data = pg_fetch_object($res)) {
    $no[$data->id] = $data;
  }
  pg_free_result($res);
  //echo "Loaded nodes from DB.<br/>\n";
  //ob_flush();

  $query="SELECT n.id AS n_id, g.id AS g_id, ST_Distance_Sphere(n.geom, g.geom) AS dist
    FROM hicheck.guideposts AS g, (
      SELECT id, geom FROM nodes WHERE tags @> '\"information\"=>\"guidepost\"'::hstore
    ) AS n
    WHERE ST_Distance_Sphere(n.geom, g.geom) < $max_ok_distance;";
  $res = pg_query($query);
  while ($data = pg_fetch_object($res)) {
    $close[$data->n_id][] = $data;
  }
  pg_free_result($res);
  
  //prepare GPX header
  $gpx=fopen($gpx_file, 'w');
  fwrite($gpx, '<?xml version="1.0" encoding="utf-8" standalone="yes"?>'."\n");
  fwrite($gpx, '<gpx version="1.1" creator="Locus Android"'."\n");
  fwrite($gpx, '  xmlns="http://www.topografix.com/GPX/1/1"'."\n");
  fwrite($gpx, '  xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">'."\n");
  
  //build GeoJSON feature collection array
  $geojson = array(
    'type'      => 'FeatureCollection',
    'features'  => array()
  );

  echo "<p>Nodes with information=guidepost (".count($no).")</p>\n";

  $gp_class['ok']=0;
  $gp_class['cor']=0;
  $gp_class['bad']=0;

  echo "<table>";
  echo '<tr><th width="7%">node ID</th><th width="11%">node coord</th><th width="12%">node ref</th><th>images</th></tr>'."\n";
  foreach($no as $n){

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

        push_geojson($geojson, $n->id, $lat, $lon, $name, 'guidepost with image but no REF');
      }
    } else {
      //gp without image
      fwrite($gpx, "<wpt lat=\"$lat\" lon=\"$lon\">"."\n");
      fwrite($gpx, "<name>$name</name>"."\n");
      fwrite($gpx, "<desc>n".$n->id."</desc>"."\n");
      fwrite($gpx, '<sym>misc-sunny</sym>'."\n");
      fwrite($gpx, '</wpt>'."\n");

      push_geojson($geojson, $n->id, $lat, $lon, $name, 'guidepost without image');
    }

    //POINT(12.5956722222222 49.6313222222222)
    $geom = preg_replace('/POINT\(([-0-9.]{1,8})[0-9]* ([-0-9.]{1,8})[0-9]*\)/', '$2 $1', $n->geom);
    echo "<td><a href=\"http://openstreetmap.org/node/".$n->id."\">".$n->id."</a></td>";
    echo "<td><a target=\"hiddenIframe\" href=\"http://localhost:8111/load_object?objects=n".$n->id."\">".$geom."</a></td><td>".$n->ref."</td>";
    if(isset($close[$n->id])) {
      echo '<td>';
      foreach($close[$n->id] as $d){
        $g_id = $d->g_id;
        $dist = sprintf("%0.2f", $d->dist);
        echo '<a href="http://api.openstreetmap.cz/table/id/'.$g_id.'">'.$g_id.'</a>';
        echo '('.$dist.'m, '.$gp[$g_id]->ref.') ';

        $gp_used[$g_id] = 1;
      }
      //$cnt = count($close[$n->id]);
      //echo '<td>'.$cnt.'</td>'."\n";
      echo "\n";
    } else {
      echo '<td></td>'."\n";
    }
    echo "</tr>";
  }
  echo "</table>";

  echo "<p>Guideposts nodes (total:".count($no)
                             .", OK: ".$gp_class['ok']
                             .", have photo but no ref: ".$gp_class['cor']
                             .", missing photo and ref: ".$gp_class['bad']
                             .", have ref but no photo: ".(count($no) - $gp_class['ok'] - $gp_class['cor'] - $gp_class['bad'])
                             .")</p>\n";
  
  echo "<p>Guideposts photo entries (total:".count($gp).", used: ".count($gp_used).", unused: ".(count($gp)-count($gp_used)).")</p>\n";

  //save current stats to DB
  $node_total = count($no);
  $node_ok = $gp_class['ok'];
  $node_cor = $gp_class['cor'];
  $node_bad = $gp_class['bad'];
  $img_total = count($gp);
  $img_used = count($gp_used);

  $date = date('Ymd');
  $check_q = "SELECT id FROM hicheck.gp_stats WHERE date='$date'";
  if(pg_num_rows(pg_query($check_q))==0){
    pg_query("INSERT INTO hicheck.gp_stats (date, node_total, img_total, img_used, node_ok, node_bad, node_cor) 
    VALUES('$date','$node_total','$img_total','$img_used','$node_ok','$node_bad','$node_cor')");
  } else {
    pg_query("UPDATE hicheck.gp_stats SET node_total='$node_total',img_total='$img_total',img_used='$img_used',node_ok='$node_ok',node_bad='$node_bad',node_cor='$node_cor' WHERE date='$date'");
  }

  echo "<table>";
  echo "<tr><th>img ID</th><th>by</th><th>ref</th><th>coords SQL</th><th>coords POST</th></tr>";
  foreach($gp as $p){
    //skip used images and only left unused ones
    if(isset($gp_used[$p->id])) continue;

    $geom = preg_replace('/POINT\(([-0-9.]{1,9})[0-9]* ([-0-9.]{1,9})[0-9]*\)/', '$2 $1', $p->geom);
    echo "<tr>\n";
    echo '  <td><a href="http://api.openstreetmap.cz/table/id/'.$p->id.'">'.$p->id.'</a></td>';
    echo '  <td>'.$p->by.'</td>';
    echo '  <td>'.$p->ref.'</td>';
    echo '  <td id="gpimg'.$p->id.'" class="click">'.$geom.'</td>';
    echo '  <td id="gpapi'.$p->id.'" class="click">'.$geom.'</td>';
    echo "</tr>\n";
  }
  echo "</table>";

  fwrite($gpx, '</gpx>'."\n");
  fclose($gpx);

  $json=fopen($json_file, 'w');
  fwrite($json, json_encode($geojson, JSON_NUMERIC_CHECK));
  fclose($json);

} // }}}

$time_end = microtime(true);

printf("<p>Total execution time: %.04fs</p>\n",($time_end - $time_start));

echo <<<EOF
</body>
</html>
EOF;

pg_close($db);

