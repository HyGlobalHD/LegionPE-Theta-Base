<?php

/*
 * Theta
 *
 * Copyright (C) 2015 PEMapModder and contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PEMapModder
 */

namespace legionpe\theta;

use legionpe\theta\config\Settings;

class Friend{
	const FRIEND_NOT_FRIEND = 0x10;
	const FRIEND_ACQUAINTANCE = 0x20;
	const FRIEND_GOOD_FRIEND = 0x40;
	const FRIEND_BEST_FRIEND = 0x80;
	const FRIEND_ENEMY = 0x08;
	const DIRECTION_SMALL_TO_BIG = 0;
	const DIRECTION_BIG_TO_SMALL = 1;
	const DIRECTION_NO_REQUEST = 2;
	const FLAG_ALL = 0xFF;
	const FLAG_OUT_ONLY = 0x100;
	const FLAG_IN_ONLY = 0x200;
	const RET_REQUEST_ALREADY_SENT = 0;
	const RET_REQUEST_ACCEPTED = 1;
	const RET_SENT_REQUEST = 2;
	const RET_REQUEST_ACCEPTED_AND_RAISE_SENT = 3;
	const RET_REDUCED = 4;
	const RET_IS_CURRENT_STATE = 5;
	const RET_RAISED_REQUEST = 6;
	const RET_REQUEST_REDUCED = 7;
	const RET_REQUEST_CANCELLED = 8;
	const RET_REQUEST_CANCELLED_AND_REDUCED = 9;
	const RET_REQUEST_REJECTED = 10;
	const RET_REQUEST_REJECTED_AND_LOWER_SENT = 11;
	const RET_REQUEST_REJECTED_AND_REDUCED = 12;
	const RET_SAME_UID = 13;
	const RET_OTHER_FULL = 14;
	const RET_ME_FULL = 15;
	const DIRECTION_IN = 0;
	const DIRECTION_OUT = 1;
	const DIRECTION_NIL = 2;
	public $myUid, $friendUid, $type, $requestedType, $requestDirection;
	public $friendName;
	public function __construct($myUid, $friendUid, $type, $requestedType, $requestDirection, $friendName){
		$this->myUid = (int) $myUid;
		$this->friendUid = (int) $friendUid;
		$this->friendName = $friendName;
		$this->type = (int) $type;
		$this->requestedType = (int) $requestedType;
		$this->requestDirection = (int) $requestDirection;
	}
	public function isRequestOut(){
		return ($this->myUid > $this->friendUid) and $this->requestDirection === self::DIRECTION_BIG_TO_SMALL;
	}
	public function getRequestRelativeDirection(){
		if($this->requestDirection === self::DIRECTION_NO_REQUEST){
			return self::DIRECTION_NIL;
		}
		if($this->requestDirection === self::DIRECTION_BIG_TO_SMALL){
			if($this->myUid > $this->friendUid){
				return self::DIRECTION_OUT;
			}
			return self::DIRECTION_IN;
		}
		if($this->myUid < $this->friendUid){
			return self::DIRECTION_OUT;
		}
		return self::DIRECTION_IN;
	}
	public static function countFriends(\mysqli $db, $type, &$fulls, ...$uids){
		$fulls = [];
		$r = $db->query("SELECT uid, (SELECT COUNT(*) FROM friends WHERE (smalluid=users.uid OR largeuid=users.uid) AND type=$type) AS cnt, rank FROM users WHERE " .
			implode(" OR ", array_map(function ($uid){
				return "uid=$uid";
			}, $uids)));
		while(is_array($row = $r->fetch_assoc())){
			$uid = (int) $row["uid"];
			$cnt = (int) $row["cnt"];
			$rank = (int) $row["rank"];
			if(($rank & Settings::RANK_IMPORTANCE_VIP) === Settings::RANK_IMPORTANCE_VIP){
				if($type === self::FRIEND_ACQUAINTANCE or $type === self::FRIEND_GOOD_FRIEND){
					if($cnt >= 12){
						$fulls[$uid] = true;
					}
				}elseif($type === self::FRIEND_BEST_FRIEND){
					if($cnt >= 8){
						$fulls[$uid] = true;
					}
				}
			}elseif(($rank & Settings::RANK_IMPORTANCE_DONATOR) === Settings::RANK_IMPORTANCE_DONATOR){
				if($type === self::FRIEND_ACQUAINTANCE or $type === self::FRIEND_GOOD_FRIEND){
					if($cnt >= 8){
						$fulls[$uid] = true;
					}
				}elseif($type === self::FRIEND_BEST_FRIEND){
					if($cnt >= 5){
						$fulls[$uid] = true;
					}
				}
			}else{
				if($type === self::FRIEND_ACQUAINTANCE or $type === self::FRIEND_GOOD_FRIEND){
					if($cnt >= 2){
						$fulls[$uid] = true;
					}
				}elseif($type === self::FRIEND_BEST_FRIEND){
					if($cnt >= 1){
						$fulls[$uid] = true;
					}
				}
			}
		}
		$r->close();
	}
}
