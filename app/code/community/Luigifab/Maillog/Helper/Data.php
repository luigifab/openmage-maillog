<?php
/**
 * Created D/22/03/2015
 * Updated M/08/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
 * https://redmine.luigifab.info/projects/magento/wiki/maillog
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


	public function sendMail($zend, $data, $parts) {

		$email = Mage::getModel('maillog/email');
		$email->setData('status', 'pending');
		$email->setData('created_at', date('Y-m-d H:i:s'));

		// enregistre la version originale des entêtes, des paramètres, des destinataires et du sujet => AUCUN TRAITEMENT
		// pour l'envoi réel et le calcul de la taille approximative de l'email
		$email->setData('mail_header', $zend->header);
		$email->setData('mail_parameters', $zend->parameters);
		$email->setData('encoded_mail_recipients', $zend->recipients);
		$email->setData('encoded_mail_subject', $data->getSubject());

		// enregistre la version décodée des destinataires et du sujet => UN SEUL TRAITEMENT (LE DÉCODAGE)
		// pour la recherche en base de données et l'affichage
		$email->setMailRecipients($zend->recipients);
		$email->setMailSubject($data->getSubject());

		// enregistre la version décodée du contenu du mail => NOMBREUX TRAITEMENTS
		// enregistre également les différentes parties du mail => AUCUN TRAITEMENT
		// pour les différents traitements, l'affichage, l'envoi réel et le calcul de la taille approximative de l'email
		$email->setMailContent($parts);

		$email->save();
		$email->send();
	}


	public function getNumberToHumanSize($number) {

		if ($number < 1) {
			return '';
		}
		else if (($number / 1024) < 1024) {
			$size = $number / 1024;
			$size = Zend_Locale_Format::toNumber($size, array('precision' => 2));
			return $this->__('%s KB', str_replace(array('.00',',00'), '', $size));
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

	public function getHumanDuration($email, $field = 'sent_at') {

		if (!in_array($email->getData('created_at'), array('', '0000-00-00 00:00:00', null)) &&
		    !in_array($email->getData($field), array('', '0000-00-00 00:00:00', null))) {

			$data = strtotime($email->getData($field)) - strtotime($email->getData('created_at'));
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

	public function getHumanRecipients($email) {
		return htmlspecialchars(str_replace(array('<','>','&lt;','&gt;',','), array('(',')','(',')',', '), $email->getData('mail_recipients')));
	}

	public function getImportStatus($key, $id) {

		$dir = Mage::getStoreConfig('maillog/'.$key.'/directory');
		$folder = Mage::getBaseDir('var').str_replace('//', '/', '/'.trim($dir, "/ \t\n\r\0\x0B").'/');

		if (is_file($folder.'status.dat') && is_readable($folder.'status.dat')) {

			$data = @unserialize(file_get_contents($folder.'status.dat'));
			$admin = Mage::helper('adminhtml');

			if (isset($data['cron'])) {

				$schedule = Mage::getModel('cron/schedule')->load($data['cron']);

				if ($schedule->getId() > 0) {

					$date = Mage::getSingleton('core/locale')->date($schedule->getExecutedAt(), Zend_Date::ISO_8601);

					if (Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog'))
						$cron = '<br />'.$this->__('Cron job <a %s>#%d</a> (%s).', 'href="'.$admin->getUrl('*/cronlog_history/view', array('id' => $schedule->getId())).'"', $schedule->getId(), $date);
					else
						$cron = '<br />'.$this->__('Cron job #%d (%s).', $schedule->getId(), $date);
				}
				else {
					$date = Mage::getSingleton('core/locale')->date()->setTimestamp(filemtime($folder.'status.dat'));
					$cron = '<br />'.$this->__('Manual import (%s).', $date);
				}
			}

			if (isset($data['file']) && ($key === 'bounces')) {

				$url = $admin->getUrl('*/maillog_sync/download', array('file' => 'bounces'));
				return '<span id="'.$id.'"><a href="'.$url.'" download="">'.$dir.'/'.$data['file'].'</a><br />'.str_replace(
					', ',
					'<br />➩ ',
					$this->__('%d lines (%s), %d addresse(s) added, %d addresse(s) removed, %d error(s)',
						(isset($data['lines']))   ? $data['lines']   : 0,
						(isset($data['size']))    ? $this->getNumberToHumanSize($data['size']) : 0,
						(isset($data['added']))   ? $data['added']   : 0,
						(isset($data['removed'])) ? $data['removed'] : 0,
						(isset($data['errors']))  ? $data['errors']  : 0
					)
				).'</span>'.((isset($cron)) ? $cron : '');
			}
			else if (isset($data['file']) && ($key === 'unsubscribers')) {

				$url = $admin->getUrl('*/maillog_sync/download', array('file' => 'unsubscribers'));
				return '<span id="'.$id.'"><a href="'.$url.'" download="">'.$dir.'/'.$data['file'].'</a><br />'.str_replace(
					', ',
					'<br />➩ ',
					$this->__('%d lines (%s), %d addresse(s) subscribed, %d addresse(s) unsubscribed, %d error(s)',
						(isset($data['lines']))        ? $data['lines']        : 0,
						(isset($data['size']))         ? $this->getNumberToHumanSize($data['size']) : 0,
						(isset($data['subscribed']))   ? $data['subscribed']   : 0,
						(isset($data['unsubscribed'])) ? $data['unsubscribed'] : 0,
						(isset($data['errors']))       ? $data['errors']       : 0
					)
				).'</span>'.((isset($cron)) ? $cron : '');
			}
			else {
				return '<span id="'.$id.'">'.((isset($data['exception'])) ? $data['exception'] : '').'</span>'.((isset($cron)) ? $cron : '');
			}
		}

		return '<span id="'.$id.'"></span>';
	}
}