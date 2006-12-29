<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

  ## Extending TypoScript from static template uid=43 to set up userdefined tag:
t3lib_extMgm::addTypoScript($_EXTKEY,'editorcfg','
	tt_content.CSS_editor.ch.tx_skpagecomments_pi1 = < plugin.tx_skpagecomments_pi1.CSS_editor
',43);


t3lib_extMgm::addPItoST43($_EXTKEY,'pi1/class.tx_skpagecomments_pi1.php','_pi1','list_type',0);

#Adds a hook for tt_news to handle own markers
if (TYPO3_MODE!='BE')	{
    require_once(t3lib_extMgm::extPath('sk_pagecomments').'hooks/class.skpagecomments_tt_news_markers.php');
}
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_news']['extraItemMarkerHook'][]='tx_skpagecomments_tt_news_markers';

?>
