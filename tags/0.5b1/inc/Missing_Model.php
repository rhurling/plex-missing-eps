<?php
/*
SELECT  series.*, (
   SELECT  GROUP_CONCAT(actors.Name, '|')
   FROM thetvdb_series as series
   LEFT JOIN thetvdb_series_actors as series_actors ON series.id = series_actors.seriesId
   LEFT JOIN thetvdb_actors as actors on series_actors.actorId = actors.id
   WHERE series.id=:seriesId
) AS Actors, (
   SELECT  GROUP_CONCAT(genres.Genre, '|')
   FROM thetvdb_series as series
   LEFT JOIN thetvdb_series_genres as series_genres ON series.id = series_genres.seriesId
   LEFT JOIN thetvdb_genres as genres on series_genres.genreId = genres.id
   WHERE series.id=:seriesId
) as Genres

FROM thetvdb_series as series

WHERE series.id=:seriesId
array(':seriesId' => $seriesId)
*/

class Missing_Model {
	
	private $db;
	
	public function __construct($dsm) {
		$this->db = new PDO($dsm);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
	}
	public function __destruct() {
		$this->db = null;
		unset($this->db);
	}

	protected function cacheSeriesActors($actors, $seriesId) {
		$stmt = $this->db->prepare('INSERT INTO thetvdb_actors (Name) VALUES (?)');
		foreach ($actors as $actor) {
			if (($actorId = $this->isActorCached($actor)) === false) {
				// add actor to db
				if ($stmt->execute(array($actor)) === false) {
					$err = $stmt->errorInfo();
					logError("Error [{$err[1]}]: {$err[2]}");
					return false;
				} else {
					logInfo('ACTOR: ' . $actor . ' cached!');
					$actorId = $this->db->lastInsertid();
				}
			}
			if (!$this->isActorLinkedToSeries($actorId, $seriesId)) {
				$this->linkActorSeries($actorId, $seriesId);
			}
		}
		return true;
	}
	protected function cacheSeriesGenres($genres, $seriesId) {
		$stmt = $this->db->prepare('INSERT INTO thetvdb_genres (Genre) VALUES (?)');

		foreach ($genres as $genre) {
			if (($genreId = $this->isGenreCached($genre)) === false) {
				// add genre to db
				if ($stmt->execute(array($genre)) === false) {
					$err = $stmt->errorInfo();
					logError("Error [{$err[1]}]: {$err[2]}");
					return false;
				} else {
					logInfo('GENRE: ' . $genre . ' cached!');
					$genreId = $this->db->lastInsertid();
				}
			}
			if (!$this->isGenreLinkedToSeries($genreId, $seriesId)) {
				$this->linkGenreSeries($genreId, $seriesId);
			}
		}
		return true;
	}
	protected function isGenreLinkedToSeries($genreId, $seriesId) {
		$ret = false;
		$stmt = $this->db->prepare('SELECT genreId FROM thetvdb_series_genres where seriesId=? AND genreId=?');
		$stmt->execute(array($seriesId, $genreId));
		return (bool)(count($stmt->fetchAll()));
	}
	protected function isActorLinkedToSeries($actorId, $seriesId) {
		$ret = false;
		$stmt = $this->db->prepare('SELECT actorId FROM thetvdb_series_actors where seriesId=? AND actorId=?');
		$stmt->execute(array($seriesId, $actorId));
		return (bool)(count($stmt->fetchAll()));
	}
	protected function linkActorSeries($actorId, $seriesId) {
		$stmt = $this->db->prepare('INSERT INTO thetvdb_series_actors (seriesId, actorId) VALUES (?, ?)');
		$stmt->execute(array($seriesId, $actorId));
	}
	protected function linkGenreSeries($genreId, $seriesId) {
		$stmt = $this->db->prepare('INSERT INTO thetvdb_series_genres (seriesId, genreId) VALUES (?, ?)');
		$stmt->execute(array($seriesId, $genreId));
	}

	public function saveLastUpdateTime($time) {
		$stmt = $this->db->prepare('UPDATE thetvdb_lastupdate SET time=? WHERE id=0');
		$stmt->execute(array($time));
	}
	public function getLastUpdateTime() {
		$stmt = $this->db->prepare('SELECT time from thetvdb_lastupdate WHERE id=0');
		$stmt->execute();
		return $stmt->fetchColumn(0);
	}
	public function getSeasonAndEpisodeCountForSeriesByPlexId($plexId) {
		$stmt = $this->db->prepare("
			SELECT ser.id as seriesId, eps.seasonid as seasonId, ps.plexId as plexId, 
			COALESCE(eps.SeasonNumber, '0') as SeasonNumber, count(eps.seasonid) as EpisodeCount
			FROM plexId_seriesId AS ps
			LEFT JOIN thetvdb_series AS ser ON ser.id = ps.seriesId
			LEFT JOIN thetvdb_episodes AS eps ON ser.id = eps.seriesId
			WHERE ps.plexId = ?
			GROUP BY eps.seasonid
			ORDER BY eps.SeasonNumber
		");
		if ($stmt->execute(array($plexId))) {
			return $stmt->fetchAll();
		}
		return false;
	}
	public function getSeasonAndEpisodeCountForSeriesBySeriesId($seriesId) {
		$stmt = $this->db->prepare("
			SELECT ser.id as seriesId, eps.seasonid as seasonId, ps.plexId as plexId, 
			COALESCE(eps.SeasonNumber, '0') as SeasonNumber, count(eps.seasonid) as EpisodeCount
			FROM thetvdb_series AS ser
			LEFT JOIN thetvdb_episodes AS eps ON ser.id = eps.seriesId
			LEFT JOIN plexId_seriesId AS ps ON ps.seriesId = ser.id
			WHERE ser.id = ?
			GROUP BY eps.seasonid
			ORDER BY eps.SeasonNumber
		");
		if ($stmt->execute(array($seriesId))) {
			return $stmt->fetchAll();
		}
		return false;
	}
	public function getEpisodeTitleByPlexId($plexId, $season, $epNum) {
		$stmt = $this->db->prepare("
			SELECT EpisodeName, OverView, IMDB_ID
			FROM thetvdb_episodes AS ep
			LEFT JOIN plexId_seriesId AS ps ON ps.seriesId = ep.seriesid
			WHERE ps.plexId = ? AND ep.EpisodeNumber = ? AND SeasonNumber = ?
		");
		if ($stmt->execute(array($plexId, $epNum, $season))) {
			return $stmt->fetch();
		}
		return false;
	}
	public function getFullSeriesByPlexId($plexId) {
		$stmt = $this->db->prepare("
			SELECT ep.FirstAired, series.SeriesName as SeriesName, ep.EpisodeName, ep.OverView, ep.SeasonNumber, ep.EpisodeNumber
			FROM thetvdb_episodes AS ep
			LEFT JOIN plexId_seriesId AS ps ON ps.seriesId = ep.seriesid
			LEFT JOIN thetvdb_series AS series ON series.id = ps.seriesId
			WHERE ps.plexId = ?
			ORDER BY SeasonNumber, EpisodeNumber
		");
		if ($stmt->execute(array($plexId))) {
			return $stmt->fetchAll();
		}
		return false;
	}
	public function getAiredEpisodesForASeriesByPlexId($plexId) {
		$stmt = $this->db->prepare("
			SELECT ep.FirstAired, series.SeriesName as SeriesName, ep.EpisodeName, ep.OverView, ep.SeasonNumber, ep.EpisodeNumber
			FROM thetvdb_episodes AS ep
			LEFT JOIN plexId_seriesId AS ps ON ps.seriesId = ep.seriesid
			LEFT JOIN thetvdb_series AS series ON series.id = ps.seriesId
			WHERE ps.plexId = ? AND (ep.FirstAired < strftime('%s', 'now') OR ep.FirstAired IS NULL)
			ORDER BY SeasonNumber, EpisodeNumber
		");
		if ($stmt->execute(array($plexId))) {
			return $stmt->fetchAll();
		}
		return false;
	}
	public function getPlexData() {
		$stmt = $this->db->prepare('SELECT data FROM plexData WHERE id=0');
		$res = $stmt->execute();
		if ($res === false) return false;
		return unserialize($stmt->fetchColumn());
	}
	public function getSeriesbyPlexId($plexId) {
		foreach ($this->db->query("
			SELECT series.*
			FROM thetvdb_series as series
			LEFT JOIN plexId_seriesId AS ps ON ps.seriesId = series.id
			WHERE ps.plexId = ".((int)$plexId)
		, PDO::FETCH_NAMED) as $row) return $row;
		return false;
	}
	public function getSeriesIdByPlexId($plexId) {
		$stmt = $this->db->prepare('SELECT seriesId FROM plexId_seriesId WHERE plexId = ?');
		$stmt->execute(array($plexId));
		return $stmt->fetchColumn();
	}
	public function getSeriesCount() {
		$stmt = $this->db->prepare('SELECT COUNT(*) FROM thetvdb_series');
		$stmt->execute();
		return $stmt->fetchColumn();
	}
	public function savePlexData($data) {
		$stmt = $this->db->prepare('INSERT OR REPLACE INTO plexData (id, data) VALUES (0, ?)');
		$res = $stmt->execute(array(serialize($data)));
		if ($res === false) return false;
		return true;
	}
	public function isSeriesCached($id) {
		$ret = false;
		$stmt = $this->db->prepare('SELECT id FROM thetvdb_series where id=?');
		$stmt->execute(array($id));
		return (bool)(count($stmt->fetchAll()));
	}
	public function isEpisodeCached($id) {
		$ret = false;
		$stmt = $this->db->prepare('SELECT id FROM thetvdb_episodes where id=?');
		$stmt->execute(array($id));
		return (bool)(count($stmt->fetchAll()));
	}
	public function isActorCached($actor) {
		$stmt = $this->db->prepare('SELECT id FROM thetvdb_actors where Name=?');

		$stmt->execute(array($actor));
		$res = $stmt->fetchAll();

		if (count($res) > 0) return $res[0]['id'];
		return false;
	}
	public function isGenreCached($genre) {
		$stmt = $this->db->prepare('SELECT id FROM thetvdb_genres where Genre=?');

		$stmt->execute(array($genre));
		$res = $stmt->fetchAll();

		if (count($res) > 0) return $res[0]['id'];
		return false;
	}
	public function updateSeries($seriesId, $series) {
		$fields = $values = $placeHolders = array();
		unset($series->seasons);
		// build statement
		foreach ($series as $field=>$value) {
			if (!property_exists('TV_Series', $field)) {
				// logWarn('MODEL: No such property: '. $field);
				continue;
			}
			switch ($field) {
				case 'Actors': case 'Genre': break;
				default:
					$fields[] = $field;
					$placeHolders[] = '?';
					if (!$value) $value = null;
					$values[] = $value;
				break;
			}
		}
		$stmt = $this->db->prepare('REPLACE INTO thetvdb_series ('.implode(',',$fields).') VALUES ('.implode(',',$placeHolders).')');
		
		if ($stmt == false) return false;
		// cache new series record
		if (!$stmt->execute($values)) {
			$err = $stmt->errorInfo();
			logError("Error [{$err[1]}]: {$err[2]}");
			return false;
		}

		// cache and link actors and genres
		if (!$this->cacheSeriesActors($series->Actors, $series->id)) return false;
		if (!$this->cacheSeriesGenres($series->Genre, $series->id)) return false;

		// logInfo("SERIES: {$series->SeriesName} Updated!");
		return true;
	}	
	public function updateEpisode($episode) {
		$fields = $values = $placeHolders = array();

		// build statement
		foreach ($episode as $field=>$value) {
			if (!property_exists('TV_Episode', $field)) {
				// logWarn('MODEL: No such property: '. $field);
				continue;
			}
			switch ($field) {
				case 'Director': case 'GuestStars': case 'Writer': break;
				default:
					$fields[] = $field;
					$placeHolders[] = '?';
					if (!$value) $value = null;
					$values[] = $value;
				break;
			}
		}
		$stmt = $this->db->prepare('REPLACE INTO thetvdb_episodes (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeHolders) . ')');
		if ($stmt == false) return false;

		// cache new episode record
		if ($stmt->execute($values)) {
			// logInfo('EPISODE: ' . $episode->EpisodeName . ' updated!');
			return true;
		} else {
			$err = $stmt->errorInfo();
			logError("Error [{$err[1]}]: {$err[2]}");
			return false;
		}
	}
	public function cacheSeriesId($seriesId, $plexId) {
		$stmt = $this->db->prepare('INSERT OR REPLACE INTO plexId_seriesId (seriesId, plexId) VALUES (?, ?)');
		return $stmt->execute(array($seriesId, $plexId));
	}
	public function cacheNewEpisode($episode, $seriesId) { // TODO#: remote seriesid argument
		$fields = $values = $placeHolders = array();

		// build statement
		foreach ($episode as $field=>$value) {
			if (!property_exists('TV_Episode', $field)) {
				// logWarn('MODEL: No such property: '. $field);
				continue;
			}
			switch ($field) {
				case 'Director': case 'GuestStars': case 'Writer': break;
				default:
					$fields[] = $field;
					$placeHolders[] = '?';
					if (!$value) $value = null;
					$values[] = $value;
				break;
			}
		}
		$stmt = $this->db->prepare('INSERT INTO thetvdb_episodes (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeHolders) . ')');
		if ($stmt == false) return false;

		// cache new episode record
		if ($stmt->execute($values)) {
			// logInfo('EPISODE: ' . $episode->EpisodeName . ' cached!');
			return true;
		} else {
			$err = $stmt->errorInfo();
			logError("Error [{$err[1]}]: {$err[2]}");
			return false;
		}
	}
	public function cacheNewSeries($series) {
		
		$fields = $values = $placeHolders = array();
		$error = false;
		$seasons = $series->seasons;
		unset($series->seasons);
		$this->db->beginTransaction();
		// build statement
		foreach ($series as $field=>$value) {
			if (!property_exists('TV_Series', $field)) {
				logWarn('MODEL: No such property: '. $field);
				continue;
			}
			switch ($field) {
				case 'Actors': case 'Genre': break;
				default:
					$fields[] = $field;
					$placeHolders[] = '?';
					if (!$value) $value = null;
					$values[] = $value;
				break;
			}
		}
		$stmt = $this->db->prepare('INSERT INTO thetvdb_series ('.implode(',',$fields).') VALUES ('.implode(',',$placeHolders).')');
		if ($stmt == false) $error = true;

		// cache and link actors and genres
		if (!$this->cacheSeriesActors($series->Actors, $series->id)) $error = true;
		if (!$this->cacheSeriesGenres($series->Genre, $series->id)) $error = true;
		
		// cache new series record
		if (!$stmt->execute($values)) {
			$err = $stmt->errorInfo();
			logError("Error [{$err[1]}]: {$err[2]}");
			$error = true;
		} else {
			// logInfo("SERIES: {$series->SeriesName} cached!");
		}

		// cache and link episodes
		foreach ($seasons as $season) {
			foreach ($season as $episode) {
				if (!$this->isEpisodeCached($episode->id)) {
					if (!$this->cacheNewEpisode($episode, $series->id)) $error = true;
				} else {
					// logInfo("***: Episode already cached!");
				}
			}
		}
		if ($error == true) {
			$this->db->rollback();
			// logInfo('***ROLLBACK***');
			return false;
		} else {
			// logInfo('***COMMIT***');
			$this->db->commit();
			return true;
		}
	}
}