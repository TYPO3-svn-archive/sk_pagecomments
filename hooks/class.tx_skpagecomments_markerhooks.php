<?php
if(!defined(PATH_tslib)) define('PATH_tslib', PATH_site.'typo3/sysext/cms/tslib/'); 
require_once(PATH_tslib."class.tslib_pibase.php"); 

class tx_skpagecomments_markerhooks extends tslib_pibase {
  var $prefixId = 'tx_skpagecomments_pi1';	
  var $scriptRelPath = 'pi1/class.tx_skpagecomments_pi1.php';
  var $extKey = 'sk_pagecomments';
  
  //own hooks
  function addWhereProcessor($addWhere,$lookForValue,$parent) { 
        //the addidional $addWhere can be manipulated her
        //   
  }
  //tt_news hook
  function extraItemMarkerProcessor($markerArray,$row,$lConf,$parentObject) { 
        $this->pi_loadLL();  
        $res=$GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)','tx_skpagecomments_comments','hidden=0 AND deleted=0 AND piVar=\'tx_ttnews[tt_news]='.$row['uid'].'\''); 
        $row=$GLOBALS['TYPO3_DB']->sql_fetch_row($res);
        $reccount=$row[0];
        $markerArray['###COMMENTCOUNT###']=$reccount.' '.($reccount==1 ? $this->pi_getLL('comment') : $this->pi_getLL('comments'));  
        
        return  $markerArray;
  }
  
  //sk-simplegallery hook
  function extraSingleMarkerProcessor ($markerArray,$row,$parentObject) {
        $this->pi_loadLL();  
        $res=$GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)','tx_skpagecomments_comments','hidden=0 AND deleted=0 AND piVar=\'tx_sksimplegallery_pi1[single]='.$row['uid'].'\''); 
        $row=$GLOBALS['TYPO3_DB']->sql_fetch_row($res);
        $reccount=$row[0];
        $markerArray['###COMMENTCOUNT###']=$reccount.' '.($reccount==1 ? $this->pi_getLL('comment') : $this->pi_getLL('comments'));  
        
        return  $markerArray;
  }
}

class tx_pc_linkHandler {
	function main($linktxt, $conf, $linkHandlerKeyword, $linkHandlerValue, $link_param, & $pObj) {
		#t3lib_div::debug($conf,'drin');
		$pid = $pObj->data['pid'];
		
		$lconf = array ();
		#$lconf['useCacheHash'] = 1;
		$lconf['parameter'] = 25;
		
		$lconf['additionalParams'] = '&value='.$linkHandlerValue;
       
		return $pObj->typoLink($linktxt, $lconf);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/hooks/class.tx_skpagecomments_markerhooks.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/hooks/class.tx_skpagecomments_markerhooks.php']);
}


?>
