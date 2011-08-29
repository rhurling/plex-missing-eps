<?php
define('TVDB_API_KEY', 'C6AC0F15DB06B9F4');
define('SKIP_EXTRAS', TRUE);
define('PLEX_SERVER', 'imac2:32400');

/* After you have editted this file, remove this whole block (including the define) */
// define('YOU_DIDNT_EDIT_THE_CONFIG_FILE', true);
/* End config edit check block */

require_once('util.inc.php');
require_once('tvdb/TVDB.class.php');
require_once('tvdb/TV_Series.class.php');
require_once('tvdb/TV_Show_Search.class.php');
require_once('tvdb/TV_Episode.class.php');
require_once('Missing_Model.php');

libxml_use_internal_errors(true);
set_time_limit(1200);
