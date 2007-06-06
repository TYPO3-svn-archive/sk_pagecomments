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
 * @author	Steffen Kamper <info@sk-typo3.de>
 */


require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(PATH_t3lib.'class.t3lib_iconworks.php');

class tx_skpagecomments_pi1 extends tslib_pibase {
	var $prefixId = 'tx_skpagecomments_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_skpagecomments_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = 'sk_pagecomments';	// The extension key.
	var $pi_checkCHash = false;
	var $prefixCSS='sk-pagecomments-';
	var $lookForValue;
    var $template;
    var $subpart;
    var $number;
    var $pageid;
    var $URLParamsArray;
    
	/**
	 * Extension for adding Pagecomments to Pages
	 */
	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;  // Disable caching                 
        
        //link to homepage ?
        if(intval($this->piVars['goto'])>0) {
            $res=$GLOBALS['TYPO3_DB']->exec_SELECTquery('homepage','tx_skpagecomments_comments','hidden=0 and deleted=0 and uid='.intval($this->piVars['goto']));
            if($res) {
                $row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
                
                header('location:'.(substr($row['homepage'],0,4)=='http'?$row['homepage']:'http://'.$row['homepage']));
                exit;
            }
        }
		
        //admin link ?
        if($this->isAdmin() && $this->piVars['hide'])  {
            $res=$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_skpagecomments_comments','uid='.$this->piVars['hide'],array('hidden'=>$this->piVars['status']));
        }
        
        $this->template=$this->cObj->fileResource($this->conf['templateFile']);
        $this->subpart['commentlink']=$this->cObj->getSubpart($this->template,'###COMMENTLINK###');
        $this->subpart['formlink']=$this->cObj->getSubpart($this->template,'###FORMLINK###');   
        $this->subpart['comments']=$this->cObj->getSubpart($this->template,'###COMMENTS###');  
		$this->subpart['form']=$this->cObj->getSubpart($this->template,'###FORM###');  
        $this->subpart['error']=$this->cObj->getSubpart($this->template,'###ERROR###');  
        $this->subpart['success']=$this->cObj->getSubpart($this->template,'###SUCCESS###');  
        $this->subpart['mail']=$this->cObj->getSubpart($this->template,'###MAILING###');  
        $this->subpart['teaser']=$this->cObj->getSubpart($this->template,'###COMMENTTEASER###');  
        
        
        
        
        $this->pageid = $GLOBALS['TSFE']->id; //page-id
		$this->orig_pageid = $this->pageid;
		
        $pidList = $this->pi_getPidList($this->cObj->data['pages'],$this->conf["recursive"]);
		if (isset($conf["pidList"])) $pidList = $conf["pidList"];
		
        
        if ($this->conf['pageid']) {
            $this->pageid=implode(',',array_merge(explode(',',$this->pageid),explode(',',$this->conf['pageid'])));        
            $pidList=implode(',',array_merge(explode(',',$pidList),explode(',',$this->conf['pageid'])));
        }

		
		
		
		$err=array();
		$content=$errormsg='';
		
        
		if ($GLOBALS['TSFE']->config['config']['sys_language_uid'] != '') {
			$this->sys_language_uid = $GLOBALS['TSFE']->config['config']['sys_language_uid'];	//Get site-language  
		} else {
			$this->sys_language_uid = 0;
		}


        //Is TEASER ?
        if($this->conf['teaser']==1) {

            return $this->pi_wrapInBaseClass($this->showTeaser());    
        }		
        
        	
		//Conf
        $this->URLParamsArray=$this->cleanUrlPars($_GET);   
        
        #t3lib_div::debug($this->conf);
        
         
		$addWhere=$getvar='';
        if($this->conf['bindToGETvar']) {
            $var=$this->conf['bindToGETvar'];
            if(strpos($var,'[')) {
                $p1=substr($var,0,strpos($var,'['));
                $p2=substr($var,strlen($p1)+1);
                $p2=substr($p2,0,strlen($p2)-1);
                $getvar=t3lib_div::GPvar($p1);
                $lookForValue=$getvar[$p2];
                $addWhere=" AND piVar='".$this->conf['bindToGETvar'].'='.$lookForValue."'";
                #t3lib_div::debug($addWhere);
            } else {
                $getvar=t3lib_div::GPvar($var);
            }
            
            
        }
        
        if (t3lib_extMgm::isLoaded('sr_freecap') && !$this->conf['useCaptcha'] && $this->conf['useFreecap']) {
            require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
            $this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
                    
        }
        
		//Get Rec-count
        
		$result=$GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)','tx_skpagecomments_comments','pageid IN('.$this->pageid.') AND pid IN('.$pidList.') '.($this->isAdmin() ? '' : 'AND hidden="0"').' AND deleted="0"'.$addWhere);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_row($result);
		$reccount=$row[0];
		
        $result=$GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)','tx_skpagecomments_comments','pageid IN('.$this->pageid.') AND pid IN('.$pidList.') '.($this->isAdmin() ? '' : 'AND hidden="0"').' AND deleted="0" AND parentId="0"'.$addWhere);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_row($result);
		$parentcount=$row[0];
		
		if ($this->piVars['success']==1) $errormsg=$this->cObj->wrap($this->pi_getLL('entry_success'),$successLayout);
        
		if(intval($this->conf['showCommentsLink'])==1 && intval($this->conf['showComments'])==0 && intval($this->piVars['showComments'])!=1) {
            #$wrappedSubpartArray['COMMENTLINKWRAP']=explode('|', $this->cObj->typolink('|',array('parameter'=>$this->pageid,'section'=>'CommentStart'))); # $this->pi_linkTP_keepPIvars('|',array('showComments'=>1),0,1));
			
             if($this->conf['bindToGETvar']) {          
                 $lconf=array_merge($this->conf['formLink.'],array(
                    'parameter' => $this->orig_pageid,
                    'additionalParams' => $this->URLParamsArray.'&'.$this->prefixId.'[showComments]=1',
                    'section' => ($this->conf['useSectionFormLink'] ? 'CommentStart' : ''),
                ));
               $l=$this->cObj->typolink('|',$lconf);   
            } else {
                $lconf=array_merge($this->conf['formLink.'],array(
                    'parameter' => $this->orig_pageid,
                    'additionalParams' => '&'.$this->prefixId.'[showComments]=1',
                    'section' => ($this->conf['useSectionFormLink'] ? 'CommentStart' : ''),
                ));
                $l=$this->cObj->typolink('|',$lconf);
            }
            
            $wrappedSubpartArray['COMMENTLINKWRAP']=explode('|',$l); # $this->pi_linkTP_keepPIvars('|',array('showComments'=>1),0,1));
            $markerArray['###RECORDCOUNT###']=$reccount;
            $markerArray['###LINKTEXT###']=$this->pi_getLL('showComment');
            $content.=$this->cObj->substituteMarkerArrayCached($this->subpart['commentlink'] ,$markerArray,$subpartArray,$wrappedSubpartArray); 
		} else {
			
			
			//Wurde gepostet ?
			if (isset($this->piVars['submit'])) {
                $ip_address=t3lib_div::getIndpEnv('REMOTE_ADDR');
        
                $insertArr['parentId']= intval($this->piVars['answerid']);
                
                $insertArr['name']=htmlspecialchars($this->piVars['name']); 
                $insertArr['email']=htmlspecialchars($this->piVars['email']); 
                $insertArr['homepage']=htmlspecialchars(strtolower($this->piVars['homepage'])); 
                $insertArr['comment']=$this->piVars['comment']; 
				$insertArr['crdate']=time();
				$insertArr['tstamp']=time();
				$insertArr['pageid']=$this->orig_pageid;
				$insertArr['pid']=$pidList;
				if ($GLOBALS['TSFE']->fe_user->user['uid'] > 0) {
					$insertArr['feuser_uid'] = $GLOBALS['TSFE']->fe_user->user['uid'];
				}
				
                if(intval($this->conf['hideNewMsg'])>0) $insertArr['hidden']=1;            
				if($this->conf['bindToGETvar']) $insertArr['piVar']=$this->conf['bindToGETvar'].'='.$lookForValue;
                if(substr($insertArr['homepage'],0,7)=='http://') {
                	$insertArr['homepage']=ereg_replace('http://','',$insertArr['homepage']);
                }elseif(substr($insertArr['homepage'],0,7)=='https://') {
                	$insertArr['homepage']=ereg_replace('https://','',$insertArr['homepage']);
                }
                if($insertArr['homepage']==$this->pi_getLL('homepage_value')) $insertArr['homepage']=''; 
                
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
					$errormsg=$this->cObj->substituteMarkerArrayCached($this->subpart['error'],array('###ERRORMSG###'=>implode('<br />',$err)),array(),array()); 
				} else {
                    if($this->conf['useCookies']>0) {
                        //store values in cookie
                        $time = 60*60*24*$this->conf['useCookies'];
					    setcookie($this->prefixId.'_name', $insertArr['name'], time()+$time);
                        setcookie($this->prefixId.'_email', $insertArr['email'], time()+$time);
					    setcookie($this->prefixId.'_homepage', $insertArr['homepage'], time()+$time);
                    }
                    //insert comment
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_skpagecomments_comments',$insertArr);
                    $insertId=$GLOBALS['TYPO3_DB']->sql_insert_id();
                    if($this->conf['emailNewMessage']==1 && $this->conf['emailAddress'] && $this->conf['emailFrom']) {
                         $link='http://'.t3lib_div::getIndpEnv('HTTP_HOST').'/'.$this->pi_getPageLink($this->orig_pageid);
                         if($this->conf['bindToGETvar']) {
                            //add extra parameter
                            $prefix = strpos($link,'?') ? '&' : '?';
                            $link.=$prefix.$insertArr['piVar'].'&no_cache=1'; # need no_cache for overriding any cHash
                         }
                         $msg=$this->cObj->substituteMarkerArrayCached(
                            $this->subpart['mail'],
                            array(
                                '###USER###'=>$insertArr['name'],
                                '###IP###' => $ip_address,
                                '###DATE###'=>date($this->conf['dateFormat'],$insertArr['crdate']),
                                '###COMMENT###'=>$insertArr['comment'],
                                '###PAGELINK###'=>$link.'#comment'.$insertId,
                                '###PAGETITLE###'=>$GLOBALS['TSFE']->page['title']
                            ),
                            array(),
                            array()
                         );  
                         $this->cObj->sendNotifyEmail($msg, $this->conf['emailAddress'], '', $this->conf['emailFrom'], $email_fromName='PageComments', $this->conf['emailFrom']);
                    }
					header('Location: '.t3lib_div::getIndpEnv('REQUEST_URI').(strpos(t3lib_div::getIndpEnv('REQUEST_URI'),'?')?'&':'?').$this->prefixId.'[showComments]=1&'.$this->prefixId.'[success]=1');
					exit;
				}
				
			}
				
			
			//show comments
            $content.='<a id="CommentStart"></a>';
            if($this->piVars['success'] && $this->conf['hideNewMsg']==1)  {
                $markerArray['###HIDEMSG###']=$this->cObj->stdWrap($this->pi_getLL('hideMsg'),$this->conf['hideMsg.']);
            } else {
                $markerArray['###HIDEMSG###']='';
            }
            
            $number=array();
            $i=1;         
            $result=$GLOBALS['TYPO3_DB']->exec_SELECTquery('uid','tx_skpagecomments_comments','pageid="'.$this->pageid.'" AND pid IN('.$pidList.') '.($this->isAdmin() ? '' : 'AND hidden="0"').' AND deleted="0"'.$addWhere,'crdate asc');
            while($temp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {  
                $this->number[$temp['uid']]=$i++;
            }   
               
            
            $orderDir=strtoupper($this->piVars['showForm']==1 ? $this->conf['sortOrderOnForm']:$this->conf['sortOrder']);
            $order='uid asc';
			$limit=$this->conf['maxRecords']>0 ? $this->conf['maxRecords'] : '';
			
            $result=$GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_skpagecomments_comments','pageid IN('.$this->pageid.') AND pid IN('.$pidList.') '.($this->isAdmin() ? '' : 'AND hidden="0"').' AND deleted="0"'.$addWhere,$order,$limit);
            
			if($reccount>0) {
				$markerArray['###COMMENTCOUNT###']=$this->showFields('count',$reccount.' '.($reccount==1 ? $this->pi_getLL('comment') : $this->pi_getLL('comments')));
                //read into array          
                while($temp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
					$this->comment[$temp['uid']]=$temp;
                    if($temp['parentId']>0) $this->childs[$temp['parentId']][]=$temp;
                }
                
                if($orderDir=='DESC')  $this->comment=array_reverse($this->comment);
                
                //render Comments
                foreach($this->comment as $key=>$temp) {    
                    if($temp['parentId']==0) $contentList.=$this->renderComment($temp);
	            }
                
                $subpartArray['###COMMENTLIST###']=$contentList;
                $subpartArray['###ANSWERLIST###']='';        
                                                                   
                if($this->piVars['success']) $content.='<a id="CommentForm"></a>';
                $content.=$this->cObj->substituteMarkerArrayCached($this->subpart['comments'],$markerArray,$subpartArray,array());  
                $markerArray=array();       
                    
			}
			
			
			// Ist eingeloggter User ?
			$feuser=(isset($GLOBALS['TSFE']->fe_user->user['uid']));
		 
            if(!$this->conf['formLink.']) $this->conf['formLink.']=array();
            
			if($this->conf['showForm']==1 && $this->piVars['success']!=1 && (($this->piVars['answer']) || ($this->conf['commentOnlyRegistered']==0) || ($this->conf['commentOnlyRegistered']==1 && $feuser===true))) {
				if($this->conf['showFormLink']==1 && $this->piVars['showForm']!=1 && !$this->piVars['answer']) {
                    #generate link for form
                    
                    $subpartArray['###FORMLINK###']='';
                    if($this->conf['bindToGETvar']) {          
                         $lconf=array_merge($this->conf['formLink.'],array(
                            'parameter' => $this->orig_pageid,
                            'additionalParams' => $this->URLParamsArray.'&'.$this->prefixId.'[showComments]=1&'.$this->prefixId.'[showForm]=1',
                            'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : ''),
                        ));
                        
                       $l=$this->cObj->typolink($this->pi_getLL('new_comment'),$lconf);   
                        if(intval($lookForValue)>0) {
                            $showLink=1;
                        }
                    } else {
                        $lconf=array_merge($this->conf['formLink.'],array(
                            'parameter' => $this->orig_pageid,
                            'additionalParams' => '&'.$this->prefixId.'[showComments]=1&'.$this->prefixId.'[showForm]=1',
                            'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : ''),
                        ));
                        $l=$this->cObj->typolink($this->pi_getLL('new_comment'),$lconf);
                        $showLink=1;
                        $subpartArray['###FORMLINK###']=$l;   
                    }
                    $markerArray['###LINKTEXT###']=$this->pi_getLL('new_comment');    
                    
                    $wrappedSubpartArray['FORMLINKWRAP']=explode('|', $this->cObj->typolink('|',$lconf));  
                    if($showLink==1) $form.=$this->cObj->substituteMarkerArrayCached($this->subpart['formlink'],$markerArray,$subpartArray,$wrappedSubpartArray); 
                    
                } else {
                    $show=1;
                    if($this->conf['bindToGETvar'] && intval($lookForValue)==0) $show=0;  
                    
                    if($show==1) {
                        //Kommentar-Formular
				        $markerArray['###ERRORMSG###']=$errormsg;
				        
                        $markerArray['###HIDDENFIELDS###']='';
                        $markerArray['###ATT_NAME###']='';  
                        $markerArray['###ATT_EMAIL###']='';  
                        $markerArray['###ATT_HOMEPAGE###']='';  
                        
                        if($this->piVars['answer']) $markerArray['###HIDDENFIELDS###'].='<input type="hidden" name="'.$this->prefixId.'[answerid]'.'" value="'.$this->piVars['answer'].'" />';
                        $markerArray['###NAME###']=$this->prefixId.'[name]';
                        $markerArray['###EMAIL###']=$this->prefixId.'[email]';     
                        $markerArray['###HOMEPAGE###']=$this->prefixId.'[homepage]';     
                        $markerArray['###SUBMIT###']=$this->prefixId.'[submit]';  
                        $markerArray['###COMMENT###']=$this->prefixId.'[comment]';  
                        
                        if($this->conf['useCookies']>0 && !$feuser) {  
                            $insertArr['name']=$_COOKIE[$this->prefixId.'_name'];
                            $insertArr['email']=$_COOKIE[$this->prefixId.'_email']; 
                            $insertArr['homepage']=$_COOKIE[$this->prefixId.'_homepage']; 
                        }
                        
                        $markerArray['###V_NAME###']=$feuser?$GLOBALS['TSFE']->fe_user->user['username']:$insertArr['name']?$insertArr['name']:$this->pi_getLL('name_value');
                        $markerArray['###V_EMAIL###']=$feuser?$GLOBALS['TSFE']->fe_user->user['email']:$insertArr['email']?$insertArr['email']:$this->pi_getLL('email_value');
                        $markerArray['###V_HOMEPAGE###']=$feuser?$GLOBALS['TSFE']->fe_user->user['www']:$insertArr['homepage']?$insertArr['homepage']:$this->pi_getLL('homepage_value');
                        $markerArray['###V_COMMENT###']=$insertArr['comment']?$insertArr['comment']:$this->pi_getLL('comment_value'); 
                        $markerArray['###V_SUBMIT###']=$this->pi_getLL('submit'); 
                         
                        $markerArray['###L_NAME###']=$this->pi_getLL('name');    
                        $markerArray['###L_EMAIL###']=$this->pi_getLL('mail');    
                        $markerArray['###L_HOMEPAGE###']=$this->pi_getLL('homepage');    
                        $markerArray['###L_COMMENT###']=$this->pi_getLL('comment');    
                        $markerArray['###L_CAPTCHA###']=$this->pi_getLL('captcha');    
                           
                           
                        $markerArray['###ACTION###']=strtr(t3lib_div::getIndpEnv('REQUEST_URI'),array('&'=>'&amp;')).'#CommentForm';  
                        $markerArray['###SMILEYS###']=$this->smileys();  
                        $markerArray['###LEGEND###']=$this->piVars['answer'] ? sprintf($this->pi_getLL('legend_answer'),$this->number[$this->piVars['answer']]): $this->pi_getLL('new_comment');
                        
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
                                $markerArray['###ATT_HOMEPAGE###']='disabled="disabled"'; 
                             } else {
                                
                                $subpartArray['###FORM_NAME###'] = '';
                                $subpartArray['###FORM_EMAIL###'] = ''; 
                                $subpartArray['###FORM_HOMEPAGE###'] = ''; 
                                $markerArray['###V_NAME###']=$GLOBALS['TSFE']->fe_user->user['username'];
                                $markerArray['###V_EMAIL###']=$GLOBALS['TSFE']->fe_user->user['email'];
                                $markerArray['###V_HOMEPAGE###']=$GLOBALS['TSFE']->fe_user->user['www'];
                                $markerArray['###HIDDENFIELDS###'].='<input type="hidden" name="'.$this->prefixId.'[name]'.'" value="'.$GLOBALS['TSFE']->fe_user->user['username'].'" />
                                <input type="hidden" name="'.$this->prefixId.'[email]'.'" value="'.$GLOBALS['TSFE']->fe_user->user['email'].'" />    
                                <input type="hidden" name="'.$this->prefixId.'[homepage]'.'" value="'.$GLOBALS['TSFE']->fe_user->user['www'].'" />';    
                             }
                        } else $subpartArray['###FORM_VALUES_USER_LOGGED_IN###']='';
                       
                        $form.=$this->cObj->substituteMarkerArrayCached($this->subpart['form'],$markerArray,$subpartArray,array()).'<a id="CommentForm"></a>'; 
                        unset($this->piVars['answer']);
                    }
                }
			}
		}
		
		return $this->pi_wrapInBaseClass($this->conf['showFormOnTop']==1 ? $form.$content : $content.$form);
	}
    
	function showFields($name, $value) {
		if (isset($this->conf['blind.'][$name]) && $this->conf['blind.'][$name] == 1) {
			return "";
		}
		return $value;
	}
	
    function renderComment($temp,$level=0,$list='') {
        if($level==-1) {
            #t3lib_div::debug($temp['pivar']);
            $lconf=array_merge($this->conf['answerLink.'],array(
                'parameter' => $temp['pageid'],
                'section' => 'comment'.$temp['uid'],
                'additionalParams' => $temp['pivar'] ? '&'.$temp['pivar'] : '',
            ));
            $l=$this->cObj->typolink('|',$lconf);
            $linkWrapArray['###LINK###']=explode('|',$l);   
            $markerArray['###GOTO###']=$this->cObj->stdWrap($this->pi_getLL('goto'),$this->conf['goto.']);     
        } elseif($level==0) {
            $list=$this->cObj->getSubpart($this->subpart['comments'],'###COMMENTLIST###');
        } else {
            $list=$this->cObj->getSubpart($this->subpart['comments'],'###ANSWERLIST###');
        }
        $markerArray['###DATEPHRASE###']=sprintf($this->pi_getLL('wrote'),$this->showDate($this->conf['dateFormat'],$temp['crdate']));
       
        $markerArray['###DATE###']=$this->showDate($this->conf['dateFormat'], $temp['crdate']);
        $markerArray['###NAME###']=$this->cObj->stdWrap($temp['name'],$this->conf['commentName.']);
        $markerArray['###NUMBER###']=$this->showFields('number','<a id="comment'.$temp['uid'].'" title="ID: '.$temp['uid'].'">'.$this->cObj->stdWrap($this->number[$temp['uid']],$this->conf['commentNumber.']).'</a>'); 
        $markerArray['###MARGIN###']=$this->conf['answerMargin']*$level; 
        
        
        if ($this->conf['pageLink'] = 1) {
        	$page = $this->pi_getRecord('pages',$temp['pid']);
	        $markerArray['###PAGELINK###'] = $this->pi_linkToPage($this->cObj->stdWrap($page['title'],$this->conf['pageLink.']),$temp['pid']);
	    }
	    
        $this->conf['emailLink.']['parameter']=$temp['email'];
        $linkWrapArray['###EMAILLINKWRAP###']=explode('|',$this->cObj->typolink('|',$this->conf['emailLink.']));
        
        if($temp['homepage']!='') {
            $this->conf['homepageLink.']['parameter']=$GLOBALS['TSFE']->id; #$temp['homepage'];   
            $this->conf['homepageLink.']['additionalParams']='&'.$this->prefixId.'[goto]='.$temp['uid'];
            $linkWrapArray['###HOMEPAGELINKWRAP###']=$this->showFields('homepage', explode('|',$this->cObj->typolink('|',$this->conf['homepageLink.'])));
        }
        $markerArray['###EMAIL###']=$this->showFields('email', $this->cObj->stdWrap($temp['email'],$this->conf['commentEmail.']));
        $homepage = $temp['homepage']!=''?$this->cObj->stdWrap($temp['homepage'],$this->conf['commentHomepage.']):'';
        $markerArray['###HOMEPAGE###']=$this->showFields('homepage', $homepage);
        
        
       
           
           
        $markerArray['###COMMENT###']=$this->displayComment($temp['comment']);
       
        
        if($this->conf['allowAnswer']) {
            if($this->conf['bindToGETvar']) {          
                 $lconf=array_merge((array) $this->conf['answerLink.'],array(
                    'parameter' => $this->orig_pageid,
                    'additionalParams' => $this->URLParamsArray.'&'.$this->prefixId.'[answer]='.$temp['uid'],
                    'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : ''),
                ));
               $l=$this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('answer'),$this->conf['answer.']),$lconf); 
            } else {
                $lconf=array_merge($this->conf['answerLink.'],array(
                    'parameter' => $this->orig_pageid,
                    'additionalParams' => '&'.$this->prefixId.'[answer]='.$temp['uid'],
                    'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : ''),
                ));
                $l=$this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('answer'),$this->conf['answer.']),$lconf);
            }
        } elseif ($this->conf['registerInfo'] && $this->conf['registerPid']>0) {
        	$lconf=array( 'parameter' => $this->conf['registerPid']);
        	$l = $this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('registerinfo'),$this->conf['registerInfo.']),$lconf);
        	
        }
        $markerArray['###ANSWER###']=$this->showFields('answer', $l);           
		
        if($this->isAdmin()) {
            if($this->conf['bindToGETvar']) {          
                 $lconf=array_merge((array) $this->conf['adminLink.'],array(
                    'parameter' => $this->orig_pageid,
                    'additionalParams' => $this->URLParamsArray.'&'.$this->prefixId.'[hide]='.$temp['uid'].'&'.$this->prefixId.'[status]='.($temp['hidden']==0?1:0),
                ));
            } else {
                $lconf=array_merge($this->conf['adminLink.'],array(
                    'parameter' => $this->orig_pageid,
                    'additionalParams' => '&'.$this->prefixId.'[hide]='.$temp['uid'].'&'.$this->prefixId.'[status]='.($temp['hidden']==0?1:0),
                ));
            }
            if($temp['hidden']==0)
                $l=$this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('hide'),$this->conf['admin.']['hide.']),$lconf);
            else
                $l=$this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('unhide'),$this->conf['admin.']['unhide.']),$lconf);
                    
            $markerArray['###ADMINHIDE###']=$this->showFields('admin', $l);
           
        } else  $markerArray['###ADMINHIDE###']='';
           
           
           
        $content=$markerArray['###COMMENT###'] != '' ? $this->cObj->substituteMarkerArrayCached($list,$markerArray,$subpartArray,$linkWrapArray) : '';  
         
        //has childs ?
        if($level!=-1 && is_array($this->childs[$temp['uid']])) {
             $level+=1;
             foreach($this->childs[$temp['uid']] as $key=>$row) {
               $content.=$this->renderComment($row,$level);
             }
        }
        return $content;
        
        
    }
    function smileys() {
    	if($this->conf['blind.']['smileys'] != 1) {
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
    	}
        return $smile;
		
    }
    
    function displayComment($comment) {
    	if (!isset($this->conf['blind.']['smileys']) || (isset($this->conf['blind.']['smileys']) && $this->conf['blind.']['smileys'] != 1)) {
    		$res=t3lib_extMgm::siteRelPath('sk_pagecomments').'res/smileys/';   
    		$comment = str_replace(":)",'<img src="'.$res.'icon_smile.gif" alt="smile" class="smilie" border="0" />',$comment);
    		$comment = str_replace(";)",'<img src="'.$res.'icon_wink.gif" alt="zwinker" class="smilie" border="0" />',$comment);
    		$comment = str_replace(":D",'<img src="'.$res.'icon_biggrin.gif" alt="big green" class="smilie" border="0" />',$comment);
    		$comment = ereg_replace(":biggrin:", '<img src="'.$res.'icon_biggrin.gif" alt="Big Grins" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":confused:", '<img src="'.$res.'icon_confused.gif" alt="Confused" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":cool:", '<img src="'.$res.'icon_cool.gif" alt="Cool" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":cry:", '<img src="'.$res.'icon_cry.gif" alt="Cry" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":eek:", '<img src="'.$res.'icon_eek.gif" alt="Eek" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":evil:", '<img src="'.$res.'icon_evil.gif" alt="Evil" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":frown:", '<img src="'.$res.'icon_frown.gif" alt="Frown" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":mad:", '<img src="'.$res.'icon_mad.gif" alt="Mad" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":mrgreen:", '<img src="'.$res.'icon_mrgreen.gif" alt="Mr. Green" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":neutral:", '<img src="'.$res.'icon_neutral.gif" alt="Neutral" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":razz:", '<img src="'.$res.'icon_razz.gif" alt="Razz" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":redface:", '<img src="'.$res.'icon_redface.gif" alt="Redface" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":rolleyes:", '<img src="'.$res.'icon_rolleyes.gif" alt="Rolleyes" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":sad:", '<img src="'.$res.'icon_sad.gif" alt="Sad" class="smilie" border="0" />', $comment);
    		$comment = ereg_replace(":surprised:", '<img src="'.$res.'icon_surprised.gif" alt="Surprised" class="smilie" border="0" />', $comment);
    	}

    	$comment=$this->disableXSS($comment);
    		
    	//Zeilenumbr�che umwandeln
    	$comment=preg_replace('/\r\n|\r|\n/', "<br />", trim($comment));

    	//Links umwandeln
    	#$comment=preg_replace("/http:\/\/(.+?)[[:space:]]/si"," <a href=\"http://$1\" target=\"_blank\">$1</a> ",$comment);
    	
        
        return $this->cObj->stdWrap($comment,$this->conf['comment.']);
    }
    
    function cleanUrlPars($arr) {
        $u='';
        foreach($arr as $var=>$val) {
            if(stristr($var,$this->prefixId)===false && $var!='id') {
                if(is_array($val)) {
                    foreach($val as $var1=>$val1) {$u.='&'.$var.'['.$var1.']='.$val1;}
                } else {
                    $u.='&'.$var.'='.$val;
                }
            } else {
            }
        }
        return $u;
    }
    
    function showDate($format, $time) {
    	if (preg_match("/%/", $format))	{
    		return strftime($format, $time);
		} else {
			return date($format, $time);
		}
	}

	function disableXSS($content) {
		$content = preg_replace("/<script.*>.*/i", "", $content);
		$content = preg_replace("/<\/script.*>.*/i", "", $content);
		$content = preg_replace("/<style.*>.*/i", "", $content);
		$content = preg_replace("/<\/style.*>.*/i", "", $content);
		$content = preg_replace("/<vbscript.*>.*/i", "", $content);
		$content = preg_replace("/<\/vbscript.*>.*/i", "", $content);
		$content = preg_replace('`(on[ a-z]+|style)=`', '', $content);
		return $content;
	}

	//TEASER
    function showTeaser() {
        
        $list=$this->cObj->getSubpart($this->subpart['teaser'],'###COMMENTS###');
		
   
        $res=$GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_skpagecomments_comments','hidden=0 AND deleted=0','','crdate desc',intval($this->conf['teaser.']['entries']));
        while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
            $content.=$this->renderComment($row,-1,$list); 
        } 
        $subpartArray['###COMMENTS###']=$content;
       
        return $this->cObj->substituteMarkerArrayCached($this->subpart['teaser'],$markerArray,$subpartArray,array());
        
    }
    
    function isAdmin() {
        $user=$group=array();
        if($this->conf['adminUser'] || $this->conf['adminGroup']) {
            if($this->conf['adminUser']) $user=explode(',',$this->conf['adminUser']);
            if($this->conf['adminGroup']) $group=explode(',',$this->conf['adminGroup']);
            if($GLOBALS['TSFE']->fe_user->user && (in_array($GLOBALS['TSFE']->fe_user->user['uid'],$user) || in_array($GLOBALS['TSFE']->fe_user->user['usergroup'],$group))) 
                return true;
        }
        return false;
    }

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php']);
}

?>
