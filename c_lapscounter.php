<?php
 
Aseco::registerEvent('onStartup', 'clc_startup');

Aseco::registerEvent('onPlayerConnect', 'clc_playerConnect');
Aseco::registerEvent('onEndMap', 'clc_endMap');
Aseco::registerEvent('onBeginMap', 'clc_beginMap');

Aseco::registerEvent('onBeginRound', 'clc_beginRound');

Aseco::registerEvent('onCheckpoint', 'clc_checkpoint');


global $clc;

function clc_beginRound($aseco){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$clc->lap = 1;
		$clc->showCustomLapCounter($aseco, true);
	}
}

function clc_startup($aseco){
	global $clc;
	$clc = new ClapsCounter();
	$clc->settings = simplexml_load_file('c_lapscounter.xml');
	$clc->lap = 1;
	$clc->gameMode = $clc->settings->gameMode;
}


function clc_playerConnect($aseco, $player){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$clc->showCustomLapCounter($aseco, true, $player->login);
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
		
		 $aseco->client->query('GetNbLaps');
		 $res = $aseco->client->getResponse();
		 $clc->numLaps = $res['CurrentValue'];
		//$clc->numLaps = $map->nblaps;
		if($clc->numLaps == 0){
			$clc->numLaps =1;
			
		}
		
		$clc->showCustomLapCounter($aseco, true);
	}
}

function clc_checkpoint($aseco, $cmd){
	global $clc;
	if($aseco->server->gameinfo->mode == $clc->gameMode){
		$login = $cmd[1];
		$cp = $cmd[4];
		$clc->showCustomLapCounter($aseco, true, $login, $cp);
	}
}



class ClapsCounter{
	public $settings;
	public $numCps;
	public $numLaps;
	public $lap;
	public $gameMode;

	function showCustomLapCounter($aseco, $show, $login=null, $cp=-2){
		if($show){
			$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
			$xml .= '<manialink id="0" version="1">';
			
			//widget_frame
			$xml .= '<frame posn="'.$this->settings->widget_frame->posn->x.' '.$this->settings->widget_frame->posn->y.' '.$this->settings->widget_frame->posn->z.'" ';
			$xml .= ' scale="'.$this->settings->widget_frame->scale.'">';		
			
			//widget-picture
			$xml .= '<quad style="BgRaceScore2" substyle="Laps" sizen="10 10"  halign="center" valign="center" />';
			
			$cp++;

		
			$this->lap = (int)($cp / $this->numCps);
			$this->lap++;
			
			//current lap
			$xml .= '<label posn="0 1 1"  textsize="1"text="$s$o'.$this->lap.'" halign="center" valign="center" />';
			//current lap / num laps
			$xml .= '<label posn="0 -3 1" textsize="1" text="'.$this->lap.'/'.$this->numLaps.'" halign="center" valign="center" />';
			
			
			$xml .= '</frame>';
			$xml .= '</manialink>';

			
			
			$xml2 = '<?xml version="1.0" encoding="UTF-8"?>';
			$xml2 .= '<manialink id="1" version="1">';
			$xml2 .= '<frame posn="152 50 -20">';
			$xml2 .= '<quad sizen="13 12" bgcolor="444F" halign="center" valign="center"/>';
			$xml2 .= '</frame>';
			$xml2 .= '</manialink>';
			
		}
		else{
			$xml = '<manialink id="0" version="1"></manialink>';
			$xml2 = '<manialink id="1" version="1"></manialink>';
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
