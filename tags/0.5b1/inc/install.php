<!DOCTYPE html>
<html>
	<head>
		<link rel="stylesheet" type="text/css" href="inc/css/style.css"/>
		<title>Plex Missing Episode Finder - Installation</title>
		<script type="text/javascript" src="inc/js/jquery-1.6.2.min.js"></script>
		<script type="text/javascript">
			$(function(){
				x = 0;
				$('div#buttons button').click(function() {
					el = $(this).attr('id');
					switch (el) {
						case 'updateMissing':
						case 'updatePlexData':
						case 'updateTheTvDb':
						url = "req.php?rt=if&r=" + el;
						$('div#buttons button').attr('disabled', true);
						$('div#status iframe').attr('src', url);
						$('div#status iframe').load(function() {
							$('div#buttons button').attr('disabled', false);
							if (++x == 4) $('div#finished').fadeIn('slow');
						});
					}
				});
			});
		</script>
	</head>
	<body id='install'>
		<div id="title">Plex Missing Episode Finder - Installation</div>
		
		<div id='preReq'>
		<h1>PreReq Check</h1>
		<ul>
			<li>PHP version greater than 5.2.11</li>
			<li>Extension: SimpleXML</li>
			<li>Extension: Curl</li>
			<li>Extension: PDO</li>
			<li>Extension: pdo_sqlite</li>
			<li>Extension: zip</li>
			<li>Database folder writable</li>
			<li>Configuration Edited</li>
		</ul>
		</div>

		<p>If you see this page, you've read the readme, edited the configuration file and are ready to start using the app.  Well, there are still a few more steps that you must take care of:</p>
		<ol>
			<li>Retrieve your TV Series collection from Plex.</li>
			<li>Generate a local cache of TheTVDB based on your collection (this increases the speed of the app and reduces the strain on TheTVDB's servers).</li>
			<li>Search for missing episodes and create a local disk cache.</li>
		</ol>
		
		<p>It may seem like a lot, but in reality, I've already taken care of that for you :)  All you need to do is click the three buttons below in order from left to right.</p>

		<div id="buttons">
			<button id='updatePlexData'>Update Plex Data</button>
			<button id='updateTheTvDb'>Update TheTVDB</button>
			<button id='updateMissing'>Update Missing Episodes</button>
		</div>
		<hr/>
		<div id='status'><iframe></iframe></div>
		<div id='finished'>
			<p>Alrighty, we're all set.  Go ahead and <a href="./">reload</a> the page and take a look at your missing episodes.</p>
		</div>
	</body>
</html>