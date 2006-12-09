<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Steffen Kamper (steffen@dislabs.de)
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
 * Plugin 'Page Comments' for the 'sk_pagecomments' extension.
 *
 * @author	Steffen Kamper <steffen@dislabs.de>
 */


require_once(PATH_tslib.'class.tslib_pibase.php');

class tx_skpagecomments_pi1 extends tslib_pibase {
	var $prefixId = 'tx_skpagecomments_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_skpagecomments_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = 'sk_pagecomments';	// The extension key.
	var $pi_checkCHash = false;
	var $prefixCSS='sk-pagecomments-';
	
	/**
	 * Extension for adding Pagecomments to Pages
	 */
	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		
        #t3lib_div::debug( $GLOBALS['TSFE']->page['title']);
        
		$pageid = $GLOBALS['TSFE']->id; //page-id
		$pidList = $this->pi_getPidList($this->cObj->data['pages'],$this->conf["recursive"]);
		$this->pi_USER_INT_obj = 1;  // Disable caching
		
		if (isset($conf["pidList"])) $pidList = $conf["pidList"];
		
		$err=array();
		$content=$fval='';
		
        $template=$this->cObj->fileResource($this->conf['templateFile']);
        $subpart['comments']=$this->cObj->getSubpart($template,'###COMMENTS###');  
		$subpart['form']=$this->cObj->getSubpart($template,'###FORM###');  
        
        
		if ($GLOBALS['TSFE']->config['config']['sys_language_uid'] != '') {
			$this->sys_language_uid = $GLOBALS['TSFE']->config['config']['sys_language_uid'];	//Get site-language  
		} else {
			$this->sys_language_uid = 0;
		}

			
		//Conf
		if (!$conf["formLayout"]) $formLayout='<div style="clear:both;padding:3px;"><div style="float:right;">###FIELD###</div>###LABEL###</div>'; else $formLayout=$conf['formLayout'];
		if (!$conf["formWrap"]) $formWrap='<div style="width:350px;">|</div>'; else $formWrap=$conf['formWrap'];
		if (!$conf['commentLayout']) $this->commentLayout='<div class="'.$this->prefixCSS.'headline">###NAME### (###EMAIL###) ###PHRASE### ###DATE###</div><div class="'.$this->prefixCSS.'comment">###COMMENT###</div>'; else $this->commentLayout=$conf['commentLayout'];
		if(!$conf['errorLayout']) $errorLayout='<div class="'.$this->prefixCSS.'error">|</div>'; else $errorLayout=$conf['errorLayout'];
		if(!$conf['successLayout']) $successLayout='<div class="'.$this->prefixCSS.'success">|</div>'; else $successLayout=$conf['successLayout'];
		
        
        if (t3lib_extMgm::isLoaded('sr_freecap') && !$this->conf['useCaptcha'] && $this->conf['useFreecap']) {
            require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
            $this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
                    
        }
        
		//Get Rec-count
		$result=$GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)','tx_skpagecomments_comments','pageid="'.$pageid.'"AND pid IN('.$pidList.') AND hidden="0" AND deleted="0"');
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_row($result);
		$reccount=$row[0];
		
		if ($this->piVars['success']==1) $fval=$this->cObj->wrap($this->pi_getLL('entry_success'),$successLayout);
		
		if($conf['startCode']=='LINK' && $this->piVars['showComments']!=1) {
			//Show Link first
			#$content.='<a href="'.t3lib_div::getIndpEnv('REQUEST_URI').(strpos(t3lib_div::getIndpEnv('REQUEST_URI'),'?')?'&':'?').$this->prefixId.'[showComments]=1">'.$this->pi_getLL('showComment').'&nbsp;&nbsp;('.$reccount.')</a>';
			$content.=$this->pi_linkTP_keepPIvars($this->pi_getLL('showComment').'&nbsp;&nbsp;('.$reccount.')',array('showComments'=>1),0,1);
		} else {
			
			//Felder vorbelegen
			$formfields['name']=$this->pi_getLL('name_value');
			$formfields['email']=$this->pi_getLL('email_value');
			$formfields['comment']=$this->pi_getLL('comment_value');
			
			//Wurde gepostet ?
			if (isset($this->piVars['submit'])) {
                
                #t3lib_div::debug($this->piVars);
				/*   
                foreach($this->piVars as $a => $b)
				{
					if(strpos('name,email,comment',$a)>0) $insertArr[$a]=htmlspecialchars($b);
				}
                */
                $insertArr['name']=htmlspecialchars($this->piVars['name']); 
                $insertArr['email']=htmlspecialchars($this->piVars['email']); 
                $insertArr['comment']=htmlspecialchars($this->piVars['comment']); 
				$insertArr['crdate']=time();
				$insertArr['tstamp']=time();
				$insertArr['pageid']=$pageid;
				$insertArr['pid']=$pidList;
				
				
				//Validate
				if ( (!(eregi('^[a-z0-9_\.-]+@[a-z0-9_-]+\.[a-z0-9_\.-]+$',$insertArr['email']))) && (strlen($insertArr['email'])>0) || $insertArr['email']=="" || $insertArr['email']==$this->pi_getLL('email_value')) {
					$err[]=$this->pi_getLL('email_error');
				}
				if($insertArr['name']=="" || $insertArr['name']==$this->pi_getLL('name_value') || strlen($insertArr['name'])<$conf['minCharsName']) {
					$err[]=$this->pi_getLL('name_error');
				}
				if($insertArr['comment']=="" || $insertArr['comment']==$this->pi_getLL('comment_value') || strlen($insertArr['comment'])<$conf['minCharsComment']) {
					$err[]=$this->pi_getLL('comment_error');
				}
                
                #captcha response
                if (t3lib_extMgm::isLoaded('captcha') && $this->conf['useCaptcha'])	{
	                session_start();
	                if ($this->piVars['captchaResponse']!=$_SESSION['tx_captcha_string']) {
                       $err[]=$this->pi_getLL('captcha_error');    
                    }
	                $_SESSION['tx_captcha_string'] = '';
                }
                
                #freecap response
                if (t3lib_extMgm::isLoaded('sr_freecap') && !$this->conf['useCaptcha'] && $this->conf['useFreecap'] && is_object($this->freeCap) && !$this->freeCap->checkWord($this->piVars['captcha_response'])) {
                        $err[]=$this->pi_getLL('captcha_error');
                }
 
                
				if(count($err)>0) {
					//error
					$fval=$this->cObj->wrap(implode('<br>',$err),$errorLayout);
					#$formfields['name']=$insertArr['name'];
					#$formfields['email']=$insertArr['email'];
					#$formfields['comment']=$insertArr['comment'];
				} else {
					//$GLOBALS['TYPO3_DB']->debugOutput = true;
					
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_skpagecomments_comments',$insertArr);
                    if($this->conf['emailNewMessage'] && $this->conf['emailAddress'] && $this->conf['emailFrom']) {
                         $msg='User '.$insertArr['name'].' added a comment at '.date('Y-m-d H:i',$insertArr['crdate']).' on Page "'.$GLOBALS['TSFE']->page['title'].'" : '.$insertArr['comment'];
                         $this->cObj->sendNotifyEmail($msg, $this->conf['emailAddress'], '', $this->conf['emailFrom'], $email_fromName='PageComments', $this->conf['emailFrom']);
                    }
					header('Location: '.t3lib_div::getIndpEnv('REQUEST_URI').(strpos(t3lib_div::getIndpEnv('REQUEST_URI'),'?')?'&':'?').$this->prefixId.'[showComments]=1&'.$this->prefixId.'[success]=1');
					exit;
				}
				
			}
				
			
			//Kommentare anzeigen
               
            
            $order='tstamp '.$conf['sortOrder'];
			$limit=$conf['maxRecords']>0 ? $conf['maxRecords'] : '';
			$result=$GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_skpagecomments_comments','pageid="'.$pageid.'"AND pid IN('.$pidList.') AND hidden="0" AND deleted="0"',$order,$limit);
			if($reccount>0) {
				if($conf['showCount']==1) $content .= '<div class="'.$this->prefixCSS.'counter">'.$reccount.' '.(($reccount==1) ? $this->pi_getLL('comment') : $this->pi_getLL('comments')).'</div>';
				$content.='<a name="comments"></a>';
                while($temp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
					/*
                    $layout=str_replace('###PHRASE###',$this->pi_getLL('wrote'),$this->commentLayout);
					$layout=str_replace('###DATE###',date('d.m.Y H:i',$temp['tstamp']),$layout);
					$layout=str_replace('###NAME###',htmlspecialchars($temp['name']),$layout);
					$layout=str_replace('###EMAIL###',htmlspecialchars($temp['email']),$layout);
					$layout=str_replace('###COMMENT###',$temp['comment'],$layout);
					$content.=$layout;
                    */
                    $markerArray['###DATEPHRASE###']=sprintf($this->pi_getLL('wrote'),date('d.m.Y H:i',$temp['tstamp']));
                    $markerArray['###DATE###']=date('d.m.Y H:i',$temp['tstamp']);
                    $markerArray['###NAME###']=htmlspecialchars($temp['name']);
                    $markerArray['###EMAIL###']=htmlspecialchars($temp['email']);
                    $markerArray['###COMMENT###']=$temp['comment'];
                    $content.=$this->cObj->substituteMarkerArrayCached($subpart['comments'],$markerArray,array(),array());    
				}
			}
			
			
			// Ist eingeloggter User ?
			$feuser=false;
			if(isset($GLOBALS['TSFE']->fe_user->user['uid'])) {
				$feuser=true;
				#$formfields['name']=$GLOBALS['TSFE']->fe_user->user['username'];
				#$formfields['email']=$GLOBALS['TSFE']->fe_user->user['email'];
                
			}
			
			if($conf['showForm']==1 && $this->piVars['success']!=1 && (($conf['commentOnlyRegistered']==0) || ($conf['commentOnlyRegistered']==1 && $feuser===true))) {
				//Kommentar-Formular
				$content.=$fval; #.'<fieldset><legend>'.$this->pi_getLL('new_comment').'</legend>';
				/*
                $form=array();
				if($feuser) {
					
					$form[]=array($this->pi_getLL('name'), $this->prefixId.'[name]='.($conf['showRegisteredFields']==1 ? 'input , 25' : 'hidden'),$formfields['name']);
					$form[]=array($this->pi_getLL('mail'), $this->prefixId.'[email]='.($conf['showRegisteredFields']==1 ? 'input , 25' : 'hidden'),$formfields['email']);
				} else {
					$form[]=array($this->pi_getLL('name'), $this->prefixId.'[name]=input , 25',$formfields['name']);
					$form[]=array($this->pi_getLL('mail'), $this->prefixId.'[email]=input , 25',$formfields['email']);
				}
				$form[]=array($this->pi_getLL('comment'), $this->prefixId.'[comment]=textarea',$formfields['comment']);
				$form[]=array('',$this->prefixId.'[submit]=submit',$this->pi_getLL('submit'));
                $form[]=array('','no_cache=hidden',1); 
				$fconf=array(
					'type'=>t3lib_div::getIndpEnv('REQUEST_URI'),
					'layout'=>$formLayout,
				);
				$content.=$this->cObj->wrap($this->cObj->FORM($fconf,$form),$formWrap).'</fieldset>';
                */
                $markerArray['###NAME###']=$this->prefixId.'[name]';
                $markerArray['###EMAIL###']=$this->prefixId.'[email]';     
                $markerArray['###SUBMIT###']=$this->prefixId.'[submit]';  
                $markerArray['###COMMENT###']=$this->prefixId.'[comment]';  
                
                $markerArray['###V_NAME###']=$feuser?$GLOBALS['TSFE']->fe_user->user['username']:$insertArr['name']?$insertArr['name']:$this->pi_getLL('name_value');
                $markerArray['###V_EMAIL###']=$feuser?$GLOBALS['TSFE']->fe_user->user['email']:$insertArr['email']?$insertArr['email']:$this->pi_getLL('email_value');
                $markerArray['###V_COMMENT###']=$insertArr['comment']?$insertArr['comment']:$this->pi_getLL('comment_value'); 
                $markerArray['###V_SUBMIT###']=$this->pi_getLL('submit'); 
                 
                $markerArray['###L_NAME###']=$this->pi_getLL('name');    
                $markerArray['###L_EMAIL###']=$this->pi_getLL('mail');    
                $markerArray['###L_COMMENT###']=$insertArr['comment']?$insertArr['comment']:$this->pi_getLL('comment');    
                $markerArray['###L_CAPTCHA###']=$this->pi_getLL('captcha');    
                   
                   
                $markerArray['###ACTION###']='#comments';    
                $markerArray['###LEGEND###']=$this->pi_getLL('new_comment');
                
                #captcha
                if (t3lib_extMgm::isLoaded('captcha') && $this->conf['useCaptcha'])	{
	                $markerArray['###CAPTCHA###'] = '<img src="'.t3lib_extMgm::siteRelPath('captcha').'captcha/captcha.php" alt="" /><input type="text" size=10 name="'.$this->prefixId.'[captchaResponse]" value="">';
                } else {
	                $subpartArray['###CAPTCHA###'] = '';
                }
                
                #freecap
                if (t3lib_extMgm::isLoaded('sr_freecap') && !$this->conf['useCaptcha'] && $this->conf['useFreecap']) {
                    $markerArray = array_merge($markerArray, $this->freeCap->makeCaptcha());
                    $subpartArray['###CAPTCHA###'] = '';  
                } else {
                    $subpartArray['###CAPTCHA_INSERT###'] = ''; 
                }
                
               
                $content.=$this->cObj->substituteMarkerArrayCached($subpart['form'],$markerArray,$subpartArray,array()); 
                
			}
		}
		
		return $this->pi_wrapInBaseClass($content);
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php']);
}

?>
