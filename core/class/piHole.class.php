<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes**********************************/
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class piHole extends eqLogic {
	/***************************Attributs*******************************/	
	public static function cron($_eqlogic_id = null) {
		$eqLogics = ($_eqlogic_id !== null) ? array(eqLogic::byId($_eqlogic_id)) : eqLogic::byType('piHole', true);
		foreach ($eqLogics as $piHole) {
			try {
				$piHole->getpiHoleInfo();
			} catch (Exception $e) {

			}
		}
	}
	
	public static function getStructure ($name) {
	
		switch($name) {
			case "summaryRaw" :
				return ["domains_being_blocked"=>"Domaines bloqués",
						"dns_queries_today"=>"Requêtes aujourd'hui",
						"ads_blocked_today"=>"Publicités bloquées aujourd'hui",
						"ads_percentage_today"=>"Pourcentage publicités bloquées aujourd'hui",
						"unique_domains"=>"Domaines uniques",
						"queries_forwarded"=>"Requêtes transmises",
						"queries_cached"=>"Requêtes en cache",
						"clients_ever_seen"=>"Clients vus",
						"unique_clients"=>"Clients uniques"
					];
			break;
		}		
	}
	
	public function getpiHoleInfo($data) {
		try {
			if(!$data) {
				$ip = $this->getConfiguration('ip','');
				$apikey = $this->getConfiguration('apikey','');
				$urlprinter = 'http://' . $ip . '/admin/api.php?status&summaryRaw&auth='.$apikey;
				$request_http = new com_http($urlprinter);
				$piHoleinfo=$request_http->exec();
			} else {
				$piHoleinfo=$data;
			}

			log::add('piHole','debug','recu:'.$piHoleinfo);
			$jsonpiHole = json_decode($piHoleinfo,true);

			$piHoleCmd = $this->getCmd(null, 'status');
			$this->checkAndUpdateCmd($piHoleCmd, (($jsonpiHole['status']=='enabled')?1:0));
			
			if($data) {
				$ip = $this->getConfiguration('ip','');
				$apikey = $this->getConfiguration('apikey','');
				$urlprinter = 'http://' . $ip . '/admin/api.php?summaryRaw&auth='.$apikey;
				$request_http = new com_http($urlprinter);
				$piHoleinfo=$request_http->exec();
				log::add('piHole','debug','recu:'.$piHoleinfo);
				$jsonpiHole = json_decode($piHoleinfo,true);
			}
			
			$summaryRaw = piHole::getStructure('summaryRaw');
			foreach($summaryRaw as $id => $trad) {
				$piHoleCmd = $this->getCmd(null, $id);
				$this->checkAndUpdateCmd($piHoleCmd, $jsonpiHole[$id]);
			}
			
		} catch (Exception $e) {
			$piHoleCmd = $this->getCmd(null, 'status');
			if (is_object($piHoleCmd)) {
				$this->checkAndUpdateCmd($piHoleCmd, 'Erreur communication');
			}
		}
	} 
	
	public function getImage(){
		return 'plugins/piHole/plugin_info/piHole_icon.png';
	}
	
	public function postSave() {
		$order=1;
		$status = $this->getCmd(null, 'status');
		if (!is_object($status)) {
			$status = new piHolecmd();
			$status->setLogicalId('status');
			$status->setIsVisible(1);
			$status->setOrder($order);
			$status->setName(__('Statut', __FILE__));
		}
		$status->setType('info');
		$status->setSubType('binary');
		$status->setEqLogic_id($this->getId());
		$status->setDisplay('generic_type', 'SWITCH_STATE');
		$status->save();
		
		$order++;
		$enable = $this->getCmd(null, 'enable');
		if (!is_object($enable)) {
			$enable = new piHolecmd();
			$enable->setLogicalId('enable');
			$enable->setDisplay('icon','<i class="fa fa-play"></i>');
			$enable->setIsVisible(1);
			$enable->setOrder($order);
			$enable->setName(__('Activer le filtrage', __FILE__));
		}
		$enable->setType('action');
		$enable->setSubType('other');
		$enable->setEqLogic_id($this->getId());
		$enable->setValue($status->getId());
		$enable->setDisplay('generic_type', 'SWITCH_ON');
		$enable->save();
		
		$order++;
		$disable = $this->getCmd(null, 'disable');
		if (!is_object($disable)) {
			$disable = new piHolecmd();
			$disable->setLogicalId('disable');
			$disable->setDisplay('icon','<i class="fa fa-stop"></i>');
			$disable->setIsVisible(1);
			$disable->setOrder($order);
			$disable->setName(__('Désactiver le filtrage', __FILE__));
		}
		$disable->setType('action');
		$disable->setSubType('other');
		$disable->setEqLogic_id($this->getId());
		$disable->setValue($status->getId());
		$disable->setDisplay('generic_type', 'SWITCH_OFF');
		$disable->save();
		
		$order++;
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new piHolecmd();
			$refresh->setLogicalId('refresh');
			$refresh->setIsVisible(1);
			$refresh->setOrder($order);
			$refresh->setName(__('Rafraîchir', __FILE__));
		}
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->setEqLogic_id($this->getId());
		$refresh->save();

		$summaryRaw = piHole::getStructure('summaryRaw');
		
		foreach($summaryRaw as $id => $trad) {
			$order++;
			$newCommand = $this->getCmd(null, $id);
			if (!is_object($newCommand)) {
				$newCommand = new piHolecmd();
				$newCommand->setLogicalId($id);
				$newCommand->setIsVisible(0);
				$newCommand->setOrder($order);
				$newCommand->setName(__($trad, __FILE__));
			}
			$newCommand->setTemplate('dashboard', 'line');
			$newCommand->setTemplate('mobile', 'line');
			$newCommand->setType('info');
			$newCommand->setSubType('numeric');
			$newCommand->setEqLogic_id($this->getId());
			$newCommand->setDisplay('generic_type', 'GENERIC_INFO');
			if(strpos($id,'percentage') !== false) $newCommand->setUnite( '%' );
			$newCommand->save();		
		
		}
		
		$this->getpiHoleInfo();
	}
}

class piHoleCmd extends cmd {
	/***************************Attributs*******************************/


	/*************************Methode static****************************/

	/***********************Methode d'instance**************************/

	public function execute($_options = null) {
		if ($this->getType() == '') {
			return '';
		}
		$eqLogic = $this->getEqlogic();
		$ip = $eqLogic->getConfiguration('ip','');
		$apikey = $eqLogic->getConfiguration('apikey','');
		$logical = $this->getLogicalId();
		$result=null;
		if ($logical != 'refresh'){
			$urlpiHole = 'http://' . $ip . '/admin/api.php?status&summaryRaw';
			$request_http = new com_http($urlpiHole);
			switch ($logical) {
				case 'disable':
					$urlpiHole = 'http://' . $ip . '/admin/api.php?disable&auth='.$apikey;
					$request_http = new com_http($urlpiHole);
				break;
				case 'enable':
					$urlpiHole = 'http://' . $ip . '/admin/api.php?enable&auth='.$apikey;
					$request_http = new com_http($urlpiHole);
				break;
			}
			$result=$request_http->exec();
			log::add('piHole','debug',$result);
		}
		$eqLogic->getpiHoleInfo($result);
	}

	/************************Getteur Setteur****************************/
}
?>
