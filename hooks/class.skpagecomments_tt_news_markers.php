<?php
require_once(PATH_tslib."class.tslib_pibase.php"); 

class tx_skpagecomments_tt_news_markers extends tslib_pibase {
  var $prefixId = 'tx_skpagecomments_pi1';	
  var $scriptRelPath = 'pi1/class.tx_skpagecomments_pi1.php';
  var $extKey = 'sk_pagecomments';
  
  function extraItemMarkerProcessor($markerArray,$row,$lConf,$parentObject) { 
        $this->pi_loadLL();  
        $res=$GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)','tx_skpagecomments_comments','hidden=0 AND deleted=0 AND piVar=\'tx_ttnews[tt_news]='.$row['uid'].'\''); 
        $row=$GLOBALS['TYPO3_DB']->sql_fetch_row($res);
        $reccount=$row[0];
        $markerArray['###COMMENTCOUNT###']=$reccount.' '.($reccount==1 ? $this->pi_getLL('comment') : $this->pi_getLL('comments'));  
        
        return  $markerArray;
  }
}
?>
