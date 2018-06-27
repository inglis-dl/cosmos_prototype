<?php
require_once 'table_generator.class.php';

$begin_date = htmlspecialchars($_POST['from']);
$end_date = htmlspecialchars($_POST['to']);
$rank = htmlspecialchars($_POST['rank']);

class hearing_generator extends table_generator
{
  protected function build_data()
  {
    global $db;

    // build the main query
    $sql =
      'select '.
      'ifnull(t.name,"NA") as tech, '.
      'site.name as site, ';

    $sql .= 'sum(if(qcdata is null, 0, if(substring_index(substring_index(qcdata,",",3),":",-1)=0,0,1))) as total_left_error_only, ';
    $sql .= 'sum(if(qcdata is null, 0, if(substring_index(substring_index(qcdata,",",1),":",-1) between 1 and 7,1,0))) as total_left_sub, ';
    $sql .= 'sum(if(qcdata is null, 0, if(substring_index(substring_index(qcdata,",",1),":",-1)=8,1,0))) as total_left_par, ';
    $sql .= 'sum(if(qcdata is null, 0, if(trim("}" from substring_index(substring_index(qcdata,",",4),":",-1))=0,0,1))) as total_right_error_only, ';
    $sql .= 'sum(if(qcdata is null, 0, if(substring_index(substring_index(qcdata,",",2),":",-1) between 1 and 7,1,0))) as total_right_sub, ';
    $sql .= 'sum(if(qcdata is null, 0, if(substring_index(substring_index(qcdata,",",2),":",-1)=8,1,0))) as total_right_par, ';

    $sql .= $this->get_main_query();

    $res = $db->get_all( $sql );
    if(false===$res || !is_array($res))
    {
      echo sprintf('error: failed query: %s', $db->get_last_error());
      echo $sql;
      die();
    }
    $this->data = $res;

    $this->page_explanation=array();
    $this->page_explanation[]='frequency error only: 0 frequencies + 1 or more errors</li>';
    $this->page_explanation[]='frequency count sub:  1 - 7 frequencies</li>';
    $this->page_explanation[]='frequency count par:  8 frequencies';
  }
}

$hearing = new hearing_generator('hearing', $rank, $begin_date, $end_date);

$qc_keys = array('total_left_error_only','total_left_sub','total_left_par',
  'total_right_error_only','total_right_sub','total_right_par');

$hearing->set_indicator_keys($qc_keys);

$hearing->build_table_data();

$hearing->set_page_stage('HEARING');

echo $hearing->build_table_html();
