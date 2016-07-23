<div class='pay-popup'>
	<?php 
		$user =  \OCP\User::getUser();
		$charge = \OCA\Files_Accounting\Storage_Lib::getChargeForUserServers($user);
		$currency = \OCA\Files_Accounting\Storage_Lib::getBillingCurrency();
		$backupServerID = \OCA\FilesSharding\Lib::lookupServerIdForUser($user,
				\OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_BACKUP_1);
		$servers = OCA\FilesSharding\Lib::dbGetServersList();
	?>
	<p>The infrastructure behind data.deic.dk covers multiple sites and servers.
	When you logged in for the first time, you were automatically assigned a home site
	with serves in geographical proximity of your home institution.</p>

	<p>To change home site, you must <i>first</i> choose a backup site and wait for first
	backup to occur (24 hours max). After that, you can change your home site to your backup site.</p> 

	<h1>Which site to choose</h1>
	
	<p>When choosing a home site, you might want to consider the following:</p>
	
	<ul>
		<li>Proximity to your location: latency and bandwith from the server to your desktop</li>
		<li>Proximity to location of compute resources: in case, you're working with
			data pipelines between this service and compute services</li>
		<li>Service level of the give site: some sites provide extended uptime and data loss guarantees
		- that could make a backup site redundant</li>
		<li>Price</li>
	</ul>
	
	<p>When choosing a backup site, in most cases, price and data loss guarantees will be the main
	considerations.</p>
	
	<h1>List of sites</h1>
	
	<?php
	foreach($servers as $server){
		if(!empty($server['description'])){
			echo "<p><b>".$server['site']."</b> - ".$server['charge_per_gb']." ".
			$currency." per GB per year<br />".$server['description']."</p>";
		}
	}
	?>
	
	<h1>More Questions</h1>
	If you need more help, please contact
	<a target="_blank" href="mailto:<?php $issuerEmail = \OCA\Files_Accounting\Storage_Lib::getIssuerEmail(); echo $issuerEmail; ?>">
	<?php echo $issuerEmail; ?></a>. 
</div>
