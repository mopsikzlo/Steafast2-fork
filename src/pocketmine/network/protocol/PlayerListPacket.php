<?php

/*
 _      _          _____    _____    ___  _____
| \    / |  \  /  |        |     |  |       |
|  \  /  |   \/   |_____   |     | _|__     |
|   \/   |   /          |  |     |  |       |
|        |  /     ______|  |_____|  |       |
*/

namespace pocketmine\network\protocol;

#include <rules/DataPacket.h>
use pocketmine\utils\TextFormat;
use pocketmine\Player;


class PlayerListPacket extends PEPacket{
	const NETWORK_ID = Info::PLAYER_LIST_PACKET;
	const PACKET_NAME = "PLAYER_LIST_PACKET";

	const TYPE_ADD = 0;
	const TYPE_REMOVE = 1;

	/**
	 * Each entry is array
	 * 0 - UUID
	 * 1 - Player ID
	 * 2 - Player Name
	 * 3 - Skin ID
	 * 4 - Skin Data
	 * 5 - Cape Data
	 * 6 - Skin Geometry Name
	 * 7 - Skin Geometry Data
	 * 8 - XUID
	 * 9 - DeviceID
	 * 10 - additionalSkinData
	 */
	/** @var array[] */
	public $entries = [];
	public $type;

	public function clean(){
		$this->entries = [];
		return parent::clean();
	}

	public function decode($playerProtocol){

	}

	public function encode($playerProtocol){
		$emptySkin = str_repeat("\x00", 8192);
		$this->reset($playerProtocol);
		$this->putByte($this->type);
		$this->putVarInt(count($this->entries));
		switch ($this->type) {
			case self::TYPE_ADD:
				foreach ($this->entries as $d) {
					$this->putUUID($d[0]);
					$this->putVarInt($d[1]); // Player ID
					$this->putString(isset($d[2]) ? $d[2] : ""); // Player Name
					if ($playerProtocol >= Info::PROTOCOL_120) {
		                if ($playerProtocol < Info::PROTOCOL_370) {
					        if ($playerProtocol >= Info::PROTOCOL_200 && $playerProtocol < Info::PROTOCOL_290) {
						    	$this->putString(""); // third party name
						    	$this->putSignedVarInt(0); // platform id
					    	}
				        	$this->putString($d[3]); // Skin ID
					    	if ($playerProtocol >= Info::PROTOCOL_200 && $playerProtocol < Info::PROTOCOL_220) {
						    	$this->putLInt(1); // num skins, always 1
						    }
				        	$skinData = !empty($d[4]) ? $d[4] : $emptySkin;
					    	if ($playerProtocol >= Info::PROTOCOL_360) {
						    	if (empty($d[7]) && strlen($skinData) == 8192) {
						    		$skinData = $this->duplicateArmAndLeg($skinData);
						    	}
					    	}
					    	$this->putString($skinData); // Skin Data
					    	$capeData = isset($d[5]) ? $d[5] : '';
					    	if ($playerProtocol >= Info::PROTOCOL_200 && $playerProtocol < Info::PROTOCOL_220) {
						    	if (!empty($capeData)) {
						    		$this->putLInt(1); // isNotEmpty
							    	$this->putString($capeData); // Cape Data
						    	} else {
							    	$this->putLInt(0); // isEmpty
						    	}
						    } else {
						    	$this->putString($capeData); // Cape Data
					    	}
					     	if ($playerProtocol >= Info::PROTOCOL_310) { //temp hack for prevent client bug
						    	if (isset($d[6])) {
						    		$d[6] = strtolower($d[6]);
						    	}
						    	if (isset($d[7])) {
							    	$tempData = json_decode($d[7], true);
							    	if (is_array($tempData)) {
								    	foreach ($tempData as $key => $value) {
									    	unset($tempData[$key]);
									    	$tempData[strtolower($key)] = $value;
								    	}
								    	$d[7] = json_encode($tempData);
							    	}
							    }
							}
					    	$this->putString(isset($d[6]) ? $d[6] : ''); // Skin Geometry Name
					    	$this->putString(isset($d[7]) ? $this->prepareGeometryDataForOld($d[7]) : ''); // Skin Geometry Data
						}
						$this->putString(isset($d[8]) ? $d[8] : ''); // XUID
						if ($playerProtocol >= Info::PROTOCOL_200) {
							$this->putString(""); // platform chat id
						}
				     	if ($playerProtocol >= Info::PROTOCOL_370) {
					    	if ($playerProtocol >= Info::PROTOCOL_385) {
						    	$this->putLInt(isset($d[9]) ? $d[9] : Player::OS_UNKNOWN);
					    	}
					        $skinData = !empty($d[4]) ? $d[4] : $emptySkin;
					        $skinGeometryName = isset($d[6]) ? $d[6] : '';
					        $skinGeometryData = isset($d[7]) ? $d[7] : '';
					        $capeData = isset($d[5]) ? $d[5] : '';
					        $this->putSerializedSkin($playerProtocol, $d[3], $skinData, $skinGeometryName, $skinGeometryData, $capeData, (isset($d[10]) ? $d[10] : []));
						    if ($playerProtocol >= Info::PROTOCOL_385) {
						    	$this->putByte(0); // is teacher
						    	$this->putByte(0); // is host
					    	}
				    	}
				    } else {
				        $this->putString("Standard_Custom");
				        $skinData = !empty($d[4]) ? $d[4] : $emptySkin; // Skin Data
				        $this->putString($skinData);
				    }
				}
				if ($playerProtocol >= Info::PROTOCOL_406) {
					$this->putByte(1); // is trusted skin                  
				}
				break;
			case self::TYPE_REMOVE:
				foreach ($this->entries as $d) {
					$this->putUUID($d[0]);
				}
				break;
		} 
			
	}
	
	private function duplicateArmAndLeg($skinData) {
		static $parts = [
			["baseXOffset" => 4, "baseZOffset" => 16, "targetXOffset" => 20, "targetZOffset" => 48, "width" => 4, "height" => 4, "isRevers" => true],
			["baseXOffset" => 8, "baseZOffset" => 16, "targetXOffset" => 24, "targetZOffset" => 48, "width" => 4, "height" => 4, "isRevers" => true],
			["baseXOffset" => 0, "baseZOffset" => 20, "targetXOffset" => 24, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true],
			["baseXOffset" => 4, "baseZOffset" => 20, "targetXOffset" => 20, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true],
			["baseXOffset" => 8, "baseZOffset" => 20, "targetXOffset" => 16, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true],
			["baseXOffset" => 12, "baseZOffset" => 20, "targetXOffset" => 28, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true],
			["baseXOffset" => 44, "baseZOffset" => 16, "targetXOffset" => 36, "targetZOffset" => 48, "width" => 4, "height" => 4, "isRevers" => true],
			["baseXOffset" => 48, "baseZOffset" => 16, "targetXOffset" => 40, "targetZOffset" => 48, "width" => 4, "height" => 4, "isRevers" => true],
			["baseXOffset" => 40, "baseZOffset" => 20, "targetXOffset" => 40, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true],
			["baseXOffset" => 44, "baseZOffset" => 20, "targetXOffset" => 36, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true],
			["baseXOffset" => 48, "baseZOffset" => 20, "targetXOffset" => 32, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true],
			["baseXOffset" => 52, "baseZOffset" => 20, "targetXOffset" => 44, "targetZOffset" => 52, "width" => 4, "height" => 12, "isRevers" => true]
		];
		$skinData .= str_repeat("\x00", 8192);
		foreach ($parts as $part) {
			for ($z = 0; $z < $part["height"]; $z++) {
				$baseZOffset = ($part["baseZOffset"] + $z) * 64 * 4;
				$targetZOffset = ($part["targetZOffset"] + $z) * 64 * 4;
				for ($x = 0; $x < $part["width"]; $x++) {
					$baseOffset = $baseZOffset + ($part["baseXOffset"] + $x) * 4;
					if ($part["isRevers"]) {
						$targetOffset = $targetZOffset + ($part["targetXOffset"] + ($part["width"] - $x - 1)) * 4;
					} else {
						$targetOffset = $targetZOffset + ($part["targetXOffset"] + $x) * 4;
					}
					$skinData[$targetOffset] = $skinData[$baseOffset];
					$skinData[$targetOffset + 1] = $skinData[$baseOffset + 1];
					$skinData[$targetOffset + 2] = $skinData[$baseOffset + 2];
					$skinData[$targetOffset + 3] = $skinData[$baseOffset + 3];
				}
			}
		}
		return $skinData;
	}

}
