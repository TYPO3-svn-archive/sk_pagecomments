<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA["tx_skpagecomments_comments"] = Array (
	"ctrl" => $TCA["tx_skpagecomments_comments"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "sys_language_uid,l18n_parent,l18n_diffsource,hidden,starttime,endtime,fe_group,name,email,homepage,comment,pageid,allowed,pivar"
	),
	"feInterface" => $TCA["tx_skpagecomments_comments"]["feInterface"],
	"columns" => Array (
		'sys_language_uid' => array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => array (
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.xml:LGL.allLanguages',-1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.default_value',0)
				)
			)
		),
		'l18n_parent' => Array (		
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.l18n_parent',
			'config' => Array (
				'type' => 'select',
				'items' => Array (
					Array('', 0),
				),
				'foreign_table' => 'tx_skpagecomments_comments',
				'foreign_table_where' => 'AND tx_skpagecomments_comments.pid=###CURRENT_PID### AND tx_skpagecomments_comments.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => Array (		
			'config' => Array (
				'type' => 'passthrough'
			)
		),
		"hidden" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.xml:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"starttime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.xml:LGL.starttime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"default" => "0",
				"checkbox" => "0"
			)
		),
		"endtime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.xml:LGL.endtime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"checkbox" => "0",
				"default" => "0",
				"range" => Array (
					"upper" => mktime(0,0,0,12,31,2020),
					"lower" => mktime(0,0,0,date("m")-1,date("d"),date("Y"))
				)
			)
		),
		"fe_group" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.xml:LGL.fe_group",
			"config" => Array (
				"type" => "select",
				"items" => Array (
					Array("", 0),
					Array("LLL:EXT:lang/locallang_general.xml:LGL.hide_at_login", -1),
					Array("LLL:EXT:lang/locallang_general.xml:LGL.any_login", -2),
					Array("LLL:EXT:lang/locallang_general.xml:LGL.usergroups", "--div--")
				),
				"foreign_table" => "fe_groups"
			)
		),
		"name" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:sk_pagecomments/locallang_db.xml:tx_skpagecomments_comments.name",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"eval" => "required,trim",
			)
		),
		"email" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:sk_pagecomments/locallang_db.xml:tx_skpagecomments_comments.email",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"eval" => "required,trim",
			)
		),
        "homepage" => Array (        
            "exclude" => 0,        
            "label" => "LLL:EXT:sk_pagecomments/locallang_db.xml:tx_skpagecomments_comments.homepage",        
            "config" => Array (
                "type"     => "input",
                "size"     => "15",
                "max"      => "255",
                "checkbox" => "",
                "eval"     => "trim",
                "wizards"  => array(
                    "_PADDING" => 2,
                    "link"     => array(
                        "type"         => "popup",
                        "title"        => "Link",
                        "icon"         => "link_popup.gif",
                        "script"       => "browse_links.php?mode=wizard",
                        "JSopenParams" => "height=300,width=500,status=0,menubar=0,scrollbars=1"
                    )
                )
            )
        ), 
		"comment" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:sk_pagecomments/locallang_db.xml:tx_skpagecomments_comments.comment",		
			"config" => Array (
				"type" => "text",
				"cols" => "40",	
				"rows" => "5",
			)
		),
		"pageid" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:sk_pagecomments/locallang_db.xml:tx_skpagecomments_comments.pageid",		
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"default" => 0
			)
		),
		"pivar" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:sk_pagecomments/locallang_db.xml:tx_skpagecomments_comments.pivar",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "sys_language_uid;;;;1-1-1, l18n_parent, l18n_diffsource, hidden;;1, name, email, homepage, comment, pageid, pivar")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "starttime, endtime, fe_group")
	)
);
?>
