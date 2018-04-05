<div class='pay-popup'>
	<?php 
		$user =  \OCP\User::getUser();
		$charge = \OCA\Files_Accounting\Storage_Lib::getChargeForUserServers($user);
		$currency = \OCA\Files_Accounting\Storage_Lib::getBillingCurrency();
		$backupServerID = \OCA\FilesSharding\Lib::lookupServerIdForUser($user,
				\OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_BACKUP_1);
		$servers = OCA\FilesSharding\Lib::dbGetServersList();
	?>
	<p><?php print_unescaped($l->t("The infrastructure behind this service spans multiple sites and servers.
	When you logged in for the first time, you were automatically assigned a home site
	with servers in geographical proximity of your home institution."));?></p>

	<p><?php print_unescaped($l->t("To change home site, you must <i>first</i> choose a backup site, and then wait for the first
	backup to occur (24 hours max). After that, you can change your home site to your backup site."));?></p> 

	<h4><?php print_unescaped($l->t("Which site to choose"));?></h4>
	
	<p><?php print_unescaped($l->t("When choosing a home site, you might want to consider the following"));?>:</p>
	
	<ul>
		<li><?php print_unescaped($l->t("Proximity to you: latency and bandwith from the server to your desktop"));?></li>
		<li><?php print_unescaped($l->t("Proximity to compute resources: in case, you're working with data pipelines between this service and compute services"));?></li>
		<li><?php print_unescaped($l->t("Service level of the give site: some sites may provide extended uptime and data loss guarantees - that could make a backup site redundant"));?></li>
		<li><?php print_unescaped($l->t("Price"));?></li>
	</ul>
	
	<p><?php print_unescaped($l->t("When choosing a backup site, in most cases, price and data loss guarantees will be the main considerations."));?></p>
	
	<h4><?php print_unescaped($l->t("List of sites"));?></h4>
	
	<?php
	foreach($servers as $server){
		if(!empty($server['description'])){
			echo "<p><b>".$server['site']."</b> - ".$server['charge_per_gb']." ".
					$currency." ".$l->t("per GB per year").
					"<br />".$server['description']."</p>";
		}
	}
	$fromEmail = \OCP\Config::getSystemValue('fromemail', '');
	?>
	
	<h4><?php print_unescaped($l->t("Questions?"));?></h4>
	<?php print_unescaped($l->t("If you need more help, please contact"));?>
	<a target="_blank" href="mailto:<?php echo $fromEmail; ?>">
	<?php echo $fromEmail; ?></a>. 
</div>
