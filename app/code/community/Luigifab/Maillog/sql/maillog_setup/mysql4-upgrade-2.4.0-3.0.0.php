<?php
/**
 * Created L/09/11/2015
 * Updated D/25/02/2018
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

// de manière à empécher de lancer cette procédure plusieurs fois car Magento en est capable
ignore_user_abort(true);
$lock = Mage::getModel('index/process')->setId('maillog_setup');
if ($lock->isLocked())
	throw new Exception('Please wait, upgrade is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

try {
	Mage::log('Update v3.0! START', Zend_Log::INFO, 'maillog.log');

	// ajoute la table sync
	$this->run('
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_sync').';
		CREATE TABLE '.$this->getTable('luigifab_maillog_sync').' (
			sync_id    int(11) unsigned NOT NULL auto_increment,
			status     ENUM("pending","success","error") NOT NULL DEFAULT "pending",
			created_at datetime         NULL DEFAULT NULL,
			sync_at    datetime         NULL DEFAULT NULL,
			email      varchar(255)     NULL DEFAULT NULL,
			details    text             NULL DEFAULT NULL,
			PRIMARY KEY (sync_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	');

	// dégage d'anciennes configurations utilisées dans les versions 3.0.0-dev
	$this->run('
		DELETE FROM '.$this->getTable('core_config_data').' WHERE
			   path LIKE "crontab/jobs/maillog_%_import/schedule/cron_expr"
			OR path LIKE "maillog/general/block"
			OR path LIKE "maillog/sync/mapping_customeridfield"
			OR path LIKE "maillog/sync/mapping_unique_attribute"
			OR path LIKE "maillog/sync/%uniqfield"
			OR path LIKE "maillog/sync/mapping_uniquefield"
			OR path LIKE "maillog/sync/mapping_fields"
			OR path LIKE "maillog/sync/maping_%"
			OR path LIKE "maillog/unsubscribers/%"
			OR path LIKE "maillog/newsletter/%"
			OR path LIKE "maillog/bounces/%"
			OR path LIKE "maillog/bounce/%"
			OR path LIKE "maillog/system/%"
			OR path LIKE "maillog/content/%";
	');

	// 10 minutes svp
	$ini = intval(ini_get('max_execution_time'));
	if (($ini > 0) && ($ini < 600))
		ini_set('max_execution_time', 600);

	// EN CAS D'INTERRUPTION IL SUFFIT SIMPLEMENT DE RELANCER SI L'ENSEMBLE DES EMAILS À AU MAXIMUM 1 SEULE PIÈCE JOINTE
	// suppression de la première pièce jointe qui correspond au contenu de l'email uniquement s'il y a au moins 2 pièces jointes
	// par lot de 1000 pour éviter un memory_limit (ce qui est le plus long, c'est le MYSQL OPTIMIZE TABLE)
	$emails = Mage::getResourceModel('maillog/email_collection')->addFieldToFilter('mail_parts', array('notnull' => true));
	$p = ceil($emails->getSize() / 1000);
	$i = 0;

	Mage::log('Update v3.0! Starting update of '.$emails->getSize().' emails in '.$p.' steps of 1000 emails...', Zend_Log::INFO, 'maillog.log');
	while ($p > 0) {

		$emails = Mage::getResourceModel('maillog/email_collection');
		$emails->addFieldToFilter('mail_parts', array('notnull' => true));
		$emails->setOrder('email_id', 'desc');
		$emails->setPageSize(1000);
		$emails->setCurPage($p);

		foreach ($emails as $email) {

			$parts = unserialize(gzdecode($email->getData('mail_parts')));

			if (($parts !== false) && (count($parts) >= 2)) {

				array_shift($parts);
				$parts = gzencode(serialize($parts), 9, FORCE_GZIP);

				Mage::log('Update v3.0! Removing body part from email #'.$email->getId().' (step '.$p.')', Zend_Log::INFO, 'maillog.log');
				$email->setData('mail_parts', $parts)->save();
				$i += 1;
			}
			else if (($parts !== false) && (count($parts) >= 1)) {

			}
			else {
				Mage::log('Update v3.0! Removing all parts from email #'.$email->getId().' (step '.$p.')', Zend_Log::INFO, 'maillog.log');
				$email->setData('mail_parts', null)->save();
				$i += 1;
			}
		}

		$p -= 1;
	}

	Mage::log('Update v3.0! done! ('.$i.' emails updated)', Zend_Log::INFO, 'maillog.log');
	Mage::log('Update v3.0! Starting optimize table...', Zend_Log::INFO, 'maillog.log');

	$this->run('OPTIMIZE TABLE '.$this->getTable('luigifab_maillog'));

	Mage::log('Update v3.0! done!', Zend_Log::INFO, 'maillog.log');
	Mage::log('Update v3.0! '.ceil(memory_get_peak_usage(true) / 1024 / 1024).' MB used (memory_get_peak_usage)', Zend_Log::INFO, 'maillog.log');
	Mage::log('Update v3.0! END', Zend_Log::INFO, 'maillog.log');
}
catch (Exception $e) {
	$lock->unlock();
	Mage::log('Update v3.0! FATAL! '.$e->getMessage(), Zend_Log::CRIT, 'maillog.log');
	throw new Exception($e);
}

$this->endSetup();
$lock->unlock();