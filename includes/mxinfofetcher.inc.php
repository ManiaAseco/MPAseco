<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * MXInfoFetcher - Fetch info/records for TM2/SM/QM maps from ManiaExchange
 * Created by Xymph <tm@gamers.org> based on:
 * http://tm.mania-exchange.com/api
 * http://tm.mania-exchange.com/threads/view/218
 * Derived from TMXInfoFetcher
 *
 * v1.3: Added URLs to downloadable replays
 * v1.2: Added the replays list in $recordlist
 * v1.1: Allowed 25-char UIDs too
 * v1.0: Initial release
 */
class MXInfoFetcher {

	public $section, $prefix, $uid, $id, $records, $error,
		$name, $userid, $author, $uploaded, $updated,
		$type, $style, $envir, $mood, $dispcost, $lightmap, $modname,
		$exever, $exebld, $routes, $length, $laps, $diffic, $lbrating,
		$replaytyp, $replayid, $replaycnt, $acomment, $awards, $comments,
		$pageurl, $replayurl, $imageurl, $thumburl, $dloadurl, $recordlist;

	/**
	 * Fetches all available data for a ManiaExchange map
	 *
	 * @param String $game
	 *        MX section for 'TM2', 'SM', 'QM'
	 * @param String $id
	 *        The map UID to search for (if a 25-27 char alphanum string),
	 *        otherwise the MX ID to search for (if a number)
	 * @param Boolean $records
	 *        If true, the script also returns the world records (max. 10)
	 *        [not yet available]
	 * @return MXInfoFetcher
	 *        If $error is not an empty string, it's an error message
	 */
	public function MXInfoFetcher($game, $id, $records) {

		$this->section = $game;
		switch ($game) {
		case 'TM2':
			$this->prefix = 'tm';
			break;
		case 'SM':
			$this->prefix = 'sm';
			break;
		case 'QM':
			$this->prefix = 'qm';
			break;
		default:
			$this->prefix = '';
			return;
		}

		$this->error = '';
		$this->records = $records;
		// check for UID string
		if (preg_match('/^\w{25,27}$/', $id)) {
			$this->uid = $id;
			$this->getData(true);
		// check for MX ID
		} elseif (is_numeric($id) && $id > 0) {
			$this->id = floor($id);
			$this->getData(false);
		}
	}  // MXInfoFetcher

	public static function __set_state($import) {

		$mx = new MXInfoFetcher('', 0, false);

		$mx->section   = $import['section'];
		$mx->prefix    = $import['prefix'];
		$mx->uid       = $import['uid'];
		$mx->id        = $import['id'];
		$mx->records   = $import['records'];
		$mx->error     = '';
		$mx->name      = $import['name'];
		$mx->userid    = $import['userid'];
		$mx->author    = $import['author'];
		$mx->uploaded  = $import['uploaded'];
		$mx->updated   = $import['updated'];
		$mx->type      = $import['type'];
		$mx->style     = $import['style'];
		$mx->envir     = $import['envir'];
		$mx->mood      = $import['mood'];
		$mx->dispcost  = $import['dispcost'];
		$mx->lightmap  = $import['lightmap'];
		$mx->modname   = $import['modname'];
		$mx->exever    = $import['exever'];
		$mx->exebld    = $import['exebld'];
		$mx->routes    = $import['routes'];
		$mx->length    = $import['length'];
		$mx->laps      = $import['laps'];
		$mx->diffic    = $import['diffic'];
		$mx->lbrating  = $import['lbrating'];
		$mx->replaytyp = $import['replaytyp'];
		$mx->replayid  = $import['replayid'];
		$mx->replaycnt = $import['replaycnt'];
		$mx->acomment  = $import['acomment'];
		$mx->awards    = $import['awards'];
		$mx->comments  = $import['comments'];
		$mx->pageurl   = $import['pageurl'];
		$mx->replayurl = $import['replayurl'];
		$mx->imageurl  = $import['imageurl'];
		$mx->thumburl  = $import['thumburl'];
		$mx->dloadurl  = $import['dloadurl'];
		$mx->recordlist = null;

		return $mx;
	}  // __set_state

	private function getData($isuid) {

		// get map info
		$url = 'http://' . $this->prefix . '.mania-exchange.com/api/tracks/get_track_info/' . ($isuid ? 'u' : '') . 'id/' . ($isuid ? $this->uid : $this->id);
		$file = $this->get_file($url);
		if ($file === false) {
			$this->error = 'Connection or response error on ' . $url;
			return;
		} else if ($file === -1) {
			$this->error = 'Timed out while reading data from ' . $url;
			return;
		} else if ($file == '') {
			$this->error = 'No data returned from ' . $url;
			return;
		}

		// process map info
		$mx = json_decode($file);
		if ($mx === null) {
			$this->error = 'Cannot decode JSON data from ' . $url;
			return;
		}

		if ($isuid)
			$this->id      = $mx->TrackID;

		$this->name      = $mx->Name;
		$this->userid    = $mx->UserID;
		$this->author    = $mx->Username;
		$this->uploaded  = $mx->UploadedAt;
		$this->updated   = $mx->UpdatedAt;
		$this->type      = $mx->TypeName;
		$this->style     = $mx->StyleName;
		$this->envir     = $mx->EnvironmentName;
		$this->mood      = $mx->Mood;
		$this->dispcost  = $mx->DisplayCost;
		$this->lightmap  = $mx->Lightmap;
		$this->modname   = isset($mx->ModName) ? $mx->ModName : '';
		$this->exever    = $mx->ExeVersion;
		$this->exebld    = $mx->ExeBuild;
		$this->routes    = $mx->RouteName;
		$this->length    = $mx->LengthName;
		$this->laps      = $mx->Laps;
		$this->diffic    = $mx->DifficultyName;
		$this->lbrating  = isset($mx->LBRating) ? $mx->LBRating : '0';
		$this->replaytyp = $mx->ReplayTypeName;
		$this->replayid  = $mx->ReplayWRID;
		$this->replaycnt = $mx->ReplayCount;
		$this->acomment  = $mx->Comments;
		$this->awards    = $mx->AwardCount;
		$this->comments  = $mx->CommentCount;

		$search = array(chr(31), '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[url]', '[/url]');
		$replace = array('<br/>', '<b>', '</b>', '<i>', '</i>', '<u>', '</u>', '<i>', '</i>');
		$this->acomment  = str_ireplace($search, $replace, $this->acomment);
		$this->acomment  = preg_replace('/\[url=.*\]/', '<i>', $this->acomment);

		$this->pageurl   = 'http://' . $this->prefix . '.mania-exchange.com/tracks/view/' . $this->id;
		$this->imageurl  = 'http://' . $this->prefix . '.mania-exchange.com/tracks/screenshot/normal/' . $this->id;
		$this->thumburl  = 'http://' . $this->prefix . '.mania-exchange.com/tracks/screenshot/small/' . $this->id;
		$this->dloadurl  = 'http://' . $this->prefix . '.mania-exchange.com/tracks/download/' . $this->id;

		if ($this->replayid > 0) {
			$this->replayurl = 'http://' . $this->prefix . '.mania-exchange.com/replays/download/' . $this->replayid;
		} else {
			$this->replayurl = '';
		}

		// fetch records too?
		$this->recordlist = array();
		if ($this->records) {
			$limit = 15;
			$url = 'http://' . $this->prefix . '.mania-exchange.com/api/replays/get_replays/' . $this->id . '/' . $limit . '/';
			$file = $this->get_file($url);
			if ($file === false) {
				$this->error = 'Connection or response error on ' . $url;
				return;
			} else if ($file === -1) {
				$this->error = 'Timed out while reading data from ' . $url;
				return;
			} else if ($file == '') {
				$this->error = 'No data returned from ' . $url;
				return;
			}

			// process replays info
			$mx = json_decode($file);
			if ($mx === null) {
				$this->error = 'Cannot decode JSON data from ' . $url;
				return;
			}

			$i = 0;
			while ($i < $limit && isset($mx[$i])) {
				$this->recordlist[$i] = array(
				                          'replayid'   => $mx[$i]->ReplayID,
				                          'userid'     => $mx[$i]->UserID,
				                          'username'   => $mx[$i]->Username,
				                          'uploadedat' => $mx[$i]->UploadedAt,
				                          'replaytime' => $mx[$i]->ReplayTime,
				                          'stuntscore' => $mx[$i]->StuntScore,
				                          'respawns'   => $mx[$i]->Respawns,
				                          'beaten'     => $mx[$i]->Beaten,
				                          'percentage' => $mx[$i]->Percentage,
				                          'replaypnts' => $mx[$i]->ReplayPoints,
				                          'nadeopnts'  => $mx[$i]->NadeoPoints,
				                          'replayurl'  => 'http://' . $this->prefix . '.mania-exchange.com/replays/download/' . $mx[$i]->ReplayID,
				                        );
				$i++;
			}
		}
	}  // getData

	// Simple HTTP Get function with timeout
	// ok: return string || error: return false || timeout: return -1
	private function get_file($url) {

		$url = parse_url($url);
		$port = isset($url['port']) ? $url['port'] : 80;
		$query = isset($url['query']) ? '?' . $url['query'] : '';

		$fp = @fsockopen($url['host'], $port, $errno, $errstr, 4);
		if (!$fp)
			return false;

		fwrite($fp, 'GET ' . $url['path'] . $query . " HTTP/1.0\r\n" .
		            'Host: ' . $url['host'] . "\r\n" .
		            'User-Agent: MXInfoFetcher (' . PHP_OS . ")\r\n\r\n");
		stream_set_timeout($fp, 2);
		$res = '';
		$info['timed_out'] = false;
		while (!feof($fp) && !$info['timed_out']) {
			$res .= fread($fp, 512);
			$info = stream_get_meta_data($fp);
		}
		fclose($fp);

		if ($info['timed_out']) {
			return -1;
		} else {
			if (substr($res, 9, 3) != '200')
				return false;
			$page = explode("\r\n\r\n", $res, 2);
			return trim($page[1]);
		}
	}  // get_file
}  // class MXInfoFetcher
?>
