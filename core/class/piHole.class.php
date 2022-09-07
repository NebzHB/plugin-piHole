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
			$autorefresh = $piHole->getConfiguration('autorefresh','*/5 * * * *');
			if ($autorefresh != '') {
				try {
					$c = new Cron\CronExpression(checkAndFixCron($autorefresh), new Cron\FieldFactory);
					if ($c->isDue()) {
						$piHole->getpiHoleInfo();
					}
				} catch (Exception $exc) {
					log::add('piHole', 'error', __('Expression cron non valide pour ', __FILE__) . $piHole->getHumanName() . ' : ' . $autorefresh);
				}
			}
		}
	}	
	
	public static function getStructure ($name) {
	
		switch($name) {
			case "summaryRaw" :
				return ["domains_being_blocked"=>__("Domaines bloqués", __FILE__),
						"dns_queries_today"=>__("Requêtes aujourd'hui", __FILE__),
						"ads_blocked_today"=>__("Publicités bloquées aujourd'hui", __FILE__),
						"ads_percentage_today"=>__("Pourcentage publicités bloquées aujourd'hui", __FILE__),
						"unique_domains"=>__("Domaines uniques", __FILE__),
						"queries_forwarded"=>__("Requêtes transmises", __FILE__),
						"queries_cached"=>__("Requêtes en cache", __FILE__),
						"clients_ever_seen"=>__("Clients vus", __FILE__),
						"unique_clients"=>__("Clients uniques", __FILE__)
					];
			break;
		}		
	}
	
	public function getpiHoleInfo($data=null,$order=null) {
		try {
			$proto = $this->getConfiguration('proto','http');
			$ip = $this->getConfiguration('ip','');
			$apikey = $this->getConfiguration('apikey','');
				
			if(!$data) {
				$urlprinter = $proto.'://' . $ip . '/admin/api.php?status&summaryRaw&auth='.$apikey;
				$request_http = new com_http($urlprinter);
				$request_http->setNoSslCheck(true);
				$piHoleinfo=$request_http->exec(60,1);
			} else {
				$piHoleinfo=$data;
			}

			log::add('piHole','debug',__('recu:', __FILE__).$piHoleinfo);
			$jsonpiHole = json_decode($piHoleinfo,true);

			$piHoleCmd = $this->getCmd(null, 'status');
			$this->checkAndUpdateCmd($piHoleCmd, (($jsonpiHole['status']=='enabled')?1:0));
			
			if($data) {
				$urlprinter = $proto.'://' . $ip . '/admin/api.php?summaryRaw&auth='.$apikey;
				$request_http = new com_http($urlprinter);
				$request_http->setNoSslCheck(true);
				$piHoleinfo=$request_http->exec(60,1);
				log::add('piHole','debug',__('recu:', __FILE__).$piHoleinfo);
				$jsonpiHole = json_decode($piHoleinfo,true);
			}
			
			$summaryRaw = piHole::getStructure('summaryRaw');
			foreach($summaryRaw as $id => $trad) {
				$piHoleCmd = $this->getCmd(null, $id);
				if(strpos($id,'percentage') !== false) $jsonpiHole[$id]=round($jsonpiHole[$id],2);
				$this->checkAndUpdateCmd($piHoleCmd, $jsonpiHole[$id]);
			}
			
			if(isset($jsonpiHole['gravity_last_updated'])) { //v4
				$nextOrder = $order || 29;
				$gravity_last_updated = $this->getCmd(null, 'gravity_last_updated');
				if (!is_object($gravity_last_updated)) { // create if not exists
					$nextOrder++;
					$gravity_last_updated = new piHolecmd();
					$gravity_last_updated->setLogicalId('gravity_last_updated');
					$gravity_last_updated->setIsVisible(0);
					$gravity_last_updated->setOrder($nextOrder);
					$gravity_last_updated->setName(__('Dernière mise à jour', __FILE__));
				}
				$gravity_last_updated->setType('info');
				$gravity_last_updated->setSubType('string');
				$gravity_last_updated->setEqLogic_id($this->getId());
				$gravity_last_updated->setDisplay('generic_type', 'GENERIC_INFO');
				$gravity_last_updated->save();
				
				$time=$jsonpiHole['gravity_last_updated']['absolute'];
				$date= new DateTime("@$time");
				$absolute = $date->format('d-m-Y H:i:s');
				
				$this->checkAndUpdateCmd($gravity_last_updated, $absolute);
			}
			
			$urlprinter = $proto.'://' . $ip . '/admin/api.php?versions';
			$request_http = new com_http($urlprinter);
			$request_http->setNoSslCheck(true);
			$piHoleVer=$request_http->exec(60,1);
			log::add('piHole','debug',__('recu version:', __FILE__).$piHoleVer);
			if($piHoleVer) {
				$jsonpiHoleVer = json_decode($piHoleVer,true);
				$piHoleCmd = $this->getCmd(null, 'hasUpdatePiHole');
				$this->checkAndUpdateCmd($piHoleCmd, (($jsonpiHoleVer['core_update']===true)?1:0));
				$piHoleCmd = $this->getCmd(null, 'hasUpdateWebInterface');
				$this->checkAndUpdateCmd($piHoleCmd, (($jsonpiHoleVer['web_update']===true)?1:0));
				$piHoleCmd = $this->getCmd(null, 'hasUpdateFTL');
				$this->checkAndUpdateCmd($piHoleCmd, (($jsonpiHoleVer['FTL_update']===true)?1:0));
			}
			
			$online = $this->getCmd(null, 'online');
			if (is_object($online)) {
				$this->checkAndUpdateCmd($online, '1');
			}
		} catch (Exception $e) {
			if($e->getCode() == "404") {
				$online = $this->getCmd(null, 'online');
				if (is_object($online)) {
					$this->checkAndUpdateCmd($online, '0');
				}
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
			$enable->setDisplay('icon','<i class="fas fa-play"></i>');
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
			$disable->setDisplay('icon','<i class="fas fa-stop"></i>');
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
				$newCommand->setName($trad);
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
		
		$order++;
		$online = $this->getCmd(null, 'online');
		if (!is_object($online)) {
			$online = new piHolecmd();
			$online->setLogicalId('online');
			$online->setIsVisible(1);
			$online->setOrder($order);
			$online->setName(__('Online', __FILE__));
		}
		$online->setType('info');
		$online->setSubType('binary');
		$online->setEqLogic_id($this->getId());
		$online->setDisplay('generic_type', 'ONLINE');
		$online->save();	

		$order++;
		$hasUpdatePiHole = $this->getCmd(null, 'hasUpdatePiHole');
		if (!is_object($hasUpdatePiHole)) {
			$hasUpdatePiHole = new piHolecmd();
			$hasUpdatePiHole->setLogicalId('hasUpdatePiHole');
			$hasUpdatePiHole->setIsVisible(1);
			$hasUpdatePiHole->setOrder($order);
			$hasUpdatePiHole->setName(__('Update PiHole Dispo', __FILE__));
		}
		$hasUpdatePiHole->setType('info');
		$hasUpdatePiHole->setSubType('binary');
		$hasUpdatePiHole->setEqLogic_id($this->getId());
		$hasUpdatePiHole->save();
		
		$order++;
		$hasUpdateWebInterface = $this->getCmd(null, 'hasUpdateWebInterface');
		if (!is_object($hasUpdateWebInterface)) {
			$hasUpdateWebInterface = new piHolecmd();
			$hasUpdateWebInterface->setLogicalId('hasUpdateWebInterface');
			$hasUpdateWebInterface->setIsVisible(1);
			$hasUpdateWebInterface->setOrder($order);
			$hasUpdateWebInterface->setName(__('Update InterfaceWeb Dispo', __FILE__));
		}
		$hasUpdateWebInterface->setType('info');
		$hasUpdateWebInterface->setSubType('binary');
		$hasUpdateWebInterface->setEqLogic_id($this->getId());
		$hasUpdateWebInterface->save();
		
		$order++;
		$hasUpdateFTL = $this->getCmd(null, 'hasUpdateFTL');
		if (!is_object($hasUpdateFTL)) {
			$hasUpdateFTL = new piHolecmd();
			$hasUpdateFTL->setLogicalId('hasUpdateFTL');
			$hasUpdateFTL->setIsVisible(1);
			$hasUpdateFTL->setOrder($order);
			$hasUpdateFTL->setName(__('Update FTL Dispo', __FILE__));
		}
		$hasUpdateFTL->setType('info');
		$hasUpdateFTL->setSubType('binary');
		$hasUpdateFTL->setEqLogic_id($this->getId());
		$hasUpdateFTL->save();
		
		$order++;
		$this->getpiHoleInfo(null,$order);
	}
}

class piHoleCmd extends cmd {
	/***************************Attributs*******************************/


	/*************************Methode static****************************/

	/***********************Methode d'instance**************************/
  	public function refresh() {
		$this->execute();
	    }
	
	public function execute($_options = null) {
		if ($this->getType() == '') {
			return '';
		}
		$eqLogic = $this->getEqlogic();
		$proto = $eqLogic->getConfiguration('proto','http');
		$ip = $eqLogic->getConfiguration('ip','');
		$apikey = $eqLogic->getConfiguration('apikey','');
		$logical = $this->getLogicalId();
		$result=null;
		if ($logical != 'refresh'){
			$urlpiHole = $proto.'://' . $ip . '/admin/api.php?status&summaryRaw';	
			switch ($logical) {
				case 'disable':
					$urlpiHole = $proto.'://' . $ip . '/admin/api.php?disable&auth='.$apikey;
				break;
				case 'enable':
					$urlpiHole = $proto.'://' . $ip . '/admin/api.php?enable&auth='.$apikey;
				break;
			}
			try{
				$request_http = new com_http($urlpiHole);
				$request_http->setNoSslCheck(true);
				$result=$request_http->exec(60,1);
				log::add('piHole','debug','Result:'.$result);
				$online = $eqLogic->getCmd(null, 'online');
				if (is_object($online)) {
					$eqLogic->checkAndUpdateCmd($online, '1');
				}
			}
			catch(Exception $e) {
				if($e->getCode() == "404") {
					$online = $eqLogic->getCmd(null, 'online');
					if (is_object($online)) {
						$eqLogic->checkAndUpdateCmd($online, '0');
					}
				}
				log::add('piHole','debug',__('piHole non joignable : ', __FILE__).$e->getCode());
			}
		}
		$eqLogic->getpiHoleInfo($result);
	}

	/************************Getteur Setteur****************************/
}
?>
