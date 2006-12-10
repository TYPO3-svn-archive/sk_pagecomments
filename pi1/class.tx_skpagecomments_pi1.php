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
		$content=$errormsg='';
		
        $template=$this->cObj->fileResource($this->conf['templateFile']);
        $subpart['comments']=$this->cObj->getSubpart($template,'###COMMENTS###');  
		$subpart['form']=$this->cObj->getSubpart($template,'###FORM###');  
        $subpart['error']=$this->cObj->getSubpart($template,'###ERROR###');  
        $subpart['success']=$this->cObj->getSubpart($template,'###SUCCESS###');  
        
        
		if ($GLOBALS['TSFE']->config['config']['sys_language_uid'] != '') {
			$this->sys_language_uid = $GLOBALS['TSFE']->config['config']['sys_language_uid'];	//Get site-language  
		} else {
			$this->sys_language_uid = 0;
		}

			
		//Conf
		
        
        if (t3lib_extMgm::isLoaded('sr_freecap') && !$this->conf['useCaptcha'] && $this->conf['useFreecap']) {
            require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
            $this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
                    
        }
        
		//Get Rec-count
		$result=$GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)','tx_skpagecomments_comments','pageid="'.$pageid.'"AND pid IN('.$pidList.') AND hidden="0" AND deleted="0"');
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_row($result);
		$reccount=$row[0];
		
		if ($this->piVars['success']==1) $errormsg=$this->cObj->wrap($this->pi_getLL('entry_success'),$successLayout);
		
		if($conf['showCommentsLink ']=='1' && $this->piVars['showComments']!=1) {
			$content.=$this->pi_linkTP_keepPIvars($this->pi_getLL('showComment').'&nbsp;&nbsp;('.$reccount.')',array('showComments'=>1),0,1);
		} else {
			
			
			//Wurde gepostet ?
			if (isset($this->piVars['submit'])) {
                
                #t3lib_div::debug($this->piVars);
				$insertArr['name']=htmlspecialchars($this->piVars['name']); 
                $insertArr['email']=htmlspecialchars($this->piVars['email']); 
                $insertArr['comment']=$this->piVars['comment']; 
				$insertArr['crdate']=time();
				$insertArr['tstamp']=time();
				$insertArr['pageid']=$pageid;
				$insertArr['pid']=$pidList;
				if(intval($this->conf['hideNewMsg'])>0) $insertArr['hidden']=1;            
				
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
					$errormsg=$this->cObj->substituteMarkerArrayCached($subpart['error'],array('###ERRORMSG###'=>implode('<br />',$err)),array(),array()); 
				} else {
					//$GLOBALS['TYPO3_DB']->debugOutput = true;
					
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_skpagecomments_comments',$insertArr);
                    if($this->conf['emailNewMessage']==1 && $this->conf['emailAddress'] && $this->conf['emailFrom']) {
                         $msg='Neuer Kommentar:User '.$insertArr['name'].' added a comment at '.date('Y-m-d H:i',$insertArr['crdate']).' on Page "'.$GLOBALS['TSFE']->page['title'].'" : '.$insertArr['comment'];
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
				if($this->conf['showCount']==1) $content .= '<div class="'.$this->prefixCSS.'counter">'.$reccount.' '.(($reccount==1) ? $this->pi_getLL('comment') : $this->pi_getLL('comments')).'</div>';
				$content.='<a name="comments"></a>';
                while($temp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
					if(intval($this->conf['showEmail '])!=0) {
                        $this->conf['commentName.']['typolink.']['parameter']=$temp['email'];
                        $this->conf['commentEmail.']['typolink.']['parameter']=$temp['email'];
                    }
                    
                    $markerArray['###DATEPHRASE###']=sprintf($this->pi_getLL('wrote'),date('d.m.Y H:i',$temp['tstamp']));
                    $markerArray['###DATE###']=date('d.m.Y H:i',$temp['tstamp']);
                    $markerArray['###NAME###']=$this->cObj->stdWrap($temp['name'],$this->conf['commentName.']);
                    
                    $markerArray['###EMAIL###']=$this->cObj->stdWrap($temp['email'],$this->conf['commentEmail.']);
                    $markerArray['###COMMENT###']=$this->displayComment($temp['comment']);
                    $content.=$this->cObj->substituteMarkerArrayCached($subpart['comments'],$markerArray,$subpartArray,array());    
				}
			}
			
			
			// Ist eingeloggter User ?
			$feuser=(isset($GLOBALS['TSFE']->fe_user->user['uid']));
		 
            
			#t3lib_div::debug($this->conf); 
			if($this->conf['showForm']==1 && $this->piVars['success']!=1 && (($this->conf['commentOnlyRegistered']==0) || ($this->conf['commentOnlyRegistered']==1 && $feuser===true))) {
				if($this->conf['showFormLink']==1 && $this->piVars['showForm']!=1) {
                    $content.=$this->pi_linkTP_keepPIvars($this->pi_getLL('new_comment'),array('showForm'=>1),0,1); 
                    
                } else {
                
                
                    //Kommentar-Formular
				    $markerArray['###ERRORMSG###']=$errormsg;
				    
                    $markerArray['###HIDDENFIELDS###']='';
                    $markerArray['###ATT_NAME###']='';  
                    $markerArray['###ATT_EMAIL###']='';  
                    
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
                    $markerArray['###L_COMMENT###']=$this->pi_getLL('comment');    
                    $markerArray['###L_CAPTCHA###']=$this->pi_getLL('captcha');    
                       
                       
                    $markerArray['###ACTION###']=t3lib_div::getIndpEnv('REQUEST_URI').'#endofcommentform';  
                    $markerArray['###SMILEYS###']=$this->smileys();  
                    $markerArray['###LEGEND###']=$this->pi_getLL('new_comment');
                    
                    #captcha
                    if (t3lib_extMgm::isLoaded('captcha') && $this->conf['useCaptcha'])	{
	                    $markerArray['###CAPTCHAINPUT###'] = '<input type="text" id="captcha" size=10 name="'.$this->prefixId.'[captchaResponse]" value="" />';
                        $markerArray['###CAPTCHAPICTURE###'] = '<img src="'.t3lib_extMgm::siteRelPath('captcha').'captcha/captcha.php" alt="" />';
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
                    
                    if($feuser) {  
                         if($conf['showRegisteredFields']==1) {
                            $markerArray['###ATT_NAME###']='disabled="disabled"';  
                            $markerArray['###ATT_EMAIL###']='disabled="disabled"'; 
                         } else {
                            $subpartArray['###FORM_NAME###'] = ''; 
                            $subpartArray['###FORM_EMAIL###'] = ''; 
                            $markerArray['###HIDDENFIELDS###'].='<input type="hidden" name="'.$this->prefixId.'[name]'.'" value="'.$GLOBALS['TSFE']->fe_user->user['username'].'" />
                            <input type="hidden" name="'.$this->prefixId.'[email]'.'" value="'.$GLOBALS['TSFE']->fe_user->user['email'].'" />';    
                         }
                    }
                   
                    $content.=$this->cObj->substituteMarkerArrayCached($subpart['form'],$markerArray,$subpartArray,array()).'<a name="endofcommentform"></a>'; 
                }
			}
		}
		
		return $this->pi_wrapInBaseClass($content);
	}
    
    function smileys() {
        $res=t3lib_extMgm::siteRelPath('sk_pagecomments').'res/smileys/';
        $GLOBALS['TSFE']->additionalHeaderData['sk_pagecomments_smileys'] = '
					<script type="text/javascript">
						/*<![CDATA[*/
						function dosmilie(Smilie)	
                        {
	                        document.getElementById(\'comment\').focus();  
                            document.getElementById(\'comment\').value+=" "+Smilie+" ";
	                        
                        }   
						/*]]>*/
					</script>
				';				
        $smile='<div id="skpagecomments-smileys">
			<a class="noul" href="javascript:dosmilie(\':)\')"><img src="'.$res.'icon_smile.gif" alt="smile" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\';)\')"><img src="'.$res.'icon_wink.gif" alt="zwinker" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':biggrin:\')"><img src="'.$res.'icon_biggrin.gif" alt="Big Grins" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':confused:\')"><img src="'.$res.'icon_confused.gif" alt="Confused" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':cool:\')"><img src="'.$res.'icon_cool.gif" alt="Cool" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':cry:\')"><img src="'.$res.'icon_cry.gif" alt="Cry" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':eek:\')"><img src="'.$res.'icon_eek.gif" alt="Eek" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':evil:\')"><img src="'.$res.'icon_evil.gif" alt="Evil" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':frown:\')"><img src="'.$res.'icon_frown.gif" alt="Frown" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':mad:\')"><img src="'.$res.'icon_mad.gif" alt="Mad" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':mrgreen:\')"><img src="'.$res.'icon_mrgreen.gif" alt="Mr. Green" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':neutral:\')"><img src="'.$res.'icon_neutral.gif" alt="Neutral" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':razz:\')"><img src="'.$res.'icon_razz.gif" alt="Razz" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':redface:\')"><img src="'.$res.'icon_redface.gif" alt="Redface" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':rolleyes:\')"><img src="'.$res.'icon_rolleyes.gif" alt="Rolleyes" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':sad:\')"><img src="'.$res.'icon_sad.gif" alt="Sad" border="0" /></a>
			<a class="noul" href="javascript:dosmilie(\':surprised:\')"><img src="'.$res.'icon_surprised.gif" alt="Surprised" border="0" /></a>
			</div>';
        return $smile;
		
    }
    
    function displayComment($comment) {
        $res=t3lib_extMgm::siteRelPath('sk_pagecomments').'res/smileys/';   
        $comment = str_replace(":)",'<img src="'.$res.'icon_smile.gif" alt="smile" class="smilie" alt="smile" border="0" />',$comment);
	    $comment = str_replace(";)",'<img src="'.$res.'icon_wink.gif" alt="zwinker" class="smilie" />',$comment);
	    $comment = str_replace(":D",'<img src="'.$res.'icon_biggrin.gif" alt="big green" class="smilie" border="0" />',$comment);
	    $comment = ereg_replace(":biggrin:", '<img src="'.$res.'icon_biggrin.gif" alt="Big Grins" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":confused:", '<img src='.$res.'icon_confused.gif" alt="Confused" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":cool:", '<img src="'.$res.'icon_cool.gif" alt="Cool" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":cry:", '<img src="'.$res.'icon_cry.gif" alt="Cry" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":eek:", '<img src="'.$res.'icon_eek.gif" alt="Eek" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":evil:", '<img src="'.$res.'icon_evil.gif" alt="Evil" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":frown:", '<img src="'.$res.'icon_frown.gif" alt="Frown" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":mad:", '<img src="'.$res.'icon_mad.gif" alt="Mad" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":mrgreen:", '<img src="'.$res.'icon_mrgreen.gif" alt="Mr. Green" class="smilie" border="0"', $comment);
	    $comment = ereg_replace(":neutral:", '<img src="'.$res.'icon_neutral.gif" alt="Neutral" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":razz:", '<img src="'.$res.'icon_razz.gif" alt="Razz" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":redface:", '<img src="'.$res.'icon_redface.gif" alt="Redface" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":rolleyes:", '<img src="'.$res.'icon_rolleyes.gif" alt="Rolleyes" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":sad:", '<img src="'.$res.'icon_sad.gif" alt="Sad" class="smilie" border="0" />', $comment);
	    $comment = ereg_replace(":surprised:", '<img src="'.$res.'icon_surprised.gif" alt="Surprised" class="smilie" border="0" />', $comment);
	    
	    
	    //Zeilenumbrüche umwandeln
	    $comment=preg_replace('/\r\n|\r|\n/', " \n", $comment);

	    //Links umwandeln
	    $comment=preg_replace("/http:\/\/(.+?)[[:space:]]/si"," <a href=\"http://$1\" target=\"_blank\">$1</a> ",$comment);
        
        
        return nl2br($comment);
    }
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php']);
}

?>
