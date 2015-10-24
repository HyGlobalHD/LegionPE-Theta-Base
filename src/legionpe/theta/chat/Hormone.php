<?php

/*
 * LegionPE Theta
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

namespace legionpe\theta\chat;

use legionpe\theta\BasePlugin;
use legionpe\theta\query\PushChatQuery;
use legionpe\theta\query\RawAsyncQuery;

abstract class Hormone{
	const SERVER_BROADCAST = 0;
	const TEAM_CHAT = 1;
	const CONSOLE_MESSAGE = 2;
	const CHANNEL_CHAT = 3;
	const MUTE_CHAT = 4;
	/** @deprecated */
	const PRIVATE_MESSAGE = 5;
	const TEAM_JOIN_PROPAGANDA = 6;
	const RELOAD_FRIENDS_PROPAGANDA = 7;
	const CLASS_CHAT = 8;
	const TEAM_DISBAND_PROPAGANDA = 9;
	const TEAM_KICK_PROPAGANDA = 10;
	const STOP_SERVER_HORMONE = 11;
	const TEAM_RANK_CHANGE_HORMONE = 12;
	/** @var BasePlugin */
	protected $src;
	protected $msg;
	protected $class;
	protected $rowId;
	protected $_classData;
	protected function __construct(BasePlugin $main, $src, $msg, $class, $data, $rowId = null){
		$this->main = $main;
		$this->src = $src;
		$this->msg = $msg;
		$this->class = $class;
		$this->rowId = $rowId;
		$this->_classData = $data;
		foreach($data as $key => $value){
			if(!isset($this->{$key})){
				$this->{$key} = $value;
			}
		}
	}
	/**
	 * @param BasePlugin $main
	 * @param int $id
	 * @param string $src
	 * @param string $msg
	 * @param int $class
	 * @param array $data
	 * @param int|null $rowId
	 * @return Hormone
	 */
	public static function get(BasePlugin $main, $id, $src, $msg, $class, $data, $rowId = null){
		switch($id){
			case self::SERVER_BROADCAST:
				return new ServerBroadcastHormone($main, $src, $msg, $class, $data, $rowId);
			case self::TEAM_CHAT:
				return new TeamMessageHormone($main, $src, $msg, $class, $data, $rowId);
			case self::CONSOLE_MESSAGE:
				return new ConsoleReportHormone($main, $src, $msg, $class, $data, $rowId);
			case self::CHANNEL_CHAT:
				return new ChannelChatHormone($main, $src, $msg, $class, $data, $rowId);
			case self::MUTE_CHAT:
				return new MuteHormone($main, $src, $msg, $class, $data, $rowId);
			/** @noinspection PhpDeprecationInspection */
			case self::PRIVATE_MESSAGE:
				/** @noinspection PhpDeprecationInspection */
				return new PrivateMessageHormone($main, $src, $msg, $class, $data, $rowId);
			case self::TEAM_JOIN_PROPAGANDA:
				return new TeamJoinHormone($main, $src, $msg, $class, $data, $rowId);
			case self::RELOAD_FRIENDS_PROPAGANDA:
				return new ReloadFriendsHormone($main, $src, $msg, $class, $data, $rowId);
			case self::CLASS_CHAT:
				return new ClassChatHormone($main, $src, $msg, $class, $data, $rowId);
			case self::TEAM_DISBAND_PROPAGANDA:
				return new TeamDisbandHormone($main, $src, $msg, $class, $data, $rowId);
			case self::TEAM_KICK_PROPAGANDA:
				return new TeamKickHormone($main, $src, $msg, $class, $data, $rowId);
			case self::STOP_SERVER_HORMONE:
				return new StopServerHormone($main, $src, $msg, $class, $data, $rowId);
			case self::TEAM_RANK_CHANGE_HORMONE:
				return new TeamRankChangeHormone($main, $src, $msg, $class, $data, $rowId);
		}
		return null;
	}
	public function release(){
		$this->onRelease();
		new PushChatQuery($this->main, $this->src, $this->msg, $this->getType(), $this->class, $this->_classData, $this);
	}
	public function consume(){
		if(is_int($this->rowId)){
			new RawAsyncQuery($this->main, "DELETE FROM chat WHERE id=$this->rowId");
			return true;
		}
		return false;
	}
	protected function onRelease(){
	}
	public abstract function getType();
	public abstract function execute();
	public function onPostRelease($rowId){
	}
}
