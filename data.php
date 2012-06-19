<?php
//--------------------------------------------------------------------------
date_default_timezone_set("America/Chicago");

$HOST = "localhost";
$USER = "root";
$PSWD = "t0k3n";
$DB   = "litl";


function yticks($max) {
   $interval = 10;
   if ($max < 50) {
      $interval = 10;
   } else if ($max < 100) {
      $interval = 20;
   } else if ($max < 250) {
      $interval = 50;
   } else if ($max < 500) {
      $interval = 100;
   } else if ($max < 1000) {
      $interval = 200;
   } else {
      $interval = 250;
   }
   $count = floor($max / $interval) + 1;
   $result = array();
   for ($i=0; $i<=$count; $i++) {
      $result[$i] = $i * $interval;
   }
   return $result; 
}


function xticks($hours) {
   if ($hours <= 12) {
      return "['12am', '1am', '2am', '3am', '4am', '5am', '6am', '7am', '8am', '9am', '10am', '11am', '']";
   } else {
      return "['12am', '', '2am', '', '4am', '', '6am', '', '8am', '', '10am', '', 'noon', '', '2pm', '', '4pm', '', '6pm', '', '8pm', '', '10pm', '', '']";
   }
}


//------------------------------------------------
// Database connection 
//------------------------------------------------
$con = mysql_connect($HOST, $USER, $PSWD);
if (!$con) die('Could not connect: ' . mysql_error());
mysql_selectdb($DB, $con);


//------------------------------------------------
// Display date
//------------------------------------------------
$date = time();
if (isset($_GET[date])) {
   $date = strtotime($_GET[date]);
}


//------------------------------------------------
// Display type (day | week | month)
//------------------------------------------------
$type = "day";
if (isset($_GET[type])) {
   $type = $_GET[type];
}


//------------------------------------------------
// SQL Query
//------------------------------------------------
if ($type == "day") {
   // 15 minute resolution (900 seconds)
   $dt_curr = date("Y-m-d", $date);
   $sql = "SELECT truncate(time_to_sec(time(tstamp)) / 900, 0) as bucket, " .
          "round(avg(watts)) as watts, " .
          "meter_id " .
          "FROM ck_readings " .
          "WHERE date(tstamp) = '$dt_curr' " .
          "GROUP BY bucket, meter_id " .
          "ORDER BY bucket, meter_id;";
}
else if ($type == "week") {
   // hourly resolution (3,600 seconds)
   $dt_curr = date("W", $date);
   $sql = "SELECT hour(tstamp) + (dayofweek(tstamp) - 1) * 24 as bucket, " .
          "meter_id, " .
          "round(avg(awatts)) as watts " .
          "FROM ck_readings " .
          "WHERE week(tstamp) = $dt_curr " .
          "GROUP BY bucket, meter_id " .
          "ORDER BY bucket, meter_id;";
}

   
//------------------------------------------------
// Query the database
//------------------------------------------------
$result = mysql_query($sql, $con);


//------------------------------------------------
// Produce JSON array from the results
//------------------------------------------------
$bucket = 0;
$data = "[";
$meters = array(0, 0, 0, 0, 0, 0);
$hmax = 0;  // whole home max
$mmax = 0;  // point source max
   
while($row = mysql_fetch_array($result)) {
   while ($bucket < $row['bucket']) {
      $data = $data . "[" . implode(",", $meters) . "], ";
      $bucket++;
      $hmax = max($hmax, $meters[0]);
      $meters[0] = 0;
      $mmax = max($mmax, array_sum($meters));
      $meters = array(0, 0, 0, 0, 0, 0);
   }
   $meter = $row['meter_id'];
   $watts = $row['watts'];
   $meters[$meter] = $watts;
}
$data = $data . "[" . implode(",", $meters) . "]]";
$yticks = yticks(max($hmax, $mmax));
echo "{";
echo "  yticks : [" . implode(", ", $yticks) . "], ";
echo "  ymax : " . $yticks[count($yticks) - 1] . ", ";
echo "  xticks : " . $yticks[count($yticks) - 1] . ", ";
echo "  data : " . $data;
echo "}";

mysql_close($con);
?>