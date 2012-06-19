<?php
include 'config.php';

$con = mysql_connect($HOST, $USER, $PSWD);
if (!$con) die('Could not connect: ' . mysql_error());

mysql_selectdb($DB, $con);

if ($_POST['instant'] == "True") {
   $sql = ("CALL ck_update_totals(
           '$_POST[tstamp]',
           '$_POST[meter_id]',
           '$_POST[family_id]',
           '$_POST[awatts]',
           '$_POST[watthr]');");
} else {
   $sql = ("CALL ck_log_rate(
           '$_POST[tstamp]',
           '$_POST[meter_id]',
           '$_POST[family_id]',
           '$_POST[awatts]',
           '$_POST[watthr]');");
}
echo($sql);

if (!mysql_query($sql, $con)) {
  die('Error: ' . mysql_error());
}
            
mysql_close($con);
echo("Success");
?>