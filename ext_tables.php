<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::allowTableOnStandardPages("tx_skpagecomments_comments");


t3lib_extMgm::addToInsertRecords("tx_skpagecomments_comments");

$TCA["tx_skpagecomments_comments"] = Array (
	"ctrl" => Array (
		'title' => 'LLL:EXT:sk_pagecomments/locallang_db.xml:tx_skpagecomments_comments',		
		'label' => 'name,crdate',	
		'label_alt' => 'name,crdate',
		'label_alt_force' => 1,	
        'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'versioningWS' => TRUE, 
		'origUid' => 't3_origuid',
		'languageField' => 'sys_language_uid',	
		'transOrigPointerField' => 'l18n_parent',	
		'transOrigDiffSourceField' => 'l18n_diffsource',	
		"default_sortby" => "ORDER BY crdate",	
		"delete" => "deleted",	
		"enablecolumns" => Array (		
			"disabled" => "hidden",	
			"starttime" => "starttime",	
			"endtime" => "endtime",	
			"fe_group" => "fe_group",
		),
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."icon_tx_skpagecomments_comments.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "sys_language_uid, l18n_parent, l18n_diffsource, hidden, starttime, endtime, fe_group, name, email, comment, pageid, allowed, pivar",
	)
);


t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key';


t3lib_extMgm::addPlugin(Array('LLL:EXT:sk_pagecomments/locallang_db.xml:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');


t3lib_extMgm::addStaticFile($_EXTKEY,"pi1/static/","Page Comments");

if (TYPO3_MODE=="BE")    {
        
    t3lib_extMgm::addModule("web","txskpagecommentsM1","",t3lib_extMgm::extPath($_EXTKEY)."mod1/");
}


?>
