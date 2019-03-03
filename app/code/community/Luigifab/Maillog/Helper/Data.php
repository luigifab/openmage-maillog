<?php
/**
 * Created D/22/03/2015
 * Updated V/01/03/2019
 *
 * Copyright 2015-2019 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.fr/magento/maillog
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

class Luigifab_Maillog_Helper_Data extends Mage_Core_Helper_Abstract {

	public function getVersion() {
		return (string) Mage::getConfig()->getModuleConfig('Luigifab_Maillog')->version;
	}

	public function _($data, $a = null, $b = null) {
		return (mb_strpos($txt = $this->__(' '.$data, $a, $b), ' ') === 0) ? $this->__($data, $a, $b) : $txt;
	}

	public function getHumanDuration($row) {

		if ((!in_array($row->getData('created_at'), array('', '0000-00-00 00:00:00', null)) &&
		    !in_array($row->getData('sent_at'), array('', '0000-00-00 00:00:00', null))) || ($row->getData('duration') > -1)) {

			$data = $row->getData('duration');
			$data = ($data > -1) ? $data : strtotime($row->getData('sent_at')) - strtotime($row->getData('created_at'));

			$minutes = intval($data / 60);
			$seconds = intval($data % 60);

			if ($data > 599)
				$data = '<strong>'.(($seconds > 9) ? $minutes.':'.$seconds : $minutes.':0'.$seconds).'</strong>';
			else if ($data > 59)
				$data = '<strong>'.(($seconds > 9) ? '0'.$minutes.':'.$seconds : '0'.$minutes.':0'.$seconds).'</strong>';
			else if ($data > 1)
				$data = ($seconds > 9) ? '00:'.$data : '00:0'.$data;
			else
				$data = '⩽&nbsp;1';

			return $data;
		}
	}

	public function getNumberToHumanSize($number) {

		if ($number < 1) {
			return '';
		}
		else if (($number / 1024) < 1024) {
			$size = $number / 1024;
			$size = Zend_Locale_Format::toNumber($size, array('precision' => 2));
			return $this->__('%s kB', str_replace(array('.00',',00'), '', $size));
		}
		else if (($number / 1024 / 1024) < 1024) {
			$size = $number / 1024 / 1024;
			$size = Zend_Locale_Format::toNumber($size, array('precision' => 2));
			return $this->__('%s MB', str_replace(array('.00',',00'), '', $size));
		}
		else {
			$size = $number / 1024 / 1024 / 1024;
			$size = Zend_Locale_Format::toNumber($size, array('precision' => 2));
			return $this->__('%s GB', str_replace(array('.00',',00'), '', $size));
		}
	}

	public function getHumanEmailAddress($data) {
		return htmlspecialchars(str_replace(array('<','>',',','"'), array('(',')',', ',''), $data));
	}

	public function formatDate($date = null, $format = Zend_Date::DATETIME_LONG, $showTime = false) {
		$object = Mage::getSingleton('core/locale');
		return str_replace($object->date($date)->toString(Zend_Date::TIMEZONE), '', $object->date($date)->toString($format));
	}

	public function getLock() {
		return Mage::getBaseDir('var').'/maillog.lock';
	}


	public function filterMail($varien, $html) {

		// foreach
		$pattern = str_replace('depend', 'foreach', Varien_Filter_Template::CONSTRUCTION_DEPEND_PATTERN);
		if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {

			foreach ($matches as $match) {

				$replaced = '';

				// items contient le contenu de la variable du foreach
				// et c'est normalement quelque chose qui ressemble à un tableau
				$items = $varien->_getVariable2($match[1], '');
				$i = 0;

				if (!empty($items) && is_array($items)) {
					foreach ($items as &$item) {
						if (is_array($item)) {
							foreach ($item as $key => &$data)
								$varien->setVariables(array($match[1].$i.'_'.$key => &$data));
							unset($data);
						}
						else {
							$varien->setVariables(array($match[1].$i.'_'.$key => &$item));
						}
						$replaced .= str_replace(' '.$match[1].'.', ' '.$match[1].$i.'_', $match[2]);
						$i += 1;
					}
					unset($item);
				}

				$html = str_replace($match[0], $replaced, $html);
			}
		}

		// if elseif/elsif else
		$pattern1 = '/{{if\s*(.*?)}}(.*?)((?:{{else?if\s*.*?}}.*?)*)({{else}}(.*?))?{{\\/if\s*}}/si';
		$pattern2 = '/{{else?if\s*(.*?)}}(.*?)(?={{else?if|$)/si';

		if (preg_match_all($pattern1, $html, $matches, PREG_SET_ORDER)) {

			foreach ($matches as $match) {

				$replaced = '';

				if ($this->variableMail($varien, $match[1], '') == '') {
					if (isset($match[3])) {
						preg_match_all($pattern2, $match[3], $submatches, PREG_SET_ORDER);
						foreach ($submatches as $submatch) {
							if ($this->variableMail($varien, $submatch[1], '') != '')
								$replaced = $submatch[2];
						}
						if (empty($replaced))
							$replaced = isset($match[4], $match[5]) ? $match[5] : '';
					}
					else {
						$replaced = isset($match[4], $match[5]) ? $match[5] : '';
					}
				}
				else {
					$replaced = $match[2];
				}

				$html = str_replace($match[0], $replaced, $html);
			}
		}

		return $html;
	}

	public function variableMail($varien, $value, $default) {

		// >
		if (mb_strpos($value, ' gt ') !== false) {
			$values = explode(' gt ', $value);
			if (empty($check = $varien->_getVariable2($values[1], $default)))
				$check = $values[1];
			return $varien->_getVariable2($values[0], $default) > $check;
		}
		// >=
		else if (mb_strpos($value, ' gte ') !== false) {
			$values = explode(' gte ', $value);
			if (empty($check = $varien->_getVariable2($values[1], $default)))
				$check = $values[1];
			return $varien->_getVariable2($values[0], $default) >= $check;
		}
		// <
		else if (mb_strpos($value, ' lt ') !== false) {
			$values = explode(' lt ', $value);
			if (empty($check = $varien->_getVariable2($values[1], $default)))
				$check = $values[1];
			return $varien->_getVariable2($values[0], $default) < $check;
		}
		// <=
		else if (mb_strpos($value, ' lte ') !== false) {
			$values = explode(' lte ', $value);
			if (empty($check = $varien->_getVariable2($values[1], $default)))
				$check = $values[1];
			return $varien->_getVariable2($values[0], $default) <= $check;
		}
		// ==
		else if (mb_strpos($value, ' eq ') !== false) {
			$values = explode(' eq ', $value);
			if (empty($check = $varien->_getVariable2($values[1], $default)))
				$check = $values[1];
			return $varien->_getVariable2($values[0], $default) == $check;
		}
		// !=
		else if (mb_strpos($value, ' neq ') !== false) {
			$values = explode(' neq ', $value);
			if (empty($check = $varien->_getVariable2($values[1], $default)))
				$check = $values[1];
			return $varien->_getVariable2($values[0], $default) != $check;
		}
		// traitement par défaut
		else {
			return $varien->_getVariable2($value, $default);
		}
	}


	public function canSend($type, $email) {

		if (!Mage::getStoreConfigFlag('maillog/'.$type.'/send'))
			return false;

		// L'adresse email ne doit pas contenir
		$ignores = array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog/'.$type.'/ignore')));
		foreach ($ignores as $ignore) {
			if (is_string($email) && (stripos($email, $ignore) !== false))
				return sprintf('STOP! Email address not allowed by keyword: %s', $ignore);
			else if ((stripos($email[4], $ignore) !== false) || (!empty($email[3]) && (stripos($email[3], $ignore) !== false)))
				return sprintf('STOP! Email address not allowed by keyword: %s', $ignore);
		}

		// Toutes les base_url doivent contenir
		// $found = false, alors c'est qu'il y a un problème
		$key   = $this->loadMemory('maillog/'.$type.'/baseurl');
		$found = Mage::registry($key);
		$error = Mage::registry($key.'_txt');

		if (!is_bool($found)) {

			$domains = array_filter(preg_split('#\s+#', Mage::getStoreConfig($key)));
			if (!empty($domains)) {

				// cherche si tous les domaines sont autorisés
				$storeIds = Mage::getResourceModel('core/store_collection')->setOrder('store_id', 'asc')->getAllIds();
				foreach ($storeIds as $storeId) {
					$baseurl1 = Mage::getStoreConfig('web/unsecure/base_url', $storeId);
					$baseurl2 = Mage::getStoreConfig('web/secure/base_url', $storeId);
					$found    = false;
					foreach ($domains as $domain) {
						if ((stripos($baseurl1, $domain) !== false) || (stripos($baseurl2, $domain) !== false)) {
							$found = true;
							break;
						}
					}
					if (!$found)
						break;
				}

				// met en cache le résultat
				// prépare toujours l'éventuel message d'erreur
				$error = $this->memorizeData($key, $found, sprintf('STOP! For store %d (%s %s), required domain was not found (%s).',
					$storeId, $baseurl1, $baseurl2, implode(' ', $domains)));
			}
			else {
				// pas d'erreur
				$error = $this->memorizeData($key, true, 'ok');
			}
		}

		if (!$found)
			return $error;

		// Toutes les base_url ne doivent pas contenir
		// $found = true, alors c'est qu'il y a un problème
		$key   = $this->loadMemory('maillog/'.$type.'/notbaseurl');
		$found = Mage::registry($key);
		$error = Mage::registry($key.'_txt');

		if (!is_bool($found)) {

			$domains = array_filter(preg_split('#\s+#', Mage::getStoreConfig($key)));
			if (!empty($domains)) {

				// cherche si tous les domaines sont autorisés
				$storeIds = Mage::getResourceModel('core/store_collection')->setOrder('store_id', 'asc')->getAllIds();
				foreach ($storeIds as $storeId) {
					$baseurl1 = Mage::getStoreConfig('web/unsecure/base_url', $storeId);
					$baseurl2 = Mage::getStoreConfig('web/secure/base_url', $storeId);
					$found    = false;
					foreach ($domains as $domain) {
						if ((stripos($baseurl1, $domain) !== false) || (stripos($baseurl2, $domain) !== false)) {
							$found = true;
							break;
						}
					}
					if ($found)
						break;
				}

				// met en cache le résultat
				// prépare toujours l'éventuel message d'erreur
				$error = $this->memorizeData($key, $found, sprintf('STOP! For store %d (%s %s), a forbidden domain was found (%s).',
					$storeId, $baseurl1, $baseurl2, implode(' ', $domains)));
			}
			else {
				// pas d'erreur
				$error = $this->memorizeData($key, false, 'ok');
			}
		}

		if ($found)
			return $error;

		return true;
	}

	private function loadMemory($key) {

		if (!is_bool(Mage::registry($key))) {
			$value = Mage::app()->loadCache($key);
			if ($value !== false) {
				Mage::register($key, (bool) $value);
				Mage::register($key.'_txt', '(from cache) '.Mage::app()->loadCache($key.'_txt'));
			}
		}

		return $key;
	}

	private function memorizeData($key, $found, $error) {

		Mage::register($key, $found);
		Mage::register($key.'_txt', '(from registry) '.$error);

		if (Mage::app()->useCache('config')) {
			$found = $found ? 1 : 0; // car on ne peut pas enregistrer true/false
			Mage::app()->saveCache($found, $key, array(Mage_Core_Model_Config::CACHE_TAG));
			Mage::app()->saveCache($error, $key.'_txt', array(Mage_Core_Model_Config::CACHE_TAG));
		}

		return $error;
	}


	public function sendMail($zend, $mail, $parts) {

		$headers = $mail->getHeaders();
		$email = Mage::getModel('maillog/email');

		$email->setData('created_at', date('Y-m-d H:i:s'));
		$email->setData('status', 'pending');
		$email->setData('mail_header', $zend->header);
		$email->setData('mail_parameters', $zend->parameters);
		$email->setData('encoded_mail_recipients', $zend->recipients);
		$email->setData('encoded_mail_subject', $mail->getSubject());

		$email->setMailSender(!empty($headers['user'][0]) ? $headers['user'][0] : '');
		$email->setMailRecipients($zend->recipients);
		$email->setMailSubject($mail->getSubject());
		$email->setMailContent($parts);

		$email->save();
	}

	public function sendSync($object, $type, $key, $todo) {

		$id    =  !empty($object->getData('customer_id')) ? $object->getData('customer_id') : $object->getId(); // !!
		$type  = (!empty($object->getData('customer_id')) && ($type != 'customer')) ? 'customer' : $type;       // !!
		$email = $object->getData($key);

		if (empty($email))
			$email = $object->getData('email');
		if (empty($email))
			$email = $object->getData('subscriber_email');
		if (empty($email))
			return;

		if ((Mage::registry('maillog_no_sync') !== true) && (Mage::registry('maillog_sync_'.$email) !== true)) {

			Mage::register('maillog_sync_'.$email, true, true);

			// 0 action : 1 type : 2 id : 3 ancien-email : 4 email
			// 0 action : 1 type : 2 id : 3              : 4 email
			$action = (!empty($object->getOrigData($key)) && ($email != $object->getOrigData($key))) ?
				$todo.':'.$type.':'.$id.':'.$object->getOrigData($key).':'.$email :
				$todo.':'.$type.':'.$id.'::'.$email;

			$username = (!Mage::app()->getStore()->isAdmin() || !is_object($user = Mage::getSingleton('admin/session')->getData('user'))) ?
				Mage::app()->getStore()->getData('code') : Mage::app()->getStore()->getData('code').' ('.$user->getData('username').')';

			$syncs = Mage::getResourceModel('maillog/sync_collection');
			$syncs->addFieldToFilter('status', 'pending');
			$syncs->addFieldToFilter('action', array('like' => $todo.':'.$type.':'.$id.':%'));
			$syncs->addFieldToSort('created_at', 'desc');

			// si une synchro en attente pour le même client existe déjà
			$sync = Mage::getModel('maillog/sync');
			//$sync->setData('request', 'waiting cron...');

			if (!empty($syncs->getSize())) {

				$candidate = $syncs->getFirstItem();
				$olddat = explode(':', $candidate->getData('action'));
				$newdat = explode(':', $action);

				// si pas de changement d'email dans l'ancienne synchro (o3) et pas de changement d'email dans la nouvelle synchro (n3)
				// la nouvelle synchro écrase la synchro précédente
				//   ancienne update:customer:408::test4@luigifab.fr
				//   nouvelle update:customer:408::test4@luigifab.fr
				//
				// si pas de changement d'email dans l'ancienne synchro (o3) et changement d'email dans la nouvelle synchro (n3)
				//  avec email ancienne synchro (o4) = ancien email nouvelle synchro (n3)
				// la nouvelle synchro écrase la synchro précédente
				//   ancienne update:customer:408::test4@luigifab.fr
				//   nouvelle update:customer:408:test4@luigifab.fr:test40@luigifab.fr
				//
				// si changement d'email dans l'ancienne synchro (o3) et pas de changement d'email dans la nouvelle synchro (n3)
				//   avec email ancienne synchro (o4) = email nouvelle synchro (n4)
				// la nouvelle synchro écrase la synchro précédente (sauf action)
				//   ancienne update:customer:408:test4@luigifab.fr:test40@luigifab.fr
				//   nouvelle update:customer:408::test40@luigifab.fr
				if (empty($olddat[3]) && empty($newdat[3])) {
					$sync = $candidate;
					//$sync->setData('request', 'no duplicate 1 - waiting cron...');
				}
				else if (empty($olddat[3]) && !empty($newdat[3]) && ($olddat[4] == $newdat[3])) {
					$sync = $candidate;
					//$sync->setData('request', 'no duplicate 2 - waiting cron...');
				}
				else if (!empty($olddat[3]) && empty($newdat[3]) && ($olddat[4] == $newdat[4])) {
					$action = $candidate->getData('action');
					$sync   = $candidate;
					//$sync->setData('request', 'no duplicate 3 - waiting cron...');
				}
			}

			$sync->setData('created_at', date('Y-m-d H:i:s'));
			$sync->setData('status', 'pending');
			$sync->setData('action', $action);
			$sync->setData('user', $username);
			$sync->save();

			if (Mage::app()->getStore()->isAdmin())
				Mage::getSingleton('adminhtml/session')->addNotice($this->__('Customer data will be synchronized (%s).', $email));
		}
	}


	public function getImportStatus($key, $id = null) {

		$help    = Mage::helper('adminhtml');
		$basedir = Mage::getStoreConfig('maillog/'.$key.'/directory');
		$basedir = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($basedir, "/ \t\n\r\0\x0B").'/');

		if (is_file($basedir.'status.dat')) {

			$result = new Varien_Object(@unserialize(file_get_contents($basedir.'status.dat')));
			$result->setData('duration', strtotime($result->getData('finished_at')) - strtotime($result->getData('started_at')));

			// de qui ?
			if (empty($result->getData('cron'))) {
				$txt = $this->__('Manual import: %s.', $this->formatDate($result->getData('finished_at'), Zend_Date::DATETIME_FULL));
			}
			else {
				$url = $help->getUrl('*/cronlog_history/view', array('id' => $result->getData('cron')));
				$txt = $this->__('Cron job #<a %s>%d</a> finished at %s (%s).', 'href="'.$url.'"', $result->getData('cron'), $this->formatDate($result->getData('finished_at'), Zend_Date::DATETIME_FULL), $this->getHumanDuration($result));

				$schedule = Mage::getModel('cron/schedule')->load($result->getData('cron'));
				if (empty($schedule->getId()) || !Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog'))
					$txt = strip_tags($txt);
			}

			// détails ?
			if (!empty($result->getData('file'))) {

				$file = $result->getData('file');
				$file = $basedir.'done/'.mb_substr($file, 0, mb_strpos($file, '-') - 2).'/'.$file;

				if (is_file($file)) {
					if ($key == 'bounces') {
						$url = $help->getUrl('*/maillog_sync/download', array('file' => 'bounces'));
						$txt = $txt."\n".'<a href="'.$url.'" download="">'.$result->getData('file').'</a>';
					}
					else if ($key == 'unsubscribers') {
						$url = $help->getUrl('*/maillog_sync/download', array('file' => 'unsubscribers'));
						$txt = $txt."\n".'<a href="'.$url.'" download="">'.$result->getData('file').'</a>';
					}
					else {
						$file = false;
						$txt .= "\n".$result->getData('file');
					}
				}
				else {
					$file = false;
					$txt .= "\n".$result->getData('file');
				}

				$txt .= "\n".'<code>';
				foreach ($result->getData() as $code => $value) {
					if (($code == 'errors') || (stripos($code, 'ed') !== false) || (stripos($code, 'Items') !== false)) {
						if (stripos($code, '_at') === false)
							$txt .= $code.':'.$value."\n";
					}
				}
				$txt .= '</code>';
			}
			else if (!empty($result->getData('exception'))) {
				$txt = $txt.'<br /><span lang="en">'.$result->getData('exception').'</span>';
			}

			// en cours ?
			$schedules = Mage::getResourceModel('cron/schedule_collection');
			$schedules->addFieldToFilter('job_code', 'maillog_'.$key.'_import');
			$schedules->addFieldToFilter('status', 'running');
			$schedule = $schedules->getFirstItem();

			if (!empty($schedule->getId())) {

				$url = $help->getUrl('*/cronlog_history/view', array('id' => $schedule->getId()));
				$job = $this->__('An import is in progress (cron job #<a %s>%d</a>).', 'href="'.$url.'"', $schedule->getId());

				if (!Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog'))
					$job = strip_tags($job);

				$txt = $txt.'<br /><strong>'.$job.'</strong>';
			}
		}

		return empty($id) ? $file : '<div style="width:180%;" id="'.$id.'">'.(!empty($txt) ? $txt : '').'</div>';
	}

	public function getSpecialCronStatus() {

		$schedules = Mage::getResourceModel('cron/schedule_collection');
		$schedules->addFieldToFilter('job_code', 'maillog_sendemails_syncdatas');
		$schedules->addFieldToFilter('status', 'success');
		$schedules->setOrder('finished_at', 'desc');
		$schedule = $schedules->getFirstItem();

		if (!empty($schedule->getId())) {

			$url = Mage::helper('adminhtml')->getUrl('*/cronlog_history/view', array('id' => $schedule->getId()));
			$txt = $this->__('last process: cron job #<a %s>%d</a> finished at %s', 'href="'.$url.'"', $schedule->getId(), $this->formatDate($schedule->getData('finished_at'), Zend_Date::DATETIME_SHORT));

			if (!Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog'))
				$txt = strip_tags($txt);
		}
		else {
			$url = Mage::helper('adminhtml')->getUrl('*/system_config/edit', array('section' => Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog') ? 'cronlog' : 'system'));
			$txt = $this->__('last process: not yet finished or short <a %s>cron jobs history</a>', 'href="'.$url.'"');
		}

		return '<span class="fri">'.$txt.'</span>';
	}

	public function getAllTypes() {

		// recherche des types
		// efficacité maximale avec la PROCEDURE ANALYSE de MySQL/MariaDB
		$database = Mage::getSingleton('core/resource');
		$table = $database->getTableName('luigifab_maillog');

		$types = $database->getConnection('core_read')->fetchAll('SELECT type FROM '.$table.' PROCEDURE ANALYSE()');
		$types = (!empty($types[0]['Optimal_fieldtype']) && (mb_stripos($types[0]['Optimal_fieldtype'], 'ENUM(') !== false)) ?
			explode(',', str_replace(array('ENUM(', '\'', ') NOT NULL'), '', $types[0]['Optimal_fieldtype'])) : array();

		$types = array_combine($types, $types);
		ksort($types);

		return $types;
	}
}