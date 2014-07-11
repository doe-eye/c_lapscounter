<?php
/*****************************************************************************************
plugin.c_lapscounter.php
shows a custom lapscounter adjustable in size and position

@version v2.5
@author aca


******************************************************************************************/ 
Aseco::registerEvent('onStartup', 'clc_startup');

Aseco::registerEvent('onPlayerConnect', 'clc_playerConnect');
Aseco::registerEvent('onPlayerDisconnect', 'clc_playerDisconnect');
Aseco::registerEvent('onPlayerInfoChanged', 'clc_playerInfoChanged');

Aseco::registerEvent('onEndMap', 'clc_endMap');
Aseco::registerEvent('onBeginMap', 'clc_beginMap');

Aseco::registerEvent('onCheckpoint', 'clc_checkpoint');


global $clc;

function clc_playerInfoChanged($aseco, $changes){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$login = $changes['Login'];
		$spectatorStatus = $changes['SpectatorStatus'];
		$isSpec = $spectatorStatus % 10000;
		
		//if status changed to spectator
		if($isSpec){
			$spectatorLogin = $changes['Login'];
			$spectatorID = $changes['PlayerId'];
			$spectatedID = (int) ($spectatorStatus / 10000);
			
			//is a player spectated
			if($spectatedID > 0 && $spectatedID < 255){
				//fetch login of spectatedID
				$aseco->client->query('GetPlayerList',254,0);//max number of infos, starting-index
				$playerList = $aseco->client->getResponse();
				$spectatedLogin = '';
				foreach($playerList as $player){
					$pID = $player['PlayerId'];
					if($pID == $spectatedID){
						$spectatedLogin = $player['Login'];
						break;
					}
				}			
				$clc->specArray[$spectatorLogin] = $spectatedLogin;
				
				//show instantly clapscounter of spectated
				$clc->showCustomLapCounter($aseco, true, $spectatorLogin, $clc->cpArray[$spectatedLogin]);
			}
			else{//is free-spec
				unset($clc->specArray[$spectatorLogin]);
				$clc->showCustomLapCounter($aseco, false, $spectatorLogin);

			}
		}
		//if status changed from spectator to player
		elseif (isset($clc->specArray[$login])){
			unset($clc->specArray[$login]);
		}
	}

	
}

function clc_startup($aseco){
	global $clc;
	$clc = new ClapsCounter();
}


function clc_playerConnect($aseco, $player){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$clc->cpArray[$player->login] = -2;
		$clc->showCustomLapCounter($aseco, true, $player->login);
	}
}

function clc_playerDisconnect($aseco, $player){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		unset($clc->cpArray[$player->login]);
		unset($clc->specArray[$player->login]);
	}
}

function clc_endMap($aseco, $race){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$clc->showCustomLapCounter($aseco, false);
	}
}

function clc_beginMap($aseco, $map){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$clc->numCps = $map->nbchecks;
		
		if($clc->laps_of_matchsettings){
			$aseco->client->query('GetNbLaps');
			$res = $aseco->client->getResponse();
			$clc->numLaps = $res['CurrentValue'];
		}
		else{
			$clc->numLaps = $map->nblaps;
		}
		if($clc->numLaps == 0){
			$clc->numLaps =1;
			
		}
		$clc->lap = 1;
		
		//reset cps in cpArray
		foreach($clc->cpArray as $key => $cp){
			$clc->cpArray[$key] = -2;
		}
		
		
		$clc->showCustomLapCounter($aseco, true);
	}
}

function clc_checkpoint($aseco, $cmd){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$login = $cmd[1];
		$cp = $cmd[4];
		$clc->cpArray[$login] = $cp+1;
		
		$clc->showCustomLapCounter($aseco, true, $login, $cp);
		
		//also show to spectators
		foreach ($clc->specArray as $spectator => $spectated){
			if($spectated == $login){
				$clc->showCustomLapCounter($aseco, true, $spectator, $cp);
			}
		}
	}
}



class ClapsCounter{
	public $specArray;//['spectatorLogin'] = spectatedLogin
	public $cpArray;//['login'] = latestcp
	public $settings;
	public $numCps;
	public $numLaps;
	public $lap;
	public $gameMode;
	public $laps_of_matchsettings;
	
	function ClapsCounter(){
		$this->specArray = array();
		$this->cpArray = array();
		
		$this->settings = simplexml_load_file('c_lapscounter.xml');
		$this->lap = 1;
		$this->gameMode = $this->settings->gameMode;
		$this->laps_of_matchsettings = $this->settings->laps_of_matchsettings;
	}
	
	function showCustomLapCounter($aseco, $show, $login=null, $cp=-2){
		if($show){
			$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
			$xml .= '<manialink id="23017810" version="1">';
			
			//widget_frame
			$xml .= '<frame posn="'.$this->settings->widget_frame->posn->x.' '.$this->settings->widget_frame->posn->y.' '.$this->settings->widget_frame->posn->z.'" ';
			$xml .= ' scale="'.$this->settings->widget_frame->scale.'">';		
			
			//widget-picture
			$xml .= '<quad style="BgRaceScore2" substyle="Laps" sizen="10 10"  halign="center" valign="center" />';
			
			$cp++;

			//work-around for division by zero-bug
			if($this->numCps == 0){
				$this->numCps = -3;
			}
			
			$this->lap = (int)($cp / $this->numCps);
			$this->lap++;
			
			//current lap
			$xml .= '<label posn="0 1 1"  textsize="1"text="$s$o'.$this->lap.'" halign="center" valign="center" />';
			//current lap / num laps
			$xml .= '<label posn="0 -3 1" textsize="1" text="'.$this->lap.'/'.$this->numLaps.'" halign="center" valign="center" />';
			
			
			$xml .= '</frame>';
			$xml .= '</manialink>';

			
			//quad covering the original lapscounter
			$xml2 = '<?xml version="1.0" encoding="UTF-8"?>';
			$xml2 .= '<manialink id="23017811" version="1">';
			$xml2 .= '<frame posn="152 50 -20">';
			$xml2 .= '<quad sizen="13 12" bgcolor="444F" halign="center" valign="center"/>';
			$xml2 .= '</frame>';
			$xml2 .= '</manialink>';
			
		}
		else{
			$xml = '<manialink id="23017810" version="1"></manialink>';
			$xml2 = '<manialink id="23017811" version="1"></manialink>';
		}


		if($login){
			$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, 0, false);
			$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml2, 0, false);
		}
		else{
			$aseco->client->query('SendDisplayManialinkPage', $xml, 0, false);
			$aseco->client->query('SendDisplayManialinkPage', $xml2, 0, false);
			
		}

	}
}
?>
