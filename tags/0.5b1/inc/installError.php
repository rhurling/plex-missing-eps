<!DOCTYPE html>
<html>
	<head>
		<link rel="stylesheet" type="text/css" href="inc/css/style.css"/>
		<title>Pre-Requisites NOT met!</title>
	</head>
	<body id="error">
		<div id="title">Plex Missing Episode Finder</div>
		<h1>Please Fix the Following:</h1>
		<ul>
		<?php
			foreach ($errors as $error) {
				echo "<li>{$error}</li>";
			}
		?>
		</ul>
	</body>
</html>