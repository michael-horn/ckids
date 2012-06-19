<?php
//--------------------------------------------------------------------------
include 'config.php';

function getMeterMax($sql, $rname) {
   global $HOST;
   global $USER;
   global $PSWD;
   global $DB;
   $house = 0;
   $meters = 0;
   
   $con = mysql_connect($HOST, $USER, $PSWD) or die('Could not connect: ' . mysql_error());
   mysql_selectdb($DB, $con);
   
   $result = mysql_query($sql, $con) or die($sql."<br/><br/>".mysql_error());
   while ($row = mysql_fetch_array($result)) {
      if ($row['meter_id'] == 0) {
         $house = $row[$rname];
      } else {
         $meters += $row[$rname];
      }
   }
   mysql_free_result($result);
   mysql_close($con);
   return max($house, $meters);
}


//------------------------------------------------
// Target date
//------------------------------------------------
$date = time();
if (isset($_GET[date])) {
   $date = strtotime($_GET[date]);
}
$dt_curr = date("Y-m-d", $date);


//------------------------------------------------
// Totals
//------------------------------------------------
$dt_curr = date("Y-m-d H:i:s", $date);
$watts = getMeterMax("call ck_curr_rate('$dt_curr', 100);", 'watts' );
$curr_cost = ($watts / 1000.0) * $COST_PER_KWH * 24 * 30.5;

$dt_curr = date("Y-m-d", $date);
$day_kwh = getMeterMax("call ck_day_total('$dt_curr', 100);", 'watthours') / 1000;
$day_cost = $day_kwh * $COST_PER_KWH;

$week_kwh = getMeterMax("call ck_week_total('$dt_curr', 100);", 'watthours') / 1000;
$week_cost = $week_kwh * $COST_PER_KWH;

$month_kwh = getMeterMax("call ck_month_total('$dt_curr', 100);", 'watthours') / 1000;
$month_cost = $month_kwh * $COST_PER_KWH;

?>
({
   watts      : "<?php echo $watts; ?>",
   curr_watts : "<?php echo number_format($watts); ?>",
   curr_cost  : "<?php echo number_format(round($curr_cost), 2); ?>",
   day_kwh    : "<?php echo number_format($day_kwh, 1); ?>",
   day_cost   : "<?php echo number_format($day_cost, 2); ?>",
   week_kwh   : "<?php echo number_format($week_kwh, 1); ?>",
   week_cost  : "<?php echo number_format($week_cost, 2); ?>",
   month_kwh  : "<?php echo number_format($month_kwh, 1); ?>",
   month_cost : "<?php echo number_format($month_cost, 2); ?>"
})