<?php

//TODO#: Figure out multiple click issue (status not updating/stream not starting)

require_once('inc/config.inc.php');
firstRunCheck();
if (($out = generateMissingList($stats)) === false) {
	$out = 'No missing episode database found.';
	$dbFound = false;
} else $dbFound = true;
?>
<!DOCTYPE html>
<html>
	<head>
		<link rel="stylesheet" type="text/css" href="inc/css/style.css"/>
		<script type="text/javascript" src="inc/js/jquery-1.6.2.min.js"></script>
		<script type="text/javascript" src="inc/js/jquery.stream-1.2.js"></script>
		<script type="text/javascript" src="inc/js/core.js"></script>
	</head>
	<body>
		<div id="title">
			<h1>Plex Missing Episode Finder</h1>
			<?php if ($dbFound) echo "<h2>Series: {$stats['ser']} | Seasons: {$stats['sea']} | Episodes: {$stats['eps']}</h2>"; ?>
			<div id="menu">
				<button id='statusButton'>Open Status</button>
			</div>
			<div id="buttons">
				<button id='updatePlexData'>Update Plex Data</button>
				<button id='updateMissing'>Update Missing Episodes</button>
				<button id='updateTheTvDb'>Update TheTVDB</button>
			</div>
		</div>
		<div id='status'></div>
		<div id='statusBar'><div id='progressBar'>&nbsp;</div><span>Processing</span></div>
		<div id="container"><?php echo $out; ?></div>
	</body>
</html>