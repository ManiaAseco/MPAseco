<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * ReplayParser - Parse in-line data for TrackMania replays
 * Created by Xymph <tm@gamers.org>
 * If your replay data is in a file, use the GBXReplayFetcher class
 * in the GBXDataFetcher module instead.
 *
 * v1.3: Add TM2C Replay compatibility; extract TM2C $exebld
 * v1.2: Fix PHP Notice level warnings
 * v1.1: Remove die() on XML parser error: if $parsedxml is a string, it
 *       is the error message, otherwise the parsed XML array; add $strtype
 * v1.0: Initial release
 */

class ReplayParser {

	public $uid, $version, $strtype, $author, $envir, $nickname, $login, $replay,
	       $xml, $parsedxml, $xmlver, $exever, $exebld, $respawns, $stuntscore,
	       $validable, $cpscur, $cpslap;

	private $replaydata, $ptr;

	/**
	 * Fetches a hell of a lot of data about a replay
	 *
	 * @param Base64 $replaydata
	 *        The replay data
	 * @return ReplayParser
	 *        If $uid is empty, replay data couldn't be extracted
	 */
	public function ReplayParser($replaydata) {

		$this->replaydata = $replaydata;
		$this->ptr = 0;
		$this->getData();
		$this->replaydata = '';  // for print_r
	}  // ReplayParser

	// data read function
	private function ReadData($len) {

		$data = '';
		while ($len-- > 0)
			$data .= $this->replaydata[$this->ptr++];
		return $data;
	}  // ReadData

	// string read function
	private function ReadString() {

		$data = $this->ReadData(4);
		$result = unpack('Vlen', $data);
		$len = $result['len'];
		if ($len <= 0 || $len >= 0x10000) {  // for large XML blocks
			return 'read error';
		}
		$data = $this->ReadData($len);
		return $data;
	}  // ReadString

	// parser functions
	private function startTag($parser, $name, $attribs) {
		// echo 'startTag: ' . $name . "\n"; print_r($attribs);
		if ($name == 'DEPS') {
			$this->parsedxml['DEPS'] = array();
		} elseif ($name == 'DEP') {
			$this->parsedxml['DEPS'][] = $attribs;
		} else {  // HEADER, IDENT, DESC, TIMES
			$this->parsedxml[$name] = $attribs;
		}
	}  // startTag

	private function charData($parser, $data) {
		// nothing to do here
		// echo 'charData: ' . $data . "\n";
	}  // charData

	private function endTag($parser, $name) {
		// nothing to do here
		// echo 'endTag: ' . $name . "\n";
	}  // endTag

	private function getData() {

		// check for minimal data
		if (!isset($this->replaydata) || !is_string($this->replaydata) || strlen($this->replaydata) < 5) {
			return false;
		}

		// check for magic GBX header
		$data = $this->ReadData(5);
		if ($data != 'GBX' . chr(6) . chr(0)) {
			return false;
		}

		$skip = $this->ReadData(4);   // "BUCR" | "BUCE"
		// get GBX type & check for Replay
		$data = $this->ReadData(4);
		$r = unpack('Ngbxtype', $data);
		$t = sprintf('%08X', $r['gbxtype']);
		if ($t != '00E00724' && $t != '00F00324' && $t != '00300903') {
			return false;
		}

		// get GBX version: 1 = TM, 2 = TMPU/TMO/TMS/TMN/TMU/TMF/TM2C
		$skip = $this->ReadData(4);  // data block offset
		$data = $this->ReadData(4);
		$r = unpack('Vversion', $data);
		$this->version = $r['version'];
		// check for unsupported versions
		if ($this->version < 1 || $this->version > 3) {
			return false;
		}

		// get Index (marker/lengths) table
		for ($i = 1; $i <= $this->version; $i++) {
			$data = $this->ReadData(8);
			$r = unpack('Nmark'.$i . '/Vlen'.$i, $data);
			$len[$i] = $r['len'.$i];
		}
		if ($this->version == 2) {  // clear high-bit
			$len[2] &= 0x7FFFFFFF;
		}

		// start of Strings block:
		// 0x1D (TM v1), 0x25 (all v2)
		// check type of Strings block
		$data = $this->ReadData(4);
		$r = unpack('Vstrtype', $data);
		$this->strtype = $r['strtype'];

		if ($this->strtype >= 3) {
			$skip = $this->ReadData(8);  // 03 00 00 00 and 00 00 00 80
			$this->uid = $this->ReadString();
			$data = $this->ReadData(4);  // if C0 00 00 00 no env, otherwise 00 00 00 40 and env
			$r = unpack('Venv', $data);
			if ($r['env'] != 12)
				$this->envir = $this->ReadString();
			else
				$this->envir = '';
			$skip = $this->ReadData(4);  // 00 00 00 [40|80]
			$this->author = $this->ReadString();
			$data = $this->ReadData(4);
			$r = unpack('Vreplay', $data);
			$this->replay = $r['replay'];
			$this->nickname = $this->ReadString();

			// check whether to get login (TMU/TMF, exever>="0.1.9.0")
			if ($this->strtype >= 6) {
				$this->login = $this->ReadString();
			}
		}

		// get optional XML block & wrap lines for readability
		if ($this->version >= 2) {
			$this->xml = $this->ReadString();
			$this->xml = str_replace("><", ">\n<", $this->xml);
		}

		// parse XML block too?
		$this->parsedxml = array();
		if ($this->xml) {
			// define a dedicated parser to handle the attributes
			$xml_parser = xml_parser_create();
			xml_set_object($xml_parser, $this);
			xml_set_element_handler($xml_parser, 'startTag', 'endTag');
			xml_set_character_data_handler($xml_parser, 'charData');

			if (!xml_parse($xml_parser, utf8_encode($this->xml), true)) {
				$this->parsedxml = sprintf("ReplayParser XML error in %s: %s at line %d", $this->uid,
				                           xml_error_string(xml_get_error_code($xml_parser)),
				                           xml_get_current_line_number($xml_parser));
				xml_parser_free($xml_parser);
				return false;
			}
			xml_parser_free($xml_parser);

			// extract some specific attributes that aren't in the Header block
			if (isset($this->parsedxml['HEADER']['VERSION']))
				$this->xmlver = $this->parsedxml['HEADER']['VERSION'];
			else
				$this->xmlver = '';
			if (isset($this->parsedxml['HEADER']['EXEVER']))
				$this->exever = $this->parsedxml['HEADER']['EXEVER'];
			else
				$this->exever = '';
			if (isset($this->parsedxml['HEADER']['EXEBUILD']))
				$this->exebld = $this->parsedxml['HEADER']['EXEBUILD'];
			else
				$this->exebld = '';
			if (isset($this->parsedxml['TIMES']['RESPAWNS']))
				$this->respawns = $this->parsedxml['TIMES']['RESPAWNS'];
			else
				$this->respawns = '';
			if (isset($this->parsedxml['TIMES']['STUNTSCORE']))
				$this->stuntscore = $this->parsedxml['TIMES']['STUNTSCORE'];
			else
				$this->stuntscore = '';
			if (isset($this->parsedxml['TIMES']['VALIDABLE']))
				$this->validable = $this->parsedxml['TIMES']['VALIDABLE'];
			else
				$this->validable = '';
			if (isset($this->parsedxml['CHECKPOINTS'])) {
				$this->cpscur = $this->parsedxml['CHECKPOINTS']['CUR'];
				$this->cpslap = $this->parsedxml['CHECKPOINTS']['ONELAP'];
			} else {
				$this->cpscur = '';
				$this->cpslap = '';
			}
		}
	}  // getData
}  // class ReplayParser
?>
