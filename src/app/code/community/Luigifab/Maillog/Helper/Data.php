<?php
/**
 * Created D/22/03/2015
 * Updated V/22/12/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://github.com/luigifab/openmage-maillog
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

	public function _(string $data, ...$values) {
		$text = $this->__(' '.$data, ...$values);
		return ($text[0] == ' ') ? $this->__($data, ...$values) : $text;
	}

	public function escapeEntities($data, bool $quotes = false) {
		return empty($data) ? $data : htmlspecialchars($data, $quotes ? ENT_SUBSTITUTE | ENT_COMPAT : ENT_SUBSTITUTE | ENT_NOQUOTES);
	}

	public function formatDate($date = null, $format = Zend_Date::DATETIME_LONG, $showTime = false) {
		$object = Mage::getSingleton('core/locale');
		return str_replace($object->date($date)->toString(Zend_Date::TIMEZONE), '', $object->date($date)->toString($format));
	}

	public function getHumanEmailAddress($email) {
		return empty($email) ? '' : $this->escapeEntities(str_replace(['<', '>', ',', '"'], ['(', ')', ', ', ''], $email));
	}

	public function getHumanDuration($start, $end = null) {

		if (is_numeric($start) || (!in_array($start, ['', '0000-00-00 00:00:00', null]) && !in_array($end, ['', '0000-00-00 00:00:00', null]))) {

			$data    = is_numeric($start) ? $start : strtotime($end) - strtotime($start);
			$minutes = (int) ($data / 60);
			$seconds = $data % 60;

			if ($data > 599)
				$data = '<strong>'.(($seconds > 9) ? $minutes.':'.$seconds : $minutes.':0'.$seconds).'</strong>';
			else if ($data > 59)
				$data = '<strong>'.(($seconds > 9) ? '0'.$minutes.':'.$seconds : '0'.$minutes.':0'.$seconds).'</strong>';
			else if ($data > 1)
				$data = ($seconds > 9) ? '00:'.$data : '00:0'.$data;
			else
				$data = '⩽&nbsp;1';
		}

		return empty($data) ? '' : $data;
	}

	public function getNumber($value, array $options = []) {
		$options['locale'] = Mage::getSingleton('core/locale')->getLocaleCode();
		return Zend_Locale_Format::toNumber($value, $options);
	}

	public function getNumberToHumanSize($number) {

		$number = (float) $number;
		if ($number < 1) {
			$data = '';
		}
		else if (($number / 1024) < 1024) {
			$data = $number / 1024;
			$data = $this->getNumber($data, ['precision' => 2]);
			$data = $this->__('%s kB', preg_replace('#[.,]00[[:>:]]#', '', $data));
		}
		else if (($number / 1024 / 1024) < 1024) {
			$data = $number / 1024 / 1024;
			$data = $this->getNumber($data, ['precision' => 2]);
			$data = $this->__('%s MB', preg_replace('#[.,]00[[:>:]]#', '', $data));
		}
		else {
			$data = $number / 1024 / 1024 / 1024;
			$data = $this->getNumber($data, ['precision' => 2]);
			$data = $this->__('%s GB', preg_replace('#[.,]00[[:>:]]#', '', $data));
		}

		return $data;
	}

	public function getUsername() {

		$file = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$file = array_pop($file);
		$file = array_key_exists('file', $file) ? basename($file['file']) : '';

		// backend
		if ((PHP_SAPI != 'cli') && Mage::app()->getStore()->isAdmin() && Mage::getSingleton('admin/session')->isLoggedIn())
			$user = sprintf('admin %s', Mage::getSingleton('admin/session')->getData('user')->getData('username'));
		// cron
		else if (is_object($cron = Mage::registry('current_cron')))
			$user = sprintf('cron %d - %s', $cron->getId(), $cron->getData('job_code'));
		// xyz.php
		else if ($file != 'index.php')
			$user = $file;
		// full action name
		else if (is_object($action = Mage::app()->getFrontController()->getAction()))
			$user = $action->getFullActionName();
		// frontend
		else
			$user = sprintf('frontend %s', Mage::app()->getStore()->getData('code'));

		return $user;
	}


	public function getSystem($code = null, $klass = null) {

		// $code  = mautic|emarsys|...
		// $klass = Luigifab_Maillog_Model_System_Mautic|Emarsys|...
		if (empty($code)) {

			$systems = [];
			$config  = Mage::getConfig()->getNode('global/models/maillog/adaptators')->asArray();

			foreach ($config as $key => $value) {
				$system = $this->getSystem($key, $value);
				if (!empty($system))
					$systems[$key] = $system;
			}

			return $systems;
		}

		$system = Mage::registry('maillog_'.$code);
		if (!is_object($system) && !is_bool($system)) {

			$system = false;
			if (empty($klass))
				$klass = (string) Mage::getConfig()->getNode('global/models/maillog/adaptators/'.$code);

			if (!empty($klass)) {
				$system = Mage::getSingleton($klass);
				$system = $system->isEnabled() ? $system : false;
			}

			Mage::register('maillog_'.$code, $system);
		}

		return $system;
	}


	public function canSend(string ...$emails) {

		// l'adresse email ne doit pas contenir
		$ignores = array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog/filters/ignore')));
		foreach ($ignores as $ignore) {
			foreach ($emails as $email) {
				if (mb_stripos($email, $ignore) !== false)
					return sprintf('STOP! Email address (%s) not allowed by keyword: %s', $email, $ignore);
			}
		}

		// toutes les base_url doivent contenir
		$key = 'maillog/filters/baseurl';
		$msg = Mage::registry($key);

		if (empty($msg)) {

			$domains = array_filter(preg_split('#\s+#', Mage::getStoreConfig($key)));
			if (!empty($domains)) {

				// vérifie les domaines
				// si on trouve pas un domaine, c'est qu'il y a un problème
				$canSend  = true;
				$storeIds = Mage::getResourceModel('core/store_collection')->getAllIds(); // with admin
				foreach ($storeIds as $storeId) {
					$baseUrl1 = Mage::getStoreConfig('web/unsecure/base_url', $storeId);
					$baseUrl2 = Mage::getStoreConfig('web/secure/base_url', $storeId);
					foreach ($domains as $domain) {
						if ((mb_stripos($baseUrl1, $domain) === false) || (mb_stripos($baseUrl2, $domain) === false)) {
							$canSend = false;
							break 2;
						}
					}
				}

				// met en cache le résultat
				Mage::register($key, $msg = $canSend ? 'ok-can-send' : sprintf('STOP! For store %d (%s %s), required domain was not found (%s).', $storeId, $baseUrl1, $baseUrl2, implode(' ', $domains)));
			}
			else {
				// met en cache le résultat
				Mage::register($key, $msg = 'ok-can-send');
			}
		}

		if ($msg != 'ok-can-send')
			return $msg;

		// toutes les base_url ne doivent pas contenir
		$key = 'maillog/filters/notbaseurl';
		$msg = Mage::registry($key);

		if (empty($msg)) {

			$domains = array_filter(preg_split('#\s+#', Mage::getStoreConfig($key)));
			if (!empty($domains)) {

				// vérifie les domaines
				// si on trouve un domaine, c'est qu'il y a un problème
				$canSend  = true;
				$storeIds = Mage::getResourceModel('core/store_collection')->getAllIds(); // with admin
				foreach ($storeIds as $storeId) {
					$baseUrl1 = Mage::getStoreConfig('web/unsecure/base_url', $storeId);
					$baseUrl2 = Mage::getStoreConfig('web/secure/base_url', $storeId);
					foreach ($domains as $domain) {
						if ((mb_stripos($baseUrl1, $domain) !== false) || (mb_stripos($baseUrl2, $domain) !== false)) {
							$canSend = false;
							break 2;
						}
					}
				}

				// met en cache le résultat
				Mage::register($key, $msg = $canSend ? 'ok-can-send' : sprintf('STOP! For store %d (%s %s), a forbidden domain was found (%s).', $storeId, $baseUrl1, $baseUrl2, implode(' ', $domains)));
			}
			else {
				// met en cache le résultat
				Mage::register($key, $msg = 'ok-can-send');
			}
		}

		if ($msg != 'ok-can-send')
			return $msg;

		return true;
	}

	public function sendMail(object $zend, object $mail, $parts) {

		$vars = Mage::registry('maillog_last_emailvars');
		$storeId = (!empty($vars['store']) && is_object($vars['store'])) ? $vars['store']->getId() : Mage::app()->getStore()->getId();

		$heads = $mail->getHeaders();
		$email = Mage::getModel('maillog/email')
			->setData('store_id', $storeId)
			->setData('created_at', date('Y-m-d H:i:s'))
			->setData('status', 'pending')
			->setData('mail_parameters', $zend->parameters)
			->setData('encoded_mail_recipients', $zend->recipients)
			->setData('encoded_mail_subject', $mail->getSubject())
			->setMailHeader($zend->header)
			->setMailSender(empty($heads['From'][0]) ? Mage::getStoreConfig('trans_email/ident_general/email') : $heads['From'][0])
			->setMailRecipients($zend->recipients)
			->setMailSubject($mail->getSubject())
			->setMailContent($vars, $parts);

		//exit($email->setId(9999999)->toHtml(true));
		// isResetPassword via setMailContent
		if ($email->isResetPassword())
			$email->sendNow(true);
		else
			$email->save();

		Mage::unregister('maillog_last_emailid');
		Mage::register('maillog_last_emailid', $email->getId());

		return $this;
	}

	public function sendSync(object $object, string $type, string $key, string $action) {

		$id   =   empty($object->getData('customer_id')) ? $object->getId() : $object->getData('customer_id');
		$type = (!empty($object->getData('customer_id')) && ($type != 'customer')) ? 'customer' : $type;

		if (empty($email = $object->getData($key)))
			$email = $object->getData('email');
		if (empty($email))
			$email = $object->getData('subscriber_email');

		if (!empty($email) && (Mage::registry('maillog_no_sync') !== true) && (Mage::registry('maillog_sync_'.$email) !== true)) {

			Mage::register('maillog_sync_'.$email, true, true);

			// 0 action : 1 type : 2 id : 3 ancien-email : 4 email
			// 0 action : 1 type : 2 id : 3              : 4 email
			$originalKey = (!empty($object->getOrigData($key)) && ($email != $object->getOrigData($key))) ?
				$action.':'.$type.':'.$id.':'.$object->getOrigData($key).':'.$email :
				$action.':'.$type.':'.$id.'::'.$email;

			$systems = $this->getSystem();
			foreach ($systems as $code => $system) {

				$syncs = Mage::getResourceModel('maillog/sync_collection')
					->addFieldToFilter('status', 'pending')
					->addFieldToFilter('action', ['like' => $action.':'.$type.':'.$id.':%'])
					->addFieldToFilter('model', $code)
					->setOrder('created_at', 'desc');

				$actionKey = $originalKey;
				$sync = Mage::getModel('maillog/sync');

				// des fois qu'il existe déjà une synchro en attente pour le même client
				if (!empty($syncs->getSize())) {

					$candidate = $syncs->getFirstItem();
					$olddat = explode(':', $candidate->getData('action'));
					$newdat = explode(':', $actionKey);

					// si pas de changement d'email dans l'ancienne synchro (o3) et pas de changement d'email dans la nouvelle synchro (n3)
					// la nouvelle synchro écrase la synchro précédente
					//   ancienne update:customer:408::emailaddr
					//   nouvelle update:customer:408::emailaddr
					//
					// si pas de changement d'email dans l'ancienne synchro (o3) et changement d'email dans la nouvelle synchro (n3)
					//  avec email ancienne synchro (o4) = ancien email nouvelle synchro (n3)
					// la nouvelle synchro écrase la synchro précédente
					//   ancienne update:customer:408::emailaddr
					//   nouvelle update:customer:408:emailaddr:emailaddr
					//
					// si changement d'email dans l'ancienne synchro (o3) et pas de changement d'email dans la nouvelle synchro (n3)
					//   avec email ancienne synchro (o4) = email nouvelle synchro (n4)
					// la nouvelle synchro écrase la synchro précédente (SAUF action/$actionKey)
					//   ancienne update:customer:408:emailaddr:emailaddr
					//   nouvelle update:customer:408::emailaddr
					//
					// if (empty($olddat[3]) && empty($newdat[3]))
					// else if (empty($olddat[3]) && !empty($newdat[3]) && ($olddat[4] == $newdat[3]))
					// else if (!empty($olddat[3]) && empty($newdat[3]) && ($olddat[4] == $newdat[4]))
					if (empty($olddat[3])) {
						if (empty($newdat[3])) {
							$sync = $candidate;
							//$sync->setData('request', 'no duplicate 1 - waiting cron...');
						}
						else if ($olddat[4] == $newdat[3]) {
							$sync = $candidate;
							//$sync->setData('request', 'no duplicate 2 - waiting cron...');
						}
					}
					else if (empty($newdat[3]) && ($olddat[4] == $newdat[4])) {
						$actionKey = $candidate->getData('action');
						$sync = $candidate;
						//$sync->setData('request', 'no duplicate 3 - waiting cron...');
					}
				}

				$sync->setData('store_id', Mage::app()->getStore()->getId());
				$sync->setData('created_at', date('Y-m-d H:i:s'));
				$sync->setData('status', 'pending');
				$sync->setData('action', $actionKey);
				$sync->setData('model', $code);
				$sync->setData('user', $this->getUsername());
				$sync->save();
			}

			if ((PHP_SAPI != 'cli') && !empty($systems) && Mage::app()->getStore()->isAdmin() && Mage::getSingleton('admin/session')->isLoggedIn())
				Mage::getSingleton('adminhtml/session')->addNotice($this->__('Customer data will be synchronized (%s).', $email));
		}

		return $this;
	}


	public function demoHelper($param = null) {
		return empty($param) ? 'This is Luigifab_Maillog_Helper_Data::demoHelper()' :
			'This is Luigifab_Maillog_Helper_Data::demoHelper($param='.$param.')';
	}

	public function getImportStatus(string $key, $id = null) {

		$dir  = Mage::getStoreConfig('maillog_sync/'.$key.'/directory');
		$dir  = empty($dir) ? '' : str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($dir, "/ \t\n\r\0\x0B").'/');
		$file = false;

		if (is_file($dir.'status.dat')) {

			$result = new Varien_Object(@json_decode(file_get_contents($dir.'status.dat'), true, 3));
			$result->setData('duration', strtotime($result->getData('finished_at')) - strtotime($result->getData('started_at')));

			// de qui
			if (empty($result->getData('cron'))) {
				$txt = $this->__('Manual import: %s.', $this->formatDate($result->getData('finished_at'), Zend_Date::DATETIME_FULL));
			}
			else {
				$url = $this->getCronUrl($result->getData('cron'));
				$txt = $this->__('Cron job #<a %s>%d</a> finished at %s.', 'href="'.$url.'"', $result->getData('cron'), $this->formatDate($result->getData('finished_at'), Zend_Date::DATETIME_FULL));

				if (empty($url))
					$txt = strip_tags($txt);
			}

			// détails
			if (!empty($result->getData('file'))) {

				$file = $result->getData('file');
				$file = $dir.'done/'.mb_substr($file, 0, mb_stripos($file, '-') - 2).'/'.$file;

				if (is_file($file)) {
					if ($key == 'bounces') {
						$url  = Mage::helper('adminhtml')->getUrl('*/maillog_sync/download', ['file' => 'bounces']);
						$txt .= "\n".'<br />'.'<a href="'.$url.'" download="">'.$result->getData('file').'</a>';
					}
					else if ($key == 'unsubscribers') {
						$url  = Mage::helper('adminhtml')->getUrl('*/maillog_sync/download', ['file' => 'unsubscribers']);
						$txt .= "\n".'<br />'.'<a href="'.$url.'" download="">'.$result->getData('file').'</a>';
					}
					else {
						$file = false;
						$txt .= "\n".'<br />'.$result->getData('file');
					}
				}
				else {
					$file = false;
					$txt .= "\n".'<br />'.$result->getData('file');
				}

				foreach ($result->getData() as $code => $value) {
					if (($code == 'errors') || (mb_stripos($code, 'ed') !== false) || (mb_stripos($code, 'Items') !== false)) {
						if (mb_stripos($code, '_at') === false)
							$txt .= "\n".'<br /><span lang="en">'.$code.':'.$value.'</span>';
					}
				}
			}
			else if (!empty($result->getData('exception'))) {
				$txt .= "\n".'<br /><span lang="en">'.$result->getData('exception').'</span>';
			}

			// en cours
			// sauf si la tâche cron à durée plus d'une heure
			$cron = Mage::getResourceModel('cron/schedule_collection')
				->addFieldToFilter('job_code', 'maillog_'.$key.'_import')
				->addFieldToFilter('status', 'running')
				->setPageSize(1)
				->getFirstItem();

			if (!empty($cron->getId())) {
				if ((time() - strtotime($cron->getData('executed_at'))) > 3600) {
					$cron->setData('status', 'error');
					$cron->setData('messages', 'Process killed?');
					$cron->save();
				}
				else {
					$url  = $this->getCronUrl($cron->getId());
					$cron = $this->__('An import is in progress (cron job #<a %s>%d</a>).', 'href="'.$url.'"', $cron->getId());
					if (empty($url))
						$cron = strip_tags($cron);
					$txt .= "\n".'<br /><strong>'.$cron.'</strong>';
				}
			}
		}

		return empty($id) ? $file : '<div style="width:180%; line-height:135%;" id="'.$id.'">'.(empty($txt) ? '' : $txt).'</div>';
	}

	public function getCronUrl($id) {

		if (!empty($id) && Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog'))
			return Mage::helper('adminhtml')->getUrl('*/cronlog_history/view', ['id' => $id]);

		return null;
	}

	public function getAllTypes() {

		$database = Mage::getSingleton('core/resource');
		$reader   = $database->getConnection('core_read');

		$types = $reader->fetchAssoc($reader->select()->distinct()->from($database->getTableName('maillog/email'), 'type'));
		$types = array_keys($types);
		$types = array_combine($types, $types);

		ksort($types);
		return $types;
	}

	public function getConfigUnserialized(string $path) {

		$data = Mage::getStoreConfig($path);
		if (empty($data))
			return [];

		$data = @unserialize($data, ['allowed_classes' => false]);
		if (empty($data) || !is_array($data))
			return [];

		return $data;
	}
}