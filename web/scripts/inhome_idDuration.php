<?php
require_once 'duration_generator.class.php';

$begin_date = htmlspecialchars($_POST['from']);
$end_date = htmlspecialchars($_POST['to']);
$rank = htmlspecialchars($_POST['rank']);

$inhome_id = new duration_generator('inhome_id', $rank, $begin_date, $end_date);

$smin = htmlspecialchars($_POST['stage-dur-min']);
$smax = htmlspecialchars($_POST['stage-dur-max']);
$mmin = htmlspecialchars($_POST['module-dur-min']);
$mmax = htmlspecialchars($_POST['module-dur-max']);

if(
  is_numeric($smin) &&
  is_numeric($smax) &&
  is_numeric($mmin) &&
  is_numeric($mmax) &&
  $smax>$smin && $mmax>$mmin)
{
  $inhome_id->set_par_time_range(array($smin,$smax,$mmin,$mmax));
}

$inhome_id->set_threshold(30);
$inhome_id->set_standard_deviation_scale(1);
$inhome_id->build_table_data();
echo $inhome_id->build_table_html();
