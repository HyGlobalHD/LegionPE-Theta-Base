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

namespace legionpe\theta\query;

use legionpe\theta\config\Settings;
use legionpe\theta\credentials\Credentials;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Utils;

function autoload($class){
	require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/" . str_replace("\\", "/", $class) . ".php";
}

abstract class AsyncQuery extends AsyncTask{
	public static $QUERY_COUNT = 0;
	const KEY_MYSQL = "legionpe.theta.query.mysql";
	const TYPE_RAW = 0;
	const TYPE_ASSOC = 1;
	const TYPE_ALL = 2;
	const COL_STRING = 0;
	const COL_INT = 1;
	const COL_UNIXTIME = 1;
	const COL_FLOAT = 2;
	private static $defaultValues = [
		self::COL_STRING => "",
		self::COL_INT => 0,
		self::COL_FLOAT => 0.0,
	];
	public function __construct(Plugin $plugin){
		if($this->getResultType() !== self::TYPE_RAW and $this->getExpectedColumns() === null){
			echo "Fatal: Plugin error. ", static::class . " must override getExpectedColumns(), but it didn't. Committing suicide.";
			sleep(604800);
			die;
		}
		$plugin->getServer()->getScheduler()->scheduleAsyncTask($this);
	}
	public abstract function getResultType();
	public function getExpectedColumns(){
		return null;
	}
	public function onRun(){
		$mysql = $this->getConn();
		try{
			$this->onPreQuery($mysql);
		}catch(\Exception $e){
			$this->setResult(["success" => false, "query" => null, "error" => $e->getMessage()]);
			return;
		}
		$result = $mysql->query($query = $this->getQuery());
		self::$QUERY_COUNT++;
		if(Settings::$SYSTEM_IS_TEST and $this->reportDebug()){
			echo "Executing query: $query", PHP_EOL;
		}
		$this->onPostQuery($mysql);
		if($result === false){
			$this->setResult(["success" => false, "query" => $query, "error" => $mysql->error]);
			if($this->reportError()){
				echo "Error executing query (" . get_class($this) . "): $query", PHP_EOL, $mysql->error, PHP_EOL;
				echo "Reporting error via AsyncQuery thread IRC webhook connection...", PHP_EOL;
				Utils::getURL(Credentials::IRC_WEBHOOK . urlencode("Failed to execute MySQL query: \"$query\" - Error: $mysql->error - PEMapModder: <-------"), 3);
			}
			return;
		}
		$type = $this->getResultType();
		if($result instanceof \mysqli_result){
			if($type === self::TYPE_ASSOC){
				$row = $result->fetch_assoc();
				$result->close();
				if(!is_array($row)){
					$this->setResult(["success" => true, "query" => $query, "result" => null, "resulttype" => self::TYPE_RAW]);
					return;
				}
				$this->processRow($row);
				$this->onAssocFetched($mysql, $row);
				$this->setResult(["success" => true, "query" => $query, "result" => $row, "resulttype" => self::TYPE_ASSOC]);
			}elseif($type === self::TYPE_ALL){
				$set = [];
				while(is_array($row = $result->fetch_assoc())){
					$this->processRow($row);
					$set[] = $row;
				}
				$result->close();
				$this->setResult(["success" => true, "query" => $query, "result" => $set, "resulttype" => self::TYPE_ALL]);
			}
			return;
		}
		$this->setResult(["success" => true, "query" => $query, "resulttype" => self::TYPE_RAW]);
	}
	/**
	 * @return \mysqli
	 */
	public function getConn(){
		$mysql = $this->getFromThreadStore(self::KEY_MYSQL);
		if(!($mysql instanceof \mysqli)){
			$mysql = Credentials::getMysql();
			$this->saveToThreadStore(self::KEY_MYSQL, $mysql);
		}
		return $mysql;
	}
	protected function onPreQuery(\mysqli $mysqli){
	}
	public abstract function getQuery();
	protected function reportDebug(){
		return true;
	}
	protected function onPostQuery(\mysqli $mysqli){
	}
	protected function reportError(){
		return true;
	}
	private function processRow(&$r){
		if(!is_array($r)){
			return;
		}
		foreach($this->getExpectedColumns() as $column => $col){
			if(!isset($r[$column])){
				$r[$column] = self::$defaultValues[$col];
			}elseif($col === self::COL_INT){
				$r[$column] = (int) $r[$column];
			}elseif($col === self::COL_FLOAT){
				$r[$column] = (float) $r[$column];
			}
		}
	}
	protected function onAssocFetched(\mysqli $mysql, array &$row){
	}
	/**
	 * @param string|mixed $str
	 * @param bool $bin
	 * @return string
	 */
	public function esc($str, $bin = false){
		if(is_string($str)){
			if($bin){
				return "X'" . bin2hex($str) . "'";
			}
			return "'" . $this->getConn()->escape_string($str) . "'";
		}
		return (string) $str;
	}
	public function __debugInfo(){
		return [];
	}
	public function quit(){
	}
}
