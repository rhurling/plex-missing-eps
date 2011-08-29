<?php
//TODO#: Create full reload function (wipe, compact db)
//TODO#: Create proper thetvdb update cron functionality
//TODO#: Reload page after missing update
//TODO#: Rethink all buttons, and if they're required or could be moved to a cron

require_once('inc/config.inc.php');
if (isStreamRequest()) {
	// file_put_contents('runlog.txt', 'Request Begin! ['.time().', '.$_GET['r']."]\n", FILE_APPEND);
	initStream();
	define('ISAJAX',true);
	$tvdb = new TVDB();
	$model = new Missing_Model('sqlite:inc/db/MissingEps.sqlite');
	$cnt = 0;
	switch ($_GET['r']) {
		case 'updatePlexData':
			logInfo('Updating plex data...');
			// Fetch series from plex
			logInfo('Fetching your collection from Plex...');
			$plexData = fetchPlexSeriesData(PLEX_SERVER);
			// Save plex data to database
			logInfo('Saving your collection to database...');
			$model->savePlexdata($plexData);
		break;
		case 'updateTheTvDb':
			logInfo('Updating local TheTVDB cache...');

			// Pick random thetvdb mirror
			$tvdb->pickRandomMirror(); // true fakes it since so far there is but one mirror
			
			// If first run, precache thetvdb date for collection
			if ($model->getSeriesCount() == 0) {
				// Fetch Plex Data
				$plexData = $model->getPlexData();
				
				// Cross reference series ids from thetvdb
				$mySeriesIds = getSeriesIds($plexData);

				// Cache any new series right off the bat
				$count = count($plexData) * 2; $cnt = count($plexData);
				update($cnt++, $count);
				foreach ($mySeriesIds['exact'] as $seriesId) {
					update($cnt++, $count);
					if (!$model->isSeriesCached($seriesId)) {
						// Load and validate series data
						if (($data = $tvdb->fetchFullSeriesData($seriesId)) === false) {
							continue;
						}
						// Data good, cache it
						if (!$model->cacheNewSeries($data)) {
							logError("CACHE: Failed to cache: {$data->SeriesName}");
						}
					}
				}
				$model->saveLastUpdateTime(time());
			} else {
				// Fetch updates
				$updates = $tvdb->fetchAllUpdates($model->getLastUpdateTime());

				$count = (count($updates->Series) + count($updates->Episode)); $cnt = 0;
				
				
				update($cnt++, $count);

				// Update each series
				foreach ($updates->Series as $seriesId) {
					update($cnt++, $count);
					$seriesId = (string)$seriesId;
					// Don't bother updating if we don't have the series cached
					if ($model->isSeriesCached($seriesId)) {
						// Make sure record is valid
						if (($series = $tvdb->fetchSeries($seriesId)) !== false) {
							$model->updateSeries($seriesId, $series);
						} else {
							// logWarn('UPDATE: Invalid/Incomplete series data for: '.$seriesId);
						}
					}
				}

				// Update each episode in the update XML
				foreach ($updates->Episode as $episodeId) {
					update($cnt++, $count);
					$episodeId = (string)$episodeId;
					
					// Retrieve and make sure thetvdb record is valid
					if (($episode = $tvdb->fetchEpisode($episodeId)) === false)	{
						// logWarn('UPDATE: Invalid/Incomplete episode data for: '.$episodeId);
						continue;
					}
					
					// Episode is valid, is it cached?
					if ($model->isEpisodeCached($episodeId)) {
						$model->updateEpisode($episode);
						// logInfo('UPDATE: Updated episode: ' . $episode->EpisodeName);
					} else {
						// Episode not cached, see if it belongs to a cached series
						if ($model->isSeriesCached($episode->seriesid)) {
							// Series does exist, must be a new episode... Add it
							$model->cacheNewEpisode($episode, $episode->seriesid);
							// logInfo('UPDATE: Added new episode: ' . $episode->EpisodeName);
						}
					}
				}
				// Save last update time
				$model->saveLastUpdateTime((string)$updates->Time);
			}
			
		break;
		case 'updateMissing':
			logInfo('Updating missing episode data...');
			// Delete original missing.db
			logInfo('Deleting original missing database...');
			if (file_exists('inc/db/missing.db')) unlink('inc/db/missing.db');
			
			// Get plex data
			logInfo('Retrieving collection data...');
			$plexData = $model->getPlexData();
			
			// Get missing episodes for each series
			logInfo('Searching for missing episodes...');
			$missingSeries = array();
			$count = count($plexData);
			foreach ($plexData as $plexId => $series) {
				if (defined('ISAJAX')) update($cnt++, $count);
				if (($tmp = getMissingEpisodesForSeriesByPlexData($series)) === false) continue;
				$missingSeries[$plexId] = $tmp;
			}
			
			// Save to file TODO#: move to database
			logInfo('Saving missing episodes to database...');
			file_put_contents('inc/db/missing.db', serialize($missingSeries));
		break;
		case 'fullReload':
			logInfo('Processing full reload, please stand by...');
			logInfo('This function has been disabled...');
		break;
		default: logError('Error');
	}
	sendMessage(array('code'=>'sta', 'val'=>100));
	logInfo('Done!');
	if (!STREAM_ENABLED) sendRaw('<br/>Done!</pre>');
	// file_put_contents('runlog.txt', 'Request Complete! ['.time().', '.$_GET['r']."]\n", FILE_APPEND);
	die();
} else die();