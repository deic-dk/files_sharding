<div class='sharding-help-popup'>
	<?php 
		$fromEmail = \OCP\Config::getSystemValue('fromemail', '');
		$user =  \OCP\User::getUser();
		$charge = \OCA\Files_Accounting\Storage_Lib::getChargeForUserServers($user);
		$currency = \OCA\Files_Accounting\Storage_Lib::getBillingCurrency();
		$backupServerID = \OCA\FilesSharding\Lib::lookupServerIdForUser($user,
				\OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_BACKUP_1);
		$servers = OCA\FilesSharding\Lib::dbGetServersList();
	?>
	<p><?php print_unescaped($l->t("The infrastructure behind this service spans multiple sites and servers.
	When you logged in for the first time, you were automatically assigned a home site."));?></p>

	<p><?php print_unescaped($l->t("To change home site, you must <i>first</i> choose a backup site, and then wait for the first
	backup to finish (24 hours max). After that, you can change your home site to your backup site."));?></p> 

	<p><?php print_unescaped($l->t("When changing home site, a final sync will be performed and in the meantime 
access will set to read-only on both the old and the new home server. 
In case of sync problems, the access may stay read-only on the new server. 
You can change this manually, but please notice that you may be missing some files. 
So please also get <a href='mailto: %s'>in touch</a> with us, so we can resolve any problems.", $fromEmail));?></p>
	
	<h4><?php print_unescaped($l->t("List of sites"));?></h4>
	
	<?php
	foreach($servers as $server){
		if(!empty($server['description'])){
			echo "<p><b>".$server['site']."</b> - ".$server['charge_per_gb']." ".
					$currency." ".$l->t("per GB per year").
					"<br />".$server['description']."</p>";
		}
	}
	?>
	
	<h4><?php print_unescaped($l->t("Questions?"));?></h4>
	<?php print_unescaped($l->t("If you need more help, please contact us at"));?>
	<a target="_blank" href="mailto:<?php echo $fromEmail; ?>">
	<?php echo $fromEmail; ?></a>. 
</div>
