<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * GBXDataFetcher - Fetch GBX challenge/map/replay data for TrackMania tracks
 * Created by Xymph <tm@gamers.org>
 * Thanks to Electron for additional input
 * Based on information at http://en.tm-wiki.org/index.php?title=GBX&oldid=5300
 * Inspired by TMNDataFetcher & "Extract GBX data"
 *  
 * v1.22: Quick & dirty fix for Shootmania maps. Updated by kremsy and maniactwister
 * 
 * v1.21: Add TM2C Map version 6 compatibility; add $unknown4 & $unknown5; add
 *        track types Shortcut & Script
 * v1.20: Add TM2C Map/Replay compatibility; extract challenge $editor;
 *        rename $coppers to $cost and extract TM2C $azone, $exebld, $lightmap
 *        in GBXChallengeFetcher; extract TM2C $exebld in GBXReplayFetcher
 * v1.15: Fix PHP Notice level warnings
 * v1.14: Extract $songurl, $modurl from XML block in GBXChallengeFetcher
 * v1.13: Remove die() on XML parser error: if $parsedxml is a string, it
 *        is the error message, otherwise the parsed XML array; add $strtype
 *        in GBXReplayFetcher; fix XML parsing bug
 * v1.12: Fix escaping of '&' characters; fix rare thumbnail read error
 * v1.11: In GBXReplayFetcher extract checkpoints fields if present;
 *        escape '&' characters before XML parse in GBXChallengeFetcher
 * v1.10: Flip $thumbnail if GD/JPEG libraries are present
 * v1.9: Fix $modname check
 * v1.8: Allow parsing of XML blocks with 8-bit ISO-8859-1 characters
 * v1.7: Add GBXReplayFetcher class; rename GBXDataFetcher->GBXChallengeFetcher
 * v1.6: Add TMF Challenge compatibility
 * v1.5: Fix class structure and parser definition
 * v1.4: Add compatibility for version 3/4, exever="0.1.3.0-0.1.4.0" (TMO/TMS)
 * v1.3: Extract TMU-only $thumbnail, $comments; add compatibility for
 *       versions 2, 3 and (old) 4; test for non-Challenge files; add
 *       type 3 (Crazy); fix $parsexml test; and more
 * v1.1: Extract $type, $multi, $unknown from Header block; extract $pub
 *       only if present; extract $modname, $modfile from XML block;
 *       rename $song to $songfile; and more
 * v1.0: Initial release
 */

define('IMAGE_FLIP_HORIZONTAL', 1);
define('IMAGE_FLIP_VERTICAL', 2);
define('IMAGE_FLIP_BOTH', 3);

class GBXChallengeFetcher {

	public $filename, $parsexml, $tnimage,
	       $uid, $version, $name, $author, $azone, $type, $envir, $mood,
	       $pub, $authortm, $goldtm, $silvertm, $bronzetm, $cost, $multi,
	       $unknown, $unknown2, $unknown4, $unknown5, $ascore, $editor, $password,
	       $xml, $parsedxml, $xmlver, $exever, $exebld, $lightmap, $nblaps,
	       $songfile, $songurl, $modname, $modfile, $modurl, $thumbnail, $comment;

	/**
	 * Fetches a hell of a lot of data about a GBX challenge
	 *
	 * @param String $filename
	 *        The challenge filename (must include full path)
	 * @param Boolean $parsexml
	 *        If true, the script also parses the XML block
	 * @param Boolean $tnimage
	 *        If true, the script also extracts the thumbnail image; if GD/JPEG
	 *        libraries are present, image will be flipped upright, otherwise
	 *        it will be in the original upside-down format
	 *        Warning: this is binary data in JPEG format, 256x256 pixels
	 * @return GBXChallengeFetcher
	 *        If $uid is empty, GBX data couldn't be extracted
	 */
	public function GBXChallengeFetcher($filename, $parsexml, $tnimage = false) {

		$this->filename = $filename;
		$this->parsexml = $parsexml;
		$this->tnimage  = $tnimage;
		$this->getData();
	}  // GBXChallengeFetcher

	// string read function
	private function ReadGBXString($handle) {

		$data = fread($handle, 4);
		$result = unpack('Vlen', $data);
		$len = $result['len'];
		if ($len <= 0 || $len >= 0x10000) {  // for large XML blocks
			return 'read error';
		}
		$data = fread($handle, $len);
		return $data;
	}  // ReadGBXString
  
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

	// http://www.php.net/manual/en/function.imagecopy.php#85992
	private function imageFlip($imgsrc, $mode) {

		$width      = imagesx($imgsrc);
		$height     = imagesy($imgsrc);
		$src_x      = 0;
		$src_y      = 0;
		$src_width  = $width;
		$src_height = $height;

		switch ((int)$mode) {
		case IMAGE_FLIP_HORIZONTAL:
			$src_y      =  $height;
			$src_height = -$height;
			break;
		case IMAGE_FLIP_VERTICAL:
			$src_x      =  $width;
			$src_width  = -$width;
			break;
		case IMAGE_FLIP_BOTH:
			$src_x      =  $width;
			$src_y      =  $height;
			$src_width  = -$width;
			$src_height = -$height;
			break;
		default:
			return $imgsrc;
		}

		$imgdest = imagecreatetruecolor($width, $height);
		if (imagecopyresampled($imgdest, $imgsrc, 0, 0, $src_x, $src_y,
		                       $width, $height, $src_width, $src_height)) {
			return $imgdest;
		}
		return $imgsrc;
	}  // imageFlip

	private function getData() {

		if (!$handle = @fopen($this->filename, 'rb')) {
			return false;
		}

		// check for magic GBX header
		fseek($handle, 0x00, SEEK_SET);
		$data = fread($handle, 5);
		if ($data != 'GBX' . chr(6) . chr(0)) {
			fclose($handle);
			return false;
		}

		fseek($handle, 0x04, SEEK_CUR);  // "BUCR" | "BUCE"
		// get GBX type & check for Challenge
		$data = fread($handle, 4);
		$r = unpack('Ngbxtype', $data);
		$t = sprintf('%08X', $r['gbxtype']);
		if ($t != '00300024' && $t != '00300403') {
			fclose($handle);
			return false;
		}

		// get GBX version: 2/3 = TM/TMPowerUp, 4 = TMO(LevelUp)/TMS/TMN, 5 = TMU/TMF/TM2C
		fseek($handle, 0x04, SEEK_CUR);  // data block offset
		$data = fread($handle, 4);
		$r = unpack('Vversion', $data);
		$this->version = $r['version'];
		// check for unsupported versions
		if ($this->version < 2 || $this->version > 6) {
			fclose($handle);
			return false;
		}

		// get Index (marker/lengths) table
		for ($i = 1; $i <= $this->version; $i++) {
			$data = fread($handle, 8);
			$r = unpack('Nmark'.$i . '/Vlen'.$i, $data);
			$len[$i] = $r['len'.$i];
		}
		if ($this->version >= 5) {  // clear high-bits
			$len[4] &= 0x7FFFFFFF;
			$len[5] &= 0x7FFFFFFF;
		}

		// start of Times/info block:
		// 0x25 (TM v2), 0x2D (TMPowerUp v3), 0x35 (TMO/TMS/TMN v4), 0x3D (TMU/TMF v5), 0x45 (TM2C v6)
		// get count of Times/info entries (well... sorta)
    $blk[1] = ftell($handle);
    
		$data = fread($handle, 1);
		// TM v2 tracks use 3, TMPowerUp v3 tracks use 4; actual count is 2 more
		// oldest TMO/TMS tracks (exever="0.1.3.0-0.1.4.1") use 6-8, actual count always 8; no unknown2/ascore
		// older TMS tracks (exever="0.1.4.3-6") use 9; no author score
		// newer TMO/TMS tracks (exever>="0.1.4.8") and TMN/TMU/TMF tracks (exever<="2.11.4") use 10
		// TMF tracks (exever>="2.11.5") use 11; with editor
		// TM2C beta tracks (exever>="3.0.0") use 12; with unknown3
		// TM2C release tracks (exever>="3.0.0") use 13; with unknown4 & unknown5
		$count = ord($data);

		fseek($handle, 0x04, SEEK_CUR);  // Unknown1: 00 00 00 00
		$data = fread($handle, 4);
		$r = unpack('Vbronze', $data);
		$this->bronzetm = $r['bronze'];
		$data = fread($handle, 4);
		$r = unpack('Vsilver', $data);
		$this->silvertm = $r['silver'];
		$data = fread($handle, 4);
		$r = unpack('Vgold', $data);
		$this->goldtm = $r['gold'];
		$data = fread($handle, 4);
		$r = unpack('Vauthor', $data);
		$this->authortm = $r['author'];

		if ($this->version >= 3) {
			$data = fread($handle, 4);
			$r = unpack('Vcost', $data);
			$this->cost = $r['cost'];
		}
		if ($count >= 6) {  // version >= 3, exever>="0.1.3.0"
			$data = fread($handle, 4);
			$r = unpack('Vmulti', $data);
			$this->multi = ($r['multi'] != 0 ? true : false);
			$data = fread($handle, 4);
			$r = unpack('Vtype', $data);
			switch ($r['type']) {
				case 0: $this->type = 'Race';
				        break;
				case 1: $this->type = 'Platform';
				        break;
				case 2: $this->type = 'Puzzle';
				        break;
				case 3: $this->type = 'Crazy';
				        break;
				case 4: $this->type = 'Shortcut';
				        break;
				case 5: $this->type = 'Stunts';
				        break;
				case 6: $this->type = 'Script';
				        break;
				default: $this->type = 'unknown!';
			}

			// check whether to fetch unknown2
			if ($count >= 9) {
				$data = fread($handle, 4);
				$r = unpack('Vunknown2', $data);
				$this->unknown = $r['unknown2'];
			}
			// check whether to fetch author score
			if ($count >= 10) {
				$data = fread($handle, 4);
				$r = unpack('Vascore', $data);
				$this->ascore = $r['ascore'];
			}
			// check whether to fetch editor
			if ($count >= 11) {
				$data = fread($handle, 4);
				$r = unpack('Veditor', $data);
				$this->editor = ($r['editor'] != 0 ? true : false);
			}
			// check whether to fetch unknown3
			if ($count >= 12) {
				$data = fread($handle, 4);
				$r = unpack('Vunknown3', $data);
				$this->unknown2 = $r['unknown3'];
			}
			// check whether to fetch unknown4/5
			if ($count >= 13) {
				$data = fread($handle, 4);
				$r = unpack('Vunknown4', $data);
				$this->unknown4 = $r['unknown4'];
				$data = fread($handle, 4);
				$r = unpack('Vunknown5', $data);
				$this->unknown5 = $r['unknown5'];
			}
		}

		// start of Strings block in version 2 (0x3A, TM)
		// start of Version? block in versions >= 3
    $blk[2] = ftell($handle);
		fseek($handle, 0x04, SEEK_CUR);
		// 00 03 00 00 (TM v2)
		// 01 03 00 00 (TMPowerUp v3; TMO v4, exever="0.1.3.3-5"; TMS v4, exever="0.1.4.0")
		// 02 03 00 00 (TMS v4, exever="0.1.4.1-6")
		// 03 03 00 00 (TMO/TMS v4, exever="0.1.4.8", rare)
		// 04 03 00 00 (TMO/TMS/TMN v4, exever>="0.1.4.8")
		// 05 03 00 00 (TMU/TMF v5)
		// 09 03 00 00 (TM2C v5/v6)

		// start of Strings block in versions >= 3
		// 0x4A (TMPowerUp v3)
		// 0x5A (TMO/TMS v4, exever="0.1.3.3-0.1.4.1")
		// 0x5E (TMS v4, exever="0.1.4.3-6")
		// 0x62 (TMO/TMS/TMN v4, exever>="0.1.4.8")
		// 0x6A (TMU/TMF v5, exever<="2.11.4")
		// 0x6E (TMF v5, exever>="2.11.5")
		// 0x72 (TM2C v5, exever>="3.0.0")
		// 0x82 (TM2C v6, exever>="3.0.0")
    
    
    /**  Shootmania fixes (removed some "if(version >=")  **/
    $blk[3] = ftell($handle);
		fseek($handle, 0x05, SEEK_CUR);  // 00 and 00 00 00 80
		$this->uid = $this->ReadGBXString($handle);
    
    fseek($handle, 0x08, SEEK_CUR);  // 00 and 00 00 00 80
    
    // author length
    $data = fread($handle, 1);
    $r = unpack('Clen', $data);
    
		fseek($handle, 0x03, SEEK_CUR);
    $data = fread($handle, $r['len']);
    $this->author = $data;
    
    // mapname length
    $data = fread($handle, 1);
    $r = unpack('Clen', $data);
    
    fseek($handle, 0x03, SEEK_CUR);
    $data = fread($handle, $r['len']);
    $this->name = $data;
    
    // kind length
    $data = fread($handle, 1);
    $r = unpack('Clen', $data);
    
    fseek($handle, 0x03, SEEK_CUR);
    $data = fread($handle, $r['len']);
    $this->kind = $data;
    
		fseek($handle, 0x1, SEEK_CUR);
    
    // mood length
    $data = fread($handle, 1);
    $r = unpack('Clen', $data);

    fseek($handle, 0x03, SEEK_CUR);
    $data = fread($handle, $r['len']);
    $this->mood = $data;
    
    fseek($handle, 0x08, SEEK_CUR);
    
    // pub length
    $data = fread($handle, 1);
    $r = unpack('Clen', $data);
    
    fseek($handle, 0x03, SEEK_CUR);
    $data = fread($handle, $r['len']);
    $this->pub = $data;
    
    fseek($handle, 32, SEEK_CUR);
    
    // maptype length
    $data = fread($handle, 1);
    $r = unpack('Clen', $data);
    
    fseek($handle, 0x03, SEEK_CUR);
    $data = fread($handle, $r['len']);
    $this->maptype = $data;
    
    fseek($handle, 17, SEEK_CUR);
    
    // mapstyle length
    $data = fread($handle, 1);
    $r = unpack('Clen', $data);
    
    fseek($handle, 0x03, SEEK_CUR);
    $data = fread($handle, $r['len']);
    $this->mapstyle = $data;
    
    //echo ftell($handle)."::\n\n";
    /**  Shootmania fixes end  **/

		// set pointer to start of next block based on actual offsets
		$lens = 0;
		for ($i = 1; $i <= $this->version; $i++) {
			$lens += 8;
			if ($i <= 3)
				$lens += $len[$i];
		}
		fseek($handle, 0x15 + $lens, SEEK_SET);

		// get optional XML block & wrap lines for readability
		if ($this->version >= 4) {
			$this->xml = $this->ReadGBXString($handle);
			$this->xml = str_replace("><", ">\n<", $this->xml);
		}

		// get optional Thumbnail/Comments block
		if ($this->version >= 5) {
			fseek($handle, 0x04, SEEK_CUR);  // 01 00 00 00
			$data = fread($handle, 4);
			$r = unpack('Vthumblen', $data);
			$thumblen = $r['thumblen'];
			fseek($handle, 15, SEEK_CUR);  // '<Thumbnail.jpg>'

			// check for thumbnail
			if ($thumblen > 0 && $thumblen < 0x10000) {
				// extract and optionally return thumbnail image
				$data = fread($handle, $thumblen);

				if ($this->tnimage) {
					$this->thumbnail = $data;

					// check for GD/JPEG libraries
					if (function_exists('imagecopyresampled') &&
					    function_exists('imagecreatefromjpeg')) {
						// flip thumbnail via temporary file
						$tmp = tempnam(sys_get_temp_dir(), 'gbxflip');
						if (@file_put_contents($tmp, $this->thumbnail)) {
							if ($tn = @imagecreatefromjpeg($tmp)) {
								$tn = $this->imageFlip($tn, IMAGE_FLIP_HORIZONTAL);
								if (@imagejpeg($tn, $tmp)) {
									if ($tn = @file_get_contents($tmp)) {
										$this->thumbnail = $tn;
									}
								}
							}
							unlink($tmp);
						}
					}
				}
			}

			fseek($handle, 16, SEEK_CUR);  // '</Thumbnail.jpg>'
			fseek($handle, 10, SEEK_CUR);  // '<Comments>'
			$this->comment = $this->ReadGBXString($handle);
			if ($this->comment == 'read error') $this->comment = '';  // is optional
			fseek($handle, 11, SEEK_CUR);  // '</Comments>'
		}

		fclose($handle);

		// convert password to hex format
		if ($p = $this->password) {
			$this->password = '';
			for ($i = 3; $i < strlen($p); $i++) {  // skip 3 bogus chars
				$this->password .= sprintf('%02X', ord($p{$i}));
			}
		}

		// parse XML block too?
		$this->parsedxml = array();
		if ($this->parsexml && $this->xml) {
			// define a dedicated parser to handle the attributes
			$xml_parser = xml_parser_create();
			xml_set_object($xml_parser, $this);
			xml_set_element_handler($xml_parser, 'startTag', 'endTag');
			xml_set_character_data_handler($xml_parser, 'charData');

			// escape '&' characters unless already a known entity
			$xml = preg_replace('/&(?!(?:amp|quot|apos|lt|gt);)/', '&amp;', $this->xml);

			if (!xml_parse($xml_parser, utf8_encode($xml), true)) {
				$this->parsedxml = sprintf("GBXChallengeFetcher XML error in %s: %s at line %d", $this->uid,
				                           xml_error_string(xml_get_error_code($xml_parser)),
				                           xml_get_current_line_number($xml_parser));
				xml_parser_free($xml_parser);
				return false;
			}
			xml_parser_free($xml_parser);

			// convert track name to readable format
			$this->parsedxml['IDENT']['NAME'] = urldecode($this->parsedxml['IDENT']['NAME']);

			// extract a few specific attributes that aren't in the Header block
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
			if (isset($this->parsedxml['HEADER']['LIGHTMAP']))
				$this->lightmap = (int)$this->parsedxml['HEADER']['LIGHTMAP'];
			else
				$this->lightmap = 0;
			if (isset($this->parsedxml['IDENT']['AUTHORZONE']))
				$this->azone = $this->parsedxml['IDENT']['AUTHORZONE'];
			else
				$this->azone = '';
			if ($this->envir == 'XML' && isset($this->parsedxml['DESC']['ENVIR']))
				$this->envir = $this->parsedxml['DESC']['ENVIR'];
			if (isset($this->parsedxml['DESC']['NBLAPS']))
				$this->nblaps = $this->parsedxml['DESC']['NBLAPS'];
			else
				$this->nblaps = '';
			if (isset($this->parsedxml['DESC']['MOD']))
				$this->modname = $this->parsedxml['DESC']['MOD'];
			else
				$this->modname = '';

			// extract optional song & mod filenames
			if (!empty($this->parsedxml['DEPS'])) {
				for ($i = 0; $i < count($this->parsedxml['DEPS']); $i++) {
					if (preg_match('/ChallengeMusics\\\\(.+)/', $this->parsedxml['DEPS'][$i]['FILE'], $path)) {
						$this->songfile = $path[1];
						if (isset($this->parsedxml['DEPS'][$i]['URL']))
							$this->songurl = $this->parsedxml['DEPS'][$i]['URL'];
						else
							$this->songurl  = '';
					} elseif (preg_match('/.+\\\\Mod\\\\.+/i', $this->parsedxml['DEPS'][$i]['FILE'], $path)) {
						$this->modfile = $path[0];
						if (isset($this->parsedxml['DEPS'][$i]['URL']))
							$this->modurl = $this->parsedxml['DEPS'][$i]['URL'];
						else
							$this->modurl  = '';
					}
				}
			}
		}
	}  // getData
}  // class GBXChallengeFetcher

class GBXReplayFetcher {

	public $filename, $parsexml,
	       $uid, $version, $strtype, $author, $envir, $nickname, $login, $replay,
	       $xml, $parsedxml, $xmlver, $exever, $exebld, $respawns, $stuntscore,
	       $validable, $cpscur, $cpslap;

	/**
	 * Fetches a hell of a lot of data about a GBX replay
	 *
	 * @param String $filename
	 *        The replay filename (must include full path)
	 * @param Boolean $parsexml
	 *        If true, the script also parses the XML block
	 * @return GBXReplayFetcher
	 *        If $uid is empty, GBX data couldn't be extracted
	 */
	public function GBXReplayFetcher($filename, $parsexml) {

		$this->filename = $filename;
		$this->parsexml = $parsexml;
		$this->getData();
	}  // GBXReplayFetcher

	// string read function
	private function ReadGBXString($handle) {

		$data = fread($handle, 4);
		$result = unpack('Vlen', $data);
		$len = $result['len'];
		if ($len <= 0 || $len >= 0x10000) {  // for large XML blocks
			return 'read error';
		}
		$data = fread($handle, $len);
		return $data;
	}  // ReadGBXString

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

		if (!$handle = @fopen($this->filename, 'rb')) {
			return false;
		}

		// check for magic GBX header
		fseek($handle, 0x00, SEEK_SET);
		$data = fread($handle, 5);
		if ($data != 'GBX' . chr(6) . chr(0)) {
			fclose($handle);
			return false;
		}

		fseek($handle, 0x04, SEEK_CUR);  // "BUCR" | "BUCE"
		// get GBX type & check for Replay
		$data = fread($handle, 4);
		$r = unpack('Ngbxtype', $data);
		$t = sprintf('%08X', $r['gbxtype']);
		if ($t != '00E00724' && $t != '00F00324' && $t != '00300903') {
			fclose($handle);
			return false;
		}

		// get GBX version: 1 = TM, 2 = TMPU/TMO/TMS/TMN/TMU/TMF/TM2C
		fseek($handle, 0x04, SEEK_CUR);  // data block offset
		$data = fread($handle, 4);
		$r = unpack('Vversion', $data);
		$this->version = $r['version'];
		// check for unsupported versions
		if ($this->version < 1 || $this->version > 2) {
			fclose($handle);
			return false;
		}

		// get Index (marker/lengths) table
		for ($i = 1; $i <= $this->version; $i++) {
			$data = fread($handle, 8);
			$r = unpack('Nmark'.$i . '/Vlen'.$i, $data);
			$len[$i] = $r['len'.$i];
		}
		if ($this->version == 2) {  // clear high-bit
			$len[2] &= 0x7FFFFFFF;
		}

		// start of Strings block:
		// 0x1D (TM v1), 0x25 (all v2)
		// check type of Strings block
		$data = fread($handle, 4);
		$r = unpack('Vstrtype', $data);
		$this->strtype = $r['strtype'];

		if ($this->strtype >= 3) {
			fseek($handle, 0x08, SEEK_CUR);  // 03 00 00 00 and 00 00 00 80
			$this->uid = $this->ReadGBXString($handle);
			$data = fread($handle, 4);  // if C0 00 00 00 no env, otherwise 00 00 00 40 and env
			$r = unpack('Venv', $data);
			if ($r['env'] != 12)
				$this->envir = $this->ReadGBXString($handle);
			else
				$this->envir = '';
			fseek($handle, 0x04, SEEK_CUR);  // 00 00 00 [40|80]
			$this->author = $this->ReadGBXString($handle);
			$data = fread($handle, 4);
			$r = unpack('Vreplay', $data);
			$this->replay = $r['replay'];
			$this->nickname = $this->ReadGBXString($handle);

			// check whether to get login (TMU/TMF, exever>="0.1.9.0")
			if ($this->strtype >= 6) {
				$this->login = $this->ReadGBXString($handle);
			}
		}

		// get optional XML block & wrap lines for readability
		if ($this->version >= 2) {
			$this->xml = $this->ReadGBXString($handle);
			$this->xml = str_replace("><", ">\n<", $this->xml);
		}

		fclose($handle);

		// parse XML block too?
		$this->parsedxml = array();
		if ($this->parsexml && $this->xml) {
			// define a dedicated parser to handle the attributes
			$xml_parser = xml_parser_create();
			xml_set_object($xml_parser, $this);
			xml_set_element_handler($xml_parser, 'startTag', 'endTag');
			xml_set_character_data_handler($xml_parser, 'charData');

			if (!xml_parse($xml_parser, utf8_encode($this->xml), true)) {
				$this->parsedxml = sprintf("GBXReplayFetcher XML error in %s: %s at line %d", $this->uid,
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
}  // class GBXReplayFetcher
?>
