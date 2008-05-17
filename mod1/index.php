<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Steffen Kamper <steffen@dislabs.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Module 'Page Comments' for the 'sk_pagecomments' extension.
 *
 * @author	Steffen Kamper <steffen@dislabs.de>
 */



	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require_once("conf.php");
require_once($BACK_PATH."init.php");
require_once($BACK_PATH."template.php");
$LANG->includeLLFile("EXT:sk_pagecomments/mod1/locallang.xml");
require_once(PATH_t3lib."class.t3lib_scbase.php");
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]

class tx_skpagecomments_module1 extends t3lib_SCbase {
	var $pageinfo;

	/**
	 * Initializes the Module
	 * @return	void
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

		/*
		if (t3lib_div::_GP("clear_all_cache"))	{
			$this->include_once[]=PATH_t3lib."class.t3lib_tcemain.php";
		}
		*/
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			"function" => Array (
				"1" => $LANG->getLL("function1"),
				#"2" => $LANG->getLL("function2"),
				#"3" => $LANG->getLL("function3"),
			),
			"sort" => Array (
				'1'=>'pages,date',
				'2'=>'date',
				'3'=>'name',
			),
			"limit" => Array (
				'1'=>'All',
				'2' => '100',
				'3' => '50',
				'4' => '30',
				'5' => '10',
			)
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return	[type]		...
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user["admin"] && !$this->id))	{

            //process hide / unhid / delete
            $gp=t3lib_div::_GP('pagecomments');
            if(intval($gp['hide'])>0) {
                $res=$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_skpagecomments_comments','uid='.intval($gp['hide']),array('hidden'=>1)); 
            }
            if(intval($gp['unhide'])>0) {
                $res=$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_skpagecomments_comments','uid='.intval($gp['unhide']),array('hidden'=>0)); 
            }
            if(intval($gp['delete'])>0) {
                $res=$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_skpagecomments_comments','uid='.intval($gp['delete']),array('deleted'=>1)); 
            }
            
				// Draw the header.
			$this->doc = t3lib_div::makeInstance("bigDoc");
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="POST">';

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = 0;
				</script>
			';

			$headerSection = $this->doc->getHeader("pages",$this->pageinfo,$this->pageinfo["_thePath"])."<br />".$LANG->sL("LLL:EXT:lang/locallang_core.xml:labels.path").": ".t3lib_div::fixed_lgd_pre($this->pageinfo["_thePath"],50);

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section("",$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,"SET[function]",$this->MOD_SETTINGS["function"],$this->MOD_MENU["function"])));
			$this->content.=$this->doc->divider(5);


			// Render content:
			$this->moduleContent();


			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section("",$this->doc->makeShortcutIcon("id",implode(",",array_keys($this->MOD_MENU)),$this->MCONF["name"]));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance("mediumDoc");
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return	void
	 */
	function moduleContent()	{
		switch((string)$this->MOD_SETTINGS["function"])	{
			case 1:
				$content="";
				$content.='Sort by: '.t3lib_BEfunc::getFuncMenu($this->id,"SET[sort]",$this->MOD_SETTINGS["sort"],$this->MOD_MENU["sort"]);
				#$content.=' limit records per page: '.t3lib_BEfunc::getFuncMenu($this->id,"SET[limit]",$this->MOD_SETTINGS["limit"],$this->MOD_MENU["limit"]);
				$content.='<hr>';
				
				$this->content.=$this->doc->section("Please select a Page to see the comments:",$content,0,1);
				$pid=$_GET['id'];
				$content=$this->showComments($pid);
				if($pid>0) $page=t3lib_BEfunc::getRecord('pages',$pid);
				$this->content.=$this->doc->section('','<p class="bgColor2"><strong>'."Comments on".($pid==0 ? " all pages":" page $pid (".$page['title'].")").'</strong></p>'.$content,0,1);
			break;
			case 2:
				$content="<div align=center><strong><pre>".print_r($this,true)."</pre></strong></div>";
				$this->content.=$this->doc->section("Message #2:".$this->id,$content,0,1);
			break;
			case 3:
				$content="<div align=center><strong>Menu item #3...</strong></div>";
				$this->content.=$this->doc->section("Message #3:",$content,0,1);
			break;
		}
	}
	
	function showComments($pid) {
		global $BACK_PATH;
		$content="";
		
		switch($this->MOD_SETTINGS["sort"]) {
			case 1:
				$sqlOrder='pid,crdate desc';
			break;
			case 2:
				$sqlOrder='crdate desc';
			break;
			case 3:
				$sqlOrder='`name`';
			break;
			default:
				$sqlOrder='pid,crdate desc';
		}
		switch($this->MOD_SETTINGS["limit"]) {
			case 1:
				$sqlLimit='';
			break;
			case 2:
				$sqlLimit='100';
			break;
			case 3:
				$sqlLimit='50';
			break;
			case 4:
				$sqlLimit='30';
			break;
			case 5:
				$sqlLimit='10';
			break;
			default:
				$sqlLimit='';
		}
		$query = $GLOBALS['TYPO3_DB']->SELECTquery(
		    '*',
		    'tx_skpagecomments_comments',
		    'deleted=0 '.($pid>0 ? "and pid=$pid" : ''),
		    '',
		    $sqlOrder,
		    $sqlLimit
		    );
        
		$res = $GLOBALS['TYPO3_DB']->sql_query($query);
		if ($res) {
			$content.='<table cellpadding="1" cellspacing="1" class="bgColor4" width="100%">';
			$content.='<tr class="tableheader bgColor5"><td>Page</td><td>Date</td><td>Name</td><td>Message</td><td>edit</td><td>hide</td><td>delete</td></tr>';
			$i=0;
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$content.='<tr class="'.($i++ % 2==0 ? 'bgColor3' : 'bgColor4').'">';
				$page=t3lib_BEfunc::getRecord('pages',$row['pid']);
                if($row['hidden']==1) {
                    $pagepic=$BACK_PATH.t3lib_iconWorks::getIcon('pages__h');
                    $hide='<a href="index.php?pagecomments[unhide]='.$row['uid'].'"><img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/button_unhide.gif','width="11" height="12"').' vspace="2" align="top" title="unhide" alt="" /></a>';
                } else {
                    $pagepic=$BACK_PATH.t3lib_iconWorks::getIcon('pages');
                    $hide='<a href="index.php?pagecomments[hide]='.$row['uid'].'"><img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/button_hide.gif','width="11" height="12"').' vspace="2" align="top" title="hide" alt="" /></a>';
                }
                $del='<a href="index.php?pagecomments[delete]='.$row['uid'].'"><img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/delete_record.gif','width="11" height="12"').' vspace="2" align="top" title="delete" alt="" /></a>';  
                    
				$content.='<td valign="top"><a href="#" onclick="previewWin=window.open(\''.$BACK_PATH.'../index.php?id='.$row['pid'].($row['pivar']!=''?'&'.$row['pivar']:'').'#comment'.$row['uid'].'\',\'newTYPO3frontendWindow\');previewWin.focus();"><img src="'.$pagepic.'" title="'.$page['title'].'" width="18" height="16" align="top" alt="" /></a>&nbsp;'.$row['pid'].'</td>';
				$content.='<td valign="top" style="color:blue;">'.t3lib_BEfunc::dateTimeAge($row['crdate'],1).'</td>';
				$content.='<td valign="top" style="color:green;">'.$row['name'].'</td>';
				$content.='<td valign="top">'.htmlspecialchars(substr($row['comment'],0,60)).'...'.'</td>';
				$content.='<td valign="top">'.$this->getItemFromRecord('tx_skpagecomments_comments',$row).'</td>';
				$content.='<td valign="top">'.$hide.'</td>';
				$content.='<td valign="top">'.$del.'</td>';
				
				
                $content.='</tr>';
			}
			$content.='</table>';
		}
		return $content;
	}
	
	function getItemFromRecord($table,$row) {
		global $BACK_PATH,$LANG,$TCA,$BE_USER;
		
		
		$iconAltText = t3lib_BEfunc::getRecordIconAltText($row,$table);
		$elementTitle=t3lib_BEfunc::getRecordPath($row['uid'],'1=1',0);
		$elementTitle=t3lib_div::fixed_lgd_cs($elementTitle,-($BE_USER->uc['titleLen']));
		#$elementIcon=t3lib_iconworks::getIconImage($table,$row,$BACK_PATH,'class="c-recicon" title="'.$iconAltText.'"');
		$elementIcon='<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/edit2.gif','width="11" height="12"').' vspace="2" align="top" title="edit" alt="" />';
		$params='&edit['.$table.']['.$row['uid'].']=edit';
		$editOnClick=t3lib_BEfunc::editOnClick($params,$BACK_PATH);
		
		return '<a href="#" onclick="'.htmlspecialchars($editOnClick).'">'.$elementIcon.'</a>';
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/mod1/index.php']);
}




// Make instance:
$SOBE = t3lib_div::makeInstance('tx_skpagecomments_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
