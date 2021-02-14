<?php
/**
 * TestLink Issue from GitHub Plugin
 * This script is distributed under the GNU General Public License 3 or later.
 *
 * @filesource  IssueFromGitHub.php
 * @copyright   2021, naofum
 * @link        https://github.com/naofum/testlink-issue-from-github
 *
 */

require_once('config.inc.php');
require_once('common.php');
require_once('exec.inc.php');

$smarty = new TLSmarty();
$gui = new stdClass();

// configure here
$begin_string1 = '原因';  // create testset after this string found within issue
$begin_string2 = '背景';
$end_string = '完了条件';  // create testset until this string found within issue


$gui->headerMessage = plugin_lang_get('disp_page_header_message');
$gui->title = plugin_lang_get('disp_page_title');
$gui->labelId = plugin_lang_get('disp_label_id');
$gui->labelParent = plugin_lang_get('disp_label_parent');
$gui->labelUrl = plugin_lang_get('disp_label_url');
$gui->labelTitle = plugin_lang_get('disp_label_title');
$gui->labelStatus = plugin_lang_get('disp_label_status');
$gui->labelSummary = plugin_lang_get('disp_label_summary');
$gui->labelTestset = plugin_lang_get('disp_label_testset');
$gui->labelUnittest1 = plugin_lang_get('disp_label_unittest1');
$gui->labelUnittest2 = plugin_lang_get('disp_label_unittest2');
$gui->labelUnittest3 = plugin_lang_get('disp_label_unittest3');
$gui->labelCancel = plugin_lang_get('disp_label_cancel_button');
$gui->labelConfirm = plugin_lang_get('disp_label_confirm_button');
$gui->labelSubmit = plugin_lang_get('disp_label_submit_button');

if ($_POST['submit']) {   // Check if the form is submitted

  $args->user = $_SESSION['currentUser'];
  $cfg = config_get('exec_cfg');

  // retrieve issue
  $args->tproject_id = $_SESSION['testprojectID'];
  doDBConnect($db);
  list($itObj,$itCfg) = getIssueFromGithub($db,$args,$gui);
  $issue = $itObj->getIssue($_POST['id']);
  if (!$issue) {
    // issue not found
    $gui->message = plugin_lang_get('disp_not_found');
    // show top page
    $smarty->assign('gui',$gui);
    $smarty->display(plugin_file_path('id.tpl'));
    return;
  }

  // get title
  $titlepos = mb_strpos($issue->summary, ":\n");
  $title = "";
  if ($titlepos) {
    $title = mb_substr($issue->summary, 0, $titlepos) . "#" . $issue->id;
  }
  // other attributes
  $gui->id = $issue->id;
  $gui->url = $issue->url;
  $gui->issueTitle = $title;
  $gui->statusCode = $issue->statusCode;
  $gui->summary = $issue->summary;
  // organize summary
  $summary = preg_split("/\n/", $issue->summary);
  $sum = "";
  for ($i = 0; $i < count($summary); $i++) {
    if (mb_substr($summary[$i], 0, 1) == '#') {
      $sum = $sum . '<h3>' . str_replace('#', '', $summary[$i]) . '</h3>';
    } else {
      $sum = $sum . $summary[$i] . '<br>';
    }
  }

  // create testcase and testset, after confirmed
  if ($_POST['confirm'] == '1') {
    $steps = array();
    $nl = '<p>';
    $num = 0;
    if ($_POST['test1'] == '1') {
      $steps[$num] = array('step_number' => $num + 1, 'action' => '',
                           'expected_results' => plugin_lang_get('disp_label_unittest1'));
      $num++;
    }
    if ($_POST['test2'] == '1') {
      $steps[$num] = array('step_number' => $num + 1, 'action' => '',
                           'expected_results' => plugin_lang_get('disp_label_unittest2'));
      $num++;
    }
    if ($_POST['test3'] == '1') {
      $steps[$num] = array('step_number' => $num + 1, 'action' => '',
                           'expected_results' => plugin_lang_get('disp_label_unittest3'));
      $num++;
    }
    for ($i = 0; $i < count($summary); $i++) {
      if (mb_strpos($summary[$i], $begin_string1) || mb_strpos($summary[i], $begin_string2)) {
        $output = '1';
      }
      if (mb_strpos($summary[$i], $end_string)) {
        $output = '';
      }
      if ($output == '1' && mb_substr($summary[$i], 0, 3) == '- [') {
        $steps[$num] = array('step_number' => $num + 1, 'action' => '',
                        'expected_results' => mb_substr($summary[$i], 6));
        $num++;
      }
    }

    $tcData = array();
    $tcData[0] = array('name' => $title, 'summary' => '<a href="' . $issue->url . '" target="_blank">' . $issue->url . '</a><p></p>' . $sum,
                        'steps' => $steps, 'internalid' => null, 'externalid' => null,
			'author_login' => null, 'preconditions' => null);
    $parentID = $_POST['parent'];
    $tproject_id = $args->tproject_id;
    $userID = $args->user;
    $kwMap = null;
    $duplecateLogic = array('hitCriteria' => 'name', 'actionOnHit' => null);
    $resultMap = saveImportedTCData($db,$tcData,$tproject_id,$parentID,$userID,$kwMap,$duplicateLogic);
    $gui->message = plugin_lang_get('disp_completed');
    // show completed page
    $smarty->assign('gui',$gui);
    $smarty->display(plugin_file_path('completed.tpl'));
    return;
  }

  $gui->message = plugin_lang_get('disp_confirm');
  $gui->confirm = '1';

  // show confirm page
  $smarty->assign('gui',$gui);
  $smarty->display(plugin_file_path('disp.tpl'));
  return;
}

// show top page
$smarty->assign('gui',$gui);
$smarty->display(plugin_file_path('id.tpl'));

// --------------------------------------------------------------------------------------
/*
  function: saveImportedTCData
  args :
  returns:
*/
function saveImportedTCData(&$db,$tcData,$tproject_id,$container_id,
                            $userID,$kwMap,$duplicatedLogic = array('hitCriteria' => 'name', 'actionOnHit' => null))
{
  static $messages;
  static $fieldSizeCfg;
  static $feedbackMsg;
  static $tcase_mgr;
  static $tproject_mgr;
  static $req_spec_mgr;
  static $req_mgr;
  static $safeSizeCfg;
  static $linkedCustomFields;
  static $tprojectHas;
  static $reqSpecSet;
  static $getVersionOpt;
  static $userObj;
  static $tcasePrefix;
  static $glueChar;
  static $userRights;

  $ret = null;

  if (!$tcData) {
    return;
  }

  // $tprojectHas = array('customFields' => false, 'reqSpec' => false);
  $hasCFieldsInfo = false;
  $hasRequirements = false;
  $hasAttachments = false;

  if(is_null($messages)) {
    $feedbackMsg = array();
    $messages = array();
    $fieldSizeCfg = config_get('field_size');

    $tcase_mgr = new testcase($db);
    $tcase_mgr->setTestProject($tproject_id);

    $tproject_mgr = new testproject($db);
    $req_spec_mgr = new requirement_spec_mgr($db);
    $req_mgr = new requirement_mgr($db);
    $userObj = new tlUser($userID);
    $userObj->readFromDB($db,tlUser::TLOBJ_O_SEARCH_BY_ID);

    $userRights['can_edit_executed'] =
      $userObj->hasRight($db,'testproject_edit_executed_testcases',$tproject_id);
	  $userRights['can_link_to_req'] =
	    $userObj->hasRight($db,'req_tcase_link_management',$tproject_id);
	  $userRights['can_assign_keywords'] =
      $userObj->hasRight($db,'keyword_assignment',$tproject_id);

    $k2l = array('already_exists_updated','original_name','testcase_name_too_long',
                 'already_exists_not_updated','already_exists_skipped',
                 'start_warning','end_warning','testlink_warning',
                 'hit_with_same_external_ID',
                 'keywords_assignment_skipped_during_import',
                 'req_assignment_skipped_during_import');

    foreach($k2l as $k) {
      $messages[$k] = lang_get($k);
    }

    $messages['start_feedback'] = $messages['start_warning'] . "\n" . $messages['testlink_warning'] . "\n";
    $messages['cf_warning'] = lang_get('no_cf_defined_can_not_import');
    $messages['reqspec_warning'] = lang_get('no_reqspec_defined_can_not_import');


    $feedbackMsg['cfield']=lang_get('cf_value_not_imported_missing_cf_on_testproject');
    $feedbackMsg['tcase'] = lang_get('testcase');
    $feedbackMsg['req'] = lang_get('req_not_in_req_spec_on_tcimport');
    $feedbackMsg['req_spec'] = lang_get('req_spec_ko_on_tcimport');
    $feedbackMsg['reqNotInDB'] = lang_get('req_not_in_DB_on_tcimport');
    $feedbackMsg['attachment'] = lang_get('attachment_skipped_during_import');


    // because name can be changed automatically during item creation
    // to avoid name conflict adding a suffix automatically generated,
    // is better to use a max size < max allowed size
    $safeSizeCfg = new stdClass();
    $safeSizeCfg->testcase_name=($fieldSizeCfg->testcase_name) * 0.8;


    // Get CF with scope design time and allowed for test cases linked to this test project
    $linkedCustomFields = $tcase_mgr->cfield_mgr->get_linked_cfields_at_design($tproject_id,1,null,'testcase',null,'name');
    $tprojectHas['customFields']=!is_null($linkedCustomFields);

#    $reqSpecSet = getReqSpecSet($db,$tproject_id);
    $reqSpecSet = null;

    $tprojectHas['reqSpec'] = (!is_null($reqSpecSet) && count($reqSpecSet) > 0);

    $getVersionOpt = array('output' => 'minimun');
    $tcasePrefix = $tproject_mgr->getTestCasePrefix($tproject_id);
    $glueChar = config_get('testcase_cfg')->glue_character;
  }

  $resultMap = array();
  $tc_qty = sizeof($tcData);
  $userIDCache = array();

  for($idx = 0; $idx <$tc_qty ; $idx++) {
    $tc = $tcData[$idx];
    $name = $tc['name'];
    $summary = $tc['summary'];
    $steps = $tc['steps'];
    $internalid = $tc['internalid'];
    $externalid = $tc['externalid'];

    $doCreate = true;
    if( $duplicatedLogic['actionOnHit'] == 'update_last_version' ||
        $duplicatedLogic['actionOnHit'] == 'skip' ) {

      $updOpt['blockIfExecuted'] = !$userRights['can_edit_executed'];

      switch($duplicatedLogic['hitCriteria']) {
        case 'name':
          $dupInfo = $tcase_mgr->getDuplicatesByName($name,$container_id);
        break;

        case 'internalID':
          $dummy = $tcase_mgr->tree_manager->get_node_hierarchy_info($internalid,$container_id);
          if( !is_null($dummy) ) {
            $dupInfo = null;
            $dupInfo[$internalid] = $dummy;
          }
        break;

        case 'externalID':
          $dupInfo = $tcase_mgr->get_by_external($externalid,$container_id);
        break;
      }
    }

    // Check for skip, to avoid useless processing
    if( $duplicatedLogic['actionOnHit'] == 'skip' && !is_null($dupInfo) &&
        count($dupInfo) > 0 ) {
      $resultMap[] = array($name,$messages['already_exists_skipped']);
      continue;
    }

    // I've changed value to use when order has not been provided
    // from testcase:DEFAULT_ORDER to a counter, because with original solution
    // an issue arise with 'save execution and go next'
    // if use has not provided order I think is OK TestLink make any choice.
    $node_order = isset($tc['node_order']) ? intval($tc['node_order']) : ($idx+1);
    $internalid = $tc['internalid'];
    $preconditions = $tc['preconditions'];
    $exec_type = isset($tc['execution_type']) ? $tc['execution_type'] : TESTCASE_EXECUTION_TYPE_MANUAL;
    $importance = isset($tc['importance']) ? $tc['importance'] : MEDIUM;

    $attr = null;
    if(isset($tc['estimated_exec_duration']) && !is_null($tc['estimated_exec_duration'])) {
      $attr['estimatedExecDuration'] = trim($tc['estimated_exec_duration']);
      $attr['estimatedExecDuration'] = $attr['estimatedExecDuration']=='' ? null : floatval($attr['estimatedExecDuration']);
    }

    if(isset($tc['is_open'])) {
      $attr['is_open'] = trim($tc['is_open']);
    }

	  if(isset($tc['active'])) {
      $attr['active'] = trim($tc['active']);
    }

    if(isset($tc['status'])) {
      $attr['status'] = trim($tc['status']);
    }

    $externalid = $tc['externalid'];
    if( intval($externalid) <= 0 ) {
      $externalid = null;
    }

    $personID = $userID;
    if( !is_null($tc['author_login']) ) {
      if( isset($userIDCache[$tc['author_login']]) ) {
        $personID = $userIDCache[$tc['author_login']];
      } else {
        $userObj->login = $tc['author_login'];
        if( $userObj->readFromDB($db,tlUser::USER_O_SEARCH_BYLOGIN) == tl::OK ) {
          $personID = $userObj->dbID;
        }

        // I will put always a valid userID on this cache,
        // this way if author_login does not exit, and is used multiple times
        // i will do check for existence JUST ONCE.
        $userIDCache[$tc['author_login']] = $personID;
      }
    }

    $name_len = tlStringLen($name);
    if($name_len > $fieldSizeCfg->testcase_name) {
      // Will put original name inside summary
      $xx = $messages['start_feedback'];
      $xx .= sprintf($messages['testcase_name_too_long'],$name_len, $fieldSizeCfg->testcase_name) . "\n";
      $xx .= $messages['original_name'] . "\n" . $name. "\n" . $messages['end_warning'] . "\n";
	    $tcCfg = getWebEditorCfg('design');
	    $tcType = $tcCfg['type'];
	    if ($tcType == 'none'){
		    $summary = $xx . $summary ;
      } else{
		    $summary = nl2br($xx) . $summary ;
	    }
	    $name = tlSubStr($name, 0, $safeSizeCfg->testcase_name);
    }


    $kwIDs = null;
    if (isset($tc['keywords']) && $tc['keywords']) {
  	  if(!$userRights['can_assign_keywords']){
  		  $resultMap[] =
          array($name,$messages['keywords_assignment_skipped_during_import']);
  	  } else{
  		  $kwIDs = implode(",",buildKeywordList($kwMap,$tc['keywords']));
  	  }
    }

    // More logic regarding Action on Duplicate
    if( $duplicatedLogic['actionOnHit'] == 'update_last_version' &&
        !is_null($dupInfo) ) {

        $tcase_qty = count($dupInfo);

        switch($tcase_qty) {
           case 1:
             $doCreate=false;
             $tcase_id = key($dupInfo);
             $last_version = $tcase_mgr->get_last_version_info($tcase_id,
                                                               $getVersionOpt);
             $tcversion_id = $last_version['id'];
             $ret = $tcase_mgr->update($tcase_id,$tcversion_id,$name,
                                       $summary,
                                       $preconditions,$steps,$personID,
                                       $kwIDs,
                                       $node_order,$exec_type,$importance,
                                       $attr,$updOpt);

             $ret['id'] = $tcase_id;
             $ret['tcversion_id'] = $tcversion_id;
             if( $ret['status_ok'] ) {
               $resultMap[] = array($name,$messages['already_exists_updated']);
             } else {
               if($ret['reason'] == '') {
                 $resultMap[] = array($name,
                  sprintf($messages['already_exists_not_updated'],
                                                     $tcasePrefix . $glueChar . $externalid,
                                                     $tcasePrefix . $glueChar . $ret['hit_on']['tc_external_id']));
               } else {
                 $resultMap[] = array($name,$ret['msg']);
               }
            }
           break;

           case 0:
             $doCreate=true;
           break;

           default:
               $doCreate=false;
           break;
       }
    }

    if( $doCreate ) {
      // Want to block creation of with existent EXTERNAL ID, if containers ARE DIFFERENT.
      $item_id = intval($tcase_mgr->getInternalID($externalid, array('tproject_id' => $tproject_id)));

      if( $item_id > 0) {
        // who is his parent ?
        $owner = $tcase_mgr->getTestSuite($item_id);
        if( $owner != $container_id) {
          // Get full path of existent Test Cases
          $stain = $tcase_mgr->tree_manager->get_path($item_id,null, 'name');
          $n = count($stain);
          $stain[$n-1] = $tcasePrefix . config_get('testcase_cfg')->glue_character . $externalid . ':' . $stain[$n-1];
          $stain = implode('/',$stain);

          $resultMap[] = array($name,$messages['hit_with_same_external_ID'] . $stain);
          $doCreate = false;
        }
      }
    }

    if( $doCreate ) {
        $createOptions =
          array('check_duplicate_name' => testcase::CHECK_DUPLICATE_NAME,
                'action_on_duplicate_name' => $duplicatedLogic['actionOnHit'],
                'external_id' => $externalid, 'importLogic' => $duplicatedLogic);

        if(!is_null($attr) ) {
          $createOptions += $attr;
        }

        if ( $ret = $tcase_mgr->create($container_id,$name,$summary,$preconditions,
                                      $steps,$personID,$kwIDs,$node_order,
                                      testcase::AUTOMATIC_ID,
                                      $exec_type,$importance,$createOptions) ) {
          $resultMap[] = array($name,$ret['msg']);
        }
    }

    // Custom Fields Management
    // Check if CF with this name and that can be used on Test Cases
    // is defined in current Test Project.
    // If Check fails => give message to user.
    // Else Import CF data
    //
    $hasCFieldsInfo = (isset($tc['customfields']) &&
                       !is_null($tc['customfields']));


   if($hasCFieldsInfo &&  !is_null($ret)) {
      if($tprojectHas['customFields']) {
        $msg = processCustomFields($tcase_mgr,$name,$ret['id'],$ret['tcversion_id'],
                 $tc['customfields'],$linkedCustomFields,$feedbackMsg);
        if( !is_null($msg) ) {
            $resultMap = array_merge($resultMap,$msg);
        }
      } else {
        // Can not import Custom Fields Values, give feedback
        $msg[]=array($name,$messages['cf_warning']);
        $resultMap = array_merge($resultMap,$msg);
      }
    }

    $hasRequirements=(isset($tc['requirements']) && !is_null($tc['requirements']));

    if($hasRequirements) {
      if( $tprojectHas['reqSpec'] ) {

        if(!$userRights['can_link_to_req']){
          $msg[]=array($name,$messages['req_assignment_skipped_during_import']);
        } else{
          $msg = processRequirements($db,$req_mgr,$name,$ret,$tc['requirements'],
                                     $reqSpecSet,$feedbackMsg,$userID);
        }

        if( !is_null($msg) ) {
          $resultMap = array_merge($resultMap,$msg);
        }
      } else {
        $msg[]=array($name,$messages['reqspec_warning']);
        $resultMap = array_merge($resultMap,$msg);
      }
    }


    $hasAttachments=(isset($tc['attachments']) && !is_null($tc['attachments']));
    if($hasAttachments) {
      $fk_id = $doCreate ? $ret['id'] : $internalid;
      if ($internalid == "" && $item_id>0) {
        $internalid = $item_id;
      }
      $msg = processAttachments( $db, $name, $internalid, $fk_id, $tc['attachments'],
               $feedbackMsg );
      if( !is_null($msg) ) {
        $resultMap = array_merge($resultMap,$msg);
      }
    }

  }

  return $resultMap;
}

/*
  function: getIssueFromGithub
  args :
  returns:
*/
function getIssueFromGithub(&$dbHandler,$argsObj,&$guiObj)
{
  $its = null;
  $tprojectMgr = new testproject($dbHandler);
  $info = $tprojectMgr->get_by_id($argsObj->tproject_id);

  if($info['issue_tracker_enabled']) {
  	$it_mgr = new tlIssueTracker($dbHandler);
  	$issueTrackerCfg = $it_mgr->getLinkedTo($argsObj->tproject_id);

  	if( !is_null($issueTrackerCfg) ) {
  		$its = $it_mgr->getInterfaceObject($argsObj->tproject_id);
  	}
  }
  return array($its,$issueTrackerCfg);
}
