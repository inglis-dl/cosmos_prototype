<?php
require_once 'common.php';

$begin_date = htmlspecialchars($_POST['from']);
$end_date = htmlspecialchars($_POST['to']);
$rank = htmlspecialchars($_POST['rank']);

// query the db for the data we want

if(''==$begin_date || ''==$end_date ||
   !(preg_match('/^20(1|2)[0-9]-(0|1)[0-9]{1}-[0-3]{1}[0-9]{1}/',$begin_date) &&
     preg_match('/^20(1|2)[0-9]-(0|1)[0-9]{1}-[0-3]{1}[0-9]{1}/',$end_date)))
{
  echo sprintf('error: invalid dates %s - %s',$begin_date,$end_date);
  die();
}

// available grades depend on study wave
// TODO: consider only using one set of grades A - F for all
// wave ranks
$grade_list=array(
  1=>array('A','B','C','C1','C2','D1','D2','F'),
  2=>array('A','B'),
  3=>array('A','B','C','C1','C2','D1','D2','F'));
$grades = $grade_list[$rank];
$grade_keys=array();

// build the main query
$sql =
  'select '.
  'ifnull(t.name,"NA") as tech, '.
  'site.name as site, ';

foreach($grades as $grade)
{
  $sql .= sprintf('sum(case when strcmp(qcdata,"%s")=0 then 1 else 0 end) as total_%s, ',$grade,$grade);
  $grade_keys[] = sprintf('total_%s',$grade);
}

$sql .= sprintf(
  'sum(case when strcmp(skip,"TechnicalProblem")=0 then 1 else 0 end) as total_skip_technical, '.
  'sum(case when strcmp(skip,"ParticipantDecision")=0 then 1 else 0 end) as total_skip_participant, '.
  'sum(case when strcmp(skip,"InterviewerDecision")=0 then 1 else 0 end) as total_skip_interviewer, '.
  'sum(case when strcmp(skip,"SeeComment")=0 then 1 else 0 end) as total_skip_other, '.
  'sum(if(skip is null,0,1)) as total_skip, '.
  'sum(missing) as total_missing, '.
  'sum(contraindicated) as total_contraindicated, '.
  'sum(if(t.name is null,0,1)) as total_tech, '.
  'sum(1) as total_interview '.
  'FROM interview i '.
  'join stage s on i.id=s.interview_id '.
  'join site on site.id=i.site_id '.
  'left join technician t on t.id=s.technician_id  '.
  'left join site as s2 on t.site_id=s2.id '.
  'where (start_date between "%s" and "%s") '.
  'and rank=%d '.
  'and s.name="spirometry" '.
  'group by site,tech', $begin_date, $end_date, $rank);

$res = $db->get_all( $sql );
if(false===$res || !is_array($res))
{
  echo 'error: failed query';
  die();
}

$first = true;
$total_keys = array();
$site_list = array();
foreach($res as $row)
{
  $site = $row['site'];
  unset($row['site']);
  $tech = $row['tech'];
  unset($row['tech']);
  if($first)
  {
    $keys = array_keys($row);
    foreach($keys as $item)
    {
      if(0==strpos($item,'total_')) $total_keys[]=$item;
    }
    $first=false;
    $site_list['ALL']['totals'] = array_combine($total_keys,array_fill(0,count($total_keys),0));
  }
  if(!array_key_exists($site,$site_list))
    $site_list[$site]['totals'] = array_combine($total_keys,array_fill(0,count($total_keys),0));
  foreach($total_keys as $key)
  {
    $site_list[$site]['totals'][$key]+=$row[$key];
    $site_list['ALL']['totals'][$key]+=$row[$key];
  }
  $site_list[$site]['technicians'][$tech]=$row;
}

$percent_keys = array('total_skip','total_missing','total_contraindicated');
$all_total = $site_list['ALL']['totals']['total_interview'];
foreach($site_list as $site=>$site_data)
{
  $grade_total=0;
  foreach($grade_keys as $key)
    $grade_total+=$site_data['totals'][$key];
  if(0<$grade_total)
  {
    foreach($grade_keys as $key)
    {
      $value = $site_list[$site]['totals'][$key] ;
      if( 0 < $value )
        $site_list[$site]['totals'][$key] = sprintf('%d</br>(%d)',
          $value,round(100.0*$value/$grade_total));
    }
  }

  $site_total = $site_data['totals']['total_interview'];
  if( 0 < $site_total )
  {
    foreach( $percent_keys as $key )
    {
      $value = $site_list[$site]['totals'][$key];
      if( 0 < $value )
        $site_list[$site]['totals'][$key] = sprintf('%d</br>(%d)',$value,round(100.0*$value/$site_total));
    }
  }
  if( 'ALL' != $site && 0 < $all_total && 0 < $site_total )
  {
    $site_list[$site]['totals']['total_interview'] = sprintf('%d</br>(%d)',$site_total,round(100.0*$site_total/$all_total));
  }
  if( !array_key_exists( 'technicians', $site_data ) ) continue;

  foreach( $site_data['technicians'] as $tech => $row )
  {
    $grade_total = 0;
    foreach( $grade_keys as $key )
      $grade_total += $row[$key];
    if( 0 < $grade_total )
    {
      foreach( $grade_keys as $key )
      {
        $value = $row[$key];
        if( 0 < $value )
          $site_list[$site]['technicians'][$tech][$key] = sprintf('%d</br>(%d)',
            $value,round(100.0*$value/$grade_total));
      }
    }
    $total = $row['total_interview'];
    if( 0 < $total )
    {
      foreach( $percent_keys as $key )
      {
        $value = $row[$key];
        if( 0 < $value )
          $site_list[$site]['technicians'][$tech][$key] =
            sprintf('%d</br>(%d)',$value,round(100.0*$value/$total));
      }

      if( 0 < $site_total )
      {
        $site_list[$site]['technicians'][$tech]['total_interview'] =
          sprintf('%d</br>(%d)',$total,round(100.0*$total/$site_total));
      }
    }
  }
}

// set up the DataTable headers
$ncol = count($total_keys)+1;
$head_str_tech = "<tr><td>TECH</td>";
$head_str_site = "<tr><td>SITE</td>";
foreach($total_keys as $key)
{
  $key_str = str_replace('_',' ',$key);
  $head_str_tech .= "<td>{$key_str}</td>";
  $head_str_site .= "<td>{$key_str}</td>";
}
$head_str_tech .= "</tr>";
$head_str_site .= "</tr>";

// set up the DataTable options for column group hiding
$col_groups = array(
  1=>array('grades'=>array(9,10,11,12),'skips'=>array(1,2,3,4,5,6,7,8)),
  2=>array('grades'=>array(3,4,5,6),'skips'=>array(1,2)),
  3=>array('grades'=>array(9,10,11,12),'skips'=>array(1,2,3,4,5,6,7,8)),
 );

$hide_grade = sprintf( '[%s]', implode(',',$col_groups[$rank]['grades']) );
$hide_skip = sprintf( '[%s]', implode(',',$col_groups[$rank]['skips']) );
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>CLSA-&Eacute;LCV QAC></title>
    <link rel="stylesheet" type="text/css" href="../css/qac.css">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <link rel="stylesheet" type="text/css" href="../css/datatables.min.css">
    <script type="text/javascript" src="datatables.min.js"></script>
    <script>
      var hide_grade = <?php echo $hide_grade; ?>;
      var hide_skip = <?php echo $hide_skip; ?>;
      $( function() {
        $( 'table.clsa' ).DataTable( {
          dom: 'Bfrtipl',
          buttons: [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
            {
              extend: 'colvisGroup',
              text: 'Grades',
              show: hide_skip,
              hide: hide_grade
            },
            {
              extend: 'colvisGroup',
              text: 'Skips',
              hide: hide_skip,
              show: hide_grade
            },
            {
              extend: 'colvisGroup',
              text: 'Show All',
              show: ':hidden'
            }
          ]
        });
      });
    </script>
  </head>
  <body>
    <h3><?php echo "SPIROMETRY RESULTS - Wave {$rank} ({$begin_date} - {$end_date})"?></h3>
    <!--build the main summary table-->
    <table id='summary' class="clsa stripe cell-border order-column" style="width:100%">
      <thead>
        <tr><?php echo"<th colspan={$ncol}>SITE SUMMARY</th>"?></tr>
        <?php echo $head_str_site?>
      </thead>
      <tbody>
        <?php
          foreach( $site_list as $site=>$site_data )
          {
            if('ALL'==$site) continue;
            echo "<tr><td>{$site}</td>";
            foreach( $site_data['totals'] as $key=>$item )
              echo "<td>{$item}</td>";
            echo "</tr>";
          }
        ?>
      </tbody>
      <tfoot>
        <tr>
          <td>TOTAL</td>
          <?php
            foreach( $site_list['ALL']['totals'] as $key=>$item )
              echo "<td>{$item}</td>";
          ?>
        </tr>
      </tfoot>
    </table>
    <!--build the sites and technician tables-->
    <?php
      foreach( $site_list as $site=>$site_data )
      {
        if('ALL'==$site) continue;
        echo "<table id='{$site}' class=\"clsa stripe cell-border order-column\" style=\"width:100%\">" .
             "<thead><tr><th colspan={$ncol}>{$site}</th></tr>";
        echo $head_str_tech . "</thead><tbody>";
        foreach( $site_data['technicians'] as $tech=>$row )
        {
          if('NA'==$tech) continue;
          echo "<tr><td>{$tech}</td>";
          foreach( $row as $key=>$item )
            echo "<td>{$item}</td>";
          echo "</tr>";
        }
        echo "</tbody><tfoot><tr><td>TOTAL</td>";
        foreach( $site_data['totals'] as $key=>$item )
          echo "<td>{$item}</td>";

        echo "</tr></tfoot></table>";
      }
    ?>
  </body>
</html>