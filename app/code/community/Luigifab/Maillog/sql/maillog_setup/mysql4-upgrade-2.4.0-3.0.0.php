<?php
/**
 * Created L/09/11/2015
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

// de manière à empécher de lancer cette procédure plusieurs fois (oui Magento en est capable, si si)
// on vérifie que le fichier temporaire n'existe pas
$lock = sys_get_temp_dir().'/maillog-v3-sql-ontime.tmp';

if (!is_file($lock)) {

	file_put_contents($lock, 'Remove me!', LOCK_EX);
	Mage::log('Update v3! START', Zend_Log::INFO, 'maillog.log');

	$this->startSetup();
	$this->run('
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_bounce').';
		CREATE TABLE '.$this->getTable('luigifab_maillog_bounce').' (
			bounce_id  int(11) unsigned NOT NULL auto_increment,
			created_at datetime         NULL DEFAULT NULL,
			email      varchar(255)     NULL DEFAULT NULL,
			source     varchar(255)     NULL DEFAULT NULL,
			notsent    int(11) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (bounce_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	');
	$this->run('
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_sync').';
		CREATE TABLE '.$this->getTable('luigifab_maillog_sync').' (
			sync_id    int(11) unsigned NOT NULL auto_increment,
			status     ENUM("pending", "success", "error") NOT NULL DEFAULT "pending",
			created_at datetime         NULL DEFAULT NULL,
			sync_at    datetime         NULL DEFAULT NULL,
			email      varchar(255)     NULL DEFAULT NULL,
			details    text             NULL DEFAULT NULL,
			PRIMARY KEY (sync_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	');

	// les configs ayant existées dans les versions 3 de dev
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "crontab/jobs/maillog_%_import/schedule/cron_expr"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/general/block"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/sync/mapping_customeridfield"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/sync/mapping_unique_attribute"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/sync/%uniqfield"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/sync/mapping_uniquefield"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/sync/mapping_fields"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/sync/maping_%"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/unsubscribers/%"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/newsletter/%"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/bounces/%"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/bounce/%"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/system/%"');

	// 5 minutes svp
	$ini = intval(ini_get('max_execution_time'));
	if (($ini > 0) && ($ini < 300))
		ini_set('max_execution_time', 300);

	// EN CAS D'ERREUR IL SUFFIT SIMPLEMENT DE RELANCER **SI** L'ENSEMBLE DES EMAILS À AU MAXIMUM 1 SEULE PIÈCE JOINTE
	// suppression de la première pièce jointe qui correspond au contenu de l'email uniquement s'il y a au moins 2 pièces jointes
	// par lot de 1000 pour éviter un memory_limit (ce qui est le plus long, c'est le MYSQL OPTIMIZE TABLE)
	$emails = Mage::getResourceModel('maillog/email_collection')->addFieldToFilter('mail_parts', array('notnull' => true));
	$p = ceil($emails->getSize() / 1000);
	$i = 0;

	Mage::log('Update v3! Starting update of '.$emails->getSize().' emails in '.$p.' steps of 1000 emails...', Zend_Log::INFO, 'maillog.log');

	while ($p > 0) {

		$emails = Mage::getResourceModel('maillog/email_collection');
		$emails->addFieldToFilter('mail_parts', array('notnull' => true));
		$emails->setOrder('email_id', 'desc');
		$emails->setPageSize(1000);
		$emails->setCurPage($p);

		foreach ($emails as $email) {

			$parts = unserialize(gzdecode($email->getMailParts()));

			if (($parts !== false) && (count($parts) >= 2)) {

				array_shift($parts);
				$parts = gzencode(serialize($parts), 9, FORCE_GZIP);

				Mage::log('Update v3! Removing body part from email #'.$email->getId().' (step '.$p.')', Zend_Log::INFO, 'maillog.log');
				$email->setMailParts($parts)->save();
				$i += 1;
			}
			else if (($parts !== false) && (count($parts) >= 1)) {

			}
			else {
				Mage::log('Update v3! Removing all parts from email #'.$email->getId().' (step '.$p.')', Zend_Log::INFO, 'maillog.log');
				$email->setMailParts(null)->save();
				$i += 1;
			}
		}

		$p -= 1;
	}

	Mage::log('Update v3! done! ('.$i.' emails updated)', Zend_Log::INFO, 'maillog.log');
	Mage::log('Update v3! Starting optimize table...', Zend_Log::INFO, 'maillog.log');

	$this->run('OPTIMIZE TABLE '.$this->getTable('luigifab_maillog').';');
	$this->endSetup();

	Mage::log('Update v3! done!', Zend_Log::INFO, 'maillog.log');
	Mage::log('Update v3! '.ceil(memory_get_peak_usage(true) / 1024 / 1024).' MB used (memory_get_peak_usage)', Zend_Log::INFO, 'maillog.log');
	Mage::log('Update v3! END', Zend_Log::INFO, 'maillog.log');
}
else {
	Mage::log('Update v3! NOT STARTED ('.getenv('REMOTE_ADDR').')', Zend_Log::INFO, 'maillog.log');
}