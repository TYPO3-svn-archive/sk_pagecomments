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

require_once (PATH_tslib . 'class.tslib_pibase.php');
require_once (PATH_t3lib . 'class.t3lib_iconworks.php');

class tx_skpagecomments_pi1 extends tslib_pibase {
	public $prefixId = 'tx_skpagecomments_pi1'; // Same as class name
	public $scriptRelPath = 'pi1/class.tx_skpagecomments_pi1.php'; // Path to this script relative to the extension dir.
	public $extKey = 'sk_pagecomments'; // The extension key.
	public $pi_checkCHash = false;
	
	protected $prefixCSS = 'sk-pagecomments-';
	protected $lookForValue;
	protected $template;
	protected $subpart;
	protected $number;
	protected $pageid;
	protected $URLParamsArray;
	protected $isNotAllowed = false;
	protected $userLoggedIn;
	
	private $freeCap;
	/**
	 * Extension for adding Pagecomments to Pages
	 */
	public function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1; // Disable caching				 
		
		$this->userLoggedIn = (isset($GLOBALS['TSFE']->fe_user->user['uid']));

		//link to homepage ?
		if (intval($this->piVars['goto']) > 0) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('homepage', 'tx_skpagecomments_comments', 'hidden=0 and deleted=0 and uid=' . intval($this->piVars['goto']));
			if ($res) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				
				header('location:' . (substr($row['homepage'], 0, 4) == 'http' ? $row['homepage'] : 'http://' . $row['homepage']));
				exit();
			}
		}
		
		//admin link ?
		if ($this->isAdmin() && $this->piVars['hide']) {
			$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_skpagecomments_comments', 'uid=' . $this->piVars['hide'], array ('hidden' => $this->piVars['status']));
		}
		
		$this->template = $this->cObj->fileResource($this->conf['templateFile']);
		$this->subpart['commentlink'] = $this->cObj->getSubpart($this->template, '###COMMENTLINK###');
		$this->subpart['formlink'] = $this->cObj->getSubpart($this->template, '###FORMLINK###');
		$this->subpart['comments'] = $this->cObj->getSubpart($this->template, '###COMMENTS###');
		$this->subpart['pageBrowser'] = $this->cObj->getSubpart($this->template, '###PAGEBROWSER###');
		$this->subpart['form'] = $this->cObj->getSubpart($this->template, '###FORM###');
		$this->subpart['error'] = $this->cObj->getSubpart($this->template, '###ERROR###');
		$this->subpart['success'] = $this->cObj->getSubpart($this->template, '###SUCCESS###');
		$this->subpart['mail'] = $this->cObj->getSubpart($this->template, '###MAILING###');
		$this->subpart['usermail'] = $this->cObj->getSubpart($this->template, '###USERMAILING###');
		$this->subpart['teaser'] = $this->cObj->getSubpart($this->template, '###COMMENTTEASER###');
		
		$this->pageid = $conf['fromPages'] ? $conf['fromPages'] : $GLOBALS['TSFE']->id; //page-id
		$this->orig_pageid = $GLOBALS['TSFE']->id; #$this->pageid;
		

		$this->pidList = $this->pi_getPidList($this->cObj->data['pages'], $this->conf["recursive"]);
		if (isset($conf["pidList"]))
			$this->pidList = $conf["pidList"];
		
		if ($this->conf['pageid']) {
			$this->pageid = implode(',', t3lib_div::array_merge(explode(',', $this->pageid), explode(',', $this->conf['pageid'])));
			$this->pidList = implode(',', t3lib_div::array_merge(explode(',', $this->pidList), explode(',', $this->conf['pageid'])));
		}
		
		$err = array ();
		$content = $errormsg = '';
		
		if ($GLOBALS['TSFE']->config['config']['sys_language_uid'] != '') {
			$this->sys_language_uid = $GLOBALS['TSFE']->config['config']['sys_language_uid']; //Get site-language  
		} else {
			$this->sys_language_uid = 0;
		}
		
		//Is TEASER ?
		if ($this->conf['teaser'] == 1) {
			
			return $this->pi_wrapInBaseClass($this->showTeaser());
		}
		
		//Conf
		$this->URLParamsArray = $this->cleanUrlPars($_GET);
		

		$this->addWhere = $getvar = '';
		if ($this->conf['bindToGETvar']) {
			$var = $this->conf['bindToGETvar'];
			if (strpos($var, '[')) {
				$p1 = substr($var, 0, strpos($var, '['));
				$p2 = substr($var, strlen($p1) + 1);
				$p2 = substr($p2, 0, strlen($p2) - 1);
				$getvar = t3lib_div::GPvar($p1);
				$lookForValue = $getvar[$p2];
			
			} else {
				$lookForValue = t3lib_div::GPvar($var);
			}
			$this->addWhere = " AND piVar='" . $this->conf['bindToGETvar'] . '=' . $lookForValue . "'";
		
		}
		
		//Hook for addWhere
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_skpagecomments']['addWhere'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_skpagecomments']['addWhere'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$this->addWhere = $_procObj->addWhereProcessor($this->addWhere, & $lookForValue, & $this);
			}
		}
		
		if (t3lib_extMgm::isLoaded('sr_freecap') && ! $this->conf['useCaptcha'] && $this->conf['useFreecap']) {
			require_once (t3lib_extMgm::extPath('sr_freecap') . 'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
		
		}
		
		//Get Rec-count
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)', 'tx_skpagecomments_comments', 'pageid IN(' . $this->pageid . ') AND pid IN(' . $this->pidList . ') ' . ($this->isAdmin() ? '' : 'AND hidden="0"') . ' AND deleted="0"' . $this->addWhere);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($result);
		$reccount = $row[0];
		
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)', 'tx_skpagecomments_comments', 'pageid IN(' . $this->pageid . ') AND pid IN(' . $this->pidList . ') ' . ($this->isAdmin() ? '' : 'AND hidden="0"') . ' AND deleted="0" AND parentId="0"' . $this->addWhere);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($result);
		$parentcount = $row[0];
		
		if ($this->piVars['success'] == 1)
			$errormsg = $this->cObj->wrap($this->pi_getLL('entry_success'), $successLayout);
		
		if (intval($this->conf['showCommentsLink']) == 1 && intval($this->conf['showComments']) == 0 && intval($this->piVars['showComments']) != 1) {
			
			if ($this->conf['bindToGETvar']) {
				$lconf = t3lib_div::array_merge($this->conf['formLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => $this->URLParamsArray . '&' . $this->prefixId . '[showComments]=1', 'section' => ($this->conf['useSectionFormLink'] ? 'CommentStart' : '')));
				$l = $this->cObj->typolink('|', $lconf);
			} else {
				$lconf = t3lib_div::array_merge($this->conf['formLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => '&' . $this->prefixId . '[showComments]=1', 'section' => ($this->conf['useSectionFormLink'] ? 'CommentStart' : '')));
				$l = $this->cObj->typolink('|', $lconf);
			}
			
			$wrappedSubpartArray['COMMENTLINKWRAP'] = explode('|', $l);
			$markerArray['###RECORDCOUNT###'] = $reccount;
			$markerArray['###LINKTEXT###'] = $this->pi_getLL('showComment');
			$content .= $this->cObj->substituteMarkerArrayCached($this->subpart['commentlink'], $markerArray, $subpartArray, $wrappedSubpartArray);
		} else {
			
			//Wurde gepostet ?
			if (isset($this->piVars['submit'])) {
				$ip_address = t3lib_div::getIndpEnv('REMOTE_ADDR');
				
				$insertArr['parentId'] = intval($this->piVars['answerid']);
				
				$insertArr['name'] = $GLOBALS['TYPO3_DB']->quoteStr($this->piVars['name'], 'tx_skpagecomments_comments');
				$insertArr['email'] = $GLOBALS['TYPO3_DB']->quoteStr($this->piVars['email'], 'tx_skpagecomments_comments');
				$insertArr['homepage'] = $GLOBALS['TYPO3_DB']->quoteStr(strtolower($this->piVars['homepage']), 'tx_skpagecomments_comments');
				$insertArr['comment'] = $this->piVars['comment'];
				$insertArr['crdate'] = time();
				$insertArr['tstamp'] = time();
				$insertArr['pageid'] = $this->orig_pageid;
				$insertArr['pid'] = $this->pidList;
				$insertArr['mailonanswer'] = intval($this->piVars['mailonanswer']);
				$insertArr['mailoncomment'] = intval($this->piVars['mailoncomment']);
				if ($GLOBALS['TSFE']->fe_user->user['uid'] > 0) {
					$insertArr['feuser_uid'] = $GLOBALS['TSFE']->fe_user->user['uid'];
				}
				
				if (intval($this->conf['hideNewMsg']) > 0)
					$insertArr['hidden'] = 1;
				if ($this->conf['bindToGETvar'])
					$insertArr['piVar'] = $this->conf['bindToGETvar'] . '=' . $lookForValue;
				if (substr($insertArr['homepage'], 0, 7) == 'http://') {
					$insertArr['homepage'] = ereg_replace('http://', '', $insertArr['homepage']);
				} elseif (substr($insertArr['homepage'], 0, 7) == 'https://') {
					$insertArr['homepage'] = ereg_replace('https://', '', $insertArr['homepage']);
				}
				if ($insertArr['homepage'] == $this->pi_getLL('homepage_value'))
					$insertArr['homepage'] = '';
					
				//Validate
				if ((! (eregi('^[a-z0-9_\.-]+@[a-z0-9_-]+\.[a-z0-9_\.-]+$', $insertArr['email']))) && (strlen($insertArr['email']) > 0) || $insertArr['email'] == "" || $insertArr['email'] == $this->pi_getLL('email_value')) {
					$err[] = $this->pi_getLL('email_error');
				}
				if ($insertArr['name'] == "" || $insertArr['name'] == $this->pi_getLL('name_value') || strlen($insertArr['name']) < $conf['minCharsName']) {
					$err[] = $this->pi_getLL('name_error');
				}
				if ($insertArr['comment'] == "" || $insertArr['comment'] == $this->pi_getLL('comment_value') || strlen($insertArr['comment']) < $conf['minCharsComment']) {
					$err[] = $this->pi_getLL('comment_error');
				}
				
				#captcha response
				if (t3lib_extMgm::isLoaded('captcha') && $this->conf['useCaptcha']) {
					session_start();
					if ($this->piVars['captchaResponse'] != $_SESSION['tx_captcha_string']) {
						$err[] = $this->pi_getLL('captcha_error');
					}
					$_SESSION['tx_captcha_string'] = '';
				}
				
				#freecap response
				if (t3lib_extMgm::isLoaded('sr_freecap') && ! $this->conf['useCaptcha'] && $this->conf['useFreecap'] && is_object($this->freeCap) && ! $this->freeCap->checkWord($this->piVars['captcha_response'])) {
					$err[] = $this->pi_getLL('captcha_error');
				}
				
				if (count($err) > 0) {
					//error
					$errormsg = $this->cObj->substituteMarkerArrayCached($this->subpart['error'], array ('###ERRORMSG###' => implode('<br />', $err)), array (), array ());
				} else {
					if ($this->conf['useCookies'] > 0) {
						//store values in cookie
						$time = 60 * 60 * 24 * $this->conf['useCookies'];
						setcookie($this->prefixId . '_name', $insertArr['name'], time() + $time);
						setcookie($this->prefixId . '_email', $insertArr['email'], time() + $time);
						setcookie($this->prefixId . '_homepage', $insertArr['homepage'], time() + $time);
						setcookie($this->prefixId . '_mailonanswer', $insertArr['mailonanswer'], time() + $time);
						setcookie($this->prefixId . '_mailoncomment', $insertArr['mailoncomment'], time() + $time);
					}
					//insert comment
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_skpagecomments_comments', $insertArr);
					$insertId = $GLOBALS['TYPO3_DB']->sql_insert_id();
					if ($this->conf['emailNewMessage'] == 1 && $this->conf['emailAddress'] && $this->conf['emailFrom']) {
						$link = 'http://' . t3lib_div::getIndpEnv('HTTP_HOST') . '/' . $this->pi_getPageLink($this->orig_pageid);
						if ($this->conf['bindToGETvar']) {
							//add extra parameter
							$prefix = strpos($link, '?') ? '&' : '?';
							$link .= $prefix . $insertArr['piVar'] . '&no_cache=1'; # need no_cache for overriding any cHash
						}
						$msg = $this->cObj->substituteMarkerArrayCached($this->subpart['mail'], array ('###USER###' => $insertArr['name'], '###IP###' => $ip_address, '###EMAIL###' => $insertArr['email'], '###HOMEPAGE###' => $insertArr['homepage'], '###DATE###' => date($this->conf['dateFormat'], $insertArr['crdate']), '###COMMENT###' => $insertArr['comment'], '###PAGELINK###' => $link . '#comment' . $insertId, '###PAGETITLE###' => $GLOBALS['TSFE']->page['title']), array (), array ());
						$usermsg = $this->cObj->substituteMarkerArrayCached($this->subpart['usermail'], array ('###USER###' => $insertArr['name'], '###IP###' => $ip_address, '###EMAIL###' => $insertArr['email'], '###HOMEPAGE###' => $insertArr['homepage'], '###DATE###' => date($this->conf['dateFormat'], $insertArr['crdate']), '###COMMENT###' => $insertArr['comment'], '###PAGELINK###' => $link . '#comment' . $insertId, '###PAGETITLE###' => $GLOBALS['TSFE']->page['title']), array (), array ());
						#mail to admin  
						$this->cObj->sendNotifyEmail($msg, $this->conf['emailAddress'], '', $this->conf['emailFrom'], $email_fromName = 'PageComments', $this->conf['emailFrom']);
						#mail to users
						$mails = $this->collectEmails($insertId);
						if (count($mails) > 0) {
							foreach ($mails as $reciever) {
								$this->cObj->sendNotifyEmail($usermsg, $reciever, '', $this->conf['emailFrom'], $email_fromName = 'PageComments', $this->conf['emailFrom']);
							}
						}
					}
					header('Location: ' . t3lib_div::getIndpEnv('REQUEST_URI') . (strpos(t3lib_div::getIndpEnv('REQUEST_URI'), '?') ? '&' : '?') . $this->prefixId . '[showComments]=1&' . $this->prefixId . '[success]=1');
					exit();
				}
			
			}
			
			//show comments
			$content .= '<a id="CommentStart"></a>';
			if ($this->piVars['success'] && $this->conf['hideNewMsg'] == 1) {
				$markerArray['###HIDEMSG###'] = $this->cObj->stdWrap($this->pi_getLL('hideMsg'), $this->conf['hideMsg.']);
			} else {
				$markerArray['###HIDEMSG###'] = '';
			}
			
			$number = array ();
			$i = 1;
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tx_skpagecomments_comments', 'pageid="' . $this->pageid . '" AND pid IN(' . $this->pidList . ') ' . ($this->isAdmin() ? '' : 'AND hidden="0"') . ' AND deleted="0"' . $this->addWhere, 'crdate asc');
			while ( $temp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result) ) {
				$this->number[$temp['uid']] = $i ++;
			}
			
			//Pagebrowser
			if ($this->conf['pageBrowser'] && ! $this->piVars['showall']) {
				$offset = intval($this->piVars['offset']);
				$commentsPerPage = intval($this->conf['pageBrowser.']['commentsPerPage']) == 0 ? 10 : intval($this->conf['pageBrowser.']['commentsPerPage']);
				$startWith = $commentsPerPage * $offset;
				$pagesTotal = ceil($parentcount / $commentsPerPage);
				$nextPage = $offset + 1;
				$previousPage = $offset - 1;
				$min = 1;
				$max = $pagesTotal;
				if ($pagesTotal > $pagesCount && $pagesCount > 0) {
					$pstart = $offset - ceil(($pagesCount - 2) / 2);
					if ($pstart < 1)
						$pstart = 1;
					$pend = $pstart + $pagesCount;
					if ($pend > $pagesTotal - 1)
						$pend = $pagesTotal - 1;
				} else {
					$pstart = $min;
					$pend = $pagesTotal;
				}
				for($i = $min; $i <= $max; $i ++) {
					if ($offset + 1 == $i) {
						$markerArray['###PAGES###'] .= $this->cObj->stdWrap($i, $this->conf['pageBrowser.']['actPage_stdWrap.']);
					} else {
						if ($i == 1 || $i == $max || ($i > 1 && $i >= $pstart && $i <= $pend && $i < $max)) {
							$GLOBALS['TSFE']->ATagParams = 'title="' . $this->pi_getLL('page') . ' ' . $i . '"';
							$link = $this->cObj->typolink($i, array ('parameter' => $this->orig_pageid, 'additionalParams' => ($this->conf['bindToGETvar'] ? $this->URLParamsArray : '') . '&' . $this->prefixId . '[offset]=' . ($i - 1), 'section' => 'CommentStart'));
							$markerArray['###PAGES###'] .= $this->cObj->stdWrap($link, $this->conf['pages_stdWrap.']);
						} elseif (($i == 2 && $i < $pstart) || ($i == $pend + 1 && $i < $max)) {
							$markerArray['###PAGES###'] .= $this->cObj->stdWrap('...', $this->conf['pageBrowser.']['actPage_stdWrap.']);
						}
					}
				}
				$markerArray['###PAGEOF###'] = sprintf($this->pi_getLL('page_of'), $offset + 1, $pagesTotal);
				;
				$markerArray['###PREVIOUS###'] = $previousPage < 0 ? '' : $this->cObj->stdWrap($this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('previous'), $this->conf['pageBrowser.']['previousText_stdWrap.']), array ('parameter' => $this->orig_pageid, 'additionalParams' => ($this->conf['bindToGETvar'] ? $this->URLParamsArray : '') . '&' . $this->prefixId . '[offset]=' . $previousPage, 'section' => 'CommentStart')

				), $this->conf['pageBrowser.']['previous_stdWrap.']);
				
				$markerArray['###NEXT###'] = $nextPage + 1 > $pagesTotal ? '' : $this->cObj->stdWrap($this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('next'), $this->conf['pageBrowser.']['nextText_stdWrap.']), array ('parameter' => $this->orig_pageid, 'additionalParams' => ($this->conf['bindToGETvar'] ? $this->URLParamsArray : '') . '&' . $this->prefixId . '[offset]=' . $nextPage, 'section' => 'CommentStart')

				), $this->conf['pageBrowser.']['next_stdWrap.']);
				
				$subpartArray['###PAGEBROWSER'] = $this->cObj->substituteMarkerArrayCached($this->subpart['pageBrowser'], $markerArray, array (), array ());
				#t3lib_div::debug("parents: $parentcount start: $startWith commentsPerPage: $commentsPerPage pages: $pagesTotal");
			} else
				$subpartArray['###PAGEBROWSER'] = '';
			
			$orderDir = strtoupper($this->piVars['showForm'] == 1 ? $this->conf['sortOrderOnForm'] : $this->conf['sortOrder']);
			$order = 'uid asc';
			$limit = $this->conf['maxRecords'] > 0 ? $this->conf['maxRecords'] : '';
			
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_skpagecomments_comments', 'pageid IN(' . $this->pageid . ') AND pid IN(' . $this->pidList . ') ' . ($this->isAdmin() ? '' : 'AND hidden="0"') . ' AND deleted="0"' . $this->addWhere, $order, $limit);
			
			if ($reccount > 0) {
				$markerArray['###COMMENTCOUNT###'] = $this->showFields('count', $reccount . ' ' . ($reccount == 1 ? $this->pi_getLL('comment') : $this->pi_getLL('comments')));
				//read into array		  
				while ( $temp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result) ) {
					$this->comment[$temp['uid']] = $temp;
					if ($temp['parentId'] > 0)
						$this->childs[$temp['parentId']][] = $temp;
				}

				if ($orderDir == 'DESC')
					$this->comment = array_reverse($this->comment);
					
				//render Comments
				$i = 0;
				foreach ($this->comment as $key => $temp) {
					if ($temp['parentId'] == 0) {
						$i ++;
						if ($i <= $startWith)
							continue;
						if ($this->conf['pageBrowser'] && $i > $startWith + $commentsPerPage)
							break;
						$contentList .= $this->renderComment($temp);
					}
				}
				
				$subpartArray['###COMMENTLIST###'] = $contentList;
				$subpartArray['###ANSWERLIST###'] = '';
				
				if ($this->piVars['success'])
					$content .= '<a id="CommentForm"></a>';
					
				//Hook for additional markers
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_skpagecomments']['addMarkerSubpart'])) {
					foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_skpagecomments']['addMarkerSubpart'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$_procObj->addMarkerSubpartProcessor(& $markerArray, & $this);
					}
				}
				
				$content .= $this->cObj->substituteMarkerArrayCached($this->subpart['comments'], $markerArray, $subpartArray, array ());
				$markerArray = array ();
			
			}
			
			// Ist eingeloggter User ?
			
			
			if (! $this->conf['formLink.']) {
				$this->conf['formLink.'] = array ();
			}
			
			$showForm = $this->conf['showForm'];
			if ($this->conf['commentOnlyRegistered'] && !$this->userLoggedIn && !$this->piVars['answer']) {
				$showForm = 0;
			}
			if ($showForm) {
				if ($this->conf['showFormLink'] == 1 && $this->piVars['showForm'] != 1 && ! $this->piVars['answer'] && ! $this->isNotAllowed) {
					#generate link for form
					$aTagParams = $GLOBALS['TSFE']->ATagParams;
					$GLOBALS['TSFE']->ATagParams = $this->conf['ATagParams.']['formLink'];

					$subpartArray['###FORMLINK###'] = '';
					if ($this->conf['bindToGETvar']) {
						$lconf = t3lib_div::array_merge($this->conf['formLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => $this->URLParamsArray . '&' . $this->prefixId . '[showComments]=1&' . $this->prefixId . '[showForm]=1' . ($this->conf['pageBrowser'] ? '&' . $this->prefixId . '[offset]=' . $offset : '') . ($this->piVars['showall'] ? '&' . $this->prefixId . '[showall]=1' : ''), 'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : '')));
						
						$l = $this->cObj->typolink($this->pi_getLL('new_comment'), $lconf);
						if (intval($lookForValue) > 0) {
							$showLink = 1;
						}
					} else {
						$lconf = t3lib_div::array_merge($this->conf['formLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => '&' . $this->prefixId . '[showComments]=1&' . $this->prefixId . '[showForm]=1' . ($this->conf['pageBrowser'] ? '&' . $this->prefixId . '[offset]=' . $offset : '') . ($this->piVars['showall'] ? '&' . $this->prefixId . '[showall]=1' : ''), 'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : '')));
						$l = $this->cObj->typolink($this->pi_getLL('new_comment'), $lconf);
						$showLink = 1;
						$subpartArray['###FORMLINK###'] = $l;
					}
					$markerArray['###LINKTEXT###'] = $this->pi_getLL('new_comment');
					$GLOBALS['TSFE']->ATagParams = $aTagParams;
					
					$wrappedSubpartArray['FORMLINKWRAP'] = explode('|', $this->cObj->typolink('|', $lconf));
					if ($showLink == 1)
						$form .= $this->cObj->substituteMarkerArrayCached($this->subpart['formlink'], $markerArray, $subpartArray, $wrappedSubpartArray);
				
				} else {
					$show = 1;
					if (($this->conf['bindToGETvar'] && intval($lookForValue) == 0) || $this->isNotAllowed) {
						$show = 0;
					}

					if ($show == 1) {
						//Kommentar-Formular
						$markerArray['###ERRORMSG###'] = $errormsg;
						
						$markerArray['###HIDDENFIELDS###'] = '';
						$markerArray['###ATT_NAME###'] = '';
						$markerArray['###ATT_EMAIL###'] = '';
						$markerArray['###ATT_HOMEPAGE###'] = '';
						
						if ($this->piVars['answer'])
							$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[answerid]' . '" value="' . $this->piVars['answer'] . '" />';
						$markerArray['###NAME###'] = $this->prefixId . '[name]';
						$markerArray['###EMAIL###'] = $this->prefixId . '[email]';
						$markerArray['###HOMEPAGE###'] = $this->prefixId . '[homepage]';
						$markerArray['###SUBMIT###'] = $this->prefixId . '[submit]';
						$markerArray['###COMMENT###'] = $this->prefixId . '[comment]';
						
						if ($this->conf['useCookies'] > 0 && ! $this->userLoggedIn) {
							$insertArr['name'] = $_COOKIE[$this->prefixId . '_name'];
							$insertArr['email'] = $_COOKIE[$this->prefixId . '_email'];
							$insertArr['homepage'] = $_COOKIE[$this->prefixId . '_homepage'];
							$insertArr['mailonanswer'] = $_COOKIE[$this->prefixId . '_mailonanswer'];
							$insertArr['mailoncomment'] = $_COOKIE[$this->prefixId . '_mailoncomment'];
						}
						
						$markerArray['###V_NAME###'] = $this->userLoggedIn ? $GLOBALS['TSFE']->fe_user->user[$this->conf['username']] : $insertArr['name'] ? $insertArr['name'] : $this->pi_getLL('name_value');
						$markerArray['###V_EMAIL###'] = $this->userLoggedIn ? $GLOBALS['TSFE']->fe_user->user['email'] : $insertArr['email'] ? $insertArr['email'] : $this->pi_getLL('email_value');
						$markerArray['###V_HOMEPAGE###'] = $this->userLoggedIn ? $GLOBALS['TSFE']->fe_user->user['www'] : $insertArr['homepage'] ? $insertArr['homepage'] : $this->pi_getLL('homepage_value');
						$markerArray['###V_COMMENT###'] = $insertArr['comment'] ? $insertArr['comment'] : $this->pi_getLL('comment_value');
						$markerArray['###V_SUBMIT###'] = $this->pi_getLL('submit');
						
						$markerArray['###L_NAME###'] = $this->pi_getLL('name');
						$markerArray['###L_EMAIL###'] = $this->pi_getLL('mail');
						$markerArray['###L_HOMEPAGE###'] = $this->pi_getLL('homepage');
						$markerArray['###L_COMMENT###'] = $this->pi_getLL('comment');
						$markerArray['###L_CAPTCHA###'] = $this->pi_getLL('captcha');
						
						$markerArray['###ACTION###'] = strtr(t3lib_div::getIndpEnv('REQUEST_URI'), array ('&' => '&amp;')) . '#CommentForm';
						$markerArray['###SMILEYS###'] = $this->smileys();
						$markerArray['###LEGEND###'] = $this->piVars['answer'] ? sprintf($this->pi_getLL('legend_answer'), $this->number[$this->piVars['answer']]) : $this->pi_getLL('new_comment');
						
						#captcha
						if (t3lib_extMgm::isLoaded('captcha') && $this->conf['useCaptcha']) {
							$markerArray['###CAPTCHAINPUT###'] = '<input type="text" id="captcha" size="10" name="' . $this->prefixId . '[captchaResponse]" value="" />';
							$markerArray['###CAPTCHAPICTURE###'] = '<img src="' . t3lib_extMgm::siteRelPath('captcha') . 'captcha/captcha.php" alt="" />';
						} else {
							$subpartArray['###CAPTCHA###'] = '';
						}
						
						#freecap
						if (t3lib_extMgm::isLoaded('sr_freecap') && ! $this->conf['useCaptcha'] && $this->conf['useFreecap']) {
							$markerArray = t3lib_div::array_merge($markerArray, $this->freeCap->makeCaptcha());
							$subpartArray['###CAPTCHA###'] = '';
						} else {
							$subpartArray['###CAPTCHA_INSERT###'] = '';
						}
						
						$markerArray['###MAILONANSWER###'] = $this->showFields('mailonanswer', '<p><label class="check" for="mailonanswer"><input type="checkbox" value="1" id="mailonanswer" name="tx_skpagecomments_pi1[mailonanswer]" ' . ($insertArr['mailonanswer'] == 1 ? 'checked="checked"' : '') . ' /> ' . $this->pi_getLL('mailonanswer') . '</label></p>');
						$markerArray['###MAILONCOMMENT###'] = $this->showFields('mailoncomment', '<p><label class="check" for="mailoncomment"><input type="checkbox" value="1" id="mailoncomment" name="tx_skpagecomments_pi1[mailoncomment]" ' . ($insertArr['mailoncomment'] == 1 ? 'checked="checked"' : '') . ' /> ' . $this->pi_getLL('mailoncomment') . '</label></p>');
						
						if ($this->userLoggedIn) {
							if ($conf['showRegisteredFields'] == 1) {
								$markerArray['###ATT_NAME###'] = 'disabled="disabled"';
								$markerArray['###ATT_EMAIL###'] = 'disabled="disabled"';
								$markerArray['###ATT_HOMEPAGE###'] = 'disabled="disabled"';
							} else {
								
								$subpartArray['###FORM_NAME###'] = '';
								$subpartArray['###FORM_EMAIL###'] = '';
								$subpartArray['###FORM_HOMEPAGE###'] = '';
								$markerArray['###V_NAME###'] = $GLOBALS['TSFE']->fe_user->user[$this->conf['username']];
								$markerArray['###V_EMAIL###'] = $GLOBALS['TSFE']->fe_user->user['email'];
								$markerArray['###V_HOMEPAGE###'] = $GLOBALS['TSFE']->fe_user->user['www'];
								$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[name]' . '" value="' . $GLOBALS['TSFE']->fe_user->user[$this->conf['username']] . '" />
								<input type="hidden" name="' . $this->prefixId . '[email]' . '" value="' . $GLOBALS['TSFE']->fe_user->user['email'] . '" />	
								<input type="hidden" name="' . $this->prefixId . '[homepage]' . '" value="' . $GLOBALS['TSFE']->fe_user->user['www'] . '" />' . ($this->conf['pageBrowser'] ? '<input type="hidden" name="' . $this->prefixId . '[offset]' . '" value="' . $offset . '" />' : '');
							
							}
						} else
							$subpartArray['###FORM_VALUES_USER_LOGGED_IN###'] = '';
						
						$form .= $this->cObj->substituteMarkerArrayCached($this->subpart['form'], $markerArray, $subpartArray, array ()) . '<a id="CommentForm"></a>';
						unset($this->piVars['answer']);
					}
				}
			}
		}
		
		return $this->pi_wrapInBaseClass($this->conf['showFormOnTop'] == 1 ? $form . $content : $content . $form);
	}
	
	protected function showFields($name, $value) {
		if (isset($this->conf['blind.'][$name]) && $this->conf['blind.'][$name] == 1) {
			return "";
		}
		return $value;
	}
	
	protected function renderComment($temp, $level = 0, $list = '') {
		if ($level == - 1) {
			$aTagParams = $GLOBALS['TSFE']->ATagParams;
			$GLOBALS['TSFE']->ATagParams = $this->conf['ATagParams.']['answerLink'];
			
			$lconf = t3lib_div::array_merge($this->conf['answerLink.'], array ('parameter' => $temp['pageid'], 'section' => 'comment' . $temp['uid'], 'additionalParams' => ($temp['pivar'] ? '&' . $temp['pivar'] : '') . ($this->piVars['showall'] ? '&' . $this->prefixId . '[showall]=1' : '')));
			$l = $this->cObj->typolink('|', $lconf);
			$linkWrapArray['###LINK###'] = explode('|', $l);
			$markerArray['###GOTO###'] = $this->cObj->stdWrap($this->pi_getLL('goto'), $this->conf['goto.']);
			$GLOBALS['TSFE']->ATagParams = $aTagParams;
		} elseif ($level == 0) {
			$list = $this->cObj->getSubpart($this->subpart['comments'], '###COMMENTLIST###');
		} else {
			$list = $this->cObj->getSubpart($this->subpart['comments'], '###ANSWERLIST###');
		}
		$markerArray['###DATEPHRASE###'] = sprintf($this->pi_getLL('wrote'), $this->showDate($this->conf['dateFormat'], $temp['crdate']));
		
		$markerArray['###DATE###'] = $this->showDate($this->conf['dateFormat'], $temp['crdate']);
		$markerArray['###NAME###'] = $this->cObj->stdWrap($temp['name'], $this->conf['commentName.']);
		$numberLink = $this->cObj->typolink($this->cObj->stdWrap($this->number[$temp['uid']], $this->conf['commentNumber.']), array(
			'parameter' => $GLOBALS['TSFE']->id,
			'section' => 'comment' . $temp['uid'],
			'addQueryString' => 1 
		));
		$markerArray['###NUMBER###'] = $this->showFields('number', $numberLink);
		$markerArray['###MARGIN###'] = $this->conf['answerMargin'] * $level;
		
		if ($this->conf['pageLink'] = 1) {
			$page = $this->pi_getRecord('pages', $temp['pid']);
			$markerArray['###PAGELINK###'] = $this->pi_linkToPage($this->cObj->stdWrap($page['title'], $this->conf['pageLink.']), $temp['pid']);
		}
		
		$this->conf['emailLink.']['parameter'] = $temp['email'];
		$linkWrapArray['###EMAILLINKWRAP###'] = explode('|', $this->cObj->typolink('|', $this->conf['emailLink.']));
		
		if ($temp['homepage'] != '') {
			$aTagParams = $GLOBALS['TSFE']->ATagParams;
			$GLOBALS['TSFE']->ATagParams = $this->conf['ATagParams.']['homePageLink'];
			$this->conf['homepageLink.']['parameter'] = $GLOBALS['TSFE']->id; #$temp['homepage'];   
			$this->conf['homepageLink.']['additionalParams'] = '&' . $this->prefixId . '[goto]=' . $temp['uid'];
			$linkWrapArray['###HOMEPAGELINKWRAP###'] = $this->showFields('homepage', explode('|', $this->cObj->typolink('|', $this->conf['homepageLink.'])));
			$GLOBALS['TSFE']->ATagParams = $aTagParams;
		}
		$markerArray['###EMAIL###'] = $this->showFields('email', $this->cObj->stdWrap($temp['email'], $this->conf['commentEmail.']));
		$homepage = $temp['homepage'] != '' ? $this->cObj->stdWrap($temp['homepage'], $this->conf['commentHomepage.']) : '';
		$markerArray['###HOMEPAGE###'] = $this->showFields('homepage', $homepage);
		
		$markerArray['###COMMENT###'] = $this->displayComment($temp['comment']);
		
		if ($this->conf['allowAnswer']) {
			$aTagParams = $GLOBALS['TSFE']->ATagParams;
			$GLOBALS['TSFE']->ATagParams = $this->conf['ATagParams.']['answerLink'];
			if ($this->conf['bindToGETvar']) {
				$lconf = t3lib_div::array_merge((array) $this->conf['answerLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => $this->URLParamsArray . '&' . $this->prefixId . '[answer]=' . $temp['uid'] . ($this->piVars['showall'] ? '&' . $this->prefixId . '[showall]=1' : ''), 'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : '')));
				$l = $this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('answer'), $this->conf['answer.']), $lconf);
			} else {
				$lconf = t3lib_div::array_merge($this->conf['answerLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => '&' . $this->prefixId . '[answer]=' . $temp['uid'] . ($this->piVars['showall'] ? '&' . $this->prefixId . '[showall]=1' : ''), 'section' => ($this->conf['useSectionFormLink'] ? 'CommentForm' : '')));
				$l = $this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('answer'), $this->conf['answer.']), $lconf);
			}
			$GLOBALS['TSFE']->ATagParams = $aTagParams;
		} elseif ($this->conf['registerInfo'] && $this->conf['registerPid'] > 0) {
			$lconf = array ('parameter' => $this->conf['registerPid'], 'additionalParams' => '&redirect_url=' . urlencode(t3lib_div::getIndpEnv('REQUEST_URI')));
			$l = $this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('registerinfo'), $this->conf['registerInfo.']), $lconf);
		
		}
		$markerArray['###ANSWER###'] = $this->showFields('answer', $l);
		
		if ($this->isAdmin()) {
			if ($this->conf['bindToGETvar']) {
				$lconf = t3lib_div::array_merge((array) $this->conf['adminLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => $this->URLParamsArray . '&' . $this->prefixId . '[hide]=' . $temp['uid'] . '&' . $this->prefixId . '[status]=' . ($temp['hidden'] == 0 ? 1 : 0) . ($this->piVars['showall'] ? '&' . $this->prefixId . '[showall]=1' : '')));
			} else {
				$lconf = t3lib_div::array_merge($this->conf['adminLink.'], array ('parameter' => $this->orig_pageid, 'additionalParams' => '&' . $this->prefixId . '[hide]=' . $temp['uid'] . '&' . $this->prefixId . '[status]=' . ($temp['hidden'] == 0 ? 1 : 0) . ($this->piVars['showall'] ? '&' . $this->prefixId . '[showall]=1' : '')));
			}
			if ($temp['hidden'] == 0)
				$l = $this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('hide'), $this->conf['admin.']['hide.']), $lconf);
			else
				$l = $this->cObj->typolink($this->cObj->stdWrap($this->pi_getLL('unhide'), $this->conf['admin.']['unhide.']), $lconf);
			
			$markerArray['###ADMINHIDE###'] = $this->showFields('admin', $l);
		
		} else
			$markerArray['###ADMINHIDE###'] = '';
			
		//Hook for additional comment markers
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_skpagecomments']['addCommentMarker'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_skpagecomments']['addCommentMarker'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->addMarkerSubpartProcessor(& $markerArray, $temp, & $this);
			}
		}
		
		$content = $markerArray['###COMMENT###'] != '' ? $this->cObj->substituteMarkerArrayCached($list, $markerArray, $subpartArray, $linkWrapArray) : '';
		
		//has childs ?
		if ($level != - 1 && is_array($this->childs[$temp['uid']])) {
			$level += 1;
			foreach ($this->childs[$temp['uid']] as $key => $row) {
				$content .= $this->renderComment($row, $level);
			}
		}
		return $content;
	
	}
	protected function smileys() {
		if ($this->conf['blind.']['smileys'] != 1) {
			$res = t3lib_extMgm::siteRelPath('sk_pagecomments') . 'res/smileys/';
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
			$smile = '<div id="skpagecomments-smileys">
			<a class="noul" href="javascript:dosmilie(\':)\')"><img src="' . $res . 'icon_smile.gif" alt="smile"  /></a>
			<a class="noul" href="javascript:dosmilie(\';)\')"><img src="' . $res . 'icon_wink.gif" alt="zwinker"  /></a>
			<a class="noul" href="javascript:dosmilie(\':biggrin:\')"><img src="' . $res . 'icon_biggrin.gif" alt="Big Grins"  /></a>
			<a class="noul" href="javascript:dosmilie(\':confused:\')"><img src="' . $res . 'icon_confused.gif" alt="Confused"  /></a>
			<a class="noul" href="javascript:dosmilie(\':cool:\')"><img src="' . $res . 'icon_cool.gif" alt="Cool"  /></a>
			<a class="noul" href="javascript:dosmilie(\':cry:\')"><img src="' . $res . 'icon_cry.gif" alt="Cry"  /></a>
			<a class="noul" href="javascript:dosmilie(\':eek:\')"><img src="' . $res . 'icon_eek.gif" alt="Eek"  /></a>
			<a class="noul" href="javascript:dosmilie(\':evil:\')"><img src="' . $res . 'icon_evil.gif" alt="Evil"  /></a>
			<a class="noul" href="javascript:dosmilie(\':frown:\')"><img src="' . $res . 'icon_frown.gif" alt="Frown"  /></a>
			<a class="noul" href="javascript:dosmilie(\':mad:\')"><img src="' . $res . 'icon_mad.gif" alt="Mad"  /></a>
			<a class="noul" href="javascript:dosmilie(\':mrgreen:\')"><img src="' . $res . 'icon_mrgreen.gif" alt="Mr. Green"  /></a>
			<a class="noul" href="javascript:dosmilie(\':neutral:\')"><img src="' . $res . 'icon_neutral.gif" alt="Neutral"  /></a>
			<a class="noul" href="javascript:dosmilie(\':razz:\')"><img src="' . $res . 'icon_razz.gif" alt="Razz"  /></a>
			<a class="noul" href="javascript:dosmilie(\':redface:\')"><img src="' . $res . 'icon_redface.gif" alt="Redface"  /></a>
			<a class="noul" href="javascript:dosmilie(\':rolleyes:\')"><img src="' . $res . 'icon_rolleyes.gif" alt="Rolleyes"  /></a>
			<a class="noul" href="javascript:dosmilie(\':sad:\')"><img src="' . $res . 'icon_sad.gif" alt="Sad"  /></a>
			<a class="noul" href="javascript:dosmilie(\':surprised:\')"><img src="' . $res . 'icon_surprised.gif" alt="Surprised"  /></a>
			</div>';
		}
		return $smile;
	
	}
	
	protected function displayComment($comment) {
		$comment = trim($comment);
		if (! isset($this->conf['blind.']['smileys']) || (isset($this->conf['blind.']['smileys']) && $this->conf['blind.']['smileys'] != 1)) {
			$res = t3lib_extMgm::siteRelPath('sk_pagecomments') . 'res/smileys/';
			$comment = str_replace(":)", '<img src="' . $res . 'icon_smile.gif" alt="smile" class="smilie"  />', $comment);
			$comment = str_replace(";)", '<img src="' . $res . 'icon_wink.gif" alt="zwinker" class="smilie"  />', $comment);
			$comment = str_replace(":D", '<img src="' . $res . 'icon_biggrin.gif" alt="big green" class="smilie"  />', $comment);
			$comment = ereg_replace(":biggrin:", '<img src="' . $res . 'icon_biggrin.gif" alt="Big Grins" class="smilie"  />', $comment);
			$comment = ereg_replace(":confused:", '<img src="' . $res . 'icon_confused.gif" alt="Confused" class="smilie"  />', $comment);
			$comment = ereg_replace(":cool:", '<img src="' . $res . 'icon_cool.gif" alt="Cool" class="smilie"  />', $comment);
			$comment = ereg_replace(":cry:", '<img src="' . $res . 'icon_cry.gif" alt="Cry" class="smilie"  />', $comment);
			$comment = ereg_replace(":eek:", '<img src="' . $res . 'icon_eek.gif" alt="Eek" class="smilie"  />', $comment);
			$comment = ereg_replace(":evil:", '<img src="' . $res . 'icon_evil.gif" alt="Evil" class="smilie"  />', $comment);
			$comment = ereg_replace(":frown:", '<img src="' . $res . 'icon_frown.gif" alt="Frown" class="smilie"  />', $comment);
			$comment = ereg_replace(":mad:", '<img src="' . $res . 'icon_mad.gif" alt="Mad" class="smilie"  />', $comment);
			$comment = ereg_replace(":mrgreen:", '<img src="' . $res . 'icon_mrgreen.gif" alt="Mr. Green" class="smilie"  />', $comment);
			$comment = ereg_replace(":neutral:", '<img src="' . $res . 'icon_neutral.gif" alt="Neutral" class="smilie"  />', $comment);
			$comment = ereg_replace(":razz:", '<img src="' . $res . 'icon_razz.gif" alt="Razz" class="smilie"  />', $comment);
			$comment = ereg_replace(":redface:", '<img src="' . $res . 'icon_redface.gif" alt="Redface" class="smilie"  />', $comment);
			$comment = ereg_replace(":rolleyes:", '<img src="' . $res . 'icon_rolleyes.gif" alt="Rolleyes" class="smilie"  />', $comment);
			$comment = ereg_replace(":sad:", '<img src="' . $res . 'icon_sad.gif" alt="Sad" class="smilie"  />', $comment);
			$comment = ereg_replace(":surprised:", '<img src="' . $res . 'icon_surprised.gif" alt="Surprised" class="smilie"  />', $comment);
		}
		
		$comment = $this->disableXSS($comment);
		
		//Zeilenumbr�che umwandeln
		#$comment=preg_replace('/\r\n|\r|\n/', "<br />", trim($comment));
		

		//Tabs
		#$comment=str_replace(chr(9),'&nbsp;&nbsp;&nbsp;',$comment);
		#$comment=str_replace(' ','&nbsp;',$comment);
		

		//Links umwandeln
		#$comment=preg_replace("/http:\/\/(.+?)[[:space:]]/si"," <a href=\"http://$1\" target=\"_blank\">$1</a> ",$comment);
		

		//BBCode
		$patterns = array ("/\[code\](.*?)\[\/code\]/isS", "/\[quote\](.*?)\[\/quote\]/isS", "/\[list:[a-z0-9]{10}\](.*?)\[\/list:[a-z0-9]{10}\]/isS", "/\[list=[a-z0-9]{1}:[a-z0-9]{10}\](.*?)\[\/list:[a-z0-9]{1}:[a-z0-9]{10}\]/isS");
		while ( list ($k, $v) = each($patterns) ) {
			#t3lib_div::debug($k - $v,'KV');
		#$text  = preg_replace_callback($v,create_function('$treffer',' return "<ul>".str_replace("[*]","</li><li>",$treffer[1])."</ul>";'),$text);
		#$comment = preg_replace_callback($v, array('tx_skpagecomments_pi1','getBBCodes'),$comment);
		

		}
		$comment = preg_replace_callback('{\[(\w+)((=)(.+)|())\]((.|\n)*)\[/\1\]}U', array ('tx_skpagecomments_pi1', 'getBBCodes'), $comment);
		
		return $this->cObj->stdWrap($comment, $this->conf['comment.']);
	}
	
	protected function getBBCodes($match) {
		#t3lib_div::debug($match);
		switch (strtolower($match[1])) {
			case 'quote' :
				$BB = explode('|', $this->conf['BBcodes.']['quote']);
				if ($match[3] == '=' && strlen($match[4]) > 0) {
					$QS = explode('|', $this->conf['BBcodes.']['quoteSource']);
					return $QS[0] . $match[4] . $QS[1] . $BB[0] . $match[6] . $BB[1];
				} else {
					return $BB[0] . $match[6] . $BB[1];
				}
				break;
			case 'php' :
				$BB = explode('|', $this->conf['BBcodes.']['code']);
				$QS = explode('|', $this->conf['BBcodes.']['codeLanguage']);
				return $QS[0] . 'PHP' . $QS[1] . $BB[0] . highlight_string('<?php' . $match[6] . '?>', true) . $BB[1];
				break;
			case 'ts' :
				$BB = explode('|', $this->conf['BBcodes.']['code']);
				$BB = explode('|', $this->conf['BBcodes.']['code']);
				$QS = explode('|', $this->conf['BBcodes.']['codeLanguage']);
				return $QS[0] . 'Typoscript' . $QS[1] . $BB[0] . $this->highlightTS($match[6]) . $BB[1];
				break;
		}
	}
	
	protected function cleanUrlPars($arr) {
		$u = '';
		foreach ($arr as $var => $val) {
			if (stristr($var, $this->prefixId) === false && $var != 'id') {
				if (is_array($val)) {
					foreach ($val as $var1 => $val1) {
						$u .= '&' . $var . '[' . $var1 . ']=' . $val1;
					}
				} else {
					$u .= '&' . $var . '=' . $val;
				}
			} else {
			}
		}
		return $u;
	}
	
	protected function showDate($format, $time) {
		if (preg_match("/%/", $format)) {
			return strftime($format, $time);
		} else {
			return date($format, $time);
		}
	}
	
	protected function disableXSS($content) {
		$content = preg_replace("/<script.*>.*/i", "", $content);
		$content = preg_replace("/<\/script.*>.*/i", "", $content);
		$content = preg_replace("/<style.*>.*/i", "", $content);
		$content = preg_replace("/<\/style.*>.*/i", "", $content);
		$content = preg_replace("/<vbscript.*>.*/i", "", $content);
		$content = preg_replace("/<\/vbscript.*>.*/i", "", $content);
		$content = preg_replace('`(on[ a-z]+|style)=`', '', $content);
		return $content;
	}
	
	protected function highlightTS($code, $numbers = 1) {
		require_once (PATH_t3lib . 'class.t3lib_tsparser.php');
		$tsparser = t3lib_div::makeInstance("t3lib_TSparser");
		$tsparser->highLightStyles = $this->conf['highLightStyles'];
		$tsparser->lineNumberOffset = 1;
		return $tsparser->doSyntaxHighlight($code, $numbers == 1 ? array ($tsparser->lineNumberOffset) : '', 0);
	}
	//TEASER
	protected function showTeaser() {
		
		$list = $this->cObj->getSubpart($this->subpart['teaser'], '###COMMENTS###');
		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_skpagecomments_comments', 'hidden=0 AND deleted=0', '', 'crdate desc', intval($this->conf['teaser.']['entries']));
		while ( $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res) ) {
			$content .= $this->renderComment($row, - 1, $list);
		}
		$subpartArray['###COMMENTS###'] = $content;
		
		return $this->cObj->substituteMarkerArrayCached($this->subpart['teaser'], $markerArray, $subpartArray, array ());
	
	}
	
	protected function isAdmin() {
		$user = $group = array ();
		if ($this->conf['adminUser'] || $this->conf['adminGroup']) {
			if ($this->conf['adminUser'])
				$user = explode(',', $this->conf['adminUser']);
			if ($this->conf['adminGroup'])
				$group = explode(',', $this->conf['adminGroup']);
			if ($GLOBALS['TSFE']->fe_user->user && (in_array($GLOBALS['TSFE']->fe_user->user['uid'], $user) || in_array($GLOBALS['TSFE']->fe_user->user['usergroup'], $group)))
				return true;
		}
		return false;
	}
	
	protected function collectEmails($id) {
		#search for parents
		$emails = array ();
		$row = $this->pi_getRecord('tx_skpagecomments_comments', $id);
		if ($row['parentId'] > 0) {
			while ( $row['parentId'] != 0 ) {
				$row = $this->pi_getRecord('tx_skpagecomments_comments', $row['parentId']);
				$this->getEmailsFromChilds($row['uid'], $emails);
			}
		}
		#search for all
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_skpagecomments_comments', 'pageid="' . $this->pageid . '" AND pid IN(' . $this->pidList . ') and hidden=0 and deleted=0 and mailoncomment=1' . $this->addWhere);
		while ( $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res) ) {
			$emails[] = $row['email'];
		}
		return array_unique($emails);
	}
	protected function getEmailsFromChilds($id, &$emails) {
		$emails = array ();
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_skpagecomments_comments', 'pageid="' . $this->pageid . '" AND pid IN(' . $this->pidList . ') and hidden=0 and deleted=0 and mailonanswer=1 and parentId=' . $id);
		while ( $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res) ) {
			$emails[] = $row['email'];
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sk_pagecomments/pi1/class.tx_skpagecomments_pi1.php']);
}

?>
