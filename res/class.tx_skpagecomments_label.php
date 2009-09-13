<?php
/*
 * Created on Nov 28, 2006
 *
 * class for labels in tce forms
 */
 
/**
 *
 * @author Steffen Kamper <info(at)sk-typo3.de>
 */


class tx_skpagecomments_label {
		
	function getCommentRecordLabel(&$params, &$pObj)	{
		
        // Get complete record 
		$rec = t3lib_BEfunc::getRecord($params['table'], $params['row']['uid']);

		// Assemble the label
		$label = 'Kommentar von '.$rec['name'].' am '.date('d.m.Y h:s',$rec['crdate']);

        //Write to the label
        $params['title'] =  $label;
	}
		
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/res/class.tx_skpagecomments_label.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/res/class.tx_skpagecomments_label.php']);
}  
  
?>