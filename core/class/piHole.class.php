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
			case "summary" :
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
			case "newStruct":
				return [	"queries"=>[
							"dns_queries_today"=>["key"=>"total","trad"=>__("Requêtes aujourd'hui", __FILE__)],
							"ads_blocked_today"=>["key"=>"blocked","trad"=>__("Publicités bloquées aujourd'hui", __FILE__)],
							"ads_percentage_today"=>["key"=>"percent_blocked","trad"=>__("Pourcentage publicités bloquées aujourd'hui", __FILE__)],
							"unique_domains"=>["key"=>"unique_domains","trad"=>__("Domaines uniques", __FILE__)],
							"queries_forwarded"=>["key"=>"forwarded","trad"=>__("Requêtes transmises", __FILE__)],
							"queries_cached"=>["key"=>"cached","trad"=>__("Requêtes en cache", __FILE__)]
						],
						"clients"=>[	
							"clients_ever_seen"=>["key"=>"total","trad"=>__("Clients vus", __FILE__)],
							"unique_clients"=>["key"=>"active","trad"=>__("Clients uniques", __FILE__)]
						],
						"gravity"=>[
							"domains_being_blocked"=>["key"=>"domains_being_blocked","trad"=>__("Domaines bloqués", __FILE__)],
							"gravity_last_updated"=>["key"=>"last_update","trad"=>__('Dernière mise à jour', __FILE__)]
						]
					];
			break;
		}		
	}

	public function piHoleAuth($proto,$ip,$apikey) {
		$sid=$this->getConfiguration('sid',null);
		
		// check auth
		$urlAuth = $proto.'://' . $ip . '/api/auth';
		$request_http = new com_http($urlAuth);
		$request_http->setNoSslCheck(true);
		if($sid) {$request_http->setHeader(["sid: $sid"]);}
		$piHoleAuth=$request_http->exec(60,1);
		log::add('piHole','debug',"CHECK AUTH:".$piHoleAuth);
		if(!$piHoleAuth) {
			throw new Exception("Cannot find $urlAuth for checking authentication status");
		}
		$jsonpiHole = json_decode($piHoleAuth,true);
		if(is_array($jsonpiHole)) {
			if(!isset($jsonpiHole['session']['valid']) || $jsonpiHole['session']['valid']==false) {
				//get sid
				$request_http = new com_http($urlAuth);
				$request_http->setNoSslCheck(true);
				$request_http->setPost(json_encode(["password"=>$apikey]));
				$piHoleAuth=$request_http->exec(60,1);
				log::add('piHole','debug',"AUTH:".$piHoleAuth." with ".json_encode(["password"=>$apikey]));
				if(!$piHoleAuth) {
					throw new Exception("Cannot find $urlAuth for authentication");
				}
				$jsonpiHole = json_decode($piHoleAuth,true);
				if (!is_array($jsonpiHole) || !isset($jsonpiHole['session']['sid'])) {
					throw new Exception("JSON received from $urlAuth is invalid");
				}
				log::add('piHole','debug',"AUTH OK SID:".$sid);
				$sid=$jsonpiHole['session']['sid'];
				$this->setConfiguration('sid',$sid);
				$this->save(true);
				return $sid;
			} else{
				if($jsonpiHole['session']['sid'] != null) {
					log::add('piHole','debug',"Session valid taking sid from cache:".$sid);
					return $sid;
				} else {
					log::add('piHole','debug',"Session valid no sid");
					$this->setConfiguration('sid',null);
					return null;
				}
			}
		} else {
			throw new Exception("CHECK AUTH JSON received from $urlAuth is invalid");
		}
	}
	
	public function getpiHoleInfo($order=null) {
		if(!$this->getIsEnable()) return;
		try {
			$proto = $this->getConfiguration('proto','http');
			$ip = $this->getConfiguration('ip','');
			$apikey = $this->getConfiguration('apikey','');
			$sid = $this->piHoleAuth($proto,$ip,$apikey);
				
			$urlBlocking = $proto.'://' . $ip . '/api/dns/blocking';
			$request_http = new com_http($urlBlocking);
			$request_http->setNoSslCheck(true);
			if($sid) {$request_http->setHeader(["sid: $sid"]);}
			$piHoleinfo=$request_http->exec(60,1);
			log::add('piHole','debug',"Request: ".$urlBlocking.' with header '.json_encode(["sid: $sid"]));
			log::add('piHole','debug',"Response: ".$piHoleinfo);
			$jsonpiHole = json_decode($piHoleinfo,true);

			$piHoleCmd = $this->getCmd(null, 'status');
			$this->checkAndUpdateCmd($piHoleCmd, (($jsonpiHole['blocking']=='enabled')?1:0));
			
			$urlSummary = $proto.'://' . $ip . '/api/stats/summary';
			$request_http = new com_http($urlSummary);
			$request_http->setNoSslCheck(true);
			if($sid) {$request_http->setHeader(["sid: $sid"]);}
			$piHoleinfo=$request_http->exec(60,1);
			log::add('piHole','debug',"Request Summary: ".$urlSummary.' with header '.json_encode(["sid: $sid"]));
			log::add('piHole','debug',"Response Summary: ".$piHoleinfo);
			$jsonpiHole = json_decode($piHoleinfo,true);
			
			$summary = piHole::getStructure('newStruct');

			foreach($summary as $keySummary => $obj) {
				foreach($obj as $id => $objContent) {
					$keyValue = $objContent['key'];
					$piHoleCmd = $this->getCmd(null, $id);
					if(is_object($piHoleCmd)) {
						if(strpos($id,'percentage') !== false) $jsonpiHole[$keySummary][$keyValue]=round($jsonpiHole[$keySummary][$keyValue],2);
						if(strpos($id,'gravity_last_updated') !== false) {
							$time=$jsonpiHole[$keySummary][$keyValue];
							$date=new DateTime("@$time");
							$jsonpiHole[$keySummary][$keyValue] = $date->format('d-m-Y H:i:s');
						}
						$this->checkAndUpdateCmd($piHoleCmd, $jsonpiHole[$keySummary][$keyValue]);
					}
				}
			}
			
			$urlprinter = $proto.'://' . $ip . '/api/info/version';
			$request_http = new com_http($urlprinter);
			$request_http->setNoSslCheck(true);
			if($sid) {$request_http->setHeader(["sid: $sid"]);}
			$piHoleVer=$request_http->exec(60,1);
			log::add('piHole','debug',__('recu version:', __FILE__).$piHoleVer);
			if($piHoleVer) {
				$jsonpiHoleVer = json_decode($piHoleVer,true);
				$piHoleCmd = $this->getCmd(null, 'hasUpdatePiHole');
				$this->checkAndUpdateCmd($piHoleCmd, version_compare($jsonpiHoleVer['version']['core']['local']['version'],$jsonpiHoleVer['version']['core']['remote']['version'],"<"));
				$piHoleCmd = $this->getCmd(null, 'hasUpdateWebInterface');
				$this->checkAndUpdateCmd($piHoleCmd, version_compare($jsonpiHoleVer['version']['web']['local']['version'],$jsonpiHoleVer['version']['web']['remote']['version'],"<"));
				$piHoleCmd = $this->getCmd(null, 'hasUpdateFTL');
				$this->checkAndUpdateCmd($piHoleCmd, version_compare($jsonpiHoleVer['version']['ftl']['local']['version'],$jsonpiHoleVer['version']['ftl']['remote']['version'],"<"));
			}
			
			$online = $this->getCmd(null, 'online');
			if (is_object($online)) {
				$this->checkAndUpdateCmd($online, '1');
			}
		} catch (Exception $e) {
			if((int) $e->getCode() === 404) {
				$online = $this->getCmd(null, 'online');
				if (is_object($online)) {
					$this->checkAndUpdateCmd($online, '0');
				}
			}
			log::add('piHole','error',$e->getMessage());
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

		$summary = piHole::getStructure('newStruct');

		foreach($summary as $keySummary => $obj) {
			foreach($obj as $id => $objContent) {
				$order++;
				$newCommand = $this->getCmd(null, $id);
				if(!is_object($newCommand)) {
					$newCommand = new piHolecmd();
					$newCommand->setLogicalId($id);
					$newCommand->setIsVisible(0);
					$newCommand->setOrder($order);
					$newCommand->setName($objContent['trad']);
				}
				$newCommand->setTemplate('dashboard', 'line');
				$newCommand->setTemplate('mobile', 'line');
				$newCommand->setType('info');
				if($id == 'gravity_last_updated') {
					$newCommand->setSubType('string');
				} else {
					$newCommand->setSubType('numeric');
				}
				$newCommand->setEqLogic_id($this->getId());
				$newCommand->setDisplay('generic_type', 'GENERIC_INFO');
				if($id == 'ads_percentage_today') $newCommand->setUnite( '%' );
				$newCommand->save();
			}
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
		
		$this->getpiHoleInfo();
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
		$sid = $eqLogic->piHoleAuth($proto,$ip,$apikey);
		$logical = $this->getLogicalId();
		$result=null;
		if ($logical != 'refresh'){
			
			switch ($logical) {
				case 'disable':
					$urlpiHole = $proto.'://' . $ip . '/api/dns/blocking';
                			$action = ["blocking"=>false,"timer"=>null];
				break;
				case 'enable':
					$urlpiHole = $proto.'://' . $ip . '/api/dns/blocking';
                			$action = ["blocking"=>true,"timer"=>null];
				break;
			}
			try{
				$request_http = new com_http($urlpiHole);
				$request_http->setNoSslCheck(true);
				if($sid) {$request_http->setHeader(["sid: $sid"]);}
				if($action) {$request_http->setPost(json_encode($action));}
				$result=$request_http->exec(60,1);
				log::add('piHole','debug','Result cmd '.$urlpiHole.' :'.$result);
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
		$eqLogic->getpiHoleInfo();
	}

	/************************Getteur Setteur****************************/
}
?>
