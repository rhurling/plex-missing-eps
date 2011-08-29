<?php
/**
 * Base class for interacting with TV shows
 *
 * @package PHP::TVDB
 * @author Ryan Doherty <ryan@ryandoherty.com>
 */

class TV_Series extends TVDB {


	public $id;
	public $Actors;
	public $Airs_DayOfWeek;
	public $Airs_Time;
	public $ContentRating;
	public $FirstAired;
	public $Genre;
	public $IMDB_ID;
	public $Language;
	public $Network;
	public $NetworkID;
	public $Overview;
	public $Rating;
	public $RatingCount;
	public $Runtime;
	public $SeriesID;
	public $SeriesName;
	public $Status;
	public $added;
	public $addedBy;
	public $banner;
	public $fanart;
	public $lastupdated;
	public $poster;
	public $zap2it_id;
	public $seasons;
	
	/**
	 * Constructor
	 *
	 * @access public
	 * @param SimpleXMLObject $config A simplexmlobject created from thetvdb.com's xml data for the tv show
	 * @return void
	 **/
	public function __construct(SimpleXMLElement $config) {
		parent::__construct();
		foreach ($config->children() as $i =>$blah) {
			switch($i) {
				case 'FirstAired':
					$this->$i = strtotime((string)$blah);
				break;
				case 'Actors': case 'Genre':
					$this->$i = $this->removeEmptyIndexes(explode('|', (string)$blah));
				break;
				default:
					$this->$i = (string)$blah;
				break;
			}
		}
	}
	
	public function addEpisode(TV_Episode $ep) {
		$this->seasons[((int)$ep->SeasonNumber)][] = $ep;
	}
}