<?php
/**
 * Created D/22/03/2015
 * Updated S/17/03/2018
 *
 * Copyright 2015-2018 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * https://www.luigifab.info/magento/maillog
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
		return (strpos($txt = $this->__(' '.$data, $a, $b), ' ') === 0) ? $this->__($data, $a, $b) : $txt;
	}

	public function getHumanDuration($row, $field = 'sent_at') {

		if (!in_array($row->getData('created_at'), array('', '0000-00-00 00:00:00', null)) &&
		    !in_array($row->getData($field), array('', '0000-00-00 00:00:00', null))) {

			$data = strtotime($row->getData($field)) - strtotime($row->getData('created_at'));
			$minutes = intval($data / 60);
			$seconds = intval($data % 60);

			if ($data > 599)
				$data = '<strong>'.(($seconds > 9) ? $minutes.':'.$seconds : $minutes.':0'.$seconds).'</strong>';
			else if ($data > 59)
				$data = '<strong>'.(($seconds > 9) ? '0'.$minutes.':'.$seconds : '0'.$minutes.':0'.$seconds).'</strong>';
			else if ($data > 1)
				$data = ($seconds > 9) ? '00:'.$data : '00:0'.$data;
			else
				$data = '⩽ 1';

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
		return htmlspecialchars(str_replace(array('<','>','&lt;','&gt;',',','"'), array('(',')','(',')',', ',''), $data));
	}


	public function sendMail($zend, $data, $parts) {

		$headers = $data->getHeaders();
		$email = Mage::getModel('maillog/email');

		$email->setData('status', 'pending');
		$email->setData('created_at', date('Y-m-d H:i:s'));
		$email->setData('mail_header', $zend->header);
		$email->setData('mail_parameters', $zend->parameters);
		$email->setData('encoded_mail_recipients', $zend->recipients);
		$email->setData('encoded_mail_subject', $data->getSubject());

		$email->setMailSender((!empty($headers['From'][0])) ? $headers['From'][0] : '');
		$email->setMailRecipients($zend->recipients);
		$email->setMailSubject($data->getSubject());
		$email->setMailContent($parts);

		$email->save();
		$email->send();
	}

	public function getImportStatus($key, $id) {

		$folder = Mage::getStoreConfig('maillog/'.$key.'/directory');
		$folder = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($folder, "/ \t\n\r\0\x0B").'/');

		if (is_file($folder.'status.dat') && is_readable($folder.'status.dat')) {

			$help = Mage::helper('adminhtml');
			$data = @unserialize(file_get_contents($folder.'status.dat'));
			$date = (!empty($data['date'])) ? Mage::getSingleton('core/locale')->date($data['date'], Zend_Date::ISO_8601) : '';

			if (!empty($data['cron'])) {

				$url = 'href="'.$help->getUrl('*/cronlog_history/view', array('id' => $data['cron'])).'"';
				$txt = $this->__('Cron job #<a %s>%d</a>: %s.', $url, $data['cron'], $date);
				$schedule = Mage::getModel('cron/schedule')->load($data['cron']);

				if (empty($schedule->getId()) || !Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog'))
					$txt = strip_tags($txt);
			}
			else {
				$txt = $this->__('Manual import: %s.', $date);
			}

			if (!empty($data['file']) && ($key == 'bounces')) {
				$url = $help->getUrl('*/maillog_sync/download', array('file' => 'bounces'));
				return $txt.'<br /><a href="'.$url.'" id="'.$id.'" download="">'.$data['file'].'</a>';
			}
			else if (!empty($data['file']) && ($key == 'unsubscribers')) {
				$url = $help->getUrl('*/maillog_sync/download', array('file' => 'unsubscribers'));
				return $txt.'<br /><a href="'.$url.'" id="'.$id.'" download="">'.$data['file'].'</a>';
			}
			else if (!empty($data['exception'])) {
				return $txt.'<br /><span lang="en" id="'.$id.'">'.$data['exception'].'</span>';
			}
		}

		return '<span id="'.$id.'">'.((!empty($txt)) ? $txt : '').'</span>';
	}

	public function getAllTypes() {

		// recherche des types
		// efficacité maximale avec la PROCEDURE ANALYSE de MySQL/MariaDB
		$database = Mage::getSingleton('core/resource');
		$table = $database->getTableName('luigifab_maillog');

		$types = $database->getConnection('core_read')->fetchAll('SELECT type FROM '.$table.' PROCEDURE ANALYSE()');
		$types = (!empty($types[0]['Optimal_fieldtype']) && (stripos($types[0]['Optimal_fieldtype'], 'ENUM(') !== false)) ?
			explode(',', str_replace(array('ENUM(', '\'', ') NOT NULL'), '', $types[0]['Optimal_fieldtype'])) : array();

		$types = array_combine($types, $types);
		ksort($types);

		return $types;
	}
}